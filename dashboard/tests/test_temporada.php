<?php
// dashboard/tests/test_temporada.php
// Self-check del paso 04: máquina de fases, creación de temporada e informe
// de equipos incompletos. Ejecutar desde la raíz del proyecto:
//   /c/xampp/php/php.exe dashboard/tests/test_temporada.php
//
// admin.php NO se renderiza con el arnés: su primera línea ejecutable es el
// Basic Auth, que en CLI responde 401 y hace exit(), matando el proceso del
// test. Se comprueba la lógica que la pantalla llama, que es donde están las
// consecuencias — la guarda en sí la cubre supertecnicas/ desde hace tiempo.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';

// -- la pantalla exige Basic Auth antes que nada -----------------------
$fuente = (string) file_get_contents(__DIR__ . '/../admin.php');
$posAuth = strpos($fuente, 'requerirAdminBasicAuthConClaves');
$posSesion = strpos($fuente, 'session_start');
plVerificar('admin.php invoca el Basic Auth del repo', $posAuth !== false);
plVerificar('y lo hace ANTES de arrancar la sesión',
    $posAuth !== false && $posSesion !== false && $posAuth < $posSesion);
plVerificar('usa las claves propias de dashboard, no las de otra herramienta',
    str_contains($fuente, "'admin_dashboard_user'") && str_contains($fuente, "'admin_dashboard_pass_hash'"));
plVerificar('todo POST valida CSRF', str_contains($fuente, 'plCsrfValido()'));
plVerificar('y corta con 403 si falla', str_contains($fuente, 'plCortar(403'));
plVerificar('sin header()+exit a pelo: todo sale por plRedirigir/plCortar',
    !preg_match('/header\s*\(\s*[\'"]Location/', $fuente) && !str_contains($fuente, 'http_response_code('));

// -- toda pantalla de admin requiere chrome.php --------------------------
// Nunca se renderizan aquí (el Basic Auth mata el proceso en CLI), así que
// esto es lo único que puede cazar un require olvidado: admin.php lo tuvo
// hasta este mismo paso — llamaba a plCabeceraAdmin() sin requerir el
// fichero que la define, y como nada la renderiza, ningún test lo vio hasta
// que dio un 500 real en producción.
foreach (['admin.php', 'admin_equipos.php', 'admin_presidentes.php', 'admin_tiers.php', 'admin_plantillas.php'] as $pantalla) {
    $src = (string) file_get_contents(__DIR__ . '/../' . $pantalla);
    plVerificar("$pantalla requiere chrome.php (de donde salen plCabeceraAdmin/plPieAdmin)",
        str_contains($src, "require_once __DIR__ . '/chrome.php'"));
}

// -- secrets.example.php ------------------------------------------------
$ejemplo = (string) file_get_contents(__DIR__ . '/../../config/secrets.example.php');
plVerificar('secrets.example.php declara las dos claves nuevas',
    str_contains($ejemplo, 'admin_dashboard_user') && str_contains($ejemplo, 'admin_dashboard_pass_hash'));
plVerificar('y conserva intactas las de las otras herramientas',
    str_contains($ejemplo, 'admin_supertecnicas_user') && str_contains($ejemplo, 'admin_lesiones_user'));

// -- creación de temporada ---------------------------------------------
plGuardarEquipos(['equipos' => [
    ['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true],
    ['id' => 'eq_b', 'nombre' => 'Beta', 'activo' => true],
    ['id' => 'eq_z', 'nombre' => 'Zeta', 'activo' => false],
]]);

plVerificar('crear la primera temporada funciona',
    plCrearTemporada('2026-27', '2026/27')['ok'] === true);
plVerificar('nace en fase ROSTER', plFaseActiva() === 'ROSTER');
plVerificar('con las plantillas vacías de los equipos activos',
    plCargarTemporada('2026-27')['equipos']['eq_a']['jugadores'] === []
    && count(plCargarTemporada('2026-27')['equipos']) === 2);

// -- transiciones de fase ----------------------------------------------
$id = plTemporadaActiva()['id'];

plVerificar('ROSTER → CLAUSULAS', plCambiarFase($id, 'CLAUSULAS')['ok'] === true);
plVerificar('queda reflejado', plFaseActiva() === 'CLAUSULAS');
plVerificar('CLAUSULAS → MERCADO', plCambiarFase($id, 'MERCADO')['ok'] === true);
plVerificar('MERCADO → CERRADA', plCambiarFase($id, 'CERRADA')['ok'] === true);
plVerificar('la temporada queda cerrada', plFaseActiva() === 'CERRADA');

// El admin puede forzar el orden: volver atrás es legítimo, es su vía de
// corrección cuando cierra una fase antes de tiempo.
plVerificar('forzar CERRADA → ROSTER está permitido', plCambiarFase($id, 'ROSTER')['ok'] === true);
plVerificar('y surte efecto', plFaseActiva() === 'ROSTER');

plVerificar('una fase inventada se rechaza', plCambiarFase($id, 'PRETEMPORADA')['ok'] === false);
plVerificar('y no cambia nada', plFaseActiva() === 'ROSTER');

// -- registro de eventos ------------------------------------------------
// La pantalla escribe un evento por transición; aquí se comprueba el contrato
// que usa, no el HTML.
plRegistrarEvento('FASE', 'admin', 'admin', ['temporada' => $id, 'de' => 'ROSTER', 'a' => 'CLAUSULAS']);
plRegistrarEvento('TEMPORADA', 'admin', 'admin', ['temporada' => '2027-28']);
$ev = plCargarJson(plRutaDatos('registro.json'), ['eventos' => []])['eventos'];
plVerificar('dos eventos registrados', count($ev) === 2);
plVerificar('el de fase lleva de dónde a dónde',
    $ev[0]['tipo'] === 'FASE' && $ev[0]['detalle']['a'] === 'CLAUSULAS');
plVerificar('y el actor es admin', $ev[0]['actor'] === 'admin');

// -- informe de equipos incompletos -------------------------------------
// Alfa: 20 jugadores y 650M repartidos -> completo por los dos lados.
// Beta: 18 jugadores y 600M -> incompleto por los dos lados.
$completos = [];
for ($i = 1; $i <= 20; $i++) {
    $completos[] = ['id' => "a$i", 'nombre' => "A$i", 'tier' => 'C', 'salario' => 2,
                    'clausula' => $i === 1 ? 650 - 19 * 1 : 1];
}
$parciales = [];
for ($i = 1; $i <= 18; $i++) {
    $parciales[] = ['id' => "b$i", 'nombre' => "B$i", 'tier' => 'C', 'salario' => 2,
                    'clausula' => $i === 1 ? 600 - 17 * 1 : 1];
}
plGuardarEquipoTemporada($id, 'eq_a', ['jugadores' => $completos], 0);
plGuardarEquipoTemporada($id, 'eq_b', ['jugadores' => $parciales], 0);

$informe = plEquiposIncompletos(plCargarTemporada($id));

plVerificar('Alfa, con 20 jugadores, no aparece como plantilla incompleta',
    !isset($informe['roster']['eq_a']));
plVerificar('Beta, con 18, sí aparece', isset($informe['roster']['eq_b']));
plVerificar('con el recuento exacto',
    $informe['roster']['eq_b']['n'] === 18 && $informe['roster']['eq_b']['max'] === 20);

plVerificar('Alfa, con 650M exactos, no aparece como cláusulas incompletas',
    !isset($informe['clausulas']['eq_a']));
plVerificar('Beta, con 600M, sí aparece', isset($informe['clausulas']['eq_b']));
plVerificar('con la suma real y el presupuesto',
    $informe['clausulas']['eq_b']['suma'] === 600
    && $informe['clausulas']['eq_b']['presupuesto'] === 650);

// Un equipo sin jugadores no está completo por ninguno de los dos lados: 0 de
// 650 no es "completo", aunque técnicamente no haya nada mal repartido.
plCrearTemporada('2028-29', '2028/29');
$informeVacio = plEquiposIncompletos(plCargarTemporada('2028-29'));
plVerificar('un equipo sin jugadores aparece en las dos listas',
    isset($informeVacio['roster']['eq_a']) && isset($informeVacio['clausulas']['eq_a']));
plVerificar('y el equipo archivado no aparece en ninguna, porque no entró',
    !isset($informeVacio['roster']['eq_z']));

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
