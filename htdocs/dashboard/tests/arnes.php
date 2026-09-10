<?php
// dashboard/tests/arnes.php
// Arnés de render en CLI. Existe porque las pantallas de presidente
// dependen de $_SESSION y de $_SERVER, y `php -l` solo parsea: no ejecuta.
// Sin este fichero no habría forma de comprobar una pantalla sin levantar
// Apache, y un gate que necesita un servidor no es un gate automatizable.
//
// A propósito NO hace require de dashboard/lib.php: este fichero llega al
// proyecto con el copiado de workspace/, antes de que exista lib.php, y el
// barrido de sintaxis del paso 01 lo recorre.

// Fija sesión y superglobales como si la petición viniera de Apache.
function plArnesPreparar(array $sesion = [], array $get = [], array $post = [], string $metodo = 'GET'): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $_SESSION = $sesion;
    $_GET = $get;
    $_POST = $post;
    $_COOKIE = [];
    $_SERVER['REQUEST_METHOD'] = $metodo;
    $_SERVER['REQUEST_URI'] = '/dashboard/';
    $_SERVER['SCRIPT_NAME'] = '/dashboard/index.php';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es';
    $_SERVER['HTTPS'] = 'on';
}

// Incluye una pantalla y devuelve el HTML que imprime.
// Solo sirve para caminos que NO terminan en exit(): un redirect corta el
// proceso entero. Para los que sí terminan así, plArnesPeticion() de abajo.
function plArnesRender(string $rutaPantalla): string
{
    ob_start();
    include $rutaPantalla;
    return (string) ob_get_clean();
}

// Ejecuta una petición completa contra una pantalla —GET o POST— y dice cómo
// terminó. Activa $GLOBALS['PL_ARNES'], con lo que plRedirigir() y plCortar()
// de lib.php lanzan una excepción marcada en vez de hacer exit, y aquí se
// captura. Así se prueba el camino del POST de cada pantalla, que es donde
// viven el rechazo por fase, el rev desfasado y el CSRF.
//
// Devuelve una de:
//   ['tipo' => 'html',      'html' => '...']
//   ['tipo' => 'redirigir', 'url'  => 'plantilla.php']
//   ['tipo' => 'cortar',    'codigo' => 403, 'mensaje' => '...']
function plArnesPeticion(string $rutaPantalla, array $sesion = [], array $get = [], array $post = []): array
{
    plArnesPreparar($sesion, $get, $post, $post === [] ? 'GET' : 'POST');
    $GLOBALS['PL_ARNES'] = true;

    $nivel = ob_get_level();
    ob_start();
    try {
        include $rutaPantalla;
        $resultado = ['tipo' => 'html', 'html' => (string) ob_get_clean()];
    } catch (RuntimeException $e) {
        // Se descarta lo que la pantalla llegara a imprimir antes de salir.
        while (ob_get_level() > $nivel) {
            ob_end_clean();
        }
        $m = $e->getMessage();
        if (str_starts_with($m, 'PL_REDIRIGIR:')) {
            $resultado = ['tipo' => 'redirigir', 'url' => substr($m, strlen('PL_REDIRIGIR:'))];
        } elseif (str_starts_with($m, 'PL_CORTAR:')) {
            [, $codigo, $mensaje] = explode(':', $m, 3) + [null, '0', ''];
            $resultado = ['tipo' => 'cortar', 'codigo' => (int) $codigo, 'mensaje' => $mensaje];
        } else {
            throw $e;
        }
    }

    // La sesión que dejó la pantalla (flash, token CSRF, usuario) se conserva
    // en el valor devuelto para que el test pueda encadenar peticiones.
    $resultado['sesion'] = $_SESSION;
    return $resultado;
}

// Directorio de datos aislado por test: nada toca dashboard/data/ real.
function plArnesDirDatos(string $prefijo = 'pl_test_'): string
{
    $dir = sys_get_temp_dir() . '/' . $prefijo . uniqid();
    mkdir($dir, 0755, true);
    return $dir;
}

function plArnesLimpiar(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    foreach (glob($dir . '/.*') ?: [] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    @rmdir($dir);
}

// Contador de fallos compartido por todos los tests, con el mismo patrón que
// supertecnicas/tests/test_lib.php: nunca corta en el primer fallo, para ver
// la lista completa de una tirada.
function plVerificar(string $descripcion, bool $condicion): void
{
    if ($condicion) {
        echo "OK   $descripcion\n";
    } else {
        echo "FAIL $descripcion\n";
        $GLOBALS['fallos'] = ($GLOBALS['fallos'] ?? 0) + 1;
    }
}

function plSalirConResultado(): void
{
    $fallos = (int) ($GLOBALS['fallos'] ?? 0);
    echo "\n";
    if ($fallos > 0) {
        echo "$fallos comprobacion(es) fallida(s).\n";
        exit(1);
    }
    echo "Todas las comprobaciones pasan.\n";
    exit(0);
}
