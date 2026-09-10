# superligafrontier.es — repositorio `htdocs/`

<!-- Emitido por The Architect. Fuente: blueprints/dashboard-liga/blueprint.md §19.1. -->

## ALCANCE — léelo antes de tocar nada

**Este fichero cubre el subproyecto `dashboard/` y nada más.** El resto de
`htdocs/` es la **web pública en producción** de superligafrontier.es:
`index.html` y sus traducciones, `_fuente/`, `api/`, `supertecnicas/`,
`admin/`, `cron/`, `datos_oficiales.json`. **No se tocan.**

Fuera de `dashboard/` solo se modifican dos ficheros, y solo añadiendo líneas
al final:

| Fichero | Cambio permitido | Paso |
|---|---|---|
| `.gitignore` | añadir las líneas de `dashboard/data/` | §10 Bootstrap |
| `config/secrets.example.php` | añadir 2 claves de admin | paso 04 |

`datos_oficiales.json` **se lee y jamás se escribe.** Es el fichero de
producción del que vive la web pública.

## Comandos

El binario de PHP de esta máquina de desarrollo es `/c/xampp/php/php.exe`;
`php` a secas **no está en PATH**. Si lo añades, `php` funciona igual. Esa
ruta es de la máquina de desarrollo, no del servidor de IONOS.

| Tarea | Comando (desde la raíz del proyecto) |
|---|---|
| Versión de PHP | `/c/xampp/php/php.exe -v` |
| Sintaxis de un fichero | `/c/xampp/php/php.exe -l dashboard/lib.php` |
| Sintaxis de todo el subproyecto | `for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" \|\| exit 1; done` |
| Un test | `/c/xampp/php/php.exe dashboard/tests/test_dominio.php` |
| Todos los tests | `for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" \|\| exit 1; done` |
| Hash de contraseña | `/c/xampp/php/php.exe -r "echo password_hash('clave', PASSWORD_DEFAULT);"` |

**Puerta:** el barrido de sintaxis **y** la tanda completa de tests pasan antes
de dar por terminada cualquier tarea. Los tests imprimen `OK`/`FAIL` línea a
línea y salen 1 si hubo algún `FAIL`.

No hay Composer, ni npm, ni `package.json`, ni paso de build, ni framework de
tests. Los tests son scripts PHP que se ejecutan directamente.

## Stack

PHP procedural sin framework (suelo **8.1.0**) · almacén de ficheros JSON con
escritura atómica `flock`+`rename` · sesiones PHP + `password_hash` para
presidentes · HTTP Basic reutilizado del repo para el admin · Apache en IONOS.
Cero dependencias externas.

## Arquitectura

**Camino de una petición.** navegador → `dashboard/plantilla.php` →
`dashboard/chrome.php` (cabecera/pie) → `dashboard/almacen.php` (carga el
JSON de temporada) → `dashboard/dominio.php` (valida cap, 20, cláusulas) →
`dashboard/almacen.php` (`plGuardarEquipoTemporada`, escritura atómica con
`rev`) → `dashboard/lib.php` (`plGuardarJsonAtomico`). Ningún POST escapa a
`plCsrfValido()`.

**Capas — cruzarlas al revés rompe la build:**

| Capa | Puede usar | Nunca |
|---|---|---|
| `dashboard/lib.php` | stdlib | conocer equipos, tiers ni fases |
| `dashboard/dominio.php` | `lib.php` | leer o escribir ficheros — es puro |
| `dashboard/almacen.php` | `lib.php`, `dominio.php` | imprimir HTML |
| pantallas (`*.php` de nivel superior) | todo lo anterior | escribir JSON a mano sin `almacen.php` |

**Dónde vive cada cosa:**

| Asunto | Única fuente de verdad |
|---|---|
| Rutas de los ficheros de datos | `dashboard/almacen.php` — `PL_DIR_DATOS` |
| Salarios de una temporada | `ajustes.tiers` **dentro** del fichero de esa temporada, nunca `tiers.json` |
| Reglas de negocio (20, 250M, 650M, fases) | `dashboard/dominio.php` |
| Escapado, CSRF, escritura atómica | `dashboard/lib.php` |
| Textos de presidente | `dashboard/i18n.php` — nunca literales en la pantalla |

## Reglas de código

1. **Prefijo `pl` en toda función nueva** de `dashboard/`: `plCargarJson`,
   `plEsc`, `plValidarClausulas`. Sin clases, sin namespaces, sin `use`.
2. **`plEsc()` en TODO valor interpolado en HTML.** Sin excepciones. Un `<?=`
   sin `plEsc()` es un fallo de revisión, no un detalle.
3. **`?? ''` antes de pasar cualquier valor de `$_GET`/`$_POST`/JSON a una
   función interna** (`str_contains`, `trim`, `mb_substr`, `htmlspecialchars`).
   Desde PHP 8.1 pasar `null` a un parámetro no nullable está deprecado y
   ensucia el log del servidor.
4. **Firmas con tipo nullable explícito**: `?string $x = null`, nunca
   `string $x = null`. PHP 8.4 deprecó la forma implícita.
5. **Toda pantalla arranca con** `if (session_status() === PHP_SESSION_NONE) { session_start(); }`.
   Es idempotente y es lo que permite renderizarla desde CLI con el arnés.
6. **Todo POST valida CSRF** con `plCsrfValido()` y responde 403 si falla.
7. **Toda regla se revalida en servidor.** El contador en vivo de cláusulas es
   comodidad; la verdad la decide `dominio.php`.
8. **Comentarios en español, explicando el porqué**, no el qué.
9. **Nada de dependencias nuevas.** Ni Composer, ni npm, ni CDN de JS.

## Sistema de diseño

Se **reutiliza** el Design System v3 de `_fuente/styles.css`. Toda pantalla
enlaza, en este orden: `../_fuente/styles.css` y luego `css/dashboard.css`.
No se inventan tokens.

| Papel | Valor | Uso |
|---|---|---|
| Fondo | `#000000` | página |
| Superficie | `#0E0E0E` · `#141414` · `#1C1C1C` | tarjetas, inputs |
| Línea | `rgba(255,255,255,.07)` · `.12` · `.20` | divisores |
| Tinta | `#EDEDED` · `#A1A1A1` · `#7A7A7A` | texto, secundario, apagado |
| Acento | `#FF5100` | 1 acento por pantalla, con cuentagotas |
| Oro | `#FFC94A` | fase CLAUSULAS, POR |
| Posiciones | POR `#FFC94A` · DEF `#3E7BFF` · MED `#46B45F` · ATA `#FF3B3B` | chips |

- **Tipografía:** Inter (UI) · Teko (display) · **JetBrains Mono para toda
  cifra** — millones, `X / 20`, totales.
- **Radios:** 8 / 12 / 16 / 24px · full 999px.
- **Motion:** `cubic-bezier(.16,1,.3,1)`, 150ms y 280ms. Respeta
  `prefers-reduced-motion`.
- **Todo estado lleva texto además de color.** Un badge de fase nunca es solo
  un color.

## Entorno

No hay fichero `.env` y no hay variables de entorno. La configuración vive en:

| Dónde | Qué | Origen |
|---|---|---|
| `config/secrets.php` | `admin_dashboard_user`, `admin_dashboard_pass_hash` | copiar de `config/secrets.example.php` y rellenar; **no se versiona** |
| `dashboard/data/*.json` | todo el estado | los crea la app; **no se versionan** |

## Reglas por área

| Fichero | Se aplica a |
|---|---|
| `.claude/rules/dashboard.md` | `dashboard/**` |
| `.claude/rules/fuera-de-alcance.md` | todo lo que no es `dashboard/**` |

## Innegociable

1. **Nunca escribas en `datos_oficiales.json`** ni en ningún fichero fuera de
   `dashboard/`, salvo los dos añadidos que autoriza la tabla de arriba.
2. **Nunca metas un valor en HTML sin `plEsc()`.**
3. **Nunca aceptes un POST sin validar CSRF y sin revalidar la fase y las
   reglas en servidor.**
4. **Nunca leas `tiers.json` para calcular un salario dentro de una
   temporada** — se lee `ajustes.tiers`, que está congelado a propósito.
5. **Nunca versiones `dashboard/data/*.json` ni `config/secrets.php`.**
6. **Nunca des una tarea por terminada con el barrido de sintaxis o la tanda
   de tests en rojo.**

Orden de construcción y estado: `blueprints/dashboard-liga/tasks.json` y
`blueprints/dashboard-liga/epics/`.
