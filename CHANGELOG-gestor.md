# Changelog del gestor

## 2026-09-09 — Palmarés inclinado, antigüedad real por temporadas, dos moderadores y peso de la página

**Fotos de trofeo inclinadas 35°.** Las fotos que se suben desde la sección
Palmarés del gestor se muestran giradas 35° a la derecha en los mosaicos de
la tira de honores (`.honour-tile img`), que es lo que se ve en el detalle de
presidente y en "Ver palmarés del club" de la ficha de equipo. La lista de
"ver campeones" (`.champ-trophy-foto`) se deja **sin inclinar**, a propósito.
La rotación va acompañada de `scale(1.6)` porque un rectángulo girado 35° no
cubre el mismo hueco: sin ella, con `object-fit:cover`, el mosaico enseñaba
el fondo por las esquinas. Los dos valores son variables CSS
(`--tilt`/`--tilt-fill`) en `.honour-tile`.

**Antigüedad de un jugador en un club: se cuenta por temporadas archivadas,
no por las etiquetas del historial.** Las 423 estancias de `historial[]` de
`datos_oficiales.json` traen etiquetas corridas de una importación antigua:
todas dicen "Temporada 1" o "2", ninguna dice 3, aunque `historial_temporadas`
ya tiene archivadas la Temporada 2 y la Temporada 3. Con eso, Raleigh
Greenstreet salía como "Temporada 1" y "1 temporada" en Zanark Domain cuando
lleva ahí la 2 y la 3.

Decisión: **la fuente de verdad de la antigüedad son los snapshots de
`historial_temporadas`**, que sí son copias de la plantilla real de cada
temporada cerrada. `temporadasEnClub(nombre, equipo_id)` en `_fuente/app.js`
cuenta en cuántos snapshots aparece ese jugador en ese club, y de ahí salen
tanto el rango ("Temporada 2 - 3") como el contador de temporadas. **La
temporada en curso no cuenta hasta que se cierre y se archive**, tal y como
lo cuenta la liga: un fichaje de esta temporada sale con 0.

- Cobertura sobre el archivo actual: 399 de 423 estancias resuelven por
  snapshot. Las 24 restantes son estancias **cerradas** de clubes que ya no
  existen; para esas se sigue usando la etiqueta del JSON como respaldo.
- **No se ha reescrito `datos_oficiales.json`.** Las etiquetas corridas se
  quedan como están y la web las ignora. Corregirlas en el archivo sería otro
  trabajo, con riesgo sobre 423 registros y sin ganancia visible.
- Limitación conocida: si un jugador se fuera y volviera al mismo club, sus
  dos estancias comparten `equipo_id` y saldrían con el mismo recuento. Hoy
  no le pasa a ningún jugador del archivo.
- Comprobación en `_fuente/test-palmares.js` (2 asserts nuevos).

**Dos moderadores nuevos en la sección Staff:** Lulu (Instituto Zeus) y Jade
Beor (Oscuridad Ancestral), con `assets/lulu.webp` y `assets/jade.webp`. Los
dos clubes **sí existen** ya en `datos_oficiales.json`, así que
`renderStaffClubs()` les inyecta el escudo real sin tocar nada más. El texto
de entrada de la sección (`staff.lede` en `dict.js`, los 10 idiomas) pasa de
"Seis personas / Dos siguen compitiendo" a "Ocho personas / Cuatro siguen
compitiendo" — cuatro porque son los cuatro que tienen club puesto en su
tarjeta (D4rkRepulser, Totti Alcresise, Lulu y Jade Beor).

**Rendimiento.** La primera carga bajaba ~21 MB. Ahora baja ~3,5 MB (de los
cuales 2,5 MB son `datos_oficiales.json`, que gzip deja en ~235 KB en el
servidor real).

- Las 9 fotos de la web (staff, leyendas, hero) eran PNG sin redimensionar:
  `franshu.png` pesaba 5 MB a 3120×3120 px para un hueco de 340 px. Se han
  reencodado a WebP a 800 px (1600 px el hero) con ffmpeg: 20 MB → 640 KB.
  Los PNG originales **se conservan en `assets/`**, simplemente ya no se
  referencian.
- Las fotos de staff y leyendas llevan `loading="lazy"` + `decoding="async"`:
  están muy por debajo del pliegue y hasta ahora se bajaban antes de que
  nadie llegara a verlas.
- La música ambiente (`assets/web*.mp3`, 2,5 MB) se bajaba entera en **cada**
  carga, incluso con el sonido silenciado: se asignaba `audio.src` y se
  llamaba a `play()` al arrancar, y eso dispara la descarga aunque el
  navegador bloquee la reproducción automática (que la bloquea siempre sin un
  gesto previo). Ahora la pista se elige y se asigna dentro de `sonar()`, y al
  cargar solo se arma el listener del primer gesto — que es el camino por el
  que ya sonaba de hecho. Comportamiento visible: idéntico.
- **No hecho a propósito:** minificar `datos_oficiales.json` al guardar desde
  el gestor. Pasaría de 2,5 MB a 1,1 MB en disco, pero con gzip la diferencia
  real por cable es de 235 KB a 208 KB, y el archivo dejaría de poder leerse
  y editarse a mano. No compensa.
- **Fuentes CJK bajo demanda.** El `<link>` de Google Fonts pedía Noto Sans
  JP y KR a todos los visitantes: entre las dos declaran cientos de
  subconjuntos unicode y engordaban esa hoja hasta **641 KB**, también para
  quien lee en español. Ahora el `<link>` base sólo pide Inter, Teko,
  Fraunces y JetBrains Mono (**30 KB**), y `sfFuentesCJK()` en `i18n.js`
  inserta la hoja de Noto sólo al aplicar japonés o coreano. Como el
  pre-renderizado de `build.js` ejecuta esa misma función, `ja.html` y
  `ko.html` salen del build con su `<link>` ya en el HTML servido: no hay
  vuelta de red extra por depender de JS. Medido: español 641 → 30 KB,
  japonés 641 → 364 KB.

  **Hallazgo al hacerlo:** Noto Sans JP/KR **no está en ninguna
  `font-family`** del sitio — ni ahora ni antes. Se pedía la hoja pero el
  texto japonés y coreano siempre ha caído al tipo del sistema, así que
  ningún fichero `.woff2` de Noto se ha descargado nunca (verificado: 0
  ficheros de `fonts.gstatic.com/s/notosansjp` en una carga limpia de
  `ja.html`). Consecuencia: hoy esos 334 KB de CSS que bajan ja/ko son peso
  muerto. Se ha dejado el mecanismo montado en vez de borrar las familias
  porque estaban puestas a propósito y lo que falta es un solo paso.
  Quedan dos salidas, ambas de una línea, a elegir por Alejandro:
  1. **Activarlas de verdad:** añadir `'Noto Sans JP','Noto Sans KR'` detrás
     de `'Inter'` en `--f-sans` (`styles.css`). Japonés y coreano pasan a
     renderizar con Noto, a cambio de ~700 KB de subconjuntos woff2 en esos
     dos idiomas.
  2. **Quitarlas del todo:** borrar `SF_CJK`/`sfFuentesCJK()`. Nadie se baja
     nada y no cambia ni un píxel, porque hoy no se usan.

Tras estos cambios se reconstruyeron `index.html` y los 9 `*.html` de idioma
con `node _fuente/build.js`.

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
