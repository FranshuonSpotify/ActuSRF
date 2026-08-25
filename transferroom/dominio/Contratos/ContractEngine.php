<?php

declare(strict_types=1);

/**
 * Firma de contratos (mercado.md v4 §2, §5, §6). Dos caminos, sin mezclarse:
 *
 * - firmarContratoInicial(): solo para un jugador que TODAVÍA no tiene tier
 *   asignado (la ceremonia de configuración inicial, Cap. V Título IV). Aquí
 *   y solo aquí se elige el tier; fija el salario en el base de ese tier.
 * - crearContratoPorOferta(): para un jugador que ya tiene tier (viene de
 *   agencia libre). El tier nunca cambia aquí; el salario es el que ganó la
 *   puja en MarketEngine, que puede superar el salario base del tier.
 *
 * La validación de Salary Cap vive aquí porque hoy es su único consumidor
 * (Ley YAGNI); si Mercado/Ofertas necesita la misma comprobación en más
 * sitios, se extrae entonces a un SalaryEngine propio.
 */
final class ContractEngine
{
    private const DURACIONES_VALIDAS = [1, 2];

    /** mercado.md §6: la duración firmada decide el tipo de agencia libre al terminar el contrato. */
    private const DURACION_A_TIPO_AGENCIA_LIBRE = [
        1 => 'RESTRINGIDA',    // RFA: el club de origen conserva derecho de tanteo (igualar una Offer Sheet)
        2 => 'NO_RESTRINGIDA', // UFA: mercado abierto; puede volver a fichar cualquier club, incluido el de origen
    ];

    /**
     * mercado.md §9 no especifica el porcentaje de descuento de la ruleta;
     * 20% es una decisión razonable propia, marcada explícitamente aquí.
     */
    private const DESCUENTO_RETENCION_FRANQUICIA = 0.20;

    private const RESULTADOS_RULETA_FRANQUICIA = ['DESCUENTO', 'MISMO_PRECIO', 'SALIDA_DIRECTA'];

    /** A petición de Franshu: cargo inmediato de dinero de traspasos al resolverse la ruleta de franquicia. */
    private const CARGO_RETENCION_FRANQUICIA = [
        'DESCUENTO' => 40_000_000.0,
        'MISMO_PRECIO' => 20_000_000.0,
    ];

    /** Pura, sin dependencias externas: testeable de forma aislada. */
    public static function cabeEnCap(float $salarioActual, float $salarioNuevo, float $cap): bool
    {
        return ($salarioActual + $salarioNuevo) <= $cap;
    }

    /**
     * Igual que cabeEnCap, pero para un intercambio: el club pierde un
     * jugador (o ninguno) y gana otro(s) a la vez, en la misma operación —
     * usado por TransferEngine cuando un traspaso incluye jugadores a
     * cambio, no solo dinero. Pura: testeable de forma aislada.
     */
    public static function cabeTrasIntercambio(float $salarioActual, float $salarioSaliente, float $salarioEntrante, float $cap): bool
    {
        return ($salarioActual - $salarioSaliente + $salarioEntrante) <= $cap;
    }

    /** Pura: qué tipo de agencia libre corresponde a un contrato de esta duración (mercado.md §6). */
    public static function tipoAgenciaLibrePorDuracion(int $duracionTemporadas): string
    {
        return self::DURACION_A_TIPO_AGENCIA_LIBRE[$duracionTemporadas]
            ?? throw new InvalidArgumentException("Duración de contrato inválida: {$duracionTemporadas}.");
    }

    /** Pura: el salario de retención con descuento de la ruleta de franquicia (mercado.md §9). */
    public static function calcularSalarioRetencionConDescuento(float $salarioBase): float
    {
        return round($salarioBase * (1 - self::DESCUENTO_RETENCION_FRANQUICIA), 2);
    }

    /** Pura: los tres desenlaces son equiprobables — mercado.md §9 no especifica pesos distintos. */
    public static function girarRuletaFranquicia(): string
    {
        return self::RESULTADOS_RULETA_FRANQUICIA[random_int(0, 2)];
    }

    public function __construct(
        private PDO $db,
        private ContratoRepository $contratos,
        private JugadorRepository $jugadores,
        private TierRepository $tiers,
        private ParticipacionRepository $participaciones,
        private TemporadaRepository $temporadas,
        private AuditEngine $auditoria,
        private ConfigurationEngine $configuracion,
        private FinancialEngine $financiero
    ) {
    }

    /** Único momento en que se elige el tier de un jugador: su primer contrato jamás. */
    public function firmarContratoInicial(
        int $jugadorId,
        int $participacionId,
        int $tierId,
        int $duracionTemporadas,
        int $usuarioAdminId
    ): int {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if ($jugador['tier_id'] !== null) {
            throw new DomainException(
                'Este jugador ya tiene un tier asignado: no puede volver a fichar por esta vía. '
                . 'Debe pasar por el mercado de agentes libres.'
            );
        }

        $tier = $this->tiers->buscarPorId($tierId);
        if ($tier === null) {
            throw new DomainException('El tier no existe.');
        }

        return $this->ejecutarFirma(
            $jugadorId,
            $participacionId,
            $tierId,
            (float) $tier['salario_base'],
            $duracionTemporadas,
            $usuarioAdminId,
            'FIRMAR_CONTRATO_INICIAL',
            false
        );
    }

    /**
     * Fichaje directo de un agente libre que TODAVÍA no tiene tier: agentes
     * libres oficiales recién importados del JSON, o jugadores externos
     * recién creados (Cap. V Título XII). Sin aprobación previa ni mercado
     * competitivo — se lo lleva quien haga clic primero — pero queda
     * marcado para que un administrador lo revise después (decisión de
     * Alejandro: el tier y el salario los elige libremente quien ficha).
     */
    public function ficharAgenteLibreSinTier(
        int $jugadorId,
        int $participacionId,
        int $tierId,
        float $salarioAnual,
        int $duracionTemporadas,
        int $usuarioAdminId
    ): int {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if ($jugador['estado'] !== 'AGENTE_LIBRE' || $jugador['tier_id'] !== null) {
            throw new DomainException('Este jugador no está disponible para un fichaje directo sin tier.');
        }

        $tier = $this->tiers->buscarPorId($tierId);
        if ($tier === null) {
            throw new DomainException('El tier no existe.');
        }

        // mercado.md §2/§6: el salario base del tier es siempre el suelo, nunca negociable a la baja.
        if ($salarioAnual < (float) $tier['salario_base']) {
            throw new DomainException(sprintf(
                'El salario (%s) no puede ser inferior al salario base del tier %s (%s).',
                number_format($salarioAnual, 0, ',', '.'),
                $tier['nombre'],
                number_format((float) $tier['salario_base'], 0, ',', '.')
            ));
        }

        // Auditoría de cobertura parte 2, punto 10: los externos SIEMPRE quedan
        // pendientes de revisión (Título XII, no negociable); los oficiales del
        // JSON lo hacen o no según el parámetro de configuración de temporada.
        $pendienteRevision = $jugador['origen'] === 'EXTERNO'
            ? true
            : $this->configuracion->obtenerBool('revision_obligatoria_agentes_oficiales');

        return $this->ejecutarFirma(
            $jugadorId,
            $participacionId,
            $tierId,
            $salarioAnual,
            $duracionTemporadas,
            $usuarioAdminId,
            'FICHAR_AGENTE_LIBRE_SIN_TIER',
            $pendienteRevision
        );
    }

    /**
     * Crea el contrato resultante de ganar una puja por un agente libre
     * (MarketEngine::resolverOfertas). El tier ya está fijado; el salario es
     * el de la oferta ganadora, no necesariamente el base del tier.
     */
    public function crearContratoPorOferta(
        int $jugadorId,
        int $participacionId,
        float $salarioOfertado,
        int $duracionTemporadas,
        int $usuarioAdminId
    ): int {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if ($jugador['tier_id'] === null) {
            throw new DomainException('Este jugador no tiene tier asignado: no puede fichar por agencia libre todavía.');
        }

        return $this->ejecutarFirma(
            $jugadorId,
            $participacionId,
            (int) $jugador['tier_id'],
            $salarioOfertado,
            $duracionTemporadas,
            $usuarioAdminId,
            'FIRMAR_CONTRATO_AGENTE_LIBRE',
            false
        );
    }

    /**
     * Firma en lote los contratos iniciales de varios jugadores del mismo
     * club, en una única transacción atómica: si uno solo del lote no cabe
     * en el Salary Cap o deja de ser válido a mitad de proceso, no se firma
     * ninguno — nunca una plantilla parcial por un fallo de uno solo
     * (05-transfer_room_docs/01_bloqueante_primer_mercado, auditoría parte 5,
     * punto 33).
     *
     * Admite tanto agentes libres SIN tier (recién importados o externos
     * recién creados: aquí es donde se les asigna tier por primera vez,
     * usando tier_id) como agentes libres que YA tienen tier de una etapa
     * anterior (se conserva el suyo, tier_id del lote se ignora para ellos).
     * Pensado para montar la plantilla de un club nuevo desde cero mezclando
     * ambos orígenes (admin/plantilla_desde_cero.php), además del caso
     * original de asignacion_masiva.php (todos sin tier).
     *
     * @param array<int, array{jugador_id: int, tier_id?: int, duracion_temporadas: int, salario_anual?: float}> $asignaciones
     * @return array<int, int> contrato_id indexado por jugador_id
     */
    public function firmarContratosInicialesEnLote(int $participacionId, array $asignaciones, int $usuarioAdminId): array
    {
        if ($asignaciones === []) {
            throw new DomainException('No hay ninguna asignación que firmar.');
        }

        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        $salarioCap = (float) ($participacion['salary_cap_override'] ?? $temporada['salary_cap']);
        $salarioAcumulado = $this->contratos->sumaSalariosActivos($participacionId);

        $contratosPorJugador = [];

        $this->db->beginTransaction();
        try {
            foreach ($asignaciones as $asignacion) {
                $jugadorId = (int) $asignacion['jugador_id'];
                $duracionTemporadas = (int) $asignacion['duracion_temporadas'];

                if (!in_array($duracionTemporadas, self::DURACIONES_VALIDAS, true)) {
                    throw new DomainException("Duración inválida para el jugador {$jugadorId}.");
                }

                $jugador = $this->jugadores->buscarPorId($jugadorId);
                if ($jugador === null) {
                    throw new DomainException("El jugador {$jugadorId} no existe.");
                }
                // Válido en dos casos: agente libre puro (sin club), o ya colocado
                // en ESTA participación pendiente de contrato (p. ej. recién
                // importado del JSON oficial, todavía sin tier ni salario).
                if ($jugador['participacion_actual_id'] !== null && (int) $jugador['participacion_actual_id'] !== $participacionId) {
                    throw new DomainException("El jugador {$jugador['nombre']} ya pertenece a otro club.");
                }
                if ($this->contratos->buscarActivoDeJugador($jugadorId) !== null) {
                    throw new DomainException("El jugador {$jugador['nombre']} ya tiene un contrato activo.");
                }

                // Si el jugador ya tiene tier de una etapa anterior, se conserva —
                // el tier_id del lote solo se usa para asignárselo por primera vez.
                $tierId = $jugador['tier_id'] !== null ? (int) $jugador['tier_id'] : (int) ($asignacion['tier_id'] ?? 0);
                $tier = $this->tiers->buscarPorId($tierId);
                if ($tier === null) {
                    throw new DomainException("El tier no existe para el jugador {$jugador['nombre']}.");
                }

                $salarioAnual = isset($asignacion['salario_anual']) ? (float) $asignacion['salario_anual'] : (float) $tier['salario_base'];
                if ($salarioAnual < (float) $tier['salario_base']) {
                    throw new DomainException("El salario de {$jugador['nombre']} no puede ser inferior al salario base de su tier.");
                }
                if (!self::cabeEnCap($salarioAcumulado, $salarioAnual, $salarioCap)) {
                    throw new DomainException("El jugador {$jugador['nombre']} supera el margen de Salary Cap disponible del lote.");
                }
                $salarioAcumulado += $salarioAnual;

                $contratosPorJugador[$jugadorId] = $this->crearContratoCore(
                    $jugadorId,
                    $participacionId,
                    $tierId,
                    $salarioAnual,
                    $duracionTemporadas,
                    (int) $participacion['temporada_id'],
                    false
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'FIRMAR_CONTRATOS_INICIALES_EN_LOTE', 'participaciones_club', (string) $participacionId, null, [
            'jugadores' => array_keys($contratosPorJugador),
            'total' => count($contratosPorJugador),
        ]);

        return $contratosPorJugador;
    }

    private function ejecutarFirma(
        int $jugadorId,
        int $participacionId,
        int $tierId,
        float $salarioAnual,
        int $duracionTemporadas,
        int $usuarioAdminId,
        string $accionAuditoria,
        bool $pendienteRevision
    ): int {
        if (!in_array($duracionTemporadas, self::DURACIONES_VALIDAS, true)) {
            throw new DomainException('Un contrato solo puede durar 1 o 2 temporadas.');
        }

        if ($this->contratos->buscarActivoDeJugador($jugadorId) !== null) {
            throw new DomainException('Este jugador ya tiene un contrato activo.');
        }

        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        $salarioCap = (float) ($participacion['salary_cap_override'] ?? $temporada['salary_cap']);
        $salarioActual = $this->contratos->sumaSalariosActivos($participacionId);

        if (!self::cabeEnCap($salarioActual, $salarioAnual, $salarioCap)) {
            $disponible = $salarioCap - $salarioActual;
            throw new DomainException(sprintf(
                'El salario (%s) supera el margen de Salary Cap disponible (%s).',
                number_format($salarioAnual, 0, ',', '.'),
                number_format(max($disponible, 0), 0, ',', '.')
            ));
        }

        $this->db->beginTransaction();
        try {
            $contratoId = $this->crearContratoCore(
                $jugadorId,
                $participacionId,
                $tierId,
                $salarioAnual,
                $duracionTemporadas,
                (int) $participacion['temporada_id'],
                $pendienteRevision
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, $accionAuditoria, 'contratos', (string) $contratoId, null, [
            'jugador_id' => $jugadorId,
            'participacion_id' => $participacionId,
            'tier_id' => $tierId,
            'salario_anual' => $salarioAnual,
            'duracion_temporadas' => $duracionTemporadas,
        ]);

        return $contratoId;
    }

    /**
     * Núcleo de la creación de un contrato, SIN transacción propia: quien
     * llama debe estar ya dentro de una transacción (PDO no soporta
     * transacciones anidadas). Usado tanto por ejecutarFirma() como por la
     * ruleta de retención de franquicia, que necesita finalizar un contrato
     * y crear el siguiente en la misma transacción atómica.
     */
    private function crearContratoCore(
        int $jugadorId,
        int $participacionId,
        int $tierId,
        float $salarioAnual,
        int $duracionTemporadas,
        int $temporadaInicioId,
        bool $pendienteRevision,
        bool $esRenovacionContinuada = false
    ): int {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        $participacion = $this->participaciones->buscarPorId($participacionId);

        $contratoId = $this->contratos->crear(
            $jugadorId,
            $participacionId,
            $tierId,
            $salarioAnual,
            $duracionTemporadas,
            $temporadaInicioId,
            $pendienteRevision
        );

        $this->jugadores->asignarTier($jugadorId, $tierId);
        $this->jugadores->moverAParticipacion($jugadorId, $participacionId);
        $this->jugadores->marcarAgenciaLibre($jugadorId, null, null); // ya no es agente libre

        // mercado.md §5: la ruleta de franquicia y la protección Franchise
        // clásica ($esRenovacionContinuada) nunca pasan por agencia libre —
        // el jugador sigue con el mismo club, así que el contador de
        // temporadas consecutivas (ya incrementado al finalizar el contrato
        // anterior) se conserva intacto. Cualquier otra firma resetea el
        // contador salvo que sea el mismo club recuperando a su propio
        // agente libre restringido (mismo caso que valida ejecutarFirma).
        if (!$esRenovacionContinuada) {
            if ($jugador['origen_club_agencia_libre'] === null || $jugador['origen_club_agencia_libre'] !== $participacion['club_id']) {
                $this->jugadores->resetearTemporadasConsecutivas($jugadorId);
            }
        }

        return $contratoId;
    }

    /**
     * Termina un contrato. Si el jugador es franquicia, gira la ruleta de
     * retención (mercado.md §9) en vez de liberarlo sin más; si no lo es,
     * pasa a agencia libre con el tipo que le corresponda. Disparado
     * manualmente por un administrador: todavía no existe un motor de fin de
     * temporada automático por fecha.
     *
     * @return array{resultado: string, contrato_nuevo_id: ?int}
     */
    public function finalizarContrato(int $contratoId, int $usuarioAdminId): array
    {
        $contrato = $this->contratos->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new DomainException('El contrato no existe.');
        }

        if ($contrato['estado'] !== 'ACTIVO') {
            throw new DomainException('Este contrato ya no está activo.');
        }

        $participacion = $this->participaciones->buscarPorId((int) $contrato['participacion_id']);
        if ($participacion === null) {
            throw new DomainException('La participación de este contrato ya no existe.');
        }

        $this->db->beginTransaction();
        try {
            $resultado = $this->resolverFinDeContrato($contrato, $participacion, $usuarioAdminId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'FINALIZAR_CONTRATO', 'contratos', (string) $contratoId,
            ['estado' => 'ACTIVO'], ['jugador_id' => $contrato['jugador_id'], 'resultado' => $resultado['resultado']]);

        return $resultado;
    }

    /**
     * Checklist de primer mercado (A.3): marca informativa de "en venta",
     * nunca cambia ninguna regla del mercado — un club sigue siendo libre
     * de aceptar cualquier oferta sobre un jugador marcado no transferible,
     * es solo lo que ve el resto de la liga antes de ofertar. La
     * verificación de que $participacionId es la dueña real del contrato
     * es defensa en profundidad: quien llama ya filtra por $puedeGestionar
     * en la página, esto solo evita que alguien manipule el id de contrato
     * en el formulario para tocar la ficha de otro club.
     */
    public function alternarTransferible(int $contratoId, int $participacionId): void
    {
        $contrato = $this->contratos->buscarPorId($contratoId);
        if ($contrato === null || (int) $contrato['participacion_id'] !== $participacionId) {
            throw new DomainException('Este contrato no pertenece a tu club.');
        }

        $this->contratos->alternarTransferible($contratoId);
    }

    /**
     * Núcleo compartido por finalizarContrato() y procesarExpiracionesDeTemporada():
     * decide qué le pasa a UN contrato que termina, sin abrir transacción
     * propia (el llamador ya tiene una abierta). Un jugador franquicia gira
     * la ruleta de retención en lugar de pasar directamente por RFA/UFA.
     *
     * @return array{resultado: string, contrato_nuevo_id: ?int}
     */
    private function resolverFinDeContrato(array $contrato, array $participacion, int $usuarioAdminId): array
    {
        $jugadorId = (int) $contrato['jugador_id'];
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        $tipoAgenciaLibre = self::tipoAgenciaLibrePorDuracion((int) $contrato['duracion_temporadas']);

        // mercado.md §5: cada contrato que concluye suma su duración al
        // contador de temporadas consecutivas del jugador, gane o no la
        // ruleta después — es "temporadas servidas", no "temporadas con
        // contrato nuevo". crearContratoCore decide luego si se conserva o
        // se resetea según quién firme el siguiente contrato.
        $this->jugadores->incrementarTemporadasConsecutivas($jugadorId, (int) $contrato['duracion_temporadas']);

        if (!(bool) $jugador['es_franquicia']) {
            $this->contratos->finalizar((int) $contrato['id']);
            $this->jugadores->moverAParticipacion($jugadorId, null);
            $this->jugadores->marcarAgenciaLibre($jugadorId, $participacion['club_id'], $tipoAgenciaLibre);

            return ['resultado' => 'LIBERADO', 'contrato_nuevo_id' => null];
        }

        // mercado.md §9: jugador franquicia -> ruleta de retención, no el circuito RFA/UFA estándar.
        $resultadoRuleta = self::girarRuletaFranquicia();
        $this->contratos->finalizar((int) $contrato['id']);

        $this->auditoria->registrar($usuarioAdminId, 'GIRAR_RULETA_FRANQUICIA', 'jugadores', (string) $jugadorId, null, [
            'contrato_id' => $contrato['id'],
            'resultado' => $resultadoRuleta,
        ]);

        if ($resultadoRuleta === 'SALIDA_DIRECTA') {
            $this->jugadores->moverAParticipacion($jugadorId, null);
            // Sin derecho a RFA aunque el contrato fuera de 1 temporada (mercado.md §9: "sin
            // derecho a Oferta Cualificadora ni ventana de igualación").
            $this->jugadores->marcarAgenciaLibre($jugadorId, $participacion['club_id'], 'NO_RESTRINGIDA');
            $this->jugadores->marcarFranquicia($jugadorId, false);

            return ['resultado' => $resultadoRuleta, 'contrato_nuevo_id' => null];
        }

        $tier = $this->tiers->buscarPorId((int) $contrato['tier_id']);
        $salarioRetencion = $resultadoRuleta === 'DESCUENTO'
            ? self::calcularSalarioRetencionConDescuento((float) $tier['salario_base'])
            : (float) $contrato['salario_anual']; // mismo precio: el que ya tenía

        $salarioCap = (float) ($participacion['salary_cap_override'] ?? $this->temporadas->buscarPorId((int) $participacion['temporada_id'])['salary_cap']);
        $salarioActual = $this->contratos->sumaSalariosActivos((int) $participacion['id']);

        if (!self::cabeEnCap($salarioActual, $salarioRetencion, $salarioCap)) {
            // Ni siquiera el precio de retención cabe ya en el cap: no hay milagro posible, sale igual.
            $this->jugadores->moverAParticipacion($jugadorId, null);
            $this->jugadores->marcarAgenciaLibre($jugadorId, $participacion['club_id'], 'NO_RESTRINGIDA');
            $this->jugadores->marcarFranquicia($jugadorId, false);

            return ['resultado' => 'SALIDA_DIRECTA', 'contrato_nuevo_id' => null];
        }

        $contratoNuevoId = $this->crearContratoCore(
            $jugadorId,
            (int) $contrato['participacion_id'],
            (int) $contrato['tier_id'],
            $salarioRetencion,
            1,
            (int) $participacion['temporada_id'],
            false,
            true // esRenovacionContinuada: nunca pasa por agencia libre, conserva el contador de 3 temporadas
        );
        $this->jugadores->marcarFranquicia($jugadorId, true); // crearContratoCore la limpia; la ruleta la conserva

        $this->financiero->registrarRetencionFranquicia(
            (int) $participacion['id'],
            self::CARGO_RETENCION_FRANQUICIA[$resultadoRuleta],
            $resultadoRuleta,
            $jugadorId
        );

        return ['resultado' => $resultadoRuleta, 'contrato_nuevo_id' => $contratoNuevoId];
    }

    /**
     * Deshace una asignación inicial de admin ANTES de que abra el mercado
     * (05-transfer_room_docs/01_bloqueante_primer_mercado/07, punto 27): el
     * jugador vuelve a quedar sin tier ni contrato, listo para una nueva
     * asignación, sin que esto cuente como traspaso formal (no mueve dinero,
     * no toca otro club, no genera historial de traspaso). Solo se permite
     * mientras la temporada sigue en PRETEMPORADA — una vez el mercado abre,
     * cualquier cambio pasa por el circuito normal (finalizar contrato,
     * agencia libre, traspaso).
     */
    public function deshacerAsignacionInicial(int $contratoId, int $usuarioAdminId): void
    {
        $contrato = $this->contratos->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new DomainException('El contrato no existe.');
        }
        if ($contrato['estado'] !== 'ACTIVO') {
            throw new DomainException('Este contrato ya no está activo.');
        }

        $participacion = $this->participaciones->buscarPorId((int) $contrato['participacion_id']);
        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        if ($temporada['estado'] !== 'PRETEMPORADA') {
            throw new DomainException('Solo se puede deshacer una asignación inicial mientras la temporada está en pretemporada, antes de abrir el mercado.');
        }

        $this->contratos->finalizar($contratoId);
        $this->jugadores->asignarTier((int) $contrato['jugador_id'], null);

        $this->auditoria->registrar($usuarioAdminId, 'DESHACER_ASIGNACION_INICIAL', 'contratos', (string) $contratoId, null, null);
    }

    /** mercado.md §8: impuesto fijo de la protección Franchise clásica, sobre el salario base del tier. */
    private const RECARGO_PROTECCION_FRANCHISE_CLASICA = 10_000_000.0;

    /**
     * Protección Franchise clásica (mercado.md §8): excepción legal fuera del
     * circuito de los 4 jugadores franquicia designados. El club paga un
     * impuesto fijo de +10M sobre el salario base del tier actual del
     * jugador para renovarlo 1 sola temporada más, sin pasar por RFA/UFA.
     * Solo para jugadores NO designados franquicia — esos ya tienen su
     * propio mecanismo (la ruleta de retención).
     */
    public function protegerConFranchiseClasica(int $contratoId, int $usuarioAdminId): int
    {
        $contrato = $this->contratos->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new DomainException('El contrato no existe.');
        }
        if ($contrato['estado'] !== 'ACTIVO') {
            throw new DomainException('Este contrato ya no está activo.');
        }

        $jugador = $this->jugadores->buscarPorId((int) $contrato['jugador_id']);
        if ((bool) $jugador['es_franquicia']) {
            throw new DomainException('Un jugador franquicia ya designado se retiene con la ruleta de retención, no con la protección clásica.');
        }

        $tier = $this->tiers->buscarPorId((int) $contrato['tier_id']);
        $salarioNuevo = (float) $tier['salario_base'] + self::RECARGO_PROTECCION_FRANCHISE_CLASICA;

        $participacion = $this->participaciones->buscarPorId((int) $contrato['participacion_id']);
        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        $salarioCap = (float) ($participacion['salary_cap_override'] ?? $temporada['salary_cap']);
        // El propio contrato a proteger sale de la suma actual: se sustituye, no se suma encima.
        $salarioActualSinEste = $this->contratos->sumaSalariosActivos((int) $participacion['id']) - (float) $contrato['salario_anual'];

        if (!self::cabeEnCap($salarioActualSinEste, $salarioNuevo, $salarioCap)) {
            $disponible = $salarioCap - $salarioActualSinEste;
            throw new DomainException(sprintf(
                'El coste de la protección (%s) supera el margen de Salary Cap disponible (%s).',
                number_format($salarioNuevo, 0, ',', '.'),
                number_format(max($disponible, 0), 0, ',', '.')
            ));
        }

        $this->db->beginTransaction();
        try {
            $this->contratos->finalizar($contratoId);
            // mercado.md §5: la temporada que se acaba de completar cuenta
            // igual que si no hubiera protección — la protección es la
            // excepción que evita la desvinculación forzosa al llegar a 3,
            // no una forma de "congelar" el contador.
            $this->jugadores->incrementarTemporadasConsecutivas((int) $contrato['jugador_id'], (int) $contrato['duracion_temporadas']);
            $contratoNuevoId = $this->crearContratoCore(
                (int) $contrato['jugador_id'],
                (int) $contrato['participacion_id'],
                (int) $contrato['tier_id'],
                $salarioNuevo,
                1,
                (int) $participacion['temporada_id'],
                false,
                true // esRenovacionContinuada: nunca pasa por agencia libre, conserva el contador
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'PROTEGER_FRANCHISE_CLASICA', 'contratos', (string) $contratoNuevoId,
            ['contrato_anterior_id' => $contratoId, 'salario_anterior' => $contrato['salario_anual']],
            ['salario_nuevo' => $salarioNuevo]);

        return $contratoNuevoId;
    }

    /**
     * Cambio administrativo de tier/versión (algunos jugadores "cambian de
     * versión" — mismo jugador, tier distinto — y no tiene sentido modelar
     * cada versión posible de cada jugador: es más simple un selector que
     * reasigna el tier directamente). Solo lo dispara un administrador.
     * Actualiza el tier del jugador y, si tiene un contrato activo, también
     * su salario para que quede acorde al tier nuevo — sin crear un
     * contrato nuevo, es una corrección en sitio del mismo contrato (a
     * diferencia de protegerConFranchiseClasica, que sí abre uno nuevo).
     */
    public function cambiarTierJugador(int $jugadorId, int $nuevoTierId, int $usuarioAdminId): int
    {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        $nuevoTier = $this->tiers->buscarPorId($nuevoTierId);
        if ($nuevoTier === null) {
            throw new DomainException('El tier no existe.');
        }

        if ((int) ($jugador['tier_id'] ?? 0) === $nuevoTierId) {
            throw new DomainException('Este jugador ya tiene ese tier.');
        }

        $nuevoSalario = (float) $nuevoTier['salario_base'];
        $contrato = $this->contratos->buscarActivoDeJugador($jugadorId);

        if ($contrato !== null) {
            $participacion = $this->participaciones->buscarPorId((int) $contrato['participacion_id']);
            $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
            $salarioCap = (float) ($participacion['salary_cap_override'] ?? $temporada['salary_cap']);
            // El propio contrato a recalcular sale de la suma actual: se sustituye, no se suma encima.
            $salarioActualSinEste = $this->contratos->sumaSalariosActivos((int) $participacion['id']) - (float) $contrato['salario_anual'];

            if (!self::cabeEnCap($salarioActualSinEste, $nuevoSalario, $salarioCap)) {
                $disponible = $salarioCap - $salarioActualSinEste;
                throw new DomainException(sprintf(
                    'El salario del tier nuevo (%s) supera el margen de Salary Cap disponible del club (%s).',
                    number_format($nuevoSalario, 0, ',', '.'),
                    number_format(max($disponible, 0), 0, ',', '.')
                ));
            }
        }

        $this->db->beginTransaction();
        try {
            $this->jugadores->asignarTier($jugadorId, $nuevoTierId);
            if ($contrato !== null) {
                $this->contratos->actualizarTierYSalario((int) $contrato['id'], $nuevoTierId, $nuevoSalario);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'CAMBIAR_TIER_JUGADOR', 'jugadores', (string) $jugadorId,
            ['tier_id' => $jugador['tier_id']],
            [
                'tier_id' => $nuevoTierId,
                'contrato_actualizado' => $contrato !== null,
                'salario_nuevo' => $contrato !== null ? $nuevoSalario : null,
            ]);

        return $jugadorId;
    }

    /**
     * Libera de golpe a toda la plantilla contratada de una participación
     * (un club que se va de la liga): cada contrato activo se finaliza y su
     * jugador pasa a agente libre, conservando el tier que ya tenía. Nunca
     * gira la ruleta de franquicia aquí: el club desaparece de la liga, no
     * hay "club" al que retener a nadie. Todo o nada en una única transacción
     * (Ley 33). No usa finalizarContrato() en bucle porque PDO no soporta
     * transacciones anidadas.
     */
    public function liberarPlantillaCompleta(int $participacionId, int $usuarioAdminId): int
    {
        $activos = $this->contratos->listarPorParticipacion($participacionId);
        if ($activos === []) {
            return 0;
        }

        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $this->db->beginTransaction();
        try {
            foreach ($activos as $contrato) {
                // 05-transfer_room_docs/01_bloqueante_primer_mercado/06, punto 17: un club que
                // se retira no tiene "club de origen" al que aplicar tanteo — siempre UFA,
                // sin importar la duración del contrato que tuviera.
                $this->contratos->finalizar((int) $contrato['id']);
                $this->jugadores->moverAParticipacion((int) $contrato['jugador_id'], null);
                $this->jugadores->marcarAgenciaLibre((int) $contrato['jugador_id'], $participacion['club_id'], 'NO_RESTRINGIDA');
                $this->jugadores->marcarFranquicia((int) $contrato['jugador_id'], false);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'LIBERAR_PLANTILLA_COMPLETA', 'participaciones_club', (string) $participacionId, null, [
            'jugadores_liberados' => count($activos),
        ]);

        return count($activos);
    }

    /**
     * Motor de Fin de Temporada (Cap. XXII): todo contrato activo cuya
     * cobertura termina exactamente en esta temporada expira a agencia libre
     * — RFA si era de 1 temporada, UFA si era de 2 — conservando el tier.
     * Los contratos de 2 temporadas que empezaron esta misma temporada NO
     * expiran todavía; seguirán activos en la siguiente. Todo o nada en una
     * única transacción.
     *
     * Devuelve un resultado por contrato (jugador, participación, presidente,
     * resultado) en vez de solo un conteo, para que la página que orquesta
     * pueda notificar al presidente afectado cuando el resultado sea de
     * ruleta de franquicia — la notificación se dispara desde ahí, nunca
     * desde dentro de este motor (Ley 19).
     *
     * @return array<int, array{jugador_id: int, jugador_nombre: string, participacion_id: int, usuario_presidente_id: ?int, resultado: string, contrato_nuevo_id: ?int}>
     */
    public function procesarExpiracionesDeTemporada(int $temporadaId, int $usuarioAdminId): array
    {
        $temporada = $this->temporadas->buscarPorId($temporadaId);
        if ($temporada === null) {
            throw new DomainException('La temporada no existe.');
        }

        $expiran = $this->contratos->listarActivosQueExpiranEnTemporada((int) $temporada['numero']);
        if ($expiran === []) {
            return [];
        }

        $resultados = [];

        $this->db->beginTransaction();
        try {
            foreach ($expiran as $contrato) {
                $participacion = $this->participaciones->buscarPorId((int) $contrato['participacion_id']);
                $jugador = $this->jugadores->buscarPorId((int) $contrato['jugador_id']);
                $resultado = $this->resolverFinDeContrato($contrato, $participacion, $usuarioAdminId);

                $resultados[] = $resultado + [
                    'jugador_id' => (int) $contrato['jugador_id'],
                    'jugador_nombre' => (string) $jugador['nombre'],
                    'participacion_id' => (int) $contrato['participacion_id'],
                    'usuario_presidente_id' => $participacion['usuario_presidente_id'] !== null
                        ? (int) $participacion['usuario_presidente_id']
                        : null,
                ];
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'PROCESAR_EXPIRACIONES_TEMPORADA', 'temporadas', (string) $temporadaId, null, [
            'contratos_procesados' => count($expiran),
            'resultados' => array_count_values(array_column($resultados, 'resultado')),
        ]);

        return $resultados;
    }

    public function gastoSalarial(int $participacionId): float
    {
        return $this->contratos->sumaSalariosActivos($participacionId);
    }

    public function listarPlantillaContratada(int $participacionId): array
    {
        return $this->contratos->listarPorParticipacion($participacionId);
    }

    public function listarTiers(): array
    {
        return $this->tiers->listarTodas();
    }

    /**
     * Editor de tabla maestra (05-transfer_room_docs/01_bloqueante_primer_mercado/10):
     * solo cambia el salario base para FUTURAS firmas — los contratos ya
     * firmados guardan su propio salario_anual, no una referencia al tier,
     * así que esto nunca reescribe retroactivamente ningún contrato existente.
     */
    public function actualizarSalarioBaseTier(int $tierId, float $nuevoSalarioBase, int $usuarioAdminId): void
    {
        $tier = $this->tiers->buscarPorId($tierId);
        if ($tier === null) {
            throw new DomainException('El tier no existe.');
        }
        if ($nuevoSalarioBase <= 0) {
            throw new DomainException('El salario base debe ser mayor que cero.');
        }

        $this->tiers->actualizarSalarioBase($tierId, $nuevoSalarioBase);
        $this->auditoria->registrar($usuarioAdminId, 'ACTUALIZAR_SALARIO_BASE_TIER', 'tiers', (string) $tierId,
            ['salario_base' => $tier['salario_base']], ['salario_base' => $nuevoSalarioBase]);
    }

    public function historialJugador(int $jugadorId): array
    {
        return $this->contratos->listarTodosPorJugador($jugadorId);
    }

    public function historialClub(string $clubId): array
    {
        return $this->contratos->listarTodosPorClub($clubId);
    }

    public function listarPendientesDeRevision(): array
    {
        return $this->contratos->listarPendientesDeRevision();
    }

    public function listarActivosGlobal(): array
    {
        return $this->contratos->listarActivosGlobal();
    }

    public function marcarRevisado(int $contratoId, int $usuarioAdminId): void
    {
        $this->contratos->marcarRevisado($contratoId);
        $this->auditoria->registrar($usuarioAdminId, 'MARCAR_CONTRATO_REVISADO', 'contratos', (string) $contratoId, null, null);
    }

    /**
     * Rechaza un fichaje directo sin tier (05-transfer_room_docs/01_bloqueante_primer_mercado/09,
     * "aprobar/rechazar agente libre externo"): a diferencia de finalizarContrato,
     * esto deshace el fichaje entero — el jugador vuelve a agente libre SIN tier,
     * como si nunca se hubiera fichado, para poder corregir un tier/salario
     * abusivo elegido libremente por quien lo fichó. Funciona en cualquier
     * momento de la temporada, no solo en pretemporada (a diferencia de
     * deshacerAsignacionInicial, que corrige una asignación legítima de admin).
     */
    public function rechazarContratoPendienteRevision(int $contratoId, int $usuarioAdminId): void
    {
        $contrato = $this->contratos->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new DomainException('El contrato no existe.');
        }
        if ($contrato['estado'] !== 'ACTIVO') {
            throw new DomainException('Este contrato ya no está activo.');
        }
        if (!(bool) $contrato['pendiente_revision']) {
            throw new DomainException('Este contrato ya fue revisado: usa "Finalizar contrato" si quieres terminarlo.');
        }

        $this->contratos->finalizar($contratoId);
        $this->jugadores->moverAParticipacion((int) $contrato['jugador_id'], null);
        $this->jugadores->asignarTier((int) $contrato['jugador_id'], null);
        $this->jugadores->marcarAgenciaLibre((int) $contrato['jugador_id'], null, null);

        $this->auditoria->registrar($usuarioAdminId, 'RECHAZAR_CONTRATO_PENDIENTE_REVISION', 'contratos', (string) $contratoId, null, null);
    }
}
