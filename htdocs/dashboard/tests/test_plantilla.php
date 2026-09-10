<?php
// dashboard/tests/test_plantilla.php
// Self-check del paso 07: Mi plantilla. Recorre el camino del POST entero con
// plArnesPeticion(): CSRF, fase, cap, límite de 20, salario manipulado y rev.
//   /c/xampp/php/php.exe dashboard/tests/test_plantilla.php

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$PANTALLA = __DIR__ . '/../plantilla.php';
$T        = '2026-27';
$SESION   = ['pl_usuario_id' => 'u_a', 'pl_csrf' => 'tok'];

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');

$post = static fn(array $datos) => plArnesPeticion($PANTALLA, $GLOBALS['SESION'], [], $datos + ['csrf' => 'tok']);
$enA  = static fn() => plEquipoEnTemporada('2026-27', 'eq_a');

// -- sin sesión ---------------------------------------------------------
$r = plArnesPeticion($PANTALLA, []);
plVerificar('sin sesión se redirige al login', $r['tipo'] === 'redirigir' && $r['url'] === 'index.php');

// -- GET en ROSTER -----------------------------------------------------
$r = plArnesPeticion($PANTALLA, $SESION);
$h = $r['html'] ?? '';
plVerificar('en ROSTER se pinta el formulario de alta', str_contains($h, 'name="accion" value="anadir"'));
plVerificar('con el rev del equipo en un campo oculto', str_contains($h, 'name="rev" value="0"'));
plVerificar('y el token CSRF', str_contains($h, 'name="csrf" value="tok"'));
plVerificar('el salario NO es un campo editable', !preg_match('/name=["\']salario["\']/', $h));
plVerificar('el desplegable de tier enseña el salario de cada tier', str_contains($h, 'S+ · 60M'));
plVerificar('las cuatro posiciones, ATA incluida', str_contains($h, 'value="ATA"') && !str_contains($h, 'value="DEL"'));
plVerificar('un solo h1', substr_count($h, '<h1') === 1);

// -- CSRF --------------------------------------------------------------
$r = plArnesPeticion($PANTALLA, $SESION, [], ['accion' => 'anadir', 'rev' => 0, 'nombre' => 'X', 'posicion' => 'MED', 'tier' => 'C']);
plVerificar('un POST sin token CSRF se corta con 403', $r['tipo'] === 'cortar' && $r['codigo'] === 403);
plVerificar('y no escribe nada', $enA()['jugadores'] === []);

// -- alta válida, con salario manipulado --------------------------------
$r = $post(['accion' => 'anadir', 'rev' => 0, 'nombre' => '  Endou  ', 'posicion' => 'POR', 'tier' => 'S+', 'salario' => 1]);
plVerificar('un alta válida redirige a la plantilla (POST/Redirect/GET)', $r['tipo'] === 'redirigir' && $r['url'] === 'plantilla.php');
plVerificar('y deja un mensaje de confirmación', ($r['sesion']['pl_flash'] ?? '') === plT('plantilla.guardado'));
$e = $enA();
plVerificar('el jugador queda guardado', count($e['jugadores']) === 1);
plVerificar('con el nombre recortado', $e['jugadores'][0]['nombre'] === 'Endou');
plVerificar('el salario manipulado (1M) se IGNORA: sale del tier, 60M', $e['jugadores'][0]['salario'] === 60);
plVerificar('nace Disponible y sin cláusula',
    $e['jugadores'][0]['estado'] === 'DISPONIBLE' && $e['jugadores'][0]['clausula'] === 0);
plVerificar('y el rev pasa a 1', $e['rev'] === 1);

// -- rev desfasado ------------------------------------------------------
// Un copresidente con la pantalla abierta desde antes (rev 0) intenta añadir.
$r = $post(['accion' => 'anadir', 'rev' => 0, 'nombre' => 'Tarde', 'posicion' => 'MED', 'tier' => 'C']);
plVerificar('un rev desfasado NO redirige', $r['tipo'] === 'html');
plVerificar('avisa de que el copresidente ha guardado', str_contains($r['html'], 'copresidente ha guardado'));
plVerificar('y no escribe nada', count($enA()['jugadores']) === 1);

// -- Salary Cap ----------------------------------------------------------
// Endou (60) + tres S++ (225) = 285: el tercer S++ ya no cabe.
$rev = $enA()['rev'];
foreach (['A', 'B'] as $n) {
    $r = $post(['accion' => 'anadir', 'rev' => $rev, 'nombre' => "Crack $n", 'posicion' => 'ATA', 'tier' => 'S++']);
    $rev = $enA()['rev'];
}
plVerificar('dos S++ más caben: 60 + 75 + 75 = 210', plTotalSalarios($enA()['jugadores']) === 210);

$r = $post(['accion' => 'anadir', 'rev' => $rev, 'nombre' => 'Crack C', 'posicion' => 'ATA', 'tier' => 'S++']);
$h = $r['html'] ?? '';
plVerificar('el tercero se rechaza por Salary Cap', $r['tipo'] === 'html' && str_contains($h, 'Salary Cap es de 250M'));
plVerificar('diciendo el total que quedaría', str_contains($h, '285M'));
plVerificar('y lo que queda libre', str_contains($h, '40M disponibles'));
plVerificar('sin escribir nada', count($enA()['jugadores']) === 3);
plVerificar('y devolviendo lo tecleado al formulario', str_contains($h, 'value="Crack C"'));

// -- cambio de tier ------------------------------------------------------
$e    = $enA();
$idA  = $e['jugadores'][1]['id'];   // Crack A, S++ (75)
$r = $post(['accion' => 'editar', 'rev' => $e['rev'], 'id' => $idA, 'nombre' => 'Crack A', 'posicion' => 'ATA', 'tier' => 'C']);
plVerificar('bajar de S++ a C se acepta', $r['tipo'] === 'redirigir');
plVerificar('y el salario se recalcula a 2M', $enA()['jugadores'][1]['salario'] === 2);

// 60 + 2 + 75 = 137. Subir Endou de S+ (60) a S++ (75) -> 152: cabe.
// Subir Crack A de C (2) a S++ -> 210, y otro jugador... se prueba el rechazo
// llenando antes el cap con un alta que lo deje justo.
$e = $enA();
$r = $post(['accion' => 'anadir', 'rev' => $e['rev'], 'nombre' => 'Relleno', 'posicion' => 'DEF', 'tier' => 'S++']);
// 137 + 75 = 212. Ahora Crack A (2) -> S++ (75) dejaría 285.
$e = $enA();
$r = $post(['accion' => 'editar', 'rev' => $e['rev'], 'id' => $idA, 'nombre' => 'Crack A', 'posicion' => 'ATA', 'tier' => 'S++']);
plVerificar('subir de tier por encima del cap se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'No puedes cambiar a este tier'));
plVerificar('y el tier no cambia', $enA()['jugadores'][1]['tier'] === 'C');

// -- editar un jugador de otro equipo -------------------------------------
plGuardarEquipoTemporada($T, 'eq_b', ['jugadores' => [['id' => 'b1', 'nombre' => 'Ajeno', 'posicion' => 'MED',
    'tier' => 'C', 'salario' => 2, 'clausula' => 0, 'estado' => 'DISPONIBLE']]], 0);
$e = $enA();
$r = $post(['accion' => 'editar', 'rev' => $e['rev'], 'id' => 'b1', 'nombre' => 'Robado', 'posicion' => 'MED', 'tier' => 'C']);
plVerificar('editar un jugador de OTRO equipo no es posible', $r['tipo'] === 'html');
plVerificar('y el jugador ajeno sigue intacto',
    plEquipoEnTemporada($T, 'eq_b')['jugadores'][0]['nombre'] === 'Ajeno');

// -- borrar -------------------------------------------------------------
$e = $enA();
$n = count($e['jugadores']);
$r = $post(['accion' => 'borrar', 'rev' => $e['rev'], 'id' => $idA]);
plVerificar('borrar un jugador funciona', $r['tipo'] === 'redirigir' && count($enA()['jugadores']) === $n - 1);

// -- límite de 20 -------------------------------------------------------
$e = $enA();
$lista = $e['jugadores'];
while (count($lista) < 20) {
    $lista[] = ['id' => 'x' . count($lista), 'nombre' => 'Relleno ' . count($lista), 'posicion' => 'MED',
        'tier' => 'C', 'salario' => 2, 'clausula' => 0, 'estado' => 'DISPONIBLE'];
}
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => $lista], $e['rev']);
$e = $enA();
$r = $post(['accion' => 'anadir', 'rev' => $e['rev'], 'nombre' => 'El 21', 'posicion' => 'MED', 'tier' => 'C']);
plVerificar('el jugador 21 se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'el máximo es de 20'));
plVerificar('y la plantilla sigue en 20', count($enA()['jugadores']) === 20);
$h = plArnesPeticion($PANTALLA, $SESION)['html'] ?? '';
plVerificar('con 20 jugadores ya no se ofrece el formulario de alta', !str_contains($h, 'value="anadir"'));

// -- fuera de ROSTER ----------------------------------------------------
plCambiarFase($T, 'CLAUSULAS');
$h = plArnesPeticion($PANTALLA, $SESION)['html'] ?? '';
plVerificar('en CLAUSULAS la pantalla avisa de que la inscripción terminó',
    str_contains($h, 'La fase de inscripción de plantillas ya ha finalizado'));
plVerificar('y no hay ningún formulario de edición', !str_contains($h, 'name="accion"'));
plVerificar('pero la plantilla se sigue viendo', str_contains($h, 'Endou'));

$e = $enA();
$r = $post(['accion' => 'borrar', 'rev' => $e['rev'], 'id' => $e['jugadores'][0]['id']]);
plVerificar('un POST reenviado a mano fuera de ROSTER se rechaza', $r['tipo'] === 'html');
plVerificar('y no borra a nadie', count($enA()['jugadores']) === 20);

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
