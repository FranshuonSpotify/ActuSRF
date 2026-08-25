<?php

declare(strict_types=1);

/**
 * Preferencias de usuario (Fase 2, hub v3, ampliación de Cuenta): modelo
 * opt-out, no opt-in — ausencia de fila = activado. Sin especificación
 * previa, diseño propio.
 */
final class PreferenciaEngine
{
    public function __construct(private PreferenciaRepository $repo)
    {
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        return $this->repo->listarPorUsuario($usuarioId);
    }

    public function estaActivo(int $usuarioId, string $clave): bool
    {
        return $this->repo->obtener($usuarioId, $clave) !== '0';
    }

    public function establecerBool(int $usuarioId, string $clave, bool $activo): void
    {
        $this->repo->establecer($usuarioId, $clave, $activo ? '1' : '0');
    }

    public function establecerTexto(int $usuarioId, string $clave, string $valor): void
    {
        $this->repo->establecer($usuarioId, $clave, $valor);
    }

    public function obtenerTexto(int $usuarioId, string $clave): string
    {
        return $this->repo->obtener($usuarioId, $clave) ?? '';
    }
}
