<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$exito = false;

if ($token === '') {
    http_response_code(404);
    exit('Enlace de recuperación no válido.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } elseif ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirmar'] ?? '')) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        try {
            $autenticacion->restablecerPassword($token, (string) ($_POST['password'] ?? ''));
            $exito = true;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}
$paginaTitulo = 'Restablecer contraseña';
$base = '';
include __DIR__ . '/../partials/head.php';
?>
<main id="contenido" style="min-height:100vh;display:grid;place-items:center;padding-block:4rem">
    <div class="wrap" style="max-width:400px">
        <div style="text-align:center;margin-bottom:2.5rem">
            <img src="<?= asset_version($base, 'assets/img/transferroom.png') ?>" alt="Transfer Room · Superliga Frontier" style="width:min(100%,260px);height:auto;display:block;margin:0 auto">
        </div>

        <div class="card">
            <h1 class="h4" style="margin-bottom:1.5rem">Restablecer contraseña</h1>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($exito): ?>
                <div class="alert alert-success" role="status"><i class="ph ph-check-circle"></i> Contraseña actualizada. Ya puedes iniciar sesión con ella.</div>
                <p class="caption" style="text-align:center;margin-top:1rem"><a href="login.php">Iniciar sesión</a></p>
            <?php else: ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="field">
                        <label class="field-label" for="recuperar-password">Contraseña nueva</label>
                        <input class="input" type="password" id="recuperar-password" name="password" minlength="8" required autofocus>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recuperar-password-confirmar">Repite la contraseña</label>
                        <input class="input" type="password" id="recuperar-password-confirmar" name="password_confirmar" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Restablecer contraseña</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
