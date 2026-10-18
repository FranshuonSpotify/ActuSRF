<?php
// lesiones/admin.php
// Panel de admin: lista los equipos con su estado de esta semana y expone la
// acción de tirar la ruleta. El resultado de la ruleta lo calcula SIEMPRE
// este fichero (vía leTirarParaEquipo), nunca el JavaScript: el cliente solo
// anima un resultado que ya ha sido decidido y guardado en el servidor.

// El Basic Auth va antes que nada: sin credenciales no se abre sesión ni se
// manda cookie a quien todavía no ha demostrado ser el admin.
require_once __DIR__ . '/../config/admin_auth.php';
requerirAdminBasicAuth();

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';

leSincronizarRecuperacion();

// -------------------------------------------------------------- acción AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['accion'] ?? '') === 'tirar') {
    if (!leCsrfValido()) {
        leResponderJson(403, ['ok' => false, 'error' => 'csrf_invalido']);
    }
    $equipoId = (string) ($_POST['equipo_id'] ?? '');
    if ($equipoId === '') {
        leResponderJson(400, ['ok' => false, 'error' => 'falta_equipo']);
    }
    // Panel secreto (Ctrl+4): mismo camino que una tirada real, pero con el
    // resultado puesto a mano. Se revalida entero en leTirarParaEquipo.
    $manual = null;
    if (is_string($_POST['manual_codigo'] ?? null) && $_POST['manual_codigo'] !== '') {
        $ids = is_array($_POST['manual_jugadores'] ?? null) ? array_filter($_POST['manual_jugadores'], 'is_string') : [];
        $manual = ['codigo' => $_POST['manual_codigo'], 'jugadores' => $ids];
    }
    $resultado = leTirarParaEquipo($equipoId, null, null, null, $manual);
    // La tira de la apertura pasa las caras de toda la plantilla antes de
    // parar en el lesionado, así que la necesita; solo cuando hay evento.
    if (($resultado['ok'] ?? false) && ($resultado['principal'] ?? '') === 'evento') {
        $resultado['plantilla'] = leBuscarEquipoOficial($equipoId)['jugadores'] ?? [];
    }
    leResponderJson(200, $resultado);
}

// ------------------------------------------------ correcciones (formulario)
$accion = $_GET['accion'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($accion === 'cerrar' || $accion === 'anular')) {
    if (!leCsrfValido()) {
        leCortar(403, 'La sesión ha caducado. Recarga la página.');
    }
    if ($accion === 'cerrar') {
        $lesionId = $_POST['lesion_id'] ?? '';
        $res = leCerrarLesion(is_string($lesionId) ? $lesionId : '');
        $exito = 'cerrada';
    } else {
        $equipoId = $_POST['equipo_id'] ?? '';
        $res = is_string($equipoId) ? leAnularTirada($equipoId) : ['ok' => false, 'error' => 'no_tirado'];
        $exito = 'anulada';
    }
    leRedirigir('admin.php?resultado=' . ($res['ok'] ? $exito : 'error_' . $res['error']));
}

// Lista cerrada: nunca se pinta lo que venga en la URL, solo su texto de aquí.
$mensajesResultado = [
    'cerrada'             => 'Lesión dada por recuperada.',
    'anulada'             => 'Tirada anulada: sus lesiones se han deshecho y el equipo puede volver a tirar esta semana.',
    'error_no_encontrada' => 'Esa lesión ya no existe.',
    'error_no_activa'     => 'Esa lesión ya no estaba activa.',
    'error_no_tirado'     => 'Ese equipo no ha tirado esta semana: no hay nada que anular.',
    'error_sin_datos'     => 'No se guardó el registro de esa tirada, así que no se puede anular.',
    'error_escritura'     => 'No se pudo guardar el cambio. Inténtalo de nuevo.',
];
$codigoResultado = $_GET['resultado'] ?? '';
$mensajeResultado = is_string($codigoResultado) ? ($mensajesResultado[$codigoResultado] ?? '') : '';

$equipos = leCargarEquiposOficiales();
usort($equipos, static fn(array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));
$lesiones = leCargarLesiones();
$temporada = leTemporadaActual();
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin · Lesiones · Superliga Frontier</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Teko:wght@500;600;700&family=Fraunces:ital,opsz,wght@1,9..144,300;1,9..144,400&family=JetBrains+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="../dashboard/css/dashboard.css">
<link rel="stylesheet" href="css/lesiones.css">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="nav stuck dash-nav-top">
  <div class="wrap nav-in">
    <a class="nav-logo" href="index.php"><img src="../assets/sf-logo-blanco.png" alt="Superliga Frontier" width="43" height="24"></a>
    <span class="dash-producto">Lesiones · Admin</span>
    <div class="nav-right">
      <span class="badge badge-superliga">Admin</span>
      <a class="btn btn-secondary btn-sm" href="index.php">Ver vista pública</a>
    </div>
  </div>
</header>
<main class="wrap dash-main" id="contenido">
  <header class="dash-cabecera">
    <div>
      <h1>Ruleta semanal de lesiones</h1>
      <p class="ayuda">Una tirada por equipo y semana. El resultado lo decide el servidor; la animación solo lo enseña.</p>
    </div>
  </header>

  <?php if ($mensajeResultado !== ''): ?>
    <p class="<?= str_starts_with((string) $codigoResultado, 'error_') ? 'aviso' : 'banda-ok' ?>" role="status"><?= leEsc($mensajeResultado) ?></p>
  <?php endif; ?>

  <section class="dash-seccion">
    <div class="tabla-scroll">
      <table class="tbl" id="tabla-equipos">
        <thead>
          <tr>
            <th scope="col">Equipo</th>
            <th scope="col">División</th>
            <th scope="col">Esta semana</th>
            <th scope="col"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($equipos as $eq): $tirado = leEquipoTiradoEstaSemana($eq['id']); ?>
            <tr data-equipo-id="<?= leEsc($eq['id']) ?>" data-equipo-nombre="<?= leEsc($eq['nombre']) ?>">
              <td><?= leEsc($eq['nombre']) ?></td>
              <td><span class="chip"><?= leEsc($eq['division']) ?></span></td>
              <td class="estado-semana"><?= $tirado ? 'Ya tirado' : 'Pendiente' ?></td>
              <td class="acciones">
                <button class="btn btn-primary btn-sm btn-tirar" type="button" <?= $tirado ? 'disabled' : '' ?>>
                  Tirar ruleta
                </button>
                <form class="form-anular" method="post" action="admin.php?accion=anular" <?= $tirado ? '' : 'hidden' ?>>
                  <input type="hidden" name="csrf" value="<?= leEsc(leTokenCsrf()) ?>">
                  <input type="hidden" name="equipo_id" value="<?= leEsc($eq['id']) ?>">
                  <button class="btn btn-secondary btn-sm" type="submit" data-confirmar="<?= leEsc('¿Anular la tirada de esta semana de ' . $eq['nombre'] . '? Se deshacen sus lesiones y se podrá volver a tirar.') ?>">Anular tirada</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="dash-seccion">
    <h2>Lesiones activas de la temporada</h2>
    <?php
    $filasActivas = [];
    foreach ($equipos as $eq) {
        foreach (leLesionesActivasPorEquipo($eq['id'], $lesiones, $temporada) as $l) {
            $filasActivas[] = ['equipo' => $eq['nombre'], 'lesion' => $l];
        }
    }
    ?>
    <?php if ($filasActivas === []): ?>
      <p class="ayuda">No hay lesiones activas.</p>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Equipo</th>
              <th scope="col">Jugador</th>
              <th scope="col">Baja</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filasActivas as $fila): $l = $fila['lesion']; $n = (int) ($l['partidos_restantes'] ?? 0); ?>
              <tr>
                <td><?= leEsc($fila['equipo']) ?></td>
                <td><?= leEsc($l['jugador_nombre'] ?? '') ?></td>
                <td><?= !empty($l['toda_temporada']) ? 'Toda la temporada' : leEsc($n . ($n === 1 ? ' partido' : ' partidos')) ?></td>
                <td class="acciones">
                  <form method="post" action="admin.php?accion=cerrar">
                    <input type="hidden" name="csrf" value="<?= leEsc(leTokenCsrf()) ?>">
                    <input type="hidden" name="lesion_id" value="<?= leEsc($l['id'] ?? '') ?>">
                    <button class="btn btn-secondary btn-sm" type="submit" data-confirmar="<?= leEsc('¿Dar por recuperado a ' . ($l['jugador_nombre'] ?? '') . ' (' . $fila['equipo'] . ')? Su lesión se cierra ya, aunque le queden partidos.') ?>">Dar por recuperada</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php // Panel secreto (Ctrl+4): lesiones asignadas a dedo. Solo equipos que aún no han tirado. ?>
  <script type="application/json" id="manual-datos"><?= json_encode([
      'tabla' => array_map(static fn(array $f): array => ['codigo' => $f['codigo'], 'jugadores' => $f['jugadores'], 'partidos' => $f['partidos']], LE_TABLA_SECUNDARIA),
      'equipos' => array_map(static fn(array $eq): array => [
          'id' => $eq['id'], 'nombre' => $eq['nombre'],
          'jugadores' => array_map(static fn(array $j): array => ['id' => $j['id'], 'nombre' => $j['nombre']], $eq['jugadores']),
      ], $equipos),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
  <div id="panel-manual" class="confirmar" hidden>
    <form class="confirmar-caja" id="form-manual" role="dialog" aria-modal="true" aria-labelledby="manual-titulo">
      <h2 id="manual-titulo">Asignar lesión a mano</h2>
      <label class="ayuda">Equipo <select class="inp" id="manual-equipo"></select></label>
      <label class="ayuda">Resultado <select class="inp" id="manual-codigo"></select></label>
      <fieldset id="manual-jugadores" style="border:0;padding:0;max-height:40vh;overflow:auto"></fieldset>
      <div class="confirmar-botones">
        <button class="btn btn-secondary" type="button" id="manual-cancelar">Cancelar</button>
        <button class="btn btn-primary" type="submit">Aplicar y tirar</button>
      </div>
    </form>
  </div>

  <div id="modal-confirmar" class="confirmar" hidden>
    <div class="confirmar-caja" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
      <h2 id="modal-titulo">¿Tirar la ruleta de <span id="modal-equipo"></span>?</h2>
      <p class="ayuda">Esta acción es irreversible y cuenta como la tirada de la semana para este equipo.</p>
      <div class="confirmar-botones">
        <button class="btn btn-secondary" type="button" id="modal-cancelar">Cancelar</button>
        <button class="btn btn-primary" type="button" id="modal-confirmar-btn">Tirar</button>
      </div>
    </div>
  </div>

  <div id="ceremonia" class="ceremonia" hidden>
    <div class="ceremonia-caja" role="dialog" aria-modal="true" aria-labelledby="ceremonia-titulo">
      <h2 id="ceremonia-titulo" class="ceremonia-titulo"></h2>
      <?php // La tira es solo movimiento: el resultado se anuncia en el aria-live de abajo. ?>
      <div id="tira" class="tira" aria-hidden="true">
        <div id="tira-pista" class="tira-pista"></div>
        <div class="tira-marcador"></div>
      </div>
      <p id="ceremonia-resultado" class="ceremonia-resultado" aria-live="polite"></p>
      <ul id="reveladas" class="reveladas"></ul>
      <div class="ceremonia-botones">
        <button class="btn btn-secondary btn-sm" id="btn-saltar" type="button">Saltar animación</button>
        <button class="btn btn-primary" id="btn-cerrar-ceremonia" type="button" hidden>Cerrar</button>
      </div>
    </div>
  </div>
</main>
<input type="hidden" id="csrf-token" value="<?= leEsc(leTokenCsrf()) ?>">
<script src="js/ruleta.js"></script>
<script src="js/apertura.js"></script>
<script src="js/manual.js"></script>
</body>
</html>
