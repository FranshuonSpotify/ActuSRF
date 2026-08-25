<?php

declare(strict_types=1);

/**
 * Log de errores técnicos, separado de la auditoría de negocio
 * (05-transfer_room_docs/01_bloqueante_primer_mercado/10): excepciones no
 * controladas y errores PHP, no acciones de usuario. Si ni siquiera se
 * puede escribir en la BD (p. ej. la propia conexión ha caído), cae a
 * error_log() nativo de PHP como último recurso.
 */
final class ErrorLogger
{
    private static ?PDO $db = null;

    public static function instalar(PDO $db): void
    {
        self::$db = $db;

        set_exception_handler(static function (Throwable $e): void {
            self::registrar('EXCEPCION', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            http_response_code(500);
            echo 'Ha ocurrido un error inesperado. El administrador ya ha sido informado.';
        });

        set_error_handler(static function (int $severidad, string $mensaje, string $archivo, int $linea): bool {
            if (!(error_reporting() & $severidad)) {
                return false; // respeta @ y niveles silenciados, igual que el comportamiento nativo
            }
            self::registrar('PHP_ERROR', $mensaje, $archivo, $linea, null);

            return false; // deja que PHP siga con su manejo normal además de registrar
        });
    }

    private static function registrar(string $nivel, string $mensaje, ?string $archivo, ?int $linea, ?string $traza): void
    {
        try {
            $usuarioId = $_SESSION['usuario_id'] ?? null;
            $url = $_SERVER['REQUEST_URI'] ?? null;

            $stmt = self::$db->prepare(
                'INSERT INTO errores_tecnicos (nivel, mensaje, archivo, linea, url, usuario_id, traza) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nivel, $mensaje, $archivo, $linea, $url, $usuarioId, $traza]);
        } catch (Throwable $e) {
            error_log("[Transfer Room] {$nivel}: {$mensaje} en {$archivo}:{$linea}");
        }
    }
}
