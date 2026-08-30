# Palmarés por presidente Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Honrar a los presidentes que ganaron títulos y dejaron la liga, mostrando un palmarés atado a la persona (no al equipo actual), más el palmarés global de cada club con el desglose por presidente.

**Architecture:** Todo el trabajo vive en `htdocs/_fuente/` (shell.html + app.js + styles.css), que se compila con `node _fuente/build.js` a `index.html` y los 9 `{lang}.html`. Ningún cambio de esquema en `datos_oficiales.json`: el presidente ya es `equipo.ciudad`, y cada `historial_temporadas[i].equipos` es una copia congelada en el momento de archivar esa temporada (`gestor/js/core.js:1162-1174`), así que ya conserva quién presidía cada club entonces. Tres funciones puras nuevas derivan todo del `historial_temporadas` existente vía la `palmares(idx)` ya existente; tres vistas nuevas (dos overlays `.ov` + un botón dentro del sheet de equipo) las muestran, reutilizando el marcado `.champ`/`.champ-crest`/`.champ-id`/`.champ-trophy` ya existente.

**Tech Stack:** HTML/CSS/JS vanilla (sin build de JS, solo el pre-render con jsdom en `_fuente/build.js`), Node.js + `jsdom` (ya en `devDependencies`) para el script de verificación.

## Global Constraints

- No se modifica el esquema de `datos_oficiales.json` ni nada del gestor (`htdocs/gestor/`).
- El presidente de un título es siempre `equipo.ciudad` del snapshot de esa temporada archivada — nunca el `ciudad` actual del equipo, nunca un mapa fijo en JS.
- Reutilizar las clases CSS `.champ`, `.champ-crest`, `.champ-id`, `.champ-trophy`, `.champ-wash`, `.ov`, `.ov-panel`, `.ov-head`, `.champ-note` donde encajen, en vez de duplicar estilos.
- Textos nuevos con `T('clave','texto en español')` (fallback en español) o `data-i18n="clave"` en el HTML estático — sin tocar `_fuente/dict.js` (fuera de alcance, ver spec).
- No se pagina ninguna lista nueva (liga pequeña, 2 temporadas archivadas).
- Verificación final obligatoria: `node _fuente/build.js` debe terminar sin errores y regenerar `index.html` + los 9 `{lang}.html` + `404.html`/`terminos.html`.
- Spec de referencia: `docs/superpowers/specs/2026-08-30-palmares-presidentes-design.md`.

---

## File Structure

- Modify: `htdocs/_fuente/app.js` — todo el cálculo y el renderizado nuevo, más la corrección del campo de presidente en el sheet de equipo.
- Modify: `htdocs/_fuente/shell.html` — marcado de los dos overlays nuevos (`ov-presidentes`, `ov-team-titulos`) y el botón de entrada en Historia.
- Modify: `htdocs/_fuente/styles.css` — clases nuevas para las tarjetas de presidente y el `z-index` del overlay anidado.
- Create: `htdocs/_fuente/test-palmares.js` — comprobación mínima (sin framework, estilo `gestor/test-core.js`) que carga `app.js` de verdad en jsdom con el `datos_oficiales.json` real y comprueba las tres funciones nuevas contra valores conocidos.

---

### Task 1: Corregir el campo de presidente en el sheet de equipo

**Files:**
- Modify: `htdocs/_fuente/app.js` (función `openTeam`, líneas ~791-834)

**Interfaces:**
- Consumes: nada nuevo, solo el campo `e.ciudad` que ya existe en cada equipo.
- Produces: nada que otras tareas consuman directamente; es una corrección aislada.

Hoy la ficha de un club muestra el `ciudad` (que en la práctica es el nombre
del presidente) **sin etiqueta** junto a la abreviatura del equipo, y la fila
etiquetada "Presidente / Gerente" lee `e.gerente`, un campo que no existe en
el JSON (siempre sale `·`). Se corrige para que la única fila visible sea la
etiquetada, leyendo el campo real.

- [ ] **Step 1: Quitar el `ciudad` duplicado y sin etiqueta de la cabecera**

En `openTeam`, busca esta línea (cabecera `tm-sub`):

```js
          '<span>'+esc(abbr3(e.nombre,e.abreviatura))+'</span>'+(e.ciudad?'<span>·</span><span>'+esc(e.ciudad)+'</span>':'')+
```

Sustitúyela por:

```js
          '<span>'+esc(abbr3(e.nombre,e.abreviatura))+'</span>'+
```

- [ ] **Step 2: Leer el presidente real en la fila de "Dirección"**

Busca esta línea:

```js
        '<div class="staff-line"><span>'+T('team.presidente','Presidente / Gerente')+'</span><b style="font-weight:500">'+esc(e.gerente||'·')+'</b></div>'+
```

Sustitúyela por:

```js
        '<div class="staff-line"><span>'+T('team.presidente','Presidente')+'</span><b style="font-weight:500">'+esc(e.ciudad||'·')+'</b></div>'+
```

- [ ] **Step 3: Verificar a mano en el navegador**

```bash
node _fuente/build.js
```

Abre `index.html` en un navegador, busca un equipo con `ciudad` no vacío
(p. ej. "Zanark Domain") desde el buscador o la sección de equipos, y
comprueba:
- La cabecera de la ficha ya NO muestra el nombre del presidente suelto junto
  a la abreviatura.
- La fila "Presidente" en "Dirección" muestra el nombre real (p. ej.
  `D4rkRepulser` para Zanark Domain), no `·`.

- [ ] **Step 4: Commit**

```bash
git add htdocs/_fuente/app.js
git commit -m "fix: la ficha de equipo lee el presidente de ciudad, no de un campo inexistente"
```

---

### Task 2: Funciones de cálculo del palmarés por presidente + corregir `openChamps`

**Files:**
- Modify: `htdocs/_fuente/app.js` (bloque `PALMARÉS`, líneas ~1116-1189)
- Create: `htdocs/_fuente/test-palmares.js`

**Interfaces:**
- Consumes: `palmares(idx)` (ya existente, sin tocar), `bd.historial_temporadas`, `bd.equipos`.
- Produces (usadas por las Tasks 3 y 4):
  - `presidenteDe(equipoSnapshot) -> string` (recorta espacios; `''` si no hay `ciudad`).
  - `todosLosTitulos() -> Array<{idx:number, temporadaNombre:string, temporadaFecha:string, comp:string, cls:string, marcador:string, equipo:object, presidente:string}>` — `equipo` es el snapshot de esa temporada (con `id`, `nombre`, `escudo`, `color1`, `color2`, `pts`...).
  - `titulosDePresidente(nombre:string) -> Array<mismo shape que todosLosTitulos()>`, más reciente primero.
  - `titulosDeEquipo(equipoId:string) -> Array<mismo shape>`, más reciente primero.
  - `presidentesConTitulos() -> Array<{nombre:string, titulos:Array<mismo shape>}>`, ordenado por nº de títulos descendente y luego alfabético; solo nombres no vacíos.
  - Todas expuestas en `window` para poder probarlas fuera del DOM real.

Esta tarea también retira el mapa fijo `PRESIDENTES` y hace que `openChamps`
lea el presidente del snapshot de esa temporada en vez de "quién dirige el
equipo hoy".

- [ ] **Step 1: Sustituir el mapa fijo y el comentario que lo justifica**

Busca (incluye el comentario de arriba del bloque `PALMARÉS`):

```js
/* PALMARÉS: se deriva de historial_temporadas en vez de escribirlo a mano —
   campeón de cada división por clasificación final y ganador de Copa por el
   resultado de la FINAL. Los presidentes reales viven aquí porque el campo
   `gerente` del JSON guarda el personaje del juego, no al manager. */
var PRESIDENTES={'Alpino':'david.gonzzalezc','Zanark Domain':'D4rkRepulser','Inazuma Kids FC':'Totti Alcresise','Academia Plenilunio':'Payo Aguao','Criaturas de la Noche':'Franshu','Gar':'Gabrii','Épsilon':'Deivid'};
/* Se indexa por posición, no por `nombre`: el snapshot archivado se llama
   "Temporada 1" en el JSON pero es la que la web narra como Temporada 2
   (la del Alpino campeón). La etiqueta la pone la propia tarjeta. */
```

Sustitúyelo por:

```js
/* PALMARÉS: se deriva de historial_temporadas en vez de escribirlo a mano —
   campeón de cada división por clasificación final y ganador de Copa por el
   resultado de la FINAL.
   El presidente de un título es equipo.ciudad DEL SNAPSHOT DE ESA TEMPORADA,
   no del equipo tal como está hoy: instantaneaTemporada() (gestor/js/core.js)
   hace una copia profunda al archivar, así que cada temporada ya conserva
   quién presidía el club entonces. Esto es real: el `ciudad` de Zanark Domain
   era "DarkRepulser" en la Temporada 1 archivada y pasó a "D4rkRepulser" en
   la Temporada 2 — un mapa fijo por nombre de equipo habría atribuido mal
   uno de los dos títulos. */
function presidenteDe(e){ return String((e&&e.ciudad)||'').trim(); }

/* Se indexa por posición, no por `nombre`: el snapshot archivado se llama
   "Temporada 1" en el JSON pero es la que la web narra como Temporada 2
   (la del Alpino campeón). La etiqueta la pone la propia tarjeta. */
```

- [ ] **Step 2: Añadir las funciones de agregación, justo después de `palmares(idx)`**

Localiza el final de la función `palmares(idx)` (termina en `return out.length?out:null;\n}`, justo antes de `function openChamps(idx,label){`). Inserta este bloque entre ambas:

```js
/* Todas las entradas de palmarés de todas las temporadas archivadas, en un
   solo array plano — punto de partida común para las tres vistas de abajo. */
function todosLosTitulos(){
  var out=[];
  (bd.historial_temporadas||[]).forEach(function(t,idx){
    var list=palmares(idx);
    if(!list) return;
    list.forEach(function(c){
      out.push({
        idx:idx,
        temporadaNombre:t.nombre||('Temporada #'+(idx+1)),
        temporadaFecha:t.fecha||'',
        comp:c.comp, cls:c.cls, marcador:c.marcador,
        equipo:c.e, presidente:presidenteDe(c.e)
      });
    });
  });
  return out;
}

function titulosDePresidente(nombre){
  return todosLosTitulos()
    .filter(function(x){ return x.presidente===nombre; })
    .sort(function(a,b){ return b.idx-a.idx; });
}
window.titulosDePresidente=titulosDePresidente;

function titulosDeEquipo(equipoId){
  return todosLosTitulos()
    .filter(function(x){ return x.equipo.id===equipoId; })
    .sort(function(a,b){ return b.idx-a.idx; });
}
window.titulosDeEquipo=titulosDeEquipo;

function presidentesConTitulos(){
  var map={};
  todosLosTitulos().forEach(function(x){
    if(!x.presidente) return;
    (map[x.presidente]=map[x.presidente]||[]).push(x);
  });
  return Object.keys(map)
    .map(function(nombre){ return {nombre:nombre, titulos:map[nombre]}; })
    .sort(function(a,b){ return b.titulos.length-a.titulos.length||a.nombre.localeCompare(b.nombre,'es'); });
}
window.presidentesConTitulos=presidentesConTitulos;
window.presidenteDe=presidenteDe;
```

- [ ] **Step 3: Hacer que `openChamps` lea el presidente del snapshot y lo convierta en botón**

Busca dentro de `openChamps`:

```js
    var pres=PRESIDENTES[c.e.nombre];
    return '<div class="champ"'+(live?' data-team="'+esc(live.id)+'"':'')+'>'+
      '<span class="champ-wash" style="background:radial-gradient(ellipse 80% 130% at 0% 50%,'+esc(wash(live||c.e,c1))+',transparent 68%)"></span>'+
      (isHttp(c.e.escudo)?'<img class="champ-crest" src="'+esc(c.e.escudo)+'" alt="'+esc(X(c.e.nombre))+'" loading="lazy">':'<span class="champ-crest noimg">'+esc(abbr3(c.e.nombre))+'</span>')+
      '<div class="champ-id">'+
        '<b>'+esc(X(c.e.nombre))+'</b>'+
        '<span class="pres"><i class="ph-bold ph-user-circle"></i>'+esc(pres||c.e.gerente||'·')+'</span>'+
      '</div>'+
```

Sustitúyelo por:

```js
    var pres=presidenteDe(c.e);
    return '<div class="champ"'+(live?' data-team="'+esc(live.id)+'"':'')+'>'+
      '<span class="champ-wash" style="background:radial-gradient(ellipse 80% 130% at 0% 50%,'+esc(wash(live||c.e,c1))+',transparent 68%)"></span>'+
      (isHttp(c.e.escudo)?'<img class="champ-crest" src="'+esc(c.e.escudo)+'" alt="'+esc(X(c.e.nombre))+'" loading="lazy">':'<span class="champ-crest noimg">'+esc(abbr3(c.e.nombre))+'</span>')+
      '<div class="champ-id">'+
        '<b>'+esc(X(c.e.nombre))+'</b>'+
        (pres
          ? '<button type="button" class="pres" data-pres="'+esc(pres)+'"><i class="ph-bold ph-user-circle"></i>'+esc(pres)+'</button>'
          : '<span class="pres"><i class="ph-bold ph-user-circle"></i>·</span>')+
      '</div>'+
```

- [ ] **Step 4: Escribir el script de comprobación**

Crea `htdocs/_fuente/test-palmares.js`. Reutiliza el mismo montaje de jsdom que
`_fuente/prerender.js` (shell.html real + dict/faq-dict/i18n/app.js inlineados
+ `fetch` sustituido por el JSON real), para probar las funciones tal como
las ejecuta un navegador de verdad, contra los datos de producción:

```js
/* Comprobación de las funciones de palmarés por presidente contra los datos
   reales. No es un framework de tests: el mínimo que falla si app.js deja de
   reproducir el comportamiento esperado.
   Uso:  node _fuente/test-palmares.js   (desde htdocs/) */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { JSDOM, VirtualConsole } = require('jsdom');

const d = __dirname;
let html = fs.readFileSync(path.join(d, 'shell.html'), 'utf8').replace(/^﻿/, '');
html = html.replace('<link rel="stylesheet" href="styles.css">', '<style>\n' + fs.readFileSync(path.join(d, 'styles.css'), 'utf8') + '\n</style>');
html = html.replace('<script src="i18n.js"></script>\n<script src="app.js"></script>',
  '<script>\n' + fs.readFileSync(path.join(d, 'dict.js'), 'utf8') + '\n</script>\n' +
  '<script>\n' + fs.readFileSync(path.join(d, 'faq-dict.js'), 'utf8') + '\n</script>\n' +
  '<script>\n' + fs.readFileSync(path.join(d, 'i18n.js'), 'utf8') + '\n</script>\n' +
  '<script>\n' + fs.readFileSync(path.join(d, 'app.js'), 'utf8') + '\n</script>');

const datosPath = path.join(d, '..', 'datos_oficiales.json');
const datos = JSON.parse(fs.readFileSync(datosPath, 'utf8'));

const virtualConsole = new VirtualConsole();
virtualConsole.on('jsdomError', function(){});

const dom = new JSDOM(html, {
  url: 'https://superligafrontier.es/',
  runScripts: 'dangerously',
  pretendToBeVisual: true,
  virtualConsole,
  beforeParse(window){
    window.fetch = function(url){
      if (String(url).indexOf('datos_oficiales.json') !== -1){
        return Promise.resolve({ ok:true, status:200, json:function(){ return Promise.resolve(datos); } });
      }
      return Promise.reject(new Error('test-palmares: fetch bloqueado para ' + url));
    };
    window.IntersectionObserver = function(){ return { observe:function(){}, unobserve:function(){}, disconnect:function(){} }; };
    window.ResizeObserver = function(){ return { observe:function(){}, unobserve:function(){}, disconnect:function(){} }; };
    window.requestAnimationFrame = function(cb){ cb(2000); return 1; };
    window.matchMedia = function(q){ return { matches:false, media:q, addListener:function(){}, removeListener:function(){}, addEventListener:function(){}, removeEventListener:function(){} }; };
  }
});

let n = 0;
const ok = (m) => { n++; console.log('  ok  ' + m); };

(async () => {
  const { document } = dom.window;
  if (document.readyState === 'loading'){
    await new Promise(function(resolve){ document.addEventListener('DOMContentLoaded', resolve); });
  }
  await new Promise(function(r){ setTimeout(r, 0); });
  await new Promise(function(r){ setTimeout(r, 0); });

  const w = dom.window;
  assert.strictEqual((w.bd.historial_temporadas || []).length, 2, 'se esperan 2 temporadas archivadas en datos_oficiales.json; si esto cambió, actualiza los valores esperados de este test');
  ok('2 temporadas archivadas cargadas');

  /* Zanark Domain (id eq_1777322423802): ciudad era "DarkRepulser" cuando se
     archivó la Temporada 1 (ganó el Ascenso) y "D4rkRepulser" cuando se
     archivó la Temporada 2 (ganó Superliga y Copa, el primer doblete). Un
     mapa fijo por nombre de equipo no puede distinguir estos dos presidentes;
     leer el ciudad del snapshot sí. */
  const zanark = w.titulosDeEquipo('eq_1777322423802');
  assert.strictEqual(zanark.length, 3, 'Zanark Domain debería tener 3 títulos en el histórico actual');
  assert.deepStrictEqual(zanark.map(function(x){ return x.presidente; }).sort(), ['D4rkRepulser', 'D4rkRepulser', 'DarkRepulser']);
  ok('titulosDeEquipo("eq_1777322423802") separa a los dos presidentes de Zanark Domain');

  const d4rk = w.titulosDePresidente('D4rkRepulser');
  assert.strictEqual(d4rk.length, 2, 'D4rkRepulser (Temporada 2) no debe incluir el título de Ascenso de DarkRepulser (Temporada 1)');
  assert.deepStrictEqual(d4rk.map(function(x){ return x.comp; }).sort(), ['Copa Fútbol Frontier', 'Superliga Frontier']);
  ok('titulosDePresidente("D4rkRepulser") solo trae sus 2 títulos, no el de "DarkRepulser"');

  const darkViejo = w.titulosDePresidente('DarkRepulser');
  assert.strictEqual(darkViejo.length, 1);
  assert.strictEqual(darkViejo[0].comp, 'Ascenso Frontier');
  ok('titulosDePresidente("DarkRepulser") conserva su único título aunque el equipo ya no lo dirija');

  const salon = w.presidentesConTitulos();
  const nombres = salon.map(function(p){ return p.nombre; });
  ['david.gonzzalezc', 'DarkRepulser', 'Totti Alcresise', 'D4rkRepulser', 'Deivid'].forEach(function(n){
    assert.ok(nombres.indexOf(n) !== -1, 'falta "' + n + '" en presidentesConTitulos()');
  });
  assert.strictEqual(salon[0].nombre, 'D4rkRepulser', 'D4rkRepulser tiene 2 títulos: debe ir primero en el ranking');
  ok('presidentesConTitulos() incluye a los 5 presidentes con título y ordena por nº de títulos');

  dom.window.close();
  console.log(n + ' comprobaciones OK');
})().catch(function(e){
  console.error('FALLO:', e.message);
  process.exit(1);
});
```

- [ ] **Step 5: Ejecutar el script y comprobar que pasa**

```bash
node _fuente/test-palmares.js
```

Esperado: las 5 líneas `ok` y `5 comprobaciones OK`, sin `FALLO`.

- [ ] **Step 6: Reconstruir el sitio y comprobar que no rompe nada existente**

```bash
node _fuente/build.js
```

Esperado: termina sin lanzar excepción y reescribe `index.html` + los 9
`{lang}.html`. Abre `index.html`, ve a Historia, abre el palmarés de una
temporada archivada (p. ej. "Temporada 2") y comprueba que el nombre del
presidente que aparece junto a cada campeón es correcto para **esa**
temporada (compara con la salida del Step 5).

- [ ] **Step 7: Commit**

```bash
git add htdocs/_fuente/app.js htdocs/_fuente/test-palmares.js
git commit -m "feat: deriva el presidente del snapshot de la temporada, no de un mapa fijo"
```

---

### Task 3: Salón de presidentes (vista pública nueva)

**Files:**
- Modify: `htdocs/_fuente/shell.html` (después del bloque `ov-champs`, y en la sección Historia)
- Modify: `htdocs/_fuente/app.js` (delegado de clics, bloque `DOMContentLoaded`, funciones de render)
- Modify: `htdocs/_fuente/styles.css` (tarjetas de presidente)

**Interfaces:**
- Consumes: `presidentesConTitulos()`, `titulosDePresidente(nombre)` (Task 2); `openTeam(id)`, `esc`, `T`, `X`, `isHttp`, `abbr3`, `wash` (ya existentes).
- Produces: `openPresidentesHall()`, `openPresidenteDetalle(nombre)`, ambas expuestas en `window` (las usa Task 4 indirectamente no, pero quedan disponibles para pruebas manuales en consola).

- [ ] **Step 1: Añadir el marcado de los dos overlays y el botón de entrada**

En `htdocs/_fuente/shell.html`, busca:

```html
<div class="ov" id="ov-champs">
  <div class="ov-panel champ-panel">
    <div class="ov-head"><h3 id="champ-title" data-no-tr>Palmarés</h3><button class="btn btn-icon" id="champ-close" aria-label="Cerrar"><i class="ph ph-x"></i></button></div>
    <div class="champs" id="champs"></div>
    <p class="champ-note" data-i18n="champs.note">Campeones calculados sobre la clasificación final archivada de esa temporada y el resultado de la final de Copa. Toca un club para abrir su ficha.</p>
  </div>
</div>
```

Sustitúyelo por (añade el overlay `ov-presidentes` justo después, deja
`ov-champs` intacto):

```html
<div class="ov" id="ov-champs">
  <div class="ov-panel champ-panel">
    <div class="ov-head"><h3 id="champ-title" data-no-tr>Palmarés</h3><button class="btn btn-icon" id="champ-close" aria-label="Cerrar"><i class="ph ph-x"></i></button></div>
    <div class="champs" id="champs"></div>
    <p class="champ-note" data-i18n="champs.note">Campeones calculados sobre la clasificación final archivada de esa temporada y el resultado de la final de Copa. Toca un club para abrir su ficha.</p>
  </div>
</div>

<div class="ov" id="ov-presidentes">
  <div class="ov-panel champ-panel">
    <div class="ov-head">
      <div style="display:flex;align-items:center;gap:.5rem;min-width:0">
        <button type="button" class="pres-back" id="pres-back" hidden><i class="ph-bold ph-arrow-left"></i> <span data-i18n="pres.back">Volver</span></button>
        <h3 id="pres-title" data-no-tr>Salón de presidentes</h3>
      </div>
      <button class="btn btn-icon" id="pres-close" aria-label="Cerrar"><i class="ph ph-x"></i></button>
    </div>
    <div id="pres-body"></div>
    <p class="champ-note" data-i18n="pres.note">Solo aparecen presidentes con al menos un título. Toca un nombre para ver su palmarés completo, o un título para abrir el club con el que se ganó.</p>
  </div>
</div>
```

- [ ] **Step 2: Añadir el botón de entrada en Historia**

Busca en `htdocs/_fuente/shell.html`:

```html
      <h2 class="h1 nowrap" data-i18n="historia.title">Cuatro temporadas construyendo liga</h2>
      <p class="lede" data-i18n="historia.lede">Cada temporada ha añadido una pieza que la anterior no tenía: primero el formato, después las competiciones, ahora la continuidad. Esta es la evolución real, sin adornos.</p>
    </div>
```

Sustitúyelo por:

```html
      <h2 class="h1 nowrap" data-i18n="historia.title">Cuatro temporadas construyendo liga</h2>
      <p class="lede" data-i18n="historia.lede">Cada temporada ha añadido una pieza que la anterior no tenía: primero el formato, después las competiciones, ahora la continuidad. Esta es la evolución real, sin adornos.</p>
      <button type="button" class="btn btn-secondary" id="btn-presidentes" style="margin-top:1.25rem">
        <i class="ph-bold ph-medal"></i> <span data-i18n="hist.presidentes.btn">Salón de presidentes</span>
      </button>
    </div>
```

- [ ] **Step 3: Añadir el CSS de las tarjetas de presidente**

En `htdocs/_fuente/styles.css`, justo después de la regla `.champ-note{...}`
(bloque `OVERLAY DE CAMPEONES`), añade:

```css
.pres-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:.625rem; padding:1rem; }
.pres-card{ display:flex; flex-direction:column; gap:.35rem; padding:1rem 1.125rem; border:1px solid var(--line); border-radius:var(--r); background:var(--surface); font:inherit; text-align:left; cursor:pointer; transition:border-color var(--t2), transform var(--t2) var(--ease); }
.pres-card:hover{ border-color:var(--line-2); transform:translateY(-2px); }
.pres-card b{ font-size:1rem; font-weight:600; letter-spacing:-.02em; }
.pres-card span{ font-size:.75rem; color:var(--ink-3); }
.pres-back{ display:inline-flex; align-items:center; gap:.4rem; font:inherit; background:none; border:0; padding:0; cursor:pointer; font-family:var(--f-mono); font-size:.6875rem; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-3); }
.pres-back:hover{ color:var(--ink-1); }
.pres-group-title{ padding:1rem 1.125rem .25rem; font-size:.8125rem; font-weight:600; color:var(--ink-2); }
.champ-id button.pres{ background:none; border:0; padding:0; font:inherit; cursor:pointer; text-align:left; }
.champ-id button.pres:hover, .champ-id button.pres:focus-visible{ color:var(--ink-1); }
```

- [ ] **Step 4: Añadir el delegado de clics para `data-pres`**

En `htdocs/_fuente/app.js`, busca (delegado global de clics):

```js
  var ch=t.closest('[data-champs]');
  if(ch){ openChamps(parseInt(ch.dataset.champs,10),ch.dataset.champsLabel||''); return; }
```

Sustitúyelo por (añade el caso `data-pres` justo después, con el mismo
comentario de prioridad que ya usa el fichero para casos que van "antes" de
`data-team`):

```js
  var ch=t.closest('[data-champs]');
  if(ch){ openChamps(parseInt(ch.dataset.champs,10),ch.dataset.champsLabel||''); return; }

  /* Va antes de data-team: el botón del presidente vive dentro de un .champ
     que también lleva data-team, y si no, abriría el club en vez del salón. */
  var pr=t.closest('[data-pres]');
  if(pr){ $('ov-champs').classList.remove('open'); openPresidenteDetalle(pr.dataset.pres); return; }

  var pc=t.closest('[data-pres-card]');
  if(pc){ openPresidenteDetalle(pc.dataset.presCard); return; }
```

- [ ] **Step 5: Añadir las funciones de render, después de `openChamps`**

En `htdocs/_fuente/app.js`, localiza el final de `openChamps` (termina en
`$('ov-champs').classList.add('open');\n}`) e inserta justo después:

```js
function openPresidentesHall(){
  var list=presidentesConTitulos();
  $('pres-title').textContent=T('pres.hall.title','Salón de presidentes');
  $('pres-back').hidden=true;
  $('pres-body').innerHTML = list.length
    ? '<div class="pres-grid">'+list.map(function(p){
        var etq=p.titulos.length===1?T('pres.titulo','título'):T('pres.titulos','títulos');
        return '<button type="button" class="pres-card" data-pres-card="'+esc(p.nombre)+'">'+
          '<b>'+esc(p.nombre)+'</b>'+
          '<span>'+p.titulos.length+' '+etq+'</span>'+
        '</button>';
      }).join('')+'</div>'
    : '<p class="champ-note">'+T('pres.vacio','Todavía no hay presidentes con títulos registrados.')+'</p>';
  $('ov-presidentes').classList.add('open');
}
window.openPresidentesHall=openPresidentesHall;

function openPresidenteDetalle(nombre){
  var titulos=titulosDePresidente(nombre);
  if(!titulos.length) return;
  $('pres-title').textContent=nombre;
  $('pres-back').hidden=false;
  $('pres-body').innerHTML='<div class="champs">'+titulos.map(function(x){
    var e=x.equipo, live=bd.equipos.find(function(z){ return z.id===e.id; });
    var c1=(live&&live.color1)||e.color1||'#3A3A3A';
    return '<div class="champ" data-team="'+esc(e.id)+'">'+
      '<span class="champ-wash" style="background:radial-gradient(ellipse 80% 130% at 0% 50%,'+esc(wash(live||e,c1))+',transparent 68%)"></span>'+
      (isHttp(e.escudo)?'<img class="champ-crest" src="'+esc(e.escudo)+'" alt="'+esc(X(e.nombre))+'" loading="lazy">':'<span class="champ-crest noimg">'+esc(abbr3(e.nombre))+'</span>')+
      '<div class="champ-id">'+
        '<b>'+esc(X(e.nombre))+'</b>'+
        '<span class="pres"><i class="ph-bold ph-calendar"></i>'+esc(x.temporadaNombre)+'</span>'+
      '</div>'+
      '<div class="champ-trophy">'+
        '<i class="ph-bold ph-trophy"></i>'+
        '<span class="badge '+x.cls+'">'+x.comp+'</span>'+
        (x.marcador?'<span class="mono" style="font-size:.6875rem;color:var(--ink-5)">'+esc(x.marcador)+'</span>':'')+
      '</div>'+
    '</div>';
  }).join('')+'</div>';
  $('ov-presidentes').classList.add('open');
}
window.openPresidenteDetalle=openPresidenteDetalle;
```

- [ ] **Step 6: Conectar los botones nuevos en `DOMContentLoaded`**

En `htdocs/_fuente/app.js`, busca:

```js
  renderAntiguedad();
  $('champ-close').addEventListener('click',function(){ $('ov-champs').classList.remove('open'); });
  $('ov-champs').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
```

Sustitúyelo por:

```js
  renderAntiguedad();
  $('champ-close').addEventListener('click',function(){ $('ov-champs').classList.remove('open'); });
  $('ov-champs').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
  $('btn-presidentes').addEventListener('click',openPresidentesHall);
  $('pres-back').addEventListener('click',openPresidentesHall);
  $('pres-close').addEventListener('click',function(){ $('ov-presidentes').classList.remove('open'); });
  $('ov-presidentes').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
```

- [ ] **Step 7: Reconstruir y comprobar a mano en el navegador**

```bash
node _fuente/build.js
```

Abre `index.html`:
- En Historia, pulsa "Salón de presidentes": debe abrirse una rejilla con
  (según los datos actuales) `D4rkRepulser` primero (2 títulos), y
  `david.gonzzalezc`, `DarkRepulser`, `Totti Alcresise`, `Deivid` con 1 cada
  uno.
- Pulsa la tarjeta de `D4rkRepulser`: debe verse su detalle con 2 títulos
  (Superliga y Copa de la Temporada 2/3 narrada), y el botón "Volver" debe
  regresar a la rejilla.
- Dentro del detalle, pulsa el título de Copa: debe abrirse la ficha de
  Zanark Domain (`openTeam`), cerrando el salón.
- Abre el modal de campeones de una temporada archivada desde Historia
  ("Ver campeones"): el nombre del presidente ahora es un botón; púlsalo y
  comprueba que abre el salón ya filtrado a esa persona.
- Comprueba que no hay scroll horizontal de página en 375×812 y en
  escritorio, y que el foco es visible al tabular por los botones nuevos.

- [ ] **Step 8: Commit**

```bash
git add htdocs/_fuente/shell.html htdocs/_fuente/app.js htdocs/_fuente/styles.css
git commit -m "feat: añade el Salón de presidentes (palmarés por persona, no por equipo)"
```

---

### Task 4: Palmarés del club dentro del sheet de equipo

**Files:**
- Modify: `htdocs/_fuente/shell.html` (overlay `ov-team-titulos`)
- Modify: `htdocs/_fuente/app.js` (función `openTeam`, delegado de clics, `DOMContentLoaded`, nueva función de render)
- Modify: `htdocs/_fuente/styles.css` (`z-index` del overlay anidado)

**Interfaces:**
- Consumes: `titulosDeEquipo(equipoId)` (Task 2); `esc`, `T` (ya existentes).
- Produces: `openTeamTitulos(equipoId)`, expuesta en `window`.

- [ ] **Step 1: Añadir el marcado del overlay anidado**

En `htdocs/_fuente/shell.html`, busca el overlay `ov-presidentes` añadido en
la Task 3 (termina en `</div>\n\n<div class="sheet" id="sheet-team">`, o
busca directamente la línea `<div class="sheet" id="sheet-team">`) e inserta
justo antes:

```html
<div class="ov" id="ov-team-titulos">
  <div class="ov-panel champ-panel">
    <div class="ov-head"><h3 data-i18n="team.titulos.title">Palmarés del club</h3><button class="btn btn-icon" id="team-titulos-close" aria-label="Cerrar"><i class="ph ph-x"></i></button></div>
    <div id="team-titulos-body"></div>
  </div>
</div>

```

- [ ] **Step 2: Añadir el `z-index` para que se vea por encima del sheet**

En `htdocs/_fuente/styles.css`, `.sheet` usa `z-index:850` (línea ~967) y
`.ov` usa `z-index:800` (línea ~888): sin corregirlo, este overlay quedaría
oculto detrás de la ficha de equipo que ya está abierta. Añade, junto a las
reglas de `.pres-*` de la Task 3:

```css
#ov-team-titulos{ z-index:900; }
```

- [ ] **Step 3: Añadir la función de render**

En `htdocs/_fuente/app.js`, después de `openPresidenteDetalle` (Task 3),
añade:

```js
function openTeamTitulos(equipoId){
  var titulos=titulosDeEquipo(equipoId);
  if(!titulos.length) return;
  var porPresidente={};
  titulos.forEach(function(x){
    var nombre=x.presidente||T('pres.desconocido','—');
    (porPresidente[nombre]=porPresidente[nombre]||[]).push(x);
  });
  var presidentes=Object.keys(porPresidente)
    .map(function(nombre){ return {nombre:nombre, titulos:porPresidente[nombre]}; })
    .sort(function(a,b){ return b.titulos.length-a.titulos.length||a.nombre.localeCompare(b.nombre,'es'); });

  var filas=titulos.map(function(x){
    return '<div class="champ">'+
      '<div class="champ-id">'+
        '<b>'+esc(x.temporadaNombre)+'</b>'+
        '<span class="pres"><i class="ph-bold ph-user-circle"></i>'+esc(x.presidente||'·')+'</span>'+
      '</div>'+
      '<div class="champ-trophy">'+
        '<i class="ph-bold ph-trophy"></i>'+
        '<span class="badge '+x.cls+'">'+x.comp+'</span>'+
        (x.marcador?'<span class="mono" style="font-size:.6875rem;color:var(--ink-5)">'+esc(x.marcador)+'</span>':'')+
      '</div>'+
    '</div>';
  }).join('');

  var grupos=presidentes.map(function(p){
    var etq=p.titulos.length===1?T('pres.titulo','título'):T('pres.titulos','títulos');
    return '<div class="pres-card" style="cursor:default">'+
      '<b>'+esc(p.nombre)+'</b>'+
      '<span>'+p.titulos.length+' '+etq+'</span>'+
    '</div>';
  }).join('');

  $('team-titulos-body').innerHTML=
    '<div class="champs">'+filas+'</div>'+
    '<div class="pres-group-title">'+T('team.titulos.presidentes','Presidentes con título en este club')+'</div>'+
    '<div class="pres-grid" style="padding-top:0">'+grupos+'</div>';
  $('ov-team-titulos').classList.add('open');
}
window.openTeamTitulos=openTeamTitulos;
```

- [ ] **Step 4: Añadir el botón dentro de la ficha de equipo, solo si tiene títulos**

En `htdocs/_fuente/app.js`, dentro de `openTeam`, busca la línea que cierra
el bloque de "Dirección" (Task 1 ya la tocó):

```js
        (e.formacion?'<div class="staff-line"><span>'+T('team.formacion','Formación')+'</span><b style="font-weight:500" class="mono">'+esc(e.formacion)+'</b></div>':'')+
      '</div>'+
    '</div>';
  openSheet('sheet-team');
```

Sustitúyelo por:

```js
        (e.formacion?'<div class="staff-line"><span>'+T('team.formacion','Formación')+'</span><b style="font-weight:500" class="mono">'+esc(e.formacion)+'</b></div>':'')+
        (function(){
          var t=titulosDeEquipo(e.id);
          return t.length
            ? '<button type="button" class="btn btn-secondary btn-sm" style="margin-top:1.25rem" data-team-titulos="'+esc(e.id)+'"><i class="ph-bold ph-trophy"></i> '+T('team.titulos.ver','Ver palmarés del club')+' · '+t.length+'</button>'
            : '';
        })()+
      '</div>'+
    '</div>';
  openSheet('sheet-team');
```

- [ ] **Step 5: Añadir el delegado de clics para `data-team-titulos`**

En `htdocs/_fuente/app.js`, busca el bloque añadido en la Task 3 (Step 4) y
sustitúyelo por (añade el caso nuevo antes del `data-team` general, ya que
Task 3 explica por qué):

```js
  var ch=t.closest('[data-champs]');
  if(ch){ openChamps(parseInt(ch.dataset.champs,10),ch.dataset.champsLabel||''); return; }

  /* Van antes de data-team: ambos botones pueden vivir dentro de marcado que
     también lleva data-team, y si no, abrirían el club en vez de su destino. */
  var pr=t.closest('[data-pres]');
  if(pr){ $('ov-champs').classList.remove('open'); openPresidenteDetalle(pr.dataset.pres); return; }

  var pc=t.closest('[data-pres-card]');
  if(pc){ openPresidenteDetalle(pc.dataset.presCard); return; }

  var tt=t.closest('[data-team-titulos]');
  if(tt){ openTeamTitulos(tt.dataset.teamTitulos); return; }
```

- [ ] **Step 6: Conectar el cierre del overlay en `DOMContentLoaded`**

En `htdocs/_fuente/app.js`, busca el bloque añadido en la Task 3 (Step 6) y
sustitúyelo por:

```js
  renderAntiguedad();
  $('champ-close').addEventListener('click',function(){ $('ov-champs').classList.remove('open'); });
  $('ov-champs').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
  $('btn-presidentes').addEventListener('click',openPresidentesHall);
  $('pres-back').addEventListener('click',openPresidentesHall);
  $('pres-close').addEventListener('click',function(){ $('ov-presidentes').classList.remove('open'); });
  $('ov-presidentes').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
  $('team-titulos-close').addEventListener('click',function(){ $('ov-team-titulos').classList.remove('open'); });
  $('ov-team-titulos').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
```

- [ ] **Step 7: Reconstruir y comprobar a mano en el navegador**

```bash
node _fuente/build.js
```

Abre `index.html`, busca "Zanark Domain" (o el equipo que tenga más títulos
según el Task 2/Step 5) y abre su ficha:
- Debe verse el botón "Ver palmarés del club · 3" (según los datos actuales)
  bajo "Dirección".
- Al pulsarlo, se abre `ov-team-titulos` **por encima** de la ficha (la ficha
  sigue debajo, no se cierra) con 3 filas cronológicas y, debajo, 2 tarjetas
  de presidente (`D4rkRepulser` · 2 títulos, `DarkRepulser` · 1 título).
- Cerrar este overlay (botón o clic fuera) debe dejar la ficha de equipo
  exactamente como estaba, sin perder el scroll.
- Abre la ficha de un equipo SIN títulos (p. ej. uno recién creado o con
  `historial_temporadas` sin apariciones): el botón no debe aparecer.
- Repite la comprobación de scroll horizontal / foco visible de la Task 3 en
  este overlay también.

- [ ] **Step 8: Commit**

```bash
git add htdocs/_fuente/shell.html htdocs/_fuente/app.js htdocs/_fuente/styles.css
git commit -m "feat: añade el palmares del club con desglose por presidente al sheet de equipo"
```

---

### Task 5: Verificación final de cierre

**Files:** ninguno nuevo — solo ejecución y checklist manual.

**Interfaces:** ninguna nueva.

- [ ] **Step 1: Reconstruir todo el sitio desde cero**

```bash
node _fuente/build.js
```

Esperado: sin excepciones, `index.html` + los 9 `{lang}.html` +
`404.html`/`terminos.html` regenerados.

- [ ] **Step 2: Ejecutar el script de comprobación de nuevo**

```bash
node _fuente/test-palmares.js
```

Esperado: las 5 comprobaciones `ok`.

- [ ] **Step 3: Repasar el checklist completo de la spec**

Contra `index.html` en el navegador, a 375×812 y en escritorio, repasa la
sección "Verificación antes de dar por terminado" de
`docs/superpowers/specs/2026-08-30-palmares-presidentes-design.md`: los ocho
puntos (sheet de equipo corregido, presidente correcto por temporada, salón
solo con presidentes con título, clic en título abre el club correcto
incluso archivado, overlay anidado no cierra la ficha, sin scroll
horizontal).

- [ ] **Step 4: Commit final (solo si el Step 3 obligó a algún ajuste)**

Si todo pasó sin tocar nada, no hay nada que commitear en esta tarea. Si el
repaso manual obligó a un ajuste puntual:

```bash
git add htdocs/_fuente/
git commit -m "fix: ajustes de verificación final del palmarés por presidente"
```
