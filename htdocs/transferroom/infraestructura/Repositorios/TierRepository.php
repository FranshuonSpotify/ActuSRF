<?php

declare(strict_types=1);

final class TierRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function listarTodas(): array
    {
        return $this->db->query('SELECT * FROM tiers ORDER BY orden')->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function actualizarSalarioBase(int $id, float $salarioBase): void
    {
        $stmt = $this->db->prepare('UPDATE tiers SET salario_base = ? WHERE id = ?');
        $stmt->execute([$salarioBase, $id]);
    }
}
