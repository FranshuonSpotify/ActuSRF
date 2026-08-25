<?php

declare(strict_types=1);

/** Acceso a datos de las 3 tablas de Mi Estrategia: watchlist, objetivos (kanban) y diario. */
final class EstrategiaRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function listarWatchlist(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT w.*, j.nombre AS jugador_nombre, j.posicion, j.foto_url, j.tier_id, j.estado AS jugador_estado,
                    t.nombre AS tier_nombre, t.salario_base
             FROM estrategia_watchlist w
             JOIN jugadores j ON j.id = w.jugador_id
             LEFT JOIN tiers t ON t.id = j.tier_id
             WHERE w.usuario_id = ?
             ORDER BY w.creado_en DESC'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function watchlistExiste(int $usuarioId, int $jugadorId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM estrategia_watchlist WHERE usuario_id = ? AND jugador_id = ?');
        $stmt->execute([$usuarioId, $jugadorId]);

        return $stmt->fetch() !== false;
    }

    public function agregarWatchlist(int $usuarioId, int $jugadorId, string $notas): void
    {
        $stmt = $this->db->prepare('INSERT INTO estrategia_watchlist (usuario_id, jugador_id, notas) VALUES (?, ?, ?)');
        $stmt->execute([$usuarioId, $jugadorId, $notas !== '' ? $notas : null]);
    }

    public function eliminarWatchlist(int $usuarioId, int $watchlistId): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_watchlist WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $watchlistId]);
    }

    public function eliminarWatchlistPorJugador(int $usuarioId, int $jugadorId): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_watchlist WHERE usuario_id = ? AND jugador_id = ?');
        $stmt->execute([$usuarioId, $jugadorId]);
    }

    public function alternarSimulador(int $usuarioId, int $watchlistId): void
    {
        $stmt = $this->db->prepare('UPDATE estrategia_watchlist SET en_simulador = NOT en_simulador WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $watchlistId]);
    }

    /** Para las alertas de Scouting (Fase 2): qué usuarios siguen a este jugador, de cualquier club. */
    public function listarUsuariosQueSiguenA(int $jugadorId): array
    {
        $stmt = $this->db->prepare('SELECT usuario_id FROM estrategia_watchlist WHERE jugador_id = ?');
        $stmt->execute([$jugadorId]);

        return array_column($stmt->fetchAll(), 'usuario_id');
    }

    public function listarObjetivos(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM estrategia_objetivos WHERE usuario_id = ? ORDER BY creado_en DESC');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function crearObjetivo(int $usuarioId, string $titulo, string $descripcion): void
    {
        $stmt = $this->db->prepare('INSERT INTO estrategia_objetivos (usuario_id, titulo, descripcion) VALUES (?, ?, ?)');
        $stmt->execute([$usuarioId, $titulo, $descripcion !== '' ? $descripcion : null]);
    }

    public function moverObjetivo(int $usuarioId, int $objetivoId, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE estrategia_objetivos SET estado = ? WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$estado, $usuarioId, $objetivoId]);
    }

    public function eliminarObjetivo(int $usuarioId, int $objetivoId): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_objetivos WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $objetivoId]);
    }

    public function listarDiario(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM estrategia_diario WHERE usuario_id = ? ORDER BY creado_en DESC');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function agregarDiario(int $usuarioId, string $texto): void
    {
        $stmt = $this->db->prepare('INSERT INTO estrategia_diario (usuario_id, texto) VALUES (?, ?)');
        $stmt->execute([$usuarioId, $texto]);
    }

    public function eliminarDiario(int $usuarioId, int $diarioId): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_diario WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $diarioId]);
    }

    /** @return array<string,array> slot => fila de jugador */
    public function listarPlanificador(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.slot, j.id AS jugador_id, j.nombre, j.posicion, j.foto_url, t.nombre AS tier_nombre
             FROM estrategia_planificador_slots s
             JOIN jugadores j ON j.id = s.jugador_id
             LEFT JOIN tiers t ON t.id = j.tier_id
             WHERE s.usuario_id = ?'
        );
        $stmt->execute([$usuarioId]);

        return array_column($stmt->fetchAll(), null, 'slot');
    }

    public function asignarPlanificador(int $usuarioId, string $slot, int $jugadorId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO estrategia_planificador_slots (usuario_id, slot, jugador_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE jugador_id = VALUES(jugador_id)'
        );
        $stmt->execute([$usuarioId, $slot, $jugadorId]);
    }

    public function limpiarSlotPlanificador(int $usuarioId, string $slot): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_planificador_slots WHERE usuario_id = ? AND slot = ?');
        $stmt->execute([$usuarioId, $slot]);
    }

    public function limpiarPlanificador(int $usuarioId): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_planificador_slots WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
    }

    /** Reservas del planificador: sin slot fijo ni límite, a diferencia de titulares/suplentes. */
    public function listarReservasPlanificador(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.jugador_id, j.nombre, j.posicion, j.foto_url, t.nombre AS tier_nombre
             FROM estrategia_planificador_reservas r
             JOIN jugadores j ON j.id = r.jugador_id
             LEFT JOIN tiers t ON t.id = j.tier_id
             WHERE r.usuario_id = ?
             ORDER BY r.creado_en'
        );
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function agregarReservaPlanificador(int $usuarioId, int $jugadorId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO estrategia_planificador_reservas (usuario_id, jugador_id) VALUES (?, ?)'
        );
        $stmt->execute([$usuarioId, $jugadorId]);
    }

    public function quitarReservaPlanificador(int $usuarioId, int $jugadorId): void
    {
        $stmt = $this->db->prepare('DELETE FROM estrategia_planificador_reservas WHERE usuario_id = ? AND jugador_id = ?');
        $stmt->execute([$usuarioId, $jugadorId]);
    }

    /* ===================== Simulador de supertécnicas ===================== */

    public function listarJugadoresSimulados(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM jugadores_simulados WHERE usuario_id = ? ORDER BY creado_en');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function crearJugadorSimulado(int $usuarioId, string $nombre): int
    {
        $stmt = $this->db->prepare('INSERT INTO jugadores_simulados (usuario_id, nombre) VALUES (?, ?)');
        $stmt->execute([$usuarioId, $nombre]);

        return (int) $this->db->lastInsertId();
    }

    public function jugadorSimuladoExiste(int $usuarioId, int $jugadorSimuladoId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM jugadores_simulados WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $jugadorSimuladoId]);

        return $stmt->fetch() !== false;
    }

    public function eliminarJugadorSimulado(int $usuarioId, int $jugadorSimuladoId): void
    {
        $stmt = $this->db->prepare('DELETE FROM jugadores_simulados WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $jugadorSimuladoId]);
    }

    public function listarTecnicasSimuladas(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tecnicas_simuladas WHERE usuario_id = ? ORDER BY creado_en');
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public function contarTecnicasDeJugador(int $usuarioId, ?int $jugadorId, ?int $jugadorSimuladoId): int
    {
        if ($jugadorId !== null) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM tecnicas_simuladas WHERE usuario_id = ? AND jugador_id = ?');
            $stmt->execute([$usuarioId, $jugadorId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM tecnicas_simuladas WHERE usuario_id = ? AND jugador_simulado_id = ?');
            $stmt->execute([$usuarioId, $jugadorSimuladoId]);
        }

        return (int) $stmt->fetchColumn();
    }

    public function agregarTecnicaSimulada(
        int $usuarioId,
        ?int $jugadorId,
        ?int $jugadorSimuladoId,
        string $nombre,
        string $afinidad,
        int $tension,
        ?string $categoria,
        ?string $subcategoria,
        ?string $hipertecnica,
        ?string $hipertecnicaVariante
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO tecnicas_simuladas
                (usuario_id, jugador_id, jugador_simulado_id, nombre, afinidad, tension, categoria, subcategoria, hipertecnica, hipertecnica_variante)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $usuarioId, $jugadorId, $jugadorSimuladoId, $nombre, $afinidad, $tension,
            $categoria, $subcategoria, $hipertecnica, $hipertecnicaVariante,
        ]);
    }

    public function eliminarTecnicaSimulada(int $usuarioId, int $tecnicaId): void
    {
        $stmt = $this->db->prepare('DELETE FROM tecnicas_simuladas WHERE usuario_id = ? AND id = ?');
        $stmt->execute([$usuarioId, $tecnicaId]);
    }
}
