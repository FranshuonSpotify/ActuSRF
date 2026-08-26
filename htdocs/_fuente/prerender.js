/* Pre-renderiza index.html ejecutando el propio app.js (y sus dependencias
   dict.js/faq-dict.js/i18n.js) dentro de un DOM headless (jsdom), con
   datos_oficiales.json ya cargado. El resultado es el MISMO HTML que hoy
   solo aparece tras ejecutar JS en el navegador (clasificación, resultados,
   goleadores, cuadro de Copa, FAQ) — pero ya presente en la respuesta HTTP
   cruda, para rastreadores que no ejecutan JavaScript (auditoría SEO,
   agosto 2026: GPTBot/ClaudeBot/PerplexityBot no ven hoy esas tablas).

   No reimplementa ninguna lógica de renderizado: ejecuta app.js tal cual,
   así que clasificación/goleadores/etc. siguen calculándose con el mismo
   código (y el mismo orderStandings()) que ve un visitante real. Al cargar
   la página real, app.js se vuelve a ejecutar y a repintar todo con datos
   frescos (fetch sin caché) — esto es solo la "foto" inicial.

   Uso: prerender(htmlDelShell, rutaDatosOficiales) -> Promise<htmlFinal> */
const fs = require('fs');
const { JSDOM, VirtualConsole } = require('jsdom');

async function prerender(html, datosPath, lang){
  const datos = JSON.parse(fs.readFileSync(datosPath, 'utf8'));

  const virtualConsole = new VirtualConsole();
  // Los errores de jsdom (APIs no implementadas, scripts externos no
  // cargados a propósito) no deben abortar el build; se listan para poder
  // revisarlos si algo relevante deja de pre-renderizarse.
  const avisos = [];
  virtualConsole.on('jsdomError', function(e){ avisos.push(e.message || String(e)); });

  // i18n.js lee "?lang=" de location.search con prioridad sobre lo guardado
  // (sfIdiomaActual()) — así que basta con abrir la página "como si" viniera
  // de esa URL para que dict.js/faq-dict.js traduzcan el HTML entero, cabecera
  // incluida, exactamente igual que hace un visitante real con ese idioma.
  const url = 'https://superligafrontier.es/' + (lang ? '?lang='+lang : '');

  const dom = new JSDOM(html, {
    url: url,
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    virtualConsole,
    beforeParse(window){
      // Sustituye el fetch real por los datos ya leídos del disco — nada
      // de red durante el build, y sin el "?t="+Date.now() que en el
      // navegador sí hace falta para no servir clasificación cacheada.
      window.fetch = function(url){
        if (String(url).indexOf('datos_oficiales.json') !== -1){
          return Promise.resolve({ ok:true, status:200, json: function(){ return Promise.resolve(datos); } });
        }
        return Promise.reject(new Error('prerender: fetch bloqueado para '+url));
      };
      // jsdom no implementa IntersectionObserver/ResizeObserver (no hay
      // layout real). Los usos en app.js son decorativos (animaciones de
      // scroll, pestaña activa del menú) y están bien como no-op aquí.
      window.IntersectionObserver = function(){ return { observe:function(){}, unobserve:function(){}, disconnect:function(){} }; };
      window.ResizeObserver = function(){ return { observe:function(){}, unobserve:function(){}, disconnect:function(){} }; };
      // countTo() anima números con requestAnimationFrame durante ~1.1s;
      // aquí basta con resolver la animación en dos pasos síncronos para
      // que el marcador final quede pintado sin esperar tiempo real.
      var rafClock = 0;
      window.requestAnimationFrame = function(cb){ rafClock += 2000; cb(rafClock); return rafClock; };
      // jsdom no calcula layout real, así que no expone matchMedia; se usa
      // solo para gustos de interacción (hover fino, reduced-motion) que no
      // aplican en un pre-render estático — false en ambos casos es correcto.
      window.matchMedia = function(query){
        return { matches:false, media:query, addListener:function(){}, removeListener:function(){}, addEventListener:function(){}, removeEventListener:function(){} };
      };
    }
  });

  const { document } = dom.window;
  if (document.readyState === 'loading'){
    await new Promise(function(resolve){ document.addEventListener('DOMContentLoaded', resolve); });
  }
  // Dos vueltas de microtasks/macrotasks para que la cadena
  // fetch().then().then(renderAll) —y cualquier .then() encadenado— termine.
  await new Promise(function(r){ setTimeout(r, 0); });
  await new Promise(function(r){ setTimeout(r, 0); });

  sincronizarFaqSchema(dom.window.document);
  if (lang) autoreferenciarUrl(dom.window.document, lang);

  const resultado = dom.window.document.documentElement.outerHTML;
  dom.window.close();

  const relevantes = avisos.filter(function(a){
    return !/IntersectionObserver|ResizeObserver|Not implemented: HTMLMediaElement|Not implemented: window\.alert|fetch bloqueado/.test(a);
  });
  if (relevantes.length){
    console.warn('[prerender] avisos de jsdom durante el pre-render:');
    relevantes.forEach(function(a){ console.warn('  -', a); });
  }

  return '<!doctype html>\n' + resultado + '\n';
}

/* El FAQPage del JSON-LD estaba escrito a mano en shell.html y no cambiaba
   con el idioma. Aquí se reconstruye a partir del propio #faq-list ya
   renderizado (que sí sale correcto en cada idioma vía faq-dict.js), para
   que el schema coincida siempre con lo que la página muestra de verdad —
   sin mantener una copia aparte que se pueda desincronizar otra vez. */
function sincronizarFaqSchema(document){
  const faqList = document.getElementById('faq-list');
  const scriptTag = document.querySelector('script[type="application/ld+json"]');
  if (!faqList || !scriptTag) return;

  const preguntas = Array.from(faqList.querySelectorAll('.qa')).map(function(qa){
    const pregunta = qa.querySelector('button span');
    const respuesta = qa.querySelector('.qa-a p');
    return {
      '@type': 'Question',
      name: pregunta ? pregunta.textContent.trim() : '',
      acceptedAnswer: { '@type': 'Answer', text: respuesta ? respuesta.textContent.trim() : '' }
    };
  }).filter(function(q){ return q.name && q.acceptedAnswer.text; });
  if (!preguntas.length) return;

  let data;
  try { data = JSON.parse(scriptTag.textContent); } catch(e){ return; }
  const nodos = data['@graph'] || (Array.isArray(data) ? data : [data]);
  const faqNode = nodos.find(function(n){ return n['@type']==='FAQPage'; });
  if (!faqNode) return;

  faqNode.mainEntity = preguntas;
  scriptTag.textContent = JSON.stringify(data, null, 2);
}

/* sfApplyMeta() (i18n.js) ya traduce título/descripción/og:title/og:description
   por idioma, pero deja canonical y og:url siempre en la raíz en español. Un
   hreflang cluster necesita que cada variante se autoreferencie: si todas
   canonicalizan a "/", se le está diciendo a Google que solo indexe la
   española y descarte el resto como duplicados — justo lo contrario de lo
   que hreflang pretende declarar. */
function autoreferenciarUrl(document, lang){
  const url = 'https://superligafrontier.es/?lang='+lang;
  const canon = document.querySelector('link[rel="canonical"]');
  if (canon) canon.setAttribute('href', url);
  const ogUrl = document.querySelector('meta[property="og:url"]');
  if (ogUrl) ogUrl.setAttribute('content', url);
}

module.exports = prerender;
