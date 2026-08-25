<?php

declare(strict_types=1);

/**
 * Datos y Sistema (Fase 2, hub v3, admin): consolida las páginas técnicas ya
 * construidas en la Fase 1 (backup, log de errores, estado del entorno,
 * intervención) en un solo destino de hub, y añade Moderación (reportes de
 * "Reportar un problema", hasta ahora solo visibles buceando en Auditoría) y
 * Rendimiento/Entorno (ampliación de estado_entorno.php). Sin especificación
 * previa para lo nuevo — diseño propio. No duplica lógica de esas páginas:
 * solo enlaza o reutiliza sus mismas consultas.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

// Moderación: reportes de problema son entradas de auditoría, no una tabla propia (YAGNI, igual que reportar_problema.php).
$reportes = array_values(array_filter(
    $auditoria->listarRecientes(300),
    fn (array $e): bool => $e['accion'] === 'REPORTE_PROBLEMA'
));

// Rendimiento: mismas consultas que estado_entorno.php, resumidas aquí para no repetir la página entera.
$tamanoBaseDatos = $db->query(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
     FROM information_schema.tables WHERE table_schema = DATABASE()"
)->fetchColumn();
$totalFilasAuditoria = (int) $db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
$totalNotificaciones = (int) $db->query('SELECT COUNT(*) FROM notificaciones')->fetchColumn();

$inicioMedicion = microtime(true);
$db->query('SELECT COUNT(*) FROM jugadores')->fetchColumn();
$tiempoConsultaMs = round((microtime(true) - $inicioMedicion) * 1000, 2);

$paginaTitulo = 'Datos y Sistema';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Datos y Sistema</h1>
        </div>
    </div>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="resumen">Resumen</button>
        <button data-tab-target="moderacion">Moderación</button>
        <button data-tab-target="rendimiento">Rendimiento</button>
        <button data-tab-target="entorno">Entorno</button>
    </div>

    <div data-tab-panel="resumen">
        <div class="grid-3">
            <a href="backup.php" class="card" style="display:block;color:inherit">
                <i class="ph ph-hard-drives" style="font-size:1.5rem"></i>
                <h3 class="h4" style="margin-top:.6rem">Backup</h3>
                <p class="caption" style="margin-top:.3rem">Descargar una copia completa de la base de datos, con histórico.</p>
            </a>
            <a href="log_errores.php" class="card" style="display:block;color:inherit">
                <i class="ph ph-bug" style="font-size:1.5rem"></i>
                <h3 class="h4" style="margin-top:.6rem">Log de errores técnicos</h3>
                <p class="caption" style="margin-top:.3rem">Últimos 100 errores capturados automáticamente por la aplicación.</p>
            </a>
            <a href="estado_entorno.php" class="card" style="display:block;color:inherit">
                <i class="ph ph-heartbeat" style="font-size:1.5rem"></i>
                <h3 class="h4" style="margin-top:.6rem">Estado del entorno</h3>
                <p class="caption" style="margin-top:.3rem">Versión de MariaDB, tamaño de la base de datos, última sincronización.</p>
            </a>
            <a href="intervencion.php" class="card" style="display:block;color:inherit">
                <i class="ph ph-wrench" style="font-size:1.5rem"></i>
                <h3 class="h4" style="margin-top:.6rem">Intervención manual</h3>
                <p class="caption" style="margin-top:.3rem">Casos potencialmente atascados (ventanas RFA vencidas, ofertas huérfanas) con acciones directas.</p>
            </a>
            <a href="auditoria.php" class="card" style="display:block;color:inherit">
                <i class="ph ph-list-magnifying-glass" style="font-size:1.5rem"></i>
                <h3 class="h4" style="margin-top:.6rem">Auditoría</h3>
                <p class="caption" style="margin-top:.3rem">Registro técnico completo de quién hizo qué, cuándo y desde dónde.</p>
            </a>
            <a href="tablas_maestras.php" class="card" style="display:block;color:inherit">
                <i class="ph ph-table" style="font-size:1.5rem"></i>
                <h3 class="h4" style="margin-top:.6rem">Tablas maestras</h3>
                <p class="caption" style="margin-top:.3rem">Tiers y afinidades: catálogos ampliables, nunca ENUM.</p>
            </a>
        </div>
    </div>

    <div data-tab-panel="moderacion" hidden>
        <p class="caption" style="margin-bottom:1rem">Reportes enviados por presidentes desde "Reportar un problema", con usuario, página de origen y hora.</p>
        <?php if ($reportes === []): ?>
            <div class="empty-state"><i class="ph ph-check-circle"></i><h3>Sin reportes pendientes</h3></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <?php foreach ($reportes as $r): ?>
                    <?php $detalle = json_decode((string) $r['valor_despues'], true) ?? []; ?>
                    <div class="card" style="padding:.9rem 1.1rem">
                        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                            <strong class="body-sm"><?= htmlspecialchars($r['usuario_nombre'] ?? 'Usuario eliminado') ?></strong>
                            <span class="caption mono"><?= htmlspecialchars($r['creado_en']) ?></span>
                        </div>
                        <p class="body-sm" style="margin-top:.4rem"><?= htmlspecialchars($detalle['mensaje'] ?? '—') ?></p>
                        <span class="caption">Origen: <span class="mono"><?= htmlspecialchars($detalle['origen'] ?? 'desconocido') ?></span></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="rendimiento" hidden>
        <div class="grid-3">
            <div class="card"><span class="caption">Tamaño de la base de datos</span><p class="h3 mono" style="margin-top:.3rem"><?= htmlspecialchars((string) $tamanoBaseDatos) ?> MB</p></div>
            <div class="card"><span class="caption">Filas en auditoría</span><p class="h3 mono" style="margin-top:.3rem"><?= number_format($totalFilasAuditoria, 0, ',', '.') ?></p></div>
            <div class="card"><span class="caption">Notificaciones totales</span><p class="h3 mono" style="margin-top:.3rem"><?= number_format($totalNotificaciones, 0, ',', '.') ?></p></div>
        </div>
        <div class="card" style="margin-top:1rem;max-width:420px">
            <span class="caption">Consulta de referencia (SELECT COUNT sobre jugadores)</span>
            <p class="h3 mono" style="margin-top:.3rem"><?= $tiempoConsultaMs ?> ms</p>
            <p class="caption" style="margin-top:.3rem">Es una medida orientativa de un solo momento, no un histórico. Si sube de forma sostenida, revisa índices antes de crecer más el catálogo.</p>
        </div>
    </div>

    <div data-tab-panel="entorno" hidden>
        <div class="card" style="max-width:640px">
            <h3 class="h4" style="margin-bottom:.6rem">No hay un entorno de staging separado</h3>
            <p class="caption">
                Es un proyecto de una sola persona (CLAUDE.md §0), sin CI/CD ni servidor intermedio: el desarrollo ocurre en XAMPP local
                y el despliegue va directo a producción (hosting compartido, IONOS). Cualquier cambio se prueba primero en local
                (<span class="mono">php -S 127.0.0.1:8090 -t publico</span>) con datos reales o desechables, nunca "en staging",
                porque staging no existe como entorno propio. Esta pestaña documenta esa realidad en vez de simular una fase de
                despliegue que no hay.
            </p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
