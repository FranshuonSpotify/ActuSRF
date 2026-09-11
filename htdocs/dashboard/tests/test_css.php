<?php
// dashboard/tests/test_css.php
// Self-check del paso 18: la hoja propia y lo que se puede comprobar de la
// maquetación sin navegador.
//   /c/xampp/php/php.exe dashboard/tests/test_css.php
//
// Lo que NO cubre, y está en DESPLIEGUE.md como comprobación manual: el
// render real a 375 px, el recorrido con tabulador y el 403 de data/. Aquí se
// cubre lo que suele romperlos: una clase sin definir, un token inventado, una
// tabla fuera de su contenedor con scroll o un estilo en línea.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$DIR     = realpath(__DIR__ . '/..');
$css     = (string) @file_get_contents("$DIR/css/dashboard.css");
$publica = (string) @file_get_contents("$DIR/../_fuente/styles.css");
$pantallas = glob("$DIR/*.php") ?: [];
$pantallas = array_values(array_filter($pantallas, static fn($f) => !in_array(basename($f),
    ['lib.php', 'dominio.php', 'almacen.php', 'i18n.php', 'logout.php'], true)));

plVerificar('existe css/dashboard.css', $css !== '');
plVerificar('y se lee _fuente/styles.css para contrastar', $publica !== '');

// -- sintaxis mínima --------------------------------------------------------
$sinComentarios = (string) preg_replace('#/\*.*?\*/#s', '', $css);
plVerificar('llaves equilibradas', substr_count($sinComentarios, '{') === substr_count($sinComentarios, '}'));

// -- tokens: ninguno inventado ---------------------------------------------
preg_match_all('/var\(\s*(--[a-z0-9-]+)/', $css, $m);
preg_match_all('/(--[a-z0-9-]+)\s*:/', $publica, $d);
$sinDefinir = array_values(array_diff(array_unique($m[1]), $d[1]));
plVerificar('todo var(--…) está definido en _fuente/styles.css' . ($sinDefinir ? ' (falta: ' . implode(', ', $sinDefinir) . ')' : ''),
    $sinDefinir === []);
preg_match_all('/(--[a-z0-9-]+)\s*:/', $sinComentarios, $propios);
plVerificar('dashboard.css no declara tokens propios', $propios[1] === []);

// -- colores literales: solo los de §7 y los de p.mal -------------------------
$permitidos = [
    'rgba(255,59,59,.14)',                                   // .chip-ata (§7)
    'rgba(62,123,255,.13)', 'rgba(255,201,74,.13)',          // .fase-* (§7)
    'rgba(70,180,95,.13)', 'rgba(255,255,255,.05)',
    '#FF8A7A', 'rgba(255,59,59,.1)', 'rgba(255,59,59,.25)',  // p.mal, replicado de supertecnicas.css
];
$sinSvg = (string) preg_replace('/url\("data:[^"]*"\)/', '', $sinComentarios);
preg_match_all('/#[0-9A-Fa-f]{3,8}\b|rgba?\([^)]*\)/', $sinSvg, $c);
$fuera = array_values(array_diff(array_unique($c[0]), $permitidos));
plVerificar('ningún color literal fuera de §7 y de p.mal' . ($fuera ? ' (sobran: ' . implode(', ', $fuera) . ')' : ''), $fuera === []);

// -- lo nuevo de §7 ---------------------------------------------------------
foreach (['.chip-ata', '.fase-roster', '.fase-clausulas', '.fase-mercado', '.fase-cerrada',
          '.barra-parcial', '.barra-completa', '.barra-excedida'] as $sel) {
    plVerificar("define $sel", str_contains($css, $sel));
}
plVerificar('.tabla-scroll desplaza en horizontal dentro de su caja',
    (bool) preg_match('/\.tabla-scroll\s*\{[^}]*overflow-x\s*:\s*auto/', $css));
// Sin esto, los .sr-only (position:absolute) de las celdas se salen de la caja
// y ensanchan la página: se midió a 375 px, 525 px de ancho en el mercado.
plVerificar('.tabla-scroll es un ancestro posicionado',
    (bool) preg_match('/\.tabla-scroll\s*\{[^}]*position\s*:\s*relative/', $css));
plVerificar('hay una regla para móvil', (bool) preg_match('/@media\s*\(max-width\s*:\s*\d+px\)/', $css));
plVerificar('respeta prefers-reduced-motion', str_contains($css, 'prefers-reduced-motion'));
plVerificar('no oculta el foco: el único outline:none es el de .inp, que lo sustituye por halo',
    substr_count($sinComentarios, 'outline:none') === 1
    && (bool) preg_match('/\.inp:focus\s*\{[^}]*outline:none[^}]*box-shadow/', $sinComentarios));

// -- todas las clases usadas existen -----------------------------------------
// Se leen los class="…" de las pantallas quitando los trozos PHP; las clases
// que se componen en PHP (fase-*, chip-*, barra-*) ya se comprobaron arriba.
$definidas = static fn(string $clase) => (bool) preg_match('/\.' . preg_quote($clase, '/') . '(?![a-zA-Z0-9_-])/', $css . $publica);
$usadas = [];
foreach ($pantallas as $f) {
    $src = (string) file_get_contents($f);
    preg_match_all('/class="([^"]*)"/', $src, $mm);
    foreach ($mm[1] as $valor) {
        $valor = (string) preg_replace('/<\?.*?\?>/s', ' ', $valor);
        foreach (preg_split('/\s+/', trim($valor)) ?: [] as $clase) {
            if (preg_match('/^[a-z][a-z0-9-]*$/', $clase)) {
                $usadas[$clase][] = basename($f);
            }
        }
    }
}
// Clases que se ponen desde PHP sin class="…" literal, más las del selector
// de idioma, que vive en i18n.php (fuera de la lista de pantallas).
foreach (['archivado', 'clausulado', 'con-error', 'propio',
          'idioma', 'lang-btn', 'lang-item', 'active', 'lang-flag-circle', 'lang-item-code', 'lang-item-name'] as $extra) {
    $usadas[$extra][] = '(PHP)';
}
// Ganchos semánticos que no necesitan estilo propio (el contenedor ya maqueta).
$ganchos = ['dash-presupuesto', 'dash-filtros'];
$huerfanas = array_keys(array_filter($usadas, static fn($_, $clase)
    => !str_ends_with($clase, '-') && !in_array($clase, $ganchos, true) && !$definidas($clase), ARRAY_FILTER_USE_BOTH));
plVerificar('toda clase usada en una pantalla está definida' . ($huerfanas ? ' (sin definir: ' . implode(', ', $huerfanas) . ')' : ''),
    $huerfanas === []);

// -- marcado --------------------------------------------------------------
$enLinea = [];
foreach ($pantallas as $f) {
    foreach (file($f) ?: [] as $n => $linea) {
        // La única excepción: el ancho de la barra, que es un dato, no un estilo.
        if (str_contains($linea, 'style=') && !str_contains($linea, 'style="width:<?= plEsc($porcentaje) ?>%"')) {
            $enLinea[] = basename($f) . ':' . ($n + 1);
        }
    }
}
plVerificar('sin estilos en línea en las pantallas' . ($enLinea ? ' (' . implode(', ', $enLinea) . ')' : ''), $enLinea === []);

$sueltas = [];
foreach ($pantallas as $f) {
    $src = (string) file_get_contents($f);
    if (substr_count($src, '<table') !== substr_count($src, 'class="tabla-scroll"')) {
        $sueltas[] = basename($f);
    }
}
plVerificar('cada tabla va dentro de su .tabla-scroll' . ($sueltas ? ' (' . implode(', ', $sueltas) . ')' : ''), $sueltas === []);

$formsVacios = [];
foreach ($pantallas as $f) {
    foreach (file($f) ?: [] as $n => $linea) {
        if (str_contains($linea, '<form id="f-') && !str_contains($linea, ' hidden>')) {
            $formsVacios[] = basename($f) . ':' . ($n + 1);
        }
    }
}
plVerificar('los formularios de fila, que solo llevan campos ocultos, van con hidden' . ($formsVacios ? ' (' . implode(', ', $formsVacios) . ')' : ''),
    $formsVacios === []);

// -- el <head> pintado --------------------------------------------------------
$h = plArnesPeticion("$DIR/index.php", [])['html'] ?? '';
$posPublica = strpos($h, 'href="../_fuente/styles.css"');
$posPropia  = strpos($h, 'href="css/dashboard.css"');
plVerificar('la pantalla enlaza las dos hojas', $posPublica !== false && $posPropia !== false);
plVerificar('primero la pública y después la propia, para que la propia pueda pisarla',
    $posPublica !== false && $posPropia !== false && $posPublica < $posPropia);
plVerificar('con el viewport para móvil', str_contains($h, 'name="viewport" content="width=device-width, initial-scale=1"'));
plVerificar('y el skip-link como primer elemento del body',
    (bool) preg_match('/<body>\s*<a class="skip-link" href="#contenido">/', $h));

// -- DESPLIEGUE.md --------------------------------------------------------
$desp = (string) @file_get_contents("$DIR/DESPLIEGUE.md");
plVerificar('existe DESPLIEGUE.md', $desp !== '');
plVerificar('con los cinco puntos numerados', (bool) preg_match('/^1\. .*^2\. .*^3\. .*^4\. .*^5\. /ms', $desp));
plVerificar('punto de PHP 8.3 o 8.4', str_contains($desp, '8.3') && str_contains($desp, '8.4'));
plVerificar('punto de las dos claves en config/secrets.php',
    str_contains($desp, 'admin_dashboard_user') && str_contains($desp, 'admin_dashboard_pass_hash'));
plVerificar('punto del permiso de escritura en data/', str_contains($desp, 'escritura') && str_contains($desp, 'dashboard/data/'));
plVerificar('punto del 403 de data/ por URL', str_contains($desp, '403') && str_contains($desp, 'dashboard/data/equipos.json'));
plVerificar('punto de la prueba de humo', stripos($desp, 'humo') !== false);

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
