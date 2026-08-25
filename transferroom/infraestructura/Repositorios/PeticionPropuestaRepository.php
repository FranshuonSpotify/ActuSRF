<?php

declare(strict_types=1);

final class PeticionPropuestaRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(int $peticionId, int $participacionId, ?int $jugadorId, string $mensaje): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO peticion_propuestas (peticion_id, participacion_id, jugador_id, mensaje) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$peticionId, $participacionId, $jugadorId, $mensaje]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM peticion_propuestas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function listarPorPeticion(int $peticionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pp.*, c.nombre AS club_nombre, j.nombre AS jugador_nombre
             FROM peticion_propuestas pp
             JOIN participaciones_club pc ON pc.id = pp.participacion_id
             JOIN clubes c ON c.id = pc.club_id
             LEFT JOIN jugadores j ON j.id = pp.jugador_id
             WHERE pp.peticion_id = ?
             ORDER BY pp.creado_en ASC"
        );
        $stmt->execute([$peticionId]);

        return $stmt->fetchAll();
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE peticion_propuestas SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    /** Todas menos la aceptada, para cerrarlas automáticamente (Título XVI). */
    public function cerrarLasDemas(int $peticionId, int $propuestaAceptadaId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE peticion_propuestas SET estado = 'CERRADA_AUTOMATICAMENTE'
             WHERE peticion_id = ? AND id != ? AND estado = 'PENDIENTE'"
        );
        $stmt->execute([$peticionId, $propuestaAceptadaId]);
    }
}
