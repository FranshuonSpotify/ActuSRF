<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$clubId = (string) ($_GET['club_id'] ?? '');
$club = $clubes->buscarPorId($clubId);

if ($club === null) {
    http_response_code(404);
    exit('El club no existe.');
}

$contratos_ = $contratos->historialClub($clubId);
$traspasos_ = $traspasos->historialTraspasosClub($clubId);

$eventos = [];
foreach ($contratos_ as $c) {
    $eventos[] = [
        'fecha' => $c['creado_en'],
        'texto' => sprintf(
            'Temporada %d: contrato con %s (%s) — tier %s, %s €/temporada. Estado: %s.',
            $c['temporada_numero'],
            $c['jugador_nombre'],
            $c['posicion'],
            $c['tier_nombre'],
            number_format((float) $c['salario_anual'], 0, ',', '.'),
            $c['estado']
        ),
    ];
}
foreach ($traspasos_ as $t) {
    $sentido = $t['club_comprador_nombre'] === $club['nombre'] ? 'Compra' : 'Venta';
    $eventos[] = [
        'fecha' => $t['resuelto_en'],
        'texto' => sprintf(
            '%s: %s — %s → %s por %s €.',
            $sentido,
            $t['jugador_nombre'],
            $t['club_vendedor_nombre'],
            $t['club_comprador_nombre'],
            number_format((float) $t['importe_traspaso'], 0, ',', '.')
        ),
    ];
}
usort($eventos, fn ($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));
$paginaTitulo = 'Historial · ' . $club['nombre'];
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'historial';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div class="fila-club" style="gap:1.25rem">
            <?php if (!empty($club['escudo_url'])): ?>
                <img referrerpolicy="no-referrer" class="escudo escudo-lg" src="<?= htmlspecialchars($club['escudo_url']) ?>" alt="" loading="lazy">
            <?php endif; ?>
            <div>
                <span class="overline">Historial</span>
                <h1 class="h1" style="margin-top:.5rem"><?= htmlspecialchars($club['nombre']) ?></h1>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:2rem">
        <?php foreach ($temporadas->listarTodas() as $t): ?>
            <a href="historial_temporada.php?temporada_id=<?= (int) $t['id'] ?>" class="chip tt" data-tt="Ver snapshot"><i class="ph ph-camera"></i> T<?= (int) $t['numero'] ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($eventos === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-clock-counter-clockwise"></i>
                <h3>Sin historial todavía</h3>
                <p>Este club todavía no tiene contratos ni traspasos registrados.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="padding:0">
            <?php foreach ($eventos as $i => $e): ?>
                <div style="display:flex;gap:1.25rem;padding:1rem var(--space-6);<?= $i > 0 ? 'border-top:1px solid var(--line)' : '' ?>">
                    <span class="mono caption" style="flex-shrink:0;width:11ch"><?= htmlspecialchars(substr((string) $e['fecha'], 0, 10)) ?></span>
                    <span class="body-sm"><?= htmlspecialchars($e['texto']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
