<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validar($_POST['csrf_token'] ?? null) && ($_POST['accion'] ?? '') === 'descargar') {
    $generado = BackupGenerator::generarYGuardar($db);
    $auditoria->registrar((int) $usuario['id'], 'DESCARGAR_BACKUP', 'configuracion', null, null, [
        'tamano_bytes' => $generado['tamano'],
        'nombre_archivo' => $generado['nombre'],
    ]);
    $db->prepare('INSERT INTO backups_generados (nombre_archivo, tamano_bytes, generado_por) VALUES (?, ?, ?)')
        ->execute([$generado['nombre'], $generado['tamano'], (int) $usuario['id']]);

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $generado['nombre'] . '"');
    header('Content-Length: ' . $generado['tamano']);
    echo $generado['sql'];
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'descargar_existente') {
    $backupId = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM backups_generados WHERE id = ?');
    $stmt->execute([$backupId]);
    $registro = $stmt->fetch();
    $sql = $registro !== false ? BackupGenerator::leerDeDisco($registro['nombre_archivo']) : null;

    if ($sql === null) {
        http_response_code(404);
        exit('Ese backup ya no está disponible en disco.');
    }

    $auditoria->registrar((int) $usuario['id'], 'DESCARGAR_BACKUP', 'configuracion', (string) $backupId, null, [
        'nombre_archivo' => $registro['nombre_archivo'],
        'reutilizado' => true,
    ]);
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $registro['nombre_archivo'] . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

$historicoBackups = $db->query(
    'SELECT b.*, u.nombre AS generado_por_nombre FROM backups_generados b
     LEFT JOIN usuarios u ON u.id = b.generado_por
     ORDER BY b.id DESC LIMIT 30'
)->fetchAll();

$paginaTitulo = 'Copia de seguridad';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec" style="max-width:720px">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Copia de seguridad</h1>
            <p>Descarga un volcado completo de la base de datos en formato SQL, listo para restaurar con cualquier cliente MySQL/MariaDB.</p>
        </div>
    </div>

    <div class="card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="descargar">
            <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-download-simple"></i> Generar y descargar copia de seguridad ahora</button>
        </form>
    </div>

    <h2 class="h2" style="margin-top:2.5rem">Backups anteriores</h2>
    <?php if ($historicoBackups === []): ?>
        <div class="alert alert-success" style="margin-top:1rem"><i class="ph ph-info"></i> Todavía no se ha generado ningún backup.</div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Fichero</th><th class="num">Tamaño</th><th>Generado por</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($historicoBackups as $b): ?>
                    <tr>
                        <td class="mono caption"><?= htmlspecialchars($b['creado_en']) ?></td>
                        <td class="mono"><?= htmlspecialchars($b['nombre_archivo']) ?></td>
                        <td class="num mono"><?= number_format((int) $b['tamano_bytes'] / 1024, 1, ',', '.') ?> KB</td>
                        <td><?= htmlspecialchars($b['generado_por_nombre'] ?? '—') ?></td>
                        <td><a href="?accion=descargar_existente&id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-secondary"><i class="ph ph-download-simple"></i> Descargar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
