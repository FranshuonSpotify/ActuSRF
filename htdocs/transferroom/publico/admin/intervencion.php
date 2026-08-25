<?php

declare(strict_types=1);

/**
 * Panel de intervención manual (05-transfer_room_docs/01_bloqueante_primer_mercado/10):
 * casos potencialmente atascados, con acciones directas — nunca tocar la BD
 * a mano. Reutiliza siempre motores/acciones ya existentes.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'corregir_jugador') {
    try {
        $jugadores->corregirEstadoInconsistente((int) ($_POST['jugador_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Estado del jugador corregido.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'confirmar_sin_igualar') {
    try {
        ModoPruebas::bloquearSiActivo();
        $mercado->confirmarSinIgualacion((int) ($_POST['jugador_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Ventana RFA resuelta a favor del ofertante.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$jugadoresInconsistentes = $jugadores->listarConEstadoInconsistente();
$ventanasVencidas = $mercado->listarVentanasVencidasSinConfirmar();
$pujasAntiguas = $mercado->listarPujasPendientesAntiguas(72);

$paginaTitulo = 'Panel de intervención';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Panel de intervención</h1>
            <p>Casos potencialmente atascados que necesitan una decisión manual. Ninguna acción de aquí toca la base de datos directamente — todas pasan por las mismas reglas que el resto de la aplicación.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <h2 class="h2">Jugadores con estado inconsistente</h2>
    <?php if ($jugadoresInconsistentes === []): ?>
        <div class="alert alert-success" style="margin-top:1rem"><i class="ph ph-check-circle"></i> Ninguno. Todo consistente.</div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Jugador</th><th>Estado</th><th>Participación</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($jugadoresInconsistentes as $j): ?>
                    <tr>
                        <td><?= htmlspecialchars($j['nombre']) ?></td>
                        <td><span class="badge badge-danger"><?= htmlspecialchars(etiqueta_legible($j['estado'])) ?></span></td>
                        <td class="mono"><?= $j['participacion_actual_id'] !== null ? (int) $j['participacion_actual_id'] : '—' ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="corregir_jugador">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">Corregir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:2.5rem">Ventanas RFA vencidas sin confirmar</h2>
    <?php if ($ventanasVencidas === []): ?>
        <div class="alert alert-success" style="margin-top:1rem"><i class="ph ph-check-circle"></i> Ninguna.</div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Jugador</th><th>Club ofertante</th><th class="num">Oferta</th><th>Venció</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($ventanasVencidas as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['jugador_nombre']) ?></td>
                        <td><?= htmlspecialchars($v['club_ofertante_nombre']) ?></td>
                        <td class="num mono"><?= number_format((float) $v['salario_ofertado'], 0, ',', '.') ?> €</td>
                        <td class="caption mono"><?= htmlspecialchars($v['fecha_limite_igualacion']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="confirmar_sin_igualar">
                                <input type="hidden" name="jugador_id" value="<?= (int) $v['jugador_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary">Confirmar sin igualar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:2.5rem">Pujas sin resolver desde hace más de 72h</h2>
    <?php if ($pujasAntiguas === []): ?>
        <div class="alert alert-success" style="margin-top:1rem"><i class="ph ph-check-circle"></i> Ninguna.</div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Jugador</th><th class="num">Salario ofertado</th><th>Desde</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pujasAntiguas as $o): ?>
                    <tr>
                        <td><?= htmlspecialchars($o['jugador_nombre']) ?></td>
                        <td class="num mono"><?= number_format((float) $o['salario_ofertado'], 0, ',', '.') ?> €</td>
                        <td class="caption mono"><?= htmlspecialchars($o['creado_en']) ?></td>
                        <td><a href="../mercado.php" class="btn btn-sm btn-secondary">Ir al mercado</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
