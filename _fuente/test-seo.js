/* SEO internacional de las páginas ya construidas: cada idioma se autoreferencia
   (canonical, og:url, og:locale), declara el cluster hreflang completo y
   recíproco, trae título/descripción en su idioma con longitud decente, un solo
   <h1>, JSON-LD válido y FAQ propia; y sitemap.xml lista las mismas URLs.
   Ejecutar DESPUÉS de "node _fuente/build.js".
   Uso:  node _fuente/test-seo.js   (desde htdocs/) */
'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert');
const { JSDOM } = require('jsdom');
const root = path.join(__dirname, '..');
const idiomas = JSON.parse(fs.readFileSync(path.join(__dirname, 'idiomas.json'), 'utf8'));
const SITIO = 'https://superligafrontier.es';
const url = (c) => c === 'es' ? SITIO + '/' : SITIO + '/?lang=' + c;
const esperadosHreflang = idiomas.map((l) => l.code).concat('x-default');
const titulos = new Set(), faqEs = {};
let faqPropias = 0;

for (const l of idiomas) {
  const f = path.join(root, l.code === 'es' ? 'index.html' : l.code + '.html');
  const d = new JSDOM(fs.readFileSync(f, 'utf8')).window.document;
  const ctx = l.code + ': ';
  assert.strictEqual(d.documentElement.lang, l.code, ctx + '<html lang>');
  assert.strictEqual(d.documentElement.dir || 'ltr', l.dir || 'ltr', ctx + 'dir');
  assert.strictEqual(d.querySelector('link[rel=canonical]').href, url(l.code), ctx + 'canonical');
  assert.strictEqual(d.querySelector('meta[property="og:url"]').content, url(l.code), ctx + 'og:url');
  assert.strictEqual(d.querySelector('meta[property="og:locale"]').content, l.locale, ctx + 'og:locale');
  const alt = [...d.querySelectorAll('meta[property="og:locale:alternate"]')].map((m) => m.content);
  assert.ok(alt.indexOf(l.locale) < 0 && alt.length === idiomas.length - 1, ctx + 'og:locale:alternate');
  const hl = [...d.querySelectorAll('link[rel=alternate][hreflang]')];
  assert.deepStrictEqual(hl.map((x) => x.hreflang), esperadosHreflang, ctx + 'hreflang (orden y conjunto)');
  hl.forEach((x) => assert.strictEqual(x.href, x.hreflang === 'x-default' ? SITIO + '/' : url(x.hreflang), ctx + 'href de hreflang ' + x.hreflang));
  assert.ok(d.title.length > 10 && d.title.length <= 65, ctx + 'título de ' + d.title.length + ' caracteres');
  const desc = d.querySelector('meta[name=description]').content;
  assert.ok(desc.length > 60 && desc.length <= 165, ctx + 'descripción de ' + desc.length + ' caracteres');
  assert.strictEqual(d.querySelector('meta[property="og:title"]').content, d.title, ctx + 'og:title = title');
  titulos.add(d.title + '|' + desc);
  assert.strictEqual(d.querySelectorAll('h1').length, 1, ctx + 'un solo h1');
  assert.ok(d.querySelector('main, #contenido, #inicio'), ctx + 'landmark principal');
  // JSON-LD
  const grafo = [];
  d.querySelectorAll('script[type="application/ld+json"]').forEach((s) => { const j = JSON.parse(s.textContent); (j['@graph'] || [j]).forEach((n) => grafo.push(n)); });
  const wp = grafo.find((n) => n['@type'] === 'WebPage');
  assert.strictEqual(wp.inLanguage, l.code, ctx + 'WebPage.inLanguage');
  assert.strictEqual(wp.url, url(l.code), ctx + 'WebPage.url');
  const faq = grafo.find((n) => n['@type'] === 'FAQPage');
  assert.ok(faq && faq.mainEntity.length >= 30, ctx + 'FAQPage con preguntas (' + (faq && faq.mainEntity.length) + ')');
  if (l.code === 'es') faq.mainEntity.forEach((q, i) => { faqEs[i] = q.name; });
  else faqPropias += faq.mainEntity.filter((q, i) => q.name !== faqEs[i]).length > 20 ? 1 : 0;
}
assert.strictEqual(titulos.size, idiomas.length, 'cada idioma tiene su propio título+descripción');
assert.strictEqual(faqPropias, idiomas.length - 1, 'la FAQ de cada idioma está traducida (no es la española)');
console.log('  ok  ' + idiomas.length + ' páginas: canonical, og, hreflang (' + esperadosHreflang.length + ' enlaces), título/descripción, h1, JSON-LD y FAQ propios');

// sitemap.xml: una <url> por idioma con todas las alternativas
const sm = fs.readFileSync(path.join(root, 'sitemap.xml'), 'utf8');
const bloques = sm.split('<url>').slice(1).filter((b) => /hreflang/.test(b));
assert.strictEqual(bloques.length, idiomas.length, 'sitemap: una URL por idioma');
bloques.forEach((b) => {
  const hrefs = [...b.matchAll(/hreflang="([^"]+)" href="([^"]+)"/g)].map((m) => m[1]);
  assert.deepStrictEqual(hrefs, esperadosHreflang, 'sitemap: alternativas completas');
});
assert.ok(idiomas.every((l) => sm.includes('<loc>' + url(l.code) + '</loc>')), 'sitemap: <loc> de cada idioma');
// .htaccess y robots
const ht = fs.readFileSync(path.join(root, '.htaccess'), 'utf8');
const lista = idiomas.filter((l) => l.code !== 'es').map((l) => l.code).join('|');
assert.ok(ht.includes('lang=(' + lista + ')'), '.htaccess: reglas con los idiomas actuales');
assert.ok(/Sitemap: https:\/\/superligafrontier\.es\/sitemap\.xml/.test(fs.readFileSync(path.join(root, 'robots.txt'), 'utf8')), 'robots.txt apunta al sitemap');
assert.ok(fs.existsSync(path.join(root, 'llms.txt')), 'llms.txt existe');
console.log('  ok  sitemap.xml (' + bloques.length + ' URLs recíprocas), .htaccess, robots.txt y llms.txt');
console.log('OK  seo');
