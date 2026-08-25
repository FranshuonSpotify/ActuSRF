<?php

declare(strict_types=1);

/**
 * Mi Estrategia (Fase 2, hub v3): watchlist, kanban de objetivos, diario de
 * decisiones, planificador táctico y simulador de supertécnicas. Sin
 * especificación previa — diseño propio. El comparador no tiene estado
 * propio (lee jugadores/tiers al vuelo); el simulador de supertécnicas sí
 * persiste (jugadores_simulados / tecnicas_simuladas), privado por usuario y
 * sin relación con los jugadores reales del mercado.
 */
final class EstrategiaEngine
{
    private const ESTADOS_KANBAN = ['PENDIENTE', 'EN_PROGRESO', 'COMPLETADO'];

    /**
     * Slots del planificador táctico: 11 titulares (portero + 10 jugadores
     * de campo, con claves genéricas o1..o10 en vez de nombres de posición
     * fijos) más 5 suplentes. La forma sobre el campo (qué o-slot es DEF,
     * MED o DEL) la decide el selector de formación, 100% en cliente — el
     * servidor solo necesita saber qué claves de slot son válidas, no su rol
     * táctico, que cambia según la formación elegida (CLAUDE.md §12).
     */
    public const SLOTS_TITULARES = ['por', 'o1', 'o2', 'o3', 'o4', 'o5', 'o6', 'o7', 'o8', 'o9', 'o10'];
    public const SLOTS_SUPLENTES = ['s1', 's2', 's3', 's4', 's5'];
    public const SLOTS_PLANIFICADOR = [...self::SLOTS_TITULARES, ...self::SLOTS_SUPLENTES];

    public function __construct(private EstrategiaRepository $repo)
    {
    }

    public function listarWatchlist(int $usuarioId): array
    {
        return $this->repo->listarWatchlist($usuarioId);
    }

    public function agregarAWatchlist(int $usuarioId, int $jugadorId, string $notas): void
    {
        if ($this->repo->watchlistExiste($usuarioId, $jugadorId)) {
            throw new DomainException('Ese jugador ya está en tu watchlist.');
        }

        $this->repo->agregarWatchlist($usuarioId, $jugadorId, trim($notas));
    }

    public function quitarDeWatchlist(int $usuarioId, int $watchlistId): void
    {
        $this->repo->eliminarWatchlist($usuarioId, $watchlistId);
    }

    public function alternarEnSimulador(int $usuarioId, int $watchlistId): void
    {
        $this->repo->alternarSimulador($usuarioId, $watchlistId);
    }

    public function listarUsuariosQueSiguenA(int $jugadorId): array
    {
        return $this->repo->listarUsuariosQueSiguenA($jugadorId);
    }

    /** Seguir/dejar de seguir desde el buscador de Scouting (Fase 2): igual que un favorito, sin notas. */
    public function alternarSeguimiento(int $usuarioId, int $jugadorId): bool
    {
        if ($this->repo->watchlistExiste($usuarioId, $jugadorId)) {
            $this->repo->eliminarWatchlistPorJugador($usuarioId, $jugadorId);

            return false;
        }

        $this->repo->agregarWatchlist($usuarioId, $jugadorId, '');

        return true;
    }

    /** Suma salarial de los jugadores marcados "en simulador" dentro de la watchlist. */
    public function simulador(int $usuarioId): array
    {
        $seleccionados = array_values(array_filter(
            $this->repo->listarWatchlist($usuarioId),
            fn (array $w): bool => (bool) $w['en_simulador']
        ));

        $total = array_sum(array_map(fn (array $w): float => (float) ($w['salario_base'] ?? 0), $seleccionados));

        return ['jugadores' => $seleccionados, 'total_salarial' => $total];
    }

    public function listarObjetivos(int $usuarioId): array
    {
        return $this->repo->listarObjetivos($usuarioId);
    }

    public function crearObjetivo(int $usuarioId, string $titulo, string $descripcion): void
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new DomainException('El objetivo necesita un título.');
        }

        $this->repo->crearObjetivo($usuarioId, $titulo, trim($descripcion));
    }

    public function moverObjetivo(int $usuarioId, int $objetivoId, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS_KANBAN, true)) {
            throw new DomainException('Estado de objetivo no válido.');
        }

        $this->repo->moverObjetivo($usuarioId, $objetivoId, $estado);
    }

    public function eliminarObjetivo(int $usuarioId, int $objetivoId): void
    {
        $this->repo->eliminarObjetivo($usuarioId, $objetivoId);
    }

    public function listarDiario(int $usuarioId): array
    {
        return $this->repo->listarDiario($usuarioId);
    }

    public function agregarDiario(int $usuarioId, string $texto): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            throw new DomainException('La entrada del diario no puede estar vacía.');
        }

        $this->repo->agregarDiario($usuarioId, $texto);
    }

    public function eliminarDiario(int $usuarioId, int $diarioId): void
    {
        $this->repo->eliminarDiario($usuarioId, $diarioId);
    }

    public function listarPlanificador(int $usuarioId): array
    {
        return $this->repo->listarPlanificador($usuarioId);
    }

    public function asignarPlanificador(int $usuarioId, string $slot, int $jugadorId): void
    {
        if (!in_array($slot, self::SLOTS_PLANIFICADOR, true)) {
            throw new DomainException('Puesto de la formación no válido.');
        }

        $this->repo->asignarPlanificador($usuarioId, $slot, $jugadorId);
    }

    public function limpiarSlotPlanificador(int $usuarioId, string $slot): void
    {
        $this->repo->limpiarSlotPlanificador($usuarioId, $slot);
    }

    public function limpiarPlanificador(int $usuarioId): void
    {
        $this->repo->limpiarPlanificador($usuarioId);
    }

    /** Reservas del planificador: 4 plazas fijas, como titulares/suplentes (corregido tras feedback: no eran "sin límite"). */
    public const MAX_RESERVAS_PLANIFICADOR = 4;

    public function listarReservasPlanificador(int $usuarioId): array
    {
        return $this->repo->listarReservasPlanificador($usuarioId);
    }

    public function agregarReservaPlanificador(int $usuarioId, int $jugadorId): void
    {
        if (count($this->repo->listarReservasPlanificador($usuarioId)) >= self::MAX_RESERVAS_PLANIFICADOR) {
            throw new DomainException('Ya tienes las ' . self::MAX_RESERVAS_PLANIFICADOR . ' reservas ocupadas. Quita una antes de añadir otra.');
        }

        $this->repo->agregarReservaPlanificador($usuarioId, $jugadorId);
    }

    public function quitarReservaPlanificador(int $usuarioId, int $jugadorId): void
    {
        $this->repo->quitarReservaPlanificador($usuarioId, $jugadorId);
    }

    /* ===================== Simulador de supertécnicas =====================
       Jugadores "de prueba" y sus supertécnicas (incluidas las hipertécnicas),
       privados por usuario, sin relación con jugadores/mercado reales. */

    public const MAX_TECNICAS_POR_JUGADOR = 4;
    private const CATEGORIAS_VALIDAS = ['parada', 'defensa', 'regate', 'tiro'];
    private const HIPERTECNICAS_VALIDAS = ['espiritu_guerrero', 'mixi_max', 'totem', 'despertar'];

    public function listarJugadoresSimulados(int $usuarioId): array
    {
        return $this->repo->listarJugadoresSimulados($usuarioId);
    }

    public function crearJugadorSimulado(int $usuarioId, string $nombre): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new DomainException('El jugador de prueba necesita un nombre.');
        }

        return $this->repo->crearJugadorSimulado($usuarioId, $nombre);
    }

    public function eliminarJugadorSimulado(int $usuarioId, int $jugadorSimuladoId): void
    {
        $this->repo->eliminarJugadorSimulado($usuarioId, $jugadorSimuladoId);
    }

    public function listarTecnicasSimuladas(int $usuarioId): array
    {
        return $this->repo->listarTecnicasSimuladas($usuarioId);
    }

    /** @param array{nombre:string,afinidad:string,tension:int,categoria:?string,subcategoria:?string,hipertecnica:?string,despertar_variante:?string,armadura:?bool} $datos */
    public function agregarTecnicaSimulada(int $usuarioId, ?int $jugadorId, ?int $jugadorSimuladoId, array $datos): void
    {
        if (($jugadorId === null) === ($jugadorSimuladoId === null)) {
            throw new DomainException('La técnica necesita exactamente un jugador (real o de prueba).');
        }
        if ($jugadorSimuladoId !== null && !$this->repo->jugadorSimuladoExiste($usuarioId, $jugadorSimuladoId)) {
            throw new DomainException('Ese jugador de prueba no existe.');
        }

        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            throw new DomainException('La supertécnica necesita un nombre.');
        }

        if ($this->repo->contarTecnicasDeJugador($usuarioId, $jugadorId, $jugadorSimuladoId) >= self::MAX_TECNICAS_POR_JUGADOR) {
            throw new DomainException('Máximo ' . self::MAX_TECNICAS_POR_JUGADOR . ' supertécnicas por jugador (incluidas las hipertécnicas).');
        }

        $categoria = $datos['categoria'] ?: null;
        if ($categoria !== null && !in_array($categoria, self::CATEGORIAS_VALIDAS, true)) {
            throw new DomainException('Categoría no válida.');
        }
        $hipertecnica = $datos['hipertecnica'] ?: null;
        if ($hipertecnica !== null && !in_array($hipertecnica, self::HIPERTECNICAS_VALIDAS, true)) {
            throw new DomainException('Hipertécnica no válida.');
        }

        $this->repo->agregarTecnicaSimulada(
            $usuarioId,
            $jugadorId,
            $jugadorSimuladoId,
            $nombre,
            (string) $datos['afinidad'],
            // Las hipertécnicas (espíritu guerrero, tótem, Mixi Max, despertar) no
            // cuestan tensión: no tienen nada que ver con ese sistema. Se fuerza a 0
            // aquí, no solo en el cliente, para que no dependa de que el formulario
            // se comporte bien.
            $hipertecnica !== null ? 0 : (int) $datos['tension'],
            $categoria,
            $datos['subcategoria'] ?: null,
            $hipertecnica,
            // hipertecnica_variante es una columna genérica: para "despertar" guarda
            // qué variante, y para "espíritu guerrero" guarda si lleva armadura — la
            // armadura es opcional, nunca parte fija de elegir esa hipertécnica.
            match ($hipertecnica) {
                'despertar' => $datos['despertar_variante'] ?: null,
                'espiritu_guerrero' => !empty($datos['armadura']) ? 'con_armadura' : null,
                default => null,
            }
        );
    }

    public function eliminarTecnicaSimulada(int $usuarioId, int $tecnicaId): void
    {
        $this->repo->eliminarTecnicaSimulada($usuarioId, $tecnicaId);
    }
}
