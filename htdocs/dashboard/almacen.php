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

// La única semilla con contenido: sin ella la primera temporada nacería sin
// tabla de salarios. Son los diez tiers oficiales; C+, C- y D no existen.
function plCargarTiers(): array
{
    return plCargarJson(plRutaDatos('tiers.json'), ['tiers' => [
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
    ]]);
}

function plGuardarTiers(array $data): bool
{
    return plGuardarJsonAtomico(plRutaDatos('tiers.json'), $data);
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

    // Solo se convierte en la activa si el fichero se escribió de verdad.
    $temporadas = plCargarTemporadas();
    $temporadas['temporadas'][] = [
        'id' => $id, 'nombre' => $nombre, 'fase' => 'ROSTER', 'creada' => plAhora(),
    ];
    $temporadas['activa'] = $id;
    if (!plGuardarTemporadas($temporadas)) {
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
    $t = plCargarTemporadas();
    foreach ($t['temporadas'] as $i => $temporada) {
        if (($temporada['id'] ?? null) === $temporadaId) {
            $t['temporadas'][$i]['fase'] = $fase;
            return ['ok' => plGuardarTemporadas($t), 'error' => null];
        }
    }
    return ['ok' => false, 'error' => 'error.temporada_no_encontrada'];
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
    $ruta = plRutaDatos("temporada-$temporadaId.json");

    $lock = fopen($ruta . '.lock', 'c');
    if ($lock === false) {
        return ['ok' => false, 'error' => 'error.lock'];
    }
    flock($lock, LOCK_EX);

    $data = plCargarJson($ruta, []);
    $revDisco = (int) ($data['equipos'][$equipoId]['rev'] ?? -1);

    if ($revDisco === -1) {
        flock($lock, LOCK_UN);
        fclose($lock);
        return ['ok' => false, 'error' => 'error.equipo_no_en_temporada'];
    }
    if ($revDisco !== $revEsperado) {
        flock($lock, LOCK_UN);
        fclose($lock);
        // Se rechaza sin escribir NADA: el presidente recarga y repite.
        return ['ok' => false, 'error' => 'error.rev_desfasado', 'rev' => $revDisco];
    }

    $datos['rev'] = $revDisco + 1;
    $data['equipos'][$equipoId] = $datos;

    // Misma escritura atómica que plGuardarJsonAtomico(), pero en línea para
    // no soltar y volver a tomar el lock entre la comprobación y el rename.
    $ok  = false;
    $tmp = tempnam(dirname($ruta), 'pl_');
    if ($tmp !== false) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ok = $json !== false
            && file_put_contents($tmp, $json) !== false
            && chmod($tmp, 0644)
            && rename($tmp, $ruta);
        if (!$ok && file_exists($tmp)) {
            unlink($tmp);
        }
    }

    flock($lock, LOCK_UN);
    fclose($lock);

    return ['ok' => $ok, 'error' => $ok ? null : 'error.escritura', 'rev' => $datos['rev']];
}

// ------------------------------------------------------------- registro

// Append-only: nunca se modifica ni se borra una entrada existente. Es la
// única forma de arbitrar una disputa de clausulación con datos en vez de con
// memoria, y por eso el admin puede corregir el estado de un jugador pero no
// puede reescribir el registro de quién lo marcó.
function plRegistrarEvento(string $tipo, string $actor, string $actorNombre, array $detalle): bool
{
    $ruta = plRutaDatos('registro.json');
    $registro = plCargarJson($ruta, ['eventos' => []]);
    $registro['eventos'][] = [
        'ts'          => plAhora(),
        'actor'       => $actor,
        'actorNombre' => $actorNombre,
        'tipo'        => $tipo,
        'detalle'     => $detalle,
    ];
    return plGuardarJsonAtomico($ruta, $registro);
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

function plBuscarEquipo(string $equipoId): ?array
{
    foreach (plCargarEquipos()['equipos'] as $e) {
        if ((string) ($e['id'] ?? '') === $equipoId) {
            return $e;
        }
    }
    return null;
}
