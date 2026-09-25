<?php
// lesiones/almacen.php
// El ÚNICO fichero del subproyecto que lee lesiones/data/*.json o
// datos_oficiales.json. Ninguna pantalla llama a leGuardarJsonAtomico()
// directamente sobre estos ficheros.

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/dominio.php';

// Existe como función y no como constante para que los tests apunten a un
// directorio temporal con $GLOBALS['LE_DIR_DATOS'] y no toquen jamás los
// datos reales.
function leRutaDatos(string $fichero): string
{
    $base = $GLOBALS['LE_DIR_DATOS'] ?? (__DIR__ . '/data');
    return rtrim($base, '/\\') . '/' . $fichero;
}

// datos_oficiales.json vive en la raíz de htdocs/, un nivel por encima de
// lesiones/. Los tests lo redirigen con $GLOBALS['LE_RUTA_OFICIALES'].
function leRutaOficiales(): string
{
    return $GLOBALS['LE_RUTA_OFICIALES'] ?? (dirname(__DIR__) . '/datos_oficiales.json');
}

// Los jugadores de datos_oficiales.json no traen un id propio. El dorsal NO
// sirve: en los datos reales hay equipos con dos jugadores activos con el
// mismo dorsal, y sus lesiones se confundirían. El nombre sí es único dentro
// de cada plantilla, y además no cambia cuando a alguien le cambian el dorsal.
// Se compara en minúsculas y sin espacios en los bordes para que un retoque
// de mayúsculas en la web no rompa el vínculo con su historial.
function leIdJugador(string $equipoId, string $nombre): string
{
    return $equipoId . '#' . mb_strtolower(trim($nombre), 'UTF-8');
}

// datos_oficiales.json decodificado, una sola vez por petición. Pesa varios MB
// y lo piden las consultas, la sincronización y la tirada: sin esta caché una
// visita a la portada lo decodificaba cuatro o cinco veces. La caché va por
// ruta y no es un valor único para que un test que cambia
// $GLOBALS['LE_RUTA_OFICIALES'] a otro fixture lea ese fixture, no el anterior.
// Se lee con leLeerJson() SIN lock: es un fichero ajeno al subproyecto, y
// tomar un lock ahí crearía un .lock fuera de lesiones/.
// ponytail: dentro de una misma petición no ve cambios en disco; ninguna
// petición de lesiones/ escribe datos_oficiales.json, así que no se invalida.
function leOficiales(): array
{
    static $cache = [];
    $ruta = leRutaOficiales();
    return $cache[$ruta] ??= leLeerJson($ruta, []);
}

// Equipos no archivados de datos_oficiales.json, con sus jugadores
// normalizados (id sintético + campos que usa la ruleta).
function leCargarEquiposOficiales(): array
{
    $oficiales = leOficiales();
    if (!isset($oficiales['equipos']) || !is_array($oficiales['equipos'])) {
        return [];
    }
    $equipos = [];
    foreach ($oficiales['equipos'] as $eq) {
        if (!empty($eq['archivado'])) {
            continue;
        }
        $id = (string) ($eq['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $jugadores = [];
        foreach (($eq['jugadores'] ?? []) as $j) {
            $dorsal = $j['dorsal'] ?? '';
            $jugadores[] = [
                'id'       => leIdJugador($id, (string) ($j['nombre'] ?? '')),
                'nombre'   => (string) ($j['nombre'] ?? ''),
                'dorsal'   => $dorsal,
                'posicion' => (string) ($j['posicion'] ?? ''),
                'foto'     => (string) ($j['foto'] ?? ''),
            ];
        }
        $equipos[] = [
            'id'        => $id,
            'nombre'    => (string) ($eq['nombre'] ?? $id),
            'division'  => (string) ($eq['division'] ?? ''),
            'escudo'    => (string) ($eq['escudo'] ?? ''),
            'jugadores' => $jugadores,
        ];
    }
    return $equipos;
}

function leBuscarEquipoOficial(string $equipoId): ?array
{
    foreach (leCargarEquiposOficiales() as $eq) {
        if ($eq['id'] === $equipoId) {
            return $eq;
        }
    }
    return null;
}

// La temporada activa marca qué lesiones son "de esta temporada" en las
// vistas: cuando cambia en datos_oficiales.json, el historial y el ranking
// deben pasar de página sin que nadie borre nada a mano.
function leTemporadaActual(): string
{
    return (string) (leOficiales()['config']['temporada'] ?? '');
}

// ------------------------------------------------------ estado por equipo

function leCargarEstadoEquipos(): array
{
    return leCargarJson(leRutaDatos('estado_equipos.json'), []);
}

function leGuardarEstadoEquipos(array $data): bool
{
    return leGuardarJsonAtomico(leRutaDatos('estado_equipos.json'), $data);
}

// Semana ISO actual, formato "2026-W38". Mismo formato que usaba el sistema
// anterior: se ordena igual como cadena que como fecha.
function leSemanaISO(): string
{
    return (new DateTimeImmutable('now'))->format('o-\WW');
}

function leEquipoTiradoEstaSemana(string $equipoId): bool
{
    $estado = leCargarEstadoEquipos();
    return ($estado[$equipoId]['ultima_semana_tirada'] ?? '') === leSemanaISO();
}

// Reclama la tirada de la semana para un equipo: comprueba y marca DENTRO del
// mismo lock. Comprobar con leEquipoTiradoEstaSemana() y marcar después
// dejaría una ventana en la que dos peticiones casi simultáneas (doble clic,
// dos pestañas) pasarían las dos la comprobación y el equipo se tiraría dos
// veces la misma semana. Devuelve la semana anterior ('' si nunca se tiró)
// para poder deshacerlo, o null si no se reclamó (ya estaba reclamada o no
// se pudo escribir).
function leMarcarEquipoTirado(string $equipoId, string $semana): ?string
{
    $anterior = null;
    $ok = leActualizarJson(leRutaDatos('estado_equipos.json'), [],
        static function (array $d) use ($equipoId, $semana, &$anterior): ?array {
            $actual = (string) ($d[$equipoId]['ultima_semana_tirada'] ?? '');
            if ($actual === $semana) {
                return null;
            }
            $anterior = $actual;
            $d[$equipoId]['ultima_semana_tirada'] = $semana;
            // Un registro que quedara de antes (por ejemplo, si al anular falló
            // su borrado) no debe servir para anular la tirada nueva.
            unset($d[$equipoId]['ultima_tirada']);
            return $d;
        });
    // Si la escritura falla, la reclamación no ha ocurrido de verdad aunque
    // el callback ya haya fijado $anterior: no se puede devolver éxito.
    return $ok ? $anterior : null;
}

// Deshace una reclamación cuando la tirada no llegó a guardarse, para que el
// admin pueda repetirla. Solo restaura si la semana guardada sigue siendo la
// reclamada: nunca pisa algo que otra petición haya escrito después.
function leSoltarSemana(string $equipoId, string $semana, string $anterior): bool
{
    return leActualizarJson(leRutaDatos('estado_equipos.json'), [],
        static function (array $d) use ($equipoId, $semana, $anterior): ?array {
            if ((string) ($d[$equipoId]['ultima_semana_tirada'] ?? '') !== $semana) {
                return null;
            }
            $d[$equipoId]['ultima_semana_tirada'] = $anterior;
            return $d;
        });
}

// ------------------------------------------------------------- lesiones

function leCargarLesiones(): array
{
    return leCargarJson(leRutaDatos('lesiones.json'), []);
}

function leGuardarLesiones(array $data): bool
{
    return leGuardarJsonAtomico(leRutaDatos('lesiones.json'), $data);
}

// La lesión ACTIVA de un jugador (a lo sumo una POR TEMPORADA, por diseño),
// con su índice en el array bajo la clave "_indice" para que quien la llame
// pueda sustituirla en su sitio. null si no tiene ninguna activa.
//
// $temporada es opcional para no romper compatibilidad, pero leTirarParaEquipo
// SIEMPRE debe pasar la temporada actual: una baja de otra temporada (por
// ejemplo una "toda_temporada" que la sincronización nunca marca recuperada
// sola) no se reabre ni se alarga al volver a tirar. La nueva lesión empieza
// de cero, con su propia fecha_inicio, y la antigua se queda tal cual como
// historial de esa temporada pasada.
function leLesionActivaDeJugador(array $lesiones, string $jugadorId, ?string $temporada = null): ?array
{
    foreach ($lesiones as $i => $l) {
        if ((string) ($l['jugador_id'] ?? '') !== $jugadorId || ($l['estado'] ?? '') !== 'activa') {
            continue;
        }
        if ($temporada !== null && (string) ($l['temporada'] ?? '') !== $temporada) {
            continue;
        }
        $l['_indice'] = $i;
        return $l;
    }
    return null;
}

// ------------------------------------------------------ recuperación

// Partidos FINALIZADO (liga + ascenso + copa) en los que ha jugado un equipo.
// local/visitante son NOMBRES en datos_oficiales.json: no hay id de partido
// ni de equipo en ellos, así que se cuenta por nombre.
function lePartidosFinalizadosDeEquipo(array $oficiales, string $nombreEquipo): int
{
    $n = 0;
    foreach (['partidos_liga', 'partidos_ascenso', 'partidos_copa'] as $competicion) {
        foreach (($oficiales[$competicion] ?? []) as $p) {
            if (($p['estado'] ?? '') === 'FINALIZADO'
                && (($p['local'] ?? null) === $nombreEquipo || ($p['visitante'] ?? null) === $nombreEquipo)) {
                $n++;
            }
        }
    }
    return $n;
}

// Recalcula partidos_restantes de cada lesión ACTIVA (no toda_temporada) de
// un equipo no archivado a partir de un recuento base: al tirar se guardó
// cuántos partidos FINALIZADO llevaba el equipo (partidos_equipo_base) y
// cuántos le quedaban a la lesión (partidos_restantes_base). Los jugados desde
// entonces son la diferencia con el recuento de ahora.
//
// Se hace así, y no apuntando qué partidos ya se descontaron, porque los
// partidos no tienen id: una clave hecha con nombres y jornada cambiaba al
// renombrar un equipo o mover un partido de jornada, y todos sus partidos
// volvían a contar como nuevos (recuperaciones falsas de golpe). Con el
// recuento, si el número de partidos del equipo BAJA (renombrado, cambio de
// temporada, un partido devuelto a PENDIENTE), los jugados se quedan en 0 y
// la lesión vuelve como mucho a su base: la recuperación se detiene, nunca se
// completa por error. Tampoco hay ninguna lista que crezca para siempre.
//
// Idempotente por construcción: el resultado solo depende del JSON actual,
// así que llamarlo en cada carga de página no descuenta nada dos veces.
function leSincronizarRecuperacion(): void
{
    $oficiales = leOficiales();
    $recuento = []; // equipoId => partidos FINALIZADO ahora mismo
    foreach (leCargarEquiposOficiales() as $eq) {
        $recuento[$eq['id']] = lePartidosFinalizadosDeEquipo($oficiales, $eq['nombre']);
    }
    if ($recuento === []) {
        return;
    }

    leActualizarJson(leRutaDatos('lesiones.json'), [], static function (array $lesiones) use ($recuento): ?array {
        $cambiado = false;
        foreach ($lesiones as $i => $l) {
            $equipoId = (string) ($l['equipo_id'] ?? '');
            if (($l['estado'] ?? '') !== 'activa' || !empty($l['toda_temporada']) || !isset($recuento[$equipoId])) {
                continue;
            }
            $ahora = $recuento[$equipoId];
            // Sin campos base (lesiones anteriores a este cálculo, o metidas a
            // mano) sale "sin cambios": un dato que falta nunca recupera a nadie.
            $base      = (int) ($l['partidos_equipo_base'] ?? $ahora);
            $restBase  = (int) ($l['partidos_restantes_base'] ?? $l['partidos_restantes'] ?? 0);
            $restantes = max(0, $restBase - max(0, $ahora - $base));
            if ($restantes === (int) ($l['partidos_restantes'] ?? 0)) {
                continue;
            }
            $lesiones[$i]['partidos_restantes'] = $restantes;
            if ($restantes === 0) {
                $lesiones[$i]['estado'] = 'recuperada';
            }
            $cambiado = true;
        }
        return $cambiado ? $lesiones : null; // null: nada cambió, no se reescribe
    });
}
// ------------------------------------------------------------- la ruleta

// Orquesta una tirada completa para un equipo: comprueba el bloqueo semanal,
// tira la ruleta principal y, si toca evento, la secundaria; selecciona
// jugadores, acumula sus lesiones y persiste todo. Es la única función que
// combina dominio.php (las reglas) con almacen.php (los datos) para esta
// operación, así que admin.php no tiene que conocer el orden de los pasos.
//
// Los tres $azar* son solo para tests deterministas; en producción se dejan
// en null y usan la aleatoriedad real de dominio.php.
function leTirarParaEquipo(
    string $equipoId,
    ?callable $azarPrincipal = null,
    ?callable $azarSecundario = null,
    ?callable $azarSeleccion = null
): array {
    $equipo = leBuscarEquipoOficial($equipoId);
    if ($equipo === null) {
        return ['ok' => false, 'error' => 'equipo_no_encontrado'];
    }

    $semana   = leSemanaISO();
    $fecha    = leAhora();
    $anterior = leMarcarEquipoTirado($equipoId, $semana);
    if ($anterior === null) {
        // $anterior null es ambiguo: puede ser que ya estuviera reclamada
        // esta semana, o que la escritura de la reclamación haya fallado. Se
        // distingue mirando el estado real: si no quedó marcado como tirado,
        // no fue "ya_tirado", fue un fallo de escritura.
        return ['ok' => false, 'error' => leEquipoTiradoEstaSemana($equipoId) ? 'ya_tirado' : 'escritura'];
    }
    $principal = leTirarPrincipal($azarPrincipal);

    // Lo necesario para anular la tirada (leAnularTirada). Se guarda justo
    // después de reclamar la semana y de escribir las lesiones, no dentro de
    // esos locks: el de lesiones.json no puede anidar el de estado_equipos.json.
    // Si esta escritura falla, la tirada sigue siendo válida; solo deja de
    // poderse anular (leAnularTirada responde 'sin_datos').
    $registrar = static function (array $nuevas, array $previas) use ($equipoId, $semana, $anterior): void {
        leActualizarJson(leRutaDatos('estado_equipos.json'), [],
            static function (array $d) use ($equipoId, $semana, $anterior, $nuevas, $previas): array {
                $d[$equipoId]['ultima_tirada'] = [
                    'semana' => $semana, 'semana_anterior' => $anterior, 'nuevas' => $nuevas, 'previas' => $previas,
                ];
                return $d;
            });
    };

    if ($principal === 'nada') {
        $registrar([], []);
        return ['ok' => true, 'principal' => 'nada'];
    }

    $resultado = leTirarSecundaria($azarSecundario);
    $elegidos  = leSeleccionarJugadores($equipo['jugadores'], (int) $resultado['jugadores'], $azarSeleccion);

    // Se lee una sola vez, antes del callback: leActualizarJson puede
    // reintentar bajo lock, y la temporada no cambia a media tirada.
    $temporada = leTemporadaActual();
    // Recuento base para la recuperación (ver leSincronizarRecuperacion).
    $partidosEquipo = lePartidosFinalizadosDeEquipo(leOficiales(), $equipo['nombre']);

    $lesionados = [];
    $nuevas = [];  // ids creados por esta tirada
    $previas = []; // id => entrada tal como estaba antes de acumular
    $guardado = leActualizarJson(leRutaDatos('lesiones.json'), [], function (array $lesiones) use ($elegidos, $resultado, $equipoId, $semana, $fecha, $temporada, $partidosEquipo, &$lesionados, &$nuevas, &$previas): array {
        foreach ($elegidos as $jugador) {
            $activa = leLesionActivaDeJugador($lesiones, $jugador['id'], $temporada);
            $combinada = leAcumularLesion($activa, $resultado, $semana, $fecha);
            $entrada = [
                'id'                 => $activa['id'] ?? ('les_' . bin2hex(random_bytes(6))),
                'equipo_id'          => $equipoId,
                'jugador_id'         => $jugador['id'],
                'jugador_nombre'     => $jugador['nombre'],
                'dorsal'             => $jugador['dorsal'],
                'foto'               => $jugador['foto'],
                'semana_inicio'      => $combinada['semana_inicio'],
                'fecha_inicio'       => $combinada['fecha_inicio'],
                'partidos_totales'   => $combinada['partidos_totales'],
                'partidos_restantes' => $combinada['partidos_restantes'],
                // Al acumular, la base es lo que les queda a las dos juntas:
                // lo ya jugado de la anterior está descontado en ese restantes.
                'partidos_equipo_base'    => $partidosEquipo,
                'partidos_restantes_base' => $combinada['partidos_restantes'],
                'toda_temporada'     => $combinada['toda_temporada'],
                'estado'             => 'activa',
                'temporada'          => $temporada,
            ];
            if ($activa !== null) {
                // Por el id de la entrada nueva: una activa antigua sin id
                // recibe uno aquí, y es ese el que se busca al anular.
                $previas[$entrada['id']] = $lesiones[$activa['_indice']];
                $lesiones[$activa['_indice']] = $entrada;
            } else {
                $nuevas[] = $entrada['id'];
                $lesiones[] = $entrada;
            }
            $lesionados[] = [
                'jugador'          => $jugador,
                'partidos_totales'   => $entrada['partidos_totales'],
                // La ceremonia enseña lo que le queda, no la duración total:
                // al acumular, los dos números no coinciden.
                'partidos_restantes' => $entrada['partidos_restantes'],
                'toda_temporada'     => $entrada['toda_temporada'],
            ];
        }
        return $lesiones;
    });

    // Si las lesiones no llegaron a disco, la tirada no ha ocurrido: se libera
    // la semana para que el admin la repita, en vez de gastarla sin resultado.
    if (!$guardado) {
        leSoltarSemana($equipoId, $semana, $anterior);
        return ['ok' => false, 'error' => 'escritura'];
    }
    $registrar($nuevas, $previas);

    return ['ok' => true, 'principal' => 'evento', 'codigo' => $resultado['codigo'], 'lesionados' => $lesionados];
}

// ------------------------------------------------- correcciones del admin

// Da por recuperada a mano una lesión activa. leSincronizarRecuperacion()
// solo recalcula las 'activa', así que no la reabre aunque su recuento base
// diga que aún le quedan partidos.
function leCerrarLesion(string $lesionId): array
{
    // Sin esto, un id vacío casaría con cualquier lesión antigua sin id.
    if ($lesionId === '') {
        return ['ok' => false, 'error' => 'no_encontrada'];
    }
    $error = 'no_encontrada';
    $ok = leActualizarJson(leRutaDatos('lesiones.json'), [],
        static function (array $lesiones) use ($lesionId, &$error): ?array {
            foreach ($lesiones as $i => $l) {
                if ((string) ($l['id'] ?? '') !== $lesionId) {
                    continue;
                }
                if (($l['estado'] ?? '') !== 'activa') {
                    $error = 'no_activa';
                    return null;
                }
                $lesiones[$i]['estado'] = 'recuperada';
                $lesiones[$i]['partidos_restantes'] = 0;
                $lesiones[$i]['cierre_manual'] = leAhora();
                $error = null;
                return $lesiones;
            }
            return null;
        });
    if (!$ok) {
        return ['ok' => false, 'error' => 'escritura'];
    }
    return $error === null ? ['ok' => true] : ['ok' => false, 'error' => $error];
}

// Deshace la tirada de esta semana de un equipo con el registro ultima_tirada
// que dejó leTirarParaEquipo: quita las lesiones que creó y devuelve las que
// acumuló a su copia de antes. Se restaura la copia entera, campos base
// incluidos, así que la recuperación por recuento sigue siendo correcta
// aunque se haya jugado un partido entre la tirada y la anulación.
// Los locks se toman uno detrás de otro, nunca anidados.
function leAnularTirada(string $equipoId): array
{
    $semana = leSemanaISO();
    $estado = leCargarEstadoEquipos()[$equipoId] ?? [];
    if ((string) ($estado['ultima_semana_tirada'] ?? '') !== $semana) {
        return ['ok' => false, 'error' => 'no_tirado'];
    }
    $registro = $estado['ultima_tirada'] ?? null;
    if (!is_array($registro) || ($registro['semana'] ?? '') !== $semana) {
        return ['ok' => false, 'error' => 'sin_datos'];
    }

    $nuevas  = array_map('strval', (array) ($registro['nuevas'] ?? []));
    $previas = (array) ($registro['previas'] ?? []);
    if ($nuevas !== [] || $previas !== []) {
        $ok = leActualizarJson(leRutaDatos('lesiones.json'), [],
            static function (array $lesiones) use ($nuevas, $previas): array {
                $restauradas = [];
                foreach ($lesiones as $l) {
                    $id = (string) ($l['id'] ?? '');
                    if (in_array($id, $nuevas, true)) {
                        continue;
                    }
                    $restauradas[] = $previas[$id] ?? $l;
                }
                return $restauradas;
            });
        // Si las lesiones no se restauraron, la semana sigue gastada y el
        // registro sigue ahí: se puede reintentar sin dejar nada a medias.
        if (!$ok) {
            return ['ok' => false, 'error' => 'escritura'];
        }
    }

    $ok = leSoltarSemana($equipoId, $semana, (string) ($registro['semana_anterior'] ?? ''))
        && leActualizarJson(leRutaDatos('estado_equipos.json'), [],
            static function (array $d) use ($equipoId): ?array {
                if (!isset($d[$equipoId]['ultima_tirada'])) {
                    return null;
                }
                unset($d[$equipoId]['ultima_tirada']);
                return $d;
            });
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'escritura'];
}

// --------------------------------------------------------------- consultas

// Los tres parámetros opcionales existen para que index.php lea
// lesiones.json/datos_oficiales.json UNA vez por petición y se lo pase a las
// tres llamadas, en vez de que cada una vuelva a leer disco por su cuenta.
// Filtrar por temporada es intencional: una lesión de "toda_temporada" de una
// temporada anterior deja de contar como activa en cuanto
// datos_oficiales.json avanza de temporada, aunque su estado siga siendo
// "activa" en el JSON — es justo lo que se busca (bajas que no expiran solas
// no deben perseguir a un jugador para siempre).
function leLesionesActivasPorEquipo(string $equipoId, ?array $lesiones = null, ?string $temporada = null): array
{
    $temporada = $temporada ?? leTemporadaActual();
    return array_values(array_filter($lesiones ?? leCargarLesiones(),
        static fn(array $l): bool => (string) ($l['equipo_id'] ?? '') === $equipoId
            && ($l['estado'] ?? '') === 'activa'
            && (string) ($l['temporada'] ?? '') === $temporada));
}

function leHistorialPorEquipo(string $equipoId, ?array $lesiones = null, ?string $temporada = null): array
{
    $temporada = $temporada ?? leTemporadaActual();
    $historial = array_values(array_filter($lesiones ?? leCargarLesiones(),
        static fn(array $l): bool => (string) ($l['equipo_id'] ?? '') === $equipoId
            && (string) ($l['temporada'] ?? '') === $temporada));
    usort($historial, static fn(array $a, array $b): int => strcmp((string) $b['fecha_inicio'], (string) $a['fecha_inicio']));
    return $historial;
}

// Equipo más golpeado (más partidos-jugador perdidos), total de lesiones de
// la temporada y el jugador con más lesiones acumuladas. Solo sobre equipos
// no archivados (leCargarEquiposOficiales() ya los excluye) y solo sobre la
// temporada actual, por el mismo motivo que las dos funciones de arriba.
function leRankingGlobal(?array $equipos = null, ?array $lesiones = null, ?string $temporada = null): array
{
    $temporada = $temporada ?? leTemporadaActual();
    $equiposValidos = array_column($equipos ?? leCargarEquiposOficiales(), 'nombre', 'id');
    $lesiones = array_values(array_filter($lesiones ?? leCargarLesiones(),
        static fn(array $l): bool => isset($equiposValidos[(string) ($l['equipo_id'] ?? '')])
            && (string) ($l['temporada'] ?? '') === $temporada));

    $partidosPorEquipo = [];
    $lesionesPorJugador = [];
    foreach ($lesiones as $l) {
        $equipoId = (string) $l['equipo_id'];
        // Una baja de toda la temporada no tiene número de partidos. Para
        // poder ordenar el ranking se cuenta como 38, una temporada entera de
        // liga a doble vuelta: es una convención de esta vista, no un dato.
        $partidos = !empty($l['toda_temporada']) ? 38 : (int) $l['partidos_totales'];
        $partidosPorEquipo[$equipoId] = ($partidosPorEquipo[$equipoId] ?? 0) + $partidos;
        $clave = (string) $l['jugador_id'];
        $lesionesPorJugador[$clave] = $lesionesPorJugador[$clave] ?? ['nombre' => $l['jugador_nombre'], 'equipo' => $equiposValidos[$equipoId] ?? '', 'cuenta' => 0];
        $lesionesPorJugador[$clave]['cuenta']++;
    }

    arsort($partidosPorEquipo);
    $equipoMasGolpeado = null;
    foreach ($partidosPorEquipo as $equipoId => $partidos) {
        $equipoMasGolpeado = ['equipo' => $equiposValidos[$equipoId] ?? $equipoId, 'partidos_perdidos' => $partidos];
        break;
    }

    usort($lesionesPorJugador, static fn(array $a, array $b): int => $b['cuenta'] <=> $a['cuenta']);
    $jugadorTop = $lesionesPorJugador[0] ?? null;

    return [
        'total_lesiones'    => count($lesiones),
        'equipo_mas_golpeado' => $equipoMasGolpeado,
        'jugador_top'        => $jugadorTop,
    ];
}

// Filtro de la vista pública por división y por texto de búsqueda. Pura (sin
// I/O): index.php le pasa $lesiones ya recortadas a la temporada actual, para
// que una coincidencia de nombre en una lesión de una temporada cerrada no
// resucite un equipo que ya no debería verse. Si el nombre del EQUIPO
// coincide, se enseña la plantilla entera ("jugadores" null); si no, solo se
// enseña si coincide el nombre de algún JUGADOR con lesión ese equipo, y en
// ese caso se devuelve la lista de sus jugador_id para que index.php recorte
// activas/historial a solo esos jugadores.
function leFiltrarEquiposVista(array $equipos, array $lesiones, string $division, string $busqueda): array
{
    if ($division === 'SUPERLIGA' || $division === 'ASCENSO') {
        $equipos = array_filter($equipos, static fn(array $e): bool => ($e['division'] ?? '') === $division);
    }

    $busquedaNorm = leNormalizarTexto($busqueda);
    $resultado = [];
    foreach ($equipos as $eq) {
        if ($busquedaNorm === '' || str_contains(leNormalizarTexto((string) ($eq['nombre'] ?? '')), $busquedaNorm)) {
            $resultado[] = ['equipo' => $eq, 'jugadores' => null];
            continue;
        }
        $jugadorIds = [];
        foreach ($lesiones as $l) {
            if ((string) ($l['equipo_id'] ?? '') !== (string) ($eq['id'] ?? '')
                || !str_contains(leNormalizarTexto((string) ($l['jugador_nombre'] ?? '')), $busquedaNorm)) {
                continue;
            }
            $jugadorId = (string) ($l['jugador_id'] ?? '');
            if ($jugadorId !== '' && !in_array($jugadorId, $jugadorIds, true)) {
                $jugadorIds[] = $jugadorId;
            }
        }
        if ($jugadorIds !== []) {
            $resultado[] = ['equipo' => $eq, 'jugadores' => $jugadorIds];
        }
    }
    return $resultado;
}
