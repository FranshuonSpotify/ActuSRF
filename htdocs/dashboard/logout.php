<?php
// dashboard/logout.php
// Cierra la sesión del presidente y devuelve al login.

// !headers_sent() ademas del estado: no se puede abrir una sesion despues
// de enviar cabeceras, y el arnes de tests renderiza la pantalla cuando ya
// ha impreso. En una peticion real esta pantalla es el punto de entrada y
// nada ha salido todavia, asi que la sesion se abre igual.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
