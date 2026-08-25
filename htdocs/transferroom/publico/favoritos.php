<?php

declare(strict_types=1);

/** Endpoint de acción del hub (Fase 2): fijar/quitar un favorito, sin vista propia. */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$volver = (string) ($_POST['volver'] ?? 'dashboard.php');
// Nunca redirigir fuera del propio sitio (evita usar este endpoint como open redirect).
if (str_starts_with($volver, 'http://') || str_starts_with($volver, 'https://') || str_starts_with($volver, '//')) {
    $volver = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::validar($_POST['csrf_token'] ?? null) && ($_POST['accion'] ?? '') === 'alternar') {
    // Una ruta favorita puede incluir query string y hash de pestaña
    // (mercado.php?x=1#agentes-libres) para fijar una subsección concreta,
    // no solo la página entera — basename() la habría destrozado (se come
    // todo lo anterior a la última "/", incluido el prefijo "admin/" de las
    // páginas de administración: bug real, el favorito quedaba roto).
    $ruta = trim((string) ($_POST['ruta'] ?? ''));
    $rutaValida = $ruta !== ''
        && mb_strlen($ruta) <= 150
        && !preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $ruta) // sin esquema ni protocolo-relativo
        && !str_starts_with($ruta, '/');
    $etiqueta = trim((string) ($_POST['etiqueta'] ?? $ruta));
    if ($rutaValida && $etiqueta !== '') {
        $favoritos->alternar((int) $usuario['id'], $ruta, $etiqueta);
    }
}

header('Location: ' . $volver);
exit;
