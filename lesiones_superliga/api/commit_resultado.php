<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../config/admin_auth.php';
requerirAdminBasicAuth();

try {
    $in = json_decode(file_get_contents('php://input') ?: '[]', true);
    $equipoId = (string)($in['equipo_id'] ?? '');
    $codigoPrincipal = (string)($in['resultado_principal'] ?? '');
    $codigoSecundario = isset($in['codigo_secundario']) ? (string)$in['codigo_secundario'] : null;
    $forzar = !empty($in['forzar']);
    $semana = getSemanaISO();

    if ($equipoId === '' || $codigoPrincipal === '') jsonResponse(['success' => false, 'error' => 'faltan_parametros'], 400);

    $equipo = buscarEquipoPorId(cargarEquipos(), $equipoId);
    if (!$equipo) jsonResponse(['success' => false, 'error' => 'equipo_no_encontrado'], 404);

    $res = commitResultado($equipo, $semana, $codigoPrincipal, $codigoSecundario, $forzar);
    jsonResponse($res, ($res['success'] ?? false) ? 200 : (($res['error'] ?? '') === 'ya_procesado' ? 409 : 400));
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
