<?php
// dashboard/tests/test_tiers.php
// Self-check del paso 13: salarios de los tiers, congelados por temporada.
//   /c/xampp/php/php.exe dashboard/tests/test_tiers.php
//
// admin_tiers.php no se renderiza (Basic Auth contra config/secrets.php). Su
// lógica vive en almacen.php y se prueba ahí; el efecto sobre el presidente se
// comprueba renderizando su pantalla de plantilla.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

$salarioDe = static fn(string $codigo) => plSalarioDeTier($codigo, plCargarTiers()['tiers']);
$codigos   = static fn() => array_column(plCargarTiers()['tiers'], 'codigo');
$ORDEN     = ['S++', 'S+', 'S', 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C'];

plVerificar('de partida están los diez tiers oficiales, en su orden', $codigos() === $ORDEN);

plGuardarEquipos(['equipos' => [['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true]]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_a', 'nombre' => 'Juan', 'email' => 'a@x.es', 'hash' => 'x', 'equipoId' => 'eq_a', 'activo' => true],
]]);
plCrearTemporada('2026-27', '2026/27');
$ficheroTemporada = plRutaDatos('temporada-2026-27.json');
$hashTemporada    = hash_file('sha256', $ficheroTemporada);

// -- guardar salarios -----------------------------------------------------
$r = plGuardarSalariosTiers(['S++' => '90', 'C' => '3']);
plVerificar('cambiar dos salarios funciona', $r['ok'] === true);
plVerificar('y quedan escritos en tiers.json', $salarioDe('S++') === 90 && $salarioDe('C') === 3);
plVerificar('sin tocar los que no se enviaron', $salarioDe('S+') === 60 && $salarioDe('A') === 18);
plVerificar('y conservando el orden del desplegable', $codigos() === $ORDEN);

// -- la temporada en curso no se entera ------------------------------------
plVerificar('el fichero de la temporada en curso queda byte a byte igual',
    hash_file('sha256', $ficheroTemporada) === $hashTemporada);
plVerificar('su ajustes.tiers sigue diciendo S++ = 75',
    plSalarioDeTier('S++', plCargarTemporada('2026-27')['ajustes']['tiers']) === 75);

$h = plArnesPeticion(__DIR__ . '/../plantilla.php', ['pl_usuario_id' => 'u_a'])['html'] ?? '';
plVerificar('y la pantalla de plantilla del presidente sigue ofreciendo S++ a 75M', str_contains($h, 'S++ · 75M'));
plVerificar('no el salario nuevo', !str_contains($h, 'S++ · 90M'));

// -- la temporada siguiente sí ------------------------------------------------
plCrearTemporada('2027-28', '2027/28');
plVerificar('una temporada creada después congela el salario nuevo',
    plSalarioDeTier('S++', plCargarTemporada('2027-28')['ajustes']['tiers']) === 90);

// -- códigos que no existen ----------------------------------------------------
$r = plGuardarSalariosTiers(['D' => '1', 'C+' => '4', 'S' => '41']);
plVerificar('un código que no es uno de los diez se ignora', $r['ok'] === true && !in_array('D', $codigos(), true));
plVerificar('ni aparece C+', !in_array('C+', $codigos(), true));
plVerificar('siguen siendo exactamente diez', count($codigos()) === 10);
plVerificar('y el código válido del mismo envío sí se guarda', $salarioDe('S') === 41);

// -- valores que no valen: se rechaza el lote ENTERO ---------------------------
$antes = plCargarTiers();
$r = plGuardarSalariosTiers(['S++' => '100', 'A' => '-5']);
plVerificar('un salario negativo se rechaza', $r['ok'] === false);
plVerificar('y no se guarda NADA del envío, ni siquiera el valor bueno', plCargarTiers() === $antes);

$r = plGuardarSalariosTiers(['S++' => '7.5']);
plVerificar('un salario decimal se rechaza', $r['ok'] === false && plCargarTiers() === $antes);
$r = plGuardarSalariosTiers(['S++' => 'mucho']);
plVerificar('un salario que no es un número se rechaza', $r['ok'] === false && plCargarTiers() === $antes);
$r = plGuardarSalariosTiers(['S++' => '']);
plVerificar('un salario vacío se rechaza', $r['ok'] === false && plCargarTiers() === $antes);

$r = plGuardarSalariosTiers(['B-' => '0']);
plVerificar('un salario de 0 se acepta: la especificación solo excluye negativos y decimales',
    $r['ok'] === true && $salarioDe('B-') === 0);

// -- guardas de la pantalla ------------------------------------------------------
$fuente = (string) file_get_contents(__DIR__ . '/../admin_tiers.php');
plVerificar('la pantalla exige Basic Auth antes de arrancar la sesión',
    strpos($fuente, 'requerirAdminBasicAuthConClaves') < strpos($fuente, 'session_start'));
plVerificar('todo POST valida CSRF y corta con 403',
    str_contains($fuente, 'plCsrfValido()') && str_contains($fuente, 'plCortar(403'));
plVerificar('sin header()+exit a pelo', !preg_match('/header\s*\(\s*[\'"]Location/', $fuente) && !str_contains($fuente, 'http_response_code('));
plVerificar('el panel de admin no usa plT(): va en español', !str_contains($fuente, 'plT('));
plVerificar('no aparece ningún tier prohibido como código creable', !preg_match("/'C\\+'|'C-'|'D'/", $fuente));
plVerificar('el aviso de congelado está en la pantalla, visible',
    str_contains($fuente, 'Estos salarios se congelan al crear una temporada'));

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
