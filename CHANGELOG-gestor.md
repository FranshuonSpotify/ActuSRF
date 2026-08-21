# CHANGELOG — Dashboard de gestión

Registro de decisiones de normalización de datos y de alcance del gestor
(`propuesta-web/gestor/`). Cada entrada dice **qué** se decidió y **por qué**.

---

## Fase 1 — Núcleo, CRUD completo y calidad de datos base

**Estado: completada.** Criterio de salida verificado (ver §Verificación).

### Decisión de partida

- **`datos_oficiales.json` de la raíz es la fuente de verdad**, no el de
  `propuesta-web/`. Confirmado por Alejandro. La copia de `propuesta-web/`
  está por detrás (jornada 9 y 54 partidos de liga, frente a jornada 11 y 66).
  **No se ha sobrescrito la copia de `propuesta-web/`**: es el archivo que
  sirve la web pública y modificarlo cambia lo que ve el visitante. Queda
  pendiente de que Alejandro decida cuándo sincronizarlas.
- **Las cuatro claves no documentadas en `CLAUDE.md` §3 son parte del
  esquema** y se preservan intactas: `historial_temporadas` (la lee la web
  para el Palmarés), `agentes_libres`, `historial` y `clasificacion_copa`.
  Confirmado por Alejandro.
- **El repositorio no estaba bajo control de versiones.** Se hizo `git init`
  con el estado previo como primer commit, para que cualquier cambio del
  gestor sea reversible.

---

### Correcciones al esquema documentado

Diferencias entre lo que dice `CLAUDE.md` §3 y lo que hay en el archivo real.
El gestor implementa **lo real**.

| Punto | `CLAUDE.md` | Realidad | Qué hace el gestor |
|---|---|---|---|
| Orden de clasificación | 7 criterios, acaba en derrotas → alfabético | `orderStandings()` tiene **8**: entre derrotas y alfabético hay uno por partidos jugados (menos, más arriba) | Reproduce los 8. El 4.º (goles en contra) es **inalcanzable por aritmética** —si empatan en diferencia y en goles a favor, los goles en contra son iguales por fuerza— pero se conserva porque está en la web |
| Tipos de evento en `detalles` | sólo `gol` | `openMatch()` reconoce `gol`, `amarilla`, `roja` y trata el resto como cambio; el prototipo usaba además `asistencia` | Editor con los cinco tipos |
| Penaltis | no documentado | `winnerOf()` busca `PEN: 3-2` **dentro de `detalles`**; es el único sitio donde vive ese dato | Se extrae aparte al parsear y se reescribe al final al serializar (ver más abajo) |
| `partidos_copa[].jornada` | implícito | no existe; la Copa se organiza por `fase` | La vista de Copa no muestra jornada |
| `config.temporada` / `jornada_actual` | — | son **cadenas** (`"3"`, `"9"`) | Se guardan como cadenas |
| `jugadores[].dorsal` | — | siempre **cadena** | Se fuerza a cadena al normalizar |
| `config.ticker_*` | listado como funcionalidad | **la web pública no los lee en ninguna pantalla** | Se editan y se conservan, pero la interfaz avisa de que hoy no se muestran |

---

### Decisiones de normalización

Todas se aplican **antes de cada guardado**, y ninguna destruye información.

1. **`amarillas`/`rojas` frente a `tarjetasAmarillas`/`tarjetasRojas`.**
   El par canónico es `amarillas`/`rojas`: lo tienen los 658 jugadores.
   El alias lo tienen 17, y **en los 17 los valores coinciden** (comprobado),
   así que no hay conflicto real que resolver.
   - Si sólo existe el alias → se copia al canónico.
   - Si existen los dos y discrepan → gana el canónico y **se registra la nota**
     para poder avisar; no se resuelve en silencio.
   - **El alias no se borra**, aunque sea redundante: no consta que nada más lo
     lea, pero borrarlo es irreversible y no aporta nada.
   - **El alias no se añade** donde no estaba: engordaría el archivo sin motivo.

2. **`goles_l`/`goles_v` frente a `golesl`/`golesv`.** Mismo criterio: el
   canónico es `goles_l`/`goles_v`, que es el que `gl()`/`gv()` de `app.js`
   consultan primero. Se sincronizan si el partido ya traía el alias; no se
   añade donde no estaba.
   - **Hallazgo:** 2 partidos de Liga guardaban `goles_l` como **texto**
     (`"3"` en vez de `3`), y 3 de Ascenso no tenían `goles_l` en absoluto,
     sólo el alias. Normalizar corrige ambos. Son 8 correcciones de tipo sobre
     el archivo real, y **ninguna cambia el valor que la web lee**.

3. **`goleadores_texto`, `goleadores_local_texto`, `goleadores_visitante_texto`**
   son derivados de `detalles` y se regeneran en cada guardado —sólo en los
   partidos que ya los traían, por el mismo criterio de no añadir campos.
   El formato se verificó regenerándolos y comparándolos con los **77 partidos
   que ya los tienen**: coinciden los tres textos en los 77.

4. **`historial[].temporada` frente a `temporada_inicio`/`temporada_fin`.**
   19 entradas no traen `temporada`. Se completa lo que falte a partir de lo
   que haya, sin tocar lo que ya está.

5. **La tanda de penaltis se conserva al editar los eventos de un partido.**
   `PEN: 3-2` vive suelto dentro de `detalles`. Al parsear se extrae aparte y
   al serializar se vuelve a escribir al final, donde ningún parser de eventos
   lo confunde con uno (`PEN` no es un tipo conocido) y `calcScorers()` no lo
   cuenta como goles. **Sin esto, editar los eventos de un cruce de Copa
   habría borrado el resultado de la tanda.**

6. **Las afinidades sucias NO se corrigen en masa.** 21 jugadores tienen
   afinidades no oficiales (`"Forest"`, `"montaña"`, `"bosque"`, dos con una
   **URL de foto** puesta por error, y algún `null`). La web las absorbe con su
   propio mapa y cae a Neutro lo que no encaje, así que **no está roto**.
   El gestor las marca en la lista y en la validación, y las corrige **en la
   entrada** (el importador CSV normaliza al vuelo), pero no reescribe 21
   fichas sin que Alejandro lo pida: una corrección masiva silenciosa sobre
   datos que hoy funcionan es peor que el aviso.

---

### Decisiones de diseño

- **Los algoritmos de la web están portados 1:1 en `js/core.js`**, no
  reimplementados. Se copian de `_fuente/app.js` tal cual porque `app.js` vive
  dentro de una IIFE cerrada y no exporta nada: no hay forma de importarlos.
  El comentario de cabecera del fichero deja escrito que si los dos divergen,
  el que miente es el gestor.

- **`resolveSide()` es de sólo lectura.** El prototipo `gestor.html` volcaba
  el ganador de la ronda previa dentro de `p.local`/`p.visitante`. Eso duplica
  la verdad: si luego cambia el resultado de la ronda anterior, el nombre
  escrito se queda obsoleto. Aquí la cascada se resuelve al pintar, como hace
  la web, y `origen_local`/`origen_visitante` es el único dato guardado.

- **Al vincular un lado de Copa a una ronda previa, el nombre fijo se vacía.**
  Por lo mismo: no puede haber dos fuentes para el mismo dato.

- **Al borrar un cruce de Copa se reajustan los `origen_*` posteriores.** Las
  vinculaciones se guardan por **posición en el array**, así que borrar el
  cruce 3 desplaza todos los índices. Los que apuntaban al borrado quedan sin
  vincular; los que apuntaban más arriba bajan uno.

- **El editor de goleadores es estructurado, nunca texto libre.** Los goles se
  enlazan al jugador comparando el nombre en texto con coincidencia difusa
  (`findPlayer()`): una errata deja el gol sin ficha y nadie se entera. El
  jugador se elige de la plantilla del club que anotó. Si el nombre guardado ya
  no está en esa plantilla (traspaso, errata antigua), se conserva como opción
  marcada «fuera de la plantilla» en vez de perderse al abrir el editor.

- **La clasificación se recalcula en cascada automáticamente** al cambiar un
  resultado, un estado o un equipo de un partido de Liga o Ascenso. Es lo que
  pide `CLAUDE.md` §5.1.3. Consecuencia asumida: un valor puesto a mano en la
  ficha del club se sobrescribe la próxima vez que se toque un resultado. Los
  campos siguen siendo editables y la interfaz marca en ámbar cualquier
  desajuste entre lo guardado y lo que dicen los partidos.
  - Sobre el archivo real hay **0 desajustes**: la tabla guardada coincide
    exactamente con la que producen los 78 partidos finalizados.

- **Renombrar un club arrastra el nombre por todos los partidos.** La web los
  referencia por nombre, no por id; dejarlos atrás rompería el calendario
  entero. Renombrar un **jugador** no arrastra nada (el nombre está dentro de
  una cadena de eventos), pero se avisa de cuántos eventos quedarán
  desenlazados.

- **Se enlaza `_fuente/styles.css` en vez de copiar tokens.** El gestor recibe
  así el Design System v3 completo —variables de `:root`, tipografías, `.card`,
  `.btn`, `.badge`, `.chip`— sin duplicar un solo valor. `css/gestor.css` sólo
  añade lo que la web pública no necesitaba: formularios, tablas de datos y el
  armazón de escritorio.

- **Contraste: `--ink-4` no se usa para texto que haya que leer.** Sobre
  `--surface` da 2,5:1, por debajo del 4,5:1 exigido. El texto de ayuda, las
  etiquetas de campo y las cabeceras de tabla usan `--ink-3` (4,6:1).
  `--ink-4` queda para marcas redundantes.

- **Nada de `confirm()` del navegador.** Toda acción destructiva pasa por un
  modal propio que dice qué se va a romper: cuántos partidos referencian al
  club que se borra, cuántas entradas de historial lo citan, cuántos cruces se
  alimentan del que se elimina.

- **El guardado se bloquea si la validación de integridad falla.** Se explica
  qué falla y se ofrece ir a arreglarlo. Forzar existe, pero detrás de una
  segunda confirmación que dice explícitamente que la web puede dejar de
  mostrar partes de la competición.

- **Las copias de seguridad van minificadas.** 644 KB frente a 1,4 MB con
  indentación, y el cupo de `localStorage` ronda los 5 MB contando cada
  carácter doble. Se guardan 5 y, si no cabe, se van tirando las más antiguas
  hasta que entre: una copia reciente vale más que cinco viejas. El archivo en
  disco sí se escribe con indentación de 4 espacios, como estaba.

---

### Qué se reutilizó de `gestor.html` y qué no

**Reutilizado (portado, no copiado):** la lógica de dominio ya probada —
recálculo de clasificación y de estadísticas de jugador, editor de eventos de
partido, selector de origen de Copa y propagación de ganadores.

**Descartado, con motivo:**

| Qué | Por qué |
|---|---|
| Todo el CSS y la capa visual | Usa `#ea4141` rojo, Bebas Neue y Space Grotesk. No es el Design System v3 |
| `onclick="…"` en línea | Es exactamente el patrón que `app.js` documenta haber eliminado por romperse con el apóstrofe de los goleadores (18 de 54 partidos no abrían) |
| `alert()` / `confirm()` como interfaz | No permiten dar contexto ni distinguir una acción destructiva de una rutinaria |
| Su persistencia | `<input type=file>` + descarga. Es el respaldo que pide `CLAUDE.md`, no el método principal. Sin estado sucio, sin copias, sin `Ctrl+S` |
| `migrarHistorialGlobal()` ejecutándose al cargar | **Mutaba los datos en silencio** en cada apertura, fusionando etapas consecutivas del mismo club. En el gestor nuevo normalizar es una acción explícita, nunca un efecto secundario de abrir el archivo |

**Cuatro fallos de `gestor.html` que no se han portado:**

1. `autocalcularClasificacion()` incluye equipos archivados y no separa por
   división.
2. `autocalcularGoleadores()` empareja jugadores con
   `j.nombre.includes(ev.nombre)`: con «Gar» en la liga, puede acumular goles
   al jugador equivocado.
3. La misma función resetea `goles/asistencias/amarillas/rojas` pero **no**
   `tarjetasAmarillas`/`tarjetasRojas`, desincronizando a los 17 jugadores que
   tienen ambos campos.
4. `propagarGanadoresCopa()` escribe el ganador dentro de `p.local` (ver
   arriba).

---

### Verificación

`node propuesta-web/gestor/test-core.js` — 13 comprobaciones sobre el archivo
real. Las que de verdad prueban algo:

- Los tres textos derivados de goleadores se regeneran **idénticos** en los 77
  partidos que ya los traían.
- La tabla calculada desde los 78 partidos finalizados coincide con la
  guardada **sin un solo desajuste**.
- Normalizar el archivo real es idempotente y no cambia **ningún** valor de
  competición (8 correcciones de tipo, 0 de significado).
- El validador de integridad da 0 críticos sobre el archivo real, y **detecta
  los 9 fallos** que se le introducen a propósito (equipo inexistente, id
  duplicado, división inválida, origen fuera de rango, origen circular directo,
  ciclo indirecto en el cuadro, estado desconocido, equipo contra sí mismo,
  `equipo_id` huérfano).
- La tanda de penaltis sobrevive a una edición de eventos y no se cuenta como
  goles.

`node propuesta-web/gestor/test-ciclo.js` — el criterio de salida de la fase
en un paso: crear equipo con plantilla → jugar jornada con goleadores →
publicar noticia → guardar.

**Comprobado además contra la web real:** se cargó el archivo resultante en
`propuesta-web/index.html` y `app.js` lo renderizó correctamente —el club
nuevo aparece 9.º en la clasificación de Ascenso con 3 puntos, sus dos
goleadores salen en el ranking **con ficha, escudo y posición**, la tarjeta de
equipo muestra los datos, y la noticia encabeza el listado con su etiqueta
nueva en los filtros.

**Pendiente de comprobar en un navegador real:** la QA visual a 375×812 y el
recorrido con tabulador. El panel de navegador del entorno de desarrollo no
compone (`innerWidth: 0`), así que las medidas de píxeles que da no son
fiables. Las reglas de CSS están puestas y verificadas a nivel de cascada
(`min-width:0` en la cadena de rejilla para que el desbordamiento se quede
dentro de `.tabla-scroll`, `.cel-btn` con 26px de alto mínimo), pero conviene
mirarlo con los ojos.

---

### Aplazado, con motivo

| Funcionalidad | Fase | Motivo |
|---|---|---|
| Sincronizar `propuesta-web/datos_oficiales.json` con el de la raíz | — | Cambia lo que ve el visitante de la web pública. Decisión de Alejandro |
| Corrección masiva de las 21 afinidades sucias | — | Hoy funcionan (la web las absorbe). Se avisa, no se reescribe sin permiso |
| Bloques 5.2 y 5.3 completos | 2 y 3 | Por orden de fases |

### Sin tocar

`propuesta-web/_fuente/app.js`, `styles.css`, `shell.html`, `index.html`,
`dict.js`, `i18n.js`, `faq-dict.js` y `datos_oficiales.json` de
`propuesta-web/`. El gestor no ha necesitado modificar la web pública.

---

## Adiciones fuera de plan (pedidas por Alejandro)

Cuatro funcionalidades pedidas junto con la Fase 2. Dos amplían el esquema, de
forma **aditiva y retrocompatible**: la web pública sigue leyendo el archivo
sin tocar una línea de `app.js`.

### 1. Fases de competición en partidos de Liga y Ascenso

Se reutiliza el campo **`fase`**, que ya existía en `partidos_copa`, en vez de
inventar uno nuevo. El motivo es concreto: `app.js` ya lo lee en dos sitios
—el pie de la tarjeta de partido y la insignia de su ficha— con la regla
`p.fase ? p.fase : 'Jornada N'`. Así la web muestra **PLAY IN** o **FINAL**
sin modificarla. Un campo nuevo habría obligado a tocarla.

Vocabulario: `PARTIDO POR EL PLAY IN`, `PLAY IN`, `SEMIFINALES`, `FINAL`,
`DESEMPATE`. Salen de `renderPlayoff()` de `app.js`, que es quien define el
cuadro de la Superliga; inventar otros habría creado dos vocabularios.

**Regla asociada: un partido con `fase` no reparte puntos.** Si los repartiera,
el campeón del play-off adelantaría en la tabla al primero de la fase regular.
`tablaCalculada()` los excluye y la vista lo explica en pantalla.

**Una eliminatoria sin jornada bloquea el guardado.** `initJornadas()` de
`app.js` descarta los partidos sin jornada y `renderMatches()` filtra por ella:
sin número, el partido existiría en el archivo pero no se vería en Resultados.

> **Límite conocido, no tapado:** `renderPlayoff()` construye su cuadro
> derivándolo de la clasificación (1.º a 6.º), **no** de los partidos de
> play-off. Marcar un partido como PLAY OFF hace que la web muestre la etiqueta
> en Resultados, pero el widget del cuadro de play-off seguirá diciendo «Por
> definir». Cambiar eso exige modificar `app.js`.

### 2. Formatos de competición (`config.formatos`)

Describen cómo está montada cada competición: vueltas, número de equipos,
plazas de play-off/descenso/ascenso, y para la Copa el tipo, número de grupos y
cuántos clasifican.

**Honestidad sobre su alcance:** `app.js` **no los lee**. Los cortes de la tabla
están escritos a mano en `renderClas()` (`pos<=3` play-off, `pos===4` play-in,
`pos<=6` partido por el play-in, últimos tres descenso). Cambiar el formato
aquí no cambia la web. Lo que sí hacen es alimentar las comprobaciones del
gestor y el reparto de grupos, y en la Fase 3 los generadores. **Cuando el
formato contradice lo que la web tiene fijo, el campo se marca en ámbar y se
avisa**, en vez de dejar creer que ha cambiado algo.

Valores por defecto 12/10 equipos: es lo que dice la propia copy de la web
(«1ª División · 12 equipos»). Hoy hay 11 y 9 activos, así que el aviso salta —
y es información útil, no ruido.

### 3. Grupos de Copa con arrastre (`config.grupos_copa`)

El reparto vive **aparte de los partidos**, a propósito: tras el sorteo hay que
poder mover un equipo de bombo antes de que exista un solo cruce, y si sólo
viviera dentro de `partidos_copa[].grupo` no habría dónde apuntarlo.
«Aplicar a los partidos» es el paso explícito que vuelca uno en el otro.

- Reparto automático **por serpiente** sobre la clasificación, para que el bombo
  no junte a los mejores de cada división en el mismo grupo.
- Al regenerar los partidos de grupos **se reajustan los `origen_*` del cuadro**,
  que apuntan por posición en el array: quitar cruces del medio los desplazaría.
- Alternativa sin ratón: un selector de grupo en cada ficha, y flechas con el
  club enfocado.

### 4. Archivo de temporadas

`historial_temporadas` es la única de las cuatro claves «no documentadas» que la
web **sí** lee: `palmares()` saca de ahí los campeones.

- **Archivar**: copia la temporada al palmarés sin tocar nada más.
- **Cerrar**: archiva, vuelca las estadísticas de cada jugador a su historial,
  pone la clasificación a cero, vacía el calendario y avanza de temporada.

Lo delicado del volcado: `app.js` calcula la carrera como
`goles_totales + goles`, y da por hecho que `goles_totales` es **exactamente**
la suma del historial. Al cerrar hay que sumar los goles de la temporada a
**las dos cosas** —al total y a la etapa abierta— y sólo entonces poner la
temporada a cero. Sumar a una sola desajustaría la carrera; no poner a cero la
contaría dos veces. Hay comprobación de que la carrera no cambia al cerrar.

---

## Fase 2 — Interacciones avanzadas

**Estado: núcleo completado.** Criterio de salida verificado (ver más abajo).

### Arrastrar y soltar: por qué no la API nativa ni SortableJS

`dnd.js` está escrito sobre **eventos de puntero**, no sobre la API nativa de
HTML5 (`draggable` + `dragstart`), porque ésta **no dispara nada en táctil** y
`CLAUDE.md` §5.2.4 pide soporte táctil completo para tablet. Los eventos de
puntero son uno solo para ratón, dedo y lápiz.

Tampoco SortableJS: haría falta por CDN —y el resto del sitio no depende de
ninguno— y aun así habría que escribir aparte toda la alternativa por teclado,
que es la parte que no puede faltar.

**Arrastrar nunca es la única vía** (SC 2.5.7). Cada pantalla ofrece además:

| Pantalla | Alternativa sin ratón |
|---|---|
| Alineación | Flechas con el jugador enfocado |
| Calendario | Casilla «J» de la tabla, y flechas |
| Grupos de Copa | Selector de grupo en cada ficha, y flechas |
| Noticias | Botón «Fijar arriba», y flechas |
| Cuadro de Copa | Los desplegables de la tabla de cruces |

### Alineación: el orden horizontal no se puede arrastrar, y se dice

La web coloca a cada jugador dentro de su línea **ordenando por dorsal**
(`sortSquad()` de `app.js`), no por su orden en el array. Comprobado sobre los
datos reales: el orden del array y el que pinta la web no coinciden.

Arrastrar de lado no cambiaría nada en la web. En vez de fingir que sí, la
interfaz lo explica y ofrece el botón que sí lo consigue: **renumerar la
línea**, repartiendo entre los mismos jugadores los dorsales que esa línea ya
tenía, sin inventar números ni pisar el de nadie de fuera.

De paso se detecta un descuadre real del archivo: **Monte Olimpo declara
formación 3-5-2 pero alinea 3 DEF, 2 MED y 5 DEL**. La ficha lo marca y ofrece
corregir la formación declarada.

### Traspasos

La mecánica vive en `core.traspasar()`, no en la vista: es la operación que más
fácil desajusta el archivo (toca plantilla, historial y estadísticas a la vez) y
tiene que poder comprobarse fuera del navegador.

Al traspasar, los goles y tarjetas de la temporada **se quedan apuntados en el
club donde se hicieron** y el jugador empieza de cero en el nuevo. Su cifra de
carrera no cambia. **El ranking de goleadores de la web tampoco se mueve**: ése
sale de los eventos de los partidos, no de la ficha.

Tres columnas y no dos, porque `agentes_libres` es un dato real (133 jugadores)
y sin él un traspaso sólo podría ser un intercambio directo.

### Cuadro de Copa arrastrable

También en `core.moverEnCuadro()`, por lo mismo: tiene dos efectos que no se
ven. Si el hueco de destino estaba ocupado hay **intercambio**, y colocar a mano
**rompe la vinculación** con la ronda previa (si no, la web seguiría pintando el
ganador de aquélla y el cambio sería invisible). Un hueco vinculado no contiene
un equipo, contiene una regla: ni se coge de él ni se suelta encima. Y se
rechaza entero cualquier movimiento que enfrentaría a un equipo consigo mismo,
en vez de dejar el cuadro a medias.

### Carga de imágenes por arrastre

Recorte cuadrado centrado, tope de 256px y **WebP con calidad 0,85**, con
recambio a PNG si el navegador no da WebP. El tamaño importa de verdad: esto
acaba dentro de `datos_oficiales.json`, que la web descarga entera en cada
visita. La interfaz **muestra los KB resultantes** y avisa cuando pasan de 120.

### Estadísticas (5.2.6)

Nueve bloques, todos con datos reales del archivo. Dos salen vacíos y explican
por qué:

> **Hallazgo:** `asistencias`, `amarillas` y `rojas` están **a cero en todo el
> archivo** — ni un jugador con valor, ni un solo evento que no sea `gol` en
> ningún `detalles`. Los rankings existen y se llenarán según se usen, porque el
> editor de eventos ya permite registrar asistencias y tarjetas; el estado vacío
> lo dice en lugar de aparentar que no hay datos por un fallo.

### Verificación del criterio de salida de la Fase 2

Ejecutado sobre `datos_oficiales.json` real, 0 errores de JavaScript,
0 problemas críticos de integridad y 0 desajustes de clasificación al terminar:

| Criterio | Resultado |
|---|---|
| Reorganizar alineación | Gus Gamer: DEF a MED |
| Mover partido de jornada | Alpino – Zanark Domain: jornada 1 a 2 |
| Reordenar cruce de Copa | Royal Academy intercambia con Instituto Otaku |
| Gestionar un traspaso | Pocus Sesame: Academia Plenilunio a Monte Olimpo, carrera intacta, 1 etapa abierta |
| Cuadro de mando con datos reales | 16 bloques · Zanark Domain 8 victorias seguidas · 220 goles, 2,65 por partido · local 42% / visitante 46% |

`node propuesta-web/gestor/test-core.js` — **20 comprobaciones**, incluidas las
nuevas de fases de Liga, grupos, formatos, cierre de temporada, traspasos y
cuadro de Copa.

### Aplazado de la Fase 2, con motivo

Se recorta por el final, como indica `CLAUDE.md` §6.4: el núcleo (5.2.1–5.2.3)
está completo y lo que queda son los bloques de extras.

| Funcionalidad | Motivo |
|---|---|
| **5.2.5 Visualización avanzada** (7 gráficos: evolución de posición, radar de jugador, árbol de traspasos, heatmap, modo TV, cuadro «cine», mapa de afinidades) | Bloque de extras, y el más grande de los que quedan. Necesita decidir si se dibuja a mano en SVG o se acepta una librería |
| **5.2.4 parcial**: atajos de teclado con panel de ayuda, tamaño de fuente ajustable, modo alto contraste | Extras de UX. El soporte táctil y la alternativa por teclado a cada arrastre —lo no negociable— sí están |
| **5.2.3 parcial**: kanban de jornada (#8), clasificación forzada por arrastre (#1), reordenar goleadores destacados (#5), maquetación de noticia por bloques (#7) | #1 rompe a propósito el cálculo automático; conviene hablarlo antes. #7 exige un esquema de bloques que `app.js` no sabe renderizar hoy |

---

## Fase 3 — Sorteos, generadores y pestañas nuevas

**Estado: núcleo (§5.3.1 y §5.3.2) completado.** Criterio de salida verificado.
El resto se declara aplazado más abajo, como pide `CLAUDE.md` §6.10.

### Generadores (§5.3.1)

Viven en `core.js` y **devuelven** los partidos, no los escriben. Por eso
«repetir el sorteo antes de confirmar» es sólo cambiar la semilla y volver a
pintar: en ningún momento se ha tocado el archivo.

El azar es **reproducible** (xorshift32 con semilla). `Math.random()` no
serviría: no hay forma de volver a un sorteo anterior.

**Calendario de liga.** Todos contra todos por el método del círculo, con
alternancia de campo. Comprobado por conteo, que es la única forma de ver que
un calendario está bien: cada pareja se enfrenta exactamente las vueltas
pedidas, nadie juega dos veces la misma jornada, el número de jornadas es el
esperado y el reparto casa/fuera queda equilibrado. Se prueba con número par,
impar (que introduce descanso), una vuelta, dos, y con los equipos reales.

**Sorteo de Copa.** Devuelve el cuadro entero con `origen_local` y
`origen_visitante` ya encadenados **por posición dentro de la propia lista**,
que es como los lee la web. La comprobación no mira que salgan cruces, sino
que la competición **se pueda jugar entera**: se simula ganando siempre el
local y se exige que no quede ningún hueco sin resolver, que nadie se enfrente
a sí mismo y que salga un único campeón. Además, todo índice de origen apunta
hacia atrás: si apuntara hacia delante, la cascada de la web no podría
resolverse en un solo recorrido.

> **Dos fallos reales que encontró la verificación en el navegador**, no los
> tests, y que se han corregido:
> 1. **La rivalidad sólo se esquivaba en la ronda previa.** Con 20 equipos,
>    Alpino y Academia Plenilunio están bien sembrados, se libran de la previa
>    y se cruzaban igualmente en la primera ronda del cuadro. Ahora el
>    emparejamiento por extremos de la primera ronda también las esquiva.
> 2. **Los ganadores de la previa se emparejaban entre sí.** Entraban al cuadro
>    por delante de los que tenían pase, así que jugaban unos contra otros y la
>    previa no servía de nada: su sentido es que se crucen con los cabezas de
>    serie. Ahora entran por detrás, como peor sembrados.
>
> Los dos casos están cubiertos por comprobaciones nuevas para que no vuelvan.

**Play-off de la Superliga.** Genera los cruces con la misma estructura que
`renderPlayoff()` dibuja: 5.º–6.º, ganador contra el 4.º, y las semifinales del
1.º y del 2.º–3.º. Se crean a partir de la jornada siguiente a la última del
calendario, porque sin jornada la web no los mostraría en Resultados.

**Ayudas de jornada.** Partido de la semana, MVP ponderado (3 por gol, 2 por
asistencia), nombre temático según la afinidad que más goles marcó, y derbis
por ciudad compartida. Son sugerencias para copiar: **no se guarda nada**.
Sobre los derbis se dice la verdad: la web sólo etiqueta como derbi lo que
tiene escrito en su lista `RIVALIDADES`, que hoy es una sola pareja.

### Pestañas nuevas (§5.3.2)

Cuatro de las nueve estaban ya cubiertas: **Traspasos** y **Palmarés** (dentro
de Temporadas) se hicieron antes, y **Patrocinadores/Medios** vive en
Configuración.

**Sanciones.** Se calculan desde los eventos de los partidos, no desde un campo
nuevo, para que no exista una segunda verdad que mantener a mano. Las reglas
(amarillas por ciclo, partidos por ciclo, partidos por roja) se guardan en
`config.formatos.SANCIONES` en vez de inventar otra clave de primer nivel.

> Hoy la pantalla sale vacía y lo explica: **no hay ni una tarjeta registrada
> en todo el archivo**. Se llenará según se usen los tipos «Amarilla» y «Roja»
> del editor de eventos.

**Papelera.** El esquema no tiene borrado lógico, así que lo único recuperable
son los clubes archivados y los jugadores sin club. Se juntan aquí para no
buscarlos por tres pantallas, y se recuerda que para deshacer un borrado ya
guardado están las copias de seguridad de Datos.

### Redes sociales (§5.3.3, parcial)

El lienzo, el marco, las pastillas y el ajuste de texto están **portados de
`_fuente/app.js`**, que ya trae un generador de tarjetas funcionando.
Reimplementarlos habría producido dos estéticas distintas para lo mismo.

Tres plantillas (resultado, clasificación, MVP) en tres formatos (16:9, 1:1,
9:16). Se hereda también el apaño del proxy de imágenes: el CDN de los retratos
no manda `Access-Control-Allow-Origin`, así que cargarlos con `crossOrigin`
falla y sin él contaminan el lienzo. Si el proxy tampoco puede, la exportación
lo dice y sugiere incrustar la imagen arrastrándola, en vez de dejar un error
mudo.

### Simulación de jornada (§5.3.5, parcial)

Resultados hipotéticos sobre los partidos pendientes, con la clasificación
resultante y las flechas de puesto al lado. Se calcula sobre **una copia** del
archivo: la clasificación real no se toca hasta pulsar «Aplicar». Al aplicar se
avisa de que los goleadores **no** se rellenan, porque la web los enlaza por
nombre y no se pueden inventar.

### Verificación del criterio de salida de la Fase 3

Sobre `datos_oficiales.json` real, 0 errores de JavaScript, 0 críticos de
integridad y 0 desajustes de clasificación al terminar. Las 15 secciones pintan.

| Criterio | Resultado |
|---|---|
| Cuadro de Copa por sorteo respetando rivalidades | 20 inscritos, 19 cruces, 4 previas · rivalidad esquivada en previa **y** en primera ronda · cada ganador de previa contra un sembrado |
| Generar calendario de una división | 129 partidos en la previa, archivo intacto hasta aplicar |
| Exportar plantilla de resultado para redes | PNG 1200×675 dibujado, listo para descargar |
| Simular una jornada sin comprometerla | 6 partidos simulables, tabla resultante de 11 filas, clasificación real intacta |
| El detector señala un error introducido a propósito | 0 críticos → 1: «Liga #1: el equipo local "Equipo Que No Existe" no existe» |

`node propuesta-web/gestor/test-core.js` — **22 comprobaciones**.

### Fase 3: lo que queda, y por qué

`CLAUDE.md` §5.3 son ocho bloques con unas sesenta funcionalidades. Es
inabarcable de una vez y lo digo explícitamente, como pide §6.10. Se ha
entregado el núcleo (§5.3.1 y §5.3.2) más las dos piezas que exige el criterio
de salida. Queda:

| Bloque | Qué falta | Por qué se aplaza |
|---|---|---|
| **§5.3.2** | Árbitros/Staff, Calendario editorial, Auditoría, Comparador de temporadas | Las tres primeras necesitan claves nuevas en el esquema que **la web no leería**: conviene decidir antes si merecen entrar en el archivo o vivir aparte. El comparador es acumulativo: hoy sólo hay una temporada archivada |
| **§5.3.3** | Previa del partido, cartel de sorteo, ficha de fichaje, hilo de jornada, banco de plantillas, cumpleaños | El motor de canvas ya está montado y probado: añadir plantillas es repetir el patrón. El de cumpleaños además exige una fecha de nacimiento que el esquema no tiene |
| **§5.3.4** | Narrativa y gamificación (storylines, logros, línea de tiempo, apodos, «¿y si…?», frase del partido) | Bloque entero de extras. El «¿y si…?» se apoyaría en la simulación que ya existe |
| **§5.3.5** | Alerta de descuadre entre goles de ficha y goles de `detalles` | Se detecta ya de otra forma (la pastilla ámbar de goleadores/marcador en Partidos), pero falta el informe global |
| **§5.3.6** | Diff entre snapshots, changelog en lenguaje natural, roles, modo solo lectura, notas internas, checklist | El historial de guardados y la restauración **sí** están, desde la Fase 1 |
| **§5.3.7** | Redactor asistido, sugeridor de titulares | El corrector de nombres parecidos existía en el prototipo y se puede portar |
| **§5.3.8** | Widgets configurables, favoritos, vista compacta, multi-idioma, modo temporada nueva guiado | El modo «temporada nueva» está cubierto en la práctica por **Temporadas → Cerrar temporada** |

**Qué propongo entregar primero si seguimos:** el informe de descuadres de
§5.3.5 y las plantillas de redes que faltan de §5.3.3 —las dos se apoyan en
motores ya construidos y probados—, y después el comparador de temporadas, que
gana valor en cuanto haya una segunda temporada archivada.

---

## Fase 3 — segunda tanda

Las tres piezas propuestas al cerrar la tanda anterior.

### Un fallo mío que apareció al empezar

Al medir los descuadres para construir el informe, el archivo parecía tener
**53 jugadores con los goles de ficha descuadrados**. No era cierto: era un
fallo de `core.js`.

`statsJugadoresCalculadas()` indexaba por la cadena `club<separador>nombre`, y
el separador que quedó escrito **era un byte NUL en vez de un espacio**. Quien
consultara con un espacio recibía cero para todos los jugadores, **sin ningún
error**: la función parecía funcionar y devolvía silencio.

Arreglado de raíz, no cambiando el separador: ahora se indexa **por el objeto
del jugador** con un `Map`, así que no hay ninguna clave de texto que acertar.
Se añade `eventosDe(mapa, jugador)`, que devuelve ceros para quien no aparece
en ningún evento en vez de `undefined`.

La función no se usaba en ninguna pantalla todavía, así que el fallo nunca
llegó a verse; habría llegado justo con este informe. Queda cubierto por una
comprobación que contrasta sus totales contra `calcScorers()` —la cuenta que
hace la web— y que además **rechaza cualquier carácter de control invisible en
`core.js`**, que es lo que lo causó.

**La cifra real, con la función arreglada: 53 fichas coinciden exactamente y
sólo hay 1 descuadre** (Mike, del Royal Academy: la ficha dice 0 goles y los
partidos le dan 1).

### Informe de goleadores frente a partidos (§5.3.5)

En **Datos**. Separa dos cosas que se confunden:

- **Cobertura**: cuántos goles del marcador tienen goleador anotado.
- **Descuadre**: cuándo la ficha de un jugador y los eventos dicen cosas
  distintas.

> **Hallazgo sobre el archivo real: sólo el 57% de los goles tiene goleador
> anotado.** 126 de 220. Y **31 partidos finalizados con goles no tienen ni un
> solo evento**: un 3-0 que no dice quién marcó. La web los muestra bien en el
> marcador, pero su cronología sale vacía y esos 94 goles no cuentan para el
> ranking de goleadores.

El botón que iguala las fichas a los eventos existe, pero **avisa de que ahora
sería contraproducente**: con 94 goles sin anotar, aplicarlo dejaría a esos
jugadores con menos goles de los que marcaron. Tiene sentido cuando los eventos
estén completos, no antes. Y sólo toca `goles`: asistencias y tarjetas se dejan
como están, porque no tienen ni un evento en todo el archivo y ponerlas a cero
borraría datos que podrían estar bien puestos a mano.

### Plantillas de redes que faltaban (§5.3.3)

De tres a **seis**, todas sobre el motor de canvas ya probado: resultado,
**previa del partido** (forma reciente en cinco puntos y cara a cara),
clasificación, **cartel de sorteo de Copa**, MVP y **ficha de fichaje**.

Más el **hilo de jornada en texto**, redactado desde los resultados: marcadores
con sus goleadores, pendientes, máximo goleador de la jornada y líder. No
inventa nada que no esté en el archivo. El botón de copiar usa `execCommand`
con `navigator.clipboard` de respaldo, porque el primero sigue funcionando
abriendo el gestor por `file://`, que aquí es un caso real.

No se hace la plantilla de cumpleaños: **el esquema no tiene fecha de
nacimiento** y no voy a inventar un campo que la web no leería.

### Comparador de temporadas (§5.3.2)

En **Temporadas**. La temporada en curso entra como una opción más, para no
tener que archivarla sólo para compararla.

Compara **clubes** (puntos, goles y partidos, con el delta en verde o rojo) o
**jugadores** (goles por temporada, marcando a quien cambió de club). Se cruza
por nombre y no por id, porque un club renombrado se busca por como se llamaba;
los que sólo aparecen en una de las dos se listan aparte.

**Aviso automático cuando las temporadas no son comparables:** con Temporada 1
cerrada (12 partidos de media) contra la 3 en curso (7), la segunda saldrá peor
en todo por haber jugado menos. La interfaz lo dice antes de que alguien saque
conclusiones, y la tabla muestra la columna PJ de las dos.

### Verificación

`node propuesta-web/gestor/test-core.js` — **23 comprobaciones**. Las 15
secciones pintan, 0 errores de JavaScript, 0 críticos de integridad. Las seis
plantillas de redes dibujan sobre el lienzo y el comparador produce 34 filas de
clubes y 30 de jugadores sobre los datos reales.

### Qué queda de la Fase 3 tras esta tanda

| Bloque | Qué falta |
|---|---|
| **§5.3.2** | Árbitros/Staff, Calendario editorial, Auditoría — las tres piden claves nuevas que la web no leería; conviene decidir antes si entran en el archivo o viven aparte |
| **§5.3.3** | Banco de plantillas favoritas; cumpleaños (bloqueado por el esquema) |
| **§5.3.4** | Narrativa y gamificación, entero |
| **§5.3.6** | Diff entre snapshots, changelog en lenguaje natural, roles, modo solo lectura, notas internas, checklist |
| **§5.3.7** | Redactor asistido y sugeridor de titulares; el corrector de nombres parecidos se puede portar del prototipo |
| **§5.3.8** | Widgets configurables, favoritos, vista compacta, multi-idioma |

---

## Corrector de consistencia de nombres (§5.3.7)

### Por qué era el punto frágil

Los eventos de un partido guardan el nombre del jugador **como texto**, y la
web lo resuelve con `findPlayer()`, que acepta el nombre completo, el primer
nombre **o un prefijo**. Una errata no da error: engancha el gol a otro
jugador, o al de otro club, y no lo nota nadie.

El prototipo `gestor.html` tenía un detector de duplicados, pero sólo buscaba
**fichas de jugador parecidas entre sí**. No miraba lo que de verdad puede
romperse, que es el enlace entre un evento y una ficha.

### Las cinco formas de que falle

| Qué busca | En el archivo real |
|---|---|
| Evento atribuido a un jugador **que no está en la plantilla del club que anotó** | **1** |
| Nombre de evento que **no casa con ningún jugador** | 0 |
| Nombre que **sólo casa por prefijo o primer nombre** | 0 |
| Nombre que **llevan dos jugadores** (`findPlayer` devuelve el primero) | 0 |
| Fichas con **nombre casi igual**, posibles duplicados | 2 |

Más una sexta que apareció al mirar: **3 jugadores sin nombre**, fichas que no
se pueden enlazar con ningún gol.

> **El caso real, con su causa:** el gol `gol:Mike:39` de un Raimon 1-3 Cala
> Pirata se cuelga del «Mike» del **Royal Academy**. No es una errata:
> **Raimon está archivado y se quedó con la plantilla vacía**, pero conserva
> sus 10 partidos. Como no hay ningún Mike en Raimon, `findPlayer()` sigue
> buscando por el resto de clubes y encuentra otro. La web enseña ese gol con
> la foto y el enlace del jugador equivocado. La pantalla lo dice con esas
> palabras, incluida la causa.

Las otras dos parejas parecidas son «Soldado de Terracota 1» / «Soldado de
Terracota 4» —dos jugadores distintos de verdad— y «Bump Trungus» / «Lump
Trungus», que puede ser cualquiera de las dos cosas. Por eso **el gestor no
fusiona fichas por su cuenta**: juntarlas mal perdería el historial de una.
Enseña las dos con sus datos y decide quien sabe.

### Unificar

Reescribe un nombre en **todos** los eventos que lo lleven y regenera los
textos de goleadores. No toca ninguna ficha. El desplegable de candidatos
ordena **primero los jugadores del club que anotó**, que es donde debería
estar el que se busca. Si el nombre nuevo tampoco casa con nadie, avisa antes
de cambiar un problema por otro.

También existe `renombrarJugador()`, que cambia el nombre de la ficha **y
arrastra sus eventos**: sin eso, renombrar a un jugador le desengancharía
todos los goles de golpe.

### Dos fallos míos corregidos por el camino

**1. `distancia()` no recortaba espacios.** Aquí se comparan identidades, no
bytes: «Mike» y «Mike » no son dos personas. Lo destapó una comprobación.

**2. Metí el análisis en el camino caliente, y costaba 127 ms.**
`contadores()` corre después de **cada** edición, y con `validarIntegridad`
(61 ms) más `analizarNombres` (127 ms) metía **232 ms entre pulsar una tecla y
ver el resultado**. Dos arreglos:

- **El algoritmo:** llamaba a `findPlayer()` por cada evento, y `findPlayer()`
  normaliza los 791 nombres de plantilla en cada llamada. Ahora se construye
  **un índice de nombres ya normalizados una sola vez** por análisis, en el
  mismo orden en que los recorre `findPlayer()` y replicando sus tres
  condiciones, más una caché por nombre repetido. **127 ms → 6 ms.**
- **El sitio:** los contadores se parten en dos. Lo barato se actualiza al
  instante; la validación de integridad y el análisis de nombres se aplazan
  400 ms y sólo corren cuando se ha dejado de escribir. **`contadores()`:
  232 ms → 0 ms.**

La comparación por parejas —lo único de verdad caro, 791 × 791— no entra en
ningún contador: se pide con un botón desde la propia pantalla.

### Verificación

`node propuesta-web/gestor/test-core.js` — **25 comprobaciones**. Las nuevas:

- `distancia` y `parecido`: tildes, espacios, cadenas vacías.
- Sobre el archivo real: 0 huérfanos, 0 difusos, 0 ambiguos, **1** atribución
  cruzada, y que su causa marcada sea `plantillaVacia`.
- Los detecta cuando se introducen a propósito: un nombre inventado
  (huérfano), «Raleigh» a secas (difuso, resuelve a Raleigh Greenstreet), y un
  segundo jugador con un nombre ya usado (ambiguo).
- **Unificar no pierde ni un gol:** se cuenta el total antes y después,
  se comprueba que el nombre viejo desaparece también de los textos derivados,
  y que renombrar a un jugador mantiene sus goles enganchados a su ficha.

Ciclo completo probado en el navegador: se mete una errata que desengancha
**16 goles** del máximo goleador, la pantalla la detecta como huérfana, el
desplegable propone «Raleigh Greenstreet» como primer candidato, y tras
unificar vuelven los 16 goles a su ficha. El total de goles del archivo no se
mueve de 126 en ningún momento.
