/* Comprueba que los clubes nombrados desde el marcado estático (staff,
   leyendas, cronología) salen con el nombre del idioma activo, y que el orden
   alfabético de clubes sigue al idioma. Usa los datos reales.
   Uso:  node _fuente/test-intl-marcado.js   (desde htdocs/) */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { JSDOM, VirtualConsole } = require('jsdom');

const d = __dirname;
let html = fs.readFileSync(path.join(d, 'shell.html'), 'utf8').replace(/^﻿/, '');
html = html.replace('<link rel="stylesheet" href="styles.css">', '<style>\n' + fs.readFileSync(path.join(d, 'styles.css'), 'utf8') + '\n</style>');
html = html.replace(/<script src="i18n\.js"><\/script>\r?\n<script src="app\.js"><\/script>/,
  '<script>\n' + fs.readFileSync(path.join(d, 'dict.js'), 'utf8') + '\n</script>\n' +
  '<script>\n' + fs.readFileSync(path.join(d, 'faq-dict.js'), 'utf8') + '\n</script>\n' +
  '<script>\n' + fs.readFileSync(path.join(d, 'i18n.js'), 'utf8') + '\n</script>\n' +
  '<script>\n' + fs.readFileSync(path.join(d, 'app.js'), 'utf8') + '\n</script>');

const datos = JSON.parse(fs.readFileSync(path.join(d, '..', 'datos_oficiales.json'), 'utf8'));
const nombreEn = n => (datos.equipos.find(e => e.nombre === n) || {}).nombre_en;

async function cargar(lang){
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', function(){});
  const dom = new JSDOM(html, {
    url: 'https://superligafrontier.es/' + (lang ? '?lang=' + lang : ''),
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    virtualConsole,
    beforeParse(window){
      window.fetch = function(url){
        if (String(url).indexOf('datos_oficiales.json') !== -1){
          return Promise.resolve({ ok:true, status:200, json:function(){ return Promise.resolve(datos); } });
        }
        /* El traductor automático queda cortado a propósito: lo que se prueba
           aquí es justo lo que NO debe pasar por él. */
        return Promise.reject(new Error('fetch bloqueado: ' + url));
      };
      window.IntersectionObserver = function(){ return { observe:function(){}, unobserve:function(){}, disconnect:function(){} }; };
      window.ResizeObserver = function(){ return { observe:function(){}, unobserve:function(){}, disconnect:function(){} }; };
      window.requestAnimationFrame = function(cb){ cb(2000); return 1; };
      window.matchMedia = function(q){ return { matches:false, media:q, addListener:function(){}, removeListener:function(){}, addEventListener:function(){}, removeEventListener:function(){} }; };
    }
  });
  const { document } = dom.window;
  if (document.readyState === 'loading'){
    await new Promise(function(r){ document.addEventListener('DOMContentLoaded', r); });
  }
  await new Promise(function(r){ setTimeout(r, 0); });
  await new Promise(function(r){ setTimeout(r, 0); });
  return dom.window;
}

let n = 0;
const ok = (m) => { n++; console.log('  ok  ' + m); };
const txt = (w, sel) => {
  const el = w.document.querySelector(sel);
  assert.ok(el, 'no existe en el marcado: ' + sel);
  return el.textContent.trim();
};

(async () => {
  /* --- 1. La ficha de Lulu apunta al Ultra Zeus ------------------------- */
  const es = await cargar(null);
  assert.ok(!es.document.querySelector('[data-club="Instituto Zeus"]'),
    'el Instituto Zeus no existe en el JSON: no debe quedar ninguna referencia');
  assert.strictEqual(txt(es, '.staff-card [data-club="Ultra Zeus"]'), 'Ultra Zeus');
  ok('en español, Lulu dirige el Ultra Zeus');

  /* --- 2. En español no cambia ningún nombre ---------------------------- */
  assert.strictEqual(txt(es, '.legend-stats [data-club="Zanark Domain"]'), 'Zanark Domain');
  assert.strictEqual(txt(es, '.staff-card [data-club="Oscuridad Ancestral"]'), 'Oscuridad Ancestral');
  assert.ok(txt(es, '[data-i18n="staff.franshu.bio"]').indexOf('Criaturas de la Noche') >= 0,
    'la bio del fundador en español nombra a las Criaturas de la Noche');
  assert.ok(txt(es, '[data-i18n="leyendas.payo.text"]').indexOf('Alpino') >= 0,
    'la cita de Payo en español nombra al Alpino');
  ok('en español los clubes del marcado estático se quedan como están');

  /* --- 3. En inglés sale el nombre inglés, no la traducción literal ----- */
  const en = await cargar('en');
  assert.strictEqual(txt(en, '.staff-card [data-club="Ultra Zeus"]'), nombreEn('Ultra Zeus'));
  assert.strictEqual(txt(en, '.staff-card [data-club="Oscuridad Ancestral"]'), nombreEn('Oscuridad Ancestral'));
  assert.strictEqual(txt(en, '.staff-card [data-club="Inazuma Kids FC"]'), nombreEn('Inazuma Kids FC'));
  ok('los clubes de los moderadores salen traducidos (Ultra Zeus -> ' + nombreEn('Ultra Zeus') + ')');

  assert.strictEqual(txt(en, '.legend-stats [data-club="Zanark Domain"]'), nombreEn('Zanark Domain'));
  ok('el club de la leyenda D4rkRepulser sale traducido (' + nombreEn('Zanark Domain') + ')');

  const bio = txt(en, '[data-i18n="staff.franshu.bio"]');
  assert.ok(bio.indexOf(nombreEn('Criaturas de la Noche')) >= 0,
    'la bio del fundador debe nombrar ' + nombreEn('Criaturas de la Noche') + ': ' + bio);
  assert.ok(bio.indexOf('Criaturas de la Noche') < 0,
    'la bio del fundador no debe dejar el nombre español');
  ok('la descripción del fundador dice ' + nombreEn('Criaturas de la Noche') + ', no la traducción literal');

  const payo = txt(en, '[data-i18n="leyendas.payo.text"]');
  assert.ok(payo.indexOf(nombreEn('Alpino')) >= 0,
    'la cita de Payo debe nombrar ' + nombreEn('Alpino') + ': ' + payo);
  const t3 = txt(en, '[data-i18n="historia.t3"]');
  assert.ok(t3.indexOf(nombreEn('Zanark Domain')) >= 0 && t3.indexOf(nombreEn('Épsilon')) >= 0,
    'la cronología de la T3 debe nombrar los clubes en inglés: ' + t3);
  ok('los clubes citados en la cronología y en las leyendas salen traducidos');

  /* --- 4. El orden alfabético sigue al idioma --------------------------- */
  /* "Ultra Zeus" es "Fallen Zeus" en inglés, así que adelanta al "Instituto
     Otaku" ("Otaku") al cambiar de idioma. Si el orden no siguiera al idioma,
     el signo sería el mismo en los dos. */
  const setLang = (w, c) => { try { w.localStorage.setItem('sf_lang', c); } catch(e){} };
  setLang(en, 'es');
  const enEs = en.cmpClub('Ultra Zeus', 'Instituto Otaku');
  setLang(en, 'en');
  const enEn = en.cmpClub('Ultra Zeus', 'Instituto Otaku');
  assert.ok(enEs > 0, 'en español Ultra Zeus va después de Instituto Otaku');
  assert.ok(enEn < 0, 'en inglés Fallen Zeus va antes de Otaku');
  ok('el orden alfabético de clubes cambia con el idioma');

  setLang(en, 'es');

  /* --- 5. Ficha de equipo: presidente arriba, gerente en Dirección ------ */
  /* El presidente vive en el campo `ciudad` (rareza histórica del esquema);
     el gerente es el cargo del juego, junto al entrenador. */
  const tarjeta = es.document.querySelector('.card.team[data-team]');
  assert.ok(tarjeta, 'debería haber tarjetas de equipo renderizadas');
  const eq = es.bd.equipos.find(x => x.id === tarjeta.dataset.team);
  assert.ok(eq && eq.gerente && eq.ciudad, 'el equipo de la prueba necesita gerente y presidente');
  tarjeta.dispatchEvent(new es.Event('click', { bubbles: true }));

  const ficha = es.document.getElementById('sheet-team-body');
  const sub = ficha.querySelector('.tm-sub').textContent;
  assert.ok(sub.indexOf(eq.ciudad) >= 0, 'el presidente debe salir junto a la abreviatura: ' + sub);

  const direccion = Array.from(ficha.querySelectorAll('.staff-line'))
    .map(el => el.textContent.trim());
  assert.ok(direccion.some(l => l.indexOf(eq.gerente) >= 0),
    'Dirección debe listar al gerente (' + eq.gerente + '): ' + JSON.stringify(direccion));
  assert.ok(!direccion.some(l => l.indexOf(eq.ciudad) >= 0),
    'Dirección ya no debe listar al presidente: ' + JSON.stringify(direccion));
  ok('en la ficha del club, el presidente va con la abreviatura y Dirección lista al gerente');

  console.log('\n' + n + ' comprobaciones OK');
})().catch(function(e){ console.error('\nFALLA:', e.message); process.exit(1); });
