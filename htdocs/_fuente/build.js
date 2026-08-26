/* Reconstruye index.html inlineando CSS + JS de esta carpeta, y pre-renderiza
   las tablas de datos (clasificación, resultados, goleadores, Copa, FAQ) para
   que ya vengan rellenas en el HTML crudo — ver prerender.js.
   Uso:  node _fuente/build.js   (desde propuesta-web/) */
const fs=require('fs'), p=require('path');
const prerender=require('./prerender.js');
const d=__dirname, root=p.join(d,'..');
/* shell.html se guardó alguna vez con BOM (UTF-8 con marca de orden de
   bytes) — un navegador real lo descarta al decodificar el HTTP y nunca se
   nota, pero si se deja en el string que le pasamos a jsdom para
   pre-renderizar, su parser HTML lo trata como contenido antes del
   <!DOCTYPE> y coloca todo el <head> dentro de <body> por error. Se quita
   aquí, en el único sitio donde se lee el fichero para el build. */
let html=fs.readFileSync(p.join(d,'shell.html'),'utf8').replace(/^﻿/, '');
html=html.replace('<link rel="stylesheet" href="styles.css">','<style>\n'+fs.readFileSync(p.join(d,'styles.css'),'utf8')+'\n</style>');
/* dict.js va ANTES que i18n.js: éste lo funde sobre el diccionario heredado
   al cargar, así que tiene que existir ya cuando i18n.js se evalúa. */
html=html.replace('<script src="i18n.js"></script>\n<script src="app.js"></script>',
  '<script>\n'+fs.readFileSync(p.join(d,'dict.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'faq-dict.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'i18n.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'app.js'),'utf8')+'\n</script>');

/* Idiomas con traducción real en dict.js/faq-dict.js (español aparte, es el
   HTML por defecto). Cada uno se pre-renderiza como fichero propio en la raíz
   ({lang}.html); .htaccess reescribe internamente "/?lang=XX" a ese fichero
   para que la URL siga siendo la que ya declaran sitemap.xml y el hreflang
   del <head> — ver comentario en .htaccess. */
const IDIOMAS=['en','pt','it','fr','ja','ko','pl','bg','sr'];
const datosPath=p.join(root,'datos_oficiales.json');

async function run(){
  const esFinal=await prerender(html, datosPath, null);
  fs.writeFileSync(p.join(root,'index.html'),esFinal);
  console.log('index.html regenerado (pre-renderizado):',esFinal.length,'bytes');

  for (const lang of IDIOMAS){
    const htmlLang=await prerender(html, datosPath, lang);
    fs.writeFileSync(p.join(root,lang+'.html'),htmlLang);
    console.log(lang+'.html regenerado (pre-renderizado):',htmlLang.length,'bytes');
  }

  ['404.html','terminos.html'].forEach(function(f){
    let h=fs.readFileSync(p.join(root,f),'utf8');
    h=h.replace(/<style>[\s\S]*?<\/style>/,'<style>\n'+fs.readFileSync(p.join(d,'styles.css'),'utf8')+'\n</style>');
    fs.writeFileSync(p.join(root,f),h);
  });
}

run().catch(function(e){
  console.error('Fallo al pre-renderizar index.html:',e);
  process.exit(1);
});
