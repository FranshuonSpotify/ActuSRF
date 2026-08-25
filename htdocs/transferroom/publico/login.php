<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if ($autenticacion->usuarioActual() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            $autenticacion->iniciarSesion($email, $password);
            header('Location: dashboard.php');
            exit;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}
$paginaTitulo = 'Iniciar sesión';
$base = '';
include __DIR__ . '/../partials/head.php';
?>
<main id="contenido" class="login-shell">
    <div class="wrap" style="max-width:412px">
        <div class="login-brand">
            <img src="<?= asset_version($base, 'assets/img/transferroom.png') ?>" alt="Transfer Room · Superliga Frontier" style="width:min(100%,300px);height:auto;display:block;margin:0 auto">
        </div>

        <div class="card con-spotlight con-filo login-card">
            <h1 class="h4">Iniciar sesión</h1>
            <p class="caption" style="margin-top:.35rem">Entra con las credenciales de tu presidencia.</p>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert" style="margin-top:1.5rem"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate style="margin-top:1.75rem">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <div class="field">
                    <label class="field-label" for="login-email">Correo</label>
                    <div class="login-field-icon">
                        <input class="input" type="email" id="login-email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <i class="ph ph-envelope-simple" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="login-password">Contraseña</label>
                    <div class="login-field-icon">
                        <input class="input" type="password" id="login-password" name="password" required>
                        <i class="ph ph-lock-key" aria-hidden="true"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:.5rem">Entrar</button>
            </form>
            <div class="login-card-foot">
                <a href="olvide_password.php">¿Olvidaste tu contraseña?</a>
            </div>
        </div>

        <p class="caption" style="text-align:center;margin-top:1.75rem;color:var(--ink-4)">Proyecto fan-made sin ánimo de lucro. Sin afiliación con Level-5 / Inazuma Eleven.</p>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
