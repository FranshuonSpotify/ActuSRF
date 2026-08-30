# Palmarés por presidente — diseño

## Contexto

El palmarés público hoy (`_fuente/app.js:1116-1189`, modal `ov-champs` abierto por
`openChamps(idx,label)`) se calcula por **temporada y equipo**: `palmares(idx)`
deriva el campeón de cada competición a partir de `historial_temporadas[idx]`
(campeón apuntado a mano en `t.campeones`, o por puntos si no hay nada
apuntado). El nombre de la persona que se muestra junto al equipo sale hoy de
un mapa fijo en JS, `PRESIDENTES` (`app.js:1120`), que solo cubre 7 equipos y
solo conoce **quién dirige el equipo ahora**.

Eso rompe en cuanto un presidente deja la liga o cambia de equipo: sus
títulos pasados quedan mal atribuidos al presidente actual del equipo, o
desaparecen si se edita/borra la entrada del mapa. Se pide un palmarés que
honre al presidente que ganó el título en su momento, no al que dirige hoy el
club con ese nombre.

## Hallazgo clave: el dato ya existe, sin cambiar el esquema

El nombre real del presidente de cada equipo ya se guarda en
`equipo.ciudad` (p. ej. `D4rkRepulser`, `Franshu`, `Gabrii`...), para los 42
equipos del JSON, activos y archivados — no solo los 7 del mapa `PRESIDENTES`.

Además, `historial_temporadas[i].equipos` es una **copia profunda tomada en
el momento de archivar esa temporada**
(`gestor/js/core.js:1162-1174`, `JSON.parse(JSON.stringify(d.equipos))`), así
que cada temporada archivada ya conserva el `ciudad` (presidente) **tal como
era entonces**, aunque hoy ese mismo equipo tenga otro presidente o esté
archivado.

Conclusión: el palmarés por presidente se puede derivar por completo de datos
que ya existen. No hace falta ninguna lista maestra de presidentes con
periodos por fecha, ni tocar el esquema de `datos_oficiales.json`, ni el
gestor.

## Corrección de paso (bug ya existente, relacionado)

El sheet de equipo (`_fuente/app.js:791-834`) tiene dos problemas con este
mismo dato:

- Línea 798: muestra `e.ciudad` sin etiqueta, pegado a la abreviatura del
  equipo, como si fuera una ciudad de verdad.
- Línea 831: la fila etiquetada **"Presidente / Gerente"** lee `e.gerente`,
  un campo que **no existe** en el JSON (siempre sale `·`).

Se corrige: la fila de staff etiquetada "Presidente / Gerente" pasa a leer
`e.ciudad`, y se retira el `e.ciudad` suelto y sin etiqueta de la cabecera
(`tm-sub`) para no duplicarlo.

## Diseño

### Cálculo (nuevas funciones en `_fuente/app.js`, junto a `palmares()`)

Todas son de solo lectura sobre `bd.historial_temporadas`; ninguna toca el
JSON.

- **`titulosDePresidente(nombre)`** — recorre todas las `historial_temporadas`
  por índice, llama a `palmares(idx)` (ya existente, sin tocar) y se queda
  con las entradas donde `(entry.e.ciudad||'').trim()===nombre`. Devuelve un
  array ordenado de más reciente a más antiguo, cada elemento con:
  `{temporadaNombre, temporadaFecha, comp, cls, marcador, equipo:entry.e}`
  (`equipo` es el snapshot de esa temporada: crest, nombre e `id` para el
  enlace al club).
- **`titulosDeEquipo(equipoId)`** — misma mecánica, filtrando por
  `entry.e.id===equipoId`. Cada elemento añade además `presidente` (el
  `ciudad` de ese snapshot), porque dentro del palmarés de un club interesa
  saber quién lo ganó en cada ocasión.
- **`presidentesConTitulos()`** — agrupa todas las entradas de todas las
  temporadas por `ciudad`, cuenta títulos por presidente y devuelve solo los
  que tienen 1 o más, ordenados por número de títulos descendente y luego
  alfabético.

Normalización mínima: se descartan entradas cuyo `ciudad` esté vacío o sea
`undefined` (equipos con datos incompletos, ya existen ejemplos en el JSON:
`Oscuridad Ancestral`, `Ragnah`) — no aparecen en el palmarés por presidente,
igual que hoy no aparecerían con un mapa incompleto.

### Vistas nuevas (overlays `.ov`, mismo patrón que `ov-champs`)

1. **`ov-presidentes`** — "Salón de presidentes". Rejilla de tarjetas, una
   por presidente de `presidentesConTitulos()`: nombre y número de títulos.
   Puntos de entrada:
   - Botón nuevo en la sección Historia (junto al bloque de antigüedad /
     palmarés por temporada existente).
   - El nombre del presidente dentro de `openChamps` (línea 1179, hoy texto
     suelto) pasa a ser un botón que abre este salón ya filtrado a esa
     persona.
2. **Detalle de presidente** (mismo overlay, contenido sustituido al
   seleccionar una tarjeta, sin overlay adicional): lista cronológica de
   `titulosDePresidente(nombre)`. Cada fila lleva `data-team="<id del equipo
   de ese snapshot>"`, así que un clic la abre en el sheet del club
   reutilizando el delegado global ya existente (`app.js:1702`,
   `[data-team]→openTeam`), sin código nuevo para ese clic. Un botón "←
   volver" regresa a la rejilla de tarjetas.
3. **`ov-team-titulos`** — botón nuevo dentro del sheet de equipo ("Ver
   palmarés del club"), abre este overlay **por encima** del sheet (overlay
   anidado; el sheet no se cierra, porque `ov-team-titulos` se abre igual
   que `ov-champs`, con `classList.add('open')` directo, sin pasar por
   `openSheet()`, que es lo único que cierra overlays). Contenido:
   - Palmarés global del club: lista cronológica de
     `titulosDeEquipo(e.id)`, cada fila mostrando también el presidente de
     esa temporada.
   - Bloque "Presidentes con título en este club": mismas entradas agrupadas
     por presidente, con recuento, debajo del listado cronológico.
   - Botón de cierre propio + clic en el fondo cierra solo este overlay
     (mismo patrón que `champ-close` / `ov-champs` en `app.js:5395-5396`),
     dejando el sheet de equipo debajo tal cual estaba.

### i18n

Textos nuevos con `T('clave','texto en español')` y fallback en español,
igual que el resto del código ya existente (p. ej. `T('champs.title',
'Palmarés')`). No se traduce a los 9 idiomas en esta iteración (ver Fuera de
alcance).

### CSS

Reglas nuevas en `_fuente/styles.css` para las tarjetas de presidente y el
overlay anidado sobre el sheet (z-index por encima de `.sheet`, mismo
lenguaje visual que `.champ`/`.champ-crest` ya existente — se reutilizan
esas clases donde encajen en vez de duplicar estilos).

## Fuera de alcance (explícitamente no se hace en esta iteración)

- **No se toca el gestor ni el esquema del JSON.** El presidente sigue
  siendo `equipo.ciudad`; no hay entidad "presidente" propia.
- **Foto/bio de presidente y una sección de gestión en el gestor** (alta de
  presidente con foto, biografía, quizá desvinculado de `ciudad`) quedan
  pendientes como mejora futura explícita, no se construyen ahora.
- **Traducción a los 9 idiomas** (`en, pt, it, fr, ja, ko, pl, bg, sr` vía
  `_fuente/dict.js`) de los textos nuevos queda pendiente como mejora futura
  explícita; por ahora los textos salen siempre en español (fallback de
  `T()`), igual que ya ocurre con otros textos del sitio que aún no están en
  `dict.js`.
- No se añade edición manual del presidente por título (override): la
  atribución es siempre automática por `ciudad` del snapshot de esa
  temporada.
- No se pagina el Salón de presidentes ni el palmarés de un club: la liga es
  pequeña (2 temporadas archivadas hasta ahora), no hace falta.

## Verificación antes de dar por terminado

- `node _fuente/build.js` corre sin errores y regenera `index.html` + los 9
  `{lang}.html` + `404.html`/`terminos.html`.
- El sheet de equipo muestra el presidente correcto bajo "Presidente /
  Gerente" (antes salía `·`), y ya no aparece duplicado sin etiqueta en la
  cabecera.
- Abrir el modal de campeones de una temporada archivada: el nombre del
  presidente que se ve es el `ciudad` **de esa temporada**, no el actual del
  equipo (probar con un equipo cuyo `ciudad` haya cambiado entre
  Temporada 1 y Temporada 2 si existe alguno, o forzar el caso a mano en una
  copia de prueba del JSON).
- Salón de presidentes: aparecen solo quienes tienen ≥1 título; el recuento
  coincide con sumar a mano las entradas de `historial_temporadas[*].campeones`
  agrupadas por `ciudad`.
- Clic en una fila de título dentro del Salón abre el club correcto
  (`openTeam`), incluso si ese club está archivado.
- Dentro del sheet de un club con títulos: "Ver palmarés del club" abre el
  overlay sin cerrar la ficha del club debajo; cerrarlo devuelve
  exactamente al mismo scroll/estado del sheet.
- Sin scroll horizontal de página ni dentro de los overlays nuevos, en
  375×812 y en escritorio.
