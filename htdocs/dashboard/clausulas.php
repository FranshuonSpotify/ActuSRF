<?php
// dashboard/clausulas.php
// Reparto de los 650M de cláusulas entre los jugadores inscritos. Solo se edita
// en fase CLAUSULAS. Nunca se puede pasar de 650M; quedarse por debajo sí se
// guarda —es un borrador— y el equipo cuenta como completo solo con el total
// exacto.

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
$editable  = $enTemp !== null && plPuedeEditarClausulas($fase);

$error = '';
// Los valores tecleados, para devolvérselos al presidente si el guardado se
// rechaza. Perder un reparto de veinte cifras por pasarse de un millón sería
// la clase de fricción que hace volver a la hoja de cálculo.
$tecleado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $recibido = $_POST['clausula'] ?? [];
    $tecleado = [];
    if (is_array($recibido)) {
        foreach ($recibido as $id => $valor) {
            // Un campo vaciado cuenta como 0: borrar el número de un jugador
            // significa "a este no le pongo cláusula". Un negativo o un
            // decimal no se tocan aquí; los rechaza plValidarClausulas.
            $valor = trim((string) $valor);
            $tecleado[(string) $id] = $valor === '' ? '0' : $valor;
        }
    }

    // La fase se comprueba en el camino del POST, no solo al pintar.
    if (!$editable) {
        $error = $fase === 'ROSTER' ? plT('aviso.clausulas_pronto') : plT('aviso.clausulas_cerradas');
    } else {
        $jugadores = $enTemp['jugadores'];

        // plValidarClausulas recorre SOLO los jugadores del equipo propio, así
        // que una cláusula enviada a mano para el id de un jugador de otro
        // equipo simplemente no se lee. Nunca se revalida en el cliente: el
        // contador en vivo de abajo es una comodidad, la verdad se decide aquí.
        $v = plValidarClausulas($jugadores, $tecleado, $enTemp['ajustes']);

        if (!$v['ok']) {
            $error = plT($v['error'], $v['datos']);
        } else {
            foreach ($jugadores as $i => $j) {
                $jugadores[$i]['clausula'] = (int) ($tecleado[(string) ($j['id'] ?? '')] ?? 0);
            }
            $g = plGuardarEquipoTemporada($temporada['id'], $equipoId, ['jugadores' => $jugadores], (int) ($_POST['rev'] ?? -1));
            if ($g['ok']) {
                $_SESSION['pl_flash'] = $v['datos']['estado'] === 'COMPLETO'
                    ? plT('clausulas.guardado_completo')
                    : plT('clausulas.guardado_borrador', ['cifra' => plM($v['datos']['disponible'])]);
                plRedirigir('clausulas.php');
            }
            $error = plT($g['error'] ?? 'error.escritura');
            $enTemp = plEquipoEnTemporada($temporada['id'], $equipoId);
        }
    }
}

$flash = (string) ($_SESSION['pl_flash'] ?? '');
unset($_SESSION['pl_flash']);

$jugadores   = $enTemp['jugadores'] ?? [];
$presupuesto = (int) ($enTemp['ajustes']['presupuestoClausulas'] ?? 650);
$rev         = (int) ($enTemp['rev'] ?? 0);

// Lo que se pinta en cada campo: lo tecleado si hubo un rechazo, lo guardado
// si no. El total de la cabecera se calcula sobre lo mismo que se ve.
$valorDe = static function (array $j) use ($tecleado): string {
    $id = (string) ($j['id'] ?? '');
    return $tecleado !== null && isset($tecleado[$id]) ? $tecleado[$id] : (string) (int) ($j['clausula'] ?? 0);
};
$asignado = 0;
foreach ($jugadores as $j) {
    $asignado += (int) $valorDe($j);
}
$disponible = $presupuesto - $asignado;
$estado     = plEstadoPresupuesto($asignado, $presupuesto);
$porcentaje = $presupuesto > 0 ? min(100, (int) round($asignado * 100 / $presupuesto)) : 0;
$claseBarra = ['INCOMPLETO' => 'barra-parcial', 'COMPLETO' => 'barra-completa', 'EXCEDIDO' => 'barra-excedida'][$estado];

// El estado se escribe SIEMPRE en texto, además del color de la barra.
$textoEstado = static function (string $estado, int $disponible): string {
    return match ($estado) {
        'COMPLETO'  => plT('clausulas.completo'),
        'EXCEDIDO'  => plT('clausulas.excedido', ['cifra' => plM(-$disponible)]),
        default     => plT('clausulas.incompleto', ['cifra' => plM($disponible)]),
    };
};

plCabecera(plT('clausulas.titulo'), 'clausulas', $fase);
?>
<header class="dash-cabecera">
  <h1><?= plEsc(plT('clausulas.titulo')) ?></h1>
</header>

<?php if ($flash !== ''): ?>
  <p class="banda-ok" role="status"><?= plEsc($flash) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($error) ?></p>
<?php endif; ?>

<?php if ($equipoId === ''): ?>
  <p class="mal"><?= plEsc(plT('aviso.sin_equipo')) ?></p>
<?php elseif ($temporada === null): ?>
  <p class="ayuda"><?= plEsc(plT('aviso.sin_temporada')) ?></p>
<?php elseif ($enTemp === null): ?>
  <p class="mal"><?= plEsc(plT('aviso.equipo_fuera')) ?></p>
<?php else: ?>

  <?php if (!$editable && $error === ''): ?>
    <p class="aviso" role="status">
      <?= plEsc($fase === 'ROSTER' ? plT('aviso.clausulas_pronto') : plT('aviso.clausulas_cerradas')) ?>
    </p>
  <?php endif; ?>

  <section class="card dash-presupuesto" id="presupuesto"
           data-presupuesto="<?= plEsc($presupuesto) ?>"
           data-txt-completo="<?= plEsc(plT('clausulas.completo')) ?>"
           data-txt-incompleto="<?= plEsc(plT('clausulas.incompleto')) ?>"
           data-txt-excedido="<?= plEsc(plT('clausulas.excedido')) ?>">
    <dl class="dash-cifras">
      <div><dt><?= plEsc(plT('clausulas.presupuesto')) ?></dt><dd class="cifra"><?= plEsc(plM($presupuesto)) ?></dd></div>
      <div><dt><?= plEsc(plT('clausulas.asignado')) ?></dt><dd class="cifra" id="asignado"><?= plEsc(plM($asignado)) ?></dd></div>
      <div><dt><?= plEsc(plT('clausulas.disponible')) ?></dt><dd class="cifra" id="disponible"><?= plEsc(plM($disponible)) ?></dd></div>
    </dl>
    <div class="barra <?= plEsc($claseBarra) ?>" id="barra" role="presentation"><i style="width:<?= plEsc($porcentaje) ?>%"></i></div>
    <p class="estado-presupuesto" id="estado" aria-live="polite"><?= plEsc($textoEstado($estado, $disponible)) ?></p>
  </section>

  <?php if ($jugadores === []): ?>
    <p class="ayuda"><?= plEsc(plT('clausulas.sin_jugadores')) ?></p>
  <?php else: ?>
    <form method="post" action="clausulas.php" id="form-clausulas">
      <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
      <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col"><?= plEsc(plT('tabla.jugador')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.tier')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.salario')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.clausula')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores as $j): $id = (string) ($j['id'] ?? ''); ?>
              <tr>
                <td><?= plEsc($j['nombre'] ?? '') ?></td>
                <td class="cifra"><?= plEsc($j['tier'] ?? '') ?></td>
                <td class="cifra"><?= plEsc(plM((int) ($j['salario'] ?? 0))) ?></td>
                <td>
                  <label class="sr-only" for="c-<?= plEsc($id) ?>"><?= plEsc(plT('clausulas.campo_de', ['jugador' => $j['nombre'] ?? ''])) ?></label>
                  <input class="inp inp-sm inp-mono campo-clausula" id="c-<?= plEsc($id) ?>" type="number" min="0" step="1" inputmode="numeric"
                         name="clausula[<?= plEsc($id) ?>]" value="<?= plEsc($valorDe($j)) ?>"<?= $editable ? '' : ' disabled' ?>>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($editable): ?>
        <p class="ayuda"><?= plEsc(plT('clausulas.nota_borrador')) ?></p>
        <button class="btn btn-accent" type="submit"><?= plEsc(plT('clausulas.guardar')) ?></button>
      <?php endif; ?>
    </form>
  <?php endif; ?>

<?php endif; ?>

<?php if ($editable): ?>
<script>
// Contador en vivo: suma los campos mientras el presidente teclea y pinta el
// total, la barra y el estado. No envía nada ni decide nada: el servidor
// revalida el reparto entero al guardar, y sin JavaScript la pantalla guarda y
// valida igual. Es una comodidad, no la validación.
(function () {
  'use strict';
  var caja = document.getElementById('presupuesto');
  var form = document.getElementById('form-clausulas');
  if (!caja || !form) { return; }

  var presupuesto = parseInt(caja.getAttribute('data-presupuesto'), 10) || 0;
  var asignado    = document.getElementById('asignado');
  var disponible  = document.getElementById('disponible');
  var barra       = document.getElementById('barra');
  var estado      = document.getElementById('estado');

  function recalcular() {
    var total = 0;
    form.querySelectorAll('.campo-clausula').forEach(function (campo) {
      var n = parseInt(campo.value, 10);
      if (!isNaN(n) && n > 0) { total += n; }
    });
    var libre = presupuesto - total;
    asignado.textContent   = total + 'M';
    disponible.textContent = libre + 'M';
    barra.firstElementChild.style.width = Math.min(100, presupuesto ? Math.round(total * 100 / presupuesto) : 0) + '%';

    var clase, texto;
    if (total === presupuesto) {
      clase = 'barra-completa';  texto = caja.getAttribute('data-txt-completo');
    } else if (total > presupuesto) {
      clase = 'barra-excedida';  texto = caja.getAttribute('data-txt-excedido').replace('{cifra}', (-libre) + 'M');
    } else {
      clase = 'barra-parcial';   texto = caja.getAttribute('data-txt-incompleto').replace('{cifra}', libre + 'M');
    }
    barra.className = 'barra ' + clase;
    estado.textContent = texto;
  }

  form.addEventListener('input', recalcular);
})();
</script>
<?php endif; ?>

<?php
plPie();
