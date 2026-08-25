<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validar($_POST['csrf_token'] ?? null)) {
    if (($_POST['accion'] ?? '') === 'marcar_leida') {
        $notificaciones->marcarLeida((int) ($_POST['notificacion_id'] ?? 0), (int) $usuario['id']);
    } elseif (($_POST['accion'] ?? '') === 'marcar_todas') {
        $notificaciones->marcarTodasLeidas((int) $usuario['id']);
    }
    header('Location: notificaciones.php');
    exit;
}

$lista = $notificaciones->listarPorUsuario((int) $usuario['id']);

$iconosPorTipo = [
    'OFERTA_TRASPASO_RECIBIDA' => 'ph-arrows-left-right',
    'TRASPASO_ACEPTADO' => 'ph-check-circle',
    'TRASPASO_RECHAZADO' => 'ph-x-circle',
    'VENTANA_IGUALACION_ABIERTA' => 'ph-hourglass-medium',
    'RULETA_FRANQUICIA_RESUELTA' => 'ph-crown-simple',
    'FICHAJE_AGENTE_LIBRE_GANADO' => 'ph-user-plus',
    'FICHAJE_AGENTE_LIBRE_PERDIDO' => 'ph-user-minus',
    'CONTRATO_PENDIENTE_REVISION' => 'ph-file-magnifying-glass',
    'CONTRATO_FINALIZADO' => 'ph-file-x',
    'CONTRATO_REVISADO' => 'ph-file-check',
    'CONTRATO_RECHAZADO' => 'ph-file-x',
    'ESTADO_MERCADO' => 'ph-storefront',
    'CIERRE_MERCADO_INMINENTE' => 'ph-bell-ringing',
    'PLANTILLA_INICIAL_ASIGNADA' => 'ph-users-three',
    'PROPUESTA_PETICION_RECIBIDA' => 'ph-handshake',
    'PROPUESTA_PETICION_ACEPTADA' => 'ph-thumbs-up',
    'CONTRAOFERTA_TRASPASO_RECIBIDA' => 'ph-arrows-counter-clockwise',
    'TRASPASO_CANCELADO_POR_RETIRADA' => 'ph-warning-circle',
    'OFERTA_SOBRE_JUGADOR_SEGUIDO' => 'ph-binoculars',
];

$paginaTitulo = 'Notificaciones';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'notificaciones';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Notificaciones</span>
            <h1 class="h1" style="margin-top:.5rem">Notificaciones</h1>
        </div>
        <?php if ($lista !== []): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="marcar_todas">
                <button type="submit" class="btn btn-secondary btn-sm">Marcar todas como leídas</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Quejas esperables #3: "no sabía que cerraba en 15 minutos". Avisos del
         navegador mientras la pestaña está abierta, con permiso explícito y
         aviso claro si el usuario lo rechaza (ver ui.js: TR.avisosNavegador). -->
    <div class="card" id="tarjeta-avisos-navegador" style="max-width:520px;margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <div>
                <strong>Avisos del navegador</strong>
                <p class="caption" id="texto-estado-avisos" style="margin-top:.2rem">Recibe un aviso del sistema cuando el mercado esté a punto de cerrar, aunque tengas la pestaña en segundo plano.</p>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="boton-activar-avisos">Activar avisos</button>
        </div>
    </div>

    <?php if ($lista === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-bell-slash"></i>
                <h3>No tienes notificaciones</h3>
                <p>Aquí verás ofertas de traspaso, resultados de mercado y avisos de la administración.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="padding:0">
            <?php foreach ($lista as $i => $n): ?>
                <div style="display:flex;align-items:flex-start;gap:1rem;padding:1rem var(--space-6);<?= $i > 0 ? 'border-top:1px solid var(--line)' : '' ?><?= $n['leida'] ? '' : ';background:rgba(255,81,0,.04)' ?>">
                    <i class="ph <?= $iconosPorTipo[$n['tipo']] ?? 'ph-bell' ?>" style="font-size:1.125rem;color:<?= $n['leida'] ? 'var(--ink-4)' : 'var(--accent-hover)' ?>;margin-top:.15rem;flex-shrink:0"></i>
                    <div style="flex:1">
                        <div class="body-sm" style="<?= $n['leida'] ? '' : 'font-weight:600' ?>">
                            <?php if ($n['enlace'] !== null): ?>
                                <a href="<?= htmlspecialchars($n['enlace']) ?>" style="color:inherit"><?= htmlspecialchars($n['mensaje']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($n['mensaje']) ?>
                            <?php endif; ?>
                        </div>
                        <span class="caption mono" style="display:block;margin-top:.25rem"><?= htmlspecialchars($n['creado_en']) ?></span>
                    </div>
                    <?php if (!$n['leida']): ?>
                        <form method="post" style="flex-shrink:0">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <input type="hidden" name="accion" value="marcar_leida">
                            <input type="hidden" name="notificacion_id" value="<?= (int) $n['id'] ?>">
                            <button type="submit" class="btn btn-icon tt" data-tt="Marcar leída"><i class="ph ph-check"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
