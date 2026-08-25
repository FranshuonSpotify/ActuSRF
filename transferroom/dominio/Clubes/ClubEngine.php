<?php

declare(strict_types=1);

final class ClubEngine
{
    public function __construct(
        private ClubRepository $clubes,
        private SincronizadorJson $sincronizador,
        private AuditEngine $auditoria
    ) {
    }

    public function listarCatalogo(): array
    {
        return $this->clubes->listarTodos();
    }

    public function buscarPorId(string $id): ?array
    {
        return $this->clubes->buscarPorId($id);
    }

    /**
     * Importa/actualiza el catálogo de clubes desde el JSON oficial.
     * Solo clubes no archivados: los archivados no compiten actualmente (Cap. XXIII).
     */
    /**
     * Vista previa de sincronización (05-transfer_room_docs/01_bloqueante_primer_mercado/10:
     * "nunca aplicar a ciegas"): compara el JSON oficial contra lo que ya hay
     * en la base de datos, sin escribir nada todavía.
     *
     * @return array{nuevos: array<int, string>, modificados: array<int, string>, sin_cambios: int}
     */
    public function previsualizarSincronizacion(): array
    {
        $nuevos = [];
        $modificados = [];
        $sinCambios = 0;

        foreach ($this->sincronizador->obtenerEquiposActivos() as $equipo) {
            $actual = $this->clubes->buscarPorId($equipo['id']);

            if ($actual === null) {
                $nuevos[] = $equipo['nombre'];
                continue;
            }

            $cambia = $actual['nombre'] !== $equipo['nombre']
                || ($actual['escudo_url'] ?? null) !== ($equipo['escudo'] ?? null)
                || ($actual['ciudad'] ?? null) !== ($equipo['ciudad'] ?? null)
                || ($actual['color1'] ?? null) !== ($equipo['color1'] ?? null)
                || ($actual['color2'] ?? null) !== ($equipo['color2'] ?? null)
                || ($actual['abreviatura'] ?? null) !== ($equipo['abreviatura'] ?? null);

            if ($cambia) {
                $modificados[] = $equipo['nombre'];
            } else {
                $sinCambios++;
            }
        }

        return ['nuevos' => $nuevos, 'modificados' => $modificados, 'sin_cambios' => $sinCambios];
    }

    /**
     * Reactiva un club marcado como archivado en el JSON oficial
     * (05-transfer_room_docs/01_bloqueante_primer_mercado/10): lo trae al
     * catálogo de `clubes` para que pueda inscribirse como participante
     * nuevo, exactamente igual que cualquier otro club activo. El JSON
     * nunca se toca — sigue diciendo "archivado", que es correcto (así lo
     * mantiene Alejandro); esto solo afecta a la base de datos.
     */
    public function reactivarClubArchivado(string $clubId, int $usuarioAdminId): void
    {
        $equipo = null;
        foreach ($this->sincronizador->obtenerEquiposArchivados() as $candidato) {
            if ($candidato['id'] === $clubId) {
                $equipo = $candidato;
                break;
            }
        }

        if ($equipo === null) {
            throw new DomainException('No se encuentra ese club como archivado en el JSON oficial.');
        }

        $this->clubes->upsert(
            $equipo['id'],
            $equipo['nombre'],
            $equipo['escudo'] ?? null,
            $equipo['ciudad'] ?? null,
            $equipo['color1'] ?? null,
            $equipo['color2'] ?? null,
            $equipo['abreviatura'] ?? null
        );

        $this->auditoria->registrar($usuarioAdminId, 'REACTIVAR_CLUB_ARCHIVADO', 'clubes', $clubId, null, [
            'nombre' => $equipo['nombre'],
        ]);
    }

    public function sincronizarDesdeJson(int $usuarioAdminId): int
    {
        $equipos = $this->sincronizador->obtenerEquiposActivos();

        foreach ($equipos as $equipo) {
            $this->clubes->upsert(
                $equipo['id'],
                $equipo['nombre'],
                $equipo['escudo'] ?? null,
                $equipo['ciudad'] ?? null,
                $equipo['color1'] ?? null,
                $equipo['color2'] ?? null,
                $equipo['abreviatura'] ?? null
            );
        }

        $this->auditoria->registrar($usuarioAdminId, 'SINCRONIZAR_CATALOGO', 'clubes', null, null, [
            'total_equipos' => count($equipos),
        ]);

        return count($equipos);
    }

    /**
     * Alta de un club que no existe en el JSON oficial (Cap. XXIII: la liga
     * es dinámica, pueden entrar clubes nuevos entre temporadas). El JSON
     * nunca se toca: este club vive solo en la base de datos hasta que
     * Alejandro decida añadirlo también al JSON a mano, si quiere.
     */
    public function crearClubManual(
        string $nombre,
        ?string $ciudad,
        ?string $escudoUrl,
        ?string $color1,
        ?string $color2,
        ?string $abreviatura,
        int $usuarioAdminId
    ): string {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new DomainException('El club necesita un nombre.');
        }

        $id = 'manual_' . bin2hex(random_bytes(6));

        $this->clubes->upsert($id, $nombre, $escudoUrl, $ciudad, $color1, $color2, $abreviatura);

        $this->auditoria->registrar($usuarioAdminId, 'CREAR_CLUB_MANUAL', 'clubes', $id, null, [
            'nombre' => $nombre,
        ]);

        return $id;
    }
}
