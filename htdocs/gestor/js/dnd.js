/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — dnd.js
   Arrastrar y soltar, una sola vez, para las cinco pantallas que lo usan:
   alineación, calendario, noticias, cuadro de Copa y grupos de Copa.

   POR QUÉ NO LA API NATIVA DE HTML5 (draggable + dragstart): no funciona en
   táctil. Ni en tablet ni en móvil dispara un solo evento de arrastre, y
   `CLAUDE.md` §5.2.4 pide soporte táctil completo. Los eventos de puntero sí
   son uno solo para ratón, dedo y lápiz, así que el módulo se escribe una vez
   y funciona en los tres.

   POR QUÉ NO SortableJS: haría falta por CDN —y el resto del sitio no depende
   de ninguno— y aun así habría que escribir aparte toda la alternativa por
   teclado, que es la parte que de verdad no puede faltar.

   ACCESIBILIDAD: arrastrar NUNCA es la única forma de hacer algo. Cada vista
   que use esto tiene que ofrecer además botones o un selector que hagan lo
   mismo (SC 2.5.7). Este módulo cubre el puntero y las flechas del teclado;
   el resto lo pone la vista.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG;
var UMBRAL = 6;                 // px antes de considerar que es un arrastre y no un clic
var activo = null;

function sinMovimiento(){
  return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/* Contenedores que participan en un mismo grupo de arrastre. `cfg`:
     grupo        nombre lógico; sólo se aceptan sueltas dentro del mismo grupo
     item         selector de los elementos arrastrables
     alSoltar(d)  d = {item, desde, hasta, indice, indiceOriginal}
     puedeSoltar(item, contenedor) opcional, para vetar destinos
   Devuelve una función para desmontarlo. */
function sortable(cfg){
  var contenedores = typeof cfg.contenedores==='string'
    ? Array.prototype.slice.call(document.querySelectorAll(cfg.contenedores))
    : cfg.contenedores;
  if(!contenedores.length) return function(){};

  contenedores.forEach(function(c){
    c.dataset.dndGrupo = cfg.grupo;
    Array.prototype.forEach.call(c.querySelectorAll(cfg.item), function(it){
      it.classList.add('dnd-item');
      /* touch-action:none es lo que impide que el dedo haga scroll de la
         página mientras arrastra. Sin esto, en tablet no se puede arrastrar
         nada hacia abajo. */
      it.style.touchAction = 'none';
      if(!it.hasAttribute('tabindex')) it.tabIndex = 0;
    });
  });

  function alBajar(ev){
    if(ev.button!=null && ev.button!==0) return;          // sólo botón principal
    var it = ev.target.closest(cfg.item);
    if(!it || !it.parentElement || it.parentElement.dataset.dndGrupo!==cfg.grupo) return;
    /* Un control dentro de la tarjeta manda sobre el arrastre: si no, no se
       podría escribir en un input que viva dentro de un elemento movible. */
    if(ev.target.closest('input,select,textarea,button,a')) return;

    activo = {
      cfg:cfg, item:it, origen:it.parentElement,
      indiceOriginal:Array.prototype.indexOf.call(it.parentElement.children, it),
      x0:ev.clientX, y0:ev.clientY, arrancado:false, fantasma:null, hueco:null,
      contenedores:contenedores
    };
    document.addEventListener('pointermove', alMover);
    document.addEventListener('pointerup', alSubir);
    document.addEventListener('pointercancel', cancelar);
  }

  function arrancar(ev){
    var a = activo, r = a.item.getBoundingClientRect();
    a.arrancado = true;
    a.dx = a.x0 - r.left; a.dy = a.y0 - r.top;

    /* Hueco: ocupa el sitio exacto del elemento para que nada salte de
       tamaño mientras se arrastra. */
    a.hueco = document.createElement('div');
    a.hueco.className = 'dnd-hueco';
    a.hueco.style.height = r.height+'px';
    a.hueco.style.width = getComputedStyle(a.origen).display==='flex' ? r.width+'px' : '';

    a.fantasma = a.item.cloneNode(true);
    a.fantasma.className += ' dnd-fantasma';
    a.fantasma.style.width = r.width+'px';
    a.fantasma.style.height = r.height+'px';
    document.body.appendChild(a.fantasma);

    a.item.parentElement.insertBefore(a.hueco, a.item);
    a.item.classList.add('dnd-oculto');
    document.body.classList.add('dnd-arrastrando');
    mover(ev);
  }

  function mover(ev){
    var a = activo;
    a.fantasma.style.transform = 'translate('+(ev.clientX-a.dx)+'px,'+(ev.clientY-a.dy)+'px)';
  }

  function alMover(ev){
    var a = activo; if(!a) return;
    if(!a.arrancado){
      if(Math.abs(ev.clientX-a.x0)<UMBRAL && Math.abs(ev.clientY-a.y0)<UMBRAL) return;
      arrancar(ev);
      return;
    }
    ev.preventDefault();
    mover(ev);

    /* El fantasma tapa el punto exacto bajo el dedo, así que se esconde un
       instante para preguntar qué hay debajo de verdad. */
    a.fantasma.style.display = 'none';
    var bajo = document.elementFromPoint(ev.clientX, ev.clientY);
    a.fantasma.style.display = '';
    if(!bajo) return;

    var destino = bajo.closest('[data-dnd-grupo="'+a.cfg.grupo+'"]');
    if(!destino || a.contenedores.indexOf(destino)<0) return;
    if(a.cfg.puedeSoltar && !a.cfg.puedeSoltar(a.item, destino)) return;

    var ref = referencia(destino, ev.clientX, ev.clientY, a.hueco);
    if(ref!==a.hueco) destino.insertBefore(a.hueco, ref);
    a.contenedores.forEach(function(c){ c.classList.toggle('dnd-encima', c===destino); });
  }

  /* Ante qué hijo hay que insertar: el primero cuyo centro quede después del
     puntero. Se mide en el eje que corresponda según cómo esté colocada la
     lista, para que valga igual en columna que en fila. */
  function referencia(cont, x, y, hueco){
    var horizontal = /flex|grid/.test(getComputedStyle(cont).display) &&
                     getComputedStyle(cont).flexDirection!=='column';
    var hijos = Array.prototype.filter.call(cont.children, function(c){
      return c!==hueco && !c.classList.contains('dnd-oculto') && c.offsetParent!==null;
    });
    for(var i=0;i<hijos.length;i++){
      var r = hijos[i].getBoundingClientRect();
      var centro = horizontal ? r.left+r.width/2 : r.top+r.height/2;
      if((horizontal?x:y) < centro) return hijos[i];
    }
    return null;
  }

  function limpiar(){
    var a = activo; if(!a) return;
    document.removeEventListener('pointermove', alMover);
    document.removeEventListener('pointerup', alSubir);
    document.removeEventListener('pointercancel', cancelar);
    if(a.fantasma) a.fantasma.remove();
    if(a.hueco) a.hueco.remove();
    a.item.classList.remove('dnd-oculto');
    a.contenedores.forEach(function(c){ c.classList.remove('dnd-encima'); });
    document.body.classList.remove('dnd-arrastrando');
    activo = null;
  }
  function cancelar(){ limpiar(); }

  function alSubir(){
    var a = activo; if(!a) return;
    if(!a.arrancado) return limpiar();          // fue un clic, no un arrastre
    var destino = a.hueco.parentElement;
    var indice = Array.prototype.indexOf.call(
      Array.prototype.filter.call(destino.children, function(c){ return c!==a.item; }), a.hueco);
    var datos = {item:a.item, desde:a.origen, hasta:destino, indice:indice, indiceOriginal:a.indiceOriginal};
    var mismo = destino===a.origen && indice===a.indiceOriginal;
    limpiar();
    if(!mismo && a.cfg.alSoltar) a.cfg.alSoltar(datos);
  }

  /* Alternativa por teclado. Las flechas mueven dentro de la lista y, con
     Ctrl, entre listas del mismo grupo. No sustituye a los botones que cada
     vista debe ofrecer, pero hace que el propio elemento sea operable. */
  function alTeclear(ev){
    var it = ev.target.closest && ev.target.closest(cfg.item);
    if(!it || contenedores.indexOf(it.parentElement)<0) return;
    var horizontal = /Left|Right/.test(ev.key);
    if(!/^Arrow(Up|Down|Left|Right)$/.test(ev.key)) return;
    var adelante = ev.key==='ArrowDown' || ev.key==='ArrowRight';

    if(ev.ctrlKey || ev.metaKey || (horizontal && contenedores.length>1)){
      var ci = contenedores.indexOf(it.parentElement) + (adelante?1:-1);
      if(ci<0 || ci>=contenedores.length) return;
      var destino = contenedores[ci];
      if(cfg.puedeSoltar && !cfg.puedeSoltar(it, destino)) return;
      ev.preventDefault();
      var origen = it.parentElement;
      destino.appendChild(it);
      if(cfg.alSoltar) cfg.alSoltar({item:it, desde:origen, hasta:destino,
        indice:destino.children.length-1, indiceOriginal:0, teclado:true});
      it.focus();
      return;
    }
    var i = Array.prototype.indexOf.call(it.parentElement.children, it);
    var j = i + (adelante?1:-1);
    if(j<0 || j>=it.parentElement.children.length) return;
    ev.preventDefault();
    var cont = it.parentElement;
    if(adelante) cont.insertBefore(it, cont.children[j].nextSibling);
    else cont.insertBefore(it, cont.children[j]);
    if(cfg.alSoltar) cfg.alSoltar({item:it, desde:cont, hasta:cont, indice:j, indiceOriginal:i, teclado:true});
    it.focus();
  }

  contenedores.forEach(function(c){
    c.addEventListener('pointerdown', alBajar);
    c.addEventListener('keydown', alTeclear);
  });
  return function(){
    contenedores.forEach(function(c){
      c.removeEventListener('pointerdown', alBajar);
      c.removeEventListener('keydown', alTeclear);
    });
  };
}

/* Soltar archivos de imagen encima de un campo. Devuelve la URL de datos, que
   es lo que se puede guardar en un JSON sin servidor.
   `recortar` deja la imagen cuadrada, que es como la pinta la web en escudos
   y fotos de jugador. */
function zonaImagen(el, alRecibir, opciones){
  opciones = opciones || {};
  ['dragenter','dragover'].forEach(function(t){
    el.addEventListener(t, function(ev){ ev.preventDefault(); el.classList.add('dnd-encima'); });
  });
  ['dragleave','drop'].forEach(function(t){
    el.addEventListener(t, function(){ el.classList.remove('dnd-encima'); });
  });
  el.addEventListener('drop', function(ev){
    ev.preventDefault();
    var f = ev.dataTransfer.files[0];
    if(!f || !/^image\//.test(f.type)) return;
    procesar(f, opciones, alRecibir);
  });
}
function procesar(file, opciones, cb){
  var lector = new FileReader();
  lector.onload = function(){
    if(!opciones.recortar) return cb(lector.result, file);
    var img = new Image();
    img.onload = function(){
      /* Recorte cuadrado centrado y tope de lado. El tamaño importa de verdad:
         esto acaba dentro de datos_oficiales.json, que la web se descarga
         entera en cada visita. Una foto sin tocar son megas; a 256px en WebP
         son unas decenas de KB. Se cae a PNG si el navegador no da WebP. */
      var lado = Math.min(img.width, img.height);
      var salida = Math.min(lado, opciones.max || 256);
      var cv = document.createElement('canvas');
      cv.width = cv.height = salida;
      cv.getContext('2d').drawImage(img, (img.width-lado)/2, (img.height-lado)/2, lado, lado, 0, 0, salida, salida);
      var url = cv.toDataURL('image/webp', 0.85);
      if(url.indexOf('data:image/webp')!==0) url = cv.toDataURL('image/png');
      cb(url, file);
    };
    img.onerror = function(){ cb(lector.result, file); };
    img.src = lector.result;
  };
  lector.readAsDataURL(file);
}

SFG.dnd = {sortable:sortable, zonaImagen:zonaImagen, procesarImagen:procesar, sinMovimiento:sinMovimiento};

})();
