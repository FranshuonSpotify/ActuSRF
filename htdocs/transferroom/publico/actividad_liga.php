<?php

declare(strict_types=1);

/**
 * Actividad de la Liga (Fase 2, hub v3): feed global + Centro de Prensa +
 * Ranking dinámico + calendario de vencimientos. Sin especificación previa
 * — diseño propio completo, construido sobre datos reales ya existentes
 * (auditoría, ventanas RFA, plantillas), nunca resultados deportivos
 * inventados (CLAUDE.md: las estadísticas deportivas están descartadas).
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

// Acciones de negocio públicamente relevantes: nunca acciones técnicas/administrativas de auditoría.
const ACCIONES_ACTIVIDAD_LIGA = [
    'RESOLVER_MERCADO_AGENTE_LIBRE' => 'ficha a %JUGADOR% como agente libre.',
    'ACEPTAR_TRASPASO' => 'cierra un traspaso.',
    'IGUALAR_OFERTA_RFA' => 'iguala una Offer Sheet y retiene a su jugador.',
    'CONFIRMAR_SIN_IGUALAR' => 'deja marchar a un jugador tras vencer el plazo de igualación.',
    'GIRAR_RULETA_FRANQUICIA' => 'resuelve la ruleta de un jugador franquicia.',
    'PROTEGER_FRANCHISE_CLASICA' => 'protege a un jugador con la Protección Franchise clásica.',
    'ACEPTAR_PROPUESTA_PETICION' => 'acepta una propuesta del tablón de Peticiones.',
    'RETIRAR_PARTICIPACION' => 'se retira de la temporada.',
    'REACTIVAR_CLUB_ARCHIVADO' => 'vuelve a la liga tras estar archivado.',
    'FIRMAR_CONTRATOS_INICIALES_EN_LOTE' => 'completa la plantilla inicial de su club.',
];

$eventosRecientes = array_values(array_filter(
    $auditoria->listarRecientes(300),
    fn (array $e): bool => isset(ACCIONES_ACTIVIDAD_LIGA[$e['accion']])
));
$eventosRecientes = array_slice($eventosRecientes, 0, 40);

// Ranking dinámico: "clima competitivo" por valor de plantilla, nunca resultados deportivos (no se importan).
$temporadaActiva = $temporadas->obtenerActiva();
$ranking = [];
if ($temporadaActiva !== null) {
    foreach ($participaciones->listarPorTemporada((int) $temporadaActiva['id']) as $p) {
        if ($p['estado'] === 'RETIRADA') {
            continue;
        }
        $ranking[] = [
            'club_nombre' => $p['club_nombre'],
            'escudo_url' => $p['escudo_url'],
            'valor_plantilla' => $contratoRepositorio->sumaSalariosActivos((int) $p['id']),
            'fichas' => $contratoRepositorio->contarActivosPorParticipacion((int) $p['id']),
        ];
    }
    usort($ranking, fn ($a, $b) => $b['valor_plantilla'] <=> $a['valor_plantilla']);
}

$anunciosPublicados = $anuncios->listarPublicados(10);

$ventanasAbiertas = $temporadaActiva !== null ? $mercado->listarTodasVentanasIgualacionAbiertas((int) $temporadaActiva['id']) : [];
usort($ventanasAbiertas, fn ($a, $b) => strtotime((string) $a['fecha_limite_igualacion']) <=> strtotime((string) $b['fecha_limite_igualacion']));

$paginaTitulo = 'Actividad de la Liga';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'actividad_liga';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Liga</span>
            <h1 class="h1" style="margin-top:.5rem">Actividad de la Liga</h1>
        </div>
    </div>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="feed">Feed</button>
        <button data-tab-target="prensa">Centro de Prensa</button>
        <button data-tab-target="ranking">Ranking dinámico</button>
        <button data-tab-target="calendario">Calendario</button>
    </div>

    <div data-tab-panel="feed">
        <?php if ($eventosRecientes === []): ?>
            <div class="empty-state"><i class="ph ph-newspaper"></i><h3>Todavía no hay actividad</h3></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <?php foreach ($eventosRecientes as $e): ?>
                    <div class="card" style="padding:.9rem 1.1rem">
                        <span class="body-sm"><strong><?= htmlspecialchars($e['usuario_nombre'] ?? 'La liga') ?></strong> <?= htmlspecialchars(ACCIONES_ACTIVIDAD_LIGA[$e['accion']]) ?></span>
                        <div class="caption mono" style="margin-top:.25rem"><?= htmlspecialchars($e['creado_en']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="prensa" hidden>
        <p class="caption" style="margin-bottom:1rem">Titulares generados a partir de los movimientos reales del mercado. Sin rumores ni contenido de usuarios: nada que moderar.</p>

        <?php if ($anunciosPublicados !== []): ?>
            <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.5rem">
                <?php foreach ($anunciosPublicados as $a): ?>
                    <div class="card" style="border-color:var(--accent)">
                        <span class="overline">Anuncio de la liga</span>
                        <p class="h4" style="margin-top:.4rem"><?= htmlspecialchars($a['titulo']) ?></p>
                        <p class="body-sm" style="margin-top:.4rem"><?= htmlspecialchars($a['cuerpo']) ?></p>
                        <span class="caption mono"><?= htmlspecialchars($a['publicar_en']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($eventosRecientes === []): ?>
            <div class="empty-state"><i class="ph ph-megaphone"></i><h3>Sin titulares todavía</h3></div>
        <?php else: ?>
            <div class="grid-2">
                <?php foreach (array_slice($eventosRecientes, 0, 12) as $e): ?>
                    <div class="card">
                        <span class="overline">Última hora</span>
                        <p class="h4" style="margin-top:.4rem"><?= htmlspecialchars($e['usuario_nombre'] ?? 'La liga') ?> <?= htmlspecialchars(ACCIONES_ACTIVIDAD_LIGA[$e['accion']]) ?></p>
                        <span class="caption mono"><?= htmlspecialchars($e['creado_en']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="ranking" hidden>
        <p class="caption" style="margin-bottom:1rem">Clima competitivo por valor total de plantilla (suma de salarios activos). No se basa en resultados deportivos: no se importan estadísticas de partidos.</p>
        <?php if ($ranking === []): ?>
            <div class="empty-state"><i class="ph ph-trophy"></i><h3>Sin datos todavía</h3></div>
        <?php else: ?>
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>#</th><th>Club</th><th class="num">Fichas</th><th class="num">Valor de plantilla</th></tr></thead>
                    <tbody>
                    <?php foreach ($ranking as $i => $r): ?>
                        <tr>
                            <td class="mono"><?= $i + 1 ?></td>
                            <td>
                                <div class="fila-club">
                                    <?php if (!empty($r['escudo_url'])): ?>
                                        <img referrerpolicy="no-referrer" class="escudo" src="<?= htmlspecialchars($r['escudo_url']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($r['club_nombre']) ?>
                                </div>
                            </td>
                            <td class="num mono"><?= $r['fichas'] ?></td>
                            <td class="num mono"><?= number_format($r['valor_plantilla'], 0, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div data-tab-panel="calendario" hidden>
        <p class="caption" style="margin-bottom:1rem">Vencimientos reales de ventanas de igualación RFA en toda la liga.</p>
        <?php if ($ventanasAbiertas === []): ?>
            <div class="empty-state"><i class="ph ph-calendar-blank"></i><h3>Sin vencimientos pendientes</h3></div>
        <?php else: ?>
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Jugador</th><th>Vence</th></tr></thead>
                    <tbody>
                    <?php foreach ($ventanasAbiertas as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['jugador_nombre']) ?></td>
                            <td class="mono"><?= htmlspecialchars($v['fecha_limite_igualacion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
