<?php

declare(strict_types=1);

function crearConexion(): PDO
{
    // Despliegue en hosting compartido (IONOS): no siempre se puede fijar
    // variables de entorno de forma fiable, así que si existe
    // config/credenciales.php (nunca en el repo, solo en el paquete de
    // despliegue) sus constantes ganan sobre las variables de entorno.
    // En local (XAMPP) este fichero no existe y todo sigue igual que antes.
    $credencialesLocal = __DIR__ . '/credenciales.php';
    if (is_file($credencialesLocal)) {
        require $credencialesLocal;
    }

    $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
    $nombre = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'transferroom');
    $usuario = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
    $password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

    $dsn = "mysql:host={$host};dbname={$nombre};charset=utf8mb4";

    return new PDO($dsn, $usuario, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

$db = crearConexion();
