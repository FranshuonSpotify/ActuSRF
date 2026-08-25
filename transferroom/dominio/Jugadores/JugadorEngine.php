<?php

declare(strict_types=1);

/**
 * Identidad y plantilla de jugadores (Cap. IV/V). No conoce contratos ni
 * salarios: eso pertenece al módulo Contratos, que llega después en el
 * orden obligatorio del Cap. III.
 */
final class JugadorEngine
{
    /** Variantes conocidas de afinidad en el JSON oficial (datos sucios reales). */
    private const NORMALIZACION_AFINIDAD = [
        'forest' => 'Bosque',
    ];

    public function __construct(
        private PDO $db,
        private JugadorRepository $jugadores,
        private AfinidadRepository $afinidades,
        private ParticipacionRepository $participaciones,
        private TemporadaRepository $temporadas,
        private ClubRepository $clubes,
        private SincronizadorJson $sincronizador,
        private AuditEngine $auditoria,
        private ?WikiResolverEngine $wikiResolver = null
    ) {
    }

    /**
     * Resolución de wiki tras crear un jugador (spec §21 "jugador nuevo").
     * Sin cola de trabajos en este proyecto (CLAUDE.md §6: no hay cron ni
     * worker persistente), así que se intenta en el momento, pero nunca debe
     * poder tumbar la creación del jugador si Fandom falla o tarda: se traga
     * cualquier excepción. El backfill (db/resolver_wiki_backfill.php) se
     * encarga de los que queden en "error"/"pending".
     */
    private function encolarResolucionWiki(int $jugadorId): void
    {
        if ($this->wikiResolver === null) {
            return;
        }

        try {
            $jugador = $this->jugadores->buscarPorId($jugadorId);
            if ($jugador === null) {
                return;
            }

            $resultado = $this->wikiResolver->resolver($jugador);
            $this->jugadores->actualizarResolucionWiki($jugadorId, $resultado);
        } catch (Throwable $e) {
            error_log('[wiki] fallo al resolver jugador nuevo #' . $jugadorId . ': ' . $e->getMessage());
        }
    }

    /**
     * Normaliza un valor de afinidad tal y como llega del JSON oficial.
     * Pura: sin BD, testeable de forma aislada.
     */
    public static function normalizarAfinidad(?string $bruto): ?string
    {
        if ($bruto === null) {
            return null;
        }

        $limpio = trim($bruto);

        if ($limpio === '' || str_starts_with(strtolower($limpio), 'http')) {
            return null;
        }

        $clave = strtolower($limpio);
        if (isset(self::NORMALIZACION_AFINIDAD[$clave])) {
            return self::NORMALIZACION_AFINIDAD[$clave];
        }

        return mb_convert_case($limpio, MB_CASE_TITLE);
    }

    /**
     * Aplica el origen de plantilla ya decidido al inscribir la participación
     * (Cap. XXIII, paso 4 del asistente). Devuelve el número de jugadores resultantes.
     */
    public function aplicarOrigenPlantilla(int $participacionId, int $usuarioAdminId): int
    {
        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        return match ($participacion['origen_plantilla']) {
            'IMPORTAR_JSON' => $this->importarDesdeJson($participacion, $usuarioAdminId),
            'CONTINUAR_ANTERIOR' => $this->continuarDesdeAnterior($participacion, $usuarioAdminId),
            'VACIA' => 0,
            default => throw new DomainException("Origen de plantilla no soportado todavía: {$participacion['origen_plantilla']}."),
        };
    }

    private function importarDesdeJson(array $participacion, int $usuarioAdminId): int
    {
        $club = $this->clubes->buscarPorId($participacion['club_id']);
        if ($club === null) {
            throw new DomainException('El club de esta participación no existe en el catálogo.');
        }

        // obtenerTodosLosEquipos(), no solo activos: un club reactivado desde
        // archivado (ClubEngine::reactivarClubArchivado) también debe poder
        // importar su plantilla real, aunque el JSON lo siga marcando archivado.
        $equipos = $this->sincronizador->obtenerTodosLosEquipos();
        $equipoJson = null;
        foreach ($equipos as $equipo) {
            if ($equipo['id'] === $participacion['club_id']) {
                $equipoJson = $equipo;
                break;
            }
        }

        if ($equipoJson === null) {
            throw new DomainException('No se encuentra la plantilla de este club en el JSON oficial.');
        }

        $participacionId = (int) $participacion['id'];
        $creados = 0;

        $this->db->beginTransaction();
        try {
            foreach ($equipoJson['jugadores'] ?? [] as $jugadorJson) {
                if ($this->crearDesdeJsonSiValido($jugadorJson, $participacionId) !== null) {
                    $creados++;
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar(
            $usuarioAdminId,
            'IMPORTAR_PLANTILLA_JSON',
            'participaciones_club',
            (string) $participacionId,
            null,
            ['jugadores_creados' => $creados]
        );

        return $creados;
    }

    private function continuarDesdeAnterior(array $participacion, int $usuarioAdminId): int
    {
        $temporadaActual = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        if ($temporadaActual === null) {
            throw new DomainException('La temporada de esta participación no existe.');
        }

        $anterior = $this->participaciones->buscarAnteriorDelClub(
            $participacion['club_id'],
            (int) $temporadaActual['numero']
        );

        if ($anterior === null) {
            throw new DomainException('Este club no tiene una participación anterior de la que continuar.');
        }

        // Si el club estuvo ausente una o más temporadas, no hay "plantilla anterior"
        // que continuar: vuelve como si fuese un club nuevo (decisión de Alejandro).
        $anteriorTemporada = $this->temporadas->buscarPorId((int) $anterior['temporada_id']);
        if ((int) $anteriorTemporada['numero'] !== (int) $temporadaActual['numero'] - 1) {
            throw new DomainException(
                'Este club no participó en la temporada inmediatamente anterior '
                . '(su última participación fue en la temporada ' . $anteriorTemporada['numero'] . '). '
                . 'Tras una ausencia vuelve sin plantilla, como un club nuevo: usa "Plantilla vacía" o "Importar del JSON oficial".'
            );
        }

        $movidos = $this->jugadores->moverTodosDeParticipacion((int) $anterior['id'], (int) $participacion['id']);

        $this->auditoria->registrar(
            $usuarioAdminId,
            'CONTINUAR_PLANTILLA_ANTERIOR',
            'participaciones_club',
            (string) $participacion['id'],
            ['participacion_anterior_id' => $anterior['id']],
            ['jugadores_movidos' => $movidos]
        );

        return $movidos;
    }

    public function buscarPorId(int $jugadorId): ?array
    {
        return $this->jugadores->buscarPorId($jugadorId);
    }

    public function listarPlantilla(int $participacionId): array
    {
        return $this->jugadores->listarPorParticipacion($participacionId);
    }

    public function listarAgentesLibresConTier(): array
    {
        return $this->jugadores->listarAgentesLibresConTier();
    }

    public function listarTodosParaScouting(): array
    {
        return $this->jugadores->listarTodosParaScouting();
    }

    public function listarAgentesLibresSinTier(): array
    {
        return $this->jugadores->listarAgentesLibresSinTier();
    }

    public function listarConEstadoInconsistente(): array
    {
        return $this->jugadores->listarConEstadoInconsistente();
    }

    public function listarTodosParaFotos(): array
    {
        return $this->jugadores->listarTodosParaFotos();
    }

    public function actualizarFoto(int $jugadorId, ?string $fotoUrl, int $usuarioAdminId): void
    {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        $fotoUrl = $fotoUrl !== null ? trim($fotoUrl) : null;
        if ($fotoUrl === '') {
            $fotoUrl = null;
        }
        if ($fotoUrl !== null && filter_var($fotoUrl, FILTER_VALIDATE_URL) === false) {
            throw new DomainException('La URL de la foto no es válida.');
        }

        $this->jugadores->actualizarFoto($jugadorId, $fotoUrl);

        $this->auditoria->registrar($usuarioAdminId, 'ACTUALIZAR_FOTO_JUGADOR', 'jugadores', (string) $jugadorId,
            ['foto_url' => $jugador['foto_url']], ['foto_url' => $fotoUrl]);
    }

    /**
     * Panel de intervención manual: corrige un jugador cuyo estado y
     * participación se contradicen, dejándolo en el lado consistente con su
     * participación real (nunca al revés, para no inventar un club que no
     * tiene). Reutiliza moverAParticipacion, que ya empareja ambos campos.
     */
    public function corregirEstadoInconsistente(int $jugadorId, int $usuarioAdminId): void
    {
        $jugador = $this->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        $estadoAntes = $jugador['estado'];
        $this->jugadores->moverAParticipacion($jugadorId, $jugador['participacion_actual_id']);

        $this->auditoria->registrar($usuarioAdminId, 'CORREGIR_ESTADO_INCONSISTENTE', 'jugadores', (string) $jugadorId,
            ['estado' => $estadoAntes], ['participacion_actual_id' => $jugador['participacion_actual_id']]);
    }

    private const MAX_FRANQUICIA_POR_CLUB = 4;

    public function contarFranquicia(int $participacionId): int
    {
        return $this->jugadores->contarFranquiciaPorParticipacion($participacionId);
    }

    /** mercado.md §9: hasta 4 jugadores franquicia por club, y solo entre quienes ya están en su plantilla. */
    public function designarFranquicia(int $jugadorId, int $participacionId, int $usuarioAdminId): void
    {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if ((int) ($jugador['participacion_actual_id'] ?? 0) !== $participacionId) {
            throw new DomainException('Este jugador no pertenece a esta plantilla.');
        }

        $this->requerirMercadoActivoParaParticipacion($participacionId);

        if ((bool) $jugador['es_franquicia']) {
            throw new DomainException('Este jugador ya está designado como franquicia.');
        }

        if ($this->jugadores->contarFranquiciaPorParticipacion($participacionId) >= self::MAX_FRANQUICIA_POR_CLUB) {
            throw new DomainException('Este club ya tiene el máximo de ' . self::MAX_FRANQUICIA_POR_CLUB . ' jugadores franquicia.');
        }

        $this->jugadores->marcarFranquicia($jugadorId, true);

        $this->auditoria->registrar($usuarioAdminId, 'DESIGNAR_FRANQUICIA', 'jugadores', (string) $jugadorId, null, [
            'participacion_id' => $participacionId,
        ]);
    }

    public function quitarFranquicia(int $jugadorId, int $usuarioAdminId): void
    {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        if (!(bool) $jugador['es_franquicia']) {
            throw new DomainException('Este jugador no está designado como franquicia.');
        }

        $this->requerirMercadoActivoParaParticipacion((int) ($jugador['participacion_actual_id'] ?? 0));

        $this->jugadores->marcarFranquicia($jugadorId, false);

        $this->auditoria->registrar($usuarioAdminId, 'QUITAR_FRANQUICIA', 'jugadores', (string) $jugadorId, null, null);
    }

    /**
     * mercado.md §9 (aclarado con Alejandro): las franquicias solo se pueden
     * designar/retirar mientras el mercado de esa temporada está abierto.
     * Una vez en COMPETICION, la designación queda fija hasta el próximo
     * mercado — SeasonEngine::validarFranquiciasCompletas ya exige las 4
     * designadas antes de permitir esa transición, así que esta guarda es
     * la contraparte: nadie puede tocarlas después de ese punto.
     */
    private function requerirMercadoActivoParaParticipacion(int $participacionId): void
    {
        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La plantilla no existe.');
        }

        $temporada = $this->temporadas->buscarPorId((int) $participacion['temporada_id']);
        if ($temporada === null) {
            throw new DomainException('La temporada no existe.');
        }

        SeasonEngine::requiereMercadoActivo($temporada);
    }

    /**
     * Importa los agentes libres oficiales del JSON (nunca tuvieron club).
     * Idempotente por nombre: no reimporta a quien ya está en el sistema
     * como agente libre oficial sin tier todavía.
     */
    public function importarAgentesLibresOficiales(int $usuarioAdminId): int
    {
        $yaImportados = array_flip($this->jugadores->listarNombresAgentesLibresOficialesImportados());
        $creados = 0;

        // Dos fuentes: agentes libres declarados como tales, y jugadores de
        // clubes archivados (el club ya no compite, pero ellos sí pueden ficharse).
        $candidatos = [
            ...$this->sincronizador->obtenerAgentesLibresOficiales(),
            ...$this->sincronizador->obtenerJugadoresDeClubesArchivados(),
        ];

        $this->db->beginTransaction();
        try {
            foreach ($candidatos as $jugadorJson) {
                $nombre = trim((string) ($jugadorJson['nombre'] ?? ''));
                if ($nombre === '' || isset($yaImportados[$nombre])) {
                    continue;
                }

                $jugadorCreadoId = $this->crearDesdeJsonSiValido($jugadorJson, null);
                if ($jugadorCreadoId !== null) {
                    // 05-transfer_room_docs/01_bloqueante_primer_mercado/04, punto 5: distinguir en
                    // la interfaz a los procedentes de un club archivado. No puede reutilizar
                    // origen_club_agencia_libre (tiene FK contra clubes, y los archivados no
                    // están ahí — nunca se sincronizan): columna propia sin FK.
                    if (isset($jugadorJson['club_archivado_nombre'])) {
                        $this->jugadores->marcarProcedenciaArchivado($jugadorCreadoId, (string) $jugadorJson['club_archivado_nombre']);
                    }
                    $yaImportados[$nombre] = true; // evita duplicados si el mismo nombre aparece en ambas fuentes
                    $creados++;
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auditoria->registrar($usuarioAdminId, 'IMPORTAR_AGENTES_LIBRES_OFICIALES', 'jugadores', null, null, [
            'jugadores_creados' => $creados,
        ]);

        return $creados;
    }

    /**
     * Jugador inventado por un presidente, fuera del JSON oficial (Cap. V
     * Título XII). No pasa por aprobación previa: se ficha al instante quien
     * lo cree primero (decisión de Alejandro); ContractEngine lo marcará
     * como pendiente de revisión al firmarle el primer contrato.
     */
    public function crearJugadorExterno(string $nombre, string $posicion, ?string $afinidadNombre, ?string $fotoUrl, int $usuarioAdminId): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new DomainException('El jugador necesita un nombre.');
        }

        $posicion = strtoupper(trim($posicion));
        if (!in_array($posicion, ['POR', 'DEF', 'MED', 'DEL'], true)) {
            throw new DomainException('Posición inválida. Debe ser POR, DEF, MED o DEL.');
        }

        $afinidadNormalizada = self::normalizarAfinidad($afinidadNombre);
        $afinidadId = $afinidadNormalizada !== null ? $this->afinidades->obtenerOCrearPorNombre($afinidadNormalizada) : null;

        $jugadorId = $this->jugadores->crear($nombre, $posicion, $afinidadId, $fotoUrl !== '' ? $fotoUrl : null, 'EXTERNO', null);

        $this->auditoria->registrar($usuarioAdminId, 'CREAR_JUGADOR_EXTERNO', 'jugadores', (string) $jugadorId, null, [
            'nombre' => $nombre,
            'posicion' => $posicion,
            'afinidad' => $afinidadNormalizada,
        ]);

        $this->encolarResolucionWiki($jugadorId);

        return $jugadorId;
    }

    /**
     * Corrige el nombre de un jugador cuando el dato de origen (JSON oficial)
     * estaba mal escrito — no es un renombrado libre, es una corrección de
     * grafía sobre el mismo jugador real. Deja auditoría de qué había antes.
     */
    public function corregirNombre(int $jugadorId, string $nombreCorregido, int $usuarioAdminId): void
    {
        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        $nombreCorregido = trim($nombreCorregido);
        if ($nombreCorregido === '') {
            throw new DomainException('El nombre no puede estar vacío.');
        }

        $this->jugadores->renombrar($jugadorId, $nombreCorregido);

        $this->auditoria->registrar($usuarioAdminId, 'CORREGIR_NOMBRE_JUGADOR', 'jugadores', (string) $jugadorId,
            ['nombre' => $jugador['nombre']], ['nombre' => $nombreCorregido]);
    }

    /** Panel admin (§29/§39): estadísticas y cola de revisión manual. */
    public function contarPorEstadoWiki(): array
    {
        return $this->jugadores->contarPorEstadoWiki();
    }

    public function listarNeedsReviewWiki(): array
    {
        return $this->jugadores->listarNeedsReviewWiki();
    }

    public function listarTodosParaWiki(): array
    {
        return $this->jugadores->listarTodosParaWiki();
    }

    /**
     * Confirmación administrativa de un candidato needs_review (§29): pasa a
     * "manual", protegido de cualquier backfill futuro (§43).
     */
    public function confirmarResolucionWikiManual(int $jugadorId, string $titulo, int $usuarioAdminId): void
    {
        if ($this->wikiResolver === null) {
            throw new DomainException('El resolver de wiki no está disponible.');
        }

        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new DomainException('El título de la página de Fandom no puede estar vacío.');
        }

        $url = $this->wikiResolver->provider()->urlPagina($titulo);
        $this->jugadores->fijarResolucionManual($jugadorId, $titulo, $url);

        $this->auditoria->registrar($usuarioAdminId, 'CONFIRMAR_WIKI_MANUAL', 'jugadores', (string) $jugadorId, null, [
            'wiki_title' => $titulo,
        ]);
    }

    /** Rechazo de un candidato dudoso (§29 "Rechazar"): vuelve a pending para que el backfill lo reintente más adelante. */
    public function rechazarResolucionWiki(int $jugadorId, int $usuarioAdminId): void
    {
        $this->jugadores->reiniciarResolucionWiki($jugadorId);

        $this->auditoria->registrar($usuarioAdminId, 'RECHAZAR_WIKI_CANDIDATO', 'jugadores', (string) $jugadorId, null, null);
    }

    /** "Buscar de nuevo" (§29): fuerza una resolución nueva ahora mismo, incluso si ya estaba needs_review/not_found. */
    public function reintentarResolucionWiki(int $jugadorId, int $usuarioAdminId): array
    {
        if ($this->wikiResolver === null) {
            throw new DomainException('El resolver de wiki no está disponible.');
        }

        $jugador = $this->jugadores->buscarPorId($jugadorId);
        if ($jugador === null) {
            throw new DomainException('El jugador no existe.');
        }

        $resultado = $this->wikiResolver->resolver($jugador, true);
        $this->jugadores->actualizarResolucionWiki($jugadorId, $resultado);

        $this->auditoria->registrar($usuarioAdminId, 'REINTENTAR_RESOLUCION_WIKI', 'jugadores', (string) $jugadorId, null, [
            'resultado' => $resultado['status'],
        ]);

        return $resultado;
    }

    /** @return int|null el id del jugador creado, o null si el registro del JSON no tenía datos válidos. */
    private function crearDesdeJsonSiValido(array $jugadorJson, ?int $participacionId): ?int
    {
        $posicion = strtoupper(trim((string) ($jugadorJson['posicion'] ?? '')));
        if (!in_array($posicion, ['POR', 'DEF', 'MED', 'DEL'], true)) {
            return null; // dato incompleto en el JSON: no se inventa una posición
        }

        $afinidadNombre = self::normalizarAfinidad($jugadorJson['afinidad'] ?? null);
        $afinidadId = $afinidadNombre !== null ? $this->afinidades->obtenerOCrearPorNombre($afinidadNombre) : null;

        return $this->jugadores->crear(
            trim((string) ($jugadorJson['nombre'] ?? 'Jugador sin nombre')),
            $posicion,
            $afinidadId,
            $jugadorJson['foto'] ?? null,
            'JSON_OFICIAL',
            $participacionId
        );
    }
}
