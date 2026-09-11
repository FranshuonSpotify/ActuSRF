<?php
// dashboard/tests/test_pegado.php
// Self-check del paso 08: pegado masivo "Nombre;POS;TIER".
//   /c/xampp/php/php.exe dashboard/tests/test_pegado.php

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$PANTALLA = __DIR__ . '/../pegado.php';
$T = '2026-27';

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_c', 'nombre' => 'Gamma', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
    ['id' => 'u_c', 'nombre' => 'Ana',  'email' => 'c@x.es', 'hash' => 'x', 'equipoId' => 'eq_c', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');
$AJ = plEquipoEnTemporada($T, 'eq_a')['ajustes'];

// ======================================================= analizador (puro)
$p = plParsearPegado("Endou;POR;S++\n\n  Kazemaru ; def ; s \nMalo;DEL;S\nSolo dos;MED\nX;MED;D", [], $AJ);
plVerificar('las líneas en blanco no cuentan', count($p['lineas']) === 5);
plVerificar('la numeración coincide con el cuadro de texto, blancos incluidos',
    array_column($p['lineas'], 'n') === [1, 3, 4, 5, 6]);
plVerificar('se recortan los espacios sobrantes', $p['lineas'][1]['nombre'] === 'Kazemaru');
plVerificar('"def" y "s" se aceptan como DEF y S',
    $p['lineas'][1]['posicion'] === 'DEF' && $p['lineas'][1]['tier'] === 'S' && $p['lineas'][1]['error'] === null);
plVerificar('y el salario sale del tier', $p['lineas'][1]['salario'] === 40);
plVerificar('DEL se rechaza: aquí la posición es ATA', $p['lineas'][2]['error'] === 'error.posicion_invalida');
plVerificar('dos campos en vez de tres se rechaza', $p['lineas'][3]['error'] === 'pegado.error_campos');
plVerificar('un tier inexistente se rechaza', $p['lineas'][4]['error'] === 'error.tier_invalido');
plVerificar('2 líneas válidas y 3 con error', $p['validas'] === 2 && $p['errores'] === 3);
plVerificar('con una sola línea mala el lote NO es aceptable', $p['ok'] === false);

$p = plParsearPegado("Endou\tPOR\tS++\nKazemaru\tDEF\tS", [], $AJ);
plVerificar('columnas copiadas de una hoja de cálculo (tabulador) se aceptan',
    $p['ok'] === true && $p['lineas'][0]['posicion'] === 'POR');

$p = plParsearPegado("\n   \n", [], $AJ);
plVerificar('un pegado vacío avisa y no es aceptable',
    $p['errorLote'] === 'pegado.error_vacio' && $p['ok'] === false);

$diecinueve = [];
for ($i = 0; $i < 19; $i++) {
    $diecinueve[] = ['id' => "e$i", 'nombre' => "E$i", 'posicion' => 'MED', 'tier' => 'C', 'salario' => 2];
}
$p = plParsearPegado("A;MED;C\nB;MED;C", $diecinueve, $AJ);
plVerificar('un lote que pasaría de 20 se rechaza ENTERO', $p['errorLote'] === 'pegado.error_max' && $p['ok'] === false);
plVerificar('diciendo cuántas plazas quedan', $p['datosLote']['libres'] === 1);

$p = plParsearPegado("A;ATA;S++\nB;ATA;S++\nC;ATA;S++\nD;ATA;S++", [], $AJ);
plVerificar('un lote válido línea a línea que pasaría del cap se rechaza entero',
    $p['errorLote'] === 'pegado.error_cap' && $p['ok'] === false);
plVerificar('con el total que quedaría', $p['datosLote']['total'] === 300);

// ======================================================= pantalla
$SA = ['pl_usuario_id' => 'u_a', 'pl_csrf' => 'tok'];
$SC = ['pl_usuario_id' => 'u_c', 'pl_csrf' => 'tok'];

$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('en ROSTER se pinta el cuadro de texto', str_contains($h, 'name="texto"'));
plVerificar('sin previsualizar no hay botón de confirmar', !str_contains($h, 'value="confirmar"'));

// -- reconocimiento de capturas (Tesseract.js) ----------------------------
// La lógica de "líneas reconocidas -> filas" la prueba aparte
// tests/test_pegado_ocr.js (un script de Node, sin framework); aquí solo se
// comprueba que la pantalla la carga y la cablea correctamente.
plVerificar('carga el módulo propio con la lógica de reconocimiento', file_exists(__DIR__ . '/../js/pegado_ocr.js'));
plVerificar('la pantalla referencia ese módulo', str_contains($h, 'src="js/pegado_ocr.js"'));
plVerificar('el input de imagen y el botón de leer están presentes', str_contains($h, 'id="ocr-archivo"') && str_contains($h, 'id="ocr-boton"'));
preg_match('#https://cdn\.jsdelivr\.net/npm/tesseract\.js@([^/]+)/#', $h, $m);
plVerificar('Tesseract.js viene pinneado a una versión exacta, no @latest',
    isset($m[1]) && $m[1] !== 'latest' && preg_match('/^\d/', $m[1]));

$r = plArnesPeticion($PANTALLA, $SA, [], ['accion' => 'previsualizar', 'rev' => 0, 'texto' => 'A;MED;C']);
plVerificar('un POST sin token CSRF se corta con 403', $r['tipo'] === 'cortar' && $r['codigo'] === 403);

$r = plArnesPeticion($PANTALLA, $SA, [], ['csrf' => 'tok', 'accion' => 'previsualizar', 'rev' => 0,
    'texto' => "Endou;POR;S++\nKazemaru;DEF;S\nMalo;DEL;S"]);
$h = $r['html'] ?? '';
plVerificar('previsualizar con una línea mala enseña la tabla', str_contains($h, 'Kazemaru'));
plVerificar('con el motivo de la línea mala', str_contains($h, 'La posición tiene que ser'));
plVerificar('y SIN botón de confirmar', !str_contains($h, 'value="confirmar"'));
plVerificar('y no escribe nada', plEquipoEnTemporada($T, 'eq_a')['jugadores'] === []);

$r = plArnesPeticion($PANTALLA, $SA, [], ['csrf' => 'tok', 'accion' => 'previsualizar', 'rev' => 0,
    'texto' => "Endou;POR;S++\nKazemaru;DEF;S"]);
$h = $r['html'] ?? '';
plVerificar('previsualizar un lote bueno ofrece confirmar', str_contains($h, 'value="confirmar"'));
plVerificar('previsualizar tampoco escribe nada', plEquipoEnTemporada($T, 'eq_a')['jugadores'] === []);

// Confirmar con un texto que NO es el previsualizado: se reanaliza en el servidor.
$r = plArnesPeticion($PANTALLA, $SA, [], ['csrf' => 'tok', 'accion' => 'confirmar', 'rev' => 0,
    'texto' => "Endou;POR;S++\nTrampa;DEL;S++"]);
plVerificar('confirmar un texto manipulado se reanaliza y se rechaza', $r['tipo'] === 'html');
plVerificar('sin escribir nada', plEquipoEnTemporada($T, 'eq_a')['jugadores'] === []);

$r = plArnesPeticion($PANTALLA, $SA, [], ['csrf' => 'tok', 'accion' => 'confirmar', 'rev' => 0,
    'texto' => "Endou;POR;S++\nKazemaru;DEF;S"]);
plVerificar('confirmar un lote bueno redirige a Mi plantilla', $r['tipo'] === 'redirigir' && $r['url'] === 'plantilla.php');
plVerificar('con el mensaje de cuántos se inscribieron', str_contains($r['sesion']['pl_flash'] ?? '', '2'));
$e = plEquipoEnTemporada($T, 'eq_a');
plVerificar('los dos jugadores quedan guardados', count($e['jugadores']) === 2);
plVerificar('con sus salarios', array_column($e['jugadores'], 'salario') === [75, 40]);

// Un copresidente confirma con la pantalla abierta desde antes (rev 0).
$r = plArnesPeticion($PANTALLA, $SA, [], ['csrf' => 'tok', 'accion' => 'confirmar', 'rev' => 0,
    'texto' => "Otro;MED;C"]);
plVerificar('un rev desfasado se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'copresidente ha guardado'));
plVerificar('y no añade a nadie', count(plEquipoEnTemporada($T, 'eq_a')['jugadores']) === 2);

// -- lote de 20 en una sola operación -----------------------------------
$veinte = [];
for ($i = 1; $i <= 20; $i++) {
    $veinte[] = "Jugador $i;MED;C";
}
$r = plArnesPeticion($PANTALLA, $SC, [], ['csrf' => 'tok', 'accion' => 'confirmar', 'rev' => 0,
    'texto' => implode("\n", $veinte)]);
$e = plEquipoEnTemporada($T, 'eq_c');
plVerificar('un lote de 20 entra entero', $r['tipo'] === 'redirigir' && count($e['jugadores']) === 20);
plVerificar('en UNA sola escritura: el rev sube exactamente en 1', $e['rev'] === 1);
plVerificar('cada jugador con su propio id', count(array_unique(array_column($e['jugadores'], 'id'))) === 20);

// -- fuera de ROSTER ----------------------------------------------------
plCambiarFase($T, 'CLAUSULAS');
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('en CLAUSULAS no se ofrece pegar', !str_contains($h, 'name="texto"'));
plVerificar('ni tampoco el reconocimiento de capturas', !str_contains($h, 'id="ocr-boton"'));
plVerificar('y se avisa de que la inscripción terminó', str_contains($h, 'ya ha finalizado'));

$r = plArnesPeticion($PANTALLA, $SA, [], ['csrf' => 'tok', 'accion' => 'confirmar', 'rev' => 1, 'texto' => 'Tarde;MED;C']);
plVerificar('un pegado reenviado a mano fuera de ROSTER se rechaza', $r['tipo'] === 'html');
plVerificar('y no añade a nadie', count(plEquipoEnTemporada($T, 'eq_a')['jugadores']) === 2);

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
