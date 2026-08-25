<?php

declare(strict_types=1);

/**
 * Resolución automática de ventanas RFA vencidas (confirmado en la auditoría
 * de mercado: "añadir la resolución automática por tiempo de 48 horas").
 * No hay cron real en este proyecto (CLAUDE.md §6), así que esto se dispara
 * por URL desde un cron externo (IONOS solo ofrece cron por URL en hosting
 * compartido) — protegido con un secreto en vez de con sesión de admin,
 * porque un cron no tiene sesión.
 *
 * Uso: GET/POST a esta URL con ?secreto=<configuracion.cron_secreto_rfa>,
 * cada 15-30 minutos. Idempotente: si no hay ventanas vencidas, no hace nada.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$secreto = (string) ($_GET['secreto'] ?? $_POST['secreto'] ?? '');
if ($secreto === '' || !hash_equals($configuracion->obtenerString('cron_secreto_rfa'), $secreto)) {
    http_response_code(403);
    echo json_encode(['error' => 'Secreto inválido.']);
    exit;
}

// El cron actúa como un administrador (confirmarSinIgualacion lo exige para
// auditoría), pero no hay usuario de sesión: se atribuye al primer
// ADMINISTRADOR existente, igual que cualquier acción de sistema sin sesión.
$adminId = (int) $db->query("SELECT id FROM usuarios WHERE rol = 'ADMINISTRADOR' ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($adminId === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'No existe ningún administrador al que atribuir la resolución automática.']);
    exit;
}

$resultado = ['resueltas' => [], 'errores' => []];

foreach ($mercado->listarVentanasVencidasSinConfirmar() as $ventana) {
    $jugadorId = (int) $ventana['jugador_id'];
    try {
        $contratoId = $mercado->confirmarSinIgualacion($jugadorId, $adminId);
        $resultado['resueltas'][] = [
            'jugador_id' => $jugadorId,
            'jugador_nombre' => $ventana['jugador_nombre'],
            'contrato_id' => $contratoId,
        ];
    } catch (DomainException $e) {
        // Ya resuelta por otra vía (un admin la igualó/confirmó a mano justo
        // antes) — no es un fallo del cron, se registra y se sigue con las demás.
        $resultado['errores'][] = ['jugador_id' => $jugadorId, 'motivo' => $e->getMessage()];
    }
}

echo json_encode($resultado);
