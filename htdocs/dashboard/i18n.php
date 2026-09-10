<?php
// dashboard/i18n.php
// Motor de idioma de las pantallas de presidente. En este paso lleva SOLO el
// esqueleto y el español: los otros nueve idiomas llegan en el paso 15, porque
// traducir pantallas cuyos textos todavía van a cambiar es trabajo que se tira
// dos veces.
//
// El panel de admin (admin*.php) NO usa plT(): va en español, igual que
// supertecnicas/admin.php. Es una decisión tomada.
//
// OJO: 'pl' dentro de PL_IDIOMAS es el código de POLACO, no el prefijo de las
// funciones de este subproyecto. Es la confusión más fácil de cometer aquí.

require_once __DIR__ . '/lib.php';   // plEsc(), que usa el selector de idioma

const PL_IDIOMAS = ['es', 'en', 'pt', 'it', 'fr', 'ja', 'ko', 'pl', 'bg', 'sr'];

// código de idioma => código de país para la bandera (flagcdn.com). Mismos
// pares que SF_LANGS en _fuente/i18n.js:8, para que el sitio entero use la
// misma correspondencia.
const PL_BANDERAS = [
    'es' => 'es', 'en' => 'gb', 'pt' => 'pt', 'it' => 'it', 'fr' => 'fr',
    'ja' => 'jp', 'ko' => 'kr', 'pl' => 'pl', 'bg' => 'bg', 'sr' => 'rs',
];

// Se asigna a $GLOBALS y no a una variable suelta: si este fichero se carga
// por primera vez desde dentro de una función —el arnés de tests incluye las
// pantallas desde plArnesPeticion()—, una variable de nivel de fichero sería
// local a esa función y plT() no la encontraría.
$GLOBALS['PL_I18N'] = [
    'es' => [
        // -- login
        'login.titulo'        => 'Dashboard de plantillas',
        'login.subtitulo'     => 'Entra con el correo y la contraseña que te dio el admin de la liga.',
        'login.campo_email'   => 'Correo',
        'login.campo_clave'   => 'Contraseña',
        'login.boton_entrar'  => 'Entrar',
        // Mensaje ÚNICO para correo inexistente, contraseña incorrecta y
        // cuenta desactivada: distinguirlos convertiría el formulario en un
        // oráculo de qué correos existen.
        'login.error'         => 'Correo o contraseña incorrectos.',
        'login.cerrar_sesion' => 'Cerrar sesión',

        // -- navegación del presidente: cuatro entradas, ni una más
        'nav.dashboard'  => 'Dashboard',
        'nav.plantilla'  => 'Mi plantilla',
        'nav.clausulas'  => 'Cláusulas',
        'nav.mercado'    => 'Mercado',
        'nav.saltar'     => 'Saltar al contenido',

        // -- fases
        'fase.roster'    => 'ROSTER',
        'fase.clausulas' => 'CLÁUSULAS',
        'fase.mercado'   => 'MERCADO',
        'fase.cerrada'   => 'CERRADA',

        // -- avisos de estado
        'aviso.sin_equipo'          => 'Aún no tienes equipo asignado. Escribe al admin de la liga para que te asocie a tu club.',
        'aviso.sin_temporada'       => 'La liga todavía no ha abierto ninguna temporada.',
        'aviso.plantilla_cerrada'   => 'No puedes modificar tu plantilla. La fase de inscripción de plantillas ya ha finalizado.',
        'aviso.clausulas_pronto'    => 'Termina primero tu inscripción de plantilla.',
        'aviso.clausulas_cerradas'  => 'La fase de cláusulas ya ha finalizado. Puedes consultarlas, pero no cambiarlas.',
        'aviso.mercado_abierto'     => 'MERCADO ABIERTO',
        'aviso.mercado_cerrado'     => 'MERCADO CERRADO',
        'aviso.equipo_fuera'        => 'Tu equipo no forma parte de la temporada en curso. Habla con el admin de la liga.',

        // -- dashboard del presidente
        'dash.presidente'           => 'Presidente: {nombre}',
        'dash.temporada'            => 'Temporada {nombre}',
        'dash.tarjeta_plantilla'    => 'PLANTILLA',
        'dash.tarjeta_cap'          => 'SALARY CAP',
        'dash.tarjeta_clausulas'    => 'CLÁUSULAS',
        'dash.tarjeta_mercado'      => 'MERCADO',
        'dash.disponibles'          => '{cifra} disponibles',
        'dash.presupuesto_completo' => 'Presupuesto completo',
        'dash.presupuesto_incompleto' => 'Incompleto',
        'dash.mercado_abierto'      => 'ABIERTO',
        'dash.mercado_cerrado'      => 'CERRADO',
        'dash.mi_plantilla'         => 'MI PLANTILLA',
        'dash.vacio_roster'         => 'Todavía no has inscrito a ningún jugador. La inscripción está abierta.',
        'dash.vacio_accion'         => 'Inscribir jugadores',
        'dash.vacio_cerrado'        => 'Tu equipo no inscribió jugadores en esta temporada.',

        // -- cabeceras de tabla y estados de jugador, compartidos
        'tabla.jugador'             => 'Jugador',
        'tabla.pos'                 => 'Pos.',
        'tabla.tier'                => 'Tier',
        'tabla.salario'             => 'Salario',
        'tabla.clausula'            => 'Cláusula',
        'tabla.estado'              => 'Estado',
        'tabla.equipo'              => 'Equipo',
        'estado.disponible'         => 'Disponible',
        'estado.clausulado_por'     => 'Clausulado por {equipo}',

        // -- errores que devuelve dominio.php, con sus marcadores
        'error.nombre_vacio'         => 'Escribe el nombre del jugador.',
        'error.posicion_invalida'    => 'La posición tiene que ser POR, DEF, MED o ATA.',
        'error.tier_invalido'        => 'Ese tier no existe.',
        'error.jugador_no_encontrado' => 'Ese jugador ya no está en tu plantilla. Recarga la página.',
        'error.max_jugadores'        => 'No puedes añadir este jugador: el máximo es de {maximo} y ya tienes {actual}.',
        'error.cap_superado'         => 'No puedes añadir este jugador. El Salary Cap es de {cap}M. Tu plantilla quedaría en {total}M. Solo tienes {disponible}M disponibles.',
        'error.clausulas_excedidas'  => 'No puedes superar los {presupuesto}M de presupuesto de cláusulas. Te pasas por {exceso}M.',
        'error.clausula_invalida'    => 'La cláusula de {jugador} tiene que ser un número entero de millones, sin decimales ni signo.',
        'error.rev_desfasado'        => 'Tu copresidente ha guardado mientras editabas. Recarga la página y repite el cambio.',
        'error.escritura'            => 'No se ha podido guardar. Vuelve a intentarlo; si sigue fallando, avisa al admin.',
        'error.csrf'                 => 'La sesión ha caducado. Recarga la página y vuelve a enviar el formulario.',
    ],
];

// ------------------------------------------------------------ resolución

function plEstablecerIdioma(string $idioma): void
{
    $GLOBALS['PL_IDIOMA_ACTUAL'] = in_array($idioma, PL_IDIOMAS, true) ? $idioma : 'es';
}

function plDetectarIdiomaNavegador(string $cabecera): string
{
    // "es-ES,es;q=0.9,en;q=0.8" -> se prueba cada preferencia en orden.
    foreach (explode(',', $cabecera) as $trozo) {
        $codigo = strtolower(trim(explode(';', $trozo)[0]));
        $corto  = substr($codigo, 0, 2);
        if (in_array($corto, PL_IDIOMAS, true)) {
            return $corto;
        }
    }
    return 'es';
}

// Orden de resolución: ?lang= explícito > lo recordado en sesión > lo que pide
// el navegador > español.
function plResolverIdioma(): string
{
    $pedido = $_GET['lang'] ?? '';
    if (is_string($pedido) && in_array($pedido, PL_IDIOMAS, true)) {
        $_SESSION['pl_lang'] = $pedido;
        return $pedido;
    }
    $enSesion = $_SESSION['pl_lang'] ?? '';
    if (is_string($enSesion) && in_array($enSesion, PL_IDIOMAS, true)) {
        return $enSesion;
    }
    $detectado = plDetectarIdiomaNavegador((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    $_SESSION['pl_lang'] = $detectado;
    return $detectado;
}

// Devuelve el texto del idioma activo; si falta, el español; si tampoco
// existe, '[clave]'. Ese corchete es deliberado: una clave sin traducir se ve
// a simple vista en la pantalla en vez de romperla o quedarse en blanco.
//
// $marcadores sustituye {nombre} por su valor DESPUÉS de traducir. Al revés
// se traduciría una cadena que ya lleva cifras dentro y el diccionario dejaría
// de casar.
function plT(string $clave, array $marcadores = []): string
{
    global $PL_I18N;
    $idioma = $GLOBALS['PL_IDIOMA_ACTUAL'] ?? 'es';

    $texto = $PL_I18N[$idioma][$clave] ?? $PL_I18N['es'][$clave] ?? ('[' . $clave . ']');

    foreach ($marcadores as $nombre => $valor) {
        $texto = str_replace('{' . $nombre . '}', (string) $valor, $texto);
    }
    return $texto;
}

// Etiqueta traducida de una fase, a partir del valor que guarda el JSON.
function plFaseTexto(string $fase): string
{
    return plT('fase.' . strtolower($fase));
}

// Fila de banderas, un enlace por idioma y sin JavaScript. Se conserva el
// resto de la query para no perder filtros al cambiar de idioma.
function plRenderSelectorIdioma(): void
{
    $activo = $GLOBALS['PL_IDIOMA_ACTUAL'] ?? 'es';
    echo '<nav class="idiomas" aria-label="Idioma">';
    foreach (PL_BANDERAS as $codigo => $pais) {
        $query = $_GET;
        $query['lang'] = $codigo;
        $href = '?' . http_build_query($query);
        $esActivo = $activo === $codigo;
        echo '<a href="' . plEsc($href) . '"'
            . ($esActivo ? ' class="activo" aria-current="true"' : '')
            . '><img src="https://flagcdn.com/16x12/' . plEsc($pais) . '.png"'
            . ' alt="' . plEsc($codigo) . '" width="16" height="12" loading="lazy"></a>';
    }
    echo '</nav>';
}
