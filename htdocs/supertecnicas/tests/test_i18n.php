<?php
// supertecnicas/tests/test_i18n.php
// Self-check sin framework, mismo patrón que tests/test_lib.php.
// Ejecutar con: php supertecnicas/tests/test_i18n.php

require_once __DIR__ . '/../i18n.php';

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

// -- ST_IDIOMAS / ST_BANDERAS ------------------------------------------
verificar('ST_IDIOMAS tiene 10 idiomas', count(ST_IDIOMAS) === 10);
verificar('ST_BANDERAS tiene una bandera por idioma', count(ST_BANDERAS) === 10);
foreach (ST_IDIOMAS as $idioma) {
    verificar("ST_BANDERAS tiene entrada para '$idioma'", isset(ST_BANDERAS[$idioma]));
}

// -- stDetectarIdiomaNavegador ------------------------------------------
verificar(
    'detecta inglés como preferido cuando tiene mayor q',
    stDetectarIdiomaNavegador('fr;q=0.5,en;q=0.9,es;q=0.3') === 'en'
);
verificar(
    'usa el primero sin q explícito (q=1.0 implícito)',
    stDetectarIdiomaNavegador('pt-BR,es;q=0.8') === 'pt'
);
verificar(
    'ignora idiomas no soportados y cae al primero soportado',
    stDetectarIdiomaNavegador('de-DE,fr;q=0.9,es;q=0.5') === 'fr'
);
verificar(
    'cabecera vacía cae a español',
    stDetectarIdiomaNavegador('') === 'es'
);
verificar(
    'ningún idioma soportado en la cabecera cae a español',
    stDetectarIdiomaNavegador('de-DE,nl-NL;q=0.9') === 'es'
);

// -- stT / stEstablecerIdioma --------------------------------------------
stEstablecerIdioma('en');
verificar('stT devuelve el texto en inglés', stT('login.boton_entrar') === 'Enter');

stEstablecerIdioma('es');
verificar('stT devuelve el texto en español', stT('login.boton_entrar') === 'Entrar');

verificar(
    'stT cae a español si la clave falta en el idioma actual',
    (function () {
        stEstablecerIdioma('fr');
        global $ST_I18N;
        $original = $ST_I18N['fr']['login.boton_entrar'];
        unset($ST_I18N['fr']['login.boton_entrar']);
        $resultado = stT('login.boton_entrar') === 'Entrar';
        $ST_I18N['fr']['login.boton_entrar'] = $original;
        stEstablecerIdioma('es');
        return $resultado;
    })()
);

verificar(
    'stT devuelve la clave entre corchetes si no existe en ningún idioma',
    stT('clave.que.no.existe') === '[clave.que.no.existe]'
);

// -- stTipoLabel / stAfinidadLabel ---------------------------------------
stEstablecerIdioma('en');
verificar('stTipoLabel traduce "tiro" a inglés', stTipoLabel('tiro') === 'Shot');
verificar('stTipoLabel es insensible a mayúsculas', stTipoLabel('TIRO') === 'Shot');
verificar('stTipoLabel devuelve vacío si no hay tipo', stTipoLabel('') === '');
verificar('stTipoLabel devuelve el valor original si el tipo no existe', stTipoLabel('inventado') === 'inventado');

verificar('stAfinidadLabel traduce "fuego" a inglés', stAfinidadLabel('fuego') === 'Fire');
verificar('stAfinidadLabel traduce "montaña" (con ñ) a inglés', stAfinidadLabel('montaña') === 'Mountain');
stEstablecerIdioma('es');

// -- stResolverIdioma ----------------------------------------------------
// Guarda y restaura los superglobales que esta sección manipula, para no
// afectar al resto del test.
$get_original = $_GET;
$session_original = $_SESSION ?? [];

$_GET = ['lang' => 'fr'];
$_SESSION = [];
verificar(
    'stResolverIdioma usa ?lang= si es válido y lo guarda en sesión',
    stResolverIdioma() === 'fr' && ($_SESSION['st_lang'] ?? null) === 'fr'
);

$_GET = [];
$_SESSION = ['st_lang' => 'pt'];
verificar(
    'stResolverIdioma usa el idioma ya guardado en sesión si no hay ?lang=',
    stResolverIdioma() === 'pt'
);

$_GET = [];
$_SESSION = [];
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,it;q=0.9';
verificar(
    'stResolverIdioma cae a Accept-Language si no hay ?lang= ni sesión',
    stResolverIdioma() === 'it'
);
unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);

$_GET = $get_original;
$_SESSION = $session_original;

// -- Paridad de claves entre idiomas --------------------------------------
verificar(
    'todos los idiomas de ST_I18N tienen exactamente las mismas claves',
    (function () use ($ST_I18N) {
        $clavesEs = array_keys($ST_I18N['es']);
        sort($clavesEs);
        foreach ($ST_I18N as $idioma => $textos) {
            $claves = array_keys($textos);
            sort($claves);
            if ($claves !== $clavesEs) return false;
        }
        return true;
    })()
);

// -- Paridad entre los mapas de traducción y las constantes de lib.php ---
verificar(
    'las claves de ST_TIPOS_I18N coinciden con ST_TIPOS (sin la vacía)',
    (function () use ($ST_TIPOS_I18N) {
        $esperadas = array_filter(ST_TIPOS, function ($t) { return $t !== ''; });
        sort($esperadas);
        $reales = array_keys($ST_TIPOS_I18N);
        sort($reales);
        return $esperadas === $reales;
    })()
);
verificar(
    'las claves de ST_AFINIDADES_I18N coinciden con ST_AFINIDADES (sin la vacía)',
    (function () use ($ST_AFINIDADES_I18N) {
        $esperadas = array_filter(ST_AFINIDADES, function ($a) { return $a !== ''; });
        sort($esperadas);
        $reales = array_keys($ST_AFINIDADES_I18N);
        sort($reales);
        return $esperadas === $reales;
    })()
);

// -- stRenderSelectorIdioma ------------------------------------------------
verificar(
    'stRenderSelectorIdioma imprime un enlace por idioma',
    (function () {
        ob_start();
        stRenderSelectorIdioma();
        $html = ob_get_clean();
        return substr_count($html, '<a href="?lang=') === 10;
    })()
);

echo "\n";
if ($fallos > 0) {
    echo "$fallos comprobación(es) fallida(s).\n";
    exit(1);
}
echo "Todas las comprobaciones pasan.\n";
exit(0);
