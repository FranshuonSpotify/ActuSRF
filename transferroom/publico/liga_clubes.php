<?php

declare(strict_types=1);

/** Liga / Clubes (Fase 2, hub v3): listado de todos los clubes de la liga con acceso a cada plantilla. */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$temporadaActiva = $temporadas->obtenerActiva();
$listaClubes = $temporadaActiva !== null ? $participaciones->listarPorTemporada((int) $temporadaActiva['id']) : [];

$paginaTitulo = 'Liga / Clubes';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'liga_clubes';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Liga</span>
            <h1 class="h1" style="margin-top:.5rem">Clubes</h1>
            <p class="caption"><?= $temporadaActiva !== null ? htmlspecialchars($temporadaActiva['nombre']) : 'Sin temporada activa' ?></p>
        </div>
    </div>

    <?php if ($temporadaActiva === null): ?>
        <div class="alert alert-danger"><i class="ph ph-warning"></i> No hay ninguna temporada activa todavía.</div>
    <?php elseif ($listaClubes === []): ?>
        <div class="empty-state">
            <i class="ph ph-shield"></i>
            <h3>Todavía no hay clubes inscritos</h3>
        </div>
    <?php else: ?>
        <div class="grid-3" style="margin-top:1.5rem">
            <?php foreach ($listaClubes as $p): ?>
                <a href="plantilla.php?participacion_id=<?= (int) $p['id'] ?>" class="card con-spotlight" style="display:block;color:inherit">
                    <div class="fila-club">
                        <?php if (!empty($p['escudo_url'])): ?>
                            <img referrerpolicy="no-referrer" class="escudo" src="<?= htmlspecialchars($p['escudo_url']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="escudo" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-shield"></i></span>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($p['club_nombre']) ?></strong>
                            <div class="caption" style="margin-top:.2rem"><?= htmlspecialchars($p['presidente_nombre'] ?? 'Sin presidente asignado') ?></div>
                        </div>
                    </div>
                    <div style="margin-top:.75rem;display:flex;gap:.4rem;flex-wrap:wrap">
                        <span class="badge <?= $p['estado'] === 'RETIRADA' ? 'badge-danger' : ($p['estado'] === 'PENDIENTE' ? 'badge-warning' : 'badge-success') ?>"><?= htmlspecialchars(etiqueta_legible($p['estado'])) ?></span>
                    </div>
                    <?php if (!empty($p['usuario_presidente_id']) && $preferencias->obtenerTexto((int) $p['usuario_presidente_id'], 'perfil_publico') === '1'): ?>
                        <?php $bio = $preferencias->obtenerTexto((int) $p['usuario_presidente_id'], 'perfil_bio'); ?>
                        <?php if ($bio !== ''): ?>
                            <p class="caption" style="margin-top:.6rem"><?= htmlspecialchars($bio) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
