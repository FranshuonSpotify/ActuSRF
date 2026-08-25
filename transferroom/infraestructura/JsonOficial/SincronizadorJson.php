<?php

declare(strict_types=1);

/**
 * Único punto de lectura del JSON oficial de la liga.
 * Nunca se consulta el JSON directamente desde una pantalla o un motor.
 *
 * Decisión cerrada de Alejandro: la sincronización es de solo lectura y en
 * un único sentido (JSON → BD), y así se queda. Esta clase nunca escribirá
 * en datos_oficiales.json bajo ningún concepto — los fichajes, traspasos y
 * cambios de club que ocurran en Transfer Room no deben tocar el JSON.
 * El JSON se actualiza a mano, por Alejandro, fuera de esta aplicación.
 */
final class SincronizadorJson
{
    public function __construct(private string $rutaJson)
    {
    }

    public function obtenerEquiposActivos(): array
    {
        $datos = $this->leer();

        return array_values(array_filter(
            $datos['equipos'] ?? [],
            static fn (array $equipo): bool => empty($equipo['archivado'])
        ));
    }

    /** Todos los equipos del JSON, activos y archivados — para reactivar un club archivado como participante nuevo. */
    public function obtenerTodosLosEquipos(): array
    {
        $datos = $this->leer();

        return $datos['equipos'] ?? [];
    }

    /** Solo los archivados, para el selector de "reactivar club" (05-transfer_room_docs/01_bloqueante_primer_mercado/10). */
    public function obtenerEquiposArchivados(): array
    {
        return array_values(array_filter(
            $this->obtenerTodosLosEquipos(),
            static fn (array $equipo): bool => !empty($equipo['archivado'])
        ));
    }

    public function obtenerAgentesLibresOficiales(): array
    {
        $datos = $this->leer();

        return $datos['agentes_libres'] ?? [];
    }

    /**
     * Jugadores de clubes archivados: el club ya no compite, pero sus
     * jugadores siguen existiendo y deben poder ficharse como agentes libres.
     */
    public function obtenerJugadoresDeClubesArchivados(): array
    {
        $datos = $this->leer();
        $jugadores = [];

        foreach ($datos['equipos'] ?? [] as $equipo) {
            if (empty($equipo['archivado'])) {
                continue;
            }

            foreach ($equipo['jugadores'] ?? [] as $jugador) {
                $jugadores[] = $jugador + ['club_archivado_nombre' => $equipo['nombre'] ?? $equipo['id']];
            }
        }

        return $jugadores;
    }

    public function obtenerConfiguracionLiga(): array
    {
        $datos = $this->leer();

        return $datos['config'] ?? [];
    }

    private function leer(): array
    {
        if (!is_file($this->rutaJson)) {
            throw new RuntimeException("No se encuentra el JSON oficial en {$this->rutaJson}.");
        }

        $contenido = file_get_contents($this->rutaJson);
        $datos = json_decode($contenido, true);

        if ($datos === null) {
            throw new RuntimeException('El JSON oficial no es válido: ' . json_last_error_msg());
        }

        return $datos;
    }
}
