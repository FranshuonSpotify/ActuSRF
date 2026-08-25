<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$participacionId = (int) ($_GET['participacion_id'] ?? 0);
$participacion = $participaciones->buscarPorId($participacionId);

if ($participacion === null) {
    http_response_code(404);
    exit('La participación no existe.');
}

// Cualquier usuario autenticado puede VER cualquier plantilla (para poder
// valorar un traspaso); solo el dueño o un administrador puede gestionarla.
$puedeGestionar = $permisos->puedeGestionarParticipacion($usuario, $participacion);
$esAdmin = $permisos->esAdministrador($usuario);

$club = $clubes->buscarPorId($participacion['club_id']);
$temporada = $temporadas->buscarPorId((int) $participacion['temporada_id']);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'firmar_contrato' && $puedeGestionar) {
    try {
        $contratos->firmarContratoInicial(
            (int) ($_POST['jugador_id'] ?? 0),
            $participacionId,
            (int) ($_POST['tier_id'] ?? 0),
            (int) ($_POST['duracion_temporadas'] ?? 0),
            (int) $usuario['id']
        );
        $exito = 'Contrato firmado correctamente.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'finalizar_contrato' && $puedeGestionar) {
    try {
        ModoPruebas::bloquearSiActivo();
        $resultado = $contratos->finalizarContrato((int) ($_POST['contrato_id'] ?? 0), (int) $usuario['id']);
        $exito = match ($resultado['resultado']) {
            'LIBERADO' => 'Contrato finalizado. El jugador ha pasado a agente libre.',
            'DESCUENTO' => 'Ruleta de franquicia: retenido con descuento sobre su salario base.',
            'MISMO_PRECIO' => 'Ruleta de franquicia: retenido al mismo precio.',
            'SALIDA_DIRECTA' => 'Ruleta de franquicia: salida directa a agente libre sin restricciones.',
            default => 'Contrato finalizado.',
        };

        if ($resultado['resultado'] !== 'LIBERADO') {
            $notificaciones->crear(
                (int) ($participacion['usuario_presidente_id'] ?? 0) ?: null,
                'RULETA_FRANQUICIA_RESUELTA',
                $exito,
                "plantilla.php?participacion_id={$participacionId}"
            );
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'alternar_transferible' && $puedeGestionar) {
    try {
        $contratos->alternarTransferible((int) ($_POST['contrato_id'] ?? 0), $participacionId);
        $exito = 'Estado de transferible actualizado.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'proteger_franchise_clasica' && $puedeGestionar) {
    try {
        ModoPruebas::bloquearSiActivo();
        $contratos->protegerConFranchiseClasica((int) ($_POST['contrato_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Jugador protegido con la Protección Franchise clásica (+10M) por 1 temporada más.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'contraofertar_traspaso' && $puedeGestionar) {
    try {
        $ofertaOriginalId = (int) ($_POST['oferta_id'] ?? 0);
        $ofertaOriginalDatos = $traspasos->buscarOferta($ofertaOriginalId);
        $nuevaOfertaId = $traspasos->contraofertar($ofertaOriginalId, $participacionId, (float) ($_POST['nuevo_importe'] ?? 0), (int) $usuario['id']);

        if ($ofertaOriginalDatos !== null) {
            $participacionCompradoraContra = $participaciones->buscarPorId((int) $ofertaOriginalDatos['participacion_compradora_id']);
            $notificaciones->crear(
                $participacionCompradoraContra['usuario_presidente_id'] ?? null,
                'CONTRAOFERTA_TRASPASO_RECIBIDA',
                'Has recibido una contraoferta a tu oferta de traspaso.',
                'dashboard.php'
            );
        }

        $exito = 'Contraoferta enviada. Queda un registro nuevo, la oferta original no se modifica.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ofertar_traspaso') {
    try {
        $jugadorOfertado = $jugadores->buscarPorId((int) ($_POST['jugador_id'] ?? 0));
        $traspasos->ofertarTraspaso(
            (int) ($_POST['jugador_id'] ?? 0),
            (int) ($_POST['participacion_compradora_id'] ?? 0),
            (float) ($_POST['importe_traspaso'] ?? 0),
            array_map('intval', $_POST['jugadores_ofrecidos'] ?? []),
            (int) $usuario['id']
        );
        // La participación de esta página es la vendedora: se notifica a su presidente.
        $notificaciones->crear(
            (int) ($participacion['usuario_presidente_id'] ?? 0) ?: null,
            'OFERTA_TRASPASO_RECIBIDA',
            "Nueva oferta de traspaso por {$jugadorOfertado['nombre']}.",
            "plantilla.php?participacion_id={$participacionId}"
        );
        // Alertas de seguimiento (Fase 2, Scouting): avisa a quien tenga a
        // este jugador en su watchlist, salvo al propio ofertante.
        foreach ($estrategia->listarUsuariosQueSiguenA((int) $_POST['jugador_id']) as $usuarioSeguidorId) {
            if ((int) $usuarioSeguidorId === (int) $usuario['id']) {
                continue;
            }
            $notificaciones->crear((int) $usuarioSeguidorId, 'OFERTA_SOBRE_JUGADOR_SEGUIDO', "Nueva oferta de traspaso por {$jugadorOfertado['nombre']}, un jugador que sigues.", 'scouting.php');
        }
        $exito = 'Oferta de traspaso enviada. El club vendedor debe aceptarla.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ajuste_financiero' && $esAdmin) {
    try {
        $signo = ($_POST['sentido'] ?? '') === 'restar' ? -1 : 1;
        $financiero->registrarAjusteAdministrativo(
            $participacionId,
            $signo * (float) ($_POST['importe'] ?? 0),
            (string) ($_POST['motivo'] ?? ''),
            (int) $usuario['id']
        );
        $exito = 'Ajuste registrado en el libro mayor.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder_traspaso' && $puedeGestionar) {
    try {
        $ofertaId = (int) ($_POST['oferta_id'] ?? 0);
        $aceptar = ($_POST['respuesta'] ?? '') === 'aceptar';
        if ($aceptar) {
            ModoPruebas::bloquearSiActivo(); // aceptar mueve dinero y jugador: irreversible. Rechazar no.
        }
        $ofertaOriginal = $traspasos->buscarOferta($ofertaId);
        $traspasos->responderOferta($ofertaId, $aceptar, (int) $usuario['id']);

        if ($ofertaOriginal !== null) {
            $participacionCompradora = $participaciones->buscarPorId((int) $ofertaOriginal['participacion_compradora_id']);
            $notificaciones->crear(
                (int) ($participacionCompradora['usuario_presidente_id'] ?? 0) ?: null,
                $aceptar ? 'TRASPASO_ACEPTADO' : 'TRASPASO_RECHAZADO',
                $aceptar ? 'Tu oferta de traspaso ha sido aceptada.' : 'Tu oferta de traspaso ha sido rechazada.',
                'dashboard.php'
            );
        }

        $exito = 'Respuesta registrada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$jugadoresPlantilla = $jugadores->listarPlantilla($participacionId);
$tiersDisponibles = $contratos->listarTiers();
$tiersPorId = array_column($tiersDisponibles, null, 'nombre');
$tiersDisponiblesPorId = array_column($tiersDisponibles, null, 'id');
$contratosActivosPlantilla = $contratos->listarPlantillaContratada($participacionId);
$contratoIdPorJugador = array_column($contratosActivosPlantilla, 'id', 'jugador_id');
$contratoDetallePorJugador = array_column($contratosActivosPlantilla, null, 'jugador_id');
$gastoActual = $contratos->gastoSalarial($participacionId);
$salaryCap = (float) ($participacion['salary_cap_override'] ?? $temporada['salary_cap']);

$misParticipaciones = [];
if (!$puedeGestionar) {
    foreach ($participaciones->listarPorTemporada((int) $participacion['temporada_id']) as $p) {
        if ((int) $p['id'] !== $participacionId
            && ($esAdmin || (int) ($p['usuario_presidente_id'] ?? 0) === (int) $usuario['id'])) {
            $misParticipaciones[] = $p;
        }
    }
}

// Salary Cap de cada posible club comprador (modal de traspaso rediseñado):
// se calcula una vez aquí, no en el bucle de la tabla, porque no depende del
// jugador concreto que se esté ofertando.
$capPorClubComprador = [];
// Plantilla propia por cada club, para poder elegir jugadores a cambio en un
// traspaso (intercambio, a petición de Franshu): id => lista de sus jugadores.
$misJugadoresPorClub = [];
foreach ($misParticipaciones as $p) {
    $capPorClubComprador[(int) $p['id']] = [
        'gastado' => $contratos->gastoSalarial((int) $p['id']),
        'cap' => (float) ($p['salary_cap_override'] ?? $temporada['salary_cap']),
    ];
    $misJugadoresPorClub[(int) $p['id']] = array_map(fn ($j) => [
        'id' => (int) $j['id'],
        'nombre' => $j['nombre'],
        'posicion' => $j['posicion'],
        'tier' => $j['tier_nombre'],
    ], $jugadores->listarPlantilla((int) $p['id']));
}

$ofertasRecibidas = $puedeGestionar ? $traspasos->listarOfertasRecibidas($participacionId) : [];
$jugadoresOfrecidosPorOferta = $traspasos->listarJugadoresOfrecidosPorOfertas(array_column($ofertasRecibidas, 'id'));
$paginaTitulo = 'Plantilla · ' . $club['nombre'];
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'plantilla';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div class="fila-club" style="gap:1.25rem">
            <?php if (!empty($club['escudo_url'])): ?>
                <img referrerpolicy="no-referrer" class="escudo escudo-lg" src="<?= htmlspecialchars($club['escudo_url']) ?>" alt="Escudo de <?= htmlspecialchars($club['nombre']) ?>" loading="lazy">
            <?php endif; ?>
            <div>
                <span class="overline">Plantilla</span>
                <h1 class="h1" style="margin-top:.15rem"><a href="historial_club.php?club_id=<?= urlencode($participacion['club_id']) ?>" style="color:inherit"><?= htmlspecialchars($club['nombre']) ?></a></h1>
            </div>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="grid-3">
        <div class="card">
            <span class="caption">Jugadores</span>
            <div class="h3 mono" style="margin-top:.35rem"><?= count($jugadoresPlantilla) ?></div>
        </div>
        <div class="card">
            <span class="caption">Salary Cap</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= number_format($gastoActual, 0, ',', '.') ?> € / <?= number_format($salaryCap, 0, ',', '.') ?> €</div>
            <?php $porcentajeCap = $salaryCap > 0 ? min(100, $gastoActual / $salaryCap * 100) : 0; ?>
            <div class="cap-tracker"><div class="cap-tracker-fill <?= $porcentajeCap >= 95 ? 'peligro' : ($porcentajeCap >= 80 ? 'aviso' : '') ?>" style="width:<?= $porcentajeCap ?>%"></div></div>
        </div>
        <div class="card">
            <span class="caption">Dinero de traspasos</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= number_format($financiero->saldoActual($participacionId), 0, ',', '.') ?> €</div>
        </div>
    </div>

    <?php if ($puedeGestionar): ?>
        <?php $movimientos = $financiero->listarMovimientos($participacionId); ?>
        <?php if ($movimientos !== []): ?>
            <h2 class="h2" style="margin-top:2.5rem">Libro mayor de traspasos</h2>
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th class="num">Importe</th></tr></thead>
                    <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td class="caption mono"><?= htmlspecialchars($m['creado_en']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars(etiqueta_legible($m['tipo'])) ?></span></td>
                            <td><?= htmlspecialchars($m['concepto']) ?></td>
                            <td class="num mono"><?= number_format((float) $m['importe'], 0, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($esAdmin): ?>
            <div class="card" style="margin-top:2rem;max-width:520px">
                <h3 class="h3">Ajustar dinero de traspasos</h3>
                <button type="button" class="btn btn-secondary" style="margin-top:1rem" data-modal-open="modal-ajuste">
                    <i class="ph ph-sliders-horizontal"></i> Aplicar ajuste
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($puedeGestionar && $ofertasRecibidas !== []): ?>
        <h2 class="h2" style="margin-top:2.5rem">Ofertas de traspaso recibidas</h2>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Jugador</th><th>Club comprador</th><th class="num">Importe</th><th>A cambio</th><th>Respuesta</th></tr></thead>
                <tbody>
                <?php foreach ($ofertasRecibidas as $o): ?>
                    <?php
                        $contratoActualOferta = $contratoRepositorio->buscarPorId((int) $o['contrato_id']);
                        $tierActualOferta = $contratoActualOferta !== null
                            ? ($tiersDisponiblesPorId[(int) $contratoActualOferta['tier_id']]['nombre'] ?? null)
                            : null;
                        $jugadoresOfrecidosOferta = $jugadoresOfrecidosPorOferta[(int) $o['id']] ?? [];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($o['jugador_nombre']) ?> <span class="caption">(<?= htmlspecialchars($o['posicion']) ?>)</span></td>
                        <td><?= htmlspecialchars($o['club_comprador_nombre']) ?></td>
                        <td class="num mono"><?= (float) $o['importe_traspaso'] > 0 ? number_format((float) $o['importe_traspaso'], 0, ',', '.') . ' €' : '—' ?></td>
                        <td>
                            <?php if ($jugadoresOfrecidosOferta === []): ?>
                                <span class="caption">—</span>
                            <?php else: ?>
                                <?php foreach ($jugadoresOfrecidosOferta as $jo): ?>
                                    <span class="badge" style="margin:.1rem"><?= htmlspecialchars($jo['jugador_nombre']) ?> (<?= htmlspecialchars($jo['posicion']) ?>)</span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex;gap:.5rem">
                            <button type="button" class="btn btn-sm btn-primary"
                                onclick='TRplantilla.confirmarAceptarTraspaso(<?= json_encode([
                                    "ofertaId" => (int) $o["id"],
                                    "jugador" => $o["jugador_nombre"],
                                    "clubComprador" => $o["club_comprador_nombre"],
                                    "importe" => (float) $o["importe_traspaso"],
                                    "salarioActual" => $contratoActualOferta !== null ? (float) $contratoActualOferta["salario_anual"] : null,
                                    "tierActual" => $tierActualOferta,
                                    "duracionActual" => $contratoActualOferta !== null ? (int) $contratoActualOferta["duracion_temporadas"] : null,
                                    "jugadoresOfrecidos" => array_map(fn ($jo) => $jo['jugador_nombre'] . ' (' . $jo['posicion'] . ')', $jugadoresOfrecidosOferta),
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                Aceptar
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary"
                                onclick='TRplantilla.abrirContraoferta(<?= (int) $o['id'] ?>, <?= json_encode($o['jugador_nombre'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= (float) $o['importe_traspaso'] ?>, <?= json_encode(array_map(fn ($jo) => $jo['jugador_nombre'] . ' (' . $jo['posicion'] . ')', $jugadoresOfrecidosOferta), JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                Contraofertar
                            </button>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="responder_traspaso">
                                <input type="hidden" name="oferta_id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="respuesta" value="rechazar">
                                <button type="submit" class="btn btn-sm btn-danger">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:2.5rem">Plantilla</h2>
    <?php if ($jugadoresPlantilla === []): ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="empty-state">
                <i class="ph ph-users-three"></i>
                <h3>Sin jugadores todavía</h3>
                <p>Esta plantilla no tiene ningún jugador importado o fichado.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="card" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="margin-bottom:0;min-width:180px">
            <label class="field-label" for="filtro-plantilla-nombre">Buscar</label>
            <input class="input" id="filtro-plantilla-nombre" type="search" placeholder="Nombre del jugador" oninput="TRplantilla.filtrar()">
        </div>
        <div class="field" style="margin-bottom:0;min-width:140px">
            <label class="field-label" for="filtro-plantilla-posicion">Posición</label>
            <select class="select" id="filtro-plantilla-posicion" onchange="TRplantilla.filtrar()">
                <option value="">Todas</option>
                <option value="POR">POR</option>
                <option value="DEF">DEF</option>
                <option value="MED">MED</option>
                <option value="DEL">DEL</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;min-width:160px">
            <label class="field-label" for="filtro-plantilla-estado">Contrato</label>
            <select class="select" id="filtro-plantilla-estado" onchange="TRplantilla.filtrar()">
                <option value="">Todos</option>
                <option value="CONTRATADO">Contratado</option>
                <option value="SIN_CONTRATO">Sin contrato</option>
            </select>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="TRplantilla.limpiarFiltros()">Limpiar</button>
    </div>
    <p class="caption" id="filtro-plantilla-resumen" style="margin-bottom:1rem"></p>
    <div class="tbl-wrap">
        <div class="tbl-scroll">
        <table class="tbl" id="tabla-plantilla">
            <thead><tr><th>Nombre</th><th>Posición</th><th>Afinidad</th><th>Tier</th><th>Estado</th><th>Franquicia</th><th>Contrato</th></tr></thead>
            <tbody>
            <?php foreach ($jugadoresPlantilla as $j): ?>
                <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>" data-posicion="<?= htmlspecialchars($j['posicion']) ?>" data-contrato="<?= isset($contratoIdPorJugador[$j['id']]) ? 'CONTRATADO' : 'SIN_CONTRATO' ?>">
                    <td>
                        <a href="historial_jugador.php?jugador_id=<?= (int) $j['id'] ?>" class="fila-jugador">
                            <?php if (!empty($j['foto_url'])): ?>
                                <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($j['nombre']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($j['posicion']) ?></td>
                    <td class="caption"><?= htmlspecialchars($j['afinidad_nombre'] ?? '—') ?></td>
                    <td><?= $j['tier_nombre'] !== null ? '<span class="chip ' . tier_chip_class($j['tier_nombre']) . '">' . htmlspecialchars($j['tier_nombre']) . '</span>' : '<span class="caption">Sin asignar</span>' ?></td>
                    <td><span class="badge <?= $j['estado'] === 'ACTIVO' ? 'badge-success' : '' ?>"><?= htmlspecialchars(etiqueta_legible($j['estado'])) ?></span></td>
                    <td>
                        <?php if ($j['es_franquicia']): ?>
                            <span class="badge badge-gold">Sí</span>
                        <?php else: ?>
                            <span class="caption">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($puedeGestionar && $j['tier_nombre'] === null): ?>
                            <form method="post" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;min-width:300px">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="firmar_contrato">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <select name="tier_id" required class="select" style="height:32px;font-size:var(--fs-caption)">
                                    <?php foreach ($tiersDisponibles as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>">
                                            <?= htmlspecialchars($t['nombre']) ?> — <?= number_format((float) $t['salario_base'], 0, ',', '.') ?> €
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="duracion_temporadas" required class="select" style="height:32px;font-size:var(--fs-caption)" title="1 = conservas tanteo (RFA) al terminar. 2 = lo pierdes (UFA) al terminar.">
                                    <option value="1">1 temp. (RFA después)</option>
                                    <option value="2">2 temp. (UFA después)</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Firmar</button>
                            </form>
                        <?php elseif ($puedeGestionar && isset($contratoIdPorJugador[$j['id']])): ?>
                            <?php
                                $contratoDetalle = $contratoDetallePorJugador[$j['id']];
                                $datosModalPropio = [
                                    'tipo' => 'propio',
                                    'jugadorId' => (int) $j['id'],
                                    'nombre' => $j['nombre'],
                                    'fotoUrl' => $j['foto_url'] ?? null,
                                    'posicion' => $j['posicion'],
                                    'tier' => $j['tier_nombre'],
                                    'esFranquicia' => (bool) $j['es_franquicia'],
                                    'contratoId' => (int) $contratoDetalle['id'],
                                    'salarioActual' => (float) $contratoDetalle['salario_anual'],
                                    'duracionRestante' => (int) $contratoDetalle['duracion_temporadas'] . ' temporada(s)',
                                    'transferible' => (bool) $contratoDetalle['transferible'],
                                    'puedeGestionar' => true,
                                    'wikiUrl' => wiki_url_publica($j),
                                ];
                            ?>
                            <span class="badge badge-success" style="margin-right:.4rem">Contratado</span>
                            <?php if (!$contratoDetalle['transferible']): ?><span class="badge badge-warning" style="margin-right:.4rem">No transferible</span><?php endif; ?>
                            <button type="button" class="btn btn-sm btn-secondary" onclick='TRjugador.abrirModal(<?= json_encode($datosModalPropio, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ver / Gestionar</button>
                            <?php if ($esAdmin && $temporada['estado'] === 'PRETEMPORADA'): ?>
                                <form method="post" action="admin/asignacion_masiva.php" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="deshacer_asignacion">
                                    <input type="hidden" name="contrato_id" value="<?= (int) $contratoIdPorJugador[$j['id']] ?>">
                                    <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost">Deshacer asignación</button>
                                </form>
                            <?php endif; ?>
                        <?php elseif (!$puedeGestionar && isset($contratoIdPorJugador[$j['id']]) && $misParticipaciones !== []): ?>
                            <?php
                                $contratoDetalle = $contratoDetallePorJugador[$j['id']];
                                $ofertasTraspasoJugador = $traspasos->listarOfertasPendientesPorJugador((int) $j['id']);
                                usort($ofertasTraspasoJugador, fn ($a, $b) => (float) $b['importe_traspaso'] <=> (float) $a['importe_traspaso']);
                                $jugadoresOfrecidosPorOfertaTraspaso = $traspasos->listarJugadoresOfrecidosPorOfertas(array_column($ofertasTraspasoJugador, 'id'));
                                $datosModalTraspaso = [
                                    'tipo' => 'traspaso',
                                    'jugadorId' => (int) $j['id'],
                                    'nombre' => $j['nombre'],
                                    'fotoUrl' => $j['foto_url'] ?? null,
                                    'posicion' => $j['posicion'],
                                    'tier' => $j['tier_nombre'],
                                    'esFranquicia' => (bool) $j['es_franquicia'],
                                    'clubActual' => $club['nombre'],
                                    'clubActualEscudo' => $club['escudo_url'] ?? null,
                                    'salarioActual' => (float) $contratoDetalle['salario_anual'],
                                    'duracionRestante' => (int) $contratoDetalle['duracion_temporadas'] . ' temporada(s)',
                                    'puedeOfertar' => true,
                                    'wikiUrl' => wiki_url_publica($j),
                                    'ofertasTraspaso' => array_map(fn ($o) => [
                                        'club' => $o['club_comprador_nombre'],
                                        'escudo' => $o['club_comprador_escudo_url'] ?? null,
                                        'importe' => (float) $o['importe_traspaso'],
                                        'fecha' => $o['creado_en'],
                                        'jugadoresOfrecidos' => array_map(fn ($jo) => $jo['jugador_nombre'], $jugadoresOfrecidosPorOfertaTraspaso[(int) $o['id']] ?? []),
                                    ], $ofertasTraspasoJugador),
                                    'capPorClub' => $capPorClubComprador,
                                    'misJugadoresPorClub' => $misJugadoresPorClub,
                                ];
                            ?>
                            <?php if (!$contratoDetalle['transferible']): ?><span class="badge badge-warning" style="margin-right:.4rem" title="El club lo ha marcado como no transferible">No transferible</span><?php endif; ?>
                            <button type="button" class="btn btn-sm btn-primary" onclick='TRjugador.abrirModal(<?= json_encode($datosModalTraspaso, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ver / Ofertar traspaso</button>
                        <?php elseif (!$puedeGestionar && isset($contratoIdPorJugador[$j['id']])): ?>
                            <span class="caption">Contratado (necesitas un club propio para ofertar)</span>
                        <?php else: ?>
                            <span class="caption">Sin contrato activo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Modal unificado de jugador (Fase 2, componente compartido): cubre "Ver / Gestionar"
     (propio) y "Ver / Ofertar traspaso" (ajeno) de esta misma tabla, incluida la
     confirmación de Proteger y Finalizar contrato. -->
<?php include __DIR__ . '/../partials/modal_jugador.php'; ?>

<!-- Modal de confirmación: aceptar traspaso (05-transfer_room_docs/02_ux_diseno/01, §1.2:
     comparación lado a lado del contrato actual vs la oferta, antes de una acción irreversible
     que mueve dinero y jugador). -->
<div class="modal-bg" id="modal-aceptar-traspaso">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-aceptar-traspaso-title">
        <div class="modal-head">
            <h2 id="modal-aceptar-traspaso-title">¿Aceptar este traspaso?</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p><strong id="modal-aceptar-jugador"></strong> a <strong id="modal-aceptar-club"></strong> <span id="modal-aceptar-importe-frase"></span>.</p>
            <div id="modal-aceptar-jugadores-ofrecidos" style="margin-top:.5rem;display:none">
                <span class="caption">A cambio también recibiríais:</span>
                <div id="modal-aceptar-jugadores-ofrecidos-lista" style="margin-top:.3rem"></div>
            </div>
            <div class="grid-2" style="margin-top:1rem">
                <div class="card">
                    <span class="caption">Contrato actual</span>
                    <div id="modal-aceptar-contrato-actual" class="body-sm" style="margin-top:.5rem"></div>
                </div>
                <div class="card">
                    <span class="caption">Este traspaso</span>
                    <div class="body-sm" style="margin-top:.5rem">El jugador pasa al comprador. Su contrato con vosotros se finaliza. El dinero (si lo hay) entra en vuestro libro mayor de traspasos, y los jugadores ofrecidos (si los hay) pasan a vuestra plantilla.</div>
                </div>
            </div>
            <p style="margin-top:1rem">Esta acción no se puede deshacer.</p>
        </div>
        <form method="post" id="form-aceptar-traspaso">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="responder_traspaso">
            <input type="hidden" name="respuesta" value="aceptar">
            <input type="hidden" name="oferta_id" id="modal-aceptar-oferta-id" value="">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Aceptar traspaso</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: contraofertar un traspaso recibido (05-transfer_room_docs/01_bloqueante_primer_mercado/05, punto 13) -->
<div class="modal-bg" id="modal-contraoferta">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-contraoferta-title">
        <div class="modal-head">
            <h2 id="modal-contraoferta-title">Contraofertar</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <p>Vas a proponer un precio distinto por <strong id="modal-contraoferta-jugador"></strong>. La oferta original queda cerrada; esta es una propuesta nueva que el comprador debe aceptar o rechazar.</p>
            <div id="modal-contraoferta-jugadores-incluidos" style="margin-top:.5rem;display:none">
                <span class="caption">Los jugadores ya incluidos en la oferta se mantienen igual, solo cambias el dinero:</span>
                <div id="modal-contraoferta-jugadores-incluidos-lista" style="margin-top:.3rem"></div>
            </div>
            <div class="field" style="margin-top:1rem">
                <label class="field-label" for="modal-contraoferta-importe">Tu contraoferta (en dinero)</label>
                <input class="input" id="modal-contraoferta-importe" type="number" name="nuevo_importe" min="0" step="1" required form="form-contraoferta">
            </div>
        </div>
        <form method="post" id="form-contraoferta">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="contraofertar_traspaso">
            <input type="hidden" name="oferta_id" id="modal-contraoferta-oferta-id" value="">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar contraoferta</button>
            </div>
        </form>
    </div>
</div>

<?php if ($esAdmin): ?>
<!-- Modal de confirmación: ajuste financiero manual (Cap. XVI, consecuencia económica) -->
<div class="modal-bg" id="modal-ajuste">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-ajuste-title">
        <div class="modal-head">
            <h2 id="modal-ajuste-title">Ajustar dinero de traspasos</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="ajuste_financiero">
                <div class="field">
                    <label class="field-label" for="ma-sentido">Sentido</label>
                    <select class="select" id="ma-sentido" name="sentido" required>
                        <option value="sumar">Sumar</option>
                        <option value="restar">Restar</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="ma-importe">Importe</label>
                    <input class="input" id="ma-importe" type="number" name="importe" min="1" step="1" required>
                </div>
                <div class="field">
                    <label class="field-label" for="ma-motivo">Motivo <span class="req">*</span></label>
                    <input class="input" id="ma-motivo" type="text" name="motivo" placeholder="Obligatorio, queda en el libro mayor" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar ajuste</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
var TRplantilla = {
    confirmarAceptarTraspaso: function (datos) {
        document.getElementById('modal-aceptar-oferta-id').value = datos.ofertaId;
        document.getElementById('modal-aceptar-jugador').textContent = datos.jugador;
        document.getElementById('modal-aceptar-club').textContent = datos.clubComprador;
        document.getElementById('modal-aceptar-importe-frase').textContent = datos.importe > 0
            ? ('por ' + datos.importe.toLocaleString('es-ES') + ' €' + (datos.jugadoresOfrecidos.length ? ' + jugadores' : ''))
            : 'a cambio de jugadores de su plantilla';

        var contenedorOfrecidos = document.getElementById('modal-aceptar-jugadores-ofrecidos');
        var listaOfrecidos = document.getElementById('modal-aceptar-jugadores-ofrecidos-lista');
        listaOfrecidos.innerHTML = '';
        if (datos.jugadoresOfrecidos && datos.jugadoresOfrecidos.length) {
            datos.jugadoresOfrecidos.forEach(function (nombre) {
                var badge = document.createElement('span');
                badge.className = 'badge';
                badge.style.margin = '.1rem';
                badge.textContent = nombre; // nunca innerHTML: nombre puede venir de un jugador externo (texto libre)
                listaOfrecidos.appendChild(badge);
            });
            contenedorOfrecidos.style.display = '';
        } else {
            contenedorOfrecidos.style.display = 'none';
        }

        var actual = document.getElementById('modal-aceptar-contrato-actual');
        if (datos.salarioActual !== null) {
            actual.innerHTML = 'Tier <strong>' + (datos.tierActual || '—') + '</strong><br>'
                + datos.salarioActual.toLocaleString('es-ES') + ' € / año<br>'
                + datos.duracionActual + ' temporada(s)';
        } else {
            actual.textContent = 'Sin datos de contrato.';
        }

        TR.abrirModal('modal-aceptar-traspaso');
    },
    abrirContraoferta: function (ofertaId, nombreJugador, importeActual, jugadoresIncluidos) {
        document.getElementById('modal-contraoferta-oferta-id').value = ofertaId;
        document.getElementById('modal-contraoferta-jugador').textContent = nombreJugador;
        document.getElementById('modal-contraoferta-importe').value = importeActual;

        var contenedor = document.getElementById('modal-contraoferta-jugadores-incluidos');
        var lista = document.getElementById('modal-contraoferta-jugadores-incluidos-lista');
        lista.innerHTML = '';
        if (jugadoresIncluidos && jugadoresIncluidos.length) {
            jugadoresIncluidos.forEach(function (nombre) {
                var badge = document.createElement('span');
                badge.className = 'badge';
                badge.style.margin = '.1rem';
                badge.textContent = nombre;
                lista.appendChild(badge);
            });
            contenedor.style.display = '';
        } else {
            contenedor.style.display = 'none';
        }

        TR.abrirModal('modal-contraoferta');
    },

    filtrar: function () {
        var tabla = document.getElementById('tabla-plantilla');
        if (!tabla) return;

        var nombre = document.getElementById('filtro-plantilla-nombre').value.trim().toLowerCase();
        var posicion = document.getElementById('filtro-plantilla-posicion').value;
        var estado = document.getElementById('filtro-plantilla-estado').value;

        var filas = tabla.querySelectorAll('tbody tr');
        var visibles = 0;

        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1
                && (posicion === '' || fila.dataset.posicion === posicion)
                && (estado === '' || fila.dataset.contrato === estado);

            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('filtro-plantilla-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    },

    limpiarFiltros: function () {
        document.getElementById('filtro-plantilla-nombre').value = '';
        document.getElementById('filtro-plantilla-posicion').value = '';
        document.getElementById('filtro-plantilla-estado').value = '';
        TRplantilla.filtrar();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('tabla-plantilla')) TRplantilla.filtrar();
});
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
