<?php

declare(strict_types=1);

/**
 * Quejas esperables #6: "no sabía a quién avisar de un bug". Captura
 * contexto automáticamente (usuario, página de origen, hora) en vez de
 * depender de que alguien escriba por Discord. Reutiliza la auditoría en
 * vez de crear una tabla nueva de un solo uso (YAGNI).
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$error = null;
$exito = null;
$origen = (string) ($_POST['origen'] ?? $_GET['origen'] ?? '');

// Punto 13 del inventario: si quien enlaza aquí se olvida de pasar ?origen=
// (o el usuario llega escribiendo la URL a mano), no debe quedar en blanco
// mientras el navegador mande Referer — se coge solo la ruta, nunca un
// Referer de fuera del propio sitio, para no filtrar dominios externos ni
// convertir esto en un vector de datos ajenos.
if ($origen === '' && isset($_SERVER['HTTP_REFERER'])) {
    // PHP_URL_HOST nunca incluye el puerto, así que HTTP_HOST hay que
    // despojarlo también antes de comparar (si no, la comparación falla
    // siempre en cualquier entorno que no use el puerto por defecto).
    $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $hostActual = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0];
    if ($refererHost !== null && $refererHost === $hostActual) {
        $origen = (string) parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        if ($mensaje === '') {
            $error = 'Describe brevemente qué ha pasado.';
        } else {
            $auditoria->registrar((int) $usuario['id'], 'REPORTE_PROBLEMA', 'usuarios', (string) $usuario['id'], null, [
                'mensaje' => $mensaje,
                'origen' => $origen,
            ]);
            $exito = 'Reporte enviado. Un administrador lo revisará en Auditoría.';
        }
    }
}

$paginaTitulo = 'Reportar un problema';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = '';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec" style="max-width:520px">
    <div class="page-head">
        <div>
            <span class="overline">Ayuda</span>
            <h1 class="h1" style="margin-top:.5rem">Reportar un problema</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="origen" value="<?= htmlspecialchars($origen) ?>">
            <p class="caption" style="margin-bottom:1rem">Página de origen: <span class="mono"><?= htmlspecialchars($origen !== '' ? $origen : 'desconocida') ?></span></p>
            <div class="field">
                <label class="field-label" for="rp-mensaje">¿Qué ha pasado?</label>
                <textarea class="input" id="rp-mensaje" name="mensaje" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Enviar reporte</button>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
