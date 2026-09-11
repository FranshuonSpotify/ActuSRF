<?php
// dashboard/tests/test_equipos.php
// Self-check del paso 11: equipos y el importador desde la web pública.
//   /c/xampp/php/php.exe dashboard/tests/test_equipos.php
//
// admin_equipos.php no se renderiza: su primera línea es el Basic Auth, que
// lee config/secrets.php. Su lógica vive en almacen.php y se prueba ahí.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';

$DIR = $GLOBALS['PL_DIR_DATOS'];
$ids = static fn() => array_column(plCargarEquipos()['equipos'], 'id');

// ============================================= importador sobre una copia
$copia = $DIR . '/oficiales-prueba.json';
plGuardarJsonAtomico($copia, ['equipos' => [
    ['id' => 'teikoku', 'nombre' => 'Monte Olimpo', 'nombre_en' => 'Mount Olympus', 'abreviatura' => 'MOL',
     'abreviatura_en' => 'MOL', 'escudo' => 'https://x/esc.png', 'color1' => '#d45908', 'jugadores' => [['nombre' => 'X']]],
    ['id' => 'eq_2', 'nombre' => 'Criaturas de la Noche', 'nombre_en' => 'Children of the Night'],
    ['id' => 'eq_3', 'nombre' => 'Academia Plenilunio'],
    ['id' => 'eq_viejo', 'nombre' => 'Retirado', 'archivado' => true],
]]);

$r = plImportarEquiposDeWeb($copia);
plVerificar('el importador trae los 3 equipos no archivados', $r['ok'] && $r['importados'] === 3);
plVerificar('y NO trae el archivado', !in_array('eq_viejo', $ids(), true));
$mo = plBuscarEquipo('teikoku');
plVerificar('cada importado queda enlazado a su id de origen', $mo['equipoId'] === 'teikoku');
plVerificar('con su nombre en inglés, abreviatura, escudo y color',
    $mo['nombre_en'] === 'Mount Olympus' && $mo['abreviatura'] === 'MOL'
    && $mo['escudo'] === 'https://x/esc.png' && $mo['color1'] === '#d45908');
plVerificar('y activo', $mo['activo'] === true);
plVerificar('sin arrastrar la plantilla deportiva de la web', !isset($mo['jugadores']));
plVerificar('un equipo sin nombre_en en la web queda con nombre_en vacío, no inventado',
    plBuscarEquipo('eq_3')['nombre_en'] === '');

$r = plImportarEquiposDeWeb($copia);
plVerificar('ejecutarlo por segunda vez no importa nada', $r['ok'] && $r['importados'] === 0 && $r['saltados'] === 3);
plVerificar('y deja el número de equipos exactamente igual', count($ids()) === 3);

// ============================================= alta, edición, archivo
$r = plCrearEquipo('Recién Llegados', 'RLL');
plVerificar('crear un equipo a mano funciona', $r['ok'] === true);
$nuevo = plBuscarEquipo($r['id']);
plVerificar('queda con equipoId null: no está en la web', $nuevo['equipoId'] === null);
plVerificar('con un id propio que no puede chocar con uno de la web', str_starts_with($r['id'], 'eq_m_'));
plVerificar('y activo', $nuevo['activo'] === true);
plVerificar('crear un equipo sin nombre se rechaza', plCrearEquipo('   ', 'X')['ok'] === false);

plVerificar('editar el nombre funciona', plEditarEquipo('eq_3', 'Plenilunio', 'PLE', 'Lunar Prime')['ok'] === true);
$e3 = plBuscarEquipo('eq_3');
plVerificar('y guarda nombre, abreviatura y nombre en inglés',
    $e3['nombre'] === 'Plenilunio' && $e3['abreviatura'] === 'PLE' && $e3['nombre_en'] === 'Lunar Prime');
plVerificar('sin tocar el enlace con la web', $e3['equipoId'] === 'eq_3');
plVerificar('dejar el nombre vacío al editar se rechaza', plEditarEquipo('eq_3', '', 'PLE', '')['ok'] === false);
plVerificar('editar un equipo que no existe se rechaza', plEditarEquipo('no_existe', 'X', '', '')['ok'] === false);

plVerificar('archivar funciona', plCambiarActivoEquipo('eq_2', false)['ok'] === true);
plVerificar('y lo deja activo:false', plBuscarEquipo('eq_2')['activo'] === false);
$r = plImportarEquiposDeWeb($copia);
plVerificar('volver a importar NO reactiva ni duplica un equipo archivado aquí',
    plBuscarEquipo('eq_2')['activo'] === false && count($ids()) === 4 && $r['importados'] === 0);

// ============================================= incorporación a la temporada
// El orden natural de un primer uso es crear la temporada y DESPUÉS importar
// los equipos. Sin incorporación, esa temporada no tendría ningún equipo.
plCrearTemporada('2026-27', '2026/27');
$enTemp = static fn() => array_keys(plCargarTemporada('2026-27')['equipos'] ?? []);
plVerificar('la temporada nace con los activos (no con el archivado)',
    !in_array('eq_2', $enTemp(), true) && in_array('teikoku', $enTemp(), true));

plGuardarEquipoTemporada('2026-27', 'teikoku', ['jugadores' => [['id' => 'j1', 'nombre' => 'Endou', 'salario' => 75]]], 0);

$r = plCrearEquipo('Tardíos', 'TAR');
plVerificar('un equipo creado con la temporada abierta entra en ella', in_array($r['id'], $enTemp(), true));
plVerificar('con la plantilla vacía y rev 0',
    plCargarTemporada('2026-27')['equipos'][$r['id']] === ['rev' => 0, 'jugadores' => []]);

plCambiarActivoEquipo('eq_2', true);
plVerificar('reactivar un equipo lo incorpora a la temporada en curso', in_array('eq_2', $enTemp(), true));

plGuardarJsonAtomico($copia, ['equipos' => array_merge(plCargarJson($copia, [])['equipos'],
    [['id' => 'eq_ultimo', 'nombre' => 'Último en llegar']])]);
plImportarEquiposDeWeb($copia);
plVerificar('importar con la temporada abierta también incorpora al nuevo', in_array('eq_ultimo', $enTemp(), true));

$t = plCargarTemporada('2026-27')['equipos']['teikoku'];
plVerificar('incorporar a otros NO toca la plantilla ni el rev de los que ya estaban',
    $t['rev'] === 1 && $t['jugadores'][0]['nombre'] === 'Endou');

plCambiarActivoEquipo('teikoku', false);
plVerificar('archivar a mitad de temporada NO borra su plantilla de la temporada',
    (plCargarTemporada('2026-27')['equipos']['teikoku']['jugadores'][0]['nombre'] ?? '') === 'Endou');

// ============================================= el fichero REAL de la web
// Se importa desde el datos_oficiales.json de verdad, en solo lectura, a un
// equipos.json temporal. Lo que se comprueba es que no se toca ni un byte.
$real = realpath(__DIR__ . '/../../datos_oficiales.json');
plVerificar('el datos_oficiales.json real existe y se puede leer', $real !== false && is_readable($real));
if ($real !== false) {
    $hashAntes  = hash_file('sha256', $real);
    $lockAntes  = file_exists($real . '.lock');
    $esperados  = count(array_filter(plLeerJson($real, ['equipos' => []])['equipos'] ?? [],
        static fn($e) => empty($e['archivado']) && (string) ($e['id'] ?? '') !== ''));

    $GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos('pl_real_');
    $r = plImportarEquiposDeWeb($real);

    plVerificar("importa exactamente los $esperados equipos no archivados de la web",
        $r['ok'] && $r['importados'] === $esperados && count($ids()) === $esperados);
    plVerificar('y el fichero de la web pública queda con el mismo hash sha256',
        hash_file('sha256', $real) === $hashAntes);
    plVerificar('sin crear ningún .lock junto a él', file_exists($real . '.lock') === $lockAntes);

    plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
    $GLOBALS['PL_DIR_DATOS'] = $DIR;
}

// ============================================= guardas de la pantalla
$fuente = (string) file_get_contents(__DIR__ . '/../admin_equipos.php');
plVerificar('la pantalla exige Basic Auth antes de arrancar la sesión',
    strpos($fuente, 'requerirAdminBasicAuthConClaves') < strpos($fuente, 'session_start'));
plVerificar('todo POST valida CSRF y corta con 403',
    str_contains($fuente, 'plCsrfValido()') && str_contains($fuente, 'plCortar(403'));
plVerificar('sin header()+exit a pelo', !preg_match('/header\s*\(\s*[\'"]Location/', $fuente) && !str_contains($fuente, 'http_response_code('));
plVerificar('el panel de admin no usa plT(): va en español', !str_contains($fuente, 'plT('));
plVerificar('ninguna escritura apunta a datos_oficiales.json',
    !preg_match('/(plGuardarJsonAtomico|plActualizarJson|file_put_contents)\s*\([^)]*(OFICIALES|datos_oficiales)/', $fuente));

plArnesLimpiar($DIR);
plSalirConResultado();
