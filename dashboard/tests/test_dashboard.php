<?php
// dashboard/tests/test_dashboard.php
// Self-check del paso 06: el dashboard del presidente.
//   /c/xampp/php/php.exe dashboard/tests/test_dashboard.php

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$INDEX = __DIR__ . '/../index.php';

// -- datos de partida ---------------------------------------------------
plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'nombre_en' => 'Alpha', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
    ['id' => 'eq_c', 'nombre' => 'Gamma', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
    ['id' => 'u_c', 'nombre' => 'Ana',  'email' => 'c@x.es', 'hash' => 'x', 'equipoId' => 'eq_c', 'activo' => true],
]]);
plCrearTemporada('2026-27', '2026/27');

// 18 jugadores cuyos salarios suman exactamente 225M:
// 75 + 60 + 40 + 5×6 + 10×2 = 225.
$jugador = static fn(string $id, string $nombre, string $tier, int $salario, int $clausula, string $estado = 'DISPONIBLE', ?string $por = null)
    => ['id' => $id, 'nombre' => $nombre, 'posicion' => 'MED', 'tier' => $tier, 'salario' => $salario,
        'clausula' => $clausula, 'estado' => $estado, 'clausuladoPor' => $por, 'clausuladoEn' => null];

$plantilla = [
    $jugador('j1', 'Estrella', 'S++', 75, 300),
    $jugador('j2', 'Crack', 'S+', 60, 200, 'CLAUSULADO', 'eq_b'),
    $jugador('j3', 'Titular', 'S', 40, 80),
];
for ($i = 4; $i <= 8; $i++)  { $plantilla[] = $jugador("j$i", "Medio $i", 'B', 6, 0); }
for ($i = 9; $i <= 18; $i++) { $plantilla[] = $jugador("j$i", "Suplente $i", 'C', 2, 0); }

plGuardarEquipoTemporada('2026-27', 'eq_a', ['jugadores' => $plantilla], 0);
plGuardarEquipoTemporada('2026-27', 'eq_b', ['jugadores' => [$jugador('b1', 'Intruso', 'S', 40, 650)]], 0);

$ver = static fn(string $usuarioId) => plArnesPeticion($INDEX, ['pl_usuario_id' => $usuarioId]);

// -- tarjetas -----------------------------------------------------------
$r = $ver('u_a');
$html = $r['html'] ?? '';
plVerificar('el dashboard se renderiza como HTML', $r['tipo'] === 'html');
plVerificar('con el nombre del equipo en el h1', str_contains($html, '<h1>Alfa</h1>'));
plVerificar('y el nombre del presidente', str_contains($html, 'Presidente: Juan'));
plVerificar('18 jugadores se muestran como 18 / 20', str_contains($html, '18 / 20'));
plVerificar('los salarios suman 225M sobre 250M', str_contains($html, '225M / 250M'));
plVerificar('y quedan 25M disponibles, calculados', str_contains($html, '25M disponibles'));
plVerificar('las cláusulas suman 580M sobre 650M', str_contains($html, '580M / 650M'));
plVerificar('con los 70M que faltan por repartir', str_contains($html, '70M disponibles'));

// -- mercado derivado de la fase ----------------------------------------
foreach (['ROSTER', 'CLAUSULAS', 'CERRADA'] as $f) {
    plCambiarFase('2026-27', $f);
    $h = $ver('u_a')['html'] ?? '';
    plVerificar("en $f el mercado aparece CERRADO", str_contains($h, 'CERRADO') && !str_contains($h, '>ABIERTO<'));
}
plCambiarFase('2026-27', 'MERCADO');
$h = $ver('u_a')['html'] ?? '';
plVerificar('en MERCADO aparece ABIERTO', str_contains($h, 'ABIERTO'));
plVerificar('y el badge de fase dice MERCADO', str_contains($h, 'fase-mercado'));

// -- tabla y estados ----------------------------------------------------
plVerificar('la tabla lista a sus jugadores', str_contains($h, 'Estrella') && str_contains($h, 'Suplente 18'));
plVerificar('un jugador clausulado dice «Clausulado por Beta», en texto',
    str_contains($h, 'Clausulado por Beta'));
plVerificar('los demás dicen Disponible', str_contains($h, 'Disponible'));
plVerificar('la tabla va dentro de un contenedor con scroll propio', str_contains($h, 'tabla-scroll'));
plVerificar('la posición lleva su chip', str_contains($h, 'chip chip-med'));

// -- aislamiento entre equipos ------------------------------------------
plVerificar('NO aparece ningún jugador de otro equipo', !str_contains($h, 'Intruso'));

// -- presupuesto completo -----------------------------------------------
$completo = $plantilla;
$completo[2]['clausula'] = 150;   // 300 + 200 + 150 = 650
plGuardarEquipoTemporada('2026-27', 'eq_a', ['jugadores' => $completo], 1);
$h = $ver('u_a')['html'] ?? '';
plVerificar('con 650M exactos se lee «Presupuesto completo»', str_contains($h, 'Presupuesto completo'));

// -- estado vacío con la acción de la fase -------------------------------
plCambiarFase('2026-27', 'ROSTER');
$h = $ver('u_c')['html'] ?? '';
plVerificar('un equipo sin jugadores en ROSTER ve el estado vacío', str_contains($h, 'no has inscrito a ningún jugador'));
plVerificar('con el enlace para inscribir', str_contains($h, 'href="plantilla.php"') && str_contains($h, 'Inscribir jugadores'));
plVerificar('y la tarjeta dice 0 / 20, no un número negativo', str_contains($h, '0 / 20'));

plCambiarFase('2026-27', 'MERCADO');
$h = $ver('u_c')['html'] ?? '';
plVerificar('fuera de ROSTER, el estado vacío no ofrece inscribir',
    str_contains($h, 'no inscribió jugadores') && !str_contains($h, 'Inscribir jugadores'));

// -- equipo fuera de la temporada ---------------------------------------
plGuardarEquipos(['equipos' => array_merge(plCargarEquipos()['equipos'],
    [['id' => 'eq_nuevo', 'nombre' => 'Recien Llegado', 'activo' => true]])]);
$usuarios = plCargarUsuarios();
$usuarios['usuarios'][] = ['id' => 'u_n', 'nombre' => 'Nuevo', 'email' => 'n@x.es', 'hash' => 'x', 'equipoId' => 'eq_nuevo', 'activo' => true];
plGuardarUsuarios($usuarios);
$h = $ver('u_n')['html'] ?? '';
plVerificar('un equipo creado después de la temporada ve un aviso, no un error',
    str_contains($h, 'no forma parte de la temporada en curso') && str_contains($h, '</html>'));

// -- en inglés, nombre_en ----------------------------------------------
$r = plArnesPeticion($INDEX, ['pl_usuario_id' => 'u_a', 'pl_lang' => 'en']);
plVerificar('en inglés el equipo se llama por su nombre_en', str_contains($r['html'] ?? '', '<h1>Alpha</h1>'));
plEstablecerIdioma('es');

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
