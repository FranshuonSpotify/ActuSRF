<?php
session_start();
require_once __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$equipoId = $_SESSION['st_equipo_id'] ?? null;
if ($equipoId === null) {
    header('Location: index.php');
    exit;
}

if (!stCsrfValido()) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$config = stCargarConfig();
if (empty($config['ventana_abierta'])) {
    http_response_code(403);
    exit('La ventana de supertécnicas está cerrada.');
}

$data = stCargarDatosOficiales();
$idx = stBuscarEquipoPorId($data, $equipoId);
if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
    unset($_SESSION['st_equipo_id']);
    header('Location: index.php');
    exit;
}

$entradas = $_POST['jugadores'] ?? [];
if (!is_array($entradas)) $entradas = [];

foreach ($entradas as $i => $entrada) {
    $i = (int) $i;
    if (!isset($data['equipos'][$idx]['jugadores'][$i])) continue;
    if (!is_array($entrada)) continue;

    $nombreEsperado = (string) ($entrada['nombre_check'] ?? '');
    $nombreReal = (string) ($data['equipos'][$idx]['jugadores'][$i]['nombre'] ?? '');
    if ($nombreReal === '' || $nombreReal !== $nombreEsperado) continue;

    $bloques = $entrada['st'] ?? [];
    if (!is_array($bloques)) $bloques = [];
    ksort($bloques);

    $nuevasSupertecnicas = [];
    foreach ($bloques as $bloque) {
        if (count($nuevasSupertecnicas) >= ST_MAX_SUPERTECNICAS) break;
        if (!is_array($bloque)) continue;

        $nombre = trim(mb_substr((string) ($bloque['nombre'] ?? ''), 0, 40));
        $especial = trim(mb_substr((string) ($bloque['especial'] ?? ''), 0, 40));
        $descripcion = trim(mb_substr((string) ($bloque['descripcion'] ?? ''), 0, 300));
        $tipo = in_array($bloque['tipo'] ?? '', ST_TIPOS, true) ? $bloque['tipo'] : '';
        $afinidad = in_array($bloque['afinidad'] ?? '', ST_AFINIDADES, true) ? $bloque['afinidad'] : '';

        if ($nombre === '' && $especial === '' && $descripcion === '' && $tipo === '' && $afinidad === '') {
            continue;
        }

        $nuevasSupertecnicas[] = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'afinidad' => $afinidad,
            'especial' => $especial,
            'descripcion' => $descripcion,
        ];
    }

    $data['equipos'][$idx]['jugadores'][$i]['supertecnicas'] = $nuevasSupertecnicas;
}

if (!stGuardarDatosOficiales($data)) {
    http_response_code(500);
    exit('No se pudo guardar: error al escribir el fichero.');
}

header('Location: index.php?guardado=1');
exit;
