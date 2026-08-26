<?php
declare(strict_types=1);

/* Reimplementación en PHP puro de las partes de _fuente/app.js que generan
   clasificación, resultados, goleadores y el cuadro de Copa — SOLO para el
   caso en que este hosting no tenga Node.js disponible (ver
   cron/reconstruir.php). Cuando SÍ hay Node, se usa _fuente/build.js tal
   cual, que es la fuente de verdad real; esto es la red de seguridad.

   Limitaciones deliberadas frente al pre-renderizador de Node (documentadas
   para quien retome esto): no traduce etiquetas ni traslitera nombres de
   equipo/jugador a otros alfabetos (ja/ko/bg/sr) — esos idiomas mostrarán el
   nombre en su forma original hasta el próximo "npm run build" en local. No
   toca el cuadro de Play Off ni la sección de noticias/equipos/plantillas.
   El fichero HTML de entrada ya tiene TODA la maquetación y las etiquetas
   traducidas de la última vez que build.js corrió en local: aquí solo se
   reemplaza el contenido de las tablas de datos, con DOMDocument, sin tocar
   nada más del documento. */

function sf_esc(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function sf_gl(array $p): int { return (int)($p['goles_l'] ?? $p['golesl'] ?? 0); }
function sf_gv(array $p): int { return (int)($p['goles_v'] ?? $p['golesv'] ?? 0); }
function sf_isFin(array $p): bool { return ($p['estado'] ?? '') === 'FINALIZADO'; }

function sf_isHttp(?string $u): bool { return is_string($u) && preg_match('#^https?://#', $u) === 1; }

function sf_abbr3(string $nombre, ?string $ab = null): string {
    if ($ab && trim($ab) !== '') return strtoupper(substr(trim($ab), 0, 3));
    $c = trim(preg_replace('/[^\p{L}\s]/u', '', $nombre) ?? '');
    if ($c === '') return '???';
    $w = preg_split('/\s+/', $c);
    if (count($w) >= 3) return strtoupper($w[0][0].$w[1][0].$w[2][0]);
    if (count($w) === 2) return strtoupper(substr($w[0], 0, 2).$w[1][0]);
    return strtoupper(substr($c, 0, 3));
}

/* El CSP del sitio solo permite img-src desde flagcdn.com, images.weserv.nl
   e i.imgur.com (ver .htaccess) — igual que normalizarUrlImagen() en app.js,
   cualquier otro host (wikia, cloudfront...) se reescribe para pasar por el
   proxy images.weserv.nl. Sin esto, escudos añadidos directamente en IONOS
   se quedarían bloqueados por el propio CSP del sitio. */
const SF_CSP_IMG_PERMITIDOS = ['images.weserv.nl', 'flagcdn.com', 'i.imgur.com'];
function sf_normalizarUrlImagen(string $u): string {
    $host = parse_url($u, PHP_URL_HOST);
    if ($host === null) return $u;
    if (in_array($host, SF_CSP_IMG_PERMITIDOS, true)) return $u;
    return 'https://images.weserv.nl/?url='.rawurlencode($u);
}

function sf_crest(?array $e): string {
    if ($e && sf_isHttp($e['escudo'] ?? null)) {
        return '<img src="'.sf_esc(sf_normalizarUrlImagen($e['escudo'])).'" alt="'.sf_esc($e['nombre'] ?? '').'" loading="lazy">';
    }
    $nombre = $e['nombre'] ?? '?';
    return '<span class="noimg" style="width:22px;height:22px;border-radius:6px;font-size:.55rem;flex-shrink:0">'.sf_esc(sf_abbr3($nombre)).'</span>';
}

function sf_divIcon(?array $e): string {
    if (!$e) return '';
    $asc = ($e['division'] ?? '') === 'ASCENSO';
    $nombre = $asc ? 'Ascenso Frontier' : 'Superliga Frontier';
    return '<img class="ms-div" src="assets/'.($asc ? 'af-icono.png' : 'sf-icono.png').'" alt="'.sf_esc($nombre).'" title="'.sf_esc($nombre).'" loading="lazy">';
}

/* ---------------------------- Clasificación ---------------------------- */

function sf_orderStandings(array $list): array {
    usort($list, function (array $a, array $b): int {
        $pa = (int)($a['pts'] ?? 0); $pb = (int)($b['pts'] ?? 0);
        if ($pb !== $pa) return $pb <=> $pa;
        $dA = (int)($a['gf'] ?? 0) - (int)($a['gc'] ?? 0);
        $dB = (int)($b['gf'] ?? 0) - (int)($b['gc'] ?? 0);
        if ($dB !== $dA) return $dB <=> $dA;
        if ((int)($b['gf'] ?? 0) !== (int)($a['gf'] ?? 0)) return (int)($b['gf'] ?? 0) <=> (int)($a['gf'] ?? 0);
        if ((int)($a['gc'] ?? 0) !== (int)($b['gc'] ?? 0)) return (int)($a['gc'] ?? 0) <=> (int)($b['gc'] ?? 0);
        if ((int)($b['g'] ?? 0) !== (int)($a['g'] ?? 0)) return (int)($b['g'] ?? 0) <=> (int)($a['g'] ?? 0);
        if ((int)($b['e'] ?? 0) !== (int)($a['e'] ?? 0)) return (int)($b['e'] ?? 0) <=> (int)($a['e'] ?? 0);
        if ((int)($a['p'] ?? 0) !== (int)($b['p'] ?? 0)) return (int)($a['p'] ?? 0) <=> (int)($b['p'] ?? 0);
        if ((int)($a['pj'] ?? 0) !== (int)($b['pj'] ?? 0)) return (int)($a['pj'] ?? 0) <=> (int)($b['pj'] ?? 0);
        return strcmp((string)($a['nombre'] ?? ''), (string)($b['nombre'] ?? ''));
    });
    return array_values($list);
}

function sf_formOf(array $matches, string $division, string $name, int $n): array {
    $ms = array_values(array_filter($matches, fn($p) => sf_isFin($p) && (($p['local'] ?? null) === $name || ($p['visitante'] ?? null) === $name)));
    usort($ms, fn($a, $b) => (int)($b['jornada'] ?? 0) <=> (int)($a['jornada'] ?? 0));
    $ms = array_slice($ms, 0, $n);
    $out = array_map(function ($p) use ($name) {
        $home = ($p['local'] ?? null) === $name;
        $f = $home ? sf_gl($p) : sf_gv($p);
        $c = $home ? sf_gv($p) : sf_gl($p);
        return $f > $c ? 'w' : ($f < $c ? 'l' : 'e');
    }, $ms);
    return array_reverse($out);
}

/* Devuelve el HTML de <tbody id="tbody-clas"> y <div id="legend-clas"> para
   una división, en el mismo formato que renderClas() de app.js. No calcula
   tendencia (▲/▼) respecto a la jornada anterior -- se deja el punto neutro
   "·", que es el estado por defecto y no rompe nada visualmente. */
function sf_renderClasBody(array $equipos, array $partidosLiga, string $div): string {
    $matches = $div === 'SUPERLIGA' ? $partidosLiga['liga'] : $partidosLiga['ascenso'];
    $list = array_values(array_filter($equipos, fn($e) => ($e['division'] ?? '') === $div && empty($e['archivado'])));
    $ord = sf_orderStandings($list);
    $total = count($ord);
    $h = '';
    foreach ($ord as $i => $e) {
        $pos = $i + 1;
        $dg = (int)($e['gf'] ?? 0) - (int)($e['gc'] ?? 0);
        $z = '';
        if ($div === 'SUPERLIGA') {
            if ($pos <= 3) $z = 'z-po'; elseif ($pos === 4) $z = 'z-pi'; elseif ($pos <= 6) $z = 'z-pp'; elseif ($pos > $total - 3) $z = 'z-desc';
        } elseif ($pos <= 3) $z = 'z-asc';
        $form = sf_formOf($matches, $div, (string)($e['nombre'] ?? ''), 5);
        $formHtml = implode('', array_map(fn($r) => '<i class="f-'.$r.'"></i>', $form));
        $h .= '<tr data-team="'.sf_esc($e['id'] ?? '').'">'
            .'<td class="pos"><span class="zone '.$z.'"></span>'.$pos.' <span class="tr-s">·</span></td>'
            .'<td><span class="tm">'.sf_crest($e).sf_esc($e['nombre'] ?? '').'</span></td>'
            .'<td class="mono">'.(int)($e['pj'] ?? 0).'</td>'
            .'<td class="mono hide-sm">'.(int)($e['g'] ?? 0).'</td><td class="mono hide-sm">'.(int)($e['e'] ?? 0).'</td><td class="mono hide-sm">'.(int)($e['p'] ?? 0).'</td>'
            .'<td class="mono hide-sm">'.(int)($e['gf'] ?? 0).'</td><td class="mono hide-sm">'.(int)($e['gc'] ?? 0).'</td>'
            .'<td class="mono hide-xs">'.($dg > 0 ? '+' : '').$dg.'</td>'
            .'<td class="pts">'.(int)($e['pts'] ?? 0).'</td>'
            .'<td class="hide-sm"><span class="frm">'.$formHtml.'</span></td>'
            .'</tr>';
    }
    return $h !== '' ? $h : '<tr><td colspan="11" style="padding:3rem;text-align:center;color:var(--ink-4)">Sin equipos.</td></tr>';
}

/* ---------------------------- Resultados ---------------------------- */

/* HTML de #matches para la última jornada de Liga (vista por defecto de
   Resultados) y el texto de #j-label a juego. */
function sf_renderMatchesLastJornada(array $partidosLiga, array $equiposPorNombre): array {
    $pool = $partidosLiga;
    $jornadas = array_values(array_unique(array_filter(array_map(fn($p) => $p['jornada'] ?? null, $pool), fn($j) => $j !== null && $j !== '')));
    sort($jornadas, SORT_NUMERIC);
    if (!$jornadas) return ['<p class="muted">Sin partidos en esta vista.</p>', 'Jornada ·'];
    $j = end($jornadas);
    $list = array_values(array_filter($pool, fn($p) => ($p['jornada'] ?? null) === $j));
    $esElim = count($list) > 0 && count(array_filter($list, fn($p) => !empty($p['fase']))) === count($list);
    $label = $esElim ? ($list[0]['fase'] ?? '') : ('Jornada '.$j);

    $html = implode('', array_map(function ($p) use ($pool, $equiposPorNombre) {
        $i = array_search($p, $pool, true);
        $a = sf_gl($p); $b = sf_gv($p); $pen = !sf_isFin($p);
        $row = function (?array $t, string $name, int $score, bool $lose) use ($pen) {
            $c1 = $t['color1'] ?? '#3A3A3A'; $c2 = $t['color2'] ?? '#141414';
            return '<div class="match-side '.($lose ? 'match-lose' : '').'">'
                .'<span class="ms-color" style="background:linear-gradient(180deg,'.sf_esc($c1).','.sf_esc($c2).')"></span>'
                .sf_crest($t)
                .'<span class="nm">'.sf_esc($name).'</span>'
                .($pen ? '' : '<span class="sc">'.$score.'</span>')
                .'</div>';
        };
        $tLocal = $equiposPorNombre[$p['local'] ?? ''] ?? null;
        $tVisitante = $equiposPorNombre[$p['visitante'] ?? ''] ?? null;
        return '<article class="card spotlight match" data-comp="liga" data-idx="'.$i.'">'
            .'<div class="match-top"><span class="badge badge-superliga">Superliga Frontier</span>'.($pen ? '<span class="match-vs">VS</span>' : '').'</div>'
            .$row($tLocal, (string)($p['local'] ?? ''), $a, !$pen && $a < $b)
            .$row($tVisitante, (string)($p['visitante'] ?? ''), $b, !$pen && $b < $a)
            .'<div class="match-foot"><span data-no-tr>'.($p['fase'] ? sf_esc($p['fase']) : ('Jornada '.sf_esc((string)($p['jornada'] ?? '')))).'</span><span>'.($pen ? 'Pendiente' : 'Finalizado').'</span></div>'
            .'</article>';
    }, $list));
    return [$html, $label];
}

/* ---------------------------- Copa ---------------------------- */

function sf_winnerOf(array $p): ?string {
    if (!sf_isFin($p)) return null;
    $a = sf_gl($p); $b = sf_gv($p);
    if ($a > $b) return $p['local'] ?? null;
    if ($b > $a) return $p['visitante'] ?? null;
    if (preg_match('/PEN[: ]?\s*(\d+)\s*-\s*(\d+)/i', $p['detalles'] ?? '', $m)) {
        return ((int)$m[1] > (int)$m[2]) ? ($p['local'] ?? null) : ($p['visitante'] ?? null);
    }
    return null;
}

function sf_resolveSide(array $p, string $side, array $partidosCopa): array {
    $ok = $side === 'local' ? 'origen_local' : 'origen_visitante';
    $idx = $p[$ok] ?? null;
    if ($idx !== null && isset($partidosCopa[$idx])) {
        $f = $partidosCopa[$idx];
        $w = sf_winnerOf($f);
        if ($w) return ['n' => $w, 'pend' => false];
        return ['n' => sf_abbr3((string)($f['local'] ?? '')).' / '.sf_abbr3((string)($f['visitante'] ?? '')), 'pend' => true];
    }
    return ['n' => $p[$side] ?? '', 'pend' => false];
}

const SF_FASES = ['RONDA 1 (PREVIA)', 'RONDA 2', 'CUARTOS DE FINAL', 'SEMIFINALES', 'FINAL'];
const SF_FASE_LABEL = [
    'RONDA 1 (PREVIA)' => 'Ronda 1 (previa)', 'RONDA 2' => 'Ronda 2', 'CUARTOS DE FINAL' => 'Cuartos de final',
    'SEMIFINALES' => 'Semifinales', 'FINAL' => 'Final',
];

function sf_renderCopa(array $partidosCopa, array $equiposPorNombre): string {
    $h = '';
    foreach (SF_FASES as $f) {
        $ms = array_values(array_filter($partidosCopa, fn($p) => ($p['fase'] ?? '') === $f));
        if (!$ms) continue;
        $h .= '<div class="br-round"><div class="br-label">'.sf_esc(SF_FASE_LABEL[$f] ?? $f).'</div>';
        foreach ($ms as $p) {
            $i = array_search($p, $partidosCopa, true);
            $L = sf_resolveSide($p, 'local', $partidosCopa);
            $V = sf_resolveSide($p, 'visitante', $partidosCopa);
            $w = sf_winnerOf($p);
            $fin = sf_isFin($p);
            $side = function (array $info, int $score, ?string $name) use ($equiposPorNombre, $w, $fin) {
                if ($info['pend']) return '<div class="br-side br-tbd"><span class="nm">'.sf_esc($info['n']).'</span></div>';
                $t = $equiposPorNombre[$info['n']] ?? null;
                $win = $fin && $w === $name;
                return '<div class="br-side '.($fin ? ($win ? 'br-win' : 'br-lose') : '').'">'.sf_divIcon($t).sf_crest($t).'<span class="nm">'.sf_esc($info['n']).'</span>'.($fin ? '<span class="sc">'.$score.'</span>' : '').'</div>';
            };
            $h .= '<div class="br-match" data-comp="copa" data-idx="'.$i.'">'.$side($L, sf_gl($p), $p['local'] ?? null).$side($V, sf_gv($p), $p['visitante'] ?? null).'</div>';
        }
        $h .= '</div>';
    }
    return $h !== '' ? $h : '<p class="muted">La Copa todavía no tiene cruces publicados.</p>';
}

function sf_renderGruposCopa(array $partidosCopa, array $equiposPorNombre): string {
    $grupos = array_values(array_filter($partidosCopa, fn($p) => ($p['fase'] ?? '') === 'FASE DE GRUPOS'));
    if (!$grupos) return '';
    $by = [];
    foreach ($grupos as $p) { $g = $p['grupo'] ?? 'A'; $by[$g][] = $p; }
    ksort($by);
    $out = '';
    foreach ($by as $k => $ms) {
        $t = [];
        foreach ($ms as $p) {
            foreach ([$p['local'] ?? '', $p['visitante'] ?? ''] as $n) if (!isset($t[$n])) $t[$n] = ['n' => $n, 'pts' => 0, 'gf' => 0, 'gc' => 0];
            if (!sf_isFin($p)) continue;
            $a = sf_gl($p); $b = sf_gv($p);
            $t[$p['local']]['gf'] += $a; $t[$p['local']]['gc'] += $b;
            $t[$p['visitante']]['gf'] += $b; $t[$p['visitante']]['gc'] += $a;
            if ($a > $b) $t[$p['local']]['pts'] += 3; elseif ($b > $a) $t[$p['visitante']]['pts'] += 3;
            else { $t[$p['local']]['pts']++; $t[$p['visitante']]['pts']++; }
        }
        $rows = array_values($t);
        usort($rows, fn($a, $b) => $b['pts'] !== $a['pts'] ? $b['pts'] <=> $a['pts'] : ($b['gf'] - $b['gc']) <=> ($a['gf'] - $a['gc']));
        $out .= '<div class="card group"><h4>Grupo '.sf_esc((string)$k).'</h4>'.implode('', array_map(function ($r, $i) use ($equiposPorNombre) {
            $t = $equiposPorNombre[$r['n']] ?? null;
            return '<div class="group-row '.($i < 2 ? 'group-q' : '').'">'.sf_crest($t).'<span class="nm">'.sf_esc($r['n']).'</span><span class="p">'.$r['pts'].'</span></div>';
        }, $rows, array_keys($rows))).'</div>';
    }
    return $out;
}

/* ---------------------------- Goleadores ---------------------------- */

function sf_findPlayer(array $equipos, string $short): ?array {
    $n = mb_strtolower($short);
    foreach ($equipos as $e) {
        foreach (($e['jugadores'] ?? []) as $j) {
            $pn = mb_strtolower((string)($j['nombre'] ?? ''));
            $pnFirst = explode(' ', $pn)[0] ?? '';
            $nFirst = explode(' ', $n)[0] ?? '';
            if ($pn === $n || $pnFirst === $n || ($nFirst === $pnFirst && str_starts_with($pn, $n))) {
                return ['j' => $j, 'e' => $e];
            }
        }
    }
    return null;
}

function sf_calcScorers(array $equipos, array $matches): array {
    $t = [];
    foreach ($matches as $p) {
        if (!sf_isFin($p)) continue;
        foreach (explode('/', (string)($p['detalles'] ?? '')) as $half) {
            foreach (explode(',', $half) as $ev) {
                $parts = array_map('trim', explode(':', trim($ev)));
                if (count($parts) >= 2 && $parts[0] === 'gol' && $parts[1] !== '') {
                    $t[$parts[1]] = ($t[$parts[1]] ?? 0) + 1;
                }
            }
        }
    }
    $out = [];
    foreach ($t as $n => $goles) {
        $f = sf_findPlayer($equipos, $n);
        $out[] = ['nombre' => $f ? $f['j']['nombre'] : $n, 'goles' => $goles, 'j' => $f['j'] ?? null, 'e' => $f['e'] ?? null];
    }
    usort($out, fn($a, $b) => $b['goles'] !== $a['goles'] ? $b['goles'] <=> $a['goles'] : strcmp((string)$a['nombre'], (string)$b['nombre']));
    return $out;
}

function sf_renderScorers(array $equipos, array $matches, int $top = 10): string {
    $list = sf_calcScorers($equipos, array_values(array_filter($matches, 'sf_isFin')));
    if (!$list) return '<div class="ev-empty">Todavía no hay goles registrados en esta competición.</div>';
    $row = function (array $r, int $i) {
        $cls = $i === 0 ? 'sc-1' : ($i === 1 ? 'sc-2' : ($i === 2 ? 'sc-3' : ''));
        $j = $r['j']; $e = $r['e'];
        $attrs = ($e && $j) ? ' data-team="'.sf_esc($e['id']).'" data-player="'.sf_esc(rawurlencode($j['nombre'])).'"' : '';
        $foto = $j['foto'] ?? null;
        $avatar = sf_isHttp($foto)
            ? '<img src="'.sf_esc(sf_normalizarUrlImagen($foto)).'" alt="'.sf_esc($r['nombre']).'" class="" loading="lazy" referrerpolicy="no-referrer">'
            : '<span class="noimg">'.sf_esc(mb_strtoupper(mb_substr($r['nombre'], 0, 1))).'</span>';
        $pos = $j['posicion'] ?? null;
        return '<div class="sc-row '.$cls.'"'.$attrs.'>'
            .'<span class="sc-rank">'.($i + 1).'</span>'
            .'<div class="sc-who">'.$avatar
            .'<span class="sc-name">'.sf_esc($r['nombre']).'</span>'
            .($pos ? '<span class="sc-pos">'.sf_esc((string)$pos).'</span>' : '')
            .($e ? '<span class="sc-team">'.sf_crest($e).'<span>'.sf_esc($e['nombre']).'</span></span>' : '')
            .'</div>'
            .'<span class="sc-goals mono">'.$r['goles'].'<small>G</small></span>'
            .'</div>';
    };
    $topList = array_slice($list, 0, $top);
    $rest = array_slice($list, $top);
    $html = implode('', array_map($row, $topList, array_keys($topList)));
    if ($rest) {
        $html .= '<div class="sc-rest">'.implode('', array_map(fn($r, $i) => $row($r, $i + $top), $rest, array_keys($rest))).'</div>'
            .'<button class="sc-more" type="button" aria-expanded="false"><span class="sc-more-txt" data-no-tr>Ver los '.count($rest).' goleadores restantes</span><i class="ph-bold ph-caret-down"></i></button>';
    }
    return $html;
}

/* ---------------------------- Schema ---------------------------- */

/* Actualiza (o añade, si falta) "dateModified" en el nodo WebPage del
   JSON-LD -- señal de recencia real para citación en IA. Cirugía de texto
   igual que sf_setInnerHtmlById, no un parse+reserialize del bloque entero,
   para no arriesgar el resto del schema. */
function sf_actualizarDateModified(string &$html): bool {
    $iso = (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM);
    $marker = '"@id": "https://superligafrontier.es/#webpage",';
    $pos = strpos($html, $marker);
    if ($pos === false) return false;
    $searchWindowEnd = strpos($html, '"@type"', $pos + strlen($marker));
    $window = $searchWindowEnd !== false ? substr($html, $pos, $searchWindowEnd - $pos) : substr($html, $pos, 500);

    if (strpos($window, '"dateModified"') !== false) {
        $newWindow = preg_replace('/"dateModified":\s*"[^"]*"/', '"dateModified": "'.$iso.'"', $window, 1);
        $html = substr($html, 0, $pos).$newWindow.substr($html, $pos + strlen($window));
        return true;
    }

    $insertAt = $pos + strlen($marker);
    $html = substr($html, 0, $insertAt)."\n            \"dateModified\": \"".$iso.'",'.substr($html, $insertAt);
    return true;
}

/* ---------------------------- Orquestación ---------------------------- */

/* Reemplaza el contenido de un elemento por su id dentro de un string HTML,
   por cirugía de texto (sin pasar por un parser DOM). Se evita a propósito
   DOMDocument aquí: cargar y volver a serializar el documento ENTERO (más de
   600KB, con bloques <script>/<style> enormes y texto en media docena de
   alfabetos) re-codifica caracteres como entidades y puede alterar cosas
   fuera del elemento que se quería tocar — se comprobó en pruebas que
   engordaba el fichero casi el doble. Buscando el elemento por su id y
   contando aperturas/cierres de la misma etiqueta hasta que el balance
   vuelve a cero, solo se toca el trozo exacto que hace falta; el resto del
   fichero queda byte a byte igual. */
function sf_setInnerHtmlById(string &$html, string $id, string $newInner): bool {
    if (!preg_match('/<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*\bid=["\']'.preg_quote($id, '/').'["\'][^>]*>/', $html, $m, PREG_OFFSET_CAPTURE)) {
        return false;
    }
    $tag = $m[1][0];
    $openTagEnd = $m[0][1] + strlen($m[0][0]); // posición justo después del '>' de apertura

    $openRe = '/<'.$tag.'\b/i';
    $closeRe = '/<\/'.$tag.'\s*>/i';
    $offset = $openTagEnd;
    $balance = 1;
    $contentEnd = null;
    $len = strlen($html);
    while ($offset < $len) {
        $nextOpen = preg_match($openRe, $html, $mo, PREG_OFFSET_CAPTURE, $offset) ? $mo[0][1] : PHP_INT_MAX;
        $nextClose = preg_match($closeRe, $html, $mc, PREG_OFFSET_CAPTURE, $offset) ? $mc[0][1] : PHP_INT_MAX;
        if ($nextClose === PHP_INT_MAX) break; // HTML mal formado; no tocar nada
        if ($nextOpen < $nextClose) {
            $balance++;
            $offset = $nextOpen + strlen($mo[0][0]);
        } else {
            $balance--;
            if ($balance === 0) { $contentEnd = $nextClose; break; }
            $offset = $nextClose + strlen($mc[0][0]);
        }
    }
    if ($contentEnd === null) return false;

    $html = substr($html, 0, $openTagEnd).$newInner.substr($html, $contentEnd);
    return true;
}

/* Actualiza clasificación (Superliga, la división por defecto), resultados
   (última jornada de Liga), goleadores (Liga) y el cuadro de Copa sobre un
   fichero HTML ya construido por _fuente/build.js. Devuelve true si guardó
   cambios. */
function sf_actualizarTablas(string $path, array $datos): bool {
    $html = file_get_contents($path);
    if ($html === false) return false;
    $original = $html;

    $equipos = $datos['equipos'] ?? [];
    $equiposPorNombre = [];
    foreach ($equipos as $e) $equiposPorNombre[$e['nombre']] = $e;
    $partidosLiga = $datos['partidos_liga'] ?? [];
    $partidosCopa = $datos['partidos_copa'] ?? [];

    $cambiado = false;
    $cambiado = sf_setInnerHtmlById($html, 'tbody-clas', sf_renderClasBody($equipos, ['liga' => $partidosLiga, 'ascenso' => $datos['partidos_ascenso'] ?? []], 'SUPERLIGA')) || $cambiado;
    [$matchesHtml, $jLabel] = sf_renderMatchesLastJornada($partidosLiga, $equiposPorNombre);
    $cambiado = sf_setInnerHtmlById($html, 'matches', $matchesHtml) || $cambiado;
    $cambiado = sf_setInnerHtmlById($html, 'scorers', sf_renderScorers($equipos, $partidosLiga)) || $cambiado;
    $cambiado = sf_setInnerHtmlById($html, 'bracket-copa', sf_renderCopa($partidosCopa, $equiposPorNombre)) || $cambiado;
    sf_setInnerHtmlById($html, 'groups-copa', sf_renderGruposCopa($partidosCopa, $equiposPorNombre));
    if ($cambiado) sf_actualizarDateModified($html);

    if (!$cambiado || $html === $original) return false;
    return file_put_contents($path, $html) !== false;
}
