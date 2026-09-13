<?php
session_start();
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/lib.php';

requerirAdminBasicAuthConClaves('admin_supertecnicas_user', 'admin_supertecnicas_pass_hash', 'Supertecnicas Admin');

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!stCsrfValido()) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }
    if (isset($_POST['toggle_ventana'])) {
        $config = stCargarConfig();
        $config['ventana_abierta'] = !$config['ventana_abierta'];
        if (stGuardarConfig($config)) {
            $mensaje = 'Ventana ahora: ' . ($config['ventana_abierta'] ? 'ABIERTA' : 'CERRADA');
        } else {
            $mensaje = 'No se pudo guardar: error al escribir el fichero.';
        }
    } elseif (isset($_POST['toggle_usuario'])) {
        // Desactivar es la vía para deshacer un registro en el equipo
        // equivocado: la cuenta deja de entrar en el siguiente clic y su plaza
        // vuelve a quedar libre para el presidente de verdad.
        $usuarioId = (string) $_POST['toggle_usuario'];
        $activar = ($_POST['activar'] ?? '') === '1';
        if (stCambiarActivoUsuario($usuarioId, $activar)) {
            $mensaje = $activar ? 'Cuenta reactivada.' : 'Cuenta desactivada.';
        } else {
            $mensaje = 'No se pudo guardar: esa cuenta ya no existe o falló la escritura.';
        }
    }
}

$data = stCargarDatosOficiales();
$equipos = stEquiposActivos($data);
$config = stCargarConfig();

// Cuentas registradas, agrupadas por equipo para ver de un vistazo si alguien
// se ha metido en un club que no es el suyo — que es el riesgo real de dejar
// que cada uno elija su equipo.
$usuarios = stCargarUsuarios()['usuarios'];
$nombresEquipo = [];
foreach (($data['equipos'] ?? []) as $e) {
    $nombresEquipo[(string) ($e['id'] ?? '')] = (string) ($e['nombre'] ?? '');
}
usort($usuarios, function ($a, $b) use ($nombresEquipo) {
    $ea = $nombresEquipo[(string) ($a['equipoId'] ?? '')] ?? '';
    $eb = $nombresEquipo[(string) ($b['equipoId'] ?? '')] ?? '';
    return $ea === $eb
        ? strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''))
        : strcasecmp($ea, $eb);
});
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · Supertécnicas</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/supertecnicas.css">
</head>
<body>
<main class="st-shell">
  <header class="st-cabecera">
    <div>
      <h1>Admin · Supertécnicas</h1>
      <p class="ayuda">Controla la ventana de edición y el código/PIN de acceso de cada equipo.</p>
    </div>
    <span class="st-estado" data-abierta="<?= $config['ventana_abierta'] ? '1' : '0' ?>">
      <span class="punto"></span>
      <?= $config['ventana_abierta'] ? 'Ventana abierta' : 'Ventana cerrada' ?>
    </span>
  </header>

  <?php if ($mensaje !== ''): ?>
    <div class="st-banda st-banda-ok">
      <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <?= stEsc($mensaje) ?>
    </div>
  <?php endif; ?>

  <form method="post" class="st-panel">
    <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
    <div class="st-panel-texto">
      <b>Ventana de supertécnicas</b>
      <span class="ayuda">Mientras está cerrada, los presidentes ven su plantilla pero no pueden guardar cambios.</span>
    </div>
    <button class="btn btn-accent btn-icon-txt" type="submit" name="toggle_ventana" value="1">
      <?php if ($config['ventana_abierta']): ?>
        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
        Cerrar ventana
      <?php else: ?>
        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Abrir ventana
      <?php endif; ?>
    </button>
  </form>

  <h2 style="margin:2rem 0 .75rem;font-size:1rem">Cuentas registradas (<?= count($usuarios) ?>)</h2>
  <p class="ayuda" style="margin-bottom:1rem">
    Cada presidente se registra él mismo en <code>registro.php</code> y elige su equipo.
    Si alguien se mete en un club que no es el suyo, desactiva su cuenta: deja de
    entrar en el siguiente clic y su plaza vuelve a quedar libre.
  </p>

  <?php if (!$usuarios): ?>
    <p class="ayuda">Todavía no se ha registrado nadie.</p>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
      <div class="tabla-caja">
        <div class="tabla-scroll">
          <table class="tabla">
            <thead>
              <tr><th>Equipo</th><th>Nombre</th><th>Correo</th><th>Registro</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($usuarios as $u):
                $uid = (string) ($u['id'] ?? '');
                $activo = !empty($u['activo']);
                $eq = (string) ($u['equipoId'] ?? '');
              ?>
                <tr>
                  <td class="col-equipo"><?= stEsc($nombresEquipo[$eq] ?? $eq) ?></td>
                  <td><?= stEsc($u['nombre'] ?? '') ?></td>
                  <td class="mono"><?= stEsc($u['email'] ?? '') ?></td>
                  <td><?= stEsc(substr((string) ($u['registrado'] ?? ''), 0, 10)) ?></td>
                  <td>
                    <?php if ($activo): ?>
                      <span class="confirmado">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Activa
                      </span>
                    <?php else: ?>
                      <span class="pendiente">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12.01" y2="16.5"/></svg>
                        Desactivada
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="col-min">
                    <input type="hidden" name="activar" value="<?= $activo ? '0' : '1' ?>">
                    <button class="btn btn-secondary btn-sm" type="submit" name="toggle_usuario" value="<?= stEsc($uid) ?>">
                      <?= $activo ? 'Desactivar' : 'Reactivar' ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
