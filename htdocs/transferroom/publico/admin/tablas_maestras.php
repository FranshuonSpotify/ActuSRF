<?php

declare(strict_types=1);

/**
 * Editor de tablas maestras (05-transfer_room_docs/01_bloqueante_primer_mercado/10).
 * Tiers: el salario base se puede editar aquí, solo afecta a futuras firmas.
 * Afinidades: no tienen edición manual porque ya se auto-gestionan al
 * importar del JSON oficial (obtenerOCrearPorNombre) — aquí solo se listan.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_tier') {
    try {
        $contratos->actualizarSalarioBaseTier(
            (int) ($_POST['tier_id'] ?? 0),
            (float) ($_POST['salario_base'] ?? 0),
            (int) $usuario['id']
        );
        $exito = 'Salario base actualizado. Solo afecta a firmas nuevas a partir de ahora.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$tiers = $contratos->listarTiers();
$afinidades = $afinidadRepositorio->listarTodas();

$paginaTitulo = 'Tablas maestras';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_datos';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Tablas maestras</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <h2 class="h2">Tiers</h2>
    <p class="caption" style="margin-top:.4rem">Cambiar el salario base solo afecta a contratos nuevos: los ya firmados guardan su propio salario, nunca se reescriben.</p>
    <div class="tbl-wrap" style="margin-top:1rem">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead><tr><th>Tier</th><th class="num">Salario base</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($tiers as $t): ?>
                <tr>
                    <td><span class="chip <?= tier_chip_class($t['nombre']) ?>"><?= htmlspecialchars($t['nombre']) ?></span></td>
                    <td class="num mono"><?= number_format((float) $t['salario_base'], 0, ',', '.') ?> €</td>
                    <td>
                        <form method="post" style="display:flex;gap:.5rem;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <input type="hidden" name="accion" value="actualizar_tier">
                            <input type="hidden" name="tier_id" value="<?= (int) $t['id'] ?>">
                            <input type="number" name="salario_base" min="1" step="1" value="<?= (int) $t['salario_base'] ?>" class="input" style="height:32px;width:140px;font-size:var(--fs-caption)">
                            <button type="submit" class="btn btn-sm btn-secondary">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <h2 class="h2" style="margin-top:2.5rem">Afinidades</h2>
    <p class="caption" style="margin-top:.4rem">Se crean solas al importar jugadores del JSON oficial. Aquí solo se consultan.</p>
    <div class="tbl-wrap" style="margin-top:1rem">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead><tr><th>Nombre</th></tr></thead>
            <tbody>
            <?php foreach ($afinidades as $a): ?>
                <tr><td><?= htmlspecialchars($a['nombre']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
