<?php
// dashboard/pegado.php
// Pegado masivo de plantilla: una línea por jugador, "Nombre;POS;TIER", con
// previsualización antes de guardar. Existe porque teclear veinte jugadores
// por equipo en formularios es más lento que la hoja de cálculo que esta app
// sustituye, y una herramienta más lenta que lo que reemplaza no se adopta.

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

$temporada = plTemporadaActiva();
$fase      = plFaseActiva();
$equipoId  = (string) ($usuario['equipoId'] ?? '');
$enTemp    = ($temporada !== null && $equipoId !== '') ? plEquipoEnTemporada($temporada['id'], $equipoId) : null;
$editable  = $enTemp !== null && plPuedeEditarPlantilla($fase);

$texto    = '';
$analisis = null;
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $texto = (string) ($_POST['texto'] ?? '');

    if (!$editable) {
        $error = plT('aviso.plantilla_cerrada');
    } else {
        // Se analiza SIEMPRE en el servidor, también al confirmar. La
        // previsualización anterior es solo lo que el presidente vio; el texto
        // viaja otra vez en el formulario de confirmación y podría no ser el
        // mismo, así que lo que manda es este análisis, no aquel.
        $analisis = plParsearPegado($texto, $enTemp['jugadores'], $enTemp['ajustes']);

        if (($_POST['accion'] ?? '') === 'confirmar' && $analisis['ok']) {
            $jugadores = $enTemp['jugadores'];
            foreach ($analisis['lineas'] as $l) {
                $jugadores[] = [
                    'id'            => plNuevoIdJugador(),
                    'nombre'        => $l['nombre'],
                    'posicion'      => $l['posicion'],
                    'tier'          => $l['tier'],
                    'salario'       => $l['salario'],
                    'clausula'      => 0,
                    'estado'        => 'DISPONIBLE',
                    'clausuladoPor' => null,
                    'clausuladoEn'  => null,
                ];
            }

            // Una sola escritura para todo el lote: o entran los veinte o no
            // entra ninguno, y el rev sube exactamente una vez.
            $g = plGuardarEquipoTemporada($temporada['id'], $equipoId, ['jugadores' => $jugadores], (int) ($_POST['rev'] ?? -1));
            if ($g['ok']) {
                $_SESSION['pl_flash'] = plT('pegado.guardado', ['n' => $analisis['validas']]);
                plRedirigir('plantilla.php');
            }
            $error = plT($g['error'] ?? 'error.escritura');

            // Tras un rechazo, el análisis se rehace contra el estado real del
            // disco: si fue un rev desfasado, el copresidente pudo cambiarlo.
            $enTemp   = plEquipoEnTemporada($temporada['id'], $equipoId);
            $analisis = plParsearPegado($texto, $enTemp['jugadores'] ?? [], $enTemp['ajustes'] ?? []);
        }
    }
}

$rev = (int) ($enTemp['rev'] ?? 0);

plCabecera(plT('pegado.titulo'), 'plantilla', $fase);
?>
<header class="dash-cabecera">
  <h1><?= plEsc(plT('pegado.titulo')) ?></h1>
  <p><a href="plantilla.php"><?= plEsc(plT('pegado.volver')) ?></a></p>
</header>

<?php if ($error !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($error) ?></p>
<?php endif; ?>

<?php if ($equipoId === ''): ?>
  <p class="mal"><?= plEsc(plT('aviso.sin_equipo')) ?></p>
<?php elseif ($temporada === null): ?>
  <p class="ayuda"><?= plEsc(plT('aviso.sin_temporada')) ?></p>
<?php elseif ($enTemp === null): ?>
  <p class="mal"><?= plEsc(plT('aviso.equipo_fuera')) ?></p>
<?php elseif (!$editable): ?>
  <?php if ($error === ''): ?>
    <p class="aviso" role="status"><?= plEsc(plT('aviso.plantilla_cerrada')) ?></p>
  <?php endif; ?>
<?php else: ?>

  <section class="card dash-seccion">
    <p class="ayuda"><?= plEsc(plT('pegado.explicacion')) ?></p>
    <p class="ayuda"><code><?= plEsc(plT('pegado.ejemplo')) ?></code></p>
    <form method="post" action="pegado.php">
      <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
      <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
      <input type="hidden" name="accion" value="previsualizar">
      <label class="campo">
        <span><?= plEsc(plT('pegado.campo')) ?></span>
        <textarea class="inp inp-mono" name="texto" rows="12" spellcheck="false"><?= plEsc($texto) ?></textarea>
      </label>
      <button class="btn btn-secondary" type="submit"><?= plEsc(plT('pegado.previsualizar')) ?></button>
    </form>
  </section>

  <?php if ($analisis !== null && $analisis['lineas'] !== []): ?>
    <section class="dash-seccion" aria-live="polite">
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col"><?= plEsc(plT('pegado.col_linea')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.jugador')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.pos')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.tier')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.salario')) ?></th>
              <th scope="col"><?= plEsc(plT('pegado.col_resultado')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($analisis['lineas'] as $l): ?>
              <tr<?= $l['error'] !== null ? ' class="con-error"' : '' ?>>
                <td class="cifra"><?= plEsc($l['n']) ?></td>
                <td><?= plEsc($l['nombre'] !== '' ? $l['nombre'] : $l['texto']) ?></td>
                <td><?= plEsc($l['posicion']) ?></td>
                <td class="cifra"><?= plEsc($l['tier']) ?></td>
                <td class="cifra"><?= $l['error'] === null ? plEsc(plM($l['salario'])) : '' ?></td>
                <td><?= plEsc($l['error'] === null ? plT('pegado.linea_ok') : plT($l['error'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="cifras">
        <?= plEsc(plT('pegado.resumen', [
            'n'        => $analisis['validas'],
            'total'    => $analisis['totalJugadores'],
            'max'      => $analisis['maximo'],
            'salarios' => plM($analisis['totalSalarios']),
            'cap'      => plM($analisis['cap']),
        ])) ?>
      </p>

      <?php if ($analisis['errores'] > 0): ?>
        <p class="mal"><?= plEsc(plT('pegado.errores_lineas', ['n' => $analisis['errores']])) ?></p>
      <?php elseif ($analisis['errorLote'] !== null): ?>
        <p class="mal"><?= plEsc(plT($analisis['errorLote'], $analisis['datosLote'])) ?></p>
      <?php elseif ($analisis['ok']): ?>
        <?php // El botón de confirmar SOLO existe si el lote entero es aceptable. ?>
        <form method="post" action="pegado.php">
          <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
          <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
          <input type="hidden" name="accion" value="confirmar">
          <input type="hidden" name="texto" value="<?= plEsc($texto) ?>">
          <button class="btn btn-accent" type="submit"><?= plEsc(plT('pegado.confirmar', ['n' => $analisis['validas']])) ?></button>
        </form>
      <?php endif; ?>
    </section>
  <?php elseif ($analisis !== null && $analisis['errorLote'] !== null): ?>
    <p class="mal"><?= plEsc(plT($analisis['errorLote'], $analisis['datosLote'])) ?></p>
  <?php endif; ?>

<?php endif; ?>

<?php
plPie();
