<?php

declare(strict_types=1);

/**
 * "Actuar como" (05-transfer_room_docs/01_bloqueante_primer_mercado/10, §1):
 * un administrador puede entrar en el sandbox de un club concreto para
 * probar el flujo real sin usar la cuenta del presidente. Vive en sesión,
 * no en base de datos: es un modo de trabajo del administrador, no un
 * estado del negocio. Nunca permite acciones irreversibles (decisión de
 * Franshu) y se auto-desactiva por inactividad.
 */
final class ModoPruebas
{
    private const MINUTOS_INACTIVIDAD = 30;

    public static function activar(int $participacionId): void
    {
        $_SESSION['modo_pruebas_participacion_id'] = $participacionId;
        $_SESSION['modo_pruebas_ultima_actividad'] = time();
    }

    public static function desactivar(): void
    {
        unset($_SESSION['modo_pruebas_participacion_id'], $_SESSION['modo_pruebas_ultima_actividad']);
    }

    /** Devuelve la participación activa, o null si no hay modo pruebas o caducó por inactividad. */
    public static function participacionActiva(): ?int
    {
        if (!isset($_SESSION['modo_pruebas_participacion_id'])) {
            return null;
        }

        $ultimaActividad = (int) ($_SESSION['modo_pruebas_ultima_actividad'] ?? 0);
        if (time() - $ultimaActividad > self::MINUTOS_INACTIVIDAD * 60) {
            self::desactivar();

            return null;
        }

        $_SESSION['modo_pruebas_ultima_actividad'] = time();

        return (int) $_SESSION['modo_pruebas_participacion_id'];
    }

    public static function activo(): bool
    {
        return self::participacionActiva() !== null;
    }

    /** Llamar al principio de cualquier acción con consecuencia irreversible. */
    public static function bloquearSiActivo(): void
    {
        if (self::activo()) {
            throw new DomainException(
                'Estás en modo pruebas: esta acción es irreversible y no está permitida aquí. Sal del modo pruebas para ejecutarla de verdad.'
            );
        }
    }
}
