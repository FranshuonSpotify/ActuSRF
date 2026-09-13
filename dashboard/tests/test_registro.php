<?php
// dashboard/tests/test_registro.php
// Self-check del auto-registro de presidentes: el primero que llega a un equipo
// lo bloquea, y la segunda plaza solo se abre con el código de invitación que
// genera el presidente que ya está dentro.
//   /c/xampp/php/php.exe dashboard/tests/test_registro.php
//
// Lo que de verdad importa aquí no es que el formulario pinte: es que nadie
// entre en un club ocupado sin invitación, que un código no valga dos veces, y
// que la contraseña acabe hasheada y nunca en claro en usuarios.json.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$PANTALLA = __DIR__ . '/../registro.php';
$INDEX    = __DIR__ . '/../index.php';

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa',  'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta',  'activo' => true],
    ['id' => 'eq_z', 'nombre' => 'Zeta',  'activo' => false],
]]);

// Sesión sin usuario pero CON token: es el estado real de quien está viendo el
// formulario de registro, y es contra ese token contra el que se compara el
// 'csrf' que viaja en el POST.
$SESION   = ['pl_csrf' => 'tok'];
$alta     = static fn(array $post) => plArnesPeticion($GLOBALS['PANTALLA'], $GLOBALS['SESION'], [], $post + ['csrf' => 'tok']);
$usuarios = static fn(): array => plCargarUsuarios()['usuarios'];
$buscar   = static function (string $email) use ($usuarios): ?array {
    foreach ($usuarios() as $u) {
        if ((string) ($u['email'] ?? '') === $email) {
            return $u;
        }
    }
    return null;
};
$enEquipo = static fn(string $eq): int => count(array_filter(
    plCargarUsuarios()['usuarios'],
    static fn(array $u): bool => (string) ($u['equipoId'] ?? '') === $eq && !empty($u['activo'])
));

// -- la pantalla ---------------------------------------------------------
$r = plArnesPeticion($PANTALLA, []);
$h = $r['html'] ?? '';
plVerificar('sin sesión se pinta el formulario de registro', $r['tipo'] === 'html' && str_contains($h, 'name="clave2"'));
plVerificar('con los equipos activos en el desplegable', str_contains($h, 'value="eq_a"') && str_contains($h, 'value="eq_b"'));
plVerificar('y SIN los archivados: nadie se registra en un equipo que ya no juega', !str_contains($h, 'value="eq_z"'));
plVerificar('el aviso de que el correo no tiene que ser real está a la vista',
    str_contains($h, plEsc(plT('registro.aviso_email'))));
plVerificar('y el aviso de elegir SOLO tu equipo también',
    str_contains($h, plEsc(plT('registro.aviso_equipo'))));
plVerificar('pide el código de invitación', str_contains($h, 'name="codigo"'));
plVerificar('explicando que solo hace falta para entrar de copresidente',
    str_contains($h, plEsc(plT('registro.aviso_codigo'))));
plVerificar('un solo h1', substr_count($h, '<h1') === 1);

// -- CSRF ----------------------------------------------------------------
// Con token en sesión pero sin enviarlo en el formulario: es el caso que de
// verdad importa, el de una página ajena enviando el alta en tu nombre.
$r = plArnesPeticion($PANTALLA, $SESION, [], ['nombre' => 'X', 'email' => 'x@x.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_a']);
plVerificar('un POST sin token CSRF se corta con 403', $r['tipo'] === 'cortar' && $r['codigo'] === 403);
plVerificar('y no da de alta a nadie', $usuarios() === []);

// -- el primero de un equipo libre entra sin nada -------------------------
$r = $alta(['nombre' => '  Juan  ', 'email' => '  JUAN@Ejemplo.ES ', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_a']);
plVerificar('el primero de un equipo libre entra sin código', $r['tipo'] === 'redirigir' && $r['url'] === 'index.php');
plVerificar('y deja la sesión iniciada: no hay que volver a entrar', ($r['sesion']['pl_usuario_id'] ?? '') !== '');

$u = $usuarios();
plVerificar('queda un usuario', count($u) === 1);
plVerificar('con el nombre recortado', $u[0]['nombre'] === 'Juan');
plVerificar('el correo en minúsculas, para que no haya dos cuentas por la caja', $u[0]['email'] === 'juan@ejemplo.es');
plVerificar('nace activo', !empty($u[0]['activo']));
plVerificar('en el equipo que eligió', $u[0]['equipoId'] === 'eq_a');
plVerificar('la contraseña se guarda hasheada, NUNCA en claro',
    !str_contains(json_encode($u), 'clave12345') && password_verify('clave12345', $u[0]['hash']));

// -- el registro queda auditado -------------------------------------------
$ev = plCargarJson(plRutaDatos('registro.json'), ['eventos' => []])['eventos'];
plVerificar('el alta escribe un evento en el registro', count($ev) === 1 && $ev[0]['tipo'] === 'REGISTRO');
plVerificar('con el equipo elegido, que es lo que hace auditable el "elijo yo mi club"',
    ($ev[0]['detalle']['equipo'] ?? '') === 'eq_a');

// -- correo repetido -------------------------------------------------------
$r = $alta(['nombre' => 'Otro', 'email' => 'juan@ejemplo.es', 'clave' => 'otraclave1', 'clave2' => 'otraclave1', 'equipo' => 'eq_b']);
plVerificar('un correo ya registrado se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_email_duplicado'))));
plVerificar('y no crea un segundo usuario', count($usuarios()) === 1);

// -- validaciones ----------------------------------------------------------
$r = $alta(['nombre' => '', 'email' => 'a@x.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_b']);
plVerificar('sin nombre se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('error.nombre_vacio'))));

$r = $alta(['nombre' => 'A', 'email' => 'no-es-un-correo', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_b']);
plVerificar('un correo sin forma de correo se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_email'))));

$r = $alta(['nombre' => 'A', 'email' => 'corto@x.es', 'clave' => 'corta', 'clave2' => 'corta', 'equipo' => 'eq_b']);
plVerificar('una contraseña por debajo del mínimo se rechaza',
    $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_clave_corta', ['minimo' => PL_CLAVE_MINIMA]))));

$r = $alta(['nombre' => 'A', 'email' => 'distintas@x.es', 'clave' => 'clave12345', 'clave2' => 'clave54321', 'equipo' => 'eq_b']);
plVerificar('dos contraseñas distintas se rechazan', $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_claves_distintas'))));

$r = $alta(['nombre' => 'A', 'email' => 'archivado@x.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_z']);
plVerificar('registrarse en un equipo archivado se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_equipo'))));

$r = $alta(['nombre' => 'A', 'email' => 'inventado@x.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_inventado']);
plVerificar('y en un equipo que no existe, también', $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_equipo'))));
plVerificar('ninguna de las validaciones ha dado de alta a nadie', count($usuarios()) === 1);

// -- el primero BLOQUEA el equipo -----------------------------------------
$r = $alta(['nombre' => 'Colado', 'email' => 'colado@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_a']);
plVerificar('sin código, un equipo que ya tiene presidente no admite a nadie más',
    $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_codigo'))));
plVerificar('y no crea la cuenta', count($usuarios()) === 1);

$r = $alta(['nombre' => 'Colado', 'email' => 'colado@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_a', 'codigo' => 'ABCDEF']);
plVerificar('con un código inventado, tampoco',
    $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_codigo'))));
plVerificar('y sigue sin crearla', count($usuarios()) === 1);

// El bloqueo es POR EQUIPO: otro club libre sigue admitiendo al primero.
$r = $alta(['nombre' => 'Otra', 'email' => 'otra@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_b']);
plVerificar('un equipo todavía libre sí admite al primero, y sin código', $r['tipo'] === 'redirigir');
plVerificar('ya son dos cuentas, una por equipo', count($usuarios()) === 2);

// -- la invitación del presidente -----------------------------------------
$juan = $buscar('juan@ejemplo.es');
$inv  = plCrearInvitacion('eq_a', (string) $juan['id'], (string) $juan['nombre']);
plVerificar('el presidente genera un código para su equipo',
    $inv['ok'] === true && strlen((string) $inv['codigo']) === 6);
plVerificar('y queda consultable', (plInvitacionDe('eq_a')['codigo'] ?? '') === $inv['codigo']);

$r = $alta(['nombre' => 'Copre', 'email' => 'copre@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345',
            'equipo' => 'eq_a', 'codigo' => strtolower((string) $inv['codigo'])]);
plVerificar('con el código correcto entra de copresidente, y da igual la caja', $r['tipo'] === 'redirigir');
plVerificar('ahora eq_a tiene dos', $enEquipo('eq_a') === 2);
plVerificar('y el código se consume al usarse', plInvitacionDe('eq_a') === null);

// -- con el equipo lleno ---------------------------------------------------
$inv2 = plCrearInvitacion('eq_a', (string) $juan['id'], (string) $juan['nombre']);
$r = $alta(['nombre' => 'Tercero', 'email' => 'tercero@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345',
            'equipo' => 'eq_a', 'codigo' => (string) $inv2['codigo']]);
plVerificar('el tercero se rechaza aunque traiga un código válido',
    $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_equipo_lleno', ['maximo' => PL_MAX_PRESIDENTES_POR_EQUIPO]))));
plVerificar('y el código NO se gasta: el equipo estaba lleno, no era culpa de la invitación',
    (plInvitacionDe('eq_a')['codigo'] ?? '') === $inv2['codigo']);

// -- regenerar invalida el anterior ---------------------------------------
$viejo = (string) $inv2['codigo'];
$nuevo = (string) plCrearInvitacion('eq_a', (string) $juan['id'], (string) $juan['nombre'])['codigo'];
plVerificar('generar otro código invalida el anterior', plConsumirInvitacion('eq_a', $viejo) === false);
plVerificar('el nuevo sí vale', plConsumirInvitacion('eq_a', $nuevo) === true);
plVerificar('y solo una vez', plConsumirInvitacion('eq_a', $nuevo) === false);
plVerificar('un código de otro equipo no sirve',
    plConsumirInvitacion('eq_b', (string) plCrearInvitacion('eq_a', (string) $juan['id'], 'Juan')['codigo']) === false);

// -- una plaza liberada sigue necesitando invitación ----------------------
plCambiarActivoPresidente((string) $buscar('copre@ejemplo.es')['id'], false);
plVerificar('desactivar a un copresidente libera su plaza', $enEquipo('eq_a') === 1);

$r = $alta(['nombre' => 'Relevo', 'email' => 'relevo@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345', 'equipo' => 'eq_a']);
plVerificar('pero la plaza libre NO vuelve a ser de libre acceso: sigue haciendo falta invitación',
    $r['tipo'] === 'html' && str_contains($r['html'], plEsc(plT('registro.error_codigo'))));

$inv3 = plCrearInvitacion('eq_a', (string) $juan['id'], (string) $juan['nombre']);
$r = $alta(['nombre' => 'Relevo', 'email' => 'relevo@ejemplo.es', 'clave' => 'clave12345', 'clave2' => 'clave12345',
            'equipo' => 'eq_a', 'codigo' => (string) $inv3['codigo']]);
plVerificar('con una invitación nueva, el relevo entra', $r['tipo'] === 'redirigir');

// -- generar el código desde el panel del presidente ----------------------
$otra = $buscar('otra@ejemplo.es');
$r = plArnesPeticion($INDEX, ['pl_usuario_id' => (string) $otra['id'], 'pl_csrf' => 'tok'], [], ['csrf' => 'tok', 'accion' => 'invitar']);
plVerificar('el presidente genera su código desde su panel', $r['tipo'] === 'redirigir' && $r['url'] === 'index.php');
plVerificar('y se guarda para SU equipo', plInvitacionDe('eq_b') !== null);
plVerificar('el formulario no manda el equipo: el equipo sale de la cuenta, así nadie invita a un club ajeno',
    !str_contains((string) file_get_contents($INDEX), 'name="equipo"'));

$r = plArnesPeticion($INDEX, ['pl_usuario_id' => (string) $otra['id'], 'pl_csrf' => 'tok'], [], ['accion' => 'invitar']);
plVerificar('generar una invitación sin CSRF se corta con 403', $r['tipo'] === 'cortar' && $r['codigo'] === 403);

$r = plArnesPeticion($INDEX, ['pl_csrf' => 'tok'], [], ['csrf' => 'tok', 'accion' => 'invitar']);
plVerificar('y sin sesión no genera nada', $r['tipo'] === 'redirigir' && plInvitacionDe('eq_z') === null);

// -- con sesión ya iniciada ------------------------------------------------
$r = plArnesPeticion($PANTALLA, ['pl_usuario_id' => (string) $juan['id'], 'pl_csrf' => 'tok']);
plVerificar('quien ya tiene sesión no ve el registro: se va a su dashboard',
    $r['tipo'] === 'redirigir' && $r['url'] === 'index.php');

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
