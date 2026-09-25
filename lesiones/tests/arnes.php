<?php
// lesiones/tests/arnes.php
// Arnés de render en CLI, mismo patrón que dashboard/tests/arnes.php.
// A propósito NO hace require de lesiones/lib.php.

function leArnesPreparar(array $sesion = [], array $get = [], array $post = [], string $metodo = 'GET'): void
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    $_SESSION = $sesion;
    $_GET = $get;
    $_POST = $post;
    $_COOKIE = [];
    $_SERVER['REQUEST_METHOD'] = $metodo;
    $_SERVER['REQUEST_URI'] = '/lesiones/';
    $_SERVER['SCRIPT_NAME'] = '/lesiones/index.php';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['HTTPS'] = 'on';
}

function leArnesRender(string $rutaPantalla): string
{
    ob_start();
    include $rutaPantalla;
    return (string) ob_get_clean();
}

function leArnesPeticion(string $rutaPantalla, array $sesion = [], array $get = [], array $post = []): array
{
    leArnesPreparar($sesion, $get, $post, $post === [] ? 'GET' : 'POST');
    $GLOBALS['LE_ARNES'] = true;

    $nivel = ob_get_level();
    ob_start();
    try {
        include $rutaPantalla;
        $resultado = ['tipo' => 'html', 'html' => (string) ob_get_clean()];
    } catch (RuntimeException $e) {
        while (ob_get_level() > $nivel) {
            ob_end_clean();
        }
        $m = $e->getMessage();
        if (str_starts_with($m, 'LE_REDIRIGIR:')) {
            $resultado = ['tipo' => 'redirigir', 'url' => substr($m, strlen('LE_REDIRIGIR:'))];
        } elseif (str_starts_with($m, 'LE_CORTAR:')) {
            [, $codigo, $mensaje] = explode(':', $m, 3) + [null, '0', ''];
            $resultado = ['tipo' => 'cortar', 'codigo' => (int) $codigo, 'mensaje' => $mensaje];
        } elseif (str_starts_with($m, 'LE_JSON:')) {
            // El JSON del cuerpo puede llevar sus propios ':', así que se
            // corta en 3 trozos como LE_CORTAR, no más.
            [, $codigo, $json] = explode(':', $m, 3) + [null, '0', ''];
            $resultado = ['tipo' => 'json', 'codigo' => (int) $codigo, 'datos' => json_decode($json, true)];
        } else {
            throw $e;
        }
    }
    $resultado['sesion'] = $_SESSION;
    return $resultado;
}

function leArnesDirDatos(string $prefijo = 'le_test_'): string
{
    $dir = sys_get_temp_dir() . '/' . $prefijo . uniqid();
    mkdir($dir, 0755, true);
    return $dir;
}

function leArnesLimpiar(string $dir): void
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

function leVerificar(string $descripcion, bool $condicion): void
{
    if ($condicion) {
        echo "OK   $descripcion\n";
    } else {
        echo "FAIL $descripcion\n";
        $GLOBALS['fallos'] = ($GLOBALS['fallos'] ?? 0) + 1;
    }
}

function leSalirConResultado(): void
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
