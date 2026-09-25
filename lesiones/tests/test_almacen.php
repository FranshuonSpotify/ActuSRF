<?php
// lesiones/tests/test_almacen.php
// Ejecutar: /c/xampp/php/php.exe lesiones/tests/test_almacen.php

require_once __DIR__ . '/arnes.php';
require_once __DIR__ . '/../almacen.php';

$GLOBALS['LE_DIR_DATOS'] = leArnesDirDatos();
$GLOBALS['LE_RUTA_OFICIALES'] = __DIR__ . '/fixtures/datos_oficiales_prueba.json';

// -- leCargarEquiposOficiales -----------------------------------------------
$equipos = leCargarEquiposOficiales();
leVerificar('excluye el equipo archivado', count($equipos) === 2);
leVerificar('no aparece "Equipo Archivado"', !in_array('Equipo Archivado', array_column($equipos, 'nombre'), true));

$alpino = leBuscarEquipoOficial('eq_1');
leVerificar('encuentra Alpino por id', $alpino !== null && $alpino['nombre'] === 'Alpino');
leVerificar('Alpino tiene 2 jugadores', count($alpino['jugadores']) === 2);
leVerificar('el id de jugador es equipoId#nombre en minúsculas', $alpino['jugadores'][0]['id'] === 'eq_1#jugador uno');

// Dos jugadores con el MISMO dorsal (pasa en los datos reales) no pueden
// compartir id, o la lesión de uno se atribuiría al otro.
$zanark = leBuscarEquipoOficial('eq_2');
$idsZanark = array_column($zanark['jugadores'], 'id');
leVerificar('mismo dorsal, ids distintos', count($idsZanark) === 2 && count(array_unique($idsZanark)) === 2);

leVerificar('leBuscarEquipoOficial devuelve null para un id inexistente', leBuscarEquipoOficial('eq_no_existe') === null);
leVerificar('el equipo archivado no se puede buscar', leBuscarEquipoOficial('eq_3') === null);

// -- bloqueo semanal ---------------------------------------------------------
leVerificar('un equipo nunca tirado no está bloqueado', !leEquipoTiradoEstaSemana('eq_1'));
leMarcarEquipoTirado('eq_1', leSemanaISO());
leVerificar('tras marcarlo, queda bloqueado esta semana', leEquipoTiradoEstaSemana('eq_1'));
leVerificar('otro equipo sigue libre', !leEquipoTiradoEstaSemana('eq_2'));

// -- lesiones activas ---------------------------------------------------------
$lesiones = [
    ['jugador_id' => 'eq_1#jugador uno', 'estado' => 'activa'],
    ['jugador_id' => 'eq_1#jugador dos', 'estado' => 'recuperada'],
];
$activa = leLesionActivaDeJugador($lesiones, 'eq_1#jugador uno');
leVerificar('encuentra la lesión activa del jugador', $activa !== null && $activa['_indice'] === 0);
leVerificar('no cuenta una lesión ya recuperada', leLesionActivaDeJugador($lesiones, 'eq_1#jugador dos') === null);
leVerificar('jugador sin lesiones devuelve null', leLesionActivaDeJugador($lesiones, 'eq_2#jugador tres') === null);

// -- recuento de partidos -------------------------------------------------------
$fixture = leOficiales();
leVerificar('Alpino tiene 1 partido FINALIZADO en el fixture', lePartidosFinalizadosDeEquipo($fixture, 'Alpino') === 1);
leVerificar('Zanark Domain (visitante) también cuenta ese partido', lePartidosFinalizadosDeEquipo($fixture, 'Zanark Domain') === 1);
leVerificar('un nombre que no juega no tiene partidos', lePartidosFinalizadosDeEquipo($fixture, 'Nadie') === 0);
leVerificar('los PENDIENTE no cuentan', lePartidosFinalizadosDeEquipo(['partidos_copa' => [
    ['estado' => 'PENDIENTE', 'local' => 'Alpino', 'visitante' => 'Zanark Domain'],
    ['estado' => 'FINALIZADO', 'local' => 'Zanark Domain', 'visitante' => 'Alpino'],
]], 'Alpino') === 1);

// -- sincronización de recuperación ------------------------------------------
// El fixture tiene un partido FINALIZADO Alpino–Zanark: con base 0, cada
// lesión de esos dos equipos lleva 1 partido jugado desde que se tiró.
leGuardarLesiones([
    ['jugador_id' => 'eq_1#jugador uno', 'equipo_id' => 'eq_1', 'jugador_nombre' => 'Jugador Uno',
     'partidos_totales' => 2, 'partidos_restantes' => 2, 'partidos_equipo_base' => 0, 'partidos_restantes_base' => 2,
     'toda_temporada' => false, 'estado' => 'activa'],
    ['jugador_id' => 'eq_2#jugador tres', 'equipo_id' => 'eq_2', 'jugador_nombre' => 'Jugador Tres',
     'partidos_totales' => 1, 'partidos_restantes' => 1, 'partidos_equipo_base' => 0, 'partidos_restantes_base' => 1,
     'toda_temporada' => false, 'estado' => 'activa'],
    ['jugador_id' => 'eq_1#jugador dos', 'equipo_id' => 'eq_1', 'jugador_nombre' => 'Jugador Dos',
     'partidos_totales' => 0, 'partidos_restantes' => 0, 'partidos_equipo_base' => 0, 'partidos_restantes_base' => 0,
     'toda_temporada' => true, 'estado' => 'activa'],
    // Base MAYOR que el recuento actual: es lo que se ve tras renombrar el
    // equipo o empezar temporada nueva. No puede recuperar a nadie.
    ['jugador_id' => 'eq_2#jugador cuatro', 'equipo_id' => 'eq_2', 'jugador_nombre' => 'Jugador Cuatro',
     'partidos_totales' => 2, 'partidos_restantes' => 2, 'partidos_equipo_base' => 5, 'partidos_restantes_base' => 2,
     'toda_temporada' => false, 'estado' => 'activa'],
    // Sin campos base (registro antiguo o metido a mano): no se toca.
    ['jugador_id' => 'eq_1#jugador sin base', 'equipo_id' => 'eq_1', 'jugador_nombre' => 'Jugador Sin Base',
     'partidos_totales' => 1, 'partidos_restantes' => 1, 'toda_temporada' => false, 'estado' => 'activa'],
]);

leSincronizarRecuperacion();
$tras1 = leCargarLesiones();
leVerificar('base 0 y 2 restantes: tras 1 partido le queda 1', $tras1[0]['partidos_restantes'] === 1);
leVerificar('Jugador Uno sigue activo', $tras1[0]['estado'] === 'activa');
leVerificar('también cuenta para el visitante: Jugador Tres llega a 0', $tras1[1]['partidos_restantes'] === 0);
leVerificar('Jugador Tres queda recuperado al llegar a 0', $tras1[1]['estado'] === 'recuperada');
leVerificar('toda_temporada nunca se descuenta ni se marca recuperada sola', $tras1[2]['estado'] === 'activa' && $tras1[2]['partidos_restantes'] === 0);
leVerificar('base mayor que el recuento (renombrado/temporada nueva) no recupera', $tras1[3]['partidos_restantes'] === 2 && $tras1[3]['estado'] === 'activa');
leVerificar('una lesión sin campos base queda intacta', $tras1[4] === [
    'jugador_id' => 'eq_1#jugador sin base', 'equipo_id' => 'eq_1', 'jugador_nombre' => 'Jugador Sin Base',
    'partidos_totales' => 1, 'partidos_restantes' => 1, 'toda_temporada' => false, 'estado' => 'activa']);
leVerificar('la sincronización no escribe en estado_equipos.json', !file_exists(leRutaDatos('estado_equipos.json')) || !str_contains((string) file_get_contents(leRutaDatos('estado_equipos.json')), 'partidos'));

// Segunda sincronización con el MISMO fixture: nada cambia.
leSincronizarRecuperacion();
leVerificar('sincronizar dos veces no cambia nada', leCargarLesiones() === $tras1);
// -- reclamación atómica de la semana -----------------------------------------
leGuardarEstadoEquipos([]);
$semanaPrueba = leSemanaISO();
leVerificar('la primera reclamación devuelve la semana anterior (vacía)', leMarcarEquipoTirado('eq_2', $semanaPrueba) === '');
leVerificar('una segunda reclamación de la misma semana devuelve null', leMarcarEquipoTirado('eq_2', $semanaPrueba) === null);
leSoltarSemana('eq_2', $semanaPrueba, '');
leVerificar('soltar la semana deja el equipo libre otra vez', !leEquipoTiradoEstaSemana('eq_2'));

// -- leTirarParaEquipo --------------------------------------------------------
leGuardarEstadoEquipos([]);
leGuardarLesiones([]);

$resNada = leTirarParaEquipo('eq_2', fn() => 0.99); // 0.99 >= 0.20 -> nada
leVerificar('sin evento: ok true, principal nada', $resNada['ok'] === true && $resNada['principal'] === 'nada');
leVerificar('tras tirar, el equipo queda bloqueado esta semana', leEquipoTiradoEstaSemana('eq_2'));

$resRepetido = leTirarParaEquipo('eq_2');
leVerificar('tirar dos veces la misma semana se rechaza', $resRepetido === ['ok' => false, 'error' => 'ya_tirado']);

$resInexistente = leTirarParaEquipo('eq_no_existe');
leVerificar('un equipo inexistente se rechaza', $resInexistente === ['ok' => false, 'error' => 'equipo_no_encontrado']);

// r=0.0 -> evento; secundaria r=0.0 -> 1j_1p; selección r=0.0 -> primer jugador (Jugador Uno, dorsal 1)
$resEvento = leTirarParaEquipo('eq_1', fn() => 0.0, fn() => 0.0, fn() => 0.0);
leVerificar('con evento: principal evento y código 1j_1p', $resEvento['ok'] === true && $resEvento['principal'] === 'evento' && $resEvento['codigo'] === '1j_1p');
leVerificar('un jugador lesionado', count($resEvento['lesionados']) === 1);
leVerificar('el jugador lesionado es Jugador Uno', $resEvento['lesionados'][0]['jugador']['nombre'] === 'Jugador Uno');
leVerificar('con 1 partido de baja', $resEvento['lesionados'][0]['partidos_totales'] === 1);
leVerificar('la respuesta lleva también los partidos restantes', $resEvento['lesionados'][0]['partidos_restantes'] === 1);

$lesionesTrasEvento = leCargarLesiones();
leVerificar('la lesión queda persistida en lesiones.json', count($lesionesTrasEvento) === 1 && $lesionesTrasEvento[0]['jugador_id'] === 'eq_1#jugador uno');
leVerificar('la lesión persistida lleva la temporada actual', $lesionesTrasEvento[0]['temporada'] === 'Temporada 4');
leVerificar('guarda el recuento base del equipo (1 partido de Alpino en el fixture)', $lesionesTrasEvento[0]['partidos_equipo_base'] === 1);
leVerificar('guarda los restantes base (1)', $lesionesTrasEvento[0]['partidos_restantes_base'] === 1);
leSincronizarRecuperacion();
leVerificar('recién tirada, sincronizar no le descuenta el partido que ya estaba jugado', leCargarLesiones()[0]['partidos_restantes'] === 1);

// -- re-lesión: se acumula sobre la activa de esta temporada -------------------
// Jugador Uno lleva 2 de 3 partidos jugados (le queda 1) y le tocan 2 más:
// total 5, pero le quedan 3. La ceremonia y la base deben usar el 3.
leGuardarEstadoEquipos([]);
leGuardarLesiones([
    ['id' => 'les_previa', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador uno', 'jugador_nombre' => 'Jugador Uno',
     'dorsal' => 1, 'foto' => 'uno.webp', 'semana_inicio' => '2026-W30', 'fecha_inicio' => '2026-07-20',
     'partidos_totales' => 3, 'partidos_restantes' => 1, 'toda_temporada' => false, 'estado' => 'activa', 'temporada' => 'Temporada 4'],
]);
// r=0.0 -> evento; secundaria r=0.5 (50 cae en 38..60) -> 1j_2p; selección r=0.0 -> Jugador Uno
$resAcumulada = leTirarParaEquipo('eq_1', fn() => 0.0, fn() => 0.5, fn() => 0.0);
leVerificar('re-lesión: código 1j_2p', $resAcumulada['ok'] === true && $resAcumulada['codigo'] === '1j_2p');
leVerificar('re-lesión: la respuesta da total 5 y restantes 3',
    $resAcumulada['lesionados'][0]['partidos_totales'] === 5 && $resAcumulada['lesionados'][0]['partidos_restantes'] === 3);
$acumulada = leCargarLesiones();
leVerificar('re-lesión: sigue siendo una sola lesión con el mismo id', count($acumulada) === 1 && $acumulada[0]['id'] === 'les_previa');
leVerificar('re-lesión: partidos_restantes_base es el restantes combinado (3)', $acumulada[0]['partidos_restantes_base'] === 3);
leVerificar('re-lesión: partidos_equipo_base es el recuento de ahora (1)', $acumulada[0]['partidos_equipo_base'] === 1);

// -- leTemporadaActual --------------------------------------------------------
leVerificar('leTemporadaActual lee config.temporada del fixture', leTemporadaActual() === 'Temporada 4');

// Fallo de escritura: se sustituye lesiones.json por un DIRECTORIO para que el
// rename() final falle. El @ va solo aquí, en el test, para callar los
// warnings de file_get_contents/rename sobre un directorio: el código de
// producción no se toca para poder probar esto.
leGuardarEstadoEquipos([]);
$rutaLesiones = leRutaDatos('lesiones.json');
unlink($rutaLesiones);
mkdir($rutaLesiones);
$resFallo = @leTirarParaEquipo('eq_1', fn() => 0.0, fn() => 0.0, fn() => 0.0);
leVerificar('si lesiones.json no se puede escribir, la tirada devuelve error escritura', $resFallo === ['ok' => false, 'error' => 'escritura']);
leVerificar('y la semana queda libre para repetirla', !leEquipoTiradoEstaSemana('eq_1'));
rmdir($rutaLesiones);

// -- fallo al reclamar la semana (bloqueo semanal) ----------------------------
// Se sustituye estado_equipos.json por un DIRECTORIO para que la escritura de
// la reclamación falle. Sin reclamación que se pueda guardar, la tirada no
// puede continuar: debe devolver 'escritura' (nunca confundirse con
// 'ya_tirado', que es un estado distinto) y no debe persistir ninguna lesión.
leGuardarEstadoEquipos([]);
leGuardarLesiones([]);
$rutaEstado = leRutaDatos('estado_equipos.json');
if (file_exists($rutaEstado)) {
    unlink($rutaEstado);
}
mkdir($rutaEstado);
$lesionesEq2Antes = count(array_filter(leCargarLesiones(), static fn($l) => ($l['equipo_id'] ?? '') === 'eq_2'));
$resFalloEstado = @leTirarParaEquipo('eq_2', fn() => 0.0, fn() => 0.0, fn() => 0.0);
leVerificar('si estado_equipos.json no se puede escribir, la tirada devuelve error escritura', $resFalloEstado === ['ok' => false, 'error' => 'escritura']);
rmdir($rutaEstado);
$lesionesEq2Despues = count(array_filter(leCargarLesiones(), static fn($l) => ($l['equipo_id'] ?? '') === 'eq_2'));
leVerificar('y no se persiste ninguna lesión de eq_2', $lesionesEq2Despues === $lesionesEq2Antes);

// -- una lesión de otra temporada no se reabre ni se alarga (round 2) --------
// Jugador Uno tiene una baja "toda_temporada" de Temporada 3 que la
// sincronización nunca marca recuperada sola (no tiene número de partidos que
// descontar). Si en Temporada 4 vuelve a tocarle la ruleta, esa baja vieja
// debe quedarse tal cual y la nueva lesión debe empezar de cero, no fundirse
// con la antigua ni heredar su fecha de inicio.
leGuardarEstadoEquipos([]);
leGuardarLesiones([
    ['id' => 'les_vieja', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador uno', 'jugador_nombre' => 'Jugador Uno',
     'dorsal' => 1, 'foto' => 'uno.webp', 'semana_inicio' => '2025-W10', 'fecha_inicio' => '2025-03-01',
     'partidos_totales' => 0, 'partidos_restantes' => 0, 'toda_temporada' => true, 'estado' => 'activa', 'temporada' => 'Temporada 3'],
]);

$lesionesConVieja = leCargarLesiones();
leVerificar('filtrando por la temporada actual, la baja de Temporada 3 no cuenta como activa', leLesionActivaDeJugador($lesionesConVieja, 'eq_1#jugador uno', 'Temporada 4') === null);
leVerificar('sin filtro de temporada (comportamiento antiguo), sí se encuentra', leLesionActivaDeJugador($lesionesConVieja, 'eq_1#jugador uno', null) !== null);

// r=0.0 -> evento; secundaria r=0.0 -> 1j_1p; selección r=0.0 -> Jugador Uno (dorsal 1)
$resNuevaTemporada = leTirarParaEquipo('eq_1', fn() => 0.0, fn() => 0.0, fn() => 0.0);
leVerificar('la tirada de la temporada nueva tiene éxito', $resNuevaTemporada['ok'] === true && $resNuevaTemporada['principal'] === 'evento');

$lesionesTrasNueva = array_values(array_filter(leCargarLesiones(), static fn(array $l): bool => ($l['jugador_id'] ?? '') === 'eq_1#jugador uno'));
leVerificar('Jugador Uno tiene ahora 2 lesiones (la de Temporada 3 no se tocó)', count($lesionesTrasNueva) === 2);

$vieja = null;
$nueva = null;
foreach ($lesionesTrasNueva as $l) {
    if (($l['temporada'] ?? '') === 'Temporada 3') {
        $vieja = $l;
    } else {
        $nueva = $l;
    }
}
leVerificar('la lesión de Temporada 3 conserva su fecha de inicio original', $vieja !== null && $vieja['fecha_inicio'] === '2025-03-01');
leVerificar('la lesión de Temporada 3 sigue siendo toda_temporada', $vieja !== null && $vieja['toda_temporada'] === true);
leVerificar('la lesión de Temporada 3 no cambió de temporada', $vieja !== null && $vieja['temporada'] === 'Temporada 3');
leVerificar('la lesión nueva es de Temporada 4', $nueva !== null && $nueva['temporada'] === 'Temporada 4');
leVerificar('la lesión nueva tiene 1 partido de baja (no hereda duración de la vieja)', $nueva !== null && $nueva['partidos_totales'] === 1);
leVerificar('la lesión nueva no es toda_temporada', $nueva !== null && $nueva['toda_temporada'] === false);

// -- anular la tirada de la semana -------------------------------------------
// Tirada con evento: la anulación borra la lesión creada y libera la semana.
leGuardarEstadoEquipos([]);
leGuardarLesiones([]);
leTirarParaEquipo('eq_1', fn() => 0.0, fn() => 0.0, fn() => 0.0);
$registro = leCargarEstadoEquipos()['eq_1']['ultima_tirada'] ?? null;
leVerificar('la tirada guarda ultima_tirada con la lesión nueva', is_array($registro) && $registro['semana'] === leSemanaISO()
    && count($registro['nuevas']) === 1 && $registro['nuevas'][0] === leCargarLesiones()[0]['id']);
leVerificar('anular una tirada con evento devuelve ok', leAnularTirada('eq_1') === ['ok' => true]);
leVerificar('tras anular no queda ninguna lesión de eq_1',
    array_filter(leCargarLesiones(), static fn(array $l): bool => ($l['equipo_id'] ?? '') === 'eq_1') === []);
leVerificar('tras anular, la semana de eq_1 queda libre', !leEquipoTiradoEstaSemana('eq_1'));
leVerificar('tras anular, ultima_tirada desaparece', !isset(leCargarEstadoEquipos()['eq_1']['ultima_tirada']));

// Tirada acumulada: la anulación restaura la lesión previa tal cual era.
leGuardarEstadoEquipos([]);
$sembrada = ['id' => 'les_sembrada', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador uno', 'jugador_nombre' => 'Jugador Uno',
    'dorsal' => 1, 'foto' => 'uno.webp', 'semana_inicio' => '2026-W30', 'fecha_inicio' => '2026-07-20',
    'partidos_totales' => 2, 'partidos_restantes' => 2, 'partidos_equipo_base' => 0, 'partidos_restantes_base' => 2,
    'toda_temporada' => false, 'estado' => 'activa', 'temporada' => 'Temporada 4'];
leGuardarLesiones([$sembrada]);
leTirarParaEquipo('eq_1', fn() => 0.0, fn() => 0.0, fn() => 0.0);
leVerificar('la tirada acumuló sobre la sembrada', leCargarLesiones()[0]['partidos_restantes'] !== 2);
leVerificar('anular la tirada acumulada devuelve ok', leAnularTirada('eq_1') === ['ok' => true]);
$trasAnularAcumulada = leCargarLesiones();
leVerificar('la lesión sembrada vuelve idéntica y no hay entradas nuevas', $trasAnularAcumulada === [$sembrada]);

// Tirada "nada": también se puede anular.
leGuardarEstadoEquipos([]);
leGuardarLesiones([]);
leTirarParaEquipo('eq_2', fn() => 0.99);
leVerificar('anular una tirada sin evento devuelve ok', leAnularTirada('eq_2') === ['ok' => true]);
leVerificar('tras anular la tirada sin evento, la semana queda libre', !leEquipoTiradoEstaSemana('eq_2'));

leGuardarEstadoEquipos([]);
leVerificar('sin tirada esta semana, anular devuelve no_tirado', leAnularTirada('eq_1') === ['ok' => false, 'error' => 'no_tirado']);

leGuardarEstadoEquipos(['eq_1' => ['ultima_semana_tirada' => leSemanaISO()]]);
leVerificar('tirada sin registro, anular devuelve sin_datos', leAnularTirada('eq_1') === ['ok' => false, 'error' => 'sin_datos']);

// -- dar por recuperada una lesión ---------------------------------------------
leGuardarEstadoEquipos([]);
leGuardarLesiones([
    ['id' => 'les_tt', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador dos', 'jugador_nombre' => 'Jugador Dos',
     'partidos_totales' => 0, 'partidos_restantes' => 0, 'toda_temporada' => true, 'estado' => 'activa', 'temporada' => 'Temporada 4'],
    // Con base 1 y 1 partido jugado, la sincronización le calcularía 3
    // restantes: si reabriera las cerradas, se vería aquí.
    ['id' => 'les_cuenta', 'equipo_id' => 'eq_1', 'jugador_id' => 'eq_1#jugador uno', 'jugador_nombre' => 'Jugador Uno',
     'partidos_totales' => 3, 'partidos_restantes' => 3, 'partidos_equipo_base' => 1, 'partidos_restantes_base' => 3,
     'toda_temporada' => false, 'estado' => 'activa', 'temporada' => 'Temporada 4'],
]);
leVerificar('cerrar una activa toda_temporada devuelve ok', leCerrarLesion('les_tt') === ['ok' => true]);
$cerrada = leCargarLesiones()[0];
leVerificar('la lesión cerrada queda recuperada, con 0 restantes y cierre_manual',
    $cerrada['estado'] === 'recuperada' && $cerrada['partidos_restantes'] === 0 && ($cerrada['cierre_manual'] ?? '') !== '');
leVerificar('cerrarla otra vez devuelve no_activa', leCerrarLesion('les_tt') === ['ok' => false, 'error' => 'no_activa']);
leVerificar('un id inexistente devuelve no_encontrada', leCerrarLesion('les_no_existe') === ['ok' => false, 'error' => 'no_encontrada']);
leVerificar('un id vacío devuelve no_encontrada', leCerrarLesion('') === ['ok' => false, 'error' => 'no_encontrada']);
leCerrarLesion('les_cuenta');
leSincronizarRecuperacion();
$trasSync = leCargarLesiones();
leVerificar('la sincronización no reabre las lesiones cerradas a mano',
    $trasSync[0]['estado'] === 'recuperada' && $trasSync[1]['estado'] === 'recuperada' && $trasSync[1]['partidos_restantes'] === 0);

leArnesLimpiar($GLOBALS['LE_DIR_DATOS']);
leSalirConResultado();
