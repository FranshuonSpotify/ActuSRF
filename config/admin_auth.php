<?php
// Puerta de acceso HTTP Basic para endpoints administrativos/destructivos
// que no tienen (todavía) un sistema de sesión/roles propio.

function cargarSecretos(): array {
    static $secretos = null;
    if ($secretos === null) {
        $secretos = require __DIR__ . '/secrets.php';
    }
    return $secretos;
}

function requerirAdminBasicAuth(): void {
    $secretos = cargarSecretos();
    $usuarioEsperado = $secretos['admin_lesiones_user'] ?? '';
    $hashEsperado = $secretos['admin_lesiones_pass_hash'] ?? '';

    $usuario = $_SERVER['PHP_AUTH_USER'] ?? '';
    $clave = $_SERVER['PHP_AUTH_PW'] ?? '';

    $usuarioValido = $usuarioEsperado !== '' && hash_equals($usuarioEsperado, $usuario);
    $claveValida = $hashEsperado !== '' && password_verify($clave, $hashEsperado);

    if (!$usuarioValido || !$claveValida) {
        header('WWW-Authenticate: Basic realm="Administracion"');
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'no_autorizado']);
        exit;
    }
}
