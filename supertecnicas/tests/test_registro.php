<?php
// supertecnicas/tests/test_registro.php
// Self-check del auto-registro de supertecnicas: el primero que llega a un
// equipo lo bloquea, y la segunda plaza solo se abre con el código de
// invitación que genera el presidente que ya está dentro.
//   php supertecnicas/tests/test_registro.php
//
// Las rutas de datos se fijan ANTES de incluir lib.php, así que todo esto
// trabaja sobre ficheros temporales: ni datos_oficiales.json ni las cuentas
// reales se tocan.

$tmp = sys_get_temp_dir() . '/st_test_' . uniqid();
mkdir($tmp, 0777, true);

define('ST_DATA_JSON', $tmp . '/datos_oficiales.json');
define('ST_USUARIOS_JSON', $tmp . '/usuarios.json');
define('ST_INVITACIONES_JSON', $tmp . '/invitaciones.json');
define('ST_CODIGOS_JSON', $tmp . '/codigos_equipos.json');
define('ST_CONFIG_JSON', $tmp . '/config.json');

require_once __DIR__ . '/../lib.php';

file_put_contents(ST_DATA_JSON, json_encode(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa'],
    ['id' => 'eq_b', 'nombre' => 'Beta'],
    ['id' => 'eq_z', 'nombre' => 'Zeta', 'archivado' => true],
]], JSON_UNESCAPED_UNICODE));

$fallos = 0;

function verificar($descripcion, $condicion) {
    global $fallos;
    if ($condicion) {
        echo "OK   $descripcion\n";
    } else {
        echo "FAIL $descripcion\n";
        $fallos++;
    }
}

function usuarios() {
    return stCargarUsuarios()['usuarios'];
}

function buscar($email) {
    foreach (usuarios() as $u) {
        if ((string) ($u['email'] ?? '') === $email) return $u;
    }
    return null;
}

function enEquipo($eq) {
    $n = 0;
    foreach (usuarios() as $u) {
        if ((string) ($u['equipoId'] ?? '') === $eq && !empty($u['activo'])) $n++;
    }
    return $n;
}

// -- el primero de un equipo libre entra sin nada --------------------------
$r = stRegistrarUsuario('  Juan  ', '  JUAN@Ejemplo.ES ', 'clave12345', 'clave12345', 'eq_a');
verificar('el primero de un equipo libre entra sin código', $r['ok'] === true && $r['id'] !== null);

$u = usuarios();
verificar('queda un usuario', count($u) === 1);
verificar('con el nombre recortado', $u[0]['nombre'] === 'Juan');
verificar('el correo en minúsculas, para que no haya dos cuentas por la caja', $u[0]['email'] === 'juan@ejemplo.es');
verificar('nace activo', !empty($u[0]['activo']));
verificar('en el equipo que eligió', $u[0]['equipoId'] === 'eq_a');
verificar('con la fecha de registro, que es lo que lo hace auditable', !empty($u[0]['registrado']));
verificar('la contraseña se guarda hasheada, NUNCA en claro',
    strpos(json_encode($u), 'clave12345') === false && password_verify('clave12345', $u[0]['hash']));

// -- búsqueda y sesión -----------------------------------------------------
verificar('se encuentra por correo sin importar mayúsculas', stBuscarUsuarioPorEmail('Juan@Ejemplo.es') !== null);
verificar('un correo que no existe no encuentra nada', stBuscarUsuarioPorEmail('nadie@x.es') === null);

$_SESSION = ['st_usuario_id' => $u[0]['id']];
verificar('stUsuarioActual devuelve la cuenta de la sesión', (stUsuarioActual()['email'] ?? '') === 'juan@ejemplo.es');
$_SESSION = [];
verificar('sin sesión no devuelve nada', stUsuarioActual() === null);

// -- correo repetido -------------------------------------------------------
$r = stRegistrarUsuario('Otro', 'juan@ejemplo.es', 'otraclave1', 'otraclave1', 'eq_b');
verificar('un correo ya registrado se rechaza', $r['error'] === 'registro.error_email_duplicado');
verificar('y no crea un segundo usuario', count(usuarios()) === 1);

// -- validaciones ----------------------------------------------------------
$r = stRegistrarUsuario('', 'a@x.es', 'clave12345', 'clave12345', 'eq_b');
verificar('sin nombre se rechaza', $r['error'] === 'registro.error_nombre');

$r = stRegistrarUsuario('A', 'no-es-un-correo', 'clave12345', 'clave12345', 'eq_b');
verificar('un correo sin forma de correo se rechaza', $r['error'] === 'registro.error_email');

$r = stRegistrarUsuario('A', 'corto@x.es', 'corta', 'corta', 'eq_b');
verificar('una contraseña por debajo del mínimo se rechaza', $r['error'] === 'registro.error_clave_corta');
verificar('y dice cuál es el mínimo, para poder ponerlo en el mensaje',
    ($r['datos']['minimo'] ?? null) === ST_CLAVE_MINIMA);

$r = stRegistrarUsuario('A', 'distintas@x.es', 'clave12345', 'clave54321', 'eq_b');
verificar('dos contraseñas distintas se rechazan', $r['error'] === 'registro.error_claves_distintas');

$r = stRegistrarUsuario('A', 'archivado@x.es', 'clave12345', 'clave12345', 'eq_z');
verificar('registrarse en un equipo archivado se rechaza', $r['error'] === 'registro.error_equipo');

$r = stRegistrarUsuario('A', 'inventado@x.es', 'clave12345', 'clave12345', 'eq_inventado');
verificar('y en un equipo que no existe, también', $r['error'] === 'registro.error_equipo');
verificar('ninguna de las validaciones ha dado de alta a nadie', count(usuarios()) === 1);

// -- el primero BLOQUEA el equipo -----------------------------------------
$r = stRegistrarUsuario('Colado', 'colado@ejemplo.es', 'clave12345', 'clave12345', 'eq_a');
verificar('sin código, un equipo que ya tiene presidente no admite a nadie más',
    $r['error'] === 'registro.error_codigo');
verificar('y no crea la cuenta', count(usuarios()) === 1);

$r = stRegistrarUsuario('Colado', 'colado@ejemplo.es', 'clave12345', 'clave12345', 'eq_a', 'ABCDEF');
verificar('con un código inventado, tampoco', $r['error'] === 'registro.error_codigo');
verificar('y sigue sin crearla', count(usuarios()) === 1);

// El bloqueo es POR EQUIPO: otro club libre sigue admitiendo al primero.
$r = stRegistrarUsuario('Otra', 'otra@ejemplo.es', 'clave12345', 'clave12345', 'eq_b');
verificar('un equipo todavía libre sí admite al primero, y sin código', $r['ok'] === true);
verificar('ya son dos cuentas, una por equipo', count(usuarios()) === 2);

// -- la invitación del presidente -----------------------------------------
$juan = buscar('juan@ejemplo.es');
$inv = stCrearInvitacion('eq_a', $juan['id'], $juan['nombre']);
verificar('el presidente genera un código para su equipo',
    $inv['ok'] === true && strlen((string) $inv['codigo']) === 6);
verificar('y queda consultable', (stInvitacionDe('eq_a')['codigo'] ?? '') === $inv['codigo']);

$r = stRegistrarUsuario('Copre', 'copre@ejemplo.es', 'clave12345', 'clave12345', 'eq_a', strtolower((string) $inv['codigo']));
verificar('con el código correcto entra de copresidente, y da igual la caja', $r['ok'] === true);
verificar('ahora eq_a tiene dos', enEquipo('eq_a') === 2);
verificar('y el código se consume al usarse', stInvitacionDe('eq_a') === null);

// -- con el equipo lleno ---------------------------------------------------
$inv2 = stCrearInvitacion('eq_a', $juan['id'], $juan['nombre']);
$r = stRegistrarUsuario('Tercero', 'tercero@ejemplo.es', 'clave12345', 'clave12345', 'eq_a', (string) $inv2['codigo']);
verificar('el tercero se rechaza aunque traiga un código válido', $r['error'] === 'registro.error_equipo_lleno');
verificar('y el código NO se gasta: el equipo estaba lleno, no era culpa de la invitación',
    (stInvitacionDe('eq_a')['codigo'] ?? '') === $inv2['codigo']);

// -- regenerar invalida el anterior ---------------------------------------
$viejo = (string) $inv2['codigo'];
$nuevo = (string) stCrearInvitacion('eq_a', $juan['id'], $juan['nombre'])['codigo'];
verificar('generar otro código invalida el anterior', stConsumirInvitacion('eq_a', $viejo) === false);
verificar('el nuevo sí vale', stConsumirInvitacion('eq_a', $nuevo) === true);
verificar('y solo una vez', stConsumirInvitacion('eq_a', $nuevo) === false);
verificar('un código de otro equipo no sirve',
    stConsumirInvitacion('eq_b', (string) stCrearInvitacion('eq_a', $juan['id'], 'Juan')['codigo']) === false);

// -- una plaza liberada sigue necesitando invitación ----------------------
verificar('desactivar una cuenta funciona', stCambiarActivoUsuario(buscar('copre@ejemplo.es')['id'], false) === true);
verificar('y libera su plaza', enEquipo('eq_a') === 1);

$_SESSION = ['st_usuario_id' => buscar('copre@ejemplo.es')['id']];
verificar('una cuenta desactivada deja de entrar en el siguiente clic', stUsuarioActual() === null);
$_SESSION = [];

$r = stRegistrarUsuario('Relevo', 'relevo@ejemplo.es', 'clave12345', 'clave12345', 'eq_a');
verificar('pero la plaza libre NO vuelve a ser de libre acceso: sigue haciendo falta invitación',
    $r['error'] === 'registro.error_codigo');

$inv3 = stCrearInvitacion('eq_a', $juan['id'], $juan['nombre']);
$r = stRegistrarUsuario('Relevo', 'relevo@ejemplo.es', 'clave12345', 'clave12345', 'eq_a', (string) $inv3['codigo']);
verificar('con una invitación nueva, el relevo entra', $r['ok'] === true);

verificar('reactivar una cuenta también funciona', stCambiarActivoUsuario(buscar('copre@ejemplo.es')['id'], true) === true);
verificar('desactivar una cuenta que no existe no dice que sí', stCambiarActivoUsuario('u_inventado', false) === false);

// -- limpieza --------------------------------------------------------------
foreach (glob($tmp . '/*') as $f) { unlink($f); }
rmdir($tmp);

echo "\n";
if ($fallos > 0) {
    echo "$fallos comprobacion(es) fallida(s).\n";
    exit(1);
}
echo "Todas las comprobaciones pasan.\n";
