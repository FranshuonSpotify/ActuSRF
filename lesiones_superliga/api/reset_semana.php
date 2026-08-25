<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../config/admin_auth.php';
requerirAdminBasicAuth();
try {
    $in = json_decode(file_get_contents('php://input') ?: '[]', true);
    $semana = trim((string)($in['semana'] ?? ''));
    if ($semana !== '') {
        $res = borrarSemanaConcreta($semana);
        jsonResponse(['success' => true, 'accion' => 'borrar_semana', 'resultado' => $res]);
    }
    guardarEstadoSemanal(['semana' => '', 'equipos_procesados' => [], 'resultados' => []]);
    jsonResponse(['success' => true, 'accion' => 'reset_semana']);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
