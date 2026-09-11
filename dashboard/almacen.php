<?php
// dashboard/almacen.php
// El ÚNICO fichero del subproyecto que lee o escribe en dashboard/data/.
// Ninguna pantalla llama a plGuardarJsonAtomico() directamente: si lo hiciera,
// el control de concurrencia por rev y el registro de eventos dejarían de ser
// obligatorios y se podrían saltar sin querer. Hay un grep en el Verify del
// paso 03 que lo comprueba.

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/dominio.php';

// Existe como función y no como constante para que los tests puedan apuntar a
// un directorio temporal con $GLOBALS['PL_DIR_DATOS'] y no tocar jamás los
// datos reales. Una batería que da miedo ejecutar no se ejecuta.
function plRutaDatos(string $fichero): string
{
    $base = $GLOBALS['PL_DIR_DATOS'] ?? (__DIR__ . '/data');
    return rtrim($base, '/\\') . '/' . $fichero;
}

// ------------------------------------------------------------- semillas
// Ninguna semilla se escribe en disco al leerla: el fichero aparece la primera
// vez que algo lo guarda. Por eso no hace falta un comando de seed.

function plCargarEquipos(): array
{
    return plCargarJson(plRutaDatos('equipos.json'), ['equipos' => []]);
}

function plGuardarEquipos(array $data): bool
{
    return plGuardarJsonAtomico(plRutaDatos('equipos.json'), $data);
}

function plCargarUsuarios(): array
{
    return plCargarJson(plRutaDatos('usuarios.json'), ['usuarios' => []]);
}

function plGuardarUsuarios(array $data): bool
{
    return plGuardarJsonAtomico(plRutaDatos('usuarios.json'), $data);
}

// Los diez tiers oficiales con su salario de partida, en el orden del
// desplegable. Es la única semilla con contenido —sin ella la primera
// temporada nacería sin tabla de salarios— y también la lista cerrada de
// códigos válidos: C+, C- y D no existen, y nadie puede añadirlos.
const PL_TIERS_SEMILLA = [
    ['codigo' => 'S++', 'salario' => 75],
    ['codigo' => 'S+',  'salario' => 60],
    ['codigo' => 'S',   'salario' => 40],
    ['codigo' => 'A+',  'salario' => 25],
    ['codigo' => 'A',   'salario' => 18],
    ['codigo' => 'A-',  'salario' => 14],
    ['codigo' => 'B+',  'salario' => 8],
    ['codigo' => 'B',   'salario' => 6],
    ['codigo' => 'B-',  'salario' => 5],
    ['codigo' => 'C',   'salario' => 2],
];

function plCargarTiers(): array
{
    return plCargarJson(plRutaDatos('tiers.json'), ['tiers' => PL_TIERS_SEMILLA]);
}

function plGuardarTiers(array $data): bool
{
    return plGuardarJsonAtomico(plRutaDatos('tiers.json'), $data);
}

// Guarda los SALARIOS de los diez tiers; los códigos no se tocan nunca.
//
// Un código que no sea uno de los diez se ignora. Un salario negativo,
// decimal, vacío o no numérico hace que se rechace el envío ENTERO, antes de
// escribir nada: guardar la mitad dejaría la tabla en un estado que el admin
// no ha pedido y que quizá ni vea.
//
// No toca ningún fichero de temporada, y eso es lo que hace segura esta
// pantalla: cada temporada lleva sus salarios congelados en ajustes.tiers, así
// que subir S++ de 75M a 90M no puede reventar el cap de nadie ya inscrito.
function plGuardarSalariosTiers(array $salarios): array
{
    $codigosValidos = array_column(PL_TIERS_SEMILLA, 'codigo');
    $limpios = [];
    foreach ($salarios as $codigo => $valor) {
        $codigo = (string) $codigo;
        if (!in_array($codigo, $codigosValidos, true)) {
            continue;
        }
        // ctype_digit sobre el texto rechaza a la vez el signo, los decimales
        // y la cadena vacía. Un -5 que llegue como entero se convierte antes a
        // texto para que no se cuele por el lado del tipo.
        $texto = is_int($valor) ? (string) $valor : trim((string) $valor);
        if (!ctype_digit($texto)) {
            return ['ok' => false, 'mensaje' => 'El salario de ' . $codigo
                . ' tiene que ser un número entero de millones, sin decimales ni signo. No se ha guardado nada.'];
        }
        $limpios[$codigo] = (int) $texto;
    }

    if ($limpios === []) {
        return ['ok' => true, 'mensaje' => ''];
    }

    $ok = plActualizarJson(plRutaDatos('tiers.json'), ['tiers' => PL_TIERS_SEMILLA],
        static function (array $d) use ($limpios): array {
            // Se actualiza en su sitio para conservar el orden del desplegable.
            foreach ($d['tiers'] as $i => $t) {
                $c = (string) ($t['codigo'] ?? '');
                if (isset($limpios[$c])) {
                    $d['tiers'][$i]['salario'] = $limpios[$c];
                }
            }
            return $d;
        });
    return ['ok' => $ok, 'mensaje' => $ok ? '' : 'No se pudieron guardar los salarios.'];
}

function plCargarTemporadas(): array
{
    return plCargarJson(plRutaDatos('temporadas.json'), ['activa' => null, 'temporadas' => []]);
}

function plGuardarTemporadas(array $data): bool
{
    return plGuardarJsonAtomico(plRutaDatos('temporadas.json'), $data);
}

function plCargarTemporada(string $id): array
{
    return plCargarJson(plRutaDatos("temporada-$id.json"), []);
}

// Devuelve la entrada completa de la temporada activa, o null si aún no hay
// ninguna (estado normal antes de que el admin cree la primera).
function plTemporadaActiva(): ?array
{
    $t = plCargarTemporadas();
    $activa = $t['activa'] ?? null;
    if ($activa === null) {
        return null;
    }
    foreach ($t['temporadas'] as $temporada) {
        if (($temporada['id'] ?? null) === $activa) {
            return $temporada;
        }
    }
    return null;
}

// ---------------------------------------------------------- temporadas

// Crea la temporada con las plantillas vacías de todos los equipos activos y
// CONGELA ajustes.tiers copiando tiers.json en este instante.
//
// Ese congelado es la decisión de diseño que hace segura la pantalla de tiers
// del admin: editar tiers.json después solo afecta a las temporadas que se
// creen a partir de entonces, así que subir S++ de 75M a 90M no puede
// reventar retroactivamente el cap de treinta equipos ya inscritos.
//
// No se copia NADA de la temporada anterior: ni jugadores, ni cláusulas, ni
// posiciones, ni tiers. Los presidentes vuelven a inscribir desde cero.
function plCrearTemporada(string $id, string $nombre): array
{
    $ruta = plRutaDatos("temporada-$id.json");
    if (file_exists($ruta)) {
        return ['ok' => false, 'error' => 'error.temporada_existe'];
    }

    $equipos = [];
    foreach (plCargarEquipos()['equipos'] as $equipo) {
        if (!empty($equipo['activo'])) {
            $equipos[(string) $equipo['id']] = ['rev' => 0, 'jugadores' => []];
        }
    }

    $ok = plGuardarJsonAtomico($ruta, [
        'id'      => $id,
        'ajustes' => [
            'salaryCap'            => 250,
            'presupuestoClausulas' => 650,
            'maxJugadores'         => 20,
            'tiers'                => plCargarTiers()['tiers'],
        ],
        'equipos' => $equipos,
    ]);
    if (!$ok) {
        return ['ok' => false, 'error' => 'error.escritura'];
    }

    // Solo se convierte en la activa si el fichero se escribió de verdad. La
    // lista se actualiza bajo el lock, leyendo dentro: ver plActualizarJson().
    $ok = plActualizarJson(plRutaDatos('temporadas.json'), ['activa' => null, 'temporadas' => []],
        static function (array $t) use ($id, $nombre): array {
            $t['temporadas'][] = ['id' => $id, 'nombre' => $nombre, 'fase' => 'ROSTER', 'creada' => plAhora()];
            $t['activa'] = $id;
            return $t;
        });
    if (!$ok) {
        return ['ok' => false, 'error' => 'error.escritura'];
    }

    return ['ok' => true, 'error' => null];
}

// Atajo para lo que preguntan todas las pantallas. Sin temporada activa
// devuelve CERRADA, que es el estado más restrictivo: es preferible que una
// pantalla se sirva en solo lectura de más a que se abra por accidente.
function plFaseActiva(): string
{
    $t = plTemporadaActiva();
    return (string) ($t['fase'] ?? 'CERRADA');
}

function plCambiarFase(string $temporadaId, string $fase): array
{
    if (!in_array($fase, PL_FASES, true)) {
        return ['ok' => false, 'error' => 'error.fase_invalida'];
    }
    $encontrada = false;
    $ok = plActualizarJson(plRutaDatos('temporadas.json'), ['activa' => null, 'temporadas' => []],
        static function (array $t) use ($temporadaId, $fase, &$encontrada): ?array {
            foreach ($t['temporadas'] as $i => $temporada) {
                if (($temporada['id'] ?? null) === $temporadaId) {
                    $t['temporadas'][$i]['fase'] = $fase;
                    $encontrada = true;
                    return $t;
                }
            }
            return null;   // no existe: no se escribe nada
        });

    if (!$encontrada) {
        return ['ok' => false, 'error' => 'error.temporada_no_encontrada'];
    }
    return ['ok' => $ok, 'error' => $ok ? null : 'error.escritura'];
}

// ------------------------------------------------- concurrencia optimista

// El corazón del control de concurrencia. flock impide que dos guardados
// dejen un JSON a medias, pero NO impide que el guardado de un copresidente
// borre el del otro sin que ninguno se entere: los dos leyeron la pantalla,
// los dos escriben, gana el último. Eso es lo que cierra el rev.
//
// La relectura y la comparación van DENTRO del mismo flock que la escritura.
// Leer fuera y escribir dentro dejaría abierta exactamente la ventana que
// este mecanismo existe para cerrar.
function plGuardarEquipoTemporada(string $temporadaId, string $equipoId, array $datos, int $revEsperado): array
{
    $resultado = ['ok' => false, 'error' => 'error.escritura'];

    $escrito = plActualizarJson(plRutaDatos("temporada-$temporadaId.json"), [],
        static function (array $data) use ($equipoId, $datos, $revEsperado, &$resultado): ?array {
            $revDisco = (int) ($data['equipos'][$equipoId]['rev'] ?? -1);

            if ($revDisco === -1) {
                $resultado = ['ok' => false, 'error' => 'error.equipo_no_en_temporada'];
                return null;
            }
            if ($revDisco !== $revEsperado) {
                // Se rechaza sin escribir NADA: el presidente recarga y repite.
                $resultado = ['ok' => false, 'error' => 'error.rev_desfasado', 'rev' => $revDisco];
                return null;
            }

            $datos['rev'] = $revDisco + 1;
            $data['equipos'][$equipoId] = $datos;
            $resultado = ['ok' => true, 'error' => null, 'rev' => $datos['rev']];
            return $data;
        });

    // Si el lock o la escritura fallaron, el cierre pudo haber dado ya el
    // guardado por bueno: lo que manda es si llegó a disco.
    return $escrito ? $resultado : ['ok' => false, 'error' => 'error.escritura'];
}

// ------------------------------------------------------------- registro

// Append-only: nunca se modifica ni se borra una entrada existente. Es la
// única forma de arbitrar una disputa de clausulación con datos en vez de con
// memoria, y por eso el admin puede corregir el estado de un jugador pero no
// puede reescribir el registro de quién lo marcó.
function plRegistrarEvento(string $tipo, string $actor, string $actorNombre, array $detalle): bool
{
    // Leer y añadir bajo el MISMO lock. Con la lectura fuera, dos
    // clausulaciones simultáneas de equipos distintos leerían el mismo
    // registro, y la segunda en escribir borraría el evento de la primera.
    return plActualizarJson(plRutaDatos('registro.json'), ['eventos' => []],
        static function (array $registro) use ($tipo, $actor, $actorNombre, $detalle): array {
            $registro['eventos'][] = [
                'ts'          => plAhora(),
                'actor'       => $actor,
                'actorNombre' => $actorNombre,
                'tipo'        => $tipo,
                'detalle'     => $detalle,
            ];
            return $registro;
        });
}

// --------------------------------------------------------------- equipos

// Importa a equipos.json los equipos NO archivados de la web pública que aún
// no estén aquí, comparando por equipoId. Es idempotente: pulsarlo dos veces
// no duplica nada, y un equipo que aquí se archivó no se reactiva.
//
// datos_oficiales.json se lee con plLeerJson(), SIN lock, y solo se lee. Con
// lock se crearía un datos_oficiales.json.lock en la raíz de la web pública,
// fuera del subproyecto. Tampoco se arrastra su plantilla deportiva: los
// jugadores de este subproyecto son otra capa y se inscriben aparte.
function plImportarEquiposDeWeb(string $rutaOficiales): array
{
    $oficiales = plLeerJson($rutaOficiales, []);
    if (!isset($oficiales['equipos']) || !is_array($oficiales['equipos'])) {
        return ['ok' => false, 'importados' => 0, 'saltados' => 0];
    }

    $nuevos   = [];
    $saltados = 0;
    $ok = plActualizarJson(plRutaDatos('equipos.json'), ['equipos' => []],
        static function (array $propios) use ($oficiales, &$nuevos, &$saltados): ?array {
            $enlazados = [];
            foreach ($propios['equipos'] as $e) {
                if (($e['equipoId'] ?? null) !== null) {
                    $enlazados[(string) $e['equipoId']] = true;
                }
            }
            foreach ($oficiales['equipos'] as $o) {
                $origen = (string) ($o['id'] ?? '');
                if ($origen === '' || !empty($o['archivado'])) {
                    continue;
                }
                if (isset($enlazados[$origen])) {
                    $saltados++;
                    continue;
                }
                // El id local es el de origen: ya es único en la web, y así un
                // equipo se reconoce igual en los dos sitios. Los creados a
                // mano llevan el prefijo eq_m_ y no pueden chocar con ellos.
                $propios['equipos'][] = [
                    'id'             => $origen,
                    'nombre'         => (string) ($o['nombre'] ?? $origen),
                    'nombre_en'      => (string) ($o['nombre_en'] ?? ''),
                    'abreviatura'    => (string) ($o['abreviatura'] ?? ''),
                    'abreviatura_en' => (string) ($o['abreviatura_en'] ?? ''),
                    'escudo'         => (string) ($o['escudo'] ?? ''),
                    'color1'         => (string) ($o['color1'] ?? ''),
                    'equipoId'       => $origen,
                    'activo'         => true,
                ];
                $enlazados[$origen] = true;
                $nuevos[] = $origen;
            }
            return $nuevos === [] ? null : $propios;
        });

    if ($ok && $nuevos !== []) {
        plIncorporarEquiposATemporadaActiva($nuevos);
    }
    return ['ok' => $ok, 'importados' => count($nuevos), 'saltados' => $saltados];
}

function plCrearEquipo(string $nombre, string $abreviatura): array
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return ['ok' => false, 'id' => null, 'mensaje' => 'Escribe el nombre del equipo.'];
    }
    // Prefijo eq_m_: un equipo creado aquí no puede recibir nunca el mismo id
    // que uno importado de la web.
    $id = 'eq_m_' . bin2hex(random_bytes(4));
    $ok = plActualizarJson(plRutaDatos('equipos.json'), ['equipos' => []],
        static function (array $d) use ($id, $nombre, $abreviatura): array {
            $d['equipos'][] = [
                'id' => $id, 'nombre' => $nombre, 'nombre_en' => '',
                'abreviatura' => strtoupper(trim($abreviatura)), 'abreviatura_en' => '',
                'escudo' => '', 'color1' => '', 'equipoId' => null, 'activo' => true,
            ];
            return $d;
        });
    if ($ok) {
        plIncorporarEquiposATemporadaActiva([$id]);
    }
    return ['ok' => $ok, 'id' => $id, 'mensaje' => $ok ? '' : 'No se pudo guardar el equipo.'];
}

function plEditarEquipo(string $id, string $nombre, string $abreviatura, string $nombreEn): array
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return ['ok' => false, 'mensaje' => 'El nombre del equipo no puede quedar vacío.'];
    }
    $encontrado = false;
    $ok = plActualizarJson(plRutaDatos('equipos.json'), ['equipos' => []],
        static function (array $d) use ($id, $nombre, $abreviatura, $nombreEn, &$encontrado): ?array {
            foreach ($d['equipos'] as $i => $e) {
                if ((string) ($e['id'] ?? '') === $id) {
                    $d['equipos'][$i]['nombre']      = $nombre;
                    $d['equipos'][$i]['abreviatura'] = strtoupper(trim($abreviatura));
                    $d['equipos'][$i]['nombre_en']   = trim($nombreEn);
                    $encontrado = true;
                    return $d;
                }
            }
            return null;
        });
    if (!$encontrado) {
        return ['ok' => false, 'mensaje' => 'Ese equipo ya no existe.'];
    }
    return ['ok' => $ok, 'mensaje' => $ok ? '' : 'No se pudo guardar el equipo.'];
}

// Archivar no saca al equipo de la temporada en curso: eso borraría su
// plantilla. Solo deja de entrar en las temporadas nuevas. Reactivar sí lo
// incorpora a la temporada abierta si no estaba.
function plCambiarActivoEquipo(string $id, bool $activo): array
{
    $encontrado = false;
    $ok = plActualizarJson(plRutaDatos('equipos.json'), ['equipos' => []],
        static function (array $d) use ($id, $activo, &$encontrado): ?array {
            foreach ($d['equipos'] as $i => $e) {
                if ((string) ($e['id'] ?? '') === $id) {
                    $d['equipos'][$i]['activo'] = $activo;
                    $encontrado = true;
                    return $d;
                }
            }
            return null;
        });
    if (!$encontrado) {
        return ['ok' => false, 'mensaje' => 'Ese equipo ya no existe.'];
    }
    if ($ok && $activo) {
        plIncorporarEquiposATemporadaActiva([$id]);
    }
    return ['ok' => $ok, 'mensaje' => $ok ? '' : 'No se pudo guardar el equipo.'];
}

// Da entrada en la temporada abierta, con la plantilla vacía, a los equipos
// que aún no la tengan. Sin esto, el orden natural de un primer uso —crear la
// temporada y DESPUÉS importar los equipos— dejaría una temporada sin nadie, y
// todos los presidentes verían «tu equipo no forma parte de la temporada».
// Solo añade: nunca toca la plantilla ni el rev de un equipo que ya estaba.
function plIncorporarEquiposATemporadaActiva(array $equipoIds): bool
{
    $temporada = plTemporadaActiva();
    if ($temporada === null || $equipoIds === []) {
        return true;
    }
    return plActualizarJson(plRutaDatos('temporada-' . $temporada['id'] . '.json'), [],
        static function (array $data) use ($equipoIds): ?array {
            if (!isset($data['equipos']) || !is_array($data['equipos'])) {
                return null;
            }
            $cambio = false;
            foreach ($equipoIds as $id) {
                if (!isset($data['equipos'][$id])) {
                    $data['equipos'][$id] = ['rev' => 0, 'jugadores' => []];
                    $cambio = true;
                }
            }
            return $cambio ? $data : null;
        });
}

// ------------------------------------------------------ operaciones de admin

// Lo que hace el admin sobre una plantilla, en CUALQUIER fase. Se salta el
// orden de las fases, pero no la aritmética: cada operación pasa por los
// mismos validadores de dominio.php que las pantallas del presidente.
//
// Viven aquí y no en admin_plantillas.php porque el panel no se puede
// renderizar en un test —su primera línea es el Basic Auth, que lee
// config/secrets.php—, y una regla que no se puede probar es una regla que
// algún día deja de cumplirse sin que nadie se entere.
//
// Devuelven ['ok', 'error' => clave de i18n o null, 'datos', 'mensaje']. Los
// errores de dominio van como clave (la pantalla los enseña en español con
// plTextoEs); los propios del admin, ya como frase en 'mensaje'.

function plResultadoAdmin(bool $ok, ?string $error = null, array $datos = [], string $mensaje = ''): array
{
    return ['ok' => $ok, 'error' => $error, 'datos' => $datos, 'mensaje' => $mensaje];
}

// Guardado común de las operaciones de admin, con el rev que trajo el
// formulario: si un presidente guardó mientras el admin editaba, se rechaza
// igual que entre copresidentes, en vez de pisar su cambio.
function plAdminGuardar(string $temporadaId, string $equipoId, array $jugadores, int $rev): array
{
    $g = plGuardarEquipoTemporada($temporadaId, $equipoId, ['jugadores' => $jugadores], $rev);
    if ($g['ok']) {
        return plResultadoAdmin(true);
    }
    return plResultadoAdmin(false, null, [], match ($g['error'] ?? '') {
        'error.rev_desfasado'          => 'Alguien ha guardado esta plantilla mientras la editabas. Recarga y repite el cambio.',
        'error.equipo_no_en_temporada' => 'Ese equipo no está en la temporada en curso.',
        default                        => 'No se pudo guardar la plantilla.',
    });
}

function plAdminAltaJugador(string $temporadaId, string $equipoId, array $nuevo, int $rev): array
{
    $e = plEquipoEnTemporada($temporadaId, $equipoId);
    if ($e === null) {
        return plResultadoAdmin(false, null, [], 'Ese equipo no está en la temporada en curso.');
    }
    $nuevo['nombre'] = trim((string) ($nuevo['nombre'] ?? ''));
    $v = plValidarAltaJugador($e['jugadores'], $nuevo, $e['ajustes']);
    if (!$v['ok']) {
        return plResultadoAdmin(false, $v['error'], $v['datos']);
    }
    $id = plNuevoIdJugador();
    $jugadores = $e['jugadores'];
    $jugadores[] = [
        'id' => $id, 'nombre' => $nuevo['nombre'], 'posicion' => (string) $nuevo['posicion'],
        'tier' => (string) $nuevo['tier'], 'salario' => $v['datos']['salario'], 'clausula' => 0,
        'estado' => 'DISPONIBLE', 'clausuladoPor' => null, 'clausuladoEn' => null,
    ];
    return plAdminGuardar($temporadaId, $equipoId, $jugadores, $rev) + ['id' => $id];
}

function plAdminEditarJugador(string $temporadaId, string $equipoId, string $jugadorId, string $nombre,
                              string $posicion, string $tier, int $rev): array
{
    $e = plEquipoEnTemporada($temporadaId, $equipoId);
    if ($e === null) {
        return plResultadoAdmin(false, null, [], 'Ese equipo no está en la temporada en curso.');
    }
    $nombre = trim($nombre);
    if ($nombre === '') {
        return plResultadoAdmin(false, 'error.nombre_vacio');
    }
    if (!in_array($posicion, PL_POSICIONES, true)) {
        return plResultadoAdmin(false, 'error.posicion_invalida');
    }
    // Sustituye el salario del jugador en el total: bajar de tier nunca se
    // rechaza por cap. Si sube por encima, la frase dice "cambiar", no "añadir".
    $v = plValidarCambioTier($e['jugadores'], $jugadorId, $tier, $e['ajustes']);
    if (!$v['ok']) {
        $clave = $v['error'] === 'error.cap_superado' ? 'error.cap_superado_cambio' : $v['error'];
        return plResultadoAdmin(false, $clave, $v['datos']);
    }
    $jugadores = $e['jugadores'];
    foreach ($jugadores as $i => $j) {
        if ((string) ($j['id'] ?? '') === $jugadorId) {
            $jugadores[$i] = array_merge($j, ['nombre' => $nombre, 'posicion' => $posicion,
                                               'tier' => $tier, 'salario' => $v['datos']['salario']]);
        }
    }
    return plAdminGuardar($temporadaId, $equipoId, $jugadores, $rev);
}

function plAdminBorrarJugador(string $temporadaId, string $equipoId, string $jugadorId, int $rev): array
{
    $e = plEquipoEnTemporada($temporadaId, $equipoId);
    if ($e === null) {
        return plResultadoAdmin(false, null, [], 'Ese equipo no está en la temporada en curso.');
    }
    $jugadores = array_values(array_filter($e['jugadores'],
        static fn($j) => (string) ($j['id'] ?? '') !== $jugadorId));
    if (count($jugadores) === count($e['jugadores'])) {
        return plResultadoAdmin(false, null, [], 'Ese jugador ya no está en la plantilla.');
    }
    return plAdminGuardar($temporadaId, $equipoId, $jugadores, $rev);
}

function plAdminGuardarClausulas(string $temporadaId, string $equipoId, array $clausulas, int $rev): array
{
    $e = plEquipoEnTemporada($temporadaId, $equipoId);
    if ($e === null) {
        return plResultadoAdmin(false, null, [], 'Ese equipo no está en la temporada en curso.');
    }
    // Mismo criterio que la pantalla del presidente: un campo vaciado es 0.
    $limpias = [];
    foreach ($clausulas as $id => $valor) {
        $texto = is_int($valor) ? (string) $valor : trim((string) $valor);
        $limpias[(string) $id] = $texto === '' ? '0' : $texto;
    }
    $v = plValidarClausulas($e['jugadores'], $limpias, $e['ajustes']);
    if (!$v['ok']) {
        return plResultadoAdmin(false, $v['error'], $v['datos']);
    }
    $jugadores = $e['jugadores'];
    foreach ($jugadores as $i => $j) {
        $jugadores[$i]['clausula'] = (int) ($limpias[(string) ($j['id'] ?? '')] ?? 0);
    }
    return plAdminGuardar($temporadaId, $equipoId, $jugadores, $rev);
}

// La vía de respaldo de la especificación (§23): el admin fija el estado de un
// jugador y quién lo clausuló, en cualquier fase, también para deshacer una
// clausulación mal registrada. Cada corrección real deja un evento CORRECCION
// con el estado de antes y el de después; una "corrección" que no cambia nada
// no escribe ni ensucia el registro.
function plCorregirClausulacion(string $temporadaId, string $equipoId, string $jugadorId, string $estado,
                                string $comprador, int $rev): array
{
    if (!in_array($estado, PL_ESTADOS, true)) {
        return plResultadoAdmin(false, null, [], 'Ese estado no existe.');
    }
    $e = plEquipoEnTemporada($temporadaId, $equipoId);
    if ($e === null) {
        return plResultadoAdmin(false, null, [], 'Ese equipo no está en la temporada en curso.');
    }
    $indice = null;
    foreach ($e['jugadores'] as $i => $j) {
        if ((string) ($j['id'] ?? '') === $jugadorId) {
            $indice = $i;
            break;
        }
    }
    if ($indice === null) {
        return plResultadoAdmin(false, null, [], 'Ese jugador ya no está en la plantilla.');
    }

    if ($estado === 'CLAUSULADO') {
        $enTemporada = array_map('strval', array_keys(plCargarTemporada($temporadaId)['equipos'] ?? []));
        if ($comprador === '' || !in_array($comprador, $enTemporada, true)) {
            return plResultadoAdmin(false, null, [], 'Elige qué equipo lo clausuló.');
        }
        if ($comprador === $equipoId) {
            return plResultadoAdmin(false, null, [], 'Un jugador no puede estar clausulado por su propio equipo.');
        }
    }

    $jugador = $e['jugadores'][$indice];
    $antes   = ['estado' => (string) ($jugador['estado'] ?? 'DISPONIBLE'), 'clausuladoPor' => $jugador['clausuladoPor'] ?? null];
    $despues = $estado === 'CLAUSULADO'
        ? ['estado' => 'CLAUSULADO', 'clausuladoPor' => $comprador]
        : ['estado' => 'DISPONIBLE', 'clausuladoPor' => null];

    if ($antes == $despues) {
        return plResultadoAdmin(true, null, [], 'Sin cambios: el jugador ya estaba así.');
    }

    $jugadores = $e['jugadores'];
    $jugadores[$indice]['estado']        = $despues['estado'];
    $jugadores[$indice]['clausuladoPor'] = $despues['clausuladoPor'];
    $jugadores[$indice]['clausuladoEn']  = $estado === 'CLAUSULADO' ? plAhora() : null;

    $r = plAdminGuardar($temporadaId, $equipoId, $jugadores, $rev);
    if ($r['ok']) {
        plRegistrarEvento('CORRECCION', 'admin', 'admin', [
            'temporada' => $temporadaId,
            'jugadorId' => $jugadorId,
            'jugador'   => (string) ($jugador['nombre'] ?? ''),
            'equipo'    => $equipoId,
            'antes'     => $antes,
            'despues'   => $despues,
        ]);
    }
    return $r;
}

// El registro, del evento más reciente al más antiguo, recortado a los
// $limite últimos: es lo que el admin consulta para arbitrar una disputa.
function plCargarRegistroReciente(int $limite = 200): array
{
    $eventos = plCargarJson(plRutaDatos('registro.json'), ['eventos' => []])['eventos'] ?? [];
    return array_slice(array_reverse($eventos), 0, max(0, $limite));
}

// ----------------------------------------------------------- presidentes

// La especificación no fija un mínimo. Ocho caracteres es la base habitual, y
// aquí la pone el admin a mano, así que no hay excusa para una más corta.
const PL_CLAVE_MINIMA = 8;

// Mensaje en español (panel de admin) si algo no vale, o null si todo vale.
function plValidarDatosPresidente(string $nombre, string $email, ?string $equipoId): ?string
{
    if ($nombre === '') {
        return 'Escribe el nombre del presidente.';
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Ese email no es válido.';
    }
    if ($equipoId !== null && plBuscarEquipo($equipoId) === null) {
        return 'Ese equipo no existe.';
    }
    return null;
}

// Alta de un presidente. La contraseña llega en claro y se hashea AQUÍ: es el
// único punto por el que pasa cualquier alta, así que ninguna pantalla, de hoy
// o futura, puede guardar una contraseña en claro aunque se le olvide hashearla.
//
// El email se guarda en minúsculas y su unicidad se comprueba DENTRO del lock,
// en la misma operación que el alta: si se mirase antes, dos altas simultáneas
// con el mismo correo pasarían las dos la comprobación.
function plCrearPresidente(string $nombre, string $email, string $clave, ?string $equipoId, bool $activo): array
{
    $nombre = trim($nombre);
    $email  = mb_strtolower(trim($email), 'UTF-8');

    $error = plValidarDatosPresidente($nombre, $email, $equipoId);
    if ($error === null && mb_strlen($clave, 'UTF-8') < PL_CLAVE_MINIMA) {
        $error = 'La contraseña tiene que tener al menos ' . PL_CLAVE_MINIMA . ' caracteres.';
    }
    if ($error !== null) {
        return ['ok' => false, 'id' => null, 'mensaje' => $error];
    }

    $id        = 'u_' . bin2hex(random_bytes(4));
    $hash      = password_hash($clave, PASSWORD_DEFAULT);
    $duplicado = false;
    $ok = plActualizarJson(plRutaDatos('usuarios.json'), ['usuarios' => []],
        static function (array $d) use ($id, $nombre, $email, $hash, $equipoId, $activo, &$duplicado): ?array {
            foreach ($d['usuarios'] as $u) {
                if (mb_strtolower((string) ($u['email'] ?? ''), 'UTF-8') === $email) {
                    $duplicado = true;
                    return null;
                }
            }
            $d['usuarios'][] = ['id' => $id, 'nombre' => $nombre, 'email' => $email, 'hash' => $hash,
                                'equipoId' => $equipoId, 'activo' => $activo];
            return $d;
        });

    if ($duplicado) {
        return ['ok' => false, 'id' => null, 'mensaje' => 'Ya hay un presidente con ese email.'];
    }
    return ['ok' => $ok, 'id' => $id, 'mensaje' => $ok ? '' : 'No se pudo guardar el presidente.'];
}

// Edición. $claveNueva vacía significa "no cambiarla" y conserva el hash: el
// error clásico aquí es rehashear la cadena vacía del campo sin rellenar y
// dejar al presidente sin poder entrar, con el síntoma apareciendo días después.
// Reasignar el equipo es solo cambiar equipoId: no se guarda histórico.
function plEditarPresidente(string $id, string $nombre, string $email, string $claveNueva, ?string $equipoId): array
{
    $nombre = trim($nombre);
    $email  = mb_strtolower(trim($email), 'UTF-8');

    $error = plValidarDatosPresidente($nombre, $email, $equipoId);
    if ($error === null && $claveNueva !== '' && mb_strlen($claveNueva, 'UTF-8') < PL_CLAVE_MINIMA) {
        $error = 'La contraseña nueva tiene que tener al menos ' . PL_CLAVE_MINIMA . ' caracteres.';
    }
    if ($error !== null) {
        return ['ok' => false, 'mensaje' => $error];
    }

    $hashNuevo  = $claveNueva === '' ? null : password_hash($claveNueva, PASSWORD_DEFAULT);
    $encontrado = false;
    $duplicado  = false;
    $ok = plActualizarJson(plRutaDatos('usuarios.json'), ['usuarios' => []],
        static function (array $d) use ($id, $nombre, $email, $hashNuevo, $equipoId, &$encontrado, &$duplicado): ?array {
            $indice = null;
            foreach ($d['usuarios'] as $i => $u) {
                if ((string) ($u['id'] ?? '') === $id) {
                    $indice = $i;
                } elseif (mb_strtolower((string) ($u['email'] ?? ''), 'UTF-8') === $email) {
                    $duplicado = true;   // ese email ya es de OTRO presidente
                }
            }
            if ($indice === null || $duplicado) {
                $encontrado = $indice !== null;
                return null;
            }
            $encontrado = true;
            $d['usuarios'][$indice]['nombre']   = $nombre;
            $d['usuarios'][$indice]['email']    = $email;
            $d['usuarios'][$indice]['equipoId'] = $equipoId;
            if ($hashNuevo !== null) {
                $d['usuarios'][$indice]['hash'] = $hashNuevo;
            }
            return $d;
        });

    if (!$encontrado) {
        return ['ok' => false, 'mensaje' => 'Ese presidente ya no existe.'];
    }
    if ($duplicado) {
        return ['ok' => false, 'mensaje' => 'Ya hay otro presidente con ese email.'];
    }
    return ['ok' => $ok, 'mensaje' => $ok ? '' : 'No se pudo guardar el presidente.'];
}

// Desactivar corta el acceso en la siguiente petición: plUsuarioActual()
// relee usuarios.json cada vez, así que no hay que esperar a que caduque nada.
function plCambiarActivoPresidente(string $id, bool $activo): array
{
    $encontrado = false;
    $ok = plActualizarJson(plRutaDatos('usuarios.json'), ['usuarios' => []],
        static function (array $d) use ($id, $activo, &$encontrado): ?array {
            foreach ($d['usuarios'] as $i => $u) {
                if ((string) ($u['id'] ?? '') === $id) {
                    $d['usuarios'][$i]['activo'] = $activo;
                    $encontrado = true;
                    return $d;
                }
            }
            return null;
        });
    if (!$encontrado) {
        return ['ok' => false, 'mensaje' => 'Ese presidente ya no existe.'];
    }
    return ['ok' => $ok, 'mensaje' => $ok ? '' : 'No se pudo guardar el presidente.'];
}

// Cuántos presidentes ACTIVOS lleva cada equipo. Sirve para señalar a los
// copresidentes, que son justo los equipos donde el rev va a entrar en juego.
function plPresidentesPorEquipo(): array
{
    $cuenta = [];
    foreach (plCargarUsuarios()['usuarios'] as $u) {
        $eq = $u['equipoId'] ?? null;
        if ($eq !== null && !empty($u['activo'])) {
            $cuenta[(string) $eq] = ($cuenta[(string) $eq] ?? 0) + 1;
        }
    }
    return $cuenta;
}

// -------------------------------------------------------------- usuarios

function plBuscarUsuarioPorEmail(string $email): ?array
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    foreach (plCargarUsuarios()['usuarios'] as $u) {
        if (mb_strtolower((string) ($u['email'] ?? ''), 'UTF-8') === $email) {
            return $u;
        }
    }
    return null;
}

// Relee usuarios.json en cada petición a propósito: así, desactivar a un
// presidente le corta el acceso en su siguiente clic, sin esperar a que
// caduque ninguna sesión.
function plUsuarioActual(): ?array
{
    $id = $_SESSION['pl_usuario_id'] ?? null;
    if ($id === null) {
        return null;
    }
    foreach (plCargarUsuarios()['usuarios'] as $u) {
        if ((string) ($u['id'] ?? '') === (string) $id) {
            return !empty($u['activo']) ? $u : null;
        }
    }
    return null;
}

// La entrada de un equipo dentro del fichero de una temporada —su rev y sus
// jugadores—, junto con los ajustes congelados de esa temporada, que es lo que
// necesita cualquier pantalla para pintar o validar. null si el equipo no
// entró en la temporada (estaba archivado cuando se creó, o es nuevo).
function plEquipoEnTemporada(string $temporadaId, string $equipoId): ?array
{
    $datos = plCargarTemporada($temporadaId);
    if (!isset($datos['equipos'][$equipoId])) {
        return null;
    }
    $entrada = $datos['equipos'][$equipoId];
    return [
        'rev'       => (int) ($entrada['rev'] ?? 0),
        'jugadores' => $entrada['jugadores'] ?? [],
        'ajustes'   => $datos['ajustes'] ?? [],
    ];
}

// Identificador de un jugador nuevo. Aleatorio y no correlativo a propósito:
// con dos copresidentes añadiendo a la vez, un contador "último + 1" daría el
// mismo id a los dos, y el rev solo protege el fichero, no la unicidad del id.
function plNuevoIdJugador(): string
{
    return 'j_' . bin2hex(random_bytes(6));
}

function plBuscarEquipo(string $equipoId): ?array
{
    foreach (plCargarEquipos()['equipos'] as $e) {
        if ((string) ($e['id'] ?? '') === $equipoId) {
            return $e;
        }
    }
    return null;
}
