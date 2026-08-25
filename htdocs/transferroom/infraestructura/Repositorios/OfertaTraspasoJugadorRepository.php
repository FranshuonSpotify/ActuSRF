<?php

declare(strict_types=1);

/**
 * Jugadores del comprador incluidos en una oferta de traspaso, además del
 * dinero (o en vez de él). Anexo de `ofertas_traspaso`, nunca una oferta por
 * sí misma — ver migración 035.
 */
final class OfertaTraspasoJugadorRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function agregar(int $ofertaTraspasoId, int $jugadorId, int $contratoId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ofertas_traspaso_jugadores (oferta_traspaso_id, jugador_id, contrato_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ofertaTraspasoId, $jugadorId, $contratoId]);
    }

    /** @return list<array{id:int,oferta_traspaso_id:int,jugador_id:int,contrato_id:int,jugador_nombre:string,posicion:string,foto_url:?string}> */
    public function listarPorOferta(int $ofertaTraspasoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT otj.*, j.nombre AS jugador_nombre, j.posicion, j.foto_url
             FROM ofertas_traspaso_jugadores otj
             JOIN jugadores j ON j.id = otj.jugador_id
             WHERE otj.oferta_traspaso_id = ?
             ORDER BY otj.id ASC'
        );
        $stmt->execute([$ofertaTraspasoId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,list<array>> ofertas_traspaso_id => filas, para no hacer una consulta por fila al listar varias ofertas. */
    public function listarPorOfertas(array $ofertaTraspasoIds): array
    {
        if ($ofertaTraspasoIds === []) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ofertaTraspasoIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT otj.*, j.nombre AS jugador_nombre, j.posicion, j.foto_url
             FROM ofertas_traspaso_jugadores otj
             JOIN jugadores j ON j.id = otj.jugador_id
             WHERE otj.oferta_traspaso_id IN ($marcadores)
             ORDER BY otj.id ASC"
        );
        $stmt->execute(array_values($ofertaTraspasoIds));

        $porOferta = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porOferta[(int) $fila['oferta_traspaso_id']][] = $fila;
        }

        return $porOferta;
    }
}
