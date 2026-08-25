<?php

declare(strict_types=1);

/**
 * Configuración y Reglas (Fase 2, admin, ampliación): añade Versionado
 * (historial real de cambios, vía auditoría — sin tabla nueva), Plantillas
 * guardadas (fotografías nombradas de los valores editables) y Roadmap
 * (documentación honesta del estado de fases, no una función dinámica).
 * Sin especificación previa para lo nuevo — diseño propio.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            switch ($_POST['accion'] ?? '') {
                case 'actualizar':
                    $configuracion->actualizar((string) ($_POST['clave'] ?? ''), (string) ($_POST['valor'] ?? ''), (int) $usuario['id']);
                    $exito = 'Parámetro actualizado.';
                    break;
                case 'guardar_plantilla':
                    $configuracion->guardarPlantilla((string) ($_POST['nombre'] ?? ''), (int) $usuario['id']);
                    $exito = 'Plantilla guardada.';
                    break;
                case 'aplicar_plantilla':
                    $configuracion->aplicarPlantilla((int) $_POST['plantilla_id'], (int) $usuario['id']);
                    $exito = 'Plantilla aplicada.';
                    break;
                case 'eliminar_plantilla':
                    $configuracion->eliminarPlantilla((int) $_POST['plantilla_id'], (int) $usuario['id']);
                    $exito = 'Plantilla eliminada.';
                    break;
                default:
                    $error = 'Acción no reconocida.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$lista = $configuracion->listarTodas();
$plantillas = $configuracion->listarPlantillas();

$historialCambios = array_values(array_filter(
    $auditoria->listarRecientes(300),
    fn (array $e): bool => in_array($e['accion'], ['ACTUALIZAR_CONFIGURACION', 'APLICAR_PLANTILLA_CONFIGURACION', 'GUARDAR_PLANTILLA_CONFIGURACION'], true)
));

$paginaTitulo = 'Configuración';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_config';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Configuración y Reglas</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert" style="margin-bottom:1rem"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success" style="margin-bottom:1rem"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="reglas">Reglas</button>
        <button data-tab-target="versionado">Versionado</button>
        <button data-tab-target="plantillas">Plantillas guardadas</button>
        <button data-tab-target="roadmap">Roadmap</button>
    </div>

    <div data-tab-panel="reglas">
        <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Clave</th><th>Descripción</th><th>Valor</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $c): ?>
                    <tr>
                        <td class="mono"><?= htmlspecialchars($c['clave']) ?></td>
                        <td class="caption"><?= htmlspecialchars($c['descripcion'] ?? '') ?></td>
                        <td>
                            <?php if ($c['editable']): ?>
                                <form method="post" style="display:flex;gap:.5rem">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="actualizar">
                                    <input type="hidden" name="clave" value="<?= htmlspecialchars($c['clave']) ?>">
                                    <?php if (($c['tipo'] ?? '') === 'bool'): ?>
                                        <select name="valor" class="input" style="height:32px;width:160px;font-size:var(--fs-caption)">
                                            <option value="1" <?= $c['valor'] === '1' ? 'selected' : '' ?>>Sí</option>
                                            <option value="0" <?= $c['valor'] === '0' ? 'selected' : '' ?>>No</option>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="valor" value="<?= htmlspecialchars($c['valor']) ?>" class="input" style="height:32px;width:160px;font-size:var(--fs-caption)">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm btn-secondary">Guardar</button>
                                </form>
                            <?php else: ?>
                                <span class="mono"><?= htmlspecialchars($c['valor']) ?></span> <span class="caption">(no editable)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div data-tab-panel="versionado" hidden>
        <p class="caption" style="margin-bottom:1rem">Historial real de cambios de configuración y de aplicación/creación de plantillas, tomado de Auditoría.</p>
        <?php if ($historialCambios === []): ?>
            <div class="empty-state"><i class="ph ph-clock-counter-clockwise"></i><h3>Sin cambios registrados todavía</h3></div>
        <?php else: ?>
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Cuándo</th><th>Quién</th><th>Acción</th><th>Detalle</th></tr></thead>
                    <tbody>
                    <?php foreach ($historialCambios as $h): ?>
                        <?php
                            $antes = json_decode((string) ($h['valor_antes'] ?? 'null'), true);
                            $despues = json_decode((string) ($h['valor_despues'] ?? 'null'), true);
                        ?>
                        <tr>
                            <td class="caption mono"><?= htmlspecialchars($h['creado_en']) ?></td>
                            <td><?= htmlspecialchars($h['usuario_nombre'] ?? 'Sistema') ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($h['accion']) ?></span></td>
                            <td class="caption">
                                <?= htmlspecialchars($h['entidad_id'] ?? '') ?>
                                <?php if ($h['accion'] === 'ACTUALIZAR_CONFIGURACION' && is_array($antes) && is_array($despues)): ?>
                                    : <span class="mono"><?= htmlspecialchars((string) ($antes['valor'] ?? '—')) ?></span> → <span class="mono"><?= htmlspecialchars((string) ($despues['valor'] ?? '—')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="plantillas" hidden>
        <form method="post" style="display:flex;gap:.75rem;align-items:flex-end;margin-bottom:1.5rem;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="guardar_plantilla">
            <div class="field" style="margin-bottom:0;min-width:260px">
                <label class="field-label" for="plantilla-nombre">Guardar la configuración actual como plantilla</label>
                <input class="input" type="text" id="plantilla-nombre" name="nombre" maxlength="120" required placeholder="Ej: Reglas de pretemporada">
            </div>
            <button type="submit" class="btn btn-primary">Guardar plantilla</button>
        </form>

        <?php if ($plantillas === []): ?>
            <div class="empty-state"><i class="ph ph-stack"></i><h3>Sin plantillas guardadas</h3></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <?php foreach ($plantillas as $p): ?>
                    <?php $valores = json_decode((string) $p['valores'], true) ?? []; ?>
                    <div class="card" style="padding:.9rem 1.1rem">
                        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center">
                            <div>
                                <strong class="body-sm"><?= htmlspecialchars($p['nombre']) ?></strong>
                                <div class="caption" style="margin-top:.2rem">Guardada por <?= htmlspecialchars($p['creado_por_nombre'] ?? '—') ?> el <?= htmlspecialchars($p['creado_en']) ?> · <?= count($valores) ?> parámetros</div>
                            </div>
                            <div style="display:flex;gap:.5rem">
                                <form method="post" style="margin:0" onsubmit="return confirm('¿Aplicar esta plantilla? Sobrescribirá los valores actuales de configuración.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="aplicar_plantilla">
                                    <input type="hidden" name="plantilla_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">Aplicar</button>
                                </form>
                                <form method="post" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="eliminar_plantilla">
                                    <input type="hidden" name="plantilla_id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" aria-label="Eliminar plantilla"><i class="ph ph-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="roadmap" hidden>
        <p class="caption" style="margin-bottom:1rem">Estado real de las fases del proyecto, tal y como está documentado en CLAUDE.md — no una lista de deseos.</p>
        <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Módulo</th><th>Estado</th></tr></thead>
                <tbody>
                    <tr><td>Núcleo (auth, clubes, jugadores, contratos, Salary Cap)</td><td><span class="badge badge-success">Construido y verificado</span></td></tr>
                    <tr><td>Mercado (agentes libres, traspasos, RFA/UFA, franquicia)</td><td><span class="badge badge-success">Construido y verificado</span></td></tr>
                    <tr><td>Fase 1 — bloqueantes del primer mercado</td><td><span class="badge badge-success">Construido y verificado</span></td></tr>
                    <tr><td>Fase 2 — hub de navegación v3 (14 iconos presidente + 8 admin)</td><td><span class="badge badge-warning">En construcción</span></td></tr>
                    <tr><td>Fin de temporada con snapshot histórico completo</td><td><span class="badge badge-info">Parcial — expiración manual sin snapshot</span></td></tr>
                    <tr><td>Estadísticas deportivas (partidos, goles, clasificación real)</td><td><span class="badge badge-danger">Sin empezar</span></td></tr>
                    <tr><td>Multi-liga, API pública, cesiones, cláusulas de rescisión</td><td><span class="badge badge-danger">Preparado para el futuro, sin empezar</span></td></tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
