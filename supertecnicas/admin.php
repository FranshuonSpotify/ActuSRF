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
    } elseif (isset($_POST['guardar_codigo'])) {
        $equipoId = (string) $_POST['guardar_codigo'];
        $codigo = trim((string) ($_POST['codigo'][$equipoId] ?? ''));
        $pin = trim((string) ($_POST['pin'][$equipoId] ?? ''));
        if ($equipoId !== '' && $codigo !== '' && $pin !== '') {
            $codigos = stCargarCodigos();
            $codigos[$equipoId] = ['codigo' => $codigo, 'pin' => $pin];
            if (stGuardarCodigos($codigos)) {
                $mensaje = 'Código actualizado.';
            } else {
                $mensaje = 'No se pudo guardar: error al escribir el fichero.';
            }
        } else {
            $mensaje = 'Código y PIN no pueden estar vacíos.';
        }
    }
}

$data = stCargarDatosOficiales();
$equipos = stEquiposActivos($data);
$codigos = stCargarCodigos();
$config = stCargarConfig();
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

  <form method="post">
    <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
    <div class="tabla-caja">
      <div class="tabla-scroll">
        <table class="tabla">
          <thead>
            <tr><th>Equipo</th><th>Ciudad</th><th>Código</th><th>PIN</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($equipos as $equipo): $id = $equipo['id']; $actual = $codigos[$id] ?? stCodigoPorDefecto($equipo); $pendiente = !isset($codigos[$id]); ?>
              <tr>
                <td class="col-equipo">
                  <?= stEsc($equipo['nombre'] ?? '') ?><br>
                  <?php if ($pendiente): ?>
                    <span class="pendiente">
                      <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>
                      Pendiente de confirmar
                    </span>
                  <?php else: ?>
                    <span class="confirmado">
                      <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                      Confirmado
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= stEsc($equipo['ciudad'] ?? '') ?></td>
                <td><input class="inp inp-sm inp-mono" type="text" name="codigo[<?= stEsc($id) ?>]" value="<?= stEsc($actual['codigo']) ?>"></td>
                <td><input class="inp inp-sm inp-mono" type="text" name="pin[<?= stEsc($id) ?>]" value="<?= stEsc($actual['pin']) ?>"></td>
                <td class="col-min"><button class="btn btn-secondary btn-sm" type="submit" name="guardar_codigo" value="<?= stEsc($id) ?>">Guardar</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>
</main>
</body>
</html>
