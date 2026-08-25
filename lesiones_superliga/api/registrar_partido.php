<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../config/admin_auth.php';
requerirAdminBasicAuth();
try {
    $in = json_decode(file_get_contents('php://input') ?: '[]', true);
    $todos = !empty($in['todos']);
    $partidos = max(1, (int)($in['partidos'] ?? 1));
    if ($todos) {
        $res = registrarPartidosTodos($partidos);
        jsonResponse(['success' => true, 'modo' => 'todos', 'partidos' => $partidos, 'resultados' => $res]);
    }
    $equipoId = (string)($in['equipo_id'] ?? '');
    if ($equipoId === '') jsonResponse(['success' => false, 'error' => 'faltan_parametros'], 400);
    $eq = buscarEquipoPorId(cargarEquipos(), $equipoId);
    if (!$eq) jsonResponse(['success' => false, 'error' => 'equipo_no_encontrado'], 404);
    $act = registrarPartidosEquipo($equipoId, $partidos);
    jsonResponse(['success' => true, 'modo' => 'equipo', 'equipo_id' => $equipoId, 'equipo_nombre' => $eq['nombre'], 'partidos' => $partidos, 'actualizadas' => $act]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
