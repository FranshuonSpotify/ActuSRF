<?php
// dashboard/tests/test_traduccion_mercado.php
// Self-check del paso 17: pegado, cláusulas y mercado pintados en los diez
// idiomas, con los errores por línea del pegado y los nombres de equipo.
//   /c/xampp/php/php.exe dashboard/tests/test_traduccion_mercado.php
//
// Cada pantalla se recorre en la fase en la que es editable, que es donde
// aparecen los textos que no se ven en solo lectura (errores, botones).

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$DIR = __DIR__ . '/..';
$T   = '2026-27';

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'nombre_en' => 'Alpha', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'nombre_en' => 'Beta FC', 'activo' => true],
    ['id' => 'eq_c', 'nombre' => 'Gamma', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');

$j = static fn(string $id, string $nombre, int $clausula = 0, string $estado = 'DISPONIBLE', ?string $por = null)
    => ['id' => $id, 'nombre' => $nombre, 'posicion' => 'MED', 'tier' => 'B', 'salario' => 6,
        'clausula' => $clausula, 'estado' => $estado, 'clausuladoPor' => $por, 'clausuladoEn' => null];
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => [$j('a1', 'Endou'), $j('a2', 'Kazemaru')]], 0);
plGuardarEquipoTemporada($T, 'eq_b', ['jugadores' => [$j('b1', 'Goenji', 100)]], 0);
plGuardarEquipoTemporada($T, 'eq_c', ['jugadores' => [$j('c1', 'Ya Vendido', 50, 'CLAUSULADO', 'eq_b')]], 0);

$sinClaves     = static fn(string $h) => !preg_match('/\[[a-z_]+\.[a-z_]+\]/', $h);
// Los data-txt-* de cláusulas llevan el {cifra} a propósito: son la plantilla
// que el contador en vivo rellena en JS (y el <script> lo nombra al hacer el
// replace). Fuera de ahí, ningún marcador.
$sinMarcadores = static fn(string $h) => !preg_match('/\{[a-zA-Z]+\}/',
    preg_replace(['/data-txt-[a-z]+="[^"]*"/', '/<script\b.*?<\/script>/s'], '', $h));
$prefijo = static function (string $clave): string {
    $t = plT($clave);
    $p = strpos($t, '{');
    return plEsc(rtrim($p === false ? $t : substr($t, 0, $p)));
};
// Pide la pantalla con el idioma en sesión y deja ese idioma activo para que
// las cadenas esperadas del test salgan en el mismo idioma que el HTML.
$pedir = static function (string $pantalla, string $idioma, array $get = [], array $post = []): array {
    $r = plArnesPeticion($GLOBALS['DIR'] . "/$pantalla",
        ['pl_usuario_id' => 'u_a', 'pl_csrf' => 'tok', 'pl_lang' => $idioma], $get, $post);
    plEstablecerIdioma($idioma);
    return $r;
};

// ========================================================== pegado (ROSTER)
foreach (PL_IDIOMAS as $idioma) {
    $r = $pedir('pegado.php', $idioma);
    $h = $r['html'] ?? '';
    plVerificar("[$idioma] pegado se pinta con <html lang>", $r['tipo'] === 'html' && str_contains($h, '<html lang="' . $idioma . '"'));
    plVerificar("[$idioma] explicación y botón de previsualizar traducidos",
        str_contains($h, plEsc(plT('pegado.explicacion'))) && str_contains($h, plEsc(plT('pegado.previsualizar'))));
    plVerificar("[$idioma] pegado no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));

    $r = $pedir('pegado.php', $idioma, [],
        ['csrf' => 'tok', 'accion' => 'previsualizar', 'rev' => 1, 'texto' => "Nuevo;POR;S\nMalo;DEL;S\nSolo dos;MED"]);
    $h = $r['html'] ?? '';
    plVerificar("[$idioma] los errores por línea salen traducidos",
        $r['tipo'] === 'html'
        && str_contains($h, plEsc(plT('error.posicion_invalida')))
        && str_contains($h, plEsc(plT('pegado.error_campos'))));
    plVerificar("[$idioma] y el aviso de lote con su cifra", str_contains($h, $prefijo('pegado.errores_lineas')) || str_contains($h, '2'));
    plVerificar("[$idioma] la previsualización no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));
}

// ====================================================== cláusulas (CLAUSULAS)
plCambiarFase($T, 'CLAUSULAS');
foreach (PL_IDIOMAS as $idioma) {
    $r = $pedir('clausulas.php', $idioma);
    $h = $r['html'] ?? '';
    plVerificar("[$idioma] cláusulas se pinta con <html lang>", $r['tipo'] === 'html' && str_contains($h, '<html lang="' . $idioma . '"'));
    plVerificar("[$idioma] presupuesto, botón y nota de borrador traducidos",
        str_contains($h, plEsc(plT('clausulas.presupuesto')))
        && str_contains($h, plEsc(plT('clausulas.guardar')))
        && str_contains($h, plEsc(plT('clausulas.nota_borrador'))));
    plVerificar("[$idioma] cada campo con su etiqueta traducida y el nombre del jugador",
        str_contains($h, plEsc(plT('clausulas.campo_de', ['jugador' => 'Kazemaru']))));
    plVerificar("[$idioma] cláusulas no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));

    $r = $pedir('clausulas.php', $idioma, [], ['csrf' => 'tok', 'rev' => 1, 'clausula' => ['a1' => 600, 'a2' => 100]]);
    $h = $r['html'] ?? '';
    plVerificar("[$idioma] pasarse de 650M da el error traducido con sus cifras",
        $r['tipo'] === 'html' && str_contains($h, $prefijo('error.clausulas_excedidas'))
        && str_contains($h, '650M') && str_contains($h, '50M') && $sinMarcadores($h));
}
plVerificar('ningún intento fallido escribió cláusulas',
    array_column(plEquipoEnTemporada($T, 'eq_a')['jugadores'], 'clausula') === [0, 0]);

// ========================================================== mercado (MERCADO)
plCambiarFase($T, 'MERCADO');
foreach (PL_IDIOMAS as $idioma) {
    $nombreB = $idioma === 'en' ? 'Beta FC' : 'Beta';
    $r = $pedir('mercado.php', $idioma);
    $h = $r['html'] ?? '';
    plVerificar("[$idioma] mercado se pinta con <html lang>", $r['tipo'] === 'html' && str_contains($h, '<html lang="' . $idioma . '"'));
    plVerificar("[$idioma] nota, aviso de fase y botón de marcar traducidos",
        str_contains($h, plEsc(plT('mercado.nota')))
        && str_contains($h, plEsc(plT('aviso.mercado_abierto')))
        && str_contains($h, plEsc(plT('mercado.marcar'))));
    plVerificar("[$idioma] el equipo comprador en el estado, con su nombre de este idioma",
        str_contains($h, plEsc(plT('estado.clausulado_por', ['equipo' => $nombreB]))));
    plVerificar("[$idioma] un equipo sin nombre_en sale con su nombre, nunca vacío", str_contains($h, 'Gamma'));
    plVerificar("[$idioma] mercado no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));

    $r = $pedir('mercado.php', $idioma, ['confirmar' => 'b1', 'equipo' => 'eq_b']);
    $h = $r['html'] ?? '';
    $miEquipo = $idioma === 'en' ? 'Alpha' : 'Alfa';
    plVerificar("[$idioma] la confirmación nombra a los dos equipos y al jugador",
        str_contains($h, plEsc(plT('mercado.confirmar_texto', ['miEquipo' => $miEquipo, 'jugador' => 'Goenji', 'suEquipo' => $nombreB]))));
    plVerificar("[$idioma] la confirmación no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));
}
plVerificar('en inglés el nombre en español de Beta no aparece en el mercado',
    !str_contains($pedir('mercado.php', 'en')['html'] ?? '', '>Beta<'));

// ================================================================ admin
// El admin va en español a propósito: ninguna pantalla admin*.php traduce.
$conPlT = array_filter(glob($DIR . '/admin*.php') ?: [], static fn($f) => str_contains((string) file_get_contents($f), 'plT('));
plVerificar('ninguna pantalla admin*.php llama a plT()', count(glob($DIR . '/admin*.php') ?: []) >= 5 && $conPlT === []);

plEstablecerIdioma('es');
plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
