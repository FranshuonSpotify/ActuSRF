/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-estadisticas.js
   Cuadro de mando de la temporada. Sólo lee: no escribe nada en el archivo.

   Todo sale de los partidos FINALIZADOS y de las fichas de jugador, con las
   mismas cuentas que la web. Las eliminatorias se excluyen de lo que tenga
   que ver con la clasificación, igual que en tablaCalculada().
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var comp = 'todas';                       // ámbito de los rankings
var h2h = {a:null, b:null};               // comparador cabeza a cabeza

function d(){ return SFG.d(); }

/* Partidos del ámbito elegido. La Copa entra en goleadores pero nunca en
   nada que hable de clasificación. */
function partidos(){
  var D = d();
  if(comp==='liga') return D.partidos_liga;
  if(comp==='ascenso') return D.partidos_ascenso;
  if(comp==='copa') return D.partidos_copa;
  return D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa);
}
function jugados(){ return partidos().filter(C.isFin); }

function pintar(el){
  var D = d();
  var ms = jugados();

  el.innerHTML =
    U.cabecera('Estadísticas', 'Temporada '+(D.config.temporada||'—')+' · sólo lectura')+
    '<div class="g-filtros">'+
      '<div style="display:flex;gap:.25rem">'+
        [['todas','Todas'],['liga','Superliga'],['ascenso','Ascenso'],['copa','Copa']].map(function(c){
          return '<button class="btn btn-sm '+(comp===c[0]?'btn-primary':'btn-secondary')+'" data-a="estadisticas:comp" data-v="'+c[0]+'">'+c[1]+'</button>';
        }).join('')+
      '</div>'+
      '<span class="ayuda" style="margin-left:auto">'+ms.length+' partidos jugados</span>'+
    '</div>'+

    (ms.length ? bloques(ms) : '<div class="vacio">No hay partidos finalizados en esta competición.</div>');
}

function bloques(ms){
  return kpis(ms)+
    '<div class="g-hueco"></div><div class="rejilla" style="--min:330px;align-items:start">'+
      goleadores()+porPosicion()+
    '</div>'+
    '<div class="g-hueco"></div><div class="rejilla" style="--min:330px;align-items:start">'+
      localVisitante(ms)+rachas()+proyeccion()+revelacion()+
    '</div>'+
    '<div class="g-hueco"></div>'+cabezaACabeza();
}

/* --- #25 Cuadro de mando general ------------------------------------- */
function kpis(ms){
  var goles = ms.reduce(function(a,p){ return a+(Number(C.gl(p))||0)+(Number(C.gv(p))||0); }, 0);
  var media = (goles/ms.length).toFixed(2);

  /* Jornada con más goles: sólo tiene sentido donde hay jornadas. */
  var porJor = {};
  ms.forEach(function(p){
    if(p.jornada==null||p.jornada==='') return;
    porJor[p.jornada] = (porJor[p.jornada]||0)+(Number(C.gl(p))||0)+(Number(C.gv(p))||0);
  });
  var mejorJor = Object.keys(porJor).sort(function(a,b){ return porJor[b]-porJor[a]; })[0];

  var gf = {}, gc = {};
  ms.forEach(function(p){
    var a = Number(C.gl(p))||0, b = Number(C.gv(p))||0;
    gf[p.local]=(gf[p.local]||0)+a; gc[p.local]=(gc[p.local]||0)+b;
    gf[p.visitante]=(gf[p.visitante]||0)+b; gc[p.visitante]=(gc[p.visitante]||0)+a;
  });
  var masGoleado = Object.keys(gc).sort(function(a,b){ return gc[b]-gc[a]; })[0];
  var menosGoleado = Object.keys(gc).sort(function(a,b){ return gc[a]-gc[b]; })[0];
  var masGoleador = Object.keys(gf).sort(function(a,b){ return gf[b]-gf[a]; })[0];

  var mayor = ms.slice().sort(function(a,b){
    return Math.abs(C.gl(b)-C.gv(b))-Math.abs(C.gl(a)-C.gv(a));
  })[0];

  var noJug = ms.filter(C.esNoJugado).length;
  return '<div class="rejilla rejilla-4">'+
    U.kpi({valor:goles, etiqueta:'Goles', icono:'ph ph-target', destacado:true,
           delta:0, deltaTexto:media+' por partido'})+
    U.kpi({valor:ms.length, etiqueta:'Partidos jugados', icono:'ph-bold ph-soccer-ball',
           delta:noJug?-noJug:null, deltaTexto:noJug?noJug+' no disputados':null,
           titulo:'Los no disputados se resolvieron con victoria administrativa'})+
    U.kpi({valor:mejorJor?('J'+mejorJor):'—', etiqueta:'Jornada con más goles', icono:'ph ph-fire',
           delta:mejorJor?0:null, deltaTexto:mejorJor?porJor[mejorJor]+' goles':null})+
    U.kpi({valor:masGoleador?C.abbr3(masGoleador,(C.equipo(masGoleador)||{}).abreviatura):'—',
           etiqueta:'Más goleador', icono:'ph ph-trophy', titulo:masGoleador,
           delta:masGoleador?0:null, deltaTexto:masGoleador?gf[masGoleador]+' a favor':null})+
    U.kpi({valor:menosGoleado?C.abbr3(menosGoleado,(C.equipo(menosGoleado)||{}).abreviatura):'—',
           etiqueta:'Menos goleado', icono:'ph ph-shield-check', titulo:menosGoleado,
           delta:menosGoleado?0:null, deltaTexto:menosGoleado?gc[menosGoleado]+' en contra':null})+
    U.kpi({valor:mayor?(C.gl(mayor)+'–'+C.gv(mayor)):'—', etiqueta:'Mayor goleada', icono:'ph ph-lightning',
           titulo:mayor?mayor.local+' – '+mayor.visitante:''})+
  '</div>';
}

/* --- Goleadores ------------------------------------------------------- */
function tabla(titulo, filas, sufijo, vacio){
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">'+esc(titulo)+'</h3>'+
    (filas.length
      ? '<table class="tabla"><tbody>'+filas.slice(0,10).map(function(r,i){
          return '<tr><td class="num" style="width:1%;color:var(--ink-3)">'+(i+1)+'</td>'+
            '<td>'+esc(r.n)+(r.j===null&&r.eq===null?' <span class="pastilla pastilla-mal">sin ficha</span>':'')+'</td>'+
            '<td style="color:var(--ink-3);font-size:.75rem">'+esc(r.eq?r.eq.nombre:'—')+'</td>'+
            '<td class="num" style="font-weight:600">'+r.v+'<small style="color:var(--ink-3)">'+esc(sufijo)+'</small></td></tr>';
        }).join('')+'</tbody></table>'
      : '<p class="ayuda">'+esc(vacio)+'</p>')+
  '</div>';
}
function goleadores(){
  var r = C.calcScorers(jugados()).map(function(x){ return {n:x.nombre, eq:x.e, j:x.j, v:x.goles}; });
  return tabla('Goleadores', r, 'G', 'Sin goles registrados.');
}
/* --- #34 Por posición ------------------------------------------------- */
function porPosicion(){
  var t = {};
  C.POS.forEach(function(p){ t[p] = {n:0, goles:0}; });
  d().equipos.forEach(function(e){
    if(e.archivado) return;
    (e.jugadores||[]).forEach(function(j){
      var k = t[j.posicion]; if(!k) return;
      k.n++;
      k.goles += (j.goles||0);
    });
  });
  var ETQ = {POR:'Porteros', DEF:'Defensas', MED:'Medios', DEL:'Delanteros'};
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Por posición</h3>'+
    '<table class="tabla"><thead><tr><th>Línea</th><th class="num">Jugadores</th><th class="num">Goles</th><th class="num">Media</th></tr></thead><tbody>'+
    C.POS.map(function(p){
      var k = t[p];
      return '<tr><td><span class="chip chip-'+p.toLowerCase()+'">'+p+'</span> '+ETQ[p]+'</td>'+
        '<td class="num">'+k.n+'</td><td class="num">'+k.goles+'</td>'+
        '<td class="num">'+(k.n?(k.goles/k.n).toFixed(2):'—')+'</td></tr>';
    }).join('')+'</tbody></table>'+
    '<p class="ayuda" style="margin-top:var(--g3)">Goles de la ficha de cada jugador, sobre los clubes activos.</p>'+
  '</div>';
}

/* --- #31 Local frente a visitante ------------------------------------ */
function localVisitante(ms){
  var reg = ms.filter(C.esRegular);
  var l=0, e=0, v=0, gl=0, gv=0;
  reg.forEach(function(p){
    var a = Number(C.gl(p))||0, b = Number(C.gv(p))||0;
    gl+=a; gv+=b;
    if(a>b) l++; else if(b>a) v++; else e++;
  });
  var n = reg.length || 1;
  function barra(valor, color, etq){
    return '<div style="margin-bottom:.5rem">'+
      '<div style="display:flex;font-size:.75rem;margin-bottom:.2rem"><span>'+etq+'</span>'+
        '<span class="mono" style="margin-left:auto;color:var(--ink-3)">'+valor+' · '+Math.round(valor/n*100)+'%</span></div>'+
      '<div style="height:6px;border-radius:3px;background:var(--surface-3);overflow:hidden">'+
        '<div style="height:100%;width:'+(valor/n*100)+'%;background:'+color+'"></div></div></div>';
  }
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Local frente a visitante</h3>'+
    barra(l, 'var(--accent)', 'Gana el local')+
    barra(e, 'var(--ink-4)', 'Empate')+
    barra(v, 'var(--c-ascenso)', 'Gana el visitante')+
    '<p class="ayuda" style="margin-top:var(--g3)">'+gl+' goles en casa y '+gv+' fuera, en '+reg.length+' partidos de jornada regular.</p>'+
  '</div>';
}

/* --- #32 Racha activa más larga -------------------------------------- */
function rachas(){
  var D = d(), out = [];
  D.equipos.filter(function(e){ return !e.archivado; }).forEach(function(e){
    var suyos = (e.division==='ASCENSO'?D.partidos_ascenso:D.partidos_liga)
      .filter(function(p){ return C.isFin(p) && C.esRegular(p) && (p.local===e.nombre||p.visitante===e.nombre); })
      .sort(function(a,b){ return (parseInt(b.jornada)||0)-(parseInt(a.jornada)||0); });
    if(!suyos.length) return;
    /* Se recorre desde el último partido hacia atrás mientras el resultado
       sea del mismo signo. */
    function signo(p){
      var casa = p.local===e.nombre;
      var f = casa?C.gl(p):C.gv(p), c = casa?C.gv(p):C.gl(p);
      return f>c?'V':(f<c?'D':'E');
    }
    var s = signo(suyos[0]), n = 0;
    for(var i=0;i<suyos.length && signo(suyos[i])===s;i++) n++;
    if(n>=2) out.push({e:e, s:s, n:n});
  });
  var ETQ = {V:'victorias', D:'derrotas', E:'empates'};
  var COL = {V:'#6FD98A', D:'#FF7B7B', E:'var(--ink-3)'};
  out.sort(function(a,b){ return b.n-a.n; });
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Rachas activas</h3>'+
    (out.length
      ? '<table class="tabla"><tbody>'+out.slice(0,8).map(function(r){
          return '<tr><td>'+U.celdaEquipo(r.e)+'</td>'+
            '<td class="num" style="font-weight:600;color:'+COL[r.s]+'">'+r.n+'</td>'+
            '<td style="color:var(--ink-3);font-size:.75rem">'+ETQ[r.s]+' seguidas</td></tr>';
        }).join('')+'</tbody></table>'
      : '<p class="ayuda">Ningún club encadena dos resultados iguales todavía.</p>')+
  '</div>';
}

/* --- #33 Proyección de puntos ---------------------------------------- */
function proyeccion(){
  var D = d();
  var filas = [];
  C.DIVISIONES.forEach(function(div){
    var total = jornadasDe(div);
    C.clasificacion(div).slice(0,6).forEach(function(e){
      if(!e.pj) return;
      filas.push({e:e, div:div, ritmo:e.pts/e.pj, proy:Math.round(e.pts/e.pj*total), total:total});
    });
  });
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Proyección de puntos</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Estimación simple: puntos por partido × jornadas del calendario. No predice nada, sólo prolonga el ritmo actual.</p>'+
    (filas.length
      ? '<table class="tabla"><thead><tr><th>Club</th><th class="num">Pts</th><th class="num">Ritmo</th><th class="num">Proyección</th></tr></thead><tbody>'+
        filas.map(function(r){
          return '<tr><td>'+U.celdaEquipo(r.e)+'</td><td class="num">'+(r.e.pts||0)+'</td>'+
            '<td class="num" style="color:var(--ink-3)">'+r.ritmo.toFixed(2)+'</td>'+
            '<td class="num" style="font-weight:600">'+r.proy+'</td></tr>';
        }).join('')+'</tbody></table>'
      : '<p class="ayuda">Todavía no hay partidos jugados.</p>')+
  '</div>';
}
function jornadasDe(div){
  var ms = div==='ASCENSO'?d().partidos_ascenso:d().partidos_liga;
  var porEquipo = {};
  ms.filter(C.esRegular).forEach(function(p){
    porEquipo[p.local]=(porEquipo[p.local]||0)+1;
    porEquipo[p.visitante]=(porEquipo[p.visitante]||0)+1;
  });
  var vals = Object.keys(porEquipo).map(function(k){ return porEquipo[k]; });
  return vals.length ? Math.max.apply(null, vals) : 0;
}

/* --- #35 Jugador revelación ------------------------------------------
   Compara los goles de esta temporada con la media de sus temporadas
   anteriores, que es lo que guarda el historial. Sin historial no hay con qué
   comparar, y se dice. */
function revelacion(){
  var out = [];
  d().equipos.filter(function(e){ return !e.archivado; }).forEach(function(e){
    (e.jugadores||[]).forEach(function(j){
      var previas = (j.historial||[]).filter(function(h){ return !h.abierto; });
      var golesPrevios = previas.reduce(function(a,h){ return a+(h.goles||0); }, 0);
      if(!previas.length) return;
      var media = golesPrevios/previas.length;
      var ahora = j.goles||0;
      if(ahora<=media) return;
      out.push({j:j, e:e, ahora:ahora, media:media, salto:ahora-media});
    });
  });
  out.sort(function(a,b){ return b.salto-a.salto; });
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Jugadores revelación</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Quienes llevan más goles esta temporada que su media en las etapas ya cerradas de su historial.</p>'+
    (out.length
      ? '<table class="tabla"><thead><tr><th>Jugador</th><th class="num">Ahora</th><th class="num">Media</th></tr></thead><tbody>'+
        out.slice(0,8).map(function(r){
          return '<tr><td>'+esc(r.j.nombre)+'<span style="color:var(--ink-3);font-size:.75rem"> · '+esc(r.e.nombre)+'</span></td>'+
            '<td class="num" style="font-weight:600;color:#6FD98A">'+r.ahora+'</td>'+
            '<td class="num" style="color:var(--ink-3)">'+r.media.toFixed(1)+'</td></tr>';
        }).join('')+'</tbody></table>'
      : '<p class="ayuda">Nadie supera todavía su media histórica, o no hay historial con el que comparar.</p>')+
  '</div>';
}

/* --- #30 y #62 Cabeza a cabeza --------------------------------------- */
function cabezaACabeza(){
  var D = d();
  var activos = D.equipos.filter(function(e){ return !e.archivado; })
    .sort(function(a,b){ return a.nombre.localeCompare(b.nombre,'es'); });
  if(activos.length<2) return '';
  if(!h2h.a) h2h.a = activos[0].id;
  if(!h2h.b) h2h.b = activos[1].id;
  var A = C.equipoPorId(h2h.a), B = C.equipoPorId(h2h.b);
  if(!A||!B) return '';

  var duelos = D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa).filter(function(p){
    return C.isFin(p) &&
      ((p.local===A.nombre&&p.visitante===B.nombre)||(p.local===B.nombre&&p.visitante===A.nombre));
  });
  var ga=0, gb=0, emp=0, golesA=0, golesB=0;
  duelos.forEach(function(p){
    var aEsLocal = p.local===A.nombre;
    var x = Number(aEsLocal?C.gl(p):C.gv(p))||0;
    var y = Number(aEsLocal?C.gv(p):C.gl(p))||0;
    golesA+=x; golesB+=y;
    if(x>y) ga++; else if(y>x) gb++; else emp++;
  });

  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Cara a cara</h3>'+
    '<div class="rejilla rejilla-2" style="margin-bottom:var(--g5)">'+
      U.campo('Club A', '<select class="inp" data-c="estadisticas:h2h" data-lado="a">'+
        activos.map(function(x){ return '<option value="'+esc(x.id)+'"'+(x.id===h2h.a?' selected':'')+'>'+esc(x.nombre)+'</option>'; }).join('')+'</select>')+
      U.campo('Club B', '<select class="inp" data-c="estadisticas:h2h" data-lado="b">'+
        activos.map(function(x){ return '<option value="'+esc(x.id)+'"'+(x.id===h2h.b?' selected':'')+'>'+esc(x.nombre)+'</option>'; }).join('')+'</select>')+
    '</div>'+
    (duelos.length
      ? '<div style="display:flex;align-items:center;gap:var(--g5);justify-content:center;margin-bottom:var(--g5);flex-wrap:wrap">'+
          lado(A, ga, golesA)+
          '<div style="text-align:center"><div class="mono" style="font-size:.75rem;color:var(--ink-3)">'+emp+' empates</div>'+
            '<div class="ayuda">'+duelos.length+' enfrentamientos</div></div>'+
          lado(B, gb, golesB)+
        '</div>'+
        '<div class="tabla-caja">'+duelos.map(function(p){
          return '<div class="problema"><i class="ph ph-soccer-ball"></i>'+
            '<span>'+esc(p.local)+' <b class="mono">'+C.gl(p)+'–'+C.gv(p)+'</b> '+esc(p.visitante)+'</span>'+
            '<span style="margin-left:auto;color:var(--ink-3);font-size:.75rem">'+esc(p.fase||('Jornada '+(p.jornada||'?')))+'</span></div>';
        }).join('')+'</div>'
      : '<p class="ayuda">Estos dos clubes no se han enfrentado todavía en ninguna competición.</p>')+
  '</div>';
}
function lado(e, victorias, goles){
  return '<div style="text-align:center;min-width:120px">'+
    '<div style="display:flex;justify-content:center;margin-bottom:.35rem">'+U.escudo(e)+'</div>'+
    '<div class="mono" style="font-size:1.75rem;font-weight:600">'+victorias+'</div>'+
    '<div class="ayuda">'+esc(e.nombre)+' · '+goles+' goles</div></div>';
}

var A = {
  comp: function(el){ comp = el.dataset.v; U.refrescar(); },
  h2h:  function(el){ h2h[el.dataset.lado] = el.value; U.refrescar(); }
};

U.registrar('estadisticas', {acciones:A, render:pintar});

})();
