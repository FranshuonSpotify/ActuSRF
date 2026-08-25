<?php

declare(strict_types=1);

/**
 * Mi Estrategia (Fase 2, hub v3): watchlist, kanban de objetivos, diario de
 * decisiones, simulador de plantilla teórica y comparador de jugadores. Sin
 * especificación previa — diseño propio completo (CLAUDE.md, tarea Fase 2).
 * El simulador reutiliza la watchlist (columna en_simulador) y el comparador
 * no persiste nada: ambos leen datos ya existentes al vuelo.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$usuarioId = (int) $usuario['id'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            switch ($_POST['accion'] ?? '') {
                case 'watchlist_agregar':
                    $estrategia->agregarAWatchlist($usuarioId, (int) $_POST['jugador_id'], (string) ($_POST['notas'] ?? ''));
                    break;
                case 'watchlist_quitar':
                    $estrategia->quitarDeWatchlist($usuarioId, (int) $_POST['watchlist_id']);
                    break;
                case 'watchlist_simulador':
                    $estrategia->alternarEnSimulador($usuarioId, (int) $_POST['watchlist_id']);
                    break;
                case 'objetivo_crear':
                    $estrategia->crearObjetivo($usuarioId, (string) ($_POST['titulo'] ?? ''), (string) ($_POST['descripcion'] ?? ''));
                    break;
                case 'objetivo_mover':
                    $estrategia->moverObjetivo($usuarioId, (int) $_POST['objetivo_id'], (string) $_POST['estado']);
                    break;
                case 'objetivo_eliminar':
                    $estrategia->eliminarObjetivo($usuarioId, (int) $_POST['objetivo_id']);
                    break;
                case 'diario_agregar':
                    $estrategia->agregarDiario($usuarioId, (string) ($_POST['texto'] ?? ''));
                    break;
                case 'diario_eliminar':
                    $estrategia->eliminarDiario($usuarioId, (int) $_POST['diario_id']);
                    break;
                case 'planificador_asignar':
                    $estrategia->asignarPlanificador($usuarioId, (string) $_POST['slot'], (int) $_POST['jugador_id']);
                    break;
                case 'planificador_limpiar_slot':
                    $estrategia->limpiarSlotPlanificador($usuarioId, (string) $_POST['slot']);
                    break;
                case 'planificador_limpiar_todo':
                    $estrategia->limpiarPlanificador($usuarioId);
                    break;
                case 'planificador_reserva_agregar':
                    $estrategia->agregarReservaPlanificador($usuarioId, (int) $_POST['jugador_id']);
                    break;
                case 'planificador_reserva_quitar':
                    $estrategia->quitarReservaPlanificador($usuarioId, (int) $_POST['jugador_id']);
                    break;
                case 'super_jugador_crear':
                    $estrategia->crearJugadorSimulado($usuarioId, (string) ($_POST['nombre'] ?? ''));
                    break;
                case 'super_jugador_eliminar':
                    $estrategia->eliminarJugadorSimulado($usuarioId, (int) $_POST['jugador_simulado_id']);
                    break;
                case 'super_tecnica_agregar':
                    $estrategia->agregarTecnicaSimulada(
                        $usuarioId,
                        isset($_POST['jugador_id']) && $_POST['jugador_id'] !== '' ? (int) $_POST['jugador_id'] : null,
                        isset($_POST['jugador_simulado_id']) && $_POST['jugador_simulado_id'] !== '' ? (int) $_POST['jugador_simulado_id'] : null,
                        [
                            'nombre' => (string) ($_POST['nombre'] ?? ''),
                            'afinidad' => (string) ($_POST['afinidad'] ?? ''),
                            'tension' => (int) ($_POST['tension'] ?? 0),
                            'categoria' => $_POST['categoria'] ?? null,
                            'subcategoria' => $_POST['subcategoria'] ?? null,
                            'hipertecnica' => $_POST['hipertecnica'] ?? null,
                            'despertar_variante' => $_POST['despertar_variante'] ?? null,
                            'armadura' => !empty($_POST['armadura']),
                        ]
                    );
                    break;
                case 'super_tecnica_eliminar':
                    $estrategia->eliminarTecnicaSimulada($usuarioId, (int) $_POST['tecnica_id']);
                    break;
            }
            if ($error === null) {
                header('Location: mi_estrategia.php');
                exit;
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$watchlist = $estrategia->listarWatchlist($usuarioId);
$objetivos = $estrategia->listarObjetivos($usuarioId);
$diario = $estrategia->listarDiario($usuarioId);
$simulador = $estrategia->simulador($usuarioId);

$participacionActiva = null;
$temporadaActiva = $temporadas->obtenerActiva();
if ($temporadaActiva !== null) {
    $participacionActiva = $participaciones->buscarPorUsuarioYTemporada($usuarioId, (int) $temporadaActiva['id']);
}
$capMaximo = $participacionActiva !== null
    ? (float) ($participacionActiva['salary_cap_override'] ?? $temporadaActiva['salary_cap'])
    : 0.0;
$capUsadoReal = $participacionActiva !== null ? $contratoRepositorio->sumaSalariosActivos((int) $participacionActiva['id']) : 0.0;

$todosLosJugadores = $jugadores->listarTodosParaScouting();
$planificador = $estrategia->listarPlanificador($usuarioId);
$reservasPlanificador = $estrategia->listarReservasPlanificador($usuarioId);
$idsReservasPlanificador = array_column($reservasPlanificador, 'jugador_id');

$superJugadoresSimulados = $estrategia->listarJugadoresSimulados($usuarioId);
$superTecnicas = $estrategia->listarTecnicasSimuladas($usuarioId);

// Roster del simulador: jugadores reales (identificados por los que ya tienen
// alguna técnica guardada) + jugadores simulados, cada uno con sus técnicas.
// clave = "real:<id>" o "sim:<id>", para que el JS distinga el origen sin
// arriesgarse a que un id real y uno simulado choquen.
$nombrePorJugadorId = array_column($todosLosJugadores, 'nombre', 'id');
$fotoPorJugadorId = array_column($todosLosJugadores, 'foto_url', 'id');
$superRoster = [];
foreach ($superJugadoresSimulados as $js) {
    $superRoster['sim:' . $js['id']] = [
        'clave' => 'sim:' . $js['id'],
        'jugadorSimuladoId' => (int) $js['id'],
        'jugadorId' => null,
        'nombre' => $js['nombre'],
        'fotoUrl' => null,
        'tecnicas' => [],
    ];
}
foreach ($superTecnicas as $t) {
    if ($t['jugador_id'] !== null) {
        $clave = 'real:' . $t['jugador_id'];
        if (!isset($superRoster[$clave])) {
            $superRoster[$clave] = [
                'clave' => $clave,
                'jugadorSimuladoId' => null,
                'jugadorId' => (int) $t['jugador_id'],
                'nombre' => $nombrePorJugadorId[$t['jugador_id']] ?? ('Jugador #' . $t['jugador_id']),
                'fotoUrl' => $fotoPorJugadorId[$t['jugador_id']] ?? null,
                'tecnicas' => [],
            ];
        }
    } else {
        $clave = 'sim:' . $t['jugador_simulado_id'];
    }
    $superRoster[$clave]['tecnicas'][] = [
        'id' => (int) $t['id'],
        'nombre' => $t['nombre'],
        'afinidad' => $t['afinidad'],
        'tension' => (int) $t['tension'],
        'categoria' => $t['categoria'],
        'subcategoria' => $t['subcategoria'],
        'hipertecnica' => $t['hipertecnica'],
        'hipertecnicaVariante' => $t['hipertecnica_variante'],
    ];
}
$superRoster = array_values($superRoster);

$objetivosPorEstado = ['PENDIENTE' => [], 'EN_PROGRESO' => [], 'COMPLETADO' => []];
foreach ($objetivos as $o) {
    $objetivosPorEstado[$o['estado']][] = $o;
}

$paginaTitulo = 'Mi Estrategia';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'mi_estrategia';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Mi club</span>
            <h1 class="h1" style="margin-top:.5rem">Mi Estrategia</h1>
            <p class="caption">Espacio personal de planificación: nada de aquí ejecuta fichajes ni afecta a otros presidentes.</p>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" style="margin-bottom:1rem"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="watchlist">Watchlist</button>
        <button data-tab-target="kanban">Objetivos</button>
        <button data-tab-target="diario">Diario</button>
        <button data-tab-target="simulador">Simulador</button>
        <button data-tab-target="comparador">Comparador</button>
        <button data-tab-target="planificador">Planificador táctico</button>
    </div>

    <div data-tab-panel="watchlist">
        <form method="post" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.5rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="watchlist_agregar">
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="watchlist-jugador">Jugador</label>
                <select class="select" id="watchlist-jugador" name="jugador_id" required>
                    <option value="">Elige un jugador…</option>
                    <?php foreach ($todosLosJugadores as $j): ?>
                        <option value="<?= (int) $j['id'] ?>"><?= htmlspecialchars($j['nombre']) ?> (<?= htmlspecialchars($j['tier_nombre'] ?? '—') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="watchlist-notas">Nota (opcional)</label>
                <input class="input" type="text" id="watchlist-notas" name="notas" maxlength="500" placeholder="Por qué te interesa">
            </div>
            <button type="submit" class="btn btn-primary">Añadir a la watchlist</button>
        </form>

        <?php if ($watchlist === []): ?>
            <div class="empty-state"><i class="ph ph-binoculars"></i><h3>Tu watchlist está vacía</h3><p class="caption">Añade jugadores que te interesen para seguirlos y probarlos en el simulador.</p></div>
        <?php else: ?>
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Jugador</th><th>Tier</th><th>Nota</th><th>En simulador</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($watchlist as $w): ?>
                        <tr>
                            <td>
                                <a href="historial_jugador.php?jugador_id=<?= (int) $w['jugador_id'] ?>" class="fila-jugador">
                                    <?php if (!empty($w['foto_url'])): ?>
                                        <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($w['foto_url']) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($w['jugador_nombre']) ?>
                                </a>
                            </td>
                            <td><span class="chip <?= tier_chip_class($w['tier_nombre'] ?? '') ?>"><?= htmlspecialchars($w['tier_nombre'] ?? '—') ?></span></td>
                            <td class="caption"><?= htmlspecialchars($w['notas'] ?? '—') ?></td>
                            <td>
                                <form method="post" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="watchlist_simulador">
                                    <input type="hidden" name="watchlist_id" value="<?= (int) $w['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $w['en_simulador'] ? 'btn-primary' : 'btn-ghost' ?>">
                                        <?= $w['en_simulador'] ? 'Sí, quitar' : 'Incluir' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="post" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="watchlist_quitar">
                                    <input type="hidden" name="watchlist_id" value="<?= (int) $w['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" aria-label="Quitar de la watchlist"><i class="ph ph-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="kanban" hidden>
        <form method="post" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.5rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="objetivo_crear">
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="objetivo-titulo">Nuevo objetivo</label>
                <input class="input" type="text" id="objetivo-titulo" name="titulo" maxlength="150" required placeholder="Ej: Fichar un DEL de tier A">
            </div>
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="objetivo-desc">Detalle (opcional)</label>
                <input class="input" type="text" id="objetivo-desc" name="descripcion" maxlength="500">
            </div>
            <button type="submit" class="btn btn-primary">Crear objetivo</button>
        </form>

        <div class="grid-3">
            <?php foreach (['PENDIENTE' => 'Pendiente', 'EN_PROGRESO' => 'En progreso', 'COMPLETADO' => 'Completado'] as $estado => $etiqueta): ?>
                <div>
                    <h3 class="h4" style="margin-bottom:.75rem"><?= $etiqueta ?> <span class="caption">(<?= count($objetivosPorEstado[$estado]) ?>)</span></h3>
                    <div style="display:flex;flex-direction:column;gap:.75rem">
                        <?php if ($objetivosPorEstado[$estado] === []): ?>
                            <p class="caption">Sin objetivos aquí.</p>
                        <?php endif; ?>
                        <?php foreach ($objetivosPorEstado[$estado] as $o): ?>
                            <div class="card" style="padding:.9rem 1.1rem">
                                <strong class="body-sm"><?= htmlspecialchars($o['titulo']) ?></strong>
                                <?php if (!empty($o['descripcion'])): ?>
                                    <p class="caption" style="margin-top:.25rem"><?= htmlspecialchars($o['descripcion']) ?></p>
                                <?php endif; ?>
                                <div style="display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap">
                                    <?php foreach (['PENDIENTE', 'EN_PROGRESO', 'COMPLETADO'] as $destino): ?>
                                        <?php if ($destino !== $estado): ?>
                                            <form method="post" style="margin:0">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                                <input type="hidden" name="accion" value="objetivo_mover">
                                                <input type="hidden" name="objetivo_id" value="<?= (int) $o['id'] ?>">
                                                <input type="hidden" name="estado" value="<?= $destino ?>">
                                                <button type="submit" class="btn btn-xs btn-ghost">→ <?= htmlspecialchars(['PENDIENTE' => 'Pendiente', 'EN_PROGRESO' => 'En progreso', 'COMPLETADO' => 'Completado'][$destino]) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <form method="post" style="margin:0">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="accion" value="objetivo_eliminar">
                                        <input type="hidden" name="objetivo_id" value="<?= (int) $o['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-ghost" aria-label="Eliminar objetivo"><i class="ph ph-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div data-tab-panel="diario" hidden>
        <form method="post" style="margin-bottom:1.5rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="diario_agregar">
            <div class="field">
                <label class="field-label" for="diario-texto">Nueva entrada</label>
                <textarea class="input" id="diario-texto" name="texto" rows="3" required placeholder="Por qué tomaste (o vas a tomar) esta decisión…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar entrada</button>
        </form>

        <?php if ($diario === []): ?>
            <div class="empty-state"><i class="ph ph-notebook"></i><h3>Sin entradas todavía</h3></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <?php foreach ($diario as $d): ?>
                    <div class="card" style="padding:.9rem 1.1rem">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
                            <p class="body-sm" style="white-space:pre-wrap"><?= nl2br(htmlspecialchars($d['texto'])) ?></p>
                            <form method="post" style="margin:0;flex-shrink:0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="diario_eliminar">
                                <input type="hidden" name="diario_id" value="<?= (int) $d['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-ghost" aria-label="Eliminar entrada"><i class="ph ph-trash"></i></button>
                            </form>
                        </div>
                        <span class="caption mono"><?= htmlspecialchars($d['creado_en']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="simulador" hidden>
        <p class="caption" style="margin-bottom:1rem">Marca jugadores de tu watchlist como "en simulador" para ver el coste hipotético de ficharlos, sin ejecutar nada real.</p>
        <?php if ($participacionActiva === null): ?>
            <div class="empty-state"><i class="ph ph-shield-warning"></i><h3>No tienes un club en la temporada activa</h3></div>
        <?php elseif ($simulador['jugadores'] === []): ?>
            <div class="empty-state"><i class="ph ph-calculator"></i><h3>Sin jugadores en el simulador</h3><p class="caption">Ve a la pestaña Watchlist y pulsa "Incluir" en los que quieras probar.</p></div>
        <?php else: ?>
            <?php
                $capProyectado = $capUsadoReal + $simulador['total_salarial'];
                $superaCap = $capProyectado > $capMaximo;
            ?>
            <div class="grid-3" style="margin-bottom:1.5rem">
                <div class="card"><span class="caption">Cap usado hoy</span><p class="h3 mono" style="margin-top:.3rem"><?= number_format($capUsadoReal, 0, ',', '.') ?> €</p></div>
                <div class="card"><span class="caption">+ Simulador</span><p class="h3 mono" style="margin-top:.3rem">+<?= number_format($simulador['total_salarial'], 0, ',', '.') ?> €</p></div>
                <div class="card">
                    <span class="caption">Cap proyectado</span>
                    <p class="h3 mono" style="margin-top:.3rem;color:<?= $superaCap ? 'var(--danger)' : 'var(--success)' ?>"><?= number_format($capProyectado, 0, ',', '.') ?> €</p>
                    <span class="caption">de <?= number_format($capMaximo, 0, ',', '.') ?> € máximo</span>
                </div>
            </div>
            <?php if ($superaCap): ?>
                <div class="alert alert-danger" style="margin-bottom:1rem">Esta combinación supera el Salary Cap. No podrías ficharlos todos a la vez sin liberar salario antes.</div>
            <?php endif; ?>
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Jugador</th><th>Tier</th><th class="num">Salario base</th></tr></thead>
                    <tbody>
                    <?php foreach ($simulador['jugadores'] as $w): ?>
                        <tr>
                            <td><?= htmlspecialchars($w['jugador_nombre']) ?></td>
                            <td><span class="chip <?= tier_chip_class($w['tier_nombre'] ?? '') ?>"><?= htmlspecialchars($w['tier_nombre'] ?? '—') ?></span></td>
                            <td class="num mono"><?= number_format((float) ($w['salario_base'] ?? 0), 0, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="comparador" hidden>
        <p class="caption" style="margin-bottom:1rem">Compara hasta 3 jugadores de la liga lado a lado.</p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="field" style="margin-bottom:0;min-width:220px">
                    <label class="field-label" for="comparador-<?= $i ?>">Jugador <?= $i ?></label>
                    <select class="select" id="comparador-<?= $i ?>" onchange="TRcomparador.actualizar()">
                        <option value="">—</option>
                        <?php foreach ($todosLosJugadores as $j): ?>
                            <option value="<?= (int) $j['id'] ?>"><?= htmlspecialchars($j['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endfor; ?>
        </div>
        <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table class="tbl" id="tabla-comparador">
                <thead><tr><th>Atributo</th><th data-col="1">—</th><th data-col="2">—</th><th data-col="3">—</th></tr></thead>
                <tbody>
                    <tr><td>Posición</td><td data-campo="posicion" data-col="1"></td><td data-campo="posicion" data-col="2"></td><td data-campo="posicion" data-col="3"></td></tr>
                    <tr><td>Tier</td><td data-campo="tier_nombre" data-col="1"></td><td data-campo="tier_nombre" data-col="2"></td><td data-campo="tier_nombre" data-col="3"></td></tr>
                    <tr><td>Salario base</td><td data-campo="salario_base" data-col="1"></td><td data-campo="salario_base" data-col="2"></td><td data-campo="salario_base" data-col="3"></td></tr>
                    <tr><td>Afinidad</td><td data-campo="afinidad_nombre" data-col="1"></td><td data-campo="afinidad_nombre" data-col="2"></td><td data-campo="afinidad_nombre" data-col="3"></td></tr>
                    <tr><td>Club</td><td data-campo="club_nombre" data-col="1"></td><td data-campo="club_nombre" data-col="2"></td><td data-campo="club_nombre" data-col="3"></td></tr>
                    <tr><td>Estado</td><td data-campo="estado_legible" data-col="1"></td><td data-campo="estado_legible" data-col="2"></td><td data-campo="estado_legible" data-col="3"></td></tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div data-tab-panel="planificador" hidden>
        <p class="caption" style="margin-bottom:1rem">Coloca en el campo a cualquier jugador de la liga — el tuyo, uno de tu watchlist, o cualquiera — para planificar una plantilla teórica: 11 titulares, 5 suplentes y reservas sin límite. No ejecuta ningún fichaje real. Toca un jugador del banquillo y luego un puesto; también puedes arrastrarlo.</p>

        <div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap">
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="planificador-formacion">Formación</label>
                <select class="select" id="planificador-formacion" onchange="TRplanificador.cambiarFormacion()"></select>
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="field-label" for="planificador-modo">Vista</label>
                <select class="select" id="planificador-modo" onchange="TRplanificador.redibujarCampo()">
                    <option value="equilibrio">Equilibrio</option>
                    <option value="ataque">Ataque</option>
                    <option value="defensa">Defensa</option>
                </select>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="btn-planificador-cancelar-seleccion" hidden onclick="TRplanificador.cancelarSeleccion()">Cancelar selección</button>
            <form method="post" style="margin:0" onsubmit="return confirm('¿Vaciar todo el campo (titulares y suplentes)?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="planificador_limpiar_todo">
                <button type="submit" class="btn btn-ghost btn-sm">Vaciar campo</button>
            </form>
        </div>

        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:flex-start">
            <div>
                <div id="campo-tactico" style="position:relative;width:min(100%,480px);aspect-ratio:2/3;background:linear-gradient(180deg,#1a3a1f,#0f2413);border:2px solid rgba(255,255,255,.15);border-radius:var(--r-lg);flex-shrink:0;overflow:hidden">
                    <?php foreach (EstrategiaEngine::SLOTS_TITULARES as $slot): ?>
                        <?php $ocupante = $planificador[$slot] ?? null; ?>
                        <button type="button" class="planificador-slot" data-slot="<?= $slot ?>" data-ocupado="<?= $ocupante !== null ? '1' : '0' ?>"
                            style="position:absolute;width:64px;display:flex;flex-direction:column;align-items:center;gap:.2rem;background:none;border:none;cursor:pointer"
                            onclick="TRplanificador.clicSlot('<?= $slot ?>')">
                            <?php if ($ocupante !== null): ?>
                                <?php if (!empty($ocupante['foto_url'])): ?>
                                    <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($ocupante['foto_url']) ?>" alt="" loading="lazy" style="border-color:var(--accent);border-width:2px">
                                <?php else: ?>
                                    <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4);border-color:var(--accent);border-width:2px"><i class="ph ph-user"></i></span>
                                <?php endif; ?>
                                <span class="caption" style="background:rgba(0,0,0,.7);color:#fff;padding:.1rem .35rem;border-radius:var(--r-sm);max-width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($ocupante['nombre']) ?></span>
                            <?php else: ?>
                                <span aria-hidden="true" style="width:40px;height:40px;border-radius:50%;border:2px dashed rgba(255,255,255,.4);display:grid;place-items:center;color:rgba(255,255,255,.6)"><i class="ph ph-plus"></i></span>
                                <span class="caption planificador-slot-rol" style="background:rgba(0,0,0,.5);color:#fff;padding:.1rem .35rem;border-radius:var(--r-sm)"><?= $slot === 'por' ? 'POR' : '' ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <h3 class="h3" style="margin-top:1.5rem">Suplentes</h3>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.75rem">
                    <?php foreach (EstrategiaEngine::SLOTS_SUPLENTES as $slot): ?>
                        <?php $ocupanteSup = $planificador[$slot] ?? null; ?>
                        <button type="button" class="planificador-slot" data-slot="<?= $slot ?>" data-ocupado="<?= $ocupanteSup !== null ? '1' : '0' ?>"
                            style="width:64px;display:flex;flex-direction:column;align-items:center;gap:.2rem;background:none;border:none;cursor:pointer"
                            onclick="TRplanificador.clicSlot('<?= $slot ?>')">
                            <?php if ($ocupanteSup !== null): ?>
                                <?php if (!empty($ocupanteSup['foto_url'])): ?>
                                    <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($ocupanteSup['foto_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                <?php endif; ?>
                                <span class="caption" style="max-width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($ocupanteSup['nombre']) ?></span>
                            <?php else: ?>
                                <span aria-hidden="true" class="jugador-foto" style="display:grid;place-items:center;color:var(--ink-4);border:2px dashed var(--line)"><i class="ph ph-plus"></i></span>
                                <span class="caption">SUP</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <h3 class="h3" style="margin-top:1.5rem">Reservas (<?= count($reservasPlanificador) ?>/<?= EstrategiaEngine::MAX_RESERVAS_PLANIFICADOR ?>)</h3>
                <p class="caption" style="margin-top:.25rem">Hasta <?= EstrategiaEngine::MAX_RESERVAS_PLANIFICADOR ?>, sin posición fija: añádelos o quítalos directamente desde el banquillo.</p>
                <div id="lista-reservas-planificador" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.75rem">
                    <?php if ($reservasPlanificador === []): ?>
                        <p class="caption">Todavía no has añadido reservas.</p>
                    <?php endif; ?>
                    <?php foreach ($reservasPlanificador as $r): ?>
                        <div style="width:64px;text-align:center;position:relative">
                            <?php if (!empty($r['foto_url'])): ?>
                                <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($r['foto_url']) ?>" alt="" loading="lazy" style="margin:0 auto">
                            <?php else: ?>
                                <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4);margin:0 auto"><i class="ph ph-user"></i></span>
                            <?php endif; ?>
                            <div class="caption" style="margin-top:.2rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['nombre']) ?></div>
                            <form method="post" style="margin-top:.2rem">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="planificador_reserva_quitar">
                                <input type="hidden" name="jugador_id" value="<?= (int) $r['jugador_id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" style="padding:.15rem .4rem;font-size:.7rem">Quitar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="flex:1;min-width:260px">
                <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.75rem">
                    <div class="field" style="margin-bottom:0;flex:1;min-width:160px">
                        <label class="field-label" for="planificador-buscar">Banquillo — busca un jugador</label>
                        <input class="input" type="search" id="planificador-buscar" placeholder="Nombre del jugador" oninput="TRplanificador.filtrarBanquillo()">
                    </div>
                    <div class="field" style="margin-bottom:0;min-width:160px">
                        <label class="field-label" for="planificador-buscar-club">Club</label>
                        <select class="select" id="planificador-buscar-club" onchange="TRplanificador.filtrarBanquillo()">
                            <option value="">Todos</option>
                            <?php $clubesBanquillo = array_unique(array_filter(array_column($todosLosJugadores, 'club_nombre'))); sort($clubesBanquillo); ?>
                            <?php foreach ($clubesBanquillo as $clubBanquillo): ?>
                                <option value="<?= htmlspecialchars($clubBanquillo) ?>"><?= htmlspecialchars($clubBanquillo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="banquillo-planificador" style="display:flex;flex-direction:column;gap:.4rem;max-height:520px;overflow-y:auto">
                    <?php foreach ($todosLosJugadores as $j): ?>
                        <div class="planificador-banquillo-item fila-jugador" data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>" data-jugador-id="<?= (int) $j['id'] ?>" data-jugador-nombre="<?= htmlspecialchars($j['nombre'], ENT_QUOTES) ?>" data-jugador-foto="<?= htmlspecialchars($j['foto_url'] ?? '', ENT_QUOTES) ?>" data-club="<?= htmlspecialchars($j['club_nombre'] ?? '') ?>"
                            style="display:flex;align-items:center;gap:.5rem;width:100%;padding:.4rem .5rem;border-radius:var(--r-sm);border:1px solid transparent">
                            <button type="button" style="display:flex;align-items:center;gap:.5rem;flex:1;text-align:left;background:none;border:none;cursor:pointer;padding:0" onclick="TRplanificador.clicBanquillo(this.closest('.planificador-banquillo-item'))">
                                <?php if (!empty($j['foto_url'])): ?>
                                    <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($j['nombre']) ?> <span class="caption"><?= htmlspecialchars($j['posicion']) ?></span></span>
                            </button>
                            <form method="post" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <?php if (in_array((int) $j['id'], $idsReservasPlanificador, true)): ?>
                                    <input type="hidden" name="accion" value="planificador_reserva_quitar">
                                    <button type="submit" class="btn btn-icon tt" data-tt="Quitar de reservas"><i class="ph ph-bookmark-simple ph-fill"></i></button>
                                <?php elseif (count($reservasPlanificador) >= EstrategiaEngine::MAX_RESERVAS_PLANIFICADOR): ?>
                                    <button type="button" class="btn btn-icon tt" data-tt="Reservas completas (4/4)" disabled><i class="ph ph-bookmark-simple"></i></button>
                                <?php else: ?>
                                    <input type="hidden" name="accion" value="planificador_reserva_agregar">
                                    <button type="submit" class="btn btn-icon tt" data-tt="Añadir a reservas"><i class="ph ph-bookmark-simple"></i></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <form method="post" id="form-planificador-asignar" hidden>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="planificador_asignar">
            <input type="hidden" name="slot" id="planificador-form-slot" value="">
            <input type="hidden" name="jugador_id" id="planificador-form-jugador-id" value="">
        </form>
        <form method="post" id="form-planificador-limpiar-slot" hidden>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="planificador_limpiar_slot">
            <input type="hidden" name="slot" id="planificador-limpiar-slot" value="">
        </form>

        <div class="card super-intro-card" style="margin:2rem 0 1.5rem">
            <div class="card-seccion-label"><i class="ph ph-flask"></i> Supertécnicas</div>
            <p class="caption" style="margin-top:.4rem;max-width:70ch">
                Asigna supertécnicas (e hipertécnicas) a cualquier jugador de la liga o a un jugador de prueba
                inventado por ti — hasta 4 por jugador — y comprueba qué metarráfagas se activarían. Es privado:
                solo tú lo ves, y nunca se conecta con los jugadores reales del mercado ni con sus fichas.
            </p>
            <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap;align-items:flex-end">
                <form id="form-super-nuevo-jugador" method="post" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin:0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="accion" value="super_jugador_crear">
                    <div class="field" style="margin-bottom:0;min-width:220px">
                        <label class="field-label" for="super-nuevo-nombre">Nuevo jugador de prueba</label>
                        <input class="input" id="super-nuevo-nombre" name="nombre" type="text" placeholder="Cualquier nombre" required maxlength="60">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="ph ph-user-plus"></i> Crear</button>
                </form>
                <div class="field" style="margin-bottom:0;min-width:220px">
                    <label class="field-label" for="super-jugador-real">Añadir un jugador real</label>
                    <select class="select" id="super-jugador-real">
                        <option value="">Elegir jugador…</option>
                        <?php foreach ($todosLosJugadores as $j): ?>
                            <option value="<?= (int) $j['id'] ?>" data-foto="<?= htmlspecialchars($j['foto_url'] ?? '', ENT_QUOTES) ?>"><?= htmlspecialchars($j['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-secondary" id="btn-super-anadir-real"><i class="ph ph-plus"></i> Añadir a la lista</button>
            </div>
        </div>

        <div id="super-roster" class="super-roster-grid" style="margin-bottom:1.5rem"></div>

        <div id="super-empty-state" class="empty-state" style="margin:1rem 0 1.5rem">
            <i class="ph ph-flask"></i>
            <h3>Todavía no hay jugadores en el simulador</h3>
            <p>Crea uno de prueba o añade uno real arriba para empezar a probar combinaciones de supertécnicas.</p>
        </div>

        <!-- Márgenes en las dos secciones, no solo en la de en medio: con
             #super-metarrafagas-wrap oculto (hidden quita el elemento del
             flujo, así que su margin no cuenta para nada) el checklist
             quedaba pegado directamente al roster/empty-state — exactamente
             lo que se reportó como "no respira". Ahora cada bloque trae su
             propio aire, así que da igual cuál de los dos esté oculto. -->
        <div id="super-metarrafagas-wrap" hidden style="margin:0 0 1.5rem">
            <div class="card super-mr-card con-filo">
                <div class="card-seccion-label"><i class="ph-fill ph-lightning"></i> Metarráfagas detectadas</div>
                <div id="super-metarrafagas-activas" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-top:.9rem"></div>
            </div>
        </div>

        <div id="super-mr-checklist-wrap" hidden style="margin:0 0 1.5rem">
            <div class="card">
                <div class="card-seccion-label"><i class="ph ph-list-checks"></i> Requisitos de todas las metarráfagas</div>
                <p class="caption" style="margin-top:.4rem">Gris: sin empezar. Ámbar: te falta al menos un requisito. Verde: activada.</p>
                <div id="super-mr-checklist" class="super-mr-check-grid"></div>
            </div>
        </div>

        <form method="post" id="form-super-jugador-eliminar" hidden>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="super_jugador_eliminar">
            <input type="hidden" name="jugador_simulado_id" id="super-form-jugador-simulado-id" value="">
        </form>
        <form method="post" id="form-super-tecnica-agregar" hidden>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="super_tecnica_agregar">
            <input type="hidden" name="jugador_id" id="super-form-jugador-id" value="">
            <input type="hidden" name="jugador_simulado_id" id="super-form-tecnica-jugador-simulado-id" value="">
            <input type="hidden" name="nombre" id="super-form-tecnica-nombre" value="">
            <input type="hidden" name="afinidad" id="super-form-tecnica-afinidad" value="">
            <input type="hidden" name="tension" id="super-form-tecnica-tension" value="">
            <input type="hidden" name="categoria" id="super-form-tecnica-categoria" value="">
            <input type="hidden" name="subcategoria" id="super-form-tecnica-subcategoria" value="">
            <input type="hidden" name="hipertecnica" id="super-form-tecnica-hipertecnica" value="">
            <input type="hidden" name="despertar_variante" id="super-form-tecnica-despertar-variante" value="">
            <input type="hidden" name="armadura" id="super-form-tecnica-armadura" value="">
        </form>
        <form method="post" id="form-super-tecnica-eliminar" hidden>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="super_tecnica_eliminar">
            <input type="hidden" name="tecnica_id" id="super-form-tecnica-id" value="">
        </form>
    </div>
</main>

<div class="modal-bg" id="modal-super-tecnica">
    <div class="modal" style="width:min(92vw,560px)">
        <div class="modal-head modal-jugador-head">
            <div style="display:flex;align-items:center;gap:1rem">
                <img id="super-modal-foto" class="jugador-foto-lg" src="" alt="" style="display:none" referrerpolicy="no-referrer">
                <span id="super-modal-foto-placeholder" class="jugador-foto-lg" style="display:grid;place-items:center;color:var(--ink-4)" aria-hidden="true"><i class="ph ph-user"></i></span>
                <div>
                    <span class="caption">Nueva supertécnica para</span>
                    <h2 id="super-modal-nombre" class="modal-jugador-nombre-grande" style="font-size:1.3rem;margin-top:.15rem">—</h2>
                </div>
            </div>
            <button type="button" class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <form id="form-super-tecnica-modal">
                <div class="super-tipo-toggle" role="group" aria-label="Tipo de técnica">
                    <button type="button" class="on" data-tipo-tecnica="supertecnica">Supertécnica</button>
                    <button type="button" data-tipo-tecnica="hiper">Hipertécnica</button>
                </div>

                <div class="field" style="margin-bottom:.6rem">
                    <label class="field-label">Nombre</label>
                    <input class="input" type="text" name="nombre" required maxlength="60" placeholder="Ej. Tornado de fuego">
                </div>
                <div class="field" style="margin-bottom:.6rem">
                    <label class="field-label">Afinidad</label>
                    <select class="select" name="afinidad">
                        <option value="aire">Aire</option>
                        <option value="fuego">Fuego</option>
                        <option value="bosque">Bosque</option>
                        <option value="montana">Montaña</option>
                        <option value="neutra">Neutra</option>
                        <option value="sin_afinidad">Sin afinidad</option>
                    </select>
                </div>

                <div data-grupo-supertecnica>
                    <div class="field" style="margin-bottom:.6rem">
                        <label class="field-label">Tensión / Potencia</label>
                        <select class="select" name="tension">
                            <option value="40">40 tensión — 30 potencia</option>
                            <option value="50">50 tensión — 50 potencia</option>
                            <option value="60">60 tensión — 60 potencia</option>
                            <option value="70">70 tensión — 70 potencia</option>
                            <option value="80">80 tensión — 85 potencia</option>
                            <option value="100">100 tensión — 100 potencia</option>
                        </select>
                    </div>
                    <div class="super-tecnica-form-grid">
                        <div class="field" style="margin-bottom:.6rem">
                            <label class="field-label">Categoría</label>
                            <select class="select" name="categoria">
                                <option value="parada">Parada</option>
                                <option value="defensa">Defensa</option>
                                <option value="regate">Regate</option>
                                <option value="tiro">Tiro</option>
                            </select>
                        </div>
                        <div class="field" style="margin-bottom:.6rem">
                            <label class="field-label">Subcategoría</label>
                            <select class="select" name="subcategoria">
                                <option value="">Sin subcategoría</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div data-grupo-hiper hidden>
                    <p class="caption" style="margin:.2rem 0 .5rem">Las hipertécnicas no cuestan tensión.</p>
                    <div class="field" style="margin-bottom:.6rem">
                        <label class="field-label">Hipertécnica</label>
                        <select class="select" name="hipertecnica">
                            <option value="espiritu_guerrero">Espíritu guerrero</option>
                            <option value="totem">Tótem</option>
                            <option value="mixi_max">Mixi Max</option>
                            <option value="despertar">Despertar</option>
                        </select>
                    </div>
                    <label class="field" style="margin-bottom:.6rem;display:flex;align-items:center;gap:.5rem;flex-direction:row" data-campo-armadura hidden>
                        <input type="checkbox" name="armadura" value="1">
                        <span>Con armadura <span class="caption">(opcional, solo espíritu guerrero)</span></span>
                    </label>
                    <div class="field" style="margin-bottom:.6rem" data-campo-despertar hidden>
                        <label class="field-label">¿Qué Despertar?</label>
                        <select class="select" name="despertar_variante">
                            <option value="porteria">Determinación de portero</option>
                            <option value="guardian">Guardián Férreo</option>
                            <option value="impulso">Impulso Temporal</option>
                            <option value="catalizador">Catalizador Elemental</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button type="submit" form="form-super-tecnica-modal" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Guardar supertécnica</button>
        </div>
    </div>
</div>
<script>
var TR_JUGADORES_COMPARADOR = <?= json_encode(array_map(static function (array $j): array {
    return [
        'id' => (int) $j['id'],
        'nombre' => $j['nombre'],
        'posicion' => $j['posicion'],
        'tier_nombre' => $j['tier_nombre'] ?? '—',
        'salario_base' => $j['salario_base'] !== null ? number_format((float) $j['salario_base'], 0, ',', '.') . ' €' : '—',
        'afinidad_nombre' => $j['afinidad_nombre'] ?? '—',
        'club_nombre' => $j['club_nombre'] ?? '—',
        'estado_legible' => $j['estado'] === 'ACTIVO' ? 'Contratado' : 'Agente libre',
    ];
}, $todosLosJugadores), JSON_UNESCAPED_UNICODE) ?>;

var TR_SUPER_ROSTER_INICIAL = <?= json_encode($superRoster, JSON_UNESCAPED_UNICODE) ?>;
var TR_SUPER_CSRF = <?= json_encode(Csrf::token(), JSON_UNESCAPED_UNICODE) ?>;

var TRcomparador = {
    actualizar: function () {
        for (var col = 1; col <= 3; col++) {
            var select = document.getElementById('comparador-' + col);
            var id = parseInt(select.value, 10);
            var jugador = TR_JUGADORES_COMPARADOR.find(function (j) { return j.id === id; });

            var cabecera = document.querySelector('#tabla-comparador thead th[data-col="' + col + '"]');
            cabecera.textContent = jugador ? jugador.nombre : '—';

            document.querySelectorAll('#tabla-comparador tbody td[data-col="' + col + '"]').forEach(function (celda) {
                var campo = celda.dataset.campo;
                celda.textContent = jugador ? jugador[campo] : '—';
            });
        }
    }
};

/* Planificador táctico (Fase 2): clic-para-asignar como alternativa
   accesible obligatoria (SC 2.5.7) + arrastrar y soltar de cortesía para
   quien prefiera el ratón. Ningún estado se guarda solo en cliente: cada
   asignación se envía al servidor de inmediato (recarga la página), igual
   que el resto de Mi Estrategia. */
var TRplanificador = {
    seleccionado: null,

    clicBanquillo: function (el) {
        document.querySelectorAll('.planificador-banquillo-item').forEach(function (b) { b.style.borderColor = 'transparent'; });
        if (TRplanificador.seleccionado && TRplanificador.seleccionado.id === el.dataset.jugadorId) {
            TRplanificador.cancelarSeleccion();
            return;
        }
        el.style.borderColor = 'var(--accent)';
        TRplanificador.seleccionado = { id: el.dataset.jugadorId, nombre: el.dataset.jugadorNombre };
        document.getElementById('btn-planificador-cancelar-seleccion').hidden = false;
    },

    cancelarSeleccion: function () {
        TRplanificador.seleccionado = null;
        document.querySelectorAll('.planificador-banquillo-item').forEach(function (b) { b.style.borderColor = 'transparent'; });
        document.getElementById('btn-planificador-cancelar-seleccion').hidden = true;
    },

    clicSlot: function (slot) {
        if (TRplanificador.seleccionado) {
            document.getElementById('planificador-form-slot').value = slot;
            document.getElementById('planificador-form-jugador-id').value = TRplanificador.seleccionado.id;
            document.getElementById('form-planificador-asignar').submit();
            return;
        }
        var boton = document.querySelector('.planificador-slot[data-slot="' + slot + '"]');
        if (boton && boton.dataset.ocupado === '1') {
            document.getElementById('planificador-limpiar-slot').value = slot;
            document.getElementById('form-planificador-limpiar-slot').submit();
        }
    },

    filtrarBanquillo: function () {
        var texto = document.getElementById('planificador-buscar').value.trim().toLowerCase();
        var club = document.getElementById('planificador-buscar-club').value;
        document.querySelectorAll('.planificador-banquillo-item').forEach(function (b) {
            var coincide = b.dataset.nombre.indexOf(texto) !== -1 && (club === '' || b.dataset.club === club);
            b.style.display = coincide ? '' : 'none';
        });
    },

    /* Formaciones (CLAUDE.md §12): cada una define el rol (DEF/MED/DEL) y la
       coordenada base (equilibrio) de los 10 slots de campo o1..o10 — el
       portero siempre va fijo abajo al centro. "Ataque"/"Defensa" son un
       único desplazamiento genérico aplicado en cliente sobre esa base (las
       líneas se adelantan o se repliegan, y los extremos se abren un poco
       más en ataque), no 16 mapas de coordenadas distintos escritos a mano:
       ponytail — más simple y sigue dando una vista claramente distinta por
       modo y por formación. */
    formaciones: {
        diamante442: { nombre: 'Diamante 4-4-2', roles: ['DEF', 'DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'MED', 'DEL', 'DEL'],
            coords: [[15, 74], [38, 76], [62, 76], [85, 74], [50, 56], [25, 42], [75, 42], [50, 28], [35, 12], [65, 12]] },
        caja442: { nombre: 'Caja 4-4-2', roles: ['DEF', 'DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'MED', 'DEL', 'DEL'],
            coords: [[15, 74], [38, 76], [62, 76], [85, 74], [15, 46], [38, 46], [62, 46], [85, 46], [35, 12], [65, 12]] },
        libre352: { nombre: 'Libre 3-5-2', roles: ['DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'MED', 'MED', 'DEL', 'DEL'],
            coords: [[25, 76], [50, 78], [75, 76], [10, 46], [30, 46], [50, 46], [70, 46], [90, 46], [35, 12], [65, 12]] },
        triangulo433: { nombre: 'Triángulo 4-3-3', roles: ['DEF', 'DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'DEL', 'DEL', 'DEL'],
            coords: [[15, 74], [38, 76], [62, 76], [85, 74], [50, 54], [25, 38], [75, 38], [18, 12], [50, 12], [82, 12]] },
        delta433: { nombre: 'Delta 4-3-3', roles: ['DEF', 'DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'DEL', 'DEL', 'DEL'],
            coords: [[15, 74], [38, 76], [62, 76], [85, 74], [32, 48], [68, 48], [50, 32], [18, 12], [50, 12], [82, 12]] },
        balanza451: { nombre: 'Balanza 4-5-1', roles: ['DEF', 'DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'MED', 'MED', 'DEL'],
            coords: [[15, 74], [38, 76], [62, 76], [85, 74], [10, 44], [30, 44], [50, 44], [70, 44], [90, 44], [50, 10]] },
        hexa361: { nombre: 'Hexa 3-6-1', roles: ['DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'MED', 'MED', 'MED', 'DEL'],
            coords: [[25, 76], [50, 78], [75, 76], [12, 52], [36, 56], [64, 56], [88, 52], [30, 34], [70, 34], [50, 10]] },
        dobleVolante541: { nombre: 'Doble volante 5-4-1', roles: ['DEF', 'DEF', 'DEF', 'DEF', 'DEF', 'MED', 'MED', 'MED', 'MED', 'DEL'],
            coords: [[10, 76], [30, 78], [50, 78], [70, 78], [90, 76], [38, 52], [62, 52], [15, 40], [85, 40], [50, 10]] }
    },

    cambiarFormacion: function () {
        localStorage.setItem('tr_planificador_formacion', document.getElementById('planificador-formacion').value);
        TRplanificador.redibujarCampo();
    },

    redibujarCampo: function () {
        var formacion = TRplanificador.formaciones[document.getElementById('planificador-formacion').value];
        if (!formacion) return;
        var modo = document.getElementById('planificador-modo').value;
        var deltaY = modo === 'ataque' ? -7 : (modo === 'defensa' ? 7 : 0);
        var factorX = modo === 'ataque' ? 1.08 : (modo === 'defensa' ? 0.94 : 1);

        var porBoton = document.querySelector('.planificador-slot[data-slot="por"]');
        if (porBoton) {
            porBoton.style.left = '50%';
            porBoton.style.top = '92%';
            porBoton.style.transform = 'translate(-50%,-50%)';
        }

        for (var i = 1; i <= 10; i++) {
            var boton = document.querySelector('.planificador-slot[data-slot="o' + i + '"]');
            if (!boton) continue;
            var par = formacion.coords[i - 1];
            var x = Math.max(8, Math.min(92, 50 + (par[0] - 50) * factorX));
            var y = Math.max(8, Math.min(92, par[1] + deltaY));
            boton.style.left = x + '%';
            boton.style.top = y + '%';
            boton.style.transform = 'translate(-50%,-50%)';
            if (boton.dataset.ocupado === '0') {
                var rol = boton.querySelector('.planificador-slot-rol');
                if (rol) rol.textContent = formacion.roles[i - 1];
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.planificador-banquillo-item').forEach(function (el) {
        el.draggable = true;
        el.addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('text/plain', el.dataset.jugadorId);
        });
    });
    document.querySelectorAll('.planificador-slot').forEach(function (el) {
        el.addEventListener('dragover', function (e) { e.preventDefault(); });
        el.addEventListener('drop', function (e) {
            e.preventDefault();
            var jugadorId = e.dataTransfer.getData('text/plain');
            if (!jugadorId) return;
            document.getElementById('planificador-form-slot').value = el.dataset.slot;
            document.getElementById('planificador-form-jugador-id').value = jugadorId;
            document.getElementById('form-planificador-asignar').submit();
        });
    });

    var selectFormacion = document.getElementById('planificador-formacion');
    Object.keys(TRplanificador.formaciones).forEach(function (clave) {
        var opt = document.createElement('option');
        opt.value = clave;
        opt.textContent = TRplanificador.formaciones[clave].nombre;
        selectFormacion.appendChild(opt);
    });
    var formacionGuardada = localStorage.getItem('tr_planificador_formacion');
    if (formacionGuardada && TRplanificador.formaciones[formacionGuardada]) {
        selectFormacion.value = formacionGuardada;
    }
    TRplanificador.redibujarCampo();
});
</script>
<script src="<?= asset_version($base, 'assets/js/supertecnicas.js') ?>"></script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
