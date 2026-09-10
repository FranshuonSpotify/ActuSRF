<?php
// dashboard/index.php
// Puerta de entrada del presidente: login por correo y contraseña. En el paso
// 06 esta misma pantalla pasa a mostrar el dashboard una vez autenticado.

// !headers_sent() ademas del estado: no se puede abrir una sesion despues
// de enviar cabeceras, y el arnes de tests renderiza la pantalla cuando ya
// ha impreso. En una peticion real esta pantalla es el punto de entrada y
// nada ha salido todavia, asi que la sesion se abre igual.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

plEstablecerIdioma(plResolverIdioma());

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        http_response_code(403);
        exit(plT('error.csrf'));
    }

    $email = (string) ($_POST['email'] ?? '');
    $clave = (string) ($_POST['clave'] ?? '');
    $usuario = plBuscarUsuarioPorEmail($email);

    // Las tres razones de fallo —no existe, contraseña mala, cuenta
    // desactivada— dan el MISMO mensaje. Distinguirlas convertiría el
    // formulario en un oráculo de qué correos están dados de alta.
    if ($usuario !== null
        && !empty($usuario['activo'])
        && password_verify($clave, (string) ($usuario['hash'] ?? ''))) {
        // Regenerar ANTES de escribir el id en la sesión: si se hiciera
        // después, el id ya habría viajado con el identificador de sesión
        // viejo y la protección contra fijación no serviría de nada.
        session_regenerate_id(true);
        $_SESSION['pl_usuario_id'] = $usuario['id'];
        header('Location: index.php');
        exit;
    }

    $error = plT('login.error');
}

$usuario = plUsuarioActual();

// if/else y no un `exit` a mitad de fichero: un exit dentro de un include
// mata el proceso, y el arnés de tests renderiza esta pantalla con include.
// Además, aquí un else dice lo mismo y se lee mejor.
if ($usuario === null):
    plCabecera(plT('login.boton_entrar'));
    ?>
    <section class="dash-login">
      <h1><?= plEsc(plT('login.titulo')) ?></h1>
      <p class="ayuda"><?= plEsc(plT('login.subtitulo')) ?></p>

      <?php if ($error !== ''): ?>
        <p class="mal"><?= plEsc($error) ?></p>
      <?php endif; ?>

      <form method="post" class="dash-form">
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <label class="campo">
          <span><?= plEsc(plT('login.campo_email')) ?></span>
          <input class="inp" type="email" name="email" autocomplete="username" required autofocus>
        </label>
        <label class="campo">
          <span><?= plEsc(plT('login.campo_clave')) ?></span>
          <input class="inp" type="password" name="clave" autocomplete="current-password" required>
        </label>
        <button class="btn btn-accent" type="submit"><?= plEsc(plT('login.boton_entrar')) ?></button>
      </form>
    </section>
    <?php
    plPie();

else:

// -------- autenticado ---------------------------------------------------
// El dashboard completo llega en el paso 06. Aquí, de momento, la confirmación
// mínima de que la sesión funciona y de qué equipo gestiona el presidente.

$temporada = plTemporadaActiva();
$fase      = plFaseActiva();
$equipo    = ($usuario['equipoId'] ?? null) !== null ? plBuscarEquipo((string) $usuario['equipoId']) : null;

plCabecera(plT('nav.dashboard'), 'dashboard', $fase);
?>
<h1><?= plEsc($equipo['nombre'] ?? plT('nav.dashboard')) ?></h1>

<?php if ($temporada === null): ?>
  <p class="ayuda"><?= plEsc(plT('aviso.sin_temporada')) ?></p>
<?php endif; ?>

<?php if ($equipo === null): ?>
  <p class="mal"><?= plEsc(plT('aviso.sin_equipo')) ?></p>
<?php else: ?>
  <p class="ayuda">
    <?= plEsc(plT('login.campo_email')) ?>: <?= plEsc($usuario['email'] ?? '') ?>
  </p>
<?php endif; ?>

<?php
plPie();

endif;
