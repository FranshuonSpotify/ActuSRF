<?php

declare(strict_types=1);

/**
 * Única pieza del sistema que sabe hablar con Fandom. El resto de la
 * aplicación (WikiResolverEngine, JugadorEngine, las páginas públicas) no
 * conoce la API de MediaWiki ni la estructura de la wiki — solo recibe
 * páginas candidatas y fichas ya normalizadas.
 *
 * Preferencia (§27 de la especificación): API estructurada de MediaWiki
 * (acción=query), nunca scraping HTML frágil. Host fijo, sin URL construida
 * a partir de datos externos: elimina SSRF/open-redirect por diseño.
 */
final class FandomWikiProvider implements WikiProviderInterface
{
    private const HOST = 'inazuma-eleven.fandom.com';
    private const API_URL = 'https://inazuma-eleven.fandom.com/api.php';

    /**
     * Wiki hermana en español (`inazuma.fandom.com/es`), wiki de Fandom
     * distinta a la principal en inglés. Muchos jugadores de esta liga
     * fan-game usan directamente el nombre del doblaje latinoamericano
     * ("Juana de Arco", "Sael"...), que no existe como dato en la wiki en
     * inglés (solo guarda el "Dub name" en inglés). La wiki en español SÍ
     * tiene esas páginas, tituladas tal cual con el nombre en español, y
     * cada página trae un `langlinks` editorial hacia la página en inglés
     * equivalente — es un cruce de datos curado por editores humanos, igual
     * de fiable que un redirect, nunca una traducción automática nuestra.
     */
    private const HOST_ES = 'inazuma.fandom.com';
    private const API_URL_ES = 'https://inazuma.fandom.com/es/api.php';

    private int $timeoutSegundos;
    private int $maxReintentos;

    public function __construct()
    {
        $this->timeoutSegundos = (int) (getenv('INAZUMA_WIKI_REQUEST_TIMEOUT') ?: 8);
        $this->maxReintentos = (int) (getenv('INAZUMA_WIKI_MAX_RETRIES') ?: 2);
    }

    /**
     * Comprueba si `$nombre` es directamente un título de página, o un
     * redirect editorial hacia uno (p. ej. "Mark Evans" -> "Endou Mamoru").
     * Un redirect en esta wiki es un alias curado a mano por editores
     * humanos: evidencia muy fuerte, sin necesidad de traducir nada.
     *
     * @return array{title:string,viaRedirect:bool}|null
     */
    public function resolverTituloDirecto(string $nombre): ?array
    {
        $datos = $this->peticion([
            'action' => 'query',
            'titles' => $nombre,
            'redirects' => '1',
            'format' => 'json',
        ]);

        if ($datos === null) {
            return null;
        }

        $paginas = $datos['query']['pages'] ?? [];
        foreach ($paginas as $pagina) {
            if (isset($pagina['missing'])) {
                continue;
            }
            $viaRedirect = !empty($datos['query']['redirects']);

            return ['title' => (string) $pagina['title'], 'viaRedirect' => $viaRedirect];
        }

        return null;
    }

    /** @return list<string> títulos candidatos, por relevancia de búsqueda de texto completo. */
    public function buscarCandidatos(string $nombre, int $limite = 8): array
    {
        $datos = $this->peticion([
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $nombre,
            'srlimit' => (string) $limite,
            'format' => 'json',
        ]);

        if ($datos === null) {
            return [];
        }

        return array_map(
            static fn (array $r): string => (string) $r['title'],
            $datos['query']['search'] ?? []
        );
    }

    /**
     * Ficha estructurada de una página: nombre localizado/doblado, y
     * posición/elemento cuando el infobox los expone (evidencia auxiliar).
     * Nunca lanza por una página sin infobox: los datos que falten quedan a
     * null y el resolver simplemente no puntúa ese criterio (§14/§15).
     *
     * @return array{title:string,dubName:?string,position:?string,element:?string}|null
     */
    public function obtenerFicha(string $titulo): ?array
    {
        $datos = $this->peticion([
            'action' => 'query',
            'titles' => $titulo,
            'prop' => 'pageprops',
            'format' => 'json',
        ]);

        if ($datos === null) {
            return null;
        }

        $paginas = $datos['query']['pages'] ?? [];
        foreach ($paginas as $pagina) {
            if (isset($pagina['missing'])) {
                continue;
            }

            $infoboxJson = $pagina['pageprops']['infoboxes'] ?? null;
            [$dubName, $posicion, $elemento] = $infoboxJson !== null
                ? $this->extraerCamposInfobox($infoboxJson)
                : [null, null, null];

            return [
                'title' => (string) $pagina['title'],
                'dubName' => $dubName,
                'position' => $posicion,
                'element' => $elemento,
            ];
        }

        return null;
    }

    /**
     * Fallback vía wiki en español (ver comentario de HOST_ES arriba).
     * Solo devuelve resultado si la página existe en español Y tiene un
     * langlink editorial "en" hacia la wiki principal — si existe la página
     * en español pero sin cruce a inglés, no hay forma segura de construir
     * la URL final (que siempre vive en inazuma-eleven.fandom.com), así que
     * se trata igual que "no encontrado".
     *
     * @return array{title:string,viaRedirect:bool}|null título en INGLÉS, listo para el resto del pipeline
     */
    public function resolverViaEspanol(string $nombre): ?array
    {
        $datos = $this->peticion([
            'action' => 'query',
            'titles' => $nombre,
            'redirects' => '1',
            'prop' => 'langlinks',
            'lllang' => 'en',
            'format' => 'json',
        ], self::API_URL_ES, self::HOST_ES);

        if ($datos === null) {
            return null;
        }

        $paginas = $datos['query']['pages'] ?? [];
        foreach ($paginas as $pagina) {
            if (isset($pagina['missing'])) {
                continue;
            }

            $tituloIngles = $pagina['langlinks'][0]['*'] ?? null;
            if ($tituloIngles === null) {
                return null; // página en español sin cruce a inglés: no se puede construir la URL final
            }

            return [
                'title' => (string) $tituloIngles,
                'viaRedirect' => !empty($datos['query']['redirects']),
            ];
        }

        return null;
    }

    public function urlPagina(string $titulo): string
    {
        $base = rtrim(getenv('INAZUMA_WIKI_BASE_URL') ?: 'https://inazuma-eleven.fandom.com/wiki/', '/');

        return $base . '/' . rawurlencode(str_replace(' ', '_', $titulo));
    }

    /**
     * Extracción best-effort de "Dub name"/"Position"/"Element" del JSON de
     * PortableInfobox. No es un parser completo del infobox (sería
     * sobre-ingeniería para 3 campos) — recorre los grupos de datos
     * buscando por `source`, que es estable entre plantillas de personaje.
     * ponytail: si Fandom cambia el `source` de estos campos, esto deja de
     * encontrar el dato y el criterio simplemente no puntúa (no rompe nada).
     *
     * @return array{0:?string,1:?string,2:?string}
     */
    private function extraerCamposInfobox(string $infoboxJson): array
    {
        $infoboxes = json_decode($infoboxJson, true);
        if (!is_array($infoboxes)) {
            return [null, null, null];
        }

        $dubName = null;
        $posicion = null;
        $elemento = null;

        $visitar = function ($nodo) use (&$visitar, &$dubName, &$posicion, &$elemento): void {
            if (!is_array($nodo)) {
                return;
            }

            $source = strtolower((string) ($nodo['source'] ?? ''));
            $label = strtolower((string) ($nodo['label'] ?? ''));
            $valor = $nodo['value'] ?? null;

            if (is_string($valor)) {
                if ($dubName === null && ($source === 'name_dub' || str_contains($label, 'dub name'))) {
                    $dubName = trim($valor);
                }
                if ($posicion === null && ($source === 'position' || $label === 'position')) {
                    $posicion = trim(strip_tags($valor));
                }
                if ($elemento === null && ($source === 'element' || $source === 'attribute' || in_array($label, ['element', 'attribute'], true))) {
                    $elemento = trim(strip_tags($valor));
                }
            }

            foreach ($nodo as $hijo) {
                if (is_array($hijo)) {
                    $visitar($hijo);
                }
            }
        };

        foreach ($infoboxes as $infobox) {
            $visitar($infobox['data'] ?? []);
        }

        return [$dubName, $posicion, $elemento];
    }

    /** @return array<string,mixed>|null null si la petición falla tras reintentos (nunca lanza). */
    private function peticion(array $parametros, string $apiUrl = self::API_URL, string $hostEsperado = self::HOST): ?array
    {
        $url = $apiUrl . '?' . http_build_query($parametros);

        // Cinturón de seguridad SSRF: la URL siempre viene de una de las dos
        // constantes de arriba (nunca de datos externos), pero se verifica
        // explícitamente el host antes de cada petición saliente real (§37).
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== $hostEsperado) {
            return null;
        }

        $intento = 0;
        while (true) {
            $intento++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeoutSegundos,
                CURLOPT_CONNECTTIMEOUT => $this->timeoutSegundos,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'TransferRoomWikiResolver/1.0 (+https://github.com; contacto interno)',
            ]);
            $respuesta = curl_exec($ch);
            $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            $transitorio = $errno !== 0 || $codigoHttp >= 500;
            if (!$transitorio || $intento > $this->maxReintentos) {
                if ($errno !== 0 || $codigoHttp !== 200) {
                    error_log(sprintf('[wiki] peticion fallida (%s): errno=%d error=%s http=%d', $url, $errno, $error, $codigoHttp));

                    return null;
                }
                break;
            }

            usleep(300000 * $intento); // backoff simple: 300ms, 600ms...
        }

        $decodificado = json_decode((string) $respuesta, true);

        return is_array($decodificado) ? $decodificado : null;
    }
}
