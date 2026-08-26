<?php
declare(strict_types=1);

/* Cron de IONOS: se llama periódicamente (cada 5-15 min, a elegir en el
   panel) para detectar cambios en datos_oficiales.json que no han pasado
   por el gestor ni por "npm run build" en local (p. ej. editado a mano
   directamente en el hosting) y regenerar index.html + los {idioma}.html.

   Configuración en el panel de IONOS — dos formas, usa la que tengas:
     a) Por CLI (recomendado si está disponible):
        php /ruta/absoluta/a/cron/reconstruir.php
     b) Por URL (si tu plan solo permite pedir una URL):
        https://superligafrontier.es/cron/reconstruir.php?token=EL_TOKEN_DE_config/secrets.php

   No hace nada (ni escribe en el log) si datos_oficiales.json no ha
   cambiado desde la última vez — así puede llamarse tan a menudo como se
   quiera sin generar ruido ni carga innecesaria. */

$root = dirname(__DIR__);
require_once __DIR__.'/render.php';

$datosPath = $root.'/datos_oficiales.json';
$hashFile = $root.'/_fuente/.last-datos-hash';
$logFile = $root.'/_fuente/cron.log';

function sf_cronLog(string $file, string $msg): void {
    @file_put_contents($file, '['.date('Y-m-d H:i:s').'] '.$msg."\n", FILE_APPEND);
}

if (PHP_SAPI !== 'cli') {
    $secrets = @include $root.'/config/secrets.php';
    $token = is_array($secrets) ? ($secrets['cron_token'] ?? null) : null;
    header('Content-Type: text/plain; charset=utf-8');
    if (!$token || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

if (!is_file($datosPath)) {
    sf_cronLog($logFile, 'ERROR: no existe datos_oficiales.json en '.$datosPath);
    exit;
}

$currentHash = md5_file($datosPath);
$lastHash = is_file($hashFile) ? trim((string)file_get_contents($hashFile)) : null;

if ($currentHash === $lastHash) {
    if (PHP_SAPI !== 'cli') echo "Sin cambios.\n";
    exit;
}

$raw = file_get_contents($datosPath);
$datos = $raw !== false ? json_decode($raw, true) : null;
if (!is_array($datos)) {
    sf_cronLog($logFile, 'ERROR: datos_oficiales.json no es JSON válido, no se toca nada.');
    if (PHP_SAPI !== 'cli') { http_response_code(500); echo "datos_oficiales.json inválido.\n"; }
    exit;
}

/* Vía preferida: si el hosting tiene Node.js accesible desde PHP, usar el
   pre-renderizador real (_fuente/build.js), que es idéntico a lo que hace un
   navegador y cubre también los 9 idiomas con traducción y transliteración
   completas — la reimplementación en render.php es solo la red de seguridad
   para cuando esto no está disponible (hosting compartido solo-PHP). */
$execFuncionaba = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
$usadoNode = false;
if ($execFuncionaba) {
    $version = null; $codigoVersion = 1;
    @exec('node --version 2>&1', $version, $codigoVersion);
    if ($codigoVersion === 0 && $version && str_starts_with(trim($version[0] ?? ''), 'v')) {
        // exec() (no shell_exec()) para poder comprobar el código de salida real:
        // si "node" existe pero falta node_modules/jsdom (p. ej. hay un Node del
        // sistema para otra cosa, sin nuestras dependencias), build.js termina con
        // error y NO hay que darlo por bueno ni quedarse sin la red de seguridad
        // de PHP — antes esto se detectaba solo con shell_exec() y se marcaba
        // "usado Node" pase lo que pase, aunque hubiera fallado.
        $salida = null; $codigo = 1;
        exec('cd '.escapeshellarg($root).' && node _fuente/build.js 2>&1', $salida, $codigo);
        $salidaTexto = implode("\n", $salida ?? []);
        if ($codigo === 0) {
            sf_cronLog($logFile, "Reconstruido con Node.js (".$version[0]."):\n".$salidaTexto);
            $usadoNode = true;
        } else {
            sf_cronLog($logFile, "AVISO: node _fuente/build.js falló (código $codigo), se usa la reserva en PHP. Salida:\n".$salidaTexto);
        }
    }
}

if (!$usadoNode) {
    $archivos = array_merge(['index.html'], array_map(fn($l) => "$l.html", ['en', 'pt', 'it', 'fr', 'ja', 'ko', 'pl', 'bg', 'sr']));
    $actualizados = [];
    foreach ($archivos as $f) {
        $path = $root.'/'.$f;
        if (!is_file($path)) continue;
        if (sf_actualizarTablas($path, $datos)) $actualizados[] = $f;
    }
    sf_cronLog($logFile, 'Node.js no disponible en este hosting: clasificación/resultados/goleadores/Copa actualizados con PHP puro en: '.implode(', ', $actualizados).'. Los textos de cada idioma y la transliteración de nombres NO se han regenerado (necesitan Node) — seguirán siendo los del último "npm run build" en local.');
}

file_put_contents($hashFile, $currentHash);
if (PHP_SAPI !== 'cli') echo "Hecho (".($usadoNode ? 'Node' : 'PHP').").\n";
