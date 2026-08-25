<?php

declare(strict_types=1);

final class OfertaAgenteLibreRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(int $jugadorId, int $participacionId, float $salarioOfertado, int $duracionTemporadas): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ofertas_agente_libre (jugador_id, participacion_id, salario_ofertado, duracion_temporadas)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$jugadorId, $participacionId, $salarioOfertado, $duracionTemporadas]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPendienteDeParticipacion(int $jugadorId, int $participacionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ofertas_agente_libre WHERE jugador_id = ? AND participacion_id = ? AND estado = 'PENDIENTE' LIMIT 1"
        );
        $stmt->execute([$jugadorId, $participacionId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Ordenada para que la primera fila sea siempre la ganadora (mercado.md §7).
     * Incluye el club ofertante: las pujas son públicas (decisión de Alejandro).
     */
    public function listarPendientesPorJugador(int $jugadorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, p.club_id, c.nombre AS club_nombre
             FROM ofertas_agente_libre o
             JOIN participaciones_club p ON p.id = o.participacion_id
             JOIN clubes c ON c.id = p.club_id
             WHERE o.jugador_id = ? AND o.estado = 'PENDIENTE'
             ORDER BY o.salario_ofertado DESC, o.creado_en ASC"
        );
        $stmt->execute([$jugadorId]);

        return $stmt->fetchAll();
    }

    public function actualizar(int $id, float $salarioOfertado, int $duracionTemporadas): void
    {
        $stmt = $this->db->prepare('UPDATE ofertas_agente_libre SET salario_ofertado = ?, duracion_temporadas = ? WHERE id = ?');
        $stmt->execute([$salarioOfertado, $duracionTemporadas, $id]);
    }

    public function marcarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE ofertas_agente_libre SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $id]);
    }

    /**
     * Reclama de forma atómica una oferta PENDIENTE para resolverla (ganadora
     * directa) o abrir su ventana de igualación (mismo patrón que
     * OfertaTraspasoRepository::intentarMarcarAceptada, verificado con dos
     * resoluciones simultáneas reales sobre el mismo jugador).
     */
    public function intentarReclamarPendiente(int $id, string $nuevoEstado): bool
    {
        $stmt = $this->db->prepare("UPDATE ofertas_agente_libre SET estado = ? WHERE id = ? AND estado = 'PENDIENTE'");
        $stmt->execute([$nuevoEstado, $id]);

        return $stmt->rowCount() === 1;
    }

    /** mercado.md §6.3: abre la ventana de igualación de 48h para el club de origen de un RFA, solo si sigue PENDIENTE. */
    public function intentarMarcarPendienteIgualacion(int $id, int $horasVentana): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE ofertas_agente_libre SET estado = 'PENDIENTE_IGUALACION', fecha_limite_igualacion = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ? AND estado = 'PENDIENTE'"
        );
        $stmt->execute([$horasVentana, $id]);

        return $stmt->rowCount() === 1;
    }

    /** Reclama de forma atómica una ventana PENDIENTE_IGUALACION para igualarla o confirmarla sin igualar. */
    public function intentarReclamarVentanaIgualacion(int $id, string $nuevoEstado): bool
    {
        $stmt = $this->db->prepare("UPDATE ofertas_agente_libre SET estado = ? WHERE id = ? AND estado = 'PENDIENTE_IGUALACION'");
        $stmt->execute([$nuevoEstado, $id]);

        return $stmt->rowCount() === 1;
    }

    public function buscarPendienteIgualacionDeJugador(int $jugadorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, p.club_id, c.nombre AS club_nombre
             FROM ofertas_agente_libre o
             JOIN participaciones_club p ON p.id = o.participacion_id
             JOIN clubes c ON c.id = p.club_id
             WHERE o.jugador_id = ? AND o.estado = 'PENDIENTE_IGUALACION' LIMIT 1"
        );
        $stmt->execute([$jugadorId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Pujas activas hechas por un club concreto: para el panel del presidente. */
    public function listarPendientesPorParticipacion(int $participacionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, j.nombre AS jugador_nombre
             FROM ofertas_agente_libre o
             JOIN jugadores j ON j.id = o.jugador_id
             WHERE o.participacion_id = ? AND o.estado IN ('PENDIENTE', 'PENDIENTE_IGUALACION')
             ORDER BY o.creado_en DESC"
        );
        $stmt->execute([$participacionId]);

        return $stmt->fetchAll();
    }

    /** Panel de intervención: ventanas RFA vencidas de TODA la liga, sin confirmar todavía. */
    public function listarTodasVencidasSinConfirmar(): array
    {
        return $this->db->query(
            "SELECT o.*, j.nombre AS jugador_nombre, cc.nombre AS club_ofertante_nombre
             FROM ofertas_agente_libre o
             JOIN jugadores j ON j.id = o.jugador_id
             JOIN participaciones_club pc ON pc.id = o.participacion_id
             JOIN clubes cc ON cc.id = pc.club_id
             WHERE o.estado = 'PENDIENTE_IGUALACION' AND o.fecha_limite_igualacion < NOW()
             ORDER BY o.fecha_limite_igualacion ASC"
        )->fetchAll();
    }

    /** Panel de intervención: pujas pendientes más antiguas que $horas, para detectar mercado que nadie resuelve. */
    public function listarPendientesAntiguas(int $horas): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, j.nombre AS jugador_nombre
             FROM ofertas_agente_libre o
             JOIN jugadores j ON j.id = o.jugador_id
             WHERE o.estado = 'PENDIENTE' AND o.creado_en < DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY o.creado_en ASC"
        );
        $stmt->execute([$horas]);

        return $stmt->fetchAll();
    }

    /** Todas las ventanas de igualación abiertas de la liga en una temporada, para el cierre general del mercado. */
    public function listarTodasPendientesIgualacion(int $temporadaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, j.nombre AS jugador_nombre
             FROM ofertas_agente_libre o
             JOIN jugadores j ON j.id = o.jugador_id
             JOIN participaciones_club pc ON pc.id = o.participacion_id
             WHERE o.estado = 'PENDIENTE_IGUALACION' AND pc.temporada_id = ?"
        );
        $stmt->execute([$temporadaId]);

        return $stmt->fetchAll();
    }

    /**
     * Ventanas de igualación abiertas donde este club es el de origen (RFA):
     * jugadores suyos que otro club está a punto de llevarse si no igualan a tiempo.
     */
    public function listarVentanasIgualacionAbiertasDeClub(string $clubId): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, j.nombre AS jugador_nombre, cc.nombre AS club_ofertante_nombre, cc.escudo_url AS club_ofertante_escudo
             FROM ofertas_agente_libre o
             JOIN jugadores j ON j.id = o.jugador_id
             JOIN participaciones_club pc ON pc.id = o.participacion_id
             JOIN clubes cc ON cc.id = pc.club_id
             WHERE o.estado = 'PENDIENTE_IGUALACION' AND j.origen_club_agencia_libre = ?
             ORDER BY o.fecha_limite_igualacion ASC"
        );
        $stmt->execute([$clubId]);

        return $stmt->fetchAll();
    }
}
