<?php

declare(strict_types=1);

/**
 * Libro Mayor de dinero de traspasos (Cap. XXIV). Único punto que registra
 * movimientos económicos; nadie más debe tocar el saldo directamente.
 * El saldo nunca se almacena: siempre se calcula a partir de los movimientos
 * (Ley 38), igual que el gasto salarial en ContractEngine.
 */
final class FinancialEngine
{
    public function __construct(
        private MovimientoFinancieroRepository $movimientos,
        private AuditEngine $auditoria
    ) {
    }

    public function registrarDotacionInicial(int $participacionId, float $importe, int $usuarioAdminId): void
    {
        $id = $this->movimientos->crear($participacionId, 'DOTACION_INICIAL', $importe, 'Dotación inicial de dinero de traspasos', null, null);

        $this->auditoria->registrar($usuarioAdminId, 'DOTACION_INICIAL_TRASPASOS', 'movimientos_financieros', (string) $id, null, [
            'participacion_id' => $participacionId,
            'importe' => $importe,
        ]);
    }

    /** Sin transacción propia: quien paga y quien cobra se registran dentro de la transacción del traspaso (TransferEngine). */
    public function registrarPagoTraspaso(int $participacionCompradoraId, int $participacionVendedoraId, float $importe, int $ofertaTraspasoId): void
    {
        $this->movimientos->crear($participacionCompradoraId, 'PAGO_TRASPASO', -$importe, 'Pago por traspaso', 'oferta_traspaso', $ofertaTraspasoId);
        $this->movimientos->crear($participacionVendedoraId, 'COBRO_TRASPASO', $importe, 'Cobro por traspaso', 'oferta_traspaso', $ofertaTraspasoId);
    }

    /** Sin transacción propia: la llama ContractEngine dentro de la transacción de finalizarContrato/procesarExpiracionesDeTemporada. */
    public function registrarRetencionFranquicia(int $participacionId, float $importe, string $resultadoRuleta, int $jugadorId): void
    {
        $this->movimientos->crear($participacionId, 'RETENCION_FRANQUICIA', -$importe, 'Retención de franquicia (' . $resultadoRuleta . ')', 'jugador', $jugadorId);
    }

    /**
     * Ajuste manual del dinero de traspasos de un club, equipo por equipo
     * (decisión de Alejandro). Positivo suma, negativo resta. El motivo es
     * obligatorio: todo ajuste manual queda registrado en el libro mayor y
     * en la auditoría, nunca es un UPDATE silencioso (Ley 50).
     */
    public function registrarAjusteAdministrativo(int $participacionId, float $importe, string $motivo, int $usuarioAdminId): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new DomainException('Todo ajuste manual necesita un motivo.');
        }

        if ($importe === 0.0) {
            throw new DomainException('El importe del ajuste no puede ser cero.');
        }

        $id = $this->movimientos->crear($participacionId, 'AJUSTE_ADMINISTRATIVO', $importe, $motivo, null, null);

        $this->auditoria->registrar($usuarioAdminId, 'AJUSTE_ADMINISTRATIVO_TRASPASOS', 'movimientos_financieros', (string) $id, null, [
            'participacion_id' => $participacionId,
            'importe' => $importe,
            'motivo' => $motivo,
        ]);
    }

    public function saldoActual(int $participacionId): float
    {
        return $this->movimientos->saldoActual($participacionId);
    }

    public function listarMovimientos(int $participacionId): array
    {
        return $this->movimientos->listarPorParticipacion($participacionId);
    }
}
