<?php

declare(strict_types=1);

/**
 * Asistente de inscripción de un club en una temporada (Cap. XXIII).
 * Un club nunca participa "por sí mismo": siempre a través de una
 * participación concreta, ligada a una temporada.
 */
final class ParticipationEngine
{
    private const ORIGENES_VALIDOS = ['CONTINUAR_ANTERIOR', 'IMPORTAR_JSON', 'VACIA', 'SNAPSHOT', 'CLON'];
    private const DIVISIONES_VALIDAS = ['SUPERLIGA', 'ASCENSO'];

    public function __construct(
        private ParticipacionRepository $participaciones,
        private ClubRepository $clubes,
        private TemporadaRepository $temporadas,
        private AuditEngine $auditoria
    ) {
    }

    public function inscribirClub(
        int $temporadaId,
        string $clubId,
        string $division,
        ?int $usuarioPresidenteId,
        string $origenPlantilla,
        int $usuarioAdminId
    ): int {
        if (!in_array($division, self::DIVISIONES_VALIDAS, true)) {
            throw new DomainException("División inválida: {$division}.");
        }

        if (!in_array($origenPlantilla, self::ORIGENES_VALIDOS, true)) {
            throw new DomainException("Origen de plantilla inválido: {$origenPlantilla}.");
        }

        if ($this->clubes->buscarPorId($clubId) === null) {
            throw new DomainException('El club no existe en el catálogo.');
        }

        if ($this->temporadas->buscarPorId($temporadaId) === null) {
            throw new DomainException('La temporada no existe.');
        }

        if ($this->participaciones->buscarPorClubYTemporada($clubId, $temporadaId) !== null) {
            throw new DomainException('Este club ya está inscrito en esta temporada.');
        }

        $id = $this->participaciones->crear(
            $clubId,
            $temporadaId,
            $usuarioPresidenteId,
            $division,
            $origenPlantilla
        );

        $this->auditoria->registrar($usuarioAdminId, 'INSCRIBIR_CLUB', 'participaciones_club', (string) $id, null, [
            'club_id' => $clubId,
            'temporada_id' => $temporadaId,
            'division' => $division,
            'origen_plantilla' => $origenPlantilla,
        ]);

        return $id;
    }

    public function asignarPresidente(int $participacionId, int $usuarioPresidenteId, int $usuarioAdminId): void
    {
        $participacion = $this->participaciones->buscarPorId($participacionId);

        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $this->participaciones->asignarPresidente($participacionId, $usuarioPresidenteId);

        $this->auditoria->registrar(
            $usuarioAdminId,
            'ASIGNAR_PRESIDENTE',
            'participaciones_club',
            (string) $participacionId,
            ['usuario_presidente_id' => $participacion['usuario_presidente_id']],
            ['usuario_presidente_id' => $usuarioPresidenteId]
        );
    }

    public function retirarClub(int $participacionId, int $usuarioAdminId): void
    {
        $participacion = $this->participaciones->buscarPorId($participacionId);

        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        if ($participacion['estado'] === 'RETIRADA') {
            throw new DomainException('Este club ya está retirado de esta temporada.');
        }

        $this->participaciones->actualizarEstado($participacionId, 'RETIRADA');

        $this->auditoria->registrar(
            $usuarioAdminId,
            'RETIRAR_PARTICIPACION',
            'participaciones_club',
            (string) $participacionId,
            ['estado' => $participacion['estado']],
            ['estado' => 'RETIRADA']
        );
    }

    public function listarPorTemporada(int $temporadaId): array
    {
        return $this->participaciones->listarPorTemporada($temporadaId);
    }

    /**
     * Recuperación manual (admin/clubes.php): si la resolución de plantilla
     * falló al inscribir (p. ej. "Continuar desde la anterior" sin que
     * exista una anterior válida), la participación queda creada pero
     * vacía. Permite reintentar con un origen distinto sin tener que borrar
     * y volver a crear el club — nunca se borra nada en este proyecto.
     */
    public function cambiarOrigenPlantilla(int $participacionId, string $origenPlantilla, int $usuarioAdminId): void
    {
        if (!in_array($origenPlantilla, self::ORIGENES_VALIDOS, true)) {
            throw new DomainException("Origen de plantilla inválido: {$origenPlantilla}.");
        }

        $participacion = $this->participaciones->buscarPorId($participacionId);
        if ($participacion === null) {
            throw new DomainException('La participación no existe.');
        }

        $this->participaciones->actualizarOrigenPlantilla($participacionId, $origenPlantilla);

        $this->auditoria->registrar(
            $usuarioAdminId,
            'CAMBIAR_ORIGEN_PLANTILLA',
            'participaciones_club',
            (string) $participacionId,
            ['origen_plantilla' => $participacion['origen_plantilla']],
            ['origen_plantilla' => $origenPlantilla]
        );
    }

    public function buscarPorId(int $id): ?array
    {
        return $this->participaciones->buscarPorId($id);
    }

    public function buscarPorUsuarioYTemporada(int $usuarioPresidenteId, int $temporadaId): ?array
    {
        return $this->participaciones->buscarPorUsuarioYTemporada($usuarioPresidenteId, $temporadaId);
    }

    public function listarPorUsuario(int $usuarioPresidenteId): array
    {
        return $this->participaciones->listarPorUsuario($usuarioPresidenteId);
    }
}
