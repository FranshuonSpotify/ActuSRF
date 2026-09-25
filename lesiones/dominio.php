<?php
// lesiones/dominio.php
// Las reglas de la ruleta de lesiones, y solo las reglas: probabilidades,
// selección de jugadores, acumulación. Puro a propósito — sin I/O, sin
// sesión, sin superglobales — para poder probarlo entero sin arnés.
//
// $azar es una función () -> float en [0,1). Por defecto usa mt_rand(), pero
// los tests inyectan una función determinista para fijar el resultado.

require_once __DIR__ . '/lib.php';

// 20% evento inesperado, 80% no ocurre nada. Con 3 partidos por semana, un
// 30% generaba solapes de recuperación poco realistas — decisión tomada con
// el propietario del proyecto, no reabrir sin motivo.
const LE_PROB_EVENTO = 0.20;

const LE_TABLA_SECUNDARIA = [
    ['codigo' => '1j_1p',     'jugadores' => 1, 'partidos' => 1,    'toda_temporada' => false, 'pct' => 38.0],
    ['codigo' => '1j_2p',     'jugadores' => 1, 'partidos' => 2,    'toda_temporada' => false, 'pct' => 22.0],
    ['codigo' => '2j_1p',     'jugadores' => 2, 'partidos' => 1,    'toda_temporada' => false, 'pct' => 18.0],
    ['codigo' => '1j_3p',     'jugadores' => 1, 'partidos' => 3,    'toda_temporada' => false, 'pct' => 11.0],
    ['codigo' => '2j_2p',     'jugadores' => 2, 'partidos' => 2,    'toda_temporada' => false, 'pct' => 7.0],
    ['codigo' => '2j_3p',     'jugadores' => 2, 'partidos' => 3,    'toda_temporada' => false, 'pct' => 3.9],
    ['codigo' => 'temporada', 'jugadores' => 1, 'partidos' => null, 'toda_temporada' => true,  'pct' => 0.1],
];

function leAzarPorDefecto(): float
{
    return mt_rand() / mt_getrandmax();
}

function leTirarPrincipal(?callable $azar = null): string
{
    $r = ($azar ?? 'leAzarPorDefecto')();
    return $r < LE_PROB_EVENTO ? 'evento' : 'nada';
}

// Elección ponderada sobre LE_TABLA_SECUNDARIA. Los porcentajes suman 100.0;
// se recorre acumulando hasta que $r cae dentro del tramo. La última fila se
// devuelve también como respaldo si el redondeo dejara $r por encima de 100.
function leTirarSecundaria(?callable $azar = null): array
{
    $r = (($azar ?? 'leAzarPorDefecto')()) * 100.0;
    $acumulado = 0.0;
    foreach (LE_TABLA_SECUNDARIA as $fila) {
        $acumulado += $fila['pct'];
        if ($r < $acumulado) {
            return $fila;
        }
    }
    return LE_TABLA_SECUNDARIA[count(LE_TABLA_SECUNDARIA) - 1];
}

// Selección aleatoria uniforme de $cantidad jugadores de la plantilla, sin
// repetir dentro de la MISMA tirada. Se permite elegir a un jugador que ya
// tenga una lesión activa: eso se resuelve luego en leAcumularLesion().
function leSeleccionarJugadores(array $jugadores, int $cantidad, ?callable $azar = null): array
{
    $azar = $azar ?? 'leAzarPorDefecto';
    $disponibles = array_values($jugadores);
    $elegidos = [];
    $cantidad = min($cantidad, count($disponibles));
    for ($i = 0; $i < $cantidad; $i++) {
        $indice = (int) floor($azar() * count($disponibles));
        $indice = max(0, min($indice, count($disponibles) - 1));
        $elegidos[] = $disponibles[$indice];
        array_splice($disponibles, $indice, 1);
    }
    return $elegidos;
}

// Combina una lesión nueva con la que el jugador ya tuviera activa (o crea
// una si no tenía). "Toda la temporada" domina: si cualquiera de las dos lo
// es, el resultado queda marcado toda_temporada y deja de contar partidos.
function leAcumularLesion(?array $existente, array $resultado, string $semana, string $fecha): array
{
    if ($existente === null) {
        return [
            'partidos_totales'   => (int) ($resultado['partidos'] ?? 0),
            'partidos_restantes' => (int) ($resultado['partidos'] ?? 0),
            'toda_temporada'     => (bool) $resultado['toda_temporada'],
            'semana_inicio'      => $semana,
            'fecha_inicio'       => $fecha,
            'estado'             => 'activa',
        ];
    }

    $todaTemporada = (bool) $existente['toda_temporada'] || (bool) $resultado['toda_temporada'];
    $nuevo = $existente;
    $nuevo['toda_temporada'] = $todaTemporada;
    $nuevo['estado'] = 'activa';
    if (!$todaTemporada) {
        $extra = (int) ($resultado['partidos'] ?? 0);
        $nuevo['partidos_totales']   = (int) $existente['partidos_totales'] + $extra;
        $nuevo['partidos_restantes'] = (int) $existente['partidos_restantes'] + $extra;
    }
    return $nuevo;
}
