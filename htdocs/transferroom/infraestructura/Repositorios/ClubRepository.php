<?php

declare(strict_types=1);

final class ClubRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT * FROM clubes ORDER BY nombre')->fetchAll();
    }

    public function buscarPorId(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clubes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function upsert(
        string $id,
        string $nombre,
        ?string $escudoUrl,
        ?string $ciudad,
        ?string $color1,
        ?string $color2,
        ?string $abreviatura
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO clubes (id, nombre, escudo_url, ciudad, color1, color2, abreviatura)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                escudo_url = VALUES(escudo_url),
                ciudad = VALUES(ciudad),
                color1 = VALUES(color1),
                color2 = VALUES(color2),
                abreviatura = VALUES(abreviatura)'
        );
        $stmt->execute([$id, $nombre, $escudoUrl, $ciudad, $color1, $color2, $abreviatura]);
    }
}
