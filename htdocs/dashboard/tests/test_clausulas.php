<?php
// dashboard/tests/test_clausulas.php
// Self-check del paso 09: reparto de los 650M de cláusulas.
//   /c/xampp/php/php.exe dashboard/tests/test_clausulas.php

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$PANTALLA = __DIR__ . '/../clausulas.php';
$T  = '2026-27';
$SA = ['pl_usuario_id' => 'u_a', 'pl_csrf' => 'tok'];

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');

$j = static fn(string $id, string $nombre, int $salario) => ['id' => $id, 'nombre' => $nombre, 'posicion' => 'MED',
    'tier' => 'S', 'salario' => $salario, 'clausula' => 0, 'estado' => 'DISPONIBLE', 'clausuladoPor' => null];
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => [$j('j1', 'Endou', 75), $j('j2', 'Goenji', 60), $j('j3', 'Kidou', 40)]], 0);
plGuardarEquipoTemporada($T, 'eq_b', ['jugadores' => [$j('b1', 'Ajeno', 40)]], 0);
plCambiarFase($T, 'CLAUSULAS');

$enA = static fn() => plEquipoEnTemporada('2026-27', 'eq_a');
$clausulasA = static fn() => array_column(plEquipoEnTemporada('2026-27', 'eq_a')['jugadores'], 'clausula', 'id');
$post = static fn(array $c, int $rev) => plArnesPeticion($GLOBALS['PANTALLA'], $GLOBALS['SA'], [],
    ['csrf' => 'tok', 'rev' => $rev, 'clausula' => $c]);

// -- GET en CLAUSULAS ---------------------------------------------------
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('se pinta un campo de cláusula por jugador',
    str_contains($h, 'name="clausula[j1]"') && str_contains($h, 'name="clausula[j3]"'));
plVerificar('los campos son numéricos, enteros y sin negativos',
    str_contains($h, 'type="number" min="0" step="1"'));
plVerificar('con el presupuesto total de 650M a la vista', str_contains($h, '650M'));
plVerificar('y el rev del equipo en un campo oculto', str_contains($h, 'name="rev" value="1"'));
plVerificar('cada campo tiene su etiqueta para lectores de pantalla', str_contains($h, 'Cláusula de Endou'));
plVerificar('el estado se anuncia en una región aria-live', str_contains($h, 'aria-live="polite"'));
plVerificar('la pantalla revalida en el servidor con plValidarClausulas',
    str_contains((string) file_get_contents($PANTALLA), 'plValidarClausulas'));

// -- CSRF --------------------------------------------------------------
$r = plArnesPeticion($PANTALLA, $SA, [], ['rev' => 1, 'clausula' => ['j1' => 650]]);
plVerificar('un POST sin token CSRF se corta con 403', $r['tipo'] === 'cortar' && $r['codigo'] === 403);
plVerificar('y no escribe nada', $clausulasA() === ['j1' => 0, 'j2' => 0, 'j3' => 0]);

// -- superar 650M -------------------------------------------------------
$r = $post(['j1' => 300, 'j2' => 200, 'j3' => 151], 1);
plVerificar('651M se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'No puedes superar los 650M'));
plVerificar('sin escribir nada', $clausulasA() === ['j1' => 0, 'j2' => 0, 'j3' => 0]);
plVerificar('y devolviendo lo tecleado a los campos', str_contains($r['html'], 'value="151"'));

// -- borrador por debajo de 650M ---------------------------------------
$r = $post(['j1' => 300, 'j2' => 200, 'j3' => 80], 1);
plVerificar('580M se GUARDA como borrador', $r['tipo'] === 'redirigir' && $r['url'] === 'clausulas.php');
plVerificar('avisando de lo que falta', str_contains($r['sesion']['pl_flash'] ?? '', '70M'));
plVerificar('las cláusulas quedan escritas', $clausulasA() === ['j1' => 300, 'j2' => 200, 'j3' => 80]);
plVerificar('sin tocar los salarios', array_column($enA()['jugadores'], 'salario') === [75, 60, 40]);

$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('la pantalla dice cuánto falta, en texto', str_contains($h, 'faltan 70M'));
plVerificar('y la barra va en estado parcial', str_contains($h, 'barra-parcial'));

// -- exactamente 650M ---------------------------------------------------
$r = $post(['j1' => 300, 'j2' => 200, 'j3' => 150], 2);
plVerificar('650M exactos se guardan', $r['tipo'] === 'redirigir');
plVerificar('con el aviso de presupuesto completo', str_contains($r['sesion']['pl_flash'] ?? '', 'Presupuesto completo'));
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('la pantalla muestra «Presupuesto completo»', str_contains($h, 'Presupuesto completo'));
plVerificar('y la barra en estado completo', str_contains($h, 'barra-completa'));

// -- cero, vacío, negativo y decimal -----------------------------------
$r = $post(['j1' => 650, 'j2' => 0, 'j3' => ''], 3);
plVerificar('una cláusula 0 y un campo vaciado se aceptan (el vacío cuenta como 0)',
    $r['tipo'] === 'redirigir' && $clausulasA() === ['j1' => 650, 'j2' => 0, 'j3' => 0]);

$r = $post(['j1' => 660, 'j2' => -10, 'j3' => 0], 4);
plVerificar('una cláusula negativa se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'número entero'));
$r = $post(['j1' => '600.5', 'j2' => 0, 'j3' => 0], 4);
plVerificar('una cláusula decimal se rechaza, no se trunca', $r['tipo'] === 'html');
plVerificar('ninguno de los dos escribió nada', $clausulasA() === ['j1' => 650, 'j2' => 0, 'j3' => 0]);

// -- jugador de otro equipo --------------------------------------------
$r = $post(['j1' => 650, 'j2' => 0, 'j3' => 0, 'b1' => 999], 4);
plVerificar('una cláusula enviada para un jugador de OTRO equipo se ignora',
    plEquipoEnTemporada($T, 'eq_b')['jugadores'][0]['clausula'] === 0);

// -- rev desfasado ------------------------------------------------------
$r = $post(['j1' => 100, 'j2' => 0, 'j3' => 0], 1);
plVerificar('un rev desfasado se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'copresidente ha guardado'));

// -- fuera de fase ------------------------------------------------------
plCambiarFase($T, 'ROSTER');
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('en ROSTER se pide terminar antes la plantilla', str_contains($h, 'Termina primero tu inscripción de plantilla'));
plVerificar('con los campos deshabilitados', str_contains($h, ' disabled'));
plVerificar('y sin botón de guardar', !str_contains($h, 'type="submit"'));
$antes = $clausulasA();
$r = $post(['j1' => 1, 'j2' => 1, 'j3' => 1], $enA()['rev']);
plVerificar('un POST reenviado en ROSTER se rechaza sin escribir', $r['tipo'] === 'html' && $clausulasA() === $antes);

plCambiarFase($T, 'MERCADO');
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('en MERCADO las cláusulas se ven en solo lectura', str_contains($h, 'ya ha finalizado') && str_contains($h, ' disabled'));
$r = $post(['j1' => 1, 'j2' => 1, 'j3' => 1], $enA()['rev']);
plVerificar('y un POST reenviado en MERCADO se rechaza sin escribir', $r['tipo'] === 'html' && $clausulasA() === $antes);

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
