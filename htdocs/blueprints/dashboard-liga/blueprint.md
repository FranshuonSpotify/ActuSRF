# Dashboard de Liga — Superliga Frontier — Blueprint

> Generado por The Architect el 2026-09-10
> Forma: internal-tool · `knowledge/shapes/internal-tool.md`
> Runtime track: **ninguno aplicable** — PHP procedural sin dependencias; los comandos están escritos literalmente en §10 y §9, no importados de un track
> Modo de emisión: bundle
> Versión del blueprint: 1
> Versiones verificadas por última vez: 2026-09-10 — procedencia por paquete en §11

---

## 1. Project Overview & Non-Goals

### Visión

La Superliga Frontier es una liga ficticia con ~30 clubes, cada uno dirigido por un presidente
real. Cada temporada esos presidentes pasan por tres trámites que hoy viven en una hoja de
cálculo compartida: **inscribir hasta 20 jugadores** (cada uno con posición y *tier*, y el tier
determina el salario) sin pasarse de un **Salary Cap de 250M**; **repartir exactamente 650M de
cláusulas** entre esos 20 jugadores; y **consultar un mercado** donde se anota —no se ejecuta—
qué jugador ha clausulado quién. La hoja de cálculo no valida nada, no impide que dos
copresidentes se pisen, y no deja rastro de quién anotó qué, que es exactamente lo que hace
falta cuando hay una reclamación.

Esto lo sustituye una herramienta cerrada de gestión, servida desde el mismo hosting que la web
pública de la liga, dentro de `htdocs/dashboard/`. Valida las tres reglas en servidor, bloquea
cada fase cuando no toca, y guarda un registro append-only de cada clausulación. **Solo gestiona
el estado de la temporada en curso**: el histórico entre temporadas vive en otra web y no es
asunto de esta app.

### Usuarios

| Persona | A qué viene | Frecuencia |
|---|---|---|
| Presidente de club (~30 cuentas, rol único) | Inscribir su plantilla, repartir sus 650M de cláusulas, consultar el mercado y anotar sus clausulaciones | En ráfagas: intenso los días de apertura y cierre de cada fase, nada en medio |
| Copresidente (comparte `equipoId` con otro presidente) | Lo mismo, sobre el mismo equipo — de ahí el control de concurrencia por `rev` | Igual que arriba |
| Admin (el dueño de la liga, una persona) | Abrir y cerrar fases, crear la temporada, dar de alta equipos y presidentes, editar tiers, corregir clausulaciones | Semanal, y muy intenso el día de la transición de fase |

Concurrencia real: 3-4 personas a la vez, con picos el último día de cada fase. No es un
problema de escala; es un problema de reglas y de trazabilidad.

### Goals — alcance v1

1. Las tres reglas duras (≤20 jugadores, ≤250M de salarios, ≤650M de cláusulas con 650M exactos
   como estado "completo") se validan **en servidor** en cada escritura, para presidentes y para
   el admin.
2. Las cuatro fases (`ROSTER`, `CLAUSULAS`, `MERCADO`, `CERRADA`) bloquean lo que toca, y el
   admin puede forzar cualquier transición.
3. Dos copresidentes editando el mismo equipo no se pisan en silencio: el segundo guardado se
   rechaza con un mensaje que dice qué ha pasado.
4. Toda clausulación y toda corrección queda registrada con actor, fecha y detalle, en un log que
   nunca se edita.
5. Un presidente puede cargar sus 20 jugadores pegando texto, no tecleando 20 formularios.
6. El presidente ve la herramienta en su idioma (10 idiomas), con el mismo selector de banderas
   que ya usa `supertecnicas/`.

### Non-Goals — explícitamente fuera del alcance v1

| No se construye | Por qué no ahora | Se revisa cuando |
|---|---|---|
| Histórico de jugadores, movimientos o presidentes entre temporadas | Ya existe y vive en otra web de la liga; duplicarlo crea dos fuentes de verdad para el mismo dato | Alguien decida retirar esa otra web |
| Sistema financiero, pagos o transferencias automáticas | El mercado **registra** lo que ocurre fuera de la app; no mueve dinero ni cambia de equipo a nadie. Ejecutar la clausulación implicaría arbitrar disputas automáticamente, que es justo lo que el registro delega en el admin | Las clausulaciones se acuerden dentro de la app y no por Discord |
| Estadísticas, rankings, chat, notificaciones, mercado avanzado (pujas, ofertas) | Nada de eso resuelve el trámite que hoy hace la hoja de cálculo; cada uno es una superficie más que puede fallar en la puerta de aceptación | La liga pida explícitamente uno de ellos con un caso de uso escrito |
| Panel de administración traducido | El admin es una sola persona hispanohablante. `supertecnicas/admin.php` ya toma la misma decisión y no ha molestado a nadie | Haya un segundo admin que no hable español |
| Edición de tiers con efecto retroactivo sobre una temporada en curso | Imposible por diseño: `ajustes.tiers` está congelado dentro del fichero de temporada (§4). Subir S++ de 75 a 90 reventaría el cap de 30 equipos ya inscritos | Nunca — es la propiedad que hace segura la edición de tiers |
| Escritura sobre `datos_oficiales.json` | Es el fichero de producción del que vive la web pública; el subproyecto lo lee (paso 11) y jamás lo escribe | Nunca dentro de este subproyecto |
| Base de datos relacional | 30 equipos × 20 jugadores son ~600 filas y 3-4 escrituras concurrentes. Un JSON con `flock` + `rename` lo cubre con cero infraestructura y cero coste | Ver §20.3, decisión 2: varias competiciones simultáneas, consultas entre temporadas, o decenas de clientes concurrentes |
| Registro público de usuarios | Las cuentas las crea el admin (paso 12). Un formulario de alta abierto en una liga cerrada solo añade una superficie de abuso | Nunca en esta herramienta |

**El constructor no implementa nada de esta tabla**, ni siquiera como añadido pequeño mientras
trabaja en un paso adyacente. Si un paso parece exigir un non-goal, es un defecto del blueprint:
para y repórtalo en vez de ampliar el alcance.

### Métricas de éxito

| Métrica | Objetivo | Cómo se mide |
|---|---|---|
| Inscripción de una plantilla completa | Menos de 5 minutos por equipo | Cronometrar la carga de 20 jugadores por pegado masivo (§9 paso 08) frente a los ~15 min de la hoja de cálculo |
| Equipos que llegan a fin de fase `ROSTER` completos | 30 de 30 antes de la transición | El informe de equipos incompletos del panel de admin (§9 paso 04) |
| Disputas de clausulación sin resolver | 0 | Toda clausulación tiene una entrada en `registro.json` con actor y fecha (§9 paso 10) |
| Guardados perdidos por copresidentes | 0 | Ningún guardado sobrescribe otro: el rechazo por `rev` es visible al usuario (§9 pasos 03, 07, 09) |

---

## 2. Tech Stack

**Runtime track: ninguno.** Ningún fichero de `knowledge/runtime-tracks/` aplica: este subproyecto
es PHP procedural plano, sin Composer, sin npm, sin framework, sin ORM y sin paso de build.
`rails-laravel.md` fue considerado y **rechazado** explícitamente — instalar Laravel dentro de un
hosting compartido de IONOS que hoy sirve HTML estático y PHP suelto añade un runtime entero,
un directorio `vendor/`, y un despliegue que el dueño de la liga no sabe operar, para gestionar
600 filas. Los comandos de §9, §10 y §20.1 están escritos literalmente, no importados de un track.

Esta tabla nombra *elecciones*, no versiones. Todo pin vive en §11 y en ningún otro sitio.

| Capa | Elección | Por qué esto, frente a qué |
|---|---|---|
| Lenguaje / runtime | PHP procedural, sin framework | Es lo que el hosting ya ejecuta y lo que el resto del repo ya es (`api/`, `supertecnicas/`, `admin/`). Frente a Laravel: cero instalación, cero `vendor/`, cero paso de despliegue nuevo |
| Framework | Ninguno | El subproyecto son 12 pantallas de formulario. Un framework aportaría enrutado y ORM que aquí no hacen falta, y un ciclo de actualización que nadie va a mantener |
| Estilos | Reutilizar `_fuente/styles.css` (Design System v3) + `dashboard/css/dashboard.css` | La estética ya existe y está en producción. Frente a Tailwind: exigiría un paso de build, que es exactamente lo que este repo no tiene |
| Capa de componentes | Ninguna — HTML servidor con helpers PHP (`plCabecera`, `plPie`) | Es el patrón de `supertecnicas/index.php`. Frente a React: un bundler entero para pintar tablas |
| Base de datos | Ficheros JSON en `dashboard/data/`, escritura atómica `flock` + `rename` | 30 equipos × 20 jugadores. Frente a MariaDB: exigiría credenciales, migraciones y una copia de seguridad más, para un volumen que cabe en 300 KB. Ver §20.3 decisión 2 para el disparador que lo revierte |
| Acceso a datos | `dashboard/almacen.php`, funciones con prefijo `pl` | Frente a un ORM: no hay esquema relacional que mapear |
| Auth presidente | Sesión PHP + `password_hash`/`password_verify` (`PASSWORD_DEFAULT`) | Está en la stdlib y es lo correcto. Frente a un proveedor externo: 30 cuentas cerradas no justifican una dependencia con la que federar |
| Auth admin | HTTP Basic vía `config/admin_auth.php`, que **ya existe en el repo** | Reutiliza `requerirAdminBasicAuthConClaves()`, ya en producción para lesiones y supertécnicas. Frente a un rol `ADMIN` en `usuarios.json`: metería al admin en el mismo almacén que gestiona, y una escritura mal hecha se llevaría por delante su propio acceso |
| Trabajo en segundo plano | Ninguno | Nada en el producto es asíncrono. Frente a un cron: no hay nada que ejecutar sin usuario delante |
| Pagos | NOT APPLICABLE — la liga es gratuita y el mercado no mueve dinero | — |
| Almacenamiento de ficheros | NOT APPLICABLE — escudos y fotos son URLs externas (`i.imgur.com`), igual que en `datos_oficiales.json` | — |
| Email / notificaciones | Ninguno | El aviso de apertura de fase lo da el admin por Discord, como ya hace. Frente a un envío SMTP: una credencial más y una cola más para sustituir un mensaje que ya se manda |
| Hosting | Apache + PHP en IONOS (compartido), mismo sitio que la web pública | Ya está pagado y ya sirve el resto del repo. Frente a un PaaS: un despliegue nuevo para un subdirectorio |
| Gestor de paquetes | Ninguno | Sin dependencias no hay nada que gestionar. La ausencia es la característica |

### Comprobación de compatibilidad

Contrastado con `knowledge/stack-compatibility.md`: **ninguna de sus filas de combinaciones
conocidas como malas aplica**. En concreto: no hay dos proveedores de identidad (el admin usa
HTTP Basic sobre `config/secrets.php` y los presidentes sesión PHP sobre `usuarios.json` — son
dos superficies distintas, no dos fuentes de verdad sobre el mismo sujeto, y ningún usuario
existe en las dos); no hay dos paradigmas de estilos (una única hoja heredada más una propia,
ambas CSS plano); no hay dos sistemas de migración (no hay base de datos); no hay driver TCP
sobre un runtime sin sockets; no hay diseño de proceso largo sobre un host por petición; y no hay
estado en memoria compartido entre instancias porque hay una sola instancia de Apache.

La fila *"Reinventar el admin"* de `knowledge/shapes/internal-tool.md` sí merece respuesta
explícita: el shape recomienda revisar un admin de caja antes de construir uno a medida. Aquí no
aplica porque el stack existente **no ships ninguno** — no hay Django, no hay Laravel, no hay
framework en absoluto — y meter uno para obtener listados CRUD costaría más que las cinco
pantallas de admin que este blueprint especifica.

---

## 3. Directory Structure

```
htdocs/                                  ← RAÍZ DEL PROYECTO = web pública en producción
├── index.html  bg.html  en.html  …      ← web pública. NO SE TOCA
├── _fuente/
│   ├── app.js                           ← renderiza la web pública. NO SE TOCA
│   ├── styles.css                       ← Design System v3. Se ENLAZA, no se edita
│   └── i18n.js                          ← referencia de los pares idioma→bandera. NO SE TOCA
├── api/  admin/  cron/  supertecnicas/  ← producción. supertecnicas/ es el PRECEDENTE a copiar
├── datos_oficiales.json                 ← producción. SE LEE (paso 11), NUNCA SE ESCRIBE
├── config/
│   ├── admin_auth.php                   ← ya existe: requerirAdminBasicAuthConClaves(). Se USA
│   ├── secrets.php                      ← no versionado. Recibe las 2 claves nuevas a mano
│   └── secrets.example.php              ← EDITADO en el paso 04: se AÑADEN 2 claves al final
├── .gitignore                           ← EDITADO en §10 Bootstrap: se AÑADEN 6 líneas al final
├── CLAUDE.md                            ← de workspace/ (§19.1). Cubre TODO el repo: define el alcance
├── AGENTS.md                            ← de workspace/ (§19.2)
├── .gitignore.dashboard.txt            ← de workspace/. Las 6 líneas que Bootstrap añade a .gitignore
├── .claude/
│   ├── settings.json                    ← de workspace/ (§19.3)
│   ├── rules/dashboard.md              ← de workspace/ (§19.5)
│   ├── rules/fuera-de-alcance.md        ← de workspace/ (§19.5)
│   └── skills/anadir-pantalla/SKILL.md  ← de workspace/ (§19.4)
├── blueprints/dashboard-liga/           ← ESTE bundle. Vive dentro del proyecto (ver §19.6)
│
└── dashboard/                          ← TODO EL TRABAJO NUEVO VIVE AQUÍ
    ├── lib.php                 paso 01 · guard de versión, JSON atómico, plEsc, CSRF, normalizar
    ├── dominio.php             paso 02 · reglas puras: tier→salario, cap, 20, 650M, fases
    ├── almacen.php             paso 03 · carga/guarda cada fichero, semillas, rev, registro
    ├── chrome.php              paso 05 · plCabecera()/plPie(): <head>, landmarks + paso 16 (plT + selector de idioma)
    ├── i18n.php                paso 05 (es) + paso 15 (9 idiomas + banderas) · PL_IDIOMAS, plT()
    ├── index.php               paso 05 (login) + paso 06 (dashboard) + paso 16 (plT)
    ├── logout.php              paso 05
    ├── plantilla.php           paso 07 · alta/edición/borrado de jugador + paso 16 (plT)
    ├── pegado.php              paso 08 · pegado masivo Nombre;POS;TIER + paso 17 (plT)
    ├── clausulas.php           paso 09 · reparto de 650M + paso 17 (plT)
    ├── mercado.php             paso 10 · listado global + marcar clausulado + paso 17 (plT)
    ├── admin.php               paso 04 · HTTP Basic + sección Temporada
    ├── admin_equipos.php       paso 11 · alta/edición/archivar + importar de la web
    ├── admin_presidentes.php   paso 12 · alta con password_hash, reasignar equipo
    ├── admin_tiers.php         paso 13 · editar los 10 salarios
    ├── admin_plantillas.php    paso 14 · vista global, corrección, aviso de duplicados
    ├── DESPLIEGUE.md           paso 18 · checklist de despliegue en IONOS
    ├── css/
    │   └── dashboard.css      paso 18 · SOLO lo que no da _fuente/styles.css
    ├── data/                   ← estado. NO SE VERSIONA (salvo .htaccess)
    │   ├── .htaccess           de workspace/ (§19.6) · contenido literal: Require all denied
    │   ├── equipos.json        lo crea almacen.php con su semilla
    │   ├── usuarios.json       ídem
    │   ├── tiers.json          ídem — los 10 tiers de §4
    │   ├── temporadas.json     ídem
    │   ├── temporada-2026-27.json   lo crea el admin (paso 04)
    │   └── registro.json       append-only
    └── tests/
        ├── arnes.php           de workspace/ (§19.6) · render en CLI + plVerificar/plSalirConResultado
        │
        │   Dieciocho tests, uno por paso de §9. Los dos de traducción son los
        │   que la división del i18n en tres pasos añadió sobre el juego original:
        │
        ├── test_lib.php                 paso 01   ├── test_mercado.php             paso 10
        ├── test_dominio.php             paso 02   ├── test_equipos.php             paso 11
        ├── test_almacen.php             paso 03   ├── test_presidentes.php         paso 12
        ├── test_temporada.php           paso 04   ├── test_tiers.php               paso 13
        ├── test_login.php               paso 05   ├── test_admin_plantillas.php    paso 14
        ├── test_dashboard.php           paso 06   ├── test_i18n.php                paso 15
        ├── test_plantilla.php           paso 07   ├── test_traduccion.php          paso 16
        ├── test_pegado.php              paso 08   ├── test_traduccion_mercado.php  paso 17
        └── test_clausulas.php           paso 09   └── test_css.php                 paso 18
```

**Reglas de frontera**

- Nada fuera de `dashboard/` se modifica, con las dos excepciones nombradas arriba
  (`.gitignore`, `config/secrets.example.php`), y ambas son **añadir líneas al final**.
- `dashboard/dominio.php` es puro: no abre ficheros, no imprime nada, no lee superglobales.
  Es lo que lo hace testeable sin arnés.
- `dashboard/almacen.php` es el **único** sitio que lee o escribe en `dashboard/data/`.
  Ninguna pantalla llama a `plGuardarJsonAtomico()` directamente.
- Ninguna pantalla calcula un salario: lo pide a `dominio.php`, que lo lee de `ajustes.tiers`.
- Las pantallas no se importan entre sí. Lo compartido baja a `chrome.php` o a `almacen.php`.

**Convención de inclusión.** Todo `require_once` usa `__DIR__` y una ruta relativa literal
(`require_once __DIR__ . '/almacen.php';`). No hay alias, no hay autoloader, no hay `include_path`.
Es la única convención de resolución que este blueprint enuncia y está reconciliada contra los
cuatro contextos que cargan estos módulos en la *matriz de convención de resolución* de §19.6 —
no se repite aquí.

**Origen de cada fichero del árbol.** Cada fichero dibujado arriba lleva anotado o el paso de §9
que lo escribe, o la marca `de workspace/`, que significa que se emite como fichero real en §19.6
y llega al proyecto con el copiado único de §10 antes del paso 01. No hay una tercera categoría:
dibujar un fichero en este árbol no lo crea.

---

## 4. Data Model

Todos los ficheros viven en `dashboard/data/`. Formato: JSON codificado con
`JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT`, escrito siempre por `plGuardarJsonAtomico()`.

### Entidades

**Equipo** — el registro propio de equipos del subproyecto. Existe aparte de `datos_oficiales.json`
para poder archivar, renombrar y crear equipos aquí sin tocar producción.

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `id` | string | PK, `eq_<timestamp>` | Identidad local. Si el equipo vino importado, coincide con el `id` de la web |
| `nombre` | string | no vacío | Nombre en español |
| `nombre_en` | string\|null | — | Nombre en inglés, arrastrado de la web si existía |
| `abreviatura` | string | 2-4 caracteres | Para tablas estrechas |
| `escudo` | string\|null | URL | Externa (`i.imgur.com`), nunca un fichero local |
| `color1` | string\|null | hex `#rrggbb` | Color de club, para el chip del listado |
| `equipoId` | string\|null | — | `id` del equipo en `../datos_oficiales.json`, o `null` si se creó a mano aquí. Es la clave que evita reimportar dos veces el mismo equipo |
| `activo` | bool | por defecto `true` | `false` = archivado **en local**. No toca la web pública |

**Usuario** — solo presidentes. **No existe fila de admin.**

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `id` | string | PK, `u_<n>` | — |
| `nombre` | string | no vacío | Nombre visible |
| `email` | string | no vacío, único | Es el identificador de login |
| `hash` | string | `password_hash(..., PASSWORD_DEFAULT)` | Nunca se muestra ni se registra |
| `equipoId` | string\|null | FK → `Equipo.id`, **no único** | `null` = presidente sin equipo asignado: entra y ve un aviso, no un error. **Varios usuarios pueden compartir `equipoId`** (copresidentes) — es una relación, no una excepción |
| `activo` | bool | por defecto `true` | `false` = no puede iniciar sesión |

**Tier** — la tabla maestra de salarios. Lista **ordenada**: el orden es el del desplegable.

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `codigo` | string | uno de los 10 de abajo | Etiqueta visible |
| `salario` | int | > 0, en millones | Lo que consume del cap |

Los diez códigos son exactamente `S++`, `S+`, `S`, `A+`, `A`, `A-`, `B+`, `B`, `B-`, `C`.
**`C+`, `C-` y `D` no existen** y el admin no puede crearlos (paso 13).

**Temporada (índice)** — `temporadas.json` dice cuál está activa y qué temporadas hay.

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `activa` | string | FK → `temporadas[].id` | La única sobre la que se opera |
| `temporadas[].id` | string | PK, `AAAA-AA` | `2026-27` |
| `temporadas[].nombre` | string | — | `2026/27` |
| `temporadas[].fase` | enum | `ROSTER` \| `CLAUSULAS` \| `MERCADO` \| `CERRADA` | Gobierna todos los bloqueos de §5 |
| `temporadas[].creada` | string | ISO-8601 con offset | `2026-09-10T12:00:00+02:00` |

**Fichero de temporada** — `temporada-<id>.json`, uno por temporada. **El de una temporada
anterior no se toca jamás.**

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `id` | string | = el id de la temporada | Redundante a propósito: el fichero se identifica solo |
| `ajustes.salaryCap` | int | 250 | Copia congelada del límite vigente al crear la temporada |
| `ajustes.presupuestoClausulas` | int | 650 | Ídem |
| `ajustes.maxJugadores` | int | 20 | Ídem |
| `ajustes.tiers` | Tier[] | **copia congelada de `tiers.json`** | Ver la nota de abajo. Es la decisión de diseño más importante de esta sección |
| `equipos.<equipoId>.rev` | int | ≥ 0 | Contador de concurrencia optimista **por equipo** |
| `equipos.<equipoId>.jugadores` | Jugador[] | ≤ `ajustes.maxJugadores` | Vacío al crear la temporada |

**Jugador** — vive dentro del fichero de temporada, nunca suelto.

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `id` | string | PK dentro del equipo, `j_<n>` | — |
| `nombre` | string | no vacío | Texto libre — de ahí el riesgo R2 de §20.2 |
| `posicion` | enum | `POR` \| `DEF` \| `MED` \| `ATA` | Exactamente estas cuatro |
| `tier` | string | uno de `ajustes.tiers[].codigo` | — |
| `salario` | int | derivado de `tier` | Desnormalizado a propósito: se recalcula al cambiar de tier y se guarda para que un listado no tenga que resolver 600 búsquedas |
| `clausula` | int | ≥ 0 | En millones. `0` es un valor legal |
| `estado` | enum | `DISPONIBLE` \| `CLAUSULADO` | Por defecto `DISPONIBLE` |
| `clausuladoPor` | string\|null | FK → `Equipo.id` | `id` del equipo **comprador**. `null` por defecto |
| `clausuladoEn` | string\|null | ISO-8601 con offset | `null` por defecto |

> **`ajustes.tiers` es una copia congelada de `tiers.json` en el momento de crear la temporada, y
> es deliberado.** Todo cálculo de salario dentro de una temporada lee `ajustes.tiers`, **nunca**
> `tiers.json`. La consecuencia es la que se busca: editar `tiers.json` afecta solo a temporadas
> futuras, así que subir S++ de 75 a 90 no puede reventar retroactivamente el Salary Cap de 30
> equipos que ya se inscribieron con la tabla vieja. Sin esta congelación, la pantalla de tiers del
> paso 13 sería una bomba de relojería: un cambio bienintencionado dejaría media liga fuera de cap
> sin que nadie hubiera tocado su plantilla. Por eso el paso 13 muestra un aviso visible diciendo
> que el cambio solo afecta a temporadas futuras.

**Evento de registro** — `registro.json`, log **append-only**. Nunca se edita ni se borra una
entrada; una corrección es un evento nuevo, no una modificación del anterior.

| Campo | Tipo | Restricciones | Significado |
|---|---|---|---|
| `ts` | string | ISO-8601 con offset | Momento del hecho |
| `actor` | string | `Usuario.id` o la cadena literal `"admin"` | Quién lo hizo |
| `actorNombre` | string | — | Copia del nombre al momento, para que borrar un usuario no borre la historia |
| `tipo` | enum | `CLAUSULACION` \| `CORRECCION` \| `FASE` \| `TEMPORADA` | — |
| `detalle` | objeto | libre, pero con las claves de §4 para cada tipo | Para `CLAUSULACION`: `temporada`, `jugadorId`, `jugador`, `equipoOrigen`, `equipoComprador` |

### Relaciones

- `Usuario` —(N:1)→ `Equipo` por `equipoId`. **N puede ser >1** (copresidentes) y puede ser `null`.
  Borrado: no hay borrado de equipo, se archiva (`activo:false`); los usuarios apuntando a él
  siguen apuntando y ven el aviso de equipo archivado. Sin cascada.
- `Equipo` —(1:1)→ entrada en `temporada-<id>.json`.`equipos`. Se crea vacía al crear la temporada
  para todo equipo con `activo:true`. Sin cascada: archivar un equipo a mitad de temporada deja su
  entrada intacta.
- `Jugador` —(N:1)→ `Equipo` (su dueño, por la clave del mapa `equipos`) y —(N:0..1)→ `Equipo`
  (el comprador, por `clausuladoPor`). **Un jugador clausulado no cambia de equipo**: el mercado
  registra, no ejecuta.
- `Evento` —(N:1)→ `Usuario` por `actor`, sin integridad referencial forzada: `actorNombre`
  conserva el dato aunque el usuario desaparezca. Es un log, no una tabla normalizada.

### Índices

| Fichero | "Índice" | Por qué |
|---|---|---|
| `temporada-<id>.json` | El mapa `equipos` está **cifrado por `equipoId`**, no es una lista | La consulta caliente es "dame la plantilla de este equipo"; con una lista serían 30 comparaciones en cada pantalla |
| `equipos.json` | Lista, recorrida entera | 30 elementos. Un índice aquí sería ceremonia |
| `registro.json` | Lista append-only, se lee entera y se filtra en PHP | Se consulta poquísimo (solo el admin, ante una disputa) |

No hay índices de base de datos porque no hay base de datos. El coste de la decisión está en
§20.3, decisión 2.

### Esquema

```json
{
  "equipos.json": {
    "equipos": [
      { "id": "eq_1776649179181", "nombre": "Criaturas de la Noche",
        "nombre_en": "Children of the Night", "abreviatura": "CDN",
        "escudo": "https://i.imgur.com/vHWASV1.png", "color1": "#ffffff",
        "equipoId": "eq_1776649179181", "activo": true }
    ]
  },

  "usuarios.json": {
    "usuarios": [
      { "id": "u_1", "nombre": "Juan", "email": "juan@ejemplo.com",
        "hash": "$2y$…", "equipoId": "eq_1776649179181", "activo": true }
    ]
  },

  "tiers.json": {
    "tiers": [
      {"codigo":"S++","salario":75}, {"codigo":"S+","salario":60},
      {"codigo":"S","salario":40},   {"codigo":"A+","salario":25},
      {"codigo":"A","salario":18},   {"codigo":"A-","salario":14},
      {"codigo":"B+","salario":8},   {"codigo":"B","salario":6},
      {"codigo":"B-","salario":5},   {"codigo":"C","salario":2}
    ]
  },

  "temporadas.json": {
    "activa": "2026-27",
    "temporadas": [
      { "id": "2026-27", "nombre": "2026/27", "fase": "ROSTER",
        "creada": "2026-09-10T12:00:00+02:00" }
    ]
  },

  "temporada-2026-27.json": {
    "id": "2026-27",
    "ajustes": {
      "salaryCap": 250, "presupuestoClausulas": 650, "maxJugadores": 20,
      "tiers": [ {"codigo":"S++","salario":75} ]
    },
    "equipos": {
      "eq_1776649179181": {
        "rev": 3,
        "jugadores": [
          { "id":"j_1", "nombre":"Jugador 1", "posicion":"MED", "tier":"S+",
            "salario":60, "clausula":80, "estado":"DISPONIBLE",
            "clausuladoPor":null, "clausuladoEn":null }
        ]
      }
    }
  },

  "registro.json": {
    "eventos": [
      { "ts":"2026-09-10T12:00:00+02:00", "actor":"u_1", "actorNombre":"Juan",
        "tipo":"CLAUSULACION",
        "detalle":{ "temporada":"2026-27", "jugadorId":"j_1", "jugador":"Jugador 1",
                    "equipoOrigen":"eq_a", "equipoComprador":"eq_b" } }
    ]
  }
}
```

`ajustes.tiers` aparece arriba con un solo elemento por brevedad del ejemplo; en un fichero real
lleva **los diez**, copiados de `tiers.json`.

### Migraciones

**No hay herramienta de migración y no la habrá.** La forma de los ficheros la fija
`dashboard/almacen.php` con dos mecanismos, y ninguno de los dos es un fichero de migración:

1. **Semilla al no existir.** `plCargarJson($ruta, $porDefecto)` devuelve `$porDefecto` cuando el
   fichero no está. `almacen.php` define el `$porDefecto` de cada uno de los cinco ficheros (§9
   paso 03), así que una instalación limpia arranca sin ningún paso de inicialización manual.
2. **Normalización al cargar.** Cada `plCargarX()` rellena las claves que falten con su valor por
   defecto antes de devolver el array. Añadir un campo a una entidad es añadirlo ahí; los ficheros
   viejos se completan solos en la primera lectura y se persisten completos en la primera escritura.

**Regla dura:** ninguna carga borra una clave que no reconoce. Un fichero escrito por una versión
posterior se lee sin perder datos.

### Datos de semilla

Una instalación limpia obtiene, sin ejecutar nada:

| Fichero | Semilla |
|---|---|
| `equipos.json` | `{"equipos": []}` — los equipos se importan de la web (paso 11) o se crean a mano |
| `usuarios.json` | `{"usuarios": []}` — las cuentas las crea el admin (paso 12) |
| `tiers.json` | **Los diez tiers de arriba, con sus salarios.** Es la única semilla con contenido: sin ella la primera temporada nacería sin tabla de salarios |
| `temporadas.json` | `{"activa": null, "temporadas": []}` — la primera temporada la crea el admin (paso 04) |
| `registro.json` | `{"eventos": []}` |

No hay comando de seed: la semilla es el valor por defecto de `plCargarJson()`. El fichero se
escribe en disco la primera vez que algo lo guarda.

## 5. API Design

**NO APLICA — este subproyecto no expone ninguna API.**

No hay endpoints JSON, no hay rutas REST, no hay `fetch()` contra el servidor. Cada `.php` de
`dashboard/` es una **página completa** que:

1. Arranca sesión, resuelve idioma y carga el estado que necesita de `almacen.php`.
2. Si `$_SERVER['REQUEST_METHOD'] === 'POST'`, valida CSRF → valida fase → valida permisos →
   valida rangos → escribe → `header('Location: …'); exit;` (patrón POST/Redirect/GET, para que
   recargar no reenvíe el formulario).
3. Si es `GET`, imprime HTML.

Es exactamente la forma de `supertecnicas/index.php` y `supertecnicas/guardar.php`, y es
deliberada: sin API no hay una segunda superficie de autorización que mantener sincronizada con la
primera. Toda regla se comprueba en un solo sitio, en el servidor, en el camino del POST.

**La única lógica de cliente que existe** es el contador en vivo de la pantalla de cláusulas
(§9 paso 09): suma los `<input>` y pinta el total y la barra mientras el presidente teclea.
No envía nada, no decide nada y **el servidor revalida el total íntegro al recibir el POST**. Si
ese JavaScript no se ejecuta, la pantalla sigue siendo funcional: se guarda y se valida igual.

---

## 6. Frontend Architecture

Renderizado en servidor, sin framework, sin bundler, sin paso de build. HTML impreso por PHP.

### Composición de una pantalla

Todas las pantallas de presidente tienen la misma forma. `chrome.php` (paso 05) aporta las dos
mitades del envoltorio para que ninguna pantalla repita el `<head>` ni los landmarks:

```php
<?php
session_start();
require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

plEstablecerIdioma(plResolverIdioma());

$usuario = plUsuarioActual();              // null ⇒ redirige a index.php
$temporada = plTemporadaActiva();
$fase = $temporada['fase'] ?? 'CERRADA';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) { http_response_code(403); exit('Token CSRF inválido.'); }
    // … validar fase, validar rangos, guardar, redirigir …
}

plCabecera($titulo, $activo);   // <!doctype> … <header> … <nav> … <main id="contenido">
?>
    <!-- contenido de la pantalla -->
<?php plPie(); ?>
```

`plCabecera($titulo, $activo)` imprime el documento hasta `<main id="contenido">`, con
`<html lang="…">` reflejando el idioma activo, el `.skip-link`, las dos hojas de estilo en el orden
de §7, la navegación del presidente con el elemento `$activo` marcado con `aria-current="page"`, el
badge de fase y el selector de banderas. `plPie()` cierra `</main>`, imprime el pie y cierra el
documento.

### Navegación

| Rol | Elementos | Nota |
|---|---|---|
| Presidente | Dashboard · Mi plantilla · Cláusulas · Mercado | Cuatro. Ni uno más (§33 de la especificación) |
| Admin | Dashboard · Equipos · Presidentes · Plantillas · Tiers · Mercado · Temporada | Siete, en `admin*.php` |

Los elementos de presidente **nunca se ocultan según la fase**: se muestran siempre, y la pantalla
que no toca editar se sirve en solo lectura con su aviso. Ocultar el enlace haría creer que la
sección no existe; deshabilitar el formulario y explicar por qué enseña al presidente en qué fase
está la liga. Es la misma decisión que toma `supertecnicas/index.php` con su ventana cerrada.

### Estado de fase en la interfaz

Cada pantalla editable resuelve una de tres situaciones, y las tres se ven distintas:

| Situación | Qué se pinta |
|---|---|
| Fase correcta | Formulario operativo |
| Fase anterior a la suya | Formulario deshabilitado + aviso (`Termina primero tu inscripción de plantilla`) |
| Fase posterior a la suya | Solo lectura + aviso (`No puedes modificar tu plantilla. La fase de inscripción de plantillas ya ha finalizado.`) |

Los textos exactos salen de `i18n.php`, nunca escritos a mano en la pantalla.

### JavaScript

Inline, al final del documento, sin fichero externo y sin dependencias. Solo dos usos en todo el
subproyecto:

1. **Contador de cláusulas** (paso 09) — suma, total, barra y estado `Completo`/`Incompleto`.
2. **Modales de confirmación** (pasos 04, 08, 10) — `<dialog>` nativo, con foco atrapado y cierre
   con `Esc` que el propio elemento ya implementa. Si `<dialog>` no estuviera disponible, el
   formulario envía igual: el modal es una confirmación, no una puerta.

No hay estado de cliente, no hay router, no hay hidratación. La página es la fuente de verdad.

### Rendimiento

30 equipos × 20 jugadores = **600 filas como máximo absoluto** en la pantalla más pesada
(`mercado.php`). Es una tabla que cabe en memoria y se pinta de una vez; no hace falta paginar.
El fichero de temporada completo ronda los 150 KB de JSON. La pitfall de "paginar en el servidor"
del arquetipo `internal-tool` **no aplica a esta escala** y forzarla sería complejidad sin causa.
Si algún día la liga creciera un orden de magnitud, §20 dice qué cambiar.

---

## 7. Design System

**No se inventa nada.** El sistema de diseño ya existe, está en producción y se llama Design
System v3. Vive en `_fuente/styles.css` y su acabado de referencia es Vercel / Linear / Apple:
negro real, 95 % monocromo, naranja como acento raro y preciso, jerarquía por tamaño y espacio.

### Cómo se enlaza

Cada pantalla, en este orden exacto — el mismo que `supertecnicas/index.php:70-71`:

```html
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/dashboard.css">
```

`_fuente/styles.css` **se enlaza y no se edita jamás**. Es el fichero de la web pública.

### Tokens (definidos en `_fuente/styles.css`, se usan por nombre)

| Grupo | Variables |
|---|---|
| Fondos | `--bg:#000000` · `--bg-raised:#0A0A0A` · `--surface:#0E0E0E` · `--surface-2:#141414` · `--surface-3:#1C1C1C` |
| Líneas | `--line:rgba(255,255,255,.07)` · `--line-2:rgba(255,255,255,.12)` · `--line-3:rgba(255,255,255,.20)` |
| Tinta | `--ink:#EDEDED` · `--ink-2:#A1A1A1` · `--ink-3:#7A7A7A` · `--ink-4:#525252` · `--ink-5:#2E2E2E` |
| Acento | `--accent:#FF5100` · `--accent-2:#FF7A38` · `--accent-glow:rgba(255,81,0,.28)` · `--gold:#FFC94A` |
| Posiciones | `--pos-por:#FFC94A` · `--pos-def:#3E7BFF` · `--pos-med:#46B45F` · `--pos-del:#FF3B3B` |
| Tipografía | `--f-sans:'Inter'` · `--f-display:'Teko'` · `--f-mono:'JetBrains Mono'` |
| Radios | `--r-sm:8px` · `--r:12px` · `--r-lg:16px` · `--r-xl:24px` · `--r-full:999px` |
| Motion | `--ease:cubic-bezier(.16,1,.3,1)` · `--t1:150ms` · `--t2:280ms` |

**Regla tipográfica que se aplica sin excepción:** toda cifra va en `--f-mono`. Millones, `18 / 20`,
totales de cap, totales de cláusulas, contadores. El texto corrido va en `--f-sans`. Es lo que hace
que las columnas de números se lean alineadas de un vistazo, que es el trabajo real de esta app.

### Componentes que ya existen y se reutilizan literalmente

De `_fuente/styles.css`: `.wrap` (máx. 1240 px) · `.card` · `.btn` con `.btn-primary` /
`.btn-secondary` / `.btn-accent` / `.btn-sm` · `.badge` · `.chip` con `.chip-por` / `.chip-def` /
`.chip-med` · `table.tbl` con sus `th` en mono, mayúsculas y `letter-spacing:.1em`.

De `supertecnicas/css/supertecnicas.css` se replican en `dashboard.css`, porque son de formulario y
la hoja pública no los trae: `.campo` · `.inp` (42 px, `--surface-2`, foco
`box-shadow:0 0 0 3px var(--accent-glow)`) · `select.inp` con la flecha SVG embebida como
`background-image` · `.inp-sm` · `.inp-mono` · `.rejilla` · `.ayuda` · `p.mal`.

### Lo único que `dashboard.css` añade de nuevo

```css
/* ATA no existe en la web pública (allí es DEL). Reusa su color: es el mismo
   rol en el campo, y añadir un quinto color de posición rompería la paleta. */
.chip-ata{ background:rgba(255,59,59,.14); color:var(--pos-del); }

/* Badges de fase. El color NUNCA es la única señal: el badge lleva siempre
   su texto, porque un daltónico y una captura en blanco y negro tienen que
   poder leer en qué fase está la liga. */
.fase{ display:inline-flex; align-items:center; gap:.4rem; font-family:var(--f-mono);
       font-size:.6875rem; font-weight:500; letter-spacing:.08em; text-transform:uppercase;
       padding:.28rem .6rem; border-radius:var(--r-full); border:1px solid currentColor; }
.fase-roster   { color:#3E7BFF; background:rgba(62,123,255,.13); }
.fase-clausulas{ color:var(--gold); background:rgba(255,201,74,.13); }
.fase-mercado  { color:#46B45F; background:rgba(70,180,95,.13); }
.fase-cerrada  { color:var(--ink-3); background:rgba(255,255,255,.05); }

/* Barra de presupuesto de cláusulas. Tres estados, y los tres se anuncian
   también por texto en el aria-live de al lado. */
.barra{ height:8px; border-radius:var(--r-full); background:var(--surface-3); overflow:hidden; }
.barra > i{ display:block; height:100%; transition:width var(--t2) var(--ease),
            background var(--t1) var(--ease); }
.barra-parcial  > i{ background:var(--accent); }   /* < 650M — borrador válido */
.barra-completa > i{ background:#46B45F; }         /* = 650M exactos */
.barra-excedida > i{ background:#FF3B3B; }         /* > 650M — se rechaza */
```

### Estados de un jugador en el mercado

| Estado | Señal visual | Señal textual |
|---|---|---|
| `DISPONIBLE` | `.badge` neutro | «Disponible» |
| `CLAUSULADO` | `.badge` con `--ink-3` y opacidad reducida en la fila | «Clausulado por {equipo}» |

Nunca se comunica un estado solo con color ni solo con un icono.

### Densidad

Es un panel de trabajo, no una portada. Se usa la escala de espaciado corta, tablas compactas y
cero animación decorativa. Las únicas transiciones son las de `--t1`/`--t2` que ya traen `.btn`,
`.inp` y `.barra`. La web pública tiene spotlight, glow radial y reveals al hacer scroll:
**nada de eso entra aquí**.

---

## 8. Authentication & Authorization

Dos mecanismos distintos y deliberadamente separados, porque protegen dos cosas distintas.

### 8.1 Admin — HTTP Basic, reutilizando lo que ya existe

`config/admin_auth.php` ya está en el repo, ya valida con `hash_equals()` + `password_verify()`
contra `config/secrets.php`, y ya lo usan `supertecnicas/admin.php` y los endpoints de
`api/`. **No se escribe un sistema de admin nuevo.** Cada `dashboard/admin*.php` empieza así:

```php
require_once __DIR__ . '/../config/admin_auth.php';
requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin');
session_start();   // aparte del Basic Auth: hace falta para el token CSRF
```

Las dos claves nuevas se añaden **al final** de `config/secrets.example.php` (paso 04), con el
comentario que ya usa ese fichero para explicar cómo generar el hash:

```php
    // Admin de dashboard/ (independiente del de supertecnicas y del de lesiones).
    // Genera el hash con: php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"
    'admin_dashboard_user' => 'admin_plantillas',
    'admin_dashboard_pass_hash' => '',
```

El `secrets.php` real no está versionado y se rellena a mano en el servidor (§14).

**No existe un rol ADMIN en `usuarios.json`.** Esto no es un atajo: significa que no hay ninguna
ruta por la que una cuenta de presidente pueda escalar a admin, porque el admin no vive en el mismo
almacén. Es una separación de mecanismo, no de permiso.

### 8.2 Presidente — sesión PHP contra `usuarios.json`

Login en `dashboard/index.php` (paso 05). Contra `usuarios.json`:

```php
$usuario = plBuscarUsuarioPorEmail($email);
if ($usuario !== null && !empty($usuario['activo']) && password_verify($clave, $usuario['hash'])) {
    session_regenerate_id(true);          // antes de escribir nada en la sesión: evita fijación
    $_SESSION['pl_usuario_id'] = $usuario['id'];
    header('Location: index.php'); exit;
}
// Mensaje único para email inexistente, contraseña incorrecta y cuenta desactivada:
// distinguirlos convierte el login en un oráculo de qué correos existen.
$error = plT('login.error');
```

Reglas de sesión:

- `session_regenerate_id(true)` **antes** de guardar el id de usuario.
- Cada pantalla resuelve `plUsuarioActual()`, que relee `usuarios.json`: si el usuario ya no existe
  o tiene `activo:false`, la sesión se destruye y se redirige al login. **Desactivar a un presidente
  le corta el acceso en su siguiente petición**, sin esperar a que caduque nada.
- `logout.php` hace `session_destroy()` y redirige.
- Un presidente con `equipoId: null` entra correctamente y ve un aviso de «aún no tienes equipo
  asignado». No es un error, no es una pantalla en blanco: es el estado normal de un presidente
  recién creado al que el admin todavía no ha asignado club.

### 8.3 Autorización — una sola función, llamada en el servidor, siempre

Todo el control de acceso pasa por `dominio.php`, que es puro y por tanto testeable sin arnés:

```php
plPuedeEditarPlantilla(string $fase): bool          // fase === 'ROSTER'
plPuedeEditarClausulas(string $fase): bool          // fase === 'CLAUSULAS'
plPuedeMarcarClausulado(string $fase): bool         // fase === 'MERCADO'
plPuedeClausular(array $usuario, array $jugadorDe, string $equipoComprador): bool
```

`plPuedeClausular()` devuelve `true` solo si se cumple **todo**:

1. `$usuario['equipoId']` no es `null`;
2. el jugador **no** pertenece al equipo del usuario;
3. `$equipoComprador === $usuario['equipoId']` — un presidente solo puede clausular *para su propio
   equipo*, nunca en nombre de otro;
4. el jugador está `DISPONIBLE` — no se revierte ni se roba una clausulación ya registrada.

### 8.4 La matriz completa

| Acción | Presidente | Admin |
|---|---|---|
| Ver su propio equipo | Sí | Sí |
| Ver equipos ajenos | Solo vía `mercado.php`, en cualquier fase | Sí, completo |
| Editar su plantilla | Solo en `ROSTER` | Sí, en cualquier fase |
| Editar plantilla ajena | **Nunca** | Sí |
| Editar sus cláusulas | Solo en `CLAUSULAS` | Sí, en cualquier fase |
| Marcar clausulado | Solo en `MERCADO`, sobre otro equipo, comprador = el suyo | Sí, siempre, cualquier combinación |
| Revertir/corregir clausulado | **Nunca** | Sí |
| Cambiar de fase | **Nunca** | Sí, incluso forzando el orden |
| Crear temporada | **Nunca** | Sí |
| Editar equipos, presidentes, tiers | **Nunca** | Sí |

**Los límites de rango no son un permiso.** 20 jugadores, 250M de cap y 650M de cláusulas se
validan igual para el admin que para el presidente. El admin puede saltarse el *orden* de las fases;
no puede saltarse la *aritmética*. Está así en la especificación (§3) y es lo que evita que una
corrección administrativa deje un equipo en un estado que la app luego no sabe representar.

### 8.5 CSRF

Token de sincronizador por sesión, idéntico al de `supertecnicas/lib.php`:

```php
function plTokenCsrf(): string {
    if (empty($_SESSION['pl_csrf'])) { $_SESSION['pl_csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['pl_csrf'];
}
function plCsrfValido(): bool {
    $enviado = $_POST['csrf'] ?? '';
    return !empty($_SESSION['pl_csrf']) && is_string($enviado)
        && hash_equals($_SESSION['pl_csrf'], $enviado);
}
```

**Todo** formulario que escribe lleva `<input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">`,
y **todo** manejador de POST devuelve 403 si `plCsrfValido()` es falso, antes de mirar ninguna otra
cosa. Incluye los formularios del admin: el Basic Auth autentica, no protege contra CSRF.

---
## 9. BUILD ORDER

18 pasos, de cero a desplegable. Cada paso cabe en una sesión y termina con el subproyecto en un
estado que arranca y pasa sus propias comprobaciones.

**PHP se invoca siempre como `/c/xampp/php/php.exe`, desde la raíz del proyecto (`htdocs/`).** No
está en el `PATH` de esta máquina; se comprobó en el momento de escribir este blueprint. Si algún
día se añade al `PATH`, `php` a secas funciona igual. Esa ruta es de la máquina de desarrollo, no
del servidor: en IONOS no se ejecuta ningún comando de este blueprint (§12).

**Todos los tests usan `dashboard/tests/arnes.php`**, que llega con el copiado de `workspace/`
(§19.6) antes del paso 01 y aporta `plVerificar()`, `plSalirConResultado()`, `plArnesPreparar()`,
`plArnesRender()`, `plArnesDirDatos()` y `plArnesLimpiar()`.

### Mapa de pasos

| # | Paso | Depende de | Toca | Puerta |
|---|---|---|---|---|
| 01 | Esqueleto y helpers de fichero | — | `lib.php`, `tests/test_lib.php` | `test_lib.php` sale 0 y `lib.php` se ejecuta de verdad |
| 02 | Reglas de dominio puras | 01 | `dominio.php`, `tests/test_dominio.php` | `test_dominio.php` sale 0 |
| 03 | Almacén, semillas y `rev` | 02 | `almacen.php`, `tests/test_almacen.php` | `test_almacen.php` sale 0 |
| 04 | Admin de temporada y fases | 03 | `admin.php`, `secrets.example.php`, `tests/test_temporada.php` | `test_temporada.php` sale 0 |
| 05 | Login, sesión y envoltorio | 03 | `index.php`, `logout.php`, `chrome.php`, `i18n.php`, `tests/test_login.php` | `test_login.php` sale 0 |
| 06 | Dashboard del presidente | 05 | `index.php`, `tests/test_dashboard.php` | `test_dashboard.php` sale 0 |
| 07 | Mi plantilla | 06 | `plantilla.php`, `tests/test_plantilla.php` | `test_plantilla.php` sale 0 |
| 08 | Pegado masivo | 07 | `pegado.php`, `tests/test_pegado.php` | `test_pegado.php` sale 0 |
| 09 | Cláusulas | 07 | `clausulas.php`, `tests/test_clausulas.php` | `test_clausulas.php` sale 0 |
| 10 | Mercado y registro | 09 | `mercado.php`, `tests/test_mercado.php` | `test_mercado.php` sale 0 |
| 11 | Admin · equipos e importador | 04 | `admin_equipos.php`, `almacen.php`, `chrome.php`, `admin.php`, `tests/test_equipos.php` | `test_equipos.php` sale 0 |
| 12 | Admin · presidentes | 04 | `admin_presidentes.php`, `almacen.php`, `tests/test_presidentes.php` | `test_presidentes.php` sale 0 |
| 13 | Admin · tiers | 04 | `admin_tiers.php`, `tests/test_tiers.php` | `test_tiers.php` sale 0 |
| 14 | Admin · plantillas y correcciones | 10, 11 | `admin_plantillas.php`, `tests/test_admin_plantillas.php` | `test_admin_plantillas.php` sale 0 |
| 15 | Motor de i18n con los diez idiomas | 10 | `i18n.php`, `tests/test_i18n.php` | `test_i18n.php` sale 0 |
| 16 | Traducir el envoltorio y las dos pantallas base | 15 | `chrome.php`, `index.php`, `plantilla.php`, `tests/test_traduccion.php` | `test_traduccion.php` sale 0 |
| 17 | Traducir pegado, cláusulas y mercado | 16 | `pegado.php`, `clausulas.php`, `mercado.php`, `tests/test_traduccion_mercado.php` | `test_traduccion_mercado.php` sale 0 |
| 18 | CSS, responsive y despliegue | 17 | `css/dashboard.css`, `DESPLIEGUE.md`, `tests/test_css.php` | `test_css.php` sale 0 y el barrido global pasa |

**Por qué este orden.** Las reglas puras van primero (pasos 01-03) porque son lo único de este
proyecto con aritmética real —cap, 650M, límite de 20— y todo lo demás las llama; probarlas sin
arnés, sin sesión y sin ficheros es lo más barato que se puede hacer. La máquina de fases (paso 04)
va antes que el login porque cada pantalla de presidente pregunta por la fase antes de decidir si es
editable: sin ella, las pantallas no tendrían contra qué comprobarse. El mercado (paso 10) va
después de cláusulas porque necesita jugadores con cláusula asignada para que la pantalla enseñe
algo real. El i18n va al final (paso 15) y no al principio a propósito: traducir pantallas que
todavía cambian de texto es trabajo que se tira dos veces.

---

#### Paso 01 — Esqueleto y helpers de fichero

**Do**

Crear `dashboard/` y escribir `dashboard/lib.php`, el único módulo que toca el sistema de
ficheros a bajo nivel. Contiene, con las firmas ya en forma explícita (`?Tipo $x = null`, nunca
`Tipo $x = null`, deprecado en PHP 8.4 — §2):

- Guard de versión: `if (version_compare(PHP_VERSION, '8.1.0', '<')) { die('Plantillas requiere PHP 8.1.0 o superior.'); }`. Va en la **primera línea ejecutable** del fichero, antes de declarar nada.
- `plCargarJson(string $ruta, array $porDefecto): array` — devuelve `$porDefecto` si el fichero no existe **o si `json_decode` no devuelve un array**. Nunca lanza, nunca crea el fichero.
- `plGuardarJsonAtomico(string $ruta, array $data): bool` — el patrón de `supertecnicas/lib.php:44`: `mkdir` del directorio si falta → `fopen("$ruta.lock", 'c')` → `flock(LOCK_EX)` → `tempnam(dirname($ruta), 'pl_')` → `file_put_contents` con `JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT` → `chmod(0644)` → `rename($tmp, $ruta)` → `flock(LOCK_UN)` → `fclose`. Si algo falla, borra el temporal y devuelve `false`.
- `plEsc($t): string` — `htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8')`.
- `plNormalizarTexto($texto): string` — acentos a ASCII, minúsculas, símbolos a espacio, espacios colapsados. Copia literal de `stNormalizarTexto()`. Se usa para detectar nombres duplicados (paso 14).
- `plTokenCsrf(): string` y `plCsrfValido(): bool` — §8.5.
- `plAhora(): string` — `date('c')`, el sello de tiempo de `registro.json`.

Escribir `dashboard/tests/test_lib.php` usando el arnés.

**Done when**

- [ ] CUANDO se cargue `lib.php` bajo un PHP anterior a 8.1.0 EL SISTEMA DEBERÁ terminar con un mensaje que nombre la versión mínima, sin imprimir HTML.
- [ ] CUANDO `plGuardarJsonAtomico()` escriba sobre un fichero que **ya existe** EL SISTEMA DEBERÁ dejar el contenido nuevo íntegro y no dejar ningún fichero `pl_*` suelto en el directorio.
- [ ] CUANDO `plGuardarJsonAtomico()` reciba texto acentuado EL SISTEMA DEBERÁ escribirlo legible en UTF-8 y NO como secuencias `\uXXXX`.
- [ ] CUANDO `plCargarJson()` reciba una ruta inexistente EL SISTEMA DEBERÁ devolver el valor por defecto y NO DEBERÁ crear el fichero.
- [ ] CUANDO `plCargarJson()` lea un fichero cuyo contenido no sea JSON válido EL SISTEMA DEBERÁ devolver el valor por defecto en lugar de propagar un error.
- [ ] CUANDO `plEsc()` reciba comillas simples y dobles EL SISTEMA DEBERÁ escapar ambas.
- [ ] CUANDO `plCsrfValido()` reciba un `$_POST['csrf']` vacío o ausente EL SISTEMA DEBERÁ devolver `false`.
- [ ] CUANDO se ejecute `test_lib.php` EL SISTEMA DEBERÁ imprimir `Todas las comprobaciones pasan.` y salir 0.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/lib.php
# expect: No syntax errors detected

/c/xampp/php/php.exe -l dashboard/tests/arnes.php
# expect: No syntax errors detected

# EJECUTA lib.php de verdad, no solo lo parsea: carga el módulo e invoca dos
# funciones. Los dos comandos ASERTAN sobre la salida en vez de limitarse a
# imprimirla: un `php -r "echo …"` suelto sale 0 pase lo que pase y no sería
# una puerta, solo una impresión.
/c/xampp/php/php.exe -r "require 'dashboard/lib.php'; echo plEsc(\"<a href='x'>\");" | grep -qF "&lt;a href=&#039;x&#039;&gt;"
# expect: exit 0

/c/xampp/php/php.exe -r "require 'dashboard/lib.php'; \$r=sys_get_temp_dir().'/pl_v.json'; plGuardarJsonAtomico(\$r,['x'=>'Montaña']); echo file_get_contents(\$r);" | grep -qF "Montaña"
# expect: exit 0  (el acento viaja legible, no como \uXXXX)

/c/xampp/php/php.exe dashboard/tests/test_lib.php
# expect: Todas las comprobaciones pasan.   (exit 0)

test -f dashboard/data/.htaccess && grep -q 'Require all denied' dashboard/data/.htaccess
# expect: exit 0  — llegó con el copiado de workspace/ (§19.6)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 01: esqueleto de dashboard/ y helpers de fichero"
git tag step-01-esqueleto
```

---

#### Paso 02 — Reglas de dominio puras

**Do**

Escribir `dashboard/dominio.php`. **Es puro: no abre ficheros, no imprime, no lee superglobales, no
llama a `session_*`.** Es lo que lo hace comprobable sin arnés y sin datos en disco.

Constantes: `PL_POSICIONES = ['POR','DEF','MED','ATA']`, `PL_FASES = ['ROSTER','CLAUSULAS','MERCADO','CERRADA']`, `PL_ESTADOS = ['DISPONIBLE','CLAUSULADO']`.

Funciones:

- `plSalarioDeTier(string $tier, array $tiers): ?int` — busca en la lista de `ajustes.tiers`; `null` si el código no existe.
- `plTotalSalarios(array $jugadores): int` · `plTotalClausulas(array $jugadores): int`
- `plValidarAltaJugador(array $jugadores, array $nuevo, array $ajustes): array` — devuelve `['ok'=>bool, 'error'=>?string]`. Comprueba, en este orden: nombre no vacío; posición en `PL_POSICIONES`; tier existente en `$ajustes['tiers']`; que no se supere `maxJugadores`; que el total de salarios con el nuevo no supere `salaryCap`.
- `plValidarCambioTier(array $jugadores, string $jugadorId, string $tierNuevo, array $ajustes): array` — mismo contrato, recalculando el total **sustituyendo** el salario del jugador, no sumándolo.
- `plValidarClausulas(array $jugadores, array $clausulas, array $ajustes): array` — rechaza cualquier cláusula negativa o no entera, y rechaza si el total **supera** `presupuestoClausulas`. **Un total por debajo es válido**: es un borrador.
- `plEstadoPresupuesto(int $total, int $presupuesto): string` — `'INCOMPLETO'` si `< $presupuesto`, `'COMPLETO'` si `===`, `'EXCEDIDO'` si `>`.
- `plPuedeEditarPlantilla`, `plPuedeEditarClausulas`, `plPuedeMarcarClausulado`, `plPuedeClausular` — §8.3, con las cuatro condiciones de `plPuedeClausular()` tal como están enunciadas allí.

Los mensajes de error son **claves de i18n** (`error.cap_superado`), no frases. Las frases viven en
`i18n.php` (paso 15) y llevan marcadores `{total}`, `{cap}`, `{disponible}`.

**Done when**

- [ ] CUANDO se añada un jugador que dejaría el total de salarios en 260M con un cap de 250M EL SISTEMA DEBERÁ devolver `ok:false` con la clave `error.cap_superado`.
- [ ] CUANDO se añada el jugador número 21 EL SISTEMA DEBERÁ devolver `ok:false` con la clave `error.max_jugadores`, aunque el cap lo permita.
- [ ] CUANDO se cambie el tier de un jugador de `S+` a `A+` EL SISTEMA DEBERÁ recalcular el total **sustituyendo** su salario, de modo que un cambio a un tier más barato nunca sea rechazado por cap.
- [ ] CUANDO el reparto de cláusulas sume exactamente 650M EL SISTEMA DEBERÁ devolver `COMPLETO`; cuando sume 580M DEBERÁ devolver `INCOMPLETO` y aceptar el guardado; cuando sume 651M DEBERÁ devolver `EXCEDIDO` y rechazarlo.
- [ ] CUANDO una cláusula sea negativa o no entera EL SISTEMA DEBERÁ rechazar el reparto completo.
- [ ] CUANDO un presidente intente clausular a un jugador de su propio equipo EL SISTEMA DEBERÁ devolver `false`, y también cuando indique como comprador un equipo distinto al suyo, y también cuando el jugador ya esté `CLAUSULADO`.
- [ ] CUANDO se ejecute `test_dominio.php` EL SISTEMA DEBERÁ salir 0 sin haber creado ni leído ningún fichero.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/dominio.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_dominio.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# dominio.php es puro: no puede contener I/O ni superglobales.
# El `test -f` va delante a propósito: `! grep` sobre un fichero que no existe
# devuelve 0 igual que sobre uno limpio, así que sin él la guarda pasaría
# justo cuando el paso ha fallado en crear el fichero.
test -f dashboard/dominio.php && ! grep -nE "file_get_contents|file_put_contents|fopen|\\\$_SESSION|\\\$_POST|\\\$_GET|echo |print " dashboard/dominio.php
# expect: exit 0  (el fichero existe y no hay ninguna coincidencia)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 02: reglas de dominio puras (cap, 20, 650M, fases)"
git tag step-02-dominio
```

---

#### Paso 03 — Almacén, semillas y control de concurrencia

**Do**

Escribir `dashboard/almacen.php`. **Es el único fichero de todo el subproyecto que lee o escribe en
`dashboard/data/`.** Ninguna pantalla llama a `plGuardarJsonAtomico()` directamente.

- `plRutaDatos(string $fichero): string` — resuelve contra `__DIR__.'/data/'`, y **acepta un
  override por `$GLOBALS['PL_DIR_DATOS']`** para que los tests trabajen sobre el directorio aislado
  de `plArnesDirDatos()` y nunca toquen los datos reales. Ese override es la razón de que exista
  esta función en vez de constantes.
- Cargadores y guardadores con las semillas de §4: `plCargarEquipos`/`plGuardarEquipos`,
  `plCargarUsuarios`/`plGuardarUsuarios`, `plCargarTiers`/`plGuardarTiers`,
  `plCargarTemporadas`/`plGuardarTemporadas`, `plCargarTemporada(string $id)`/`plGuardarTemporada`.
- `plTemporadaActiva(): ?array` — resuelve `temporadas.json.activa` y devuelve la entrada completa, o `null` si no hay ninguna.
- `plGuardarEquipoTemporada(string $temporadaId, string $equipoId, array $datos, int $revEsperado): array` — **el corazón de la concurrencia optimista.** Bajo el mismo `flock`: relee el fichero, compara `rev` con `$revEsperado`, y si difieren devuelve `['ok'=>false,'error'=>'error.rev_desfasado']` **sin escribir nada**. Si coinciden, incrementa `rev` y escribe.
- `plRegistrarEvento(string $tipo, string $actor, string $actorNombre, array $detalle): bool` — **append-only**: lee `registro.json`, añade al final, guarda. Nunca modifica ni borra una entrada existente.
- `plCrearTemporada(string $id, string $nombre): array` — crea `temporada-XXXX-YY.json` con `equipos` vacíos para todos los equipos con `activo:true`, `rev:0`, y **congela `ajustes.tiers` copiando `tiers.json`**. Falla si el id ya existe.
- `plUsuarioActual(): ?array` · `plBuscarUsuarioPorEmail(string $email): ?array`

**Done when**

- [ ] CUANDO se cargue un fichero de datos que aún no existe EL SISTEMA DEBERÁ devolver la semilla de §4 sin escribir nada en disco.
- [ ] CUANDO se cree una temporada nueva EL SISTEMA DEBERÁ escribir un `ajustes.tiers` con los diez tiers copiados de `tiers.json` **en ese instante**, y una entrada de `equipos` vacía con `rev:0` por cada equipo `activo:true`.
- [ ] CUANDO se cree una temporada nueva EL SISTEMA DEBERÁ dejar el fichero de la temporada anterior byte a byte intacto y NO DEBERÁ copiar ni un jugador, cláusula, posición o tier de ella.
- [ ] CUANDO se modifique `tiers.json` después de crear una temporada EL SISTEMA DEBERÁ seguir devolviendo los salarios congelados en `ajustes.tiers` de esa temporada.
- [ ] CUANDO `plGuardarEquipoTemporada()` reciba un `rev` distinto al del disco EL SISTEMA DEBERÁ devolver `error.rev_desfasado` y dejar el fichero sin modificar.
- [ ] CUANDO `plGuardarEquipoTemporada()` reciba el `rev` correcto EL SISTEMA DEBERÁ incrementarlo exactamente en 1.
- [ ] CUANDO `plRegistrarEvento()` se llame tres veces EL SISTEMA DEBERÁ dejar exactamente tres entradas, en orden de llegada, sin alterar las anteriores.
- [ ] CUANDO se ejecute `test_almacen.php` EL SISTEMA DEBERÁ salir 0 operando sobre un directorio temporal, dejando `dashboard/data/` sin tocar.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/almacen.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_almacen.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# El test no puede haber escrito en los datos reales.
test ! -f dashboard/data/temporadas.json || git check-ignore -q dashboard/data/temporadas.json
# expect: exit 0

# almacen.php es el único que LLAMA a plGuardarJsonAtomico(). Se excluyen dos
# ficheros y no uno: lib.php aparece en el grep porque es quien DEFINE la
# función, no porque la use. Excluir solo almacen.php haría que esta guarda
# fallara sobre una implementación correcta.
test "$(grep -lE 'plGuardarJsonAtomico\(' dashboard/*.php | grep -vE 'dashboard/(almacen|lib)\.php' | wc -l)" = "0"
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 03: almacen con semillas, rev optimista y registro append-only"
git tag step-03-almacen
```

---

#### Paso 04 — Admin de temporada y máquina de fases

**Do**

Escribir `dashboard/admin.php`: HTTP Basic de §8.1, `session_start()` para el CSRF, y la sección
**Temporada** con los tres botones de transición y `+ EMPEZAR NUEVA TEMPORADA`.

- Cabecera del panel: `TEMPORADA {nombre} — Fase: {badge}`, número de equipos y número de jugadores inscritos, las cifras en `--f-mono`.
- Tres transiciones: `ROSTER → CLAUSULAS`, `CLAUSULAS → MERCADO`, `MERCADO → CERRADA`. Y, como el admin puede **forzar** cualquier transición (§3 de la especificación), un selector de fase libre además de los tres botones.
- **Antes de cerrar una fase, informe de equipos incompletos**, que avisa pero no bloquea: al salir de `ROSTER`, los equipos con menos de 20 jugadores; al salir de `CLAUSULAS`, los que no suman 650M exactos. Es la mitigación del riesgo R1 (§20).
- `+ EMPEZAR NUEVA TEMPORADA` pide el id (`2027-28`) y el nombre (`2027/28`), y abre un `<dialog>` con el texto literal de la especificación: *«¿Empezar nueva temporada? Se crearán plantillas vacías para todos los equipos. Los presidentes tendrán que volver a inscribir a sus jugadores.»* con `[Cancelar] [Empezar temporada]`.
- Toda transición y toda creación escriben en `registro.json` (`tipo` `FASE` y `TEMPORADA`, `actor:"admin"`).

Añadir **al final** de `config/secrets.example.php` las dos claves de §8.1. No se toca nada más de
ese fichero.

**Done when**

- [ ] CUANDO se pida `dashboard/admin.php` sin credenciales Basic válidas EL SISTEMA DEBERÁ responder 401 y NO DEBERÁ imprimir ningún dato de la liga.
- [ ] CUANDO el admin confirme una transición de fase EL SISTEMA DEBERÁ escribir la nueva fase en `temporadas.json` y añadir exactamente un evento `FASE` a `registro.json`.
- [ ] CUANDO el admin vaya a cerrar `ROSTER` EL SISTEMA DEBERÁ listar los equipos con menos de 20 jugadores y DEBERÁ permitir continuar de todas formas.
- [ ] CUANDO el admin vaya a cerrar `CLAUSULAS` EL SISTEMA DEBERÁ listar los equipos cuyo total no sea exactamente 650M y DEBERÁ permitir continuar de todas formas.
- [ ] CUANDO el admin cree una temporada nueva EL SISTEMA DEBERÁ dejarla en fase `ROSTER` con todas las plantillas y cláusulas vacías, y DEBERÁ pasar a ser la temporada activa.
- [ ] CUANDO se envíe un POST al panel sin token CSRF válido EL SISTEMA DEBERÁ responder 403 y no modificar ningún fichero.
- [ ] CUANDO `config/secrets.example.php` se lea tras este paso EL SISTEMA DEBERÁ contener `admin_dashboard_user` y `admin_dashboard_pass_hash`, conservando intactas todas las claves anteriores.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin.php
# expect: No syntax errors detected

/c/xampp/php/php.exe -l config/secrets.example.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_temporada.php
# expect: Todas las comprobaciones pasan.   (exit 0)

grep -q "admin_dashboard_user" config/secrets.example.php && grep -q "admin_dashboard_pass_hash" config/secrets.example.php
# expect: exit 0

# Las claves anteriores siguen ahí: se añadió, no se reescribió.
grep -q "admin_supertecnicas_user" config/secrets.example.php && grep -q "admin_lesiones_user" config/secrets.example.php
# expect: exit 0

# El panel exige Basic Auth antes de cualquier otra cosa.
grep -q "requerirAdminBasicAuthConClaves" dashboard/admin.php
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ config/secrets.example.php && git commit -m "paso 04: admin de temporada, transiciones de fase y nueva temporada"
git tag step-04-admin-temporada
```

---

#### Paso 05 — Login de presidente, sesión y envoltorio de pantalla

**Do**

Cuatro ficheros:

- `dashboard/i18n.php` — **solo el esqueleto y el español en este paso.** `PL_IDIOMAS`, `PL_BANDERAS`, `plResolverIdioma()`, `plEstablecerIdioma()`, `plT()`, `plRenderSelectorIdioma()`. Los otros nueve idiomas llegan en el paso 15. `plT()` ya cae a español y devuelve `'['.$clave.']'` si la clave no existe: eso hace que una clave sin traducir se vea a simple vista en vez de romper la página. **Ojo: `'pl'` en `PL_IDIOMAS` es polaco, no el prefijo de funciones.**
- `dashboard/chrome.php` — `plCabecera(string $titulo, string $activo)` y `plPie()`, según §6.
- `dashboard/index.php` — login por email + contraseña (§8.2) y, tras autenticar, el dashboard del paso 06. En este paso basta con que tras el login redirija a sí mismo y muestre el nombre del equipo.
- `dashboard/logout.php`.

**Done when**

- [ ] CUANDO se pida cualquier pantalla de presidente sin sesión válida EL SISTEMA DEBERÁ redirigir al login y NO DEBERÁ imprimir dato alguno de ningún equipo.
- [ ] CUANDO un email no exista, la contraseña sea incorrecta, o la cuenta tenga `activo:false` EL SISTEMA DEBERÁ mostrar **el mismo** mensaje de error en los tres casos.
- [ ] CUANDO un login sea correcto EL SISTEMA DEBERÁ invocar `session_regenerate_id(true)` antes de escribir el id de usuario en la sesión.
- [ ] CUANDO el admin desactive a un presidente con sesión abierta EL SISTEMA DEBERÁ cerrarle la sesión en su siguiente petición.
- [ ] CUANDO el usuario autenticado tenga `equipoId: null` EL SISTEMA DEBERÁ mostrar el aviso de «aún no tienes equipo asignado» y NO DEBERÁ producir un error ni una página en blanco.
- [ ] CUANDO se pinte cualquier pantalla EL SISTEMA DEBERÁ emitir un único `<h1>`, un `<main id="contenido">`, un `.skip-link` y `<html lang="…">` con el idioma activo.
- [ ] CUANDO se pida una clave de traducción inexistente EL SISTEMA DEBERÁ devolver `[clave]` y NO DEBERÁ interrumpir el renderizado.

**Verify**

```bash
for f in dashboard/i18n.php dashboard/chrome.php dashboard/index.php dashboard/logout.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
# expect: No syntax errors detected (×4), exit 0

/c/xampp/php/php.exe dashboard/tests/test_login.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Un solo <h1> y los landmarks obligatorios en el HTML del login. Va envuelto
# en `test` para que sea una puerta: imprimir el resultado saldría 0 siempre.
test "$(/c/xampp/php/php.exe -r "require 'dashboard/tests/arnes.php'; plArnesPreparar(); \$h=plArnesRender('dashboard/index.php'); echo substr_count(\$h,'<h1'), (int)str_contains(\$h,'id=\"contenido\"'), (int)str_contains(\$h,'skip-link');")" = "111"
# expect: exit 0  (un h1, main#contenido presente, skip-link presente)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 05: login de presidente, sesion, i18n en espanol y envoltorio"
git tag step-05-login
```

---

#### Paso 06 — Dashboard del presidente

**Do**

Completar `dashboard/index.php` con el dashboard de §7 de la especificación: el bloque MI EQUIPO
(nombre, presidente, temporada, badge de fase) y las cuatro tarjetas —PLANTILLA `X / 20`, SALARY CAP
`225M / 250M (25M disponibles)`, CLÁUSULAS `580M / 650M (70M disponibles)`, MERCADO
`🟢 ABIERTO` / `🔴 CERRADO`— más la tabla MI PLANTILLA en solo lectura con Jugador, Pos., Tier,
Salario, Cláusula y Estado.

Todas las cifras en `--f-mono`. El badge de fase indica al presidente qué pantalla tiene habilitada.
El estado del mercado se deriva de la fase, no de un campo aparte.

**Done when**

- [ ] CUANDO la fase sea `MERCADO` EL SISTEMA DEBERÁ mostrar el mercado como ABIERTO; en `ROSTER`, `CLAUSULAS` y `CERRADA` DEBERÁ mostrarlo CERRADO.
- [ ] CUANDO el equipo tenga 18 jugadores EL SISTEMA DEBERÁ mostrar `18 / 20` y NO DEBERÁ mostrar el número de plazas como negativo en ningún caso.
- [ ] CUANDO los salarios sumen 225M con un cap de 250M EL SISTEMA DEBERÁ mostrar los 25M disponibles calculados, no un valor almacenado.
- [ ] CUANDO un jugador esté `CLAUSULADO` EL SISTEMA DEBERÁ mostrarlo con el texto «Clausulado por {equipo}», nunca solo con un color.
- [ ] CUANDO el equipo no tenga ningún jugador EL SISTEMA DEBERÁ mostrar un estado vacío con la acción que corresponda a la fase actual, no una tabla vacía sin explicación.
- [ ] CUANDO se ejecute `test_dashboard.php` EL SISTEMA DEBERÁ salir 0.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/index.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_dashboard.php
# expect: Todas las comprobaciones pasan.   (exit 0)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 06: dashboard del presidente con cap, clausulas y fase"
git tag step-06-dashboard
```

---

#### Paso 07 — Mi plantilla

**Do**

`dashboard/plantilla.php`: alta, edición y borrado de jugador. Formulario con Nombre (texto),
Posición (`<select>` de las cuatro), Tier (`<select>` de los diez, leídos de `ajustes.tiers`) y el
**Salario mostrado como texto calculado, nunca como `<input>`**.

Cada formulario lleva el `rev` actual del equipo en un `hidden`. El POST llama a
`plValidarAltaJugador()` / `plValidarCambioTier()` y después a `plGuardarEquipoTemporada()` con ese
`rev`. Los mensajes de error son los de la especificación §14, con las cifras reales sustituidas.

Fuera de `ROSTER`, la pantalla se sirve en solo lectura con el aviso literal: *«No puedes modificar
tu plantilla. La fase de inscripción de plantillas ya ha finalizado.»* — **y el servidor rechaza el
POST igualmente**, no solo la interfaz.

**Done when**

- [ ] CUANDO la fase no sea `ROSTER` EL SISTEMA DEBERÁ rechazar todo POST de plantilla en el servidor, aunque el formulario se reenvíe a mano.
- [ ] CUANDO se intente añadir un jugador que dejaría el total en 260M con cap de 250M EL SISTEMA DEBERÁ rechazarlo mostrando el total resultante y los millones disponibles, y NO DEBERÁ escribir nada.
- [ ] CUANDO se intente añadir el jugador número 21 EL SISTEMA DEBERÁ rechazarlo indicando el límite de 20.
- [ ] CUANDO se cambie el tier de un jugador EL SISTEMA DEBERÁ recalcular su salario a partir de `ajustes.tiers` y DEBERÁ rechazar el cambio si el nuevo total supera el cap.
- [ ] CUANDO el formulario se envíe con un `rev` distinto al del disco EL SISTEMA DEBERÁ rechazar el guardado con el aviso de copresidente y DEBERÁ dejar el fichero sin modificar.
- [ ] CUANDO un guardado tenga éxito EL SISTEMA DEBERÁ redirigir (POST/Redirect/GET) de modo que recargar no reenvíe el formulario.
- [ ] CUANDO se envíe un salario manipulado a mano en el POST EL SISTEMA DEBERÁ ignorarlo y usar el derivado del tier.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/plantilla.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_plantilla.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# El salario nunca es un campo editable. El `test -f` evita que la guarda pase
# vacíamente si el fichero no llegó a crearse.
test -f dashboard/plantilla.php && ! grep -nE "name=[\"']salario[\"']" dashboard/plantilla.php
# expect: exit 0  (el fichero existe y no hay ninguna coincidencia)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 07: mi plantilla con cap, limite de 20 y rev optimista"
git tag step-07-plantilla
```

---

#### Paso 08 — Pegado masivo de plantilla

**Do**

`dashboard/pegado.php`. Es la mitigación del riesgo R1 (§20) y por eso está en la v1, no en una v2:
teclear 20 jugadores por equipo en formularios es más lento que la hoja de cálculo que esta app
sustituye, y una herramienta más lenta que lo que reemplaza no se adopta.

Un `<textarea>` que acepta una línea por jugador con el formato `Nombre;POS;TIER`. Al enviar:

1. Se parsea cada línea y se muestra una **previsualización** en tabla, con el error indicado por
   línea (posición desconocida, tier desconocido, nombre vacío, línea con número de campos incorrecto).
2. Se muestran el total de salarios resultante y el número de jugadores resultante.
3. Solo si **todo** el lote es válido y no supera ni el cap ni los 20, aparece el botón de confirmar.
4. La confirmación escribe el lote entero con un solo `plGuardarEquipoTemporada()`.

**El lote es atómico: o entra entero o no entra nada.** Importar la mitad de una plantilla y dejar
al presidente adivinando cuáles faltaron sería peor que rechazar el lote.

**Done when**

- [ ] CUANDO se pegue un lote con una línea inválida EL SISTEMA DEBERÁ señalar el número de línea y su motivo, y NO DEBERÁ ofrecer el botón de confirmar.
- [ ] CUANDO un lote válido por sí mismo hiciera superar el cap de 250M EL SISTEMA DEBERÁ rechazarlo entero indicando el total resultante.
- [ ] CUANDO un lote hiciera pasar de 20 jugadores EL SISTEMA DEBERÁ rechazarlo entero indicando cuántas plazas quedan.
- [ ] CUANDO se confirme un lote válido de 20 líneas EL SISTEMA DEBERÁ escribir los 20 jugadores en una sola operación e incrementar `rev` exactamente en 1.
- [ ] CUANDO el pegado incluya espacios sobrantes o líneas en blanco EL SISTEMA DEBERÁ ignorar las vacías y recortar los espacios sin considerarlo un error.
- [ ] CUANDO la fase no sea `ROSTER` EL SISTEMA DEBERÁ rechazar el pegado en el servidor.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/pegado.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_pegado.php
# expect: Todas las comprobaciones pasan.   (exit 0)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 08: pegado masivo Nombre;POS;TIER con previsualizacion atomica"
git tag step-08-pegado
```

---

#### Paso 09 — Cláusulas

**Do**

`dashboard/clausulas.php`. Cabecera con `Presupuesto total 650M`, `Asignado`, `Disponible`, la
barra de §7 y el estado en texto. Tabla con Jugador, Tier, Salario y un `<input type="number">` de
cláusula por jugador. Botón `GUARDAR CLÁUSULAS`.

**La regla, tal como se decidió:** el total no puede **superar** 650M nunca; guardar por debajo
**sí se permite** y deja al equipo marcado `INCOMPLETO`; el ✓ `Presupuesto completo` aparece
únicamente en 650M exactos. Guardar por debajo es el borrador: un presidente que ha repartido 600M
y aún duda de los últimos 50M no debe perder el reparto por no haber cuadrado.

Contador en vivo en cliente (§5), **revalidado íntegro en el servidor**. `rev` optimista como en el
paso 07. Fuera de `CLAUSULAS`: en `ROSTER` se muestra deshabilitada con «Termina primero tu
inscripción de plantilla»; en `MERCADO` y `CERRADA`, en solo lectura.

**Done when**

- [ ] CUANDO el total supere 650M EL SISTEMA DEBERÁ rechazar el guardado indicando los millones disponibles y NO DEBERÁ escribir nada.
- [ ] CUANDO el total sea inferior a 650M EL SISTEMA DEBERÁ guardar el reparto y marcar el equipo `INCOMPLETO`.
- [ ] CUANDO el total sea exactamente 650M EL SISTEMA DEBERÁ guardar y mostrar `Presupuesto completo`.
- [ ] CUANDO una cláusula sea 0 EL SISTEMA DEBERÁ aceptarla — no hay mínimo por jugador.
- [ ] CUANDO se desactive JavaScript EL SISTEMA DEBERÁ seguir permitiendo guardar y DEBERÁ seguir validando el total en el servidor.
- [ ] CUANDO la fase sea `ROSTER` EL SISTEMA DEBERÁ mostrar el aviso de «Termina primero tu inscripción de plantilla» y rechazar todo POST.
- [ ] CUANDO el `rev` enviado no coincida con el del disco EL SISTEMA DEBERÁ rechazar el guardado con el aviso de copresidente.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/clausulas.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_clausulas.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# El servidor revalida: la validación no puede vivir solo en el <script>.
grep -q "plValidarClausulas" dashboard/clausulas.php
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 09: reparto de clausulas con borrador y 650M exactos"
git tag step-09-clausulas
```

---

#### Paso 10 — Mercado y registro de clausulaciones

**Do**

`dashboard/mercado.php`. Listado de **todos** los jugadores de **todos** los equipos, con Jugador,
Equipo, Pos., Tier, Salario, Cláusula y Estado. Consulta libre en **cualquier** fase.

En fase `MERCADO`, junto a cada jugador de otro equipo que esté `DISPONIBLE`, el botón
`Marcar como clausulado por mi equipo`, con el `<dialog>` de confirmación y el texto literal de la
especificación: *«¿Confirmas que {mi equipo} ha clausulado a {jugador} ({su equipo})? Esto solo
registra el resultado; el pago se gestiona fuera de la app.»* con `[Cancelar] [Confirmar clausulación]`.

El POST comprueba `plPuedeMarcarClausulado($fase)` **y** `plPuedeClausular()` con sus cuatro
condiciones (§8.3), escribe `estado`, `clausuladoPor` y `clausuladoEn` en el jugador, y añade un
evento `CLAUSULACION` a `registro.json` con actor, nombre del actor y sello de tiempo. Ese registro
es la mitigación del riesgo R4: sin él, una disputa se arbitra de memoria.

Filtro por equipo y por estado. Buscador por nombre. La tabla va dentro de un contenedor con
`overflow-x:auto` (§15).

**Done when**

- [ ] CUANDO la fase no sea `MERCADO` EL SISTEMA DEBERÁ mostrar el listado completo en solo lectura y DEBERÁ rechazar en el servidor todo intento de marcar clausulado.
- [ ] CUANDO un presidente marque a un jugador de otro equipo estando en `MERCADO` EL SISTEMA DEBERÁ ponerlo `CLAUSULADO` con `clausuladoPor` igual a **su propio** `equipoId` y añadir exactamente un evento `CLAUSULACION`.
- [ ] CUANDO un presidente intente marcar a un jugador de **su propio** equipo EL SISTEMA DEBERÁ rechazarlo en el servidor.
- [ ] CUANDO el POST indique como comprador un equipo distinto al del presidente EL SISTEMA DEBERÁ rechazarlo, aunque el valor venga manipulado en el formulario.
- [ ] CUANDO un jugador ya esté `CLAUSULADO` EL SISTEMA DEBERÁ ocultar el botón a los presidentes y rechazar el POST: un presidente no revierte ni reasigna una clausulación ajena.
- [ ] CUANDO se registre una clausulación EL SISTEMA DEBERÁ dejar las entradas anteriores de `registro.json` intactas.
- [ ] CUANDO el mercado se pinte con 600 jugadores EL SISTEMA DEBERÁ mostrarlos sin scroll horizontal de página, con la tabla desplazándose dentro de su contenedor.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/mercado.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_mercado.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Las dos guardas de autorización se invocan en el camino del POST.
grep -q "plPuedeMarcarClausulado" dashboard/mercado.php && grep -q "plPuedeClausular" dashboard/mercado.php
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 10: mercado consultable y registro append-only de clausulaciones"
git tag step-10-mercado
```

---

#### Paso 11 — Admin · equipos e importador desde la web pública

**Do**

`dashboard/admin_equipos.php`: alta manual, edición de nombre y abreviatura, archivar/desarchivar
(`activo`).

Y el importador: **Importar equipos de la web**, que lee `../datos_oficiales.json` en **solo
lectura**, recorre los equipos con `archivado` vacío, y crea en `equipos.json` los que aún no
existan —comparando por `equipoId`—, arrastrando `nombre`, `nombre_en`, `abreviatura`,
`abreviatura_en`, `escudo` y `color1`. Los ya importados se saltan; el importador es **idempotente**
y ejecutarlo dos veces no duplica nada.

Esto resuelve el problema real que dio origen al diseño: hay equipos nuevos que aún no están en la
web pública —se crean a mano aquí, con `equipoId: null`— y equipos de la web que aquí hay que
archivar. Archivar es local y **no toca `datos_oficiales.json`**.

`test_equipos.php` comprueba la identidad byte a byte del fichero de la web pública así: calcula
`hash_file('sha256', $ruta)` **antes** de invocar el importador y otra vez **después**, y verifica
que coinciden. Se hace con un hash y no con `git status` porque ese fichero es de la web pública y
cambia por su propio flujo de trabajo —archivar un equipo, editar una plantilla—, así que en una
copia de trabajo real casi siempre tiene cambios sin confirmar ajenos a este subproyecto. Un hash
antes/después mide justo lo que interesa: que **el importador** no lo tocó.

**Done when**

- [ ] CUANDO se ejecute el importador EL SISTEMA DEBERÁ crear una entrada por cada equipo de `datos_oficiales.json` con `archivado` vacío que no exista ya, con su `equipoId` apuntando al id de origen.
- [ ] CUANDO el importador se ejecute por segunda vez EL SISTEMA DEBERÁ dejar el número de equipos exactamente igual que tras la primera.
- [ ] CUANDO el importador termine EL SISTEMA DEBERÁ dejar `datos_oficiales.json` con el mismo hash sha256 que antes de ejecutarlo.
- [ ] CUANDO se archive un equipo EL SISTEMA DEBERÁ ponerlo `activo:false` en `equipos.json` y NO DEBERÁ modificar `datos_oficiales.json`.
- [ ] CUANDO se cree un equipo a mano EL SISTEMA DEBERÁ guardarlo con `equipoId: null` y DEBERÁ permitir usarlo en la temporada igual que a uno importado.
- [ ] CUANDO se cree una temporada nueva EL SISTEMA DEBERÁ incluir solo los equipos con `activo:true`.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_equipos.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_equipos.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Nada del subproyecto escribe en el fichero de produccion de la web publica.
test -f dashboard/admin_equipos.php && ! grep -rnE "plGuardarJsonAtomico\([^)]*datos_oficiales|file_put_contents\([^)]*datos_oficiales" dashboard/
# expect: exit 0  (el fichero existe y no hay ninguna coincidencia)

# La identidad byte a byte de datos_oficiales.json antes y despues de importar
# la comprueba test_equipos.php con un hash, NO `git status`: ese fichero es de
# la web publica y cambia por su propio flujo de trabajo (archivar un equipo,
# editar una plantilla), asi que en una copia de trabajo real casi siempre
# tiene cambios sin confirmar que no tienen nada que ver con este subproyecto.
# Una puerta basada en `git status` fallaria por el trabajo de otra persona.
/c/xampp/php/php.exe -r "echo (int) is_readable('datos_oficiales.json');" | grep -qF "1"
# expect: exit 0  (el importador solo necesita poder LEERLO)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 11: admin de equipos e importador idempotente desde la web"
git tag step-11-admin-equipos
```

---

#### Paso 12 — Admin · presidentes

**Do**

`dashboard/admin_presidentes.php`: alta (Nombre, Email, Contraseña, Equipo, Activo), edición,
activar/desactivar y reasignar equipo. La contraseña se guarda con
`password_hash($clave, PASSWORD_DEFAULT)` y **nunca** se muestra ni se devuelve al formulario.

**Los copresidentes son un caso normal, no una excepción:** varios usuarios pueden tener el mismo
`equipoId` y el formulario no lo impide. La pantalla muestra cuántos presidentes gestiona cada
equipo, para que el admin vea de un vistazo dónde hay dos.

Cambiar de presidente entre temporadas es reasignar `equipoId`. No se guarda histórico (§1).

**Done when**

- [ ] CUANDO se cree un presidente EL SISTEMA DEBERÁ guardar únicamente el hash de la contraseña y NO DEBERÁ almacenar el texto en claro en ningún fichero.
- [ ] CUANDO se editen los datos de un presidente sin escribir contraseña nueva EL SISTEMA DEBERÁ conservar el hash anterior.
- [ ] CUANDO se asigne a dos usuarios el mismo `equipoId` EL SISTEMA DEBERÁ aceptarlo y DEBERÁ mostrar que ese equipo tiene dos presidentes.
- [ ] CUANDO se desactive a un presidente EL SISTEMA DEBERÁ impedirle iniciar sesión y DEBERÁ cerrarle la sesión abierta en su siguiente petición.
- [ ] CUANDO se reasigne a un presidente a otro equipo EL SISTEMA DEBERÁ hacer que su siguiente pantalla muestre el equipo nuevo, sin dejar rastro del anterior.
- [ ] CUANDO se intente crear un presidente con un email ya existente EL SISTEMA DEBERÁ rechazarlo.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_presidentes.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_presidentes.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# La contraseña se hashea en el almacén —el único punto por el que pasa toda
# alta o edición—, no en la pantalla: así ninguna pantalla, de hoy o futura,
# puede guardar una contraseña en claro aunque se le olvide hashearla.
grep -q "password_hash" dashboard/almacen.php
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 12: admin de presidentes con hash, copresidentes y reasignacion"
git tag step-12-admin-presidentes
```

---

#### Paso 13 — Admin · tiers

**Do**

`dashboard/admin_tiers.php`: editar el salario de los diez tiers de `tiers.json`. **Los códigos son
fijos**: no se pueden crear, renombrar ni borrar, y `C+`, `C-` y `D` no existen ni existirán desde
esta pantalla.

Aviso permanente y visible en la pantalla: *«Estos salarios se congelan al crear una temporada.
Cambiarlos solo afecta a las temporadas que se creen a partir de ahora; la temporada en curso
conserva los suyos.»* Es la explicación de por qué esta pantalla no puede romper nada, y el
presidente de la liga necesita entenderlo antes de tocar un número.

**Done when**

- [ ] CUANDO se guarde un salario nuevo EL SISTEMA DEBERÁ escribirlo en `tiers.json` y NO DEBERÁ modificar `ajustes.tiers` de ninguna temporada existente.
- [ ] CUANDO se cambie un tier y se recargue la pantalla de plantilla de la temporada en curso EL SISTEMA DEBERÁ seguir mostrando los salarios congelados de esa temporada.
- [ ] CUANDO se cree una temporada después del cambio EL SISTEMA DEBERÁ congelar en ella los salarios nuevos.
- [ ] CUANDO se envíe un POST con un código de tier que no sea uno de los diez EL SISTEMA DEBERÁ ignorarlo.
- [ ] CUANDO se envíe un salario negativo o no entero EL SISTEMA DEBERÁ rechazar el guardado completo.
- [ ] CUANDO se pinte la pantalla EL SISTEMA DEBERÁ mostrar los diez tiers en el orden de `tiers.json`, que es el del desplegable de la plantilla.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_tiers.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_tiers.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Los tiers prohibidos no aparecen como opciones creables en ningun sitio.
test -f dashboard/admin_tiers.php && ! grep -nE "'C\+'|'C-'|'D'" dashboard/admin_tiers.php
# expect: exit 0  (el fichero existe y no hay ninguna coincidencia)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 13: admin de tiers, congelados por temporada"
git tag step-13-admin-tiers
```

---

#### Paso 14 — Admin · plantillas, correcciones y aviso de duplicados

**Do**

`dashboard/admin_plantillas.php`, la vista de arbitraje del admin:

- Tabla global de los equipos de la temporada activa: jugadores `X/20`, total de salarios sobre el
  cap, total de cláusulas sobre 650M, y el estado `COMPLETO` / `INCOMPLETO`.
- Detalle de un equipo con edición completa: el admin puede tocar plantilla y cláusulas **en
  cualquier fase**, pero **con las mismas validaciones de rango** que el presidente (20, 250M,
  650M). Puede saltarse el orden de las fases; no la aritmética.
- Corrección de clausulaciones: estado (`Disponible` / `Clausulado`) y `Clausulado por` (`<select>`
  de equipos), tal como está en §23 de la especificación. Cada corrección escribe un evento
  `CORRECCION` en `registro.json`.
- Vista del `registro.json`, en orden cronológico inverso, con actor, fecha y detalle. Es la
  herramienta con la que se arbitra una disputa (R4).
- **Aviso de nombres duplicados** (R2): agrupa todos los jugadores de la temporada por
  `plNormalizarTexto($nombre)` y avisa de los grupos con más de un jugador en equipos distintos.
  Es un aviso, no un bloqueo: «Endou Mamoru» y «Endo Mamoru» pueden ser dos personas.

**Done when**

- [ ] CUANDO el admin corrija una clausulación EL SISTEMA DEBERÁ actualizar `estado` y `clausuladoPor` y añadir exactamente un evento `CORRECCION` a `registro.json`.
- [ ] CUANDO el admin edite una plantilla en fase `MERCADO` EL SISTEMA DEBERÁ permitirlo pero DEBERÁ rechazar el cambio si deja el equipo por encima de 250M o de 20 jugadores.
- [ ] CUANDO el admin edite cláusulas EL SISTEMA DEBERÁ rechazar cualquier reparto que supere 650M.
- [ ] CUANDO dos jugadores de equipos distintos tengan nombres que normalicen igual EL SISTEMA DEBERÁ listarlos como posible duplicado y DEBERÁ permitir continuar sin corregirlos.
- [ ] CUANDO dos jugadores del **mismo** equipo tengan nombres que normalicen igual EL SISTEMA DEBERÁ listarlos también, señalando el equipo.
- [ ] CUANDO se abra la vista del registro EL SISTEMA DEBERÁ mostrar los eventos del más reciente al más antiguo, con su actor y su sello de tiempo.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/admin_plantillas.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_admin_plantillas.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Las validaciones de rango se aplican tambien en el panel de admin.
grep -q "plValidarAltaJugador" dashboard/admin_plantillas.php && grep -q "plValidarClausulas" dashboard/admin_plantillas.php
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 14: vista global del admin, correcciones y aviso de duplicados"
git tag step-14-admin-plantillas
```

---

#### Paso 15 — Motor de i18n con los diez idiomas

**Do**

Completar `dashboard/i18n.php` con los diez idiomas de `PL_IDIOMAS`
(`es, en, pt, it, fr, ja, ko, pl, bg, sr`) y el selector de banderas de `PL_BANDERAS`, con la misma
forma que `supertecnicas/i18n.php:375`: una fila de `<a href="?lang=xx">` con
`https://flagcdn.com/16x12/{pais}.png`, `width="16" height="12" loading="lazy"`, y `aria-current`
en el activo.

Este paso construye **el motor y el diccionario completo**; aplicarlo a las pantallas son los pasos 16 y 17.
Están separados porque traducir diez idiomas y recorrer seis pantallas son dos trabajos distintos, y
juntos no caben en una sesión.

**Ojo:** `'pl'` en `PL_IDIOMAS` es el código de polaco, no el prefijo de las funciones.

**Done when**

- [ ] CUANDO se pida `?lang=en` EL SISTEMA DEBERÁ resolver el idioma a inglés y recordarlo en la sesión para las siguientes peticiones.
- [ ] CUANDO se pida un `lang` que no esté en `PL_IDIOMAS` EL SISTEMA DEBERÁ ignorarlo y conservar el idioma anterior.
- [ ] CUANDO no haya `lang` ni idioma en sesión EL SISTEMA DEBERÁ deducirlo de `HTTP_ACCEPT_LANGUAGE` y caer a español si no hay coincidencia.
- [ ] CUANDO falte una clave en el idioma activo EL SISTEMA DEBERÁ devolver la versión española de esa clave, y `[clave]` si tampoco existe en español.
- [ ] CUANDO se pinten los diez idiomas EL SISTEMA DEBERÁ tener el mismo juego de claves en todos, sin que a ninguno le falte una que los demás tengan.
- [ ] CUANDO se ejecute `test_i18n.php` EL SISTEMA DEBERÁ salir 0.

**Verify**

```bash
/c/xampp/php/php.exe -l dashboard/i18n.php
# expect: No syntax errors detected

/c/xampp/php/php.exe dashboard/tests/test_i18n.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Los diez idiomas estan declarados.
test "$(/c/xampp/php/php.exe -r "require 'dashboard/i18n.php'; echo count(PL_IDIOMAS);")" = "10"
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 15: motor de i18n con los diez idiomas"
git tag step-15-i18n
```

---

#### Paso 16 — Traducir el envoltorio y las dos pantallas base

**Do**

Aplicar `plT()` a `chrome.php`, `index.php` (login y dashboard) y `plantilla.php`. Sustituir cada
literal por `plT('clave')`, incluidos los mensajes de error que `dominio.php` devuelve como claves y
los avisos de fase. Los marcadores `{total}`, `{cap}`, `{disponible}` se sustituyen **después** de
traducir, nunca antes.

Añadir a `chrome.php` el selector de banderas y `<html lang="…">` con el idioma activo, que es lo
que hace que un lector de pantalla pronuncie el búlgaro como búlgaro.

La traducción va en dos pasos —este y el 17— porque seis pantallas no caben en una sesión y porque
el envoltorio es lo que hay que dejar bien primero: el resto lo hereda.

**Done when**

- [ ] CUANDO se pida `?lang=en` EL SISTEMA DEBERÁ servir el login y el dashboard en inglés, con sus etiquetas, botones y avisos traducidos.
- [ ] CUANDO se pinte cualquiera de estas tres pantallas EL SISTEMA DEBERÁ emitir `<html lang="…">` con el idioma activo.
- [ ] CUANDO se recorra su HTML EL SISTEMA NO DEBERÁ contener ninguna cadena `[clave]` sin traducir en ninguno de los diez idiomas.
- [ ] CUANDO se pinte un error de cap o de límite de 20 EL SISTEMA DEBERÁ mostrarlo traducido y con sus marcadores sustituidos por las cifras reales.
- [ ] CUANDO se pinte el selector de idioma EL SISTEMA DEBERÁ marcar el idioma activo con `aria-current` y dar `alt` a cada bandera.
- [ ] CUANDO se ejecute `test_traduccion.php` EL SISTEMA DEBERÁ salir 0.

**Verify**

```bash
for f in dashboard/chrome.php dashboard/index.php dashboard/plantilla.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
# expect: exit 0

/c/xampp/php/php.exe dashboard/tests/test_traduccion.php
# expect: Todas las comprobaciones pasan.   (exit 0)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 16: plT() en el envoltorio, el login y la plantilla"
git tag step-16-traduccion
```

---

#### Paso 17 — Traducir pegado, cláusulas y mercado

**Do**

Aplicar `plT()` a `pegado.php`, `clausulas.php` y `mercado.php`, las tres pantallas con más texto
del subproyecto: los errores por línea del pegado, los tres estados del presupuesto de cláusulas, y
los estados y el diálogo de confirmación del mercado.

En `mercado.php`, usar `nombre_en` del equipo cuando el idioma activo sea inglés — es el dato que
el importador del paso 11 ya arrastra de `datos_oficiales.json`, y sin usarlo el mercado en inglés
mostraría «Monte Olimpo» en vez de «Mount Olympus».

**El panel de admin (`admin*.php`) se queda en español y no usa `plT()`.** Es una decisión tomada:
lo usa una sola persona hispanohablante, igual que `supertecnicas/admin.php`.

**Done when**

- [ ] CUANDO se pida `?lang=en` EL SISTEMA DEBERÁ servir las tres pantallas en inglés, incluidos los mensajes de error por línea del pegado.
- [ ] CUANDO el idioma activo sea inglés y el equipo tenga `nombre_en` EL SISTEMA DEBERÁ mostrar ese nombre en el mercado.
- [ ] CUANDO el idioma activo sea inglés y el equipo NO tenga `nombre_en` EL SISTEMA DEBERÁ mostrar su `nombre` en español, sin dejar la celda vacía.
- [ ] CUANDO se recorra el HTML de las tres pantallas EL SISTEMA NO DEBERÁ contener ninguna cadena `[clave]` sin traducir en ninguno de los diez idiomas.
- [ ] CUANDO se lea cualquier `admin*.php` EL SISTEMA NO DEBERÁ encontrar ninguna llamada a `plT()`.
- [ ] CUANDO se ejecute `test_traduccion_mercado.php` EL SISTEMA DEBERÁ salir 0.

**Verify**

```bash
for f in dashboard/pegado.php dashboard/clausulas.php dashboard/mercado.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
# expect: exit 0

/c/xampp/php/php.exe dashboard/tests/test_traduccion_mercado.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# El panel de admin no usa plT(): se queda en espanol, por decision de §1.
# El `test -f` va delante para que la guarda no pase vaciamente si los ficheros
# de admin no existen.
test -f dashboard/admin.php && ! grep -l "plT(" dashboard/admin.php dashboard/admin_equipos.php dashboard/admin_presidentes.php dashboard/admin_tiers.php dashboard/admin_plantillas.php
# expect: exit 0  (los ficheros existen y ninguno usa plT)
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 17: plT() en pegado, clausulas y mercado"
git tag step-17-traduccion-mercado
```

---

#### Paso 18 — CSS, responsive y despliegue

**Do**

- `dashboard/css/dashboard.css` con **solo** lo que `_fuente/styles.css` no da: los componentes de
  formulario replicados de `supertecnicas/css/supertecnicas.css` y lo nuevo de §7 (`.chip-ata`,
  `.fase-*`, `.barra*`). Ni un token nuevo, ni un color fuera de la paleta.
- Responsive a 375 px: las tablas anchas dentro de un contenedor `overflow-x:auto`, las tarjetas del
  dashboard apiladas, los objetivos táctiles de acción primaria a 44 px.
- `dashboard/DESPLIEGUE.md` — el checklist de IONOS de §12: subir `dashboard/` por FTP, rellenar
  las dos claves en `config/secrets.php` del servidor, **elegir PHP 8.3 o 8.4 en el panel** (8.2
  entra en fin de soporte el 31-12-2026, §2), dar permiso de escritura a `dashboard/data/`, y
  **comprobar por URL que `dashboard/data/equipos.json` devuelve 403** gracias al `.htaccess`.

**Done when**

- [ ] CUANDO se cargue cualquier pantalla a 375 px de ancho EL SISTEMA NO DEBERÁ producir scroll horizontal de página; las tablas anchas DEBERÁN desplazarse dentro de su propio contenedor.
- [ ] CUANDO se recorra la interfaz con el tabulador EL SISTEMA DEBERÁ mostrar el foco visible en todo elemento interactivo, sin quedar tapado.
- [ ] CUANDO `dashboard.css` se lea EL SISTEMA NO DEBERÁ contener ningún valor de color literal fuera de los tokens y de los tres estados de `.barra` declarados en §7.
- [ ] CUANDO se ejecute el barrido de sintaxis sobre todo el subproyecto EL SISTEMA DEBERÁ salir 0 en todos los ficheros.
- [ ] CUANDO se ejecute la batería completa de tests EL SISTEMA DEBERÁ salir 0 en los dieciocho.
- [ ] CUANDO se lea `DESPLIEGUE.md` EL SISTEMA DEBERÁ nombrar los cinco puntos del checklist, incluida la comprobación de que `data/` responde 403 por URL.

**Verify**

```bash
test -f dashboard/css/dashboard.css && test -f dashboard/DESPLIEGUE.md
# expect: exit 0

/c/xampp/php/php.exe dashboard/tests/test_css.php
# expect: Todas las comprobaciones pasan.   (exit 0)

# Barrido de sintaxis de todo el subproyecto.
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
# expect: exit 0

# Bateria completa: los 18 tests.
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
# expect: exit 0

# El checklist nombra la comprobacion de que data/ no es accesible.
grep -q "403" dashboard/DESPLIEGUE.md
# expect: exit 0
```

**Checkpoint**

```bash
git add dashboard/ && git commit -m "paso 18: css propio, responsive a 375px y checklist de despliegue"
git tag step-18-css-entrega
```

---
## 10. Environment Setup

No hay nada que instalar. No hay Composer, no hay npm, no hay `node_modules/`, no hay paso de
compilación. Preparar el entorno consiste en comprobar que PHP existe, crear dos directorios y poner
en su sitio los ficheros de `workspace/`.

### Variables de entorno

**Ninguna.** Este subproyecto no lee ni una variable de entorno. Los secretos viven en
`config/secrets.php`, que es un fichero PHP que devuelve un array y que ya existe en el repo (§14).
No hay `.env`, ni `.env.example`, ni cargador de entorno, y por eso no hay ningún comando de este
blueprint que necesite que algo se lo cargue antes.

### Ruta de PHP

| Contexto | Invocación |
|---|---|
| Máquina de desarrollo (Windows + XAMPP) | `/c/xampp/php/php.exe` — **la que usan todos los `Verify` de §9** |
| Si PHP se añade al `PATH` | `php` funciona igual |
| Servidor IONOS | Ninguna: allí no se ejecuta ningún comando de este blueprint (§12) |

### Bootstrap

Este bloque se ejecuta **entero, en orden, desde la raíz del proyecto**, antes del paso 01. Es
idempotente: ejecutarlo por segunda vez no cambia nada y **sale 0**. Esto no es un detalle de
estilo — reejecutar el bootstrap es lo primero que hace quien se queda atascado, y un guard que
falla justo cuando acierta al no pisar nada rompe esa vía de recuperación.

```bash
set -e

# 1. PHP existe y cumple el suelo de 8.1.0 (§2). Sin esto, todo lo demás falla
#    más tarde y con un mensaje peor.
PHP=/c/xampp/php/php.exe
command -v "$PHP" >/dev/null 2>&1 || PHP=php
command -v "$PHP" >/dev/null 2>&1 || { echo "FALLO: no encuentro PHP. Instala XAMPP o pon php en el PATH."; exit 1; }
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' \
  || { echo "FALLO: se requiere PHP 8.1.0 o superior. Actual: $("$PHP" -r 'echo PHP_VERSION;')"; exit 1; }
echo "PHP OK: $("$PHP" -r 'echo PHP_VERSION;')"

# 2. Los dos directorios del subproyecto. -p no falla si ya existen.
mkdir -p dashboard/data dashboard/tests dashboard/css

# 3. Copiado de workspace/ SIN pisar nada. Se hace fichero a fichero con un
#    test previo en vez de con `cp -Rn`: cp -Rn sale 1 en BSD/macOS cuando
#    salta un fichero existente y 0 en GNU, así que el mismo comando abortaría
#    este bloque en un Mac y no en Linux. Este bucle sale 0 en ambos.
WS=blueprints/dashboard-liga/workspace
if [ -d "$WS" ]; then
  find "$WS" -type f | while IFS= read -r origen; do
    destino="${origen#$WS/}"
    if [ ! -e "$destino" ]; then
      mkdir -p "$(dirname "$destino")"
      cp "$origen" "$destino"
      echo "copiado: $destino"
    else
      echo "ya existe, se conserva: $destino"
    fi
  done
else
  echo "AVISO: $WS no está aquí. El bundle debe vivir dentro del proyecto (§19.6)."
fi

# 4. Las líneas de .gitignore se AÑADEN al fichero existente, nunca lo
#    sustituyen: htdocs/.gitignore ya protege secrets.php y los datos de
#    supertecnicas/. Se comprueba línea a línea, así que repetir el bootstrap
#    no duplica ninguna.
if [ -f .gitignore.dashboard.txt ]; then
  while IFS= read -r linea; do
    [ -z "$linea" ] && continue
    grep -qxF "$linea" .gitignore 2>/dev/null || echo "$linea" >> .gitignore
  done < .gitignore.dashboard.txt
  echo ".gitignore al día"
fi

# 5. Repositorio git. Se pregunta con `git rev-parse --git-dir` y NO con
#    `[ -d .git ]`: el repositorio de este proyecto tiene su raíz en el
#    directorio PADRE de htdocs/, así que aquí no hay ningún `.git` y la
#    comprobación por directorio daría un falso negativo. Un `git init` en ese
#    caso crea un repositorio anidado y el padre deja de seguir htdocs/ en
#    silencio, que es exactamente el fallo que este guard existe para evitar.
if git rev-parse --git-dir >/dev/null 2>&1; then
  echo "ya dentro de un repositorio git ($(git rev-parse --show-toplevel)), no se toca"
else
  git init
  git add -A
  git commit -m "estado inicial antes del paso 01"
  echo "repositorio inicializado"
fi

echo "BOOTSTRAP COMPLETO"
```

### Comprobación del bootstrap

```bash
test -d dashboard/data && test -f dashboard/data/.htaccess && test -f dashboard/tests/arnes.php
# expect: exit 0
grep -qxF '/dashboard/data/registro.json' .gitignore
# expect: exit 0
grep -c 'dashboard/data' .gitignore
# expect: 6  (no 12 — la segunda ejecución no duplicó nada)
```

---

## 11. Dependencies

**Cero dependencias de terceros.** No hay manifiesto de paquetes: ni `composer.json`, ni
`package.json` nuevo, ni ningún fichero de bloqueo. Nada que instalar, nada que auditar, nada que
actualizar, y ninguna cadena de suministro que vigilar.

| Se usa | De dónde sale | Nota |
|---|---|---|
| `flock`, `tempnam`, `rename`, `file_put_contents` | stdlib de PHP | Escritura atómica |
| `json_encode` / `json_decode` | stdlib | Con `JSON_UNESCAPED_UNICODE\|JSON_PRETTY_PRINT` |
| `password_hash` / `password_verify` / `hash_equals` / `random_bytes` | stdlib | Auth y CSRF |
| `htmlspecialchars` | stdlib | Escapado, con `ENT_QUOTES` explícito |
| `session_*`, `mb_*`, `preg_*`, `str_contains`, `array_is_list` | stdlib | `array_is_list` fija el suelo en 8.1.0 |
| `_fuente/styles.css` | El propio repo | Se **enlaza**, nunca se edita |
| `config/admin_auth.php` | El propio repo | Se **usa**, no se reimplementa |
| `flagcdn.com` | CDN externo | Solo las 10 banderas del selector, `loading="lazy"`. Si el CDN cae, el selector sigue funcionando: son enlaces con texto alternativo, no botones que dependan de la imagen |

La única dependencia externa en tiempo de ejecución es ese CDN de banderas, y ya lo usa
`supertecnicas/i18n.php` en producción. No se añade ninguna más.

---

## 12. Deployment Strategy

Hosting compartido en IONOS, Apache + PHP. **El despliegue es una subida de ficheros por FTP/SFTP**,
igual que el del resto de `htdocs/`. No hay CI, no hay contenedor, no hay proceso de build, no hay
comando que ejecutar en el servidor.

### URL final

**`https://superligafrontier.es/dashboard/`**, servida por `dashboard/index.php` a través del
`DirectoryIndex` por defecto de Apache. No hace falta ninguna regla de reescritura nueva.

Dos cosas del `.htaccess` de la raíz que conviene tener presentes y que **no** interfieren:

- La reescritura de idioma (`RewriteRule ^$ %2.html`) solo casa con la **raíz** del sitio, así que
  `?lang=xx` dentro de `/dashboard/` lo resuelve `i18n.php` y nunca esa regla, pese a usar el mismo
  nombre de parámetro.
- El `RewriteCond %{HTTPS} off` fuerza HTTPS en todo el dominio, incluido `/dashboard/`. Es lo que
  se quiere: por ahí viajan contraseñas de presidente.

### Qué se sube

| Se sube | No se sube |
|---|---|
| Todo `dashboard/` **menos** `data/*.json` | `dashboard/data/*.json` — el estado vive en el servidor y se quedaría pisado |
| `dashboard/data/.htaccess` | `dashboard/tests/` — no hacen falta en producción, aunque no estorban |
| `config/secrets.example.php` | `config/secrets.php` — se edita **en el servidor** |
| | `blueprints/` — es documentación de construcción |

### Checklist (el contenido de `dashboard/DESPLIEGUE.md`, paso 18)

1. **Elegir PHP 8.3 o 8.4 en el panel de IONOS.** No 8.2: su soporte de seguridad termina el
   31-12-2026 (§2). El suelo del código es 8.1.0, así que las cuatro valen para funcionar, pero
   quedarse en la que expira antes es elegir trabajo para dentro de tres meses.
2. **Rellenar `config/secrets.php` en el servidor** con `admin_dashboard_user` y
   `admin_dashboard_pass_hash`. El hash se genera con
   `php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"` y **nunca** se escribe la clave en
   claro en ningún fichero.
3. **Permiso de escritura en `dashboard/data/`** para el usuario de PHP. Sin esto la app carga pero
   ningún guardado funciona, y el síntoma —`plGuardarJsonAtomico()` devolviendo `false`— parece un
   error de la aplicación.
4. **Comprobar que `dashboard/data/` no es accesible por URL**: pedir
   `https://superligafrontier.es/dashboard/data/equipos.json` en el navegador **debe devolver 403**.
   Si devuelve el JSON, el `.htaccess` no se ha subido o `AllowOverride` no está activo, y en ese
   caso los hashes de contraseña de los presidentes estarían publicados.
5. **Prueba de humo**: entrar con una cuenta de presidente, comprobar que se ve el equipo correcto,
   y que la fase mostrada coincide con la del panel de admin.

### Copias de seguridad

El estado entero son seis ficheros JSON en `dashboard/data/`. **Copiar ese directorio es la copia
de seguridad completa**, y se puede hacer por FTP en segundos. Antes de cerrar una fase o de empezar
una temporada nueva, descargarlo: es la única operación de este sistema que no tiene deshacer.

### Reversión

Cada paso de §9 deja una etiqueta `step-NN-slug`. Volver atrás en el código es
`git reset --hard step-NN-slug` y volver a subir. **Los datos no se revierten con git** —no están
versionados, y no deben estarlo— así que se restauran desde la copia del punto anterior.

---

## 13. Testing Strategy

Scripts PHP planos, sin framework, sin runner y sin fichero de configuración. Ejecutados
directamente: `/c/xampp/php/php.exe dashboard/tests/test_dominio.php`. Es la convención que ya usa
`supertecnicas/tests/`, y para un subproyecto de este tamaño instalar PHPUnit sería añadir una
dependencia, un manifiesto y un paso de instalación para obtener exactamente lo que estas 30 líneas
ya dan.

### Las tres capas, y qué se prueba en cada una

| Capa | Qué | Cómo | Ficheros |
|---|---|---|---|
| **Dominio** | Toda la aritmética: cap, límite de 20, 650M, quién puede clausular a quién | Llamadas puras, sin ficheros ni sesión. Es donde vive el valor real de la batería | `test_dominio.php` |
| **Almacén** | Escritura atómica, semillas, congelado de tiers, `rev`, registro append-only | Sobre `plArnesDirDatos()`, un directorio temporal por test | `test_lib.php`, `test_almacen.php`, `test_temporada.php`, `test_equipos.php`, `test_presidentes.php`, `test_tiers.php` |
| **Pantalla** | Que la pantalla se renderiza, que respeta la fase y que no filtra datos de otros equipos | `plArnesRender()`, comparando sobre el HTML devuelto | `test_login.php`, `test_dashboard.php`, `test_plantilla.php`, `test_pegado.php`, `test_clausulas.php`, `test_mercado.php`, `test_admin_plantillas.php`, `test_i18n.php`, `test_css.php` |

### Reglas

- **Ningún test escribe en `dashboard/data/`.** Todos usan `plArnesDirDatos()` y limpian con
  `plArnesLimpiar()`. Un test que ensucie los datos reales convierte la batería en algo que da miedo
  ejecutar, y una batería que da miedo no se ejecuta.
- **Nunca se corta en el primer fallo.** `plVerificar()` acumula y `plSalirConResultado()` decide el
  código de salida al final, para ver la lista completa de una tirada.
- Los caminos que terminan en `header()+exit` no se prueban con `plArnesRender()` —cortan el
  proceso—; se prueban sobre la función de guardia (`plPuedeEditarPlantilla`, `plPuedeClausular`),
  que es pura y para eso existe.
- Cada paso de §9 escribe su propio test **en el mismo paso**. No hay un paso final de "añadir
  tests": un test escrito después no es una puerta, es documentación.

### La batería completa

```bash
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
# expect: exit 0 — los 18 tests
```

**Qué no se prueba automáticamente**, dicho para que no parezca cubierto: el renderizado real en
navegador, el responsive a 375 px, el foco visible con tabulador y el 403 de `data/` por URL. Son
comprobaciones manuales, están en el checklist de §12 y en §15, y no hay Playwright ni navegador
headless en este proyecto porque su coste no lo justifica a esta escala.

---

## 14. Security & Secrets

### Secretos

Dos, ambos en `config/secrets.php`, que **no está versionado** (`.gitignore:1`) y que ya alberga los
secretos del resto del sitio:

| Clave | Qué es |
|---|---|
| `admin_dashboard_user` | Usuario del Basic Auth del panel |
| `admin_dashboard_pass_hash` | `password_hash()` de su clave. **Nunca la clave en claro** |

`config/secrets.example.php` recibe las dos claves con valor vacío (paso 04), como plantilla.

Las contraseñas de los presidentes viven hasheadas en `dashboard/data/usuarios.json`, que está en
`.gitignore` y **protegido por `dashboard/data/.htaccess`** (`Require all denied`). Las dos
protecciones son necesarias: git evita publicarlo en el repositorio, el `.htaccess` evita servirlo
por URL. Ninguna sustituye a la otra.

### Superficie de ataque y cómo se cierra

| Riesgo | Defensa | Dónde |
|---|---|---|
| XSS | `plEsc()` en **todo** valor interpolado en HTML, sin excepción. Los nombres de jugador los escriben los presidentes: son entrada de usuario | Todas las pantallas |
| CSRF | Token de sincronizador por sesión, `hash_equals()`, comprobado en todo POST antes que nada, también en el admin | §8.5 |
| Fijación de sesión | `session_regenerate_id(true)` antes de escribir el id de usuario | Paso 05 |
| Enumeración de cuentas | Mensaje idéntico para email inexistente, clave incorrecta y cuenta desactivada | Paso 05 |
| Escalada de privilegios | El admin no vive en `usuarios.json`: no hay campo que manipular para convertirse en admin | §8.1 |
| Manipulación del formulario | Toda regla se revalida en el servidor: fase, cap, 20, 650M, y quién puede clausular a quién. El salario enviado en el POST se **ignora** y se deriva del tier | §9 pasos 07, 09, 10 |
| Lectura directa de los datos | `dashboard/data/.htaccess` con `Require all denied` | §19.6 |
| Corrupción por escritura concurrente | `flock` exclusivo + `rename` atómico | Paso 01 |
| Pérdida silenciosa entre copresidentes | `rev` optimista, que rechaza el segundo guardado en vez de aceptarlo | Paso 03 |
| Escritura accidental en producción | `datos_oficiales.json` es de solo lectura; hay una regla en `.claude/rules/` y una comprobación en el `Verify` del paso 11 | §19.5 |

### Lo que este sistema no protege, dicho claramente

No hay cifrado en reposo, no hay 2FA, no hay límite de intentos de login y no hay registro de
accesos. Son decisiones proporcionadas a lo que guarda: nombres de jugadores ficticios y números de
una liga de aficionados. **El único dato de verdad sensible son los hashes de contraseña**, y por eso
tienen las dos capas de protección de arriba. Si algún día esta app guardara datos personales
reales, esta sección tendría que reescribirse antes.

---

## 15. Accessibility

Nivel objetivo: **WCAG 2.2 AA**. No es un adorno: la app la usan treinta personas durante las horas
finales de una fase, y la mitad lo harán desde el móvil.

| Requisito | Cómo se cumple |
|---|---|
| Contraste ≥ 4.5:1 en texto normal, ≥ 3:1 en texto grande | Los tokens de Design System v3 ya lo cumplen. `--ink-4` y `--ink-5` no se usan para texto legible, solo para bordes y separadores |
| **El color nunca es la única señal** | Los badges de fase llevan su texto. Los estados del mercado llevan «Disponible» / «Clausulado por {equipo}». La barra de cláusulas va acompañada de `COMPLETO` / `INCOMPLETO` en texto |
| Foco visible siempre | `.inp:focus` con `box-shadow:0 0 0 3px var(--accent-glow)`; los `.btn` conservan su anillo. Nunca `outline:none` sin sustituto |
| Objetivo táctil | 44 px en acciones primarias (guardar, confirmar, marcar clausulado); mínimo 24 px en el resto |
| Operable por teclado | Todo son formularios y enlaces nativos. Los modales usan `<dialog>`, que ya atrapa el foco y cierra con `Esc` |
| Sin scroll horizontal de página | Las tablas anchas —mercado, plantillas del admin— dentro de un contenedor `overflow-x:auto` |
| Estructura | Un solo `<h1>` por pantalla, `<main id="contenido">`, `.skip-link` como primer elemento enfocable, `aria-current="page"` en la navegación |
| Cambios dinámicos anunciados | El contador de cláusulas actualiza una región `aria-live="polite"` con el total y el estado, para que un lector de pantalla no se quede con la cifra vieja |
| Idioma declarado | `<html lang="…">` con el idioma activo. Es lo que hace que un lector de pantalla pronuncie el búlgaro como búlgaro |
| Imágenes | Las banderas del selector llevan `alt` con el código de idioma; los escudos, `alt` con el nombre del equipo |
| Formularios | Todo `<input>` con su `<label>` asociado. Los errores se muestran junto al campo y **sin perder lo ya escrito** |

**Errores que no se van a cometer**, porque son los que suelen colarse en tablas densas: no se usa
`title` como sustituto de `label`; no se comunica «excedido» solo pintando el número en rojo; y no se
deshabilita un campo sin explicar por qué al lado, que es justo lo que hacen las pantallas fuera de
su fase (§6).

Las cuatro comprobaciones manuales —375 px, tabulador, foco, lectores— están en el checklist de
§12 porque no hay forma automatizada de hacerlas en este stack, y decir lo contrario sería fingir
una cobertura que no existe.

---

## 16. Observability & Cost

### Coste

**Cero.** El subproyecto vive en el hosting que ya está pagado, no añade servicios, no llama a
ninguna API de pago y no consume nada que se facture por uso. El único tráfico externo son diez
imágenes de bandera de 16×12 px con `loading="lazy"`.

### Observabilidad

No hay APM, no hay tracing, no hay agregador de logs, y no hacen falta: son treinta usuarios y una
aplicación sin trabajo en segundo plano. Lo que sí hay:

| Señal | Dónde | Para qué |
|---|---|---|
| `registro.json` | `dashboard/data/` | **La traza que de verdad importa**: quién clausuló a quién y cuándo, quién cambió de fase, quién creó una temporada. Es append-only y es lo que arbitra una disputa (R4) |
| Log de errores de PHP | El del panel de IONOS | Errores de ejecución. No se escribe un log propio |
| Fallos de escritura | `plGuardarJsonAtomico()` devuelve `false` | **Toda pantalla debe comprobar ese `false` y avisar al usuario.** Un guardado que falla en silencio es el peor fallo posible en esta app: el presidente cree que ha guardado su reparto de 650M y no lo ha hecho |

Esa última fila es una regla de construcción, no una observación: ninguna pantalla llama a un
guardador ignorando su valor de retorno.

### Qué vigilar en la práctica

- Que `dashboard/data/` sigue teniendo permiso de escritura después de cada subida por FTP.
- Que no queda ningún fichero `*.lock` ni `pl_*` suelto en `data/` — señal de un guardado
  interrumpido a medias.
- El tamaño del fichero de temporada: 30 equipos × 20 jugadores rondan los 150 KB. Si algún día se
  acercara a varios megas, es la señal que §20 nombra para replantear el almacén.

---

## 17. Model Routing

**NO APLICA — este producto no llama a ningún modelo de lenguaje.**

No hay IA en el subproyecto: ni generación de texto, ni clasificación, ni embeddings, ni asistencia
de ningún tipo. Es aritmética determinista sobre ficheros JSON. Esta sección existe con su
encabezado porque las herramientas que leen el blueprint indexan por número de sección, no porque
haya algo que enrutar.

---

## 18. Skills to Use During Build

| Skill | Cuándo | Para qué | Cómo se instala |
|---|---|---|---|
| `ui-ux-pro-max` | Pasos 06, 07, 09, 10, 18 | Densidad de tabla, formularios y diálogos, y comprobar contraste de los estados nuevos de §7. **Se activa sola: no se escribe con barra** | `/plugin marketplace add nextlevelbuilder/ui-ux-pro-max-skill` y después `/plugin install ui-ux-pro-max@ui-ux-pro-max-skill` |
| `superpowers:test-driven-development` | Pasos 01-03 | Las funciones puras de `dominio.php` son el caso ideal: escribir `test_dominio.php` antes que el módulo hace que los mensajes de error salgan bien a la primera | Del plugin `superpowers`, ya instalado en la máquina de Alejandro. **No está en el registro de skills de este plugin**: si no aparece, se prescinde de ella |
| `superpowers:systematic-debugging` | Cuando un test falle sin causa evidente | Sobre todo en los de concurrencia (`rev`) y en los de render con arnés | Igual que la anterior |
| `/code-review` | Al cerrar cada epic | Revisión del diff acumulado antes de pasar al siguiente | Comando integrado de Claude Code. No se instala |

**No se usan aquí:** `frontend-design` (no hay framework de front), `playwright-cli` (no hay
navegador en la batería, §13), `dataviz` (no hay gráficas), `hyperframes` y `media-use` (no hay
vídeo ni medios).

Si alguna de estas skills no está instalada, se sigue adelante sin ella: ninguna es un requisito
para completar un paso, y ningún `Verify` de §9 depende de una.

---

## 19. Agent Workspace

Todos estos ficheros ya están escritos como **ficheros reales** en
`blueprints/dashboard-liga/workspace/`, y el bootstrap de §10 los copia a la raíz del proyecto sin
pisar nada. En modo bundle esa es su forma canónica: lo que hay en disco es la fuente, y esta
sección dice qué es cada uno y por qué existe.

### 19.1 `CLAUDE.md` → `htdocs/CLAUDE.md`

Fichero: `workspace/CLAUDE.md`. Bajo 200 líneas, comandos primero.

**Su primera sección es el radio de impacto, y es la parte que no se puede recortar.** Este fichero
aterriza en la raíz de un sitio en producción que no tenía `CLAUDE.md`, así que gobierna el repo
entero: tiene que dejar dicho, sin margen de duda, que el trabajo nuevo vive **solo** en
`dashboard/`, que `index.html`, `_fuente/`, `api/`, `cron/`, `admin/` y `supertecnicas/` son la web
pública y no se tocan, y que `datos_oficiales.json` se lee y jamás se escribe.

### 19.2 `AGENTS.md` → `htdocs/AGENTS.md`

Fichero: `workspace/AGENTS.md`. El equivalente neutro de §19.1, para agentes que no son Claude Code
y que no leen `CLAUDE.md`. No es opcional y no es un enlace simbólico: es el mismo contenido en el
fichero que esas herramientas sí abren.

### 19.3 `.claude/settings.json` → `htdocs/.claude/settings.json`

Fichero: `workspace/.claude/settings.json`.

`permissions.allow` cubre **todos** los comandos `Verify` de §9 y los de la puerta global de §20.1,
incluidas las formas `/c/xampp/php/php.exe -l …`, `-r …`, la ejecución de los tests, y los dos
bucles `for`. Un comando de verificación que no esté en la lista detiene una construcción
desatendida en un permiso que nadie está despierto para conceder.

`permissions.deny` es la otra mitad, y es la que hace cumplir §14 mecánicamente:
`Write(./datos_oficiales.json)` y `Edit(./datos_oficiales.json)` bloquean la escritura en el fichero
de producción; `Edit(./_fuente/app.js)` y `Edit(./_fuente/styles.css)` protegen la web pública;
`Read(./config/secrets.php)` y `Read(./dashboard/data/usuarios.json)` evitan que los secretos y los
hashes acaben en un transcript.

### 19.4 `.claude/skills/anadir-pantalla/SKILL.md`

Fichero: `workspace/.claude/skills/anadir-pantalla/SKILL.md`.

Es el flujo repetible del proyecto: añadir una pantalla nueva a `dashboard/` sin olvidar ninguno de
los seis pasos que hacen que una pantalla sea correcta aquí —`session_start()`, resolver idioma,
resolver usuario, validar CSRF, validar fase en el servidor, escapar con `plEsc()`— y su test. Va en
`skills/` y no en `commands/`: un comando de barra solo se dispara cuando lo teclea una persona, y
un constructor autónomo no teclea nada.

### 19.5 `.claude/rules/`

Dos ficheros, ambos acotados por `paths:`:

| Fichero | Alcance | Qué fija |
|---|---|---|
| `workspace/.claude/rules/dashboard.md` | `dashboard/**` | Prefijo `pl` en toda función; `plEsc()` obligatorio en todo lo interpolado; CSRF en todo POST; validación de fase en servidor; `?? ''` antes de toda función interna de PHP (§2); solo `almacen.php` escribe ficheros; `dominio.php` se mantiene puro |
| `workspace/.claude/rules/fuera-de-alcance.md` | la raíz del repo | Lo que no se toca: la web pública, `_fuente/`, `datos_oficiales.json`, `api/`, `cron/`, `supertecnicas/` |

### 19.6 Ficheros de configuración que las puertas necesitan

**No es `NO APLICA`.** Tres ficheros se emiten como ficheros reales porque sin ellos hay `Verify` de
§9 que no puede ejecutarse:

| Fichero emitido | Destino | Qué puerta lo necesita | Resolución / entorno | Exclusión del bundle |
|---|---|---|---|---|
| `workspace/dashboard/data/.htaccess` | `dashboard/data/.htaccess` | El `Verify` del paso 01 comprueba que existe y contiene `Require all denied`. Contenido literal, una línea: `Require all denied` | Ninguna: fichero estático, sin runner y sin variables de entorno. Lo lee Apache, no PHP | n/a — ninguna herramienta de este proyecto recorre el árbol |
| `workspace/dashboard/tests/arnes.php` | `dashboard/tests/arnes.php` | **Todos** los tests de §9 lo cargan. Aporta `plVerificar()`, `plSalirConResultado()`, `plArnesPreparar()`, `plArnesRender()`, `plArnesDirDatos()` y `plArnesLimpiar()`. Sin él, ningún `Verify` del paso 01 en adelante corre | `require_once __DIR__ . '/arnes.php'` desde cada test. Sin alias, sin autoloader, sin `include_path`, sin variables de entorno | n/a — el glob `dashboard/tests/*.php` está anclado en la raíz del proyecto y no alcanza `blueprints/` |
| `workspace/.gitignore.dashboard.txt` | `.gitignore.dashboard.txt` (raíz) | El paso 4 del bootstrap lo lee para **añadir** sus seis líneas al `.gitignore` existente. Es un fichero aparte y no un `.gitignore` completo precisamente porque `htdocs/.gitignore` ya existe y contiene reglas que no se pueden perder | Ninguna: lo lee un `while read` del bootstrap, no una herramienta con configuración propia | n/a — no es configuración de ninguna herramienta que camine el árbol |

**No hay fichero de configuración de runner de tests, y no es un olvido:** los tests son scripts PHP
que se invocan directamente (§13). No hay PHPUnit, no hay `phpunit.xml`, no hay `composer.json`, no
hay alias de rutas y no hay cargador de entorno, porque no hay entorno que cargar (§10).

**Convención de resolución, única en todo el proyecto:** `require_once __DIR__ . '/fichero.php'`.
Ruta relativa literal, siempre anclada en `__DIR__`. Se sostiene en los cuatro contextos que cargan
estos módulos:

| Contexto | Directorio de trabajo | Por qué funciona |
|---|---|---|
| Apache sirviendo `dashboard/index.php` | Depende de la configuración | `__DIR__` es absoluto: no depende del cwd |
| `php dashboard/tests/test_x.php` desde la raíz | `htdocs/` | Igual |
| `php -r "require 'dashboard/lib.php'; …"` desde la raíz | `htdocs/` | La ruta del `require` externo es relativa al cwd, y por eso **todos los `Verify` de §9 se ejecutan desde la raíz del proyecto** |
| `plArnesRender('dashboard/index.php')` | `htdocs/` | Igual que la anterior |

**El bundle se excluye de sí mismo, por anclaje de ruta.** El barrido del paso 18 es
`for f in dashboard/*.php dashboard/tests/*.php`, un glob **anclado en la raíz del proyecto**: no
es recursivo y estructuralmente no puede alcanzar `blueprints/`, sea cual sea su contenido. Esto
importa decirlo con precisión porque `blueprints/dashboard-liga/workspace/` **sí contiene un `.php`**
—`arnes.php`, la copia de origen del que se despliega—, así que el argumento no es «no hay ficheros
PHP ahí dentro», sino «ninguna herramienta de este proyecto camina el árbol». No hay linter, no hay
formateador y no hay ningún comando recursivo en §9 ni en §20.1.

La única copia de `dashboard/data/.htaccess` que Apache ve es la de `dashboard/`; la de
`workspace/` no está bajo ninguna ruta servida.

---

## 20. Acceptance Gate, Risks & Decision Log

### 20.1 Puerta de aceptación global

El subproyecto está terminado cuando **estos cuatro comandos salen 0**, ejecutados desde la raíz del
proyecto:

```bash
# 1. Sintaxis de todo el subproyecto.
for f in dashboard/*.php dashboard/tests/*.php; do /c/xampp/php/php.exe -l "$f" || exit 1; done
# expect: exit 0

# 2. Los dieciocho tests.
for t in dashboard/tests/test_*.php; do /c/xampp/php/php.exe "$t" || exit 1; done
# expect: exit 0

# 3. Nada del subproyecto escribe en el fichero de producción de la web pública.
# Se comprueba por código y no con `git status`: ese fichero cambia por el
# flujo de trabajo de la web pública, así que su estado en git no dice nada
# sobre este subproyecto.
! grep -rnE "plGuardarJsonAtomico\([^)]*datos_oficiales|file_put_contents\([^)]*datos_oficiales" dashboard/
# expect: exit 0

# 4. Ningún fichero de estado se ha colado en el repositorio.
! git ls-files --error-unmatch dashboard/data/usuarios.json 2>/dev/null
# expect: exit 0  (el fichero no está versionado)
```

Y cuando los **trece criterios de aceptación de la especificación** (§38 del documento original) se
cumplen a mano, en navegador:

- [ ] Un presidente inicia sesión y ve únicamente su equipo.
- [ ] Inscribe hasta 20 jugadores con posición y tier, con salario automático — **solo en `ROSTER`**.
- [ ] El Salary Cap de 250M no se puede superar por ninguna vía.
- [ ] Reparte 650M en cláusulas sin superarlos — **solo en `CLAUSULAS`** — y puede guardar por debajo como borrador.
- [ ] Consulta el mercado en cualquier fase.
- [ ] Marca a un jugador de otro equipo como clausulado por su equipo — **solo en `MERCADO`**.
- [ ] No puede marcar a un jugador propio ni asignar la clausulación a otro equipo.
- [ ] El admin marca o corrige cualquier clausulación en cualquier momento.
- [ ] El admin fuerza el avance de fase y abre/cierra el mercado.
- [ ] El admin crea una temporada nueva que empieza vacía, en `ROSTER`.
- [ ] Los presidentes pueden cambiar de equipo entre temporadas.
- [ ] Los datos se guardan sin corrupción con varios presidentes editando a la vez.
- [ ] A 375 px ninguna pantalla produce scroll horizontal de página, y el foco nunca queda tapado al recorrer con el tabulador. *(Forma observable del «funciona en móvil» de §38 de la especificación; los criterios del paso 18 son la versión ejecutable de esto.)*

### 20.2 Registro de riesgos

| # | Riesgo | Disparador | Mitigación | Paso | Estado |
|---|---|---|---|---|---|
| R1 | **Adopción.** Teclear 600 jugadores a mano es más lento que la hoja de cálculo que esta app sustituye, y la liga se queda encallada en la fase `ROSTER` de la primera temporada | La primera inscripción real tarda más de 15 minutos por equipo | Pegado masivo `Nombre;POS;TIER` con previsualización, **en la v1** y no en una v2. Es el riesgo que más probablemente mata el proyecto, y por eso su mitigación es un paso completo | 08 | Mitigado |
| R2 | **Typos entre equipos.** Los nombres se escriben a mano, así que «Endou» y «Endo» acabarán conviviendo en el mercado como si fueran dos jugadores | Dos nombres que normalizan igual en equipos distintos | Aviso de duplicados en el panel del admin usando `plNormalizarTexto()`. **Avisa, no bloquea**: pueden ser dos personas distintas de verdad | 14 | Mitigado |
| R3 | **Copresidentes pisándose.** `flock` impide un JSON corrupto, pero no impide que el guardado de un copresidente borre el del otro sin que ninguno se entere | Dos copresidentes editan la misma plantilla a la vez | `rev` optimista por equipo: el segundo guardado se rechaza con un mensaje explícito en vez de aceptarse en silencio | 03, 07, 09 | Mitigado |
| R4 | **Disputa de clausulación** sin forma de arbitrarla: un presidente marca mal y no puede revertirlo | Una reclamación después de una clausulación | `registro.json` append-only con actor, sello de tiempo y detalle, más su vista en el panel. El admin arbitra con datos, no de memoria | 04, 10, 14 | Mitigado |
| R5 | **Versión de PHP de IONOS desconocida.** No es consultable desde ningún registro: solo desde el panel de hosting | El despliegue falla, o empieza a emitir deprecaciones | Guard `version_compare` con suelo 8.1.0 en `lib.php`, más el punto 1 del checklist de despliegue. **Aceptado**: no se puede verificar desde aquí, solo defenderse | 01, 18 | Aceptado |

### 20.3 Registro de decisiones

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| **Ficheros JSON, sin base de datos** | Postgres, Supabase, MySQL | 30 equipos × 20 jugadores y 3-4 escrituras concurrentes. Una BD añadiría un servidor que administrar, migraciones y un despliegue distinto, a cambio de nada a esta escala. **Cuándo cambiar**: decenas de competiciones simultáneas, consultas entre muchas temporadas a la vez, o muchos clientes concurrentes |
| **PHP plano, sin framework** | Next.js + React + TypeScript | El repo entero ya es PHP plano sobre IONOS, con un subproyecto hermano (`supertecnicas/`) que resuelve exactamente estos mismos problemas. Next.js exigiría un proceso Node persistente, un hosting distinto y un stack que nadie mantiene aquí |
| **Equipos propios, con enlace opcional a la web** | Leer los equipos solo de `datos_oficiales.json` | Hay equipos nuevos que aún no están en la web, y equipos de la web que aquí hay que archivar. Un registro propio con `equipoId` y un importador idempotente cubre los dos casos sin duplicar la gestión |
| **Jugadores propios, sin enlazar con la web** | Reutilizar `equipos[].jugadores[]` | Son capas distintas: allí está la plantilla deportiva real; aquí, 20 inscritos con tier, salario y cláusula. Además cada temporada empieza vacía, lo que habría vaciado la plantilla pública |
| **Nombres de jugador a texto libre** | Desplegable alimentado de la web pública | Decisión del propietario: permite inscribir a alguien que aún no está en la web. El coste es R2, y su aviso |
| **`ajustes.tiers` congelado por temporada** | Leer siempre `tiers.json` | Elimina una clase entera de fallos: subir S++ de 75 a 90 nunca puede reventar retroactivamente el cap de 30 equipos ya inscritos |
| **Admin por HTTP Basic, no como rol** | Un campo `rol` en `usuarios.json` | Reutiliza `config/admin_auth.php`, que ya existe y ya está en producción. Y como el admin no vive en el mismo almacén, **no hay ninguna ruta por la que una cuenta de presidente pueda escalar** |
| **Cláusulas: guardar por debajo de 650M** | Bloquear el guardado hasta cuadrar exactamente | Más fiel a la letra, mucho peor de usar: perder media hora de reparto por no haber cuadrado es la clase de fricción que hace que la gente vuelva a la hoja de cálculo. El equipo queda `INCOMPLETO` y el admin lo ve antes de cerrar la fase |
| **Panel de admin sin traducir** | Los diez idiomas también en el admin | Lo usa una sola persona hispanohablante. Es la misma decisión que ya tomó `supertecnicas/admin.php` |
| **`ATA` y no `DEL`** | `DEL`, como en `datos_oficiales.json` | Lo fija la especificación. Como los jugadores de este subproyecto no se enlazan con los de la web pública, no hay conflicto de datos. Si algún día se quisiera unificar, es un renombrado en `PL_POSICIONES` y en `.chip-ata` |
| **Sin paginar el mercado** | Paginación en servidor | 600 filas como máximo absoluto. La pitfall de paginar del arquetipo `internal-tool` está pensada para bases de datos internas grandes; aquí sería complejidad sin causa |

---

### 20.4 Qué construir después

Lo de abajo **no es alcance**. Es la lista corta de lo que se ha dejado fuera a propósito (§1) con
la señal concreta que haría razonable reabrirlo. Va al final del blueprint precisamente para que
nadie la confunda con trabajo pendiente: mientras no ocurra el disparador, la respuesta correcta es
no construirlo.

| Fuera de alcance hoy | Se reabre cuando | Qué costaría, a ojo |
|---|---|---|
| **Histórico entre temporadas** dentro de esta app | Alguien decida retirar la otra web de la liga, y el histórico se quede sin sitio | Grande: cambia el modelo de datos de «temporada actual» a «serie de temporadas», y con él la mitad de las pantallas |
| **Mercado que ejecuta la clausulación** (mover al jugador de equipo y ajustar cap y cláusulas) | Las clausulaciones se acuerden dentro de la app y no por Discord | Medio: hace falta arbitrar disputas automáticamente, que es justo lo que hoy delega el registro en el admin |
| **Panel de administración traducido** | Haya un segundo admin que no hable español | Pequeño: el motor de i18n del paso 15 ya está, es aplicar `plT()` a cinco pantallas más |
| **Desplegable de jugadores alimentado de la web pública**, en vez de texto libre | Los typos entre equipos (R2) den un problema real de arbitraje, y no solo un aviso | Pequeño-medio: obliga a decidir qué pasa con un jugador que aún no está en la web pública |
| **Base de datos relacional** en lugar de ficheros JSON | Haya decenas de competiciones simultáneas, o consultas que crucen muchas temporadas a la vez, o muchos clientes concurrentes | Grande, y hoy no compra nada: 30 equipos × 20 jugadores con 3-4 escrituras a la vez es holgado para `flock` (§20.3) |

**La señal a vigilar de verdad**, por encima de las cinco filas: si la primera inscripción real
tarda más de 15 minutos por equipo (R1), el problema no está en ninguna de estas ampliaciones sino
en la velocidad de entrada de datos, y ahí es donde hay que trabajar.
