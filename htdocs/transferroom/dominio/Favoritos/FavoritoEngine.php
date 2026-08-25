<?php

declare(strict_types=1);

/** Favoritos del hub (Fase 2): sin reglas de negocio propias, solo pertenencia al usuario. */
final class FavoritoEngine
{
    public function __construct(private FavoritoRepository $favoritos)
    {
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        return $this->favoritos->listarPorUsuario($usuarioId);
    }

    public function alternar(int $usuarioId, string $ruta, string $etiqueta): bool
    {
        if ($this->favoritos->existe($usuarioId, $ruta)) {
            $this->favoritos->eliminar($usuarioId, $ruta);

            return false;
        }

        $this->favoritos->crear($usuarioId, $ruta, $etiqueta);

        return true;
    }
}
