<?php

declare(strict_types=1);

final class AuditoriaRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function registrar(
        ?int $usuarioId,
        ?string $ip,
        string $accion,
        string $entidad,
        ?string $entidadId,
        ?string $valorAntes,
        ?string $valorDespues,
        string $resultado
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO auditoria (usuario_id, ip, accion, entidad, entidad_id, valor_antes, valor_despues, resultado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$usuarioId, $ip, $accion, $entidad, $entidadId, $valorAntes, $valorDespues, $resultado]);
    }

    public function listarRecientes(int $limite = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.nombre AS usuario_nombre
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY a.creado_en DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
