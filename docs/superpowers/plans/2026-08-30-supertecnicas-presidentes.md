# Supertécnicas por presidentes — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir `supertecnicas/`, una herramienta PHP nueva e independiente donde cada presidente de equipo (login por código+PIN) asigna hasta 4 supertécnicas a los jugadores de su plantilla, escribiendo directamente en `datos_oficiales.json`, con un panel de admin que abre/cierra la ventana de edición y gestiona los códigos por equipo.

**Architecture:** Todo el estado vive en ficheros planos: `datos_oficiales.json` (compartido con el resto del sitio), y dos ficheros nuevos bajo `supertecnicas/data/` (`codigos_equipos.json`, `config.json`), todos protegidos de descarga directa y escritos con lock+rename atómico. Sin sesión de base de datos, sin JS de cliente: páginas PHP con formularios normales que se envían por POST y redirigen.

**Tech Stack:** PHP 8 puro (sin frameworks), sesiones PHP nativas (`session_start()`), HTTP Basic para el admin (reutilizando el patrón de `config/admin_auth.php`), CSS reutilizando los tokens de `_fuente/styles.css`.

## Global Constraints

- No se toca `transferroom/`, `admin/index.php` ni `includes/auth.php` (código huérfano no usado).
- El esquema de `datos_oficiales.json` no cambia: `equipo.jugadores[].supertecnicas[]` ya existe como array de objetos `{nombre, tipo, afinidad, especial, descripcion}` (`gestor/js/vista-equipos.js:462-479`). El límite de 4 por jugador es exclusivo de esta herramienta.
- `tipo` ∈ `['', 'tiro', 'regate', 'bloqueo', 'parada']`; `afinidad` ∈ `['', 'neutro', 'fuego', 'montaña', 'bosque', 'aire']` (mismos valores que usa `gestor/`).
- Todo texto de usuario se escapa con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` al imprimir en HTML.
- Toda escritura a un fichero JSON compartido usa `flock` + fichero temporal + `rename()` (atómica), nunca `file_put_contents` directo.
- Nombres de función, variable y comentarios en español, siguiendo el estilo ya usado en `api/recalcular.php` y `api/discord_update.php` (funciones sueltas, sin clases).
- `supertecnicas/data/*.json` no debe ser descargable directamente por URL (contienen los PIN de acceso).

---

### Task 1: Librería compartida (`supertecnicas/lib.php`)

**Files:**
- Create: `supertecnicas/lib.php`
- Test: `supertecnicas/tests/test_lib.php`

**Interfaces:**
- Produces (usadas por todas las tareas siguientes):
  - `ST_MAX_SUPERTECNICAS` (int, `const`, valor `4`)
  - `ST_TIPOS` (array de strings, `const`)
  - `ST_AFINIDADES` (array de strings, `const`)
  - `stNormalizarTexto(string $texto): string`
  - `stCargarJson(string $ruta, array $porDefecto): array`
  - `stGuardarJsonAtomico(string $ruta, array $data): bool`
  - `stCargarDatosOficiales(): array`
  - `stGuardarDatosOficiales(array $data): bool`
  - `stCargarCodigos(): array`
  - `stGuardarCodigos(array $codigos): bool`
  - `stCargarConfig(): array` (siempre devuelve `['ventana_abierta' => bool]`)
  - `stGuardarConfig(array $config): bool`
  - `stEquiposActivos(array $data): array` (equipos con `archivado` no `true`)
  - `stBuscarEquipoPorId(array &$data, string $equipoId): ?int` (índice en `$data['equipos']` o `null`)
  - `stCodigoPorDefecto(array $equipo): array` (`['codigo' => ..., 'pin' => ...]`)

- [ ] **Step 1: Crear el fichero de test (fallará porque `lib.php` no existe todavía)**

```php
<?php
// supertecnicas/tests/test_lib.php
// Self-check sin framework: cada verificarX() imprime FAIL y corta con
// exit(1) al primer fallo. Ejecutar con: php supertecnicas/tests/test_lib.php

require_once __DIR__ . '/../lib.php';

$fallos = 0;

function verificar($descripcion, $condicion) {
    global $fallos;
    if ($condicion) {
        echo "OK   $descripcion\n";
    } else {
        echo "FAIL $descripcion\n";
        $fallos++;
    }
}

// -- stNormalizarTexto -------------------------------------------------
verificar(
    'normaliza acentos y mayúsculas',
    stNormalizarTexto('Montaña Épica') === 'montana epica'
);
verificar(
    'colapsa espacios extra',
    stNormalizarTexto('  Monte   Olimpo  ') === 'monte olimpo'
);
verificar(
    'quita símbolos que no son letra/número',
    stNormalizarTexto('a.b-c!d') === 'a b c d'
);

// -- stCargarJson / stGuardarJsonAtomico -------------------------------
$dirTmp = sys_get_temp_dir() . '/st_test_' . uniqid();
mkdir($dirTmp);
$rutaTmp = $dirTmp . '/prueba.json';

verificar(
    'stCargarJson devuelve el valor por defecto si el fichero no existe',
    stCargarJson($rutaTmp, ['x' => 1]) === ['x' => 1]
);

$ok = stGuardarJsonAtomico($rutaTmp, ['equipos' => [1, 2, 3]]);
verificar('stGuardarJsonAtomico devuelve true', $ok === true);
verificar(
    'stGuardarJsonAtomico escribe un JSON legible por stCargarJson',
    stCargarJson($rutaTmp, []) === ['equipos' => [1, 2, 3]]
);
verificar(
    'no quedan ficheros temporales sueltos en el directorio',
    count(glob($dirTmp . '/st_*')) === 0
);

unlink($rutaTmp);
@unlink($rutaTmp . '.lock');
rmdir($dirTmp);

// -- stEquiposActivos / stBuscarEquipoPorId / stCodigoPorDefecto -------
$dataPrueba = [
    'equipos' => [
        ['id' => 'a', 'nombre' => 'Alfa FC', 'ciudad' => 'Ciudad Alfa'],
        ['id' => 'b', 'nombre' => 'Beta FC', 'ciudad' => 'Ciudad Beta', 'archivado' => true],
    ],
];

$activos = stEquiposActivos($dataPrueba);
verificar('stEquiposActivos excluye los archivados', count($activos) === 1 && $activos[0]['id'] === 'a');

$idx = stBuscarEquipoPorId($dataPrueba, 'b');
verificar('stBuscarEquipoPorId encuentra por id', $idx === 1);
verificar('stBuscarEquipoPorId devuelve null si no existe', stBuscarEquipoPorId($dataPrueba, 'z') === null);

$defecto = stCodigoPorDefecto(['nombre' => 'Alfa FC', 'ciudad' => 'Ciudad Alfa']);
verificar(
    'stCodigoPorDefecto normaliza nombre y ciudad',
    $defecto === ['codigo' => 'alfa fc', 'pin' => 'ciudad alfa']
);

// -- constantes ---------------------------------------------------------
verificar('ST_MAX_SUPERTECNICAS es 4', ST_MAX_SUPERTECNICAS === 4);
verificar('ST_TIPOS tiene 5 valores', count(ST_TIPOS) === 5);
verificar('ST_AFINIDADES tiene 6 valores', count(ST_AFINIDADES) === 6);

echo "\n";
if ($fallos > 0) {
    echo "$fallos comprobación(es) fallida(s).\n";
    exit(1);
}
echo "Todas las comprobaciones pasan.\n";
exit(0);
```

- [ ] **Step 2: Ejecutar el test y confirmar que falla**

Run: `php supertecnicas/tests/test_lib.php`
Expected: error fatal `Failed opening required '.../supertecnicas/lib.php'` (el fichero todavía no existe).

- [ ] **Step 3: Crear `supertecnicas/lib.php`**

```php
<?php
// supertecnicas/lib.php
// Helpers compartidos de la herramienta de supertécnicas: acceso a
// datos_oficiales.json y a los ficheros propios de esta herramienta, con
// lectura/escritura atómica. Sin clases, siguiendo el estilo de
// api/recalcular.php y api/discord_update.php.

define('ST_DATA_JSON', __DIR__ . '/../datos_oficiales.json');
define('ST_CODIGOS_JSON', __DIR__ . '/data/codigos_equipos.json');
define('ST_CONFIG_JSON', __DIR__ . '/data/config.json');

const ST_MAX_SUPERTECNICAS = 4;
const ST_TIPOS = ['', 'tiro', 'regate', 'bloqueo', 'parada'];
const ST_AFINIDADES = ['', 'neutro', 'fuego', 'montaña', 'bosque', 'aire'];

function stNormalizarTexto($texto) {
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = preg_replace('/[^a-z0-9]+/i', ' ', $texto);
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
        $ok = file_put_contents($tmp, $json) !== false && rename($tmp, $ruta);
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

function stBuscarEquipoPorId(array &$data, $equipoId) {
    foreach ($data['equipos'] as $i => $equipo) {
        if (($equipo['id'] ?? null) === $equipoId) return $i;
    }
    return null;
}

// Valor por defecto de código/PIN para un equipo sin entrada todavía en
// codigos_equipos.json: nombre del equipo / ciudad, normalizados.
function stCodigoPorDefecto(array $equipo) {
    return [
        'codigo' => stNormalizarTexto($equipo['nombre'] ?? ''),
        'pin' => stNormalizarTexto($equipo['ciudad'] ?? ''),
    ];
}
```

- [ ] **Step 4: Ejecutar el test y confirmar que pasa**

Run: `php supertecnicas/tests/test_lib.php`
Expected: todas las líneas empiezan por `OK`, termina con `Todas las comprobaciones pasan.` y código de salida `0`.

- [ ] **Step 5: `php -l` y commit**

Run: `php -l supertecnicas/lib.php && php -l supertecnicas/tests/test_lib.php`
Expected: `No syntax errors detected` en ambos.

```bash
git add supertecnicas/lib.php supertecnicas/tests/test_lib.php
git commit -m "Añade librería compartida de supertecnicas con self-check"
```

---

### Task 2: Auth de admin, secretos y protección de los datos

**Files:**
- Modify: `config/admin_auth.php`
- Modify: `config/secrets.example.php`
- Modify: `config/secrets.php` (fichero local, no versionado)
- Modify: `.gitignore`
- Create: `supertecnicas/data/.htaccess`

**Interfaces:**
- Produces: `requerirAdminBasicAuthConClaves(string $claveUsuario, string $claveHash, string $realm = 'Administracion'): void` — corta la petición con 401 si las credenciales HTTP Basic no coinciden con `cargarSecretos()[$claveUsuario]` / `[$claveHash]`. Usada por `supertecnicas/admin.php` (Task 5).
- Consumes: `cargarSecretos()` (ya existe en `config/admin_auth.php`).

- [ ] **Step 1: Generalizar `config/admin_auth.php` sin romper el uso existente**

`requerirAdminBasicAuth()` la llama hoy solo `test_conexion.php` (comprobado por grep). Generalizamos manteniendo esa función intacta para no tocar ese caller.

Modificar `config/admin_auth.php`:

```php
<?php
// Puerta de acceso HTTP Basic para endpoints administrativos/destructivos
// que no tienen (todavía) un sistema de sesión/roles propio.

function cargarSecretos(): array {
    static $secretos = null;
    if ($secretos === null) {
        $secretos = require __DIR__ . '/secrets.php';
    }
    return $secretos;
}

function requerirAdminBasicAuthConClaves(string $claveUsuario, string $claveHash, string $realm = 'Administracion'): void {
    $secretos = cargarSecretos();
    $usuarioEsperado = $secretos[$claveUsuario] ?? '';
    $hashEsperado = $secretos[$claveHash] ?? '';

    $usuario = $_SERVER['PHP_AUTH_USER'] ?? '';
    $clave = $_SERVER['PHP_AUTH_PW'] ?? '';

    $usuarioValido = $usuarioEsperado !== '' && hash_equals($usuarioEsperado, $usuario);
    $claveValida = $hashEsperado !== '' && password_verify($clave, $hashEsperado);

    if (!$usuarioValido || !$claveValida) {
        header('WWW-Authenticate: Basic realm="' . $realm . '"');
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'no_autorizado']);
        exit;
    }
}

function requerirAdminBasicAuth(): void {
    requerirAdminBasicAuthConClaves('admin_lesiones_user', 'admin_lesiones_pass_hash');
}
```

- [ ] **Step 2: `php -l` sobre el fichero modificado**

Run: `php -l config/admin_auth.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Añadir las claves nuevas a la plantilla de secretos**

En `config/secrets.example.php`, añadir dentro del array devuelto, junto a las claves `admin_lesiones_*` existentes:

```php
    // Admin de supertecnicas/ (independiente del admin de lesiones).
    // Genera el hash con: php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
    'admin_supertecnicas_user' => 'admin_supertecnicas',
    'admin_supertecnicas_pass_hash' => '',
```

- [ ] **Step 4: Generar credenciales reales en `config/secrets.php` (fichero local, no se commitea)**

Generar un hash para una contraseña temporal:

Run: `php -r "echo password_hash('cambia-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"`

Copiar el hash resultante y añadir estas dos líneas al array devuelto por `config/secrets.php` (el fichero real del entorno, ya existe con `db_*`, `admin_lesiones_*`, etc. — no se pisa nada, solo se añaden estas dos claves):

```php
    'admin_supertecnicas_user' => 'admin_supertecnicas',
    'admin_supertecnicas_pass_hash' => '<hash generado en el paso anterior>',
```

Avisar al usuario en el resumen final: usuario `admin_supertecnicas`, contraseña temporal `cambia-esta-clave` — debe cambiarla generando un hash nuevo con una clave propia y reemplazando `admin_supertecnicas_pass_hash` en `config/secrets.php`.

- [ ] **Step 5: Proteger de descarga directa los datos de esta herramienta**

Create `supertecnicas/data/.htaccess`:

```apache
Require all denied
```

- [ ] **Step 6: Evitar versionar los ficheros de datos generados en tiempo de ejecución**

Añadir a `.gitignore` (junto a la entrada ya existente `/config/secrets.php`):

```
/supertecnicas/data/codigos_equipos.json
/supertecnicas/data/config.json
```

- [ ] **Step 7: Commit**

`config/secrets.php` no se añade (está en `.gitignore`).

```bash
git add config/admin_auth.php config/secrets.example.php .gitignore supertecnicas/data/.htaccess
git commit -m "Prepara auth de admin y protección de datos para supertecnicas"
```

---

### Task 3: Hoja de estilos compartida

**Files:**
- Create: `supertecnicas/css/supertecnicas.css`

**Interfaces:**
- Produces: clases usadas por `index.php` (Task 4) y `admin.php` (Task 5): `.campo`, `.ayuda`, `p.mal`, `.inp` (+ `.inp-sm`, `.inp-mono`), `.rejilla` (+ `.rejilla-4`), `.tabla-caja`, `.tabla-scroll`, `.tabla`, `.st-login`, `.st-login-card`, `.st-form`, `.st-roster`, `.st-admin`, `.st-cabecera`, `.st-aviso`, `.st-jugador`, `.st-slot`, `.st-ventana`.
- Consumes: variables de `_fuente/styles.css` (`--ink*`, `--line*`, `--surface`, `--accent`, `--c-copa`, `--r*`, `--f-sans`, `--f-mono`, `--t1`). Se carga siempre después de `_fuente/styles.css`.

- [ ] **Step 1: Crear el fichero**

```css
/* supertecnicas/css/supertecnicas.css
   Se carga DESPUÉS de _fuente/styles.css (tokens, .card, .btn, .badge, .wrap).
   Aquí solo lo que esta herramienta necesita y styles.css no trae:
   formularios, tabla y el layout de sus tres páginas. */

.campo{ display:flex; flex-direction:column; gap:.3rem; min-width:0; font-size:.8125rem; color:var(--ink-2); }
.ayuda{ font-size:.8125rem; color:var(--ink-3); }
p.mal{ color:var(--c-copa); font-size:.8125rem; }

.inp{
  height:38px; width:100%; padding:0 .7rem; border-radius:var(--r-sm);
  background:var(--surface); border:1px solid var(--line-2); color:var(--ink);
  font-family:var(--f-sans); font-size:.875rem; transition:border-color var(--t1);
}
textarea.inp{ height:auto; min-height:72px; padding:.6rem .7rem; line-height:1.5; resize:vertical; }
.inp:hover{ border-color:var(--line-3); }
.inp:focus{ outline:none; border-color:var(--accent); }
.inp:disabled{ opacity:.45; cursor:not-allowed; }
.inp-sm{ height:32px; font-size:.8125rem; }
.inp-mono{ font-family:var(--f-mono); }

.rejilla{ display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); }
.rejilla-4{ grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); }

.tabla-caja{ border:1px solid var(--line); border-radius:var(--r-lg); overflow:hidden; background:var(--surface); }
.tabla-scroll{ overflow-x:auto; }
.tabla{ width:100%; border-collapse:collapse; font-size:.8125rem; }
.tabla th, .tabla td{ padding:.6rem .75rem; text-align:left; border-bottom:1px solid var(--line); }
.tabla th{ color:var(--ink-3); font-weight:500; }

/* -------- páginas -------- */
.st-login{ min-height:100vh; display:grid; place-items:center; padding:1.5rem; }
.st-login-card{ width:100%; max-width:360px; padding:1.75rem; border:1px solid var(--line); }
.st-login-card h1{ font-size:1.5rem; margin-bottom:.5rem; }
.st-form{ display:flex; flex-direction:column; gap:1rem; margin-top:1.25rem; }

.st-roster, .st-admin{ max-width:960px; margin:0 auto; padding:2rem 1.25rem 4rem; }
.st-cabecera{ display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
.st-aviso{ padding:.75rem 1rem; border:1px solid var(--line-2); border-radius:var(--r-sm); color:var(--ink-2); margin-bottom:1.5rem; }
.st-jugador{ border:1px solid var(--line); padding:1.25rem; margin-bottom:1.25rem; }
.st-jugador legend{ padding:0 .4rem; font-weight:600; }
.st-slot{ margin-bottom:.6rem; }
.st-ventana{ display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
```

- [ ] **Step 2: Commit**

```bash
git add supertecnicas/css/supertecnicas.css
git commit -m "Añade hoja de estilos de supertecnicas"
```

---

### Task 4: Página del presidente — login y plantilla (`index.php`, `logout.php`)

**Files:**
- Create: `supertecnicas/index.php`
- Create: `supertecnicas/logout.php`

**Interfaces:**
- Consumes: todo lo de `supertecnicas/lib.php` (Task 1) y las clases CSS de Task 3.
- Produces: sesión `$_SESSION['st_equipo_id']` (string, `equipo.id`), consumida por `supertecnicas/guardar.php` (Task 5) y comprobada del mismo modo en `admin.php` no aplica (admin usa Basic Auth, no esta sesión).
- El formulario de guardado que renderiza aquí postea a `guardar.php` (Task 5) con nombres de campo `jugadores[<indice>][nombre_check]` y `jugadores[<indice>][st][<slot 0-3>][nombre|tipo|afinidad|especial|descripcion]`, donde `<indice>` es la posición del jugador en `equipo['jugadores']`.

- [ ] **Step 1: Crear `supertecnicas/logout.php`**

```php
<?php
session_start();
unset($_SESSION['st_equipo_id']);
session_destroy();
header('Location: index.php');
exit;
```

- [ ] **Step 2: Crear `supertecnicas/index.php`**

```php
<?php
session_start();
require_once __DIR__ . '/lib.php';

function stEsc($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
    $codigoIntento = stNormalizarTexto($_POST['codigo'] ?? '');
    $pinIntento = stNormalizarTexto($_POST['pin'] ?? '');
    $codigos = stCargarCodigos();

    $equipoEncontrado = null;
    if ($codigoIntento !== '') {
        foreach ($codigos as $id => $c) {
            $codigoGuardado = stNormalizarTexto($c['codigo'] ?? '');
            $pinGuardado = stNormalizarTexto($c['pin'] ?? '');
            if ($codigoGuardado === $codigoIntento && $pinGuardado === $pinIntento) {
                $equipoEncontrado = $id;
                break;
            }
        }
    }

    if ($equipoEncontrado !== null) {
        $_SESSION['st_equipo_id'] = $equipoEncontrado;
        header('Location: index.php');
        exit;
    }
    $error = 'Código o PIN incorrectos.';
}

$equipoId = $_SESSION['st_equipo_id'] ?? null;
$equipo = null;

if ($equipoId !== null) {
    $data = stCargarDatosOficiales();
    $idx = stBuscarEquipoPorId($data, $equipoId);
    if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
        unset($_SESSION['st_equipo_id']);
        $equipoId = null;
    } else {
        $equipo = $data['equipos'][$idx];
    }
}

$config = stCargarConfig();
$ventanaAbierta = $config['ventana_abierta'];
$guardado = isset($_GET['guardado']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supertécnicas — Superliga Frontier</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/supertecnicas.css">
</head>
<body>
<?php if ($equipoId === null): ?>
  <main class="st-login">
    <div class="card st-login-card">
      <h1>Supertécnicas</h1>
      <p class="ayuda">Entra con el código y el PIN de tu equipo.</p>
      <?php if ($error !== ''): ?><p class="mal"><?= stEsc($error) ?></p><?php endif; ?>
      <form method="post" action="index.php" class="st-form">
        <input type="hidden" name="accion" value="login">
        <label class="campo">Código de equipo
          <input class="inp" type="text" name="codigo" required autofocus>
        </label>
        <label class="campo">PIN
          <input class="inp" type="text" name="pin" required>
        </label>
        <button class="btn btn-accent btn-lg" type="submit">Entrar</button>
      </form>
    </div>
  </main>
<?php else: ?>
  <main class="st-roster">
    <header class="st-cabecera">
      <div>
        <h1><?= stEsc($equipo['nombre'] ?? '') ?></h1>
        <p class="ayuda">Asigna hasta 4 supertécnicas por jugador.</p>
      </div>
      <a class="btn btn-secondary" href="logout.php">Cerrar sesión</a>
    </header>

    <?php if ($guardado): ?>
      <p class="ayuda">Guardado.</p>
    <?php endif; ?>

    <?php if (!$ventanaAbierta): ?>
      <p class="st-aviso">La ventana de supertécnicas está cerrada. Puedes ver lo asignado, pero no editarlo.</p>
    <?php endif; ?>

    <form method="post" action="guardar.php">
      <?php foreach (($equipo['jugadores'] ?? []) as $i => $j): ?>
        <fieldset class="card st-jugador" <?= $ventanaAbierta ? '' : 'disabled' ?>>
          <legend><?= stEsc($j['nombre'] ?? '') ?> · #<?= stEsc($j['dorsal'] ?? '') ?> · <?= stEsc($j['posicion'] ?? '') ?></legend>
          <input type="hidden" name="jugadores[<?= (int) $i ?>][nombre_check]" value="<?= stEsc($j['nombre'] ?? '') ?>">
          <?php
            $slots = $j['supertecnicas'] ?? [];
            for ($s = 0; $s < ST_MAX_SUPERTECNICAS; $s++):
              $st = $slots[$s] ?? ['nombre' => '', 'tipo' => '', 'afinidad' => '', 'especial' => '', 'descripcion' => ''];
          ?>
            <div class="rejilla rejilla-4 st-slot">
              <label class="campo">Nombre
                <input class="inp inp-sm" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][nombre]" value="<?= stEsc($st['nombre'] ?? '') ?>">
              </label>
              <label class="campo">Tipo
                <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][tipo]">
                  <?php foreach (ST_TIPOS as $t): ?>
                    <option value="<?= stEsc($t) ?>" <?= ($st['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? '—' : stEsc($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="campo">Afinidad
                <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][afinidad]">
                  <?php foreach (ST_AFINIDADES as $a): ?>
                    <option value="<?= stEsc($a) ?>" <?= ($st['afinidad'] ?? '') === $a ? 'selected' : '' ?>><?= $a === '' ? '—' : stEsc($a) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="campo">Especial
                <input class="inp inp-sm inp-mono" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][especial]" value="<?= stEsc($st['especial'] ?? '') ?>" placeholder="miximax, tótem…">
              </label>
            </div>
            <label class="campo">Descripción
              <textarea class="inp" maxlength="300" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][descripcion]"><?= stEsc($st['descripcion'] ?? '') ?></textarea>
            </label>
          <?php endfor; ?>
        </fieldset>
      <?php endforeach; ?>

      <?php if ($ventanaAbierta): ?>
        <button class="btn btn-accent btn-lg" type="submit">Guardar supertécnicas</button>
      <?php endif; ?>
    </form>
  </main>
<?php endif; ?>
</body>
</html>
```

- [ ] **Step 3: `php -l`**

Run: `php -l supertecnicas/index.php && php -l supertecnicas/logout.php`
Expected: `No syntax errors detected` en ambos.

- [ ] **Step 4: Prueba manual con el servidor embebido de PHP**

Run (en segundo plano, desde `htdocs/`): `php -S localhost:8000`

Con el navegador o `curl`:
1. `curl -i http://localhost:8000/supertecnicas/` → 200, contiene `Entra con el código y el PIN de tu equipo.`
2. Sin ningún equipo en `codigos_equipos.json` todavía (se siembra en Task 6), un login con cualquier código falla → confirmar que aparece `Código o PIN incorrectos.` y no hay error PHP en la salida del servidor embebido.

Parar el servidor embebido al terminar la prueba.

- [ ] **Step 5: Commit**

```bash
git add supertecnicas/index.php supertecnicas/logout.php
git commit -m "Añade login y plantilla del presidente en supertecnicas"
```

---

### Task 5: Guardado del presidente (`guardar.php`)

**Files:**
- Create: `supertecnicas/guardar.php`

**Interfaces:**
- Consumes: `$_SESSION['st_equipo_id']` (de Task 4), `stCargarConfig()`, `stCargarDatosOficiales()`, `stBuscarEquipoPorId()`, `stGuardarDatosOficiales()`, `ST_MAX_SUPERTECNICAS`, `ST_TIPOS`, `ST_AFINIDADES` (de Task 1). Espera el mismo formato de `$_POST['jugadores']` que genera el formulario de `index.php` (Task 4).
- Produces: redirección a `index.php?guardado=1` en éxito; escribe `equipo.jugadores[*].supertecnicas` en `datos_oficiales.json`, solo para los jugadores del equipo de la sesión.

- [ ] **Step 1: Crear `supertecnicas/guardar.php`**

```php
<?php
session_start();
require_once __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$equipoId = $_SESSION['st_equipo_id'] ?? null;
if ($equipoId === null) {
    header('Location: index.php');
    exit;
}

$config = stCargarConfig();
if (empty($config['ventana_abierta'])) {
    http_response_code(403);
    exit('La ventana de supertécnicas está cerrada.');
}

$data = stCargarDatosOficiales();
$idx = stBuscarEquipoPorId($data, $equipoId);
if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
    unset($_SESSION['st_equipo_id']);
    header('Location: index.php');
    exit;
}

$entradas = $_POST['jugadores'] ?? [];
if (!is_array($entradas)) $entradas = [];

foreach ($entradas as $i => $entrada) {
    $i = (int) $i;
    if (!isset($data['equipos'][$idx]['jugadores'][$i])) continue;
    if (!is_array($entrada)) continue;

    $nombreEsperado = (string) ($entrada['nombre_check'] ?? '');
    $nombreReal = (string) ($data['equipos'][$idx]['jugadores'][$i]['nombre'] ?? '');
    if ($nombreReal === '' || $nombreReal !== $nombreEsperado) continue;

    $bloques = $entrada['st'] ?? [];
    if (!is_array($bloques)) $bloques = [];
    ksort($bloques);

    $nuevasSupertecnicas = [];
    foreach ($bloques as $bloque) {
        if (count($nuevasSupertecnicas) >= ST_MAX_SUPERTECNICAS) break;
        if (!is_array($bloque)) continue;

        $nombre = trim(mb_substr((string) ($bloque['nombre'] ?? ''), 0, 40));
        $especial = trim(mb_substr((string) ($bloque['especial'] ?? ''), 0, 40));
        $descripcion = trim(mb_substr((string) ($bloque['descripcion'] ?? ''), 0, 300));
        $tipo = in_array($bloque['tipo'] ?? '', ST_TIPOS, true) ? $bloque['tipo'] : '';
        $afinidad = in_array($bloque['afinidad'] ?? '', ST_AFINIDADES, true) ? $bloque['afinidad'] : '';

        if ($nombre === '' && $especial === '' && $descripcion === '' && $tipo === '' && $afinidad === '') {
            continue;
        }

        $nuevasSupertecnicas[] = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'afinidad' => $afinidad,
            'especial' => $especial,
            'descripcion' => $descripcion,
        ];
    }

    $data['equipos'][$idx]['jugadores'][$i]['supertecnicas'] = $nuevasSupertecnicas;
}

stGuardarDatosOficiales($data);

header('Location: index.php?guardado=1');
exit;
```

- [ ] **Step 2: `php -l`**

Run: `php -l supertecnicas/guardar.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Prueba manual end-to-end (requiere haber sembrado un código en Task 6, o hacerlo aquí de forma puntual)**

Con el servidor embebido corriendo (`php -S localhost:8000` desde `htdocs/`):

1. Editar temporalmente `supertecnicas/data/config.json` a mano con `{"ventana_abierta": true}` (o hacerlo desde el panel de admin si Task 6 ya está hecha).
2. Añadir manualmente una entrada de prueba a `supertecnicas/data/codigos_equipos.json`, p. ej. usando el primer equipo real de `datos_oficiales.json` (`node -e "console.log(JSON.parse(require('fs').readFileSync('datos_oficiales.json')).equipos[0].id, JSON.parse(require('fs').readFileSync('datos_oficiales.json')).equipos[0].nombre)"` para ver un id/nombre reales) con `codigo`/`pin` conocidos.
3. Login con esas credenciales, rellenar el nombre de una supertécnica para el primer jugador, guardar.
4. Confirmar en `datos_oficiales.json` que **solo** ese jugador de ese equipo cambió (`git diff datos_oficiales.json` debe mostrar un único jugador afectado).
5. Repetir guardando con 5 bloques rellenos manipulando el HTML/una petición manual (`curl`) para confirmar que el servidor igualmente corta en 4 (el quinto no debe aparecer en el JSON).
6. Cerrar la ventana (`ventana_abierta: false`) y confirmar que un POST directo a `guardar.php` devuelve 403 y no modifica el fichero.
7. Revertir los cambios de prueba en `datos_oficiales.json` con `git checkout -- datos_oficiales.json` y borrar la entrada de prueba de `codigos_equipos.json`.

- [ ] **Step 4: Commit**

```bash
git add supertecnicas/guardar.php
git commit -m "Añade el guardado del presidente en supertecnicas"
```

---

### Task 6: Panel de admin (`admin.php`)

**Files:**
- Create: `supertecnicas/admin.php`

**Interfaces:**
- Consumes: `requerirAdminBasicAuthConClaves()` (Task 2), todo lo de `lib.php` (Task 1), CSS de Task 3.
- Produces: página que gestiona `config.json` (interruptor `ventana_abierta`) y `codigos_equipos.json` (código/PIN por equipo activo). Es la única forma prevista de sembrar/editar `codigos_equipos.json` en producción.

- [ ] **Step 1: Crear `supertecnicas/admin.php`**

```php
<?php
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/lib.php';

requerirAdminBasicAuthConClaves('admin_supertecnicas_user', 'admin_supertecnicas_pass_hash', 'Supertecnicas Admin');

function stEsc($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_ventana'])) {
        $config = stCargarConfig();
        $config['ventana_abierta'] = !$config['ventana_abierta'];
        stGuardarConfig($config);
        $mensaje = 'Ventana ahora: ' . ($config['ventana_abierta'] ? 'ABIERTA' : 'CERRADA');
    } elseif (isset($_POST['guardar_codigo'])) {
        $equipoId = (string) $_POST['guardar_codigo'];
        $codigo = trim((string) ($_POST['codigo'][$equipoId] ?? ''));
        $pin = trim((string) ($_POST['pin'][$equipoId] ?? ''));
        if ($equipoId !== '' && $codigo !== '' && $pin !== '') {
            $codigos = stCargarCodigos();
            $codigos[$equipoId] = ['codigo' => $codigo, 'pin' => $pin];
            stGuardarCodigos($codigos);
            $mensaje = 'Código actualizado.';
        } else {
            $mensaje = 'Código y PIN no pueden estar vacíos.';
        }
    }
}

$data = stCargarDatosOficiales();
$equipos = stEquiposActivos($data);
$codigos = stCargarCodigos();
$config = stCargarConfig();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · Supertécnicas</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/supertecnicas.css">
</head>
<body>
<main class="st-admin">
  <h1>Admin · Supertécnicas</h1>
  <?php if ($mensaje !== ''): ?><p class="ayuda"><?= stEsc($mensaje) ?></p><?php endif; ?>

  <form method="post" class="st-ventana">
    <span class="ayuda">Ventana de supertécnicas: <strong><?= $config['ventana_abierta'] ? 'ABIERTA' : 'CERRADA' ?></strong></span>
    <button class="btn btn-accent" type="submit" name="toggle_ventana" value="1">
      <?= $config['ventana_abierta'] ? 'Cerrar ventana' : 'Abrir ventana' ?>
    </button>
  </form>

  <form method="post">
    <div class="tabla-caja">
      <div class="tabla-scroll">
        <table class="tabla">
          <thead>
            <tr><th>Equipo</th><th>Ciudad</th><th>Código</th><th>PIN</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($equipos as $equipo): $id = $equipo['id']; $actual = $codigos[$id] ?? stCodigoPorDefecto($equipo); $pendiente = !isset($codigos[$id]); ?>
              <tr>
                <td><?= stEsc($equipo['nombre'] ?? '') ?><?= $pendiente ? ' <span class="ayuda">(pendiente de confirmar)</span>' : '' ?></td>
                <td><?= stEsc($equipo['ciudad'] ?? '') ?></td>
                <td><input class="inp inp-sm" type="text" name="codigo[<?= stEsc($id) ?>]" value="<?= stEsc($actual['codigo']) ?>"></td>
                <td><input class="inp inp-sm" type="text" name="pin[<?= stEsc($id) ?>]" value="<?= stEsc($actual['pin']) ?>"></td>
                <td><button class="btn btn-secondary btn-sm" type="submit" name="guardar_codigo" value="<?= stEsc($id) ?>">Guardar</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>
</main>
</body>
</html>
```

- [ ] **Step 2: `php -l`**

Run: `php -l supertecnicas/admin.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Prueba manual con el servidor embebido**

Con `php -S localhost:8000` corriendo desde `htdocs/`:

1. `curl -i http://localhost:8000/supertecnicas/admin.php` → 401.
2. `curl -i -u admin_supertecnicas:cambia-esta-clave http://localhost:8000/supertecnicas/admin.php` (usar la clave real puesta en Task 2 Step 4) → 200, la tabla lista los equipos activos de `datos_oficiales.json`, con `(pendiente de confirmar)` en todos si `codigos_equipos.json` todavía no existe.
3. Guardar un código/PIN para un equipo desde el formulario (o con `curl -u ... -d "guardar_codigo=<id>&codigo[<id>]=x&pin[<id>]=y" http://localhost:8000/supertecnicas/admin.php`) y confirmar que `supertecnicas/data/codigos_equipos.json` se crea con esa entrada.
4. Pulsar "Abrir ventana" y confirmar que `supertecnicas/data/config.json` pasa a `{"ventana_abierta": true}`.
5. `curl -i http://localhost:8000/supertecnicas/data/codigos_equipos.json` → debe dar 403 (protegido por el `.htaccess` de Task 2). **Nota:** el servidor embebido de PHP (`php -S`) no lee `.htaccess` — esta comprobación concreta hay que hacerla contra Apache/XAMPP, no contra `php -S`.

- [ ] **Step 4: Commit**

```bash
git add supertecnicas/admin.php
git commit -m "Añade el panel de admin de supertecnicas"
```

---

### Task 7: Verificación end-to-end y cierre

**Files:** ninguno nuevo — solo verificación sobre lo ya creado.

- [ ] **Step 1: Sintaxis de todo el proyecto nuevo**

Run: `for f in supertecnicas/*.php supertecnicas/tests/*.php config/admin_auth.php; do php -l "$f"; done`
Expected: `No syntax errors detected` en cada fichero.

- [ ] **Step 2: Recorrido completo con Apache/XAMPP real (no el servidor embebido, para que `.htaccess` aplique)**

1. Confirmar que `htdocs` sirve bajo Apache (o el entorno equivalente) y que `http://<host>/supertecnicas/` carga el login.
2. Repetir el flujo de presidente de Task 5 Step 3 contra Apache.
3. Confirmar que `http://<host>/supertecnicas/data/codigos_equipos.json` y `.../config.json` devuelven 403 (aquí sí aplica el `.htaccess`).
4. Cargar `index.html` (o cualquier `{lang}.html`) de la web pública tras guardar una supertécnica de prueba y confirmar en las herramientas de red del navegador que el `fetch('datos_oficiales.json')` trae el dato nuevo sin build ni redeploy.
5. Deshacer cualquier dato de prueba dejado en `datos_oficiales.json` (`git checkout -- datos_oficiales.json` si no era un cambio real) y en `supertecnicas/data/*.json`.

- [ ] **Step 3: Resumen de cierre**

Escribir en el mensaje final al usuario (no hace falta commit adicional):
- Qué se construyó y dónde vive (`supertecnicas/`).
- Credenciales de admin por defecto (`admin_supertecnicas` / `cambia-esta-clave`) y el aviso de cambiarlas.
- Que `codigos_equipos.json` empieza vacío: el admin tiene que entrar a `supertecnicas/admin.php` y confirmar/guardar el código de cada equipo antes de que los presidentes puedan entrar.
- Que la ventana empieza cerrada (`ventana_abierta: false`) por defecto.
