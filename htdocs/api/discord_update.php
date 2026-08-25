<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON no válido', 'raw' => $raw], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!hash_equals(DISCORD_SHARED_TOKEN, (string) ($input['token'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token inválido'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!defined('DATA_JSON') || !file_exists(DATA_JSON)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No existe el JSON'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function normalizarTexto($texto) {
    $texto = mb_strtolower(trim((string)$texto), 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    return preg_replace('/[^a-z0-9]+/i', ' ', $texto);
}

function obtenerClavePartidos($competicion) {
    $competicion = strtoupper(trim((string)$competicion));
    if ($competicion === 'SUPERLIGA' || $competicion === 'LIGA') return 'partidos_liga';
    if ($competicion === 'ASCENSO') return 'partidos_ascenso';
    if ($competicion === 'COPA') return 'partidos_copa';
    return null;
}

function parsearGoleadoresTexto($texto) {
    $texto = trim((string)$texto);
    if ($texto === '') return [];
    $out = [];
    foreach (array_map('trim', explode(',', $texto)) as $parte) {
        if ($parte === '') continue;
        if (preg_match("/^(.*?)\s+(\d{1,3}(?:\+\d{1,3})?)'?$/u", $parte, $m)) {
            $out[] = ['nombre' => trim($m[1]), 'minuto' => trim($m[2])];
        }
    }
    return $out;
}

// IMPORTANTE: NO se aplica JSON_UNESCAPED_SLASHES aquí a propósito.
// El formato que debe quedar guardado en el JSON es:
// "gol:Juan:10, gol:Pedro:44 \/ gol:Ana:35"
// (con la barra "/" escapada como "\/" por PHP), donde todo lo que hay
// ANTES de la barra son los goles del equipo LOCAL y todo lo que hay
// DESPUÉS son los goles del equipo VISITANTE.
function construirDetalles($local, $visitante) {
    $tokensLocal = [];
    foreach ($local as $gol) $tokensLocal[] = 'gol:' . trim($gol['nombre']) . ':' . trim($gol['minuto']);
    $tokensVisitante = [];
    foreach ($visitante as $gol) $tokensVisitante[] = 'gol:' . trim($gol['nombre']) . ':' . trim($gol['minuto']);
    return implode(', ', $tokensLocal) . ' / ' . implode(', ', $tokensVisitante);
}

function construirTextoVisible($local, $visitante) {
    $partes = [];
    foreach ($local as $gol) $partes[] = trim($gol['nombre']) . ' ' . $gol['minuto'] . "'";
    foreach ($visitante as $gol) $partes[] = trim($gol['nombre']) . ' ' . $gol['minuto'] . "'";
    return implode(', ', $partes);
}

// Esta función deshace el escape SOLO en memoria para poder volver a
// parsear los goles guardados (local antes de "/", visitante después).
// El fichero en disco sigue guardándose con la barra escapada como "\/".
function desescaparBarra($detalles) {
    return str_replace('\\/', '/', (string)$detalles);
}

function parsearDetallesGuardados($detalles) {
    $limpio = desescaparBarra($detalles);
    $partes = explode('/', $limpio, 2);
    $localStr = trim($partes[0] ?? '');
    $visStr = trim($partes[1] ?? '');
    $parseLado = function($str) {
        $out = [];
        if ($str === '') return $out;
        foreach (array_map('trim', explode(',', $str)) as $token) {
            if ($token === '') continue;
            $bits = explode(':', $token);
            if (count($bits) >= 2 && trim($bits[0]) === 'gol') {
                $out[] = ['nombre' => trim($bits[1]), 'minuto' => isset($bits[2]) ? trim($bits[2]) : ''];
            }
        }
        return $out;
    };
    return ['local' => $parseLado($localStr), 'visitante' => $parseLado($visStr)];
}

// ── RECÁLCULO DE CLASIFICACIÓN Y ESTADÍSTICAS ──

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

// Recalcula la clasificación de la FASE DE GRUPOS de Copa, agrupando
// los partidos por letra de grupo (A, B, C, D...) y guardando el
// resultado en $data['clasificacion_copa'][$grupo] = [ ...equipos... ]
function recalcularClasificacionGruposCopa(&$data) {
    if (!isset($data['partidos_copa']) || !is_array($data['partidos_copa'])) return;

    $grupos = []; // grupo => [ nombreNormalizado => stats ]
    $nombresOriginales = []; // nombreNormalizado => nombre original

    foreach ($data['partidos_copa'] as $partido) {
        $fase = strtoupper(trim((string)($partido['fase'] ?? '')));
        if ($fase !== 'FASE DE GRUPOS') continue;

        $grupo = strtoupper(trim((string)($partido['grupo'] ?? '')));
        if ($grupo === '') continue;

        $local = $partido['local'] ?? '';
        $visitante = $partido['visitante'] ?? '';
        $localNorm = normalizarTexto($local);
        $visitanteNorm = normalizarTexto($visitante);

        if (!isset($grupos[$grupo])) $grupos[$grupo] = [];
        foreach ([[$localNorm, $local], [$visitanteNorm, $visitante]] as $par) {
            list($norm, $orig) = $par;
            if (!isset($grupos[$grupo][$norm])) {
                $grupos[$grupo][$norm] = ['pj' => 0, 'g' => 0, 'e' => 0, 'p' => 0, 'gf' => 0, 'gc' => 0, 'pts' => 0];
            }
            $nombresOriginales[$norm] = $orig;
        }

        if (($partido['estado'] ?? '') !== 'FINALIZADO') continue;

        $golesL = obtenerGolesPartido($partido, 'local');
        $golesV = obtenerGolesPartido($partido, 'visitante');

        $grupos[$grupo][$localNorm]['pj']++;
        $grupos[$grupo][$visitanteNorm]['pj']++;
        $grupos[$grupo][$localNorm]['gf'] += $golesL;
        $grupos[$grupo][$localNorm]['gc'] += $golesV;
        $grupos[$grupo][$visitanteNorm]['gf'] += $golesV;
        $grupos[$grupo][$visitanteNorm]['gc'] += $golesL;

        if ($golesL > $golesV) {
            $grupos[$grupo][$localNorm]['g']++;
            $grupos[$grupo][$visitanteNorm]['p']++;
            $grupos[$grupo][$localNorm]['pts'] += 3;
        } elseif ($golesV > $golesL) {
            $grupos[$grupo][$visitanteNorm]['g']++;
            $grupos[$grupo][$localNorm]['p']++;
            $grupos[$grupo][$visitanteNorm]['pts'] += 3;
        } else {
            $grupos[$grupo][$localNorm]['e']++;
            $grupos[$grupo][$visitanteNorm]['e']++;
            $grupos[$grupo][$localNorm]['pts']++;
            $grupos[$grupo][$visitanteNorm]['pts']++;
        }
    }

    $clasificacionFinal = [];
    foreach ($grupos as $grupo => $equiposGrupo) {
        $lista = [];
        foreach ($equiposGrupo as $norm => $stats) {
            $stats['equipo'] = $nombresOriginales[$norm] ?? $norm;
            $lista[] = $stats;
        }
        usort($lista, function($a, $b) {
            if ($b['pts'] !== $a['pts']) return $b['pts'] - $a['pts'];
            $dgA = $a['gf'] - $a['gc'];
            $dgB = $b['gf'] - $b['gc'];
            if ($dgB !== $dgA) return $dgB - $dgA;
            return $b['gf'] - $a['gf'];
        });
        $clasificacionFinal[$grupo] = $lista;
    }

    $data['clasificacion_copa'] = $clasificacionFinal;
}

function resetGoalsJugadores(&$data) {
    if (!isset($data['equipos']) || !is_array($data['equipos'])) return;
    foreach ($data['equipos'] as $i => $eq) {
        if (!isset($eq['jugadores']) || !is_array($eq['jugadores'])) continue;
        foreach ($eq['jugadores'] as $j => $jug) {
            $data['equipos'][$i]['jugadores'][$j]['goles'] = 0;
        }
    }
}

function incrementarGolJugador(&$data, $nombreJugador, $equipoNombre) {
    $idxEquipo = buscarEquipoPorNombre($data, $equipoNombre);
    if ($idxEquipo === null) return;
    if (!isset($data['equipos'][$idxEquipo]['jugadores']) || !is_array($data['equipos'][$idxEquipo]['jugadores'])) return;
    $objetivo = normalizarTexto($nombreJugador);
    foreach ($data['equipos'][$idxEquipo]['jugadores'] as $j => $jugador) {
        $nombreNorm = normalizarTexto($jugador['nombre'] ?? '');
        if ($nombreNorm === $objetivo) {
            $data['equipos'][$idxEquipo]['jugadores'][$j]['goles'] = intval($data['equipos'][$idxEquipo]['jugadores'][$j]['goles'] ?? 0) + 1;
            return;
        }
    }
}

function recalcularEstadisticasJugadores(&$data) {
    resetGoalsJugadores($data);
    $bloques = ['partidos_liga', 'partidos_ascenso', 'partidos_copa'];
    foreach ($bloques as $bloque) {
        if (!isset($data[$bloque]) || !is_array($data[$bloque])) continue;
        foreach ($data[$bloque] as $partido) {
            if (($partido['estado'] ?? '') !== 'FINALIZADO') continue;
            $ev = parsearDetallesGuardados($partido['detalles'] ?? '');
            foreach ($ev['local'] as $gol) incrementarGolJugador($data, $gol['nombre'], $partido['local'] ?? '');
            foreach ($ev['visitante'] as $gol) incrementarGolJugador($data, $gol['nombre'], $partido['visitante'] ?? '');
        }
    }
}

$data = json_decode(file_get_contents(DATA_JSON), true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo leer el JSON'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$competicion = strtoupper(trim((string)($input['competicion'] ?? '')));
$jornada = $input['jornada'] ?? null;
$fase = trim((string)($input['fase'] ?? ''));
$grupo = strtoupper(trim((string)($input['grupo'] ?? '')));
$local = trim((string)($input['local'] ?? ''));
$visitante = trim((string)($input['visitante'] ?? ''));
$golesLocal = intval($input['goles_local'] ?? 0);
$golesVisitante = intval($input['goles_visitante'] ?? 0);
$goleadoresLocal = parsearGoleadoresTexto((string)($input['goleadores_local'] ?? ''));
$goleadoresVisitante = parsearGoleadoresTexto((string)($input['goleadores_visitante'] ?? ''));

if ($competicion === '' || $local === '' || $visitante === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan campos obligatorios'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$clavePartidos = obtenerClavePartidos($competicion);
if ($clavePartidos === null || !isset($data[$clavePartidos]) || !is_array($data[$clavePartidos])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Competición no válida'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$detalles = construirDetalles($goleadoresLocal, $goleadoresVisitante);
$textoVisible = construirTextoVisible($goleadoresLocal, $goleadoresVisitante);
$encontrado = false;

foreach ($data[$clavePartidos] as $i => $partido) {
    if (normalizarTexto($partido['local'] ?? '') !== normalizarTexto($local)) continue;
    if (normalizarTexto($partido['visitante'] ?? '') !== normalizarTexto($visitante)) continue;

    if ($clavePartidos === 'partidos_copa') {
        if (normalizarTexto($partido['fase'] ?? '') !== normalizarTexto($fase)) continue;
        if (strtoupper(trim((string)($partido['fase'] ?? ''))) === 'FASE DE GRUPOS' && $grupo !== '') {
            if (strtoupper(trim((string)($partido['grupo'] ?? ''))) !== $grupo) continue;
        }
    } else {
        if (intval($partido['jornada'] ?? -1) !== intval($jornada)) continue;
    }

    $data[$clavePartidos][$i]['goles_l'] = $golesLocal;
    $data[$clavePartidos][$i]['goles_v'] = $golesVisitante;
    $data[$clavePartidos][$i]['golesl'] = $golesLocal;
    $data[$clavePartidos][$i]['golesv'] = $golesVisitante;
    $data[$clavePartidos][$i]['estado'] = 'FINALIZADO';
    $data[$clavePartidos][$i]['detalles'] = $detalles;
    if ($clavePartidos === 'partidos_copa' && $grupo !== '') {
        $data[$clavePartidos][$i]['grupo'] = $grupo;
    }
    $data[$clavePartidos][$i]['goleadores_texto'] = $textoVisible;
    $data[$clavePartidos][$i]['goleadores_local_texto'] = implode(', ', array_map(fn($g) => trim($g['nombre']) . ' ' . $g['minuto'] . "'", $goleadoresLocal));
    $data[$clavePartidos][$i]['goleadores_visitante_texto'] = implode(', ', array_map(fn($g) => trim($g['nombre']) . ' ' . $g['minuto'] . "'", $goleadoresVisitante));
    $encontrado = true;
    break;
}

if (!$encontrado) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => 'No se encontró el partido',
        'busqueda' => [
            'competicion' => $competicion,
            'clave' => $clavePartidos,
            'jornada' => $jornada,
            'fase' => $fase,
            'grupo' => $grupo,
            'local' => $local,
            'visitante' => $visitante
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Recalcular clasificación de SUPERLIGA y ASCENSO tras guardar el resultado
recalcularClasificacionDivision($data, 'SUPERLIGA', 'partidos_liga');
recalcularClasificacionDivision($data, 'ASCENSO', 'partidos_ascenso');

// Recalcular clasificación de la fase de grupos de COPA (por grupo A/B/C/D...)
recalcularClasificacionGruposCopa($data);

// Recalcular estadísticas individuales de goles de todos los jugadores
recalcularEstadisticasJugadores($data);

// NOTA: sin JSON_UNESCAPED_SLASHES a propósito, para que las barras "/"
// del campo "detalles" queden guardadas escapadas como "\/" en el JSON.
$ok = file_put_contents(DATA_JSON, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
if ($ok === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el JSON'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'ok' => true,
    'mensaje' => 'Partido actualizado, clasificación y estadísticas recalculadas correctamente',
    'competicion' => $competicion,
    'bloque' => $clavePartidos,
    'local' => $local,
    'visitante' => $visitante,
    'goles_local' => $golesLocal,
    'goles_visitante' => $golesVisitante,
    'detalles_guardados' => $detalles,
    'goleadores_texto' => $textoVisible
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
