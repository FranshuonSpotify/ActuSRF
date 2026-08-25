<?php

declare(strict_types=1);

/**
 * Ciclo de vida de una temporada (Cap. XXII). Toda transición de estado
 * pasa obligatoriamente por aquí; nunca UPDATE directo a temporadas.estado.
 */
final class SeasonEngine
{
    private const TRANSICIONES = [
        'CONFIGURACION' => ['PRETEMPORADA'],
        'PRETEMPORADA' => ['MERCADO_ABIERTO'],
        'MERCADO_ABIERTO' => ['COMPETICION'],
        'COMPETICION' => ['MERCADO_EXTRAORDINARIO', 'CIERRE'],
        'MERCADO_EXTRAORDINARIO' => ['CIERRE'],
        'CIERRE' => ['ARCHIVADA'],
        'ARCHIVADA' => [],
    ];

    private const FICHAS_EXACTAS_PLANTILLA = 20;
    private const FRANQUICIAS_EXACTAS_PARA_COMPETICION = 4;

    public function __construct(
        private PDO $db,
        private TemporadaRepository $temporadas,
        private ConfigurationEngine $configuracion,
        private AuditEngine $auditoria,
        private ParticipacionRepository $participaciones,
        private ContratoRepository $contratos,
        private MovimientoFinancieroRepository $movimientosFinancieros,
        private SnapshotTemporadaRepository $snapshots,
        private JugadorRepository $jugadores
    ) {
    }

    /** Pura, sin dependencias externas: testeable de forma aislada. */
    public static function puedeTransicionar(string $estadoActual, string $estadoNuevo): bool
    {
        return in_array($estadoNuevo, self::TRANSICIONES[$estadoActual] ?? [], true);
    }

    /** Estados a los que se puede pasar desde $estadoActual (para pintar acciones en la vista). */
    public static function transicionesDesde(string $estadoActual): array
    {
        return self::TRANSICIONES[$estadoActual] ?? [];
    }

    private const ESTADOS_CON_MERCADO_ACTIVO = ['MERCADO_ABIERTO', 'MERCADO_EXTRAORDINARIO'];

    /**
     * Pura: cualquier motor que cree o resuelva ofertas/traspasos llama a
     * esto primero. CONGELADO no es una fase más del ciclo de vida (por eso
     * no vive en TRANSICIONES): es una pausa reversible dentro de una fase
     * de mercado ya abierta (05-transfer_room_docs/01_bloqueante_primer_mercado/09).
     */
    public static function requiereMercadoActivo(array $temporada): void
    {
        if ((bool) $temporada['congelada']) {
            throw new DomainException('El mercado está congelado temporalmente por un administrador.');
        }

        if (!in_array($temporada['estado'], self::ESTADOS_CON_MERCADO_ACTIVO, true)) {
            throw new DomainException('El mercado no está abierto en esta temporada.');
        }
    }

    public function crear(int $numero, string $nombre, int $usuarioId): int
    {
        if ($this->temporadas->buscarPorNumero($numero) !== null) {
            throw new DomainException("Ya existe la temporada número {$numero}.");
        }

        $salaryCap = $this->configuracion->obtenerDecimal('salary_cap_defecto');
        $franquicias = $this->configuracion->obtenerInt('franquicias_defecto');

        $id = $this->temporadas->crear($numero, $nombre, $salaryCap, $franquicias);

        $this->auditoria->registrar($usuarioId, 'CREAR_TEMPORADA', 'temporadas', (string) $id, null, [
            'numero' => $numero,
            'nombre' => $nombre,
            'salary_cap' => $salaryCap,
        ]);

        return $id;
    }

    public function cambiarEstado(int $temporadaId, string $nuevoEstado, int $usuarioId): void
    {
        $temporada = $this->temporadas->buscarPorId($temporadaId);

        if ($temporada === null) {
            throw new DomainException('La temporada no existe.');
        }

        $estadoActual = $temporada['estado'];

        if (!self::puedeTransicionar($estadoActual, $nuevoEstado)) {
            throw new DomainException("No se puede pasar de {$estadoActual} a {$nuevoEstado}.");
        }

        if ($nuevoEstado === 'COMPETICION') {
            $this->validarPlantillasCompletas($temporadaId);
            $this->validarFranquiciasCompletas($temporadaId);
        }

        // Checklist maestro del protocolo de verificación, 2.7: "nunca puede
        // haber dos temporadas con el mercado abierto simultáneamente". No
        // había ninguna guarda real contra esto — se detectó verificando con
        // una temporada desechable.
        if (in_array($nuevoEstado, self::ESTADOS_CON_MERCADO_ACTIVO, true)) {
            $otrasConMercadoAbierto = $this->temporadas->listarOtrasEnEstados($temporadaId, self::ESTADOS_CON_MERCADO_ACTIVO);
            if ($otrasConMercadoAbierto !== []) {
                throw new DomainException(
                    'No se puede abrir el mercado: la temporada "' . $otrasConMercadoAbierto[0]['nombre']
                    . '" ya tiene el mercado abierto. Ciérrala antes de abrir otra.'
                );
            }
        }

        $this->temporadas->actualizarEstado($temporadaId, $nuevoEstado);

        $this->auditoria->registrar(
            $usuarioId,
            'CAMBIAR_ESTADO_TEMPORADA',
            'temporadas',
            (string) $temporadaId,
            ['estado' => $estadoActual],
            ['estado' => $nuevoEstado]
        );
    }

    public function pausarMercado(int $temporadaId, int $usuarioAdminId): void
    {
        $temporada = $this->temporadas->buscarPorId($temporadaId);
        if ($temporada === null) {
            throw new DomainException('La temporada no existe.');
        }
        if (!in_array($temporada['estado'], self::ESTADOS_CON_MERCADO_ACTIVO, true)) {
            throw new DomainException('Solo se puede congelar un mercado que está abierto.');
        }
        if ((bool) $temporada['congelada']) {
            throw new DomainException('El mercado ya está congelado.');
        }

        $this->temporadas->actualizarCongelada($temporadaId, true);
        $this->auditoria->registrar($usuarioAdminId, 'CONGELAR_MERCADO', 'temporadas', (string) $temporadaId, ['congelada' => 0], ['congelada' => 1]);
    }

    public function reanudarMercado(int $temporadaId, int $usuarioAdminId): void
    {
        $temporada = $this->temporadas->buscarPorId($temporadaId);
        if ($temporada === null) {
            throw new DomainException('La temporada no existe.');
        }
        if (!(bool) $temporada['congelada']) {
            throw new DomainException('El mercado no está congelado.');
        }

        $this->temporadas->actualizarCongelada($temporadaId, false);
        $this->auditoria->registrar($usuarioAdminId, 'REANUDAR_MERCADO', 'temporadas', (string) $temporadaId, ['congelada' => 1], ['congelada' => 0]);
    }

    public function obtenerActiva(): ?array
    {
        return $this->temporadas->buscarActiva();
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->temporadas->buscarPorId($id);
    }

    public function listarTodas(): array
    {
        return $this->temporadas->listarTodas();
    }

    /**
     * mercado.md §2/§10: regla de hierro de 20 fichas exactas. Una "ficha"
     * es un contrato activo (lo que consume Salary Cap), no un jugador
     * simplemente importado a la plantilla. Solo se exige al pasar a
     * COMPETICION; mientras el mercado está abierto se permiten plantillas
     * incompletas o con exceso temporal (decisión de Alejandro).
     */
    private function validarPlantillasCompletas(int $temporadaId): void
    {
        $irregulares = [];

        foreach ($this->participaciones->listarPorTemporada($temporadaId) as $participacion) {
            // PENDIENTE también cuenta: no tener presidente asignado no exime de tener plantilla legal.
            if (!in_array($participacion['estado'], ['PENDIENTE', 'CONFIRMADA', 'ACTIVA'], true)) {
                continue;
            }

            $total = $this->contratos->contarActivosPorParticipacion((int) $participacion['id']);
            if ($total !== self::FICHAS_EXACTAS_PLANTILLA) {
                $irregulares[] = "{$participacion['club_nombre']} ({$total}/" . self::FICHAS_EXACTAS_PLANTILLA . ')';
            }
        }

        if ($irregulares !== []) {
            throw new DomainException(
                'No se puede iniciar la competición: estos clubes no tienen exactamente 20 fichas activas — '
                . implode(', ', $irregulares) . '.'
            );
        }
    }

    /**
     * mercado.md §9: las franquicias solo se pueden designar/retirar con el
     * mercado abierto (JugadorEngine::requerirMercadoActivoParaParticipacion).
     * Como contrapartida, aquí se exige que cada club llegue a COMPETICION
     * con sus 4 franquicias ya designadas — si no, la ventana para
     * corregirlo ya se ha cerrado hasta el próximo mercado.
     */
    private function validarFranquiciasCompletas(int $temporadaId): void
    {
        $irregulares = [];

        foreach ($this->participaciones->listarPorTemporada($temporadaId) as $participacion) {
            if (!in_array($participacion['estado'], ['PENDIENTE', 'CONFIRMADA', 'ACTIVA'], true)) {
                continue;
            }

            $total = $this->jugadores->contarFranquiciaPorParticipacion((int) $participacion['id']);
            if ($total !== self::FRANQUICIAS_EXACTAS_PARA_COMPETICION) {
                $irregulares[] = "{$participacion['club_nombre']} ({$total}/" . self::FRANQUICIAS_EXACTAS_PARA_COMPETICION . ')';
            }
        }

        if ($irregulares !== []) {
            throw new DomainException(
                'No se puede iniciar la competición: estos clubes no tienen sus '
                . self::FRANQUICIAS_EXACTAS_PARA_COMPETICION . ' franquicias designadas — '
                . implode(', ', $irregulares) . '.'
            );
        }
    }

    /**
     * Fotografía inmutable de la temporada, tomada ANTES de expirar contratos
     * (Cap. XXII): si se tomara después, capturaría plantillas ya vacías.
     * El único punto de entrada es publico/admin/temporadas.php, en la
     * transición a CIERRE, antes de ContractEngine::procesarExpiracionesDeTemporada().
     */
    public function generarSnapshot(int $temporadaId, int $usuarioAdminId): void
    {
        if ($this->snapshots->existeParaTemporada($temporadaId)) {
            throw new DomainException('Esta temporada ya tiene un snapshot generado.');
        }

        $participaciones = $this->participaciones->listarPorTemporada($temporadaId);
        $clubesSnapshoteados = 0;

        $this->db->beginTransaction();
        try {
            foreach ($participaciones as $participacion) {
                if (!in_array($participacion['estado'], ['PENDIENTE', 'CONFIRMADA', 'ACTIVA'], true)) {
                    continue;
                }

                $participacionId = (int) $participacion['id'];
                $contratos = $this->contratos->listarPorParticipacion($participacionId);
                $fichas = self::mapearFichasParaSnapshot($contratos);
                $gastoSalarial = $this->contratos->sumaSalariosActivos($participacionId);
                $dineroTraspasos = $this->movimientosFinancieros->saldoActual($participacionId);

                $this->snapshots->crear(
                    $temporadaId,
                    (string) $participacion['club_id'],
                    (string) $participacion['club_nombre'],
                    $gastoSalarial,
                    $dineroTraspasos,
                    $fichas
                );

                $clubesSnapshoteados++;
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            // La UNIQUE(temporada_id, club_id) es el backstop real contra duplicados
            // (p. ej. dos cierres concurrentes que pasan ambos el pre-check de arriba).
            throw new DomainException('Esta temporada ya tiene un snapshot generado.');
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'GENERAR_SNAPSHOT_TEMPORADA', 'temporadas', (string) $temporadaId, null, [
            'clubes_snapshoteados' => $clubesSnapshoteados,
        ]);
    }

    /**
     * Transforma filas de ContratoRepository::listarPorParticipacion() en la
     * forma que se guarda en el JSON de snapshots_temporada. Pura: sin BD,
     * para poder testearla de forma aislada.
     */
    public static function mapearFichasParaSnapshot(array $contratos): array
    {
        return array_map(
            static fn (array $c): array => [
                'jugador_id' => (int) $c['jugador_id'],
                'nombre' => (string) $c['jugador_nombre'],
                'posicion' => (string) $c['posicion'],
                'tier' => (string) $c['tier_nombre'],
                'salario_anual' => (float) $c['salario_anual'],
                'es_franquicia' => (bool) ((int) $c['es_franquicia']),
            ],
            $contratos
        );
    }
}
