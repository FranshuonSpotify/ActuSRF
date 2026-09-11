<?php
// dashboard/tests/test_lib.php
// Self-check de lib.php. Ejecutar desde la raíz del proyecto:
//   /c/xampp/php/php.exe dashboard/tests/test_lib.php
//
// Nunca corta en el primer fallo: plVerificar() acumula y plSalirConResultado()
// decide el código de salida al final, para ver la lista completa de una tirada.
// Todo ocurre en un directorio temporal: esta batería no toca dashboard/data/.

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../lib.php';

$dir  = plArnesDirDatos();
$ruta = $dir . '/prueba.json';

// -- plCargarJson ------------------------------------------------------
plVerificar(
    'una ruta inexistente devuelve el valor por defecto',
    plCargarJson($ruta, ['x' => 1]) === ['x' => 1]
);
plVerificar(
    'y NO crea el fichero al leerlo',
    !file_exists($ruta)
);
plVerificar(
    'ni su .lock: leer no escribe nada en disco',
    !file_exists($ruta . '.lock')
);
plVerificar(
    'plLeerJson, la lectura sin lock, devuelve lo mismo',
    plLeerJson($ruta, ['x' => 1]) === ['x' => 1] && !file_exists($ruta . '.lock')
);

// -- plGuardarJsonAtomico ----------------------------------------------
plVerificar(
    'guardar en una ruta nueva devuelve true',
    plGuardarJsonAtomico($ruta, ['a' => 1]) === true
);
plVerificar(
    'sobrescribir un fichero que YA existe devuelve true',
    plGuardarJsonAtomico($ruta, ['a' => 2, 'equipo' => 'Montaña']) === true
);
plVerificar(
    'el contenido sobrescrito es el nuevo, íntegro',
    plCargarJson($ruta, []) === ['a' => 2, 'equipo' => 'Montaña']
);
plVerificar(
    'los acentos se escriben legibles, no como secuencias \\uXXXX',
    str_contains((string) file_get_contents($ruta), 'Montaña')
);
plVerificar(
    'no queda ningún fichero temporal pl_* suelto en el directorio',
    count(glob($dir . '/pl_*') ?: []) === 0
);

// Un JSON corrupto no debe propagar el error: la app tiene que seguir en pie
// con la semilla en vez de romper la pantalla entera.
file_put_contents($ruta, '{esto no es json valido');
plVerificar(
    'un JSON corrupto devuelve el valor por defecto en lugar de fallar',
    plCargarJson($ruta, ['seguro' => true]) === ['seguro' => true]
);

// -- plActualizarJson --------------------------------------------------
$rutaAct = $dir . '/actualizar.json';
plVerificar('con el fichero ausente, el cambio recibe el valor por defecto',
    plActualizarJson($rutaAct, ['n' => 0], static function (array $d): array {
        $d['n']++;
        return $d;
    }) === true && plCargarJson($rutaAct, []) === ['n' => 1]);
plVerificar('y la siguiente actualización parte de lo que hay en disco',
    plActualizarJson($rutaAct, ['n' => 0], static function (array $d): array {
        $d['n'] += 10;
        return $d;
    }) === true && plCargarJson($rutaAct, []) === ['n' => 11]);

$antes = (string) file_get_contents($rutaAct);
plVerificar('si el cambio devuelve null, no se escribe nada',
    plActualizarJson($rutaAct, [], static fn(array $d) => null) === true
    && (string) file_get_contents($rutaAct) === $antes);
plVerificar('null sobre un fichero ausente tampoco lo crea',
    plActualizarJson($dir . '/nunca.json', [], static fn(array $d) => null) === true
    && !file_exists($dir . '/nunca.json'));
plVerificar('actualizar no deja temporales pl_* sueltos', count(glob($dir . '/pl_*') ?: []) === 0);

// -- plEsc -------------------------------------------------------------
plVerificar(
    'plEsc escapa comillas dobles Y simples',
    plEsc('<a href=\'x\'>"y"') === '&lt;a href=&#039;x&#039;&gt;&quot;y&quot;'
);
plVerificar(
    'plEsc convierte un valor no textual sin romper',
    plEsc(42) === '42'
);

// -- plNormalizarTexto -------------------------------------------------
plVerificar(
    'normaliza acentos y mayúsculas',
    plNormalizarTexto('Montaña Épica') === 'montana epica'
);
plVerificar(
    'colapsa los espacios sobrantes',
    plNormalizarTexto('  Endou   Mamoru  ') === 'endou mamoru'
);
plVerificar(
    'convierte en espacio lo que no es letra ni número',
    plNormalizarTexto('a.b-c!d') === 'a b c d'
);

// -- CSRF --------------------------------------------------------------
$_SESSION = [];
$_POST    = [];
plVerificar(
    'plCsrfValido es false cuando no hay token en sesión',
    plCsrfValido() === false
);

$token = plTokenCsrf();
plVerificar(
    'plTokenCsrf devuelve 64 caracteres hexadecimales',
    strlen($token) === 64 && ctype_xdigit($token)
);
plVerificar(
    'plTokenCsrf es estable dentro de la misma sesión',
    plTokenCsrf() === $token
);

$_POST['csrf'] = '';
plVerificar(
    'plCsrfValido es false con un token enviado vacío',
    plCsrfValido() === false
);

$_POST['csrf'] = str_repeat('0', 64);
plVerificar(
    'plCsrfValido es false con un token del tamaño correcto pero equivocado',
    plCsrfValido() === false
);

$_POST['csrf'] = $token;
plVerificar(
    'plCsrfValido es true con el token correcto',
    plCsrfValido() === true
);

// -- plAhora -----------------------------------------------------------
plVerificar(
    'plAhora devuelve una fecha ISO-8601 interpretable',
    strtotime(plAhora()) !== false
);

// -- plM ---------------------------------------------------------------
plVerificar('plM escribe las cifras en millones', plM(75) === '75M' && plM(0) === '0M');

// -- costura de salida: plRedirigir / plCortar --------------------------
// Todos los tests de pantalla dependen de esto. Si la costura dejara de
// lanzar bajo el arnés, esos tests morirían en el primer exit sin decir por qué.
$GLOBALS['PL_ARNES'] = true;

$marca = '';
try {
    plRedirigir('plantilla.php');
} catch (RuntimeException $e) {
    $marca = $e->getMessage();
}
plVerificar('bajo el arnés, plRedirigir lanza en vez de hacer exit', $marca === 'PL_REDIRIGIR:plantilla.php');

$marca = '';
try {
    plCortar(403, 'CSRF: invalido');
} catch (RuntimeException $e) {
    $marca = $e->getMessage();
}
plVerificar('bajo el arnés, plCortar lanza con código y mensaje', $marca === 'PL_CORTAR:403:CSRF: invalido');

unset($GLOBALS['PL_ARNES']);

// plArnesPeticion traduce esas marcas a un resultado. Se prueba contra una
// pantalla mínima escrita para la ocasión, independiente de las reales.
$pantalla = sys_get_temp_dir() . '/pl_pantalla_' . uniqid() . '.php';
file_put_contents($pantalla, '<?php
require_once ' . var_export(realpath(__DIR__ . '/../lib.php'), true) . ';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (($_POST["accion"] ?? "") === "cortar") { plCortar(403, "no: dos puntos"); }
    plRedirigir("destino.php");
}
echo "<p>hola</p>";');

$r = plArnesPeticion($pantalla);
plVerificar('un GET devuelve el HTML', $r['tipo'] === 'html' && $r['html'] === '<p>hola</p>');

$r = plArnesPeticion($pantalla, [], [], ['accion' => 'ir']);
plVerificar('un POST que redirige devuelve tipo redirigir y la URL',
    $r['tipo'] === 'redirigir' && $r['url'] === 'destino.php');

$r = plArnesPeticion($pantalla, [], [], ['accion' => 'cortar']);
plVerificar('un POST cortado devuelve el código', $r['tipo'] === 'cortar' && $r['codigo'] === 403);
plVerificar('y el mensaje entero, aunque lleve dos puntos', $r['mensaje'] === 'no: dos puntos');
plVerificar('y no deja buffers de salida abiertos', ob_get_level() <= 1);

unlink($pantalla);
unset($GLOBALS['PL_ARNES']);

plArnesLimpiar($dir);
plSalirConResultado();
