<?php

declare(strict_types=1);

final class NotificacionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(int $usuarioId, string $tipo, string $mensaje, ?string $enlace): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notificaciones (usuario_id, tipo, mensaje, enlace) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$usuarioId, $tipo, $mensaje, $enlace]);

        return (int) $this->db->lastInsertId();
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0');
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    public function listarPorUsuario(int $usuarioId, int $limite = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT ?'
        );
        $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Notificaciones nuevas desde el último id visto por el cliente (sondeo para avisos del navegador). */
    public function listarNoLeidasDesde(int $usuarioId, int $desdeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notificaciones WHERE usuario_id = ? AND id > ? ORDER BY id ASC LIMIT 20'
        );
        $stmt->execute([$usuarioId, $desdeId]);

        return $stmt->fetchAll();
    }

    public function marcarLeida(int $id, int $usuarioId): void
    {
        $stmt = $this->db->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $usuarioId]);
    }

    public function marcarTodasLeidas(int $usuarioId): void
    {
        $stmt = $this->db->prepare('UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0');
        $stmt->execute([$usuarioId]);
    }
}
