<?php
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/lib.php';

requerirAdminBasicAuthConClaves('admin_supertecnicas_user', 'admin_supertecnicas_pass_hash', 'Supertecnicas Admin');

function stEsc($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_ventana'])) {
        $config = stCargarConfig();
        $config['ventana_abierta'] = !$config['ventana_abierta'];
        stGuardarConfig($config);
        $mensaje = 'Ventana ahora: ' . ($config['ventana_abierta'] ? 'ABIERTA' : 'CERRADA');
    } elseif (isset($_POST['guardar_codigo'])) {
        $equipoId = (string) $_POST['guardar_codigo'];
        $codigo = trim((string) ($_POST['codigo'][$equipoId] ?? ''));
        $pin = trim((string) ($_POST['pin'][$equipoId] ?? ''));
        if ($equipoId !== '' && $codigo !== '' && $pin !== '') {
            $codigos = stCargarCodigos();
            $codigos[$equipoId] = ['codigo' => $codigo, 'pin' => $pin];
            stGuardarCodigos($codigos);
            $mensaje = 'Código actualizado.';
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
<main class="st-admin">
  <h1>Admin · Supertécnicas</h1>
  <?php if ($mensaje !== ''): ?><p class="ayuda"><?= stEsc($mensaje) ?></p><?php endif; ?>

  <form method="post" class="st-ventana">
    <span class="ayuda">Ventana de supertécnicas: <strong><?= $config['ventana_abierta'] ? 'ABIERTA' : 'CERRADA' ?></strong></span>
    <button class="btn btn-accent" type="submit" name="toggle_ventana" value="1">
      <?= $config['ventana_abierta'] ? 'Cerrar ventana' : 'Abrir ventana' ?>
    </button>
  </form>

  <form method="post">
    <div class="tabla-caja">
      <div class="tabla-scroll">
        <table class="tabla">
          <thead>
            <tr><th>Equipo</th><th>Ciudad</th><th>Código</th><th>PIN</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($equipos as $equipo): $id = $equipo['id']; $actual = $codigos[$id] ?? stCodigoPorDefecto($equipo); $pendiente = !isset($codigos[$id]); ?>
              <tr>
                <td><?= stEsc($equipo['nombre'] ?? '') ?><?= $pendiente ? ' <span class="ayuda">(pendiente de confirmar)</span>' : '' ?></td>
                <td><?= stEsc($equipo['ciudad'] ?? '') ?></td>
                <td><input class="inp inp-sm" type="text" name="codigo[<?= stEsc($id) ?>]" value="<?= stEsc($actual['codigo']) ?>"></td>
                <td><input class="inp inp-sm" type="text" name="pin[<?= stEsc($id) ?>]" value="<?= stEsc($actual['pin']) ?>"></td>
                <td><button class="btn btn-secondary btn-sm" type="submit" name="guardar_codigo" value="<?= stEsc($id) ?>">Guardar</button></td>
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
