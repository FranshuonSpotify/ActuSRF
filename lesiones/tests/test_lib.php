<?php
// lesiones/tests/test_lib.php
// Ejecutar: /c/xampp/php/php.exe lesiones/tests/test_lib.php

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../lib.php';

$dir = leArnesDirDatos();
$ruta = $dir . '/prueba.json';

leVerificar('leCargarJson sobre fichero inexistente devuelve el default', leCargarJson($ruta, ['x' => 1]) === ['x' => 1]);

leVerificar('leGuardarJsonAtomico escribe y se puede releer', leGuardarJsonAtomico($ruta, ['a' => 1]) && leLeerJson($ruta, []) === ['a' => 1]);

leActualizarJson($ruta, [], static function (array $d): array {
    $d['a'] = ($d['a'] ?? 0) + 1;
    return $d;
});
leVerificar('leActualizarJson modifica sobre lo ya guardado', leLeerJson($ruta, []) === ['a' => 2]);

leVerificar('leEsc escapa comillas y ángulos', leEsc('<a href="x">') === '&lt;a href=&quot;x&quot;&gt;');

// Sesión como array plano: session_start() real en CLI, tras el echo que ya
// ha hecho leVerificar(), avisaría con "headers already sent" sin aportar
// nada — leTokenCsrf()/leCsrfValido() solo tocan $_SESSION como array.
$_SESSION = [];
$t1 = leTokenCsrf();
$t2 = leTokenCsrf();
leVerificar('leTokenCsrf devuelve el mismo token dentro de la sesión', $t1 === $t2);

$_POST['csrf'] = $t1;
leVerificar('leCsrfValido acepta el token correcto', leCsrfValido());
$_POST['csrf'] = 'otro';
leVerificar('leCsrfValido rechaza un token distinto', !leCsrfValido());

// -- costura de salida: leRedirigir / leCortar --------------------------
// Toda pantalla futura depende de esto: si dejara de lanzar bajo el arnés,
// esos tests morirían en el primer exit sin decir por qué.
$GLOBALS['LE_ARNES'] = true;

$marca = '';
try {
    leRedirigir('index.php');
} catch (RuntimeException $e) {
    $marca = $e->getMessage();
}
leVerificar('bajo el arnés, leRedirigir lanza en vez de hacer exit', $marca === 'LE_REDIRIGIR:index.php');

$marca = '';
try {
    leCortar(403, 'CSRF: invalido');
} catch (RuntimeException $e) {
    $marca = $e->getMessage();
}
leVerificar('bajo el arnés, leCortar lanza con código y mensaje', $marca === 'LE_CORTAR:403:CSRF: invalido');

$marca = '';
try {
    leResponderJson(403, ['ok' => false, 'error' => 'csrf_invalido']);
} catch (RuntimeException $e) {
    $marca = $e->getMessage();
}
leVerificar('bajo el arnés, leResponderJson lanza con la marca LE_JSON', $marca === 'LE_JSON:403:' . json_encode(['ok' => false, 'error' => 'csrf_invalido'], JSON_UNESCAPED_UNICODE));

unset($GLOBALS['LE_ARNES']);

// leArnesPeticion traduce esas marcas a un resultado, contra una pantalla
// mínima escrita para la ocasión, independiente de las reales.
$pantalla = $dir . '/le_pantalla_' . uniqid() . '.php';
file_put_contents($pantalla, '<?php
require_once ' . var_export(realpath(__DIR__ . '/../lib.php'), true) . ';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (($_POST["accion"] ?? "") === "cortar") { leCortar(403, "no: dos puntos"); }
    if (($_POST["accion"] ?? "") === "json") { leResponderJson(200, ["ok" => true, "hora" => "12:30:00"]); }
    leRedirigir("destino.php");
}
echo "<p>hola</p>";');

$r = leArnesPeticion($pantalla);
leVerificar('un GET devuelve el HTML', $r['tipo'] === 'html' && $r['html'] === '<p>hola</p>');

$r = leArnesPeticion($pantalla, [], [], ['accion' => 'ir']);
leVerificar('un POST que redirige devuelve tipo redirigir y la URL',
    $r['tipo'] === 'redirigir' && $r['url'] === 'destino.php');

$r = leArnesPeticion($pantalla, [], [], ['accion' => 'cortar']);
leVerificar('un POST cortado devuelve el código', $r['tipo'] === 'cortar' && $r['codigo'] === 403);
leVerificar('y el mensaje entero, aunque lleve dos puntos', $r['mensaje'] === 'no: dos puntos');

$r = leArnesPeticion($pantalla, [], [], ['accion' => 'json']);
leVerificar('un POST que responde JSON devuelve tipo json con su código',
    $r['tipo'] === 'json' && $r['codigo'] === 200);
leVerificar('y los datos decodificados, incluido un valor con dos puntos',
    $r['datos'] === ['ok' => true, 'hora' => '12:30:00']);

unset($GLOBALS['LE_ARNES']);

// -- leNormalizarTexto / leFormatearFecha --------------------------------
leVerificar('leNormalizarTexto pasa acentos a ASCII y a minusculas', leNormalizarTexto('Épsilon') === 'epsilon');
leVerificar('leNormalizarTexto colapsa espacios y recorta bordes', leNormalizarTexto('  Jugador   UNO ') === 'jugador uno');
leVerificar('leFormatearFecha convierte ISO-8601 a d/m/Y', leFormatearFecha('2026-09-10T18:30:00+02:00') === '10/09/2026');
leVerificar('leFormatearFecha con texto que no es fecha devuelve vacio', leFormatearFecha('no es fecha') === '');
leVerificar('leFormatearFecha con cadena vacia devuelve vacio', leFormatearFecha('') === '');

leArnesLimpiar($dir);
leSalirConResultado();
