# Idioma en supertécnicas — diseño

## Contexto

Se han unido presidentes que no hablan español (ingleses, franceses...). Dos
problemas distintos:

1. El nombre/descripción de una supertécnica es texto libre que cada
   presidente escribe en su propio idioma. Hoy, cuando un visitante ve la
   ficha del jugador en la web pública (`openPlayer()` en `_fuente/app.js`,
   línea ~852) con un idioma distinto seleccionado, ese texto **no se
   traduce de verdad** aunque el sitio ya tiene un sistema de auto-traducción
   genérico (`_fuente/i18n.js`): un `MutationObserver` que detecta cualquier
   texto nuevo pintado en `document.body` y lo traduce vía la API pública de
   Google Translate, con caché. El bug real es que esa llamada tiene fijado
   `sl=es` (idioma de origen = español) en dos sitios — si el texto de
   origen no es español, la traducción parte de una premisa falsa.
2. La propia herramienta `supertecnicas/` (login del presidente + plantilla
   de edición) no tiene ningún sistema de idioma: siempre está en español,
   sin más texto que el que escribió el desarrollador.

## Alcance

- **Bloque 1**: arreglar la auto-traducción de texto libre en la web
  pública, y añadir un diccionario curado para el campo `tipo` de cada
  supertécnica (igual que ya existe para `afinidad`).
- **Bloque 2**: traducir el chrome de `supertecnicas/index.php` (login +
  plantilla del presidente) a los 10 idiomas que ya soporta la web pública,
  con detección automática por navegador y banderas para cambiar a mano.
- **Fuera de alcance** (confirmado con el usuario): `supertecnicas/admin.php`
  se queda en español — solo lo usa el administrador.

## Bloque 1 — Auto-traducción de texto libre (web pública)

### Fix del idioma de origen

`_fuente/i18n.js` tiene dos llamadas a la API de Google Translate
(`_sfATRequest`, línea 213, y `sfATBatch`, línea 406), ambas con
`sl=es&tl=...` en la URL. Se cambia `sl=es` por `sl=auto` en las dos, para
que Google detecte el idioma real del texto en vez de asumir español
siempre. Es un cambio de dos líneas: no toca la caché, la cola de peticiones
ni el `MutationObserver` que ya vigila la página — solo corrige la premisa
de origen. Beneficio colateral: cualquier otro texto no-español que ya
exista en el sitio (aunque hoy no se dé el caso) también se traduciría bien.

### Diccionario curado para `tipo`

Se añade `SF_TIPO_MAP` en `_fuente/i18n.js`, con la misma forma que
`SF_AFINIDADES_MAP` (línea 252): claves normalizadas (`tiro`, `regate`,
`bloqueo`, `parada`) → objeto con los 10 idiomas del sitio
(`es/en/pt/it/fr/ja/ko/pl/bg/sr`), y una función `sfTipoLabel(tipo)` que la
consulta (mismo patrón que `sfAfinidadLabel()`). Terminología oficial del
juego, no traducción literal.

En `_fuente/app.js`, se añade un helper `tipoName(t)` justo al lado de
`afName(a)` (línea 71), con el mismo patrón de fallback:
`window.sfTipoLabel ? sfTipoLabel(t) : TIPO_LABEL[...]`. El bloque `techs`
de `openPlayer()` (línea 852-854) pasa a imprimir
`esc(tipoName(t.tipo))` en el badge en vez de `esc(t.tipo)` en crudo.

`nombre` y `descripcion` no necesitan ningún cambio de código: ya se pintan
como texto normal dentro de `document.body`, así que el `MutationObserver`
existente los recoge y traduce solo, una vez corregido el `sl=auto`.

## Bloque 2 — Idioma de `supertecnicas/index.php`

### Fichero nuevo: `supertecnicas/i18n.php`

- `$ST_I18N`: array asociativo `[idioma][clave] => texto`, con los 10
  idiomas del sitio. Cubre las ~35 cadenas visibles de `index.php` y
  `logout.php` (título/subtítulo de login, etiquetas de campo, mensaje de
  error, cabecera de la plantilla, estado de ventana, avisos, etiquetas de
  cada supertécnica, botón de guardar, "Cerrar sesión"...).
- `stT(string $clave): string` — devuelve `$ST_I18N[$idiomaActual][$clave]`;
  si la clave falta en ese idioma, cae a `$ST_I18N['es'][$clave]`; si
  tampoco existe ahí, devuelve la propia clave entre corchetes (para que un
  texto que falte se note en pantalla en vez de desaparecer en silencio).
- `$ST_TIPOS_I18N` / `$ST_AFINIDADES_I18N`: mismas 10 traducciones que
  `SF_TIPO_MAP`/`SF_AFINIDADES_MAP` de `_fuente/i18n.js`, copiadas a PHP
  (no se puede compartir el mismo fichero JS desde PHP sin añadir un paso de
  build, así que se mantienen dos copias con un comentario en cada una
  apuntando a la otra). Solo cambian las **etiquetas** que ve el presidente
  en los `<select>` de Tipo/Afinidad; el `value` de cada `<option>` sigue
  siendo siempre el término canónico en español, así que `guardar.php` seguí
  aceptando exactamente los mismos valores de `ST_TIPOS`/`ST_AFINIDADES` que
  ya definía `lib.php` — cero cambios ahí.

### Resolución de idioma

Función `stResolverIdioma(): string` en `i18n.php`, llamada al principio de
`index.php` (antes de cualquier salida HTML):

1. Si `$_GET['lang']` es uno de los 10 códigos soportados, se usa y se
   guarda en `$_SESSION['st_lang']`.
2. Si no hay `lang` en la URL pero `$_SESSION['st_lang']` ya tiene un valor
   válido, se usa ese.
3. Si tampoco hay nada en sesión, se parsea la cabecera
   `HTTP_ACCEPT_LANGUAGE` (formato `es-ES,es;q=0.9,en;q=0.8`): se extrae el
   subtag primario de cada entrada, ordenado por `q` descendente, y se usa
   el primero que coincida con un idioma soportado.
4. Si nada de lo anterior da un idioma válido, español por defecto.

El resultado se guarda también en `$_SESSION['st_lang']` en el paso 3 (para
no repetir el parseo de cabecera en cada petición de esa sesión).

### Selector de idioma

Fila de banderas sobre el login y sobre la cabecera de la plantilla:
enlaces simples `<a href="?lang=en">` con `<img>` de
`https://flagcdn.com/16x12/{código-país}.png` (misma fuente que ya usa
`_fuente/shell.html`), mismos pares idioma→código de país que
`SF_LANGS` de `i18n.js` (`es→es, en→gb, pt→pt, it→it, fr→fr, ja→jp, ko→kr,
pl→pl, bg→bg, sr→rs`). Sin JavaScript: cada clic es una navegación normal
que vuelve a pasar por `stResolverIdioma()`.

### Qué NO cambia

- `supertecnicas/admin.php` se queda en español, sin tocar.
- `supertecnicas/guardar.php` no cambia: sigue validando `tipo`/`afinidad`
  contra `ST_TIPOS`/`ST_AFINIDADES` (los mismos 5+4 valores canónicos en
  español de siempre) — el idioma es puramente de presentación.
- El esquema de `datos_oficiales.json` no cambia.
- `supertecnicas/data/*.json` (códigos/PIN, ventana) no cambian de forma.

## Verificación antes de dar por terminado

- Cambiar `sl=es`→`sl=auto`, abrir la web pública, forzar `?lang=fr` y
  comprobar que un texto de prueba en español sigue traduciéndose bien
  (que el cambio no regresiona el caso ya existente).
- Añadir una supertécnica de prueba con nombre en francés (vía
  `supertecnicas/index.php` en local), ver su ficha en la web pública con
  `?lang=es` seleccionado y confirmar que aparece traducida al español (no
  el francés en crudo, ni una traducción absurda por asumir origen
  español).
- `tipo` de esa misma supertécnica se ve con la etiqueta del idioma
  seleccionado (`SF_TIPO_MAP`), no el string español en crudo.
- En `supertecnicas/index.php`: sin `?lang` ni sesión previa, con el
  navegador en inglés, la página carga en inglés. Clic en la bandera de
  español cambia a español y lo recuerda en el resto de la sesión.
- Un presidente francés guarda una supertécnica con las etiquetas del
  desplegable en francés; en `datos_oficiales.json` el valor guardado de
  `tipo`/`afinidad` sigue siendo el término canónico en español.
- `php -l` sobre `supertecnicas/i18n.php` y `supertecnicas/index.php`
  modificado.
