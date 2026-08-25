<?php

declare(strict_types=1);

final class MensajeRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(int $conversacionId, int $remitenteId, string $cuerpo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO mensajes (conversacion_id, remitente_id, cuerpo) VALUES (?, ?, ?)'
        );
        $stmt->execute([$conversacionId, $remitenteId, $cuerpo]);

        return (int) $this->db->lastInsertId();
    }

    public function listarPorConversacion(int $conversacionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.nombre AS remitente_nombre, u.rol AS remitente_rol
             FROM mensajes m JOIN usuarios u ON u.id = m.remitente_id
             WHERE m.conversacion_id = ? ORDER BY m.creado_en ASC'
        );
        $stmt->execute([$conversacionId]);

        return $stmt->fetchAll();
    }

    /** Marca leídos los mensajes de la conversación que NO envió $usuarioId (los suyos ya están "leídos" por definición). */
    public function marcarLeidosParaUsuario(int $conversacionId, int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE mensajes SET leido = 1 WHERE conversacion_id = ? AND remitente_id != ? AND leido = 0'
        );
        $stmt->execute([$conversacionId, $usuarioId]);
    }

    /** No leídos en conversaciones ENTRE_PRESIDENTES o CON_ADMINISTRACION donde $usuarioId es el presidente iniciador. */
    public function contarNoLeidosDePresidente(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM mensajes m
             JOIN conversaciones c ON c.id = m.conversacion_id
             WHERE m.leido = 0 AND m.remitente_id != ?
               AND (c.usuario_iniciador_id = ? OR c.usuario_contraparte_id = ?)"
        );
        $stmt->execute([$usuarioId, $usuarioId, $usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    /** No leídos en TODAS las conversaciones CON_ADMINISTRACION (bandeja compartida de todo el equipo de admins). */
    public function contarNoLeidosParaAdministracion(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM mensajes m
             JOIN conversaciones c ON c.id = m.conversacion_id
             WHERE c.tipo = 'CON_ADMINISTRACION' AND m.leido = 0 AND m.remitente_id = c.usuario_iniciador_id"
        );

        return (int) $stmt->fetchColumn();
    }
}
