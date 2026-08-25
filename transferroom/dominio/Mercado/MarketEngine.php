<?php

declare(strict_types=1);

/**
 * Mercado de agentes libres (mercado.md §6/§7). El salario base del tier es
 * el suelo de la puja, nunca el precio fijo. Gana la oferta más alta; en
 * empate exacto, la registrada primero — lo garantiza el propio ORDER BY de
 * OfertaAgenteLibreRepository::listarPendientesPorJugador().
 *
 * RFA real (§6.2/§6.3): si el jugador salió de un contrato de 1 temporada
 * (agencia libre RESTRINGIDA) y la mejor oferta no es de su club de origen,
 * no se firma de inmediato — se abre una ventana de 48h en la que solo el
 * club de origen puede igualarla. Si no lo hace a tiempo, un administrador
 * confirma el traspaso a la oferta ganadora (no hay temporizador automático,
 * igual que el resto de resoluciones manuales de esta aplicación).
 */
final class MarketEngine
{
    private const DURACIONES_VALIDAS = [1, 2];

    public function __construct(
        private OfertaAgenteLibreRepository $ofertas,
        private JugadorRepository $jugadores,
        private TierRepository $tiers,
        private ParticipacionRepository $participaciones,
        private ContractEngine $contratos,
        private AuditEngine $auditoria,
        private TemporadaRepository $temporadas,
        private ConfigurationEngine $configuracion
    ) {
    }

    public function ofertarPorAgenteLibre(
        int $jugadorId,
        int $participacionId,
        float $salarioOfertado,
        int $duracionTemporadas,
        int $usuarioId
    ): int {
        if (!in_array($duracionTemporadas, self::DURACIONES_VALIDAS, true)) {
            throw new DomainException('Un contrato solo puede durar 1 o 2 temporadas.');
        }

        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if ($jugador['estado'] !== 'AGENTE_LIBRE') {
            throw new DomainException('Este jugador no está en el mercado de agentes libres.');
        }

        if ($jugador['tier_id'] === null) {
            throw new DomainException('Este jugador no tiene tier asignado; no puede recibir ofertas todavía.');
        }

        $tier = $this->tiers->buscarPorId((int) $jugador['tier_id']);
        $this->requerirSuperaSuelo($jugadorId, $tier, $salarioOfertado, null);

        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        SeasonEngine::requiereMercadoActivo($temporada);

        if ($this->ofertas->buscarPendienteDeParticipacion($jugadorId, $participacionId) !== null) {
            throw new DomainException('Este club ya tiene una oferta pendiente por este jugador.');
        }

        $ofertaId = $this->ofertas->crear($jugadorId, $participacionId, $salarioOfertado, $duracionTemporadas);

        $this->auditoria->registrar($usuarioId, 'OFERTAR_AGENTE_LIBRE', 'ofertas_agente_libre', (string) $ofertaId, null, [
            'jugador_id' => $jugadorId,
            'participacion_id' => $participacionId,
            'salario_ofertado' => $salarioOfertado,
            'duracion_temporadas' => $duracionTemporadas,
        ]);

        return $ofertaId;
    }

    /**
     * Edita la oferta ya activa de un club sobre un jugador (05-transfer_room_docs/
     * 01_bloqueante_primer_mercado/04, punto 8): la resolución elegida para la
     * doble puja del mismo club sobre el mismo jugador es dejar editar la
     * oferta existente, nunca bloquear sin más ni crear una segunda en paralelo.
     */
    public function editarOferta(int $jugadorId, int $participacionId, float $nuevoSalario, int $nuevaDuracion, int $usuarioId): void
    {
        if (!in_array($nuevaDuracion, self::DURACIONES_VALIDAS, true)) {
            throw new DomainException('Un contrato solo puede durar 1 o 2 temporadas.');
        }

        $oferta = $this->ofertas->buscarPendienteDeParticipacion($jugadorId, $participacionId);
        if ($oferta === null) {
            throw new DomainException('No tienes ninguna oferta activa por este jugador.');
        }

        $jugador = $this->jugadores->buscarPorId($jugadorId);
        $tier = $this->tiers->buscarPorId((int) $jugador['tier_id']);
        $this->requerirSuperaSuelo($jugadorId, $tier, $nuevoSalario, $participacionId);

        $participacion = $this->participaciones->buscarPorId($participacionId);
        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        SeasonEngine::requiereMercadoActivo($temporada);

        $this->ofertas->actualizar((int) $oferta['id'], $nuevoSalario, $nuevaDuracion);

        $this->auditoria->registrar($usuarioId, 'EDITAR_OFERTA_AGENTE_LIBRE', 'ofertas_agente_libre', (string) $oferta['id'],
            ['salario_ofertado' => $oferta['salario_ofertado'], 'duracion_temporadas' => $oferta['duracion_temporadas']],
            ['salario_ofertado' => $nuevoSalario, 'duracion_temporadas' => $nuevaDuracion]);
    }

    /**
     * Auditoría de mercado (confirmado por Franshu): la subasta de un agente
     * libre empieza en el suelo del tier y "va avanzando" — cada nueva oferta
     * (o edición) tiene que superar estrictamente la puja pendiente más alta
     * ya existente, no solo el suelo del tier. $participacionPropiaId se pasa
     * en editarOferta() para excluir la oferta propia que se está sustituyendo
     * (si no, un club nunca podría "editar" sin antes ser superado por sí mismo).
     */
    private function requerirSuperaSuelo(int $jugadorId, array $tier, float $salarioOfertado, ?int $participacionPropiaId): void
    {
        $suelo = (float) $tier['salario_base'];
        $sueloEsDelTier = true;

        foreach ($this->ofertas->listarPendientesPorJugador($jugadorId) as $pendiente) {
            if ($participacionPropiaId !== null && (int) $pendiente['participacion_id'] === $participacionPropiaId) {
                continue;
            }
            // Ya viene ordenada por salario_ofertado DESC: la primera que no es la propia es la máxima a batir.
            $suelo = (float) $pendiente['salario_ofertado'];
            $sueloEsDelTier = false;
            break;
        }

        $valida = $sueloEsDelTier ? $salarioOfertado >= $suelo : $salarioOfertado > $suelo;
        if (!$valida) {
            throw new DomainException($sueloEsDelTier
                ? sprintf(
                    'La oferta (%s) no puede ser inferior al salario base de su tier %s (%s).',
                    number_format($salarioOfertado, 0, ',', '.'),
                    $tier['nombre'],
                    number_format($suelo, 0, ',', '.')
                )
                : sprintf(
                    'La oferta (%s) tiene que superar la puja más alta actual (%s).',
                    number_format($salarioOfertado, 0, ',', '.'),
                    number_format($suelo, 0, ',', '.')
                ));
        }
    }

    /** Retirar una oferta activa (05-transfer_room_docs/01_bloqueante_primer_mercado/04, punto 3). */
    public function retirarOferta(int $jugadorId, int $participacionId, int $usuarioId): void
    {
        $oferta = $this->ofertas->buscarPendienteDeParticipacion($jugadorId, $participacionId);
        if ($oferta === null) {
            throw new DomainException('No tienes ninguna oferta activa por este jugador.');
        }

        $this->ofertas->marcarEstado((int) $oferta['id'], 'CANCELADA');

        $this->auditoria->registrar($usuarioId, 'RETIRAR_OFERTA_AGENTE_LIBRE', 'ofertas_agente_libre', (string) $oferta['id'], null, null);
    }

    /**
     * Cierra el mercado de un jugador concreto: la mejor oferta gana. Disparado
     * manualmente por un administrador (todavía no existe un cierre automático
     * de ventana de mercado ni un plazo límite por jugador).
     *
     * Devuelve el id del contrato firmado, o null si el jugador es RFA de otro
     * club y se ha abierto la ventana de igualación en su lugar.
     */
    public function resolverOfertas(int $jugadorId, int $usuarioAdminId): ?int
    {
        $pendientes = $this->ofertas->listarPendientesPorJugador($jugadorId);
        if ($pendientes === []) {
            throw new DomainException('No hay ofertas pendientes para este jugador.');
        }

        $ganadora = $pendientes[0]; // ya ordenada: mayor salario y, en empate, la más antigua
        $perdedoras = array_slice($pendientes, 1);

        $jugador = $this->jugadores->buscarPorId($jugadorId);
        $esRfaDeOtroClub = $jugador['tipo_agencia_libre'] === 'RESTRINGIDA'
            && $jugador['origen_club_agencia_libre'] !== null
            && $jugador['origen_club_agencia_libre'] !== $ganadora['club_id'];

        // Reclamo atómico ANTES de tocar nada más (mismo patrón que
        // OfertaTraspasoRepository::intentarMarcarAceptada, Cap. II 2.4 del
        // protocolo): si el mercado de este jugador se resuelve dos veces
        // casi a la vez (dos admins, o doble clic), solo la primera gana.
        if ($esRfaDeOtroClub) {
            $horasVentana = $this->configuracion->obtenerInt('ventana_igualacion_rfa_horas');
            if (!$this->ofertas->intentarMarcarPendienteIgualacion((int) $ganadora['id'], $horasVentana)) {
                throw new DomainException('El mercado de este jugador ya se ha resuelto en otra operación.');
            }
        } else {
            if (!$this->ofertas->intentarReclamarPendiente((int) $ganadora['id'], 'GANADORA')) {
                throw new DomainException('El mercado de este jugador ya se ha resuelto en otra operación.');
            }
        }

        foreach ($perdedoras as $oferta) {
            $this->ofertas->marcarEstado((int) $oferta['id'], 'SUPERADA');
        }

        if ($esRfaDeOtroClub) {
            $this->auditoria->registrar($usuarioAdminId, 'ABRIR_VENTANA_IGUALACION', 'ofertas_agente_libre', (string) $ganadora['id'], null, [
                'jugador_id' => $jugadorId,
                'club_origen' => $jugador['origen_club_agencia_libre'],
                'club_ofertante' => $ganadora['club_id'],
                'salario_ofertado' => $ganadora['salario_ofertado'],
                'horas_ventana' => $this->configuracion->obtenerInt('ventana_igualacion_rfa_horas'),
            ]);

            return null;
        }

        try {
            $contratoId = $this->contratos->crearContratoPorOferta(
                $jugadorId,
                (int) $ganadora['participacion_id'],
                (float) $ganadora['salario_ofertado'],
                (int) $ganadora['duracion_temporadas'],
                $usuarioAdminId
            );
        } catch (Throwable $e) {
            // Mismo problema que en igualarOferta(): el reclamo de arriba ya
            // está comprometido fuera de la transacción de crearContratoPorOferta.
            // Si falla (cap insuficiente, límite de 3 temporadas, etc.), hay
            // que devolver todas las ofertas a PENDIENTE en vez de dejar la
            // ganadora consumida sin ningún contrato resultante.
            $this->ofertas->marcarEstado((int) $ganadora['id'], 'PENDIENTE');
            foreach ($perdedoras as $oferta) {
                $this->ofertas->marcarEstado((int) $oferta['id'], 'PENDIENTE');
            }
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'RESOLVER_MERCADO_AGENTE_LIBRE', 'jugadores', (string) $jugadorId,
            ['ofertas_pendientes' => count($pendientes)],
            ['oferta_ganadora_id' => $ganadora['id'], 'contrato_id' => $contratoId]);

        return $contratoId;
    }

    /**
     * El club de origen de un RFA iguala la oferta ganadora dentro del plazo
     * de 48h: se queda con el jugador en los mismos términos que ofreció el
     * rival, y el rival no recibe ninguna compensación (mercado.md §6.3).
     */
    public function igualarOferta(int $jugadorId, int $participacionOrigenId, int $usuarioId): int
    {
        $pendiente = $this->ofertas->buscarPendienteIgualacionDeJugador($jugadorId);
        if ($pendiente === null) {
            throw new DomainException('No hay ninguna oferta pendiente de igualación para este jugador.');
        }

        $origen = $this->participaciones->buscarPorId($participacionOrigenId);
        if ($origen === null) {
            throw new DomainException('La participación no existe.');
        }

        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador['origen_club_agencia_libre'] !== $origen['club_id']) {
            throw new DomainException('Solo el club de origen de este jugador puede igualar esta oferta.');
        }

        if (strtotime((string) $pendiente['fecha_limite_igualacion']) < time()) {
            throw new DomainException('El plazo de 48 horas para igualar esta oferta ya ha pasado.');
        }

        // Reclamo atómico antes de crear el contrato: evita que igualar y
        // confirmarSinIgualacion (o dos igualaciones casi simultáneas)
        // resuelvan la misma ventana dos veces.
        if (!$this->ofertas->intentarReclamarVentanaIgualacion((int) $pendiente['id'], 'IGUALADA')) {
            throw new DomainException('Esta ventana de igualación ya se ha resuelto en otra operación.');
        }

        try {
            $contratoId = $this->contratos->crearContratoPorOferta(
                $jugadorId,
                $participacionOrigenId,
                (float) $pendiente['salario_ofertado'],
                (int) $pendiente['duracion_temporadas'],
                $usuarioId
            );
        } catch (Throwable $e) {
            // crearContratoPorOferta ya deshizo su propia transacción (p. ej.
            // por falta de Salary Cap), pero el reclamo de arriba ya se
            // había comprometido fuera de esa transacción. Sin este revert,
            // la ventana quedaba marcada IGUALADA sin ningún contrato nuevo:
            // el jugador se quedaba sin contrato activo y sin ventana abierta.
            $this->ofertas->marcarEstado((int) $pendiente['id'], 'PENDIENTE_IGUALACION');
            throw $e;
        }

        $this->auditoria->registrar($usuarioId, 'IGUALAR_OFERTA_RFA', 'ofertas_agente_libre', (string) $pendiente['id'], null, [
            'jugador_id' => $jugadorId,
            'contrato_id' => $contratoId,
        ]);

        return $contratoId;
    }

    /**
     * Pasado el plazo de 48h sin que el club de origen iguale, un
     * administrador confirma el traspaso al club que ganó la puja.
     */
    public function confirmarSinIgualacion(int $jugadorId, int $usuarioAdminId): int
    {
        $pendiente = $this->ofertas->buscarPendienteIgualacionDeJugador($jugadorId);
        if ($pendiente === null) {
            throw new DomainException('No hay ninguna oferta pendiente de igualación para este jugador.');
        }

        if (strtotime((string) $pendiente['fecha_limite_igualacion']) > time()) {
            throw new DomainException('El plazo de igualación todavía no ha terminado.');
        }

        // Reclamo atómico: si el club de origen iguala justo mientras un
        // admin confirma sin igualar (o dos admins confirman a la vez), solo
        // una de las dos operaciones puede ganar la ventana.
        if (!$this->ofertas->intentarReclamarVentanaIgualacion((int) $pendiente['id'], 'GANADORA')) {
            throw new DomainException('Esta ventana de igualación ya se ha resuelto en otra operación.');
        }

        try {
            $contratoId = $this->contratos->crearContratoPorOferta(
                $jugadorId,
                (int) $pendiente['participacion_id'],
                (float) $pendiente['salario_ofertado'],
                (int) $pendiente['duracion_temporadas'],
                $usuarioAdminId
            );
        } catch (Throwable $e) {
            // Mismo problema que en igualarOferta()/resolverOfertas(): el
            // reclamo de arriba ya está comprometido fuera de la transacción
            // de crearContratoPorOferta. Sin este revert, la ventana quedaba
            // consumida sin contrato si el club ganador ya no tenía cap.
            $this->ofertas->marcarEstado((int) $pendiente['id'], 'PENDIENTE_IGUALACION');
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'CONFIRMAR_SIN_IGUALAR', 'ofertas_agente_libre', (string) $pendiente['id'], null, [
            'jugador_id' => $jugadorId,
            'contrato_id' => $contratoId,
        ]);

        return $contratoId;
    }

    /**
     * Retirada de club (05-transfer_room_docs/01_bloqueante_primer_mercado/06,
     * punto 16): cancela toda oferta PENDIENTE que este club tenga hecha sobre
     * agentes libres. No toca PENDIENTE_IGUALACION — eso lo resuelve
     * resolverVentanasPorRetiradaDeClub().
     *
     * @return array<int, array{jugador_id: int, jugador_nombre: string}>
     */
    public function cancelarOfertasPendientesDeParticipacion(int $participacionId, int $usuarioAdminId): array
    {
        $canceladas = [];
        foreach ($this->ofertas->listarPendientesPorParticipacion($participacionId) as $oferta) {
            if ($oferta['estado'] !== 'PENDIENTE') {
                continue;
            }
            $this->ofertas->marcarEstado((int) $oferta['id'], 'CANCELADA');
            $canceladas[] = ['jugador_id' => (int) $oferta['jugador_id'], 'jugador_nombre' => (string) $oferta['jugador_nombre']];
        }

        if ($canceladas !== []) {
            $this->auditoria->registrar($usuarioAdminId, 'CANCELAR_OFERTAS_POR_RETIRADA', 'participaciones_club', (string) $participacionId, null, [
                'total_canceladas' => count($canceladas),
            ]);
        }

        return $canceladas;
    }

    /**
     * Retirada de club (punto 30): las ventanas RFA donde este club era el de
     * origen se resuelven automáticamente a favor del club que hizo la mejor
     * oferta — no tiene sentido esperar 48h a que un club que ya no existe
     * decida igualar.
     *
     * @return array<int, array{jugador_id: int, jugador_nombre: string, contrato_id: int, participacion_ganadora_id: int}>
     */
    public function resolverVentanasPorRetiradaDeClub(string $clubId, int $usuarioAdminId): array
    {
        $resueltas = [];
        foreach ($this->ofertas->listarVentanasIgualacionAbiertasDeClub($clubId) as $ventana) {
            $contratoId = $this->contratos->crearContratoPorOferta(
                (int) $ventana['jugador_id'],
                (int) $ventana['participacion_id'],
                (float) $ventana['salario_ofertado'],
                (int) $ventana['duracion_temporadas'],
                $usuarioAdminId
            );
            $this->ofertas->marcarEstado((int) $ventana['id'], 'GANADORA');

            $resueltas[] = [
                'jugador_id' => (int) $ventana['jugador_id'],
                'jugador_nombre' => (string) $ventana['jugador_nombre'],
                'contrato_id' => $contratoId,
                'participacion_ganadora_id' => (int) $ventana['participacion_id'],
            ];
        }

        if ($resueltas !== []) {
            $this->auditoria->registrar($usuarioAdminId, 'RESOLVER_VENTANAS_POR_RETIRADA', 'clubes', $clubId, null, [
                'total_resueltas' => count($resueltas),
            ]);
        }

        return $resueltas;
    }

    /**
     * Cierre general del mercado (decisión confirmada: "el cierre general del
     * mercado fuerza también el cierre de ventanas RFA en curso"): resuelve
     * TODAS las ventanas de igualación pendientes de la liga a favor del
     * ofertante, no solo las de un club que se retira.
     *
     * @return array<int, array{jugador_id: int, jugador_nombre: string, contrato_id: int, participacion_ganadora_id: int}>
     */
    public function resolverTodasLasVentanasPorCierreDeMercado(int $temporadaId, int $usuarioAdminId): array
    {
        $resueltas = [];
        foreach ($this->ofertas->listarTodasPendientesIgualacion($temporadaId) as $ventana) {
            $contratoId = $this->contratos->crearContratoPorOferta(
                (int) $ventana['jugador_id'],
                (int) $ventana['participacion_id'],
                (float) $ventana['salario_ofertado'],
                (int) $ventana['duracion_temporadas'],
                $usuarioAdminId
            );
            $this->ofertas->marcarEstado((int) $ventana['id'], 'GANADORA');

            $resueltas[] = [
                'jugador_id' => (int) $ventana['jugador_id'],
                'jugador_nombre' => (string) $ventana['jugador_nombre'],
                'contrato_id' => $contratoId,
                'participacion_ganadora_id' => (int) $ventana['participacion_id'],
            ];
        }

        if ($resueltas !== []) {
            $this->auditoria->registrar($usuarioAdminId, 'RESOLVER_VENTANAS_POR_CIERRE_MERCADO', 'temporadas', (string) $temporadaId, null, [
                'total_resueltas' => count($resueltas),
            ]);
        }

        return $resueltas;
    }

    /** Panel de intervención manual. */
    public function listarVentanasVencidasSinConfirmar(): array
    {
        return $this->ofertas->listarTodasVencidasSinConfirmar();
    }

    /** Todas las ventanas RFA abiertas de la liga, con fecha límite (Fase 2: calendario de Actividad de la Liga). */
    public function listarTodasVentanasIgualacionAbiertas(int $temporadaId): array
    {
        return $this->ofertas->listarTodasPendientesIgualacion($temporadaId);
    }

    /** Panel de intervención manual: pujas que llevan más de $horas sin que nadie las resuelva. */
    public function listarPujasPendientesAntiguas(int $horas): array
    {
        return $this->ofertas->listarPendientesAntiguas($horas);
    }

    public function listarOfertasPendientes(int $jugadorId): array
    {
        return $this->ofertas->listarPendientesPorJugador($jugadorId);
    }

    public function buscarPendienteIgualacion(int $jugadorId): ?array
    {
        return $this->ofertas->buscarPendienteIgualacionDeJugador($jugadorId);
    }

    public function listarMisOfertas(int $participacionId): array
    {
        return $this->ofertas->listarPendientesPorParticipacion($participacionId);
    }

    public function listarVentanasIgualacionDeClub(string $clubId): array
    {
        return $this->ofertas->listarVentanasIgualacionAbiertasDeClub($clubId);
    }
}
