/* Idiomas de la web: (1) el selector de i18n.js y idiomas.json dicen lo mismo,
   (2) los 20 idiomas con diccionario propio (lang/*.js) cubren TODAS las claves
   que usa la web, con sus placeholders, y (3) la detección del idioma del
   navegador elige bien y deja a los rastreadores en español.
   Uso:  node _fuente/test-idiomas.js   (desde htdocs/) */
'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert');
const { JSDOM } = require('jsdom');
const D = __dirname;
const idiomas = JSON.parse(fs.readFileSync(path.join(D, 'idiomas.json'), 'utf8'));
const leer = (f) => fs.readFileSync(path.join(D, f), 'utf8');

/* El mismo conjunto de claves que usan shell.html y app.js. Las 6 de abajo son
   contenedores/rótulos dinámicos que nunca han tenido diccionario (los traduce
   el traductor automático) y no cuentan. */
const SIN_DICCIONARIO = ['historia.t1', 'historia.t2', 'historia.t3', 'historia.t4', 'staff.franshu.bio', 'staff.d4rk.rol'];
const claves = new Set();
for (const m of leer('shell.html').matchAll(/data-i18n(?:-placeholder)?="([^"]+)"/g)) claves.add(m[1]);
for (const m of leer('app.js').matchAll(/\bT\('([^']+)'/g)) claves.add(m[1]);
const dinamicas = ['pos1.', 'pos.abbr.', 'pos.', 'faq.c', 'faq.q', 'faq.a', 'resenas.rol.', 'pres.desconocido'];
for (const k of ['hist.pj','zone.playin.part','staff.fundador','staff.admin','staff.mod','scroll','br.grupo1','br.grupo2']) claves.add(k);
const usadas = [...claves].filter((k) => SIN_DICCIONARIO.indexOf(k) < 0 && dinamicas.indexOf(k) < 0);

function cargar(ua, langs, guardado) {
  const dom = new JSDOM('<!doctype html><html><body></body></html>', {
    url: 'https://superligafrontier.es/', runScripts: 'outside-only', pretendToBeVisual: true,
    beforeParse(w) {
      Object.defineProperty(w.navigator, 'userAgent', { value: ua });
      Object.defineProperty(w.navigator, 'languages', { value: langs });
      w.matchMedia = () => ({ matches: false, addListener() {}, removeListener() {} });
      if (guardado) w.localStorage.setItem('sf_lang', guardado);
    }
  });
  const w = dom.window;
  ['dict.js', 'faq-dict.js'].forEach((f) => w.eval(leer(f)));
  fs.readdirSync(path.join(D, 'lang')).filter((f) => /\.js$/.test(f)).sort().forEach((f) => w.eval(leer('lang/' + f)));
  w.eval(leer('i18n.js'));
  return w;
}

// 1) selector e idiomas.json
const w0 = cargar('Mozilla/5.0 Chrome/120', ['es-ES']);
const enJs = JSON.parse(/var SF_LANGS = (\[\[.*\]\]);/.exec(leer('i18n.js'))[1]).map((l) => l[0]);
assert.deepStrictEqual(enJs, idiomas.map((l) => l.code));

// 2) cobertura: cada idioma con diccionario propio resuelve todas las claves
const dic = new Proxy({}, { get: (_, c) => w0.sfDict(c) });
const propios = fs.readdirSync(path.join(D, 'lang')).map((f) => f.replace('.js', '')).filter((c) => idiomas.some((l) => l.code === c));
assert.strictEqual(propios.length, 20, 'esperaba 20 idiomas con diccionario propio, hay ' + propios.length);
for (const c of propios.concat(['en', 'pt', 'it', 'fr', 'ja', 'ko', 'pl', 'bg', 'sr'])) {
  const faltan = usadas.filter((k) => dic[c][k] === undefined);
  assert.strictEqual(faltan.length, 0, c + ' sin traducir: ' + faltan.slice(0, 6).join(', '));
}
for (const c of propios) {
  ['clas.sancion', 'br.grupo1', 'br.grupo2'].forEach((k) => {
    if (dic.es[k] === undefined) return;
    ['{n}', '{g}'].forEach((p) => assert.strictEqual((dic[c][k] || '').includes(p), (dic.es[k] || '').includes(p), c + ' ' + k + ' ' + p));
  });
}
console.log('  ok  selector = idiomas.json; 29 idiomas cubren las ' + usadas.length + ' claves de la web');

// 3) detección del idioma del navegador
const det = (ua, langs, guardado) => cargar(ua, langs, guardado).sfGetLang();
const CH = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36';
assert.strictEqual(det(CH, ['es-ES', 'en']), 'es');
assert.strictEqual(det(CH, ['de-DE', 'en']), 'de');
assert.strictEqual(det(CH, ['pt-BR']), 'pt');
assert.strictEqual(det(CH, ['zh-TW']), 'zh');
assert.strictEqual(det(CH, ['fil-PH']), 'tl');
assert.strictEqual(det(CH, ['ca-ES']), 'es');     // catalán → español (no hay versión propia)
assert.strictEqual(det(CH, ['eu']), 'es');
assert.strictEqual(det(CH, ['gl-ES']), 'gl');
assert.strictEqual(det(CH, ['no', 'sw']), 'en');  // idioma sin versión → inglés
assert.strictEqual(det(CH, ['fr-FR'], 'ja'), 'ja'); // lo guardado manda
assert.strictEqual(det('Mozilla/5.0 (compatible; Googlebot/2.1)', ['en-US']), 'es');
assert.strictEqual(det('Mozilla/5.0 (jsdom/29)', ['en-US']), 'es');
console.log('  ok  detección: idioma del navegador, alias y rastreadores en español');
console.log('OK  idiomas');
