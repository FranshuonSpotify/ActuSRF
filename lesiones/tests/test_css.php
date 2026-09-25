<?php
// lesiones/tests/test_css.php
// Comprueba que lesiones.css no reintroduce colores hexadecimales sueltos
// fuera de los fallbacks documentados (mismo criterio que dashboard/tests/test_css.php).
// Ejecutar: /c/xampp/php/php.exe lesiones/tests/test_css.php

require_once __DIR__ . '/arnes.php';

$css = file_get_contents(__DIR__ . '/../css/lesiones.css');
leVerificar('el fichero existe y no está vacío', is_string($css) && trim($css) !== '');

// Solo se permiten los hex de respaldo de var(--x, #hex), documentados en el
// propio fichero (gold, c-copa, bg). Cualquier otro hex fuera de un
// fallback de var() es un color colado sin pasar por tokens.
preg_match_all('/#[0-9a-fA-F]{3,6}\b/', $css, $todos);
$permitidos = ['#FFC94A', '#FF3B3B', '#000'];
$sueltos = array_diff(array_unique($todos[0]), $permitidos);
leVerificar('no hay colores hex fuera de los fallbacks de var() documentados: ' . implode(',', $sueltos), $sueltos === []);

// Guarda contra choques de nombre con las hojas que se cargan antes: si una
// clase de aquí ya existe como selector en _fuente/styles.css o en
// dashboard/css/dashboard.css, el navegador aplica las dos reglas al mismo
// elemento (pasó con ".modal", que en dashboard.css es un <dialog> nativo).
function leClasesDeSelectores(string $codigoCss): array {
    // Quita comentarios primero: pueden mencionar ".algo" en prosa sin ser selectores.
    $sinComentarios = preg_replace('#/\*.*?\*/#s', '', $codigoCss);
    // Captura el texto que precede a cada bloque {...} más interno (sin llaves
    // dentro). Para ".a{...}" suelto es directamente su selector; para
    // "@media (...){ .a{...} }" el preludio "@media (...)" nunca casa porque
    // su bloque contiene llaves anidadas, así que solo se captura " .a" — esto
    // es lo que hace que una clase definida solo dentro de un @media (como
    // .hide-sm) no se escape de la comprobación. Un decimal como ".35" o
    // ".75rem" vive dentro de un bloque de declaración, nunca antes de "{",
    // así que el extractor de clases de abajo no lo toca.
    preg_match_all('/([^{}]+)\{[^{}]*\}/', $sinComentarios, $bloques);
    $selectores = implode(' ', $bloques[1]);
    preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $selectores, $m);
    return array_unique($m[1]);
}

$clasesLesiones = leClasesDeSelectores($css);
$clasesFuente = leClasesDeSelectores((string) file_get_contents(__DIR__ . '/../../_fuente/styles.css'));
$clasesDashboard = leClasesDeSelectores((string) file_get_contents(__DIR__ . '/../../dashboard/css/dashboard.css'));

// Sin esto, un extractor roto que no encuentra nada dejaría pasar la
// comprobación de choques en verde por vacío, sin haber comprobado nada.
leVerificar('el extractor encuentra clases en lesiones.css', $clasesLesiones !== []);
leVerificar('el extractor encuentra clases en _fuente/styles.css', $clasesFuente !== []);
leVerificar('el extractor encuentra clases en dashboard/css/dashboard.css', $clasesDashboard !== []);
// Clases que en _fuente/styles.css solo existen dentro de un @media: si el
// extractor volviera a vaciar los bloques de @media enteros (el bug de la
// ronda anterior), estas dos no aparecerían y esta comprobación lo detectaría.
leVerificar('el extractor ve clases definidas solo dentro de un @media (hide-sm)', in_array('hide-sm', $clasesFuente, true));
leVerificar('el extractor ve clases definidas solo dentro de un @media (nowrap)', in_array('nowrap', $clasesFuente, true));
leVerificar('el extractor ve una clase propia de lesiones.css (tira-celda)', in_array('tira-celda', $clasesLesiones, true));

$choques = array_intersect($clasesLesiones, array_merge($clasesFuente, $clasesDashboard));
leVerificar('ninguna clase de lesiones.css choca con _fuente/styles.css ni dashboard/css/dashboard.css: ' . implode(',', $choques), $choques === []);

// Sin esta regla, hidden no oculta nada con clase: .btn, .confirmar y
// .ceremonia fijan su propio display y ganan al [hidden] del navegador (la
// ceremonia llegó a tapar admin.php entero al cargar).
leVerificar('lesiones.css fuerza [hidden] a display:none !important',
    preg_match('/(^|[\s},])\[hidden\]\s*\{[^}]*display\s*:\s*none\s*!important/', (string) preg_replace('#/\*.*?\*/#s', '', $css)) === 1);

leSalirConResultado();
