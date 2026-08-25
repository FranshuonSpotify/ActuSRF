<?php

declare(strict_types=1);

final class TemporadaRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(int $numero, string $nombre, float $salaryCap, int $maxFranquicias): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO temporadas (numero, nombre, salary_cap, max_franquicias) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$numero, $nombre, $salaryCap, $maxFranquicias]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM temporadas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function buscarPorNumero(int $numero): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM temporadas WHERE numero = ? LIMIT 1');
        $stmt->execute([$numero]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function buscarActiva(): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM temporadas WHERE estado != 'ARCHIVADA' ORDER BY numero DESC LIMIT 1"
        );
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function listarTodas(): array
    {
        return $this->db->query('SELECT * FROM temporadas ORDER BY numero DESC')->fetchAll();
    }

    /** Otras temporadas (excluyendo $idExcluido) que ya están en alguno de $estados. */
    public function listarOtrasEnEstados(int $idExcluido, array $estados): array
    {
        $marcadores = implode(',', array_fill(0, count($estados), '?'));
        $stmt = $this->db->prepare("SELECT * FROM temporadas WHERE id != ? AND estado IN ($marcadores)");
        $stmt->execute([$idExcluido, ...$estados]);

        return $stmt->fetchAll();
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE temporadas SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    public function actualizarCongelada(int $id, bool $congelada): void
    {
        $stmt = $this->db->prepare('UPDATE temporadas SET congelada = ? WHERE id = ?');
        $stmt->execute([$congelada ? 1 : 0, $id]);
    }
}
