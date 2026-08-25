<?php

declare(strict_types=1);

/**
 * Panel admin del enlace automático a la Wiki de Inazuma Eleven
 * (ESPECIFICACION_CLAUDE_WIKI_INAZUMA.md §29/§39). Sin AJAX nuevo: sigue el
 * mismo patrón de formulario + recarga que el resto de páginas de admin/
 * (pendientes.php, tablas_maestras.php) — no hace falta una infraestructura
 * distinta solo para esta pantalla.
 *
 * El backfill se procesa en lotes acotados por click (no todos los 500+ de
 * golpe) para no arriesgarse al límite de tiempo de ejecución del hosting
 * real (IONOS): cada click resuelve un lote y recarga con el resultado.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;
$resultadoBackfill = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            switch ($_POST['accion'] ?? '') {
                case 'confirmar_manual':
                    $tituloBruto = trim((string) ($_POST['wiki_titulo'] ?? ''));
                    // Si el admin pega una URL completa de Fandom en vez del
                    // título, nos quedamos solo con el último segmento de la
                    // ruta: nunca se guarda una URL externa tal cual (§19/§37),
                    // la URL final siempre se reconstruye desde el título.
                    if (preg_match('#fandom\.com/wiki/([^/?#]+)#i', $tituloBruto, $m)) {
                        $tituloBruto = rawurldecode($m[1]);
                    }
                    $jugadores->confirmarResolucionWikiManual((int) $_POST['jugador_id'], str_replace('_', ' ', $tituloBruto), (int) $usuario['id']);
                    $exito = 'Coincidencia confirmada manualmente.';
                    break;
                case 'rechazar':
                    $jugadores->rechazarResolucionWiki((int) $_POST['jugador_id'], (int) $usuario['id']);
                    $exito = 'Candidato rechazado. Volverá a "pendiente" para el próximo backfill.';
                    break;
                case 'buscar_de_nuevo':
                    $r = $jugadores->reintentarResolucionWiki((int) $_POST['jugador_id'], (int) $usuario['id']);
                    $exito = 'Nueva búsqueda: ' . strtoupper($r['status']) . ($r['title'] !== null ? ' — ' . $r['title'] : '');
                    break;
                case 'ejecutar_backfill':
                    $tamanoLote = min(50, max(1, (int) ($_POST['lote'] ?? 20)));
                    $lote = $jugadorRepositorio->listarPendientesWiki($tamanoLote);
                    $resultadoBackfill = ['matched' => 0, 'needs_review' => 0, 'not_found' => 0, 'error' => 0, 'total' => count($lote)];
                    foreach ($lote as $j) {
                        $r = $wikiResolver->resolver($j);
                        $jugadorRepositorio->actualizarResolucionWiki((int) $j['id'], $r);
                        $resultadoBackfill[$r['status']] = ($resultadoBackfill[$r['status']] ?? 0) + 1;
                    }
                    $exito = "Lote procesado: {$resultadoBackfill['total']} jugadores.";
                    break;
                default:
                    $error = 'Acción no reconocida.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$stats = $jugadores->contarPorEstadoWiki();
$needsReview = $jugadores->listarNeedsReviewWiki();
$todosParaWiki = $jugadores->listarTodosParaWiki();

$paginaTitulo = 'Wiki de Jugadores';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_wiki';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Wiki de Jugadores</h1>
            <p class="caption" style="margin-top:.4rem">Enlace automático de cada jugador a su página de la wiki de Inazuma Eleven en Fandom. Nunca se muestra un enlace dudoso al público — solo lo confirmado aquí o resuelto con alta confianza.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="grid-3" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
        <div class="card"><span class="caption">Matched</span><div class="h2 mono" style="margin-top:.3rem"><?= (int) $stats['matched'] ?></div></div>
        <div class="card"><span class="caption">Manual</span><div class="h2 mono" style="margin-top:.3rem"><?= (int) $stats['manual'] ?></div></div>
        <div class="card"><span class="caption">Needs review</span><div class="h2 mono" style="margin-top:.3rem"><?= (int) $stats['needs_review'] ?></div></div>
        <div class="card"><span class="caption">Not found</span><div class="h2 mono" style="margin-top:.3rem"><?= (int) $stats['not_found'] ?></div></div>
        <div class="card"><span class="caption">Pending</span><div class="h2 mono" style="margin-top:.3rem"><?= (int) $stats['pending'] ?></div></div>
        <div class="card"><span class="caption">Error</span><div class="h2 mono" style="margin-top:.3rem"><?= (int) $stats['error'] ?></div></div>
    </div>

    <h2 class="h2" style="margin-top:2rem">Backfill</h2>
    <p class="caption" style="margin-top:.4rem">
        Procesa por lotes a los jugadores en pending / needs_review / not_found / error (nunca a los ya matched o manual).
        Para los 500+ jugadores existentes, ejecuta varias veces o usa <code class="mono">php db/resolver_wiki_backfill.php</code> desde CLI (con <code class="mono">--dry-run</code> para previsualizar sin guardar).
    </p>
    <form method="post" style="display:flex;gap:.6rem;align-items:flex-end;margin-top:.75rem;flex-wrap:wrap">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="accion" value="ejecutar_backfill">
        <div class="field" style="margin-bottom:0">
            <label class="field-label" for="lote">Tamaño del lote</label>
            <input class="input" type="number" id="lote" name="lote" value="20" min="1" max="50" style="width:100px">
        </div>
        <button type="submit" class="btn btn-primary">Ejecutar lote ahora</button>
    </form>
    <?php if ($resultadoBackfill !== null): ?>
        <p class="body-sm" style="margin-top:.75rem">
            Procesados: <strong><?= $resultadoBackfill['total'] ?></strong> ·
            Matched: <strong><?= $resultadoBackfill['matched'] ?></strong> ·
            Needs review: <strong><?= $resultadoBackfill['needs_review'] ?></strong> ·
            Not found: <strong><?= $resultadoBackfill['not_found'] ?></strong> ·
            Errores: <strong><?= $resultadoBackfill['error'] ?></strong>
        </p>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:2rem">Pendientes de revisión (<?= count($needsReview) ?>)</h2>
    <p class="caption" style="margin-top:.4rem">Coincidencias con confianza intermedia: el público no ve ningún enlace hasta que se confirmen aquí.</p>

    <?php if ($needsReview === []): ?>
        <div class="empty-state" style="margin-top:1rem">
            <i class="ph ph-check-circle"></i>
            <h3>Nada pendiente de revisión</h3>
        </div>
    <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Jugador</th><th>Posición</th><th>Candidato</th><th>Confianza</th><th>Motivo</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($needsReview as $j): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($j['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($j['posicion']) ?></td>
                        <td><?= $j['wiki_title'] !== null ? htmlspecialchars($j['wiki_title']) : '<span class="caption">—</span>' ?></td>
                        <td class="mono"><?= $j['wiki_confidence'] !== null ? number_format((float) $j['wiki_confidence'], 2) : '—' ?></td>
                        <td class="caption"><?= htmlspecialchars(etiqueta_legible($j['wiki_reason'])) ?></td>
                        <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                            <?php if ($j['wiki_title'] !== null): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="confirmar_manual">
                                    <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                    <input type="hidden" name="wiki_titulo" value="<?= htmlspecialchars($j['wiki_title']) ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Confirmar</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">Rechazar</button>
                            </form>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="buscar_de_nuevo">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">Buscar de nuevo</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:2rem">Corregir cualquier jugador a mano (<?= count($todosParaWiki) ?>)</h2>
    <p class="caption" style="margin-top:.4rem">
        Para cualquier caso que la búsqueda automática (wiki en inglés, wiki en español) no resuelva bien, o que quieras
        corregir aunque ya esté <code class="mono">matched</code>: busca el jugador, pega el título de la página de Fandom
        (o la URL completa, se queda solo con el título) y confirma. También puedes quitar un enlace existente para que
        vuelva a "pendiente".
    </p>
    <div class="field" style="max-width:360px;margin-top:.75rem">
        <label class="field-label" for="filtro-wiki-todos-nombre">Buscar jugador</label>
        <input class="input" type="search" id="filtro-wiki-todos-nombre" placeholder="Nombre del jugador" oninput="TRwiki.filtrar()">
    </div>
    <p class="caption" id="filtro-wiki-todos-resumen" style="margin-top:.5rem"></p>
    <div class="tbl-wrap" style="margin-top:.5rem">
        <div class="tbl-scroll">
        <table class="tbl" id="tabla-wiki-todos">
            <thead><tr><th>Jugador</th><th>Posición</th><th>Estado</th><th>Título / URL de Fandom</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($todosParaWiki as $j): ?>
                <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>">
                    <td><strong><?= htmlspecialchars($j['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($j['posicion']) ?></td>
                    <td>
                        <span class="badge <?= $j['wiki_status'] === 'matched' ? 'badge-success' : ($j['wiki_status'] === 'manual' ? 'badge-accent' : ($j['wiki_status'] === 'needs_review' ? 'badge-warning' : '')) ?>">
                            <?= htmlspecialchars(etiqueta_legible($j['wiki_status'])) ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;min-width:260px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <input type="hidden" name="accion" value="confirmar_manual">
                            <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                            <input class="input" type="text" name="wiki_titulo" value="<?= htmlspecialchars($j['wiki_title'] ?? '') ?>" placeholder="Endou Mamoru" style="height:32px;font-size:var(--fs-caption);flex:1;min-width:160px">
                            <button type="submit" class="btn btn-sm btn-primary">Fijar</button>
                        </form>
                    </td>
                    <td>
                        <?php if (in_array($j['wiki_status'], ['matched', 'manual', 'needs_review'], true)): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">Quitar enlace</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</main>

<script>
var TRwiki = {
    filtrar: function () {
        var nombre = document.getElementById('filtro-wiki-todos-nombre').value.trim().toLowerCase();
        var filas = document.querySelectorAll('#tabla-wiki-todos tbody tr');
        var visibles = 0;
        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1;
            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });
        document.getElementById('filtro-wiki-todos-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    }
};
document.addEventListener('DOMContentLoaded', TRwiki.filtrar);
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
