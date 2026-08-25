<?php

declare(strict_types=1);

/**
 * Cambio de tier/versión de un jugador (petición de Franshu: algunos
 * jugadores "cambian de versión" — mismo jugador, tier distinto — y no es
 * viable modelar cada versión posible de cada uno, así que es un selector
 * directo). Solo administrador. Si el jugador tiene un contrato activo, su
 * salario se recalcula al del tier nuevo en el mismo momento (confirmado con
 * Franshu: el cambio también afecta al contrato ya firmado, no solo a
 * fichajes futuros) — con la misma validación de Salary Cap que cualquier
 * otro cambio de salario.
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
            $contratos->cambiarTierJugador((int) $_POST['jugador_id'], (int) $_POST['tier_id'], (int) $usuario['id']);
            $exito = 'Tier actualizado.';
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$todosLosTiers = $tierRepositorio->listarTodas();
$jugadoresConTier = $jugadores->listarTodosParaScouting();

$paginaTitulo = 'Cambiar Tier / Versión';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_cambiar_tier';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Cambiar Tier / Versión</h1>
            <p class="caption" style="margin-top:.4rem">Algunos jugadores cambian de tier según la versión que fichan. Elige el tier nuevo: si el jugador tiene contrato activo, su salario se recalcula al del tier nuevo en el momento, respetando el Salary Cap del club.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="field" style="max-width:360px;margin-top:1rem">
        <label class="field-label" for="filtro-tier-nombre">Buscar jugador</label>
        <input class="input" type="search" id="filtro-tier-nombre" placeholder="Nombre del jugador" oninput="TRcambiarTier.filtrar()">
    </div>
    <p class="caption" id="filtro-tier-resumen" style="margin-top:.5rem"></p>

    <?php if ($jugadoresConTier === []): ?>
        <div class="empty-state" style="margin-top:1rem">
            <i class="ph ph-user-circle-minus"></i>
            <h3>Todavía no hay ningún jugador con tier asignado</h3>
            <p class="caption">Asigna tiers primero desde <a href="asignacion_masiva.php">Asignación masiva</a> o el mercado — este panel cambia el tier de jugadores que ya lo tienen.</p>
        </div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:.5rem">
            <div class="tbl-scroll">
            <table class="tbl" id="tabla-cambiar-tier">
                <thead><tr><th>Jugador</th><th>Tier actual</th><th>Club</th><th>Tier nuevo</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($jugadoresConTier as $j): ?>
                    <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>">
                        <td>
                            <div class="fila-jugador">
                                <?php if (!empty($j['foto_url'])): ?>
                                    <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($j['nombre']) ?> <span class="caption">(<?= htmlspecialchars($j['posicion']) ?>)</span></span>
                            </div>
                        </td>
                        <td><span class="chip <?= tier_chip_class($j['tier_nombre'] ?? '') ?>"><?= htmlspecialchars($j['tier_nombre'] ?? '—') ?></span></td>
                        <td class="caption">
                            <?= $j['estado'] === 'ACTIVO' ? htmlspecialchars($j['club_nombre'] ?? '—') : '— agente libre —' ?>
                            <?php if ($j['estado'] === 'ACTIVO'): ?>
                                <br><span class="caption" style="color:var(--warning)">Tiene contrato activo: el salario se recalcula al guardar.</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;min-width:260px">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <select class="select" name="tier_id" style="height:32px;font-size:var(--fs-caption)">
                                    <?php foreach ($todosLosTiers as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $j['tier_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['nombre']) ?> (<?= number_format((float) $t['salario_base'], 0, ',', '.') ?> €)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Cambiar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
var TRcambiarTier = {
    filtrar: function () {
        var nombre = document.getElementById('filtro-tier-nombre').value.trim().toLowerCase();
        var filas = document.querySelectorAll('#tabla-cambiar-tier tbody tr');
        var visibles = 0;
        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1;
            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });
        document.getElementById('filtro-tier-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    }
};
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('tabla-cambiar-tier')) TRcambiarTier.filtrar();
});
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
