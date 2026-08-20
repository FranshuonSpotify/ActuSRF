# CLAUDE.md — Dashboard de gestión Superliga Frontier

Este archivo es la fuente de verdad del proyecto para Claude Code. Léelo por completo antes de escribir cualquier línea de código, y vuelve a él antes de empezar cada fase.

---

## 1. CONTEXTO DEL PROYECTO

Superliga Frontier es una liga de fútbol ficticia con una web pública ya construida en `propuesta-web/`. Tu tarea es construir un **dashboard de gestión** en `propuesta-web/gestor/` que lea y escriba directamente el archivo de datos real `propuesta-web/datos_oficiales.json`, consumido por `propuesta-web/_fuente/app.js` para renderizar la web pública.

No es una demo. `datos_oficiales.json` tiene datos de producción reales (equipos con plantillas completas, ~9 jornadas jugadas, cuadro de Copa en curso, noticias publicadas). Cualquier cambio de esquema debe ser retrocompatible con `app.js`, que **no se debe modificar** salvo necesidad estricta y documentada en `CHANGELOG-gestor.md`.

## 2. RESTRICCIONES NO NEGOCIABLES

1. **Sin backend.** Página HTML estática más, igual que el resto de `propuesta-web/`.
2. **Persistencia con File System Access API** (`showOpenFilePicker`, `FileSystemFileHandle.createWritable()`) como método principal. Fallback con `<input type="file">` + `Blob`/`<a download>` para navegadores sin soporte.
3. **Mismo Design System v3** de `propuesta-web/_fuente/styles.css`: fondo negro real `#000000`, 95% monocromo, acento naranja `--accent:#FF5100` usado con cuentagotas, tipografías Inter/Teko/Fraunces/JetBrains Mono, radios `--r-sm/--r/--r-lg/--r-xl`, easings `--ease/--ease-io`, componentes `.card/.btn/.badge/.chip` reutilizados literalmente. Los componentes que falten (inputs, selects, date/color pickers, drag handles) se crean siguiendo ese mismo lenguaje.
4. **Vanilla JS**, sin frameworks de build, siguiendo el estilo de `app.js` (IIFE, `document.getElementById`, delegación de eventos, plantillas de string). Librerías ligeras sin dependencias pesadas están permitidas para drag-and-drop (ej. SortableJS por CDN) si aportan valor real — evalúa antes de añadir cualquier dependencia.
5. **Fórmula de clasificación intocable**: puntos → diferencia de goles (gf-gc) → goles a favor → goles en contra → victorias → empates → derrotas → alfabético. Ya implementada en `orderStandings()` de `app.js`; reutilízala o repórtala idéntica.
6. **Revisar primero `gestor (1).html`** (raíz del proyecto, fuera de `propuesta-web/`) antes de escribir código nuevo — puede ser un prototipo parcialmente reutilizable.

## 3. ESQUEMA REAL DE DATOS (`datos_oficiales.json`)

Nivel superior: `config`, `equipos[]`, `partidos_liga[]`, `partidos_ascenso[]`, `partidos_copa[]`, `historial[]`, `noticias[]`.

- **`config`**: `nombre_liga`, `temporada`, `jornada_actual`, `ticker_superliga[]`, `ticker_ascenso[]`, `ticker_copa[]`, `medios.twitter`.
- **`equipos[]`**: `id`, `nombre`, `escudo`, `division` (`SUPERLIGA`|`ASCENSO`), `ciudad`, `estadio`, `entrenador`, `gerente`, `formacion`, `pj/g/e/p/gf/gc/pts`, `color1`/`color2` (hex), `abreviatura`, `archivado` (bool opcional), `jugadores[]`.
- **`jugadores[]`**: `nombre`, `dorsal`, `posicion` (`POR`|`DEF`|`MED`|`DEL`), `titular` (bool), `goles`/`asistencias`/`amarillas`/`rojas` (temporada), `foto`, `afinidad` (`Fuego`|`Montaña`|`Bosque`|`Aire`|`Neutro`), `goles_totales`/`asistencias_totales`/`amarillas_totales`/`rojas_totales` (histórico), `historial[]` (`{equipo, equipo_id, division, temporada, fecha, goles, asistencias, amarillas, rojas, pj, temporada_inicio, temporada_fin, abierto}`), `supertecnicas[]` opcional (`{nombre, descripcion, afinidad, tipo, especial}`).
- ⚠️ **Inconsistencia real a normalizar**: algunos jugadores usan `amarillas`/`rojas`, otros `tarjetasAmarillas`/`tarjetasRojas` para el mismo dato. Normalizar al guardar sin perder el dato si solo existe en un formato.
- **`partidos_liga[]`/`partidos_ascenso[]`**: `jornada`, `fecha`, `estado` (`FINALIZADO`|`PENDIENTE`), `local`, `visitante`, `goles_l`/`goles_v` (alias `golesl`/`golesv`, mantener sincronizados), `detalles` (`" / gol:Nombre:minuto, gol:Nombre:minuto"`, antes de `/` = local, después = visitante), `goleadores_texto`, `goleadores_local_texto`, `goleadores_visitante_texto` (derivados, regenerables).
- **`partidos_copa[]`**: igual, más `fase` (`RONDA 1 (PREVIA)`|`RONDA 2`|`CUARTOS DE FINAL`|`SEMIFINALES`|`FINAL`|`FASE DE GRUPOS`), `grupo`, `origen_local`/`origen_visitante` (índice al partido anterior cuyo ganador alimenta este cruce, o `null`).
- **`noticias[]`**: `tag`, `color`, `titulo`, `resumen`, `cuerpo`, `imagen`, `video`, `autor`, `fecha`.

## 4. FUNCIONALIDADES BASE A PRESERVAR (no romper nada de esto)

Clasificación Superliga/Ascenso · Calendario y resultados por jornada · Bracket de Copa con resolución en cascada de `origen_local`/`origen_visitante` y fase de grupos · Fichas de equipo (plantilla, titulares/suplentes, formación en campo) · Fichas de jugador (temporada + histórico) · Play-off/Play-in Superliga (top 6) y ascenso directo Ascenso (top 3) · Noticias/prensa · Goleadores extraídos de `detalles`.

---

## 5. PLAN DE IMPLEMENTACIÓN — 3 FASES CON TODAS LAS FUNCIONALIDADES

### FASE 1 — Núcleo, CRUD completo y calidad de datos base

**5.1.1 Infraestructura de archivo**
- `fileIO.js`: `abrirArchivo()` (File System Access API + fallback), `guardarArchivo(data)` (write directo + fallback descarga), detección de soporte, dirty-state visible permanentemente, atajo `Ctrl/Cmd+S`.
- Copia de seguridad automática en `localStorage` antes de cada guardado (snapshot con timestamp, rotación de últimas N versiones).
- Validación de esquema al cargar (claves de primer nivel esperadas).
- Bloqueo temporal de edición durante el guardado, para evitar clics duplicados (#86 del catálogo).
- Registro local de errores de guardado con reintento manual (#88).

**5.1.2 Gestión de equipos**
- Listado filtrable por división/estado + buscador.
- Formulario completo con color picker de `color1`/`color2` (preview del gradiente en vivo).
- CRUD de plantilla completo (todos los campos, incluyendo `historial` y `supertecnicas`).
- Normalización automática de campos duplicados (`amarillas`/`tarjetasAmarillas`, etc.) al guardar.
- Recalculo en vivo de `pj/g/e/p/gf/gc/pts` (solo lectura) para detectar desajustes.
- Archivar/desarchivar equipo (`archivado:true/false`).
- Plantilla rápida de "nuevo equipo" con valores por defecto sensatos (#48).

**5.1.3 Gestión de partidos**
- Calendario navegable por competición/jornada/fase+grupo.
- Formulario de partido con editor de goleadores estructurado (selector jugador+minuto, no texto libre) generando `detalles` en el formato exacto de `app.js`.
- Recalculo en cascada de estadísticas de equipo al guardar un resultado.
- Selector de partido de ronda anterior para `origen_local`/`origen_visitante` en Copa (no edición manual de índice).

**5.1.4 Noticias y configuración**
- CRUD completo de noticias con preview de imagen.
- Editor de `config` (temporada, jornada actual, tickers, redes).

**5.1.5 Calidad de datos base**
- Validación de integridad referencial completa antes de cada guardado (`equipo_id`, `origen_local`/`origen_visitante`, nombres de equipo en partidos) — bloquea guardado si hay error crítico, avisa si es menor (#87).
- Validador de enlaces de imagen rotos, comprobación en segundo plano (#47).
- Importador CSV/Excel para carga masiva de plantillas (#49).
- Buscador global con resultados agrupados por tipo, atajo `Cmd/Ctrl+K` (#51).

**5.1.6 Layout y sistema de formularios**
- Navegación entre secciones (Equipos, Partidos, Copa, Noticias, Configuración) con tokens del Design System v3.
- Sistema de formularios propio (inputs, selects, textareas, color pickers) coherente con la estética existente.

**Criterio de salida:** ciclo completo (crear equipo con plantilla → jugar jornada con goleadores → publicar noticia → guardar) funciona de punta a punta; el resultado se renderiza correctamente en `propuesta-web/index.html`.

---

### FASE 2 — Interacciones avanzadas: drag-and-drop, visualización y estadísticas

**5.2.1 Drag-and-drop de alineación**
- Campo táctico con jugadores arrastrables (reutilizando estilo `.pitch`/`.pp` de `app.js`), titulares↔suplentes por arrastre, reordenamiento dentro de línea.

**5.2.2 Drag-and-drop de calendario y contenido**
- Calendario completo con partidos arrastrables entre jornadas.
- Reordenar noticias por arrastre / fijar ("pin") una noticia arriba del listado (#3).
- Panel kanban de tareas de jornada (Pendiente/En revisión/Publicado), arrastrable (#8).
- Carga de imágenes por arrastre (drag-over con feedback) en todos los campos de foto/escudo/imagen, con recorte cuadrado automático (#4).

**5.2.3 Drag-and-drop de Copa y traspasos**
- Bracket de Copa interactivo: mover equipos entre cruces/rondas por arrastre, actualizando `local`/`visitante` u `origen_local`/`origen_visitante`.
- Arrastrar equipos entre grupos de la fase de grupos tras el sorteo, para ajustes manuales (#6).
- Arrastrar jugadores entre equipos para gestionar traspasos, generando automáticamente la entrada de `historial` (#2).
- Edición forzada de clasificación arrastrando filas, con aviso de que rompe el cálculo automático hasta corregir el origen (#1).
- Reordenar "jugador del partido"/goleadores destacados por arrastre en la ficha de un encuentro (#5).
- Maquetación de noticia tipo "artículo largo" arrastrando bloques de contenido (foto/texto/cita) (#7).

**5.2.4 UX y accesibilidad de drag-and-drop**
- Feedback visual consistente: placeholder de destino, elemento fantasma semitransparente, animación con `--ease`/`--t1`/`--t2`.
- Alternativa por teclado/botón para cada acción de drag-and-drop (mover arriba/abajo, mover a jornada X vía selector).
- Soporte táctil completo para tablet (#78).
- Atajos de teclado completos de navegación, con panel de ayuda (`?`) (#80).
- Tamaño de fuente ajustable en el dashboard (#81).
- Modo alto contraste adicional (#79).

**5.2.5 Visualización avanzada**
- Gráfico de evolución de posición en la tabla jornada a jornada (#29).
- Gráfico de radar de jugador (goles, asistencias, tarjetas, afinidad vs. media de su posición) (#65).
- Vista de "árbol genealógico de traspasos" entre clubes/temporadas (#66).
- Heatmap tipo GitHub de densidad de partidos/goles por jornada (#67).
- Modo presentación/TV a pantalla completa con rotación automática de clasificación/resultados/noticias (#68).
- Vista de bracket de Copa en modo "cine" (zoom y pan) para cuadros grandes (#69).
- Mapa de calor de afinidades elementales dominantes por división/entre goleadores (#28).

**5.2.6 Estadísticas**
- Cuadro de mando general (KPIs de temporada: goles totales, media por partido, jornada con más goles, equipo más/menos goleado) (#25).
- Ranking de asistencias (dato ya existente, sin vista hoy) (#26).
- Ranking de tarjetas por jugador y equipo (#27).
- Comparador cabeza a cabeza entre dos equipos (enfrentamientos directos, rachas) (#30, #62).
- Estadística de efectividad local vs. visitante (#31).
- Detección automática de racha activa más larga (#32).
- Estadísticas por posición (POR/DEF/MED/DEL) (#34).
- "Jugador revelación" respecto a temporadas anteriores (#35).
- Proyección simple de puntos finales de temporada (con aviso de que es estimación) (#33).

**Criterio de salida:** reorganizar alineación completa, mover partido de jornada, reordenar cruce de Copa y gestionar un traspaso, todo por arrastre; el cuadro de mando de estadísticas refleja datos reales del JSON.

---

### FASE 3 — Sorteos, generadores, contenido para redes, pestañas nuevas y todo lo demás

**5.3.1 Sorteos y generadores automáticos**
- Generador de sorteos de Copa (selección de equipos, formato grupos/eliminatoria, animación de sorteo con glow naranja y tipografía mono), con reglas configurables (evitar rivalidades en Ronda 1, equilibrar equipos por división), generación automática de `partidos_copa[]` con `origen_local`/`origen_visitante` encadenados, opción de repetir sorteo antes de confirmar.
- Sorteo de bombos con siembra por posición en clasificación de temporada anterior (#11).
- Generador de calendario completo de Liga/Ascenso (todos contra todos, ida/vuelta configurable, reparto equilibrado local/visitante) (#13, sección B original).
- Generador de calendario de playoffs/play-in a partir de la clasificación final (#13).
- Sorteo de MVP/jugador del partido ponderado por goles+asistencias, con override manual (#9).
- Generador automático de derbis/rivalidades sugeridos por proximidad de `ciudad` (#10).
- Generador de nombres de jornada temática según afinidades predominantes (#12).
- Sorteo de "partido de la semana" para portada (#14).
- Generador de fixture de amistosos/pretemporada entre divisiones (#15).

**5.3.2 Nuevas pestañas**
- **Traspasos**: mercado de fichajes con línea de tiempo por temporada.
- **Palmarés**: campeones históricos por temporada/competición, generado desde finales `FINALIZADO`.
- **Sanciones**: tarjetas acumuladas y suspensiones automáticas (ej. 5 amarillas = 1 partido), aviso al alinear jugador sancionado.
- **Árbitros/Staff**: catálogo y asignación a partidos.
- **Patrocinadores/Medios**: gestión de `config.medios` y futuros patrocinadores.
- **Calendario editorial**: planificación de publicación de noticias, independiente de la fecha real.
- **Papelera**: elementos archivados/eliminados recuperables en un solo sitio.
- **Auditoría**: registro cronológico de cambios (si se añade identificación de sesión/usuario).
- **Comparador de temporadas**: evolución de equipo/jugador entre temporadas.

**5.3.3 Generador de contenido para redes sociales**
- Plantilla "resultado del partido" exportable a PNG (marcador + escudos + goleadores, Design System v3).
- Plantilla "clasificación actualizada" en formato vertical (historia).
- Plantilla "MVP de la jornada" (foto + club + estadística destacada).
- Plantilla "previa del partido" (forma reciente + historial de enfrentamientos).
- Plantilla "cartel de sorteo de Copa".
- Plantilla "ficha de fichaje" al registrar traspaso.
- Generador de "hilo de jornada" en texto, redactado automáticamente a partir de los resultados.
- Exportación en varios formatos de aspecto (1:1, 9:16, 16:9).
- Banco de plantillas reutilizable (combinaciones de color/plantilla favoritas).
- Plantilla de cumpleaños/aniversario de club o jugador (si se añade fecha al esquema).

**5.3.4 Narrativa y gamificación**
- Generador de "storylines" a partir de rachas detectadas (equipo revelación, caída en desgracia, derbi caliente).
- Sistema de "logros" desbloqueables automáticamente (primer hat-trick, portería menos goleada, remontada histórica).
- Línea de tiempo visual de eventos clave de la temporada.
- Generador de apodos de equipo sugeridos según estilo de juego.
- Simulador "¿Y si...?" recalculando clasificación hipotética sobre un partido ya finalizado.
- Generador automático de "frase del partido" a partir de plantillas narrativas rellenadas con datos reales.

**5.3.5 Modo simulación y detección de inconsistencias**
- Simulación de jornada con resultados hipotéticos sobre partidos pendientes, sin guardar hasta "aplicar".
- Detector de inconsistencias al abrir/guardar: `equipo_id` de `historial` inexistente, equipo referenciado inexistente/archivado, jornada incompleta, `origen_local`/`origen_visitante` inválido — cada aviso con enlace directo al elemento afectado.
- Alerta de descuadre entre goles del jugador y goles registrados en `detalles` de sus partidos.

**5.3.6 Versionado y colaboración**
- Historial de guardados con restauración de versión anterior (sobre los snapshots de la Fase 1).
- Comparador visual (diff) entre dos snapshots del JSON.
- Changelog en lenguaje natural de una sesión de edición.
- Modo "sesión de edición en vivo" con lista de cambios pendientes revisable antes de guardar.
- Plantillas de configuración exportables/importables (reglas de sorteo, paletas de redes sociales).
- Roles de acceso simulados en cliente (editor de noticias vs. gestor completo).
- Modo solo lectura para compartir revisión sin riesgo de guardado accidental.
- Notas internas por equipo/jugador/partido (no visibles en la web pública).
- Checklist de publicación de jornada (resultados cargados / goleadores revisados / noticia publicada).

**5.3.7 Asistencia inteligente (sin backend de IA externo obligatorio)**
- Redactor asistido de noticias a partir de los datos del partido (borrador inicial editable).
- Sugeridor de titulares alternativos a partir del cuerpo ya escrito.
- Corrector de consistencia de nombres (typos entre distintos partidos) con sugerencia de unificación.

**5.3.8 Personalización del dashboard**
- Dashboard de inicio personalizable (elegir widgets: pendientes de hoy, últimas noticias, clasificación, próximos partidos).
- Favoritos de equipos/jugadores para acceso rápido.
- Vista compacta vs. detallada conmutable en listados largos.
- Multi-idioma del dashboard reutilizando `i18n.js`/`dict.js` existentes.
- Modo "temporada nueva" guiado: archiva temporada actual, resetea estadísticas de equipos activos, prepara calendario vacío.
- Panel de "pendientes de hoy": partidos de la jornada actual sin resultado, ordenados por urgencia.
- Resumen de "qué cambió desde la última vez" al recargar, comparando contra el último snapshot local.
- Modo "confirmar antes de guardar cambios masivos" con resumen de lo que se aplicará.

**Criterio de salida:** se puede generar desde cero un cuadro de Copa por sorteo respetando rivalidades, generar calendario de una división nueva, exportar una plantilla de resultado para redes, simular una jornada sin comprometerla, y el detector de inconsistencias señala correctamente un error introducido a propósito.

---

## 6. INSTRUCCIONES DE TRABAJO

1. Lee y resume `gestor (1).html` antes de escribir código nuevo; decide explícitamente qué se reutiliza.
2. Lee `propuesta-web/_fuente/app.js` completo antes de reimplementar cualquier lógica (clasificación, bracket, goleadores) para garantizar paridad exacta.
3. Trabaja fase por fase, en orden. No empieces la Fase 2 sin que el criterio de salida de la Fase 1 esté cumplido y verificado manualmente contra el JSON real. Igual entre Fase 2 y Fase 3.
4. Dentro de cada fase, prioriza siempre el bloque de "núcleo" (5.1.1–5.1.4, 5.2.1–5.2.2, 5.3.1–5.3.2) antes que los bloques de extras de esa misma fase — si hay que recortar alcance, recorta primero los bloques finales de cada fase, nunca el núcleo.
5. Cada fase debe dejar el dashboard en un estado funcional y desplegable, no a medio construir.
6. Documenta en `CHANGELOG-gestor.md` cada decisión de normalización de datos (ej. campos duplicados) y cada funcionalidad de las listadas que se decida omitir o posponer, con el motivo.
7. No modifiques `propuesta-web/_fuente/app.js`, `styles.css` ni el resto de la web pública salvo necesidad imprescindible, justificada explícitamente antes de aplicarla.
8. Commits pequeños y descriptivos por funcionalidad, no por fase completa.
9. Al terminar cada fase, resume qué se implementó, qué decisiones de diseño se tomaron, y qué quedó pendiente o fuera de alcance.
10. Si el volumen de funcionalidades de la Fase 3 resulta inabarcable en una sola sesión de trabajo, decláralo explícitamente y propone qué subconjunto entregar primero, en lugar de dejar funcionalidades a medio implementar.
