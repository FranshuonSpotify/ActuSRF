<?php
// supertecnicas/tests/test_lib.php
// Self-check sin framework: cada verificarX() imprime FAIL y corta con
// exit(1) al primer fallo. Ejecutar con: php supertecnicas/tests/test_lib.php

require_once __DIR__ . '/../lib.php';

$fallos = 0;

function verificar($descripcion, $condicion) {
    global $fallos;
    if ($condicion) {
        echo "OK   $descripcion\n";
    } else {
        echo "FAIL $descripcion\n";
        $fallos++;
    }
}

// -- stNormalizarTexto -------------------------------------------------
verificar(
    'normaliza acentos y mayúsculas',
    stNormalizarTexto('Montaña Épica') === 'montana epica'
);
verificar(
    'colapsa espacios extra',
    stNormalizarTexto('  Monte   Olimpo  ') === 'monte olimpo'
);
verificar(
    'quita símbolos que no son letra/número',
    stNormalizarTexto('a.b-c!d') === 'a b c d'
);

// -- stCargarJson / stGuardarJsonAtomico -------------------------------
$dirTmp = sys_get_temp_dir() . '/st_test_' . uniqid();
mkdir($dirTmp);
$rutaTmp = $dirTmp . '/prueba.json';

verificar(
    'stCargarJson devuelve el valor por defecto si el fichero no existe',
    stCargarJson($rutaTmp, ['x' => 1]) === ['x' => 1]
);

$ok = stGuardarJsonAtomico($rutaTmp, ['equipos' => [1, 2, 3]]);
verificar('stGuardarJsonAtomico devuelve true', $ok === true);
verificar(
    'stGuardarJsonAtomico escribe un JSON legible por stCargarJson',
    stCargarJson($rutaTmp, []) === ['equipos' => [1, 2, 3]]
);
verificar(
    'no quedan ficheros temporales sueltos en el directorio',
    count(glob($dirTmp . '/st_*')) === 0
);

unlink($rutaTmp);
@unlink($rutaTmp . '.lock');
rmdir($dirTmp);

// -- stEquiposActivos / stBuscarEquipoPorId / stCodigoPorDefecto -------
$dataPrueba = [
    'equipos' => [
        ['id' => 'a', 'nombre' => 'Alfa FC', 'ciudad' => 'Ciudad Alfa'],
        ['id' => 'b', 'nombre' => 'Beta FC', 'ciudad' => 'Ciudad Beta', 'archivado' => true],
    ],
];

$activos = stEquiposActivos($dataPrueba);
verificar('stEquiposActivos excluye los archivados', count($activos) === 1 && $activos[0]['id'] === 'a');

$idx = stBuscarEquipoPorId($dataPrueba, 'b');
verificar('stBuscarEquipoPorId encuentra por id', $idx === 1);
verificar('stBuscarEquipoPorId devuelve null si no existe', stBuscarEquipoPorId($dataPrueba, 'z') === null);

$defecto = stCodigoPorDefecto(['nombre' => 'Alfa FC', 'ciudad' => 'Ciudad Alfa']);
verificar(
    'stCodigoPorDefecto normaliza nombre y ciudad',
    $defecto === ['codigo' => 'alfa fc', 'pin' => 'ciudad alfa']
);

// -- constantes ---------------------------------------------------------
verificar('ST_MAX_SUPERTECNICAS es 4', ST_MAX_SUPERTECNICAS === 4);
verificar('ST_TIPOS tiene 5 valores', count(ST_TIPOS) === 5);
verificar('ST_AFINIDADES tiene 6 valores', count(ST_AFINIDADES) === 6);

echo "\n";
if ($fallos > 0) {
    echo "$fallos comprobación(es) fallida(s).\n";
    exit(1);
}
echo "Todas las comprobaciones pasan.\n";
exit(0);
