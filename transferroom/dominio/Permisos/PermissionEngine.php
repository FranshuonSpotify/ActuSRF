<?php

declare(strict_types=1);

/**
 * Único punto que decide "¿puede hacer X?". Nunca comprobar $_SESSION['rol']
 * directamente fuera de este motor (Ley 77/78).
 */
final class PermissionEngine
{
    public function esAdministrador(array $usuario): bool
    {
        return $usuario['rol'] === 'ADMINISTRADOR';
    }

    public function puedeGestionarTemporadas(array $usuario): bool
    {
        return $this->esAdministrador($usuario);
    }

    public function puedeGestionarClubes(array $usuario): bool
    {
        return $this->esAdministrador($usuario);
    }

    public function puedeGestionarParticipacion(array $usuario, array $participacion): bool
    {
        if ($this->esAdministrador($usuario)) {
            return true;
        }

        return (int) ($participacion['usuario_presidente_id'] ?? 0) === (int) $usuario['id'];
    }

    public function requerirAdministrador(array $usuario): void
    {
        if (!$this->esAdministrador($usuario)) {
            http_response_code(403);
            throw new DomainException('No tienes permisos de administrador para realizar esta acción.');
        }
    }
}
