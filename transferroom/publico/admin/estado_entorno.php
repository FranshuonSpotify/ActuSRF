<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$ultimaSincronizacion = $db->query(
    "SELECT creado_en FROM auditoria WHERE accion = 'SINCRONIZAR_CATALOGO' ORDER BY id DESC LIMIT 1"
)->fetchColumn();

$tamanoBaseDatos = $db->query(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
     FROM information_schema.tables WHERE table_schema = DATABASE()"
)->fetchColumn();

$versionMariaDb = $db->query('SELECT VERSION()')->fetchColumn();
$totalErroresRecientes = (int) $db->query(
    "SELECT COUNT(*) FROM errores_tecnicos WHERE creado_en > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetchColumn();

$paginaTitulo = 'Estado del entorno';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Estado del entorno</h1>
        </div>
    </div>

    <div class="grid-3">
        <div class="card">
            <span class="caption">Última sincronización del JSON</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= htmlspecialchars($ultimaSincronizacion ?: 'Nunca') ?></div>
        </div>
        <div class="card">
            <span class="caption">Tamaño de la base de datos</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= htmlspecialchars((string) $tamanoBaseDatos) ?> MB</div>
        </div>
        <div class="card">
            <span class="caption">Versión de MariaDB</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= htmlspecialchars((string) $versionMariaDb) ?></div>
        </div>
        <div class="card">
            <span class="caption">Versión de PHP</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= htmlspecialchars(PHP_VERSION) ?></div>
        </div>
        <div class="card">
            <span class="caption">Errores técnicos (24h)</span>
            <div class="h4 mono" style="margin-top:.35rem"><?= $totalErroresRecientes ?></div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
