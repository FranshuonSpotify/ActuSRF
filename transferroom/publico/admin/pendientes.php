<?php

declare(strict_types=1);

/**
 * Bandeja de Pendientes (Fase 2, hub v3, admin): consolida en un solo
 * destino las 4 cosas que un administrador tiene que revisar con
 * regularidad. Tres de las cuatro fuentes ya existían como páginas propias
 * (revision_contratos.php, intervencion.php) — esta página NO duplica sus
 * acciones de escritura (evita dos caminos de código distintos para la
 * misma mutación), solo muestra el recuento y enlaza a la página real.
 * Anuncios programados es la única pieza nueva, sin especificación previa
 * — diseño propio (ver migración 029).
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
                case 'crear_anuncio':
                    $anuncios->crear(
                        (string) ($_POST['titulo'] ?? ''),
                        (string) ($_POST['cuerpo'] ?? ''),
                        (string) ($_POST['publicar_en'] ?? ''),
                        (int) $usuario['id']
                    );
                    $exito = 'Anuncio programado.';
                    break;
                case 'eliminar_anuncio':
                    $anuncios->eliminar((int) $_POST['anuncio_id']);
                    $exito = 'Anuncio eliminado.';
                    break;
                default:
                    $error = 'Acción no reconocida.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$contratosPendientes = $contratos->listarPendientesDeRevision();
$ventanasVencidas = $mercado->listarVentanasVencidasSinConfirmar();
$anunciosProgramados = $anuncios->listarProgramados();

$paginaTitulo = 'Bandeja de Pendientes';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_pendientes';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Bandeja de Pendientes</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert" style="margin-bottom:1rem"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success" style="margin-bottom:1rem"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="grid-3" style="margin-bottom:2rem">
        <a href="revision_contratos.php" class="card" style="display:block;color:inherit">
            <span class="caption">Fichajes / agentes externos sin revisar</span>
            <p class="h1 mono" style="margin-top:.4rem;color:<?= $contratosPendientes !== [] ? 'var(--warning)' : 'var(--success)' ?>"><?= count($contratosPendientes) ?></p>
            <span class="caption">Ir a Contratos pendientes de revisión →</span>
        </a>
        <a href="intervencion.php" class="card" style="display:block;color:inherit">
            <span class="caption">Ventanas RFA vencidas sin confirmar</span>
            <p class="h1 mono" style="margin-top:.4rem;color:<?= $ventanasVencidas !== [] ? 'var(--danger)' : 'var(--success)' ?>"><?= count($ventanasVencidas) ?></p>
            <span class="caption">Ir al Panel de intervención →</span>
        </a>
        <div class="card">
            <span class="caption">Anuncios programados</span>
            <p class="h1 mono" style="margin-top:.4rem"><?= count($anunciosProgramados) ?></p>
            <span class="caption">Se gestionan más abajo, en esta misma página</span>
        </div>
    </div>

    <?php if ($contratosPendientes !== []): ?>
        <div class="card" style="margin-bottom:1.5rem;border-color:var(--warning)">
            <h3 class="h4" style="margin-bottom:.5rem"><i class="ph ph-warning"></i> <?= count($contratosPendientes) ?> fichaje(s) esperando revisión</h3>
            <p class="caption">Fichajes directos de agentes libres externos u oficiales recién importados, sin aprobación previa del tier/salario elegido.</p>
        </div>
    <?php endif; ?>

    <?php if ($ventanasVencidas !== []): ?>
        <div class="card" style="margin-bottom:1.5rem;border-color:var(--danger)">
            <h3 class="h4" style="margin-bottom:.5rem"><i class="ph ph-hourglass-medium"></i> <?= count($ventanasVencidas) ?> ventana(s) RFA vencidas</h3>
            <p class="caption">El plazo de 48h para que el club de origen iguale ya pasó. Resuélvelas desde el Panel de intervención.</p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="h3" style="margin-bottom:.4rem">Anuncios programados</h2>
        <p class="caption" style="margin-bottom:1rem">No hay tareas programadas en este proyecto (sin cron): un anuncio empieza a aparecer en el Centro de Prensa en cuanto alguien carga la página después de su fecha de publicación, nunca antes.</p>

        <form method="post" style="display:flex;flex-direction:column;gap:.75rem;max-width:520px;margin-bottom:1.5rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="crear_anuncio">
            <div class="field" style="margin-bottom:0">
                <label class="field-label" for="anuncio-titulo">Título</label>
                <input class="input" type="text" id="anuncio-titulo" name="titulo" maxlength="150" required>
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="field-label" for="anuncio-cuerpo">Cuerpo</label>
                <textarea class="input" id="anuncio-cuerpo" name="cuerpo" rows="3" required></textarea>
            </div>
            <div class="field" style="margin-bottom:0">
                <label class="field-label" for="anuncio-publicar-en">Publicar el</label>
                <input class="input" type="datetime-local" id="anuncio-publicar-en" name="publicar_en" required>
            </div>
            <button type="submit" class="btn btn-primary">Programar anuncio</button>
        </form>

        <?php if ($anunciosProgramados === []): ?>
            <div class="empty-state"><i class="ph ph-megaphone"></i><h3>Sin anuncios programados</h3></div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <?php foreach ($anunciosProgramados as $a): ?>
                    <div class="card" style="padding:.9rem 1.1rem">
                        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start">
                            <div>
                                <strong class="body-sm"><?= htmlspecialchars($a['titulo']) ?></strong>
                                <p class="caption" style="margin-top:.3rem"><?= htmlspecialchars($a['cuerpo']) ?></p>
                                <span class="caption mono">Se publica: <?= htmlspecialchars($a['publicar_en']) ?></span>
                            </div>
                            <form method="post" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="eliminar_anuncio">
                                <input type="hidden" name="anuncio_id" value="<?= (int) $a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost" aria-label="Eliminar anuncio"><i class="ph ph-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
