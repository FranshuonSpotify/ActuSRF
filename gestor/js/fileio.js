/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — fileio.js
   Apertura y guardado de datos_oficiales.json.

   Dos caminos, no uno con parches:
   - File System Access API cuando el navegador la ofrece: se guarda encima
     del archivo real, sin diálogo, con Ctrl+S. Es el modo de trabajo.
   - Descarga clásica cuando no: se lee con <input type=file> y se guarda
     bajando el archivo, que hay que mover a mano. Sirve, pero avisa de que
     está en el modo degradado en todo momento.

   La API no existe en Firefox ni Safari, y tampoco funciona abriendo la
   página con doble clic (file:// es un origen opaco y el selector se niega).
   Por eso la detección es por intento real, no por 'showOpenFilePicker' in
   window: la comprobación de existencia da un falso positivo en file://.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG = window.SFG || {};
var C = SFG.core;

var NOMBRE = 'datos_oficiales.json';
var INDENT = 4;              // el archivo real usa 4 espacios; se respeta
var MAX_SNAPS = 5;
var K_SNAPS = 'sfg:snaps';   // índice de copias
var K_SNAP  = 'sfg:snap:';   // contenido de cada copia
var K_ERR   = 'sfg:errores';

var handle = null;      // FileSystemFileHandle cuando hay API
var nombreArchivo = '';
var sucio = false;
var guardando = false;
var ultimoGuardado = null;

/* Aviso de estado a la interfaz. Se usa un evento del DOM en vez de montar
   un emisor propio: la interfaz sólo tiene que escuchar. */
function avisar(){
  document.dispatchEvent(new CustomEvent('sfg:estado', {detail:estado()}));
}
function estado(){
  return {
    cargado: !!SFG.d(),
    sucio: sucio,
    guardando: guardando,
    archivo: nombreArchivo,
    directo: !!handle,           // ¿se escribe encima del archivo real?
    ultimoGuardado: ultimoGuardado
  };
}

function marcarSucio(){
  if(sucio) return;
  sucio = true;
  avisar();
}

/* --------------------------------------------------------------------------
   COPIAS DE SEGURIDAD EN localStorage
   Se guardan minificadas (644 KB frente a 1,4 MB con indentación) porque el
   cupo de localStorage ronda los 5 MB y cada carácter cuenta doble. Si aun
   así no cabe, se van tirando las más viejas hasta que entre: una copia
   reciente vale más que cinco antiguas.
   -------------------------------------------------------------------------- */
function leerIndice(){
  try { return JSON.parse(localStorage.getItem(K_SNAPS)) || []; }
  catch(e){ return []; }
}
function escribirIndice(x){
  try { localStorage.setItem(K_SNAPS, JSON.stringify(x)); } catch(e){}
}
function snapshots(){ return leerIndice(); }

function guardarSnapshot(datos, nota){
  var ts = Date.now();
  var cuerpo = JSON.stringify(datos);
  var idx = leerIndice();
  idx.unshift({ts:ts, nota:nota||'', bytes:cuerpo.length});
  while(idx.length > MAX_SNAPS) borrarSnap(idx.pop().ts);

  for(var intento=0; intento<MAX_SNAPS+1; intento++){
    try {
      localStorage.setItem(K_SNAP+ts, cuerpo);
      escribirIndice(idx);
      return true;
    } catch(e){
      /* Sin cupo: se sacrifica la copia más antigua y se reintenta. Si ni
         siquiera cabe una, se abandona sin romper el guardado — la copia es
         una red, no el objetivo. */
      if(idx.length <= 1){ idx.shift(); escribirIndice(idx); return false; }
      borrarSnap(idx.pop().ts);
    }
  }
  return false;
}
function borrarSnap(ts){ try { localStorage.removeItem(K_SNAP+ts); } catch(e){} }
function leerSnapshot(ts){
  try { var s = localStorage.getItem(K_SNAP+ts); return s ? JSON.parse(s) : null; }
  catch(e){ return null; }
}

/* Registro de errores de guardado, para poder reintentar más tarde. */
function registrarError(msg){
  try {
    var l = JSON.parse(localStorage.getItem(K_ERR)) || [];
    l.unshift({ts:Date.now(), msg:String(msg)});
    localStorage.setItem(K_ERR, JSON.stringify(l.slice(0,20)));
  } catch(e){}
}
function errores(){
  try { return JSON.parse(localStorage.getItem(K_ERR)) || []; } catch(e){ return []; }
}
function limpiarErrores(){ try { localStorage.removeItem(K_ERR); } catch(e){} }

/* --------------------------------------------------------------------------
   CARGA
   -------------------------------------------------------------------------- */
/* Todo lo que entra pasa por aquí, venga del selector nativo o del <input>.
   Devuelve el informe de validación para que la interfaz decida qué contar;
   los errores de esquema abortan la carga, los avisos no. */
function adoptar(texto, nombre){
  var datos;
  try { datos = JSON.parse(texto); }
  catch(e){ throw new Error('El archivo no es JSON válido: ' + e.message); }

  var v = C.validarEsquema(datos);
  if(v.err.length) throw new Error('El archivo no tiene la forma esperada:\n· ' + v.err.join('\n· '));

  C.completarEsquema(datos);
  SFG.setD(datos);
  nombreArchivo = nombre || NOMBRE;
  sucio = false;
  avisar();

  var integridad = C.validarIntegridad(datos);
  return {avisosEsquema:v.avi, integridad:integridad};
}

/* ¿Hay File System Access API utilizable de verdad? Se comprueba en el
   primer uso, no al cargar, porque el selector exige un gesto del usuario. */
function hayApi(){
  return typeof window.showOpenFilePicker === 'function';
}

function abrir(){
  if(!hayApi()) return Promise.reject(new Error('SIN_API'));
  return window.showOpenFilePicker({
    id:'sfg-datos',
    types:[{description:'Datos de la Superliga', accept:{'application/json':['.json']}}],
    multiple:false
  }).then(function(hs){
    var h = hs[0];
    return h.getFile().then(function(f){ return f.text(); }).then(function(t){
      var r = adoptar(t, h.name);
      handle = h;                    // sólo tras leer bien: si falla, no queda enganchado
      avisar();
      return r;
    });
  });
}

/* Camino de respaldo: el usuario elige el archivo con un <input type=file>.
   No hay handle, así que guardar significa descargar. */
function abrirDesdeFichero(file){
  return file.text().then(function(t){
    var r = adoptar(t, file.name);
    handle = null;
    avisar();
    return r;
  });
}

/* --------------------------------------------------------------------------
   GUARDADO
   Secuencia fija: bloquear → normalizar → validar → copia de seguridad →
   escribir. Si la validación encuentra un error crítico no se escribe nada:
   más vale no guardar que dejar el archivo en un estado que rompa la web.
   -------------------------------------------------------------------------- */
function guardar(opciones){
  opciones = opciones || {};
  var d = SFG.d();
  if(!d) return Promise.reject(new Error('No hay datos cargados.'));
  /* Bloqueo contra el doble clic y contra un Ctrl+S mientras ya se escribe. */
  if(guardando) return Promise.reject(new Error('EN_CURSO'));

  guardando = true;
  avisar();

  return Promise.resolve().then(function(){
    var notas = C.normalizar(d);
    var integridad = C.validarIntegridad(d);
    if(integridad.err.length && !opciones.forzar){
      var e = new Error('INTEGRIDAD');
      e.integridad = integridad;
      throw e;
    }
    guardarSnapshot(d, opciones.nota || '');
    var texto = JSON.stringify(d, null, INDENT);
    return (handle ? escribirEnHandle(texto) : descargar(texto)).then(function(){
      sucio = false;
      ultimoGuardado = Date.now();
      limpiarErrores();
      return {notas:notas, integridad:integridad, directo:!!handle};
    });
  }).catch(function(e){
    if(e.message !== 'INTEGRIDAD') registrarError(e.message || e);
    throw e;
  }).then(function(r){
    guardando = false; avisar(); return r;
  }, function(e){
    guardando = false; avisar(); throw e;
  });
}

function escribirEnHandle(texto){
  return handle.createWritable().then(function(w){
    return w.write(texto).then(function(){ return w.close(); });
  });
}

/* Descarga clásica. No confirma nada: el navegador puede haberla bloqueado y
   no hay forma de saberlo, así que la interfaz avisa de que el archivo hay
   que moverlo a su sitio a mano. */
function descargar(texto){
  var blob = new Blob([texto], {type:'application/json'});
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url; a.download = nombreArchivo || NOMBRE;
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  setTimeout(function(){ URL.revokeObjectURL(url); }, 4000);
  return Promise.resolve();
}

/* Elegir dónde guardar: permite pasar del modo descarga al modo directo sin
   recargar, y crear un archivo nuevo desde cero. */
function guardarComo(){
  if(!hayApi()) return guardar();
  return window.showSaveFilePicker({
    id:'sfg-datos',
    suggestedName: nombreArchivo || NOMBRE,
    types:[{description:'Datos de la Superliga', accept:{'application/json':['.json']}}]
  }).then(function(h){
    handle = h; nombreArchivo = h.name; avisar();
    return guardar();
  });
}

/* --------------------------------------------------------------------------
   RESTAURACIÓN
   -------------------------------------------------------------------------- */
function restaurar(ts){
  var datos = leerSnapshot(ts);
  if(!datos) throw new Error('Esa copia ya no está disponible.');
  C.completarEsquema(datos);
  SFG.setD(datos);
  marcarSucio();       // restaurar no guarda: deja el cambio pendiente a propósito
  avisar();
  return datos;
}

/* --------------------------------------------------------------------------
   ATAJOS Y SALVAGUARDAS
   -------------------------------------------------------------------------- */
document.addEventListener('keydown', function(e){
  if((e.ctrlKey||e.metaKey) && !e.shiftKey && !e.altKey && (e.key==='s'||e.key==='S')){
    e.preventDefault();
    if(SFG.d() && !guardando) document.dispatchEvent(new CustomEvent('sfg:guardar'));
  }
});
window.addEventListener('beforeunload', function(e){
  if(!sucio) return;
  e.preventDefault();
  e.returnValue = '';       // exigido por Chrome para que salga el diálogo
});

SFG.io = {
  NOMBRE:NOMBRE,
  hayApi:hayApi, estado:estado, marcarSucio:marcarSucio,
  abrir:abrir, abrirDesdeFichero:abrirDesdeFichero,
  guardar:guardar, guardarComo:guardarComo,
  snapshots:snapshots, restaurar:restaurar,
  errores:errores, limpiarErrores:limpiarErrores
};

})();
