<?php

declare(strict_types=1);

/**
 * Eventos dirigidos a un usuario concreto. Nunca modifican negocio, solo
 * informan. Se generan desde la página que orquesta la operación de origen,
 * nunca desde dentro de otro motor (Notificaciones va después de Mercado/
 * Ofertas/Finanzas en el orden del Cap. III; esos módulos nunca deben
 * depender de este, Ley 19).
 */
final class NotificationEngine
{
    public function __construct(
        private NotificacionRepository $notificaciones,
        private PreferenciaRepository $preferencias
    ) {
    }

    public function crear(?int $usuarioId, string $tipo, string $mensaje, ?string $enlace = null): void
    {
        if ($usuarioId === null) {
            return; // sin presidente asignado todavía: no hay a quién notificar
        }

        // Preferencias granulares (Fase 2): ausencia de fila = activado (opt-out, no opt-in).
        if ($this->preferencias->obtener($usuarioId, 'notif_' . $tipo) === '0') {
            return;
        }

        $this->notificaciones->crear($usuarioId, $tipo, $mensaje, $enlace);
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        return $this->notificaciones->contarNoLeidas($usuarioId);
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        return $this->notificaciones->listarPorUsuario($usuarioId);
    }

    public function listarNoLeidasDesde(int $usuarioId, int $desdeId): array
    {
        return $this->notificaciones->listarNoLeidasDesde($usuarioId, $desdeId);
    }

    public function marcarLeida(int $id, int $usuarioId): void
    {
        $this->notificaciones->marcarLeida($id, $usuarioId);
    }

    public function marcarTodasLeidas(int $usuarioId): void
    {
        $this->notificaciones->marcarTodasLeidas($usuarioId);
    }
}
