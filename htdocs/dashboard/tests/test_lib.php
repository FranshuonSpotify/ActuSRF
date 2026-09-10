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

plArnesLimpiar($dir);
plSalirConResultado();
