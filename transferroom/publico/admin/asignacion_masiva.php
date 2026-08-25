<?php

declare(strict_types=1);

/**
 * Herramienta de asignación masiva de contrato inicial (05-transfer_room_docs/
 * 01_bloqueante_primer_mercado/07 y 08: "asignación en una única transacción
 * atómica de hasta 20 jugadores"). Firma en un solo envío el contrato inicial
 * (tier + duración) de todos los jugadores de una participación que todavía
 * no tienen tier — típicamente los recién importados del JSON oficial al
 * inscribir un club. Si uno solo no cabe en el Salary Cap, no se firma
 * ninguno: nunca queda una plantilla a medias por un fallo de uno solo.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$participacionId = (int) ($_GET['participacion_id'] ?? $_POST['participacion_id'] ?? 0);
$participacion = $participaciones->buscarPorId($participacionId);
if ($participacion === null) {
    http_response_code(404);
    exit('Participación no encontrada.');
}
$club = $clubes->buscarPorId($participacion['club_id']);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'firmar_lote') {
    $jugadorIds = array_map('intval', $_POST['jugador_id'] ?? []);
    $tierPorJugador = $_POST['tier_id'] ?? [];
    $duracionPorJugador = $_POST['duracion'] ?? [];

    $asignaciones = array_map(fn (int $id) => [
        'jugador_id' => $id,
        'tier_id' => (int) ($tierPorJugador[$id] ?? 0),
        'duracion_temporadas' => (int) ($duracionPorJugador[$id] ?? 0),
    ], $jugadorIds);

    try {
        $firmados = $contratos->firmarContratosInicialesEnLote($participacionId, $asignaciones, (int) $usuario['id']);
        $exito = 'Contrato inicial firmado para ' . count($firmados) . ' jugadores. Ninguno quedó a medias.';

        // Punto 25 de la auditoría: notificar al club nuevo qué plantilla se le ha asignado.
        $notificaciones->crear(
            $participacion['usuario_presidente_id'] ?? null,
            'PLANTILLA_INICIAL_ASIGNADA',
            'Se te ha asignado la plantilla inicial: ' . count($firmados) . ' jugadores con contrato.',
            "plantilla.php?participacion_id={$participacionId}"
        );
    } catch (DomainException $e) {
        $error = $e->getMessage() . ' No se ha firmado ningún contrato del lote.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'deshacer_asignacion') {
    try {
        $contratos->deshacerAsignacionInicial((int) ($_POST['contrato_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Asignación deshecha. El jugador vuelve a estar sin tier.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$pendientes = array_filter($jugadores->listarPlantilla($participacionId), fn ($j) => $j['tier_id'] === null);
$tiers = $contratos->listarTiers();

$paginaTitulo = 'Asignación masiva de contrato inicial';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_clubes';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Asignación masiva — <?= htmlspecialchars($club['nombre'] ?? $participacion['club_id']) ?></h1>
        </div>
        <a href="clubes.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Volver a clubes</a>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ($pendientes === []): ?>
        <div class="tbl-wrap" style="margin-top:1.5rem">
            <div class="empty-state">
                <i class="ph ph-check-circle"></i>
                <h3>Sin jugadores pendientes</h3>
                <p>Todos los jugadores de esta plantilla ya tienen tier y contrato.</p>
            </div>
        </div>
    <?php else: ?>
        <p class="caption" style="margin-top:1rem"><?= count($pendientes) ?> jugadores sin tier. Elige tier y duración para cada uno y firma todos a la vez: si alguno no cabe en el Salary Cap, la firma completa se cancela.</p>
        <form method="post" style="margin-top:1.5rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="firmar_lote">
            <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Jugador</th><th>Posición</th><th>Tier</th><th>Duración</th></tr></thead>
                    <tbody>
                    <?php foreach ($pendientes as $j): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="jugador_id[]" value="<?= (int) $j['id'] ?>">
                                <?= htmlspecialchars($j['nombre']) ?>
                            </td>
                            <td class="caption"><?= htmlspecialchars($j['posicion']) ?></td>
                            <td>
                                <select class="select" name="tier_id[<?= (int) $j['id'] ?>]" required style="height:32px;font-size:var(--fs-caption)">
                                    <option value="">-- elegir --</option>
                                    <?php foreach ($tiers as $tier): ?>
                                        <option value="<?= (int) $tier['id'] ?>"><?= htmlspecialchars($tier['nombre']) ?> (<?= number_format((float) $tier['salario_base'], 0, ',', '.') ?> €)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="select" name="duracion[<?= (int) $j['id'] ?>]" required style="height:32px;font-size:var(--fs-caption)">
                                    <option value="1">1 temporada (RFA al terminar)</option>
                                    <option value="2">2 temporadas (UFA al terminar)</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:1.5rem">Firmar los <?= count($pendientes) ?> contratos</button>
        </form>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
