<?php

declare(strict_types=1);

/**
 * Sondeo ligero para avisos del navegador (punto 7 del inventario, quejas
 * esperables #3): "no sabía que cerraba en 15 minutos". Nunca sustituye al
 * centro de notificaciones — solo permite que ui.js dispare un Notification()
 * real cuando llega un CIERRE_MERCADO_INMINENTE mientras la pestaña está abierta.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$desdeId = (int) ($_GET['desde_id'] ?? 0);
$nuevas = $notificaciones->listarNoLeidasDesde((int) $usuario['id'], $desdeId);

header('Content-Type: application/json');
echo json_encode(array_map(static fn (array $n): array => [
    'id' => (int) $n['id'],
    'tipo' => $n['tipo'],
    'mensaje' => $n['mensaje'],
    'enlace' => $n['enlace'],
], $nuevas));
