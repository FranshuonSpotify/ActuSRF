<?php
// lesiones/tests/test_admin.php
// admin.php no se puede ejecutar en CLI sin Basic Auth real (ver nota de la
// tarea 6 del plan), así que aquí se comprueba por inspección estática que
// la puerta de acceso y el CSRF están en el orden correcto. El comportamiento
// de la ruleta en sí ya lo cubre test_almacen.php sobre leTirarParaEquipo().
// Ejecutar: /c/xampp/php/php.exe lesiones/tests/test_admin.php

require_once __DIR__ . '/arnes.php';

$fuente = file_get_contents(__DIR__ . '/../admin.php');

leVerificar('admin.php requiere config/admin_auth.php', str_contains($fuente, "require_once __DIR__ . '/../config/admin_auth.php'"));

$posAuth = strpos($fuente, 'requerirAdminBasicAuth()');
$posAlmacen = strpos($fuente, "require_once __DIR__ . '/almacen.php'");
leVerificar('requerirAdminBasicAuth() se llama antes de cargar almacen.php', $posAuth !== false && $posAlmacen !== false && $posAuth < $posAlmacen);

$posSesion = strpos($fuente, 'session_start');
leVerificar('requerirAdminBasicAuth() se llama antes de abrir sesión', $posAuth !== false && $posSesion !== false && $posAuth < $posSesion);

$posCsrf = strpos($fuente, 'leCsrfValido()');
$posTirar = strpos($fuente, 'leTirarParaEquipo(');
leVerificar('el CSRF se valida antes de tirar la ruleta', $posCsrf !== false && $posTirar !== false && $posCsrf < $posTirar);

$posCerrar = strpos($fuente, 'leCerrarLesion(');
$posAnular = strpos($fuente, 'leAnularTirada(');
// La validación que protege estas rutas es la suya propia, no la de la ruta
// de tirar: el leCsrfValido() más cercano por delante va después de tirar.
leVerificar('el CSRF se valida antes de cerrar una lesión', $posCerrar !== false
    && strrpos(substr($fuente, 0, $posCerrar), 'leCsrfValido()') > strpos($fuente, 'leTirarParaEquipo('));
leVerificar('el CSRF se valida antes de anular una tirada', $posAnular !== false
    && strrpos(substr($fuente, 0, $posAnular), 'leCsrfValido()') > strpos($fuente, 'leTirarParaEquipo('));
$posRedirigir = strpos($fuente, 'leRedirigir(');
leVerificar('cerrar y anular terminan con leRedirigir()', $posRedirigir !== false && $posCerrar !== false && $posAnular !== false
    && $posRedirigir > $posCerrar && $posRedirigir > $posAnular);

leVerificar('la acción AJAX responde con leResponderJson', str_contains($fuente, 'leResponderJson('));
leVerificar('con evento, la respuesta incluye la plantilla para la tira', str_contains($fuente, "\$resultado['plantilla']"));
leVerificar('usa los componentes del dashboard', str_contains($fuente, '../dashboard/css/dashboard.css'));
leVerificar('no queda ningún exit a pelo en admin.php', !preg_match('/\bexit\b/', $fuente));

leSalirConResultado();
