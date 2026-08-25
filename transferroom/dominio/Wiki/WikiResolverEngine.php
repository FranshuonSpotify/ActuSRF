<?php

declare(strict_types=1);

/**
 * Resuelve automáticamente la página de Fandom de un jugador a partir de su
 * nombre (ESPECIFICACION_CLAUDE_WIKI_INAZUMA.md). No traduce nada: busca
 * candidatos reales en la wiki y puntúa la evidencia (§3, §12, §13).
 *
 * Responsabilidad única (§48): esta clase no sabe renderizar nada ni cómo
 * funciona la base de datos de jugadores — solo decide, dado un nombre y
 * evidencia auxiliar, cuál es la página correcta (o si no hay suficiente
 * certeza). Quien la usa decide qué hacer con el resultado.
 */
final class WikiResolverEngine
{
    public const ESTADO_MATCHED = 'matched';
    public const ESTADO_NEEDS_REVIEW = 'needs_review';
    public const ESTADO_NOT_FOUND = 'not_found';
    public const ESTADO_ERROR = 'error';
    public const ESTADO_MANUAL = 'manual';
    public const ESTADO_PENDING = 'pending';

    /** Estos dos estados nunca se sobrescriben por una resolución automática (§43, §44). */
    private const ESTADOS_PROTEGIDOS = [self::ESTADO_MATCHED, self::ESTADO_MANUAL];

    private const MAPA_POSICION = [
        'POR' => 'goalkeeper',
        'DEF' => 'defender',
        'MED' => 'midfielder',
        'DEL' => 'forward',
    ];

    /** Elementos/afinidades conocidas de la web -> término inglés usado en Fandom. */
    private const MAPA_ELEMENTO = [
        'bosque' => 'wood',
        'fuego' => 'fire',
        'montaña' => 'mountain',
        'montana' => 'mountain',
        'aire' => 'wind',
        'viento' => 'wind',
        'tierra' => 'earth',
    ];

    private float $umbralAutoMatch;
    private float $umbralRevision;
    private float $margenMinimo;

    public function __construct(private WikiProviderInterface $provider)
    {
        $this->umbralAutoMatch = (float) (getenv('INAZUMA_WIKI_AUTO_MATCH_THRESHOLD') ?: 0.90);
        $this->umbralRevision = (float) (getenv('INAZUMA_WIKI_REVIEW_THRESHOLD') ?: 0.60);
        $this->margenMinimo = (float) (getenv('INAZUMA_WIKI_MIN_SCORE_MARGIN') ?: 0.05);
    }

    /** Para construir la URL final de un título confirmado a mano en el panel admin (§30). */
    public function provider(): WikiProviderInterface
    {
        return $this->provider;
    }

    /**
     * Diacríticos latinos habituales en nombres de esta liga (español,
     * portugués, francés). Tabla propia en vez de `iconv(...,'//TRANSLIT',...)`:
     * en el PHP de este entorno (Windows) TRANSLIT no limpia el acento, lo
     * sustituye por un apóstrofo suelto ("García" -> "Garc'ia"), lo que rompía
     * silenciosamente toda comparación con nombres acentuados — bug real
     * encontrado al revisar por qué "Gabriel García" no encontraba a
     * "Kirino Ranmaru" (dub name real: "Gabriel Garcia") a pesar de que el
     * candidato correcto sí estaba en los resultados de búsqueda.
     */
    private const MAPA_DIACRITICOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /** Comparación sin alterar el nombre mostrado al usuario (§11). */
    public static function normalizar(string $nombre): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $nombre) ?? $nombre);
        $limpio = mb_strtolower($limpio, 'UTF-8');

        return strtr($limpio, self::MAPA_DIACRITICOS);
    }

    /**
     * @param array{nombre:string,posicion:?string,afinidad_nombre:?string,wiki_status:?string} $jugador
     * @return array{status:string,provider:?string,title:?string,url:?string,confidence:?float,reason:?string,error:?string}
     */
    public function resolver(array $jugador, bool $forzar = false): array
    {
        $estadoActual = $jugador['wiki_status'] ?? self::ESTADO_PENDING;
        if (!$forzar && in_array($estadoActual, self::ESTADOS_PROTEGIDOS, true)) {
            return $this->resultado($estadoActual, null, null, null, null, 'ya_resuelto_sin_forzar');
        }

        $nombre = trim($jugador['nombre'] ?? '');
        if ($nombre === '') {
            return $this->resultado(self::ESTADO_ERROR, null, null, null, null, null, 'El jugador no tiene nombre.');
        }

        try {
            $candidatos = $this->reunirCandidatos($nombre, $jugador['posicion'] ?? null, $jugador['afinidad_nombre'] ?? null);
        } catch (Throwable $e) {
            error_log('[wiki] error de resolución: ' . $e->getMessage());

            return $this->resultado(self::ESTADO_ERROR, null, null, null, null, null, $e->getMessage());
        }

        if ($candidatos === []) {
            return $this->resultado(self::ESTADO_NOT_FOUND, 'fandom_inazuma', null, null, null, 'sin_candidatos');
        }

        usort($candidatos, static fn (array $a, array $b): int => $b['puntuacion'] <=> $a['puntuacion']);
        $mejor = $candidatos[0];
        $segundo = $candidatos[1] ?? null;

        $confianza = min($mejor['puntuacion'] / 100.0, 1.0);
        $margenSuficiente = $segundo === null || ($mejor['puntuacion'] - $segundo['puntuacion']) / 100.0 >= $this->margenMinimo;

        if ($confianza >= $this->umbralAutoMatch && $margenSuficiente) {
            $estado = self::ESTADO_MATCHED;
        } elseif ($confianza >= $this->umbralRevision) {
            $estado = self::ESTADO_NEEDS_REVIEW;
        } else {
            $estado = self::ESTADO_NOT_FOUND;
        }

        $titulo = $estado === self::ESTADO_NOT_FOUND ? null : $mejor['titulo'];
        $url = $titulo !== null ? $this->provider->urlPagina($titulo) : null;

        return $this->resultado($estado, 'fandom_inazuma', $titulo, $url, round($confianza, 3), $mejor['razon']);
    }

    /** @return list<array{titulo:string,puntuacion:float,razon:string}> */
    private function reunirCandidatos(string $nombre, ?string $posicionWeb, ?string $elementoWeb): array
    {
        $candidatos = [];

        // Camino barato primero (§1 de la escalera): si "Mark Evans" es
        // exactamente un título, o un redirect editorial hacia uno, ya es
        // evidencia muy fuerte sin gastar una búsqueda de texto completo.
        $directo = $this->provider->resolverTituloDirecto($nombre);
        if ($directo !== null) {
            $ficha = $this->provider->obtenerFicha($directo['title']);
            $puntuacion = $directo['viaRedirect'] ? 95.0 : 90.0;
            $razon = $directo['viaRedirect'] ? 'redirect_alias_match' : 'exact_title_match';

            if ($ficha !== null && $ficha['dubName'] !== null
                && self::normalizar($ficha['dubName']) === self::normalizar($nombre)) {
                $puntuacion = 100.0;
                $razon = 'exact_localized_name_match';
            }

            if ($ficha !== null) {
                $puntuacion += self::bonoEvidenciaAuxiliar($posicionWeb, $ficha['position'], $elementoWeb, $ficha['element']);
            }

            $candidatos[] = [
                'titulo' => $directo['title'],
                'puntuacion' => $puntuacion,
                'razon' => $razon,
            ];

            // Un redirect/título directo ya es concluyente; no hace falta
            // gastar más peticiones en buscar alternativas peores.
            return $candidatos;
        }

        // Segundo camino barato: muchos jugadores de esta liga usan el
        // nombre del doblaje latinoamericano tal cual (p. ej. "Juana de
        // Arco", "Sael"), que no existe como dato en la wiki en inglés — solo
        // guarda el "Dub name" en inglés. La wiki en español SÍ tiene esas
        // páginas, y cruza a la página en inglés vía un langlink editorial:
        // mismo nivel de fiabilidad que un redirect, cero traducción nuestra.
        $viaEspanol = $this->provider->resolverViaEspanol($nombre);
        if ($viaEspanol !== null) {
            $ficha = $this->provider->obtenerFicha($viaEspanol['title']);
            $puntuacion = 97.0; // coincidencia exacta de título en ES + langlink editorial: más fiable que un redirect en inglés
            if ($ficha !== null) {
                $puntuacion += self::bonoEvidenciaAuxiliar($posicionWeb, $ficha['position'], $elementoWeb, $ficha['element']);
            }

            return [[
                'titulo' => $viaEspanol['title'],
                'puntuacion' => $puntuacion,
                'razon' => 'spanish_wiki_langlink_match',
            ]];
        }

        foreach ($this->provider->buscarCandidatos($nombre) as $tituloCandidato) {
            $ficha = $this->provider->obtenerFicha($tituloCandidato);
            if ($ficha === null) {
                continue;
            }

            $puntuacion = 0.0;
            $razon = 'sin_evidencia_directa';

            if ($ficha['dubName'] !== null && self::normalizar($ficha['dubName']) === self::normalizar($nombre)) {
                $puntuacion = 100.0;
                $razon = 'exact_localized_name_match';
            } elseif (self::normalizar($ficha['title']) === self::normalizar($nombre)) {
                $puntuacion = 75.0;
                $razon = 'exact_title_match';
            } else {
                similar_text(self::normalizar($ficha['title']), self::normalizar($nombre), $porcentaje);
                if ($porcentaje >= 85.0) {
                    $puntuacion = 50.0;
                    $razon = 'fuzzy_name_match';
                }
            }

            if ($puntuacion === 0.0) {
                continue; // ni rastro de evidencia directa: no es candidato real
            }

            $puntuacion += self::bonoEvidenciaAuxiliar($posicionWeb, $ficha['position'], $elementoWeb, $ficha['element']);

            $candidatos[] = [
                'titulo' => $ficha['title'],
                'puntuacion' => $puntuacion,
                'razon' => $razon,
            ];
        }

        return $candidatos;
    }

    /**
     * Evidencia auxiliar (§13/§14/§15): solo suma si hay dato comparable en
     * ambos lados. Nunca penaliza una coincidencia por falta de dato.
     */
    public static function bonoEvidenciaAuxiliar(?string $posicionWeb, ?string $posicionWiki, ?string $elementoWeb, ?string $elementoWiki): float
    {
        $bono = 0.0;

        if ($posicionWeb !== null && $posicionWiki !== null) {
            $esperado = self::MAPA_POSICION[strtoupper($posicionWeb)] ?? null;
            if ($esperado !== null && str_contains(strtolower($posicionWiki), $esperado)) {
                $bono += 20.0;
            }
        }

        if ($elementoWeb !== null && $elementoWiki !== null) {
            $esperado = self::MAPA_ELEMENTO[mb_strtolower($elementoWeb, 'UTF-8')] ?? null;
            if ($esperado !== null && str_contains(strtolower($elementoWiki), $esperado)) {
                $bono += 20.0;
            }
        }

        return $bono;
    }

    private function resultado(
        string $status,
        ?string $provider,
        ?string $title,
        ?string $url,
        ?float $confidence,
        ?string $reason,
        ?string $error = null
    ): array {
        return [
            'status' => $status,
            'provider' => $provider,
            'title' => $title,
            'url' => $url,
            'confidence' => $confidence,
            'reason' => $reason,
            'error' => $error,
        ];
    }
}
