<?php
// dashboard/tests/test_almacen.php
// Self-check del almacén. Ejecutar desde la raíz del proyecto:
//   /c/xampp/php/php.exe dashboard/tests/test_almacen.php
//
// Todo ocurre sobre un directorio temporal: el override PL_DIR_DATOS se fija
// ANTES de incluir almacen.php, así que dashboard/data/ no se toca.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';

// -- semillas ----------------------------------------------------------
plVerificar('tiers.json inexistente devuelve los diez tiers oficiales',
    count(plCargarTiers()['tiers']) === 10);
plVerificar('S++ arranca a 75M',
    plSalarioDeTier('S++', plCargarTiers()['tiers']) === 75);
plVerificar('equipos.json inexistente devuelve la semilla vacía',
    plCargarEquipos() === ['equipos' => []]);
plVerificar('temporadas.json inexistente devuelve activa=null',
    plCargarTemporadas() === ['activa' => null, 'temporadas' => []]);
plVerificar('leer una semilla NO escribe el fichero en disco',
    !file_exists(plRutaDatos('tiers.json')) && !file_exists(plRutaDatos('equipos.json')));
plVerificar('sin temporadas, plTemporadaActiva devuelve null',
    plTemporadaActiva() === null);

// -- creación de temporada --------------------------------------------
plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa',  'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta',  'activo' => true],
    ['id' => 'eq_z', 'nombre' => 'Zeta',  'activo' => false],
]]);

$r = plCrearTemporada('2026-27', '2026/27');
plVerificar('crear la temporada devuelve ok', $r['ok'] === true);

$t = plCargarTemporada('2026-27');
plVerificar('solo entran los equipos activos',
    array_keys($t['equipos']) === ['eq_a', 'eq_b']);
plVerificar('cada equipo nace con rev 0 y sin jugadores',
    $t['equipos']['eq_a'] === ['rev' => 0, 'jugadores' => []]);
plVerificar('los ajustes llevan cap 250, presupuesto 650 y máximo 20',
    $t['ajustes']['salaryCap'] === 250
    && $t['ajustes']['presupuestoClausulas'] === 650
    && $t['ajustes']['maxJugadores'] === 20);
plVerificar('ajustes.tiers queda congelado con los diez tiers',
    count($t['ajustes']['tiers']) === 10);
plVerificar('la temporada pasa a ser la activa',
    plTemporadaActiva()['id'] === '2026-27');
plVerificar('y nace en fase ROSTER',
    plTemporadaActiva()['fase'] === 'ROSTER');
plVerificar('crear una temporada con un id que ya existe se rechaza',
    plCrearTemporada('2026-27', 'duplicada')['ok'] === false);

// -- TIERS CONGELADOS --------------------------------------------------
// Es la propiedad que hace segura la pantalla de tiers del admin.
$tiers = plCargarTiers();
$tiers['tiers'][0]['salario'] = 90;   // S++ de 75M a 90M
plGuardarTiers($tiers);

plVerificar('editar tiers.json NO altera la temporada en curso',
    plSalarioDeTier('S++', plCargarTemporada('2026-27')['ajustes']['tiers']) === 75);

plCrearTemporada('2027-28', '2027/28');
plVerificar('la temporada NUEVA sí congela el salario nuevo',
    plSalarioDeTier('S++', plCargarTemporada('2027-28')['ajustes']['tiers']) === 90);
plVerificar('y la anterior sigue intacta',
    plSalarioDeTier('S++', plCargarTemporada('2026-27')['ajustes']['tiers']) === 75);

// -- fases -------------------------------------------------------------
plVerificar('cambiar a CLAUSULAS funciona',
    plCambiarFase('2027-28', 'CLAUSULAS')['ok'] === true);
plVerificar('y queda reflejado', plTemporadaActiva()['fase'] === 'CLAUSULAS');
plVerificar('una fase inventada se rechaza',
    plCambiarFase('2027-28', 'PRETEMPORADA')['ok'] === false);
plVerificar('la fase inválida no cambió nada',
    plTemporadaActiva()['fase'] === 'CLAUSULAS');

// -- REV OPTIMISTA -----------------------------------------------------
$jugadores = [['id' => 'j1', 'nombre' => 'Uno', 'posicion' => 'MED',
               'tier' => 'S+', 'salario' => 60, 'clausula' => 0,
               'estado' => 'DISPONIBLE', 'clausuladoPor' => null]];

$g = plGuardarEquipoTemporada('2026-27', 'eq_a', ['jugadores' => $jugadores], 0);
plVerificar('guardar con el rev correcto funciona', $g['ok'] === true);
plVerificar('el rev se incrementa exactamente en 1',
    plCargarTemporada('2026-27')['equipos']['eq_a']['rev'] === 1);

// El copresidente que tenía la pantalla abierta con rev 0 le da a guardar.
$g2 = plGuardarEquipoTemporada('2026-27', 'eq_a', ['jugadores' => []], 0);
plVerificar('un rev desfasado se rechaza', $g2['ok'] === false);
plVerificar('con error.rev_desfasado', $g2['error'] === 'error.rev_desfasado');
plVerificar('el rechazo NO modifica el fichero: el jugador sigue ahí',
    count(plCargarTemporada('2026-27')['equipos']['eq_a']['jugadores']) === 1);
plVerificar('y devuelve el rev real para que la pantalla pueda recargar',
    $g2['rev'] === 1);

plVerificar('reintentar con el rev correcto sí entra',
    plGuardarEquipoTemporada('2026-27', 'eq_a', ['jugadores' => []], 1)['ok'] === true);
plVerificar('el rev va por 2',
    plCargarTemporada('2026-27')['equipos']['eq_a']['rev'] === 2);

plVerificar('guardar un equipo que no está en la temporada se rechaza',
    plGuardarEquipoTemporada('2026-27', 'eq_z', ['jugadores' => []], 0)['error'] === 'error.equipo_no_en_temporada');
plVerificar('guardar el equipo A no tocó el rev del equipo B',
    plCargarTemporada('2026-27')['equipos']['eq_b']['rev'] === 0);

// -- registro append-only ---------------------------------------------
plRegistrarEvento('FASE', 'admin', 'admin', ['de' => 'ROSTER', 'a' => 'CLAUSULAS']);
plRegistrarEvento('CLAUSULACION', 'u_1', 'Juan', ['jugador' => 'Uno']);
plRegistrarEvento('CORRECCION', 'admin', 'admin', ['jugador' => 'Uno']);

$eventos = plCargarJson(plRutaDatos('registro.json'), ['eventos' => []])['eventos'];
plVerificar('tres eventos, en orden de llegada', count($eventos) === 3);
plVerificar('el primero sigue siendo el primero y no se alteró',
    $eventos[0]['tipo'] === 'FASE' && $eventos[0]['detalle'] === ['de' => 'ROSTER', 'a' => 'CLAUSULAS']);
plVerificar('el último es el más reciente', $eventos[2]['tipo'] === 'CORRECCION');
plVerificar('cada evento lleva actor y sello de tiempo',
    $eventos[1]['actor'] === 'u_1' && strtotime($eventos[1]['ts']) !== false);

// -- usuarios ----------------------------------------------------------
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_1', 'nombre' => 'Juan', 'email' => 'Juan@Ejemplo.com',
     'hash' => password_hash('secreta', PASSWORD_DEFAULT), 'equipoId' => 'eq_a', 'activo' => true],
    ['id' => 'u_2', 'nombre' => 'Ana', 'email' => 'ana@ejemplo.com',
     'hash' => password_hash('otra', PASSWORD_DEFAULT), 'equipoId' => 'eq_a', 'activo' => false],
]]);

plVerificar('buscar por email ignora mayúsculas y espacios',
    plBuscarUsuarioPorEmail('  juan@ejemplo.com ')['id'] === 'u_1');
plVerificar('un email inexistente devuelve null',
    plBuscarUsuarioPorEmail('nadie@ejemplo.com') === null);
plVerificar('dos usuarios pueden compartir equipo: son copresidentes',
    plBuscarUsuarioPorEmail('ana@ejemplo.com')['equipoId'] === 'eq_a');

$_SESSION = ['pl_usuario_id' => 'u_1'];
plVerificar('plUsuarioActual devuelve al usuario de la sesión',
    plUsuarioActual()['nombre'] === 'Juan');

$_SESSION = ['pl_usuario_id' => 'u_2'];
plVerificar('un usuario desactivado no tiene sesión válida',
    plUsuarioActual() === null);

$_SESSION = ['pl_usuario_id' => 'u_borrado'];
plVerificar('un usuario que ya no existe tampoco',
    plUsuarioActual() === null);

$_SESSION = [];
plVerificar('sin sesión, null', plUsuarioActual() === null);

// -- concurrencia REAL: varios procesos escribiendo el registro a la vez -----
// No se simula: se lanzan cuatro procesos PHP de verdad, cada uno añadiendo 40
// eventos al mismo registro.json, todos a la vez. Con la lectura fuera del lock
// se pierden eventos; con plActualizarJson() tienen que quedar los 160.
$hijo = sys_get_temp_dir() . '/pl_hijo_' . uniqid() . '.php';
file_put_contents($hijo, '<?php
$GLOBALS["PL_DIR_DATOS"] = $argv[1];
require ' . var_export((string) realpath(__DIR__ . '/../almacen.php'), true) . ';
for ($i = 0; $i < (int) $argv[3]; $i++) {
    plRegistrarEvento("PRUEBA", $argv[2], $argv[2], ["i" => $i]);
}
');
$dirConc  = plArnesDirDatos('pl_conc_');
$procesos = [];
for ($p = 0; $p < 4; $p++) {
    $tuberias   = [];
    $procesos[] = proc_open([PHP_BINARY, $hijo, $dirConc, "p$p", '40'], [], $tuberias);
}
foreach ($procesos as $proc) {
    proc_close($proc);   // espera a que termine
}
$enDisco = plCargarJson($dirConc . '/registro.json', ['eventos' => []])['eventos'];
plVerificar('4 procesos × 40 eventos simultáneos: quedan los 160, ninguno perdido', count($enDisco) === 160);
$porActor = array_count_values(array_column($enDisco, 'actor'));
plVerificar('y cada proceso conserva sus 40', $porActor === ['p0' => 40, 'p1' => 40, 'p2' => 40, 'p3' => 40]
    || (count($porActor) === 4 && min($porActor) === 40 && max($porActor) === 40));
plVerificar('sin temporales pl_* abandonados por la carrera', count(glob($dirConc . '/pl_*') ?: []) === 0);
unlink($hijo);
plArnesLimpiar($dirConc);

// -- concurrencia con LECTORES: pantallas leyendo mientras otros guardan -----
// Los GET leen sin pasar por plActualizarJson. En Windows, un lector con el
// fichero abierto hace fallar el rename() del escritor si no hay lock
// compartido; en Linux no, pero el test tiene que pasar en los dos.
$escritor = sys_get_temp_dir() . '/pl_escritor_' . uniqid() . '.php';
$lector   = sys_get_temp_dir() . '/pl_lector_' . uniqid() . '.php';
$almacen  = var_export((string) realpath(__DIR__ . '/../almacen.php'), true);
file_put_contents($escritor, '<?php
$GLOBALS["PL_DIR_DATOS"] = $argv[1];
require ' . $almacen . ';
$fallos = 0;
for ($i = 0; $i < 40; $i++) {
    if (!plRegistrarEvento("PRUEBA", $argv[2], $argv[2], ["i" => $i])) { $fallos++; }
}
file_put_contents($argv[1] . "/fallos-" . $argv[2] . ".txt", (string) $fallos);
');
file_put_contents($lector, '<?php
$GLOBALS["PL_DIR_DATOS"] = $argv[1];
require ' . $almacen . ';
for ($i = 0; $i < 3000; $i++) { plCargarJson(plRutaDatos("registro.json"), ["eventos" => []]); }
');
$dirLect  = plArnesDirDatos('pl_lect_');
$procesos = [];
// Los lectores arrancan primero para que ya estén leyendo cuando escriben los otros.
foreach ([[$lector], [$lector], [$escritor, 'w1'], [$escritor, 'w2']] as $args) {
    $tuberias   = [];
    $procesos[] = proc_open(array_merge([PHP_BINARY, $args[0], $dirLect], array_slice($args, 1)),
        [1 => ['file', $dirLect . '/salida.txt', 'a'], 2 => ['file', $dirLect . '/salida.txt', 'a']], $tuberias);
}
foreach ($procesos as $proc) {
    proc_close($proc);
}
$enDisco = plCargarJson($dirLect . '/registro.json', ['eventos' => []])['eventos'];
$fallidos = (int) @file_get_contents($dirLect . '/fallos-w1.txt') + (int) @file_get_contents($dirLect . '/fallos-w2.txt');
$salida   = (string) @file_get_contents($dirLect . '/salida.txt');
plVerificar('2 escritores × 40 con 2 lectores martilleando: los 80 eventos en disco', count($enDisco) === 80);
plVerificar('ningún guardado devolvió fallo', $fallidos === 0);
plVerificar('y ni un solo aviso de rename denegado', !str_contains($salida, 'rename'));
unlink($escritor);
unlink($lector);
plArnesLimpiar($dirLect);

// -- limpieza ----------------------------------------------------------
plVerificar('no quedan ficheros temporales pl_* en el directorio de datos',
    count(glob($GLOBALS['PL_DIR_DATOS'] . '/pl_*') ?: []) === 0);

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
