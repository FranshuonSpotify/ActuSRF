# Changelog del gestor

## 2026-09-09 — Arranque de la Temporada 4

La Temporada 3 ya estaba completa (71 partidos de Liga, 45 de Ascenso, 37 de
Copa, todos `FINALIZADO`) y archivada en `historial_temporadas[1]` (commit
`6f464ba`). Se hizo el flip real a Temporada 4 en `datos_oficiales.json`:

- `config.temporada`: `"3"` → `"4"`.
- `config.jornada_actual`: `"11"` (desincronizado, quedó atrás) → `"1"`.
- `config.grupos_copa` y `clasificacion_copa`: vaciados a `{}` — eran el
  sorteo/clasificación de la Copa de T3, no aplican a una Copa de T4 sin
  sortear todavía.
- `partidos_liga`, `partidos_ascenso`, `partidos_copa`: vaciados a `[]`. El
  detalle partido a partido de T3 sigue disponible en
  `historial_temporadas[1]`.
- Equipos **activos** (`archivado` distinto de `true`): `pj/g/e/p/gf/gc/pts`
  reseteados a 0. Los equipos archivados no se tocaron.
- Jugadores de equipos activos: `goles/asistencias/amarillas/rojas`
  reseteados a 0 tras acumular su valor de T3 en `*_totales` y, si existe una
  estancia abierta (`historial[].abierto===true`) en su club actual, también
  ahí. Patrón verificado contra jugadores que no cambiaron de club entre T2 y
  T3 (p. ej. Joaquine Downtown, Soji Okita) para replicarlo exactamente.

**Decisiones explícitas de Alejandro, no tocadas:**
- `division` de los equipos: no se recalculan ascensos/descensos
  automáticamente; los aplicará él a mano porque algunos equipos van a salir
  de la liga.
- Equipos nuevos y bajas de equipos: los añadirá/archivará él manualmente.

**Gap de datos preexistente, no corregido (fuera de alcance de este
cambio):** 227 jugadores de equipos activos no tienen `historial[]` en
absoluto (esquema legacy, nunca migrado) y otros 106 tienen `historial[]`
pero ninguna estancia abierta que coincida con su club actual (probablemente
traspasos que nunca abrieron una estancia nueva). En ambos casos solo se
resetearon sus estadísticas de temporada; no se les inventó estructura de
historial nueva.

**Bug de la web pública corregido de paso (necesario para que "Temporada 4"
se viera en algún sitio):** `renderAntiguedad()` — la función que pinta
"Temporada N · En juego" en el hero — nunca estaba en la lista de
`renderAll()` en `_fuente/app.js`, así que solo se ejecutaba una vez al
cargar la página, antes de que llegaran los datos reales por `fetch`. Se
quedaba pintado con el valor por defecto (`||3`) para siempre, tanto en el
navegador real como en el pre-renderizado de build (`_fuente/prerender.js`
ejecuta literalmente `app.js`). Se añadió `pasoRender(renderAntiguedad)` a
`renderAll()` y se actualizaron los defaults `||3`→`||4`. Se aprovechó para
cambiar el estado de la tarjeta "T.4" de la timeline de Historia, de "En
preparación" a "En juego" (clave `historia.t4.tag`/`historia.t4.f3` en
`dict.js`, en los 10 idiomas).

Tras estos cambios se reconstruyeron `index.html` y los 9 `*.html` de idioma
con `node _fuente/build.js`.
