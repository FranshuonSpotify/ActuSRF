<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$temporadaActiva = $temporadas->obtenerActiva();
$esAdmin = $permisos->esAdministrador($usuario);

$error = null;
$exito = null;

// Auditoría de seguridad (reglamento de fichajes, previa al primer mercado):
// mismo problema que se encontró y corrigió en mercado.php — estos 4
// handlers tomaban participacion_id directamente del POST sin comprobar que
// perteneciera a quien hace la petición. En "responder_contraoferta" el
// chequeo existente solo comprobaba que el participacion_id coincidiera con
// el de la contraoferta (consistencia), nunca que perteneciera de verdad a
// quien la enviaba (propiedad) — un participacion_id ajeno leído de datos
// públicos también lo pasaba.
$puedeActuarComoParticipacionDash = function (int $participacionId) use ($permisos, $usuario, $participaciones): bool {
    $participacion = $participaciones->buscarPorId($participacionId);

    return $participacion !== null && $permisos->puedeGestionarParticipacion($usuario, $participacion);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'retirar_oferta_agente_libre') {
    try {
        if (!$puedeActuarComoParticipacionDash((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para retirar esta oferta.');
        }
        $mercado->retirarOferta((int) ($_POST['jugador_id'] ?? 0), (int) ($_POST['participacion_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Oferta retirada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_oferta_agente_libre') {
    try {
        if (!$puedeActuarComoParticipacionDash((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para editar esta oferta.');
        }
        $mercado->editarOferta(
            (int) ($_POST['jugador_id'] ?? 0),
            (int) ($_POST['participacion_id'] ?? 0),
            (float) ($_POST['salario_ofertado'] ?? 0),
            (int) ($_POST['duracion_temporadas'] ?? 0),
            (int) $usuario['id']
        );
        $exito = 'Oferta actualizada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder_contraoferta') {
    try {
        $ofertaContraId = (int) ($_POST['oferta_id'] ?? 0);
        $participacionCompradoraId = (int) ($_POST['participacion_id'] ?? 0);
        if (!$puedeActuarComoParticipacionDash($participacionCompradoraId)) {
            throw new DomainException('No tienes permiso para responder a esta contraoferta.');
        }
        $ofertaContra = $traspasos->buscarOferta($ofertaContraId);
        if ($ofertaContra === null || (int) $ofertaContra['participacion_compradora_id'] !== $participacionCompradoraId) {
            throw new DomainException('Esta contraoferta no pertenece a tu club.');
        }
        $aceptarContra = ($_POST['respuesta'] ?? '') === 'aceptar';
        if ($aceptarContra) {
            ModoPruebas::bloquearSiActivo();
        }
        $traspasos->responderOferta($ofertaContraId, $aceptarContra, (int) $usuario['id']);
        $exito = $aceptarContra ? 'Contraoferta aceptada. El traspaso se ha ejecutado.' : 'Contraoferta rechazada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'retirar_oferta_traspaso') {
    try {
        if (!$puedeActuarComoParticipacionDash((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para retirar esta oferta.');
        }
        $traspasos->retirarOferta((int) ($_POST['oferta_id'] ?? 0), (int) ($_POST['participacion_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Oferta de traspaso retirada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$miParticipacion = null;
$participacionesTemporada = [];

if ($temporadaActiva !== null) {
    $participacionesTemporada = $participaciones->listarPorTemporada((int) $temporadaActiva['id']);

    if ($esAdmin) {
        $participacionModoPruebas = ModoPruebas::participacionActiva();
        if ($participacionModoPruebas !== null) {
            $miParticipacion = $participaciones->buscarPorId($participacionModoPruebas);
        }
    } else {
        foreach ($participacionesTemporada as $p) {
            if ((int) ($p['usuario_presidente_id'] ?? 0) === (int) $usuario['id']) {
                $miParticipacion = $p;
                break;
            }
        }
    }
}

// --- Datos de mi club, si presido uno ---
$club = null;
$fichasContratadas = 0;
$gastoSalarial = 0.0;
$salaryCap = 0.0;
$franquiciasDesignadas = 0;
$ofertasTraspasoRecibidas = [];
$misOfertasAgenteLibre = [];
$misOfertasTraspasoEnviadas = [];
$ventanasIgualacion = [];

$plantillaClub = [];
$saludClubDash = null;
$presidenteNombreDash = null;

if ($miParticipacion !== null) {
    $participacionId = (int) $miParticipacion['id'];
    $club = $clubes->buscarPorId($miParticipacion['club_id']);
    $fichasContratadas = $contratoRepositorio->contarActivosPorParticipacion($participacionId);
    $gastoSalarial = $contratos->gastoSalarial($participacionId);
    $salaryCap = (float) ($miParticipacion['salary_cap_override'] ?? $temporadaActiva['salary_cap']);
    $franquiciasDesignadas = $jugadores->contarFranquicia($participacionId);
    $ofertasTraspasoRecibidas = $traspasos->listarOfertasRecibidas($participacionId);
    $misOfertasAgenteLibre = $mercado->listarMisOfertas($participacionId);
    $misOfertasTraspasoEnviadas = $traspasos->listarMisOfertasEnviadas($participacionId);
    $ventanasIgualacion = $mercado->listarVentanasIgualacionDeClub($miParticipacion['club_id']);
    $plantillaClub = $contratoRepositorio->listarPorParticipacion($participacionId);
    $saludClubDash = SaludClubCalculadora::calcular($fichasContratadas, $gastoSalarial, $salaryCap);
    $presidenteNombreDash = $usuario['nombre'];

    // Reparto de gasto salarial por posición: para saber de un vistazo dónde
    // se va el Salary Cap, no solo cuánto queda.
    $gastoPorPosicionDash = ['POR' => 0.0, 'DEF' => 0.0, 'MED' => 0.0, 'DEL' => 0.0];
    foreach ($plantillaClub as $j) {
        if (isset($gastoPorPosicionDash[$j['posicion']])) {
            $gastoPorPosicionDash[$j['posicion']] += (float) $j['salario_anual'];
        }
    }
    $misContratosPendientesRevision = array_values(array_filter($plantillaClub, fn ($j) => (bool) $j['pendiente_revision']));
    $notificacionesRecientesDash = array_slice($notificaciones->listarPorUsuario((int) $usuario['id']), 0, 5);
    $noLeidasDash = $notificaciones->contarNoLeidas((int) $usuario['id']);
}

// --- Datos globales para el administrador ---
$participacionesSinPresidente = [];
$pendientesRevisionGlobal = [];
$usuariosGlobalDash = [];
$auditoriaRecienteDash = [];
$mantenimientoActivoDash = false;
if ($esAdmin) {
    $participacionesSinPresidente = array_filter($participacionesTemporada, fn ($p) => empty($p['usuario_presidente_id']));
    $pendientesRevisionGlobal = $contratos->listarPendientesDeRevision();
    $usuariosGlobalDash = $usuarioRepositorio->listarTodos();
    $auditoriaRecienteDash = array_slice($auditoria->listarRecientes(), 0, 6);
    $mantenimientoActivoDash = $configuracion->obtenerBool('modo_mantenimiento_activo');
}
$paginaTitulo = 'Panel';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'resumen';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert" style="margin-bottom:1rem"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success" style="margin-bottom:1rem"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ($temporadaActiva === null): ?>
        <div class="page-head">
            <div>
                <span class="overline">Panel</span>
                <h1 class="h1" style="margin-top:.5rem">Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?></h1>
            </div>
        </div>
        <div class="empty-state">
            <i class="ph ph-calendar-blank"></i>
            <h3>Todavía no existe ninguna temporada</h3>
            <?php if ($esAdmin): ?>
                <p>Crea la primera temporada para empezar a gestionar la liga.</p>
                <a href="admin/temporadas.php" class="btn btn-primary btn-sm">Crear temporada</a>
            <?php else: ?>
                <p>Contacta con la administración.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($miParticipacion !== null): ?>
            <!-- ============ CABECERA DE CLUB (estilo Football Manager) ============ -->
            <div class="card con-spotlight con-filo" style="margin-bottom:2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;background:linear-gradient(135deg,var(--surface),var(--surface-2))">
                <?php if (!empty($club['escudo_url'])): ?>
                    <img referrerpolicy="no-referrer" class="escudo-xl" src="<?= htmlspecialchars($club['escudo_url']) ?>" alt="Escudo de <?= htmlspecialchars($club['nombre']) ?>" loading="lazy">
                <?php else: ?>
                    <span class="escudo-xl" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-shield" style="font-size:2.5rem"></i></span>
                <?php endif; ?>
                <div style="flex:1;min-width:220px">
                    <span class="overline"><?= htmlspecialchars($temporadaActiva['nombre']) ?> · <?= htmlspecialchars($miParticipacion['division']) ?></span>
                    <h1 class="h1" style="margin-top:.3rem"><?= htmlspecialchars($club['nombre']) ?></h1>
                    <p class="caption" style="margin-top:.3rem"><i class="ph ph-user-circle"></i> Presidente: <?= htmlspecialchars($presidenteNombreDash) ?></p>
                </div>
                <?php if ($saludClubDash !== null): ?>
                    <div class="tt" data-tt="<?= htmlspecialchars($saludClubDash['motivo']) ?>" style="text-align:center;padding:0 1rem">
                        <span aria-hidden="true" style="width:16px;height:16px;border-radius:50%;display:inline-block;background:<?= ['verde' => 'var(--success,#3ddc9b)', 'ambar' => 'var(--warning,#f2b134)', 'rojo' => 'var(--danger,#f0554a)'][$saludClubDash['estado']] ?>"></span>
                        <div class="caption" style="margin-top:.4rem">Salud del club</div>
                    </div>
                <?php endif; ?>
                <a href="plantilla.php?participacion_id=<?= (int) $miParticipacion['id'] ?>" class="btn btn-primary"><i class="ph ph-users-three"></i> Ver mi plantilla</a>
            </div>

            <div style="margin-bottom:1.5rem">
                <?php if ($fichasContratadas !== 20): ?>
                    <div class="alert alert-warning" role="alert"><i class="ph ph-warning"></i> Plantilla incompleta: <?= $fichasContratadas ?>/20 fichas. La temporada no podrá pasar a competición hasta completarla.</div>
                <?php endif; ?>
                <?php if ($gastoSalarial > $salaryCap * 0.9): ?>
                    <div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> Salary Cap casi al límite.</div>
                <?php endif; ?>
            </div>

            <!-- ============ NECESITA TU ATENCIÓN ============ -->
            <?php
                $pendientesAtencionDash = array_filter([
                    ['n' => count($ofertasTraspasoRecibidas), 'texto' => 'oferta(s) de traspaso recibidas', 'icono' => 'ph-arrows-left-right', 'href' => 'plantilla.php?participacion_id=' . $participacionId, 'clase' => 'badge-info'],
                    ['n' => count($ventanasIgualacion), 'texto' => 'ventana(s) RFA por igualar', 'icono' => 'ph-hourglass-medium', 'href' => 'mercado.php', 'clase' => 'badge-warning'],
                    ['n' => count($misContratosPendientesRevision), 'texto' => 'contrato(s) tuyos pendientes de revisión', 'icono' => 'ph-file-magnifying-glass', 'href' => 'plantilla.php?participacion_id=' . $participacionId, 'clase' => 'badge-info'],
                ], fn ($a) => $a['n'] > 0);
            ?>
            <?php if ($pendientesAtencionDash !== []): ?>
                <div class="card" style="margin-bottom:1.5rem;border-left:3px solid var(--warning,#f2b134)">
                    <span class="caption" style="text-transform:uppercase;letter-spacing:.05em"><i class="ph ph-bell-ringing"></i> Necesita tu atención</span>
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.75rem">
                        <?php foreach ($pendientesAtencionDash as $item): ?>
                            <a href="<?= htmlspecialchars($item['href']) ?>" class="badge <?= $item['clase'] ?>" style="font-size:.85rem;padding:.5rem .85rem"><i class="ph <?= $item['icono'] ?>"></i> <?= $item['n'] ?> <?= htmlspecialchars($item['texto']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ============ ESTADÍSTICAS CLAVE ============
                 Cada tarjeta enlaza a la página donde de verdad se gestiona
                 ese dato (a petición de Franshu) — .card-interactivo ya
                 existe en el sistema (lift + sombra al pasar el ratón), solo
                 hacía falta convertir el div en enlace. -->
            <div class="grid-3" style="margin-bottom:1.5rem">
                <a href="plantilla.php?participacion_id=<?= $participacionId ?>" class="card card-interactivo">
                    <span class="caption">Fichas</span>
                    <div class="h1 mono" style="margin-top:.35rem"><?= $fichasContratadas ?><span class="caption">/20</span></div>
                </a>
                <a href="finanzas.php" class="card card-interactivo" style="grid-column:span 1">
                    <span class="caption">Salary Cap</span>
                    <div class="h3 mono" style="margin-top:.35rem"><?= number_format($gastoSalarial, 0, ',', '.') ?> € <span class="caption">/ <?= number_format($salaryCap, 0, ',', '.') ?> €</span></div>
                    <?php $porcentajeCapDash = $salaryCap > 0 ? min(100, $gastoSalarial / $salaryCap * 100) : 0; ?>
                    <div class="cap-tracker" style="margin-top:.5rem"><div class="cap-tracker-fill <?= $porcentajeCapDash >= 95 ? 'peligro' : ($porcentajeCapDash >= 80 ? 'aviso' : '') ?>" style="width:<?= $porcentajeCapDash ?>%"></div></div>
                </a>
                <a href="mercado.php" class="card card-interactivo">
                    <span class="caption">Dinero de traspasos</span>
                    <div class="h1 mono" style="margin-top:.35rem"><?= number_format($financiero->saldoActual((int) $miParticipacion['id']), 0, ',', '.') ?> €</div>
                </a>
                <a href="franquicias.php" class="card card-interactivo">
                    <span class="caption">Franquicias designadas</span>
                    <div class="h1 mono" style="margin-top:.35rem"><?= $franquiciasDesignadas ?><span class="caption">/4</span></div>
                </a>
                <a href="mercado.php" class="card card-interactivo">
                    <span class="caption">Ofertas de traspaso recibidas</span>
                    <div class="h1 mono" style="margin-top:.35rem"><?= count($ofertasTraspasoRecibidas) ?></div>
                </a>
                <a href="mercado.php" class="card card-interactivo">
                    <span class="caption">Ventanas RFA abiertas sobre tus jugadores</span>
                    <div class="h1 mono" style="margin-top:.35rem"><?= count($ventanasIgualacion) ?></div>
                </a>
            </div>

            <!-- ============ SALUD DEL CLUB + GASTO POR POSICIÓN ============ -->
            <div class="grid-2" style="margin-bottom:2rem;gap:1.5rem">
                <?php if ($saludClubDash !== null): ?>
                    <?php $colorSaludDashCard = ['verde' => 'var(--success,#3ddc9b)', 'ambar' => 'var(--warning,#f2b134)', 'rojo' => 'var(--danger,#f0554a)'][$saludClubDash['estado']]; ?>
                    <a href="plantilla.php?participacion_id=<?= $participacionId ?>" class="card card-interactivo" style="display:flex;gap:1rem;align-items:flex-start">
                        <span aria-hidden="true" style="width:14px;height:14px;border-radius:50%;flex-shrink:0;margin-top:.3rem;background:<?= $colorSaludDashCard ?>;box-shadow:0 0 0 4px color-mix(in srgb, <?= $colorSaludDashCard ?> 20%, transparent)"></span>
                        <div>
                            <span class="caption" style="text-transform:uppercase;letter-spacing:.05em">Salud del club</span>
                            <div class="body-sm" style="margin-top:.35rem;color:var(--ink-2)"><?= htmlspecialchars($saludClubDash['motivo']) ?></div>
                        </div>
                    </a>
                <?php endif; ?>
                <a href="plantilla.php?participacion_id=<?= $participacionId ?>" class="card card-interactivo">
                    <span class="caption" style="text-transform:uppercase;letter-spacing:.05em">Gasto salarial por posición</span>
                    <div style="margin-top:.75rem;display:flex;flex-direction:column;gap:.5rem">
                        <?php foreach ($gastoPorPosicionDash as $posDash => $gastoDash): ?>
                            <?php $porcPosDash = $gastoSalarial > 0 ? $gastoDash / $gastoSalarial * 100 : 0; ?>
                            <div style="display:flex;align-items:center;gap:.6rem">
                                <span class="caption mono" style="width:3ch"><?= $posDash ?></span>
                                <div class="cap-tracker" style="flex:1;margin-top:0"><div class="cap-tracker-fill" style="width:<?= $porcPosDash ?>%"></div></div>
                                <span class="caption mono" style="width:9ch;text-align:right"><?= number_format($gastoDash, 0, ',', '.') ?> €</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </a>
            </div>

            <!-- ============ SNAPSHOT DE PLANTILLA (fotos grandes, estilo FM) ============ -->
            <?php if ($plantillaClub !== []): ?>
                <h2 class="h2" style="margin-bottom:1rem">Tu plantilla</h2>
                <div style="display:flex;gap:1rem;overflow-x:auto;padding-bottom:.5rem;margin-bottom:2.5rem">
                    <?php foreach ($plantillaClub as $j): ?>
                        <?php
                            $datosModalSnapshot = [
                                'tipo' => 'propio',
                                'jugadorId' => (int) $j['jugador_id'],
                                'nombre' => $j['jugador_nombre'],
                                'fotoUrl' => $j['foto_url'] ?? null,
                                'posicion' => $j['posicion'],
                                'tier' => $j['tier_nombre'],
                                'esFranquicia' => (bool) $j['es_franquicia'],
                                'contratoId' => (int) $j['id'],
                                'salarioActual' => (float) $j['salario_anual'],
                                'duracionRestante' => (int) $j['duracion_temporadas'] . ' temporada(s)',
                                'puedeGestionar' => true,
                            ];
                        ?>
                        <button type="button" onclick='TRjugador.abrirModal(<?= json_encode($datosModalSnapshot, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' style="flex-shrink:0;width:104px;text-align:center;cursor:pointer">
                            <?php if (!empty($j['foto_url'])): ?>
                                <img referrerpolicy="no-referrer" class="jugador-foto-lg" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy" style="margin:0 auto">
                            <?php else: ?>
                                <span class="jugador-foto-lg" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4);margin:0 auto"><i class="ph ph-user" style="font-size:2rem"></i></span>
                            <?php endif; ?>
                            <div class="caption" style="margin-top:.4rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($j['jugador_nombre']) ?></div>
                            <span class="chip <?= tier_chip_class($j['tier_nombre']) ?>" style="font-size:.65rem"><?= htmlspecialchars($j['tier_nombre']) ?></span>
                            <?php if ($j['es_franquicia']): ?><i class="ph-fill ph-crown-simple" style="color:var(--gold);margin-left:.2rem"></i><?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($ofertasTraspasoRecibidas !== []): ?>
                <h3 class="h3" style="margin-top:2.5rem">Ofertas de traspaso recibidas (<?= count($ofertasTraspasoRecibidas) ?>)</h3>
                <div class="tbl-wrap" style="margin-top:1rem">
                    <div class="tbl-scroll">
                        <table class="tbl">
                            <thead><tr><th>Jugador</th><th>Club comprador</th><th class="num">Importe</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($ofertasTraspasoRecibidas as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['jugador_nombre']) ?></td>
                                        <td class="fila-club">
                                            <?php if (!empty($o['club_comprador_escudo'])): ?>
                                                <img class="escudo" src="<?= htmlspecialchars($o['club_comprador_escudo']) ?>" alt="" loading="lazy">
                                            <?php endif; ?>
                                            <?= htmlspecialchars($o['club_comprador_nombre']) ?>
                                        </td>
                                        <td class="num mono"><?= number_format((float) $o['importe_traspaso'], 0, ',', '.') ?> €</td>
                                        <td><a href="plantilla.php?participacion_id=<?= (int) $miParticipacion['id'] ?>" class="btn btn-sm btn-secondary">Responder</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($ventanasIgualacion !== []): ?>
                <h3 class="h3" style="margin-top:2.5rem">Ventanas de igualación abiertas</h3>
                <p class="caption" style="margin-top:.25rem">Jugadores tuyos que otro club se puede llevar si no igualas a tiempo.</p>
                <div class="tbl-wrap" style="margin-top:1rem">
                    <div class="tbl-scroll">
                        <table class="tbl">
                            <thead><tr><th>Jugador</th><th>Club ofertante</th><th class="num">Oferta</th><th>Plazo</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($ventanasIgualacion as $v): ?>
                                    <?php
                                        $horasRestantesDash = (strtotime((string) $v['fecha_limite_igualacion']) - time()) / 3600;
                                        $urgenciaDash = $horasRestantesDash <= 0 ? 'badge-danger' : ($horasRestantesDash < 6 ? 'badge-danger' : ($horasRestantesDash < 24 ? 'badge-warning' : 'badge-info'));
                                        $textoDash = $horasRestantesDash <= 0 ? 'Plazo vencido' : (
                                            $horasRestantesDash < 1 ? round($horasRestantesDash * 60) . ' min' : round($horasRestantesDash) . 'h'
                                        );
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['jugador_nombre']) ?></td>
                                        <td class="fila-club">
                                            <?php if (!empty($v['club_ofertante_escudo'])): ?>
                                                <img class="escudo" src="<?= htmlspecialchars($v['club_ofertante_escudo']) ?>" alt="" loading="lazy">
                                            <?php endif; ?>
                                            <?= htmlspecialchars($v['club_ofertante_nombre']) ?>
                                        </td>
                                        <td class="num mono"><?= number_format((float) $v['salario_ofertado'], 0, ',', '.') ?> €</td>
                                        <td><span class="badge <?= $urgenciaDash ?>"><i class="ph ph-timer"></i> <?= htmlspecialchars($textoDash) ?></span></td>
                                        <td><a href="mercado.php" class="btn btn-sm btn-primary">Igualar</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($misOfertasAgenteLibre !== []): ?>
                <h3 class="h3" style="margin-top:2.5rem">Mis pujas activas en el mercado (<?= count($misOfertasAgenteLibre) ?>)</h3>
                <div class="tbl-wrap" style="margin-top:1rem">
                    <div class="tbl-scroll">
                        <table class="tbl">
                            <thead><tr><th>Jugador</th><th class="num">Salario ofertado</th><th>Estado</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($misOfertasAgenteLibre as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['jugador_nombre']) ?></td>
                                        <td class="num mono"><?= number_format((float) $o['salario_ofertado'], 0, ',', '.') ?> €</td>
                                        <td><span class="badge"><?= htmlspecialchars(etiqueta_legible($o['estado'])) ?></span></td>
                                        <td>
                                            <?php if ($o['estado'] === 'PENDIENTE'): ?>
                                                <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                                                    <form method="post" style="display:flex;gap:.3rem;align-items:center">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                                        <input type="hidden" name="accion" value="editar_oferta_agente_libre">
                                                        <input type="hidden" name="jugador_id" value="<?= (int) $o['jugador_id'] ?>">
                                                        <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
                                                        <input type="number" name="salario_ofertado" min="1" step="1" value="<?= (int) $o['salario_ofertado'] ?>" required class="input" style="height:32px;width:110px;font-size:var(--fs-caption)">
                                                        <select name="duracion_temporadas" class="select" style="height:32px;font-size:var(--fs-caption)">
                                                            <option value="1" <?= (int) $o['duracion_temporadas'] === 1 ? 'selected' : '' ?>>1 temp.</option>
                                                            <option value="2" <?= (int) $o['duracion_temporadas'] === 2 ? 'selected' : '' ?>>2 temp.</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-secondary">Editar</button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                        onclick='TRdashboard.confirmarRetirarOferta(<?= (int) $o['jugador_id'] ?>, <?= json_encode($o['jugador_nombre'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                        Retirar
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="caption">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($misOfertasTraspasoEnviadas !== []): ?>
                <h3 class="h3" style="margin-top:2.5rem">Mis ofertas de traspaso enviadas (<?= count($misOfertasTraspasoEnviadas) ?>)</h3>
                <div class="tbl-wrap" style="margin-top:1rem">
                    <div class="tbl-scroll">
                        <table class="tbl">
                            <thead><tr><th>Jugador</th><th>Club vendedor</th><th class="num">Importe</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($misOfertasTraspasoEnviadas as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['jugador_nombre']) ?></td>
                                        <td class="fila-club">
                                            <?php if (!empty($o['club_vendedor_escudo'])): ?>
                                                <img class="escudo" src="<?= htmlspecialchars($o['club_vendedor_escudo']) ?>" alt="" loading="lazy">
                                            <?php endif; ?>
                                            <?= htmlspecialchars($o['club_vendedor_nombre']) ?>
                                        </td>
                                        <td class="num mono"><?= number_format((float) $o['importe_traspaso'], 0, ',', '.') ?> €</td>
                                        <td>
                                            <?php if ($o['oferta_padre_id'] !== null): ?>
                                                <div style="display:flex;gap:.4rem;align-items:center">
                                                    <span class="badge badge-warning">Contraoferta</span>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                                        <input type="hidden" name="accion" value="responder_contraoferta">
                                                        <input type="hidden" name="oferta_id" value="<?= (int) $o['id'] ?>">
                                                        <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
                                                        <input type="hidden" name="respuesta" value="aceptar">
                                                        <button type="submit" class="btn btn-sm btn-primary">Aceptar</button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                                        <input type="hidden" name="accion" value="responder_contraoferta">
                                                        <input type="hidden" name="oferta_id" value="<?= (int) $o['id'] ?>">
                                                        <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
                                                        <input type="hidden" name="respuesta" value="rechazar">
                                                        <button type="submit" class="btn btn-sm btn-danger">Rechazar</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                                    <input type="hidden" name="accion" value="retirar_oferta_traspaso">
                                                    <input type="hidden" name="oferta_id" value="<?= (int) $o['id'] ?>">
                                                    <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Retirar</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($notificacionesRecientesDash !== []): ?>
                <h3 class="h3" style="margin-top:2.5rem">Notificaciones recientes <?php if ($noLeidasDash > 0): ?><span class="badge badge-info"><?= $noLeidasDash ?> sin leer</span><?php endif; ?></h3>
                <div class="card" style="margin-top:1rem;padding:0">
                    <?php foreach ($notificacionesRecientesDash as $i => $n): ?>
                        <a href="<?= htmlspecialchars($n['enlace'] ?? 'notificaciones.php') ?>" style="display:flex;gap:.75rem;align-items:flex-start;padding:.9rem var(--space-6);text-decoration:none;color:inherit;<?= $i > 0 ? 'border-top:1px solid var(--line)' : '' ?>">
                            <i class="ph ph-bell" style="font-size:1.1rem;margin-top:.1rem;flex-shrink:0;color:<?= $n['leida'] ? 'var(--ink-4)' : 'var(--accent-hover)' ?>"></i>
                            <span class="body-sm" style="flex:1"><?= htmlspecialchars($n['mensaje']) ?></span>
                            <span class="caption mono" style="flex-shrink:0"><?= htmlspecialchars(substr((string) $n['creado_en'], 5, 11)) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a href="notificaciones.php" class="caption" style="display:inline-block;margin-top:.6rem">Ver todas las notificaciones →</a>
            <?php endif; ?>
        <?php elseif (!$esAdmin): ?>
            <div class="empty-state">
                <i class="ph ph-shield-slash"></i>
                <h3>Todavía no presides ningún club</h3>
                <p>Contacta con la administración para que te asignen uno en esta temporada.</p>
            </div>
        <?php endif; ?>

        <?php if ($esAdmin): ?>
            <h2 class="h2" style="margin-top:3rem">Resumen de la liga</h2>

            <?php if ($mantenimientoActivoDash): ?>
                <div class="alert alert-danger" role="alert" style="margin-top:1rem"><i class="ph ph-warning-octagon"></i> Modo Mantenimiento está activo: todos los usuarios ven el banner de emergencia. <a href="admin/configuracion.php" style="color:inherit;text-decoration:underline">Desactivar</a></div>
            <?php endif; ?>

            <div class="card con-spotlight con-filo" style="margin-top:1.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <span class="caption">Temporada activa</span>
                    <div class="h3" style="margin-top:.3rem"><?= htmlspecialchars($temporadaActiva['nombre']) ?></div>
                </div>
                <span class="badge badge-info" style="font-size:.85rem"><?= htmlspecialchars(etiqueta_legible($temporadaActiva['estado'])) ?></span>
                <?php if ((bool) ($temporadaActiva['congelada'] ?? false)): ?>
                    <span class="badge badge-warning" style="font-size:.85rem"><i class="ph ph-snowflake"></i> Mercado congelado</span>
                <?php endif; ?>
                <a href="admin/temporadas.php" class="btn btn-secondary btn-sm">Gestionar temporadas</a>
            </div>

            <div class="grid-3" style="margin-top:1.5rem">
                <div class="card">
                    <span class="caption">Clubes inscritos</span>
                    <div class="h3 mono" style="margin-top:.35rem"><?= count($participacionesTemporada) ?></div>
                </div>
                <div class="card">
                    <span class="caption">Sin presidente asignado</span>
                    <div class="h3 mono" style="margin-top:.35rem"><?= count($participacionesSinPresidente) ?></div>
                    <?php if ($participacionesSinPresidente !== []): ?>
                        <p class="caption" style="margin-top:.5rem"><?= implode(', ', array_map(fn ($p) => htmlspecialchars($p['club_nombre']), $participacionesSinPresidente)) ?></p>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <span class="caption">Contratos pendientes de revisión</span>
                    <div class="h3 mono" style="margin-top:.35rem"><?= count($pendientesRevisionGlobal) ?></div>
                    <?php if ($pendientesRevisionGlobal !== []): ?>
                        <a href="admin/revision_contratos.php" class="btn btn-sm btn-secondary" style="margin-top:.5rem">Revisar</a>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <span class="caption">Usuarios totales</span>
                    <div class="h3 mono" style="margin-top:.35rem"><?= count($usuariosGlobalDash) ?></div>
                    <p class="caption" style="margin-top:.5rem"><?= count(array_filter($usuariosGlobalDash, fn ($u) => $u['rol'] === 'ADMINISTRADOR')) ?> administrador(es)</p>
                </div>
                <div class="card">
                    <span class="caption">Herramientas de administración</span>
                    <div style="display:flex;flex-direction:column;gap:.4rem;margin-top:.6rem">
                        <a href="admin/salud_liga.php" class="caption"><i class="ph ph-heartbeat"></i> Salud de la Liga</a>
                        <a href="admin/pendientes.php" class="caption"><i class="ph ph-tray"></i> Bandeja de Pendientes</a>
                        <a href="admin/auditoria.php" class="caption"><i class="ph ph-list-magnifying-glass"></i> Auditoría completa</a>
                    </div>
                </div>
            </div>

            <?php if ($auditoriaRecienteDash !== []): ?>
                <h3 class="h3" style="margin-top:2.5rem">Actividad reciente</h3>
                <div class="card" style="margin-top:1rem;padding:0">
                    <?php foreach ($auditoriaRecienteDash as $i => $a): ?>
                        <div style="display:flex;gap:.75rem;align-items:baseline;padding:.75rem var(--space-6);<?= $i > 0 ? 'border-top:1px solid var(--line)' : '' ?>">
                            <span class="mono caption" style="flex-shrink:0;width:11ch"><?= htmlspecialchars(substr((string) $a['creado_en'], 0, 16)) ?></span>
                            <span class="body-sm" style="flex:1"><strong><?= htmlspecialchars($a['usuario_nombre'] ?? 'Sistema') ?></strong> — <?= htmlspecialchars(etiqueta_legible($a['accion'])) ?> <span class="caption"><?= htmlspecialchars($a['entidad']) ?><?= $a['entidad_id'] !== null ? ' #' . htmlspecialchars((string) $a['entidad_id']) : '' ?></span></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="admin/auditoria.php" class="caption" style="display:inline-block;margin-top:.6rem">Ver auditoría completa →</a>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Modal de confirmación: retirar puja de agente libre -->
<div class="modal-bg" id="modal-retirar-oferta">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-retirar-oferta-title">
        <div class="modal-head">
            <h2 id="modal-retirar-oferta-title">¿Retirar esta puja?</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <span id="modal-retirar-oferta-texto"></span> Otro club podría ganar el fichaje mientras tanto.
        </div>
        <form method="post" id="form-retirar-oferta">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="retirar_oferta_agente_libre">
            <input type="hidden" name="jugador_id" id="modal-retirar-oferta-jugador-id" value="">
            <input type="hidden" name="participacion_id" value="<?= (int) ($miParticipacion['id'] ?? 0) ?>">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-danger">Retirar puja</button>
            </div>
        </form>
    </div>
</div>

<script>
var TRdashboard = {
    confirmarRetirarOferta: function (jugadorId, nombreJugador) {
        document.getElementById('modal-retirar-oferta-jugador-id').value = jugadorId;
        document.getElementById('modal-retirar-oferta-texto').textContent = 'Vas a retirar tu puja por ' + nombreJugador + '.';
        TR.abrirModal('modal-retirar-oferta');
    }
};
</script>

<?php
    // El snapshot de plantilla de este panel solo abre el modal en modo
    // "propio" (gestionar mi jugador), nunca el formulario de oferta — no
    // necesita el selector de club que sí usan mercado.php/plantilla.php.
    $misParticipaciones = [];
    include __DIR__ . '/../partials/modal_jugador.php';
?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
