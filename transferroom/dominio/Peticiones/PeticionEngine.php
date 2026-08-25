<?php

declare(strict_types=1);

/**
 * Tablón de necesidades (CONSTITUCION.md Título XVI). Una petición representa
 * una necesidad de un club, nunca una negociación: no acepta ofertas, no
 * ejecuta fichajes, no modifica estados de contratos. Aceptar una propuesta
 * solo cierra automáticamente las demás propuestas de esa petición — el
 * traspaso o fichaje real, si se acuerda, se formaliza aparte por el canal
 * normal (Mercado/Ofertas), igual que ya ocurre con los Mensajes (Título XV).
 */
final class PeticionEngine
{
    public function __construct(
        private PeticionRepository $peticiones,
        private PeticionPropuestaRepository $propuestas,
        private ParticipacionRepository $participaciones,
        private AuditEngine $auditoria
    ) {
    }

    private const POSICIONES_VALIDAS = ['POR', 'DEF', 'MED', 'DEL'];

    /**
     * @param string[]|null $posiciones Subconjunto de POR/DEF/MED/DEL, o null si no filtra por posición.
     */
    public function crear(
        int $participacionId,
        string $descripcion,
        int $usuarioId,
        ?array $posiciones = null,
        ?float $topeSalarial = null,
        ?string $afinidad = null,
        ?int $tierMinimoId = null
    ): int {
        $descripcion = trim($descripcion);
        if ($descripcion === '') {
            throw new DomainException('La petición necesita una descripción.');
        }

        if ($posiciones !== null) {
            $posiciones = array_values(array_unique($posiciones));
            foreach ($posiciones as $pos) {
                if (!in_array($pos, self::POSICIONES_VALIDAS, true)) {
                    throw new DomainException("Posición no válida: {$pos}.");
                }
            }
            if ($posiciones === []) {
                $posiciones = null;
            }
        }

        if ($topeSalarial !== null && $topeSalarial <= 0) {
            throw new DomainException('El tope salarial debe ser mayor que cero.');
        }

        $afinidad = $afinidad !== null ? trim($afinidad) : null;
        if ($afinidad === '') {
            $afinidad = null;
        }

        $id = $this->peticiones->crear(
            $participacionId,
            $descripcion,
            $posiciones !== null ? json_encode($posiciones) : null,
            $topeSalarial,
            $afinidad,
            $tierMinimoId
        );
        $this->auditoria->registrar($usuarioId, 'CREAR_PETICION', 'peticiones', (string) $id, null, [
            'participacion_id' => $participacionId,
        ]);

        return $id;
    }

    /**
     * Filtros estructurados (Fase 2, ronda de feedback): ¿este jugador de mi
     * plantilla encaja con lo que pide esta petición? Tier "mínimo" se lee
     * como "este tier o mejor" (t.orden más alto = mejor, ya es el criterio
     * que usa el resto de la app para comparar tiers).
     */
    public function jugadorEncaja(array $peticion, array $jugadorConTierYSalario): bool
    {
        if (!empty($peticion['posiciones'])) {
            $posiciones = json_decode((string) $peticion['posiciones'], true) ?? [];
            if ($posiciones !== [] && !in_array($jugadorConTierYSalario['posicion'] ?? null, $posiciones, true)) {
                return false;
            }
        }

        if ($peticion['tope_salarial'] !== null && isset($jugadorConTierYSalario['salario_anual'])) {
            if ((float) $jugadorConTierYSalario['salario_anual'] > (float) $peticion['tope_salarial']) {
                return false;
            }
        }

        if (!empty($peticion['afinidad']) && ($jugadorConTierYSalario['afinidad_nombre'] ?? null) !== $peticion['afinidad']) {
            return false;
        }

        if ($peticion['tier_minimo_id'] !== null && isset($jugadorConTierYSalario['tier_orden'])) {
            $tierMinimoOrden = $peticion['tier_minimo_orden'] ?? null;
            if ($tierMinimoOrden !== null && (int) $jugadorConTierYSalario['tier_orden'] < (int) $tierMinimoOrden) {
                return false;
            }
        }

        return true;
    }

    public function cancelar(int $peticionId, int $usuarioId): void
    {
        $peticion = $this->peticiones->buscarPorId($peticionId);
        if ($peticion === null) {
            throw new DomainException('La petición no existe.');
        }
        if ($peticion['estado'] !== 'ABIERTA') {
            throw new DomainException('Esta petición ya no está abierta.');
        }

        $this->peticiones->actualizarEstado($peticionId, 'CANCELADA');
        $this->auditoria->registrar($usuarioId, 'CANCELAR_PETICION', 'peticiones', (string) $peticionId, null, null);
    }

    public function proponer(int $peticionId, int $participacionId, ?int $jugadorId, string $mensaje, int $usuarioId): int
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            throw new DomainException('La propuesta necesita un mensaje.');
        }

        $peticion = $this->peticiones->buscarPorId($peticionId);
        if ($peticion === null) {
            throw new DomainException('La petición no existe.');
        }
        if ($peticion['estado'] !== 'ABIERTA') {
            throw new DomainException('Esta petición ya no está abierta.');
        }
        if ((int) $peticion['participacion_id'] === $participacionId) {
            throw new DomainException('No puedes proponer sobre tu propia petición.');
        }

        $id = $this->propuestas->crear($peticionId, $participacionId, $jugadorId, $mensaje);
        $this->auditoria->registrar($usuarioId, 'PROPONER_PETICION', 'peticion_propuestas', (string) $id, null, [
            'peticion_id' => $peticionId,
            'participacion_id' => $participacionId,
        ]);

        return $id;
    }

    /** Aceptar una propuesta cierra automáticamente todas las demás de la misma petición (Título XVI). */
    public function aceptarPropuesta(int $propuestaId, int $usuarioId): void
    {
        $propuesta = $this->propuestas->buscarPorId($propuestaId);
        if ($propuesta === null) {
            throw new DomainException('La propuesta no existe.');
        }
        if ($propuesta['estado'] !== 'PENDIENTE') {
            throw new DomainException('Esta propuesta ya no está pendiente.');
        }

        $peticion = $this->peticiones->buscarPorId((int) $propuesta['peticion_id']);
        if ($peticion === null || $peticion['estado'] !== 'ABIERTA') {
            throw new DomainException('Esta petición ya no está abierta.');
        }

        // Reclamo atómico de la PETICIÓN (no de la propuesta): es el recurso
        // que de verdad solo puede resolverse una vez. Dos propuestas
        // distintas de la misma petición aceptadas casi a la vez, cada una
        // pasaba su propia comprobación de estado antes de que la otra
        // escribiera nada — verificado con una carrera real, las dos quedaban
        // ACEPTADA. Reclamar la petición primero cierra ese hueco.
        if (!$this->peticiones->intentarCerrar((int) $propuesta['peticion_id'])) {
            throw new DomainException('Esta petición ya se ha resuelto en otra operación.');
        }

        $this->propuestas->actualizarEstado($propuestaId, 'ACEPTADA');
        $this->propuestas->cerrarLasDemas((int) $propuesta['peticion_id'], $propuestaId);

        $this->auditoria->registrar($usuarioId, 'ACEPTAR_PROPUESTA_PETICION', 'peticion_propuestas', (string) $propuestaId, null, [
            'peticion_id' => $propuesta['peticion_id'],
        ]);
    }

    public function listarAbiertas(): array
    {
        return $this->peticiones->listarAbiertas();
    }

    public function listarPorParticipacion(int $participacionId): array
    {
        return $this->peticiones->listarPorParticipacion($participacionId);
    }

    public function listarPropuestas(int $peticionId): array
    {
        return $this->propuestas->listarPorPeticion($peticionId);
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->peticiones->buscarPorId($id);
    }
}
