<?php
// dashboard/dominio.php
// Las reglas de la liga, y solo las reglas: cap de 250M, máximo de 20
// jugadores, presupuesto de 650M en cláusulas, y quién puede hacer qué en
// cada fase.
//
// Este fichero es PURO a propósito: no abre ficheros, no imprime, no lee
// superglobales y no toca la sesión. Es lo que permite probarlo entero sin
// arnés, sin datos en disco y sin navegador — y es donde está el valor real
// del subproyecto, porque es la única parte con aritmética que puede estar
// mal sin que se note hasta que un equipo quede mal inscrito.
//
// Los errores se devuelven como CLAVE de i18n, nunca como frase: las frases
// viven en i18n.php y llegan traducidas a diez idiomas.

require_once __DIR__ . '/lib.php';

const PL_POSICIONES = ['POR', 'DEF', 'MED', 'ATA'];
const PL_FASES      = ['ROSTER', 'CLAUSULAS', 'MERCADO', 'CERRADA'];
const PL_ESTADOS    = ['DISPONIBLE', 'CLAUSULADO'];

// ---------------------------------------------------------------- salarios

// Busca el salario de un tier en la tabla CONGELADA de la temporada
// ($ajustes['tiers']), nunca en tiers.json. Devuelve null si el código no
// existe, que es lo que convierte un tier inventado en un error de validación
// en vez de en un salario 0 silencioso.
function plSalarioDeTier(string $tier, array $tiers): ?int
{
    foreach ($tiers as $t) {
        if (($t['codigo'] ?? '') === $tier) {
            return (int) ($t['salario'] ?? 0);
        }
    }
    return null;
}

function plTotalSalarios(array $jugadores): int
{
    $total = 0;
    foreach ($jugadores as $j) {
        $total += (int) ($j['salario'] ?? 0);
    }
    return $total;
}

function plTotalClausulas(array $jugadores): int
{
    $total = 0;
    foreach ($jugadores as $j) {
        $total += (int) ($j['clausula'] ?? 0);
    }
    return $total;
}

// ------------------------------------------------------------- validaciones

// Todas devuelven ['ok' => bool, 'error' => ?clave, 'datos' => array], donde
// 'datos' lleva las cifras que el mensaje necesita interpolar (total, cap,
// disponible) para que la pantalla no tenga que recalcularlas.
function plResultado(bool $ok, ?string $error = null, array $datos = []): array
{
    return ['ok' => $ok, 'error' => $error, 'datos' => $datos];
}

// Orden de comprobación deliberado: primero lo que es culpa del formulario
// (nombre, posición, tier), después los límites del equipo. Así un tier mal
// enviado no se reporta como "has superado el cap".
function plValidarAltaJugador(array $jugadores, array $nuevo, array $ajustes): array
{
    $nombre = trim((string) ($nuevo['nombre'] ?? ''));
    if ($nombre === '') {
        return plResultado(false, 'error.nombre_vacio');
    }

    $posicion = (string) ($nuevo['posicion'] ?? '');
    if (!in_array($posicion, PL_POSICIONES, true)) {
        return plResultado(false, 'error.posicion_invalida');
    }

    $tier    = (string) ($nuevo['tier'] ?? '');
    $salario = plSalarioDeTier($tier, $ajustes['tiers'] ?? []);
    if ($salario === null) {
        return plResultado(false, 'error.tier_invalido');
    }

    $maximo = (int) ($ajustes['maxJugadores'] ?? 20);
    if (count($jugadores) >= $maximo) {
        return plResultado(false, 'error.max_jugadores', [
            'maximo' => $maximo,
            'actual' => count($jugadores),
        ]);
    }

    $cap      = (int) ($ajustes['salaryCap'] ?? 250);
    $actual   = plTotalSalarios($jugadores);
    $quedaria = $actual + $salario;
    if ($quedaria > $cap) {
        return plResultado(false, 'error.cap_superado', [
            'total'       => $quedaria,
            'cap'         => $cap,
            'disponible'  => max(0, $cap - $actual),
            'salario'     => $salario,
        ]);
    }

    return plResultado(true, null, ['salario' => $salario]);
}

// El salario del jugador que cambia de tier se SUSTITUYE en el total, no se
// suma. Sumarlo haría que bajar de S+ (60M) a A+ (25M) fuese rechazado por
// cap, que es exactamente lo contrario de lo que debe pasar. Es el error que
// más fácil se cuela en esta función.
function plValidarCambioTier(array $jugadores, string $jugadorId, string $tierNuevo, array $ajustes): array
{
    $salarioNuevo = plSalarioDeTier($tierNuevo, $ajustes['tiers'] ?? []);
    if ($salarioNuevo === null) {
        return plResultado(false, 'error.tier_invalido');
    }

    $encontrado    = false;
    $salarioViejo  = 0;
    foreach ($jugadores as $j) {
        if ((string) ($j['id'] ?? '') === $jugadorId) {
            $encontrado   = true;
            $salarioViejo = (int) ($j['salario'] ?? 0);
            break;
        }
    }
    if (!$encontrado) {
        return plResultado(false, 'error.jugador_no_encontrado');
    }

    $cap      = (int) ($ajustes['salaryCap'] ?? 250);
    $actual   = plTotalSalarios($jugadores);
    $quedaria = $actual - $salarioViejo + $salarioNuevo;
    if ($quedaria > $cap) {
        return plResultado(false, 'error.cap_superado', [
            'total'      => $quedaria,
            'cap'        => $cap,
            'disponible' => max(0, $cap - ($actual - $salarioViejo)),
            'salario'    => $salarioNuevo,
        ]);
    }

    return plResultado(true, null, ['salario' => $salarioNuevo]);
}

// El reparto de cláusulas nunca puede SUPERAR el presupuesto. Quedarse por
// debajo sí se permite: es un borrador, y existe para que nadie pierda media
// hora de reparto por no haber cuadrado todavía. El equipo queda INCOMPLETO
// hasta llegar al total exacto.
//
// $clausulas es un mapa jugadorId => cantidad, tal como llega del formulario.
function plValidarClausulas(array $jugadores, array $clausulas, array $ajustes): array
{
    $total = 0;
    foreach ($jugadores as $j) {
        $id = (string) ($j['id'] ?? '');
        $valor = $clausulas[$id] ?? 0;

        // Se rechaza cualquier cosa que no sea un entero >= 0. Un "40.5" o un
        // "-10" enviado a mano no puede convertirse silenciosamente en 40 ó 0.
        //
        // El signo se comprueba aparte del tipo: -10 ES un int válido para
        // is_int(), así que sin esta segunda condición una cláusula negativa
        // pasaría el filtro y además restaría del total, dejando repartir más
        // de 650M. ctype_digit() ya excluye el signo en la rama de cadena.
        $esEnteroTextual = is_string($valor) && ctype_digit($valor);
        if (!is_int($valor) && !$esEnteroTextual) {
            return plResultado(false, 'error.clausula_invalida', ['jugador' => $j['nombre'] ?? $id]);
        }
        if ((int) $valor < 0) {
            return plResultado(false, 'error.clausula_invalida', ['jugador' => $j['nombre'] ?? $id]);
        }
        $total += (int) $valor;
    }

    $presupuesto = (int) ($ajustes['presupuestoClausulas'] ?? 650);
    if ($total > $presupuesto) {
        return plResultado(false, 'error.clausulas_excedidas', [
            'total'       => $total,
            'presupuesto' => $presupuesto,
            'disponible'  => 0,
            'exceso'      => $total - $presupuesto,
        ]);
    }

    return plResultado(true, null, [
        'total'       => $total,
        'presupuesto' => $presupuesto,
        'disponible'  => $presupuesto - $total,
        'estado'      => plEstadoPresupuesto($total, $presupuesto),
    ]);
}

function plEstadoPresupuesto(int $total, int $presupuesto): string
{
    if ($total < $presupuesto) {
        return 'INCOMPLETO';
    }
    return $total === $presupuesto ? 'COMPLETO' : 'EXCEDIDO';
}

// ------------------------------------------------------------ autorización

// Qué pantalla es editable en cada fase. El admin se salta el ORDEN de las
// fases, pero no la aritmética de arriba: eso se comprueba igual para él.
function plPuedeEditarPlantilla(string $fase): bool
{
    return $fase === 'ROSTER';
}

function plPuedeEditarClausulas(string $fase): bool
{
    return $fase === 'CLAUSULAS';
}

function plPuedeMarcarClausulado(string $fase): bool
{
    return $fase === 'MERCADO';
}

// Las cuatro condiciones que tiene que cumplir un presidente para registrar
// una clausulación. Se llama en el camino del POST, no solo al decidir si se
// pinta el botón: ocultar el botón es presentación, y un formulario reenviado
// a mano con el equipoId de otro club tiene que ser rechazado igual.
function plPuedeClausular(array $usuario, array $jugador, string $equipoDelJugador, string $equipoComprador): bool
{
    $miEquipo = (string) ($usuario['equipoId'] ?? '');

    // 1. El presidente tiene equipo asignado.
    if ($miEquipo === '') {
        return false;
    }
    // 2. El jugador NO es de su propio equipo.
    if ($equipoDelJugador === $miEquipo) {
        return false;
    }
    // 3. El comprador es su propio equipo, nunca otro.
    if ($equipoComprador !== $miEquipo) {
        return false;
    }
    // 4. El jugador sigue disponible: no se revierte ni se roba una
    //    clausulación ya registrada por otro. Corregir es cosa del admin.
    if ((string) ($jugador['estado'] ?? 'DISPONIBLE') !== 'DISPONIBLE') {
        return false;
    }

    return true;
}
