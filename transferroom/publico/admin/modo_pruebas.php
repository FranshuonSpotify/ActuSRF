<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$temporadaActiva = $temporadas->obtenerActiva();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'activar') {
    $participacionElegida = $participaciones->buscarPorId((int) ($_POST['participacion_id'] ?? 0));
    if ($participacionElegida === null) {
        $error = 'La participación no existe.';
    } else {
        ModoPruebas::activar((int) $participacionElegida['id']);
        $auditoria->registrar((int) $usuario['id'], 'ACTIVAR_MODO_PRUEBAS', 'participaciones_club', (string) $participacionElegida['id'], null, null);
        header('Location: ../dashboard.php');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'desactivar') {
    $participacionPrevia = ModoPruebas::participacionActiva();
    ModoPruebas::desactivar();
    $auditoria->registrar((int) $usuario['id'], 'DESACTIVAR_MODO_PRUEBAS', 'participaciones_club', (string) $participacionPrevia, null, null);
    header('Location: modo_pruebas.php');
    exit;
}

$participacionesTemporada = $temporadaActiva !== null ? $participaciones->listarPorTemporada((int) $temporadaActiva['id']) : [];
$activa = ModoPruebas::participacionActiva();

$paginaTitulo = 'Modo pruebas';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_pruebas';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec" style="max-width:640px">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Modo pruebas</h1>
            <p class="body-sm" style="margin-top:.4rem">Actúa como un club concreto para probar el flujo real. Nunca permite acciones irreversibles (finalizar contratos, retirar clubes, aceptar/rechazar ofertas, resolver mercado) y se desactiva sola tras 30 minutos sin uso.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($activa !== null): ?>
        <?php $clubActivo = $clubes->buscarPorId($participaciones->buscarPorId($activa)['club_id']); ?>
        <div class="card" style="border-color:rgba(255,177,104,.4)">
            <div class="alert alert-warning" style="margin-bottom:0">
                <i class="ph ph-flask"></i>
                <span>Estás actuando como <strong><?= htmlspecialchars($clubActivo['nombre'] ?? '—') ?></strong>.</span>
            </div>
            <form method="post" style="margin-top:1.25rem">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="desactivar">
                <button type="submit" class="btn btn-secondary">Salir del modo pruebas</button>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="activar">
                <div class="field">
                    <label class="field-label" for="mp-club">Club</label>
                    <select class="select" id="mp-club" name="participacion_id" required>
                        <option value="">-- selecciona --</option>
                        <?php foreach ($participacionesTemporada as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['club_nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Activar modo pruebas</button>
            </form>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
