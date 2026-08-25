<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$esAdmin = $permisos->esAdministrador($usuario);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } elseif (($_POST['accion'] ?? '') === 'abrir_admin' && !$esAdmin) {
        try {
            $conversacionId = $mensajes->obtenerOCrearConversacionConAdmin((int) $usuario['id']);
            header('Location: mensajes.php?conversacion_id=' . $conversacionId);
            exit;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    } elseif (($_POST['accion'] ?? '') === 'iniciar_entre_presidentes' && !$esAdmin) {
        try {
            $conversacionId = $mensajes->obtenerOCrearConversacionEntrePresidentes(
                (int) $usuario['id'],
                (int) ($_POST['contraparte_id'] ?? 0)
            );
            header('Location: mensajes.php?conversacion_id=' . $conversacionId);
            exit;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    } elseif (($_POST['accion'] ?? '') === 'enviar') {
        try {
            $mensajes->enviarMensaje(
                (int) ($_POST['conversacion_id'] ?? 0),
                (int) $usuario['id'],
                (string) ($_POST['cuerpo'] ?? '')
            );
            header('Location: mensajes.php?conversacion_id=' . (int) ($_POST['conversacion_id'] ?? 0));
            exit;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$conversacionIdVista = isset($_GET['conversacion_id']) ? (int) $_GET['conversacion_id'] : null;
$hilo = null;
if ($conversacionIdVista !== null) {
    try {
        $hilo = $mensajes->abrirConversacion($conversacionIdVista, $usuario);
    } catch (DomainException $e) {
        $error = $e->getMessage();
        $conversacionIdVista = null;
    }
}

$conversacionesEntrePresidentes = $esAdmin ? [] : $mensajes->listarConversacionesDePresidente((int) $usuario['id']);
$conversacionesAdmin = $esAdmin ? $mensajes->listarConversacionesParaAdministracion() : [];
$otrosPresidentes = $esAdmin ? [] : array_values(array_filter(
    $autenticacion->listarUsuarios(),
    fn (array $u): bool => $u['rol'] === 'PRESIDENTE' && (int) $u['id'] !== (int) $usuario['id']
));

$paginaTitulo = 'Mensajes';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'mensajes';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Mensajes</span>
            <h1 class="h1" style="margin-top:.5rem">Mensajes</h1>
            <p class="caption">Comunicación libre, sin relación con ofertas ni fichajes.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Antes: grid de dos columnas fijas en línea, sin media query — en
         móvil la columna de la lista (mínimo 240px) se comía casi todo el
         ancho y dejaba el hilo de la conversación aplastado/cortado
         (reportado con captura). Master-detail real ahora: en móvil se ve
         SOLO la lista o SOLO el hilo (según haya conversacion_id en la URL,
         que ya existe en el servidor — sin JS), con un enlace de vuelta. -->
    <div class="mensajes-grid">
        <div class="card mensajes-lista <?= $conversacionIdVista !== null ? 'mensajes-oculto-movil' : '' ?>" style="padding:0;overflow:hidden">
            <?php if (!$esAdmin): ?>
                <form method="post" style="padding:1rem;border-bottom:1px solid var(--line)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="accion" value="abrir_admin">
                    <button type="submit" class="btn btn-secondary btn-block btn-sm"><i class="ph ph-headset"></i> Escribir a la administración</button>
                </form>
                <?php if ($otrosPresidentes !== []): ?>
                    <form method="post" style="padding:1rem;border-bottom:1px solid var(--line);display:flex;gap:.5rem">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <input type="hidden" name="accion" value="iniciar_entre_presidentes">
                        <select name="contraparte_id" class="input" style="height:32px;font-size:var(--fs-caption)" required>
                            <option value="">Nueva conversación con...</option>
                            <?php foreach ($otrosPresidentes as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm">Ir</button>
                    </form>
                <?php endif; ?>
                <?php foreach ($conversacionesEntrePresidentes as $c): ?>
                    <a href="mensajes.php?conversacion_id=<?= (int) $c['id'] ?>" style="display:block;padding:.75rem 1rem;border-bottom:1px solid var(--line);<?= $conversacionIdVista === (int) $c['id'] ? 'background:var(--panel-2)' : '' ?>">
                        <strong><?= htmlspecialchars($c['contraparte_nombre']) ?></strong>
                        <?php if ((int) $c['no_leidos'] > 0): ?><span class="badge badge-accent" style="margin-left:.4rem"><?= (int) $c['no_leidos'] ?></span><?php endif; ?>
                        <div class="caption" style="margin-top:.15rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars((string) ($c['ultimo_mensaje'] ?? 'Sin mensajes todavía')) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if ($conversacionesEntrePresidentes === []): ?>
                    <p class="caption" style="padding:1rem">Todavía no tienes conversaciones con otros presidentes.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($conversacionesAdmin as $c): ?>
                    <a href="mensajes.php?conversacion_id=<?= (int) $c['id'] ?>" style="display:block;padding:.75rem 1rem;border-bottom:1px solid var(--line);<?= $conversacionIdVista === (int) $c['id'] ? 'background:var(--panel-2)' : '' ?>">
                        <strong><?= htmlspecialchars($c['presidente_nombre']) ?></strong>
                        <?php if ((int) $c['no_leidos'] > 0): ?><span class="badge badge-accent" style="margin-left:.4rem"><?= (int) $c['no_leidos'] ?></span><?php endif; ?>
                        <div class="caption" style="margin-top:.15rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars((string) ($c['ultimo_mensaje'] ?? '')) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if ($conversacionesAdmin === []): ?>
                    <p class="caption" style="padding:1rem">Ningún presidente ha escrito todavía.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card mensajes-hilo <?= $conversacionIdVista === null ? 'mensajes-oculto-movil' : '' ?>">
            <?php if ($hilo === null): ?>
                <div class="empty-state">
                    <i class="ph ph-chat-circle"></i>
                    <h3>Selecciona una conversación</h3>
                </div>
            <?php else: ?>
                <a href="mensajes.php" class="mensajes-volver"><i class="ph ph-arrow-left"></i> Conversaciones</a>
                <div style="display:flex;flex-direction:column;gap:.75rem;max-height:480px;overflow-y:auto;padding-bottom:1rem">
                    <?php foreach ($hilo['mensajes'] as $m): ?>
                        <?php $esMio = (int) $m['remitente_id'] === (int) $usuario['id']; ?>
                        <div style="align-self:<?= $esMio ? 'flex-end' : 'flex-start' ?>;max-width:75%">
                            <div class="caption" style="margin-bottom:.15rem;<?= $esMio ? 'text-align:right' : '' ?>"><?= htmlspecialchars($m['remitente_nombre']) ?><?= $m['remitente_rol'] === 'ADMINISTRADOR' ? ' (Administración)' : '' ?></div>
                            <div class="card" style="padding:.6rem .9rem;background:<?= $esMio ? 'var(--accent-dim, var(--panel-2))' : 'var(--panel-2)' ?>">
                                <?= nl2br(htmlspecialchars($m['cuerpo'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="post" style="display:flex;gap:.5rem;margin-top:1rem;border-top:1px solid var(--line);padding-top:1rem">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="accion" value="enviar">
                    <input type="hidden" name="conversacion_id" value="<?= (int) $conversacionIdVista ?>">
                    <textarea class="input" name="cuerpo" rows="2" maxlength="4000" required placeholder="Escribe un mensaje..." style="flex:1"></textarea>
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
