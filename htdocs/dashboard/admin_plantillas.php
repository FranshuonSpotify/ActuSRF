<?php
// dashboard/admin_plantillas.php
// Admin → Plantillas y Mercado: la pantalla de arbitraje. Vista global de los
// equipos con su cap y sus cláusulas, edición completa de cualquier plantilla,
// corrección de clausulaciones, el aviso de nombres posiblemente repetidos y
// el registro de eventos con el que se arbitra una disputa.
//
// El admin puede saltarse el ORDEN de las fases —edita en cualquier fase—,
// pero no la aritmética: los 20 jugadores, los 250M y los 650M se le aplican
// igual que a un presidente. Esa validación vive en las operaciones de admin
// de almacen.php, que es donde se prueba.
//
// En español y sin pasar por la función de traducción: el panel de admin lo
// usa una sola persona. Los mensajes de error de dominio.php se enseñan con
// plTextoEs(), que devuelve siempre el texto español.

// El Basic Auth va antes que cualquier otra cosa.
require_once __DIR__ . '/../config/admin_auth.php';
requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

$temporada = plTemporadaActiva();

// La vuelta tras cada acción se construye con parámetros VALIDADOS —un id de
// equipo que existe, o la vista de mercado—, nunca con una URL que venga en el
// formulario. Una redirección abierta en un panel protegido sería un buen
// anzuelo: "entra en el admin de la liga" y acabas en otra web.
$volverA = static function (string $equipoId, string $vista): string {
    if ($vista === 'mercado') {
        return 'admin_plantillas.php?vista=mercado';
    }
    if ($equipoId !== '' && plBuscarEquipo($equipoId) !== null) {
        return 'admin_plantillas.php?' . http_build_query(['equipo' => $equipoId]);
    }
    return 'admin_plantillas.php';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, 'Token CSRF invalido.');
    }

    $accion   = (string) ($_POST['accion'] ?? '');
    $equipoId = (string) ($_POST['equipo'] ?? '');
    $id       = (string) ($_POST['id'] ?? '');
    $rev      = (int) ($_POST['rev'] ?? -1);

    if ($temporada === null) {
        $r = ['ok' => false, 'error' => null, 'datos' => [], 'mensaje' => 'No hay ninguna temporada activa.'];
    } else {
        $t = $temporada['id'];
        $r = match ($accion) {
            'alta' => plAdminAltaJugador($t, $equipoId, [
                'nombre'   => (string) ($_POST['nombre'] ?? ''),
                'posicion' => (string) ($_POST['posicion'] ?? ''),
                'tier'     => (string) ($_POST['tier'] ?? ''),
            ], $rev),
            'editar' => plAdminEditarJugador($t, $equipoId, $id, (string) ($_POST['nombre'] ?? ''),
                (string) ($_POST['posicion'] ?? ''), (string) ($_POST['tier'] ?? ''), $rev),
            'borrar' => plAdminBorrarJugador($t, $equipoId, $id, $rev),
            'clausulas' => plAdminGuardarClausulas($t, $equipoId,
                is_array($_POST['clausula'] ?? null) ? $_POST['clausula'] : [], $rev),
            'corregir' => plCorregirClausulacion($t, $equipoId, $id, (string) ($_POST['estado'] ?? ''),
                (string) ($_POST['comprador'] ?? ''), $rev),
            default => ['ok' => false, 'error' => null, 'datos' => [], 'mensaje' => 'Acción desconocida.'],
        };
    }

    $texto = ($r['mensaje'] ?? '') !== ''
        ? $r['mensaje']
        : ($r['ok'] ? 'Cambio guardado.' : plTextoEs((string) ($r['error'] ?? ''), $r['datos'] ?? []));
    $_SESSION['pl_admin_flash'] = $r['ok'] ? ['mensaje' => $texto, 'error' => ''] : ['mensaje' => '', 'error' => $texto];
    plRedirigir($volverA($equipoId, (string) ($_POST['vista'] ?? '')));
}

$flash = $_SESSION['pl_admin_flash'] ?? ['mensaje' => '', 'error' => ''];
unset($_SESSION['pl_admin_flash']);

$vista     = ($_GET['vista'] ?? '') === 'mercado' ? 'mercado' : 'plantillas';
$equipoSel = (string) ($_GET['equipo'] ?? '');

$nombreDe = [];
foreach (plCargarEquipos()['equipos'] as $e) {
    $nombreDe[(string) ($e['id'] ?? '')] = (string) ($e['nombre'] ?? '');
}
$nombre = static fn(?string $id) => $id === null || $id === '' ? '—' : ($nombreDe[$id] ?? $id);

$datos   = $temporada !== null ? plCargarTemporada($temporada['id']) : [];
$ajustes = $datos['ajustes'] ?? [];
$tiers   = $ajustes['tiers'] ?? [];
$idsTemporada = array_map('strval', array_keys($datos['equipos'] ?? []));

$opcionesTier = static function (string $elegido) use ($tiers): string {
    $html = '';
    foreach ($tiers as $t) {
        $c = (string) ($t['codigo'] ?? '');
        $html .= '<option value="' . plEsc($c) . '"' . ($c === $elegido ? ' selected' : '') . '>'
            . plEsc($c . ' · ' . plM((int) ($t['salario'] ?? 0))) . '</option>';
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
// Compradores posibles para un jugador: cualquier equipo de la temporada
// menos el suyo, que no puede clausularse a sí mismo.
$opcionesComprador = static function (string $equipoDelJugador, ?string $elegido) use ($idsTemporada, $nombre): string {
    $html = '<option value="">—</option>';
    foreach ($idsTemporada as $id) {
        if ($id === $equipoDelJugador) {
            continue;
        }
        $html .= '<option value="' . plEsc($id) . '"' . ($id === $elegido ? ' selected' : '') . '>' . plEsc($nombre($id)) . '</option>';
    }
    return $html;
};

// Formulario de corrección de clausulación de un jugador. Se usa en el
// detalle de un equipo y en la vista de mercado, con el mismo marcado.
$formCorreccion = static function (string $equipoId, int $rev, array $j, string $vista) use ($opcionesComprador): string {
    $id     = (string) ($j['id'] ?? '');
    $estado = (string) ($j['estado'] ?? 'DISPONIBLE');
    $fid    = 'k-' . md5($equipoId . '|' . $id);
    return '<form id="' . plEsc($fid) . '" method="post" action="admin_plantillas.php" class="dash-form-fila">'
        . '<input type="hidden" name="csrf" value="' . plEsc(plTokenCsrf()) . '">'
        . '<input type="hidden" name="accion" value="corregir">'
        . '<input type="hidden" name="vista" value="' . plEsc($vista) . '">'
        . '<input type="hidden" name="equipo" value="' . plEsc($equipoId) . '">'
        . '<input type="hidden" name="id" value="' . plEsc($id) . '">'
        . '<input type="hidden" name="rev" value="' . plEsc($rev) . '">'
        . '<label class="sr-only" for="' . plEsc($fid) . '-e">Estado</label>'
        . '<select class="inp inp-sm" id="' . plEsc($fid) . '-e" name="estado">'
        . '<option value="DISPONIBLE"' . ($estado === 'DISPONIBLE' ? ' selected' : '') . '>Disponible</option>'
        . '<option value="CLAUSULADO"' . ($estado === 'CLAUSULADO' ? ' selected' : '') . '>Clausulado</option>'
        . '</select>'
        . '<label class="sr-only" for="' . plEsc($fid) . '-c">Clausulado por</label>'
        . '<select class="inp inp-sm" id="' . plEsc($fid) . '-c" name="comprador">'
        . $opcionesComprador($equipoId, isset($j['clausuladoPor']) ? (string) $j['clausuladoPor'] : null)
        . '</select>'
        . '<button class="btn btn-secondary btn-sm" type="submit">Corregir</button>'
        . '</form>';
};

$estadoTexto = static fn(array $j) => ($j['estado'] ?? '') === 'CLAUSULADO'
    ? 'Clausulado por ' . $nombre((string) ($j['clausuladoPor'] ?? ''))
    : 'Disponible';

plCabeceraAdmin($vista === 'mercado' ? 'Mercado' : 'Plantillas', $vista === 'mercado' ? 'mercado' : 'plantillas');
?>
<?php if (($flash['mensaje'] ?? '') !== ''): ?>
  <p class="banda-ok" role="status"><?= plEsc($flash['mensaje']) ?></p>
<?php endif; ?>
<?php if (($flash['error'] ?? '') !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($flash['error']) ?></p>
<?php endif; ?>

<?php if ($temporada === null): ?>

  <h1>Plantillas</h1>
  <p class="ayuda">Todavía no hay ninguna temporada. Créala en <a href="admin.php#temporada">Temporada</a>.</p>

<?php elseif ($vista === 'mercado'): ?>

  <header class="dash-cabecera">
    <h1>Mercado</h1>
    <p class="ayuda">
      Corrección de clausulaciones, en cualquier fase. Cada corrección queda en
      el registro de abajo, con la fecha y el estado anterior.
    </p>
  </header>

  <div class="tabla-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th scope="col">Jugador</th><th scope="col">Equipo</th><th scope="col">Tier</th>
          <th scope="col">Cláusula</th><th scope="col">Estado</th><th scope="col">Corregir</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($datos['equipos'] ?? [] as $eqId => $entrada): $eqId = (string) $eqId; ?>
          <?php foreach ($entrada['jugadores'] ?? [] as $j): ?>
            <tr<?= ($j['estado'] ?? '') === 'CLAUSULADO' ? ' class="clausulado"' : '' ?>>
              <td><?= plEsc($j['nombre'] ?? '') ?></td>
              <td><?= plEsc($nombre($eqId)) ?></td>
              <td class="cifra"><?= plEsc($j['tier'] ?? '') ?></td>
              <td class="cifra"><?= plEsc(plM((int) ($j['clausula'] ?? 0))) ?></td>
              <td><?= plEsc($estadoTexto($j)) ?></td>
              <td><?= $formCorreccion($eqId, (int) ($entrada['rev'] ?? 0), $j, 'mercado') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <section class="dash-seccion">
    <h2>Registro</h2>
    <p class="ayuda">
      Del más reciente al más antiguo. Es de solo añadir: nadie, tampoco el
      admin, puede editar ni borrar una entrada.
    </p>
    <?php $eventos = plCargarRegistroReciente(200); ?>
    <?php if ($eventos === []): ?>
      <p class="ayuda">Todavía no hay eventos.</p>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tbl">
          <thead><tr><th scope="col">Fecha</th><th scope="col">Quién</th><th scope="col">Qué</th><th scope="col">Detalle</th></tr></thead>
          <tbody>
            <?php foreach ($eventos as $ev): $d = $ev['detalle'] ?? []; ?>
              <tr>
                <td class="cifra"><?= plEsc(date('d/m/Y H:i', strtotime((string) ($ev['ts'] ?? '')) ?: 0)) ?></td>
                <td><?= plEsc(($ev['actor'] ?? '') === 'admin' ? 'admin' : ($ev['actorNombre'] ?? $ev['actor'] ?? '')) ?></td>
                <td><?= plEsc($ev['tipo'] ?? '') ?></td>
                <td><?= plEsc(match ($ev['tipo'] ?? '') {
                    'CLAUSULACION' => ($d['jugador'] ?? '') . ': ' . $nombre($d['equipoOrigen'] ?? '') . ' → ' . $nombre($d['equipoComprador'] ?? ''),
                    'CORRECCION'   => ($d['jugador'] ?? '') . ': ' . ($d['antes']['estado'] ?? '') . ' → ' . ($d['despues']['estado'] ?? '')
                                      . (($d['despues']['clausuladoPor'] ?? null) !== null ? ' por ' . $nombre($d['despues']['clausuladoPor']) : ''),
                    'FASE'         => ($d['de'] ?? '') . ' → ' . ($d['a'] ?? ''),
                    'TEMPORADA'    => 'Temporada ' . ($d['nombre'] ?? $d['temporada'] ?? '') . ' creada',
                    default        => '',
                }) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php elseif ($equipoSel !== '' && isset($datos['equipos'][$equipoSel])): ?>

  <?php
    $entrada   = $datos['equipos'][$equipoSel];
    $jugadores = $entrada['jugadores'] ?? [];
    $rev       = (int) ($entrada['rev'] ?? 0);
    $clausulas = plTotalClausulas($jugadores);
  ?>
  <header class="dash-cabecera">
    <p><a href="admin_plantillas.php">← Todas las plantillas</a></p>
    <h1><?= plEsc($nombre($equipoSel)) ?></h1>
    <p class="cifras">
      <span class="cifra"><?= count($jugadores) ?> / <?= (int) ($ajustes['maxJugadores'] ?? 20) ?> jugadores</span>
      <span class="cifra">Salarios <?= plEsc(plM(plTotalSalarios($jugadores))) ?> / <?= plEsc(plM((int) ($ajustes['salaryCap'] ?? 250))) ?></span>
      <span class="cifra">Cláusulas <?= plEsc(plM($clausulas)) ?> / <?= plEsc(plM((int) ($ajustes['presupuestoClausulas'] ?? 650))) ?></span>
    </p>
    <p class="ayuda">
      Puedes editar en cualquier fase. Los límites de 20 jugadores, 250M de
      salarios y 650M de cláusulas se aplican igual que a un presidente.
    </p>
  </header>

  <?php foreach ($jugadores as $j): $jid = (string) ($j['id'] ?? ''); ?>
    <form id="f-<?= plEsc(md5($jid)) ?>" method="post" action="admin_plantillas.php" hidden>
      <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
      <input type="hidden" name="equipo" value="<?= plEsc($equipoSel) ?>">
      <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
      <input type="hidden" name="id" value="<?= plEsc($jid) ?>">
    </form>
  <?php endforeach; ?>

  <section class="dash-seccion">
    <h2>Plantilla</h2>
    <?php if ($jugadores === []): ?>
      <p class="ayuda">Este equipo no tiene jugadores inscritos.</p>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Jugador</th><th scope="col">Pos.</th><th scope="col">Tier</th>
              <th scope="col">Salario</th><th scope="col"><span class="sr-only">Acciones</span></th>
              <th scope="col">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores as $j): $jid = (string) ($j['id'] ?? ''); $f = 'f-' . md5($jid); ?>
              <tr>
                <td>
                  <label class="sr-only" for="n-<?= plEsc($f) ?>">Nombre</label>
                  <input class="inp inp-sm" id="n-<?= plEsc($f) ?>" form="<?= plEsc($f) ?>" name="nombre" value="<?= plEsc($j['nombre'] ?? '') ?>" required maxlength="60">
                </td>
                <td>
                  <label class="sr-only" for="p-<?= plEsc($f) ?>">Posición</label>
                  <select class="inp inp-sm" id="p-<?= plEsc($f) ?>" form="<?= plEsc($f) ?>" name="posicion"><?= $opcionesPosicion((string) ($j['posicion'] ?? '')) ?></select>
                </td>
                <td>
                  <label class="sr-only" for="t-<?= plEsc($f) ?>">Tier</label>
                  <select class="inp inp-sm inp-mono" id="t-<?= plEsc($f) ?>" form="<?= plEsc($f) ?>" name="tier"><?= $opcionesTier((string) ($j['tier'] ?? '')) ?></select>
                </td>
                <td class="cifra"><?= plEsc(plM((int) ($j['salario'] ?? 0))) ?></td>
                <td class="acciones">
                  <button class="btn btn-secondary btn-sm" type="submit" form="<?= plEsc($f) ?>" name="accion" value="editar">Guardar</button>
                  <button class="btn btn-secondary btn-sm" type="submit" form="<?= plEsc($f) ?>" name="accion" value="borrar"
                          data-confirmar="<?= plEsc('¿Borrar a ' . ($j['nombre'] ?? '') . ' de esta plantilla?') ?>">Borrar</button>
                </td>
                <td>
                  <?= plEsc($estadoTexto($j)) ?>
                  <?= $formCorreccion($equipoSel, $rev, $j, 'plantillas') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <form method="post" action="admin_plantillas.php" class="dash-form-fila">
      <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
      <input type="hidden" name="accion" value="alta">
      <input type="hidden" name="equipo" value="<?= plEsc($equipoSel) ?>">
      <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
      <label class="campo"><span>Nombre</span><input class="inp" name="nombre" required maxlength="60"></label>
      <label class="campo"><span>Posición</span><select class="inp" name="posicion"><?= $opcionesPosicion('MED') ?></select></label>
      <label class="campo"><span>Tier</span><select class="inp inp-mono" name="tier"><?= $opcionesTier('') ?></select></label>
      <button class="btn btn-secondary" type="submit">Añadir jugador</button>
    </form>
  </section>

  <?php if ($jugadores !== []): ?>
    <section class="dash-seccion">
      <h2>Cláusulas</h2>
      <form method="post" action="admin_plantillas.php">
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <input type="hidden" name="accion" value="clausulas">
        <input type="hidden" name="equipo" value="<?= plEsc($equipoSel) ?>">
        <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
        <div class="tabla-scroll">
          <table class="tbl">
            <thead><tr><th scope="col">Jugador</th><th scope="col">Cláusula</th></tr></thead>
            <tbody>
              <?php foreach ($jugadores as $j): $jid = (string) ($j['id'] ?? ''); ?>
                <tr>
                  <td><label for="cl-<?= plEsc(md5($jid)) ?>"><?= plEsc($j['nombre'] ?? '') ?></label></td>
                  <td><input class="inp inp-sm inp-mono" id="cl-<?= plEsc(md5($jid)) ?>" type="number" min="0" step="1"
                             name="clausula[<?= plEsc($jid) ?>]" value="<?= plEsc((int) ($j['clausula'] ?? 0)) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn-secondary" type="submit">Guardar cláusulas</button>
      </form>
    </section>
  <?php endif; ?>

<?php else: ?>

  <header class="dash-cabecera">
    <h1>Plantillas</h1>
    <p class="ayuda">Temporada <?= plEsc($temporada['nombre'] ?? $temporada['id']) ?>. Pulsa un equipo para editar su plantilla.</p>
  </header>

  <div class="tabla-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th scope="col">Equipo</th><th scope="col">Jugadores</th><th scope="col">Salarios</th>
          <th scope="col">Cláusulas</th><th scope="col">Estado</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($datos['equipos'] ?? [] as $eqId => $entrada): $eqId = (string) $eqId; $js = $entrada['jugadores'] ?? [];
            $cl = plTotalClausulas($js); $est = plEstadoPresupuesto($cl, (int) ($ajustes['presupuestoClausulas'] ?? 650)); ?>
          <tr>
            <td><a href="admin_plantillas.php?<?= plEsc(http_build_query(['equipo' => $eqId])) ?>"><?= plEsc($nombre($eqId)) ?></a></td>
            <td class="cifra"><?= count($js) ?> / <?= (int) ($ajustes['maxJugadores'] ?? 20) ?></td>
            <td class="cifra"><?= plEsc(plM(plTotalSalarios($js))) ?> / <?= plEsc(plM((int) ($ajustes['salaryCap'] ?? 250))) ?></td>
            <td class="cifra"><?= plEsc(plM($cl)) ?> / <?= plEsc(plM((int) ($ajustes['presupuestoClausulas'] ?? 650))) ?></td>
            <td><?= $est === 'COMPLETO' ? 'Completo' : 'Incompleto' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $duplicados = plPosiblesDuplicados($datos); ?>
  <section class="dash-seccion">
    <h2>Posibles jugadores repetidos</h2>
    <?php if ($duplicados === []): ?>
      <p class="ayuda">Ningún nombre se parece a otro.</p>
    <?php else: ?>
      <p class="ayuda">
        Es un aviso, no un error: dos nombres parecidos pueden ser dos personas
        distintas. «Igual» es el mismo nombre con otra caja o sin acentos;
        «Parecido» está a una sola letra, que es la errata típica.
      </p>
      <ul class="lista-avisos">
        <?php foreach ($duplicados as $p): ?>
          <li>
            <?= $p['tipo'] === 'igual' ? 'Igual' : 'Parecido' ?>:
            <?= plEsc($p['a']['nombre']) ?> (<?= plEsc($nombre($p['a']['equipoId'])) ?>)
            y <?= plEsc($p['b']['nombre']) ?> (<?= plEsc($nombre($p['b']['equipoId'])) ?>)
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

<?php endif; ?>

<?php
plPieAdmin();
