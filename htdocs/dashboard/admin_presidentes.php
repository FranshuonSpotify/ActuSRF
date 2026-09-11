<?php
// dashboard/admin_presidentes.php
// Admin → Presidentes: alta, edición, activar y desactivar, y reasignar a otro
// equipo. Los copresidentes —dos personas llevando el mismo club— son un caso
// normal, no una excepción, y la pantalla los señala. Cambiar de presidente
// entre temporadas es reasignar el equipo: no se guarda histórico.
//
// En español y sin pasar por el diccionario de i18n: el panel de admin lo usa
// una sola persona.

// El Basic Auth va antes que cualquier otra cosa.
require_once __DIR__ . '/../config/admin_auth.php';
requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';
require_once __DIR__ . '/chrome.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, 'Token CSRF invalido.');
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $id     = (string) ($_POST['id'] ?? '');
    // "" en el desplegable es "sin equipo": un presidente dado de alta antes de
    // saber qué club llevará.
    $equipo = (string) ($_POST['equipo'] ?? '');
    $equipo = $equipo === '' ? null : $equipo;
    $flash  = ['mensaje' => '', 'error' => ''];

    // Las contraseñas llegan en claro por el formulario y se hashean DENTRO de
    // almacen.php, en plCrearPresidente()/plEditarPresidente(): es el único
    // punto por el que pasa cualquier alta o edición, así que ninguna pantalla
    // puede guardar una contraseña sin hashear aunque se le olvide.
    if ($accion === 'crear') {
        $r = plCrearPresidente((string) ($_POST['nombre'] ?? ''), (string) ($_POST['email'] ?? ''),
            (string) ($_POST['clave'] ?? ''), $equipo, !empty($_POST['activo']));
        $r['ok'] ? $flash['mensaje'] = 'Presidente creado.' : $flash['error'] = $r['mensaje'];
    } elseif ($accion === 'editar') {
        // Contraseña vacía = no cambiarla. Rehashear una cadena vacía dejaría
        // al presidente sin poder entrar, y el síntoma aparecería días después.
        $r = plEditarPresidente($id, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['email'] ?? ''),
            (string) ($_POST['clave'] ?? ''), $equipo);
        $r['ok'] ? $flash['mensaje'] = 'Presidente guardado.' : $flash['error'] = $r['mensaje'];
    } elseif ($accion === 'desactivar' || $accion === 'activar') {
        $r = plCambiarActivoPresidente($id, $accion === 'activar');
        $r['ok']
            ? $flash['mensaje'] = $accion === 'desactivar'
                ? 'Presidente desactivado. Pierde el acceso en su siguiente clic.'
                : 'Presidente activado.'
            : $flash['error'] = $r['mensaje'];
    }

    $_SESSION['pl_admin_flash'] = $flash;
    plRedirigir('admin_presidentes.php');
}

$flash = $_SESSION['pl_admin_flash'] ?? ['mensaje' => '', 'error' => ''];
unset($_SESSION['pl_admin_flash']);

$usuarios   = plCargarUsuarios()['usuarios'];
$equipos    = plCargarEquipos()['equipos'];
$porEquipo  = plPresidentesPorEquipo();
$nombreDe   = [];
foreach ($equipos as $e) {
    $nombreDe[(string) ($e['id'] ?? '')] = (string) ($e['nombre'] ?? '');
}

// Opciones del desplegable de equipo: los activos, más el que ya tenga
// asignado el presidente aunque esté archivado, para no perderlo al guardar.
$opcionesEquipo = static function (?string $elegido) use ($equipos): string {
    $html = '<option value="">— Sin equipo —</option>';
    foreach ($equipos as $e) {
        $id = (string) ($e['id'] ?? '');
        if (empty($e['activo']) && $id !== $elegido) {
            continue;
        }
        $html .= '<option value="' . plEsc($id) . '"' . ($id === $elegido ? ' selected' : '') . '>'
            . plEsc(($e['nombre'] ?? $id) . (empty($e['activo']) ? ' (archivado)' : '')) . '</option>';
    }
    return $html;
};

$copresidencias = array_filter($porEquipo, static fn(int $n) => $n > 1);

plCabeceraAdmin('Presidentes', 'presidentes');
?>
<header class="dash-cabecera">
  <h1>Presidentes</h1>
  <p class="ayuda">
    Cada presidente gestiona el equipo que tenga asignado. Un club puede tener
    dos: los copresidentes editan la misma plantilla, y si coinciden guardando,
    la app avisa al segundo en vez de pisar el trabajo del primero.
  </p>
</header>

<?php if (($flash['mensaje'] ?? '') !== ''): ?>
  <p class="banda-ok" role="status"><?= plEsc($flash['mensaje']) ?></p>
<?php endif; ?>
<?php if (($flash['error'] ?? '') !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($flash['error']) ?></p>
<?php endif; ?>

<?php if ($copresidencias !== []): ?>
  <p class="aviso">
    Equipos con copresidentes:
    <?= plEsc(implode(', ', array_map(
        static fn($id, $n) => ($nombreDe[$id] ?? $id) . ' (' . $n . ')',
        array_keys($copresidencias), $copresidencias))) ?>.
  </p>
<?php endif; ?>

<section class="card dash-seccion">
  <h2>Añadir presidente</h2>
  <form method="post" action="admin_presidentes.php" class="dash-form-fila">
    <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
    <input type="hidden" name="accion" value="crear">
    <label class="campo"><span>Nombre</span><input class="inp" name="nombre" required maxlength="60"></label>
    <label class="campo"><span>Email</span><input class="inp" type="email" name="email" required autocomplete="off"></label>
    <label class="campo"><span>Contraseña</span><input class="inp" type="password" name="clave" required minlength="8" autocomplete="new-password"></label>
    <label class="campo"><span>Equipo</span><select class="inp" name="equipo"><?= $opcionesEquipo(null) ?></select></label>
    <label class="campo campo-check"><input type="checkbox" name="activo" value="1" checked> <span>Activo</span></label>
    <button class="btn btn-primary" type="submit">Añadir presidente</button>
  </form>
  <p class="ayuda">La contraseña tiene que tener al menos 8 caracteres. Se guarda cifrada: ni tú podrás leerla después.</p>
</section>

<section class="dash-seccion">
  <h2><?= count($usuarios) ?> presidentes</h2>

  <?php if ($usuarios === []): ?>
    <p class="ayuda">Todavía no hay ninguno.</p>
  <?php else: ?>
    <?php foreach ($usuarios as $u): $uid = (string) ($u['id'] ?? ''); ?>
      <form id="f-<?= plEsc($uid) ?>" method="post" action="admin_presidentes.php" hidden>
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <input type="hidden" name="id" value="<?= plEsc($uid) ?>">
      </form>
    <?php endforeach; ?>

    <div class="tabla-scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th scope="col">Nombre</th>
            <th scope="col">Email</th>
            <th scope="col">Nueva contraseña</th>
            <th scope="col">Equipo</th>
            <th scope="col">Estado</th>
            <th scope="col"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u):
              $uid    = (string) ($u['id'] ?? '');
              $eq     = ($u['equipoId'] ?? null) === null ? null : (string) $u['equipoId'];
              $activo = !empty($u['activo']); ?>
            <tr<?= $activo ? '' : ' class="archivado"' ?>>
              <td>
                <label class="sr-only" for="n-<?= plEsc($uid) ?>">Nombre</label>
                <input class="inp inp-sm" id="n-<?= plEsc($uid) ?>" form="f-<?= plEsc($uid) ?>" name="nombre" value="<?= plEsc($u['nombre'] ?? '') ?>" required maxlength="60">
              </td>
              <td>
                <label class="sr-only" for="e-<?= plEsc($uid) ?>">Email</label>
                <input class="inp inp-sm" id="e-<?= plEsc($uid) ?>" form="f-<?= plEsc($uid) ?>" type="email" name="email" value="<?= plEsc($u['email'] ?? '') ?>" required autocomplete="off">
              </td>
              <td>
                <?php // Nunca se devuelve el hash al formulario: el campo va siempre vacío. ?>
                <label class="sr-only" for="c-<?= plEsc($uid) ?>">Nueva contraseña</label>
                <input class="inp inp-sm" id="c-<?= plEsc($uid) ?>" form="f-<?= plEsc($uid) ?>" type="password" name="clave" value="" minlength="8"
                       placeholder="Vacía = no cambiarla" autocomplete="new-password">
              </td>
              <td>
                <label class="sr-only" for="q-<?= plEsc($uid) ?>">Equipo</label>
                <select class="inp inp-sm" id="q-<?= plEsc($uid) ?>" form="f-<?= plEsc($uid) ?>" name="equipo"><?= $opcionesEquipo($eq) ?></select>
                <?php if ($eq !== null && ($porEquipo[$eq] ?? 0) > 1): ?>
                  <br><span class="ayuda">Copresidente: el equipo tiene <?= (int) $porEquipo[$eq] ?> presidentes</span>
                <?php endif; ?>
              </td>
              <td><?= $activo ? 'Activo' : 'Desactivado' ?></td>
              <td class="acciones">
                <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($uid) ?>" name="accion" value="editar">Guardar</button>
                <?php if ($activo): ?>
                  <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($uid) ?>" name="accion" value="desactivar"
                          data-confirmar="<?= plEsc('¿Desactivar a ' . ($u['nombre'] ?? '') . '? Perderá el acceso en su siguiente clic.') ?>">Desactivar</button>
                <?php else: ?>
                  <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($uid) ?>" name="accion" value="activar">Activar</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php
plPieAdmin();
