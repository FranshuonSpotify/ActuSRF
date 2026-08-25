<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

http_response_code(404);

$paginaTitulo = 'Página no encontrada';
$base = '';
include __DIR__ . '/../partials/head.php';
?>
<main id="contenido" style="min-height:100vh;display:grid;place-items:center;padding-block:4rem">
    <div class="wrap" style="max-width:440px;text-align:center">
        <div style="margin-bottom:2rem">
            <img src="<?= asset_version($base, 'assets/img/transferroom.png') ?>" alt="Transfer Room · Superliga Frontier" style="width:min(100%,260px);height:auto;display:block;margin:0 auto">
        </div>

        <div class="card">
            <i class="ph ph-compass" style="font-size:2.5rem;color:var(--ink-4)"></i>
            <h1 class="h3" style="margin-top:1rem">Página no encontrada</h1>
            <p class="caption" style="margin-top:.5rem">La dirección no existe o ha cambiado. Comprueba el enlace o vuelve al panel.</p>
            <a href="<?= $autenticacion->usuarioActual() !== null ? 'dashboard.php' : 'login.php' ?>" class="btn btn-primary btn-block" style="margin-top:1.5rem">Volver al panel</a>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
