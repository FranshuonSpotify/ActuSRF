<?php
// dashboard/tests/test_presidentes.php
// Self-check del paso 12: presidentes, contraseñas, copresidentes y reasignación.
//   /c/xampp/php/php.exe dashboard/tests/test_presidentes.php
//
// admin_presidentes.php no se renderiza (Basic Auth contra config/secrets.php).
// Su lógica vive en almacen.php y se prueba ahí; el efecto sobre el
// presidente se comprueba renderizando SUS pantallas, que sí se pueden.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$INDEX = __DIR__ . '/../index.php';
$fichero = static fn() => (string) @file_get_contents(plRutaDatos('usuarios.json'));
$usuario = static function (string $id): ?array {
    foreach (plCargarUsuarios()['usuarios'] as $u) {
        if ($u['id'] === $id) {
            return $u;
        }
    }
    return null;
};

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
]]);
plCrearTemporada('2026-27', '2026/27');

// -- alta ---------------------------------------------------------------------
$r = plCrearPresidente('Juan', 'Juan@Liga.es', 'clave-secreta-1', 'eq_a', true);
plVerificar('crear un presidente funciona', $r['ok'] === true);
$juan = $usuario($r['id']);
plVerificar('se guarda solo el hash: la contraseña en claro no aparece en ningún sitio del fichero',
    !str_contains($fichero(), 'clave-secreta-1'));
plVerificar('y el hash verifica la contraseña correcta', password_verify('clave-secreta-1', $juan['hash']));
plVerificar('y rechaza una incorrecta', !password_verify('clave-secreta-2', $juan['hash']));
plVerificar('el email se guarda en minúsculas, para no duplicarlo por la caja', $juan['email'] === 'juan@liga.es');
plVerificar('queda asignado a su equipo y activo', $juan['equipoId'] === 'eq_a' && $juan['activo'] === true);

// -- validaciones de alta ---------------------------------------------------------
plVerificar('sin nombre se rechaza', plCrearPresidente('  ', 'x@liga.es', 'clave-larga', 'eq_a', true)['ok'] === false);
plVerificar('un email mal formado se rechaza', plCrearPresidente('X', 'no-es-un-email', 'clave-larga', 'eq_a', true)['ok'] === false);
plVerificar('un email ya usado se rechaza, aunque cambie la caja',
    plCrearPresidente('Otro', 'JUAN@liga.es', 'clave-larga', 'eq_b', true)['ok'] === false);
plVerificar('una contraseña de menos de 8 caracteres se rechaza',
    plCrearPresidente('Corto', 'corto@liga.es', '1234567', 'eq_a', true)['ok'] === false);
plVerificar('un equipo inexistente se rechaza',
    plCrearPresidente('Fantasma', 'f@liga.es', 'clave-larga', 'eq_no', true)['ok'] === false);
$r = plCrearPresidente('Sin Club', 'sinclub@liga.es', 'clave-larga', null, true);
plVerificar('un presidente sin equipo todavía sí se puede crear', $r['ok'] === true && $usuario($r['id'])['equipoId'] === null);
$sinClub = $r['id'];
plVerificar('ningún rechazo escribió en el fichero', count(plCargarUsuarios()['usuarios']) === 2);

// -- copresidentes ----------------------------------------------------------------
$r = plCrearPresidente('Ana', 'ana@liga.es', 'clave-de-ana', 'eq_a', true);
plVerificar('dos presidentes en el mismo equipo se aceptan: son copresidentes', $r['ok'] === true);
$ana = $r['id'];
plVerificar('y el recuento por equipo lo refleja', (plPresidentesPorEquipo()['eq_a'] ?? 0) === 2);
plVerificar('el recuento no cuenta a quien no tiene equipo', !array_key_exists('', plPresidentesPorEquipo()));

// -- edición --------------------------------------------------------------------
$hashAntes = $usuario($juan['id'])['hash'];
$r = plEditarPresidente($juan['id'], 'Juan Pérez', 'juan@liga.es', '', 'eq_a');
plVerificar('editar sin escribir contraseña funciona', $r['ok'] === true);
plVerificar('y CONSERVA el hash anterior: no rehashea una cadena vacía', $usuario($juan['id'])['hash'] === $hashAntes);
plVerificar('con el nombre nuevo', $usuario($juan['id'])['nombre'] === 'Juan Pérez');

$r = plEditarPresidente($juan['id'], 'Juan Pérez', 'juan@liga.es', 'otra-clave-nueva', 'eq_a');
$nuevo = $usuario($juan['id'])['hash'];
plVerificar('cambiar la contraseña la rehashea', $r['ok'] === true && $nuevo !== $hashAntes);
plVerificar('la nueva entra y la vieja ya no',
    password_verify('otra-clave-nueva', $nuevo) && !password_verify('clave-secreta-1', $nuevo));
plVerificar('y tampoco la nueva queda en claro', !str_contains($fichero(), 'otra-clave-nueva'));

plVerificar('una contraseña nueva demasiado corta se rechaza',
    plEditarPresidente($juan['id'], 'Juan', 'juan@liga.es', 'corta', 'eq_a')['ok'] === false);
plVerificar('quitarle el email a otro presidente se rechaza',
    plEditarPresidente($juan['id'], 'Juan', 'ANA@liga.es', '', 'eq_a')['ok'] === false);
plVerificar('editar a un presidente inexistente se rechaza',
    plEditarPresidente('u_no', 'X', 'x2@liga.es', '', 'eq_a')['ok'] === false);

// -- reasignación de equipo ---------------------------------------------------------
$h = plArnesPeticion($INDEX, ['pl_usuario_id' => $ana])['html'] ?? '';
plVerificar('antes de reasignar, Ana ve Alfa', str_contains($h, '<h1>Alfa</h1>'));
plEditarPresidente($ana, 'Ana', 'ana@liga.es', '', 'eq_b');
$h = plArnesPeticion($INDEX, ['pl_usuario_id' => $ana])['html'] ?? '';
plVerificar('reasignada a Beta, su siguiente pantalla muestra Beta', str_contains($h, '<h1>Beta</h1>'));
plVerificar('sin rastro del equipo anterior', !str_contains($h, 'Alfa'));
plVerificar('y el recuento de copresidentes se actualiza', (plPresidentesPorEquipo()['eq_a'] ?? 0) === 1);

$h = plArnesPeticion($INDEX, ['pl_usuario_id' => $sinClub])['html'] ?? '';
plVerificar('un presidente sin equipo ve el aviso, no un error', str_contains($h, 'Aún no tienes equipo asignado'));

// -- desactivación -----------------------------------------------------------------
plVerificar('desactivar funciona', plCambiarActivoPresidente($ana, false)['ok'] === true);
$_SESSION = ['pl_usuario_id' => $ana];
plVerificar('con la sesión abierta, pierde el acceso en su siguiente petición', plUsuarioActual() === null);
$r = plArnesPeticion($INDEX, ['pl_csrf' => 'tok'], [], ['csrf' => 'tok', 'email' => 'ana@liga.es', 'clave' => 'clave-de-ana']);
plVerificar('y no puede volver a entrar aunque la contraseña sea la buena',
    $r['tipo'] === 'html' && str_contains($r['html'], 'Correo o contraseña incorrectos'));
plVerificar('reactivarla le devuelve el acceso', plCambiarActivoPresidente($ana, true)['ok'] === true
    && ($usuario($ana)['activo'] ?? false) === true);
plVerificar('cambiar el estado de un presidente inexistente se rechaza', plCambiarActivoPresidente('u_no', false)['ok'] === false);

// -- guardas de la pantalla y del hash ---------------------------------------------
$pantalla = (string) file_get_contents(__DIR__ . '/../admin_presidentes.php');
$almacen  = (string) file_get_contents(__DIR__ . '/../almacen.php');
plVerificar('la pantalla exige Basic Auth antes de arrancar la sesión',
    strpos($pantalla, 'requerirAdminBasicAuthConClaves') < strpos($pantalla, 'session_start'));
plVerificar('todo POST valida CSRF y corta con 403',
    str_contains($pantalla, 'plCsrfValido()') && str_contains($pantalla, 'plCortar(403'));
plVerificar('sin header()+exit a pelo', !preg_match('/header\s*\(\s*[\'"]Location/', $pantalla) && !str_contains($pantalla, 'http_response_code('));
plVerificar('el panel de admin no usa plT(): va en español', !str_contains($pantalla, 'plT('));
plVerificar('el hash vive en el almacén, el único punto por el que pasa toda alta o edición',
    str_contains($almacen, 'password_hash('));
plVerificar('la pantalla nunca pinta un hash', !str_contains($pantalla, "['hash']"));

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
