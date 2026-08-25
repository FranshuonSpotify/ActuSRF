/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-graficos.js
   Visualización avanzada. Sólo lee: ningún gráfico escribe en el archivo.

   SVG escrito a mano, sin librería de gráficos. No es cabezonería: los siete
   gráficos que pide CLAUDE.md §5.2.5 son formas simples (líneas, rejillas de
   color, polígonos) y una librería traería por CDN cien veces más código del
   que ocupan, con su propio sistema de temas peleándose con los tokens del
   Design System. El SVG usa las mismas variables de color que el resto.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var divEvo = 'SUPERLIGA';       // división del gráfico de evolución
var jugRadar = null;            // "idEquipo|nombre" del jugador del radar

function d(){ return SFG.d(); }

/* --------------------------------------------------------------------------
   AYUDANTES DE SVG
   -------------------------------------------------------------------------- */
function svg(w, h, cuerpo, extra){
  /* viewBox + width 100%: escala solo en cualquier ancho sin media queries.
     El ancho mínimo lo pone .grafico en CSS, que además da el desplazamiento
     horizontal: sin él, en pantalla estrecha el texto se apelmazaba hasta
     ser ilegible en vez de poder desplazarse. */
  return '<svg viewBox="0 0 '+w+' '+h+'" width="100%" style="display:block;overflow:visible'+(extra||'')+'" '+
    'role="img" preserveAspectRatio="xMidYMid meet">'+cuerpo+'</svg>';
}
/* Recorta un rótulo a lo que quepa de verdad en los píxeles disponibles.
   Antes se cortaba a 14 caracteres a ojo y los nombres largos se salían del
   margen derecho del gráfico. */
function recorta(texto, px, tam){
  var max = Math.max(3, Math.floor(px/(tam*0.58)));
  texto = String(texto||'');
  return texto.length<=max ? texto : texto.slice(0, max-1)+'…';
}
function txt(x, y, s, opciones){
  opciones = opciones || {};
  return '<text x="'+x+'" y="'+y+'" fill="'+(opciones.color||'var(--ink-3)')+'" '+
    'font-size="'+(opciones.tam||10)+'" font-family="var(--f-mono)" '+
    'text-anchor="'+(opciones.anclaje||'start')+'"'+(opciones.peso?' font-weight="'+opciones.peso+'"':'')+'>'+
    esc(s)+'</text>';
}
/* Paleta para series múltiples. Se parte del ámbar de marca y se recorre el
   tono; con doce equipos hace falta que se distingan entre sí. */
function color(i, n){
  return 'hsl('+Math.round((22 + i*(360/Math.max(n,1)))%360)+' 72% 58%)';
}

/* --------------------------------------------------------------------------
   #29 EVOLUCIÓN DE LA POSICIÓN, JORNADA A JORNADA
   Se recalcula la tabla acumulada tras cada jornada con la misma fórmula que
   la web, y se sigue el puesto de cada club.
   -------------------------------------------------------------------------- */
function tablaHasta(div, jornada){
  var ms = (div==='ASCENSO' ? d().partidos_ascenso : d().partidos_liga)
    .filter(function(p){ return C.isFin(p) && C.esRegular(p) && (parseInt(p.jornada)||0)<=jornada; });
  var t = {};
  d().equipos.filter(function(e){ return e.division===div && !e.archivado; })
    .forEach(function(e){ t[e.nombre] = {nombre:e.nombre, id:e.id, pj:0,g:0,e:0,p:0,gf:0,gc:0,pts:0}; });
  ms.forEach(function(p){
    var a = Number(C.gl(p))||0, b = Number(C.gv(p))||0;
    var L = t[p.local], V = t[p.visitante];
    if(L){ L.pj++; L.gf+=a; L.gc+=b; if(a>b){L.g++;L.pts+=3;} else if(a===b){L.e++;L.pts++;} else L.p++; }
    if(V){ V.pj++; V.gf+=b; V.gc+=a; if(b>a){V.g++;V.pts+=3;} else if(a===b){V.e++;V.pts++;} else V.p++; }
  });
  return C.orderStandings(Object.keys(t).map(function(k){ return t[k]; }));
}

function evolucion(){
  var jorn = Array.from(new Set(
    (divEvo==='ASCENSO' ? d().partidos_ascenso : d().partidos_liga)
      .filter(function(p){ return C.isFin(p) && C.esRegular(p); })
      .map(function(p){ return parseInt(p.jornada)||0; })
  )).filter(Boolean).sort(function(a,b){ return a-b; });

  var equipos = d().equipos.filter(function(e){ return e.division===divEvo && !e.archivado; });
  var cabecera = '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Evolución en la tabla</h3>'+
      '<div style="display:flex;gap:.25rem;margin-left:auto">'+
        C.DIVISIONES.map(function(x){
          return '<button class="btn btn-sm '+(divEvo===x?'btn-primary':'btn-secondary')+'" data-a="graficos:divEvo" data-v="'+x+'">'+
            (x==='SUPERLIGA'?'Superliga':'Ascenso')+'</button>';
        }).join('')+
      '</div></div>';

  if(jorn.length<2 || !equipos.length)
    return tarjeta(cabecera+'<p class="ayuda">Hacen falta al menos dos jornadas jugadas para dibujar una evolución.</p>');

  /* Puesto de cada club tras cada jornada. */
  var series = {};
  equipos.forEach(function(e){ series[e.nombre] = []; });
  jorn.forEach(function(j){
    tablaHasta(divEvo, j).forEach(function(e, i){
      if(series[e.nombre]) series[e.nombre].push(i+1);
    });
  });

  var W = 720, H = 300, mI = 34, mD = 128, mS = 14, mB = 26;
  var n = equipos.length;
  var px = function(k){ return mI + k*((W-mI-mD)/Math.max(jorn.length-1,1)); };
  var py = function(pos){ return mS + (pos-1)*((H-mS-mB)/Math.max(n-1,1)); };

  var cuerpo = '';
  /* Rejilla horizontal: una línea por puesto. */
  for(var pos=1; pos<=n; pos++){
    cuerpo += '<line x1="'+mI+'" y1="'+py(pos)+'" x2="'+(W-mD)+'" y2="'+py(pos)+'" stroke="var(--line)" stroke-width="1"/>';
    cuerpo += txt(mI-8, py(pos)+3, String(pos), {anclaje:'end', tam:9});
  }
  jorn.forEach(function(j, k){ cuerpo += txt(px(k), H-8, 'J'+j, {anclaje:'middle', tam:9}); });

  /* Orden final para que la leyenda salga en el orden de la tabla. */
  var finales = tablaHasta(divEvo, jorn[jorn.length-1]);
  finales.forEach(function(e, idx){
    var s = series[e.nombre];
    if(!s || s.length<2) return;
    var col = color(idx, n);
    var pts = s.map(function(pos, k){ return px(k)+','+py(pos); }).join(' ');
    cuerpo += '<polyline points="'+pts+'" fill="none" stroke="'+col+'" stroke-width="2" '+
      'stroke-linejoin="round" stroke-linecap="round" opacity=".9"><title>'+esc(e.nombre)+'</title></polyline>';
    /* Punto final más grueso: es donde el ojo busca quién es quién. */
    cuerpo += '<circle cx="'+px(s.length-1)+'" cy="'+py(s[s.length-1])+'" r="3.5" fill="'+col+'"/>';
    cuerpo += txt(W-mD+8, py(s[s.length-1])+3,
      C.abbr3(e.nombre, e.abreviatura)+'  '+recorta(e.nombre, mD-30, 9),
      {color:col, tam:9, peso:600});
  });

  return tarjeta(cabecera+
    '<div class="grafico grafico-alto">'+svg(W, H, cuerpo)+'</div>'+
    '<p class="ayuda" style="margin-top:var(--g3)">Puesto tras cada jornada, recalculado con la fórmula de la web. '+
    'Sólo cuentan los partidos de jornada regular: las eliminatorias no mueven la tabla.</p>');
}

/* --------------------------------------------------------------------------
   #67 MAPA DE DENSIDAD DE GOLES POR JORNADA
   -------------------------------------------------------------------------- */
function heatmap(){
  var comps = [['Superliga','partidos_liga'], ['Ascenso','partidos_ascenso']];
  var jorn = [];
  comps.forEach(function(c){
    d()[c[1]].forEach(function(p){
      var j = parseInt(p.jornada)||0;
      if(j && jorn.indexOf(j)<0) jorn.push(j);
    });
  });
  jorn.sort(function(a,b){ return a-b; });
  if(!jorn.length) return '';

  var celda = 22, hueco = 3, etq = 74;
  var W = etq + jorn.length*(celda+hueco), H = comps.length*(celda+hueco) + 22;
  var max = 0, datos = {};
  comps.forEach(function(c, fila){
    jorn.forEach(function(j){
      var ms = d()[c[1]].filter(function(p){ return (parseInt(p.jornada)||0)===j && C.isFin(p); });
      var g = ms.reduce(function(a,p){ return a+(Number(C.gl(p))||0)+(Number(C.gv(p))||0); }, 0);
      datos[fila+'|'+j] = {g:g, n:ms.length};
      if(g>max) max = g;
    });
  });

  var cuerpo = '';
  comps.forEach(function(c, fila){
    var y = fila*(celda+hueco);
    cuerpo += txt(etq-8, y+celda/2+3, c[0], {anclaje:'end', tam:9});
    jorn.forEach(function(j, k){
      var v = datos[fila+'|'+j];
      /* Sin partidos jugados, celda hueca; con ellos, opacidad proporcional
         al número de goles. Es la escala del calendario de GitHub. */
      var op = v.n ? (0.15 + 0.85*(max?v.g/max:0)) : 0;
      cuerpo += '<rect x="'+(etq+k*(celda+hueco))+'" y="'+y+'" width="'+celda+'" height="'+celda+'" rx="3" '+
        'fill="'+(v.n?'var(--accent)':'var(--surface-3)')+'" opacity="'+(v.n?op.toFixed(2):'1')+'" '+
        'stroke="var(--line)" stroke-width="1">'+
        '<title>'+esc(c[0]+' · jornada '+j+': '+(v.n?v.g+' goles en '+v.n+' partidos':'sin jugar'))+'</title></rect>';
    });
  });
  jorn.forEach(function(j, k){
    cuerpo += txt(etq+k*(celda+hueco)+celda/2, H-6, String(j), {anclaje:'middle', tam:8});
  });

  return tarjeta(
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Goles por jornada</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Cuanto más intenso, más goles. Las celdas huecas son jornadas sin jugar. Máximo: '+max+' goles.</p>'+
    '<div class="grafico">'+svg(W, H, cuerpo)+'</div>');
}

/* --------------------------------------------------------------------------
   #28 MAPA DE AFINIDADES DOMINANTES
   -------------------------------------------------------------------------- */
function afinidades(){
  var goleadores = {};
  C.calcScorers(d().partidos_liga.concat(d().partidos_ascenso, d().partidos_copa).filter(C.isFin))
    .forEach(function(r){ if(r.j) goleadores[C.afKey(r.j.afinidad)] = (goleadores[C.afKey(r.j.afinidad)]||0)+r.goles; });

  var filas = C.DIVISIONES.map(function(div){
    var t = {};
    d().equipos.filter(function(e){ return e.division===div && !e.archivado; })
      .forEach(function(e){ (e.jugadores||[]).forEach(function(j){
        var k = C.afKey(j.afinidad); t[k] = (t[k]||0)+1;
      }); });
    return {etq:div, t:t, total:Object.keys(t).reduce(function(a,k){ return a+t[k]; }, 0)};
  });
  var totalGoles = Object.keys(goleadores).reduce(function(a,k){ return a+goleadores[k]; }, 0);
  if(totalGoles) filas.push({etq:'GOLES', t:goleadores, total:totalGoles, esGoles:true});

  var CLAVES = ['fuego','montana','bosque','aire','neutro'];
  var ETQ = {fuego:'Fuego', montana:'Montaña', bosque:'Bosque', aire:'Aire', neutro:'Neutro'};

  return tarjeta(
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Afinidades dominantes</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Reparto de las cinco afinidades por división, y qué afinidad marca los goles. '+
      'Los valores sucios del archivo se cuentan donde la web los interpreta.</p>'+
    filas.map(function(f){
      return '<div style="margin-bottom:var(--g4)">'+
        '<div style="display:flex;font-size:.6875rem;margin-bottom:.25rem">'+
          '<span style="font-family:var(--f-mono);letter-spacing:.1em;color:var(--ink-3)">'+esc(f.etq)+'</span>'+
          '<span class="ayuda" style="margin-left:auto">'+f.total+(f.esGoles?' goles':' jugadores')+'</span></div>'+
        '<div style="display:flex;height:22px;border-radius:var(--r-sm);overflow:hidden;border:1px solid var(--line)">'+
          CLAVES.map(function(k){
            var v = f.t[k]||0;
            if(!v) return '';
            var pct = v/f.total*100;
            return '<div style="width:'+pct.toFixed(1)+'%;background:'+C.AF_HEX[k]+';display:grid;place-items:center" '+
              'title="'+esc(ETQ[k]+': '+v+' ('+Math.round(pct)+'%)')+'">'+
              (pct>9?'<span style="font-size:.5625rem;color:#000;font-weight:700">'+Math.round(pct)+'%</span>':'')+'</div>';
          }).join('')+
        '</div></div>';
    }).join('')+
    '<div style="display:flex;gap:var(--g3);flex-wrap:wrap;margin-top:var(--g3)">'+
      CLAVES.map(function(k){
        return '<span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.6875rem;color:var(--ink-3)">'+
          '<span style="width:9px;height:9px;border-radius:2px;background:'+C.AF_HEX[k]+'"></span>'+ETQ[k]+'</span>';
      }).join('')+
    '</div>');
}

/* --------------------------------------------------------------------------
   #65 RADAR DE JUGADOR
   Cada eje se normaliza contra el máximo de su misma posición: comparar un
   portero con un delantero en goles no diría nada.
   -------------------------------------------------------------------------- */
function radar(){
  var todos = [];
  d().equipos.filter(function(e){ return !e.archivado; }).forEach(function(e){
    (e.jugadores||[]).forEach(function(j){ todos.push({j:j, e:e}); });
  });
  if(!todos.length) return '';
  if(!jugRadar || !todos.some(function(x){ return x.e.id+'|'+x.j.nombre===jugRadar; })){
    /* Por defecto, el máximo goleador: es el que tiene algo que enseñar. */
    var mejor = todos.slice().sort(function(a,b){ return (b.j.goles||0)-(a.j.goles||0); })[0];
    jugRadar = mejor.e.id+'|'+mejor.j.nombre;
  }
  var sel = todos.find(function(x){ return x.e.id+'|'+x.j.nombre===jugRadar; });
  var j = sel.j;

  var mismos = todos.filter(function(x){ return x.j.posicion===j.posicion; }).map(function(x){ return x.j; });
  var EJES = [
    {k:'goles', etq:'Goles'},
    {k:'asistencias', etq:'Asist.'},
    {k:'amarillas', etq:'Amarillas'},
    {k:'rojas', etq:'Rojas'},
    {k:'carrera', etq:'Carrera'}
  ];
  function valor(x, k){ return k==='carrera' ? (x.goles_totales||0)+(x.goles||0) : (x[k]||0); }
  var maxes = {}, medias = {};
  EJES.forEach(function(a){
    maxes[a.k] = Math.max.apply(null, mismos.map(function(x){ return valor(x, a.k); }).concat([1]));
    medias[a.k] = mismos.reduce(function(s,x){ return s+valor(x, a.k); }, 0)/mismos.length;
  });

  var W = 300, H = 260, cx = W/2, cy = H/2 - 6, R = 88;
  function punto(i, frac){
    var ang = -Math.PI/2 + i*(2*Math.PI/EJES.length);
    return [(cx + Math.cos(ang)*R*frac).toFixed(1), (cy + Math.sin(ang)*R*frac).toFixed(1)];
  }
  var cuerpo = '';
  [0.25, 0.5, 0.75, 1].forEach(function(f){
    cuerpo += '<polygon points="'+EJES.map(function(_, i){ return punto(i, f).join(','); }).join(' ')+
      '" fill="none" stroke="var(--line)" stroke-width="1"/>';
  });
  EJES.forEach(function(a, i){
    var p = punto(i, 1);
    cuerpo += '<line x1="'+cx+'" y1="'+cy+'" x2="'+p[0]+'" y2="'+p[1]+'" stroke="var(--line)" stroke-width="1"/>';
    var e = punto(i, 1.22);
    cuerpo += txt(e[0], Number(e[1])+3, a.etq, {anclaje:'middle', tam:9});
  });
  /* Media de la posición en gris, el jugador en ámbar: el contraste es el
     dato, no el polígono suelto. */
  cuerpo += '<polygon points="'+EJES.map(function(a,i){ return punto(i, Math.min(medias[a.k]/maxes[a.k],1)).join(','); }).join(' ')+
    '" fill="var(--ink-4)" fill-opacity=".18" stroke="var(--ink-4)" stroke-width="1" stroke-dasharray="3 3"/>';
  cuerpo += '<polygon points="'+EJES.map(function(a,i){ return punto(i, Math.min(valor(j,a.k)/maxes[a.k],1)).join(','); }).join(' ')+
    '" fill="var(--accent)" fill-opacity=".22" stroke="var(--accent)" stroke-width="2"/>';
  EJES.forEach(function(a, i){
    var p = punto(i, Math.min(valor(j,a.k)/maxes[a.k],1));
    cuerpo += '<circle cx="'+p[0]+'" cy="'+p[1]+'" r="3" fill="var(--accent)"><title>'+
      esc(a.etq+': '+valor(j,a.k)+' (máximo entre '+j.posicion+': '+maxes[a.k]+')')+'</title></circle>';
  });

  var vacios = EJES.filter(function(a){ return maxes[a.k]<=1 && medias[a.k]===0; });

  return tarjeta(
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Radar de jugador</h3>'+
      '<select class="inp inp-sm" style="width:auto;margin-left:auto;max-width:230px" data-c="graficos:radar">'+
        todos.slice().sort(function(a,b){ return String(a.j.nombre).localeCompare(String(b.j.nombre),'es'); })
          .map(function(x){
            var v = x.e.id+'|'+x.j.nombre;
            return '<option value="'+esc(v)+'"'+(v===jugRadar?' selected':'')+'>'+esc(x.j.nombre)+' · '+esc(x.e.nombre)+'</option>';
          }).join('')+
      '</select></div>'+
    '<div style="display:flex;gap:var(--g4);align-items:center;flex-wrap:wrap">'+
      '<div style="flex:1;min-width:250px">'+svg(W, H, cuerpo)+'</div>'+
      '<div style="min-width:150px">'+
        '<div style="font-size:.875rem;font-weight:600;margin-bottom:.15rem">'+esc(j.nombre)+'</div>'+
        '<div class="ayuda" style="margin-bottom:var(--g3)"><span class="chip chip-'+String(j.posicion||'').toLowerCase()+'">'+
          esc(j.posicion||'—')+'</span> '+esc(sel.e.nombre)+'</div>'+
        EJES.map(function(a){
          return '<div style="display:flex;font-size:.75rem;padding:.15rem 0">'+
            '<span style="color:var(--ink-3)">'+a.etq+'</span>'+
            '<span class="mono" style="margin-left:auto">'+valor(j,a.k)+
            '<span style="color:var(--ink-4)"> / '+medias[a.k].toFixed(1)+'</span></span></div>';
        }).join('')+
        '<p class="ayuda" style="margin-top:var(--g3)">Valor del jugador frente a la media de los '+mismos.length+' '+esc(j.posicion||'jugadores')+'.</p>'+
      '</div></div>'+
    (vacios.length
      ? '<p class="ayuda" style="margin-top:var(--g3)"><i class="ph ph-info"></i> '+
        vacios.map(function(a){ return a.etq.toLowerCase(); }).join(', ')+
        ': sin datos en todo el archivo, así que ese eje siempre sale a cero.</p>' : ''));
}

/* --------------------------------------------------------------------------
   #66 ÁRBOL DE TRASPASOS
   Se reconstruye desde el historial de cada jugador: cada par de etapas
   consecutivas es un movimiento entre dos clubes.
   -------------------------------------------------------------------------- */
function traspasos(){
  var aristas = {};
  function anota(desdeId, hastaId, jugador){
    if(!desdeId || !hastaId || desdeId===hastaId) return;
    var k = desdeId+'>'+hastaId;
    (aristas[k] = aristas[k] || {de:desdeId, a:hastaId, quien:[]}).quien.push(jugador);
  }
  d().equipos.forEach(function(e){
    (e.jugadores||[]).forEach(function(j){
      var h = j.historial||[];
      for(var i=1;i<h.length;i++) anota(h[i-1].equipo_id, h[i].equipo_id, j.nombre);
      /* La última etapa cerrada hacia el club actual también es un traspaso. */
      if(h.length && !h[h.length-1].abierto) anota(h[h.length-1].equipo_id, e.id, j.nombre);
    });
  });
  var lista = Object.keys(aristas).map(function(k){ return aristas[k]; })
    .sort(function(a,b){ return b.quien.length-a.quien.length; });

  if(!lista.length) return tarjeta(
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Movimientos entre clubes</h3>'+
    '<p class="ayuda">Ningún jugador tiene dos etapas en clubes distintos todavía. Los traspasos que hagas desde la pestaña Traspasos irán apareciendo aquí.</p>');

  var maxN = lista[0].quien.length;
  return tarjeta(
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Movimientos entre clubes</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Reconstruido desde el historial de los jugadores: '+lista.length+
      ' rutas distintas. El grosor es cuánta gente ha hecho ese camino.</p>'+
    '<div class="tabla-caja">'+lista.slice(0,25).map(function(a){
      var A = C.equipoPorId(a.de), B = C.equipoPorId(a.a);
      return '<div class="problema" title="'+esc(a.quien.slice(0,12).join(', '))+'">'+
        '<span style="min-width:130px;display:flex;align-items:center;gap:.35rem">'+U.escudo(A)+
          '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(A?A.nombre:a.de)+'</span></span>'+
        '<span style="flex:1;min-width:40px;height:3px;border-radius:2px;background:var(--accent);opacity:'+
          (0.3+0.7*a.quien.length/maxN).toFixed(2)+'"></span>'+
        '<span style="min-width:130px;display:flex;align-items:center;gap:.35rem">'+U.escudo(B)+
          '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(B?B.nombre:a.a)+'</span></span>'+
        '<span class="mono" style="color:var(--ink-3);font-size:.75rem;min-width:24px;text-align:right">'+a.quien.length+'</span>'+
      '</div>';
    }).join('')+'</div>'+
    (lista.length>25 ? '<p class="ayuda" style="margin-top:var(--g3)">y '+(lista.length-25)+' rutas más.</p>' : ''));
}

/* --------------------------------------------------------------------------
   VISTA
   -------------------------------------------------------------------------- */
function tarjeta(html){ return '<div class="card" style="padding:var(--g5)">'+html+'</div>'; }

function pintar(el){
  el.innerHTML =
    U.cabecera('Gráficos', 'Todo se dibuja desde el archivo. Ningún gráfico escribe nada.',
      '<button class="btn btn-secondary btn-sm" data-a="graficos:cine"><i class="ph ph-magnifying-glass-plus"></i> Cuadro de Copa</button>'+
      '<button class="btn btn-primary btn-sm" data-a="graficos:tv"><i class="ph-bold ph-television-simple"></i> Modo presentación</button>')+
    evolucion()+
    '<div class="g-hueco"></div>'+heatmap()+
    '<div class="g-hueco"></div><div class="rejilla" style="--min:340px;align-items:start">'+
      afinidades()+radar()+
    '</div>'+
    '<div class="g-hueco"></div>'+traspasos();
}

var A = {
  divEvo: function(el){ divEvo = el.dataset.v; U.refrescar(); },
  radar:  function(el){ jugRadar = el.value; U.refrescar(); },
  tv:     function(){ SFG.tv.abrir(); },
  cine:   function(){ SFG.tv.cine(); }
};

U.registrar('graficos', {acciones:A, render:pintar, tablaHasta:tablaHasta});
SFG.graficos = {tablaHasta:tablaHasta};

})();
