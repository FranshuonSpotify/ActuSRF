<?php
// supertecnicas/lib.php
// Helpers compartidos de la herramienta de supertécnicas: acceso a
// datos_oficiales.json y a los ficheros propios de esta herramienta, con
// lectura/escritura atómica. Sin clases, siguiendo el estilo de
// api/recalcular.php y api/discord_update.php.

define('ST_DATA_JSON', __DIR__ . '/../datos_oficiales.json');
define('ST_CODIGOS_JSON', __DIR__ . '/data/codigos_equipos.json');
define('ST_CONFIG_JSON', __DIR__ . '/data/config.json');

const ST_MAX_SUPERTECNICAS = 4;
const ST_TIPOS = ['', 'tiro', 'regate', 'bloqueo', 'parada'];
const ST_AFINIDADES = ['', 'neutro', 'fuego', 'montaña', 'bosque', 'aire'];

function stNormalizarTexto($texto) {
    // Mapeo explícito de acentos a ASCII equivalentes
    $accents = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ',
                'à', 'è', 'ì', 'ò', 'ù', 'À', 'È', 'Ì', 'Ò', 'Ù',
                'â', 'ê', 'î', 'ô', 'û', 'Â', 'Ê', 'Î', 'Ô', 'Û',
                'ä', 'ë', 'ï', 'ö', 'ü', 'Ä', 'Ë', 'Ï', 'Ö', 'Ü'];
    $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'];
    $texto = str_replace($accents, $replace, $texto);
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}

function stCargarJson($ruta, array $porDefecto) {
    if (!file_exists($ruta)) return $porDefecto;
    $contenido = file_get_contents($ruta);
    $data = json_decode($contenido, true);
    return is_array($data) ? $data : $porDefecto;
}

// Escritura atómica: fichero temporal en el mismo directorio + rename(),
// bajo un lock exclusivo sobre "$ruta.lock" para que dos guardados
// simultáneos no se pisen ni una lectura vea un JSON a medias.
function stGuardarJsonAtomico($ruta, array $data) {
    $directorio = dirname($ruta);
    if (!is_dir($directorio)) mkdir($directorio, 0755, true);

    $lock = fopen($ruta . '.lock', 'c');
    if (!$lock) return false;
    flock($lock, LOCK_EX);

    $tmp = tempnam($directorio, 'st_');
    $ok = false;
    if ($tmp !== false) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ok = file_put_contents($tmp, $json) !== false && chmod($tmp, 0644) && rename($tmp, $ruta);
        if (!$ok && file_exists($tmp)) unlink($tmp);
    }

    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

function stCargarDatosOficiales() {
    return stCargarJson(ST_DATA_JSON, ['equipos' => []]);
}

function stGuardarDatosOficiales(array $data) {
    return stGuardarJsonAtomico(ST_DATA_JSON, $data);
}

function stCargarCodigos() {
    return stCargarJson(ST_CODIGOS_JSON, []);
}

function stGuardarCodigos(array $codigos) {
    return stGuardarJsonAtomico(ST_CODIGOS_JSON, $codigos);
}

function stCargarConfig() {
    $config = stCargarJson(ST_CONFIG_JSON, ['ventana_abierta' => false]);
    $config['ventana_abierta'] = !empty($config['ventana_abierta']);
    return $config;
}

function stGuardarConfig(array $config) {
    return stGuardarJsonAtomico(ST_CONFIG_JSON, $config);
}

function stEquiposActivos(array $data) {
    $equipos = $data['equipos'] ?? [];
    return array_values(array_filter($equipos, function ($e) {
        return empty($e['archivado']);
    }));
}

function stBuscarEquipoPorId(array $data, $equipoId) {
    foreach (($data['equipos'] ?? []) as $i => $equipo) {
        if ((string) ($equipo['id'] ?? '') === (string) $equipoId) return $i;
    }
    return null;
}

function stEsc($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

// Protección CSRF de sinónimo (synchronizer token): un token por sesión,
// comparado con hash_equals(). index.php (con sesión de presidente) y
// admin.php (con session_start() propio añadido para esto, aparte del
// Basic Auth) comparten este mismo mecanismo.
function stTokenCsrf() {
    if (empty($_SESSION['st_csrf'])) {
        $_SESSION['st_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['st_csrf'];
}

function stCsrfValido() {
    $enviado = $_POST['csrf'] ?? '';
    return !empty($_SESSION['st_csrf']) && is_string($enviado) && hash_equals($_SESSION['st_csrf'], $enviado);
}

// Valor por defecto de código/PIN para un equipo sin entrada todavía en
// codigos_equipos.json: nombre del equipo / ciudad, normalizados.
function stCodigoPorDefecto(array $equipo) {
    return [
        'codigo' => stNormalizarTexto($equipo['nombre'] ?? ''),
        'pin' => stNormalizarTexto($equipo['ciudad'] ?? ''),
    ];
}
