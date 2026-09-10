# Epic 01: Núcleo — ficheros, reglas, almacén y puerta de entrada

Este epic construye todo lo que no se ve. Al terminarlo no hay ninguna pantalla de trabajo
todavía, pero existen las tres capas sobre las que se apoya el resto del subproyecto: la escritura
atómica de ficheros, la aritmética de la liga y la máquina de fases de la temporada. Los dos epics
siguientes son interfaz sobre esto.

**Va primero porque aquí vive la única matemática real del producto** —Salary Cap de 250M, límite de
20 jugadores, 650M de cláusulas— y probarla sin sesión, sin ficheros y sin navegador es lo más
barato que se puede hacer. Un error de cálculo descubierto en el paso 14 cuesta cien veces más que
descubierto en el paso 02.

Tareas: **E1-T1 … E1-T5** (pasos 01-05 de `blueprint.md` §9).

## Stack

PHP 8 procedural · sin framework · sin Composer · sin npm · sin paso de build · almacén en ficheros
JSON con `flock` + `rename` atómico · autenticación de admin por HTTP Basic reutilizando
`config/admin_auth.php` · sesión PHP con `password_hash` para presidentes · Apache en IONOS.

Suelo de versión: **PHP 8.1.0** (lo fija `array_is_list()`). Máquina de desarrollo: 8.2.12.

PHP se invoca siempre como `/c/xampp/php/php.exe`, **desde la raíz del proyecto** (`htdocs/`). No
está en el `PATH` de esta máquina.

## Directory subtree

```
htdocs/                          ← raíz del proyecto = web pública EN PRODUCCIÓN
├── datos_oficiales.json         ← SE LEE, NUNCA SE ESCRIBE
├── config/
│   ├── admin_auth.php           ← ya existe. Se USA, no se reimplementa
│   └── secrets.example.php      ← se le AÑADEN 2 claves (E1-T4)
└── dashboard/                  ← todo el trabajo de este epic
    ├── lib.php                  E1-T1
    ├── dominio.php              E1-T2
    ├── almacen.php              E1-T3
    ├── admin.php                E1-T4
    ├── i18n.php                 E1-T5 (solo esqueleto + español)
    ├── chrome.php               E1-T5
    ├── index.php                E1-T5 (login)
    ├── logout.php               E1-T5
    ├── data/
    │   └── .htaccess            llega con workspace/ · «Require all denied»
    └── tests/
        ├── arnes.php            llega con workspace/
        ├── test_lib.php         E1-T1
        ├── test_dominio.php     E1-T2
        ├── test_almacen.php     E1-T3
        ├── test_temporada.php   E1-T4
        └── test_login.php       E1-T5
```

**Nada fuera de `dashboard/` se modifica**, con una única excepción en este epic:
`config/secrets.example.php`, y solo añadiendo dos líneas al final.

## Data model touched here

Este epic crea **todos** los ficheros de datos y sus semillas. Las formas exactas están en
`blueprint.md` §4 y no se reproducen aquí; lo que importa para construir:

| Fichero | Semilla | Quién lo escribe |
|---|---|---|
| `equipos.json` | `{"equipos": []}` | E1-T3 (lectura), epic 03 (escritura) |
| `usuarios.json` | `{"usuarios": []}` | E1-T3 |
| `tiers.json` | Los **diez** tiers con sus salarios — la única semilla con contenido | E1-T3 |
| `temporadas.json` | `{"activa": null, "temporadas": []}` | E1-T3, E1-T4 |
| `temporada-XXXX-YY.json` | Lo crea el admin | E1-T4 |
| `registro.json` | `{"eventos": []}` | E1-T3 |

**No hay comando de seed.** La semilla es el valor por defecto de `plCargarJson()`, y el fichero
aparece en disco la primera vez que algo lo guarda.

## Contracts

Tres contratos nacen en este epic y los dos siguientes los consumen sin volver a discutirlos:

1. **`plGuardarEquipoTemporada($temporadaId, $equipoId, $datos, $revEsperado)`** devuelve
   `['ok'=>bool, 'error'=>?string]`. Si el `rev` del disco no coincide con `$revEsperado`, devuelve
   `error.rev_desfasado` y **no escribe nada**. Toda pantalla que guarde plantilla o cláusulas pasa
   por aquí.
2. **Las validaciones devuelven `['ok'=>bool,'error'=>?string]` con una CLAVE de i18n**, nunca una
   frase. `error.cap_superado`, `error.max_jugadores`, `error.clausulas_excedidas`,
   `error.rev_desfasado`. Las frases llegan en el epic 03.
3. **`ajustes.tiers` de una temporada es una copia congelada** de `tiers.json` en el instante de
   crearla. Todo cálculo de salario dentro de una temporada lee `ajustes.tiers`, **nunca**
   `tiers.json`.

## Conventions that bite in this area

- **Prefijo `pl` en toda función.** Sin clases, sin namespaces. Es el estilo de `supertecnicas/`.
- **`dominio.php` es puro**: no abre ficheros, no imprime, no lee superglobales, no toca la sesión.
  Su `Verify` lo comprueba con un `grep` negativo, así que un `echo` de depuración olvidado rompe la
  puerta. Es deliberado.
- **Solo `almacen.php` llama a `plGuardarJsonAtomico()`.** También hay un `grep` que lo verifica.
- **`?? ''` antes de toda función interna de PHP.** Desde 8.1 pasar `null` a un parámetro no nullable
  está deprecado, y todo lo que viene de `$_GET`/`$_POST`/JSON puede ser `null`.
- **Firmas nullable explícitas**: `?Tipo $x = null`, nunca `Tipo $x = null` (deprecado en 8.4).
- **`plEsc()` en todo valor interpolado en HTML.** Sin excepciones, desde la primera pantalla.
- **Ningún test escribe en `dashboard/data/`.** Todos usan `plArnesDirDatos()` y limpian después.
- **Cuidado con `'pl'`**: en `PL_IDIOMAS` es el código de polaco, no el prefijo de funciones.

## Tasks

### E1-T1 — Crear `dashboard/` y `lib.php` con escritura atómica y CSRF

Copiar el patrón de `supertecnicas/lib.php` cambiando el prefijo `st` por `pl`. Ese fichero ya está
en producción y ya resuelve esto: no hay que rediseñarlo, hay que trasladarlo. El guard de versión
va en la **primera línea ejecutable**, antes de declarar nada, o no protege de nada.

La escritura atómica es `fopen(lock)` → `flock(LOCK_EX)` → `tempnam` → `file_put_contents` →
`chmod` → `rename` → `flock(LOCK_UN)` → `fclose`, con `JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT`.
Se comprobó en la máquina de desarrollo que `rename()` sobre un fichero existente funciona en
Windows con PHP 8.2.12, acentos incluidos.

**Acceptance**

1. CUANDO se cargue lib.php bajo un PHP anterior a 8.1.0 EL SISTEMA DEBERÁ terminar con un mensaje que nombre la versión mínima, sin imprimir HTML.
2. CUANDO plGuardarJsonAtomico() escriba sobre un fichero que ya existe EL SISTEMA DEBERÁ dejar el contenido nuevo íntegro y no dejar ningún fichero temporal pl_* en el directorio.
3. CUANDO plGuardarJsonAtomico() reciba texto acentuado EL SISTEMA DEBERÁ escribirlo legible en UTF-8 y NO como secuencias \uXXXX.
4. CUANDO plCargarJson() reciba una ruta inexistente o un JSON corrupto EL SISTEMA DEBERÁ devolver el valor por defecto sin crear el fichero ni propagar un error.
5. CUANDO plEsc() reciba comillas simples y dobles EL SISTEMA DEBERÁ escapar ambas.
6. CUANDO se ejecute test_lib.php EL SISTEMA DEBERÁ imprimir 'Todas las comprobaciones pasan.' y salir 0.

**Files**

- `dashboard/lib.php`
- `dashboard/tests/test_lib.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/lib.php
/c/xampp/php/php.exe -l dashboard/tests/arnes.php
/c/xampp/php/php.exe -r "require 'dashboard/lib.php'; echo plEsc(\"<a href='x'>\");" | grep -qF "&lt;a href=&#039;x&#039;&gt;"
/c/xampp/php/php.exe -r "require 'dashboard/lib.php'; \$r=sys_get_temp_dir().'/pl_v.json'; plGuardarJsonAtomico(\$r,['x'=>'Montaña']); echo file_get_contents(\$r);" | grep -qF "Montaña"
/c/xampp/php/php.exe dashboard/tests/test_lib.php
test -f dashboard/data/.htaccess && grep -q 'Require all denied' dashboard/data/.htaccess
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E1-T1: Crear dashboard/ y lib.php con escritura atómica y CSRF"
git tag step-01-esqueleto
```

### E1-T2 — Escribir `dominio.php` con las reglas puras

Aquí está el valor del subproyecto. Escribir `test_dominio.php` **antes** que el módulo: son
funciones puras con entradas y salidas claras, el caso ideal para hacerlo al revés, y así los
mensajes de error salen bien a la primera.

El error que más se cuela: `plValidarCambioTier()` **sustituye** el salario del jugador en el total,
no lo suma. Sumarlo hace que bajar de `S+` a `A+` sea rechazado por cap, que es exactamente lo
contrario de lo que debe pasar.

**Acceptance**

1. CUANDO se añada un jugador que dejaría el total en 260M con un cap de 250M EL SISTEMA DEBERÁ devolver ok:false con la clave error.cap_superado.
2. CUANDO se añada el jugador número 21 EL SISTEMA DEBERÁ devolver ok:false con la clave error.max_jugadores, aunque el cap lo permita.
3. CUANDO se cambie el tier de un jugador EL SISTEMA DEBERÁ recalcular el total sustituyendo su salario, de modo que bajar a un tier más barato nunca sea rechazado por cap.
4. CUANDO el reparto de cláusulas sume 650M EL SISTEMA DEBERÁ devolver COMPLETO; con 580M DEBERÁ devolver INCOMPLETO; con 651M DEBERÁ devolver EXCEDIDO.
5. CUANDO un presidente intente clausular a un jugador propio, o indique un comprador distinto a su equipo, o el jugador ya esté CLAUSULADO EL SISTEMA DEBERÁ devolver false.
6. CUANDO se ejecute test_dominio.php EL SISTEMA DEBERÁ salir 0 sin haber leído ni escrito ningún fichero.

**Files**

- `dashboard/dominio.php`
- `dashboard/tests/test_dominio.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/dominio.php
/c/xampp/php/php.exe dashboard/tests/test_dominio.php
test -f dashboard/dominio.php && ! grep -nE "file_get_contents|file_put_contents|fopen|\\\$_SESSION|\\\$_POST|\\\$_GET|echo |print " dashboard/dominio.php
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E1-T2: Escribir dominio.php con las reglas puras de cap, 20 y 650M"
git tag step-02-dominio
```

### E1-T3 — Escribir `almacen.php` con semillas, `rev` optimista y registro

`plRutaDatos()` debe aceptar el override por `$GLOBALS['PL_DIR_DATOS']`. Es lo que permite que los
tests trabajen sobre un directorio temporal, y sin ello la batería tocaría los datos reales y daría
miedo ejecutarla.

La comparación de `rev` va **dentro del mismo `flock`** que la escritura. Si se lee fuera y se
escribe dentro, la ventana entre ambas es justo el fallo que este mecanismo existe para cerrar.

**Acceptance**

1. CUANDO se cargue un fichero de datos inexistente EL SISTEMA DEBERÁ devolver su semilla sin escribir nada en disco.
2. CUANDO se cree una temporada EL SISTEMA DEBERÁ congelar en ajustes.tiers los diez tiers copiados de tiers.json en ese instante, con una entrada de equipos vacía y rev:0 por cada equipo activo.
3. CUANDO se cree una temporada EL SISTEMA DEBERÁ dejar el fichero de la temporada anterior intacto y NO DEBERÁ copiar ningún jugador, cláusula, posición ni tier de ella.
4. CUANDO plGuardarEquipoTemporada() reciba un rev distinto al del disco EL SISTEMA DEBERÁ devolver error.rev_desfasado y dejar el fichero sin modificar.
5. CUANDO plGuardarEquipoTemporada() reciba el rev correcto EL SISTEMA DEBERÁ incrementarlo exactamente en 1.
6. CUANDO plRegistrarEvento() se llame tres veces EL SISTEMA DEBERÁ dejar exactamente tres entradas en orden de llegada, sin alterar las anteriores.

**Files**

- `dashboard/almacen.php`
- `dashboard/tests/test_almacen.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/almacen.php
/c/xampp/php/php.exe dashboard/tests/test_almacen.php
test ! -f dashboard/data/temporadas.json || git check-ignore -q dashboard/data/temporadas.json
test "$(grep -lE 'plGuardarJsonAtomico\(' dashboard/*.php | grep -vE 'dashboard/(almacen|lib)\.php' | wc -l)" = "0"
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E1-T3: Escribir almacen.php con semillas, rev optimista y registro"
git tag step-03-almacen
```

### E1-T4 — Panel de admin: transiciones de fase y nueva temporada

`requerirAdminBasicAuthConClaves()` va **antes que cualquier otra cosa** en el fichero, incluido el
`session_start()` que hace falta para el CSRF. Las dos claves nuevas se **añaden al final** de
`config/secrets.example.php`: ese fichero ya tiene las de lesiones y supertécnicas y perderlas
rompería otras partes del sitio.

El informe de equipos incompletos **avisa y deja continuar**. No es una puerta: el admin manda.

**Acceptance**

1. CUANDO se pida dashboard/admin.php sin credenciales Basic válidas EL SISTEMA DEBERÁ responder 401 y NO DEBERÁ imprimir ningún dato de la liga.
2. CUANDO el admin confirme una transición de fase EL SISTEMA DEBERÁ escribirla en temporadas.json y añadir exactamente un evento FASE a registro.json.
3. CUANDO el admin vaya a cerrar ROSTER o CLAUSULAS EL SISTEMA DEBERÁ listar los equipos incompletos y DEBERÁ permitir continuar de todas formas.
4. CUANDO el admin cree una temporada EL SISTEMA DEBERÁ dejarla en fase ROSTER, vacía, y convertirla en la temporada activa.
5. CUANDO se envíe un POST sin token CSRF válido EL SISTEMA DEBERÁ responder 403 y no modificar ningún fichero.
6. CUANDO se lea config/secrets.example.php EL SISTEMA DEBERÁ contener las dos claves nuevas conservando intactas todas las anteriores.

**Files**

- `dashboard/admin.php`
- `config/secrets.example.php`
- `dashboard/tests/test_temporada.php`

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin.php
/c/xampp/php/php.exe -l config/secrets.example.php
/c/xampp/php/php.exe dashboard/tests/test_temporada.php
grep -q "admin_dashboard_user" config/secrets.example.php && grep -q "admin_dashboard_pass_hash" config/secrets.example.php
grep -q "admin_supertecnicas_user" config/secrets.example.php && grep -q "admin_lesiones_user" config/secrets.example.php
grep -q "requerirAdminBasicAuthConClaves" dashboard/admin.php
```

**Checkpoint**

```bash
git add dashboard/ config/secrets.example.php && git commit -m "E1-T4: Panel de admin: transiciones de fase y nueva temporada"
git tag step-04-admin-temporada
```

### E1-T5 — Login de presidente, sesión, i18n en español y envoltorio

`i18n.php` en este paso lleva **solo el esqueleto y el español**. Los otros nueve idiomas son el
epic 03, y traducir pantallas cuyos textos aún van a cambiar es trabajo que se tira dos veces.

El mensaje de error del login es **el mismo** para email inexistente, contraseña incorrecta y cuenta
desactivada. Distinguirlos convierte el formulario en un oráculo de qué correos existen.

`session_regenerate_id(true)` va **antes** de escribir el id de usuario en la sesión, no después.

**Acceptance**

1. CUANDO se pida cualquier pantalla de presidente sin sesión válida EL SISTEMA DEBERÁ redirigir al login sin imprimir dato alguno de ningún equipo.
2. CUANDO el email no exista, la contraseña sea incorrecta o la cuenta esté desactivada EL SISTEMA DEBERÁ mostrar el mismo mensaje en los tres casos.
3. CUANDO un login sea correcto EL SISTEMA DEBERÁ invocar session_regenerate_id(true) antes de escribir el id de usuario en la sesión.
4. CUANDO el admin desactive a un presidente con sesión abierta EL SISTEMA DEBERÁ cerrarle la sesión en su siguiente petición.
5. CUANDO el usuario tenga equipoId null EL SISTEMA DEBERÁ mostrar el aviso de equipo no asignado y NO DEBERÁ producir un error ni una página en blanco.
6. CUANDO se pinte cualquier pantalla EL SISTEMA DEBERÁ emitir un único h1, un main#contenido, un .skip-link y html lang con el idioma activo.

**Files**

- `dashboard/i18n.php`
- `dashboard/chrome.php`
- `dashboard/index.php`
- `dashboard/logout.php`
- `dashboard/tests/test_login.php`

**Verify**

```bash
for f in dashboard/i18n.php dashboard/chrome.php dashboard/index.php dashboard/logout.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
/c/xampp/php/php.exe dashboard/tests/test_login.php
test "$(/c/xampp/php/php.exe -r "require 'dashboard/tests/arnes.php'; plArnesPreparar(); \$h=plArnesRender('dashboard/index.php'); echo substr_count(\$h,'<h1'), (int)str_contains(\$h,'id=\"contenido\"'), (int)str_contains(\$h,'skip-link');")" = "111"
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "E1-T5: Login de presidente, sesión, i18n en español y envoltorio"
git tag step-05-login
```

## Epic acceptance

```bash
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
! grep -rnE "plGuardarJsonAtomico([^)]*datos_oficiales|file_put_contents([^)]*datos_oficiales" dashboard/
```

Los tres salen 0, y existen las cinco etiquetas `step-01-esqueleto` … `step-05-login`.

## Pitfalls

- **Poner el guard de versión después de las declaraciones.** No protege: PHP parsea el fichero
  entero antes de ejecutar la primera línea, y una sintaxis no soportada falla antes de llegar al
  guard. Va arriba del todo.
- **Leer el `rev` fuera del `flock`.** Deja abierta exactamente la ventana que el mecanismo cierra.
- **Sumar en vez de sustituir en `plValidarCambioTier()`.** Rechaza cambios a tiers más baratos.
- **Dejar un `echo` de depuración en `dominio.php`.** Rompe su `Verify`, y con razón: un módulo puro
  que imprime deja de ser testeable sin arnés.
- **Sobrescribir `config/secrets.example.php` en vez de añadir al final.** Se llevaría por delante
  las credenciales de lesiones y supertécnicas.
- **Escribir en `dashboard/data/` desde un test.** Ensucia los datos reales y hace que nadie quiera
  ejecutar la batería.

## Before moving on

- [ ] Los cinco tests pasan y el barrido de sintaxis sale 0.
- [ ] `dashboard/data/` tiene su `.htaccess` con `Require all denied`.
- [ ] Ningún fichero de `dashboard/` escribe en `datos_oficiales.json` (lo comprueba el `grep` recursivo; `git status` no vale aquí: ese fichero cambia por el flujo de la web pública).
- [ ] Ningún fichero `*.json` de `dashboard/data/` aparece en `git ls-files`.
- [ ] Existe la etiqueta `step-05-login`.

Luego: `epics/02-presidente.md`.
