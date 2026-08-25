<?php

declare(strict_types=1);

final class SnapshotTemporadaRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        int $temporadaId,
        string $clubId,
        string $clubNombre,
        float $gastoSalarial,
        float $dineroTraspasos,
        array $fichas
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO snapshots_temporada
                (temporada_id, club_id, club_nombre, gasto_salarial, dinero_traspasos, fichas)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $temporadaId,
            $clubId,
            $clubNombre,
            $gastoSalarial,
            $dineroTraspasos,
            json_encode($fichas, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    public function existeParaTemporada(int $temporadaId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM snapshots_temporada WHERE temporada_id = ?');
        $stmt->execute([$temporadaId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function listarPorTemporada(int $temporadaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM snapshots_temporada WHERE temporada_id = ? ORDER BY club_nombre'
        );
        $stmt->execute([$temporadaId]);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['fichas'] = json_decode((string) $fila['fichas'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $filas;
    }
}
