<?php
// lesiones/tests/test_dominio.php
// Ejecutar: /c/xampp/php/php.exe lesiones/tests/test_dominio.php

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../dominio.php';

// -- tabla ----------------------------------------------------------------
$suma = array_sum(array_column(LE_TABLA_SECUNDARIA, 'pct'));
leVerificar('la tabla secundaria suma 100%', abs($suma - 100.0) < 0.0001);
leVerificar('hay exactamente 7 resultados', count(LE_TABLA_SECUNDARIA) === 7);

// -- leTirarPrincipal -------------------------------------------------------
leVerificar('r=0.0 siempre es evento', leTirarPrincipal(fn() => 0.0) === 'evento');
leVerificar('r=0.19999 es evento (justo por debajo del 20%)', leTirarPrincipal(fn() => 0.19999) === 'evento');
leVerificar('r=0.2 exacto ya es nada', leTirarPrincipal(fn() => 0.2) === 'nada');
leVerificar('r=0.999 es nada', leTirarPrincipal(fn() => 0.999) === 'nada');

// -- leTirarSecundaria: cada tramo cae en el resultado esperado -----------
leVerificar('r=0.0 -> 1j_1p (primer tramo)', leTirarSecundaria(fn() => 0.0)['codigo'] === '1j_1p');
leVerificar('r=0.375 (37.5%) -> 1j_1p, dentro del 38%', leTirarSecundaria(fn() => 0.375)['codigo'] === '1j_1p');
leVerificar('r=0.385 (38.5%) -> 1j_2p, ya en el segundo tramo', leTirarSecundaria(fn() => 0.385)['codigo'] === '1j_2p');
leVerificar('r=0.9999 -> temporada (último tramo, 99.9-100%)', leTirarSecundaria(fn() => 0.9999)['codigo'] === 'temporada');

// -- leSeleccionarJugadores -------------------------------------------------
$plantilla = [
    ['id' => 'a'], ['id' => 'b'], ['id' => 'c'], ['id' => 'd'],
];
$elegidos = leSeleccionarJugadores($plantilla, 2, fn() => 0.0);
leVerificar('selecciona la cantidad pedida', count($elegidos) === 2);
$ids = array_column($elegidos, 'id');
leVerificar('no repite jugador dentro de la misma tirada', count($ids) === count(array_unique($ids)));

$elegidosDeMas = leSeleccionarJugadores($plantilla, 10, fn() => 0.0);
leVerificar('nunca selecciona más jugadores de los que hay en plantilla', count($elegidosDeMas) === 4);

// -- leAcumularLesion --------------------------------------------------------
$nueva = leAcumularLesion(null, ['partidos' => 2, 'toda_temporada' => false], '2026-W38', '2026-09-14');
leVerificar('lesión nueva: partidos_totales = partidos_restantes = 2', $nueva['partidos_totales'] === 2 && $nueva['partidos_restantes'] === 2);
leVerificar('lesión nueva no es toda_temporada', $nueva['toda_temporada'] === false);

$acumulada = leAcumularLesion(
    ['partidos_totales' => 1, 'partidos_restantes' => 1, 'toda_temporada' => false, 'semana_inicio' => '2026-W37', 'fecha_inicio' => '2026-09-07', 'estado' => 'activa'],
    ['partidos' => 2, 'toda_temporada' => false],
    '2026-W38', '2026-09-14'
);
leVerificar('se suman los partidos pendientes', $acumulada['partidos_restantes'] === 3 && $acumulada['partidos_totales'] === 3);
leVerificar('la fecha de inicio no cambia al acumular', $acumulada['fecha_inicio'] === '2026-09-07');

$dominaTemporada = leAcumularLesion(
    ['partidos_totales' => 1, 'partidos_restantes' => 1, 'toda_temporada' => false, 'semana_inicio' => '2026-W37', 'fecha_inicio' => '2026-09-07', 'estado' => 'activa'],
    ['partidos' => null, 'toda_temporada' => true],
    '2026-W38', '2026-09-14'
);
leVerificar('toda_temporada domina sobre una lesión de partidos existente', $dominaTemporada['toda_temporada'] === true);

leSalirConResultado();
