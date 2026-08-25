<?php

declare(strict_types=1);

/**
 * Franquicias (Fase 2, hub v3): página dedicada para designar/quitar franquicia
 * y proteger (+10M) — antes vivía mezclado en la tabla de plantilla.php.
 * mercado.md §9: hasta 4 jugadores franquicia por club.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$esAdmin = $permisos->esAdministrador($usuario);

if ($esAdmin) {
    header('Location: admin/clubes.php');
    exit;
}

$temporadaActiva = $temporadas->obtenerActiva();
$participacionModoPruebas = ModoPruebas::participacionActiva();
$miParticipacion = null;
if ($temporadaActiva !== null) {
    $miParticipacion = $participacionModoPruebas !== null
        ? $participaciones->buscarPorId($participacionModoPruebas)
        : $participaciones->buscarPorUsuarioYTemporada((int) $usuario['id'], (int) $temporadaActiva['id']);
}

$error = null;
$exito = null;

if ($miParticipacion !== null) {
    $participacionId = (int) $miParticipacion['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'designar_franquicia') {
        try {
            $jugadores->designarFranquicia((int) ($_POST['jugador_id'] ?? 0), $participacionId, (int) $usuario['id']);
            $exito = 'Jugador designado como franquicia.';
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'quitar_franquicia') {
        try {
            $jugadores->quitarFranquicia((int) ($_POST['jugador_id'] ?? 0), (int) $usuario['id']);
            $exito = 'Designación de franquicia retirada.';
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'proteger_franchise_clasica') {
        try {
            ModoPruebas::bloquearSiActivo();
            $contratos->protegerConFranchiseClasica((int) ($_POST['contrato_id'] ?? 0), (int) $usuario['id']);
            $exito = 'Jugador protegido con la Protección Franchise clásica (+10M) por 1 temporada más.';
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }

    $plantilla = $jugadores->listarPlantilla($participacionId);
    $contratosActivos = array_column($contratos->listarPlantillaContratada($participacionId), null, 'jugador_id');
    $franquiciaActuales = array_values(array_filter($plantilla, fn ($j) => (bool) $j['es_franquicia']));
    $totalFranquicias = count($franquiciaActuales);
}

// mercado.md §9: designar/quitar franquicia solo con el mercado abierto —
// mismo chequeo que SeasonEngine::requiereMercadoActivo, aquí solo para
// decidir qué botones pintar (JugadorEngine ya lo exige de verdad).
$mercadoActivoParaFranquicias = $temporadaActiva !== null
    && !(bool) $temporadaActiva['congelada']
    && in_array($temporadaActiva['estado'], ['MERCADO_ABIERTO', 'MERCADO_EXTRAORDINARIO'], true);

$paginaTitulo = 'Franquicias';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'franquicias';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Mi club</span>
            <h1 class="h1" style="margin-top:.5rem">Franquicias</h1>
            <p class="caption">Hasta 4 jugadores franquicia por club (mercado.md §9). Proteger cuesta +10M sobre el salario base de su tier.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ($miParticipacion !== null && !$mercadoActivoParaFranquicias): ?>
        <div class="alert alert-warning" role="status">
            <i class="ph ph-lock"></i>
            Las franquicias solo se pueden designar o quitar mientras el mercado está abierto.
            <?= $totalFranquicias < 4 ? 'Te faltan ' . (4 - $totalFranquicias) . ' por designar antes de que empiece la competición.' : 'Ya tienes tus 4 designadas.' ?>
        </div>
    <?php endif; ?>

    <?php if ($miParticipacion === null): ?>
        <div class="empty-state">
            <i class="ph ph-crown-simple"></i>
            <h3>Todavía no presides ningún club</h3>
            <p>Contacta con la administración para que te asignen uno en esta temporada.</p>
        </div>
    <?php else: ?>
        <h2 class="h2" style="margin-top:1rem">Designadas (<?= $totalFranquicias ?>/4)</h2>
        <?php if ($franquiciaActuales === []): ?>
            <div class="alert alert-success" style="margin-top:1rem"><i class="ph ph-info"></i> Todavía no has designado ninguna franquicia.</div>
        <?php else: ?>
            <div class="grid-2" style="margin-top:1rem">
                <?php foreach ($franquiciaActuales as $j): ?>
                    <div class="card">
                        <div class="fila-jugador">
                            <?php if (!empty($j['foto_url'])): ?>
                                <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($j['nombre']) ?></strong>
                                <div class="caption" style="margin-top:.2rem"><?= htmlspecialchars($j['posicion']) ?> · <span class="chip <?= tier_chip_class($j['tier_nombre'] ?? '') ?>"><?= htmlspecialchars($j['tier_nombre'] ?? '—') ?></span></div>
                            </div>
                        </div>
                        <p class="caption" style="margin-top:.6rem">Al terminar su contrato, se resolverá con la ruleta de retención (mercado.md §9), no con la Protección clásica.</p>
                        <?php if ($mercadoActivoParaFranquicias): ?>
                        <div style="margin-top:.75rem">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="quitar_franquicia">
                                <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost">Quitar designación</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="h2" style="margin-top:2.5rem">Jugadores contratados sin franquicia</h2>
        <p class="caption">Designar como franquicia y Proteger (+10M) son caminos alternativos para un mismo jugador: si ya es franquicia, su renovación se resuelve con la ruleta, no con la protección clásica.</p>
        <?php
            $candidatos = array_values(array_filter($plantilla, fn ($j) => !$j['es_franquicia'] && isset($contratosActivos[$j['id']])));
        ?>
        <?php if ($candidatos === []): ?>
            <p class="caption" style="margin-top:1rem">No tienes más jugadores contratados sin franquicia.</p>
        <?php else: ?>
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Jugador</th><th>Posición</th><th>Tier</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($candidatos as $j): ?>
                        <?php $contrato = $contratosActivos[$j['id']]; ?>
                        <tr>
                            <td><?= htmlspecialchars($j['nombre']) ?></td>
                            <td><?= htmlspecialchars($j['posicion']) ?></td>
                            <td><span class="chip <?= tier_chip_class($j['tier_nombre'] ?? '') ?>"><?= htmlspecialchars($j['tier_nombre'] ?? '—') ?></span></td>
                            <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                                <?php if ($totalFranquicias < 4 && $mercadoActivoParaFranquicias): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="accion" value="designar_franquicia">
                                        <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">Designar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="proteger_franchise_clasica">
                                    <input type="hidden" name="contrato_id" value="<?= (int) $contrato['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost">Proteger (+10M)</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($totalFranquicias >= 4): ?>
            <div class="alert alert-danger" style="margin-top:1rem"><i class="ph ph-warning"></i> Ya tienes el máximo de 4 franquicias designadas — "Designar" queda oculto hasta que quites alguna.</div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
