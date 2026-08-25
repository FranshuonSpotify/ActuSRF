<?php

declare(strict_types=1);

final class OfertaTraspasoRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        int $jugadorId,
        int $contratoId,
        int $participacionVendedoraId,
        int $participacionCompradoraId,
        float $importeTraspaso,
        ?int $ofertaPadreId = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO ofertas_traspaso
                (jugador_id, contrato_id, oferta_padre_id, participacion_vendedora_id, participacion_compradora_id, importe_traspaso)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$jugadorId, $contratoId, $ofertaPadreId, $participacionVendedoraId, $participacionCompradoraId, $importeTraspaso]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ofertas_traspaso WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function buscarPendienteDeJugadorYComprador(int $jugadorId, int $participacionCompradoraId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ofertas_traspaso
             WHERE jugador_id = ? AND participacion_compradora_id = ? AND estado = 'PENDIENTE' LIMIT 1"
        );
        $stmt->execute([$jugadorId, $participacionCompradoraId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function listarPendientesPorVendedora(int $participacionVendedoraId): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, j.nombre AS jugador_nombre, j.posicion, cc.nombre AS club_comprador_nombre, cc.escudo_url AS club_comprador_escudo
             FROM ofertas_traspaso o
             JOIN jugadores j ON j.id = o.jugador_id
             JOIN participaciones_club pc ON pc.id = o.participacion_compradora_id
             JOIN clubes cc ON cc.id = pc.club_id
             WHERE o.participacion_vendedora_id = ? AND o.estado = "PENDIENTE"
             ORDER BY o.creado_en ASC'
        );
        $stmt->execute([$participacionVendedoraId]);

        return $stmt->fetchAll();
    }

    /** Ofertas de traspaso que ENVIÉ y siguen pendientes: para "mis ofertas activas" del comprador. */
    public function listarPendientesPorCompradora(int $participacionCompradoraId): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, j.nombre AS jugador_nombre, j.posicion, cv.nombre AS club_vendedor_nombre, cv.escudo_url AS club_vendedor_escudo
             FROM ofertas_traspaso o
             JOIN jugadores j ON j.id = o.jugador_id
             JOIN participaciones_club pv ON pv.id = o.participacion_vendedora_id
             JOIN clubes cv ON cv.id = pv.club_id
             WHERE o.participacion_compradora_id = ? AND o.estado = "PENDIENTE"
             ORDER BY o.creado_en ASC'
        );
        $stmt->execute([$participacionCompradoraId]);

        return $stmt->fetchAll();
    }

    public function marcarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE ofertas_traspaso SET estado = ?, resuelto_en = NOW() WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    /**
     * Reclama la oferta de forma atómica: solo tiene éxito si todavía está
     * PENDIENTE en el momento exacto del UPDATE (condición de carrera, Cap.
     * II 2.4 del protocolo — verificado con dos aceptaciones simultáneas
     * reales: sin esto, ambas completaban el traspaso y duplicaban contrato
     * y pago). Debe ser la primera escritura dentro de la transacción de
     * aceptación, antes de tocar contratos o dinero.
     */
    public function intentarMarcarAceptada(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE ofertas_traspaso SET estado = 'ACEPTADA', resuelto_en = NOW() WHERE id = ? AND estado = 'PENDIENTE'");
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    /** Alertas de seguimiento (Fase 2, Scouting): ofertas de traspaso pendientes sobre un jugador, sin importar el club. */
    public function listarPendientesPorJugador(int $jugadorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*, cc.nombre AS club_comprador_nombre, cc.escudo_url AS club_comprador_escudo_url
             FROM ofertas_traspaso o
             JOIN participaciones_club pc ON pc.id = o.participacion_compradora_id
             JOIN clubes cc ON cc.id = pc.club_id
             WHERE o.jugador_id = ? AND o.estado = "PENDIENTE"
             ORDER BY o.creado_en ASC'
        );
        $stmt->execute([$jugadorId]);

        return $stmt->fetchAll();
    }

    /** Historial deportivo: traspasos completados de un jugador (Cap. XX). */
    public function listarAceptadasPorJugador(int $jugadorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, cv.nombre AS club_vendedor_nombre, cc.nombre AS club_comprador_nombre
             FROM ofertas_traspaso o
             JOIN participaciones_club pv ON pv.id = o.participacion_vendedora_id
             JOIN clubes cv ON cv.id = pv.club_id
             JOIN participaciones_club pc ON pc.id = o.participacion_compradora_id
             JOIN clubes cc ON cc.id = pc.club_id
             WHERE o.jugador_id = ? AND o.estado = 'ACEPTADA'
             ORDER BY o.resuelto_en ASC"
        );
        $stmt->execute([$jugadorId]);

        return $stmt->fetchAll();
    }

    /** Historial deportivo: traspasos completados donde este club compró o vendió, en cualquier temporada. */
    public function listarAceptadasPorClub(string $clubId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, j.nombre AS jugador_nombre, cv.nombre AS club_vendedor_nombre, cc.nombre AS club_comprador_nombre
             FROM ofertas_traspaso o
             JOIN jugadores j ON j.id = o.jugador_id
             JOIN participaciones_club pv ON pv.id = o.participacion_vendedora_id
             JOIN clubes cv ON cv.id = pv.club_id
             JOIN participaciones_club pc ON pc.id = o.participacion_compradora_id
             JOIN clubes cc ON cc.id = pc.club_id
             WHERE (pv.club_id = ? OR pc.club_id = ?) AND o.estado = 'ACEPTADA'
             ORDER BY o.resuelto_en ASC"
        );
        $stmt->execute([$clubId, $clubId]);

        return $stmt->fetchAll();
    }
}
