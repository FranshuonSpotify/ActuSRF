<?php

declare(strict_types=1);

/**
 * Traspasos de jugadores con contrato en vigor en otro club (Cap. V Título X,
 * Cap. XXV). Se paga con dinero_traspasos — una divisa separada del Salary
 * Cap (confirmado por Alejandro) — pero el jugador, al cambiar de club,
 * empieza a contar contra el Salary Cap del comprador, así que ese también
 * se valida. El vendedor decide aceptar o rechazar: no es una puja
 * competitiva como el mercado de agentes libres, es una oferta dirigida.
 */
final class TransferEngine
{
    public function __construct(
        private PDO $db,
        private OfertaTraspasoRepository $ofertas,
        private OfertaTraspasoJugadorRepository $ofertasJugadores,
        private ContratoRepository $contratos,
        private JugadorRepository $jugadores,
        private ParticipacionRepository $participaciones,
        private TemporadaRepository $temporadas,
        private FinancialEngine $financiero,
        private AuditEngine $auditoria
    ) {
    }

    /**
     * @param list<int> $jugadoresOfrecidosIds jugadores de la propia plantilla del
     *        comprador incluidos en el trato, además del dinero (o en vez de él).
     *        A petición de Franshu: un traspaso ya no tiene por qué ser solo dinero.
     */
    public function ofertarTraspaso(
        int $jugadorId,
        int $participacionCompradoraId,
        float $importeTraspaso,
        array $jugadoresOfrecidosIds,
        int $usuarioId
    ): int {
        if ($importeTraspaso < 0) {
            throw new DomainException('El importe del traspaso no puede ser negativo.');
        }

        $jugadoresOfrecidosIds = array_values(array_unique(array_map('intval', $jugadoresOfrecidosIds)));

        if ($importeTraspaso <= 0 && $jugadoresOfrecidosIds === []) {
            throw new DomainException('La oferta necesita dinero, al menos un jugador a cambio, o ambas cosas.');
        }

        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if ($jugador['estado'] !== 'ACTIVO' || $jugador['participacion_actual_id'] === null) {
            throw new DomainException('Este jugador no pertenece actualmente a ningún club: no es un traspaso, es fichaje de agente libre.');
        }

        $participacionVendedoraId = (int) $jugador['participacion_actual_id'];
        if ($participacionVendedoraId === $participacionCompradoraId) {
            throw new DomainException('No puedes ofertar por tu propio jugador.');
        }

        $compradora = $this->participaciones->buscarPorId($participacionCompradoraId);
        if ($compradora === null) {
            throw new DomainException('La participación compradora no existe.');
        }

        $temporada = $this->temporadas->buscarPorId((int) $compradora['temporada_id']);
        SeasonEngine::requiereMercadoActivo($temporada);

        if ($importeTraspaso > 0) {
            $saldoComprador = $this->financiero->saldoActual($participacionCompradoraId);
            if ($importeTraspaso > $saldoComprador) {
                throw new DomainException(sprintf(
                    'No tienes suficiente dinero de traspasos: dispones de %s y ofertas %s.',
                    number_format($saldoComprador, 0, ',', '.'),
                    number_format($importeTraspaso, 0, ',', '.')
                ));
            }
        }

        $contrato = $this->contratos->buscarActivoDeJugador($jugadorId);
        if ($contrato === null) {
            throw new DomainException('Este jugador no tiene un contrato activo.');
        }

        if ($this->ofertas->buscarPendienteDeJugadorYComprador($jugadorId, $participacionCompradoraId) !== null) {
            throw new DomainException('Ya tienes una oferta de traspaso pendiente por este jugador.');
        }

        // jugador_id (ofrecido) => contrato_id: se valida aquí, antes de escribir nada.
        $contratosOfrecidos = $this->validarJugadoresOfrecidos($jugadoresOfrecidosIds, $jugadorId, $participacionCompradoraId);

        $ofertaId = $this->ofertas->crear(
            $jugadorId,
            (int) $contrato['id'],
            $participacionVendedoraId,
            $participacionCompradoraId,
            $importeTraspaso
        );

        foreach ($contratosOfrecidos as $idOfrecido => $contratoIdOfrecido) {
            $this->ofertasJugadores->agregar($ofertaId, $idOfrecido, $contratoIdOfrecido);
        }

        $this->auditoria->registrar($usuarioId, 'OFERTAR_TRASPASO', 'ofertas_traspaso', (string) $ofertaId, null, [
            'jugador_id' => $jugadorId,
            'participacion_vendedora_id' => $participacionVendedoraId,
            'participacion_compradora_id' => $participacionCompradoraId,
            'importe_traspaso' => $importeTraspaso,
            'jugadores_ofrecidos' => $jugadoresOfrecidosIds,
        ]);

        return $ofertaId;
    }

    /**
     * Comprueba que cada jugador ofrecido es de verdad de la plantilla del
     * comprador y tiene contrato activo — antes de escribir nada (misma
     * disciplina que el resto de validaciones de este método).
     *
     * @param list<int> $jugadoresOfrecidosIds
     * @return array<int,int> jugador_id => contrato_id
     */
    private function validarJugadoresOfrecidos(array $jugadoresOfrecidosIds, int $jugadorObjetivoId, int $participacionCompradoraId): array
    {
        $contratosOfrecidos = [];
        foreach ($jugadoresOfrecidosIds as $idOfrecido) {
            if ($idOfrecido === $jugadorObjetivoId) {
                throw new DomainException('No puedes ofrecer al mismo jugador que estás pidiendo.');
            }

            $jugadorOfrecido = $this->jugadores->buscarPorId($idOfrecido);
            if ($jugadorOfrecido === null
                || $jugadorOfrecido['estado'] !== 'ACTIVO'
                || (int) ($jugadorOfrecido['participacion_actual_id'] ?? 0) !== $participacionCompradoraId
            ) {
                throw new DomainException('Solo puedes ofrecer jugadores que estén ahora mismo en tu propia plantilla.');
            }

            $contratoOfrecido = $this->contratos->buscarActivoDeJugador($idOfrecido);
            if ($contratoOfrecido === null) {
                throw new DomainException('Uno de los jugadores ofrecidos no tiene un contrato activo.');
            }

            $contratosOfrecidos[$idOfrecido] = (int) $contratoOfrecido['id'];
        }

        return $contratosOfrecidos;
    }

    /**
     * Auditoría de mercado (Fase QA): retirarOferta/contraofertar/responderOferta
     * no comprobaban el mercado abierto — solo ofertarTraspaso() lo hacía.
     * Con el mercado cerrado (COMPETICION), una oferta que quedó PENDIENTE
     * no se puede aceptar, contraofertar ni retirar hasta que el mercado
     * vuelva a abrirse; queda congelada tal cual, igual que las franquicias.
     */
    private function requerirMercadoActivoDeParticipacion(int $participacionId): void
    {
        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        SeasonEngine::requiereMercadoActivo($temporada);
    }

    /** Retirar una oferta de traspaso propia todavía pendiente (05-transfer_room_docs/01_bloqueante_primer_mercado/04, punto 3). */
    public function retirarOferta(int $ofertaId, int $participacionCompradoraId, int $usuarioId): void
    {
        $oferta = $this->ofertas->buscarPorId($ofertaId);
        if ($oferta === null) {
            throw new DomainException('La oferta de traspaso no existe.');
        }
        if ((int) $oferta['participacion_compradora_id'] !== $participacionCompradoraId) {
            throw new DomainException('Esta oferta no pertenece a tu club.');
        }
        if ($oferta['estado'] !== 'PENDIENTE') {
            throw new DomainException('Esta oferta ya no está pendiente.');
        }

        $this->requerirMercadoActivoDeParticipacion($participacionCompradoraId);

        $this->ofertas->marcarEstado($ofertaId, 'CANCELADA');
        $this->auditoria->registrar($usuarioId, 'RETIRAR_OFERTA_TRASPASO', 'ofertas_traspaso', (string) $ofertaId, null, null);
    }

    /**
     * Contraoferta del vendedor (05-transfer_room_docs/01_bloqueante_primer_mercado/05,
     * punto 13): crea un registro NUEVO enlazado al anterior, nunca edita el
     * original — así el historial completo queda visible. La original pasa a
     * CONTRAOFERTADA (terminal); la nueva queda PENDIENTE a la espera de que
     * el comprador original la acepte o la rechace.
     * Los jugadores ofrecidos en la oferta original (si los había) se
     * mantienen tal cual en la contraoferta — el vendedor solo cambia el
     * importe de dinero, no la lista de jugadores del comprador.
     */
    public function contraofertar(int $ofertaOriginalId, int $participacionVendedoraId, float $nuevoImporte, int $usuarioId): int
    {
        if ($nuevoImporte < 0) {
            throw new DomainException('El importe de la contraoferta no puede ser negativo.');
        }

        $original = $this->ofertas->buscarPorId($ofertaOriginalId);
        if ($original === null) {
            throw new DomainException('La oferta de traspaso no existe.');
        }
        if ((int) $original['participacion_vendedora_id'] !== $participacionVendedoraId) {
            throw new DomainException('Esta oferta no pertenece a tu club.');
        }
        if ($original['estado'] !== 'PENDIENTE') {
            throw new DomainException('Esta oferta ya no está pendiente.');
        }

        $this->requerirMercadoActivoDeParticipacion($participacionVendedoraId);

        $jugadoresOfrecidosOriginal = $this->ofertasJugadores->listarPorOferta($ofertaOriginalId);
        if ($nuevoImporte <= 0 && $jugadoresOfrecidosOriginal === []) {
            throw new DomainException('La contraoferta necesita un importe mayor que cero (la oferta original no incluía jugadores).');
        }

        $this->ofertas->marcarEstado($ofertaOriginalId, 'CONTRAOFERTADA');

        $nuevaOfertaId = $this->ofertas->crear(
            (int) $original['jugador_id'],
            (int) $original['contrato_id'],
            (int) $original['participacion_vendedora_id'],
            (int) $original['participacion_compradora_id'],
            $nuevoImporte,
            $ofertaOriginalId
        );

        foreach ($jugadoresOfrecidosOriginal as $jo) {
            $this->ofertasJugadores->agregar($nuevaOfertaId, (int) $jo['jugador_id'], (int) $jo['contrato_id']);
        }

        $this->auditoria->registrar($usuarioId, 'CONTRAOFERTAR_TRASPASO', 'ofertas_traspaso', (string) $nuevaOfertaId, null, [
            'oferta_padre_id' => $ofertaOriginalId,
            'importe_traspaso' => $nuevoImporte,
        ]);

        return $nuevaOfertaId;
    }

    public function responderOferta(int $ofertaId, bool $aceptar, int $usuarioId): void
    {
        $oferta = $this->ofertas->buscarPorId($ofertaId);
        if ($oferta === null) {
            throw new DomainException('La oferta de traspaso no existe.');
        }

        if ($oferta['estado'] !== 'PENDIENTE') {
            throw new DomainException('Esta oferta ya no está pendiente.');
        }

        $this->requerirMercadoActivoDeParticipacion((int) $oferta['participacion_vendedora_id']);

        if (!$aceptar) {
            $this->ofertas->marcarEstado($ofertaId, 'RECHAZADA');
            $this->auditoria->registrar($usuarioId, 'RECHAZAR_TRASPASO', 'ofertas_traspaso', (string) $ofertaId, null, null);
            return;
        }

        $this->aceptarOferta($oferta, $usuarioId);
    }

    private function aceptarOferta(array $oferta, int $usuarioId): void
    {
        $contrato = $this->contratos->buscarPorId((int) $oferta['contrato_id']);
        if ($contrato === null || $contrato['estado'] !== 'ACTIVO') {
            throw new DomainException('El contrato de este jugador ya no está activo: el traspaso no puede completarse.');
        }

        $compradora = $this->participaciones->buscarPorId((int) $oferta['participacion_compradora_id']);
        if ($compradora === null) {
            throw new DomainException('La participación compradora ya no existe.');
        }

        $vendedora = $this->participaciones->buscarPorId((int) $oferta['participacion_vendedora_id']);
        if ($vendedora === null) {
            throw new DomainException('La participación vendedora ya no existe.');
        }

        // Recomprobar cada jugador ofrecido en el intercambio: puede haber
        // cambiado de contrato (protegido, finalizado...) desde que se hizo
        // la oferta — misma cautela que ya se aplicaba al jugador objetivo.
        $jugadoresOfrecidos = $this->ofertasJugadores->listarPorOferta((int) $oferta['id']);
        $contratosOfrecidos = [];
        $salarioJugadoresOfrecidos = 0.0;
        foreach ($jugadoresOfrecidos as $jo) {
            $contratoOfrecido = $this->contratos->buscarPorId((int) $jo['contrato_id']);
            if ($contratoOfrecido === null || $contratoOfrecido['estado'] !== 'ACTIVO') {
                throw new DomainException('Uno de los jugadores ofrecidos en el intercambio ya no tiene un contrato activo: el traspaso no puede completarse.');
            }
            $contratosOfrecidos[] = $contratoOfrecido;
            $salarioJugadoresOfrecidos += (float) $contratoOfrecido['salario_anual'];
        }

        $salarioJugadorObjetivo = (float) $contrato['salario_anual'];

        // Salary Cap de AMBOS clubes tras el trato (antes solo se comprobaba
        // el del comprador, porque nunca ganaba jugadores el vendedor; ahora
        // sí puede recibir jugadores a cambio).
        $temporadaComprador = $this->temporadas->buscarPorId((int) $compradora['temporada_id']);
        $capComprador = (float) ($compradora['salary_cap_override'] ?? $temporadaComprador['salary_cap']);
        $salarioActualComprador = $this->contratos->sumaSalariosActivos((int) $compradora['id']);
        if (!ContractEngine::cabeTrasIntercambio($salarioActualComprador, $salarioJugadoresOfrecidos, $salarioJugadorObjetivo, $capComprador)) {
            throw new DomainException('El comprador no tiene margen de Salary Cap para completar este traspaso.');
        }

        if ($jugadoresOfrecidos !== []) {
            $temporadaVendedor = $this->temporadas->buscarPorId((int) $vendedora['temporada_id']);
            $capVendedor = (float) ($vendedora['salary_cap_override'] ?? $temporadaVendedor['salary_cap']);
            $salarioActualVendedor = $this->contratos->sumaSalariosActivos((int) $vendedora['id']);
            if (!ContractEngine::cabeTrasIntercambio($salarioActualVendedor, $salarioJugadorObjetivo, $salarioJugadoresOfrecidos, $capVendedor)) {
                throw new DomainException('El club vendedor no tiene margen de Salary Cap para recibir a los jugadores ofrecidos en el intercambio.');
            }
        }

        if ((float) $oferta['importe_traspaso'] > 0 && (float) $oferta['importe_traspaso'] > $this->financiero->saldoActual((int) $compradora['id'])) {
            throw new DomainException('El comprador ya no tiene suficiente dinero de traspasos disponible.');
        }

        $this->db->beginTransaction();
        try {
            // Reclamo atómico, PRIMERA escritura de la transacción, antes de
            // tocar contratos o dinero (Cap. II 2.4 del protocolo: dos clubes
            // aceptando la misma oferta casi a la vez — verificado con dos
            // aceptaciones reales simultáneas que, sin este guard, duplicaban
            // el contrato y el pago). Si no la ganamos, alguien se adelantó
            // entre que leímos la oferta arriba y llegamos aquí.
            if (!$this->ofertas->intentarMarcarAceptada((int) $oferta['id'])) {
                throw new DomainException('Esta oferta ya no está disponible: otro club se adelantó.');
            }

            // Se finaliza el contrato viejo y se crea uno nuevo idéntico en el
            // club comprador (mismo tier, salario y cobertura de temporadas),
            // en vez de mutar la fila: así el historial conserva con qué club
            // estuvo el jugador en cada momento (Cap. XX).
            $this->contratos->finalizar((int) $contrato['id']);
            $this->contratos->crear(
                (int) $oferta['jugador_id'],
                (int) $compradora['id'],
                (int) $contrato['tier_id'],
                (float) $contrato['salario_anual'],
                (int) $contrato['duracion_temporadas'],
                (int) $contrato['temporada_inicio_id']
            );
            $this->jugadores->moverAParticipacion((int) $oferta['jugador_id'], (int) $compradora['id']);
            // Auditoría de seguridad (reglamento de fichajes, previa al primer
            // mercado): es_franquicia es una designación explícita del CLUB
            // ACTUAL (máx. 4, mercado.md §9) — sin este reset, un jugador
            // franquicia traspasado llegaba al comprador todavía marcado como
            // tal, sin que ese club lo hubiera elegido ni pasado por el
            // chequeo de designarFranquicia(): podía superar en la práctica
            // el límite de 4, y si su contrato terminaba, el comprador
            // heredaba la ruleta de retención sobre un jugador que nunca
            // designó, en vez del circuito RFA/UFA normal.
            $this->jugadores->marcarFranquicia((int) $oferta['jugador_id'], false);

            // Mismo patrón, en sentido contrario, para cada jugador ofrecido
            // en el intercambio: comprador -> vendedor.
            foreach ($jugadoresOfrecidos as $i => $jo) {
                $contratoOfrecido = $contratosOfrecidos[$i];
                $this->contratos->finalizar((int) $contratoOfrecido['id']);
                $this->contratos->crear(
                    (int) $jo['jugador_id'],
                    (int) $vendedora['id'],
                    (int) $contratoOfrecido['tier_id'],
                    (float) $contratoOfrecido['salario_anual'],
                    (int) $contratoOfrecido['duracion_temporadas'],
                    (int) $contratoOfrecido['temporada_inicio_id']
                );
                $this->jugadores->moverAParticipacion((int) $jo['jugador_id'], (int) $vendedora['id']);
                $this->jugadores->marcarFranquicia((int) $jo['jugador_id'], false);
            }

            if ((float) $oferta['importe_traspaso'] > 0) {
                $this->financiero->registrarPagoTraspaso(
                    (int) $oferta['participacion_compradora_id'],
                    (int) $oferta['participacion_vendedora_id'],
                    (float) $oferta['importe_traspaso'],
                    (int) $oferta['id']
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioId, 'ACEPTAR_TRASPASO', 'ofertas_traspaso', (string) $oferta['id'], null, [
            'jugador_id' => $oferta['jugador_id'],
            'de' => $oferta['participacion_vendedora_id'],
            'a' => $oferta['participacion_compradora_id'],
            'importe' => $oferta['importe_traspaso'],
            'jugadores_ofrecidos' => array_column($jugadoresOfrecidos, 'jugador_id'),
        ]);
    }

    public function buscarOferta(int $ofertaId): ?array
    {
        return $this->ofertas->buscarPorId($ofertaId);
    }

    public function listarJugadoresOfrecidos(int $ofertaId): array
    {
        return $this->ofertasJugadores->listarPorOferta($ofertaId);
    }

    /** @param list<int> $ofertaIds @return array<int,list<array>> oferta_id => jugadores ofrecidos */
    public function listarJugadoresOfrecidosPorOfertas(array $ofertaIds): array
    {
        return $this->ofertasJugadores->listarPorOfertas($ofertaIds);
    }

    /**
     * Retirada de club (05-transfer_room_docs/01_bloqueante_primer_mercado/06,
     * punto 16): cancela toda oferta de traspaso PENDIENTE en la que este club
     * participe, como comprador o como vendedor.
     *
     * @return array<int, array{oferta_id: int, jugador_nombre: string, contraparte_participacion_id: int}>
     */
    public function cancelarOfertasPendientesDeParticipacion(int $participacionId, int $usuarioAdminId): array
    {
        $canceladas = [];

        foreach ($this->ofertas->listarPendientesPorCompradora($participacionId) as $oferta) {
            $this->ofertas->marcarEstado((int) $oferta['id'], 'CANCELADA');
            $canceladas[] = [
                'oferta_id' => (int) $oferta['id'],
                'jugador_nombre' => (string) $oferta['jugador_nombre'],
                'contraparte_participacion_id' => (int) $oferta['participacion_vendedora_id'],
            ];
        }

        foreach ($this->ofertas->listarPendientesPorVendedora($participacionId) as $oferta) {
            $this->ofertas->marcarEstado((int) $oferta['id'], 'CANCELADA');
            $canceladas[] = [
                'oferta_id' => (int) $oferta['id'],
                'jugador_nombre' => (string) $oferta['jugador_nombre'],
                'contraparte_participacion_id' => (int) $oferta['participacion_compradora_id'],
            ];
        }

        if ($canceladas !== []) {
            $this->auditoria->registrar($usuarioAdminId, 'CANCELAR_TRASPASOS_POR_RETIRADA', 'participaciones_club', (string) $participacionId, null, [
                'total_canceladas' => count($canceladas),
            ]);
        }

        return $canceladas;
    }

    public function listarOfertasRecibidas(int $participacionVendedoraId): array
    {
        return $this->ofertas->listarPendientesPorVendedora($participacionVendedoraId);
    }

    public function listarOfertasPendientesPorJugador(int $jugadorId): array
    {
        return $this->ofertas->listarPendientesPorJugador($jugadorId);
    }

    public function listarMisOfertasEnviadas(int $participacionCompradoraId): array
    {
        return $this->ofertas->listarPendientesPorCompradora($participacionCompradoraId);
    }

    public function historialTraspasosJugador(int $jugadorId): array
    {
        return $this->ofertas->listarAceptadasPorJugador($jugadorId);
    }

    public function historialTraspasosClub(string $clubId): array
    {
        return $this->ofertas->listarAceptadasPorClub($clubId);
    }
}
