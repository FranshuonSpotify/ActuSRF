<?php
// supertecnicas/lib.php
// Helpers compartidos de la herramienta de supertécnicas: acceso a
// datos_oficiales.json y a los ficheros propios de esta herramienta, con
// lectura/escritura atómica. Sin clases, siguiendo el estilo de
// api/recalcular.php y api/discord_update.php.

// defined() antes de define(): así un test puede fijar estas rutas ANTES de
// incluir este fichero y trabajar sobre ficheros temporales, en vez de sobre
// datos_oficiales.json y las cuentas reales. Sin esto no hay forma de probar
// el registro sin escribir en producción.
defined('ST_DATA_JSON')     || define('ST_DATA_JSON', __DIR__ . '/../datos_oficiales.json');
defined('ST_CODIGOS_JSON')  || define('ST_CODIGOS_JSON', __DIR__ . '/data/codigos_equipos.json');
defined('ST_CONFIG_JSON')   || define('ST_CONFIG_JSON', __DIR__ . '/data/config.json');
defined('ST_USUARIOS_JSON')    || define('ST_USUARIOS_JSON', __DIR__ . '/data/usuarios.json');
defined('ST_INVITACIONES_JSON') || define('ST_INVITACIONES_JSON', __DIR__ . '/data/invitaciones.json');

const ST_MAX_SUPERTECNICAS = 4;
const ST_TIPOS = ['', 'tiro', 'regate', 'bloqueo', 'parada'];
const ST_AFINIDADES = ['', 'neutro', 'fuego', 'montaña', 'bosque', 'aire'];

function stNormalizarTexto($texto) {
    // Mapeo explícito de acentos a ASCII equivalentes
    $accents = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ',
                'à', 'è', 'ì', 'ò', 'ù', 'À', 'È', 'Ì', 'Ò', 'Ù',
                'â', 'ê', 'î', 'ô', 'û', 'Â', 'Ê', 'Î', 'Ô', 'Û',
                'ä', 'ë', 'ï', 'ö', 'ü', 'Ä', 'Ë', 'Ï', 'Ö', 'Ü'];
    $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U',
                'a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'];
    $texto = str_replace($accents, $replace, $texto);
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
    return trim($texto);
}

function stCargarJson($ruta, array $porDefecto) {
    if (!file_exists($ruta)) return $porDefecto;
    $contenido = file_get_contents($ruta);
    $data = json_decode($contenido, true);
    return is_array($data) ? $data : $porDefecto;
}

// Escritura atómica: fichero temporal en el mismo directorio + rename(),
// bajo un lock exclusivo sobre "$ruta.lock" para que dos guardados
// simultáneos no se pisen ni una lectura vea un JSON a medias.
function stGuardarJsonAtomico($ruta, array $data) {
    $directorio = dirname($ruta);
    if (!is_dir($directorio)) mkdir($directorio, 0755, true);

    $lock = fopen($ruta . '.lock', 'c');
    if (!$lock) return false;
    flock($lock, LOCK_EX);

    $tmp = tempnam($directorio, 'st_');
    $ok = false;
    if ($tmp !== false) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ok = file_put_contents($tmp, $json) !== false && chmod($tmp, 0644) && rename($tmp, $ruta);
        if (!$ok && file_exists($tmp)) unlink($tmp);
    }

    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

function stCargarDatosOficiales() {
    return stCargarJson(ST_DATA_JSON, ['equipos' => []]);
}

function stGuardarDatosOficiales(array $data) {
    return stGuardarJsonAtomico(ST_DATA_JSON, $data);
}

function stCargarCodigos() {
    return stCargarJson(ST_CODIGOS_JSON, []);
}

function stGuardarCodigos(array $codigos) {
    return stGuardarJsonAtomico(ST_CODIGOS_JSON, $codigos);
}

function stCargarConfig() {
    $config = stCargarJson(ST_CONFIG_JSON, ['ventana_abierta' => false]);
    $config['ventana_abierta'] = !empty($config['ventana_abierta']);
    return $config;
}

function stGuardarConfig(array $config) {
    return stGuardarJsonAtomico(ST_CONFIG_JSON, $config);
}

function stEquiposActivos(array $data) {
    $equipos = $data['equipos'] ?? [];
    return array_values(array_filter($equipos, function ($e) {
        return empty($e['archivado']);
    }));
}

function stBuscarEquipoPorId(array $data, $equipoId) {
    foreach (($data['equipos'] ?? []) as $i => $equipo) {
        if ((string) ($equipo['id'] ?? '') === (string) $equipoId) return $i;
    }
    return null;
}

function stEsc($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

// Protección CSRF de sinónimo (synchronizer token): un token por sesión,
// comparado con hash_equals(). index.php (con sesión de presidente) y
// admin.php (con session_start() propio añadido para esto, aparte del
// Basic Auth) comparten este mismo mecanismo.
function stTokenCsrf() {
    if (empty($_SESSION['st_csrf'])) {
        $_SESSION['st_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['st_csrf'];
}

function stCsrfValido() {
    $enviado = $_POST['csrf'] ?? '';
    return !empty($_SESSION['st_csrf']) && is_string($enviado) && hash_equals($_SESSION['st_csrf'], $enviado);
}

// Valor por defecto de código/PIN para un equipo sin entrada todavía en
// codigos_equipos.json: nombre del equipo / ciudad, normalizados.
function stCodigoPorDefecto(array $equipo) {
    return [
        'codigo' => stNormalizarTexto($equipo['nombre'] ?? ''),
        'pin' => stNormalizarTexto($equipo['ciudad'] ?? ''),
    ];
}

// --------------------------------------------------------- cuentas de acceso
// Mismo modelo que dashboard/: cada presidente se registra él mismo con correo
// y contraseña y elige su equipo, en vez de recibir un código y un PIN creados
// por el admin. stCargarCodigos()/stCodigoPorDefecto() siguen aquí porque
// codigos_equipos.json sigue en el servidor, pero el login ya no los mira: el
// día que haya que revertir esto, el fichero está intacto.

const ST_CLAVE_MINIMA = 8;
const ST_MAX_PRESIDENTES_POR_EQUIPO = 2;

function stCargarUsuarios() {
    return stCargarJson(ST_USUARIOS_JSON, ['usuarios' => []]);
}

// Lectura-modificación-escritura bajo UN solo lock exclusivo. No reutiliza
// stGuardarJsonAtomico() a propósito: ese toma su propio flock sobre el mismo
// ".lock", y pedirlo por segunda vez desde el mismo proceso se quedaría
// esperándose a sí mismo. El callback devuelve null para abortar sin escribir.
function stActualizarJson($ruta, array $porDefecto, callable $cambio) {
    $directorio = dirname($ruta);
    if (!is_dir($directorio)) mkdir($directorio, 0755, true);

    $lock = fopen($ruta . '.lock', 'c');
    if (!$lock) return false;
    flock($lock, LOCK_EX);

    // Lectura directa y no stCargarJson(): el lock ya está tomado aquí.
    $actual = $porDefecto;
    if (file_exists($ruta)) {
        $decodificado = json_decode((string) file_get_contents($ruta), true);
        if (is_array($decodificado)) $actual = $decodificado;
    }

    $nuevo = $cambio($actual);
    $ok = false;
    if ($nuevo !== null) {
        $tmp = tempnam($directorio, 'st_');
        if ($tmp !== false) {
            $json = json_encode($nuevo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $ok = file_put_contents($tmp, $json) !== false && chmod($tmp, 0644) && rename($tmp, $ruta);
            if (!$ok && file_exists($tmp)) unlink($tmp);
        }
    }

    flock($lock, LOCK_UN);
    fclose($lock);
    return $ok;
}

function stBuscarUsuarioPorEmail($email) {
    $email = mb_strtolower(trim((string) $email), 'UTF-8');
    foreach (stCargarUsuarios()['usuarios'] as $u) {
        if (mb_strtolower((string) ($u['email'] ?? ''), 'UTF-8') === $email) return $u;
    }
    return null;
}

// Relee usuarios.json en cada petición a propósito: desactivar una cuenta le
// corta el acceso en su siguiente clic, sin esperar a que caduque la sesión.
function stUsuarioActual() {
    $id = $_SESSION['st_usuario_id'] ?? null;
    if ($id === null) return null;
    foreach (stCargarUsuarios()['usuarios'] as $u) {
        if ((string) ($u['id'] ?? '') === (string) $id) {
            return !empty($u['activo']) ? $u : null;
        }
    }
    return null;
}

// Auto-registro. Devuelve claves de i18n en 'error', no frases: la pantalla
// que la llama está traducida a diez idiomas. El correo duplicado y el cupo del
// equipo se comprueban DENTRO del lock, por lo mismo que en dashboard/: dos
// registros simultáneos sobre la última plaza pasarían los dos si se mirasen
// antes.
// ----------------------------------------------------------- invitaciones
// Una invitación por equipo. El primero que se registra en un club lo bloquea;
// la segunda plaza solo se abre con un código que genera el presidente que ya
// está dentro. Mismo modelo que dashboard/.

function stCargarInvitaciones() {
    return stCargarJson(ST_INVITACIONES_JSON, ['invitaciones' => []]);
}

// Seis caracteres hex en mayúsculas: se dicta por Discord y se teclea a mano.
function stNuevoCodigoInvitacion() {
    return strtoupper(bin2hex(random_bytes(3)));
}

function stInvitacionDe($equipoId) {
    foreach (stCargarInvitaciones()['invitaciones'] as $i) {
        if ((string) ($i['equipoId'] ?? '') === (string) $equipoId) return $i;
    }
    return null;
}

// Reemplaza la invitación que hubiera: generar otro código invalida el
// anterior, y es la única forma de retirar uno ya compartido de más.
function stCrearInvitacion($equipoId, $actorId, $actorNombre) {
    $codigo = stNuevoCodigoInvitacion();
    $equipoId = (string) $equipoId;
    $ok = stActualizarJson(ST_INVITACIONES_JSON, ['invitaciones' => []],
        function (array $d) use ($equipoId, $codigo, $actorId, $actorNombre) {
            $d['invitaciones'] = array_values(array_filter(
                $d['invitaciones'],
                function ($i) use ($equipoId) { return (string) ($i['equipoId'] ?? '') !== $equipoId; }
            ));
            $d['invitaciones'][] = ['equipoId' => $equipoId, 'codigo' => $codigo,
                                    'creadaPor' => (string) $actorId, 'creadaPorNombre' => (string) $actorNombre,
                                    'creada' => date('c')];
            return $d;
        });
    return ['ok' => $ok, 'codigo' => $ok ? $codigo : null];
}

// Borra la invitación y devuelve true SOLO al primero que llegue con el código
// correcto: dos personas con el mismo código no pueden entrar las dos.
function stConsumirInvitacion($equipoId, $codigo) {
    $equipoId = (string) $equipoId;
    $codigo = strtoupper(trim((string) $codigo));
    if ($codigo === '') return false;

    $consumida = false;
    stActualizarJson(ST_INVITACIONES_JSON, ['invitaciones' => []],
        function (array $d) use ($equipoId, $codigo, &$consumida) {
            foreach ($d['invitaciones'] as $n => $i) {
                if ((string) ($i['equipoId'] ?? '') === $equipoId
                    && hash_equals((string) ($i['codigo'] ?? ''), $codigo)) {
                    unset($d['invitaciones'][$n]);
                    $d['invitaciones'] = array_values($d['invitaciones']);
                    $consumida = true;
                    return $d;
                }
            }
            return null;
        });
    return $consumida;
}

// Cuántas cuentas ACTIVAS tiene cada equipo.
function stPresidentesPorEquipo() {
    $cuenta = [];
    foreach (stCargarUsuarios()['usuarios'] as $u) {
        $eq = (string) ($u['equipoId'] ?? '');
        if ($eq !== '' && !empty($u['activo'])) {
            $cuenta[$eq] = ($cuenta[$eq] ?? 0) + 1;
        }
    }
    return $cuenta;
}

function stRegistrarUsuario($nombre, $email, $clave, $claveRepetida, $equipoId, $codigoInvitacion = '') {
    $nombre = trim((string) $nombre);
    $email  = mb_strtolower(trim((string) $email), 'UTF-8');
    $clave  = (string) $clave;

    $mal = function ($claveError, array $datos = []) {
        return ['ok' => false, 'id' => null, 'error' => $claveError, 'datos' => $datos];
    };

    if ($nombre === '') return $mal('registro.error_nombre');
    // Formato, no existencia: un correo inventado vale mientras tenga forma de
    // correo. La pantalla lo dice con todas las letras.
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return $mal('registro.error_email');
    if (mb_strlen($clave, 'UTF-8') < ST_CLAVE_MINIMA) {
        return $mal('registro.error_clave_corta', ['minimo' => ST_CLAVE_MINIMA]);
    }
    if ($clave !== (string) $claveRepetida) return $mal('registro.error_claves_distintas');

    $data = stCargarDatosOficiales();
    $idx  = stBuscarEquipoPorId($data, $equipoId);
    if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
        return $mal('registro.error_equipo');
    }

    $equipoId = (string) $equipoId;

    // Si el equipo ya tiene presidente hace falta invitación, y se consume
    // ANTES de crear la cuenta: es lo que garantiza que, de dos que lleguen a
    // la vez con el mismo código, solo entre uno. Si el alta fallara después,
    // el código se queda gastado y hay que generar otro: falla del lado seguro.
    $yaDentro = stPresidentesPorEquipo()[$equipoId] ?? 0;
    // El cupo se mira ANTES de consumir: si el equipo ya está completo, gastar
    // un código válido para luego rechazar el alta sería tirar la invitación
    // del presidente sin que nadie entre.
    if ($yaDentro >= ST_MAX_PRESIDENTES_POR_EQUIPO) {
        return $mal('registro.error_equipo_lleno', ['maximo' => ST_MAX_PRESIDENTES_POR_EQUIPO]);
    }
    $invitacionConsumida = false;
    if ($yaDentro > 0) {
        $invitacionConsumida = stConsumirInvitacion($equipoId, $codigoInvitacion);
        if (!$invitacionConsumida) return $mal('registro.error_codigo');
    }

    $id   = 'u_' . bin2hex(random_bytes(4));
    $hash = password_hash($clave, PASSWORD_DEFAULT);

    $duplicado = false;
    $lleno = false;
    $sinCodigo = false;
    $ok = stActualizarJson(ST_USUARIOS_JSON, ['usuarios' => []],
        function (array $d) use ($id, $nombre, $email, $hash, $equipoId, $invitacionConsumida, &$duplicado, &$lleno, &$sinCodigo) {
            $enEsteEquipo = 0;
            foreach ($d['usuarios'] as $u) {
                if (mb_strtolower((string) ($u['email'] ?? ''), 'UTF-8') === $email) {
                    $duplicado = true;
                    return null;
                }
                if ((string) ($u['equipoId'] ?? '') === $equipoId && !empty($u['activo'])) {
                    $enEsteEquipo++;
                }
            }
            if ($enEsteEquipo >= ST_MAX_PRESIDENTES_POR_EQUIPO) {
                $lleno = true;
                return null;
            }
            // La regla, donde no puede colarse nadie entre medias: un equipo
            // ocupado solo admite a quien traiga invitación.
            if ($enEsteEquipo > 0 && !$invitacionConsumida) {
                $sinCodigo = true;
                return null;
            }
            $d['usuarios'][] = ['id' => $id, 'nombre' => $nombre, 'email' => $email, 'hash' => $hash,
                                'equipoId' => $equipoId, 'activo' => true,
                                'registrado' => date('c')];
            return $d;
        });

    if ($duplicado) return $mal('registro.error_email_duplicado');
    if ($lleno) return $mal('registro.error_equipo_lleno', ['maximo' => ST_MAX_PRESIDENTES_POR_EQUIPO]);
    if ($sinCodigo) return $mal('registro.error_codigo');
    if (!$ok) return $mal('registro.error_escritura');

    return ['ok' => true, 'id' => $id, 'error' => null, 'datos' => []];
}

function stCambiarActivoUsuario($id, $activo) {
    $encontrado = false;
    $ok = stActualizarJson(ST_USUARIOS_JSON, ['usuarios' => []],
        function (array $d) use ($id, $activo, &$encontrado) {
            foreach ($d['usuarios'] as $i => $u) {
                if ((string) ($u['id'] ?? '') === (string) $id) {
                    $d['usuarios'][$i]['activo'] = (bool) $activo;
                    $encontrado = true;
                    return $d;
                }
            }
            return null;
        });
    return $encontrado && $ok;
}
