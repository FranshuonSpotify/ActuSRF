<?php
// dashboard/admin_equipos.php
// Admin → Equipos: alta manual, edición del nombre, archivar y reactivar, y el
// importador de equipos desde la web pública. Existe porque hay equipos nuevos
// que todavía no están en datos_oficiales.json —se crean a mano aquí— y
// equipos de la web que aquí hay que archivar. Archivar es local: nunca toca
// el fichero de la web pública.
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

// El fichero de la web pública. Se LEE para importar y jamás se escribe.
const PL_DATOS_OFICIALES = __DIR__ . '/../datos_oficiales.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, 'Token CSRF invalido.');
    }

    $accion = (string) ($_POST['accion'] ?? '');
    $id     = (string) ($_POST['id'] ?? '');
    $flash  = ['mensaje' => '', 'error' => ''];

    if ($accion === 'importar') {
        $r = plImportarEquiposDeWeb(PL_DATOS_OFICIALES);
        if ($r['ok']) {
            $flash['mensaje'] = $r['importados'] === 0
                ? 'No había equipos nuevos que importar: los ' . $r['saltados'] . ' de la web ya estaban.'
                : 'Importados ' . $r['importados'] . ' equipos de la web. ' . $r['saltados'] . ' ya estaban y se han dejado como estaban.';
        } else {
            $flash['error'] = 'No se pudo leer datos_oficiales.json o guardar los equipos.';
        }
    } elseif ($accion === 'crear') {
        $r = plCrearEquipo((string) ($_POST['nombre'] ?? ''), (string) ($_POST['abreviatura'] ?? ''));
        $r['ok'] ? $flash['mensaje'] = 'Equipo creado.' : $flash['error'] = $r['mensaje'];
    } elseif ($accion === 'editar') {
        $r = plEditarEquipo($id, (string) ($_POST['nombre'] ?? ''), (string) ($_POST['abreviatura'] ?? ''),
            (string) ($_POST['nombre_en'] ?? ''));
        $r['ok'] ? $flash['mensaje'] = 'Equipo guardado.' : $flash['error'] = $r['mensaje'];
    } elseif ($accion === 'archivar' || $accion === 'reactivar') {
        $r = plCambiarActivoEquipo($id, $accion === 'reactivar');
        $r['ok']
            ? $flash['mensaje'] = $accion === 'archivar' ? 'Equipo archivado.' : 'Equipo reactivado.'
            : $flash['error'] = $r['mensaje'];
    }

    $_SESSION['pl_admin_flash'] = $flash;
    plRedirigir('admin_equipos.php');
}

$flash = $_SESSION['pl_admin_flash'] ?? ['mensaje' => '', 'error' => ''];
unset($_SESSION['pl_admin_flash']);

$equipos   = plCargarEquipos()['equipos'];
$temporada = plTemporadaActiva();
$enTemp    = $temporada !== null ? array_keys(plCargarTemporada($temporada['id'])['equipos'] ?? []) : [];

plCabeceraAdmin('Equipos', 'equipos');
?>
<header class="dash-cabecera">
  <h1>Equipos</h1>
  <p class="ayuda">
    Los equipos son permanentes: se crean una vez y se archivan cuando dejan la
    liga. Archivar aquí no toca la web pública.
  </p>
</header>

<?php if (($flash['mensaje'] ?? '') !== ''): ?>
  <p class="banda-ok" role="status"><?= plEsc($flash['mensaje']) ?></p>
<?php endif; ?>
<?php if (($flash['error'] ?? '') !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($flash['error']) ?></p>
<?php endif; ?>

<section class="card dash-seccion">
  <h2>Importar equipos de la web</h2>
  <p class="ayuda">
    Trae los equipos no archivados de la web pública que todavía no estén aquí,
    con su nombre en inglés, su abreviatura y su escudo. Se puede pulsar las
    veces que haga falta: los que ya están se saltan. No escribe nada en la web.
  </p>
  <form method="post" action="admin_equipos.php">
    <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
    <input type="hidden" name="accion" value="importar">
    <button class="btn btn-accent" type="submit">Importar equipos de la web</button>
  </form>
</section>

<section class="card dash-seccion">
  <h2>Crear un equipo a mano</h2>
  <p class="ayuda">Para los equipos que aún no están en la web pública.</p>
  <form method="post" action="admin_equipos.php" class="dash-form-fila">
    <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
    <input type="hidden" name="accion" value="crear">
    <label class="campo"><span>Nombre</span><input class="inp" name="nombre" required maxlength="60"></label>
    <label class="campo"><span>Abreviatura</span><input class="inp inp-mono" name="abreviatura" maxlength="5"></label>
    <button class="btn btn-secondary" type="submit">Crear equipo</button>
  </form>
</section>

<section class="dash-seccion">
  <h2><?= count($equipos) ?> equipos</h2>

  <?php if ($equipos === []): ?>
    <p class="ayuda">Todavía no hay ninguno. Empieza importándolos de la web.</p>
  <?php else: ?>
    <?php foreach ($equipos as $e): $eid = (string) ($e['id'] ?? ''); ?>
      <form id="f-<?= plEsc($eid) ?>" method="post" action="admin_equipos.php" hidden>
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <input type="hidden" name="id" value="<?= plEsc($eid) ?>">
      </form>
    <?php endforeach; ?>

    <div class="tabla-scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th scope="col">Nombre</th>
            <th scope="col">Abrev.</th>
            <th scope="col">Nombre en inglés</th>
            <th scope="col">Origen</th>
            <th scope="col">Estado</th>
            <th scope="col"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($equipos as $e): $eid = (string) ($e['id'] ?? ''); $activo = !empty($e['activo']); ?>
            <tr<?= $activo ? '' : ' class="archivado"' ?>>
              <td>
                <label class="sr-only" for="n-<?= plEsc($eid) ?>">Nombre</label>
                <input class="inp inp-sm" id="n-<?= plEsc($eid) ?>" form="f-<?= plEsc($eid) ?>" name="nombre" value="<?= plEsc($e['nombre'] ?? '') ?>" required maxlength="60">
              </td>
              <td>
                <label class="sr-only" for="a-<?= plEsc($eid) ?>">Abreviatura</label>
                <input class="inp inp-sm inp-mono" id="a-<?= plEsc($eid) ?>" form="f-<?= plEsc($eid) ?>" name="abreviatura" value="<?= plEsc($e['abreviatura'] ?? '') ?>" maxlength="5">
              </td>
              <td>
                <label class="sr-only" for="i-<?= plEsc($eid) ?>">Nombre en inglés</label>
                <input class="inp inp-sm" id="i-<?= plEsc($eid) ?>" form="f-<?= plEsc($eid) ?>" name="nombre_en" value="<?= plEsc($e['nombre_en'] ?? '') ?>" maxlength="60">
              </td>
              <td><?= ($e['equipoId'] ?? null) === null ? 'Creado aquí' : 'Web pública' ?></td>
              <td>
                <?= $activo ? 'Activo' : 'Archivado' ?>
                <?php if ($activo && $temporada !== null && !in_array($eid, $enTemp, true)): ?>
                  <br><span class="ayuda">No está en la temporada en curso</span>
                <?php endif; ?>
              </td>
              <td class="acciones">
                <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($eid) ?>" name="accion" value="editar">Guardar</button>
                <?php if ($activo): ?>
                  <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($eid) ?>" name="accion" value="archivar"
                          data-confirmar="<?= plEsc('¿Archivar ' . ($e['nombre'] ?? '') . '? Sus presidentes seguirán pudiendo entrar, pero el equipo no entrará en las temporadas nuevas.') ?>">Archivar</button>
                <?php else: ?>
                  <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($eid) ?>" name="accion" value="reactivar">Reactivar</button>
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
