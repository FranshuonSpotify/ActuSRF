<?php
// dashboard/tests/test_login.php
// Self-check del paso 05: resolución de idioma, envoltorio y login.
//   /c/xampp/php/php.exe dashboard/tests/test_login.php
//
// El camino de login CORRECTO no se renderiza con el arnés: termina en
// header()+exit, y un exit dentro de un include mata el proceso del test. Se
// comprueba la función de guardia (plUsuarioActual) y el render de los
// caminos que sí imprimen.

require_once __DIR__ . '/arnes.php';
$GLOBALS['PL_DIR_DATOS'] = plArnesDirDatos();
require_once __DIR__ . '/../almacen.php';
require_once __DIR__ . '/../i18n.php';

// -- i18n: resolución de idioma ---------------------------------------
plArnesPreparar([], ['lang' => 'en']);
plVerificar('?lang=en resuelve a inglés', plResolverIdioma() === 'en');
plVerificar('y lo recuerda en la sesión', ($_SESSION['pl_lang'] ?? '') === 'en');

plArnesPreparar(['pl_lang' => 'en'], []);
plVerificar('sin ?lang, manda lo recordado en sesión', plResolverIdioma() === 'en');

plArnesPreparar(['pl_lang' => 'en'], ['lang' => 'klingon']);
plVerificar('un idioma inventado se ignora y conserva el anterior',
    plResolverIdioma() === 'en');

plArnesPreparar([], []);
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9,en;q=0.8';
plVerificar('sin nada, se deduce del navegador', plResolverIdioma() === 'fr');

plArnesPreparar([], []);
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'zz-ZZ';
plVerificar('un navegador en un idioma que no tenemos cae a español',
    plResolverIdioma() === 'es');

plVerificar('los diez idiomas están declarados', count(PL_IDIOMAS) === 10);
plVerificar('cada idioma tiene su bandera', count(PL_BANDERAS) === count(PL_IDIOMAS));
plVerificar('pl es polaco y su bandera es pl, no la del prefijo',
    PL_BANDERAS['pl'] === 'pl' && PL_BANDERAS['en'] === 'gb');

// -- plT ---------------------------------------------------------------
plEstablecerIdioma('es');
plVerificar('una clave existente se traduce',
    plT('login.boton_entrar') === 'Entrar');
plVerificar('una clave inexistente se ve a simple vista como [clave]',
    plT('no.existe.esta') === '[no.existe.esta]');
plVerificar('los marcadores se sustituyen después de traducir',
    str_contains(plT('error.cap_superado', ['cap' => 250, 'total' => 260, 'disponible' => 15]), '260M'));
plVerificar('y no queda ningún marcador sin sustituir',
    !str_contains(plT('error.cap_superado', ['cap' => 250, 'total' => 260, 'disponible' => 15]), '{'));

plEstablecerIdioma('en');
plVerificar('un idioma sin traducir aún cae al español, no rompe',
    plT('login.boton_entrar') === 'Entrar');
plEstablecerIdioma('klingon');
plVerificar('establecer un idioma inválido deja español',
    ($GLOBALS['PL_IDIOMA_ACTUAL'] ?? '') === 'es');

plVerificar('la fase se traduce desde el valor del JSON',
    plFaseTexto('CLAUSULAS') === 'CLÁUSULAS' && plFaseTexto('ROSTER') === 'ROSTER');

// -- guardia de sesión --------------------------------------------------
plGuardarEquipos(['equipos' => [['id' => 'eq_a', 'nombre' => 'Alfa', 'activo' => true]]]);
plGuardarUsuarios(['usuarios' => [
    ['id' => 'u_1', 'nombre' => 'Juan', 'email' => 'juan@ejemplo.com',
     'hash' => password_hash('secreta', PASSWORD_DEFAULT), 'equipoId' => 'eq_a', 'activo' => true],
    ['id' => 'u_2', 'nombre' => 'Ana', 'email' => 'ana@ejemplo.com',
     'hash' => password_hash('otra', PASSWORD_DEFAULT), 'equipoId' => null, 'activo' => true],
    ['id' => 'u_3', 'nombre' => 'Baja', 'email' => 'baja@ejemplo.com',
     'hash' => password_hash('x', PASSWORD_DEFAULT), 'equipoId' => 'eq_a', 'activo' => false],
]]);

$_SESSION = [];
plVerificar('sin sesión no hay usuario', plUsuarioActual() === null);
$_SESSION = ['pl_usuario_id' => 'u_1'];
plVerificar('con sesión válida, sí', plUsuarioActual()['nombre'] === 'Juan');
$_SESSION = ['pl_usuario_id' => 'u_3'];
plVerificar('un presidente desactivado pierde el acceso en su siguiente petición',
    plUsuarioActual() === null);

// -- contraseñas --------------------------------------------------------
$u = plBuscarUsuarioPorEmail('juan@ejemplo.com');
plVerificar('la contraseña correcta verifica', password_verify('secreta', $u['hash']) === true);
plVerificar('una incorrecta no', password_verify('Secreta', $u['hash']) === false);
plVerificar('el hash no guarda la contraseña en claro', !str_contains($u['hash'], 'secreta'));

// -- render del login ---------------------------------------------------
plArnesPreparar([], []);
plEstablecerIdioma('es');
$html = plArnesRender(__DIR__ . '/../index.php');

plVerificar('el login trae un único h1', substr_count($html, '<h1') === 1);
plVerificar('con main#contenido', str_contains($html, 'id="contenido"'));
plVerificar('y el skip-link', str_contains($html, 'skip-link'));
plVerificar('declara el idioma activo en <html lang>', str_contains($html, '<html lang="es"'));
plVerificar('lleva el token CSRF en el formulario', str_contains($html, 'name="csrf"'));
plVerificar('pide correo y contraseña',
    str_contains($html, 'name="email"') && str_contains($html, 'name="clave"'));
plVerificar('no filtra ningún dato de equipo antes de autenticar',
    !str_contains($html, 'Alfa'));
plVerificar('no muestra la navegación de presidente sin sesión',
    !str_contains($html, 'aria-current="page"'));

// -- render del dashboard con sesión ------------------------------------
plArnesPreparar(['pl_usuario_id' => 'u_1'], []);
plEstablecerIdioma('es');
$html = plArnesRender(__DIR__ . '/../index.php');
plVerificar('con sesión se ve el equipo del presidente', str_contains($html, 'Alfa'));
plVerificar('y aparece la navegación', str_contains($html, 'aria-current="page"'));
plVerificar('con el enlace de cerrar sesión', str_contains($html, 'logout.php'));
plVerificar('sin temporada, se avisa', str_contains($html, 'no ha abierto ninguna temporada'));

// -- presidente sin equipo asignado -------------------------------------
plArnesPreparar(['pl_usuario_id' => 'u_2'], []);
plEstablecerIdioma('es');
$html = plArnesRender(__DIR__ . '/../index.php');
plVerificar('un presidente sin equipo ve un aviso, no un error',
    str_contains($html, 'Aún no tienes equipo asignado'));
plVerificar('y la página se renderiza entera igualmente',
    str_contains($html, 'id="contenido"') && str_contains($html, '</html>'));

// -- el login regenera la sesión ----------------------------------------
$fuente = (string) file_get_contents(__DIR__ . '/../index.php');
$posRegenerar = strpos($fuente, 'session_regenerate_id(true)');
$posEscribir  = strpos($fuente, "\$_SESSION['pl_usuario_id'] =");
plVerificar('index.php regenera el id de sesión', $posRegenerar !== false);
plVerificar('y lo hace ANTES de escribir el id de usuario',
    $posRegenerar !== false && $posEscribir !== false && $posRegenerar < $posEscribir);

plArnesLimpiar($GLOBALS['PL_DIR_DATOS']);
plSalirConResultado();
