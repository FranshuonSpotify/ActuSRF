<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if ($autenticacion->usuarioActual() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        $urlBase = (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $autenticacion->solicitarRecuperacion((string) ($_POST['email'] ?? ''), $urlBase);
        // Siempre el mismo resultado exista o no la cuenta: no se revela qué correos están dados de alta.
        $enviado = true;
    }
}
$paginaTitulo = 'Recuperar contraseña';
$base = '';
include __DIR__ . '/../partials/head.php';
?>
<main id="contenido" style="min-height:100vh;display:grid;place-items:center;padding-block:4rem">
    <div class="wrap" style="max-width:400px">
        <div style="text-align:center;margin-bottom:2.5rem">
            <img src="<?= asset_version($base, 'assets/img/transferroom.png') ?>" alt="Transfer Room · Superliga Frontier" style="width:min(100%,260px);height:auto;display:block;margin:0 auto">
        </div>

        <div class="card">
            <h1 class="h4" style="margin-bottom:1.5rem">Recuperar contraseña</h1>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($enviado): ?>
                <div class="alert alert-success" role="status"><i class="ph ph-check-circle"></i> Si ese correo tiene una cuenta activa, te hemos enviado un enlace para restablecer la contraseña. Caduca en 1 hora.</div>
                <p class="caption" style="text-align:center;margin-top:1rem"><a href="login.php">Volver a iniciar sesión</a></p>
            <?php else: ?>
                <p class="caption" style="margin-bottom:1.25rem">Escribe el correo de tu cuenta y te enviaremos un enlace para restablecer la contraseña.</p>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <div class="field">
                        <label class="field-label" for="olvide-email">Correo</label>
                        <input class="input" type="email" id="olvide-email" name="email" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Enviar enlace</button>
                </form>
                <p class="caption" style="text-align:center;margin-top:1rem"><a href="login.php">Volver a iniciar sesión</a></p>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
