/* ==========================================================================
   SUPERLIGA FRONTIER — app.js
   Algoritmos (clasificación, goleadores, bracket de Copa, búsqueda)
   conservados 1:1 respecto al index.html original. Cambia la presentación.

   NOTA DE ARQUITECTURA — por qué ya no hay onclick="..." con datos dentro:
   la versión anterior inyectaba JSON.stringify(partido) dentro de un atributo
   onclick. Los goleadores se guardan como "Raleigh Greenstreet 23'" y ese
   apóstrofe cerraba el atributo antes de tiempo: el navegador parseaba el
   resto como atributos basura y el partido no abría (18 de 54 rotos).
   Ahora todo va por delegación de eventos + data-* con índices numéricos,
   que no puede romperse por el contenido de los datos.
   ========================================================================== */
(function(){
'use strict';

if (window.gsap && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

var bd = { equipos:[], partidos_liga:[], partidos_ascenso:[], partidos_copa:[], noticias:[], config:{} };
window.bd = bd;

var RIVALIDADES = [['Alpino','Academia Plenilunio']];
/* Marcado interno: el Zanark Domain es el equipo a batir de la temporada.
   Se conserva el dato, pero NO se etiqueta en Resultados — ese concepto se
   queda fuera de la sección por decisión editorial. */
var FAVORITO = 'Zanark Domain';

/* ---------------------------- utilidades ---------------------------- */
function norm(s){ return String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,''); }
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function hl(text,q){
  if(!q) return esc(text);
  var nt=norm(text), nq=norm(q), i=nt.indexOf(nq);
  if(i<0) return esc(text);
  return esc(text.slice(0,i))+'<em>'+esc(text.slice(i,i+q.length))+'</em>'+esc(text.slice(i+q.length));
}
function gl(p){ return p.goles_l!==undefined?p.goles_l:(p.golesl!==undefined?p.golesl:0); }
function gv(p){ return p.goles_v!==undefined?p.goles_v:(p.golesv!==undefined?p.golesv:0); }
function isFin(p){ return p.estado==='FINALIZADO'; }
function isPen(p){ return p.estado==='PENDIENTE'; }
function X(s){ return window.SFX ? SFX(s) : s; }
function T(k,f){ return window.sfT ? sfT(k,f) : f; }
function $(id){ return document.getElementById(id); }

function abbr3(name,ab){
  if(ab&&ab.trim()) return ab.trim().toUpperCase().slice(0,3);
  var c=String(name||'').replace(/[^\p{L}\s]/gu,'').trim();
  if(!c) return '???';
  var w=c.split(/\s+/);
  if(w.length>=3) return (w[0][0]+w[1][0]+w[2][0]).toUpperCase();
  if(w.length===2) return (w[0].slice(0,2)+w[1][0]).toUpperCase();
  return c.slice(0,3).toUpperCase();
}
window.abbr3=abbr3;

/* AFINIDADES ELEMENTALES — los cinco nombres oficiales son
   Fuego, Montaña, Bosque, Aire y Neutro.
   El JSON trae alias sueltos ("Forest", "viento", minúsculas, incluso una URL
   por error y algún null), así que todo se normaliza contra estas cinco y lo
   que no encaje cae en Neutro en vez de inventarse una afinidad. */
var AF_MAP={
  fuego:'fuego', fire:'fuego',
  montana:'montana', 'montaña':'montana', tierra:'montana', mountain:'montana',
  bosque:'bosque', forest:'bosque', wood:'bosque',
  aire:'aire', air:'aire', viento:'aire', wind:'aire',
  neutro:'neutro', neutral:'neutro', vacio:'neutro', 'vacío':'neutro'
};
var AF_LABEL={fuego:'Fuego',montana:'Montaña',bosque:'Bosque',aire:'Aire',neutro:'Neutro'};
var AF_HEX={fuego:'#FF5A3C',montana:'#C08A3E',bosque:'#46B45F',aire:'#35D0C6',neutro:'#9AA0A6'};
function afKey(a){ return AF_MAP[norm(a||'').replace(/[^a-zñ]/g,'')]||'neutro'; }
function afName(a){ return window.sfAfinidadLabel ? sfAfinidadLabel(a) : AF_LABEL[afKey(a)]; }
function afTag(a,cls){ var k=afKey(a); return '<span class="af af-'+k+(cls?' '+cls:'')+'"><i></i><span class="pn">'+esc(afName(a))+'</span></span>'; }

function team(n){ return bd.equipos.find(function(e){ return e.nombre===n; }); }
function isHttp(u){ return !!u && /^https?:\/\//.test(u); }

/* El CSP de IONOS solo permite img-src desde flagcdn.com, images.weserv.nl
   e i.imgur.com. escudo/foto/afinidad/imagen a veces vienen de wikia.nocookie.net
   o cloudfront (el gestor no lo controla), así que cualquier URL fuera de la
   whitelist se reescribe para pasar por el proxy images.weserv.nl, que sí está
   permitido. Se aplica una sola vez al cargar el JSON (ver DOMContentLoaded). */
var CSP_IMG_PERMITIDOS=['images.weserv.nl','flagcdn.com','i.imgur.com'];
var CSP_IMG_CAMPOS=['escudo','foto','afinidad','imagen'];
function normalizarUrlImagen(u){
  var host;
  try{ host=new URL(u).hostname; }catch(e){ return u; }
  if(CSP_IMG_PERMITIDOS.indexOf(host)!==-1) return u;
  return 'https://images.weserv.nl/?url='+encodeURIComponent(u);
}
function normalizarImagenesDatos(o){
  if(Array.isArray(o)){ o.forEach(normalizarImagenesDatos); return; }
  if(o && typeof o==='object'){
    for(var k in o){
      var v=o[k];
      if(CSP_IMG_CAMPOS.indexOf(k)!==-1 && isHttp(v)) o[k]=normalizarUrlImagen(v);
      else normalizarImagenesDatos(v);
    }
  }
}
function avatar(url,cls,alt){
  if(isHttp(url)) return '<img src="'+esc(url)+'" alt="'+esc(alt||'')+'" class="'+cls+'" loading="lazy" referrerpolicy="no-referrer">';
  return '<span class="noimg '+cls+'">'+esc(((alt||'?').trim()[0]||'?').toUpperCase())+'</span>';
}
function crest(e,size){
  if(e&&isHttp(e.escudo)) return '<img src="'+esc(e.escudo)+'" alt="" loading="lazy">';
  return '<span class="noimg" style="width:'+(size||22)+'px;height:'+(size||22)+'px;border-radius:6px;font-size:.55rem;flex-shrink:0">'+esc(abbr3(e?e.nombre:'?'))+'</span>';
}

function derbi(a,b){
  if(RIVALIDADES.some(function(r){ return (r[0]===a&&r[1]===b)||(r[0]===b&&r[1]===a); })) return {t:T('badge.derbi','Derbi'),c:'badge-copa'};
  return null;
}
/* Icono de división: en Copa se cruzan Superliga y Ascenso, y hay que poder
   distinguirlo de un vistazo sin leer el nombre del equipo. */
/* Luminancia aproximada de un color hex (0–1). */
function lum(h){
  var c=String(h||'').replace('#','');
  if(c.length===3) c=c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
  var n=parseInt(c,16);
  if(isNaN(n)||c.length!==6) return 0;
  return (0.2126*((n>>16)&255)+0.7152*((n>>8)&255)+0.0722*(n&255))/255;
}
/* Color de lavado de un club: se elige el más luminoso de sus dos colores.
   Varios clubes tienen el primario casi negro (#100d10) y sobre fondo negro
   no teñiría absolutamente nada — en ese caso se cae al color de la marca. */
function wash(t,fallback){
  var best=[t&&t.color1,t&&t.color2].filter(Boolean)
    .map(function(c){ return {c:c,l:lum(c)}; })
    .sort(function(a,b){ return b.l-a.l; })[0];
  return (best&&best.l>0.14) ? best.c : fallback;
}
function divIcon(e){
  if(!e) return '';
  var asc=e.division==='ASCENSO';
  var nombre=T(asc?'comp.ascenso':'comp.superliga',asc?'Ascenso Frontier':'Superliga Frontier');
  return '<img class="ms-div" src="assets/'+(asc?'af-icono.png':'sf-icono.png')+'" alt="'+esc(nombre)+'" title="'+esc(nombre)+'" loading="lazy">';
}
/* Nombre oficial de cada competición, no un badge corto: son los nombres
   confirmados por el cliente (p.ej. inglés "Frontier Superleague", no una
   traducción literal de "Superliga"). */
function compBadge(comp){
  if(comp==='ascenso') return '<span class="badge badge-ascenso">'+esc(T('comp.ascenso','Ascenso Frontier'))+'</span>';
  if(comp==='copa') return '<span class="badge badge-copa">'+esc(T('sec.copa','Copa Fútbol Frontier'))+'</span>';
  return '<span class="badge badge-superliga">'+esc(T('comp.superliga','Superliga Frontier'))+'</span>';
}

/* Ancho real de la barra de scroll (0 en móvil / scrollbars superpuestas).
   Alimenta --sbw, que usa .hero-photo para sangrar bajo ella. Se mide ya
   —no hace falta esperar a DOMContentLoaded— y se corrige si cambia el modo
   de la barra (p. ej. al conectar un ratón en una tablet). */
(function medirScrollbar(){
  function fijar(){ document.documentElement.style.setProperty('--sbw', (window.innerWidth-document.documentElement.clientWidth)+'px'); }
  fijar();
  window.addEventListener('resize', fijar, {passive:true});
})();

/* ---------------------------- carga ---------------------------- */
document.addEventListener('DOMContentLoaded', function(){
  fetch('datos_oficiales.json?t='+Date.now())
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(d){ normalizarImagenesDatos(d); bd = Object.assign(bd, d); window.bd = bd; renderAll(); })
    .catch(function(e){
      console.error('datos_oficiales.json', e);
      var tb=$('tbody-clas');
      if(tb) tb.innerHTML='<tr><td colspan="11" style="padding:3rem;text-align:center;color:var(--ink-4)">'+T('err.datos','No se pudieron cargar los datos.')+'</td></tr>';
    });
});

function renderAll(){
  _posCache=null;
  renderMetrics();
  renderClas(curDiv);
  renderPlayoff();
  initJornadas();
  renderMatches();
  renderCopa();
  renderTeams(curTeamDiv);
  renderScorers(curGol);
  renderNews();
  renderStaffClubs();
  observeReveals();
}

function renderMetrics(){
  var act=bd.equipos.filter(function(e){ return !e.archivado; });
  var players=act.reduce(function(a,e){ return a+(e.jugadores?e.jugadores.length:0); },0);
  countTo($('m-temp'), parseInt(bd.config.temporada)||3);
  countTo($('m-teams'), act.length);
  countTo($('m-players'), players);
  countTo($('m-jor'), parseInt(bd.config.jornada_actual)||0);
  if($('hm-teams')) $('hm-teams').textContent=act.length;
  if($('hm-players')) $('hm-players').textContent=players;
}
function countTo(el,val){
  if(!el) return;
  var dur=1100, t0=null;
  function step(t){
    if(!t0) t0=t;
    var p=Math.min((t-t0)/dur,1), e=1-Math.pow(1-p,3);
    el.textContent=Math.round(val*e);
    if(p<1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

/* ==========================================================================
   CLASIFICACIÓN
   ========================================================================== */
var curDiv='SUPERLIGA';
function matchesOf(div){ return div==='SUPERLIGA'?bd.partidos_liga:bd.partidos_ascenso; }

function orderStandings(list){
  return list.slice().sort(function(a,b){
    if(b.pts!==a.pts) return b.pts-a.pts;
    var dA=(a.gf||0)-(a.gc||0), dB=(b.gf||0)-(b.gc||0);
    if(dB!==dA) return dB-dA;
    if((b.gf||0)!==(a.gf||0)) return (b.gf||0)-(a.gf||0);
    if((a.gc||0)!==(b.gc||0)) return (a.gc||0)-(b.gc||0);
    if((b.g||0)!==(a.g||0)) return (b.g||0)-(a.g||0);
    if((b.e||0)!==(a.e||0)) return (b.e||0)-(a.e||0);
    if((a.p||0)!==(b.p||0)) return (a.p||0)-(b.p||0);
    if((a.pj||0)!==(b.pj||0)) return (a.pj||0)-(b.pj||0);
    return a.nombre.localeCompare(b.nombre,'es');
  });
}
function prevOrder(div,list){
  var ms=matchesOf(div).filter(isFin);
  var maxJ=ms.reduce(function(m,p){ return Math.max(m,parseInt(p.jornada)||0); },0);
  var t={}; list.forEach(function(e){ t[e.nombre]={pts:0,gf:0,gc:0}; });
  ms.filter(function(p){ return (parseInt(p.jornada)||0)<maxJ; }).forEach(function(p){
    if(!t[p.local]||!t[p.visitante]) return;
    var a=gl(p), b=gv(p);
    t[p.local].gf+=a; t[p.local].gc+=b; t[p.visitante].gf+=b; t[p.visitante].gc+=a;
    if(a>b) t[p.local].pts+=3; else if(b>a) t[p.visitante].pts+=3; else { t[p.local].pts++; t[p.visitante].pts++; }
  });
  return Object.keys(t).sort(function(a,b){
    if(t[b].pts!==t[a].pts) return t[b].pts-t[a].pts;
    return (t[b].gf-t[b].gc)-(t[a].gf-t[a].gc);
  });
}
function formOf(div,name,n){
  var ms=matchesOf(div).filter(function(p){ return isFin(p)&&(p.local===name||p.visitante===name); });
  ms.sort(function(a,b){ return (parseInt(b.jornada)||0)-(parseInt(a.jornada)||0); });
  return ms.slice(0,n).map(function(p){
    var home=p.local===name, f=home?gl(p):gv(p), c=home?gv(p):gl(p);
    return f>c?'w':(f<c?'l':'e');
  }).reverse();
}

/* Posición actual en la tabla de cada equipo, por división. Se calcula una vez
   por carga de datos: renderMatches la consulta dos veces por partido. */
var _posCache=null;
function posOf(name){
  if(!_posCache){
    _posCache={};
    ['SUPERLIGA','ASCENSO'].forEach(function(d){
      orderStandings(bd.equipos.filter(function(e){ return e.division===d&&!e.archivado; }))
        .forEach(function(e,i){ _posCache[e.nombre]=i+1; });
    });
  }
  return _posCache[name]||null;
}

function renderClas(div){
  curDiv=div;
  var list=bd.equipos.filter(function(e){ return e.division===div&&!e.archivado; });
  var ord=orderStandings(list), prev=prevOrder(div,ord), total=ord.length;
  var h='';
  ord.forEach(function(e,i){
    var pos=i+1, dg=(e.gf||0)-(e.gc||0), z='';
    if(div==='SUPERLIGA'){
      if(pos<=3) z='z-po'; else if(pos===4) z='z-pi'; else if(pos<=6) z='z-pp'; else if(pos>total-3) z='z-desc';
    } else if(pos<=3) z='z-asc';
    var pp=prev.indexOf(e.nombre)+1, tr='<span class="tr-s">·</span>';
    if(pp&&pp!==pos) tr=pp>pos?'<span class="tr-u">▲</span>':'<span class="tr-d">▼</span>';
    h+='<tr data-team="'+esc(e.id)+'">'+
      '<td class="pos"><span class="zone '+z+'"></span>'+pos+' '+tr+'</td>'+
      '<td><span class="tm">'+crest(e)+esc(X(e.nombre))+'</span></td>'+
      '<td class="mono">'+(e.pj||0)+'</td>'+
      '<td class="mono hide-sm">'+(e.g||0)+'</td><td class="mono hide-sm">'+(e.e||0)+'</td><td class="mono hide-sm">'+(e.p||0)+'</td>'+
      '<td class="mono hide-sm">'+(e.gf||0)+'</td><td class="mono hide-sm">'+(e.gc||0)+'</td>'+
      '<td class="mono hide-xs">'+(dg>0?'+':'')+dg+'</td>'+
      '<td class="pts">'+(e.pts||0)+'</td>'+
      '<td class="hide-sm"><span class="frm">'+formOf(div,e.nombre,5).map(function(r){ return '<i class="f-'+r+'"></i>'; }).join('')+'</span></td>'+
    '</tr>';
  });
  $('tbody-clas').innerHTML = h || '<tr><td colspan="11" style="padding:3rem;text-align:center;color:var(--ink-4)">'+T('empty.equipos','Sin equipos.')+'</td></tr>';

  $('legend-clas').innerHTML = div==='SUPERLIGA'
    ? '<span><i class="z-po"></i>'+T('zone.playoffs','Play Off')+'</span><span><i class="z-pi"></i>'+T('zone.playin','Play In')+'</span><span><i class="z-pp"></i>'+T('zone.playin.part','Partido por el Play In')+'</span><span><i class="z-desc"></i>'+T('zone.descenso','Descenso')+'</span>'
    : '<span><i class="z-asc"></i>'+T('zone.ascenso','Ascenso')+'</span>';
  var pw=$('playoff-wrap'); if(pw) pw.style.display = div==='SUPERLIGA' ? '' : 'none';
}

/* Fases del play-off de Superliga, en el orden en que se juegan. Son las mismas
   etiquetas que ya usan las rondas calculadas de aquí abajo. */
var FASES_PO=['DESEMPATE','PARTIDO POR EL PLAY IN','PLAY IN','SEMIFINALES','FINAL'];

function renderPlayoff(){
  var el=$('bracket-playoff'); if(!el) return;

  /* Si hay partidos de play-off cargados mandan ellos: son lo que ha pasado de
     verdad, frente a un cuadro deducido de la clasificación en el que la final
     sale siempre en blanco. Sin ellos se dibuja el previsto, como siempre. */
  var reales=bd.partidos_liga.filter(function(p){ return FASES_PO.indexOf(p.fase)>=0; });
  if(reales.length) return renderPlayoffReal(el,reales);

  var ord=orderStandings(bd.equipos.filter(function(e){ return e.division==='SUPERLIGA'&&!e.archivado; }));
  if(ord.length<6){ var w=$('playoff-wrap'); if(w) w.style.display='none'; return; }
  function side(e,sub){
    if(!e) return '<div class="br-side br-tbd"><span class="nm">'+T('br.tbd','Por definir')+'</span></div>';
    return '<div class="br-side">'+crest(e,18)+'<span class="nm">'+esc(X(e.nombre))+'</span><span class="sc" style="color:var(--ink-5);font-size:.6875rem">'+sub+'</span></div>';
  }
  var tbd='<div class="br-side br-tbd"><span class="nm">'+T('br.tbd','Por definir')+'</span></div>';
  el.innerHTML=
    '<div class="br-round"><div class="br-label">'+T('zone.playin.part','Partido por el Play In')+'</div><div class="br-match">'+side(ord[4],'5º')+side(ord[5],'6º')+'</div></div>'+
    '<div class="br-round"><div class="br-label">'+T('zone.playin','Play In')+'</div><div class="br-match">'+side(ord[3],'4º')+'<div class="br-side br-tbd"><span class="nm">'+T('br.ganador','Ganador')+' 5º-6º</span></div></div></div>'+
    '<div class="br-round"><div class="br-label">'+T('br.semis','Semifinales')+'</div><div class="br-match">'+side(ord[0],'1º')+'<div class="br-side br-tbd"><span class="nm">'+T('br.ganador','Ganador')+' '+T('zone.playin','Play In')+'</span></div></div><div class="br-match">'+side(ord[1],'2º')+side(ord[2],'3º')+'</div></div>'+
    '<div class="br-round"><div class="br-label">'+T('br.final','Final')+'</div><div class="br-match">'+tbd+tbd+'</div></div>';
}

/* Cuadro dibujado desde los partidos cargados. Reutiliza las clases del cuadro
   de Copa, así que no hace falta CSS nuevo, y quien decide si #playoff-wrap se
   ve sigue siendo renderClas(), según la división que se esté mirando. */
function renderPlayoffReal(el,ms){
  el.innerHTML=FASES_PO.map(function(f){
    var ronda=ms.filter(function(p){ return p.fase===f; });
    if(!ronda.length) return '';
    return '<div class="br-round"><div class="br-label">'+esc(faseName(f))+'</div>'+
      ronda.map(function(p){
        var fin=isFin(p), a=gl(p), b=gv(p);
        function lado(nombre,gol,gana){
          if(!nombre) return '<div class="br-side br-tbd"><span class="nm">'+T('br.tbd','Por definir')+'</span></div>';
          return '<div class="br-side '+(fin?(gana?'br-win':'br-lose'):'')+'">'+
            crest(team(nombre),18)+'<span class="nm">'+esc(X(nombre))+'</span>'+
            (fin?'<span class="sc">'+gol+'</span>':'')+'</div>';
        }
        /* data-comp/data-idx: la ficha del partido ya se abre por delegación con
           esos dos atributos, igual que en Resultados y en Copa. */
        return '<div class="br-match" data-comp="liga" data-idx="'+bd.partidos_liga.indexOf(p)+'">'+
          lado(p.local,a,fin&&a>b)+lado(p.visitante,b,fin&&b>a)+'</div>';
      }).join('')+'</div>';
  }).join('');
}

/* ==========================================================================
   RESULTADOS
   ========================================================================== */
var curComp='liga', jornadas=[], jIdx=0;
function poolOf(c){ return c==='liga'?bd.partidos_liga:c==='ascenso'?bd.partidos_ascenso:bd.partidos_copa; }

function initJornadas(){
  var jn=$('jnav');
  if(curComp==='copa'){ jornadas=[]; if(jn) jn.style.display='none'; return; }
  if(jn) jn.style.display='';
  jornadas=Array.from(new Set(poolOf(curComp).map(function(p){ return p.jornada; })))
    .filter(function(x){ return x!=null&&x!==''; })
    .sort(function(a,b){ return (parseInt(a)||0)-(parseInt(b)||0); });
  jIdx=Math.max(0,jornadas.length-1);
}
function renderMatches(){
  var pool=poolOf(curComp), list;
  if(curComp==='copa') list=pool.slice();
  else { var j=jornadas[jIdx]; list=pool.filter(function(p){ return p.jornada===j; }); $('j-label').textContent=T('jornada','Jornada')+' '+(j||'·'); }

  $('matches').innerHTML = list.map(function(p){
    var i=pool.indexOf(p);
    var L=team(p.local), V=team(p.visitante), pen=!isFin(p);
    var a=gl(p), b=gv(p);
    var d=derbi(p.local,p.visitante);
    var tag = d?'<span class="badge '+d.c+'">'+d.t+'</span>':compBadge(curComp);
    function row(t,name,score,lose){
      /* Franja de color del club + señal de contexto a la derecha del nombre:
         en liga/ascenso, la posición actual en la tabla; en Copa, el icono de
         la división a la que pertenece el equipo (se cruzan las dos). */
      var c1=(t&&t.color1)||'#3A3A3A', c2=(t&&t.color2)||'#141414';
      var mark;
      if(curComp==='copa') mark=divIcon(t);
      else { var pp=posOf(name); mark=pp?'<span class="ms-pos" title="'+T('th.pos','Pos')+'">'+pp+'º</span>':''; }
      return '<div class="match-side '+(lose?'match-lose':'')+'">'+
        '<span class="ms-color" style="background:linear-gradient(180deg,'+esc(c1)+','+esc(c2)+')"></span>'+
        crest(t,26)+
        '<span class="nm">'+esc(X(name))+'</span>'+mark+
        (pen?'':'<span class="sc">'+score+'</span>')+
      '</div>';
    }
    return '<article class="card spotlight match" data-comp="'+curComp+'" data-idx="'+i+'">'+
      '<div class="match-top">'+tag+(pen?'<span class="match-vs">VS</span>':'')+'</div>'+
      row(L,p.local,a,!pen&&a<b)+
      row(V,p.visitante,b,!pen&&b<a)+
      /* data-no-tr: "Jornada N" es un texto ya traducido y combinado con un
         número — nunca coincide con un valor exacto del diccionario, así que
         sin protegerlo el recorrido automático lo volvía a traducir por su
         cuenta cada vez que repintaba (el bug de "kolo"→"Боја" en serbio).
         El nombre de fase ya sale de faseName(), también auto-protegido por
         estar solo en su propio nodo si no llevase el mismo envoltorio. */
      '<div class="match-foot"><span data-no-tr>'+(p.fase?esc(faseName(p.fase)):(T('jornada','Jornada')+' '+esc(p.jornada||'')))+'</span><span>'+(pen?T('estado.pendiente','Pendiente'):T('estado.finalizado','Finalizado'))+'</span></div>'+
    '</article>';
  }).join('') || '<p class="muted">'+T('empty.partidos','Sin partidos en esta vista.')+'</p>';
  observeReveals();
}

/* ==========================================================================
   COPA
   ========================================================================== */
function winnerOf(p){
  if(!isFin(p)) return null;
  var a=gl(p), b=gv(p);
  if(a>b) return p.local;
  if(b>a) return p.visitante;
  var m=/PEN[: ]?\s*(\d+)\s*-\s*(\d+)/i.exec(p.detalles||'');
  if(m) return parseInt(m[1])>parseInt(m[2])?p.local:p.visitante;
  return null;
}
function resolveSide(p,side){
  var ok=side==='local'?'origen_local':'origen_visitante';
  if(p[ok]!=null&&bd.partidos_copa[p[ok]]){
    var f=bd.partidos_copa[p[ok]], w=winnerOf(f);
    if(w) return {n:w,pend:false};
    return {n:abbr3(f.local)+' / '+abbr3(f.visitante),pend:true};
  }
  return {n:p[side],pend:false};
}
var FASES=['RONDA 1 (PREVIA)','RONDA 2','CUARTOS DE FINAL','SEMIFINALES','FINAL'];
/* Nombre de ronda de Copa, ya traducido (audit Tarea 2.3: antes salía tal
   cual del JSON, sin curar, a merced de lo que devolviera la API). Semis y
   final reutilizan br.semis/br.final: son la misma palabra que ya existía
   para el cuadro de Play Off, no hace falta duplicarla. */
var FASE_KEY={'RONDA 1 (PREVIA)':'fase.ronda1','RONDA 2':'fase.ronda2','CUARTOS DE FINAL':'fase.cuartos','SEMIFINALES':'br.semis','FINAL':'br.final',
  'PARTIDO POR EL PLAY IN':'zone.playin.part','PLAY IN':'zone.playin'};
function faseName(f){ var k=FASE_KEY[f]; return k?T(k,f):(f||''); }
function renderCopa(){
  var el=$('bracket-copa'); if(!el) return;
  var h='';
  FASES.forEach(function(f){
    var ms=bd.partidos_copa.filter(function(p){ return p.fase===f; });
    if(!ms.length) return;
    h+='<div class="br-round"><div class="br-label">'+esc(faseName(f))+'</div>';
    ms.forEach(function(p){
      var i=bd.partidos_copa.indexOf(p);
      var L=resolveSide(p,'local'), V=resolveSide(p,'visitante');
      var w=winnerOf(p), fin=isFin(p);
      function s(info,score,name){
        if(info.pend) return '<div class="br-side br-tbd"><span class="nm">'+esc(info.n)+'</span></div>';
        var t=team(info.n), win=fin&&w===name;
        /* En el cuadro de Copa se cruzan las dos divisiones: el icono (en
           blanco) dice de cuál viene cada equipo sin tener que saberse la liga. */
        return '<div class="br-side '+(fin?(win?'br-win':'br-lose'):'')+'">'+divIcon(t)+crest(t,18)+'<span class="nm">'+esc(X(info.n))+'</span>'+(fin?'<span class="sc">'+score+'</span>':'')+'</div>';
      }
      h+='<div class="br-match" data-comp="copa" data-idx="'+i+'">'+s(L,gl(p),p.local)+s(V,gv(p),p.visitante)+'</div>';
    });
    h+='</div>';
  });
  el.innerHTML=h||'<p class="muted">'+T('empty.copa','La Copa todavía no tiene cruces publicados.')+'</p>';

  var grupos=bd.partidos_copa.filter(function(p){ return p.fase==='FASE DE GRUPOS'; });
  var g=$('groups-copa'); if(!g) return;
  if(!grupos.length){ g.innerHTML=''; return; }
  var by={};
  grupos.forEach(function(p){ (by[p.grupo||'A']=by[p.grupo||'A']||[]).push(p); });
  g.innerHTML=Object.keys(by).sort().map(function(k){
    var t={};
    by[k].forEach(function(p){
      [p.local,p.visitante].forEach(function(n){ if(!t[n]) t[n]={n:n,pts:0,gf:0,gc:0}; });
      if(!isFin(p)) return;
      var a=gl(p), b=gv(p);
      t[p.local].gf+=a; t[p.local].gc+=b; t[p.visitante].gf+=b; t[p.visitante].gc+=a;
      if(a>b) t[p.local].pts+=3; else if(b>a) t[p.visitante].pts+=3; else { t[p.local].pts++; t[p.visitante].pts++; }
    });
    var rows=Object.keys(t).map(function(k2){ return t[k2]; }).sort(function(a,b){
      if(b.pts!==a.pts) return b.pts-a.pts; return (b.gf-b.gc)-(a.gf-a.gc);
    });
    return '<div class="card group"><h4>Grupo '+esc(k)+'</h4>'+rows.map(function(r,i){
      return '<div class="group-row '+(i<2?'group-q':'')+'">'+crest(team(r.n),18)+'<span class="nm">'+esc(X(r.n))+'</span><span class="p">'+r.pts+'</span></div>';
    }).join('')+'</div>';
  }).join('');
}

/* ==========================================================================
   EQUIPOS
   ========================================================================== */
var POS=['POR','DEF','MED','DEL'];
var POS_ORDER={POR:0,DEF:1,MED:2,DEL:3};
var POS_LABEL={POR:'Porteros',DEF:'Defensas',MED:'Medios',DEL:'Delanteros'};
var POS_LABEL_1={POR:'Portero',DEF:'Defensa',MED:'Medio',DEL:'Delantero'};
/* Nombre de posición en singular, ya traducido: la jerga futbolística va por
   diccionario propio, nunca por traducción automática. */
function posName(p){ return POS_LABEL_1[p] ? T('pos1.'+String(p).toLowerCase(),POS_LABEL_1[p]) : (p||'·'); }
/* Abreviatura de posición (chip pequeño de plantilla/goleadores/cronología).
   Antes se imprimía el código español (POR/DEF/MED/DEL) tal cual en los 10
   idiomas: eso evitaba que la API lo confundiera con una preposición ("DEL"
   → "of"), pero no era la sigla real del idioma de destino. Ahora sale la
   sigla estándar de cada mercado (GK/DF/MF/FW en inglés, etc.) — ver
   pos.abbr.* en i18n.js. */
function posAbbr(p){ return POS_LABEL_1[p] ? T('pos.abbr.'+String(p).toLowerCase(),p) : (p||'·'); }
function sortSquad(list){
  return list.slice().sort(function(a,b){
    var pa=POS_ORDER[a.posicion], pb=POS_ORDER[b.posicion];
    if(pa==null) pa=9; if(pb==null) pb=9;
    if(pa!==pb) return pa-pb;
    var da=parseInt(a.dorsal), db=parseInt(b.dorsal);
    if(isNaN(da)) da=999; if(isNaN(db)) db=999;
    if(da!==db) return da-db;
    return String(a.nombre).localeCompare(String(b.nombre),'es');
  });
}
/* Nombre corto para la placa del campo: apellido si lo hay, si no el nombre. */
function shortName(n){
  var w=String(n||'').trim().split(/\s+/);
  return w.length>1 ? w[w.length-1] : (w[0]||'');
}
/* GOLES DE CARRERA — `goles_totales` del JSON es exactamente la suma del
   historial, es decir SÓLO temporadas cerradas (comprobado: coincide en los
   424 jugadores que lo traen). Los goles de la temporada en curso viven
   aparte, en `goles`, y no se vuelcan al historial hasta que la temporada
   cierra. Mostrando sólo `goles_totales` el máximo goleador de la liga
   aparecía con 9 en vez de 16. La carrera es la suma de ambos. */
function golesTemporada(j){ return j&&j.goles!=null?(j.goles||0):0; }
function golesCarrera(j){ return (j&&j.goles_totales!=null?j.goles_totales:0)+golesTemporada(j); }

/* Clubes renombrados entre temporadas: el historial guarda el nombre de
   entonces ("Ragnah", "Oscuridad Ancestral") pero el `equipo_id` apunta al
   club de hoy, así que salía el nombre viejo con el escudo nuevo. Se muestra
   el nombre actual y el de entonces como contexto, sin perder ninguno. */
function clubHist(h){
  var te=bd.equipos.find(function(x){ return x.id===h.equipo_id; });
  var actual=te?te.nombre:h.equipo;
  return { e:te, actual:actual, antes:(h.equipo&&h.equipo!==actual)?h.equipo:null };
}

/* "Temporada 2" -> 2. Sirve para contar cuántas temporadas duró un fichaje. */
function seasonNum(s){ var m=/(\d+)/.exec(String(s||'')); return m?parseInt(m[1],10):null; }
/* El JSON escribe la temporada de tres formas distintas: "Temporada 1", "2" y
   null. Se normaliza siempre a "Temporada N" para que el historial no mezcle
   formatos dentro de la misma ficha. */
function seasonLabel(s){
  var n=seasonNum(s);
  return n==null ? '' : T('temporada','Temporada')+' '+n;
}

var curTeamDiv='SUPERLIGA';
function renderTeams(div){
  curTeamDiv=div;
  var list=bd.equipos.filter(function(e){ return e.division===div&&!e.archivado; })
    .sort(function(a,b){ return (b.pts||0)-(a.pts||0)||a.nombre.localeCompare(b.nombre,'es'); });
  $('teams').innerHTML=list.map(function(e){
    var dg=(e.gf||0)-(e.gc||0);
    return '<article class="card spotlight team" data-team="'+esc(e.id)+'">'+
      '<span class="team-accent" style="background:linear-gradient(90deg,'+esc(e.color1||'#333')+','+esc(e.color2||'#111')+')"></span>'+
      /* .pn marca las partes que son nombre propio dentro de una cadena mixta:
         el traductor las respeta y el resto de la frase sí se traduce. */
      '<div class="team-top">'+crest(e,42)+'<div><h3 class="pn">'+esc(X(e.nombre))+'</h3><span class="pn">'+esc(abbr3(e.nombre,e.abreviatura))+(e.ciudad?' · '+esc(e.ciudad):'')+'</span></div></div>'+
      '<div class="team-stats"><div><b class="mono">'+(e.pts||0)+'</b><span>'+T('st.puntos','Puntos')+'</span></div><div><b class="mono">'+(e.pj||0)+'</b><span>'+T('st.jugados','Jugados')+'</span></div><div><b class="mono">'+(dg>0?'+':'')+dg+'</b><span>'+T('st.dif','Dif.')+'</span></div></div>'+
    '</article>';
  }).join('')||'<p class="muted">'+T('empty.equipos','Sin equipos.')+'</p>';
  observeReveals();
}

/* ==========================================================================
   GOLEADORES
   ========================================================================== */
var curGol='liga';
function findPlayer(short){
  var n=norm(short);
  for(var i=0;i<bd.equipos.length;i++){
    var e=bd.equipos[i];
    for(var j=0;j<(e.jugadores||[]).length;j++){
      var p=e.jugadores[j], pn=norm(p.nombre);
      if(pn===n||pn.split(' ')[0]===n||(n.split(' ')[0]===pn.split(' ')[0]&&pn.indexOf(n)===0)) return {j:p,e:e};
    }
  }
  return null;
}
function calcScorers(ms){
  var t={};
  ms.forEach(function(p){
    (p.detalles||'').split('/').forEach(function(half){
      half.split(',').forEach(function(ev){
        var parts=ev.trim().split(':');
        if(parts.length>=2&&parts[0].trim()==='gol'){
          var n=parts[1].trim(); if(n) t[n]=(t[n]||0)+1;
        }
      });
    });
  });
  return Object.keys(t).map(function(n){
    var f=findPlayer(n);
    return { nombre:f?f.j.nombre:n, goles:t[n], j:f?f.j:null, e:f?f.e:null };
  }).sort(function(a,b){
    /* A igualdad de goles se ordena alfabéticamente, no por quién marcó
       primero: el orden de aparición en el JSON no es un criterio deportivo. */
    return b.goles-a.goles || String(a.nombre).localeCompare(String(b.nombre),'es');
  });
}
var GOL_TOP=10;
/* Misma gramática que la cronología: todo en una línea, posición abreviada y
   afinidad como punto de color. En Copa se añade el icono de la división a la
   que pertenece el club, porque ahí se cruzan Superliga y Ascenso. */
function scorerRow(r,i,comp){
  var cls=i===0?'sc-1':i===1?'sc-2':i===2?'sc-3':'';
  var pos=r.j&&r.j.posicion;
  return '<div class="sc-row '+cls+'"'+(r.e&&r.j?' data-team="'+esc(r.e.id)+'" data-player="'+esc(encodeURIComponent(r.j.nombre))+'"':'')+'>'+
    '<span class="sc-rank">'+(i+1)+'</span>'+
    '<div class="sc-who">'+
      avatar(r.j?r.j.foto:'','',r.nombre)+
      '<span class="sc-name">'+esc(X(r.nombre))+'</span>'+
      (pos?'<span class="sc-pos">'+esc(posAbbr(pos))+'</span>':'')+
      (r.j?'<span class="af-dot" style="--afc:'+AF_HEX[afKey(r.j.afinidad)]+'" title="'+esc(afName(r.j.afinidad))+'"></span>':'')+
      (r.e?'<span class="sc-team">'+(comp==='copa'?divIcon(r.e):'')+crest(r.e,16)+'<span>'+esc(X(r.e.nombre))+'</span></span>':'')+
    '</div>'+
    '<span class="sc-goals mono">'+r.goles+'<small>G</small></span>'+
  '</div>';
}
function renderScorers(comp){
  curGol=comp;
  var list=calcScorers(poolOf(comp).filter(isFin));
  var el=$('scorers');
  el.classList.remove('open');
  if(!list.length){ el.innerHTML='<div class="ev-empty">'+T('empty.goles','Todavía no hay goles registrados en esta competición.')+'</div>'; return; }
  var top=list.slice(0,GOL_TOP), rest=list.slice(GOL_TOP);
  el.innerHTML=top.map(function(r,i){ return scorerRow(r,i,comp); }).join('')+
    (rest.length
      ? '<div class="sc-rest">'+rest.map(function(r,i){ return scorerRow(r,i+GOL_TOP,comp); }).join('')+'</div>'+
        '<button class="sc-more" type="button" aria-expanded="false">'+
          '<span class="sc-more-txt" data-no-tr>'+T('gol.ver','Ver los')+' '+rest.length+' '+T('gol.restantes','goleadores restantes')+'</span>'+
          '<i class="ph-bold ph-caret-down"></i></button>'
      : '');
}

/* ==========================================================================
   SHEETS
   ========================================================================== */
function openSheet(id){
  /* Cualquier overlay abierto se cierra al abrir una ficha: si no, el panel de
     campeones se quedaba flotando por debajo de la ficha del club. */
  document.querySelectorAll('.ov.open').forEach(function(o){ o.classList.remove('open'); });
  $(id).classList.add('open'); document.body.classList.add('locked');
}
function closeSheets(){ document.querySelectorAll('.sheet.open').forEach(function(s){ s.classList.remove('open'); }); document.body.classList.remove('locked'); }
window.closeSheets=closeSheets;

function openTeam(id){
  var e=bd.equipos.find(function(x){ return x.id===id; }); if(!e) return;
  /* La plantilla se lee por líneas, no por dorsal suelto: primero Porteros,
     después Defensas, Medios y Delanteros, y dentro de cada línea por dorsal. */
  var js=sortSquad(e.jugadores||[]);
  var tit=js.filter(function(j){ return j.titular; }), sup=js.filter(function(j){ return !j.titular; });
  var dg=(e.gf||0)-(e.gc||0);
  var form=formOf(e.division,e.nombre,5);

  var rows={POR:[],DEF:[],MED:[],DEL:[]};
  tit.forEach(function(j){ (rows[j.posicion]||rows.MED).push(j); });
  var yOf={POR:90,DEF:70,MED:46,DEL:21}, pitch='';
  POS.forEach(function(pos){
    var arr=rows[pos], n=arr.length;
    arr.forEach(function(j,i){
      /* margen de 10% a cada lado: con la ficha nueva (foto + placa) las
         posiciones de banda se salían del césped en el diseño anterior */
      var x=n===1?50:10+i*(80/(n-1));
      var tok=isHttp(j.foto)
        ? '<img src="'+esc(j.foto)+'" alt="" loading="lazy" referrerpolicy="no-referrer">'
        : '<span class="noimg">'+esc(((X(j.nombre)||'?').trim()[0]||'?').toUpperCase())+'</span>';
      pitch+='<div class="pp p-'+pos.toLowerCase()+'" style="left:'+x.toFixed(1)+'%;top:'+yOf[pos]+'%" data-team="'+esc(e.id)+'" data-player="'+esc(encodeURIComponent(j.nombre))+'" title="'+esc(X(j.nombre))+'">'+
        '<span class="tok">'+tok+'</span>'+
        '<span class="plate">'+(j.dorsal?'<span class="num">'+esc(j.dorsal)+'</span>':'')+'<span class="nm">'+esc(shortName(X(j.nombre)))+'</span></span>'+
      '</div>';
    });
  });

  function sq(j){
    return '<div class="squad-row" data-team="'+esc(e.id)+'" data-player="'+esc(encodeURIComponent(j.nombre))+'">'+
      '<span class="num">'+esc(j.dorsal||'')+'</span>'+
      avatar(j.foto,'',j.nombre)+
      '<span class="chip chip-'+String(j.posicion||'').toLowerCase()+'">'+esc(posAbbr(j.posicion))+'</span>'+
      '<span class="nm">'+esc(X(j.nombre))+'</span>'+
      afTag(j.afinidad)+
      '<span class="g">'+golesCarrera(j)+' G</span>'+
    '</div>';
  }
  /* Cabecera por línea: hace visible el orden y da un punto de anclaje al ojo */
  function squadBlock(list){
    var out='';
    POS.forEach(function(pos){
      var arr=list.filter(function(j){ return j.posicion===pos; });
      if(!arr.length) return;
      /* data-no-tr: la etiqueta ya viene traducida del diccionario. Sin esto,
         "Defensas · 3" viajaba entero a la API y volvía como "Defenders 3". */
      out+='<div class="squad-group" data-no-tr><i style="background:var(--pos-'+pos.toLowerCase()+')"></i>'+
        T('pos.'+pos.toLowerCase(),POS_LABEL[pos])+' · '+arr.length+'</div>'+arr.map(sq).join('');
    });
    var otros=list.filter(function(j){ return POS_ORDER[j.posicion]==null; });
    if(otros.length) out+='<div class="squad-group" data-no-tr><i style="background:var(--ink-4)"></i>'+T('pos.otros','Otros')+' · '+otros.length+'</div>'+otros.map(sq).join('');
    return out;
  }
  var c1=e.color1||'#222', c2=e.color2||'#111';

  $('sheet-team-body').innerHTML=
    '<header class="tm-hero">'+
      '<div class="tm-hero-wash" style="background:radial-gradient(ellipse 70% 100% at 12% 0%, '+esc(c1)+'55, transparent 70%),radial-gradient(ellipse 60% 90% at 60% 10%, '+esc(c2)+'33, transparent 70%)"></div>'+
      '<div class="tm-hero-in">'+
        (isHttp(e.escudo)?'<img class="tm-crest" src="'+esc(e.escudo)+'" alt="">':'')+
        '<div><h1 class="tm-name">'+esc(X(e.nombre))+'</h1>'+
          '<div class="tm-sub">'+(e.division==='SUPERLIGA'?'<span class="badge badge-superliga">'+esc(T('comp.superliga','Superliga Frontier'))+'</span>':'<span class="badge badge-ascenso">'+esc(T('comp.ascenso','Ascenso Frontier'))+'</span>')+
          '<span>'+esc(abbr3(e.nombre,e.abreviatura))+'</span>'+(e.ciudad?'<span>·</span><span>'+esc(e.ciudad)+'</span>':'')+
          '<span class="frm" style="margin-left:.5rem">'+form.map(function(r){ return '<i class="f-'+r+'"></i>'; }).join('')+'</span></div>'+
        '</div>'+
      '</div>'+
    '</header>'+
    '<div class="tm-stats">'+
      '<div class="tm-stat"><b class="mono">'+(e.pts||0)+'</b><span>'+T('st.puntos','Puntos')+'</span></div>'+
      '<div class="tm-stat"><b class="mono">'+(e.pj||0)+'</b><span>PJ</span></div>'+
      '<div class="tm-stat"><b class="mono">'+(e.g||0)+'</b><span>'+T('st.ganados','Ganados')+'</span></div>'+
      '<div class="tm-stat"><b class="mono">'+(e.e||0)+'</b><span>'+T('st.empates','Empates')+'</span></div>'+
      '<div class="tm-stat"><b class="mono">'+(e.p||0)+'</b><span>'+T('st.perdidos','Perdidos')+'</span></div>'+
      '<div class="tm-stat"><b class="mono">'+(e.gf||0)+'</b><span>GF</span></div>'+
      '<div class="tm-stat"><b class="mono">'+(dg>0?'+':'')+dg+'</b><span>'+T('st.dif','Dif.')+'</span></div>'+
    '</div>'+
    '<div class="tm-body">'+
      '<div>'+
        '<div class="sec-label">'+T('team.titulares','Plantilla · Titulares')+'</div><div style="margin-bottom:2.5rem">'+squadBlock(tit)+'</div>'+
        (sup.length?'<div class="sec-label">'+T('team.suplentes','Suplentes')+'</div><div>'+squadBlock(sup)+'</div>':'')+
      '</div>'+
      '<div>'+
        '<div class="sec-label">'+T('team.alineacion','Alineación titular')+'</div>'+
        '<div class="pitch">'+
          (e.formacion?'<span class="pitch-form mono">'+esc(e.formacion)+'</span>':'')+
          '<div class="pitch-lines"></div>'+
          '<span class="pitch-box pb-top"></span><span class="pitch-box pb-top-s"></span>'+
          '<span class="pitch-box pb-bot"></span><span class="pitch-box pb-bot-s"></span>'+
          '<div class="pitch-circle"></div>'+pitch+
        '</div>'+
        '<div class="pitch-legend">'+POS.map(function(p){
          return '<span><i style="background:var(--pos-'+p.toLowerCase()+')"></i>'+T('pos.'+p.toLowerCase(),POS_LABEL[p])+'</span>';
        }).join('')+'</div>'+
        '<div class="sec-label" style="margin-top:2.5rem">'+T('team.direccion','Dirección')+'</div>'+
        '<div class="staff-line"><span>'+T('team.entrenador','Entrenador')+'</span><b style="font-weight:500">'+esc(e.entrenador||'·')+'</b></div>'+
        '<div class="staff-line"><span>'+T('team.presidente','Presidente / Gerente')+'</span><b style="font-weight:500">'+esc(e.gerente||'·')+'</b></div>'+
        (e.formacion?'<div class="staff-line"><span>'+T('team.formacion','Formación')+'</span><b style="font-weight:500" class="mono">'+esc(e.formacion)+'</b></div>':'')+
      '</div>'+
    '</div>';
  openSheet('sheet-team');
}

function openPlayer(teamId,nameEnc){
  var e=bd.equipos.find(function(x){ return x.id===teamId; }); if(!e) return;
  var name=decodeURIComponent(nameEnc);
  var j=(e.jugadores||[]).find(function(p){ return p.nombre===name; }); if(!j) return;
  var goles=golesCarrera(j);
  var k=afKey(j.afinidad), hex=AF_HEX[k];
  var posCls=String(j.posicion||'').toLowerCase();

  var techs=(j.supertecnicas||[]).map(function(t){
    return '<div class="tech"><div class="tech-top"><b>'+esc(t.nombre)+'</b>'+(t.tipo?'<span class="badge">'+esc(t.tipo)+'</span>':'')+'</div>'+(t.descripcion?'<p>'+esc(t.descripcion)+'</p>':'')+'</div>';
  }).join('');

  /* HISTORIAL DE EQUIPOS — un logo enorme con nombre y temporada no decía
     nada. Ahora cada club es una fila desplegable con lo que de verdad se
     pregunta: cuántos goles marcó ahí, cuántas temporadas estuvo y si sigue
     en el club o ya lo dejó (campo `abierto` del JSON). */
  var hist=(j.historial||[]).map(function(h,hi){
    var cl=clubHist(h), te=cl.e;
    var ini=h.temporada_inicio||h.temporada||'', fin=h.temporada_fin||ini;
    var nIni=seasonNum(ini), nFin=seasonNum(fin);
    var rango=(nIni!=null&&nFin!=null&&nFin!==nIni)
      ? seasonLabel(ini)+' - '+nFin
      : (seasonLabel(ini)||'·');
    var activo=h.abierto===true;
    var temps=(nIni!=null&&nFin!=null)?Math.max(1,nFin-nIni+1):(nIni!=null?1:0);
    var divTxt=h.division==='ASCENSO'?T('comp.ascenso','Ascenso Frontier'):(h.division==='SUPERLIGA'?T('comp.superliga','Superliga Frontier'):(h.division||'·'));
    /* La etapa abierta suma también lo marcado en la temporada en curso, que
       el JSON todavía no ha volcado al historial. */
    var golesEtapa=(h.goles||0)+(activo&&te&&te.id===e.id?golesTemporada(j):0);
    return '<div class="hist-item" data-hist="'+hi+'">'+
      '<button class="hist-btn" type="button" aria-expanded="false">'+
        crest(te,30)+
        '<span class="hist-id"><b>'+esc(X(cl.actual))+'</b><span data-no-tr>'+esc(rango)+
          (cl.antes?'<em class="hist-antes"> · '+T('hist.entonces','entonces')+' '+esc(X(cl.antes))+'</em>':'')+'</span></span>'+
        '<span class="hist-state '+(activo?'hist-on':'hist-off')+'"><i></i>'+(activo?T('hist.activo','Activo'):T('hist.exclub','Ex-club'))+'</span>'+
        '<i class="ph ph-caret-down"></i>'+
      '</button>'+
      '<div class="hist-panel"><div><div class="hist-grid">'+
        /* Dos celdas arriba a mitad y mitad: al quitar «Partidos» la rejilla de
           tres dejaba un hueco suelto a la derecha. */
        '<div class="hist-cell hist-cell-half"><b class="mono">'+golesEtapa+'</b><span>'+T('hist.goles','Goles')+'</span></div>'+
        '<div class="hist-cell hist-cell-half"><b class="mono">'+temps+'</b><span>'+T('hist.temporadas','Temporadas')+'</span></div>'+
        /* «Partidos» no se muestra: el JSON trae pj=0 en las 423 entradas del
           historial y en los 658 jugadores, y sin datos de alineación por
           partido no hay forma honesta de deducirlo. Mejor no enseñar un cero
           que parece un dato. */
        '<div class="hist-cell hist-cell-wide"><span>'+T('hist.estado','Estado')+'</span><b>'+(activo?T('hist.sigue','Sigue en el club'):T('hist.dejo','Ya no está en el club'))+'</b></div>'+
        '<div class="hist-cell hist-cell-wide"><span>'+T('hist.division','División')+'</span><b>'+esc(divTxt)+'</b></div>'+
        ((h.asistencias||0)||(h.amarillas||0)||(h.rojas||0)
          ? '<div class="hist-cell"><b class="mono">'+(h.asistencias||0)+'</b><span>'+T('hist.asis','Asistencias')+'</span></div>'+
            '<div class="hist-cell"><b class="mono">'+(h.amarillas||0)+'</b><span>'+T('hist.amar','Amarillas')+'</span></div>'+
            '<div class="hist-cell"><b class="mono">'+(h.rojas||0)+'</b><span>'+T('hist.rojas','Rojas')+'</span></div>'
          : '')+
      '</div></div></div>'+
    '</div>';
  }).join('');

  $('sheet-player-body').innerHTML=
    '<header class="pl-hero">'+
      '<div class="pl-hero-bg" style="background:radial-gradient(ellipse 65% 80% at 72% 25%, '+hex+'40, transparent 68%),radial-gradient(ellipse 50% 60% at 10% 90%, '+hex+'18, transparent 70%)"></div>'+
      '<div class="pl-photo">'+(isHttp(j.foto)
        ? '<img src="'+esc(j.foto)+'" alt="" referrerpolicy="no-referrer">'
        : '<span class="noimg">'+esc((X(j.nombre)||'?').trim()[0])+'</span>')+'</div>'+
      '<div class="pl-hero-in">'+
        '<div class="pl-dorsal">'+esc(j.dorsal||'·')+'</div>'+
        '<h1 class="pl-name">'+esc(X(j.nombre))+'</h1>'+
        '<div class="pl-meta">'+
          '<span class="chip chip-'+posCls+'">'+esc(posName(j.posicion))+'</span>'+
          afTag(j.afinidad)+
          '<span class="pl-club" data-team="'+esc(e.id)+'" style="cursor:pointer">'+crest(e,22)+esc(X(e.nombre))+'</span>'+
        '</div>'+
      '</div>'+
    '</header>'+
    '<div class="pl-stats">'+
      '<div class="pl-stat"><b class="mono">'+goles+'</b><span>'+T('pl.goles','Goles')+'</span></div>'+
      '<div class="pl-stat"><b class="mono">'+golesTemporada(j)+'</b><span>'+T('pl.estatemp','esta temporada')+'</span></div>'+
      '<div class="pl-stat"><b class="mono">'+esc(j.dorsal||'·')+'</b><span>'+T('pl.dorsal','Dorsal')+'</span></div>'+
      '<div class="pl-stat"><b class="mono" style="font-size:clamp(1.25rem,2.4vw,1.75rem);color:'+hex+'">'+esc(afName(j.afinidad))+'</b><span>'+T('pl.afinidad','Afinidad')+'</span></div>'+
    '</div>'+
    '<div class="pl-body">'+
      '<div><div class="sec-label">'+T('pl.tecnicas','Súper técnicas')+'</div>'+(techs||'<p class="muted" style="font-size:.875rem">'+T('empty.tecnicas','Sin súper técnicas registradas.')+'</p>')+'</div>'+
      '<div>'+(hist?'<div class="sec-label">'+T('hist.title','Historial de equipos')+'</div>'+hist:'')+
        '<div class="sec-label" style="margin-top:2.5rem">'+T('pl.compartir','Compartir')+'</div>'+
        '<button class="btn btn-secondary" data-share-player="'+esc(e.id)+'|'+esc(encodeURIComponent(j.nombre))+'"><i class="ph-bold ph-download-simple"></i> Descargar carta</button>'+
      '</div>'+
    '</div>';
  openSheet('sheet-player');
}

/* Busca al autor de un evento dentro del equipo que lo anotó; si no aparece
   (jugador traspasado, nombre abreviado), cae al buscador global. */
function playerIn(teamName,nm){
  var e=team(teamName);
  if(e){
    var n=norm(nm);
    var f=(e.jugadores||[]).find(function(p){ var pn=norm(p.nombre); return pn===n||pn.indexOf(n)===0||n.indexOf(pn.split(' ')[0])===0; });
    if(f) return {j:f,e:e};
  }
  return findPlayer(nm);
}

function openMatch(comp,idx){
  var p=poolOf(comp)[idx]; if(!p) return;
  var L=team(p.local), V=team(p.visitante), pen=!isFin(p);
  var a=gl(p), b=gv(p);
  /* CRONOLOGÍA — una fila por evento, una sola línea, monocroma.
     Del jugador sólo se muestra lo que aporta de un vistazo: foto, posición
     abreviada y un punto con el color de su afinidad. Sin naranja de
     competición: el color aquí es dato, no decoración. */
  var evs=[];
  (p.detalles||'').split('/').forEach(function(half,side){
    half.split(',').forEach(function(ev){
      var q=ev.trim().split(':');
      if(q.length>=3&&q[0].trim()){
        var tipo=q[0].trim(), nm=q[1].trim(), min=q[2].trim();
        if(!nm) return;
        var ic=tipo==='gol'?'<i class="ph-bold ph-soccer-ball"></i>'
          :tipo==='amarilla'?'<span class="ev-card" style="background:#FFC94A"></span>'
          :tipo==='roja'?'<span class="ev-card" style="background:#FF3B3B"></span>'
          :'<i class="ph-bold ph-arrows-clockwise"></i>';
        var club=side===0?p.local:p.visitante, f=playerIn(club,nm);
        var ce=team(club), j=f&&f.j;
        evs.push({min:parseInt(min)||0,html:
          '<div class="ev"'+(j&&f.e?' data-team="'+esc(f.e.id)+'" data-player="'+esc(encodeURIComponent(j.nombre))+'"':'')+'>'+
            '<span class="ev-min">'+esc(min)+'′</span>'+
            '<span class="ev-ic">'+ic+'</span>'+
            avatar(j?j.foto:'','ev-face',nm)+
            '<span class="ev-name">'+esc(X(j?j.nombre:nm))+'</span>'+
            (j&&j.posicion?'<span class="ev-pos">'+esc(posAbbr(j.posicion))+'</span>':'')+
            (j?'<span class="af-dot" style="--afc:'+AF_HEX[afKey(j.afinidad)]+'" title="'+esc(afName(j.afinidad))+'"></span>':'')+
            '<span class="ev-club">'+(comp==='copa'?divIcon(ce):'')+crest(ce,18)+'<span>'+esc(X(club))+'</span></span>'+
          '</div>'});
      }
    });
  });
  evs.sort(function(x,y){ return x.min-y.min; });
  var d=derbi(p.local,p.visitante);
  /* Split de color por equipo (sistema híbrido): base negra + lavado del color
     de cada club en su mitad. Los nombres se quedan en tinta neutra porque
     algunos colores de club son casi negros y no habría contraste. */
  var cl=wash(L,'#FF5100'), cv=wash(V,'#3E7BFF');
  function teamCol(t,name,side){
    var c1=(t&&t.color1)||'#3A3A3A', c2=(t&&t.color2)||'#141414';
    return '<div class="mt-team mt-team-'+side+'"'+(t?' data-team="'+esc(t.id)+'"':'')+'>'+
      crest(t,86)+
      '<span class="mt-bar" style="background:linear-gradient(90deg,'+esc(c1)+','+esc(c2)+')"></span>'+
      '<b>'+esc(X(name))+'</b>'+
      (comp==='copa'?'<span class="mt-divi">'+divIcon(t)+(t?(t.division==='ASCENSO'?T('comp.ascenso','Ascenso Frontier'):T('comp.superliga','Superliga Frontier')):'')+'</span>':'')+
    '</div>';
  }

  $('sheet-match-body').innerHTML=
    '<header class="mt-hero">'+
      '<div class="mt-split" style="--cl:'+esc(cl)+';--cv:'+esc(cv)+'"></div><span class="mt-seam"></span>'+
      '<div class="mt-hero-in">'+
        '<div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">'+(d?'<span class="badge '+d.c+'">'+d.t+'</span>':'')+compBadge(comp)+'<span class="badge" data-no-tr>'+(p.fase?esc(faseName(p.fase)):T('jornada','Jornada')+' '+esc(p.jornada||''))+'</span></div>'+
        '<div class="mt-teams">'+
          teamCol(L,p.local,'l')+
          (pen?'<span class="mt-pending">VS</span>':'<div class="mt-score mono"><span>'+a+'</span><span class="dash">–</span><span>'+b+'</span></div>')+
          teamCol(V,p.visitante,'v')+
        '</div>'+
      '</div>'+
    '</header>'+
    '<div class="mt-body">'+
      (pen
        ? '<div class="ev-empty">'+T('match.pendiente','Este enfrentamiento todavía no se ha jugado.')+'</div>'
        : '<div class="sec-label">'+T('match.crono','Cronología')+'</div>'+(evs.length?'<div class="ev-list">'+evs.map(function(x){ return x.html; }).join('')+'</div>':'<div class="ev-empty">'+T('empty.eventos','Sin eventos detallados para este partido.')+'</div>')+
          '<div class="mt-cards"><button class="btn btn-secondary" data-share-match="'+esc(comp)+'|'+idx+'"><i class="ph-bold ph-download-simple"></i> '+T('share.match','Descargar resultado')+'</button></div>')+
    '</div>';
  openSheet('sheet-match');
}

/* ==========================================================================
   NOTICIAS
   ========================================================================== */
var newsIdx=0, newsTag='TODOS', newsList=[];
function renderNews(){
  newsList=(bd.noticias||[]).slice(); newsIdx=0;
  var tags=['TODOS'].concat(Array.from(new Set((bd.noticias||[]).map(function(n){ return n.tag; }).filter(Boolean))));
  $('news-filters').innerHTML=tags.map(function(t){ return '<button class="filter'+(t===newsTag?' on':'')+'" data-tag="'+esc(t)+'">'+esc(t)+'</button>'; }).join('');
  newsSlide();
}
function newsSlide(){
  var n=newsList[newsIdx], el=$('news-slide');
  if(!n){ el.innerHTML='<p class="muted">'+T('empty.noticias','Sin noticias.')+'</p>'; return; }
  el.innerHTML='<article class="news spotlight" data-news="'+newsIdx+'">'+
    '<div class="news-img">'+(isHttp(n.imagen)?'<img src="'+esc(n.imagen)+'" alt="" referrerpolicy="no-referrer">':'<span class="noimg" style="width:100%;height:100%"></span>')+'</div>'+
    '<div class="news-body"><span class="badge" style="align-self:flex-start;color:'+esc(n.color||'#FFC94A')+'">'+esc(n.tag||'')+'</span>'+
      '<h3>'+esc(n.titulo)+'</h3><p>'+esc(n.resumen)+'</p>'+
      '<div class="news-foot">'+esc(n.autor||'')+' · '+esc(n.fecha||'')+'</div>'+
    '</div></article>';
}
/* La noticia abierta ocupa el ancho completo: portada a sangre y cuerpo en dos
   columnas (ficha lateral + texto). Por debajo de 1000px el CSS lo devuelve a
   una sola columna, que es donde el ancho total dejaría de ayudar. */
function openNews(i){
  var n=newsList[i]; if(!n) return;
  var hasImg=isHttp(n.imagen);
  $('sheet-news-body').innerHTML=
    '<article class="art">'+
      '<header class="art-hero'+(hasImg?'':' art-hero-plain')+'">'+
        (hasImg?'<div class="art-hero-img"><img src="'+esc(n.imagen)+'" alt="" referrerpolicy="no-referrer"></div>':'')+
        '<div class="art-hero-in">'+
          '<span class="badge" style="color:'+esc(n.color||'#FFC94A')+'">'+esc(n.tag||T('sheet.news','Noticia'))+'</span>'+
          '<h1 class="art-title">'+esc(n.titulo)+'</h1>'+
          (n.resumen?'<p class="art-lede">'+esc(n.resumen)+'</p>':'')+
        '</div>'+
      '</header>'+
      '<div class="art-body">'+
        '<aside class="art-side">'+
          '<dl class="art-meta">'+
            '<dt>'+T('news.autor','Autor')+'</dt><dd>'+esc(n.autor||'·')+'</dd>'+
            '<dt>'+T('news.fecha','Fecha')+'</dt><dd>'+esc(n.fecha||'·')+'</dd>'+
            (n.tag?'<dt>'+T('news.seccion','Sección')+'</dt><dd>'+esc(n.tag)+'</dd>':'')+
          '</dl>'+
          '<a href="https://discord.gg/KgEBHA87fF" target="_blank" rel="noopener" class="btn btn-secondary"><i class="ph-bold ph-discord-logo"></i> '+T('news.comentar','Comentar en Discord')+'</a>'+
        '</aside>'+
        '<div class="art-text"><p>'+esc(n.cuerpo||'')+'</p></div>'+
      '</div>'+
    '</article>';
  openSheet('sheet-news');
}

/* ==========================================================================
   STAFF — escudo real del club de cada organizador
   El icono genérico de escudo no decía nada: cada persona dirige un club
   concreto y ese escudo ya existe en el JSON. Se inyecta al cargar los datos.
   ========================================================================== */
function renderStaffClubs(){
  document.querySelectorAll('.staff-club[data-club]').forEach(function(el){
    var nombre=el.dataset.club;
    var e=bd.equipos.find(function(x){ return x.nombre===nombre; });
    var ic=el.querySelector('.staff-crest');
    if(!ic) return;
    ic.innerHTML = e&&isHttp(e.escudo)
      ? '<img src="'+esc(e.escudo)+'" alt="" loading="lazy">'
      : '<i class="ph-bold ph-shield-chevron"></i>';
  });
}
window.renderStaffClubs=renderStaffClubs;

/* ==========================================================================
   HISTORIA — antigüedad automática y palmarés por temporada
   ========================================================================== */
/* La liga se fundó en enero de 2026. El "7 meses de recorrido" estaba escrito
   a mano y caducaba solo: aquí se calcula cada vez que se carga la página y
   pasa a años en cuanto se cumplen doce meses. */
var FUNDACION=new Date(2026,0,1);
function antiguedad(now){
  now=now||new Date();
  var m=(now.getFullYear()-FUNDACION.getFullYear())*12+(now.getMonth()-FUNDACION.getMonth());
  if(now.getDate()<FUNDACION.getDate()) m--;
  if(m<1) return T('edad.inicio','Recién nacida');
  // japonés y coreano no separan con espacio: "7か月の歩み", no "7 か月 の歩み"
  var lang=window.sfGetLang?sfGetLang():'es', s=(lang==='ja'||lang==='ko')?'':' ';
  function j(){ return Array.prototype.slice.call(arguments).join(s); }
  var meses=function(n){ return n===1?T('edad.mes','mes'):T('edad.meses','meses'); };
  if(m<12) return j(m,meses(m),T('edad.recorrido','de recorrido'));
  var y=Math.floor(m/12), r=m%12;
  var ys=j(y,(y===1?T('edad.ano','año'):T('edad.anos','años')));
  if(!r) return j(ys,T('edad.recorrido','de recorrido'));
  return j(ys,T('edad.y','y'),r,meses(r),T('edad.recorrido','de recorrido'));
}
function renderAntiguedad(){
  var el=$('hist-edad'); if(el) el.textContent=antiguedad();
  /* La temporada en curso sale del JSON, no escrita a mano: decía
     "Temporada 2" cuando la config ya iba por la 3. */
  var t=$('hero-temp');
  if(t) t.textContent=T('temporada','Temporada')+' '+(parseInt(bd.config.temporada)||3)+' · '+T('hero.enjuego','En juego');
}
/* Se repinta al cambiar de idioma: el texto se compone de claves del
   diccionario y está marcado data-no-tr, así que nadie más lo tocaría. */
window.renderAntiguedad=renderAntiguedad;

/* PALMARÉS: se deriva de historial_temporadas en vez de escribirlo a mano —
   campeón de cada división por clasificación final y ganador de Copa por el
   resultado de la FINAL. Los presidentes reales viven aquí porque el campo
   `gerente` del JSON guarda el personaje del juego, no al manager. */
var PRESIDENTES={'Alpino':'david.gonzzalezc','Zanark Domain':'D4rkRepulser','Inazuma Kids FC':'Totti Alcresise','Academia Plenilunio':'Payo Aguao','Criaturas de la Noche':'Franshu','Gar':'Gabrii'};
/* Se indexa por posición, no por `nombre`: el snapshot archivado se llama
   "Temporada 1" en el JSON pero es la que la web narra como Temporada 2
   (la del Alpino campeón). La etiqueta la pone la propia tarjeta. */
function palmares(idx){
  var t=(bd.historial_temporadas||[])[idx];
  if(!t) return null;

  /* Campeones apuntados a mano. Mandan sobre lo deducido COMPETICIÓN A
     COMPETICIÓN, no en bloque: apuntar sólo el de Superliga no debe borrar del
     palmarés al de Ascenso ni al de Copa. Hacen falta porque champ() deduce el
     campeón como «el que más puntos tiene», y en una liga con play-off campeón
     es quien gana la final, no el primero de la fase regular. */
  var puestos=(Array.isArray(t.campeones)?t.campeones:[]).filter(function(x){ return x&&x.equipo; });
  function apuntado(clave){
    var g=puestos.filter(function(x){ return x.comp===clave; })[0];
    if(!g) return null;
    var e=(t.equipos||[]).filter(function(x){ return x.id===g.equipo_id||x.nombre===g.equipo; })[0];
    return e?{e:e,marcador:g.marcador||''}:null;
  }

  function champ(div){
    var l=(t.equipos||[]).filter(function(e){ return e.division===div; })
      .sort(function(a,b){ return (b.pts||0)-(a.pts||0)||((b.gf-b.gc)-(a.gf-a.gc))||(b.gf-a.gf); });
    return l[0]||null;
  }
  var out=[];
  function fila(clave,div,comp,cls){
    var ap=apuntado(clave), e=ap?ap.e:champ(div);
    if(e) out.push({comp:comp,cls:cls,e:e,marcador:ap?ap.marcador:''});
  }
  fila('SUPERLIGA','SUPERLIGA',T('comp.superliga','Superliga Frontier'),'badge-superliga');
  fila('ASCENSO','ASCENSO',T('comp.ascenso','Ascenso Frontier'),'badge-ascenso');

  var apCopa=apuntado('COPA');
  if(apCopa){
    out.push({comp:T('sec.copa','Copa Fútbol Frontier'),cls:'badge-copa',e:apCopa.e,marcador:apCopa.marcador});
  }else{
    var fin=(t.partidos_copa||[]).filter(function(p){ return p.fase==='FINAL'&&isFin(p); })[0];
    if(fin){
      var wn=gl(fin)>gv(fin)?fin.local:(gv(fin)>gl(fin)?fin.visitante:winnerOf(fin));
      var ce=(t.equipos||[]).find(function(e){ return e.nombre===wn; });
      if(ce) out.push({comp:T('sec.copa','Copa Fútbol Frontier'),cls:'badge-copa',e:ce,marcador:fin.local+' '+gl(fin)+'-'+gv(fin)+' '+fin.visitante});
    }
  }
  return out.length?out:null;
}
function openChamps(idx,label){
  var list=palmares(idx); if(!list) return;
  $('champ-title').textContent=T('champs.title','Palmarés')+' · '+label;
  $('champs').innerHTML=list.map(function(c){
    var live=bd.equipos.find(function(x){ return x.nombre===c.e.nombre; });
    var c1=(live&&live.color1)||c.e.color1||'#3A3A3A', c2=(live&&live.color2)||c.e.color2||'#141414';
    var pres=PRESIDENTES[c.e.nombre];
    return '<div class="champ"'+(live?' data-team="'+esc(live.id)+'"':'')+'>'+
      '<span class="champ-wash" style="background:radial-gradient(ellipse 80% 130% at 0% 50%,'+esc(wash(live||c.e,c1))+',transparent 68%)"></span>'+
      (isHttp(c.e.escudo)?'<img class="champ-crest" src="'+esc(c.e.escudo)+'" alt="" loading="lazy">':'<span class="champ-crest noimg">'+esc(abbr3(c.e.nombre))+'</span>')+
      '<div class="champ-id">'+
        '<b>'+esc(X(c.e.nombre))+'</b>'+
        '<span class="pres"><i class="ph-bold ph-user-circle"></i>'+esc(pres||c.e.gerente||'·')+'</span>'+
      '</div>'+
      '<div class="champ-trophy">'+
        '<i class="ph-bold ph-trophy"></i>'+
        '<span class="badge '+c.cls+'">'+c.comp+'</span>'+
        (c.marcador?'<span class="mono" style="font-size:.6875rem;color:var(--ink-5)">'+esc(c.marcador)+'</span>':'<span class="mono" style="font-size:.6875rem;color:var(--ink-5)">'+(c.e.pts||0)+' pts</span>')+
      '</div>'+
    '</div>';
  }).join('');
  $('ov-champs').classList.add('open');
}

/* ==========================================================================
   BÚSQUEDA
   ========================================================================== */
var sTab='todo', sActions=[];
function openSearch(){ $('ov-search').classList.add('open'); setTimeout(function(){ $('q').focus(); },60); }
function closeSearch(){ $('ov-search').classList.remove('open'); }
function doSearch(q){
  var nq=norm(q), out=$('srch-out'); sActions=[];
  if(!nq){ out.innerHTML='<div class="srch-empty">'+T('search.hint','Escribe para buscar equipos, jugadores o noticias.')+'</div>'; return; }
  var res=[];
  if(sTab==='todo'||sTab==='equipos'){
    bd.equipos.filter(function(e){ return !e.archivado; }).forEach(function(e){
      if((norm(e.nombre)+' '+norm(X(e.nombre))+' '+norm(e.ciudad||'')).indexOf(nq)>=0)
        res.push({s:norm(e.nombre).indexOf(nq)===0?2:1,label:X(e.nombre),sub:e.division==='SUPERLIGA'?'Superliga Frontier':'Ascenso Frontier',img:e.escudo,noTr:true,go:function(){ closeSearch(); openTeam(e.id); }});
    });
  }
  if(sTab==='todo'||sTab==='jugadores'){
    bd.equipos.filter(function(e){ return !e.archivado; }).forEach(function(e){
      (e.jugadores||[]).forEach(function(j){
        if((norm(j.nombre)+' '+norm(X(j.nombre))).indexOf(nq)>=0)
          res.push({s:norm(j.nombre).indexOf(nq)===0?2:1,label:X(j.nombre),sub:X(e.nombre)+' · '+j.posicion,img:j.foto,noTr:true,go:function(){ closeSearch(); openPlayer(e.id,encodeURIComponent(j.nombre)); }});
      });
    });
  }
  if(sTab==='todo'||sTab==='noticias'){
    (bd.noticias||[]).forEach(function(n,i){
      if((norm(n.titulo)+' '+norm(n.resumen||'')+' '+norm(n.tag||'')).indexOf(nq)>=0)
        res.push({s:norm(n.titulo).indexOf(nq)===0?2:1,label:n.titulo,sub:n.tag||'Noticia',img:n.imagen,go:function(){ closeSearch(); newsList=(bd.noticias||[]).slice(); openNews(i); }});
    });
  }
  res.sort(function(a,b){ return b.s-a.s||a.label.localeCompare(b.label); });
  res=res.slice(0,40);
  if(!res.length){ out.innerHTML='<div class="srch-empty">'+T('search.empty','Sin resultados para esta búsqueda')+'</div>'; return; }
  sActions=res.map(function(r){ return r.go; });
  out.innerHTML=res.map(function(r,i){
    /* Equipos y jugadores: nombre propio + abreviatura de posición, nunca se
       traducen (data-no-tr). Noticias: el tag SÍ debe traducirse, se deja tal cual. */
    return '<div class="srch-item" data-si="'+i+'"'+(r.noTr?' data-no-tr':'')+'>'+
      (isHttp(r.img)?'<img src="'+esc(r.img)+'" alt="" referrerpolicy="no-referrer">':'<span class="noimg">'+esc((r.label||'?').trim()[0])+'</span>')+
      '<div style="min-width:0"><b>'+hl(r.label,q)+'</b><span>'+esc(r.sub)+'</span></div></div>';
  }).join('');
}

/* ==========================================================================
   FAQ / RESEÑAS
   ========================================================================== */
var FAQ=[
  {c:'Empezar en la liga',items:[
    ['¿Cómo me uno a la Superliga Frontier?','Entrando al Discord de la comunidad y presentándote en el canal de incorporaciones. Desde ahí el staff te explica el estado de la temporada en curso y si hay plaza libre de manager o entras en la lista para la siguiente.'],
    ['¿Hay que pagar algo para participar?','No. Es un proyecto de fans sin ánimo de lucro: no se cobra inscripción, no hay suscripciones y la liga no genera ningún beneficio económico.'],
    ['¿Necesito tener un nivel alto para entrar?','No. Existen dos divisiones precisamente para que cada manager compita contra gente de su nivel. Si empiezas, lo normal es hacerlo en el Ascenso Frontier.'],
    ['¿Puedo entrar con la temporada empezada?','Depende de si queda algún club sin manager. Si no lo hay, se entra en la lista de espera para la siguiente temporada o para el mercado de fichajes.'],
    ['¿Se puede participar desde fuera de España?','Sí. Hay managers de varios países y la web está disponible en varios idiomas. Lo único que hay que cuadrar es el horario de los partidos con tu rival.'],
    ['¿Qué necesito para jugar?','Inazuma Eleven: Victory Road y una cuenta de Discord. Nada más.'],
    ['¿Quién creó la Superliga Frontier?','Franshu, en enero de 2026. Ha jugado las tres temporadas como manager antes de dedicarse por completo a organizar la liga; puedes ver su ficha en la sección de Staff.']
  ]},
  {c:'Reglas de juego',items:[
    ['¿Qué es un "jugador verde"?','Un jugador tal y como sale de su invocación inicial, sin que se le hayan cambiado las pasivas después. El azar de la invocación existe: lo prohibido es tocar, cambiar o mejorar una pasiva una vez invocada.'],
    ['¿Por qué se prohíbe el reroll de pasivas?','Porque convierte la competición en una carrera de tiempo invertido en reintentar invocaciones en lugar de una carrera de decisiones de manager. Con jugadores verdes, todos parten de la misma regla.'],
    ['¿Se permiten tácticas de RNG?','No. Están prohibidas las tácticas que dependen de la suerte en vez de la decisión del manager durante el partido.'],
    ['¿Cuántas súper técnicas puede llevar un jugador?','Cuatro súper técnicas canónicas por jugador, tal y como existen en el juego.'],
    ['¿Qué equipamiento se permite?','Solo judías, sin limitaciones adicionales más allá de eso.'],
    ['¿Qué pasa si alguien incumple el reglamento?','El staff revisa el caso con las pruebas del partido. Según la gravedad puede suponer la repetición del encuentro, la pérdida del partido o una sanción de mercado.'],
    ['¿Cómo se registran los resultados?','Cada partido se reporta al staff con su resultado y sus goleadores, y de ahí salen la clasificación, la tabla de goleadores y las fichas de jugador de esta web.']
  ]},
  {c:'Mercado y plantillas',items:[
    ['¿Qué es el tope salarial?','Un límite de 250M por equipo en el mercado de fichajes. Reparte el nivel entre clubes y evita que uno solo acumule todo el talento top-tier.'],
    ['¿Cuándo abre el mercado de fichajes?','Entre temporadas, y con una ventana concreta anunciada por el staff en Discord. Fuera de esa ventana las plantillas quedan cerradas.'],
    ['¿Puedo fichar a un jugador de otro club?','Sí, negociando con su manager y siempre que la operación quepa dentro del tope salarial de ambos equipos.'],
    ['¿Qué son los agentes libres?','Jugadores sin club en ese momento. Pueden incorporarse a una plantilla durante el mercado respetando igualmente el tope salarial.'],
    ['¿Cuántos jugadores tiene una plantilla?','Once titulares más suplentes. En la ficha de cada equipo puedes ver la plantilla completa ordenada por posición y dorsal.'],
    ['¿Se pueden cambiar los dorsales o el once titular?','El once titular lo decide el manager para cada partido. Los dorsales se mantienen dentro de la temporada para que las fichas y las estadísticas sean coherentes.']
  ]},
  {c:'Formato de competición',items:[
    ['¿Cuántas divisiones hay?','Dos: la Superliga Frontier (1ª división) y el Ascenso Frontier (2ª división), con ascenso y descenso entre ambas cada temporada.'],
    ['¿Cómo se decide el campeón de la Superliga Frontier?','La liga regular fija las posiciones. Los 6 primeros disputan la fase final: 5º contra 6º, el ganador juega el Play In contra el 4º (local por mejor clasificación), y el ganador entra al Play Off junto al 1º, 2º y 3º.'],
    ['¿Cómo se asciende y se desciende?','Los tres primeros del Ascenso Frontier suben a la Superliga y los tres últimos de la Superliga bajan al Ascenso.'],
    ['¿Cómo funciona la Copa Fútbol Frontier?','Ronda previa (7º-10º y 8º-9º de Ascenso) → Ronda 2 (los 6 restantes de Ascenso + los 2 clasificados) → Fase de grupos (12 de Superliga + 4 de Ascenso, 4 grupos de 4, pasan los 2 primeros) → Cuartos, semifinales y final.'],
    ['¿Cómo se desempata en la clasificación?','Por puntos; después diferencia de goles, goles a favor, goles en contra, partidos ganados, empatados, perdidos, partidos jugados y, por último, orden alfabético.'],
    ['¿Y en la tabla de goleadores?','Por goles marcados. Cuando varios jugadores empatan, se ordenan alfabéticamente entre ellos: quién marcó antes en el calendario no es un criterio deportivo.'],
    ['¿Qué pasa si una eliminatoria acaba en empate?','Se resuelve en penaltis, y el resultado de la tanda queda reflejado en el detalle del partido.'],
    ['¿Con qué frecuencia se juega?','Tres jornadas semanales, con horarios flexibles para adaptarse a la disponibilidad de los managers.'],
    ['¿Qué pasa si un manager no puede jugar su partido?','Se reprograma dentro de la jornada siempre que sea posible. Si aun así no se juega, el staff decide según el reglamento de incomparecencias.']
  ]},
  {c:'La web y los datos',items:[
    ['¿Cada cuánto se actualizan los datos?','Después de cada jornada. Clasificación, resultados, goleadores y fichas salen todos del mismo archivo de datos oficial de la liga.'],
    ['¿Qué significan los colores de la clasificación?','Marcan las zonas de Play Off, Play In, partido por el Play In y descenso en la Superliga, y la zona de ascenso en el Ascenso Frontier. La leyenda está justo debajo de la tabla.'],
    ['¿Qué son las afinidades elementales?','La afinidad de cada jugador dentro del juego: Fuego, Montaña, Bosque, Aire y Neutro. Aparecen en su ficha, en la tabla de goleadores y en el detalle de cada partido.'],
    ['¿Puedo compartir la ficha de un jugador o un resultado?','Sí. En la ficha de jugador y en el detalle de partido hay un botón para descargar una tarjeta en imagen, lista para publicar.'],
    ['¿En qué idiomas está la web?','En español, inglés, portugués, italiano, francés, japonés, coreano, polaco, búlgaro y serbio, desde el selector de idioma de la cabecera.'],
    ['¿La web es oficial de Level-5?','No. El arte de jugadores y equipos pertenece a Level-5, creadores de Inazuma Eleven. Superliga Frontier es un proyecto de fans sin ánimo de lucro.']
  ]}
];
/* El FAQ se pinta desde el diccionario (faq.cN / faq.qN.M / faq.aN.M). El
   array FAQ de arriba queda como respaldo en español: si una clave faltase,
   T() cae a ese texto en lugar de dejar el hueco vacío. */
function renderFaq(){
  $('faq-list').innerHTML=FAQ.map(function(cat,ci){
    return '<div class="faq-cat" data-faq="'+ci+'"><button><span>'+esc(T('faq.c'+ci,cat.c))+'</span><i class="ph ph-plus"></i></button>'+
      '<div class="faq-body"><div>'+cat.items.map(function(it,ii){
        return '<div class="qa" data-qa="'+ci+'-'+ii+'"><button><span>'+esc(T('faq.q'+ci+'.'+ii,it[0]))+'</span><i class="ph ph-plus"></i></button>'+
          '<div class="qa-a"><div><p>'+esc(T('faq.a'+ci+'.'+ii,it[1]))+'</p></div></div></div>';
      }).join('')+'</div></div></div>';
  }).join('');
}
/* Se repinta al cambiar de idioma, como el resto de vistas dinámicas. */
window.renderFaq=renderFaq;
/* q.a es una CLAVE ('superliga'/'ascenso'/'exjugador'), no el texto final: se
   resuelve en renderQuotes() vía resenas.rol.*. Antes era literal en español
   ("Manager de Superliga Frontier") y, aunque protegido de la traducción
   automática (.quote footer b), nunca se localizaba: salía en español en los
   10 idiomas (audit Tarea 2.3). */
var QUOTES=[
  {t:'Llevo tres ligas distintas probadas y esta es la única donde perder no se siente como una excusa de mala suerte del rival.',a:'superliga',s:'Contenido de ejemplo',i:'assets/franshu.png',n:9.2},
  {t:'El tope salarial cambia la mentalidad completamente. No puedes fichar la solución fácil: tienes que jugar mejor.',a:'ascenso',s:'Contenido de ejemplo',i:'assets/gabrii.png',n:8.7},
  {t:'Dejé Victory Road hace meses. Volví solo por esta liga y no me arrepiento.',a:'exjugador',s:'Contenido de ejemplo',i:'assets/totti_alcresise.png',n:9.5}
];
function renderQuotes(){
  /* QUOTES es el respaldo: si el archivo trae reseñas propias mandan ellas, con
     la misma forma {t,a,s,i,n}. Su campo de autor será texto libre y no una clave de rol,
     y T() ya cae al propio valor cuando no encuentra la clave. */
  var lista=(bd.config&&Array.isArray(bd.config.resenas)&&bd.config.resenas.length)?bd.config.resenas:QUOTES;
  $('quotes').innerHTML=lista.map(function(q,i){
    var n=Math.max(0,Math.min(10,q.n||0));
    var rol=T('resenas.rol.'+q.a,q.a);
    return '<blockquote class="card spotlight quote rv rv-d'+(i+1)+'">'+
      '<div class="q-top"><span class="q-mark" aria-hidden="true">“</span>'+
        '<span class="q-score" title="'+T('resenas.nota','Nota del manager')+'"><b>'+n.toFixed(1)+'</b><span>/10</span></span>'+
      '</div>'+
      '<p>'+esc(q.t)+'”</p>'+
      /* --w lo consume el CSS: la barra crece de 0 a la nota cuando la tarjeta
         entra en pantalla (clase .in del observador de reveals). */
      '<span class="q-bar" aria-hidden="true" style="--w:'+(n*10)+'%"><i></i></span>'+
      '<footer><img src="'+esc(q.i||'')+'" alt="" loading="lazy"><div><b>'+esc(rol)+'</b><span>'+esc(q.s)+'</span></div></footer>'+
    '</blockquote>';
  }).join('');
}
window.renderQuotes=renderQuotes; // faltaba: sin esto i18n.js nunca la repinta al cambiar idioma

/* ==========================================================================
   TARJETAS COMPARTIBLES (canvas)

   FOTO DEL JUGADOR: el CDN que sirve los retratos responde sin cabecera
   Access-Control-Allow-Origin (comprobado: `vary: Origin` pero ningún ACAO),
   así que cargarla con crossOrigin falla y sin crossOrigin contamina el
   canvas y toDataURL lanza. Por eso se reintenta a través de un proxy de
   imágenes que sí manda ACAO. Si el proxy también falla, la ficha cae a la
   inicial del jugador y la tarjeta sigue saliendo.
   ponytail: el día que los retratos se sirvan desde dominio propio, sobra
   el proxy y basta con la carga directa.
   ========================================================================== */
var IMG_PROXY='https://images.weserv.nl/?url=';
function rawLoad(src,cors){
  return new Promise(function(res){
    var i=new Image();
    if(cors) i.crossOrigin='anonymous';
    i.onload=function(){ res(i); };
    i.onerror=function(){ res(null); };
    i.src=src;
  });
}
function loadImg(src){
  if(!src) return Promise.resolve(null);
  if(!isHttp(src)) return rawLoad(src,false);           // assets locales
  return rawLoad(src,true).then(function(i){
    if(i) return i;
    return rawLoad(IMG_PROXY+encodeURIComponent(String(src).replace(/^https?:\/\//,''))+'&output=png&n=-1',true);
  });
}
function cover(ctx,img,x,y,w,h){
  var ir=img.width/img.height, r=w/h, sx,sy,sw,sh;
  if(ir>r){ sh=img.height; sw=sh*r; sy=0; sx=(img.width-sw)/2; }
  else { sw=img.width; sh=sw/r; sx=0; sy=(img.height-sh)/2; }
  ctx.drawImage(img,sx,sy,sw,sh,x,y,w,h);
}
function contain(ctx,img,cx,cy,max){
  var s=Math.min(max/img.width,max/img.height);
  ctx.drawImage(img,cx-img.width*s/2,cy-img.height*s/2,img.width*s,img.height*s);
}
function rr(ctx,x,y,w,h,r){
  r=Math.min(r,w/2,h/2);
  ctx.beginPath();
  ctx.moveTo(x+r,y);
  ctx.arcTo(x+w,y,x+w,y+h,r);
  ctx.arcTo(x+w,y+h,x,y+h,r);
  ctx.arcTo(x,y+h,x,y,r);
  ctx.arcTo(x,y,x+w,y,r);
  ctx.closePath();
}
function pillWidth(ctx,text,font,padX){ ctx.font=font; return ctx.measureText(text).width+padX*2; }
function pill(ctx,x,y,text,font,fg,bg,padX,h){
  ctx.font=font;
  var w=ctx.measureText(text).width+padX*2;
  ctx.fillStyle=bg; rr(ctx,x,y,w,h,h/2); ctx.fill();
  ctx.fillStyle=fg; ctx.textAlign='left'; ctx.textBaseline='middle';
  ctx.fillText(text,x+padX,y+h/2+.5);
  ctx.textBaseline='alphabetic';
  return w;
}
/* Ajusta el cuerpo hasta que el texto quepa: los nombres de esta liga van de
   "Gar" a "Raleigh Greenstreet" y no puede salirse ninguno de la tarjeta. */
function fit(ctx,text,max,weight,start,min,family){
  var s=start;
  do{ ctx.font=weight+' '+s+'px '+(family||'Inter, sans-serif'); s-=2; }
  while(ctx.measureText(text).width>max && s>min);
  return ctx.font;
}
function hex2rgba(h,a){
  var c=String(h||'').replace('#','');
  if(c.length===3) c=c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
  var n=parseInt(c,16);
  if(isNaN(n)||c.length!==6) return 'rgba(255,255,255,'+a+')';
  return 'rgba('+((n>>16)&255)+','+((n>>8)&255)+','+(n&255)+','+a+')';
}
function dl(cv,name){
  try{
    var a=document.createElement('a');
    a.download=String(name).replace(/[\\/:*?"<>|]/g,'-');
    a.href=cv.toDataURL('image/png'); a.click();
  }catch(e){ console.error('La tarjeta no se pudo exportar:',e); }
}
async function fonts(){
  if(!document.fonts||!document.fonts.load) return;
  try{
    await Promise.all([
      document.fonts.load('700 64px Inter'), document.fonts.load('600 24px Inter'),
      document.fonts.load('500 20px Inter'), document.fonts.load('600 90px "JetBrains Mono"'),
      document.fonts.load('500 18px "JetBrains Mono"')
    ]);
  }catch(e){}
}
var F_SANS='Inter, -apple-system, sans-serif', F_MONO='"JetBrains Mono", ui-monospace, monospace';

/* Lienzo base común: negro real, viñeta, rejilla tenue y marco hairline
   redondeado — el mismo lenguaje que las tarjetas de la web. */
function frame(ctx,W,H,tint){
  ctx.fillStyle='#000'; ctx.fillRect(0,0,W,H);
  ctx.save();
  ctx.strokeStyle='rgba(255,255,255,.05)'; ctx.lineWidth=1;
  for(var x=0;x<W;x+=64){ ctx.beginPath(); ctx.moveTo(x+.5,0); ctx.lineTo(x+.5,H); ctx.stroke(); }
  for(var y=0;y<H;y+=64){ ctx.beginPath(); ctx.moveTo(0,y+.5); ctx.lineTo(W,y+.5); ctx.stroke(); }
  ctx.restore();
  var g=ctx.createRadialGradient(W*.5,-H*.1,0,W*.5,-H*.1,H*.95);
  g.addColorStop(0,hex2rgba(tint||'#FF5100',.26)); g.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle=g; ctx.fillRect(0,0,W,H);
  var v=ctx.createRadialGradient(W*.5,H*.45,H*.25,W*.5,H*.5,H*.95);
  v.addColorStop(0,'rgba(0,0,0,0)'); v.addColorStop(1,'rgba(0,0,0,.75)');
  ctx.fillStyle=v; ctx.fillRect(0,0,W,H);
  ctx.strokeStyle='rgba(255,255,255,.14)'; ctx.lineWidth=2;
  rr(ctx,14,14,W-28,H-28,26); ctx.stroke();
}
function wordmark(ctx,W,H){
  ctx.textAlign='center'; ctx.font='500 15px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.4)';
  ctx.fillText('SUPERLIGA FRONTIER  ·  superligafrontier.es',W/2,H-44);
}
function crestOn(ctx,img,e,cx,cy,size){
  if(img){ contain(ctx,img,cx,cy,size); return; }
  ctx.fillStyle='#141414'; rr(ctx,cx-size/2,cy-size/2,size,size,size*.22); ctx.fill();
  ctx.strokeStyle='rgba(255,255,255,.12)'; ctx.lineWidth=1; ctx.stroke();
  ctx.fillStyle='#EDEDED'; ctx.textAlign='center';
  ctx.font='700 '+Math.round(size*.3)+'px '+F_SANS;
  ctx.fillText(abbr3(e&&e.nombre,e&&e.abreviatura),cx,cy+size*.11);
}

/* ------------------------------ TARJETA DE PARTIDO ------------------------ */
async function shareMatch(comp,idx){
  var p=poolOf(comp)[idx]; if(!p) return;
  await fonts();
  var L=team(p.local), V=team(p.visitante);
  var cl=wash(L,'#FF5100'), cv2=wash(V,'#3E7BFF');
  var W=1200,H=675, cv=document.createElement('canvas'); cv.width=W; cv.height=H;
  var ctx=cv.getContext('2d');
  frame(ctx,W,H,cl);

  // Split híbrido: cada mitad lavada con el color de su club
  var gl1=ctx.createRadialGradient(0,H/2,0,0,H/2,W*.62);
  gl1.addColorStop(0,hex2rgba(cl,.34)); gl1.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle=gl1; ctx.fillRect(0,0,W/2,H);
  var gl2=ctx.createRadialGradient(W,H/2,0,W,H/2,W*.62);
  gl2.addColorStop(0,hex2rgba(cv2,.34)); gl2.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle=gl2; ctx.fillRect(W/2,0,W/2,H);
  var seam=ctx.createLinearGradient(0,60,0,H-60);
  seam.addColorStop(0,'rgba(255,255,255,0)'); seam.addColorStop(.5,'rgba(255,255,255,.14)'); seam.addColorStop(1,'rgba(255,255,255,0)');
  ctx.fillStyle=seam; ctx.fillRect(W/2-.5,60,1,H-120);

  // Cabecera: competición + fase/jornada
  var compTxt=(comp==='ascenso'?T('comp.ascenso','Ascenso Frontier'):comp==='copa'?T('sec.copa','Copa Fútbol Frontier'):T('comp.superliga','Superliga Frontier')).toUpperCase();
  var compCol=comp==='ascenso'?'#3E7BFF':comp==='copa'?'#FF3B3B':'#FF5100';
  var sub=(p.fase?faseName(p.fase):T('jornada.label','JORNADA')+' '+(p.jornada||'')).toUpperCase();
  var HF='600 15px '+F_MONO;
  var w1=pillWidth(ctx,compTxt,HF,20), w2=pillWidth(ctx,sub,HF,20);
  var sx=(W-(w1+12+w2))/2;
  pill(ctx,sx,52,compTxt,HF,compCol,hex2rgba(compCol,.14),20,36);
  pill(ctx,sx+w1+12,52,sub,HF,'rgba(255,255,255,.65)','rgba(255,255,255,.07)',20,36);

  var imgs=await Promise.all([loadImg(L&&L.escudo),loadImg(V&&V.escudo)]);
  var S=132, cy=232, lx=235, rx=W-235;
  crestOn(ctx,imgs[0],L,lx,cy,S);
  crestOn(ctx,imgs[1],V,rx,cy,S);

  ctx.textAlign='center'; ctx.fillStyle='#EDEDED';
  ctx.font=fit(ctx,X(p.local),380,'600',28,17,F_SANS);   ctx.fillText(X(p.local),lx,cy+S/2+52);
  ctx.font=fit(ctx,X(p.visitante),380,'600',28,17,F_SANS); ctx.fillText(X(p.visitante),rx,cy+S/2+52);

  // Barra de color bajo cada nombre
  [[lx,cl,(L&&L.color2)||cl],[rx,cv2,(V&&V.color2)||cv2]].forEach(function(t){
    var bg=ctx.createLinearGradient(t[0]-40,0,t[0]+40,0);
    bg.addColorStop(0,t[1]); bg.addColorStop(1,t[2]);
    ctx.fillStyle=bg; rr(ctx,t[0]-40,cy+S/2+70,80,4,2); ctx.fill();
  });

  // Marcador
  var pen=!isFin(p);
  ctx.textAlign='center'; ctx.fillStyle='#fff';
  if(pen){ ctx.font='500 46px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.55)'; ctx.fillText('VS',W/2,cy+22); }
  else{
    // el marcador perdedor se apaga: se ve quién ganó sin leer los números
    var a=gl(p), b=gv(p);
    ctx.font='600 104px '+F_MONO;
    ctx.fillStyle=a<b?'rgba(255,255,255,.42)':'#fff'; ctx.fillText(String(a),W/2-78,cy+34);
    ctx.fillStyle=b<a?'rgba(255,255,255,.42)':'#fff'; ctx.fillText(String(b),W/2+78,cy+34);
    ctx.fillStyle='#3A3A3A'; ctx.font='500 52px '+F_MONO; ctx.fillText('-',W/2,cy+28);
  }

  /* Goleadores del encuentro: es lo primero que se busca al compartir un
     resultado y la tarjeta anterior no los mostraba. */
  var goalsL=[], goalsV=[];
  (p.detalles||'').split('/').forEach(function(half,side){
    half.split(',').forEach(function(ev){
      var q=ev.trim().split(':');
      if(q.length>=3&&q[0].trim()==='gol'&&q[1].trim())
        (side===0?goalsL:goalsV).push({n:X(q[1].trim()),m:parseInt(q[2])||0});
    });
  });
  goalsL.sort(function(a,b){ return a.m-b.m; }); goalsV.sort(function(a,b){ return a.m-b.m; });

  var listY=442;
  ctx.strokeStyle='rgba(255,255,255,.10)'; ctx.lineWidth=1;
  ctx.beginPath(); ctx.moveTo(96,listY-34); ctx.lineTo(W-96,listY-34); ctx.stroke();
  ctx.font='500 13px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.38)'; ctx.textAlign='center';
  ctx.fillText('GOLEADORES',W/2,listY-8);

  if(!goalsL.length&&!goalsV.length&&!pen){
    ctx.font='500 16px '+F_SANS; ctx.fillStyle='rgba(255,255,255,.35)';
    ctx.fillText('Sin goles registrados',W/2,listY+34);
  }
  [{g:goalsL,align:'right',min:W/2-40,name:W/2-96,col:cl},
   {g:goalsV,align:'left', min:W/2+40,name:W/2+96,col:cv2}].forEach(function(c){
    c.g.slice(0,6).forEach(function(g,i){
      var y=listY+30+i*30;
      ctx.textAlign=c.align==='right'?'right':'left';
      ctx.font='500 14px '+F_MONO; ctx.fillStyle=hex2rgba(c.col,.95);
      ctx.fillText(g.m+'′',c.min,y);
      ctx.font='500 17px '+F_SANS; ctx.fillStyle='rgba(255,255,255,.9)';
      ctx.fillText(g.n,c.name,y);
    });
    if(c.g.length>6){
      ctx.textAlign=c.align==='right'?'right':'left';
      ctx.font='500 13px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.4)';
      ctx.fillText('+'+(c.g.length-6)+' más',c.name,listY+30+6*30);
    }
  });

  wordmark(ctx,W,H);
  dl(cv,'superliga-frontier-'+p.local+'-vs-'+p.visitante+'.png');
}

/* ------------------------------ TARJETA DE JUGADOR ------------------------ */
async function sharePlayer(teamId,nameEnc){
  var e=bd.equipos.find(function(x){ return x.id===teamId; });
  var j=e&&(e.jugadores||[]).find(function(p){ return p.nombre===decodeURIComponent(nameEnc); });
  if(!j) return;
  await fonts();
  var k=afKey(j.afinidad), hex=AF_HEX[k];
  var c1=wash(e,hex);
  var W=900,H=1260, cv=document.createElement('canvas'); cv.width=W; cv.height=H;
  var ctx=cv.getContext('2d');
  frame(ctx,W,H,c1);

  var res=await Promise.all([loadImg(j.foto),loadImg(e.escudo)]);
  var foto=res[0], escudo=res[1];

  // Cabecera
  ctx.textAlign='left'; ctx.font='600 15px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.5)';
  ctx.fillText(T('comp.superliga','Superliga Frontier').toUpperCase(),56,72);
  var divTxt=(e.division==='ASCENSO'?T('comp.ascenso','Ascenso Frontier'):T('comp.superliga','Superliga Frontier')).toUpperCase();
  var divCol=e.division==='ASCENSO'?'#3E7BFF':'#FF5100';
  ctx.font='600 13px '+F_MONO;
  var dw=ctx.measureText(divTxt).width+36;
  pill(ctx,W-56-dw,52,divTxt,'600 13px '+F_MONO,divCol,hex2rgba(divCol,.16),18,30);

  // Marco de retrato: lavado de colores del club + afinidad
  var bx=56, by=104, bw=W-112, bh=680;
  var g=ctx.createLinearGradient(bx,by,bx+bw,by+bh);
  g.addColorStop(0,hex2rgba(c1,.55)); g.addColorStop(.55,hex2rgba(hex,.28)); g.addColorStop(1,'rgba(10,10,10,.9)');
  ctx.save(); rr(ctx,bx,by,bw,bh,24); ctx.clip();
  ctx.fillStyle='#0A0A0A'; ctx.fillRect(bx,by,bw,bh);
  ctx.fillStyle=g; ctx.fillRect(bx,by,bw,bh);

  // Dorsal como marca de agua, detrás del jugador
  ctx.textAlign='center'; ctx.font='700 420px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.07)';
  ctx.fillText(String(j.dorsal||''),bx+bw/2,by+bh*.72);

  if(foto){
    /* El retrato manda: se encaja al alto del marco y se funde por abajo. */
    cover(ctx,foto,bx,by,bw,bh);
    var fade=ctx.createLinearGradient(0,by+bh*.68,0,by+bh);
    fade.addColorStop(0,'rgba(10,10,10,0)'); fade.addColorStop(1,'rgba(8,8,8,.78)');
    ctx.fillStyle=fade; ctx.fillRect(bx,by,bw,bh);
  }else{
    ctx.font='700 300px '+F_SANS; ctx.fillStyle='rgba(255,255,255,.18)';
    ctx.fillText(((X(j.nombre)||'?').trim()[0]||'?').toUpperCase(),bx+bw/2,by+bh*.62);
  }
  ctx.restore();
  ctx.strokeStyle='rgba(255,255,255,.16)'; ctx.lineWidth=1.5; rr(ctx,bx,by,bw,bh,24); ctx.stroke();

  // Escudo del club sobre el marco
  if(escudo){
    ctx.save();
    ctx.fillStyle='rgba(0,0,0,.55)'; rr(ctx,bx+bw-114,by+bh-114,90,90,20); ctx.fill();
    ctx.strokeStyle='rgba(255,255,255,.14)'; ctx.lineWidth=1; ctx.stroke();
    contain(ctx,escudo,bx+bw-69,by+bh-69,62);
    ctx.restore();
  }

  // Nombre
  var y=by+bh+72;
  ctx.textAlign='center'; ctx.fillStyle='#EDEDED';
  ctx.font=fit(ctx,X(j.nombre),W-140,'700',60,26,F_SANS);
  ctx.fillText(X(j.nombre),W/2,y);

  // Píldoras: posición · afinidad · club
  y+=44;
  var pos=posName(j.posicion);
  var posCol={POR:'#FFC94A',DEF:'#3E7BFF',MED:'#46B45F',DEL:'#FF3B3B'}[j.posicion]||'#A1A1A1';
  var afTxt=afName(j.afinidad), clubTxt=X(e.nombre);
  ctx.font='500 16px '+F_SANS;
  var wp=ctx.measureText(pos).width+36, wa=ctx.measureText(afTxt).width+36, wc=ctx.measureText(clubTxt).width+36;
  var tot=wp+wa+wc+24, px=(W-tot)/2;
  pill(ctx,px,y,pos,'500 16px '+F_SANS,posCol,hex2rgba(posCol,.15),18,38); px+=wp+12;
  pill(ctx,px,y,afTxt,'500 16px '+F_SANS,hex,hex2rgba(hex,.15),18,38); px+=wa+12;
  pill(ctx,px,y,clubTxt,'500 16px '+F_SANS,'rgba(255,255,255,.8)','rgba(255,255,255,.07)',18,38);

  // Estadísticas
  y+=104;
  ctx.strokeStyle='rgba(255,255,255,.10)'; ctx.lineWidth=1;
  ctx.beginPath(); ctx.moveTo(56,y-40); ctx.lineTo(W-56,y-40); ctx.stroke();
  var stats=[
    [String(golesCarrera(j)),'GOLES'],
    [String(golesTemporada(j)),T('pl.estatemp','esta temporada').toUpperCase()],
    ['#'+(j.dorsal||'·'),'DORSAL']
  ];
  stats.forEach(function(s,i){
    var cx=W/6+(W/3)*i;
    if(i){ ctx.beginPath(); ctx.moveTo(W/3*i+.5,y-24); ctx.lineTo(W/3*i+.5,y+46); ctx.stroke(); }
    ctx.textAlign='center';
    ctx.font='600 60px '+F_MONO; ctx.fillStyle='#fff'; ctx.fillText(s[0],cx,y+18);
    ctx.font='500 12px '+F_MONO; ctx.fillStyle='rgba(255,255,255,.42)'; ctx.fillText(s[1],cx,y+46);
  });

  wordmark(ctx,W,H);
  dl(cv,'superliga-frontier-'+j.nombre+'.png');
}

/* ==========================================================================
   DELEGACIÓN GLOBAL DE EVENTOS
   ========================================================================== */
document.addEventListener('click', function(ev){
  var t=ev.target;
  if(t.closest('[data-close-sheet]')){ closeSheets(); return; }

  var sm=t.closest('[data-share-match]');
  if(sm){ var a=sm.dataset.shareMatch.split('|'); sm.disabled=true; Promise.resolve(shareMatch(a[0],parseInt(a[1]))).then(function(){ sm.disabled=false; },function(){ sm.disabled=false; }); return; }
  var sp=t.closest('[data-share-player]');
  if(sp){ var b=sp.dataset.sharePlayer.split('|'); sp.disabled=true; Promise.resolve(sharePlayer(b[0],b[1])).then(function(){ sp.disabled=false; },function(){ sp.disabled=false; }); return; }

  var si=t.closest('[data-si]'); if(si){ var f=sActions[parseInt(si.dataset.si)]; if(f) f(); return; }

  /* Estos dos van ANTES de data-player/data-team: viven dentro de fichas que
     ya llevan esos atributos y si no, abrirían el perfil en vez de desplegar. */
  var hi=t.closest('[data-hist]');
  if(hi){
    var wasOpen=hi.classList.toggle('open');
    var hb=hi.querySelector('.hist-btn'); if(hb) hb.setAttribute('aria-expanded',wasOpen);
    return;
  }
  var ch=t.closest('[data-champs]');
  if(ch){ openChamps(parseInt(ch.dataset.champs,10),ch.dataset.champsLabel||''); return; }

  var more=t.closest('.sc-more');
  if(more){
    var box=$('scorers'), open=box.classList.toggle('open');
    var n=box.querySelectorAll('.sc-rest .sc-row').length;
    more.setAttribute('aria-expanded',open);
    more.querySelector('.sc-more-txt').textContent=open
      ? T('gol.top','Ver solo el top')+' '+GOL_TOP
      : T('gol.ver','Ver los')+' '+n+' '+T('gol.restantes','goleadores restantes');
    return;
  }

  var match=t.closest('[data-idx][data-comp]'); if(match){ openMatch(match.dataset.comp,parseInt(match.dataset.idx)); return; }
  var pl=t.closest('[data-player]'); if(pl){ openPlayer(pl.dataset.team,pl.dataset.player); return; }
  var tm=t.closest('[data-team]'); if(tm){ openTeam(tm.dataset.team); return; }
  var nw=t.closest('[data-news]'); if(nw){ openNews(parseInt(nw.dataset.news)); return; }

  var fc=t.closest('[data-faq]'); if(fc && t.closest('.faq-cat > button')){ fc.classList.toggle('open'); return; }
  var qa=t.closest('[data-qa]'); if(qa){ qa.classList.toggle('open'); return; }
  var tag=t.closest('[data-tag]');
  if(tag){
    newsTag=tag.dataset.tag;
    newsList=newsTag==='TODOS'?(bd.noticias||[]).slice():(bd.noticias||[]).filter(function(n){ return n.tag===newsTag; });
    newsIdx=0;
    document.querySelectorAll('#news-filters .filter').forEach(function(b){ b.classList.toggle('on',b.dataset.tag===newsTag); });
    newsSlide(); return;
  }
}, false);

function seg(id,cb){
  var el=$(id); if(!el) return;
  el.addEventListener('click',function(e){
    var b=e.target.closest('button'); if(!b) return;
    el.querySelectorAll('button').forEach(function(x){ x.classList.remove('on'); });
    b.classList.add('on'); cb(b);
  });
}

document.addEventListener('DOMContentLoaded', function(){
  renderFaq(); renderQuotes(); observeReveals();

  seg('seg-clas',function(b){ renderClas(b.dataset.div); });
  seg('seg-teams',function(b){ renderTeams(b.dataset.div); });
  seg('seg-gol',function(b){ renderScorers(b.dataset.comp); });
  seg('seg-res',function(b){ curComp=b.dataset.comp; initJornadas(); renderMatches(); });

  $('j-prev').addEventListener('click',function(){ if(!jornadas.length) return; jIdx=(jIdx-1+jornadas.length)%jornadas.length; renderMatches(); });
  $('j-next').addEventListener('click',function(){ if(!jornadas.length) return; jIdx=(jIdx+1)%jornadas.length; renderMatches(); });
  $('news-prev').addEventListener('click',function(){ if(!newsList.length) return; newsIdx=(newsIdx-1+newsList.length)%newsList.length; newsSlide(); });
  $('news-next').addEventListener('click',function(){ if(!newsList.length) return; newsIdx=(newsIdx+1)%newsList.length; newsSlide(); });

  $('btn-search').addEventListener('click',openSearch);
  $('q').addEventListener('input',function(){ doSearch(this.value); });
  $('ov-search').addEventListener('click',function(e){ if(e.target===this) closeSearch(); });
  $('srch-tabs').addEventListener('click',function(e){
    var b=e.target.closest('button'); if(!b) return;
    sTab=b.dataset.tab;
    this.querySelectorAll('button').forEach(function(x){ x.classList.remove('on'); });
    b.classList.add('on'); doSearch($('q').value);
  });

  $('btn-lang').addEventListener('click',function(){ if(window.openLangModal) openLangModal(); else $('ov-lang').classList.add('open'); });
  $('lang-close').addEventListener('click',function(){ $('ov-lang').classList.remove('open'); });
  $('ov-lang').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });

  renderAntiguedad();
  $('champ-close').addEventListener('click',function(){ $('ov-champs').classList.remove('open'); });
  $('ov-champs').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
  document.addEventListener('keydown',function(e){
    if(e.key==='Enter'||e.key===' '){
      var c=e.target.closest&&e.target.closest('[data-champs]');
      if(c){ e.preventDefault(); openChamps(parseInt(c.dataset.champs,10),c.dataset.champsLabel||''); }
    }
  });

  var dr=$('drawer'), db=$('drawer-bg');
  function drOpen(v){ dr.classList.toggle('open',v); db.classList.toggle('open',v); $('btn-burger').setAttribute('aria-expanded',v); }
  $('btn-burger').addEventListener('click',function(){ drOpen(true); });
  $('btn-drawer-close').addEventListener('click',function(){ drOpen(false); });
  db.addEventListener('click',function(){ drOpen(false); });
  dr.querySelectorAll('nav a').forEach(function(a){ a.addEventListener('click',function(){ drOpen(false); }); });

  document.addEventListener('keydown',function(e){
    if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){ e.preventDefault(); openSearch(); }
    if(e.key==='Escape'){ closeSearch(); closeSheets(); document.querySelectorAll('.ov.open').forEach(function(o){ o.classList.remove('open'); }); }
  });

  var nav=$('nav');
  function onScroll(){ nav.classList.toggle('stuck',window.scrollY>20); }
  window.addEventListener('scroll',onScroll,{passive:true}); onScroll();

  var tabLinks=document.querySelectorAll('#tabbar a');
  var io=new IntersectionObserver(function(es){
    es.forEach(function(en){
      if(en.isIntersecting) tabLinks.forEach(function(l){ l.classList.toggle('on',l.getAttribute('href')==='#'+en.target.id); });
    });
  },{rootMargin:'-40% 0px -55% 0px'});
  ['clasificacion','resultados','copa','equipos','goleadores'].forEach(function(id){ var el=$(id); if(el) io.observe(el); });

  document.addEventListener('mousemove',function(e){
    var c=e.target.closest ? e.target.closest('.spotlight') : null; if(!c) return;
    var r=c.getBoundingClientRect();
    c.style.setProperty('--mx',(e.clientX-r.left)+'px');
    c.style.setProperty('--my',(e.clientY-r.top)+'px');
  },{passive:true});

  if(matchMedia('(hover:hover)').matches){
    document.documentElement.classList.add('cur-custom');
    var cur=$('cur');
    window.addEventListener('mousemove',function(e){ cur.style.transform='translate('+e.clientX+'px,'+e.clientY+'px) translate(-50%,-50%)'; },{passive:true});
    document.addEventListener('mouseover',function(e){ if(e.target.closest&&e.target.closest('a,button,.card,tr,.squad-row,.sc-row,.br-match,input[type="range"]')) cur.classList.add('on'); });
    document.addEventListener('mouseout',function(e){ if(e.target.closest&&e.target.closest('a,button,.card,tr,.squad-row,.sc-row,.br-match,input[type="range"]')) cur.classList.remove('on'); });
  }

  /* ══════════════════════════════════════════════════════════════════════
     MÚSICA AMBIENTE  ·  sistema reescrito de cero

     Reglas de diseño, tras varias rondas de fallos con la versión anterior:

     1. UN SOLO <audio>, declarado en el HTML. Creado con new Audio() fuera
        del documento, algunos navegadores no lo asocian a la pestaña y
        silenciar la pestaña no lo callaba.
     2. UNA SOLA variable de verdad: sndOn (lo que el usuario quiere). El
        icono NO la lee; el icono lo pintan los eventos play/pause reales
        del elemento, así que nunca puede mentir sobre lo que se oye.
     3. PARAR ES INMEDIATO Y SIN CONDICIONES: pause + muted + volumen 0, sin
        depender de que termine ningún temporizador. El fundido es sólo
        cosmético al ENTRAR, nunca al salir.
     4. Una sola pestaña suena a la vez (BroadcastChannel).
     5. Al ocultar/cerrar la pestaña se para de verdad.
     ══════════════════════════════════════════════════════════════════════ */
  (function musicaAmbiente(){
    var boton = $('btn-sound');
    var audio = $('sf-audio');
    var range = $('sound-range');
    var wrap = $('sound-wrap');
    if(!boton || !audio) return;

    var VOL = parseInt(localStorage.getItem('sf_vol'), 10);
    if(isNaN(VOL) || VOL < 0 || VOL > 100) VOL = 10;   // 10%: dentro del 8-12% pedido
    VOL = VOL / 100;
    function pintarFill(){ if(range) range.style.setProperty('--fill', (VOL * 100) + '%'); }
    if(range){ range.value = Math.round(VOL * 100); pintarFill(); }

    var quiere = localStorage.getItem('sf_sound') !== 'off';   // por defecto ON
    if(VOL <= 0) quiere = false;   // volumen a 0 equivale a silenciado

    /* Las 3 pistas son las únicas disponibles; el "selector" sigue siendo el
       mismo interruptor on/off (confirmado). Se elige una al azar en cada
       carga de página — no hay UI de lista de pistas. */
    var PISTAS = ['assets/web1.mp3','assets/web2.mp3','assets/web3.mp3'];
    audio.src = PISTAS[Math.floor(Math.random()*PISTAS.length)];

    var rampa = null;

    function recordar(v){ try{ localStorage.setItem('sf_sound', v ? 'on' : 'off'); }catch(e){} }
    function pintar(on){
      boton.setAttribute('aria-pressed', on ? 'true' : 'false');
      boton.innerHTML = '<i class="ph ph-speaker-simple-' + (on ? 'high' : 'slash') + '"></i>';
      boton.setAttribute('aria-label', on ? T('snd.off','Silenciar música') : T('snd.on','Activar música'));
    }
    // el icono siempre refleja la realidad del elemento, no la intención
    audio.addEventListener('play',  function(){ pintar(true); });
    audio.addEventListener('playing', function(){ pintar(true); });
    audio.addEventListener('pause', function(){ pintar(false); });
    audio.addEventListener('error', function(){
      boton.disabled = true;
      boton.title = 'No se pudo cargar la música ambiente';
      pintar(false);
    });

    /* El fundido va con temporizador, NO con requestAnimationFrame: rAF se
       congela cuando la pestaña no está componiendo frames y el volumen se
       quedaba clavado en 0 con la música técnicamente sonando. El audio no
       puede depender del compositor. */
    function pararRampa(){ if(rampa){ clearInterval(rampa); rampa = null; } }

    function parar(){                     // inmediato, pase lo que pase
      pararRampa();
      audio.muted = true;
      audio.volume = 0;
      try{ audio.pause(); }catch(e){}
      pintar(false);
    }

    function sonar(){
      pararRampa();
      audio.muted = false;
      audio.volume = 0;
      var p = audio.play();
      // fundido de entrada de 1,2 s; se cancela solo si se para por el camino
      var t0 = Date.now();
      rampa = setInterval(function(){
        if(audio.muted || audio.paused){ pararRampa(); return; }
        var k = Math.min((Date.now() - t0) / 1200, 1);
        audio.volume = VOL * k;
        if(k >= 1) pararRampa();
      }, 40);
      return (p && p.then) ? p : Promise.resolve();
    }

    /* Sólo una pestaña sonando. La última que arranca manda. */
    var canal = null;
    try{
      canal = new BroadcastChannel('sf-audio');
      canal.onmessage = function(ev){
        if(ev.data === 'suena' && !audio.paused){ quiere = false; parar(); }
      };
    }catch(e){}
    function avisar(){ if(canal){ try{ canal.postMessage('suena'); }catch(e){} } }

    /* Los navegadores exigen un gesto antes de reproducir. Se intenta al
       cargar y, si lo rechazan, se arma para la primera interacción. */
    var GESTOS = ['pointerdown','keydown','wheel','touchstart'];
    function desarmar(){ GESTOS.forEach(function(ev){ document.removeEventListener(ev, alGesto, true); }); }
    function alGesto(e){
      if(e.target && e.target.closest && e.target.closest('#btn-sound')) return;  // lo lleva su propio handler
      desarmar();
      if(quiere && audio.paused){ avisar(); sonar().catch(function(){}); }
    }
    function armar(){ GESTOS.forEach(function(ev){ document.addEventListener(ev, alGesto, {capture:true, passive:true}); }); }

    boton.addEventListener('click', function(){
      desarmar();
      if(!audio.paused){ quiere = false; recordar(false); parar(); return; }
      quiere = true; recordar(true); avisar();
      sonar().catch(function(){ boton.title = 'El navegador ha bloqueado la reproducción'; pintar(false); });
    });

    if(range){
      range.addEventListener('input', function(){
        VOL = range.value / 100;
        try{ localStorage.setItem('sf_vol', range.value); }catch(e){}
        pintarFill();
        if(VOL <= 0){
          // volumen a 0: se comporta como el botón de silenciar
          if(!audio.paused){ quiere = false; recordar(false); parar(); }
        }else if(audio.paused){
          // subir el volumen desde silencio reactiva la reproducción
          quiere = true; recordar(true); avisar();
          sonar().catch(function(){});
        }else{
          audio.volume = VOL;
        }
      });
    }

    // el desplegable de volumen se abre/cierra por sí solo, sin depender
    // de :focus-within (que se quedaba pegado tras arrastrar el control)
    if(wrap){
      var abrir  = function(){ wrap.classList.add('open'); };
      var cerrar = function(e){
        if(e && e.relatedTarget && wrap.contains(e.relatedTarget)) return;
        wrap.classList.remove('open');
      };
      wrap.addEventListener('mouseenter', abrir);
      wrap.addEventListener('mouseleave', cerrar);
      wrap.addEventListener('focusin', abrir);
      wrap.addEventListener('focusout', cerrar);
    }

    document.addEventListener('visibilitychange', function(){
      if(document.hidden){ if(!audio.paused) parar(); }
      else if(quiere && audio.paused) sonar().catch(function(){});
    });
    window.addEventListener('pagehide', function(){ try{ audio.pause(); }catch(e){} });

    pintar(false);
    if(quiere){ avisar(); sonar().catch(function(){ pintar(false); armar(); }); }
  })();


  if(window.gsap&&window.ScrollTrigger&&$('hero-img')){
    gsap.to('#hero-img',{ scale:1.14, ease:'none', scrollTrigger:{ trigger:'.hero', start:'top top', end:'bottom top', scrub:true } });
  }

  /* Raíl de la línea temporal: se rellena con el scroll. Sin GSAP se queda
     relleno del todo — la historia se lee igual, sólo pierde la animación. */
  var rail=$('tl-rail');
  if(rail){
    if(window.gsap&&window.ScrollTrigger){
      ScrollTrigger.create({
        trigger:'.tl-wrap', start:'top 80%', end:'bottom 70%',
        onUpdate:function(s){ rail.style.setProperty('--tl-p',(s.progress*100).toFixed(1)+'%'); }
      });
    } else {
      rail.style.setProperty('--tl-p','100%');
    }
  }
});

window.addEventListener('load',function(){ setTimeout(function(){ var p=$('pre'); if(p) p.classList.add('done'); },700); });

/* Reveals.
   El estado oculto vive bajo html.anim, que se activa aquí: si este script
   no corre, el contenido se ve igual. Además un failsafe revela todo pasados
   2,5 s por si el IntersectionObserver no llega a disparar (pestaña en
   segundo plano, renderizador sin componer, navegador raro): una animación
   de entrada nunca puede dejar la página en blanco. */
document.documentElement.classList.add('anim');
var revealIO=null, revealFailsafe=null;
function revealAll(){ document.querySelectorAll('.rv:not(.in)').forEach(function(el){ el.classList.add('in'); }); }
function observeReveals(){
  if(!('IntersectionObserver' in window)){ revealAll(); return; }
  if(!revealIO){
    revealIO=new IntersectionObserver(function(es){
      es.forEach(function(en){ if(en.isIntersecting){ en.target.classList.add('in'); revealIO.unobserve(en.target); } });
    },{threshold:.05,rootMargin:'0px 0px -30px 0px'});
  }
  document.querySelectorAll('.rv:not(.in)').forEach(function(el){ revealIO.observe(el); });
  clearTimeout(revealFailsafe);
  revealFailsafe=setTimeout(revealAll,2500);
}

/* re-render al cambiar idioma (lo invoca sfTransliterarPagina) */
window.renderClasificacion=function(){ renderClas(curDiv); };
window.renderTarjetasEquipos=function(){ renderTeams(curTeamDiv); };
window.initGoleadores=function(){ renderScorers(curGol); };
window.renderPartidosCopa=function(){ renderCopa(); renderMatches(); };
window.renderNoticias=function(){ renderNews(); };
window.renderArbolCopa=function(){ renderCopa(); renderPlayoff(); };

})();
