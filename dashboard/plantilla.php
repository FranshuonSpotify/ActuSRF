<?php
// dashboard/plantilla.php
// Mi plantilla: alta, edición y borrado de jugadores. Solo se edita en fase
// ROSTER; en el resto se sirve en solo lectura con su aviso, y el servidor
// rechaza el POST igualmente aunque el formulario se reenvíe a mano.

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

$error = '';
// Lo que el presidente escribió en el formulario de alta, para devolvérselo si
// el guardado se rechaza: perder lo tecleado por un error es peor que el error.
$borrador = ['nombre' => '', 'posicion' => 'MED', 'tier' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $accion = (string) ($_POST['accion'] ?? '');

    // La fase se comprueba AQUÍ, en el camino del POST, y no solo al decidir si
    // se pinta el formulario: ocultar el formulario es presentación, y un POST
    // reenviado a mano fuera de ROSTER tiene que rechazarse igual.
    if (!$editable) {
        $error = plT('aviso.plantilla_cerrada');
    } else {
        $jugadores = $enTemp['jugadores'];
        $ajustes   = $enTemp['ajustes'];
        $rev       = (int) ($_POST['rev'] ?? -1);
        $valido    = false;

        if ($accion === 'anadir') {
            // El salario NUNCA se lee del POST: si alguien lo manda a mano, se
            // ignora. Sale del tier, a través de ajustes.tiers de la temporada.
            $borrador = [
                'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
                'posicion' => (string) ($_POST['posicion'] ?? ''),
                'tier'     => (string) ($_POST['tier'] ?? ''),
            ];
            $v = plValidarAltaJugador($jugadores, $borrador, $ajustes);
            if ($v['ok']) {
                $jugadores[] = [
                    'id'            => plNuevoIdJugador(),
                    'nombre'        => $borrador['nombre'],
                    'posicion'      => $borrador['posicion'],
                    'tier'          => $borrador['tier'],
                    'salario'       => $v['datos']['salario'],
                    'clausula'      => 0,
                    'estado'        => 'DISPONIBLE',
                    'clausuladoPor' => null,
                    'clausuladoEn'  => null,
                ];
                $valido = true;
            } else {
                $error = plT($v['error'], $v['datos']);
            }

        } elseif ($accion === 'editar') {
            $id       = (string) ($_POST['id'] ?? '');
            $nombre   = trim((string) ($_POST['nombre'] ?? ''));
            $posicion = (string) ($_POST['posicion'] ?? '');
            $tier     = (string) ($_POST['tier'] ?? '');

            if ($nombre === '') {
                $error = plT('error.nombre_vacio');
            } elseif (!in_array($posicion, PL_POSICIONES, true)) {
                $error = plT('error.posicion_invalida');
            } else {
                // plValidarCambioTier SUSTITUYE el salario del jugador en el
                // total: bajar de tier nunca puede rechazarse por cap.
                $v = plValidarCambioTier($jugadores, $id, $tier, $ajustes);
                if ($v['ok']) {
                    foreach ($jugadores as $i => $j) {
                        if ((string) ($j['id'] ?? '') === $id) {
                            $jugadores[$i]['nombre']   = $nombre;
                            $jugadores[$i]['posicion'] = $posicion;
                            $jugadores[$i]['tier']     = $tier;
                            $jugadores[$i]['salario']  = $v['datos']['salario'];
                        }
                    }
                    $valido = true;
                } else {
                    // Mismo dato, frase distinta: aquí no se "añade" a nadie.
                    $clave = $v['error'] === 'error.cap_superado' ? 'error.cap_superado_cambio' : $v['error'];
                    $error = plT($clave, $v['datos']);
                }
            }

        } elseif ($accion === 'borrar') {
            $id    = (string) ($_POST['id'] ?? '');
            $antes = count($jugadores);
            $jugadores = array_values(array_filter(
                $jugadores,
                static fn($j) => (string) ($j['id'] ?? '') !== $id
            ));
            if (count($jugadores) === $antes) {
                $error = plT('error.jugador_no_encontrado');
            } else {
                $valido = true;
            }
        }

        if ($valido) {
            $g = plGuardarEquipoTemporada($temporada['id'], $equipoId, ['jugadores' => $jugadores], $rev);
            if ($g['ok']) {
                // POST/Redirect/GET: recargar no reenvía el formulario.
                $_SESSION['pl_flash'] = plT('plantilla.guardado');
                plRedirigir('plantilla.php');
            }
            // Un guardado que falla en silencio es el peor fallo posible aquí:
            // el presidente creería haber inscrito a un jugador que no está.
            $error = plT($g['error'] ?? 'error.escritura');
        }
    }

    // Tras un rechazo se vuelve a leer del disco, para enseñar el estado real
    // (el que dejó el copresidente si fue un rev desfasado), no el intentado.
    $enTemp = ($temporada !== null && $equipoId !== '') ? plEquipoEnTemporada($temporada['id'], $equipoId) : null;
}

$flash = (string) ($_SESSION['pl_flash'] ?? '');
unset($_SESSION['pl_flash']);

$jugadores = $enTemp['jugadores'] ?? [];
$ajustes   = $enTemp['ajustes'] ?? [];
$tiers     = $ajustes['tiers'] ?? [];
$maximo    = (int) ($ajustes['maxJugadores'] ?? 20);
$cap       = (int) ($ajustes['salaryCap'] ?? 250);
$salarios  = plTotalSalarios($jugadores);
$rev       = (int) ($enTemp['rev'] ?? 0);
$lleno     = count($jugadores) >= $maximo;

// Un <select> de tiers con el salario a la vista en cada opción. Así el
// presidente ve lo que cuesta cada tier sin que haya ningún campo de salario
// que pueda tocar: el salario es consecuencia del tier, nunca un dato.
$opcionesTier = static function (array $tiers, string $elegido): string {
    $html = '';
    foreach ($tiers as $t) {
        $codigo = (string) ($t['codigo'] ?? '');
        $html .= '<option value="' . plEsc($codigo) . '"' . ($codigo === $elegido ? ' selected' : '') . '>'
            . plEsc($codigo . ' · ' . plM((int) ($t['salario'] ?? 0))) . '</option>';
    }
    return $html;
};
$opcionesPosicion = static function (string $elegida): string {
    $html = '';
    foreach (PL_POSICIONES as $p) {
        $html .= '<option value="' . plEsc($p) . '"' . ($p === $elegida ? ' selected' : '') . '>' . plEsc($p) . '</option>';
    }
    return $html;
};

plCabecera(plT('plantilla.titulo'), 'plantilla', $fase);
?>
<header class="dash-cabecera">
  <h1><?= plEsc(plT('plantilla.titulo')) ?></h1>
  <?php if ($enTemp !== null): ?>
    <p class="cifras">
      <span class="cifra"><?= plEsc(plT('plantilla.contador', ['n' => count($jugadores), 'max' => $maximo])) ?></span>
      <span class="cifra"><?= plEsc(plT('plantilla.masa_salarial', ['total' => plM($salarios), 'cap' => plM($cap)])) ?></span>
    </p>
  <?php endif; ?>
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
    <p class="aviso" role="status"><?= plEsc(plT('aviso.plantilla_cerrada')) ?></p>
  <?php endif; ?>

  <?php if ($jugadores !== []): ?>
    <?php
      // Un <form> por fila, colocado FUERA de la tabla y enlazado desde cada
      // celda con el atributo form=: una tabla no puede contener formularios
      // directamente, y así cada fila se guarda por separado sin romper el HTML.
      // Llevan hidden porque no tienen nada visible; sus campos se envían igual.
    ?>
    <?php if ($editable): ?>
      <?php foreach ($jugadores as $j): $id = (string) ($j['id'] ?? ''); ?>
        <form id="f-<?= plEsc($id) ?>" method="post" action="plantilla.php" hidden>
          <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
          <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
          <input type="hidden" name="id" value="<?= plEsc($id) ?>">
        </form>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="tabla-scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th scope="col"><?= plEsc(plT('tabla.jugador')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.pos')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.tier')) ?></th>
            <th scope="col"><?= plEsc(plT('tabla.salario')) ?></th>
            <?php if ($editable): ?>
              <th scope="col"><span class="sr-only"><?= plEsc(plT('plantilla.acciones')) ?></span></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($jugadores as $j): $id = (string) ($j['id'] ?? ''); $pos = (string) ($j['posicion'] ?? ''); ?>
            <tr>
              <?php if ($editable): ?>
                <td>
                  <label class="sr-only" for="n-<?= plEsc($id) ?>"><?= plEsc(plT('plantilla.campo_nombre')) ?></label>
                  <input class="inp inp-sm" id="n-<?= plEsc($id) ?>" form="f-<?= plEsc($id) ?>" name="nombre" value="<?= plEsc($j['nombre'] ?? '') ?>" required maxlength="60">
                </td>
                <td>
                  <label class="sr-only" for="p-<?= plEsc($id) ?>"><?= plEsc(plT('plantilla.campo_posicion')) ?></label>
                  <select class="inp inp-sm" id="p-<?= plEsc($id) ?>" form="f-<?= plEsc($id) ?>" name="posicion"><?= $opcionesPosicion($pos) ?></select>
                </td>
                <td>
                  <label class="sr-only" for="t-<?= plEsc($id) ?>"><?= plEsc(plT('plantilla.campo_tier')) ?></label>
                  <select class="inp inp-sm inp-mono" id="t-<?= plEsc($id) ?>" form="f-<?= plEsc($id) ?>" name="tier"><?= $opcionesTier($tiers, (string) ($j['tier'] ?? '')) ?></select>
                </td>
                <td class="cifra"><?= plEsc(plM((int) ($j['salario'] ?? 0))) ?></td>
                <td class="acciones">
                  <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($id) ?>" name="accion" value="editar"><?= plEsc(plT('plantilla.guardar')) ?></button>
                  <button class="btn btn-secondary btn-sm" type="submit" form="f-<?= plEsc($id) ?>" name="accion" value="borrar"
                          data-confirmar="<?= plEsc(plT('plantilla.confirmar_borrar', ['jugador' => $j['nombre'] ?? ''])) ?>"><?= plEsc(plT('plantilla.borrar')) ?></button>
                </td>
              <?php else: ?>
                <td><?= plEsc($j['nombre'] ?? '') ?></td>
                <td><span class="chip chip-<?= plEsc(strtolower($pos)) ?>"><?= plEsc($pos) ?></span></td>
                <td class="cifra"><?= plEsc($j['tier'] ?? '') ?></td>
                <td class="cifra"><?= plEsc(plM((int) ($j['salario'] ?? 0))) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($editable): ?>
    <section class="card dash-seccion">
      <h2><?= plEsc(plT('plantilla.anadir')) ?></h2>

      <?php if ($lleno): ?>
        <p class="ayuda"><?= plEsc(plT('plantilla.completa', ['max' => $maximo])) ?></p>
      <?php else: ?>
        <form method="post" action="plantilla.php" class="dash-form-fila">
          <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
          <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
          <input type="hidden" name="accion" value="anadir">
          <label class="campo">
            <span><?= plEsc(plT('plantilla.campo_nombre')) ?></span>
            <input class="inp" name="nombre" value="<?= plEsc($borrador['nombre']) ?>" required maxlength="60">
          </label>
          <label class="campo">
            <span><?= plEsc(plT('plantilla.campo_posicion')) ?></span>
            <select class="inp" name="posicion"><?= $opcionesPosicion($borrador['posicion']) ?></select>
          </label>
          <label class="campo">
            <span><?= plEsc(plT('plantilla.campo_tier')) ?></span>
            <select class="inp inp-mono" name="tier"><?= $opcionesTier($tiers, $borrador['tier']) ?></select>
          </label>
          <button class="btn btn-primary" type="submit"><?= plEsc(plT('plantilla.anadir')) ?></button>
        </form>
        <p class="ayuda"><?= plEsc(plT('plantilla.salario_auto')) ?></p>
        <p><a href="pegado.php"><?= plEsc(plT('plantilla.pegar')) ?></a></p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php
plPie();
