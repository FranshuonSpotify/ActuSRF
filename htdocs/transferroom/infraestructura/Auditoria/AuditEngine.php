<?php

declare(strict_types=1);

final class AuditEngine
{
    public function __construct(private AuditoriaRepository $repositorio)
    {
    }

    public function registrar(
        ?int $usuarioId,
        string $accion,
        string $entidad,
        ?string $entidadId,
        ?array $antes,
        ?array $despues,
        string $resultado = 'OK'
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $this->repositorio->registrar(
            $usuarioId,
            $ip,
            $accion,
            $entidad,
            $entidadId,
            $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            $resultado
        );
    }

    public function listarRecientes(int $limite = 200): array
    {
        return $this->repositorio->listarRecientes($limite);
    }
}
