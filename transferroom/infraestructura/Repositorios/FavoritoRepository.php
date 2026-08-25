<?php

declare(strict_types=1);

final class FavoritoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuario_favoritos WHERE usuario_id = ? ORDER BY orden, id');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function existe(int $usuarioId, string $ruta): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM usuario_favoritos WHERE usuario_id = ? AND ruta = ?');
        $stmt->execute([$usuarioId, $ruta]);

        return $stmt->fetch() !== false;
    }

    public function crear(int $usuarioId, string $ruta, string $etiqueta): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(orden), -1) + 1 FROM usuario_favoritos WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        $orden = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('INSERT INTO usuario_favoritos (usuario_id, ruta, etiqueta, orden) VALUES (?, ?, ?, ?)');
        $stmt->execute([$usuarioId, $ruta, $etiqueta, $orden]);

        return (int) $this->db->lastInsertId();
    }

    public function eliminar(int $usuarioId, string $ruta): void
    {
        $stmt = $this->db->prepare('DELETE FROM usuario_favoritos WHERE usuario_id = ? AND ruta = ?');
        $stmt->execute([$usuarioId, $ruta]);
    }
}
