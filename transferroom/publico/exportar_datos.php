<?php

declare(strict_types=1);

/** Exportar mis datos (Fase 2, ampliación de Cuenta): volcado JSON descargable de los datos propios del usuario. */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$usuarioId = (int) $usuario['id'];

$datos = [
    'exportado_en' => date('c'),
    'perfil' => [
        'id' => $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email'],
        'rol' => $usuario['rol'],
        'ultimo_acceso' => $usuario['ultimo_acceso'],
    ],
    'clubes_gestionados' => array_map(static function (array $p): array {
        return [
            'temporada' => $p['temporada_nombre'],
            'club' => $p['club_nombre'],
            'division' => $p['division'],
            'estado' => $p['estado'],
        ];
    }, $participaciones->listarPorUsuario($usuarioId)),
    'notificaciones' => $notificaciones->listarPorUsuario($usuarioId),
    'preferencias' => $preferencias->listarPorUsuario($usuarioId),
];

$nombreArchivo = 'transfer_room_mis_datos_' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
