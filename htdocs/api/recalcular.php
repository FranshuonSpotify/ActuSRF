<?php

function normalizarTexto($texto) {
    $texto = mb_strtolower(trim((string)$texto), 'UTF-8');

    $reemplazos = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n'
    ];

    $texto = strtr($texto, $reemplazos);
    $texto = preg_replace('/[^a-z0-9 ]/u', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

function buscarEquipoPorNombre(&$data, $nombreBuscado) {
    if (!isset($data['equipos']) || !is_array($data['equipos'])) return null;

    $objetivo = normalizarTexto($nombreBuscado);

    foreach ($data['equipos'] as $i => $equipo) {
        if (normalizarTexto($equipo['nombre'] ?? '') === $objetivo) {
            return $i;
        }
    }

    return null;
}

function resetearStatsEquipos(&$data, $divisionObjetivo) {
    if (!isset($data['equipos']) || !is_array($data['equipos'])) return;

    foreach ($data['equipos'] as $i => $equipo) {
        if (($equipo['division'] ?? '') === $divisionObjetivo) {
            $data['equipos'][$i]['pj'] = 0;
            $data['equipos'][$i]['g'] = 0;
            $data['equipos'][$i]['e'] = 0;
            $data['equipos'][$i]['p'] = 0;
            $data['equipos'][$i]['gf'] = 0;
            $data['equipos'][$i]['gc'] = 0;
            $data['equipos'][$i]['pts'] = 0;
        }
    }
}

function parsearDetalles($detalles) {
    $resultado = [];
    if (!$detalles || !is_string($detalles)) return $resultado;

    preg_match_all('/gol([A-Za-z0-9ÁÉÍÓÚáéíóúÑñ]+)(\d{1,3})/u', $detalles, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $resultado[] = [
            'jugadorToken' => $m[1],
            'minuto' => intval($m[2])
        ];
    }

    return $resultado;
}

function incrementarGolJugadorPorToken(&$data, $tokenJugador, $equipoNombre) {
    $idxEquipo = buscarEquipoPorNombre($data, $equipoNombre);
    if ($idxEquipo === null) return false;
    if (!isset($data['equipos'][$idxEquipo]['jugadores']) || !is_array($data['equipos'][$idxEquipo]['jugadores'])) return false;

    $tokenNorm = normalizarTexto($tokenJugador);

    foreach ($data['equipos'][$idxEquipo]['jugadores'] as $j => $jugador) {
        $nombre = $jugador['nombre'] ?? '';
        $nombreNorm = normalizarTexto($nombre);
        $nombreCompacto = str_replace(' ', '', $nombreNorm);

        if (
            $tokenNorm === $nombreNorm ||
            $tokenNorm === $nombreCompacto ||
            str_contains($nombreCompacto, $tokenNorm) ||
            str_contains($tokenNorm, $nombreCompacto)
        ) {
            $data['equipos'][$idxEquipo]['jugadores'][$j]['goles'] = intval($data['equipos'][$idxEquipo]['jugadores'][$j]['goles'] ?? 0) + 1;
            return true;
        }
    }

    return false;
}

function obtenerGolesPartido($partido, $lado) {
    if ($lado === 'local') {
        return intval($partido['golesl'] ?? $partido['goles_l'] ?? 0);
    }

    return intval($partido['golesv'] ?? $partido['goles_v'] ?? 0);
}

function recalcularClasificacionDivision(&$data, $division, $clavePartidos) {
    resetearStatsEquipos($data, $division);

    if (!isset($data[$clavePartidos]) || !is_array($data[$clavePartidos])) return;

    foreach ($data[$clavePartidos] as $partido) {
        if (($partido['estado'] ?? '') !== 'FINALIZADO') continue;

        $local = $partido['local'] ?? '';
        $visitante = $partido['visitante'] ?? '';
        $golesL = obtenerGolesPartido($partido, 'local');
        $golesV = obtenerGolesPartido($partido, 'visitante');

        $idxLocal = buscarEquipoPorNombre($data, $local);
        $idxVisit = buscarEquipoPorNombre($data, $visitante);

        if ($idxLocal === null || $idxVisit === null) continue;
        if (($data['equipos'][$idxLocal]['division'] ?? '') !== $division) continue;
        if (($data['equipos'][$idxVisit]['division'] ?? '') !== $division) continue;

        $data['equipos'][$idxLocal]['pj']++;
        $data['equipos'][$idxVisit]['pj']++;

        $data['equipos'][$idxLocal]['gf'] += $golesL;
        $data['equipos'][$idxLocal]['gc'] += $golesV;
        $data['equipos'][$idxVisit]['gf'] += $golesV;
        $data['equipos'][$idxVisit]['gc'] += $golesL;

        if ($golesL > $golesV) {
            $data['equipos'][$idxLocal]['g']++;
            $data['equipos'][$idxVisit]['p']++;
            $data['equipos'][$idxLocal]['pts'] += 3;
        } elseif ($golesV > $golesL) {
            $data['equipos'][$idxVisit]['g']++;
            $data['equipos'][$idxLocal]['p']++;
            $data['equipos'][$idxVisit]['pts'] += 3;
        } else {
            $data['equipos'][$idxLocal]['e']++;
            $data['equipos'][$idxVisit]['e']++;
            $data['equipos'][$idxLocal]['pts']++;
            $data['equipos'][$idxVisit]['pts']++;
        }
    }
}
