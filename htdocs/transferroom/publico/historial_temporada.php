<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$temporadaId = (int) ($_GET['temporada_id'] ?? 0);
$temporada = $temporadas->buscarPorId($temporadaId);

if ($temporada === null) {
    http_response_code(404);
    exit('La temporada no existe.');
}

$snapshots = $snapshotsTemporadaRepositorio->listarPorTemporada($temporadaId);
$paginaTitulo = 'Snapshot · ' . $temporada['nombre'];
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'historial';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Snapshot · Temporada <?= (int) $temporada['numero'] ?></span>
            <h1 class="h1" style="margin-top:.5rem"><?= htmlspecialchars($temporada['nombre']) ?></h1>
        </div>
    </div>

    <?php if ($snapshots === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-camera"></i>
                <h3>Sin snapshot todavía</h3>
                <p>Se genera automáticamente al cerrar la temporada.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="grid-2">
            <?php foreach ($snapshots as $s): ?>
                <div class="card">
                    <h2 class="h3"><?= htmlspecialchars($s['club_nombre']) ?></h2>
                    <div style="display:flex;gap:1.5rem;margin-top:.75rem">
                        <div>
                            <span class="caption">Gasto salarial</span>
                            <div class="mono body-sm" style="margin-top:.15rem"><?= number_format((float) $s['gasto_salarial'], 0, ',', '.') ?> €</div>
                        </div>
                        <div>
                            <span class="caption">Dinero de traspasos</span>
                            <div class="mono body-sm" style="margin-top:.15rem"><?= number_format((float) $s['dinero_traspasos'], 0, ',', '.') ?> €</div>
                        </div>
                    </div>
                    <div style="margin-top:1.25rem;display:flex;flex-direction:column;gap:.4rem">
                        <?php foreach ($s['fichas'] as $f): ?>
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:var(--fs-body-sm);padding-block:.25rem;border-top:1px solid var(--line)">
                                <span style="flex:1"><?= htmlspecialchars($f['nombre']) ?> <span class="caption">(<?= htmlspecialchars($f['posicion']) ?>)</span></span>
                                <span class="chip"><?= htmlspecialchars($f['tier']) ?></span>
                                <span class="mono caption"><?= number_format((float) $f['salario_anual'], 0, ',', '.') ?> €</span>
                                <?php if ($f['es_franquicia']): ?><span class="badge badge-gold">F</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
