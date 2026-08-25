<?php

declare(strict_types=1);

final class PeticionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        int $participacionId,
        string $descripcion,
        ?string $posicionesJson,
        ?float $topeSalarial,
        ?string $afinidad,
        ?int $tierMinimoId
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO peticiones (participacion_id, descripcion, posiciones, tope_salarial, afinidad, tier_minimo_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$participacionId, $descripcion, $posicionesJson, $topeSalarial, $afinidad, $tierMinimoId]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM peticiones WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Peticiones abiertas de CUALQUIER club, para que otros presidentes puedan proponer. */
    public function listarAbiertas(): array
    {
        $stmt = $this->db->query(
            "SELECT p.*, c.nombre AS club_nombre, t.nombre AS tier_minimo_nombre, t.orden AS tier_minimo_orden
             FROM peticiones p
             JOIN participaciones_club pc ON pc.id = p.participacion_id
             JOIN clubes c ON c.id = pc.club_id
             LEFT JOIN tiers t ON t.id = p.tier_minimo_id
             WHERE p.estado = 'ABIERTA'
             ORDER BY p.creado_en DESC"
        );

        return $stmt->fetchAll();
    }

    public function listarPorParticipacion(int $participacionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, t.nombre AS tier_minimo_nombre, t.orden AS tier_minimo_orden
             FROM peticiones p
             LEFT JOIN tiers t ON t.id = p.tier_minimo_id
             WHERE p.participacion_id = ?
             ORDER BY p.creado_en DESC'
        );
        $stmt->execute([$participacionId]);

        return $stmt->fetchAll();
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE peticiones SET estado = ?, resuelto_en = NOW() WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    /**
     * Reclama de forma atómica una petición ABIERTA para cerrarla (mismo
     * patrón que OfertaTraspasoRepository::intentarMarcarAceptada — verificado
     * con dos propuestas de la misma petición aceptadas de verdad a la vez:
     * sin esto, las DOS quedaban ACEPTADA, violando "nunca aceptar dos
     * propuestas" del Título XVI).
     */
    public function intentarCerrar(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE peticiones SET estado = 'CERRADA', resuelto_en = NOW() WHERE id = ? AND estado = 'ABIERTA'");
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }
}
