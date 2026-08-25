<?php
require_once __DIR__ . '/helpers.php';

try {
    $equipos = cargarEquipos();
    $semana = getSemanaISO();
    $idsActivos = array_map(fn($e) => (string)$e['id'], $equipos);
    $mapa = array_fill_keys($idsActivos, true);

    $estado = cargarEstadoSemanal();
    $procesados = array_values(array_filter($estado['equipos_procesados'] ?? [], fn($id) => isset($mapa[(string)$id])));
    $resultadosEstado = [];
    foreach (($estado['resultados'] ?? []) as $equipoId => $res) {
        if (isset($mapa[(string)$equipoId])) $resultadosEstado[(string)$equipoId] = $res;
    }

    $historial = historialFiltradoActivos(cargarHistorial());
    $lesionesSemana = array_values(array_filter($historial, fn($l) => (string)($l['anio_semana'] ?? '') === $semana));

    $resultados = [];
    foreach ($procesados as $equipoId) {
        $equipo = buscarEquipoPorId($equipos, (string)$equipoId);
        $lesionesEquipo = array_values(array_filter($lesionesSemana, fn($l) => (string)($l['equipo_id'] ?? '') === (string)$equipoId));
        if ($lesionesEquipo) {
            $primera = $lesionesEquipo[0];
            $resultados[$equipoId] = [
                'equipo_id' => $equipoId,
                'equipo_nombre' => $equipo['nombre'] ?? ($primera['equipo_nombre'] ?? $equipoId),
                'escudo' => $equipo['escudo'] ?? null,
                'resultado_principal' => 'evento_inesperado',
                'descripcion_resultado' => $primera['descripcion_resultado'] ?? null,
                'lesiones' => array_map(fn($l) => [
                    'jugador_id' => $l['jugador_id'] ?? '',
                    'jugador_nombre' => $l['jugador_nombre'] ?? '',
                    'dorsal' => $l['dorsal'] ?? '',
                    'foto' => $l['foto'] ?? '',
                    'partidos_restantes' => $l['partidos_restantes'] ?? 0,
                    'estado' => $l['estado'] ?? 'activa',
                ], $lesionesEquipo),
            ];
        } else {
            $resultados[$equipoId] = $resultadosEstado[$equipoId] ?? [
                'equipo_id' => $equipoId,
                'equipo_nombre' => $equipo['nombre'] ?? $equipoId,
                'escudo' => $equipo['escudo'] ?? null,
                'resultado_principal' => 'no_evento',
                'descripcion_resultado' => null,
                'lesiones' => [],
            ];
        }
    }

    if (($estado['semana'] ?? '') === $semana) {
        guardarEstadoSemanal(['semana' => $semana, 'equipos_procesados' => $procesados, 'resultados' => $resultados]);
    }

    jsonResponse([
        'success' => true,
        'equipos' => $equipos,
        'total' => count($equipos),
        'semana' => $semana,
        'procesados' => $procesados,
        'semana_completa' => count($procesados) >= count($equipos) && count($equipos) > 0,
        'resultados' => $resultados
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'php_fatal', 'message' => $e->getMessage()], 500);
}
