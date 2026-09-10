<?php
// dashboard/tests/test_admin_plantillas.php
// Self-check del paso 14: la pantalla de arbitraje del admin.
//   /c/xampp/php/php.exe dashboard/tests/test_admin_plantillas.php
//
// admin_plantillas.php no se renderiza (Basic Auth contra config/secrets.php).
// Toda su lógica vive en las operaciones de admin de almacen.php y en
// plPosiblesDuplicados() de dominio.php, y se prueba ahí.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$T = '2026-27';
plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
    ['id' => 'eq_c', 'nombre' => 'Gamma', 'activo' => true],
]]);
plCrearTemporada($T, '2026/27');

$j = static fn(string $id, string $nombre, string $tier, int $salario, int $clausula = 0) => ['id' => $id,
    'nombre' => $nombre, 'posicion' => 'MED', 'tier' => $tier, 'salario' => $salario, 'clausula' => $clausula,
    'estado' => 'DISPONIBLE', 'clausuladoPor' => null, 'clausuladoEn' => null];
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => [$j('a1', 'Kidou Yuuto', 'S', 40, 100), $j('a2', 'Sakúma', 'A+', 25)]], 0);
plGuardarEquipoTemporada($T, 'eq_b', ['jugadores' => [$j('b1', 'KIDOU  yuuto', 'S', 40), $j('b2', 'Endo Mamoru', 'S++', 75)]], 0);
plGuardarEquipoTemporada($T, 'eq_c', ['jugadores' => [$j('c1', 'Endou Mamoru', 'S++', 75), $j('c2', 'Sakuma', 'C', 2),
    $j('c3', 'Goenji', 'S', 40)]], 0);
plCambiarFase($T, 'MERCADO');

$en      = static fn(string $eq) => plEquipoEnTemporada('2026-27', $eq);
$jugador = static function (string $eq, string $id) use ($en): ?array {
    foreach ($en($eq)['jugadores'] as $x) {
        if ($x['id'] === $id) {
            return $x;
        }
    }
    return null;
};
$eventos = static fn() => plCargarJson(plRutaDatos('registro.json'), ['eventos' => []])['eventos'];

// ============================================= corrección de clausulaciones
$antes = count($eventos());
$r = plCorregirClausulacion($T, 'eq_a', 'a1', 'CLAUSULADO', 'eq_b', $en('eq_a')['rev']);
plVerificar('el admin puede marcar una clausulación, en cualquier fase', $r['ok'] === true);
$a1 = $jugador('eq_a', 'a1');
plVerificar('el jugador queda CLAUSULADO por el equipo elegido', $a1['estado'] === 'CLAUSULADO' && $a1['clausuladoPor'] === 'eq_b');
plVerificar('con su fecha', strtotime((string) $a1['clausuladoEn']) !== false);
$ev = $eventos();
plVerificar('y se añade exactamente UN evento CORRECCION', count($ev) === $antes + 1 && end($ev)['tipo'] === 'CORRECCION');
plVerificar('con el estado de antes y el de después',
    end($ev)['detalle']['antes']['estado'] === 'DISPONIBLE'
    && end($ev)['detalle']['despues']['estado'] === 'CLAUSULADO'
    && end($ev)['detalle']['despues']['clausuladoPor'] === 'eq_b');
plVerificar('hecho por el admin', end($ev)['actor'] === 'admin');

$r = plCorregirClausulacion($T, 'eq_a', 'a1', 'DISPONIBLE', '', $en('eq_a')['rev']);
$a1 = $jugador('eq_a', 'a1');
plVerificar('y puede revertirla: vuelve a Disponible', $r['ok'] === true && $a1['estado'] === 'DISPONIBLE');
plVerificar('limpiando comprador y fecha', $a1['clausuladoPor'] === null && $a1['clausuladoEn'] === null);
plVerificar('con su propio evento', count($eventos()) === $antes + 2);

$r = plCorregirClausulacion($T, 'eq_a', 'a1', 'DISPONIBLE', '', $en('eq_a')['rev']);
plVerificar('una corrección que no cambia nada no escribe evento', $r['ok'] === true && count($eventos()) === $antes + 2);

$rev = $en('eq_a')['rev'];
plVerificar('clausular un jugador a su propio equipo se rechaza',
    plCorregirClausulacion($T, 'eq_a', 'a1', 'CLAUSULADO', 'eq_a', $rev)['ok'] === false);
plVerificar('a un equipo que no está en la temporada, también',
    plCorregirClausulacion($T, 'eq_a', 'a1', 'CLAUSULADO', 'eq_zz', $rev)['ok'] === false);
plVerificar('clausulado sin comprador, también',
    plCorregirClausulacion($T, 'eq_a', 'a1', 'CLAUSULADO', '', $rev)['ok'] === false);
plVerificar('un estado inventado, también',
    plCorregirClausulacion($T, 'eq_a', 'a1', 'VENDIDO', 'eq_b', $rev)['ok'] === false);
plVerificar('un jugador inexistente, también',
    plCorregirClausulacion($T, 'eq_a', 'nadie', 'CLAUSULADO', 'eq_b', $rev)['ok'] === false);
plVerificar('y con un rev desfasado, también',
    plCorregirClausulacion($T, 'eq_a', 'a1', 'CLAUSULADO', 'eq_b', $rev - 1)['ok'] === false);
plVerificar('ninguno de los rechazos escribió evento', count($eventos()) === $antes + 2);
plVerificar('ni cambió al jugador', $jugador('eq_a', 'a1')['estado'] === 'DISPONIBLE');

// ============================================= plantilla en fase MERCADO
// El admin se salta el orden de las fases, no la aritmética.
$r = plAdminAltaJugador($T, 'eq_a', ['nombre' => 'Refuerzo', 'posicion' => 'DEF', 'tier' => 'C'], $en('eq_a')['rev']);
plVerificar('en MERCADO el admin puede añadir un jugador', $r['ok'] === true && $jugador('eq_a', $r['id'] ?? '') !== null);
plVerificar('con el salario sacado del tier', ($jugador('eq_a', $r['id'] ?? '')['salario'] ?? null) === 2);

// Alfa: 40 + 25 + 2 = 67. Tres S++ más serían 292.
foreach (['X', 'Y'] as $n) {
    plAdminAltaJugador($T, 'eq_a', ['nombre' => "Crack $n", 'posicion' => 'ATA', 'tier' => 'S++'], $en('eq_a')['rev']);
}
plVerificar('dos S++ caben: 67 + 150 = 217', plTotalSalarios($en('eq_a')['jugadores']) === 217);
$r = plAdminAltaJugador($T, 'eq_a', ['nombre' => 'Crack Z', 'posicion' => 'ATA', 'tier' => 'S++'], $en('eq_a')['rev']);
plVerificar('pero el admin tampoco puede pasar del Salary Cap', $r['ok'] === false && $r['error'] === 'error.cap_superado');
plVerificar('y el mensaje en español dice el total que quedaría',
    str_contains(plTextoEs($r['error'], $r['datos']), '292M'));

$r = plAdminEditarJugador($T, 'eq_a', 'a2', 'Sakúma', 'MED', 'S++', $en('eq_a')['rev']);
plVerificar('ni subir un tier por encima del cap', $r['ok'] === false && $r['error'] === 'error.cap_superado_cambio');
plVerificar('y el mensaje dice «cambiar a este tier», no «añadir»',
    str_contains(plTextoEs($r['error'], $r['datos']), 'No puedes cambiar a este tier'));
$r = plAdminEditarJugador($T, 'eq_a', 'a2', 'Sakuma Jirou', 'DEF', 'A+', $en('eq_a')['rev']);
plVerificar('pero sí editar nombre, posición y tier dentro del cap',
    $r['ok'] === true && $jugador('eq_a', 'a2')['nombre'] === 'Sakuma Jirou' && $jugador('eq_a', 'a2')['posicion'] === 'DEF');

$lista = $en('eq_a')['jugadores'];
while (count($lista) < 20) {
    $lista[] = $j('r' . count($lista), 'Relleno ' . count($lista), 'C', 2);
}
plGuardarEquipoTemporada($T, 'eq_a', ['jugadores' => $lista], $en('eq_a')['rev']);
$r = plAdminAltaJugador($T, 'eq_a', ['nombre' => 'El 21', 'posicion' => 'MED', 'tier' => 'C'], $en('eq_a')['rev']);
plVerificar('ni pasar de 20 jugadores', $r['ok'] === false && $r['error'] === 'error.max_jugadores');

$n = count($en('eq_a')['jugadores']);
$r = plAdminBorrarJugador($T, 'eq_a', 'r19', $en('eq_a')['rev']);
plVerificar('borrar un jugador funciona', $r['ok'] === true && count($en('eq_a')['jugadores']) === $n - 1);
plVerificar('borrar uno inexistente se rechaza', plAdminBorrarJugador($T, 'eq_a', 'nadie', $en('eq_a')['rev'])['ok'] === false);
$r = plAdminAltaJugador($T, 'eq_a', ['nombre' => 'Tarde', 'posicion' => 'MED', 'tier' => 'C'], 0);
plVerificar('las operaciones del admin también respetan el rev', $r['ok'] === false);

// ============================================= cláusulas
$rev = $en('eq_b')['rev'];
$r = plAdminGuardarClausulas($T, 'eq_b', ['b1' => 651, 'b2' => 0], $rev);
plVerificar('el admin no puede pasar de 650M en cláusulas', $r['ok'] === false && $r['error'] === 'error.clausulas_excedidas');
$r = plAdminGuardarClausulas($T, 'eq_b', ['b1' => -1, 'b2' => 0], $rev);
plVerificar('ni meter una negativa', $r['ok'] === false);
$r = plAdminGuardarClausulas($T, 'eq_b', ['b1' => 400, 'b2' => 250], $rev);
plVerificar('650M exactos se guardan', $r['ok'] === true
    && array_column($en('eq_b')['jugadores'], 'clausula', 'id') === ['b1' => 400, 'b2' => 250]);
plVerificar('y sin tocar los salarios', array_column($en('eq_b')['jugadores'], 'salario') === [40, 75]);

// ============================================= nombres repetidos (puro)
$pares = plPosiblesDuplicados(plCargarTemporada($T));
$hay = static function (string $x, string $y, string $tipo) use ($pares): bool {
    foreach ($pares as $p) {
        $ids = [$p['a']['jugadorId'], $p['b']['jugadorId']];
        if (in_array($x, $ids, true) && in_array($y, $ids, true) && $p['tipo'] === $tipo) {
            return true;
        }
    }
    return false;
};
plVerificar('«Kidou Yuuto» y «KIDOU  yuuto», de equipos distintos, salen como iguales', $hay('a1', 'b1', 'igual'));
plVerificar('«Endo Mamoru» y «Endou Mamoru» salen como parecidos: la errata típica', $hay('b2', 'c1', 'parecido'));
plVerificar('«Goenji» no se empareja con nadie', !array_filter($pares,
    static fn($p) => $p['a']['jugadorId'] === 'c3' || $p['b']['jugadorId'] === 'c3'));

$temporal = plCargarTemporada($T);
$temporal['equipos']['eq_c']['jugadores'][] = $j('c9', 'goenji', 'C', 2);
plVerificar('también se avisan los repetidos dentro del mismo equipo', (static function () use ($temporal): bool {
    foreach (plPosiblesDuplicados($temporal) as $p) {
        if ($p['a']['equipoId'] === 'eq_c' && $p['b']['equipoId'] === 'eq_c' && $p['tipo'] === 'igual') {
            return true;
        }
    }
    return false;
})());
plVerificar('entre nombres de menos de 4 letras una letra de diferencia no avisa',
    plPosiblesDuplicados(['equipos' => ['x' => ['jugadores' => [['id' => '1', 'nombre' => 'Leo'], ['id' => '2', 'nombre' => 'Lea']]]]]) === []);

// ============================================= registro, del más reciente al más antiguo
plRegistrarEvento('FASE', 'admin', 'admin', ['de' => 'MERCADO', 'a' => 'CERRADA']);
$recientes = plCargarRegistroReciente(200);
plVerificar('el registro se muestra del más reciente al más antiguo',
    $recientes[0]['tipo'] === 'FASE' && ($recientes[0]['detalle']['a'] ?? '') === 'CERRADA');
plVerificar('con actor y sello de tiempo', $recientes[0]['actor'] === 'admin' && strtotime($recientes[0]['ts']) !== false);
plVerificar('y el límite recorta por los más viejos', count(plCargarRegistroReciente(1)) === 1
    && plCargarRegistroReciente(1)[0]['tipo'] === 'FASE');

// ============================================= guardas de la pantalla y del almacén
$pantalla = (string) file_get_contents(__DIR__ . '/../admin_plantillas.php');
$almacen  = (string) file_get_contents(__DIR__ . '/../almacen.php');
plVerificar('la pantalla exige Basic Auth antes de arrancar la sesión',
    strpos($pantalla, 'requerirAdminBasicAuthConClaves') < strpos($pantalla, 'session_start'));
plVerificar('todo POST valida CSRF y corta con 403',
    str_contains($pantalla, 'plCsrfValido()') && str_contains($pantalla, 'plCortar(403'));
plVerificar('sin header()+exit a pelo', !preg_match('/header\s*\(\s*[\'"]Location/', $pantalla) && !str_contains($pantalla, 'http_response_code('));
plVerificar('el panel de admin no usa plT(): va en español', !str_contains($pantalla, 'plT('));
plVerificar('la vuelta no se toma de una URL del formulario (sin redirección abierta)',
    !preg_match('/plRedirigir\s*\(\s*\$_(POST|GET|REQUEST)/', $pantalla) && !str_contains($pantalla, "\$_POST['volver']"));
plVerificar('las operaciones de admin validan la plantilla con plValidarAltaJugador',
    str_contains($almacen, 'plValidarAltaJugador('));
plVerificar('y las cláusulas con plValidarClausulas', str_contains($almacen, 'plValidarClausulas('));

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
