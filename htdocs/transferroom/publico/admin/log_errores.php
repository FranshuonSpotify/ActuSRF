<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$errores = $db->query('SELECT * FROM errores_tecnicos ORDER BY id DESC LIMIT 100')->fetchAll();

$paginaTitulo = 'Log de errores técnicos';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Log de errores técnicos</h1>
            <p>Excepciones y errores PHP no controlados, distinto de la auditoría de acciones de negocio. Últimos 100.</p>
        </div>
    </div>

    <?php if ($errores === []): ?>
        <div class="alert alert-success"><i class="ph ph-check-circle"></i> Sin errores registrados.</div>
    <?php else: ?>
        <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Nivel</th><th>Mensaje</th><th>Archivo</th><th>URL</th></tr></thead>
                <tbody>
                <?php foreach ($errores as $e): ?>
                    <tr>
                        <td class="caption mono"><?= htmlspecialchars($e['creado_en']) ?></td>
                        <td><span class="badge badge-danger"><?= htmlspecialchars($e['nivel']) ?></span></td>
                        <td class="body-sm"><?= htmlspecialchars($e['mensaje']) ?></td>
                        <td class="caption mono"><?= htmlspecialchars(($e['archivo'] ?? '—') . ($e['linea'] !== null ? ':' . $e['linea'] : '')) ?></td>
                        <td class="caption"><?= htmlspecialchars($e['url'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
