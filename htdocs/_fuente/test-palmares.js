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
const dictContent = fs.readFileSync(path.join(d, 'dict.js'), 'utf8');
const faqDictContent = fs.readFileSync(path.join(d, 'faq-dict.js'), 'utf8');
const i18nContent = fs.readFileSync(path.join(d, 'i18n.js'), 'utf8');
const appContent = fs.readFileSync(path.join(d, 'app.js'), 'utf8');
const replacement = '<script>\n' + dictContent + '\n</script>\n' +
  '<script>\n' + faqDictContent + '\n</script>\n' +
  '<script>\n' + i18nContent + '\n</script>\n' +
  '<script>\n' + appContent + '\n</script>';
// Handle both Unix (\n) and Windows (\r\n) line endings
html = html.replace(/<script src="i18n\.js"><\/script>\r?\n<script src="app\.js"><\/script>/, replacement);

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
  const zanarkPres = zanark.map(function(x){ return x.presidente; }).sort();
  assert.strictEqual(zanarkPres.length, 3);
  assert.strictEqual(zanarkPres[0], 'D4rkRepulser', 'Primer presidente debe ser D4rkRepulser');
  assert.strictEqual(zanarkPres[1], 'D4rkRepulser', 'Segundo presidente debe ser D4rkRepulser');
  assert.strictEqual(zanarkPres[2], 'DarkRepulser', 'Tercer presidente debe ser DarkRepulser');
  ok('titulosDeEquipo("eq_1777322423802") separa a los dos presidentes de Zanark Domain');

  const d4rk = w.titulosDePresidente('D4rkRepulser');
  assert.strictEqual(d4rk.length, 2, 'D4rkRepulser (Temporada 2) no debe incluir el título de Ascenso de DarkRepulser (Temporada 1)');
  const d4rkComps = d4rk.map(function(x){ return x.comp; }).sort();
  assert.strictEqual(d4rkComps[0], 'Copa Fútbol Frontier', 'Primer título debe ser Copa');
  assert.strictEqual(d4rkComps[1], 'Superliga Frontier', 'Segundo título debe ser Superliga');
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
