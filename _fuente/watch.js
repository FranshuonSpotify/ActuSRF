/* Reconstruye index.html automáticamente cada vez que el gestor guarda
   datos_oficiales.json (o que se edita algo en _fuente/), para no tener que
   acordarse de ejecutar "node _fuente/build.js" a mano.
   Uso:  node _fuente/watch.js   (déjalo abierto en una terminal mientras
   trabajas en el gestor) */
const { spawn } = require('child_process');
const fs = require('fs');
const p = require('path');
const d = __dirname, root = p.join(d, '..');

let building = false, pending = false;
function build(){
  if (building){ pending = true; return; }
  building = true;
  const t0 = Date.now();
  const proc = spawn(process.execPath, [p.join(d, 'build.js')], { stdio: 'inherit' });
  proc.on('exit', function(code){
    building = false;
    if (code === 0) console.log('[watch] listo en', (Date.now()-t0)+'ms —', new Date().toLocaleTimeString());
    else console.error('[watch] build.js falló (código', code+')');
    if (pending){ pending = false; build(); }
  });
}

/* Debounce: el gestor puede escribir el archivo más de una vez por guardado
   (p. ej. un editor que hace write+rename), así que se espera un pelín tras
   el último cambio antes de reconstruir. */
let timer = null;
function onChange(label){
  return function(){
    clearTimeout(timer);
    timer = setTimeout(function(){ console.log('[watch] cambio detectado en', label+', reconstruyendo…'); build(); }, 300);
  };
}

fs.watch(p.join(root, 'datos_oficiales.json'), onChange('datos_oficiales.json'));
fs.watch(d, { recursive: true }, function(eventType, filename){
  if (!filename) return;
  if (filename === 'watch.js') return;
  onChange('_fuente/'+filename)();
});

console.log('[watch] vigilando datos_oficiales.json y _fuente/ — Ctrl+C para salir.');
build(); // primera reconstrucción al arrancar, por si algo cambió mientras no vigilaba
