<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_revisado') {
    try {
        $contratoId = (int) ($_POST['contrato_id'] ?? 0);
        $contratoRevisado = $contratoRepositorio->buscarPorId($contratoId);
        $contratos->marcarRevisado($contratoId, (int) $usuario['id']);

        if ($contratoRevisado !== null) {
            $participacionRevisada = $participaciones->buscarPorId((int) $contratoRevisado['participacion_id']);
            $jugadorRevisado = $jugadores->buscarPorId((int) $contratoRevisado['jugador_id']);
            $notificaciones->crear(
                $participacionRevisada['usuario_presidente_id'] ?? null,
                'CONTRATO_REVISADO',
                "El administrador ha revisado y aprobado el fichaje de {$jugadorRevisado['nombre']}.",
                "plantilla.php?participacion_id={$contratoRevisado['participacion_id']}"
            );
        }

        $exito = 'Contrato marcado como revisado.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'rechazar') {
    try {
        ModoPruebas::bloquearSiActivo();
        $contratoId = (int) ($_POST['contrato_id'] ?? 0);
        $contratoRechazado = $contratoRepositorio->buscarPorId($contratoId);
        $jugadorRechazado = $contratoRechazado !== null ? $jugadores->buscarPorId((int) $contratoRechazado['jugador_id']) : null;
        $contratos->rechazarContratoPendienteRevision($contratoId, (int) $usuario['id']);

        if ($contratoRechazado !== null) {
            $participacionRechazada = $participaciones->buscarPorId((int) $contratoRechazado['participacion_id']);
            $notificaciones->crear(
                $participacionRechazada['usuario_presidente_id'] ?? null,
                'CONTRATO_RECHAZADO',
                "El administrador ha rechazado el fichaje de {$jugadorRechazado['nombre']}: vuelve a agente libre sin tier.",
                'mercado.php'
            );
        }

        $exito = 'Fichaje rechazado. El jugador vuelve a agente libre sin tier.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$pendientes = $contratos->listarPendientesDeRevision();
$paginaTitulo = 'Contratos pendientes de revisión';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_pendientes';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Contratos pendientes de revisión</h1>
            <p>Fichajes directos de agentes libres sin tier (oficiales recién importados o jugadores externos): el tier y el salario los eligió quien fichó, sin aprobación previa.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ($pendientes === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-check-circle"></i>
                <h3>Nada pendiente de revisión</h3>
                <p>Todos los fichajes directos ya han sido revisados.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead><tr><th>Jugador</th><th>Origen</th><th>Club</th><th>Tier</th><th class="num">Salario</th><th>Duración</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($pendientes as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['jugador_nombre']) ?> <span class="caption">(<?= htmlspecialchars($c['posicion']) ?>)</span></td>
                    <td class="caption"><?= htmlspecialchars(etiqueta_legible($c['origen'])) ?></td>
                    <td><?= htmlspecialchars($c['club_nombre']) ?></td>
                    <td><span class="chip"><?= htmlspecialchars($c['tier_nombre']) ?></span></td>
                    <td class="num mono"><?= number_format((float) $c['salario_anual'], 0, ',', '.') ?> €</td>
                    <td class="caption"><?= (int) $c['duracion_temporadas'] ?> temporada(s)</td>
                    <td style="display:flex;gap:.5rem">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <input type="hidden" name="accion" value="marcar_revisado">
                            <input type="hidden" name="contrato_id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Marcar revisado</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-danger"
                            onclick='TRrevision.confirmarRechazar(<?= (int) $c['id'] ?>, <?= json_encode($c['jugador_nombre'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            Rechazar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<div class="modal-bg" id="modal-rechazar">
    <div class="modal modal-danger" role="dialog" aria-modal="true" aria-labelledby="modal-rechazar-title">
        <div class="modal-head">
            <h2 id="modal-rechazar-title">¿Rechazar este fichaje?</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <span id="modal-rechazar-texto"></span> El jugador vuelve a agente libre sin tier, como si nunca se hubiera fichado. Esta acción no se puede deshacer.
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="rechazar">
            <input type="hidden" name="contrato_id" id="modal-rechazar-contrato-id" value="">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-danger">Rechazar fichaje</button>
            </div>
        </form>
    </div>
</div>

<script>
var TRrevision = {
    confirmarRechazar: function (contratoId, nombreJugador) {
        document.getElementById('modal-rechazar-contrato-id').value = contratoId;
        document.getElementById('modal-rechazar-texto').textContent = 'Vas a rechazar el fichaje de ' + nombreJugador + '.';
        TR.abrirModal('modal-rechazar');
    }
};
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
