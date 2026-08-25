<?php

declare(strict_types=1);

final class AnuncioRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(string $titulo, string $cuerpo, string $publicarEn, int $creadoPor): int
    {
        $stmt = $this->db->prepare('INSERT INTO anuncios (titulo, cuerpo, publicar_en, creado_por) VALUES (?, ?, ?, ?)');
        $stmt->execute([$titulo, $cuerpo, $publicarEn, $creadoPor]);

        return (int) $this->db->lastInsertId();
    }

    public function listarProgramados(): array
    {
        $stmt = $this->db->query(
            'SELECT a.*, u.nombre AS creado_por_nombre
             FROM anuncios a LEFT JOIN usuarios u ON u.id = a.creado_por
             WHERE a.publicar_en > NOW() ORDER BY a.publicar_en ASC'
        );

        return $stmt->fetchAll();
    }

    public function listarPublicados(int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.nombre AS creado_por_nombre
             FROM anuncios a LEFT JOIN usuarios u ON u.id = a.creado_por
             WHERE a.publicar_en <= NOW() ORDER BY a.publicar_en DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM anuncios WHERE id = ?');
        $stmt->execute([$id]);
    }
}
