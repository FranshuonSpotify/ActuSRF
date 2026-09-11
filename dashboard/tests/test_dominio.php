<?php
// dashboard/tests/test_dominio.php
// Self-check de las reglas de la liga. Ejecutar desde la raíz del proyecto:
//   /c/xampp/php/php.exe dashboard/tests/test_dominio.php
//
// dominio.php es puro, así que esta batería no necesita arnés de sesión ni
// ficheros: son llamadas con entradas y salidas, y nada más.

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../dominio.php';

// Los diez tiers oficiales, con la forma exacta que tiene ajustes.tiers
// dentro del fichero de temporada.
$TIERS = [
    ['codigo' => 'S++', 'salario' => 75], ['codigo' => 'S+', 'salario' => 60],
    ['codigo' => 'S',   'salario' => 40], ['codigo' => 'A+', 'salario' => 25],
    ['codigo' => 'A',   'salario' => 18], ['codigo' => 'A-', 'salario' => 14],
    ['codigo' => 'B+',  'salario' => 8],  ['codigo' => 'B',  'salario' => 6],
    ['codigo' => 'B-',  'salario' => 5],  ['codigo' => 'C',  'salario' => 2],
];
$AJUSTES = [
    'salaryCap'            => 250,
    'presupuestoClausulas' => 650,
    'maxJugadores'         => 20,
    'tiers'                => $TIERS,
];

// Fabrica una plantilla de $n jugadores con $salario cada uno.
function plantillaDe(int $n, int $salario, string $tier = 'S'): array
{
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $out[] = ['id' => "j$i", 'nombre' => "Jugador $i", 'posicion' => 'MED',
                  'tier' => $tier, 'salario' => $salario, 'clausula' => 0,
                  'estado' => 'DISPONIBLE'];
    }
    return $out;
}

// -- constantes ---------------------------------------------------------
plVerificar('las cuatro posiciones, y solo esas', PL_POSICIONES === ['POR', 'DEF', 'MED', 'ATA']);
plVerificar('las cuatro fases, en orden', PL_FASES === ['ROSTER', 'CLAUSULAS', 'MERCADO', 'CERRADA']);

// -- plSalarioDeTier ----------------------------------------------------
plVerificar('S++ vale 75M', plSalarioDeTier('S++', $TIERS) === 75);
plVerificar('C vale 2M', plSalarioDeTier('C', $TIERS) === 2);
plVerificar('los diez tiers están', count($TIERS) === 10);
plVerificar('un tier inexistente devuelve null, no 0', plSalarioDeTier('D', $TIERS) === null);
plVerificar('C+ no existe', plSalarioDeTier('C+', $TIERS) === null);

// -- plValidarAltaJugador ----------------------------------------------
$vacia = [];
$r = plValidarAltaJugador($vacia, ['nombre' => 'Uno', 'posicion' => 'MED', 'tier' => 'S+'], $AJUSTES);
plVerificar('alta válida en plantilla vacía', $r['ok'] === true && $r['datos']['salario'] === 60);

$r = plValidarAltaJugador($vacia, ['nombre' => '  ', 'posicion' => 'MED', 'tier' => 'S+'], $AJUSTES);
plVerificar('nombre vacío se rechaza', $r['ok'] === false && $r['error'] === 'error.nombre_vacio');

$r = plValidarAltaJugador($vacia, ['nombre' => 'Uno', 'posicion' => 'DEL', 'tier' => 'S+'], $AJUSTES);
plVerificar('posición DEL se rechaza: aquí es ATA', $r['ok'] === false && $r['error'] === 'error.posicion_invalida');

$r = plValidarAltaJugador($vacia, ['nombre' => 'Uno', 'posicion' => 'ATA', 'tier' => 'D'], $AJUSTES);
plVerificar('tier inexistente se rechaza', $r['ok'] === false && $r['error'] === 'error.tier_invalido');

// 20 jugadores a 2M = 40M: el cap sobra, pero el hueco no.
$llena = plantillaDe(20, 2, 'C');
$r = plValidarAltaJugador($llena, ['nombre' => '21', 'posicion' => 'MED', 'tier' => 'C'], $AJUSTES);
plVerificar(
    'el jugador 21 se rechaza aunque el cap lo permita de sobra',
    $r['ok'] === false && $r['error'] === 'error.max_jugadores'
);

// 5 jugadores a 48M = 240M. Añadir uno de 25M dejaría 265M.
$cerca = plantillaDe(5, 48);
$r = plValidarAltaJugador($cerca, ['nombre' => 'Caro', 'posicion' => 'ATA', 'tier' => 'A+'], $AJUSTES);
plVerificar('superar el cap se rechaza', $r['ok'] === false && $r['error'] === 'error.cap_superado');
plVerificar('el error dice el total que quedaría', $r['datos']['total'] === 265);
plVerificar('y los millones disponibles', $r['datos']['disponible'] === 10);

$r = plValidarAltaJugador($cerca, ['nombre' => 'Barato', 'posicion' => 'ATA', 'tier' => 'B+'], $AJUSTES);
plVerificar('un jugador de 8M sí cabe en los 10M libres', $r['ok'] === true);

// Justo en el límite: 240M + 10M = 250M exactos. El cap es "no superar".
$diez = plantillaDe(1, 240);
$r = plValidarAltaJugador($diez, ['nombre' => 'Justo', 'posicion' => 'POR', 'tier' => 'B+'], $AJUSTES);
plVerificar('quedarse exactamente en el cap es válido', $r['ok'] === true);

// -- plValidarCambioTier -----------------------------------------------
// 5 a 48M = 240M. j1 baja de 48M a 25M -> 217M. Sumar en vez de sustituir
// daría 265M y lo rechazaría: ese es el fallo que esta comprobación vigila.
$r = plValidarCambioTier($cerca, 'j1', 'A+', $AJUSTES);
plVerificar('bajar de tier NUNCA se rechaza por cap', $r['ok'] === true);
plVerificar('y el salario nuevo es el del tier elegido', $r['datos']['salario'] === 25);

// j1 sube de 48M a 75M -> 240 - 48 + 75 = 267M.
$r = plValidarCambioTier($cerca, 'j1', 'S++', $AJUSTES);
plVerificar('subir de tier por encima del cap se rechaza', $r['ok'] === false && $r['error'] === 'error.cap_superado');
plVerificar('con el total sustituido, no sumado', $r['datos']['total'] === 267);

$r = plValidarCambioTier($cerca, 'no_existe', 'A+', $AJUSTES);
plVerificar('cambiar el tier de un jugador ausente se rechaza', $r['ok'] === false && $r['error'] === 'error.jugador_no_encontrado');

// -- plValidarClausulas -------------------------------------------------
$tres = plantillaDe(3, 40);
$r = plValidarClausulas($tres, ['j1' => 300, 'j2' => 200, 'j3' => 150], $AJUSTES);
plVerificar('650M exactos es válido', $r['ok'] === true);
plVerificar('y el estado es COMPLETO', $r['datos']['estado'] === 'COMPLETO');
plVerificar('sin nada disponible', $r['datos']['disponible'] === 0);

$r = plValidarClausulas($tres, ['j1' => 300, 'j2' => 200, 'j3' => 80], $AJUSTES);
plVerificar('quedarse por debajo se GUARDA: es un borrador', $r['ok'] === true);
plVerificar('pero el estado es INCOMPLETO', $r['datos']['estado'] === 'INCOMPLETO');
plVerificar('y quedan 70M por repartir', $r['datos']['disponible'] === 70);

$r = plValidarClausulas($tres, ['j1' => 300, 'j2' => 200, 'j3' => 151], $AJUSTES);
plVerificar('superar 650M se rechaza', $r['ok'] === false && $r['error'] === 'error.clausulas_excedidas');
plVerificar('diciendo por cuánto se pasa', $r['datos']['exceso'] === 1);

$r = plValidarClausulas($tres, ['j1' => 650, 'j2' => 0, 'j3' => 0], $AJUSTES);
plVerificar('una cláusula 0 es válida: no hay mínimo por jugador', $r['ok'] === true);

$r = plValidarClausulas($tres, ['j1' => -10, 'j2' => 0, 'j3' => 0], $AJUSTES);
plVerificar('una cláusula negativa se rechaza', $r['ok'] === false && $r['error'] === 'error.clausula_invalida');

$r = plValidarClausulas($tres, ['j1' => '40.5', 'j2' => 0, 'j3' => 0], $AJUSTES);
plVerificar('una cláusula decimal se rechaza, no se trunca', $r['ok'] === false && $r['error'] === 'error.clausula_invalida');

// -- plEstadoPresupuesto ------------------------------------------------
plVerificar('580 de 650 es INCOMPLETO', plEstadoPresupuesto(580, 650) === 'INCOMPLETO');
plVerificar('650 de 650 es COMPLETO', plEstadoPresupuesto(650, 650) === 'COMPLETO');
plVerificar('651 de 650 es EXCEDIDO', plEstadoPresupuesto(651, 650) === 'EXCEDIDO');

// -- fases --------------------------------------------------------------
plVerificar('la plantilla solo se edita en ROSTER',
    plPuedeEditarPlantilla('ROSTER') && !plPuedeEditarPlantilla('CLAUSULAS')
    && !plPuedeEditarPlantilla('MERCADO') && !plPuedeEditarPlantilla('CERRADA'));
plVerificar('las cláusulas solo en CLAUSULAS',
    plPuedeEditarClausulas('CLAUSULAS') && !plPuedeEditarClausulas('ROSTER')
    && !plPuedeEditarClausulas('MERCADO'));
plVerificar('marcar clausulado solo en MERCADO',
    plPuedeMarcarClausulado('MERCADO') && !plPuedeMarcarClausulado('ROSTER')
    && !plPuedeMarcarClausulado('CLAUSULAS') && !plPuedeMarcarClausulado('CERRADA'));

// -- plPuedeClausular ---------------------------------------------------
$presi   = ['id' => 'u_1', 'equipoId' => 'eq_mio'];
$libre   = ['id' => 'j9', 'estado' => 'DISPONIBLE'];
$tomado  = ['id' => 'j9', 'estado' => 'CLAUSULADO'];

plVerificar('caso normal: jugador ajeno, disponible, comprador = mi equipo',
    plPuedeClausular($presi, $libre, 'eq_otro', 'eq_mio') === true);
plVerificar('un jugador de mi propio equipo, NO',
    plPuedeClausular($presi, $libre, 'eq_mio', 'eq_mio') === false);
plVerificar('asignar la compra a otro equipo, NO',
    plPuedeClausular($presi, $libre, 'eq_otro', 'eq_tercero') === false);
plVerificar('un jugador ya clausulado, NO: corregir es cosa del admin',
    plPuedeClausular($presi, $tomado, 'eq_otro', 'eq_mio') === false);
plVerificar('un presidente sin equipo asignado, NO',
    plPuedeClausular(['id' => 'u_2', 'equipoId' => null], $libre, 'eq_otro', '') === false);

plSalirConResultado();
