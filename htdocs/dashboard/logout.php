<?php
// dashboard/logout.php
// Cierra la sesión del presidente y devuelve al login.

// !headers_sent() ademas del estado: no se puede abrir una sesion despues
// de enviar cabeceras. Aqui no hay arnes que imprima antes, pero se mantiene
// la misma guarda en todas las pantallas para que no haya dos convenciones.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/lib.php';   // plRedirigir()

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
plRedirigir('index.php');
