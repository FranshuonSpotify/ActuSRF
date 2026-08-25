<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$jugadorId = (int) ($_GET['jugador_id'] ?? 0);
$jugador = $jugadores->buscarPorId($jugadorId);

if ($jugador === null) {
    http_response_code(404);
    exit('El jugador no existe.');
}

$contratos_ = $contratos->historialJugador($jugadorId);
$traspasos_ = $traspasos->historialTraspasosJugador($jugadorId);

$afinidadNombre = null;
if ($jugador['afinidad_id'] !== null) {
    foreach ($afinidadRepositorio->listarTodas() as $a) {
        if ((int) $a['id'] === (int) $jugador['afinidad_id']) {
            $afinidadNombre = $a['nombre'];
            break;
        }
    }
}

// Ficha de jugador (05-transfer_room_docs/02_ux_diseno): contrato activo
// resumido arriba, el resto del historial cronológico debajo.
$contratoActual = null;
foreach ($contratos_ as $c) {
    if ($c['estado'] === 'ACTIVO') {
        $contratoActual = $c;
        break;
    }
}

// Historial deportivo unificado: contratos (firmas) y traspasos (cambios de
// club a mitad de contrato), mezclados en orden cronológico (Cap. XX).
$eventos = [];
foreach ($contratos_ as $c) {
    $eventos[] = [
        'fecha' => $c['creado_en'],
        'texto' => sprintf(
            'Contrato firmado con %s — Temporada %d, tier %s, %s €/temporada, %d temporada(s). Estado: %s.',
            $c['club_nombre'],
            $c['temporada_numero'],
            $c['tier_nombre'],
            number_format((float) $c['salario_anual'], 0, ',', '.'),
            $c['duracion_temporadas'],
            $c['estado']
        ),
    ];
}
foreach ($traspasos_ as $t) {
    $eventos[] = [
        'fecha' => $t['resuelto_en'],
        'texto' => sprintf(
            'Traspaso: %s → %s por %s €.',
            $t['club_vendedor_nombre'],
            $t['club_comprador_nombre'],
            number_format((float) $t['importe_traspaso'], 0, ',', '.')
        ),
    ];
}
usort($eventos, fn ($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));
$paginaTitulo = 'Historial · ' . $jugador['nombre'];
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'historial';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div class="fila-jugador" style="gap:1.25rem">
            <?php if (!empty($jugador['foto_url'])): ?>
                <img referrerpolicy="no-referrer" class="jugador-foto jugador-foto-lg" src="<?= htmlspecialchars($jugador['foto_url']) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="jugador-foto jugador-foto-lg" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user" style="font-size:1.75rem"></i></span>
            <?php endif; ?>
            <div>
                <span class="overline">Historial</span>
                <h1 class="h1" style="margin-top:.5rem"><?= htmlspecialchars($jugador['nombre']) ?></h1>
                <p>
                    <?= htmlspecialchars($jugador['posicion']) ?>
                    <?php if ($afinidadNombre !== null): ?> · <?= htmlspecialchars($afinidadNombre) ?><?php endif; ?>
                    · Estado actual: <span class="badge <?= $jugador['estado'] === 'ACTIVO' ? 'badge-success' : '' ?>"><?= htmlspecialchars(etiqueta_legible($jugador['estado'])) ?></span>
                </p>
                <?php $wikiUrlJugador = wiki_url_publica($jugador); ?>
                <?php if ($wikiUrlJugador !== null): ?>
                    <a href="<?= htmlspecialchars($wikiUrlJugador) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="margin-top:.75rem;width:fit-content">
                        <i class="ph ph-book-open-text"></i> Ver en la Wiki
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($contratoActual !== null): ?>
        <div class="card" style="max-width:480px;margin-bottom:2rem">
            <span class="caption">Contrato actual</span>
            <div class="h4" style="margin-top:.4rem"><?= htmlspecialchars($contratoActual['club_nombre']) ?></div>
            <div class="body-sm" style="margin-top:.5rem;color:var(--ink-2)">
                Tier <span class="chip <?= tier_chip_class($contratoActual['tier_nombre']) ?>"><?= htmlspecialchars($contratoActual['tier_nombre']) ?></span>
                · <span class="mono"><?= number_format((float) $contratoActual['salario_anual'], 0, ',', '.') ?> €</span>/temporada
                · <?= (int) $contratoActual['duracion_temporadas'] ?> temporada(s)
            </div>
        </div>
    <?php endif; ?>

    <?php if ($eventos === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-clock-counter-clockwise"></i>
                <h3>Sin historial todavía</h3>
                <p>Este jugador todavía no tiene contratos ni traspasos registrados.</p>
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
