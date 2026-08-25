<?php

declare(strict_types=1);

final class JugadorRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        string $nombre,
        string $posicion,
        ?int $afinidadId,
        ?string $fotoUrl,
        string $origen,
        ?int $participacionId
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO jugadores (nombre, posicion, afinidad_id, foto_url, origen, estado, participacion_actual_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $nombre,
            $posicion,
            $afinidadId,
            $fotoUrl,
            $origen,
            $participacionId !== null ? 'ACTIVO' : 'AGENTE_LIBRE',
            $participacionId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM jugadores WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function listarPorParticipacion(int $participacionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT j.*, a.nombre AS afinidad_nombre, t.nombre AS tier_nombre, t.orden AS tier_orden
             FROM jugadores j
             LEFT JOIN afinidades a ON a.id = j.afinidad_id
             LEFT JOIN tiers t ON t.id = j.tier_id
             WHERE j.participacion_actual_id = ?
             ORDER BY FIELD(j.posicion, "POR", "DEF", "MED", "DEL"), j.nombre'
        );
        $stmt->execute([$participacionId]);

        return $stmt->fetchAll();
    }

    /**
     * Para no reimportar dos veces al mismo agente libre oficial si se
     * repite la sincronización. Bug real corregido: antes solo miraba los
     * que seguían sin tier, así que un jugador ya importado Y con tier
     * asignado dejaba de "contar" como ya importado, y la siguiente
     * sincronización lo duplicaba con una fila nueva sin tier (pasó de
     * verdad con Abuelo Danger/Artie Mishman/Daisy Fields). Ahora mira
     * cualquier agente libre oficial ya existente, tenga tier o no.
     */
    public function listarNombresAgentesLibresOficialesImportados(): array
    {
        $stmt = $this->db->query("SELECT nombre FROM jugadores WHERE origen = 'JSON_OFICIAL'");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Agentes libres sin tier todavía: agentes libres oficiales recién importados o jugadores externos recién creados. */
    public function listarAgentesLibresSinTier(): array
    {
        return $this->db->query(
            'SELECT j.*, a.nombre AS afinidad_nombre
             FROM jugadores j
             LEFT JOIN afinidades a ON a.id = j.afinidad_id
             WHERE j.estado = "AGENTE_LIBRE" AND j.tier_id IS NULL
             ORDER BY j.nombre'
        )->fetchAll();
    }

    /** Solo agentes libres con tier ya asignado: pueden recibir ofertas (mercado.md §6/§7). */
    public function listarAgentesLibresConTier(): array
    {
        return $this->db->query(
            'SELECT j.*, t.nombre AS tier_nombre, t.salario_base, c.nombre AS club_origen_nombre,
                    EXISTS(
                        SELECT 1 FROM participaciones_club pc
                        WHERE pc.club_id = c.id AND pc.estado = "RETIRADA"
                    ) AS club_origen_retirado
             FROM jugadores j
             JOIN tiers t ON t.id = j.tier_id
             LEFT JOIN clubes c ON c.id = j.origen_club_agencia_libre
             WHERE j.estado = "AGENTE_LIBRE"
             ORDER BY t.orden DESC, j.nombre'
        )->fetchAll();
    }

    public function contarPorParticipacion(int $participacionId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM jugadores WHERE participacion_actual_id = ?');
        $stmt->execute([$participacionId]);

        return (int) $stmt->fetchColumn();
    }

    public function moverAParticipacion(int $jugadorId, ?int $participacionId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE jugadores SET participacion_actual_id = ?, estado = ? WHERE id = ?"
        );
        $stmt->execute([$participacionId, $participacionId !== null ? 'ACTIVO' : 'AGENTE_LIBRE', $jugadorId]);
    }

    public function moverTodosDeParticipacion(int $participacionOrigenId, int $participacionDestinoId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE jugadores SET participacion_actual_id = ? WHERE participacion_actual_id = ? AND estado = 'ACTIVO'"
        );
        $stmt->execute([$participacionDestinoId, $participacionOrigenId]);

        return $stmt->rowCount();
    }

    /** mercado.md §6: se fija al liberar a un jugador (finalizar contrato / retirada de club), se limpia al firmar uno nuevo. */
    public function marcarAgenciaLibre(int $jugadorId, ?string $clubOrigenId, ?string $tipoAgenciaLibre): void
    {
        $stmt = $this->db->prepare(
            'UPDATE jugadores SET origen_club_agencia_libre = ?, tipo_agencia_libre = ? WHERE id = ?'
        );
        $stmt->execute([$clubOrigenId, $tipoAgenciaLibre, $jugadorId]);
    }

    /** mercado.md §5: se suma al concluir cada contrato (duración servida), nunca al firmar uno nuevo. */
    public function incrementarTemporadasConsecutivas(int $jugadorId, int $temporadas): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET temporadas_consecutivas = temporadas_consecutivas + ? WHERE id = ?');
        $stmt->execute([$temporadas, $jugadorId]);
    }

    /** mercado.md §5: solo cuando lo ficha un club distinto al que ya lo tenía — camiseta nueva, cuenta nueva. */
    public function resetearTemporadasConsecutivas(int $jugadorId): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET temporadas_consecutivas = 0 WHERE id = ?');
        $stmt->execute([$jugadorId]);
    }

    public function marcarProcedenciaArchivado(int $jugadorId, string $nombreClubArchivado): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET procedencia_archivado = ? WHERE id = ?');
        $stmt->execute([$nombreClubArchivado, $jugadorId]);
    }

    /** mercado.md §9: máximo 4 jugadores franquicia por participación. */
    public function contarFranquiciaPorParticipacion(int $participacionId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM jugadores WHERE participacion_actual_id = ? AND es_franquicia = 1'
        );
        $stmt->execute([$participacionId]);

        return (int) $stmt->fetchColumn();
    }

    public function marcarFranquicia(int $jugadorId, bool $esFranquicia): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET es_franquicia = ? WHERE id = ?');
        $stmt->execute([$esFranquicia ? 1 : 0, $jugadorId]);
    }

    /**
     * Panel de intervención manual: jugadores cuyo estado y participación se
     * contradicen (violaría el CHECK de la BD si se intentara guardar hoy,
     * pero pudo quedar así por una corrección manual antigua o un fallo ya
     * corregido). Nunca debería devolver filas si todo va bien.
     */
    /**
     * Scouting (Fase 2, hub v3): buscador global de todos los jugadores con
     * tier asignado, estén contratados o sean agentes libres. Los que aún no
     * tienen tier no se listan aquí, igual que ya excluye mercado.php.
     */
    public function listarTodosParaScouting(): array
    {
        return $this->db->query(
            'SELECT j.*, t.nombre AS tier_nombre, t.orden AS tier_orden, t.salario_base,
                    a.nombre AS afinidad_nombre, c.nombre AS club_nombre, c.escudo_url
             FROM jugadores j
             JOIN tiers t ON t.id = j.tier_id
             LEFT JOIN afinidades a ON a.id = j.afinidad_id
             LEFT JOIN participaciones_club p ON p.id = j.participacion_actual_id
             LEFT JOIN clubes c ON c.id = p.club_id
             ORDER BY t.orden DESC, j.nombre'
        )->fetchAll();
    }

    /**
     * Panel admin de fotos: TODOS los jugadores (con o sin tier), con el
     * club actual si tienen uno. Prioriza primero a quienes no tienen foto
     * (venían así del JSON oficial, o son externos sin foto todavía) y luego
     * a cualquier externo creado por un usuario, aunque ya tenga foto —
     * conviene revisarlos igual porque la puso un presidente, no la liga.
     */
    public function listarTodosParaFotos(): array
    {
        return $this->db->query(
            "SELECT j.id, j.nombre, j.posicion, j.foto_url, j.origen, c.nombre AS club_nombre
             FROM jugadores j
             LEFT JOIN participaciones_club p ON p.id = j.participacion_actual_id
             LEFT JOIN clubes c ON c.id = p.club_id
             ORDER BY (j.foto_url IS NULL) DESC, (j.origen = 'EXTERNO') DESC, j.nombre"
        )->fetchAll();
    }

    public function actualizarFoto(int $jugadorId, ?string $fotoUrl): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET foto_url = ? WHERE id = ?');
        $stmt->execute([$fotoUrl, $jugadorId]);
    }

    public function listarConEstadoInconsistente(): array
    {
        return $this->db->query(
            "SELECT * FROM jugadores
             WHERE (estado = 'ACTIVO' AND participacion_actual_id IS NULL)
                OR (estado = 'AGENTE_LIBRE' AND participacion_actual_id IS NOT NULL)"
        )->fetchAll();
    }

    public function asignarTier(int $jugadorId, ?int $tierId): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET tier_id = ? WHERE id = ?');
        $stmt->execute([$tierId, $jugadorId]);
    }

    /** Corrección de un nombre mal escrito en el JSON oficial en origen (nunca se traduce, solo se corrige la grafía). */
    public function renombrar(int $jugadorId, string $nuevoNombre): void
    {
        $stmt = $this->db->prepare('UPDATE jugadores SET nombre = ? WHERE id = ?');
        $stmt->execute([$nuevoNombre, $jugadorId]);
    }

    /**
     * Resolución de wiki (ESPECIFICACION_CLAUDE_WIKI_INAZUMA.md §6/§20).
     * Un único punto de escritura para las 9 columnas wiki_*: evita que cada
     * llamador tenga que acordarse de poner wiki_last_checked_at a mano.
     */
    public function actualizarResolucionWiki(int $jugadorId, array $resultado): void
    {
        $stmt = $this->db->prepare(
            'UPDATE jugadores SET
                wiki_provider = ?, wiki_title = ?, wiki_url = ?, wiki_status = ?,
                wiki_confidence = ?, wiki_reason = ?, wiki_last_checked_at = NOW(),
                wiki_last_error = ?,
                wiki_resolved_at = IF(? IN ("matched","manual"), NOW(), wiki_resolved_at)
             WHERE id = ?'
        );
        $stmt->execute([
            $resultado['provider'] ?? null,
            $resultado['title'] ?? null,
            $resultado['url'] ?? null,
            $resultado['status'],
            $resultado['confidence'] ?? null,
            $resultado['reason'] ?? null,
            $resultado['error'] ?? null,
            $resultado['status'],
            $jugadorId,
        ]);
    }

    /** Resolución manual desde el panel admin (§30): fija el estado a "manual", nunca lo toca el backfill. */
    public function fijarResolucionManual(int $jugadorId, string $titulo, string $url): void
    {
        $stmt = $this->db->prepare(
            'UPDATE jugadores SET
                wiki_provider = "fandom_inazuma", wiki_title = ?, wiki_url = ?,
                wiki_status = "manual", wiki_confidence = NULL, wiki_reason = "manual_admin",
                wiki_last_checked_at = NOW(), wiki_last_error = NULL, wiki_resolved_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$titulo, $url, $jugadorId]);
    }

    /** Devuelve a "pending" para que el backfill lo vuelva a intentar (§29: "Buscar de nuevo" / rechazar). */
    public function reiniciarResolucionWiki(int $jugadorId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE jugadores SET
                wiki_status = "pending", wiki_title = NULL, wiki_url = NULL,
                wiki_confidence = NULL, wiki_reason = NULL, wiki_last_error = NULL
             WHERE id = ?'
        );
        $stmt->execute([$jugadorId]);
    }

    /**
     * Lote para el backfill: jugadores sin resolución protegida (matched/manual),
     * los más antiguos sin comprobar primero, para que sea reanudable sin
     * repetir siempre los mismos.
     */
    public function listarPendientesWiki(int $limite): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, posicion, afinidad_id, wiki_status,
                    (SELECT nombre FROM afinidades a WHERE a.id = jugadores.afinidad_id) AS afinidad_nombre
             FROM jugadores
             WHERE wiki_status NOT IN ('matched', 'manual')
             ORDER BY wiki_last_checked_at IS NOT NULL, wiki_last_checked_at ASC, id ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string,int> conteo por wiki_status, para el panel de observabilidad (§39). */
    public function contarPorEstadoWiki(): array
    {
        $filas = $this->db->query('SELECT wiki_status, COUNT(*) AS total FROM jugadores GROUP BY wiki_status')->fetchAll();
        $conteo = ['pending' => 0, 'matched' => 0, 'needs_review' => 0, 'not_found' => 0, 'error' => 0, 'manual' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['wiki_status']] = (int) $fila['total'];
        }

        return $conteo;
    }

    public function listarNeedsReviewWiki(): array
    {
        return $this->db->query(
            "SELECT * FROM jugadores WHERE wiki_status = 'needs_review' ORDER BY wiki_confidence DESC, nombre"
        )->fetchAll();
    }

    /** Todos los jugadores con su estado de wiki, para el panel de corrección manual (cualquiera, no solo needs_review). */
    public function listarTodosParaWiki(): array
    {
        return $this->db->query(
            'SELECT id, nombre, posicion, wiki_status, wiki_title FROM jugadores ORDER BY nombre'
        )->fetchAll();
    }
}
