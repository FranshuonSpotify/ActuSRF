<?php

declare(strict_types=1);

final class MovimientoFinancieroRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(
        int $participacionId,
        string $tipo,
        float $importe,
        string $concepto,
        ?string $referenciaTipo,
        ?int $referenciaId
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO movimientos_financieros
                (participacion_id, tipo, importe, concepto, referencia_tipo, referencia_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$participacionId, $tipo, $importe, $concepto, $referenciaTipo, $referenciaId]);

        return (int) $this->db->lastInsertId();
    }

    /** Ley 38: el saldo nunca se almacena, siempre se calcula a partir del libro mayor. */
    public function saldoActual(int $participacionId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(importe), 0) FROM movimientos_financieros WHERE participacion_id = ?'
        );
        $stmt->execute([$participacionId]);

        return (float) $stmt->fetchColumn();
    }

    public function listarPorParticipacion(int $participacionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM movimientos_financieros WHERE participacion_id = ? ORDER BY creado_en DESC, id DESC'
        );
        $stmt->execute([$participacionId]);

        return $stmt->fetchAll();
    }
}
