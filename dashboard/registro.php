<?php
// dashboard/registro.php
// Auto-registro del presidente: se da de alta él mismo, elige su equipo y su
// contraseña. Existe para que el admin no tenga que crear una cuenta por
// persona y repartir credenciales una a una, que es justo lo que hace que una
// liga de treinta equipos tarde una semana en arrancar.
//
// La pantalla no decide nada: valida plRegistrarPresidente() en almacen.php,
// que comprueba el correo duplicado y el cupo del equipo dentro del mismo lock.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

plEstablecerIdioma(plResolverIdioma());

// Quien ya tiene sesión no se registra otra vez: se va a su dashboard.
if (plUsuarioActual() !== null) {
    plRedirigir('index.php');
}

$error = '';
// Lo tecleado, para devolverlo si el alta se rechaza. Las contraseñas NO se
// devuelven nunca: reaparecer en el HTML es justo lo que no debe hacer una
// contraseña, y volver a escribirla es barato.
$borrador = ['nombre' => '', 'email' => '', 'equipo' => '', 'codigo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $borrador = [
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'email'  => trim((string) ($_POST['email'] ?? '')),
        'equipo' => (string) ($_POST['equipo'] ?? ''),
        'codigo' => trim((string) ($_POST['codigo'] ?? '')),
    ];

    $r = plRegistrarPresidente(
        $borrador['nombre'],
        $borrador['email'],
        (string) ($_POST['clave'] ?? ''),
        (string) ($_POST['clave2'] ?? ''),
        $borrador['equipo'],
        $borrador['codigo']
    );

    if ($r['ok']) {
        // Regenerar ANTES de escribir el id, igual que en el login: si se
        // hiciera después, el id ya habría viajado con el identificador de
        // sesión viejo y la protección contra fijación no serviría de nada.
        //
        // El !headers_sent() es el mismo guard que el session_start() de
        // arriba y por el mismo motivo: en una petición real aquí no se ha
        // impreso nada y siempre regenera; bajo el arnés, que renderiza con
        // la salida ya empezada, se salta en vez de llenar el test de avisos.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['pl_usuario_id'] = $r['id'];
        plRedirigir('index.php');
    }

    $error = plT($r['error'], $r['datos']);
}

// Solo equipos activos. Los que ya tienen el cupo lleno siguen en la lista a
// propósito: quitarlos dejaría a alguien buscando su club sin entender por qué
// no está, y el servidor ya responde con un mensaje que lo explica.
$equipos = array_values(array_filter(
    plCargarEquipos()['equipos'],
    static fn(array $e): bool => !empty($e['activo'])
));
usort($equipos, static fn(array $a, array $b): int
    => strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? '')));

plCabecera(plT('registro.titulo'));
?>
<section class="dash-login">
  <h1 class="hero-title"><span class="grad-text">Superliga Frontier</span><span class="l2"><?= plEsc(plT('registro.titulo')) ?></span></h1>
  <p class="ayuda"><?= plEsc(plT('registro.subtitulo')) ?></p>

  <?php if ($error !== ''): ?>
    <p class="mal" role="alert"><?= plEsc($error) ?></p>
  <?php endif; ?>

  <form method="post" class="dash-form">
    <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">

    <label class="campo">
      <span><?= plEsc(plT('registro.campo_nombre')) ?></span>
      <input class="inp" type="text" name="nombre" value="<?= plEsc($borrador['nombre']) ?>" required maxlength="60" autofocus>
    </label>

    <label class="campo">
      <span><?= plEsc(plT('login.campo_email')) ?></span>
      <input class="inp" type="email" name="email" value="<?= plEsc($borrador['email']) ?>" autocomplete="username" required>
    </label>
    <p class="aviso" role="note"><?= plEsc(plT('registro.aviso_email')) ?></p>

    <label class="campo">
      <span><?= plEsc(plT('login.campo_clave')) ?></span>
      <input class="inp" type="password" name="clave" autocomplete="new-password" required minlength="<?= plEsc(PL_CLAVE_MINIMA) ?>">
    </label>
    <label class="campo">
      <span><?= plEsc(plT('registro.campo_clave2')) ?></span>
      <input class="inp" type="password" name="clave2" autocomplete="new-password" required minlength="<?= plEsc(PL_CLAVE_MINIMA) ?>">
    </label>

    <label class="campo">
      <span><?= plEsc(plT('registro.campo_equipo')) ?></span>
      <select class="inp" name="equipo" required>
        <option value=""><?= plEsc(plT('registro.equipo_placeholder')) ?></option>
        <?php foreach ($equipos as $e): $id = (string) ($e['id'] ?? ''); ?>
          <option value="<?= plEsc($id) ?>"<?= $id === $borrador['equipo'] ? ' selected' : '' ?>><?= plEsc(plNombreEquipo($e)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="aviso" role="note"><?= plEsc(plT('registro.aviso_equipo')) ?></p>

    <?php // El campo se enseña siempre, sin JavaScript que lo muestre y lo
          // esconda según el equipo elegido: quien llega a un club libre lo
          // deja en blanco, y el servidor es quien decide si hacía falta. ?>
    <label class="campo">
      <span><?= plEsc(plT('registro.campo_codigo')) ?></span>
      <input class="inp inp-mono" type="text" name="codigo" value="<?= plEsc($borrador['codigo']) ?>" maxlength="12" autocomplete="off">
    </label>
    <p class="aviso" role="note"><?= plEsc(plT('registro.aviso_codigo')) ?></p>

    <button class="btn btn-primary" type="submit"><?= plEsc(plT('registro.boton')) ?></button>
  </form>

  <p><a href="index.php"><?= plEsc(plT('registro.volver_login')) ?></a></p>
</section>
<?php
plPie();
