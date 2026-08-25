<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../config/admin_auth.php';
requerirAdminBasicAuth();
try {
    $in = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (empty($in['confirmar'])) jsonResponse(['success' => false, 'error' => 'confirmacion_requerida'], 400);
    borrarTodosLosDatos();
    jsonResponse(['success' => true, 'accion' => 'borrar_todo']);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
