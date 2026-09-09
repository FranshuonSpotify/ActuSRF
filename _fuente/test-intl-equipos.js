/* Comprueba que el nombre de club en español solo se ve en español y que en el
   resto de idiomas manda nombre_en/abreviatura_en (con caída al español si el
   club aún no está traducido). Carga los datos reales y traduce dos clubes al
   vuelo, en memoria: no toca datos_oficiales.json.
   Uso:  node _fuente/test-intl-equipos.js   (desde htdocs/) */
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

/* Dos casos que hay que distinguir: uno traducido del todo (nombre + sigla) y
   otro traducido a medias, para ver que la sigla cae a la española. */
const A = datos.equipos[0], B = datos.equipos[1], C = datos.equipos[2];
A.nombre_en = 'Test United FC';   A.abreviatura_en = 'TUF';
B.nombre_en = 'Test Rovers';      B.abreviatura_en = '';
if (!B.abreviatura) B.abreviatura = 'TRV';
/* Hoy los 43 clubes tienen nombre_en, así que el club sin traducir hay que
   fabricarlo: la caída al español tiene que seguir funcionando para cuando
   se dé de alta uno nuevo. */
C.nombre_en = '';                 C.abreviatura_en = '';

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
      return Promise.reject(new Error('test-intl-equipos: fetch bloqueado para ' + url));
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
  const lang = (c) => { try { w.localStorage.setItem('sf_lang', c); } catch(e){} };

  lang('es');
  assert.strictEqual(w.SFX(A.nombre), A.nombre, 'en español se muestra el nombre español');
  assert.strictEqual(w.abbr3(A.nombre, A.abreviatura), (A.abreviatura || w.abbr3(A.nombre)).toUpperCase().slice(0,3), 'en español manda la sigla española');
  ok('en español no cambia nada');

  lang('en');
  assert.strictEqual(w.SFX(A.nombre), 'Test United FC', 'en inglés se muestra nombre_en');
  assert.strictEqual(w.abbr3(A.nombre, A.abreviatura), 'TUF', 'en inglés manda abreviatura_en');
  ok('en inglés salen nombre_en y abreviatura_en');

  lang('fr');
  assert.strictEqual(w.SFX(A.nombre), 'Test United FC', 'el nombre inglés vale para todos los idiomas que no son el español');
  ok('el francés (y por tanto el resto) hereda el nombre inglés');

  lang('en');
  assert.strictEqual(w.SFX(B.nombre), 'Test Rovers', 'nombre_en sin sigla propia sigue traduciendo el nombre');
  assert.strictEqual(w.abbr3(B.nombre, B.abreviatura), B.abreviatura.toUpperCase().slice(0,3), 'sin abreviatura_en cae a la española');
  ok('sin abreviatura_en cae a la sigla española');

  const sinTraducir = C;
  assert.strictEqual(w.SFX(sinTraducir.nombre), sinTraducir.nombre, 'un club sin nombre_en se queda con su nombre español en inglés');
  ok('un club sin traducir conserva su nombre español');

  lang('sr');
  const srA = w.SFX(A.nombre);
  assert.ok(/[Ѐ-ӿ]/.test(srA), 'en serbio el nombre inglés se translitera a cirílico, no se deja en latino: ' + srA);
  assert.notStrictEqual(srA, w.SFX(sinTraducir.nombre), 'la transliteración parte del nombre inglés, no del español');
  ok('en serbio se translitera el nombre inglés (' + srA + ')');

  lang('es');
  assert.strictEqual(w.SFX('Un texto que no es ningún club'), 'Un texto que no es ningún club', 'el texto que no es un club pasa intacto');
  lang('en');
  assert.strictEqual(w.SFX('Un texto que no es ningún club'), 'Un texto que no es ningún club', 'el texto que no es un club pasa intacto en inglés');
  ok('el texto que no es nombre de club no se toca');

  console.log('\n' + n + ' comprobaciones OK');
  lang('es');
})().catch(function(e){ console.error('\nFALLA:', e.message); process.exit(1); });
