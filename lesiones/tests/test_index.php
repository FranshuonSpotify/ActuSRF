<?php
// lesiones/tests/test_index.php
// Ejecutar: /c/xampp/php/php.exe lesiones/tests/test_index.php

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../almacen.php';

$GLOBALS['LE_DIR_DATOS'] = leArnesDirDatos();
$GLOBALS['LE_RUTA_OFICIALES'] = __DIR__ . '/fixtures/datos_oficiales_prueba.json';

leGuardarLesiones([
    ['id' => 'l1', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador uno', 'jugador_nombre' => 'Jugador Uno',
     'partidos_totales' => 2, 'partidos_restantes' => 1, 'toda_temporada' => false, 'estado' => 'activa', 'fecha_inicio' => '2026-09-10', 'temporada' => 'Temporada 4'],
    ['id' => 'l2', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador dos', 'jugador_nombre' => 'Jugador Dos',
     'partidos_totales' => 1, 'partidos_restantes' => 0, 'toda_temporada' => false, 'estado' => 'recuperada', 'fecha_inicio' => '2026-09-05', 'temporada' => 'Temporada 4'],
    ['id' => 'l3', 'equipo_id' => 'eq_3', 'jugador_id' => 'eq_3#jugador fantasma', 'jugador_nombre' => 'Jugador Fantasma',
     'partidos_totales' => 5, 'partidos_restantes' => 5, 'toda_temporada' => false, 'estado' => 'activa', 'fecha_inicio' => '2026-09-01', 'temporada' => 'Temporada 4'],
    // Lesión activa de una temporada YA CERRADA: no debe verse en ninguna
    // vista de la temporada actual, aunque su estado siga siendo "activa".
    ['id' => 'l4', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador antiguo', 'jugador_nombre' => 'Jugador Antiguo',
     'partidos_totales' => 3, 'partidos_restantes' => 3, 'toda_temporada' => false, 'estado' => 'activa', 'fecha_inicio' => '2025-01-01', 'temporada' => 'Temporada 3'],
]);

// -- leLesionesActivasPorEquipo -----------------------------------------------
$activas = leLesionesActivasPorEquipo('eq_1');
leVerificar('solo una activa para Alpino', count($activas) === 1 && $activas[0]['jugador_id'] === 'eq_1#jugador uno');
leVerificar('la lesión de una temporada anterior no cuenta como activa', !in_array('eq_1#jugador antiguo', array_column($activas, 'jugador_id'), true));

// -- leHistorialPorEquipo -----------------------------------------------------
$historial = leHistorialPorEquipo('eq_1');
leVerificar('el historial de Alpino trae las dos lesiones de esta temporada', count($historial) === 2);
leVerificar('el historial va del más reciente al más antiguo', $historial[0]['jugador_id'] === 'eq_1#jugador uno');
leVerificar('la lesión de la temporada anterior no entra en el historial', !in_array('eq_1#jugador antiguo', array_column($historial, 'jugador_id'), true));

// -- leRankingGlobal: el equipo archivado (eq_3) y la temporada anterior no entran --
$ranking = leRankingGlobal();
leVerificar('el ranking ignora al equipo archivado y la temporada anterior', $ranking['total_lesiones'] === 2);
leVerificar('el equipo más golpeado es Alpino', $ranking['equipo_mas_golpeado']['equipo'] === 'Alpino');

// -- leFiltrarEquiposVista (pura) ---------------------------------------------
// eq_3 no aparece: leCargarEquiposOficiales() ya excluye los archivados.
$equiposVista = leCargarEquiposOficiales();
// Igual que hará index.php: solo las lesiones de la temporada actual, antes
// de pasarlas al filtro de vista.
$lesionesVista = array_values(array_filter(leCargarLesiones(),
    static fn(array $l): bool => (string) ($l['temporada'] ?? '') === 'Temporada 4'));

$vAscenso = leFiltrarEquiposVista($equiposVista, $lesionesVista, 'ASCENSO', '');
leVerificar('division ASCENSO deja solo a Zanark Domain (eq_2)', count($vAscenso) === 1 && $vAscenso[0]['equipo']['id'] === 'eq_2');

$vDivisionInvalida = leFiltrarEquiposVista($equiposVista, $lesionesVista, 'nada', '');
leVerificar('una division que no es SUPERLIGA/ASCENSO se ignora', count($vDivisionInvalida) === 2);

$vBuscaEquipo = leFiltrarEquiposVista($equiposVista, $lesionesVista, '', 'alpino');
leVerificar('buscar por nombre de equipo deja jugadores en null', count($vBuscaEquipo) === 1
    && $vBuscaEquipo[0]['equipo']['id'] === 'eq_1' && $vBuscaEquipo[0]['jugadores'] === null);

$vBuscaJugador = leFiltrarEquiposVista($equiposVista, $lesionesVista, '', 'JUGADOR uno');
leVerificar('buscar por nombre de jugador deja solo su jugador_id', count($vBuscaJugador) === 1
    && $vBuscaJugador[0]['equipo']['id'] === 'eq_1' && $vBuscaJugador[0]['jugadores'] === ['eq_1#jugador uno']);

$vSinCoincidencias = leFiltrarEquiposVista($equiposVista, $lesionesVista, '', 'zzz');
leVerificar('una busqueda sin coincidencias no deja ningun equipo', $vSinCoincidencias === []);

// -- pantalla pública ----------------------------------------------------------
$r = leArnesPeticion(__DIR__ . '/../index.php');
leVerificar('GET index.php devuelve html', $r['tipo'] === 'html');
leVerificar('muestra a Jugador Uno como activo', str_contains($r['html'], 'Jugador Uno'));
leVerificar('no muestra al equipo archivado', !str_contains($r['html'], 'Jugador Fantasma'));
leVerificar('no muestra la lesión de una temporada anterior', !str_contains($r['html'], 'Jugador Antiguo'));
leVerificar('usa los componentes del dashboard', str_contains($r['html'], '../dashboard/css/dashboard.css'));
leVerificar('el filtro tiene un botón de envío visible', str_contains($r['html'], '<button class="btn btn-secondary" type="submit">Filtrar</button>'));
leVerificar('el filtro no depende de JS (sin onchange)', !str_contains($r['html'], 'onchange='));
leVerificar('la temporada del fixture ya viene formateada tal cual', str_contains($r['html'], 'Temporada 4'));

// -- filtro por equipo -----------------------------------------------------
// Ojo: "Jugador Uno" no sirve de marcador aquí porque es el jugador_top del
// ranking global, que se ve siempre, filtrado o no. "Jugador Dos" en cambio
// solo aparece en el historial de Alpino, así que si el filtro funciona debe
// desaparecer al filtrar por otro equipo.
$rZanark = leArnesPeticion(__DIR__ . '/../index.php', [], ['equipo' => 'eq_2']);
leVerificar('filtrando por Zanark no aparece el historial de Alpino', !str_contains($rZanark['html'], 'Jugador Dos'));

$rInexistente = leArnesPeticion(__DIR__ . '/../index.php', [], ['equipo' => 'noexiste']);
leVerificar('un equipo inexistente en el filtro cae a mostrar todos (incluye el historial de Alpino)', str_contains($rInexistente['html'], 'Jugador Dos'));

// ?equipo[]=x llega como array: la página no debe avisar "Array to string
// conversion" (cualquier visitante puede provocarlo). Se convierte cualquier
// aviso en excepción solo durante esta petición para que no pase en silencio.
set_error_handler(static function (int $n, string $msg): bool { throw new ErrorException($msg, 0, $n); });
try {
    $rArray = leArnesPeticion(__DIR__ . '/../index.php', [], ['equipo' => ['x']]);
    $avisoArray = '';
} catch (ErrorException $e) {
    $rArray = ['tipo' => ''];
    $avisoArray = $e->getMessage();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
restore_error_handler();
leVerificar('equipo como array no lanza avisos: ' . $avisoArray, $avisoArray === '');
leVerificar('equipo como array devuelve html con todos los equipos', $rArray['tipo'] === 'html' && str_contains($rArray['html'], 'Jugador Dos'));

// -- filtro por division y buscador -----------------------------------------
// "Jugador Uno" no sirve de marcador para descartar la sección de Alpino: es
// el jugador_top del ranking global (ver comentario más arriba), que se
// muestra siempre, filtrado o no, porque el ranking se calcula ANTES de
// aplicar division/q. "Jugador Dos" en cambio solo vive en el historial de
// Alpino, así que es el marcador fiable de que esa sección desapareció.
$rAscensoDiv = leArnesPeticion(__DIR__ . '/../index.php', [], ['division' => 'ASCENSO']);
leVerificar('division=ASCENSO deja fuera el historial de Alpino', !str_contains($rAscensoDiv['html'], 'Jugador Dos'));

$rBuscaUno = leArnesPeticion(__DIR__ . '/../index.php', [], ['q' => 'jugador uno']);
leVerificar('q=jugador uno muestra a Jugador Uno', str_contains($rBuscaUno['html'], 'Jugador Uno'));
leVerificar('q=jugador uno no muestra a Jugador Dos', !str_contains($rBuscaUno['html'], 'Jugador Dos'));

$rZzz = leArnesPeticion(__DIR__ . '/../index.php', [], ['q' => 'zzz']);
leVerificar('q=zzz muestra el mensaje de sin coincidencias', str_contains($rZzz['html'], 'No hay equipos que coincidan'));

// ?q[]=x y ?division[]=x llegan como array: is_string() debe descartarlos sin
// avisar (mismo riesgo ya cubierto arriba para ?equipo[]=x).
set_error_handler(static function (int $n, string $msg): bool { throw new ErrorException($msg, 0, $n); });
try {
    $rQArray = leArnesPeticion(__DIR__ . '/../index.php', [], ['q' => ['x']]);
    $avisoQArray = '';
} catch (ErrorException $e) {
    $rQArray = ['tipo' => ''];
    $avisoQArray = $e->getMessage();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
restore_error_handler();
leVerificar('q como array no lanza avisos: ' . $avisoQArray, $avisoQArray === '');
leVerificar('q como array devuelve html sin filtrar', $rQArray['tipo'] === 'html' && str_contains($rQArray['html'], 'Jugador Dos'));

set_error_handler(static function (int $n, string $msg): bool { throw new ErrorException($msg, 0, $n); });
try {
    $rDivisionArray = leArnesPeticion(__DIR__ . '/../index.php', [], ['division' => ['x']]);
    $avisoDivisionArray = '';
} catch (ErrorException $e) {
    $rDivisionArray = ['tipo' => ''];
    $avisoDivisionArray = $e->getMessage();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
restore_error_handler();
leVerificar('division como array no lanza avisos: ' . $avisoDivisionArray, $avisoDivisionArray === '');
leVerificar('division como array devuelve html sin filtrar', $rDivisionArray['tipo'] === 'html' && str_contains($rDivisionArray['html'], 'Jugador Dos'));

// -- columna Fecha del historial ($r es el GET plano de más arriba) ----------
leVerificar('la tabla de historial tiene cabecera Fecha', str_contains($r['html'], '<th scope="col">Fecha</th>'));
leVerificar('la tabla de historial muestra la fecha formateada de Jugador Uno', str_contains($r['html'], '10/09/2026'));

// -- config.temporada como número suelto (así está en producción real) -------
// "4" a secas se leería como una cifra sin contexto; debe mostrarse como
// "Temporada 4", nunca como "· 4." en la entradilla.
$GLOBALS['LE_RUTA_OFICIALES'] = __DIR__ . '/fixtures/datos_oficiales_temporada_numero.json';
$rNumero = leArnesPeticion(__DIR__ . '/../index.php');
leVerificar('una temporada numérica se muestra como "Temporada 4"', str_contains($rNumero['html'], 'Temporada 4'));
leVerificar('una temporada numérica nunca se muestra como cifra suelta', !str_contains($rNumero['html'], '· 4.'));
$GLOBALS['LE_RUTA_OFICIALES'] = __DIR__ . '/fixtures/datos_oficiales_prueba.json';

leArnesLimpiar($GLOBALS['LE_DIR_DATOS']);
leSalirConResultado();
