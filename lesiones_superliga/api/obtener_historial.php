<?php
require_once __DIR__ . '/helpers.php';
try {
    $lesiones = historialFiltradoActivos(cargarHistorial());
    $equipoId = (string)($_GET['equipo_id'] ?? '');
    $estado = (string)($_GET['estado'] ?? '');
    $semana = (string)($_GET['semana'] ?? '');
    if ($equipoId !== '') $lesiones = array_values(array_filter($lesiones, fn($l) => (string)($l['equipo_id'] ?? '') === $equipoId));
    if ($estado !== '') $lesiones = array_values(array_filter($lesiones, fn($l) => (string)($l['estado'] ?? '') === $estado));
    if ($semana !== '') $lesiones = array_values(array_filter($lesiones, fn($l) => (string)($l['anio_semana'] ?? '') === $semana));
    usort($lesiones, fn($a, $b) => strcmp((string)($b['fecha'] ?? ''), (string)($a['fecha'] ?? '')));
    jsonResponse(['success' => true, 'lesiones' => $lesiones, 'total' => count($lesiones)]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
