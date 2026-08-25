/* Reconstruye index.html inlineando CSS + JS de esta carpeta.
   Uso:  node _fuente/build.js   (desde propuesta-web/) */
const fs=require('fs'), p=require('path');
const d=__dirname, root=p.join(d,'..');
let html=fs.readFileSync(p.join(d,'shell.html'),'utf8');
html=html.replace('<link rel="stylesheet" href="styles.css">','<style>\n'+fs.readFileSync(p.join(d,'styles.css'),'utf8')+'\n</style>');
/* dict.js va ANTES que i18n.js: éste lo funde sobre el diccionario heredado
   al cargar, así que tiene que existir ya cuando i18n.js se evalúa. */
html=html.replace('<script src="i18n.js"></script>\n<script src="app.js"></script>',
  '<script>\n'+fs.readFileSync(p.join(d,'dict.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'faq-dict.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'i18n.js'),'utf8')+'\n</script>\n'+
  '<script>\n'+fs.readFileSync(p.join(d,'app.js'),'utf8')+'\n</script>');
fs.writeFileSync(p.join(root,'index.html'),html);
['404.html','terminos.html'].forEach(f=>{
  let h=fs.readFileSync(p.join(root,f),'utf8');
  h=h.replace(/<style>[\s\S]*?<\/style>/,'<style>\n'+fs.readFileSync(p.join(d,'styles.css'),'utf8')+'\n</style>');
  fs.writeFileSync(p.join(root,f),h);
});
console.log('index.html regenerado:',html.length,'bytes');
