<?php
// dashboard/tests/test_traduccion.php
// Self-check del paso 16: el envoltorio, el login, el dashboard y Mi plantilla
// pintados de verdad en los diez idiomas.
//   /c/xampp/php/php.exe dashboard/tests/test_traduccion.php
//
// test_i18n.php mira el diccionario; este mira el HTML. Lo que caza aquí y no
// allí: una pantalla que escribe un literal en vez de llamar a plT(), una
// clave mal escrita que sale como [clave], o un marcador que la pantalla no
// sustituye y llega al presidente como {cap}.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$INDEX     = __DIR__ . '/../index.php';
$PLANTILLA = __DIR__ . '/../plantilla.php';
$T         = '2026-27';

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'nombre_en' => 'Alpha', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
    ['id' => 'u_b', 'nombre' => 'Ana',  'email' => 'b@x.es', 'hash' => 'x', 'equipoId' => 'eq_b', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');

$jugador = static fn(string $id, string $tier, int $salario)
    => ['id' => $id, 'nombre' => "Jugador $id", 'posicion' => 'MED', 'tier' => $tier, 'salario' => $salario,
        'clausula' => 0, 'estado' => 'DISPONIBLE', 'clausuladoPor' => null, 'clausuladoEn' => null];

// Alfa: tres S++ = 225M, así un S++ más (300M) choca con el Salary Cap.
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => [
    $jugador('a1', 'S++', 75), $jugador('a2', 'S++', 75), $jugador('a3', 'S++', 75),
]], 0);
// Beta: veinte C = 40M, así un jugador más choca con el límite de 20.
$veinte = [];
for ($i = 1; $i <= 20; $i++) {
    $veinte[] = $jugador("b$i", 'C', 2);
}
plGuardarEquipoTemporada($T, 'eq_b', ['jugadores' => $veinte], 0);

// Una clave sin resolver sale como [seccion.clave]; un marcador sin sustituir,
// como {nombre}. Ninguno de los dos debe llegar nunca al HTML.
$sinClaves     = static fn(string $h) => !preg_match('/\[[a-z_]+\.[a-z_]+\]/', $h);
$sinMarcadores = static fn(string $h) => !preg_match('/\{[a-zA-Z]+\}/', $h);

// Todo lo que va antes del primer marcador: basta para saber que la frase está
// en el idioma correcto sin depender de cómo formatea la pantalla las cifras.
$prefijo = static function (string $clave): string {
    $t = plT($clave);
    $p = strpos($t, '{');
    return plEsc(rtrim($p === false ? $t : substr($t, 0, $p)));
};

foreach (PL_IDIOMAS as $idioma) {
    // -- login ---------------------------------------------------------------
    $r = plArnesPeticion($INDEX, [], ['lang' => $idioma]);
    $h = $r['html'] ?? '';
    plEstablecerIdioma($idioma);
    plVerificar("[$idioma] el login se pinta", $r['tipo'] === 'html' && str_contains($h, 'name="email"'));
    plVerificar("[$idioma] con <html lang=\"$idioma\">", str_contains($h, '<html lang="' . $idioma . '"'));
    plVerificar("[$idioma] título, campos y botón del login traducidos",
        str_contains($h, plEsc(plT('login.titulo')))
        && str_contains($h, plEsc(plT('login.campo_clave')))
        && str_contains($h, plEsc(plT('login.boton_entrar'))));
    plVerificar("[$idioma] el login no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));

    // -- selector de idioma --------------------------------------------------
    preg_match('/<nav class="idiomas".*?<\/nav>/s', $h, $m);
    $selector = $m[0] ?? '';
    plVerificar("[$idioma] el selector ofrece los diez idiomas", substr_count($selector, '<img') === 10);
    plVerificar("[$idioma] marca el activo, y solo ese, con aria-current",
        substr_count($selector, 'aria-current') === 1
        && (bool) preg_match('/<a [^>]*lang="' . $idioma . '"[^>]*aria-current="true"/', $selector));
    plVerificar("[$idioma] cada bandera tiene alt con el nombre del idioma",
        !preg_match('/alt=""/', $selector)
        && str_contains($selector, 'alt="' . plEsc(PL_NOMBRES_IDIOMA[$idioma]) . '"'));

    // -- dashboard -----------------------------------------------------------
    $sesion = ['pl_usuario_id' => 'u_a', 'pl_csrf' => 'tok', 'pl_lang' => $idioma];
    $r = plArnesPeticion($INDEX, $sesion);
    $h = $r['html'] ?? '';
    plEstablecerIdioma($idioma);
    plVerificar("[$idioma] el dashboard se pinta con <html lang>", $r['tipo'] === 'html' && str_contains($h, '<html lang="' . $idioma . '"'));
    plVerificar("[$idioma] navegación y tarjetas traducidas",
        str_contains($h, plEsc(plT('nav.plantilla')))
        && str_contains($h, plEsc(plT('nav.saltar')))
        && str_contains($h, plEsc(plT('dash.tarjeta_plantilla'))));
    plVerificar("[$idioma] el dashboard no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));
    plVerificar("[$idioma] el nombre del equipo: nombre_en solo en inglés",
        str_contains($h, $idioma === 'en' ? 'Alpha' : 'Alfa'));

    // -- Mi plantilla --------------------------------------------------------
    $r = plArnesPeticion($PLANTILLA, $sesion);
    $h = $r['html'] ?? '';
    plEstablecerIdioma($idioma);
    plVerificar("[$idioma] Mi plantilla se pinta con <html lang>", $r['tipo'] === 'html' && str_contains($h, '<html lang="' . $idioma . '"'));
    plVerificar("[$idioma] formulario de alta traducido",
        str_contains($h, plEsc(plT('plantilla.anadir')))
        && str_contains($h, plEsc(plT('plantilla.campo_posicion')))
        && str_contains($h, plEsc(plT('plantilla.salario_auto'))));
    plVerificar("[$idioma] Mi plantilla no deja ninguna [clave] ni {marcador}", $sinClaves($h) && $sinMarcadores($h));
    plVerificar("[$idioma] la posición se pinta con su abreviatura real, no una traducción literal del código",
        str_contains($h, plEsc(plPosicionTexto('MED'))));

    // -- error de Salary Cap -------------------------------------------------
    $r = plArnesPeticion($PLANTILLA, $sesion, [],
        ['accion' => 'anadir', 'rev' => 1, 'nombre' => 'Otro', 'posicion' => 'ATA', 'tier' => 'S++', 'csrf' => 'tok']);
    $h = $r['html'] ?? '';
    plEstablecerIdioma($idioma);
    plVerificar("[$idioma] el error de cap sale traducido",
        $r['tipo'] === 'html' && str_contains($h, $prefijo('error.cap_superado')));
    plVerificar("[$idioma] con las cifras reales: 250, 300 y 25",
        str_contains($h, '250M') && str_contains($h, '300M') && str_contains($h, '25M'));
    plVerificar("[$idioma] y sin ningún {marcador} suelto", $sinMarcadores($h));

    // -- error del límite de 20 ----------------------------------------------
    $r = plArnesPeticion($PLANTILLA, ['pl_usuario_id' => 'u_b', 'pl_csrf' => 'tok', 'pl_lang' => $idioma], [],
        ['accion' => 'anadir', 'rev' => 1, 'nombre' => 'Veintiuno', 'posicion' => 'DEF', 'tier' => 'C', 'csrf' => 'tok']);
    $h = $r['html'] ?? '';
    plEstablecerIdioma($idioma);
    plVerificar("[$idioma] el error del límite de 20 sale traducido",
        $r['tipo'] === 'html' && str_contains($h, $prefijo('error.max_jugadores')));
    plVerificar("[$idioma] con sus cifras y sin marcadores", str_contains($h, '20') && $sinMarcadores($h));
}

plVerificar('ningún intento fallido escribió: Alfa sigue con 3 y Beta con 20',
    count(plEquipoEnTemporada($T, 'eq_a')['jugadores']) === 3
    && count(plEquipoEnTemporada($T, 'eq_b')['jugadores']) === 20);

plEstablecerIdioma('es');
plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
