<?php
require_once __DIR__ . '/helpers.php';
try {
    $lesiones = historialFiltradoActivos(cargarHistorial());
    $lesiones = array_values(array_filter($lesiones, fn($l) => (string)($l['estado'] ?? '') === 'activa'));
    usort($lesiones, fn($a, $b) => strcmp((string)($b['fecha'] ?? ''), (string)($a['fecha'] ?? '')));
    jsonResponse(['success' => true, 'lesiones' => $lesiones, 'total' => count($lesiones)]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
