/* Reconstruye index.html inlineando CSS + JS de esta carpeta, y pre-renderiza
   las tablas de datos (clasificación, resultados, goleadores, Copa, FAQ) para
   que ya vengan rellenas en el HTML crudo — ver prerender.js.
   Uso:  node _fuente/build.js   (desde propuesta-web/)

   Fuente única de idiomas: _fuente/idiomas.json. De ahí salen los {lang}.html,
   el bloque hreflang y og:locale del <head>, sitemap.xml y las reglas de
   .htaccess. SF_LANGS de i18n.js (que corre en el navegador y no puede leer el
   JSON) se comprueba contra él: si se desincronizan, el build falla en vez de
   publicar un selector con idiomas que no existen. */
const fs=require('fs'), p=require('path');
const prerender=require('./prerender.js');
const d=__dirname, root=p.join(d,'..');
const SITIO='https://superligafrontier.es';
const idiomas=JSON.parse(fs.readFileSync(p.join(d,'idiomas.json'),'utf8'));
const IDIOMAS=idiomas.map(function(l){ return l.code; }).filter(function(c){ return c!=='es'; });

/* i18n.js declara SF_LANGS a mano: mismo orden y mismos códigos que el JSON. */
(function comprobarSincronia(){
  const src=fs.readFileSync(p.join(d,'i18n.js'),'utf8');
  const m=/var SF_LANGS = (\[\[.*\]\]);/.exec(src);
  const enJs=m?JSON.parse(m[1]).map(function(l){ return l[0]; }).join(','):'';
  const enJson=idiomas.map(function(l){ return l.code; }).join(',');
  if(enJs!==enJson) throw new Error('SF_LANGS (i18n.js) e idiomas.json no coinciden:\n  js:   '+enJs+'\n  json: '+enJson);
})();

/* shell.html se guardó alguna vez con BOM (UTF-8 con marca de orden de
   bytes) — un navegador real lo descarta al decodificar el HTTP y nunca se
   nota, pero si se deja en el string que le pasamos a jsdom para
   pre-renderizar, su parser HTML lo trata como contenido antes del
   <!DOCTYPE> y coloca todo el <head> dentro de <body> por error. Se quita
   aquí, en el único sitio donde se lee el fichero para el build. */
let html=fs.readFileSync(p.join(d,'shell.html'),'utf8').replace(/^﻿/, '');
html=html.replace('<link rel="stylesheet" href="styles.css">','<style>\n'+fs.readFileSync(p.join(d,'styles.css'),'utf8')+'\n</style>');

/* Bloques del <head> que dependen de la lista de idiomas. */
function bloque(ini,fin,contenido){
  const a=html.indexOf(ini), b=html.indexOf(fin);
  if(a<0||b<a) throw new Error('faltan los marcadores '+ini);
  html=html.slice(0,a+ini.length)+'\n'+contenido+'\n'+html.slice(b);
}
function urlDe(c){ return c==='es' ? SITIO+'/' : SITIO+'/?lang='+c; }
bloque('<!-- SF-HREFLANG -->','<!-- /SF-HREFLANG -->',
  idiomas.map(function(l){ return '<link rel="alternate" hreflang="'+l.code+'" href="'+urlDe(l.code)+'">'; }).join('\n')+
  '\n<link rel="alternate" hreflang="x-default" href="'+SITIO+'/">');
bloque('<!-- SF-OGLOC -->','<!-- /SF-OGLOC -->',
  idiomas.filter(function(l){ return l.code!=='es'; })
    .map(function(l){ return '<meta property="og:locale:alternate" content="'+l.locale+'">'; }).join('\n'));

/* dict.js va ANTES que i18n.js: éste lo funde sobre el diccionario heredado
   al cargar, así que tiene que existir ya cuando i18n.js se evalúa. lang/*.js
   son los diccionarios por idioma (uno por fichero) y sus metadatos. */
const dirLang=p.join(d,'lang');
const ficherosLang=fs.existsSync(dirLang)?fs.readdirSync(dirLang).filter(function(f){ return /\.js$/.test(f); }).sort():[];
/* Solo viaja el diccionario del idioma de CADA página: los metadatos (_meta) y
   los ficheros que funden (zz_) van en todas; lang/<código>.js, solo en
   <código>.html. Ver "DICCIONARIOS POR PÁGINA" en i18n.js. */
const MARCA_LANG='<!--SF-LANG-DICT-->';
function scriptsDe(lang){
  return ficherosLang.filter(function(f){ return /^(_|zz_)/.test(f) || f===lang+'.js'; })
    .map(function(f){ return '<script>\n'+fs.readFileSync(p.join(dirLang,f),'utf8')+'\n</script>\n'; }).join('');
}
const antes=html;
html=html.replace(/<script src="i18n\.js"><\/script>\r?\n<script src="app\.js"><\/script>/,
  '<script>\n'+fs.readFileSync(p.join(d,'dict.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'faq-dict.js'),'utf8')+'\n</script>\n'+
  MARCA_LANG+
  '<script>\n'+fs.readFileSync(p.join(d,'i18n.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'app.js'),'utf8')+'\n</script>');
if(html===antes) throw new Error('shell.html: no se encontró el par <script src="i18n.js"> + <script src="app.js">');

const datosPath=p.join(root,'datos_oficiales.json');

/* sitemap.xml: una <url> por idioma, cada una con TODAS las alternativas
   (incluida ella misma), que es lo que exige hreflang para ser recíproco.
   lastmod = última modificación real de los datos, no la fecha del build. */
function escribirSitemap(){
  const lastmod=fs.statSync(datosPath).mtime.toISOString().slice(0,10);
  const alternos=idiomas.map(function(l){ return '    <xhtml:link rel="alternate" hreflang="'+l.code+'" href="'+urlDe(l.code)+'" />'; }).join('\n')+
    '\n    <xhtml:link rel="alternate" hreflang="x-default" href="'+SITIO+'/" />';
  const urls=idiomas.map(function(l){
    return '  <url>\n    <loc>'+urlDe(l.code)+'</loc>\n    <lastmod>'+lastmod+'</lastmod>\n'+alternos+'\n  </url>';
  }).join('\n\n');
  let terminosMod='2026-08-26';
  try{ terminosMod=fs.statSync(p.join(root,'terminos.html')).mtime.toISOString().slice(0,10); }catch(e){}
  fs.writeFileSync(p.join(root,'sitemap.xml'),
    '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"\n        xmlns:xhtml="http://www.w3.org/1999/xhtml">\n\n'+
    urls+'\n\n  <url>\n    <loc>'+SITIO+'/terminos</loc>\n    <lastmod>'+terminosMod+'</lastmod>\n  </url>\n\n</urlset>\n');
  console.log('sitemap.xml regenerado:',idiomas.length,'idiomas');
}

/* .htaccess: las dos reglas que sirven {lang}.html bajo la URL /?lang=xx
   llevan la lista de códigos; se mantiene aquí para no editarla a mano. */
function actualizarHtaccess(){
  const f=p.join(root,'.htaccess');
  const h=fs.readFileSync(f,'utf8');
  const lista=IDIOMAS.join('|');
  const nuevo=h.replace(/(RewriteCond [^\n]*?)\((?:[a-z]{2}\|)+[a-z]{2}\)/g,function(m,pre){ return pre+'('+lista+')'; });
  if(nuevo!==h){ fs.writeFileSync(f,nuevo); console.log('.htaccess: lista de idiomas actualizada'); }
}

async function run(){
  const opciones={ idiomas: idiomas };
  const esFinal=await prerender(html.replace(MARCA_LANG,scriptsDe('es')), datosPath, null, opciones);
  fs.writeFileSync(p.join(root,'index.html'),esFinal);
  console.log('index.html regenerado (pre-renderizado):',esFinal.length,'bytes');

  for (const lang of IDIOMAS){
    const htmlLang=await prerender(html.replace(MARCA_LANG,scriptsDe(lang)), datosPath, lang, opciones);
    fs.writeFileSync(p.join(root,lang+'.html'),htmlLang);
    console.log(lang+'.html regenerado (pre-renderizado):',htmlLang.length,'bytes');
  }
  escribirSitemap();
  actualizarHtaccess();

  /* terminos.html marca con <!-- SF-I18N --> dónde va el motor de idiomas.
     Se inlinea en vez de enlazarlo con src porque el despliegue sube solo los
     .html: un <script src="_fuente/i18n.js"> serviría la copia que hubiera en
     el hosting, congelada desde la última subida manual. Los marcadores
     sobreviven al reemplazo, así que el build siguiente vuelve a refrescar el
     contenido — el fichero es a la vez fuente y resultado, igual que pasa con
     el <style> de la línea de arriba. */
  const I18N_INI='<!-- SF-I18N -->', I18N_FIN='<!-- /SF-I18N -->';
  ['404.html','terminos.html'].forEach(function(f){
    let h=fs.readFileSync(p.join(root,f),'utf8');
    h=h.replace(/<style>[\s\S]*?<\/style>/,'<style>\n'+fs.readFileSync(p.join(d,'styles.css'),'utf8')+'\n</style>');
    const ini=h.indexOf(I18N_INI), fin=h.indexOf(I18N_FIN);
    if(ini>=0 && fin>ini){
      h=h.slice(0,ini)+I18N_INI+'\n<script>\n'+fs.readFileSync(p.join(d,'i18n.js'),'utf8')+'\n</script>\n'+h.slice(fin);
    }
    fs.writeFileSync(p.join(root,f),h);
    console.log(f+' actualizado:',h.length,'bytes');
  });
}

run().catch(function(e){
  console.error('Fallo al pre-renderizar index.html:',e);
  process.exit(1);
});
