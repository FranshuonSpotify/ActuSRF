<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('DATOS_OFICIALES_PATHS', [ROOT_PATH . '/datos_oficiales.json', dirname(ROOT_PATH) . '/datos_oficiales.json']);
define('HISTORIAL_FILE', DATA_PATH . '/lesiones_historial.json');
define('ESTADO_EQUIPOS_FILE', DATA_PATH . '/estado_equipos.json');
define('ESTADO_SEMANAL_FILE', DATA_PATH . '/estado_semanal.json');
define('TOTAL_PARTIDOS_TEMPORADA', 38);

define('CODIGOS_PRINCIPAL', ['no_evento', 'evento_inesperado']);
define('CODIGOS_SECUNDARIO', ['1j_1p', '2j_1p', '1j_2p', '2j_2p', '1j_3p', '2j_3p', '1j_temp']);

function asegurarDirectorio(string $file): void {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function leerJSON(string $file, $default = []) {
    if (!file_exists($file)) return $default;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return $default;
    $data = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $default;
}

function escribirJSON(string $file, $data): bool {
    asegurarDirectorio($file);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return @file_put_contents($file, $json, LOCK_EX) !== false;
}

function getSemanaISO(): string { return (new DateTimeImmutable('now'))->format('o-\WW'); }

function normalizarDatosOficiales(array $raw): array {
    $source = null;
    foreach (['equipos', 'teams', 'clubes'] as $k) {
        if (isset($raw[$k]) && is_array($raw[$k])) { $source = $raw[$k]; break; }
    }
    if ($source === null) return [];
    $equipos = [];
    foreach ($source as $eq) {
        if (!empty($eq['archivado'])) continue;
        $id = (string)($eq['id'] ?? $eq['_id'] ?? uniqid('eq_', true));
        $team = [
            'id' => $id,
            'nombre' => (string)($eq['nombre'] ?? $eq['name'] ?? 'Equipo'),
            'escudo' => (string)($eq['escudo'] ?? $eq['badge'] ?? $eq['logo'] ?? $eq['imagen'] ?? ''),
            'division' => (string)($eq['division'] ?? $eq['liga'] ?? ''),
            'jugadores' => []
        ];
        $jk = null;
        foreach (['jugadores', 'players', 'plantilla'] as $k) {
            if (isset($eq[$k]) && is_array($eq[$k])) { $jk = $k; break; }
        }
        if ($jk !== null) {
            foreach ($eq[$jk] as $j) {
                $team['jugadores'][] = [
                    'id' => (string)($j['id'] ?? $j['_id'] ?? ($id . '_' . ($j['dorsal'] ?? uniqid()))),
                    'nombre' => (string)($j['nombre'] ?? $j['name'] ?? 'Jugador'),
                    'dorsal' => (string)($j['dorsal'] ?? $j['number'] ?? '0'),
                    'foto' => (string)($j['foto'] ?? $j['photo'] ?? $j['imagen'] ?? ''),
                    'posicion' => (string)($j['posicion'] ?? $j['position'] ?? '')
                ];
            }
        }
        $equipos[] = $team;
    }
    return $equipos;
}

function cargarEquipos(): array {
    foreach (DATOS_OFICIALES_PATHS as $path) {
        if (file_exists($path)) return normalizarDatosOficiales(leerJSON($path, []));
    }
    return [];
}

function mapaEquiposActivos(): array {
    $map = [];
    foreach (cargarEquipos() as $eq) $map[(string)$eq['id']] = true;
    return $map;
}

function buscarEquipoPorId(array $equipos, string $id): ?array {
    foreach ($equipos as $eq) if ((string)($eq['id'] ?? '') === (string)$id) return $eq;
    return null;
}

function descripcionResultado(string $codigo): string {
    return [
        '1j_1p' => '1 jugador, 1 partido',
        '2j_1p' => '2 jugadores, 1 partido',
        '1j_2p' => '1 jugador, 2 partidos',
        '2j_2p' => '2 jugadores, 2 partidos',
        '1j_3p' => '1 jugador, 3 partidos',
        '2j_3p' => '2 jugadores, 3 partidos',
        '1j_temp' => '1 jugador, toda la temporada'
    ][$codigo] ?? $codigo;
}

function parsearResultado(string $codigo): array {
    $jugadores = str_starts_with($codigo, '2j') ? 2 : 1;
    if (str_ends_with($codigo, '_temp')) return ['jugadores' => $jugadores, 'partidos' => -1, 'toda_temporada' => true];
    preg_match('/(\d+)p$/', $codigo, $m);
    return ['jugadores' => $jugadores, 'partidos' => (int)($m[1] ?? 1), 'toda_temporada' => false];
}

function cargarEstadoSemanal(): array {
    $raw = leerJSON(ESTADO_SEMANAL_FILE, ['semana' => '', 'equipos_procesados' => [], 'resultados' => []]);
    return [
        'semana' => (string)($raw['semana'] ?? ''),
        'equipos_procesados' => array_values(is_array($raw['equipos_procesados'] ?? null) ? $raw['equipos_procesados'] : []),
        'resultados' => is_array($raw['resultados'] ?? null) ? $raw['resultados'] : []
    ];
}

function guardarEstadoSemanal(array $estado): bool { return escribirJSON(ESTADO_SEMANAL_FILE, $estado); }

function cargarHistorial(): array {
    $h = leerJSON(HISTORIAL_FILE, []);
    return is_array($h) ? $h : [];
}

function guardarHistorial(array $historial): bool { return escribirJSON(HISTORIAL_FILE, array_values($historial)); }

function historialFiltradoActivos(array $historial): array {
    $activos = mapaEquiposActivos();
    return array_values(array_filter($historial, fn($l) => isset($activos[(string)($l['equipo_id'] ?? '')])));
}

function equipoProcesado(string $equipoId, string $semana): bool {
    $e = cargarEstadoSemanal();
    return $e['semana'] === $semana && in_array($equipoId, $e['equipos_procesados'], true);
}

function marcarEquipoProcesado(string $equipoId, string $semana, array $resultado): void {
    $estado = cargarEstadoSemanal();
    if ($estado['semana'] !== $semana) $estado = ['semana' => $semana, 'equipos_procesados' => [], 'resultados' => []];
    if (!in_array($equipoId, $estado['equipos_procesados'], true)) $estado['equipos_procesados'][] = $equipoId;
    $estado['resultados'][$equipoId] = $resultado;
    guardarEstadoSemanal($estado);
}

function obtenerEstadoEquipos(): array {
    $e = leerJSON(ESTADO_EQUIPOS_FILE, []);
    return is_array($e) ? $e : [];
}

function guardarEstadoEquipos(array $estado): bool { return escribirJSON(ESTADO_EQUIPOS_FILE, $estado); }

function obtenerPartidosJugados(string $equipoId): int {
    $e = obtenerEstadoEquipos();
    return (int)($e[$equipoId]['partidos_jugados'] ?? 0);
}

function registrarPartidosJugados(string $equipoId, int $n): void {
    $e = obtenerEstadoEquipos();
    if (!isset($e[$equipoId]) || !is_array($e[$equipoId])) $e[$equipoId] = ['partidos_jugados' => 0];
    $e[$equipoId]['partidos_jugados'] = max(0, (int)($e[$equipoId]['partidos_jugados'] ?? 0) + $n);
    guardarEstadoEquipos($e);
}

function jugadoresLesionadosActivos(string $equipoId): array {
    return array_values(array_unique(array_column(array_filter(cargarHistorial(), fn($l) => (($l['equipo_id'] ?? '') === $equipoId) && (($l['estado'] ?? '') === 'activa')), 'jugador_id')));
}

function seleccionarJugadores(array $equipo, int $cantidad): array {
    $jugadores = is_array($equipo['jugadores'] ?? null) ? $equipo['jugadores'] : [];
    if (!$jugadores) return [];
    $ocupados = jugadoresLesionadosActivos((string)$equipo['id']);
    $sanos = array_values(array_filter($jugadores, fn($j) => !in_array((string)($j['id'] ?? ''), $ocupados, true)));
    $pool = count($sanos) >= $cantidad ? $sanos : $jugadores;
    shuffle($pool);
    $seleccion = [];
    foreach ($pool as $j) {
        if (count($seleccion) >= $cantidad) break;
        $idsSel = array_column($seleccion, 'id');
        if (!in_array((string)$j['id'], $idsSel, true)) $seleccion[] = $j;
    }
    return $seleccion;
}

function guardarLesion(array $lesion): void {
    $h = cargarHistorial();
    $h[] = $lesion;
    guardarHistorial($h);
}

function commitResultado(array $equipo, string $semana, string $codigoPrincipal, ?string $codigoSecundario, bool $forzar = false): array {
    if (!$forzar && equipoProcesado((string)$equipo['id'], $semana)) return ['success' => false, 'error' => 'ya_procesado', 'equipo_id' => $equipo['id']];
    if (!in_array($codigoPrincipal, CODIGOS_PRINCIPAL, true)) return ['success' => false, 'error' => 'codigo_principal_invalido'];
    if ($codigoPrincipal === 'evento_inesperado' && !in_array((string)$codigoSecundario, CODIGOS_SECUNDARIO, true)) return ['success' => false, 'error' => 'codigo_secundario_invalido'];

    $resultado = [
        'equipo_id' => $equipo['id'],
        'equipo_nombre' => $equipo['nombre'],
        'escudo' => $equipo['escudo'] ?? '',
        'semana' => $semana,
        'resultado_principal' => $codigoPrincipal,
        'descripcion_resultado' => null,
        'lesiones' => [],
        'timestamp' => date('c')
    ];

    if ($codigoPrincipal === 'evento_inesperado') {
        $parsed = parsearResultado((string)$codigoSecundario);
        $resultado['codigo_secundario'] = $codigoSecundario;
        $resultado['descripcion_resultado'] = descripcionResultado((string)$codigoSecundario);
        foreach (seleccionarJugadores($equipo, (int)$parsed['jugadores']) as $jug) {
            $pts = $parsed['toda_temporada'] ? max(0, TOTAL_PARTIDOS_TEMPORADA - obtenerPartidosJugados((string)$equipo['id'])) : (int)$parsed['partidos'];
            $lesion = [
                'id' => 'les_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)),
                'fecha' => date('c'),
                'anio_semana' => $semana,
                'equipo_id' => $equipo['id'],
                'equipo_nombre' => $equipo['nombre'],
                'jugador_id' => $jug['id'],
                'jugador_nombre' => $jug['nombre'],
                'dorsal' => $jug['dorsal'],
                'foto' => $jug['foto'] ?? '',
                'tipo_evento' => 'lesion',
                'descripcion_resultado' => descripcionResultado((string)$codigoSecundario),
                'partidos_totales' => $pts,
                'partidos_restantes' => $pts,
                'estado' => $pts > 0 ? 'activa' : 'recuperado',
                'origen' => 'ruleta_semanal',
                'temporada' => '2025/2026',
                'fecha_recuperacion' => $pts > 0 ? null : date('c')
            ];
            guardarLesion($lesion);
            $resultado['lesiones'][] = $lesion;
        }
    }

    marcarEquipoProcesado((string)$equipo['id'], $semana, $resultado);
    return array_merge(['success' => true], $resultado);
}

function registrarPartidosEquipo(string $equipoId, int $n): array {
    registrarPartidosJugados($equipoId, $n);
    $historial = cargarHistorial();
    $actualizadas = [];
    foreach ($historial as &$l) {
        if ((string)($l['equipo_id'] ?? '') === $equipoId && (string)($l['estado'] ?? '') === 'activa') {
            $l['partidos_restantes'] = max(0, (int)($l['partidos_restantes'] ?? 0) - $n);
            if ((int)$l['partidos_restantes'] <= 0) {
                $l['estado'] = 'recuperado';
                $l['fecha_recuperacion'] = date('c');
            }
            $actualizadas[] = $l;
        }
    }
    unset($l);
    guardarHistorial($historial);
    return $actualizadas;
}

function registrarPartidosTodos(int $n): array {
    $out = [];
    foreach (cargarEquipos() as $eq) {
        $out[] = [
            'equipo_id' => $eq['id'],
            'equipo_nombre' => $eq['nombre'],
            'actualizadas' => registrarPartidosEquipo((string)$eq['id'], $n)
        ];
    }
    return $out;
}

function borrarTodosLosDatos(): void {
    guardarHistorial([]);
    guardarEstadoEquipos([]);
    guardarEstadoSemanal(['semana' => '', 'equipos_procesados' => [], 'resultados' => []]);
}

function borrarSemanaConcreta(string $semana): array {
    $historial = cargarHistorial();
    $nuevo = array_values(array_filter($historial, fn($l) => (string)($l['anio_semana'] ?? '') !== $semana));
    $eliminadas = count($historial) - count($nuevo);
    guardarHistorial($nuevo);
    $estado = cargarEstadoSemanal();
    if (($estado['semana'] ?? '') === $semana) guardarEstadoSemanal(['semana' => '', 'equipos_procesados' => [], 'resultados' => []]);
    return ['semana' => $semana, 'lesiones_eliminadas' => $eliminadas];
}
