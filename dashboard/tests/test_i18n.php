<?php
// dashboard/tests/test_i18n.php
// Self-check del paso 15: el motor de idioma y el diccionario de diez idiomas.
//   /c/xampp/php/php.exe dashboard/tests/test_i18n.php
//
// Comprueba la ESTRUCTURA de las traducciones, no su calidad literaria: que
// los diez idiomas tienen exactamente las mismas claves, que ninguna está
// vacía y que cada una conserva los marcadores {…} de la española. Un marcador
// perdido no da error: la pantalla enseñaría una frase sin la cifra que
// importa, y eso solo lo caza una comprobación como esta.

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../dominio.php';
require_once __DIR__ . '/../i18n.php';

$dic = $GLOBALS['PL_I18N'];
$es  = $dic['es'];
$marcadores = static function (string $texto): array {
    preg_match_all('/\{[a-zA-Z]+\}/', $texto, $m);
    $m = array_unique($m[0]);
    sort($m);
    return $m;
};

// -- idiomas declarados ----------------------------------------------------
plVerificar('hay exactamente diez idiomas', count(PL_IDIOMAS) === 10);
plVerificar('en el orden del resto del sitio', PL_IDIOMAS === ['es', 'en', 'pt', 'it', 'fr', 'ja', 'ko', 'pl', 'bg', 'sr']);
plVerificar('cada idioma tiene su bandera', array_keys(PL_BANDERAS) === PL_IDIOMAS);
plVerificar('pl es polaco, con bandera polaca: no el prefijo de las funciones', PL_BANDERAS['pl'] === 'pl');

// -- el diccionario, idioma a idioma ----------------------------------------
foreach (PL_IDIOMAS as $idioma) {
    plVerificar("el diccionario tiene el idioma $idioma", isset($dic[$idioma]) && is_array($dic[$idioma]));
    $claves   = array_keys($dic[$idioma] ?? []);
    $faltan   = array_diff(array_keys($es), $claves);
    $sobran   = array_diff($claves, array_keys($es));
    plVerificar("$idioma no deja ninguna clave sin traducir" . ($faltan ? ' (faltan: ' . implode(', ', array_slice($faltan, 0, 5)) . ')' : ''),
        $faltan === []);
    plVerificar("$idioma no inventa claves que el español no tiene" . ($sobran ? ' (sobran: ' . implode(', ', $sobran) . ')' : ''),
        $sobran === []);

    $vacias = array_keys(array_filter($dic[$idioma] ?? [], static fn($t) => !is_string($t) || trim($t) === ''));
    plVerificar("$idioma no tiene traducciones vacías" . ($vacias ? ' (' . implode(', ', $vacias) . ')' : ''), $vacias === []);

    $rotas = [];
    foreach ($es as $clave => $texto) {
        if (isset($dic[$idioma][$clave]) && $marcadores($dic[$idioma][$clave]) !== $marcadores($texto)) {
            $rotas[] = $clave;
        }
    }
    plVerificar("$idioma conserva todos los marcadores {…}" . ($rotas ? ' (rotos: ' . implode(', ', $rotas) . ')' : ''),
        $rotas === []);
}

// -- resolución de idioma ------------------------------------------------------
plArnesPreparar([], ['lang' => 'en']);
plVerificar('?lang=en resuelve a inglés', plResolverIdioma() === 'en');
plVerificar('y lo recuerda en la sesión para las siguientes peticiones', ($_SESSION['pl_lang'] ?? '') === 'en');

plArnesPreparar(['pl_lang' => 'ja'], ['lang' => 'klingon']);
plVerificar('un idioma que no existe se ignora y se conserva el anterior', plResolverIdioma() === 'ja');

plArnesPreparar([], []);
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'sr-RS,sr;q=0.9,en;q=0.5';
plVerificar('sin ?lang ni sesión, se deduce del navegador', plResolverIdioma() === 'sr');

plArnesPreparar([], []);
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'zh-CN,zh;q=0.9';
plVerificar('un navegador en un idioma que no tenemos cae a español', plResolverIdioma() === 'es');

// -- traducción ---------------------------------------------------------------
plEstablecerIdioma('en');
plVerificar('en inglés se traduce', plT('login.boton_entrar') !== $es['login.boton_entrar']);
plVerificar('los marcadores se sustituyen en cualquier idioma',
    str_contains(plT('error.cap_superado', ['cap' => 250, 'total' => 260, 'disponible' => 15]), '260'));

$GLOBALS['PL_I18N']['en'] = array_diff_key($GLOBALS['PL_I18N']['en'], ['nav.mercado' => true]);
plVerificar('si a un idioma le faltara una clave, se enseñaría la española, no un hueco',
    plT('nav.mercado') === $es['nav.mercado']);
$GLOBALS['PL_I18N'] = $dic;
plVerificar('y una clave que no existe en ningún idioma se ve a simple vista como [clave]',
    plT('no.existe') === '[no.existe]');

foreach (PL_IDIOMAS as $idioma) {
    plEstablecerIdioma($idioma);
    $ok = true;
    foreach (PL_FASES as $f) {
        $ok = $ok && !str_starts_with(plFaseTexto($f), '[');
    }
    plVerificar("las cuatro fases tienen nombre en $idioma", $ok);
}

plEstablecerIdioma('ko');
plVerificar('plTextoEs da español aunque el idioma activo sea otro: el admin no se traduce',
    plTextoEs('login.boton_entrar') === $es['login.boton_entrar']);

plEstablecerIdioma('es');
plSalirConResultado();
