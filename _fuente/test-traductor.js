/* Comprueba de punta a punta que la capa de traducción automática funciona:
   carga la página en inglés y verifica que los textos que NO están en el
   diccionario (bio del fundador, cronología, reseñas) acaban traducidos.

   Este test SÍ sale a la red, contra el mismo endpoint que usa la web. Si no
   hay conexión, avisa y no falla: lo que prueba es el camino del código, no
   que Google esté disponible en este momento.
   Uso:  node _fuente/test-traductor.js   (desde htdocs/) */
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

let peticiones = 0, fallos = 0;

(async () => {
  /* Sonda previa: si el endpoint no responde, no tiene sentido seguir. */
  try {
    const r = await fetch('https://translate.googleapis.com/translate_a/t?client=dict-chrome-ex&sl=auto&tl=en&q=' + encodeURIComponent('Hola mundo'));
    const j = await r.json();
    const t = Array.isArray(j[0]) ? j[0][0] : j[0];
    if (!/hello/i.test(String(t))) throw new Error('respuesta inesperada: ' + JSON.stringify(j).slice(0, 120));
  } catch (e) {
    console.log('  AVISO  sin acceso al traductor (' + String(e.message).slice(0, 80) + '); test omitido');
    return;
  }

  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', function(){});

  const dom = new JSDOM(html, {
    url: 'https://superligafrontier.es/?lang=en',
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    virtualConsole,
    beforeParse(window){
      window.fetch = function(url){
        const u = String(url);
        if (u.indexOf('datos_oficiales.json') !== -1){
          return Promise.resolve({ ok:true, status:200, json:function(){ return Promise.resolve(datos); } });
        }
        if (u.indexOf('translate.googleapis.com') !== -1){
          peticiones++;
          return fetch(u).catch(function(e){ fallos++; throw e; });
        }
        return Promise.reject(new Error('fetch bloqueado: ' + u));
      };
      window.IntersectionObserver = function(){ return { observe(){}, unobserve(){}, disconnect(){} }; };
      window.ResizeObserver = function(){ return { observe(){}, unobserve(){}, disconnect(){} }; };
      window.requestAnimationFrame = function(cb){ cb(2000); return 1; };
      window.matchMedia = function(q){ return { matches:false, media:q, addListener(){}, removeListener(){}, addEventListener(){}, removeEventListener(){} }; };
    }
  });

  const w = dom.window;
  if (w.document.readyState === 'loading'){
    await new Promise(function(r){ w.document.addEventListener('DOMContentLoaded', r); });
  }
  /* La traducción va por red y en lotes; se le da margen de sobra. */
  for (let i = 0; i < 40 && (w.document.documentElement.classList.contains('sf-translating') || i < 6); i++){
    await new Promise(function(r){ setTimeout(r, 500); });
  }

  let n = 0;
  const ok = (m) => { n++; console.log('  ok  ' + m); };
  const txt = (sel) => { const el = w.document.querySelector(sel); return el ? el.textContent.trim() : ''; };

  assert.ok(peticiones > 0, 'el traductor no llegó a pedirse ni una vez');
  ok('la capa de traducción sale a la red (' + peticiones + ' peticiones, ' + fallos + ' fallidas)');

  const bio = txt('[data-i18n="staff.franshu.bio"]');
  assert.ok(bio.indexOf('Creó la liga') < 0, 'la bio del fundador sigue en español: ' + bio.slice(0, 90));
  ok('la descripción del fundador se traduce: ' + bio.slice(0, 70));

  const t1 = txt('[data-i18n="historia.t1"]');
  assert.ok(t1.indexOf('La liga arrancó') < 0, 'la cronología sigue en español: ' + t1.slice(0, 90));
  ok('las descripciones de Construyendo historia se traducen');

  const resena = txt('#quotes blockquote p');
  assert.ok(resena && resena.indexOf('Gracias a esta liga') < 0, 'las reseñas siguen en español: ' + resena.slice(0, 90));
  ok('las reseñas se traducen');

  /* La caché no debe guardar el original como si fuese la traducción: eso era
     lo que dejaba las cadenas congeladas en español para siempre. */
  const cache = JSON.parse(w.localStorage.getItem('sf_at_cache') || '{}');
  const claves = Object.keys(cache).filter(k => k !== '__v');
  const identicas = claves.filter(k => String(cache[k]) === k.replace(/^[a-z]{2}::/, ''));
  assert.ok(claves.length > 10, 'se esperaba una caché con contenido, hay ' + claves.length + ' entradas');
  assert.ok(identicas.length < claves.length * 0.5,
    identicas.length + ' de ' + claves.length + ' entradas de caché son el original sin traducir: ' + JSON.stringify(identicas.slice(0, 4)));
  ok('la caché guarda traducciones de verdad (' + claves.length + ' entradas, ' + identicas.length + ' idénticas al original)');

  console.log('\n' + n + ' comprobaciones OK');
  process.exit(0);
})().catch(function(e){ console.error('\nFALLA:', e.message); process.exit(1); });
