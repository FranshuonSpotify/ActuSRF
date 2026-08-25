<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$lista = $auditoria->listarRecientes(200);
$paginaTitulo = 'Auditoría';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Auditoría</h1>
            <p>Últimas 200 acciones.</p>
        </div>
    </div>

    <?php if ($lista === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-list-magnifying-glass"></i>
                <h3>Sin actividad todavía</h3>
                <p>Aquí aparecerá cada acción de negocio que se registre en el sistema.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>IP</th><th>Acción</th><th>Entidad</th><th>Resultado</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $a): ?>
                <tr>
                    <td class="mono caption"><?= htmlspecialchars($a['creado_en']) ?></td>
                    <td><?= htmlspecialchars($a['usuario_nombre'] ?? 'Sistema') ?></td>
                    <td class="mono caption"><?= htmlspecialchars($a['ip'] ?? '—') ?></td>
                    <td><span class="chip"><?= htmlspecialchars($a['accion']) ?></span></td>
                    <td class="caption"><?= htmlspecialchars($a['entidad']) ?><?= $a['entidad_id'] !== null ? ' #' . htmlspecialchars($a['entidad_id']) : '' ?></td>
                    <td><span class="badge <?= $a['resultado'] === 'OK' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($a['resultado']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
