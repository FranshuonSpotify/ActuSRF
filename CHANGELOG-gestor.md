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
