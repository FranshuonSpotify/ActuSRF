<?php

declare(strict_types=1);

final class PreferenciaRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string,string> clave => valor */
    public function listarPorUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare('SELECT clave, valor FROM usuario_preferencias WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);

        return array_column($stmt->fetchAll(), 'valor', 'clave');
    }

    public function obtener(int $usuarioId, string $clave): ?string
    {
        $stmt = $this->db->prepare('SELECT valor FROM usuario_preferencias WHERE usuario_id = ? AND clave = ?');
        $stmt->execute([$usuarioId, $clave]);
        $valor = $stmt->fetchColumn();

        return $valor !== false ? (string) $valor : null;
    }

    public function establecer(int $usuarioId, string $clave, string $valor): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuario_preferencias (usuario_id, clave, valor) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute([$usuarioId, $clave, $valor]);
    }
}
