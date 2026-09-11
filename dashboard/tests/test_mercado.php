<?php
// dashboard/tests/test_mercado.php
// Self-check del paso 10: mercado consultable y registro de clausulaciones.
//   /c/xampp/php/php.exe dashboard/tests/test_mercado.php

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$PANTALLA = __DIR__ . '/../mercado.php';
$T  = '2026-27';
$SA = ['pl_usuario_id' => 'u_a', 'pl_csrf' => 'tok'];

plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'nombre_en' => 'Beta FC', 'activo' => true],
    ['id' => 'eq_c', 'nombre' => 'Gamma', 'activo' => true],
]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');

$j = static fn(string $id, string $nombre, string $estado = 'DISPONIBLE', ?string $por = null) => ['id' => $id,
    'nombre' => $nombre, 'posicion' => 'MED', 'tier' => 'S', 'salario' => 40, 'clausula' => 120,
    'estado' => $estado, 'clausuladoPor' => $por, 'clausuladoEn' => null];
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => [$j('a1', 'Propio')]], 0);
plGuardarEquipoTemporada($T, 'eq_b', ['jugadores' => [$j('b1', 'Endou Mamoru'), $j('b2', 'Goenji')]], 0);
plGuardarEquipoTemporada($T, 'eq_c', ['jugadores' => [$j('c1', 'Ya Vendido', 'CLAUSULADO', 'eq_b')]], 0);

// Un evento previo, para comprobar que el registro nunca se reescribe.
plRegistrarEvento('FASE', 'admin', 'admin', ['a' => 'MERCADO']);

$jugadorB = static fn(string $id) => array_values(array_filter(
    plEquipoEnTemporada('2026-27', 'eq_b')['jugadores'], static fn($x) => $x['id'] === $id))[0];
$eventos  = static fn() => plCargarJson(plRutaDatos('registro.json'), ['eventos' => []])['eventos'];
$revB     = static fn() => plEquipoEnTemporada('2026-27', 'eq_b')['rev'];
$marcar   = static fn(string $jugador, string $equipo, string $comprador, int $rev) => plArnesPeticion(
    $GLOBALS['PANTALLA'], $GLOBALS['SA'], [],
    ['csrf' => 'tok', 'jugador' => $jugador, 'equipo' => $equipo, 'comprador' => $comprador, 'rev' => $rev]);

// -- fuera de MERCADO: consulta libre, sin registrar ------------------------
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('en ROSTER el mercado se consulta igual', str_contains($h, 'Endou Mamoru') && str_contains($h, 'Propio'));
plVerificar('con todos los equipos', str_contains($h, 'Gamma'));
plVerificar('dice que el mercado está cerrado', str_contains($h, 'MERCADO CERRADO'));
plVerificar('y no ofrece marcar a nadie', !str_contains($h, 'Marcar como clausulado'));
plVerificar('la cláusula de cada jugador está a la vista', str_contains($h, '120M'));
plVerificar('la tabla va dentro de su propio contenedor con scroll', str_contains($h, 'tabla-scroll'));

$r = $marcar('b1', 'eq_b', 'eq_a', $revB());
plVerificar('en ROSTER un POST de clausulación se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'mercado está cerrado'));
plVerificar('y el jugador sigue disponible', $jugadorB('b1')['estado'] === 'DISPONIBLE');

// -- MERCADO: botones -----------------------------------------------------
plCambiarFase($T, 'MERCADO');
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('en MERCADO dice que está abierto', str_contains($h, 'MERCADO ABIERTO'));
plVerificar('se ofrece marcar a un jugador disponible de otro equipo', str_contains($h, 'confirmar=b1'));
plVerificar('NUNCA a uno del propio equipo', !str_contains($h, 'confirmar=a1'));
plVerificar('NUNCA a uno ya clausulado', !str_contains($h, 'confirmar=c1'));
plVerificar('el ya clausulado se lee «Clausulado por Beta», en texto', str_contains($h, 'Clausulado por Beta'));

// -- paso de confirmación en servidor --------------------------------------
$h = plArnesPeticion($PANTALLA, $SA, ['confirmar' => 'b1', 'equipo' => 'eq_b'])['html'] ?? '';
plVerificar('marcar pide confirmación con el texto literal',
    str_contains($h, '¿Confirmas que Alfa ha clausulado a Endou Mamoru (Beta)?'));
plVerificar('recordando que solo registra el resultado', str_contains($h, 'solo registra el resultado'));
plVerificar('el formulario de confirmación lleva el rev del equipo vendedor', str_contains($h, 'name="rev" value="' . $revB() . '"'));
plVerificar('la confirmación no escribe nada todavía', $jugadorB('b1')['estado'] === 'DISPONIBLE');

$h = plArnesPeticion($PANTALLA, $SA, ['confirmar' => 'a1', 'equipo' => 'eq_a'])['html'] ?? '';
plVerificar('no se puede ni preparar la confirmación de un jugador propio', !str_contains($h, '¿Confirmas que'));

// -- CSRF -----------------------------------------------------------------
$r = plArnesPeticion($PANTALLA, $SA, [], ['jugador' => 'b1', 'equipo' => 'eq_b', 'comprador' => 'eq_a', 'rev' => $revB()]);
plVerificar('un POST sin token CSRF se corta con 403', $r['tipo'] === 'cortar' && $r['codigo'] === 403);

// -- intentos que se tienen que rechazar ----------------------------------
$antes = count($eventos());

$r = $marcar('a1', 'eq_a', 'eq_a', plEquipoEnTemporada($T, 'eq_a')['rev']);
plVerificar('marcar a un jugador del PROPIO equipo se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'No puedes registrar'));

$r = $marcar('b1', 'eq_b', 'eq_c', $revB());
plVerificar('asignar la compra a OTRO equipo, manipulando el formulario, se rechaza', $r['tipo'] === 'html');
plVerificar('y el jugador sigue disponible', $jugadorB('b1')['estado'] === 'DISPONIBLE');

$r = $marcar('b9', 'eq_b', 'eq_a', $revB());
plVerificar('un jugador inexistente se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'ya no está en el mercado'));

plVerificar('ningún intento rechazado escribió en el registro', count($eventos()) === $antes);

// -- clausulación válida ----------------------------------------------------
$r = $marcar('b1', 'eq_b', 'eq_a', $revB());
plVerificar('una clausulación válida redirige al mercado', $r['tipo'] === 'redirigir' && $r['url'] === 'mercado.php');
plVerificar('con un mensaje que dice quién y por quién',
    str_contains($r['sesion']['pl_flash'] ?? '', 'Endou Mamoru') && str_contains($r['sesion']['pl_flash'] ?? '', 'Alfa'));
$b1 = $jugadorB('b1');
plVerificar('el jugador queda CLAUSULADO', $b1['estado'] === 'CLAUSULADO');
plVerificar('por el equipo del presidente', $b1['clausuladoPor'] === 'eq_a');
plVerificar('con la fecha de la clausulación', strtotime((string) $b1['clausuladoEn']) !== false);
plVerificar('sin tocar su cláusula ni su salario', $b1['clausula'] === 120 && $b1['salario'] === 40);

$ev = $eventos();
plVerificar('se añade exactamente UN evento al registro', count($ev) === $antes + 1);
$ultimo = end($ev);
plVerificar('de tipo CLAUSULACION, con actor y detalle',
    $ultimo['tipo'] === 'CLAUSULACION' && $ultimo['actor'] === 'u_a'
    && $ultimo['detalle']['equipoOrigen'] === 'eq_b' && $ultimo['detalle']['equipoComprador'] === 'eq_a');
plVerificar('y el evento anterior sigue intacto', $ev[0]['tipo'] === 'FASE' && $ev[0]['detalle'] === ['a' => 'MERCADO']);

// -- no revertir ni robar -------------------------------------------------
$r = $marcar('b1', 'eq_b', 'eq_a', $revB());
plVerificar('volver a marcar a un jugador ya clausulado se rechaza', $r['tipo'] === 'html');
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('y ya no se ofrece el botón para él', !str_contains($h, 'confirmar=b1'));

// -- dos compradores a la vez ---------------------------------------------
// Otro presidente vio a Goenji disponible con el rev de antes de la
// clausulación de Endou. Su formulario trae un rev que ya no es el del disco.
$r = $marcar('b2', 'eq_b', 'eq_a', 0);
plVerificar('un rev desfasado del vendedor se rechaza', $r['tipo'] === 'html' && str_contains($r['html'], 'Otro equipo ha registrado un cambio'));
plVerificar('y Goenji sigue disponible', $jugadorB('b2')['estado'] === 'DISPONIBLE');

// -- filtros ----------------------------------------------------------------
$h = plArnesPeticion($PANTALLA, $SA, ['equipo' => 'eq_b'])['html'] ?? '';
plVerificar('filtrar por equipo enseña solo ese equipo', str_contains($h, 'Goenji') && !str_contains($h, '>Propio<'));
$h = plArnesPeticion($PANTALLA, $SA, ['estado' => 'CLAUSULADO'])['html'] ?? '';
plVerificar('filtrar por estado enseña solo los clausulados', str_contains($h, 'Ya Vendido') && !str_contains($h, 'Goenji'));
$h = plArnesPeticion($PANTALLA, $SA, ['q' => 'endou'])['html'] ?? '';
plVerificar('buscar ignora mayúsculas y encuentra por parte del nombre', str_contains($h, 'Endou Mamoru') && !str_contains($h, 'Goenji'));

// -- en inglés, nombre_en -----------------------------------------------------
$h = plArnesPeticion($PANTALLA, $SA + ['pl_lang' => 'en'])['html'] ?? '';
plVerificar('en inglés el equipo aparece con su nombre_en', str_contains($h, 'Beta FC'));
plVerificar('y un equipo sin nombre_en cae a su nombre, no a una celda vacía', str_contains($h, 'Gamma'));
plEstablecerIdioma('es');

// -- temporada cerrada ------------------------------------------------------
plCambiarFase($T, 'CERRADA');
$h = plArnesPeticion($PANTALLA, $SA)['html'] ?? '';
plVerificar('con la temporada cerrada no se ofrece marcar', !str_contains($h, 'Marcar como clausulado'));
$r = $marcar('b2', 'eq_b', 'eq_a', $revB());
plVerificar('y un POST reenviado se rechaza', $r['tipo'] === 'html' && $jugadorB('b2')['estado'] === 'DISPONIBLE');

// -- guardas del código -----------------------------------------------------
$fuente = (string) file_get_contents($PANTALLA);
plVerificar('la pantalla comprueba la fase con plPuedeMarcarClausulado', str_contains($fuente, 'plPuedeMarcarClausulado'));
plVerificar('y las cuatro condiciones con plPuedeClausular', str_contains($fuente, 'plPuedeClausular'));

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
