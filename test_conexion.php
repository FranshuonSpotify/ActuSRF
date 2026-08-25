<?php
require_once __DIR__ . '/config/admin_auth.php';
requerirAdminBasicAuth();

$secretos = cargarSecretos();
$host = $secretos['db_host'];
$dbname = $secretos['db_name'];
$username = $secretos['db_user'];
$password = $secretos['db_pass'];
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "<h1>¡Conexión Exitosa!</h1>";
    echo "<p>Tu base de datos está lista para empezar a recibir usuarios y apuestas.</p>";
} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>