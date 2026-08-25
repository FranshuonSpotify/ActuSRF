<?php

declare(strict_types=1);

final class ConfigurationEngine
{
    public function __construct(
        private ConfiguracionRepository $repositorio,
        private AuditEngine $auditoria
    ) {
    }

    public function obtenerDecimal(string $clave): float
    {
        return (float) $this->requerir($clave);
    }

    public function obtenerInt(string $clave): int
    {
        return (int) $this->requerir($clave);
    }

    public function obtenerString(string $clave): string
    {
        return $this->requerir($clave);
    }

    public function obtenerBool(string $clave): bool
    {
        return $this->requerir($clave) === '1';
    }

    public function listarTodas(): array
    {
        return $this->repositorio->listarTodas();
    }

    public function actualizar(string $clave, string $valor, int $usuarioAdminId): void
    {
        $anterior = $this->repositorio->obtener($clave);
        $this->repositorio->actualizar($clave, $valor);
        $this->auditoria->registrar($usuarioAdminId, 'ACTUALIZAR_CONFIGURACION', 'configuracion', $clave,
            ['valor' => $anterior], ['valor' => $valor]);
    }

    /** Plantillas guardadas (Fase 2): fotografía nombrada de los valores editables actuales, para reaplicar más tarde. */
    public function guardarPlantilla(string $nombre, int $usuarioAdminId): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new DomainException('La plantilla necesita un nombre.');
        }

        $valores = [];
        foreach ($this->repositorio->listarTodas() as $c) {
            if ($c['editable']) {
                $valores[$c['clave']] = $c['valor'];
            }
        }

        $this->repositorio->crearPlantilla($nombre, json_encode($valores, JSON_UNESCAPED_UNICODE), $usuarioAdminId);
        $this->auditoria->registrar($usuarioAdminId, 'GUARDAR_PLANTILLA_CONFIGURACION', 'configuracion_plantillas', $nombre, null, $valores);

        return (int) $this->repositorio->listarPlantillas()[0]['id'];
    }

    public function listarPlantillas(): array
    {
        return $this->repositorio->listarPlantillas();
    }

    /** Reutiliza actualizar() clave a clave: cada cambio queda igualmente registrado en auditoría (es lo que alimenta la pestaña Versionado). */
    public function aplicarPlantilla(int $plantillaId, int $usuarioAdminId): void
    {
        $plantilla = $this->repositorio->buscarPlantilla($plantillaId);
        if ($plantilla === null) {
            throw new DomainException('Esa plantilla ya no existe.');
        }

        $valores = json_decode((string) $plantilla['valores'], true) ?? [];
        foreach ($valores as $clave => $valor) {
            $this->actualizar((string) $clave, (string) $valor, $usuarioAdminId);
        }

        $this->auditoria->registrar($usuarioAdminId, 'APLICAR_PLANTILLA_CONFIGURACION', 'configuracion_plantillas', (string) $plantillaId, null, $valores);
    }

    public function eliminarPlantilla(int $id, int $usuarioAdminId): void
    {
        $plantilla = $this->repositorio->buscarPlantilla($id);
        if ($plantilla === null) {
            throw new DomainException('Esa plantilla ya no existe.');
        }

        $this->repositorio->eliminarPlantilla($id);
        $this->auditoria->registrar($usuarioAdminId, 'ELIMINAR_PLANTILLA_CONFIGURACION', 'configuracion_plantillas', (string) $id, null, null);
    }

    private function requerir(string $clave): string
    {
        $valor = $this->repositorio->obtener($clave);

        if ($valor === null) {
            throw new DomainException("No existe el parámetro de configuración '{$clave}'.");
        }

        return $valor;
    }
}
