<?php

declare(strict_types=1);

final class TokenRecuperacionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function crear(int $usuarioId, string $tokenHash, string $expiraEn): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tokens_recuperacion_password (usuario_id, token_hash, expira_en) VALUES (?, ?, ?)'
        );
        $stmt->execute([$usuarioId, $tokenHash, $expiraEn]);
    }

    public function buscarValidoPorHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tokens_recuperacion_password WHERE token_hash = ? AND usado = 0 AND expira_en > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function marcarUsado(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE tokens_recuperacion_password SET usado = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Invalida cualquier otro token pendiente del mismo usuario al pedir uno nuevo o al usarlo. */
    public function invalidarPendientesDeUsuario(int $usuarioId): void
    {
        $stmt = $this->db->prepare('UPDATE tokens_recuperacion_password SET usado = 1 WHERE usuario_id = ? AND usado = 0');
        $stmt->execute([$usuarioId]);
    }
}
