<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    try {
        $temporadas->crear(
            (int) ($_POST['numero'] ?? 0),
            trim((string) ($_POST['nombre'] ?? '')),
            (int) $usuario['id']
        );
        $exito = 'Temporada creada correctamente.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    try {
        $idTemporadaCambio = (int) ($_POST['temporada_id'] ?? 0);
        $nuevoEstado = (string) ($_POST['nuevo_estado'] ?? '');

        // Orquestado aquí, no dentro de un motor: procesar expiraciones es
        // Contratos, generar snapshot y cambiar el estado son Temporadas, y
        // Temporadas nunca debe depender de Contratos (Ley 19). El snapshot
        // se toma ANTES de expirar: si se tomara después, capturaría
        // plantillas ya vacías, no la foto de cómo compitió la liga.
        if ($nuevoEstado === 'CIERRE') {
            $temporadas->generarSnapshot($idTemporadaCambio, (int) $usuario['id']);
            $resultadosExpiracion = $contratos->procesarExpiracionesDeTemporada($idTemporadaCambio, (int) $usuario['id']);
            $temporadas->cambiarEstado($idTemporadaCambio, $nuevoEstado, (int) $usuario['id']);

            foreach ($resultadosExpiracion as $r) {
                if ($r['resultado'] === 'LIBERADO') {
                    $notificaciones->crear(
                        $r['usuario_presidente_id'],
                        'CONTRATO_FINALIZADO',
                        "El contrato de {$r['jugador_nombre']} ha terminado y ha pasado a agente libre.",
                        'dashboard.php'
                    );
                    continue;
                }
                $mensaje = match ($r['resultado']) {
                    'DESCUENTO' => "Ruleta de franquicia — {$r['jugador_nombre']}: retenido con descuento sobre su salario base.",
                    'MISMO_PRECIO' => "Ruleta de franquicia — {$r['jugador_nombre']}: retenido al mismo precio.",
                    'SALIDA_DIRECTA' => "Ruleta de franquicia — {$r['jugador_nombre']}: salida directa a agente libre sin restricciones.",
                    default => "Ruleta de franquicia resuelta para {$r['jugador_nombre']}.",
                };
                $notificaciones->crear($r['usuario_presidente_id'], 'RULETA_FRANQUICIA_RESUELTA', $mensaje, 'dashboard.php');
            }

            $totalExpirados = count($resultadosExpiracion);
            $exito = "Temporada cerrada. Snapshot generado. {$totalExpirados} contratos expirados a agencia libre.";
        } else {
            $ventanasCerradasPorMercado = [];
            if ($nuevoEstado === 'COMPETICION') {
                // Decisión confirmada: cerrar el mercado fuerza también el cierre de
                // cualquier ventana RFA en curso en toda la liga, a favor del ofertante.
                // Antes de cambiarEstado(): así el chequeo de 20 fichas exactas ya ve
                // el resultado final, no un estado a medio resolver.
                $ventanasCerradasPorMercado = $mercado->resolverTodasLasVentanasPorCierreDeMercado($idTemporadaCambio, (int) $usuario['id']);
            }

            $temporadas->cambiarEstado($idTemporadaCambio, $nuevoEstado, (int) $usuario['id']);

            foreach ($ventanasCerradasPorMercado as $v) {
                $ganadora = $participaciones->buscarPorId($v['participacion_ganadora_id']);
                $notificaciones->crear(
                    $ganadora['usuario_presidente_id'] ?? null,
                    'FICHAJE_AGENTE_LIBRE_GANADO',
                    "Fichaje de {$v['jugador_nombre']} confirmado: la ventana RFA se cerró junto con el mercado.",
                    "plantilla.php?participacion_id={$v['participacion_ganadora_id']}"
                );
            }

            // Título XVIII: "Mercado abierto" / "Mercado cerrado" avisan a toda
            // la liga, no solo a quien pulsa el botón.
            if (in_array($nuevoEstado, ['MERCADO_ABIERTO', 'COMPETICION'], true)) {
                $mensajeEstado = $nuevoEstado === 'MERCADO_ABIERTO'
                    ? 'El mercado de fichajes ya está abierto.'
                    : 'El mercado de fichajes se ha cerrado.';
                foreach ($participaciones->listarPorTemporada($idTemporadaCambio) as $p) {
                    $notificaciones->crear($p['usuario_presidente_id'] ?? null, 'ESTADO_MERCADO', $mensajeEstado, 'mercado.php');
                }
            }

            $exito = $ventanasCerradasPorMercado !== []
                ? 'Estado actualizado correctamente. ' . count($ventanasCerradasPorMercado) . ' ventana(s) RFA resueltas al cerrar el mercado.'
                : 'Estado actualizado correctamente.';
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'avisar_cierre_inminente') {
    try {
        $idTemporadaAviso = (int) ($_POST['temporada_id'] ?? 0);
        // Quejas esperables #3: "no sabía que cerraba en 15 minutos". Sin cron
        // no hay forma de disparar esto solo; el admin decide cuándo es "inminente"
        // y lo dispara a mano, igual que el resto de resoluciones por tiempo de la app.
        foreach ($participaciones->listarPorTemporada($idTemporadaAviso) as $p) {
            $notificaciones->crear($p['usuario_presidente_id'] ?? null, 'CIERRE_MERCADO_INMINENTE', 'El mercado va a cerrarse en breve. Revisa tus ofertas activas.', 'mercado.php');
        }
        $exito = 'Aviso de cierre inminente enviado a todos los presidentes.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'congelar') {
    try {
        $temporadas->pausarMercado((int) ($_POST['temporada_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Mercado congelado. No se podrán crear nuevas ofertas ni traspasos hasta reanudarlo.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reanudar') {
    try {
        $temporadas->reanudarMercado((int) ($_POST['temporada_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Mercado reanudado.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$listaTemporadas = $temporadas->listarTodas();

// Resumen previo (05-transfer_room_docs/01_bloqueante_primer_mercado/09): antes
// de abrir o cerrar el mercado, mostrar de un vistazo cuántos clubes están
// listos, en vez de que el admin lo descubra por prueba y error.
$resumenPorTemporada = [];
foreach ($listaTemporadas as $t) {
    if (!in_array($t['estado'], ['PRETEMPORADA', 'MERCADO_ABIERTO'], true)) {
        continue;
    }
    $filas = [];
    foreach ($participaciones->listarPorTemporada((int) $t['id']) as $p) {
        if (!in_array($p['estado'], ['PENDIENTE', 'CONFIRMADA', 'ACTIVA'], true)) {
            continue;
        }
        $filas[] = [
            'club' => $p['club_nombre'],
            'fichas' => $contratoRepositorio->contarActivosPorParticipacion((int) $p['id']),
            // Checklist de primer mercado (A.4): revisar franquicias antes de
            // abrir. Se cuenta aquí para poder mostrarlo junto al resto del
            // resumen, sin una pantalla aparte que el admin tenga que
            // recordar visitar por separado.
            'franquicias' => $jugadorRepositorio->contarFranquiciaPorParticipacion((int) $p['id']),
        ];
    }
    $resumenPorTemporada[(int) $t['id']] = $filas;
}
$paginaTitulo = 'Temporadas';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_mercado';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Temporadas</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="card" style="max-width:420px">
        <h2 class="h3">Crear temporada</h2>
        <form method="post" novalidate style="margin-top:1.25rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="crear">
            <div class="field">
                <label class="field-label" for="tp-numero">Número</label>
                <input class="input" id="tp-numero" type="number" name="numero" min="1" required>
            </div>
            <div class="field">
                <label class="field-label" for="tp-nombre">Nombre</label>
                <input class="input" id="tp-nombre" type="text" name="nombre" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Crear</button>
        </form>
    </div>

    <h2 class="h2" style="margin-top:2.5rem">Temporadas existentes</h2>
    <?php if ($listaTemporadas === []): ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="empty-state">
                <i class="ph ph-calendar-blank"></i>
                <h3>Sin temporadas todavía</h3>
                <p>Crea la primera con el formulario de arriba.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="tbl-wrap" style="margin-top:1rem">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead>
            <tr><th>Número</th><th>Nombre</th><th>Estado</th><th class="num">Salary Cap</th><th class="num">Franquicias</th><th>Acción</th></tr>
            </thead>
            <tbody>
            <?php foreach ($listaTemporadas as $t): ?>
                <tr>
                    <td class="mono"><?= (int) $t['numero'] ?></td>
                    <td><?= htmlspecialchars($t['nombre']) ?></td>
                    <td>
                        <span class="badge badge-info"><?= htmlspecialchars(etiqueta_legible($t['estado'])) ?></span>
                        <?php if ((bool) $t['congelada']): ?><span class="badge badge-warning"><i class="ph ph-snowflake"></i> Congelado</span><?php endif; ?>
                    </td>
                    <td class="num mono"><?= number_format((float) $t['salary_cap'], 0, ',', '.') ?> €</td>
                    <td class="num mono"><?= (int) $t['max_franquicias'] ?></td>
                    <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                        <?php foreach (SeasonEngine::transicionesDesde($t['estado']) as $siguiente): ?>
                            <?php
                                $clubesNoListos = $siguiente === 'COMPETICION' && isset($resumenPorTemporada[(int) $t['id']])
                                    ? count(array_filter($resumenPorTemporada[(int) $t['id']], fn ($f) => $f['fichas'] !== 20))
                                    : 0;
                            ?>
                            <?php
                                // Checklist de primer mercado (A.6): antes esto era un
                                // formulario que se enviaba solo, sin confirmación —
                                // el único cambio de estado de la app sin el patrón de
                                // modal reforzado que ya usan proteger/finalizar. Ahora
                                // el botón solo abre el modal compartido de abajo.
                                $textoConfirmacion = match ($siguiente) {
                                    'MERCADO_ABIERTO' => 'Se avisará a todos los presidentes de que el mercado ya está abierto.',
                                    'COMPETICION' => 'El mercado se cerrará: se resolverán todas las ventanas RFA pendientes a favor del ofertante y se avisará a todos los presidentes.',
                                    'CIERRE' => 'Se generará el snapshot de la liga y expirarán todos los contratos que tocan. Esta acción no se puede deshacer.',
                                    default => '',
                                };
                            ?>
                            <button type="button" class="btn btn-sm <?= $siguiente === 'CIERRE' ? 'btn-danger' : 'btn-secondary' ?> <?= $clubesNoListos > 0 ? 'tt' : '' ?>"
                                <?= $clubesNoListos > 0 ? 'disabled data-tt="' . $clubesNoListos . ' club(es) sin 20 fichas exactas"' : '' ?>
                                onclick='TRtemporadas.confirmarCambioEstado(<?= json_encode([
                                    'temporadaId' => (int) $t['id'],
                                    'nuevoEstado' => $siguiente,
                                    'nombre' => $t['nombre'],
                                    'texto' => $textoConfirmacion,
                                    'danger' => $siguiente === 'CIERRE',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="ph ph-arrow-right"></i> <?= htmlspecialchars($siguiente) ?>
                            </button>
                        <?php endforeach; ?>
                        <?php if (in_array($t['estado'], ['MERCADO_ABIERTO', 'MERCADO_EXTRAORDINARIO'], true)): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="<?= (bool) $t['congelada'] ? 'reanudar' : 'congelar' ?>">
                                <input type="hidden" name="temporada_id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"><i class="ph ph-snowflake"></i> <?= (bool) $t['congelada'] ? 'Reanudar mercado' : 'Congelar mercado' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($t['estado'], ['MERCADO_ABIERTO', 'MERCADO_EXTRAORDINARIO'], true)): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="avisar_cierre_inminente">
                                <input type="hidden" name="temporada_id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"><i class="ph ph-bell-ringing"></i> Avisar cierre inminente</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (isset($resumenPorTemporada[(int) $t['id']])): ?>
                    <tr>
                        <td colspan="6">
                            <div class="card" style="margin:.5rem 0">
                                <h3 class="h4"><?= $t['estado'] === 'PRETEMPORADA' ? 'Resumen antes de abrir el mercado' : 'Checklist antes de cerrar el mercado (20 fichas exactas por club)' ?></h3>
                                <?php $pendientes = array_filter($resumenPorTemporada[(int) $t['id']], fn ($f) => $f['fichas'] !== 20); ?>
                                <p class="caption" style="margin-top:.4rem">
                                    <?= count($resumenPorTemporada[(int) $t['id']]) ?> clubes en la temporada,
                                    <?= count($resumenPorTemporada[(int) $t['id']]) - count($pendientes) ?> con 20/20 fichas.
                                </p>
                                <?php if ($pendientes !== []): ?>
                                    <ul style="margin-top:.6rem">
                                        <?php foreach ($pendientes as $f): ?>
                                            <li><?= htmlspecialchars($f['club']) ?>: <span class="mono"><?= $f['fichas'] ?>/20</span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="body-sm" style="margin-top:.6rem;color:var(--success)"><i class="ph ph-check-circle"></i> Todos los clubes están listos.</p>
                                <?php endif; ?>

                                <?php if ($t['estado'] === 'PRETEMPORADA'): ?>
                                    <!-- Checklist de primer mercado (A.4): estado de franquicias visible
                                         antes de abrir, no una pantalla aparte que haya que recordar mirar. -->
                                    <h4 class="h4" style="margin-top:1.25rem;font-size:var(--fs-body)">Franquicias designadas (máx. <?= (int) $t['max_franquicias'] ?> por club)</h4>
                                    <ul style="margin-top:.6rem;columns:2;column-gap:1.5rem">
                                        <?php foreach ($resumenPorTemporada[(int) $t['id']] as $f): ?>
                                            <li><?= htmlspecialchars($f['club']) ?>: <span class="mono"><?= $f['franquicias'] ?>/<?= (int) $t['max_franquicias'] ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Checklist de primer mercado (A.6): confirmación explícita reforzada,
     mismo patrón que proteger/finalizar contrato — nunca un submit directo
     para un cambio de estado de mercado. -->
<div class="modal-bg" id="modal-confirmar-estado">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-confirmar-estado-title">
        <div class="modal-head">
            <h2 id="modal-confirmar-estado-title">¿Cambiar el estado?</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <span id="modal-confirmar-estado-texto"></span>
        </div>
        <form method="post" id="form-confirmar-estado">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="cambiar_estado">
            <input type="hidden" name="temporada_id" id="modal-confirmar-estado-temporada-id" value="">
            <input type="hidden" name="nuevo_estado" id="modal-confirmar-estado-nuevo-estado" value="">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary" id="modal-confirmar-estado-boton">Confirmar</button>
            </div>
        </form>
    </div>
</div>
<script>
var TRtemporadas = {
    confirmarCambioEstado: function (datos) {
        document.getElementById('modal-confirmar-estado-title').textContent =
            'Pasar "' + datos.nombre + '" a ' + datos.nuevoEstado + '?';
        document.getElementById('modal-confirmar-estado-texto').textContent = datos.texto || 'Vas a cambiar el estado de esta temporada.';
        document.getElementById('modal-confirmar-estado-temporada-id').value = datos.temporadaId;
        document.getElementById('modal-confirmar-estado-nuevo-estado').value = datos.nuevoEstado;
        var boton = document.getElementById('modal-confirmar-estado-boton');
        boton.className = 'btn ' + (datos.danger ? 'btn-danger' : 'btn-primary');
        boton.textContent = datos.danger ? 'Confirmar (no se puede deshacer)' : 'Confirmar';
        TR.abrirModal('modal-confirmar-estado');
    }
};
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
