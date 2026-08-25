<?php

declare(strict_types=1);

/**
 * Scouting (Fase 2, rediseño estético): reconvertido en panel de
 * seguimiento con alertas — Mercado ya absorbió el buscador global (con
 * pujas incluidas), así que Scouting se especializa en avisar cuándo alguien
 * oferta por un jugador que sigues (reutiliza estrategia_watchlist, la misma
 * tabla que Mi Estrategia). El buscador para AÑADIR jugadores al
 * seguimiento se mantiene como pestaña secundaria. Sin especificación
 * previa — diseño propio, decisión confirmada con Franshu.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$usuarioId = (int) $usuario['id'];

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            switch ($_POST['accion'] ?? '') {
                case 'alternar_seguimiento':
                    $estrategia->alternarSeguimiento($usuarioId, (int) $_POST['jugador_id']);
                    break;
                case 'dejar_de_seguir':
                    $estrategia->quitarDeWatchlist($usuarioId, (int) $_POST['watchlist_id']);
                    break;
                default:
                    $error = 'Acción no reconocida.';
            }
            if ($error === null) {
                header('Location: scouting.php');
                exit;
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$watchlistSeguimiento = $estrategia->listarWatchlist($usuarioId);
$jugadorIdsSeguidos = array_column($watchlistSeguimiento, 'jugador_id');

// Alertas: para cada jugador seguido, ¿hay una oferta activa sobre él ahora mismo?
$alertasPorJugador = [];
foreach ($watchlistSeguimiento as $w) {
    $jugadorId = (int) $w['jugador_id'];
    if ($w['jugador_estado'] === 'ACTIVO') {
        $ofertas = $traspasos->listarOfertasPendientesPorJugador($jugadorId);
        $alertasPorJugador[$jugadorId] = array_map(fn ($o) => [
            'club' => $o['club_comprador_nombre'],
            'importe' => (float) $o['importe_traspaso'],
            'tipo' => 'traspaso',
        ], $ofertas);
    } else {
        $ofertas = $mercado->listarOfertasPendientes($jugadorId);
        $alertasPorJugador[$jugadorId] = array_map(fn ($o) => [
            'club' => $o['club_nombre'],
            'importe' => (float) $o['salario_ofertado'],
            'tipo' => 'agente_libre',
        ], $ofertas);
    }
}

$todosLosJugadores = $jugadores->listarTodosParaScouting();
$afinidadesDisponibles = array_unique(array_filter(array_column($todosLosJugadores, 'afinidad_nombre')));
sort($afinidadesDisponibles);

$paginaTitulo = 'Scouting';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'scouting';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Liga</span>
            <h1 class="h1" style="margin-top:.5rem">Scouting</h1>
            <p class="caption">Sigue a los jugadores que te interesan y entérate en cuanto alguien oferte por ellos.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert" style="margin-bottom:1rem"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success" style="margin-bottom:1rem"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="seguimiento">Seguimiento</button>
        <button data-tab-target="buscar">Buscar jugadores</button>
    </div>

    <div data-tab-panel="seguimiento">
        <?php if ($watchlistSeguimiento === []): ?>
            <div class="empty-state"><i class="ph ph-binoculars"></i><h3>Todavía no sigues a ningún jugador</h3><p class="caption">Ve a la pestaña "Buscar jugadores" y pulsa "Seguir" en los que te interesen.</p></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <?php foreach ($watchlistSeguimiento as $w): ?>
                    <?php $alertas = $alertasPorJugador[(int) $w['jugador_id']] ?? []; ?>
                    <div class="card" style="<?= $alertas !== [] ? 'border-color:var(--warning)' : '' ?>">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                            <a href="historial_jugador.php?jugador_id=<?= (int) $w['jugador_id'] ?>" class="fila-jugador">
                                <?php if (!empty($w['foto_url'])): ?>
                                    <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($w['foto_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= htmlspecialchars($w['jugador_nombre']) ?></strong>
                                    <div class="caption" style="margin-top:.15rem">
                                        <span class="chip <?= tier_chip_class($w['tier_nombre'] ?? '') ?>"><?= htmlspecialchars($w['tier_nombre'] ?? '—') ?></span>
                                        <?= $w['jugador_estado'] === 'ACTIVO' ? 'Contratado' : 'Agente libre' ?>
                                    </div>
                                </div>
                            </a>
                            <form method="post" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="dejar_de_seguir">
                                <input type="hidden" name="watchlist_id" value="<?= (int) $w['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">Dejar de seguir</button>
                            </form>
                        </div>
                        <?php if ($alertas !== []): ?>
                            <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--line)">
                                <span class="badge badge-warning"><i class="ph ph-bell-ringing"></i> <?= count($alertas) ?> oferta(s) activa(s)</span>
                                <div class="body-sm" style="margin-top:.5rem">
                                    <?php foreach ($alertas as $a): ?>
                                        <div><strong><?= htmlspecialchars($a['club']) ?></strong>: <span class="mono"><?= number_format($a['importe'], 0, ',', '.') ?> €</span> (<?= $a['tipo'] === 'traspaso' ? 'traspaso' : 'agente libre' ?>)</div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="buscar" hidden>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="filtro-scouting-nombre">Nombre</label>
                <input class="input" type="search" id="filtro-scouting-nombre" placeholder="Nombre del jugador" oninput="TRscouting.filtrar()">
            </div>
            <div class="field" style="margin-bottom:0;min-width:140px">
                <label class="field-label" for="filtro-scouting-tier">Tier</label>
                <select class="select" id="filtro-scouting-tier" onchange="TRscouting.filtrar()">
                    <option value="">Todos</option>
                    <?php foreach (['C', 'B-', 'B', 'B+', 'A-', 'A', 'A+', 'S', 'S+', 'S++'] as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:140px">
                <label class="field-label" for="filtro-scouting-posicion">Posición</label>
                <select class="select" id="filtro-scouting-posicion" onchange="TRscouting.filtrar()">
                    <option value="">Todas</option>
                    <option value="POR">POR</option>
                    <option value="DEF">DEF</option>
                    <option value="MED">MED</option>
                    <option value="DEL">DEL</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:160px">
                <label class="field-label" for="filtro-scouting-afinidad">Afinidad</label>
                <select class="select" id="filtro-scouting-afinidad" onchange="TRscouting.filtrar()">
                    <option value="">Todas</option>
                    <?php foreach ($afinidadesDisponibles as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:160px">
                <label class="field-label" for="filtro-scouting-estado">Estado</label>
                <select class="select" id="filtro-scouting-estado" onchange="TRscouting.filtrar()">
                    <option value="">Todos</option>
                    <option value="CONTRATADO">Contratado</option>
                    <option value="AGENTE_LIBRE">Agente libre</option>
                </select>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="TRscouting.limpiarFiltros()">Limpiar</button>
        </div>
        <p class="caption" id="filtro-scouting-resumen" style="margin-bottom:1rem"></p>

        <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table class="tbl" id="tabla-scouting">
                <thead><tr><th>Jugador</th><th>Posición</th><th>Afinidad</th><th>Tier</th><th>Club</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($todosLosJugadores as $j): ?>
                    <?php
                        $estadoFiltro = $j['estado'] === 'ACTIVO' ? 'CONTRATADO' : 'AGENTE_LIBRE';
                        $yaSeguido = in_array((int) $j['id'], $jugadorIdsSeguidos, true);
                    ?>
                    <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>" data-tier="<?= htmlspecialchars($j['tier_nombre']) ?>" data-posicion="<?= htmlspecialchars($j['posicion']) ?>" data-afinidad="<?= htmlspecialchars($j['afinidad_nombre'] ?? '') ?>" data-estado="<?= $estadoFiltro ?>">
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
                        <td><span class="chip <?= tier_chip_class($j['tier_nombre']) ?>"><?= htmlspecialchars($j['tier_nombre']) ?></span></td>
                        <td>
                            <?php if ($estadoFiltro === 'CONTRATADO'): ?>
                                <div class="fila-club">
                                    <?php if (!empty($j['escudo_url'])): ?>
                                        <img referrerpolicy="no-referrer" class="escudo" src="<?= htmlspecialchars($j['escudo_url']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($j['club_nombre'] ?? '—') ?>
                                </div>
                            <?php else: ?>
                                <span class="badge badge-success">Agente libre</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="alternar_seguimiento">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $yaSeguido ? 'btn-primary' : 'btn-secondary' ?>">
                                    <i class="<?= $yaSeguido ? 'ph-fill ph-binoculars' : 'ph ph-binoculars' ?>"></i> <?= $yaSeguido ? 'Siguiendo' : 'Seguir' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</main>
<script>
var TRscouting = {
    filtrar: function () {
        var tabla = document.getElementById('tabla-scouting');
        if (!tabla) return;

        var nombre = document.getElementById('filtro-scouting-nombre').value.trim().toLowerCase();
        var tier = document.getElementById('filtro-scouting-tier').value;
        var posicion = document.getElementById('filtro-scouting-posicion').value;
        var afinidad = document.getElementById('filtro-scouting-afinidad').value;
        var estado = document.getElementById('filtro-scouting-estado').value;

        var filas = tabla.querySelectorAll('tbody tr');
        var visibles = 0;

        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1
                && (tier === '' || fila.dataset.tier === tier)
                && (posicion === '' || fila.dataset.posicion === posicion)
                && (afinidad === '' || fila.dataset.afinidad === afinidad)
                && (estado === '' || fila.dataset.estado === estado);

            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('filtro-scouting-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    },

    limpiarFiltros: function () {
        document.getElementById('filtro-scouting-nombre').value = '';
        document.getElementById('filtro-scouting-tier').value = '';
        document.getElementById('filtro-scouting-posicion').value = '';
        document.getElementById('filtro-scouting-afinidad').value = '';
        document.getElementById('filtro-scouting-estado').value = '';
        TRscouting.filtrar();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('tabla-scouting')) TRscouting.filtrar();
});
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
