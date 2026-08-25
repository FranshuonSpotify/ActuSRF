<?php

declare(strict_types=1);

final class AfinidadRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function obtenerOCrearPorNombre(string $nombre): int
    {
        $stmt = $this->db->prepare('SELECT id FROM afinidades WHERE nombre = ? LIMIT 1');
        $stmt->execute([$nombre]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = $this->db->prepare('INSERT INTO afinidades (nombre) VALUES (?)');
        $stmt->execute([$nombre]);

        return (int) $this->db->lastInsertId();
    }

    public function listarTodas(): array
    {
        return $this->db->query('SELECT * FROM afinidades ORDER BY nombre')->fetchAll();
    }
}
