<?php
// dashboard/admin.php
// Panel del admin de la liga, sección Temporada: las tres transiciones de
// fase, la creación de una temporada nueva y el informe de equipos
// incompletos que se muestra ANTES de cerrar cada fase.
//
// Va en español y sin plT(): lo usa una sola persona hispanohablante, igual
// que supertecnicas/admin.php. Es una decisión tomada, no un descuido.

// El Basic Auth va antes que cualquier otra cosa: nada de la liga se calcula,
// y mucho menos se imprime, para una petición no autenticada.
require_once __DIR__ . '/../config/admin_auth.php';
requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin');

// La sesión es aparte del Basic Auth: hace falta para el token CSRF.
// !headers_sent() ademas del estado: no se puede abrir una sesion despues
// de enviar cabeceras, y el arnes de tests renderiza la pantalla cuando ya
// ha impreso. En una peticion real esta pantalla es el punto de entrada y
// nada ha salido todavia, asi que la sesion se abre igual.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Primero CSRF, antes de mirar nada más. El Basic Auth autentica, pero no
    // protege contra que otra página envíe este formulario en tu nombre.
    if (!plCsrfValido()) {
        http_response_code(403);
        exit('Token CSRF invalido.');
    }

    $temporada = plTemporadaActiva();

    if (isset($_POST['cambiar_fase'])) {
        $faseNueva = (string) ($_POST['cambiar_fase'] ?? '');
        if ($temporada === null) {
            $error = 'No hay ninguna temporada activa.';
        } else {
            $r = plCambiarFase($temporada['id'], $faseNueva);
            if ($r['ok']) {
                plRegistrarEvento('FASE', 'admin', 'admin', [
                    'temporada' => $temporada['id'],
                    'de'        => $temporada['fase'] ?? '',
                    'a'         => $faseNueva,
                ]);
                $mensaje = 'Fase cambiada a ' . $faseNueva . '.';
            } else {
                $error = 'No se pudo cambiar la fase (' . ($r['error'] ?? '') . ').';
            }
        }
    } elseif (isset($_POST['crear_temporada'])) {
        $id     = trim((string) ($_POST['temporada_id'] ?? ''));
        $nombre = trim((string) ($_POST['temporada_nombre'] ?? ''));

        // El id va a un nombre de fichero: se restringe a lo que no puede
        // escaparse del directorio de datos.
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $id)) {
            $error = 'El identificador debe tener la forma 2027-28.';
        } elseif ($nombre === '') {
            $error = 'Escribe el nombre visible de la temporada (por ejemplo 2027/28).';
        } else {
            $r = plCrearTemporada($id, $nombre);
            if ($r['ok']) {
                plRegistrarEvento('TEMPORADA', 'admin', 'admin', ['temporada' => $id, 'nombre' => $nombre]);
                $mensaje = 'Temporada ' . $nombre . ' creada, en fase ROSTER y con las plantillas vacías.';
            } else {
                $error = $r['error'] === 'error.temporada_existe'
                    ? 'Ya existe una temporada con ese identificador.'
                    : 'No se pudo crear la temporada.';
            }
        }
    }

    // POST/Redirect/GET: recargar no debe reenviar el formulario.
    $_SESSION['pl_admin_flash'] = ['mensaje' => $mensaje, 'error' => $error];
    header('Location: admin.php');
    exit;
}

// Mensaje heredado del redirect anterior, si lo hay.
if (!empty($_SESSION['pl_admin_flash'])) {
    $mensaje = (string) ($_SESSION['pl_admin_flash']['mensaje'] ?? '');
    $error   = (string) ($_SESSION['pl_admin_flash']['error'] ?? '');
    unset($_SESSION['pl_admin_flash']);
}

$temporada = plTemporadaActiva();
$fase      = plFaseActiva();
$equipos   = plCargarEquipos()['equipos'];
$activos   = array_values(array_filter($equipos, static fn($e) => !empty($e['activo'])));

// Recuento e informe de incompletos. Se calculan siempre, no solo al pulsar:
// el admin tiene que poder verlos antes de decidir si cierra la fase.
$totalJugadores = 0;
$incompletosRoster    = [];
$incompletosClausulas = [];

if ($temporada !== null) {
    $datos = plCargarTemporada($temporada['id']);

    foreach ($datos['equipos'] ?? [] as $entrada) {
        $totalJugadores += count($entrada['jugadores'] ?? []);
    }

    // El cálculo vive en dominio.php, que es puro y está probado; aquí solo
    // se traduce el id de equipo a su nombre para enseñarlo.
    $informe = plEquiposIncompletos($datos);
    foreach ($informe['roster'] as $equipoId => $d) {
        $equipo = plBuscarEquipo((string) $equipoId);
        $incompletosRoster[] = ['nombre' => (string) ($equipo['nombre'] ?? $equipoId)] + $d;
    }
    foreach ($informe['clausulas'] as $equipoId => $d) {
        $equipo = plBuscarEquipo((string) $equipoId);
        $incompletosClausulas[] = ['nombre' => (string) ($equipo['nombre'] ?? $equipoId)] + $d;
    }
}

// Etiqueta y color del badge de fase. El color nunca va solo: siempre lleva
// su texto, para que se lea igual en blanco y negro o con daltonismo.
$badges = [
    'ROSTER'    => ['texto' => 'ROSTER',    'clase' => 'fase-roster'],
    'CLAUSULAS' => ['texto' => 'CLÁUSULAS', 'clase' => 'fase-clausulas'],
    'MERCADO'   => ['texto' => 'MERCADO',   'clase' => 'fase-mercado'],
    'CERRADA'   => ['texto' => 'CERRADA',   'clase' => 'fase-cerrada'],
];
$badge = $badges[$fase] ?? $badges['CERRADA'];

// Las tres transiciones del flujo, en orden. El selector libre de más abajo
// es la vía para forzar cualquier otra.
$transiciones = [
    'ROSTER'    => ['a' => 'CLAUSULAS', 'texto' => 'Cerrar inscripciones → pasar a Cláusulas'],
    'CLAUSULAS' => ['a' => 'MERCADO',   'texto' => 'Cerrar Cláusulas → abrir Mercado'],
    'MERCADO'   => ['a' => 'CERRADA',   'texto' => 'Cerrar Mercado → fin de temporada'],
];
$siguiente = $transiciones[$fase] ?? null;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin · Dashboard de plantillas</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
<main class="wrap" id="contenido" style="padding-block:2.5rem;display:grid;gap:1.5rem">

  <header style="display:flex;flex-wrap:wrap;gap:1rem;align-items:baseline;justify-content:space-between">
    <div>
      <h1 style="margin:0">Admin · Dashboard de plantillas</h1>
      <p class="ayuda" style="margin:.35rem 0 0">
        Controla la fase de la temporada y crea la siguiente. Los presidentes
        editan lo que la fase permita en cada momento.
      </p>
    </div>
    <span class="fase <?= plEsc($badge['clase']) ?>"><?= plEsc($badge['texto']) ?></span>
  </header>

  <?php if ($mensaje !== ''): ?>
    <p class="banda-ok"><?= plEsc($mensaje) ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="mal"><?= plEsc($error) ?></p>
  <?php endif; ?>

  <?php if ($temporada === null): ?>

    <section class="card" style="padding:1.25rem">
      <h2 style="margin-top:0">Todavía no hay ninguna temporada</h2>
      <p class="ayuda">
        Crea la primera abajo. Se abrirá en fase ROSTER, con la plantilla vacía
        para cada uno de los <?= count($activos) ?> equipos activos.
      </p>
    </section>

  <?php else: ?>

    <section class="card" style="padding:1.25rem;display:grid;gap:1rem">
      <div style="display:flex;flex-wrap:wrap;gap:2rem">
        <div>
          <div class="ayuda">Temporada</div>
          <div class="cifra"><?= plEsc($temporada['nombre'] ?? $temporada['id']) ?></div>
        </div>
        <div>
          <div class="ayuda">Equipos</div>
          <div class="cifra"><?= count($activos) ?></div>
        </div>
        <div>
          <div class="ayuda">Jugadores inscritos</div>
          <div class="cifra"><?= $totalJugadores ?></div>
        </div>
      </div>
    </section>

    <?php
      // El informe se enseña justo antes de la transición que le corresponde:
      // avisa, y deja continuar. El admin manda.
      $aviso = null;
      if ($fase === 'ROSTER' && $incompletosRoster !== []) {
          $aviso = ['titulo' => 'Equipos que aún no han completado su plantilla', 'filas' => array_map(
              static fn($e) => $e['nombre'] . ' — ' . $e['n'] . ' / ' . $e['max'] . ' jugadores',
              $incompletosRoster)];
      } elseif ($fase === 'CLAUSULAS' && $incompletosClausulas !== []) {
          $aviso = ['titulo' => 'Equipos cuyas cláusulas no suman el presupuesto exacto', 'filas' => array_map(
              static fn($e) => $e['nombre'] . ' — ' . $e['suma'] . 'M de ' . $e['presupuesto'] . 'M',
              $incompletosClausulas)];
      }
    ?>
    <?php if ($aviso !== null): ?>
      <section class="card" style="padding:1.25rem">
        <h2 style="margin-top:0;font-size:1rem"><?= plEsc($aviso['titulo']) ?></h2>
        <p class="ayuda">
          Puedes cerrar la fase de todas formas: esto es un aviso, no un
          bloqueo. Los equipos de esta lista se quedarán como están.
        </p>
        <ul class="lista-avisos">
          <?php foreach ($aviso['filas'] as $fila): ?>
            <li><?= plEsc($fila) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="card" style="padding:1.25rem;display:grid;gap:1rem">
      <h2 style="margin:0;font-size:1rem">Fase de la temporada</h2>

      <?php if ($siguiente !== null): ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
          <button class="btn btn-accent" type="submit" name="cambiar_fase" value="<?= plEsc($siguiente['a']) ?>">
            <?= plEsc($siguiente['texto']) ?>
          </button>
        </form>
      <?php else: ?>
        <p class="ayuda">La temporada está cerrada. Crea la siguiente abajo.</p>
      <?php endif; ?>

      <form method="post" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:end">
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <label class="campo">
          <span>Forzar cualquier fase</span>
          <select class="inp" name="cambiar_fase">
            <?php foreach (PL_FASES as $f): ?>
              <option value="<?= plEsc($f) ?>" <?= $f === $fase ? 'selected' : '' ?>><?= plEsc($f) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn-secondary" type="submit">Aplicar</button>
      </form>
      <p class="ayuda">
        Forzar una fase salta el orden, pero no las validaciones: el máximo de
        20 jugadores, el Salary Cap de 250M y los 650M de cláusulas se siguen
        comprobando también para ti.
      </p>
    </section>

  <?php endif; ?>

  <section class="card" style="padding:1.25rem">
    <h2 style="margin-top:0;font-size:1rem">Empezar nueva temporada</h2>
    <form method="post" id="form-temporada" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:end">
      <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
      <input type="hidden" name="crear_temporada" value="1">
      <label class="campo">
        <span>Identificador</span>
        <input class="inp inp-mono" type="text" name="temporada_id" placeholder="2027-28" required>
      </label>
      <label class="campo">
        <span>Nombre visible</span>
        <input class="inp" type="text" name="temporada_nombre" placeholder="2027/28" required>
      </label>
      <button class="btn btn-accent" type="button" id="btn-nueva-temporada">+ Empezar nueva temporada</button>
    </form>
    <p class="ayuda" style="margin-bottom:0">
      No se copia nada de la temporada anterior: ni jugadores, ni cláusulas, ni
      posiciones, ni tiers. Los salarios de los tiers se congelan tal y como
      estén en el momento de crearla.
    </p>
  </section>

  <dialog id="dlg-temporada" class="modal">
    <h2 style="margin-top:0;font-size:1rem">¿Empezar nueva temporada?</h2>
    <p>Se crearán plantillas vacías para todos los equipos.</p>
    <p>Los presidentes tendrán que volver a inscribir a sus jugadores.</p>
    <div style="display:flex;gap:.6rem;justify-content:flex-end;margin-top:1rem">
      <button class="btn btn-secondary" type="button" id="dlg-cancelar">Cancelar</button>
      <button class="btn btn-accent" type="button" id="dlg-confirmar">Empezar temporada</button>
    </div>
  </dialog>

</main>

<script>
// El modal es una confirmación, no una puerta: si <dialog> no estuviera
// disponible, el botón envía el formulario igual.
(function () {
  'use strict';
  var form = document.getElementById('form-temporada');
  var btn  = document.getElementById('btn-nueva-temporada');
  var dlg  = document.getElementById('dlg-temporada');
  if (!form || !btn) { return; }

  if (!dlg || typeof dlg.showModal !== 'function') {
    btn.addEventListener('click', function () { form.submit(); });
    return;
  }
  btn.addEventListener('click', function () {
    if (!form.reportValidity()) { return; }
    dlg.showModal();
  });
  document.getElementById('dlg-cancelar').addEventListener('click', function () { dlg.close(); });
  document.getElementById('dlg-confirmar').addEventListener('click', function () { form.submit(); });
})();
</script>
</body>
</html>
