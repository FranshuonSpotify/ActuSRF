<?php
// lesiones/lib.php
// Fontanería del subproyecto de lesiones: JSON atómico, CSRF, escapado.
// Mismo patrón que dashboard/lib.php, prefijo `le` en vez de `pl`. No sabe
// nada de equipos, ruletas ni jugadores: eso vive en dominio.php y almacen.php.

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die('lesiones/ requiere PHP 8.1.0 o superior. Versión actual: ' . PHP_VERSION);
}

function leCargarJson(string $ruta, array $porDefecto): array
{
    if (!file_exists($ruta)) {
        return $porDefecto;
    }
    $lock = fopen($ruta . '.lock', 'c');
    if ($lock === false) {
        return leLeerJson($ruta, $porDefecto);
    }
    flock($lock, LOCK_SH);
    $data = leLeerJson($ruta, $porDefecto);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $data;
}

function leLeerJson(string $ruta, array $porDefecto): array
{
    if (!file_exists($ruta)) {
        return $porDefecto;
    }
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        return $porDefecto;
    }
    $data = json_decode($contenido, true);
    return is_array($data) ? $data : $porDefecto;
}

function leGuardarJsonAtomico(string $ruta, array $data): bool
{
    $lock = leTomarLock($ruta);
    if ($lock === null) {
        return false;
    }
    $ok = leEscribirJsonSinLock($ruta, $data);
    leSoltarLock($lock);
    return $ok;
}

function leActualizarJson(string $ruta, array $porDefecto, callable $cambio): bool
{
    $lock = leTomarLock($ruta);
    if ($lock === null) {
        return false;
    }
    $nuevo = $cambio(leLeerJson($ruta, $porDefecto));
    $ok    = $nuevo === null ? true : leEscribirJsonSinLock($ruta, $nuevo);
    leSoltarLock($lock);
    return $ok;
}

function leTomarLock(string $ruta)
{
    $directorio = dirname($ruta);
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        return null;
    }
    $lock = fopen($ruta . '.lock', 'c');
    if ($lock === false) {
        return null;
    }
    flock($lock, LOCK_EX);
    return $lock;
}

function leSoltarLock($lock): void
{
    flock($lock, LOCK_UN);
    fclose($lock);
}

function leEscribirJsonSinLock(string $ruta, array $data): bool
{
    $ok  = false;
    $tmp = tempnam(dirname($ruta), 'le_');
    if ($tmp !== false) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ok = $json !== false
            && file_put_contents($tmp, $json) !== false
            && chmod($tmp, 0644)
            && rename($tmp, $ruta);
        if (!$ok && file_exists($tmp)) {
            unlink($tmp);
        }
    }
    return $ok;
}

function leEsc($t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

function leTokenCsrf(): string
{
    if (empty($_SESSION['le_csrf'])) {
        $_SESSION['le_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['le_csrf'];
}

function leCsrfValido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return !empty($_SESSION['le_csrf'])
        && is_string($enviado)
        && hash_equals($_SESSION['le_csrf'], $enviado);
}

function leAhora(): string
{
    return date('c');
}

function leRedirigir(string $url): never
{
    if (!empty($GLOBALS['LE_ARNES'])) {
        throw new RuntimeException('LE_REDIRIGIR:' . $url);
    }
    header('Location: ' . $url);
    exit;
}

function leCortar(int $codigo, string $mensaje): never
{
    if (!empty($GLOBALS['LE_ARNES'])) {
        throw new RuntimeException('LE_CORTAR:' . $codigo . ':' . $mensaje);
    }
    http_response_code($codigo);
    exit($mensaje);
}

// Igual que leCortar, pero para respuestas AJAX en JSON: fija el código, la
// cabecera y el cuerpo en un solo sitio para que ninguna pantalla necesite un
// header()+echo+exit a pelo, que rompería el arnés de tests (no es
// capturable como excepción).
function leResponderJson(int $codigo, array $datos): never
{
    if (!empty($GLOBALS['LE_ARNES'])) {
        throw new RuntimeException('LE_JSON:' . $codigo . ':' . json_encode($datos, JSON_UNESCAPED_UNICODE));
    }
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Mismo comportamiento que plNormalizarTexto() de dashboard/lib.php: los dos
// subproyectos son independientes (no hay include entre ellos), así que se
// copia en vez de compartirse. Sirve para comparar "Épsilon" con "epsilon" o
// "Jugador Uno" con "jugador   uno" en el buscador de la vista pública.
function leNormalizarTexto($texto): string
{
    $acentos = ['á','é','í','ó','ú','ñ','ü','Á','É','Í','Ó','Ú','Ñ','Ü',
                'à','è','ì','ò','ù','À','È','Ì','Ò','Ù',
                'â','ê','î','ô','û','Â','Ê','Î','Ô','Û',
                'ä','ë','ï','ö','Ä','Ë','Ï','Ö'];
    $planos  = ['a','e','i','o','u','n','u','A','E','I','O','U','N','U',
                'a','e','i','o','u','A','E','I','O','U',
                'a','e','i','o','u','A','E','I','O','U',
                'a','e','i','o','A','E','I','O'];
    $texto = str_replace($acentos, $planos, (string) $texto);
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim((string) $texto);
}

// Fecha ISO-8601 (como la que escribe leAhora()) a d/m/Y para mostrarla en la
// tabla de historial. Sin lanzar ni avisar: una fecha rota en el JSON no debe
// tumbar la vista pública, solo dejar la celda vacía.
function leFormatearFecha(string $iso): string
{
    if ($iso === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($iso))->format('d/m/Y');
    } catch (Exception $e) {
        return '';
    }
}
