<?php
// dashboard/mercado.php
// El mercado: todos los jugadores de todos los equipos, consultable en
// cualquier fase. En fase MERCADO, el presidente que ha ejecutado una cláusula
// FUERA de la app la registra aquí marcando al jugador como clausulado por su
// equipo. La app no mueve jugadores ni dinero: solo deja constancia.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

plEstablecerIdioma(plResolverIdioma());

$usuario = plUsuarioActual();
if ($usuario === null) {
    plRedirigir('index.php');
}

$temporada   = plTemporadaActiva();
$fase        = plFaseActiva();
$miEquipo    = (string) ($usuario['equipoId'] ?? '');
$puedeMarcar = $temporada !== null && $miEquipo !== '' && plPuedeMarcarClausulado($fase);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $vendedor  = (string) ($_POST['equipo'] ?? '');
    $jugadorId = (string) ($_POST['jugador'] ?? '');
    // El comprador SE LEE del formulario a propósito, en vez de fijarlo al
    // equipo del presidente: así, un valor manipulado a mano llega hasta
    // plPuedeClausular() y se RECHAZA, en lugar de corregirse en silencio.
    $comprador = (string) ($_POST['comprador'] ?? '');

    // Las dos guardas se comprueban aquí, en el camino del POST. Ocultar el
    // botón es presentación: un formulario reenviado a mano tiene que
    // rechazarse igual.
    if ($temporada === null || !plPuedeMarcarClausulado($fase)) {
        $error = plT('mercado.error_fase');
    } else {
        $entrada = plEquipoEnTemporada($temporada['id'], $vendedor);
        $indice  = null;
        foreach ($entrada['jugadores'] ?? [] as $i => $j) {
            if ((string) ($j['id'] ?? '') === $jugadorId) {
                $indice = $i;
                break;
            }
        }

        if ($indice === null) {
            $error = plT('mercado.error_no_encontrado');
        } elseif (!plPuedeClausular($usuario, $entrada['jugadores'][$indice], $vendedor, $comprador)) {
            // Jugador propio, comprador ajeno o jugador ya clausulado: un
            // presidente no revierte ni reasigna una clausulación. Eso es
            // cosa del admin, que tiene su propia pantalla de corrección.
            $error = plT('mercado.error_no_permitido');
        } else {
            $jugadores = $entrada['jugadores'];
            $jugadores[$indice]['estado']        = 'CLAUSULADO';
            $jugadores[$indice]['clausuladoPor'] = $comprador;
            $jugadores[$indice]['clausuladoEn']  = plAhora();

            // El rev es el del equipo VENDEDOR, que viajó en el formulario de
            // confirmación. Es lo que impide que dos compradores registren a la
            // vez al mismo jugador: los dos lo vieron disponible, pero solo el
            // primero encuentra el rev que esperaba.
            $g = plGuardarEquipoTemporada($temporada['id'], $vendedor, ['jugadores' => $jugadores], (int) ($_POST['rev'] ?? -1));
            if ($g['ok']) {
                // El registro append-only es lo que permitirá arbitrar una
                // disputa con datos en vez de con memoria: quién marcó, cuándo.
                plRegistrarEvento('CLAUSULACION', (string) $usuario['id'], (string) ($usuario['nombre'] ?? ''), [
                    'temporada'       => $temporada['id'],
                    'jugadorId'       => $jugadorId,
                    'jugador'         => (string) ($jugadores[$indice]['nombre'] ?? ''),
                    'equipoOrigen'    => $vendedor,
                    'equipoComprador' => $comprador,
                ]);
                $_SESSION['pl_flash'] = plT('mercado.registrado', [
                    'jugador' => $jugadores[$indice]['nombre'] ?? '',
                    'equipo'  => plNombreEquipo(plBuscarEquipo($comprador), $comprador),
                ]);
                plRedirigir('mercado.php');
            }
            // Aquí un rev desfasado no es un copresidente: es otro comprador,
            // o el admin, que ha cambiado esa plantilla mientras se miraba.
            $error = ($g['error'] ?? '') === 'error.rev_desfasado'
                ? plT('mercado.error_rev')
                : plT($g['error'] ?? 'error.escritura');
        }
    }
}

$flash = (string) ($_SESSION['pl_flash'] ?? '');
unset($_SESSION['pl_flash']);

// -- el listado ------------------------------------------------------------
$datos = $temporada !== null ? plCargarTemporada($temporada['id']) : [];

$equiposPorId = [];
foreach (plCargarEquipos()['equipos'] as $e) {
    $equiposPorId[(string) ($e['id'] ?? '')] = $e;
}

$filas = [];
foreach ($datos['equipos'] ?? [] as $equipoId => $entrada) {
    foreach ($entrada['jugadores'] ?? [] as $j) {
        $filas[] = ['equipoId' => (string) $equipoId, 'rev' => (int) ($entrada['rev'] ?? 0), 'j' => $j];
    }
}

// Filtros por GET: un listado de hasta 600 filas se lee mucho mejor filtrado,
// y en la URL se puede compartir o guardar como marcador.
$fEquipo = (string) ($_GET['equipo'] ?? '');
$fEstado = (string) ($_GET['estado'] ?? '');
$fBuscar = trim((string) ($_GET['q'] ?? ''));
$busqueda = plNormalizarTexto($fBuscar);

$visibles = array_values(array_filter($filas, static function (array $f) use ($fEquipo, $fEstado, $busqueda): bool {
    if ($fEquipo !== '' && $f['equipoId'] !== $fEquipo) {
        return false;
    }
    if ($fEstado !== '' && ($f['j']['estado'] ?? 'DISPONIBLE') !== $fEstado) {
        return false;
    }
    // Comparación normalizada: "endou" encuentra a "Endou Mamoru" y "Endō".
    if ($busqueda !== '' && !str_contains(plNormalizarTexto($f['j']['nombre'] ?? ''), $busqueda)) {
        return false;
    }
    return true;
}));

$marcable = static function (array $f) use ($puedeMarcar, $miEquipo): bool {
    return $puedeMarcar
        && $f['equipoId'] !== $miEquipo
        && ($f['j']['estado'] ?? 'DISPONIBLE') === 'DISPONIBLE';
};

// -- paso de confirmación --------------------------------------------------
// La confirmación se hace en el SERVIDOR y no con un modal de JavaScript: es
// la única acción irreversible para un presidente, y con un modal un
// navegador sin JavaScript la registraría con un solo clic equivocado.
$confirmar = null;
if (isset($_GET['confirmar']) && $puedeMarcar) {
    foreach ($filas as $f) {
        if ((string) ($f['j']['id'] ?? '') === (string) $_GET['confirmar']
            && $f['equipoId'] === (string) ($_GET['equipo'] ?? '')
            && $marcable($f)) {
            $confirmar = $f;
            break;
        }
    }
}

plCabecera(plT('mercado.titulo'), 'mercado', $fase);
?>
<header class="dash-cabecera">
  <h1><?= plEsc(plT('mercado.titulo')) ?></h1>
  <p class="estado-mercado" data-estado="<?= $fase === 'MERCADO' ? 'abierto' : 'cerrado' ?>">
    <?= plEsc($fase === 'MERCADO' ? plT('aviso.mercado_abierto') : plT('aviso.mercado_cerrado')) ?>
  </p>
  <p class="ayuda"><?= plEsc(plT('mercado.nota')) ?></p>
</header>

<?php if ($flash !== ''): ?>
  <p class="banda-ok" role="status"><?= plEsc($flash) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($error) ?></p>
<?php endif; ?>

<?php if ($temporada === null): ?>
  <p class="ayuda"><?= plEsc(plT('aviso.sin_temporada')) ?></p>
<?php else: ?>

  <?php if ($confirmar !== null): ?>
    <section class="card dash-confirmar" aria-labelledby="titulo-confirmar">
      <h2 id="titulo-confirmar"><?= plEsc(plT('mercado.confirmar_titulo')) ?></h2>
      <p>
        <?= plEsc(plT('mercado.confirmar_texto', [
            'miEquipo' => plNombreEquipo($equiposPorId[$miEquipo] ?? null, $miEquipo),
            'jugador'  => $confirmar['j']['nombre'] ?? '',
            'suEquipo' => plNombreEquipo($equiposPorId[$confirmar['equipoId']] ?? null, $confirmar['equipoId']),
        ])) ?>
      </p>
      <p class="ayuda"><?= plEsc(plT('mercado.confirmar_nota')) ?></p>
      <form method="post" action="mercado.php" class="dash-form-fila">
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <input type="hidden" name="rev" value="<?= plEsc($confirmar['rev']) ?>">
        <input type="hidden" name="equipo" value="<?= plEsc($confirmar['equipoId']) ?>">
        <input type="hidden" name="jugador" value="<?= plEsc($confirmar['j']['id'] ?? '') ?>">
        <input type="hidden" name="comprador" value="<?= plEsc($miEquipo) ?>">
        <a class="btn btn-secondary" href="mercado.php"><?= plEsc(plT('mercado.cancelar')) ?></a>
        <button class="btn btn-accent" type="submit"><?= plEsc(plT('mercado.confirmar')) ?></button>
      </form>
    </section>
  <?php endif; ?>

  <form method="get" action="mercado.php" class="dash-form-fila dash-filtros">
    <label class="campo">
      <span><?= plEsc(plT('tabla.equipo')) ?></span>
      <select class="inp inp-sm" name="equipo">
        <option value=""><?= plEsc(plT('mercado.filtro_todos')) ?></option>
        <?php foreach (array_keys($datos['equipos'] ?? []) as $id): $id = (string) $id; ?>
          <option value="<?= plEsc($id) ?>"<?= $id === $fEquipo ? ' selected' : '' ?>><?= plEsc(plNombreEquipo($equiposPorId[$id] ?? null, $id)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="campo">
      <span><?= plEsc(plT('tabla.estado')) ?></span>
      <select class="inp inp-sm" name="estado">
        <option value=""><?= plEsc(plT('mercado.filtro_todos')) ?></option>
        <option value="DISPONIBLE"<?= $fEstado === 'DISPONIBLE' ? ' selected' : '' ?>><?= plEsc(plT('estado.disponible')) ?></option>
        <option value="CLAUSULADO"<?= $fEstado === 'CLAUSULADO' ? ' selected' : '' ?>><?= plEsc(plT('mercado.estado_clausulado')) ?></option>
      </select>
    </label>
    <label class="campo">
      <span><?= plEsc(plT('mercado.buscar')) ?></span>
      <input class="inp inp-sm" type="search" name="q" value="<?= plEsc($fBuscar) ?>">
    </label>
    <button class="btn btn-secondary btn-sm" type="submit"><?= plEsc(plT('mercado.filtrar')) ?></button>
  </form>

  <?php if ($visibles === []): ?>
    <p class="ayuda"><?= plEsc(plT('mercado.vacio')) ?></p>
  <?php else: ?>
    <?php // Tabla ancha dentro de su propio contenedor con scroll: con siete
          // columnas, a 375 px, sin él la página entera se desplazaría en
          // horizontal. ?>
    <div class="tabla-scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th scope="col"><?= plEsc(plT('tabla.jugador')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.equipo')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.pos')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.tier')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.salario')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.clausula')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.estado')) ?></th>
            <?php if ($puedeMarcar): ?>
              <th scope="col"><span class="sr-only"><?= plEsc(plT('plantilla.acciones')) ?></span></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($visibles as $f): $j = $f['j']; $pos = (string) ($j['posicion'] ?? ''); $propio = $f['equipoId'] === $miEquipo; ?>
            <tr class="<?= trim(($propio ? 'propio ' : '') . (($j['estado'] ?? '') === 'CLAUSULADO' ? 'clausulado' : '')) ?>">
              <td><?= plEsc($j['nombre'] ?? '') ?></td>
              <td><?= plEsc(plNombreEquipo($equiposPorId[$f['equipoId']] ?? null, $f['equipoId'])) ?></td>
              <td><span class="chip chip-<?= plEsc(strtolower($pos)) ?>"><?= plEsc($pos) ?></span></td>
              <td class="cifra"><?= plEsc($j['tier'] ?? '') ?></td>
              <td class="cifra"><?= plEsc(plM((int) ($j['salario'] ?? 0))) ?></td>
              <td class="cifra"><?= plEsc(plM((int) ($j['clausula'] ?? 0))) ?></td>
              <td><?= plEsc(plEstadoJugadorTexto($j)) ?></td>
              <?php if ($puedeMarcar): ?>
                <td class="acciones">
                  <?php if ($marcable($f)): ?>
                    <a class="btn btn-secondary btn-sm" href="mercado.php?<?= plEsc(http_build_query(['confirmar' => $j['id'] ?? '', 'equipo' => $f['equipoId']])) ?>"><?= plEsc(plT('mercado.marcar')) ?></a>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php
plPie();
