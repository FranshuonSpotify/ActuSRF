/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — presentacion.js
   Modo presentación a pantalla completa y cuadro de Copa con zoom.

   Las dos son vistas de sólo lectura pensadas para proyectar: no tienen
   controles de edición a propósito, porque se usan delante de gente.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var PASOS = ['SUPERLIGA', 'ASCENSO', 'resultados', 'goleadores', 'noticias'];
var paso = 0, reloj = null, SEG = 12;

function d(){ return SFG.d(); }
function el(){ return document.getElementById('tv'); }

/* --------------------------------------------------------------------------
   MODO PRESENTACIÓN
   -------------------------------------------------------------------------- */
function abrir(){
  if(!SFG.d()) return;
  paso = 0;
  el().classList.add('on');
  document.body.classList.add('tv-activo');
  pintar();
  arrancar();
  /* Pantalla completa si el navegador deja; si no, el overlay ya ocupa todo. */
  var raiz = document.documentElement;
  if(raiz.requestFullscreen) raiz.requestFullscreen().catch(function(){});
  el().focus();
}
function cerrar(){
  el().classList.remove('on');
  document.body.classList.remove('tv-activo');
  parar();
  if(document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function(){});
}
function arrancar(){
  parar();
  /* Con reducción de movimiento activa no se rota solo: quien la pide no
     quiere que la pantalla cambie por su cuenta. Se avanza a mano. */
  if(SFG.dnd.sinMovimiento()) return;
  reloj = setInterval(function(){ mover(1); }, SEG*1000);
}
function parar(){ if(reloj){ clearInterval(reloj); reloj = null; } }
function mover(n){
  paso = (paso + n + PASOS.length) % PASOS.length;
  pintar();
  arrancar();          // reinicia la cuenta al mover a mano
}

function pintar(){
  var p = PASOS[paso];
  var cuerpo = (p==='SUPERLIGA' || p==='ASCENSO') ? tabla(p)
    : p==='resultados' ? resultados()
    : p==='goleadores' ? goleadores()
    : noticias();

  el().innerHTML =
    '<div class="tv-barra">'+
      '<span class="tv-marca">'+esc(d().config.nombre_liga||'Superliga Frontier')+
        '<span> · Temporada '+esc(d().config.temporada||'?')+'</span></span>'+
      '<div class="tv-puntos" role="tablist">'+
        PASOS.map(function(_, i){ return '<button class="tv-punto'+(i===paso?' on':'')+'" data-a="tv:ir" data-i="'+i+'" '+
          'aria-label="Pantalla '+(i+1)+' de '+PASOS.length+'"></button>'; }).join('')+
      '</div>'+
      '<div class="tv-ctrl">'+
        '<button class="btn-icon" data-a="tv:prev" aria-label="Anterior"><i class="ph ph-caret-left"></i></button>'+
        '<button class="btn-icon" data-a="tv:next" aria-label="Siguiente"><i class="ph ph-caret-right"></i></button>'+
        '<button class="btn btn-secondary btn-sm" data-a="tv:cerrar">Salir <span class="mono">Esc</span></button>'+
      '</div>'+
    '</div>'+
    '<div class="tv-cuerpo">'+cuerpo+'</div>'+
    (reloj ? '<div class="tv-progreso"><i style="animation-duration:'+SEG+'s"></i></div>' : '');
}

function tabla(div){
  var ord = C.clasificacion(div);
  var z = C.ZONAS_APP[div] || {};
  return '<h2 class="tv-tit">'+(div==='SUPERLIGA'?'Superliga Frontier':'Ascenso Frontier')+'</h2>'+
    '<table class="tv-tabla"><thead><tr><th>#</th><th>Equipo</th><th>PJ</th><th>G</th><th>E</th><th>P</th><th>GF</th><th>GC</th><th>DG</th><th>Pts</th></tr></thead><tbody>'+
    ord.map(function(e, i){
      var pos = i+1, dg = (e.gf||0)-(e.gc||0), marca = '';
      /* Los mismos cortes que pinta la web, que los tiene fijos en su código. */
      if(div==='SUPERLIGA'){
        if(pos<=z.playoff) marca='po'; else if(pos===z.playin) marca='pi';
        else if(pos<=z.partido_playin) marca='pp'; else if(pos>ord.length-z.descenso) marca='desc';
      } else if(pos<=z.ascenso) marca='asc';
      return '<tr class="'+(marca?'z-'+marca:'')+'">'+
        '<td class="tv-pos">'+pos+'</td>'+
        '<td class="tv-eq">'+U.escudo(e)+'<span>'+esc(e.nombre)+'</span></td>'+
        '<td>'+(e.pj||0)+'</td><td>'+(e.g||0)+'</td><td>'+(e.e||0)+'</td><td>'+(e.p||0)+'</td>'+
        '<td>'+(e.gf||0)+'</td><td>'+(e.gc||0)+'</td><td>'+(dg>0?'+':'')+dg+'</td>'+
        '<td class="tv-pts">'+(e.pts||0)+'</td></tr>';
    }).join('')+'</tbody></table>';
}

function resultados(){
  /* La última jornada con algún resultado: es la que interesa enseñar. */
  var todos = d().partidos_liga.concat(d().partidos_ascenso);
  var maxJ = todos.filter(C.isFin).reduce(function(m,p){ return Math.max(m, parseInt(p.jornada)||0); }, 0);
  var ms = todos.filter(function(p){ return (parseInt(p.jornada)||0)===maxJ; });
  return '<h2 class="tv-tit">Jornada '+maxJ+'</h2>'+
    (ms.length
      ? '<div class="tv-rejilla">'+ms.map(function(p){
          var fin = C.isFin(p);
          return '<div class="tv-partido">'+
            '<div class="tv-lado">'+U.escudo(C.equipo(p.local))+'<span>'+esc(p.local||'?')+'</span></div>'+
            '<div class="tv-marcador">'+(fin ? C.gl(p)+'<i>–</i>'+C.gv(p) : '<span class="tv-vs">VS</span>')+'</div>'+
            '<div class="tv-lado">'+U.escudo(C.equipo(p.visitante))+'<span>'+esc(p.visitante||'?')+'</span></div>'+
          '</div>';
        }).join('')+'</div>'
      : '<p class="tv-vacio">Todavía no hay resultados.</p>');
}

function goleadores(){
  var top = C.calcScorers(d().partidos_liga.concat(d().partidos_ascenso, d().partidos_copa).filter(C.isFin)).slice(0,12);
  return '<h2 class="tv-tit">Goleadores</h2>'+
    (top.length
      ? '<table class="tv-tabla"><tbody>'+top.map(function(r,i){
          return '<tr><td class="tv-pos">'+(i+1)+'</td>'+
            '<td class="tv-eq">'+(r.e?U.escudo(r.e):'')+'<span>'+esc(r.nombre)+'</span></td>'+
            '<td style="color:var(--ink-3)">'+esc(r.e?r.e.nombre:'—')+'</td>'+
            '<td class="tv-pts">'+r.goles+'</td></tr>';
        }).join('')+'</tbody></table>'
      : '<p class="tv-vacio">Sin goles registrados.</p>');
}

function noticias(){
  var ns = (d().noticias||[]).slice(0,3);
  return '<h2 class="tv-tit">Prensa</h2>'+
    (ns.length
      ? '<div class="tv-rejilla">'+ns.map(function(n){
          return '<article class="tv-noticia">'+
            (/^(https?:|data:)/.test(n.imagen||'') ? '<img src="'+esc(n.imagen)+'" alt="" referrerpolicy="no-referrer">' : '')+
            '<span class="pastilla" style="background:'+esc(n.color||'#333')+'22;color:'+esc(n.color||'#999')+'">'+esc(n.tag||'')+'</span>'+
            '<h3>'+esc(n.titulo||'')+'</h3><p>'+esc(n.resumen||'')+'</p></article>';
        }).join('')+'</div>'
      : '<p class="tv-vacio">Sin noticias publicadas.</p>');
}

/* --------------------------------------------------------------------------
   CUADRO DE COPA EN GRANDE, CON ZOOM Y ARRASTRE
   -------------------------------------------------------------------------- */
var vista = {z:1, x:0, y:0};

function cine(){
  if(!SFG.d()) return;
  vista = {z:1, x:0, y:0};
  el().classList.add('on');
  document.body.classList.add('tv-activo');
  parar();
  el().innerHTML =
    '<div class="tv-barra">'+
      '<span class="tv-marca">Copa Fútbol Frontier</span>'+
      '<div class="tv-ctrl">'+
        '<button class="btn-icon" data-a="tv:zoom" data-v="-1" aria-label="Alejar"><i class="ph ph-minus"></i></button>'+
        '<span class="mono" id="cine-z" style="min-width:48px;text-align:center;font-size:.75rem">100%</span>'+
        '<button class="btn-icon" data-a="tv:zoom" data-v="1" aria-label="Acercar"><i class="ph ph-plus"></i></button>'+
        '<button class="btn btn-secondary btn-sm" data-a="tv:reset">Centrar</button>'+
        '<button class="btn btn-secondary btn-sm" data-a="tv:cerrar">Salir <span class="mono">Esc</span></button>'+
      '</div>'+
    '</div>'+
    '<div class="cine-lienzo" id="cine-lienzo"><div class="cine-mundo" id="cine-mundo">'+cuadro()+'</div></div>'+
    '<p class="cine-ayuda">Rueda del ratón o pellizco para acercar · arrastra para mover · + y − también funcionan</p>';
  montarCine();
  aplicar();
}

function cuadro(){
  var ms = d().partidos_copa;
  var fases = C.FASES.filter(function(f){ return ms.some(function(p){ return p.fase===f; }); });
  if(!fases.length) return '<p class="tv-vacio">La Copa no tiene cruces de eliminatoria.</p>';
  return '<div class="cine-fases">'+fases.map(function(f){
    var cruces = ms.filter(function(p){ return p.fase===f; });
    return '<div class="cine-ronda"><div class="cine-etq">'+esc(f)+'</div>'+
      cruces.map(function(p){
        var w = C.winnerOf(p), fin = C.isFin(p);
        function lado(side, gol){
          var r = C.resolveSide(p, side);
          var gana = fin && w===p[side];
          return '<div class="cine-lado'+(gana?' gana':(fin?' pierde':''))+(r.pend?' pend':'')+'">'+
            (r.pend?'':U.escudo(C.equipo(r.n)))+
            '<span>'+esc(r.n||'Por definir')+'</span>'+
            (fin?'<b>'+gol+'</b>':'')+'</div>';
        }
        var pen = C.parseDetalles(p.detalles).pen;
        return '<div class="cine-cruce">'+lado('local', C.gl(p))+lado('visitante', C.gv(p))+
          (pen?'<div class="cine-pen">penaltis '+pen.l+'–'+pen.v+'</div>':'')+'</div>';
      }).join('')+'</div>';
  }).join('')+'</div>';
}

function aplicar(){
  var m = document.getElementById('cine-mundo');
  if(!m) return;
  m.style.transform = 'translate('+vista.x+'px,'+vista.y+'px) scale('+vista.z+')';
  var z = document.getElementById('cine-z');
  if(z) z.textContent = Math.round(vista.z*100)+'%';
}
function zoom(f, cx, cy){
  var antes = vista.z;
  vista.z = Math.min(3, Math.max(0.4, vista.z*f));
  if(cx!=null){
    /* Se acerca hacia el puntero, no hacia el centro: es lo que se espera al
       hacer zoom sobre un punto concreto del cuadro. */
    var k = vista.z/antes;
    vista.x = cx - (cx - vista.x)*k;
    vista.y = cy - (cy - vista.y)*k;
  }
  aplicar();
}
function montarCine(){
  var l = document.getElementById('cine-lienzo');
  if(!l) return;
  l.addEventListener('wheel', function(ev){
    ev.preventDefault();
    var r = l.getBoundingClientRect();
    zoom(ev.deltaY<0 ? 1.12 : 1/1.12, ev.clientX-r.left, ev.clientY-r.top);
  }, {passive:false});

  var arr = null;
  l.addEventListener('pointerdown', function(ev){
    arr = {x:ev.clientX-vista.x, y:ev.clientY-vista.y};
    l.setPointerCapture(ev.pointerId);
    l.style.cursor = 'grabbing';
  });
  l.addEventListener('pointermove', function(ev){
    if(!arr) return;
    vista.x = ev.clientX-arr.x;
    vista.y = ev.clientY-arr.y;
    aplicar();
  });
  ['pointerup','pointercancel'].forEach(function(t){
    l.addEventListener(t, function(){ arr = null; l.style.cursor = ''; });
  });
}

/* --------------------------------------------------------------------------
   CONTROLES
   -------------------------------------------------------------------------- */
U.acciones.tv = {
  cerrar: cerrar,
  prev:   function(){ mover(-1); },
  next:   function(){ mover(1); },
  ir:     function(x){ paso = Number(x.dataset.i); pintar(); arrancar(); },
  zoom:   function(x){ zoom(Number(x.dataset.v)>0 ? 1.2 : 1/1.2); },
  reset:  function(){ vista = {z:1, x:0, y:0}; aplicar(); }
};

document.addEventListener('keydown', function(ev){
  if(!el() || !el().classList.contains('on')) return;
  if(ev.key==='Escape'){ ev.preventDefault(); return cerrar(); }
  if(ev.key==='ArrowRight'){ ev.preventDefault(); mover(1); }
  if(ev.key==='ArrowLeft'){ ev.preventDefault(); mover(-1); }
  if(ev.key==='+'||ev.key==='='){ ev.preventDefault(); zoom(1.2); }
  if(ev.key==='-'){ ev.preventDefault(); zoom(1/1.2); }
});
/* Salir de pantalla completa con F11 o el botón del navegador cierra también
   el overlay: quedarse con la presentación a medias en una ventana normal es
   peor que salir del todo. */
document.addEventListener('fullscreenchange', function(){
  if(!document.fullscreenElement && el() && el().classList.contains('on') && reloj) cerrar();
});

SFG.tv = {abrir:abrir, cerrar:cerrar, cine:cine};

})();
