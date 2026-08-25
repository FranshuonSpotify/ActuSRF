<?php

declare(strict_types=1);

/** Anuncios programados (Fase 2, admin/Bandeja de Pendientes). Sin especificación previa — diseño propio. */
final class AnuncioEngine
{
    public function __construct(private AnuncioRepository $repo)
    {
    }

    public function crear(string $titulo, string $cuerpo, string $publicarEn, int $usuarioAdminId): void
    {
        $titulo = trim($titulo);
        $cuerpo = trim($cuerpo);
        if ($titulo === '' || $cuerpo === '') {
            throw new DomainException('El anuncio necesita título y cuerpo.');
        }

        $timestamp = strtotime($publicarEn);
        if ($timestamp === false) {
            throw new DomainException('La fecha de publicación no es válida.');
        }

        $this->repo->crear($titulo, $cuerpo, date('Y-m-d H:i:s', $timestamp), $usuarioAdminId);
    }

    public function listarProgramados(): array
    {
        return $this->repo->listarProgramados();
    }

    public function listarPublicados(int $limite = 20): array
    {
        return $this->repo->listarPublicados($limite);
    }

    public function eliminar(int $id): void
    {
        $this->repo->eliminar($id);
    }
}
