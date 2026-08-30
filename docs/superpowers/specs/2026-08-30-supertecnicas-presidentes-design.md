# Asignación de supertécnicas por presidentes — diseño

## Contexto

`datos_oficiales.json` (raíz de `htdocs`) ya tiene, para cada jugador, un
array `supertecnicas` de objetos `{nombre, tipo, afinidad, especial,
descripcion}` (`tipo` ∈ `tiro|regate|bloqueo|parada`, `afinidad` ∈
`neutro|fuego|montaña|bosque|aire`). Hoy solo se edita desde el panel interno
`gestor/` (`gestor/js/vista-equipos.js:462-479`), de uso exclusivo del
administrador.

Se pide una web nueva y separada donde **cada presidente de equipo** pueda
asignar las supertécnicas de los jugadores de su propio equipo, después de
cada ventana de mercado, y que el cambio se refleje solo en la web pública.

`_fuente/app.js` ya hace `fetch('datos_oficiales.json', {cache:'no-cache'})`
en cada carga — escribir el JSON en el servidor es suficiente para que la web
pública se actualice, sin build ni prerender.

Este proyecto es **independiente de `transferroom/`** (que se está retirando
del repo) y de `admin/index.php` (código huérfano que referencia un
`includes/auth.php` inexistente — no está en uso). No se toca ninguno de los
dos.

## Alcance

- Carpeta nueva: `supertecnicas/`, PHP puro, sin dependencias nuevas.
- Un presidente entra con código de equipo + PIN, ve solo la plantilla activa
  de su equipo, y asigna hasta 4 supertécnicas por jugador con el mismo
  formulario de campos que ya usa `gestor/`.
- Un interruptor de "ventana abierta/cerrada", controlado por un admin propio
  de esta herramienta, bloquea el guardado cuando está cerrada.
- El admin también gestiona (ver/editar) el código y PIN de cada equipo,
  porque los equipos y sus presidentes cambian entre temporadas.

## Datos

### `datos_oficiales.json` (fichero compartido, ya existente)

Sin cambio de esquema: se reutiliza `equipo.jugadores[].supertecnicas[]` tal
cual. El único límite nuevo lo impone esta herramienta al guardar: máximo 4
elementos por jugador (el gestor interno no tiene ese límite y no se toca).

### `supertecnicas/data/codigos_equipos.json` (nuevo)

```json
{
  "teikoku": { "codigo": "monte olimpo", "pin": "andes" }
}
```

- Clave = `equipo.id` de `datos_oficiales.json`.
- Se siembra la primera vez que el admin abre el panel: para cada equipo
  activo (`archivado` no `true`) sin entrada todavía, se propone por defecto
  `codigo = nombre del equipo` y `pin = ciudad`, normalizados (minúsculas,
  sin acentos, espacios simples). El admin confirma o edita antes de guardar;
  hasta que no guarda, no es válido para login.
- El admin puede editar código/PIN de cualquier equipo en cualquier momento
  (cambio de presidente, etc.). Equipos archivados se ocultan de la tabla y
  no pueden loguearse (si ya tenían código, se conserva por si se
  desarchivan).

### `supertecnicas/data/config.json` (nuevo)

```json
{ "ventana_abierta": false }
```

Un único booleano. Lo edita solo el admin.

## Autenticación

### Presidente

- Formulario con dos campos: código de equipo, PIN.
- Comparación case-insensitive/normalizada contra
  `codigos_equipos.json`. Sin límite de intentos por ahora (no hay datos
  sensibles de pago detrás; el peor caso es que alguien vea las
  supertécnicas de otro equipo, no las edite sin acertar el PIN correcto).
- Sesión PHP (`session_start()`), guarda `$_SESSION['st_equipo_id']`.
- Sin registro, sin recuperación de contraseña: si un presidente pierde el
  PIN, el admin se lo mira/cambia en su panel.

### Admin

- HTTP Basic reutilizando el patrón de `config/admin_auth.php`
  (`requerirAdminBasicAuth()`), pero con credenciales **propias** de esta
  herramienta en `config/secrets.php`
  (`admin_supertecnicas_user`, `admin_supertecnicas_pass_hash`), para no
  acoplar esta herramienta a la de lesiones. Se añaden esas dos claves al
  `secrets.php` existente.

## Flujo de presidente (`supertecnicas/index.php`)

1. Sin sesión → formulario de login.
2. Con sesión → cargar `datos_oficiales.json`, localizar el equipo de
   `st_equipo_id`. Si ya no existe o está archivado, cerrar sesión y volver
   al login con aviso.
3. Listar jugadores activos del equipo (foto, nombre, dorsal, posición).
   Para cada jugador, 4 bloques de supertécnica (ya tenga 0, 1, 2, 3 o 4
   asignadas — huecos vacíos se muestran en blanco), cada bloque con los
   mismos campos que `gestor/`: nombre (texto), tipo (select
   tiro/regate/bloqueo/parada/—), afinidad (select
   neutro/fuego/montaña/bosque/aire/—), especial (texto libre), descripción
   (textarea).
4. Si `config.json.ventana_abierta` es `false`: los campos se muestran
   deshabilitados (`disabled`) y aviso "La ventana de supertécnicas está
   cerrada" — se puede ver lo ya asignado, no editar.
5. Botón "Guardar" → `POST supertecnicas/guardar.php` con todo el formulario.
6. Botón "Cerrar sesión" → `supertecnicas/logout.php`.

## Guardado (`supertecnicas/guardar.php`)

1. Requiere sesión con `st_equipo_id` válido.
2. Requiere `ventana_abierta === true` (si no, 403 + mensaje, no escribe
   nada).
3. Abre `datos_oficiales.json` con `flock(LOCK_EX)`, relee en el momento del
   guardado (no confía en una copia cacheada del navegador).
4. Localiza el equipo de la sesión; si ya no existe, aborta sin tocar el
   fichero.
5. Para cada jugador del equipo presente en el POST: reconstruye su array
   `supertecnicas` a partir de los hasta 4 bloques enviados, descartando
   bloques totalmente vacíos, recorta `nombre`/`especial` a 40 caracteres y
   `descripcion` a 300, valida que `tipo`/`afinidad` sean uno de los valores
   permitidos (si no, se guarda vacío). Nunca toca jugadores de otros
   equipos ni otros campos del jugador (goles, historial, etc.).
6. Escribe con patrón atómico: `json_encode` a fichero temporal en el mismo
   directorio + `rename()` sobre `datos_oficiales.json`, todavía bajo el
   lock. Evita que una lectura concurrente (la web pública, u otro
   presidente) vea un JSON a medio escribir.
7. Responde JSON `{ok:true}` / `{ok:false, error:'...'}`; la página
   recarga/actualiza sin perder la posición de scroll.

## Panel de admin (`supertecnicas/admin.php`)

- Protegido con HTTP Basic (credenciales propias, ver arriba).
- Interruptor "Ventana de supertécnicas: abierta/cerrada" → guarda
  `config.json`.
- Tabla de equipos activos (excluye `archivado:true`): nombre, ciudad,
  código actual, PIN actual, inputs editables + botón guardar por fila.
  Equipos sin entrada en `codigos_equipos.json` aparecen con el valor
  sembrado por defecto (nombre/ciudad) sin guardar todavía, marcados como
  "pendiente de confirmar".
- Guardado de esta tabla también usa `flock` + escritura atómica sobre
  `codigos_equipos.json` (fichero propio, pequeño, sin relación con
  `datos_oficiales.json`).

## Fuera de alcance (explícitamente no se hace)

- No se toca `transferroom/`, `admin/index.php` ni `includes/auth.php`.
- No hay recuperación de PIN autoservicio, ni límite de intentos de login,
  ni registro de auditoría de quién cambió qué.
- No se añade un catálogo cerrado de supertécnicas ni validación de balance
  de juego: los campos de texto son libres (salvo `tipo`/`afinidad`, que son
  selects restringidos porque ya lo son en `gestor/`).
- No se pagina la plantilla del presidente (equipos de esta liga son
  pequeños, ~15-25 jugadores).
- El límite de 4 supertécnicas por jugador es exclusivo de esta herramienta;
  `gestor/` sigue sin límite y no se modifica.

## Verificación antes de dar por terminado

- `php -l` sobre cada `.php` nuevo.
- Login con código/PIN correcto e incorrecto.
- Con ventana cerrada: los campos se ven pero no se pueden guardar (probar
  también llamando `guardar.php` directamente para confirmar que el
  servidor lo rechaza, no solo el HTML deshabilitado).
- Guardar 4 supertécnicas completas en un jugador y comprobar en
  `datos_oficiales.json` que solo cambió ese jugador de ese equipo.
- Guardar más de 4 bloques rellenos no debe ser posible desde el HTML
  (solo se renderizan 4) ni desde una petición manual (el servidor corta en
  4).
- Recargar `index.html`/`en.html` (fetch de `datos_oficiales.json`) y
  confirmar que la supertécnica nueva aparece en la web pública sin build.
- Admin: cambiar código/PIN de un equipo y comprobar que el login viejo deja
  de funcionar y el nuevo sí.
