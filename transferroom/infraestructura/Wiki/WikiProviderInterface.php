<?php

declare(strict_types=1);

/**
 * Contrato mínimo que WikiResolverEngine necesita de un proveedor de wiki.
 * Única interfaz de todo el proyecto (el resto usa clases concretas
 * directamente) — se justifica aquí porque la spec exige poder mockear el
 * proveedor externo en tests sin red real (§41), y `FandomWikiProvider` es
 * `final` a propósito en todo lo demás.
 */
interface WikiProviderInterface
{
    /** @return array{title:string,viaRedirect:bool}|null */
    public function resolverTituloDirecto(string $nombre): ?array;

    /** @return list<string> */
    public function buscarCandidatos(string $nombre, int $limite = 8): array;

    /** @return array{title:string,dubName:?string,position:?string,element:?string}|null */
    public function obtenerFicha(string $titulo): ?array;

    /** @return array{title:string,viaRedirect:bool}|null título en inglés, resuelto vía la wiki en español + langlink editorial */
    public function resolverViaEspanol(string $nombre): ?array;

    public function urlPagina(string $titulo): string;
}
