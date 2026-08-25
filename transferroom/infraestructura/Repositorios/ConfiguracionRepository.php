<?php

declare(strict_types=1);

final class ConfiguracionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function obtener(string $clave): ?string
    {
        $stmt = $this->db->prepare('SELECT valor FROM configuracion WHERE clave = ? LIMIT 1');
        $stmt->execute([$clave]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : $valor;
    }

    public function listarTodas(): array
    {
        return $this->db->query('SELECT * FROM configuracion ORDER BY categoria, clave')->fetchAll();
    }

    public function actualizar(string $clave, string $valor): void
    {
        $stmt = $this->db->prepare('UPDATE configuracion SET valor = ? WHERE clave = ? AND editable = 1');
        $stmt->execute([$valor, $clave]);
    }

    public function listarPlantillas(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, u.nombre AS creado_por_nombre
             FROM configuracion_plantillas p
             LEFT JOIN usuarios u ON u.id = p.creado_por
             ORDER BY p.creado_en DESC'
        );

        return $stmt->fetchAll();
    }

    public function crearPlantilla(string $nombre, string $valoresJson, int $creadoPor): void
    {
        $stmt = $this->db->prepare('INSERT INTO configuracion_plantillas (nombre, valores, creado_por) VALUES (?, ?, ?)');
        $stmt->execute([$nombre, $valoresJson, $creadoPor]);
    }

    public function buscarPlantilla(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM configuracion_plantillas WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function eliminarPlantilla(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM configuracion_plantillas WHERE id = ?');
        $stmt->execute([$id]);
    }
}
