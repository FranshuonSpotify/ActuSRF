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
verificar('stTipoLabel devuelve vacío si el tipo no existe', stTipoLabel('inventado') === '');

verificar('stAfinidadLabel traduce "fuego" a inglés', stAfinidadLabel('fuego') === 'Fire');
verificar('stAfinidadLabel traduce "montaña" (con ñ) a inglés', stAfinidadLabel('montaña') === 'Mountain');
stEstablecerIdioma('es');

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
