<?php

declare(strict_types=1);

final class UsuarioRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT id, email, nombre, rol, estado, ultimo_acceso FROM usuarios ORDER BY nombre')->fetchAll();
    }

    public function crear(string $email, string $passwordHash, string $nombre, string $rol): int
    {
        $stmt = $this->db->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $passwordHash, $nombre, $rol]);

        return (int) $this->db->lastInsertId();
    }

    public function actualizarPassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public function invalidarSesion(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET sesion_invalidada_desde = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function registrarAcceso(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }
}
