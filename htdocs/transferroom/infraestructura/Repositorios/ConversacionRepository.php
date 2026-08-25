<?php

declare(strict_types=1);

final class ConversacionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function buscarConAdministracion(int $usuarioIniciadorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM conversaciones WHERE tipo = 'CON_ADMINISTRACION' AND usuario_iniciador_id = ? LIMIT 1"
        );
        $stmt->execute([$usuarioIniciadorId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function crearConAdministracion(int $usuarioIniciadorId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO conversaciones (tipo, usuario_iniciador_id, usuario_contraparte_id) VALUES ('CON_ADMINISTRACION', ?, NULL)"
        );
        $stmt->execute([$usuarioIniciadorId]);

        return (int) $this->db->lastInsertId();
    }

    /** $usuarioMenorId y $usuarioMayorId ya vienen normalizados (menor id primero) por el motor. */
    public function buscarEntrePresidentes(int $usuarioMenorId, int $usuarioMayorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM conversaciones WHERE tipo = 'ENTRE_PRESIDENTES' AND usuario_iniciador_id = ? AND usuario_contraparte_id = ? LIMIT 1"
        );
        $stmt->execute([$usuarioMenorId, $usuarioMayorId]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function crearEntrePresidentes(int $usuarioMenorId, int $usuarioMayorId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO conversaciones (tipo, usuario_iniciador_id, usuario_contraparte_id) VALUES ('ENTRE_PRESIDENTES', ?, ?)"
        );
        $stmt->execute([$usuarioMenorId, $usuarioMayorId]);

        return (int) $this->db->lastInsertId();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM conversaciones WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Conversaciones ENTRE_PRESIDENTES de un usuario, con nombre de la contraparte y resumen del último mensaje. */
    public function listarEntrePresidentesDeUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*,
                    IF(c.usuario_iniciador_id = ?, c.usuario_contraparte_id, c.usuario_iniciador_id) AS contraparte_id,
                    u.nombre AS contraparte_nombre,
                    (SELECT cuerpo FROM mensajes WHERE conversacion_id = c.id ORDER BY creado_en DESC LIMIT 1) AS ultimo_mensaje,
                    (SELECT creado_en FROM mensajes WHERE conversacion_id = c.id ORDER BY creado_en DESC LIMIT 1) AS ultimo_mensaje_en,
                    (SELECT COUNT(*) FROM mensajes WHERE conversacion_id = c.id AND leido = 0 AND remitente_id != ?) AS no_leidos
             FROM conversaciones c
             JOIN usuarios u ON u.id = IF(c.usuario_iniciador_id = ?, c.usuario_contraparte_id, c.usuario_iniciador_id)
             WHERE c.tipo = 'ENTRE_PRESIDENTES' AND (c.usuario_iniciador_id = ? OR c.usuario_contraparte_id = ?)
             ORDER BY ultimo_mensaje_en IS NULL, ultimo_mensaje_en DESC"
        );
        $stmt->execute([$usuarioId, $usuarioId, $usuarioId, $usuarioId, $usuarioId]);

        return $stmt->fetchAll();
    }

    /** Todas las conversaciones CON_ADMINISTRACION que ya tienen al menos un mensaje (bandeja del equipo de admins). */
    public function listarConAdministracionConMensajes(): array
    {
        return $this->db->query(
            "SELECT c.*, u.nombre AS presidente_nombre,
                    (SELECT cuerpo FROM mensajes WHERE conversacion_id = c.id ORDER BY creado_en DESC LIMIT 1) AS ultimo_mensaje,
                    (SELECT creado_en FROM mensajes WHERE conversacion_id = c.id ORDER BY creado_en DESC LIMIT 1) AS ultimo_mensaje_en,
                    (SELECT COUNT(*) FROM mensajes WHERE conversacion_id = c.id AND leido = 0 AND remitente_id = c.usuario_iniciador_id) AS no_leidos
             FROM conversaciones c
             JOIN usuarios u ON u.id = c.usuario_iniciador_id
             WHERE c.tipo = 'CON_ADMINISTRACION'
               AND EXISTS (SELECT 1 FROM mensajes WHERE conversacion_id = c.id)
             ORDER BY ultimo_mensaje_en DESC"
        )->fetchAll();
    }
}
