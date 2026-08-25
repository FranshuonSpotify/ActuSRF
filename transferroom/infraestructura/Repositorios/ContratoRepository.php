<?php

declare(strict_types=1);

final class ContratoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        int $jugadorId,
        int $participacionId,
        int $tierId,
        float $salarioAnual,
        int $duracionTemporadas,
        int $temporadaInicioId,
        bool $pendienteRevision = false
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO contratos
                (jugador_id, participacion_id, tier_id, salario_anual, duracion_temporadas, temporada_inicio_id, pendiente_revision)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $jugadorId, $participacionId, $tierId, $salarioAnual, $duracionTemporadas, $temporadaInicioId, $pendienteRevision ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listarPendientesDeRevision(): array
    {
        $stmt = $this->db->query(
            'SELECT c.*, j.nombre AS jugador_nombre, j.posicion, j.origen, t.nombre AS tier_nombre, cl.nombre AS club_nombre
             FROM contratos c
             JOIN jugadores j ON j.id = c.jugador_id
             JOIN tiers t ON t.id = c.tier_id
             JOIN participaciones_club p ON p.id = c.participacion_id
             JOIN clubes cl ON cl.id = p.club_id
             WHERE c.pendiente_revision = 1
             ORDER BY c.creado_en DESC'
        );

        return $stmt->fetchAll();
    }

    public function marcarRevisado(int $contratoId): void
    {
        $stmt = $this->db->prepare('UPDATE contratos SET pendiente_revision = 0 WHERE id = ?');
        $stmt->execute([$contratoId]);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contratos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function buscarActivoDeJugador(int $jugadorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM contratos WHERE jugador_id = ? AND estado = 'ACTIVO' LIMIT 1"
        );
        $stmt->execute([$jugadorId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Ley 38: el gasto salarial nunca se almacena, siempre se calcula a partir de los contratos activos. */
    public function sumaSalariosActivos(int $participacionId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(salario_anual), 0) FROM contratos WHERE participacion_id = ? AND estado = 'ACTIVO'"
        );
        $stmt->execute([$participacionId]);

        return (float) $stmt->fetchColumn();
    }

    public function contarActivosPorParticipacion(int $participacionId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM contratos WHERE participacion_id = ? AND estado = 'ACTIVO'"
        );
        $stmt->execute([$participacionId]);

        return (int) $stmt->fetchColumn();
    }

    public function listarPorParticipacion(int $participacionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, j.nombre AS jugador_nombre, j.posicion, j.es_franquicia, j.foto_url, t.nombre AS tier_nombre
             FROM contratos c
             JOIN jugadores j ON j.id = c.jugador_id
             JOIN tiers t ON t.id = c.tier_id
             WHERE c.participacion_id = ? AND c.estado = "ACTIVO"
             ORDER BY t.orden DESC, j.nombre'
        );
        $stmt->execute([$participacionId]);

        return $stmt->fetchAll();
    }

    /** Todos los contratos activos de la liga, para el buscador global de Mercado (Fase 2). */
    public function listarActivosGlobal(): array
    {
        $stmt = $this->db->query(
            'SELECT c.*, cl.nombre AS club_nombre, cl.escudo_url AS club_escudo_url
             FROM contratos c
             JOIN participaciones_club p ON p.id = c.participacion_id
             JOIN clubes cl ON cl.id = p.club_id
             WHERE c.estado = "ACTIVO"'
        );

        return $stmt->fetchAll();
    }

    /**
     * Contratos activos cuya cobertura termina exactamente en la temporada
     * indicada (temporada_inicio.numero + duracion_temporadas - 1 = numero).
     * Motor de Fin de Temporada: son los que deben expirar a agencia libre.
     */
    public function listarActivosQueExpiranEnTemporada(int $numeroTemporada): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.club_id
             FROM contratos c
             JOIN temporadas t ON t.id = c.temporada_inicio_id
             JOIN participaciones_club p ON p.id = c.participacion_id
             WHERE c.estado = "ACTIVO"
               AND (t.numero + c.duracion_temporadas - 1) = ?'
        );
        $stmt->execute([$numeroTemporada]);

        return $stmt->fetchAll();
    }

    /** Historial deportivo de un jugador: todos sus contratos, cualquier estado (Cap. XX). */
    public function listarTodosPorJugador(int $jugadorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, t.nombre AS tier_nombre, cl.nombre AS club_nombre, tmp.numero AS temporada_numero
             FROM contratos c
             JOIN tiers t ON t.id = c.tier_id
             JOIN participaciones_club p ON p.id = c.participacion_id
             JOIN clubes cl ON cl.id = p.club_id
             JOIN temporadas tmp ON tmp.id = c.temporada_inicio_id
             WHERE c.jugador_id = ?
             ORDER BY c.creado_en ASC'
        );
        $stmt->execute([$jugadorId]);

        return $stmt->fetchAll();
    }

    /** Historial deportivo de un club: todos los contratos de cualquiera de sus participaciones, en cualquier temporada. */
    public function listarTodosPorClub(string $clubId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, t.nombre AS tier_nombre, j.nombre AS jugador_nombre, j.posicion, tmp.numero AS temporada_numero
             FROM contratos c
             JOIN tiers t ON t.id = c.tier_id
             JOIN participaciones_club p ON p.id = c.participacion_id
             JOIN jugadores j ON j.id = c.jugador_id
             JOIN temporadas tmp ON tmp.id = c.temporada_inicio_id
             WHERE p.club_id = ?
             ORDER BY c.creado_en ASC'
        );
        $stmt->execute([$clubId]);

        return $stmt->fetchAll();
    }

    public function finalizar(int $contratoId): void
    {
        $stmt = $this->db->prepare("UPDATE contratos SET estado = 'FINALIZADO' WHERE id = ?");
        $stmt->execute([$contratoId]);
    }

    /** Checklist de primer mercado (A.3): marca informativa, no cambia ninguna regla de oferta. */
    public function alternarTransferible(int $contratoId): void
    {
        $stmt = $this->db->prepare('UPDATE contratos SET transferible = NOT transferible WHERE id = ?');
        $stmt->execute([$contratoId]);
    }

    /** Corrección administrativa de tier/versión (ContractEngine::cambiarTierJugador): ajusta el contrato activo en sitio, no crea uno nuevo. */
    public function actualizarTierYSalario(int $contratoId, int $tierId, float $salarioAnual): void
    {
        $stmt = $this->db->prepare('UPDATE contratos SET tier_id = ?, salario_anual = ? WHERE id = ?');
        $stmt->execute([$tierId, $salarioAnual, $contratoId]);
    }
}
