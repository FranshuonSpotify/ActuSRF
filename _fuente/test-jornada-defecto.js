/* Comprueba que Resultados abre por defecto en la primera jornada con algún
   partido sin jugar (y avanza sola a la siguiente en cuanto se completa), en
   vez de saltar siempre a la última jornada del calendario.
   Uso:  node _fuente/test-jornada-defecto.js   (desde htdocs/) */
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

const equipo = (id, nombre) => ({ id, nombre, division: 'SUPERLIGA', pj: 0, g: 0, e: 0, p: 0, gf: 0, gc: 0, pts: 0, jugadores: [] });
const partido = (jornada, local, visitante, fin) => ({
  jornada: String(jornada), fecha: '', estado: fin ? 'FINALIZADO' : 'PENDIENTE',
  local, visitante, goles_l: fin ? 1 : 0, goles_v: 0, detalles: ' / '
});

function datosCon(partidosLiga) {
  return {
    config: { temporada: '4', jornada_actual: '1' }, noticias: [],
    equipos: [equipo('a', 'A'), equipo('b', 'B'), equipo('c', 'C'), equipo('d', 'D')],
    partidos_liga: partidosLiga, partidos_ascenso: [], partidos_copa: [], partidos_torneo: []
  };
}

async function cargar(datos) {
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', function () {});
  const dom = new JSDOM(html, {
    url: 'https://superligafrontier.es/',
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    virtualConsole,
    beforeParse(window) {
      window.fetch = function (url) {
        if (String(url).indexOf('datos_oficiales.json') !== -1) {
          return Promise.resolve({ ok: true, status: 200, json: function () { return Promise.resolve(datos); } });
        }
        return Promise.reject(new Error('fetch bloqueado: ' + url));
      };
      window.IntersectionObserver = function () { return { observe() {}, unobserve() {}, disconnect() {} }; };
      window.ResizeObserver = function () { return { observe() {}, unobserve() {}, disconnect() {} }; };
      window.requestAnimationFrame = function (cb) { return setTimeout(cb, 0); };
      window.matchMedia = function (q) { return { matches: false, media: q, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }; };
    }
  });
  const { document } = dom.window;
  if (document.readyState === 'loading') {
    await new Promise((r) => document.addEventListener('DOMContentLoaded', r));
  }
  for (let i = 0; i < 500 && !dom.window.__renderListo; i++) await new Promise((r) => setTimeout(r, 5));
  assert.ok(dom.window.__renderListo, 'renderAll no terminó');
  return dom;
}

function jLabel(dom) { return dom.window.document.getElementById('j-label').textContent.trim(); }

let n = 0;
const ok = (m) => { n++; console.log('  ok  ' + m); };

(async () => {
  // Jornada 1 sin jugar todavía: se abre en la 1, aunque la 2 ya exista.
  {
    const dom = await cargar(datosCon([partido(1, 'A', 'B', false), partido(2, 'C', 'D', false)]));
    assert.strictEqual(jLabel(dom), 'Jornada 1');
    ok('con la jornada 1 sin jugar, Resultados abre en la 1');
  }
  // Jornada 1 completa, jornada 2 pendiente: salta sola a la 2.
  {
    const dom = await cargar(datosCon([partido(1, 'A', 'B', true), partido(2, 'C', 'D', false)]));
    assert.strictEqual(jLabel(dom), 'Jornada 2');
    ok('al completarse la jornada 1, Resultados avanza solo a la 2');
  }
  // Jornada 1 a medias (un partido sin jugar): sigue en la 1.
  {
    const dom = await cargar(datosCon([partido(1, 'A', 'B', true), partido(1, 'C', 'D', false), partido(2, 'A', 'C', false)]));
    assert.strictEqual(jLabel(dom), 'Jornada 1');
    ok('con la jornada 1 a medias, se queda en la 1, no salta a la 2');
  }
  // Todo jugado: se queda en la última, como antes de este cambio.
  {
    const dom = await cargar(datosCon([partido(1, 'A', 'B', true), partido(2, 'C', 'D', true), partido(3, 'A', 'D', true)]));
    assert.strictEqual(jLabel(dom), 'Jornada 3');
    ok('con todo jugado, se queda en la última jornada');
  }

  console.log('\n' + n + ' comprobaciones OK');
})().catch((e) => { console.error(e); process.exit(1); });
