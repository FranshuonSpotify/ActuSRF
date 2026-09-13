<?php
// supertecnicas/registro.php
// Auto-registro del presidente, mismo modelo que dashboard/registro.php: se da
// de alta él mismo con correo y contraseña y elige su equipo, en vez de recibir
// un código y un PIN creados por el admin.
//
// La pantalla no decide nada: valida stRegistrarUsuario() en lib.php, que
// comprueba el correo duplicado y el cupo del equipo dentro del mismo lock.

session_start();
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/i18n.php';

stEstablecerIdioma(stResolverIdioma());

// Quien ya tiene sesión no se registra otra vez.
if (stUsuarioActual() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
// Lo tecleado, para devolverlo si el alta se rechaza. Las contraseñas NO se
// devuelven nunca: reaparecer en el HTML es justo lo que no debe hacer una
// contraseña, y volver a escribirla es barato.
$borrador = ['nombre' => '', 'email' => '', 'equipo' => '', 'codigo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registro') {
    if (!stCsrfValido()) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    $borrador = [
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'email'  => trim((string) ($_POST['email'] ?? '')),
        'equipo' => (string) ($_POST['equipo'] ?? ''),
        'codigo' => trim((string) ($_POST['codigo'] ?? '')),
    ];

    $r = stRegistrarUsuario(
        $borrador['nombre'],
        $borrador['email'],
        (string) ($_POST['clave'] ?? ''),
        (string) ($_POST['clave2'] ?? ''),
        $borrador['equipo'],
        $borrador['codigo']
    );

    if ($r['ok']) {
        session_regenerate_id(true);
        $_SESSION['st_usuario_id'] = $r['id'];
        header('Location: index.php');
        exit;
    }

    $error = stT($r['error'], $r['datos']);
}

// Solo equipos en activo. Los que ya tienen el cupo lleno siguen en la lista a
// propósito: quitarlos dejaría a alguien buscando su club sin entender por qué
// no está, y el servidor ya responde con un mensaje que lo explica.
$equipos = stEquiposActivos(stCargarDatosOficiales());
usort($equipos, function ($a, $b) {
    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
});
?>
<!doctype html>
<html lang="<?= stEsc($GLOBALS['ST_IDIOMA_ACTUAL']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= stEsc(stT('registro.titulo')) ?> — Superliga Frontier</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/supertecnicas.css">
</head>
<body>
<main class="st-login">
  <div class="st-marca">
    <span class="pip"></span>
    <span>Superliga Frontier</span>
  </div>
  <?php stRenderSelectorIdioma(); ?>
  <div class="card st-login-card">
    <h1><?= stEsc(stT('registro.titulo')) ?></h1>
    <p class="ayuda"><?= stEsc(stT('registro.subtitulo')) ?></p>

    <?php if ($error !== ''): ?>
      <p class="mal">
        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12.01" y2="16.5"/></svg>
        <?= stEsc($error) ?>
      </p>
    <?php endif; ?>

    <form method="post" action="registro.php" class="st-form">
      <input type="hidden" name="accion" value="registro">
      <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">

      <label class="campo"><span><?= stEsc(stT('registro.campo_nombre')) ?></span>
        <input class="inp" type="text" name="nombre" value="<?= stEsc($borrador['nombre']) ?>" required maxlength="60" autofocus>
      </label>

      <label class="campo"><span><?= stEsc(stT('login.campo_email')) ?></span>
        <input class="inp" type="email" name="email" value="<?= stEsc($borrador['email']) ?>" required autocomplete="username">
      </label>
      <p class="ayuda"><?= stEsc(stT('registro.aviso_email')) ?></p>

      <label class="campo"><span><?= stEsc(stT('login.campo_clave')) ?></span>
        <input class="inp" type="password" name="clave" required minlength="<?= stEsc(ST_CLAVE_MINIMA) ?>" autocomplete="new-password">
      </label>
      <label class="campo"><span><?= stEsc(stT('registro.campo_clave2')) ?></span>
        <input class="inp" type="password" name="clave2" required minlength="<?= stEsc(ST_CLAVE_MINIMA) ?>" autocomplete="new-password">
      </label>

      <label class="campo"><span><?= stEsc(stT('registro.campo_equipo')) ?></span>
        <select class="inp" name="equipo" required>
          <option value=""><?= stEsc(stT('registro.equipo_placeholder')) ?></option>
          <?php foreach ($equipos as $e): $id = (string) ($e['id'] ?? ''); ?>
            <option value="<?= stEsc($id) ?>"<?= $id === $borrador['equipo'] ? ' selected' : '' ?>><?= stEsc($e['nombre'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="ayuda"><?= stEsc(stT('registro.aviso_equipo')) ?></p>

      <?php // Siempre visible, sin JS que lo muestre según el equipo: quien
            // llega a un club libre lo deja en blanco y el servidor decide. ?>
      <label class="campo"><span><?= stEsc(stT('registro.campo_codigo')) ?></span>
        <input class="inp inp-mono" type="text" name="codigo" value="<?= stEsc($borrador['codigo']) ?>" maxlength="12" autocomplete="off">
      </label>
      <p class="ayuda"><?= stEsc(stT('registro.aviso_codigo')) ?></p>

      <button class="btn btn-accent btn-lg btn-icon-txt" type="submit">
        <?= stEsc(stT('registro.boton')) ?>
        <svg class="icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </button>
    </form>

    <p class="ayuda"><a href="index.php"><?= stEsc(stT('registro.volver_login')) ?></a></p>
  </div>
</main>
</body>
</html>
