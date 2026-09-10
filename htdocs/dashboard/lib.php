<?php
// dashboard/lib.php
// Helpers compartidos del dashboard de plantillas: acceso a los ficheros JSON
// propios con lectura/escritura atómica, escapado y protección CSRF. Sin
// clases, siguiendo el estilo de supertecnicas/lib.php y api/recalcular.php.
//
// Este fichero no sabe nada de equipos, tiers ni fases: eso vive en
// dominio.php y almacen.php. Aquí solo hay fontanería.

// El guard va en la PRIMERA línea ejecutable. Más abajo no protegería de nada
// útil: PHP parsea el fichero entero antes de ejecutar, así que una sintaxis
// no soportada revienta antes de llegar aquí; lo que esto sí ataja es un
// hosting con una versión vieja donde array_is_list() no existe.
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die('El dashboard requiere PHP 8.1.0 o superior. Versión actual: ' . PHP_VERSION);
}

// Devuelve el contenido del JSON, o $porDefecto si el fichero no existe o
// está corrupto. Nunca lanza y nunca crea nada: ni el fichero ni su .lock. La
// semilla se materializa en disco la primera vez que algo la guarda.
//
// Lee bajo un lock COMPARTIDO sobre "$ruta.lock". En Linux no haría falta,
// porque rename() sobre un fichero abierto funciona; en Windows, no: si un
// lector tiene el JSON abierto justo cuando un escritor hace el rename, el
// rename falla con "Acceso denegado" y el guardado se pierde. Con el lock
// compartido, los lectores esperan a que termine el escritor y el escritor a
// que salgan los lectores, así que nunca coinciden. Se comprobó con procesos
// reales en la máquina de desarrollo antes de escribir esto.
function plCargarJson(string $ruta, array $porDefecto): array
{
    // Si no existe no se toma lock: tomarlo crearía el .lock, y leer no debe
    // escribir nada. Si el fichero aparece justo después, el resultado es el
    // mismo que haber leído un instante antes.
    if (!file_exists($ruta)) {
        return $porDefecto;
    }
    $lock = fopen($ruta . '.lock', 'c');
    if ($lock === false) {
        return plLeerJson($ruta, $porDefecto);
    }
    flock($lock, LOCK_SH);
    $data = plLeerJson($ruta, $porDefecto);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $data;
}

// La lectura sin lock. Se usa DENTRO de plActualizarJson(), que ya tiene el
// lock exclusivo —pedir además el compartido desde el mismo proceso lo dejaría
// esperándose a sí mismo—, y para ficheros ajenos al subproyecto como
// datos_oficiales.json, junto a los que no se debe crear ningún .lock.
function plLeerJson(string $ruta, array $porDefecto): array
{
    if (!file_exists($ruta)) {
        return $porDefecto;
    }
    $contenido = file_get_contents($ruta);
    if ($contenido === false) {
        return $porDefecto;
    }
    $data = json_decode($contenido, true);
    return is_array($data) ? $data : $porDefecto;
}

// Escritura atómica: fichero temporal en el MISMO directorio + rename(), bajo
// un lock exclusivo sobre "$ruta.lock". El temporal va en el mismo directorio
// a propósito: rename() solo es atómico dentro del mismo sistema de ficheros.
//
// El lock evita que dos guardados simultáneos se pisen; el rename evita que
// una lectura concurrente vea un JSON a medias. Son dos problemas distintos y
// hacen falta las dos piezas.
//
// Comprobado en Windows con PHP 8.2.12: rename() sobre un fichero que ya
// existe funciona, y JSON_UNESCAPED_UNICODE deja los acentos legibles.
function plGuardarJsonAtomico(string $ruta, array $data): bool
{
    $lock = plTomarLock($ruta);
    if ($lock === null) {
        return false;
    }
    $ok = plEscribirJsonSinLock($ruta, $data);
    plSoltarLock($lock);
    return $ok;
}

// Lee, modifica y escribe un JSON como UNA sola operación bajo el lock.
//
// Existe porque "cargar, cambiar, plGuardarJsonAtomico()" tiene una ventana:
// la lectura ocurre FUERA del lock. Si dos peticiones leen a la vez, las dos
// escriben, y la segunda borra el cambio de la primera sin que nadie se
// entere. En el registro de clausulaciones eso significa perder un evento, que
// es justo lo que ese registro existe para no perder.
//
// $cambio recibe los datos actuales y devuelve los nuevos, o null para no
// escribir nada (por ejemplo, porque una comprobación hecha dentro del lock ha
// fallado). Devuelve false solo si no se pudo tomar el lock o escribir.
function plActualizarJson(string $ruta, array $porDefecto, callable $cambio): bool
{
    $lock = plTomarLock($ruta);
    if ($lock === null) {
        return false;
    }
    // plLeerJson y no plCargarJson: aquí ya se tiene el lock exclusivo, y el
    // compartido que toma plCargarJson se quedaría esperando a este mismo.
    $nuevo = $cambio(plLeerJson($ruta, $porDefecto));
    $ok    = $nuevo === null ? true : plEscribirJsonSinLock($ruta, $nuevo);
    plSoltarLock($lock);
    return $ok;
}

// Lock exclusivo sobre "$ruta.lock", no sobre el JSON: el JSON se sustituye
// entero con rename(), y un lock sobre un fichero que se reemplaza dejaría de
// proteger al siguiente en llegar.
function plTomarLock(string $ruta)
{
    $directorio = dirname($ruta);
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        return null;
    }
    $lock = fopen($ruta . '.lock', 'c');
    if ($lock === false) {
        return null;
    }
    flock($lock, LOCK_EX);
    return $lock;
}

function plSoltarLock($lock): void
{
    flock($lock, LOCK_UN);
    fclose($lock);
}

// Solo la mitad de la escritura: temporal + rename. Se llama siempre con el
// lock ya tomado, desde plGuardarJsonAtomico() o plActualizarJson().
function plEscribirJsonSinLock(string $ruta, array $data): bool
{
    $ok  = false;
    $tmp = tempnam(dirname($ruta), 'pl_');
    if ($tmp !== false) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ok = $json !== false
            && file_put_contents($tmp, $json) !== false
            && chmod($tmp, 0644)
            && rename($tmp, $ruta);
        // Si algo falló a mitad, el temporal no se queda tirado en data/.
        if (!$ok && file_exists($tmp)) {
            unlink($tmp);
        }
    }
    return $ok;
}

// Escapado para HTML. Se pasa ENT_QUOTES explícitamente aunque desde PHP 8.1
// ya sea parte del valor por defecto: es redundante, no incorrecto, y deja la
// intención escrita para quien lea el código sin conocer ese cambio.
function plEsc($t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

// Acentos a ASCII, minúsculas, y todo lo que no sea letra o número a espacio.
// Se usa para detectar nombres de jugador equivalentes escritos distinto
// ("Endou Mamoru" / "Endo Mamoru"), que es un aviso del panel de admin, no un
// bloqueo: pueden ser dos personas.
function plNormalizarTexto($texto): string
{
    $acentos = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü',
                'à', 'è', 'ì', 'ò', 'ù', 'À', 'È', 'Ì', 'Ò', 'Ù',
                'â', 'ê', 'î', 'ô', 'û', 'Â', 'Ê', 'Î', 'Ô', 'Û',
                'ä', 'ë', 'ï', 'ö', 'Ä', 'Ë', 'Ï', 'Ö'];
    $planos  = ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'A', 'E', 'I', 'O', 'U', 'N', 'U',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U',
                'a', 'e', 'i', 'o', 'A', 'E', 'I', 'O'];

    $texto = str_replace($acentos, $planos, (string) $texto);
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim((string) $texto);
}

// Protección CSRF de sinónimo (synchronizer token): un token por sesión,
// comparado con hash_equals() para no filtrar información por tiempo de
// comparación. Lo comparten las pantallas de presidente y las de admin: el
// Basic Auth del admin autentica, pero no protege contra CSRF.
function plTokenCsrf(): string
{
    if (empty($_SESSION['pl_csrf'])) {
        $_SESSION['pl_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pl_csrf'];
}

function plCsrfValido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return !empty($_SESSION['pl_csrf'])
        && is_string($enviado)
        && hash_equals($_SESSION['pl_csrf'], $enviado);
}

// Sello de tiempo de registro.json, en ISO-8601 con zona horaria. Se centraliza
// aquí para que todos los eventos del registro tengan exactamente el mismo
// formato y ordenarlos como cadena siga siendo ordenarlos cronológicamente.
function plAhora(): string
{
    return date('c');
}

// ---------------------------------------------------- salidas de pantalla

// Toda pantalla termina un POST con una de estas dos, nunca con header()+exit
// a pelo. En producción hacen exactamente eso; bajo el arnés de tests
// ($GLOBALS['PL_ARNES']) lanzan una excepción con una marca reconocible.
//
// El motivo: un exit dentro de un include mata el proceso entero del test, así
// que sin esta costura el camino del POST —rechazo por fase, salario
// manipulado, rev desfasado, CSRF— sería justo la parte de cada pantalla que
// se quedaría sin probar. Es RuntimeException y no una clase propia porque el
// subproyecto no usa clases; la marca del mensaje basta para distinguirla.
function plRedirigir(string $url): never
{
    if (!empty($GLOBALS['PL_ARNES'])) {
        throw new RuntimeException('PL_REDIRIGIR:' . $url);
    }
    header('Location: ' . $url);
    exit;
}

function plCortar(int $codigo, string $mensaje): never
{
    if (!empty($GLOBALS['PL_ARNES'])) {
        throw new RuntimeException('PL_CORTAR:' . $codigo . ':' . $mensaje);
    }
    http_response_code($codigo);
    exit($mensaje);
}

// Cifra en millones para enseñar: 75 -> "75M". Centralizado para que todas
// las pantallas escriban las cantidades igual y en una sola forma.
function plM(int $millones): string
{
    return $millones . 'M';
}
