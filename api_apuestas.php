<?php
require_once __DIR__ . '/config/admin_auth.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
header('Content-Type: application/json');

// Datos de tu base de datos de IONOS
$secretos = cargarSecretos();
$host = $secretos['db_host'];
$dbname = $secretos['db_name'];
$username = $secretos['db_user'];
$password = $secretos['db_pass'];
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a IONOS: ' . $e->getMessage()]);
    exit;
}

// Acción enviada desde APUESTAS_TEST.html
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// REGISTRO
if ($action === 'register') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    
    if (strlen($user) < 3 || strlen($pass) < 4) {
        echo json_encode(['status' => 'error', 'message' => 'Usuario (mínimo 3 letras) o clave (mínimo 4) muy cortos.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$user]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Ese nombre de usuario ya está registrado.']);
        exit;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    // Creamos al usuario con 1000 monedas de saldo inicial
    $stmt = $pdo->prepare("INSERT INTO usuarios (username, password, saldo) VALUES (?, ?, 1000.00)");
    
    if ($stmt->execute([$user, $hash])) {
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['username'] = $user;
        echo json_encode(['status' => 'success', 'message' => '¡Registrado con éxito! Has recibido 1000 Monedas Frontier.', 'saldo' => 1000.00]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos al intentar registrar.']);
    }
    exit;
}

// LOGIN
if ($action === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, password, saldo FROM usuarios WHERE username = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        echo json_encode(['status' => 'success', 'message' => '¡Sesión iniciada!', 'saldo' => $row['saldo'], 'username' => $row['username']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Usuario o contraseña incorrectos.']);
    }
    exit;
}

// LOGOUT
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'success', 'message' => 'Sesión cerrada.']);
    exit;
}

// OBTENER SALDO
if ($action === 'get_user_info') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'No has iniciado sesión.']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT username, saldo FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    
    if ($row) {
        echo json_encode(['status' => 'success', 'username' => $row['username'], 'saldo' => $row['saldo']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado en la base de datos.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida o no definida.']);
?>