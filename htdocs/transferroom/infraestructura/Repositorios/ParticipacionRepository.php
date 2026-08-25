<?php

declare(strict_types=1);

final class ParticipacionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        string $clubId,
        int $temporadaId,
        ?int $usuarioPresidenteId,
        string $division,
        string $origenPlantilla
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO participaciones_club
                (club_id, temporada_id, usuario_presidente_id, division, origen_plantilla, estado)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $clubId,
            $temporadaId,
            $usuarioPresidenteId,
            $division,
            $origenPlantilla,
            $usuarioPresidenteId !== null ? 'CONFIRMADA' : 'PENDIENTE',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM participaciones_club WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function buscarPorClubYTemporada(string $clubId, int $temporadaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM participaciones_club WHERE club_id = ? AND temporada_id = ? LIMIT 1'
        );
        $stmt->execute([$clubId, $temporadaId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Para el semáforo de salud del hub (nav.php, en toda página): la participación del presidente en la temporada activa, si tiene una. */
    public function buscarPorUsuarioYTemporada(int $usuarioPresidenteId, int $temporadaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM participaciones_club WHERE usuario_presidente_id = ? AND temporada_id = ? AND estado != "RETIRADA" LIMIT 1'
        );
        $stmt->execute([$usuarioPresidenteId, $temporadaId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Para exportar datos (Fase 2, Cuenta): historial de clubes gestionados por un usuario, en cualquier temporada. */
    public function listarPorUsuario(int $usuarioPresidenteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.nombre AS club_nombre, t.nombre AS temporada_nombre
             FROM participaciones_club p
             JOIN clubes c ON c.id = p.club_id
             JOIN temporadas t ON t.id = p.temporada_id
             WHERE p.usuario_presidente_id = ?
             ORDER BY t.numero DESC'
        );
        $stmt->execute([$usuarioPresidenteId]);

        return $stmt->fetchAll();
    }

    public function listarPorTemporada(int $temporadaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.nombre AS club_nombre, c.escudo_url, u.nombre AS presidente_nombre
             FROM participaciones_club p
             JOIN clubes c ON c.id = p.club_id
             LEFT JOIN usuarios u ON u.id = p.usuario_presidente_id
             WHERE p.temporada_id = ?
             ORDER BY p.division, c.nombre'
        );
        $stmt->execute([$temporadaId]);

        return $stmt->fetchAll();
    }

    /** Última participación del club en una temporada anterior a $numeroTemporadaActual (Cap. XXIII). */
    public function buscarAnteriorDelClub(string $clubId, int $numeroTemporadaActual): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*
             FROM participaciones_club p
             JOIN temporadas t ON t.id = p.temporada_id
             WHERE p.club_id = ? AND t.numero < ?
             ORDER BY t.numero DESC
             LIMIT 1'
        );
        $stmt->execute([$clubId, $numeroTemporadaActual]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE participaciones_club SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    public function actualizarOrigenPlantilla(int $id, string $origenPlantilla): void
    {
        $stmt = $this->db->prepare('UPDATE participaciones_club SET origen_plantilla = ? WHERE id = ?');
        $stmt->execute([$origenPlantilla, $id]);
    }

    public function asignarPresidente(int $id, int $usuarioPresidenteId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE participaciones_club SET usuario_presidente_id = ?, estado = 'CONFIRMADA' WHERE id = ?"
        );
        $stmt->execute([$usuarioPresidenteId, $id]);
    }
}
