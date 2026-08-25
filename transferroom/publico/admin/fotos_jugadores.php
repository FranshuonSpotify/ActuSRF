<?php

declare(strict_types=1);

/**
 * Panel admin de fotos de jugador. Dos prioridades explícitas (a petición de
 * Franshu): los jugadores que no tenían foto en los datos oficiales al
 * importarse, y los jugadores externos que va creando la gente (aunque ya
 * tengan una foto puesta, conviene revisarla porque la puso un presidente,
 * no la liga). Mismo patrón de formulario + recarga que wiki_jugadores.php.
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
                case 'actualizar_foto':
                    $jugadores->actualizarFoto((int) $_POST['jugador_id'], trim((string) ($_POST['foto_url'] ?? '')) ?: null, (int) $usuario['id']);
                    $exito = 'Foto actualizada.';
                    break;
                case 'quitar_foto':
                    $jugadores->actualizarFoto((int) $_POST['jugador_id'], null, (int) $usuario['id']);
                    $exito = 'Foto quitada.';
                    break;
                default:
                    $error = 'Acción no reconocida.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$todos = $jugadores->listarTodosParaFotos();
$sinFotoOficial = array_values(array_filter($todos, static fn (array $j): bool => $j['foto_url'] === null && $j['origen'] === 'JSON_OFICIAL'));
$externos = array_values(array_filter($todos, static fn (array $j): bool => $j['origen'] === 'EXTERNO'));
$prioritariosIds = array_unique(array_merge(array_column($sinFotoOficial, 'id'), array_column($externos, 'id')));
$resto = array_values(array_filter($todos, static fn (array $j): bool => !in_array($j['id'], $prioritariosIds, true)));

$paginaTitulo = 'Fotos de Jugadores';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_fotos';
include __DIR__ . '/../../partials/nav.php';

$renderFilaFoto = static function (array $j): void {
    ?>
    <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>">
        <td>
            <?php if ($j['foto_url'] !== null): ?>
                <img referrerpolicy="no-referrer" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" class="jugador-foto" loading="lazy" onerror="this.style.visibility='hidden'">
            <?php else: ?>
                <span class="jugador-foto" style="display:grid;place-items:center;background:var(--surface-2);color:var(--ink-4)"><i class="ph ph-image"></i></span>
            <?php endif; ?>
        </td>
        <td><strong><?= htmlspecialchars($j['nombre']) ?></strong></td>
        <td class="caption"><?= htmlspecialchars($j['posicion']) ?></td>
        <td class="caption"><?= htmlspecialchars($j['club_nombre'] ?? '— agente libre —') ?></td>
        <td>
            <span class="badge <?= $j['origen'] === 'EXTERNO' ? 'badge-accent' : '' ?>">
                <?= $j['origen'] === 'EXTERNO' ? 'Creado por usuario' : 'JSON oficial' ?>
            </span>
        </td>
        <td>
            <form method="post" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;min-width:280px">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="actualizar_foto">
                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                <input class="input" type="url" name="foto_url" value="<?= htmlspecialchars($j['foto_url'] ?? '') ?>" placeholder="https://…" style="height:32px;font-size:var(--fs-caption);flex:1;min-width:180px">
                <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
            </form>
        </td>
        <td>
            <?php if ($j['foto_url'] !== null): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="accion" value="quitar_foto">
                    <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-ghost">Quitar</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
};
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Fotos de Jugadores</h1>
            <p class="caption" style="margin-top:.4rem">Edita la foto de cualquier jugador. Arriba, en prioritario: quienes no tenían foto en los datos oficiales al importarse, y los jugadores externos que va creando la gente.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="grid-3" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <div class="card"><span class="caption">Sin foto (datos oficiales)</span><div class="h2 mono" style="margin-top:.3rem"><?= count($sinFotoOficial) ?></div></div>
        <div class="card"><span class="caption">Creados por usuarios</span><div class="h2 mono" style="margin-top:.3rem"><?= count($externos) ?></div></div>
        <div class="card"><span class="caption">Total de jugadores</span><div class="h2 mono" style="margin-top:.3rem"><?= count($todos) ?></div></div>
    </div>

    <h2 class="h2" style="margin-top:2rem">Prioritarios (<?= count($sinFotoOficial) + count($externos) ?>)</h2>
    <p class="caption" style="margin-top:.4rem">Sin foto de origen, o subidos por un presidente — revísalos primero.</p>

    <?php if ($sinFotoOficial === [] && $externos === []): ?>
        <div class="empty-state" style="margin-top:1rem">
            <i class="ph ph-check-circle"></i>
            <h3>Nada prioritario pendiente</h3>
        </div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Foto</th><th>Jugador</th><th>Pos.</th><th>Club</th><th>Origen</th><th>URL de la foto</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($sinFotoOficial as $j): $renderFilaFoto($j); endforeach; ?>
                <?php foreach ($externos as $j): if (!in_array($j['id'], array_column($sinFotoOficial, 'id'), true)) $renderFilaFoto($j); endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:2rem">Resto de la plantilla (<?= count($resto) ?>)</h2>
    <div class="field" style="max-width:360px;margin-top:.75rem">
        <label class="field-label" for="filtro-fotos-nombre">Buscar jugador</label>
        <input class="input" type="search" id="filtro-fotos-nombre" placeholder="Nombre del jugador" oninput="TRfotos.filtrar()">
    </div>
    <p class="caption" id="filtro-fotos-resumen" style="margin-top:.5rem"></p>
    <div class="tbl-wrap" style="margin-top:.5rem">
        <div class="tbl-scroll">
        <table class="tbl" id="tabla-fotos-resto">
            <thead><tr><th>Foto</th><th>Jugador</th><th>Pos.</th><th>Club</th><th>Origen</th><th>URL de la foto</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($resto as $j): $renderFilaFoto($j); endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</main>

<script>
var TRfotos = {
    filtrar: function () {
        var nombre = document.getElementById('filtro-fotos-nombre').value.trim().toLowerCase();
        var filas = document.querySelectorAll('#tabla-fotos-resto tbody tr');
        var visibles = 0;
        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1;
            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });
        document.getElementById('filtro-fotos-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    }
};
document.addEventListener('DOMContentLoaded', TRfotos.filtrar);
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
