<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_usuario') {
    try {
        $autenticacion->crearUsuario(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['nombre'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['rol'] ?? 'PRESIDENTE'),
            (int) $usuario['id']
        );
        $exito = 'Usuario creado correctamente.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cerrar_sesion_distancia') {
    try {
        $autenticacion->cerrarSesionDeUsuario((int) ($_POST['usuario_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Sesión cerrada. En su próxima petición, ese usuario tendrá que volver a iniciar sesión.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$listaUsuarios = $autenticacion->listarUsuarios();
$paginaTitulo = 'Usuarios';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_usuarios';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Usuarios</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="card" style="max-width:420px">
        <h2 class="h3">Crear usuario</h2>
        <form method="post" style="margin-top:1.25rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="crear_usuario">
            <div class="field"><label class="field-label" for="us-email">Correo</label><input class="input" id="us-email" type="email" name="email" required></div>
            <div class="field"><label class="field-label" for="us-nombre">Nombre</label><input class="input" id="us-nombre" type="text" name="nombre" required></div>
            <div class="field"><label class="field-label" for="us-password">Contraseña</label><input class="input" id="us-password" type="password" name="password" required minlength="8"></div>
            <div class="field">
                <label class="field-label" for="us-rol">Rol</label>
                <select class="select" id="us-rol" name="rol" required>
                    <option value="PRESIDENTE">Presidente</option>
                    <option value="ADMINISTRADOR">Administrador</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Crear</button>
        </form>
    </div>

    <h2 class="h2" style="margin-top:2.5rem">Usuarios existentes (<?= count($listaUsuarios) ?>)</h2>
    <div class="tbl-wrap" style="margin-top:1rem">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($listaUsuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td class="caption"><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge <?= $u['rol'] === 'ADMINISTRADOR' ? 'badge-accent' : '' ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
                    <td><span class="badge <?= $u['estado'] === 'ACTIVO' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars(etiqueta_legible($u['estado'])) ?></span></td>
                    <td class="caption mono"><?= htmlspecialchars($u['ultimo_acceso'] ?? 'Nunca') ?></td>
                    <td>
                        <?php if ((int) $u['id'] !== (int) $usuario['id']): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="cerrar_sesion_distancia">
                                <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Cerrar sesión</button>
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
<?php include __DIR__ . '/../../partials/footer.php'; ?>
