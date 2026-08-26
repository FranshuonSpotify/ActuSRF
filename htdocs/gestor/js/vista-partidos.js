/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-partidos.js
   Calendario de Liga y Ascenso, cuadro de Copa y el editor de eventos que
   comparten los tres.

   El editor de eventos existe por una razón concreta: los goles de la web se
   enlazan al jugador comparando el NOMBRE en texto, con coincidencia difusa.
   Escribir "gol:Ralei:23" a mano deja el gol sin ficha y nadie se entera. Aquí
   el jugador se elige de la plantilla del club que anotó, y la cadena
   `detalles` se genera siempre desde la estructura, nunca al revés.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var st = {comp:'liga', j:null, vista:'regular'};   // competición, jornada y qué se está mirando
var copaFase = '';                     // filtro de fase en el cuadro
var edit = null;                       // {comp, idx, ev} mientras el editor está abierto
var TODAS = '*';                       // valor del selector para «todas las jornadas»

function d(){ return SFG.d(); }
function lista(comp){ return comp==='ascenso'?d().partidos_ascenso:comp==='copa'?d().partidos_copa:d().partidos_liga; }

/* Jornadas presentes en una competición, en orden numérico. */
function jornadas(comp){
  return Array.from(new Set(lista(comp).map(function(p){ return p.jornada; })))
    .filter(function(x){ return x!=null && x!==''; })
    .sort(function(a,b){ return (parseInt(a)||0)-(parseInt(b)||0); });
}

/* --------------------------------------------------------------------------
   CASCADA
   Un resultado cambia la clasificación de dos clubes. Se recalcula la tabla
   entera desde los partidos y se vuelca: es la misma cuenta que hace la web,
   y dejarla desincronizada es el fallo más caro de este programa.
   -------------------------------------------------------------------------- */
function cascada(){
  var calc = C.tablaCalculada(), tocados = 0;
  d().equipos.forEach(function(e){
    var c = calc[e.nombre]; if(!c) return;
    C.CAMPOS_TABLA.forEach(function(k){
      if((e[k]||0)!==c[k]){ e[k] = c[k]; tocados++; }
    });
  });
  return tocados;
}
function trasResultado(){
  var n = cascada();
  U.cambio();
  if(n) U.aviso('Clasificación actualizada ('+n+' valores).', 'ok');
}

/* --------------------------------------------------------------------------
   LIGA Y ASCENSO
   -------------------------------------------------------------------------- */
function pintar(el){
  var js = jornadas(st.comp);
  /* `null` significa «aun no he elegido», y entonces se abre por la ultima
     jornada. `'*'` significa «quiero todas», y eso hay que respetarlo: antes
     se trataba igual que null y el selector volvia solo a la ultima. */
  if(st.j==null || (st.j!==TODAS && js.indexOf(st.j)<0)) st.j = js.length ? js[js.length-1] : TODAS;
  var todos = lista(st.comp);
  var elim = todos.map(function(p,i){ return {p:p,i:i}; }).filter(function(o){ return !C.esRegular(o.p); });
  if(!elim.length) st.vista = 'regular';

  /* Las eliminatorias no son una jornada mas: o se miran las jornadas o se
     miran ellas, nunca mezcladas en la misma tabla. Antes salian en las dos
     cosas a la vez, duplicadas. */
  var vis = st.vista==='elim'
    ? elim
    : todos.map(function(p,i){ return {p:p,i:i}; })
        .filter(function(o){ return C.esRegular(o.p) && (st.j===TODAS || o.p.jornada===st.j); });

  var pend = vis.filter(function(o){ return !C.isFin(o.p); }).length;

  el.innerHTML =
    U.cabecera('Partidos', 'Calendario y resultados de Liga y Ascenso',
      '<button class="btn btn-secondary btn-sm" data-a="partidos:simular"><i class="ph ph-flask"></i> Simular jornada</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="partidos:recalcular"><i class="ph ph-calculator"></i> Recalcular clasificación</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="partidos:recalcularGoles"><i class="ph ph-soccer-ball"></i> Recalcular goles de jugador</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="partidos:nuevaElim"><i class="ph ph-tree-structure"></i> Añadir eliminatoria</button>'+
      '<button class="btn btn-primary btn-sm" data-a="partidos:nuevo"><i class="ph-bold ph-plus"></i> Añadir partido</button>')+

    '<div class="g-filtros">'+
      '<div style="display:flex;gap:.25rem">'+
        [['liga','Superliga'],['ascenso','Ascenso']].map(function(c){
          return '<button class="btn btn-sm '+(st.comp===c[0]?'btn-primary':'btn-secondary')+'" data-a="partidos:comp" data-v="'+c[0]+'">'+c[1]+'</button>';
        }).join('')+
      '</div>'+
      (elim.length
        ? '<div style="display:flex;gap:.25rem;margin-left:var(--g3)">'+
            [['regular','Jornadas'],['elim','Eliminatorias · '+elim.length]].map(function(v){
              return '<button class="btn btn-sm '+(st.vista===v[0]?'btn-accent':'btn-secondary')+
                '" data-a="partidos:vista" data-v="'+v[0]+'">'+v[1]+'</button>';
            }).join('')+
          '</div>'
        : '')+
      (js.length && st.vista==='regular' ? '<span style="display:flex;align-items:center;gap:.25rem;margin-left:var(--g3)">'+
        '<button class="btn btn-secondary btn-sm" data-a="partidos:jorMenos" aria-label="Jornada anterior"><i class="ph ph-caret-left"></i></button>'+
        '<select class="inp inp-sm" style="width:auto" data-c="partidos:jor">'+
          '<option value="'+TODAS+'"'+(st.j===TODAS?' selected':'')+'>Todas las jornadas</option>'+
          js.map(function(j){ return '<option value="'+esc(j)+'"'+(st.j===j?' selected':'')+'>Jornada '+esc(j)+'</option>'; }).join('')+
        '</select>'+
        '<button class="btn btn-secondary btn-sm" data-a="partidos:jorMas" aria-label="Jornada siguiente"><i class="ph ph-caret-right"></i></button>'+
      '</span>' : '')+
      '<span class="ayuda" style="margin-left:auto">'+vis.length+' partidos'+(pend?' · '+pend+' sin resultado':'')+'</span>'+
    '</div>'+

    (st.vista==='elim' ? cabeceraElim(elim) : '')+
    (vis.length ? tablaPartidos(vis, st.comp)
      : '<div class="vacio">'+(st.vista==='elim'?'No hay eliminatorias.':'No hay partidos en esta jornada.')+'</div>')+
    '<div class="g-hueco"></div>'+
    (st.vista==='regular' ? crearEnfrentamiento()+moverJornada() : '');

  if(st.vista==='regular'){ montarCalendario(); montarCrear(); }
}

/* Cabecera del modo eliminatorias: dice qué son y cuántas hay de cada fase,
   para que se vea la estructura del cuadro sin tener que leer la tabla. */
function cabeceraElim(elim){
  var porFase = {};
  elim.forEach(function(o){ porFase[o.p.fase] = (porFase[o.p.fase]||0)+1; });
  var orden = C.FASES_LIGA.filter(function(f){ return porFase[f]; })
    .concat(Object.keys(porFase).filter(function(f){ return C.FASES_LIGA.indexOf(f)<0; }));
  return '<div class="elim-bloque" style="padding:var(--g4) var(--g5)">'+
    '<div class="elim-tit"><i class="ph-bold ph-tree-structure"></i> Fuera del calendario regular</div>'+
    '<p class="ayuda">Estos partidos <b>no reparten puntos</b>: la clasificación los ignora. '+
      'La web muestra la etiqueta de la fase en lugar de «Jornada N».</p>'+
    '<div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-top:var(--g3)">'+
      orden.map(function(f){ return '<span class="pastilla pastilla-ojo">'+esc(f)+' · '+porFase[f]+'</span>'; }).join('')+
    '</div></div>';
}

/* --------------------------------------------------------------------------
   CREAR ENFRENTAMIENTOS ARRASTRANDO
   Se arrastra un club sobre otro y sale el partido. Es la forma natural de
   montar una jornada desde cero: pensar en parejas, no en filas de tabla.
   -------------------------------------------------------------------------- */
function crearEnfrentamiento(){
  var div = st.comp==='ascenso' ? 'ASCENSO' : 'SUPERLIGA';
  var eqs = d().equipos.filter(function(e){ return e.division===div && !e.archivado; })
    .sort(function(a,b){ return a.nombre.localeCompare(b.nombre,'es'); });
  if(eqs.length<2) return '';
  var jorDestino = (st.j===TODAS||!st.j) ? (jornadas(st.comp).slice(-1)[0]||'1') : st.j;

  return '<div class="card" style="padding:var(--g5);margin-top:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Crear enfrentamiento</h3>'+
      '<span class="ayuda" style="margin-left:auto">a la jornada '+
        '<input class="inp inp-sm inp-num" id="crear-jor" value="'+esc(jorDestino)+'"></span>'+
    '</div>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Arrastra un club sobre otro y se crea el partido: el primero juega en casa. '+
      'Sin ratón, usa los dos desplegables de abajo.</p>'+
    '<div class="crear-pista" id="crear-pista">'+
      eqs.map(function(e){
        return '<div class="dnd-ficha crear-eq" data-nombre="'+esc(e.nombre)+'" role="button" tabindex="0" '+
            'aria-label="'+esc(e.nombre)+'">'+
          U.escudo(e,'sm')+'<span class="nm">'+esc(C.abbr3(e.nombre,e.abreviatura))+'</span></div>';
      }).join('')+
    '</div>'+
    '<div style="display:flex;gap:.35rem;align-items:flex-end;margin-top:var(--g4);flex-wrap:wrap">'+
      U.campo('Local', U.selectEquipos('', 'class="inp inp-sm" id="crear-local"'))+
      '<span style="color:var(--ink-5);padding-bottom:.6rem">–</span>'+
      U.campo('Visitante', U.selectEquipos('', 'class="inp inp-sm" id="crear-visitante"'))+
      '<button class="btn btn-primary btn-sm" data-a="partidos:crearManual">Crear</button>'+
    '</div></div>';
}
function montarCrear(){
  var pista = document.getElementById('crear-pista');
  if(!pista) return;
  /* Soltar un club encima de otro crea el partido. El propio contenedor es la
     zona: lo que importa es sobre QUIÉN se suelta, no dónde queda. */
  SFG.dnd.sortable({
    grupo:'crear', item:'.crear-eq', contenedores:[pista],
    alSoltar:function(dd){
      var hijos = Array.prototype.slice.call(pista.querySelectorAll('.crear-eq'));
      var i = hijos.indexOf(dd.item);
      /* El vecino sobre el que ha caído: el de al lado en el sentido del
         movimiento. Si se suelta en el mismo sitio no hay pareja. */
      var vecino = hijos[i-1] || hijos[i+1];
      if(!vecino) return U.refrescar();
      crearPartido(dd.item.dataset.nombre, vecino.dataset.nombre);
    }
  });
}
function crearPartido(local, visitante){
  if(!local || !visitante || local===visitante) return U.refrescar();
  var j = (document.getElementById('crear-jor')||{}).value || st.j || '1';
  var ya = lista(st.comp).some(function(p){
    return p.jornada===String(j) && ((p.local===local&&p.visitante===visitante)||(p.local===visitante&&p.visitante===local));
  });
  U.confirmar({
    titulo:'Crear '+local+' – '+visitante,
    html:'Se añade a la <b>jornada '+esc(String(j))+'</b> como pendiente, con '+esc(local)+' en casa.'+
      (ya ? '<br><br><b style="color:var(--gold)">Esos dos ya se enfrentan en esa jornada.</b> Se creará otro partido igual.' : ''),
    ok:'Crear'
  }).then(function(si){
    if(!si) return U.refrescar();
    lista(st.comp).push(nuevoPartidoLiga(local, visitante, String(j)));
    st.j = String(j);
    U.cambio();
    U.aviso(local+' – '+visitante+' creado en la jornada '+j+'.', 'ok');
  });
}
function nuevoPartidoLiga(local, visitante, jornada){
  return {jornada:jornada, fecha:'', estado:'PENDIENTE', local:local, visitante:visitante,
          goles_l:0, goles_v:0, detalles:' / '};
}



/* --------------------------------------------------------------------------
   CALENDARIO ARRASTRABLE
   Una columna por jornada; los partidos se mueven entre ellas arrastrando.

   Alternativa sin ratón: la casilla «J» de la tabla de arriba hace lo mismo
   escribiendo el número, y con el partido enfocado las flechas izquierda y
   derecha lo cambian de jornada. Arrastrar nunca es la única vía.
   -------------------------------------------------------------------------- */
function moverJornada(){
  var js = jornadas(st.comp);
  if(js.length<2) return '';
  var todos = lista(st.comp);
  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Calendario</h3>'+
      '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="partidos:nuevaJornada">'+
        '<i class="ph ph-plus"></i> Jornada '+(Math.max.apply(null,js.map(Number).concat(0))+1)+'</button>'+
    '</div>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Arrastra un partido a otra jornada. Con el teclado: enfócalo y usa las flechas ← →, o escribe el número en la columna «J» de la tabla.</p>'+
    '<div style="display:flex;gap:var(--g3);overflow-x:auto;padding-bottom:var(--g2)" id="cal-cols">'+
      js.map(function(j){
        var ps = todos.map(function(p,i){ return {p:p,i:i}; }).filter(function(o){ return o.p.jornada===j; });
        var fin = ps.filter(function(o){ return C.isFin(o.p); }).length;
        return '<div style="min-width:186px;flex-shrink:0">'+
          '<div style="display:flex;align-items:center;gap:.35rem;margin-bottom:var(--g2)">'+
            '<span style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3)">JORNADA '+esc(j)+'</span>'+
            '<span class="pastilla'+(fin===ps.length&&ps.length?' pastilla-ok':'')+'" style="margin-left:auto">'+fin+'/'+ps.length+'</span>'+
          '</div>'+
          '<div class="dnd-col" data-jornada="'+esc(j)+'">'+
            (ps.length ? ps.map(fichaPartido).join('') : '<div class="vacio">Vacía</div>')+
          '</div></div>';
      }).join('')+
    '</div></div>';
}
function fichaPartido(o){
  var p = o.p, elim = !C.esRegular(p);
  return '<div class="dnd-ficha cal-p" data-i="'+o.i+'" role="button" '+
      'aria-label="'+esc((p.local||'?')+' contra '+(p.visitante||'?')+', jornada '+(p.jornada||''))+'">'+
    '<i class="ph ph-dots-six-vertical dnd-asa" aria-hidden="true"></i>'+
    '<span class="nm">'+esc(C.abbr3(p.local,(C.equipo(p.local)||{}).abreviatura))+
      ' <span style="color:var(--ink-4)">'+(C.isFin(p)?(C.gl(p)+'-'+C.gv(p)):'vs')+'</span> '+
      esc(C.abbr3(p.visitante,(C.equipo(p.visitante)||{}).abreviatura))+'</span>'+
    (elim ? '<span class="pastilla pastilla-ojo tras" title="'+esc(p.fase)+'">'+esc(p.fase.slice(0,3))+'</span>' : '')+
  '</div>';
}
/* Se monta después de pintar. Cada repintado crea nodos nuevos, así que los
   oyentes viejos se van con ellos y no hay que desmontar nada. */
function montarCalendario(){
  var cols = document.querySelectorAll('#cal-cols .dnd-col');
  if(!cols.length) return;
  SFG.dnd.sortable({
    grupo:'calendario', item:'.cal-p',
    contenedores:Array.prototype.slice.call(cols),
    alSoltar:function(dd){
      var p = lista(st.comp)[Number(dd.item.dataset.i)];
      var nueva = dd.hasta.dataset.jornada;
      if(p.jornada===nueva) return;
      p.jornada = nueva;
      U.aviso((p.local||'?')+' – '+(p.visitante||'?')+' pasa a la jornada '+nueva+'.', 'ok');
      U.cambio();
    }
  });
}

function tablaPartidos(vis, comp){
  return barraLote(vis)+
  '<div class="tabla-caja"><div class="tabla-scroll"><table class="tabla"><thead><tr>'+
    '<th class="acc"><input type="checkbox" data-c="partidos:lotesTodos" aria-label="Seleccionar todos"'+
      (vis.length && vis.every(function(o){ return lote[o.i]; }) ? ' checked' : '')+'></th>'+
    '<th class="num">J</th><th>Fase</th><th>Fecha</th><th>Local</th><th class="num">Goles</th><th></th><th class="num">Goles</th><th>Visitante</th>'+
    '<th>Estado</th><th>Eventos</th><th class="acc"></th></tr></thead><tbody>'+
    vis.map(function(o){ return filaPartido(o.p, o.i, comp); }).join('')+
  '</tbody></table></div></div>';
}

/* --------------------------------------------------------------------------
   EDICIÓN EN LOTE
   Marcar 31 partidos como no jugados de uno en uno son 31 clics y 31
   repintados. Con selección múltiple es uno.
   -------------------------------------------------------------------------- */
var lote = {};
function seleccionados(){ return Object.keys(lote).filter(function(k){ return lote[k]; }).map(Number); }

/* La barra se pinta en su propio hueco y se refresca sola. Marcar una
   casilla NO repinta la tabla: hacerlo destruía los checkbox mientras se
   estaban marcando, así que de tres clics sólo contaba el primero. */
function barraLote(vis){
  return '<div id="lote-barra">'+contenidoBarra(vis)+'</div>';
}
function contenidoBarra(vis){
  var sel = seleccionados().filter(function(i){ return vis.some(function(o){ return o.i===i; }); });
  if(!sel.length) return '';
  return '<div class="card" style="padding:var(--g3) var(--g4);margin-bottom:var(--g3);'+
      'display:flex;align-items:center;gap:var(--g3);flex-wrap:wrap;border-color:var(--accent)">'+
    '<b style="font-size:.8125rem">'+sel.length+(sel.length===1?' seleccionado':' seleccionados')+'</b>'+
    '<button class="btn btn-secondary btn-sm" data-a="partidos:loteNoJugado">'+
      '<i class="ph ph-prohibit"></i> Marcar como no jugado</button>'+
    '<button class="btn btn-secondary btn-sm" data-a="partidos:loteEstado" data-v="FINALIZADO">Finalizar</button>'+
    '<button class="btn btn-secondary btn-sm" data-a="partidos:loteEstado" data-v="PENDIENTE">Pasar a pendiente</button>'+
    '<span style="display:flex;align-items:center;gap:.3rem">'+
      '<span class="ayuda">Mover a jornada</span>'+
      '<input class="inp inp-sm inp-num" type="number" min="1" id="lote-jor" placeholder="nº">'+
      '<button class="btn btn-secondary btn-sm" data-a="partidos:loteJornada">Mover</button></span>'+
    '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="partidos:loteNada">Quitar selección</button>'+
  '</div>';
}

function filaPartido(p, i, comp){
  var ev = C.parseDetalles(p.detalles);
  var goles = ev.local.filter(esGol).length + ev.visitante.filter(esGol).length;
  var marcados = (Number(C.gl(p))||0) + (Number(C.gv(p))||0);
  /* Marcador y goleadores tienen que decir lo mismo: si no, la ficha del
     partido en la web enseña un 3-1 con dos goleadores. */
  var descuadre = C.isFin(p) && goles!==marcados;
  var elim = !C.esRegular(p);
  var noJug = C.esNoJugado(p);
  /* Un partido no jugado no descuadra: no tuvo goles que anotar. */
  if(noJug) descuadre = false;
  return '<tr'+(descuadre?' class="ojo"':'')+(noJug?' class="apagado"':'')+'>'+
    '<td class="acc"><input type="checkbox" data-c="partidos:lote" data-i="'+i+'"'+(lote[i]?' checked':'')+
      ' aria-label="Seleccionar partido"></td>'+
    '<td class="num"><input class="inp inp-sm inp-num" value="'+esc(p.jornada||'')+'" data-c="partidos:campo" data-i="'+i+'" data-k="jornada"'+
      (elim && (p.jornada==null||p.jornada==='') ? ' style="border-color:var(--c-copa)" title="Una eliminatoria sin jornada no se ve en la web"' : '')+'></td>'+
    /* Con fase, el partido es eliminatoria: no reparte puntos y la web pone
       la etiqueta en el pie de la tarjeta en lugar de "Jornada N". */
    '<td><select class="inp inp-sm" style="width:auto;min-width:104px'+(elim?';border-color:var(--accent);color:var(--accent-2)':'')+'" data-c="partidos:campo" data-i="'+i+'" data-k="fase" '+
      'title="Vacío = jornada regular. Con fase, no suma a la clasificación.">'+
      '<option value="">Regular</option>'+
      C.FASES_LIGA.map(function(f){ return '<option value="'+esc(f)+'"'+(p.fase===f?' selected':'')+'>'+esc(f)+'</option>'; }).join('')+
      (p.fase && C.FASES_LIGA.indexOf(p.fase)<0 ? '<option selected>'+esc(p.fase)+'</option>' : '')+
    '</select></td>'+
    '<td><input class="inp inp-sm" style="width:120px" value="'+esc(p.fecha||'')+'" data-c="partidos:campo" data-i="'+i+'" data-k="fecha" placeholder="dd/mm/aaaa"></td>'+
    '<td style="min-width:180px">'+U.selectEquipos(p.local, 'class="inp inp-sm" data-c="partidos:campo" data-i="'+i+'" data-k="local"')+'</td>'+
    '<td class="num"><input class="inp inp-sm inp-num" type="number" min="0" value="'+(Number(C.gl(p))||0)+'" data-c="partidos:gol" data-i="'+i+'" data-k="goles_l"></td>'+
    '<td style="color:var(--ink-5)">–</td>'+
    '<td class="num"><input class="inp inp-sm inp-num" type="number" min="0" value="'+(Number(C.gv(p))||0)+'" data-c="partidos:gol" data-i="'+i+'" data-k="goles_v"></td>'+
    '<td style="min-width:180px">'+U.selectEquipos(p.visitante, 'class="inp inp-sm" data-c="partidos:campo" data-i="'+i+'" data-k="visitante"')+'</td>'+
    '<td><select class="inp inp-sm" style="width:auto" data-c="partidos:estado" data-i="'+i+'">'+
      ['PENDIENTE','FINALIZADO'].map(function(e){ return '<option'+(p.estado===e?' selected':'')+'>'+e+'</option>'; }).join('')+'</select></td>'+
    '<td><button class="btn btn-secondary btn-sm" data-a="partidos:eventos" data-comp="'+comp+'" data-i="'+i+'">'+
      (goles||ev.local.length||ev.visitante.length
        ? '<i class="ph-bold ph-list-bullets"></i> '+(ev.local.length+ev.visitante.length)
        : '<i class="ph ph-plus"></i>')+
      '</button>'+
      (noJug?' <span class="pastilla" title="No se disputó: victoria administrativa">no jugado</span>':'')+
      (descuadre?' <span class="pastilla pastilla-ojo" title="El marcador dice '+marcados+' goles y hay '+goles+' goleadores">'+goles+'/'+marcados+'</span>':'')+
    '</td>'+
    '<td class="acc"><button class="btn btn-secondary btn-sm" data-a="partidos:borrar" data-comp="'+comp+'" data-i="'+i+'">×</button></td>'+
  '</tr>';
}
function esGol(e){ return e.tipo==='gol'; }

/* Sólo se reemplaza el contenido de la barra: la tabla y sus casillas se
   quedan donde están. */
function refrescarBarra(){
  var caja = document.getElementById('lote-barra');
  if(!caja) return;
  var todos = lista(st.comp);
  var vis = todos.map(function(p,i){ return {p:p,i:i}; })
    .filter(function(o){ return st.j===TODAS || o.p.jornada===st.j; });
  caja.innerHTML = contenidoBarra(vis);
}

/* --------------------------------------------------------------------------
   COPA
   -------------------------------------------------------------------------- */
function pintarCopa(el){
  var ms = d().partidos_copa;
  var fases = C.FASES_TODAS.filter(function(f){ return ms.some(function(p){ return p.fase===f; }); });
  var otras = Array.from(new Set(ms.map(function(p){ return p.fase; })
    .filter(function(f){ return f && C.FASES_TODAS.indexOf(f)<0; })));

  el.innerHTML =
    U.cabecera('Copa Fútbol Frontier', ms.length+' cruces · las rondas se encadenan solas por el ganador de la anterior',
      '<button class="btn btn-primary btn-sm" data-a="copa:nuevo"><i class="ph-bold ph-plus"></i> Añadir cruce</button>')+
    '<div class="g-filtros">'+
      '<select class="inp inp-sm" style="width:auto" data-c="copa:fase">'+
        '<option value="">Todas las fases</option>'+
        fases.concat(otras).map(function(f){ return '<option value="'+esc(f)+'"'+(copaFase===f?' selected':'')+'>'+esc(f)+'</option>'; }).join('')+
      '</select>'+
      '<span class="ayuda" style="margin-left:auto">'+ms.filter(C.isFin).length+' de '+ms.length+' jugados</span>'+
    '</div>'+
    (ms.length ? tablaCopa(ms) : '<div class="vacio">La Copa todavía no tiene cruces.</div>')+
    '<div class="g-hueco"></div>'+
    bloqueGrupos()+
    '<div class="g-hueco"></div>'+
    cuadroPrevio(ms, fases);

  montarGrupos();
  montarCuadro();
}

/* --------------------------------------------------------------------------
   GRUPOS DE COPA
   El reparto vive en config.grupos_copa como {A:[nombre,…], B:[…]}. Es un
   dato aparte de los partidos a propósito: después del sorteo hay que poder
   mover un equipo de bombo antes de que exista un solo cruce, y si el reparto
   sólo viviera dentro de partidos_copa[].grupo no habría dónde apuntarlo.
   «Aplicar a los partidos» es el paso explícito que vuelca uno en el otro.
   -------------------------------------------------------------------------- */
function bloqueGrupos(){
  var D = d(), letras = C.letrasGrupo(D), gc = D.config.grupos_copa || {};
  var asignados = {};
  Object.keys(gc).forEach(function(g){ (gc[g]||[]).forEach(function(n){ asignados[n] = g; }); });
  var sinAsignar = D.equipos.filter(function(e){ return !e.archivado && !asignados[e.nombre]; });
  var porGrupo = (D.config.formatos.COPA||{}).clasifican_por_grupo || 2;

  /* Cuántos partidos de grupo hay ya por letra: sirve para avisar antes de
     regenerar nada. */
  var conPartidos = {};
  (D.partidos_copa||[]).forEach(function(p){
    if(p.fase==='FASE DE GRUPOS' && p.grupo) conPartidos[p.grupo] = (conPartidos[p.grupo]||0)+1;
  });

  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Fase de grupos</h3>'+
      '<span class="pastilla">'+letras.length+' grupos · pasan '+porGrupo+'</span>'+
      '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="copa:repartirGrupos"><i class="ph ph-shuffle"></i> Repartir</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="copa:vaciarGrupos">Vaciar</button>'+
      '<button class="btn btn-primary btn-sm" data-a="copa:aplicarGrupos"><i class="ph-bold ph-arrow-down"></i> Aplicar a los partidos</button>'+
    '</div>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Arrastra clubes entre grupos. Sin ratón: enfoca un club y usa ← → para cambiarlo de grupo, o el selector de cada ficha.</p>'+

    '<div class="rejilla" style="--min:200px">'+
      letras.map(function(g){
        var l = gc[g] || [];
        return '<div>'+
          '<div style="display:flex;align-items:center;gap:.35rem;margin-bottom:var(--g2)">'+
            '<span style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3)">GRUPO '+g+'</span>'+
            '<span class="pastilla'+(l.length===4?' pastilla-ok':(l.length>4?' pastilla-mal':''))+'" style="margin-left:auto">'+l.length+'</span>'+
            (conPartidos[g] ? '<span class="pastilla pastilla-ojo" title="Ya hay partidos con este grupo">'+conPartidos[g]+'P</span>' : '')+
          '</div>'+
          '<div class="dnd-col" data-grupo="'+g+'">'+
            (l.length ? l.map(function(n){ return fichaClub(n, g, letras); }).join('') : '<div class="vacio">Vacío</div>')+
          '</div></div>';
      }).join('')+
    '</div>'+

    '<div style="margin-top:var(--g5)">'+
      '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">SIN ASIGNAR · '+sinAsignar.length+'</div>'+
      '<div class="dnd-col" data-grupo="" style="flex-direction:row;flex-wrap:wrap">'+
        (sinAsignar.length ? sinAsignar.map(function(e){ return fichaClub(e.nombre, '', letras); }).join('')
                           : '<div class="vacio">Todos los clubes activos están repartidos.</div>')+
      '</div></div>'+
  '</div>';
}
function fichaClub(nombre, grupo, letras){
  var e = C.equipo(nombre);
  return '<div class="dnd-ficha grp-c" data-nombre="'+esc(nombre)+'" role="button" '+
      'aria-label="'+esc(nombre+(grupo?', grupo '+grupo:', sin asignar'))+'">'+
    '<i class="ph ph-dots-six-vertical dnd-asa" aria-hidden="true"></i>'+
    U.escudo(e)+
    '<span class="nm">'+esc(nombre)+'</span>'+
    '<span class="tras">'+
      /* Selector: la misma acción sin arrastrar, para teclado y para quien
         prefiera no arrastrar 16 fichas a mano. */
      '<select class="inp inp-sm" style="width:auto;height:24px;font-size:.6875rem;padding-inline:.3rem" '+
        'data-c="copa:grupoDe" data-nombre="'+esc(nombre)+'" aria-label="Grupo de '+esc(nombre)+'">'+
        '<option value=""'+(grupo?'':' selected')+'>—</option>'+
        letras.map(function(g){ return '<option value="'+g+'"'+(grupo===g?' selected':'')+'>'+g+'</option>'; }).join('')+
      '</select>'+
    '</span>'+
  '</div>';
}
function montarGrupos(){
  var cols = document.querySelectorAll('.dnd-col[data-grupo]');
  if(!cols.length) return;
  SFG.dnd.sortable({
    grupo:'grupos-copa', item:'.grp-c',
    contenedores:Array.prototype.slice.call(cols),
    alSoltar:function(dd){ ponerEnGrupo(dd.item.dataset.nombre, dd.hasta.dataset.grupo); }
  });
}
/* Un club sólo puede estar en un grupo: se quita de todos y se mete en el
   nuevo. La validación lo comprueba igualmente, pero es mejor que el estado
   imposible no llegue a existir. */
function ponerEnGrupo(nombre, grupo){
  var gc = d().config.grupos_copa;
  Object.keys(gc).forEach(function(g){
    gc[g] = (gc[g]||[]).filter(function(n){ return n!==nombre; });
  });
  if(grupo){
    if(!gc[grupo]) gc[grupo] = [];
    gc[grupo].push(nombre);
  }
  U.cambio();
}

function tablaCopa(ms){
  var vis = ms.map(function(p,i){ return {p:p,i:i}; })
    .filter(function(o){ return !copaFase || o.p.fase===copaFase; });
  if(!vis.length) return '<div class="vacio">Ningún cruce en esa fase.</div>';
  return '<div class="tabla-caja"><div class="tabla-scroll"><table class="tabla"><thead><tr>'+
    '<th class="num">#</th><th>Fase</th><th>Grupo</th><th>Local</th><th class="num">G</th><th class="num">G</th><th>Visitante</th>'+
    '<th>Estado</th><th>Eventos</th><th class="acc"></th></tr></thead><tbody>'+
    vis.map(function(o){
      var p = o.p, i = o.i;
      var pen = C.parseDetalles(p.detalles).pen;
      var grupos = p.fase==='FASE DE GRUPOS';
      return '<tr>'+
        /* El índice se muestra porque es lo que guardan origen_local y
           origen_visitante: sin él, un cruce mal vinculado es indepurable. */
        '<td class="num" style="color:var(--ink-4)">'+i+'</td>'+
        '<td><select class="inp inp-sm" style="width:auto" data-c="copa:campo" data-i="'+i+'" data-k="fase">'+
          C.FASES_TODAS.map(function(f){ return '<option'+(p.fase===f?' selected':'')+'>'+f+'</option>'; }).join('')+
          (p.fase && C.FASES_TODAS.indexOf(p.fase)<0 ? '<option selected>'+esc(p.fase)+'</option>' : '')+
        '</select></td>'+
        '<td>'+(grupos?'<input class="inp inp-sm inp-num" value="'+esc(p.grupo||'')+'" data-c="copa:campo" data-i="'+i+'" data-k="grupo" placeholder="A">':'')+'</td>'+
        '<td style="min-width:210px">'+ladoCopa(p, i, 'local')+'</td>'+
        '<td class="num"><input class="inp inp-sm inp-num" type="number" min="0" value="'+(Number(C.gl(p))||0)+'" data-c="copa:gol" data-i="'+i+'" data-k="goles_l"></td>'+
        '<td class="num"><input class="inp inp-sm inp-num" type="number" min="0" value="'+(Number(C.gv(p))||0)+'" data-c="copa:gol" data-i="'+i+'" data-k="goles_v"></td>'+
        '<td style="min-width:210px">'+ladoCopa(p, i, 'visitante')+'</td>'+
        '<td><select class="inp inp-sm" style="width:auto" data-c="copa:estado" data-i="'+i+'">'+
          ['PENDIENTE','FINALIZADO'].map(function(e){ return '<option'+(p.estado===e?' selected':'')+'>'+e+'</option>'; }).join('')+'</select>'+
          (pen?'<div class="pastilla" style="margin-top:.25rem">PEN '+pen.l+'-'+pen.v+'</div>':'')+
        '</td>'+
        '<td><button class="btn btn-secondary btn-sm" data-a="partidos:eventos" data-comp="copa" data-i="'+i+'"><i class="ph-bold ph-list-bullets"></i></button></td>'+
        '<td class="acc"><button class="btn btn-secondary btn-sm" data-a="copa:borrar" data-i="'+i+'">×</button></td>'+
      '</tr>';
    }).join('')+'</tbody></table></div></div>';
}

/* Un lado de un cruce: o se elige el equipo a mano, o se encadena al ganador
   de otro cruce. Nunca las dos cosas, para que no haya dos verdades. */
function ladoCopa(p, i, lado){
  var k = lado==='local' ? 'origen_local' : 'origen_visitante';
  var vinculado = p[k]!=null && p[k]!=='';
  var sel = '<select class="inp inp-sm" style="margin-top:.25rem;font-size:.6875rem" data-c="copa:origen" data-i="'+i+'" data-k="'+lado+'">'+
    '<option value="">Equipo fijo</option>'+
    d().partidos_copa.map(function(q,qi){
      if(qi===i) return '';
      return '<option value="'+qi+'"'+(String(p[k])===String(qi)?' selected':'')+'>Ganador de #'+qi+': '+esc(etiquetaCruce(q))+'</option>';
    }).join('')+'</select>';

  if(vinculado){
    var r = C.resolveSide(p, lado);
    return '<div style="font-size:.75rem;'+(r.pend?'color:var(--ink-4)':'color:var(--ink);font-weight:500')+'">'+
      (r.pend ? '<i class="ph ph-hourglass"></i> ' : '<i class="ph-bold ph-arrow-elbow-down-right" style="color:var(--accent)"></i> ')+
      esc(r.n||'—')+'</div>'+sel;
  }
  return U.selectEquipos(p[lado], 'class="inp inp-sm" data-c="copa:campo" data-i="'+i+'" data-k="'+lado+'"')+sel;
}
function etiquetaCruce(q){
  var a = q.local ? C.abbr3(q.local, (C.equipo(q.local)||{}).abreviatura) : '?';
  var b = q.visitante ? C.abbr3(q.visitante, (C.equipo(q.visitante)||{}).abreviatura) : '?';
  return a+'/'+b;
}

/* Cuadro tal y como lo pintará la web, resolviendo la cascada, y además
   editable: se pueden mover equipos de un cruce a otro arrastrando.

   Un hueco vinculado al ganador de una ronda previa no se puede arrastrar:
   ahí no hay un equipo, hay una regla. Para poner uno a mano primero hay que
   quitar la vinculación, y el propio hueco lo dice. */
function cuadroPrevio(ms, fases){
  if(!fases.length) return '';
  var eliminatorias = fases.filter(function(f){ return f!=='FASE DE GRUPOS'; });
  if(!eliminatorias.length) return '';
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Cuadro</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Así lo verá la web. Arrastra un equipo a otro hueco para cambiar el cruce; si el hueco está ocupado, se intercambian. '+
      'Sin ratón: usa los desplegables de la tabla de arriba.</p>'+
    '<div style="display:flex;gap:var(--g5);overflow-x:auto;padding-bottom:var(--g2)" id="br-cuadro">'+
    eliminatorias.map(function(f){
      var cruces = ms.map(function(p,i){ return {p:p,i:i}; }).filter(function(o){ return o.p.fase===f; });
      return '<div style="min-width:216px">'+
        '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">'+esc(f)+'</div>'+
        cruces.map(function(o){
          var w = C.winnerOf(o.p), fin = C.isFin(o.p);
          return '<div class="card" style="margin-bottom:var(--g2);padding:.2rem">'+
            hueco(o, 'local', C.gl(o.p), w, fin)+
            hueco(o, 'visitante', C.gv(o.p), w, fin)+
          '</div>';
        }).join('')+'</div>';
    }).join('')+'</div></div>';
}
function hueco(o, lado, gol, w, fin){
  var r = C.resolveSide(o.p, lado);
  var vinculado = r.origen!=null;
  var gana = fin && w===o.p[lado];
  return '<div class="br-slot'+(vinculado?' fijo':'')+'" data-slot="'+o.i+'" data-lado="'+lado+'"'+
      (vinculado?' title="Viene del ganador del cruce #'+r.origen+'. Quita la vinculación para poner un equipo a mano."':'')+'>'+
    (vinculado
      ? '<span class="br-vinc"><i class="ph ph-arrow-elbow-down-right"></i>'+esc(r.n||'—')+'</span>'
      : (o.p[lado]
          ? '<div class="br-eq'+(gana?' gana':(fin?' pierde':''))+'" data-nombre="'+esc(o.p[lado])+'" role="button" tabindex="0" '+
              'aria-label="'+esc(o.p[lado]+', '+lado)+'">'+
              U.escudo(C.equipo(o.p[lado]))+
              '<span class="nm">'+esc(o.p[lado])+'</span>'+
              (fin?'<span class="mono" style="margin-left:auto">'+gol+'</span>':'')+
            '</div>'
          : '<span class="br-vacio">vacío</span>'))+
  '</div>';
}
function montarCuadro(){
  var slots = document.querySelectorAll('#br-cuadro .br-slot:not(.fijo)');
  if(!slots.length) return;
  SFG.dnd.sortable({
    grupo:'cuadro', item:'.br-eq',
    contenedores:Array.prototype.slice.call(slots),
    /* Un hueco sólo admite un equipo, y nunca uno vinculado a otra ronda. */
    puedeSoltar:function(item, slot){ return !slot.classList.contains('fijo'); },
    alSoltar:function(dd){
      /* El movimiento lo resuelve core: hay intercambio si el destino estaba
         ocupado, y hay casos que hay que rechazar (un equipo contra si mismo).
         La vista solo informa del resultado. */
      var r = C.moverEnCuadro(d(),
        {idx:Number(dd.desde.dataset.slot), lado:dd.desde.dataset.lado},
        {idx:Number(dd.hasta.dataset.slot), lado:dd.hasta.dataset.lado});
      if(!r){
        U.aviso('Ese movimiento dejaria el cruce invalido.', 'ojo');
        return U.refrescar();
      }
      U.cambio();
      U.aviso(r.ocupante ? r.movido+' y '+r.ocupante+' intercambian cruce.' : r.movido+' se mueve de cruce.', 'ok');
    }
  });
}

/* --------------------------------------------------------------------------
   EDITOR DE EVENTOS
   -------------------------------------------------------------------------- */
function abrirEditor(comp, i){
  var p = lista(comp)[i];
  edit = {comp:comp, idx:i, ev:C.parseDetalles(p.detalles)};
  U.modal({
    titulo:'Eventos · '+(p.local||'?')+' – '+(p.visitante||'?'),
    ancho:true,
    cuerpo:cuerpoEditor(),
    pie:[
      {txt:'Cancelar', fn:function(){ edit=null; U.cerrarModal(); }},
      {txt:'Guardar eventos', cls:'btn-primary', fn:aplicarEventos}
    ],
    alCerrar:function(){ edit=null; }
  });
}
function cuerpoEditor(){
  var p = lista(edit.comp)[edit.idx];
  var gl = Number(C.gl(p))||0, gv = Number(C.gv(p))||0;
  var nl = edit.ev.local.filter(esGol).length, nv = edit.ev.visitante.filter(esGol).length;
  var mal = (nl!==gl || nv!==gv);
  return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--g5)" class="ed-cols">'+
      columna('local', p.local, gl, nl)+
      columna('visitante', p.visitante, gv, nv)+
    '</div>'+
    (mal ? '<p class="mal" style="margin-top:var(--g4);font-size:.8125rem"><i class="ph-bold ph-warning"></i> '+
      'El marcador dice '+gl+'–'+gv+' y hay '+nl+'–'+nv+' goleadores. La web muestra el marcador, pero la cronología quedará incompleta.</p>' : '')+
    (edit.comp==='copa' ? bloquePenaltis() : '')+
    '<p class="ayuda" style="margin-top:var(--g4)">Se guardará como <span class="mono">'+esc(C.serializarDetalles(edit.ev)||' / ')+'</span></p>';
}
function columna(lado, nombreEq, goles, cuenta){
  var eq = C.equipo(nombreEq);
  var evs = edit.ev[lado];
  return '<div>'+
    '<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:var(--g3)">'+
      U.celdaEquipo(eq, nombreEq||'Sin equipo')+
      '<span class="pastilla'+(cuenta===goles?' pastilla-ok':' pastilla-ojo')+'" style="margin-left:auto">'+cuenta+'/'+goles+' goles</span>'+
    '</div>'+
    (evs.length ? evs.map(function(e,k){ return filaEvento(lado, e, k, eq); }).join('')
                : '<div class="vacio" style="padding:1rem">Sin eventos.</div>')+
    '<button class="btn btn-secondary btn-sm" style="width:100%;margin-top:var(--g2)" data-a="partidos:evAdd" data-lado="'+lado+'">'+
      '<i class="ph ph-plus"></i> Añadir evento</button>'+
  '</div>';
}
function filaEvento(lado, e, k, eq){
  return '<div style="display:flex;gap:.25rem;margin-bottom:.35rem;align-items:center">'+
    /* Sólo se ofrece «gol»: en esta liga no se registran asistencias ni
       tarjetas. Si un evento antiguo trae otro tipo se respeta y se muestra,
       pero no se propone crear ninguno nuevo. */
    (C.TIPOS_EDITABLES.indexOf(e.tipo)<0
      ? '<span class="pastilla pastilla-ojo" style="width:96px;justify-content:center" title="Tipo heredado">'+esc(C.TIPO_LABEL[e.tipo]||e.tipo)+'</span>'
      : '<span class="pastilla" style="width:96px;justify-content:center"><i class="ph-bold ph-soccer-ball"></i> Gol</span>')+
    selectJugador(eq, e.nombre, 'data-c="partidos:ev" data-lado="'+lado+'" data-i="'+k+'" data-k="nombre"')+
    '<input class="inp inp-sm inp-num" type="number" min="0" max="130" value="'+esc(e.minuto||'')+'" placeholder="min" data-c="partidos:ev" data-lado="'+lado+'" data-i="'+k+'" data-k="minuto">'+
    '<button class="btn btn-secondary btn-sm" data-a="partidos:evDel" data-lado="'+lado+'" data-i="'+k+'" aria-label="Quitar evento">×</button>'+
  '</div>';
}
/* Desplegable con la plantilla del club. Si el nombre guardado ya no está en
   ella (traspaso, errata antigua), se conserva como opción marcada en vez de
   perderlo al abrir el editor. */
function selectJugador(eq, valor, attrs){
  var js = (eq && eq.jugadores || []).slice().sort(function(a,b){
    return String(a.nombre).localeCompare(String(b.nombre),'es');
  });
  var dentro = js.some(function(j){ return j.nombre===valor; });
  return '<select class="inp inp-sm" style="flex:1;min-width:0" '+attrs+'>'+
    '<option value="">— jugador —</option>'+
    (valor && !dentro ? '<option value="'+esc(valor)+'" selected>'+esc(valor)+' (fuera de la plantilla)</option>' : '')+
    js.map(function(j){
      return '<option value="'+esc(j.nombre)+'"'+(j.nombre===valor?' selected':'')+'>'+esc(j.nombre)+(j.dorsal?' · '+esc(j.dorsal):'')+'</option>';
    }).join('')+
  '</select>';
}
function bloquePenaltis(){
  var pen = edit.ev.pen;
  return '<div style="margin-top:var(--g5);padding-top:var(--g4);border-top:1px solid var(--line)">'+
    '<label class="sw"><input type="checkbox"'+(pen?' checked':'')+' data-c="partidos:penOn"><span class="pista"></span> Se decidió en los penaltis</label>'+
    (pen ? '<div class="color-par" style="margin-top:var(--g3);max-width:200px">'+
        '<input class="inp inp-sm inp-num" type="number" min="0" value="'+pen.l+'" data-c="partidos:pen" data-k="l" aria-label="Penaltis local">'+
        '<span style="color:var(--ink-5)">–</span>'+
        '<input class="inp inp-sm inp-num" type="number" min="0" value="'+pen.v+'" data-c="partidos:pen" data-k="v" aria-label="Penaltis visitante">'+
      '</div>'+
      '<p class="ayuda" style="margin-top:.35rem">Sólo se usa si el partido acaba en empate: es lo que decide quién pasa de ronda.</p>' : '')+
  '</div>';
}
function repintarEditor(){
  document.getElementById('ov-cuerpo').innerHTML = cuerpoEditor();
}
function aplicarEventos(){
  var p = lista(edit.comp)[edit.idx];
  /* Se descartan los eventos a medio rellenar en vez de escribir basura en
     `detalles`, que es lo que la web parsea. */
  ['local','visitante'].forEach(function(l){
    edit.ev[l] = edit.ev[l].filter(function(e){ return e.nombre && e.tipo; });
  });
  p.detalles = C.serializarDetalles(edit.ev);
  /* Los textos derivados sólo se escriben si el partido ya los traía; el
     resto de la normalización los mantendrá al día en cada guardado. */
  if(p.goleadores_texto!=null || p.goleadores_local_texto!=null || p.goleadores_visitante_texto!=null){
    var t = C.textosDerivados(edit.ev);
    p.goleadores_texto = t.goleadores_texto;
    p.goleadores_local_texto = t.goleadores_local_texto;
    p.goleadores_visitante_texto = t.goleadores_visitante_texto;
  }
  edit = null;
  U.cerrarModal();
  U.cambio();
  U.aviso('Eventos guardados.', 'ok');
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
function nuevoPartido(comp){
  var js = jornadas(comp);
  var p = {
    jornada: st.j || (js.length ? js[js.length-1] : '1'),
    fecha:'', estado:'PENDIENTE', local:'', visitante:'',
    goles_l:0, goles_v:0, detalles:' / '
  };
  if(comp==='copa'){ p.fase='RONDA 1 (PREVIA)'; p.grupo=''; p.origen_local=null; p.origen_visitante=null; delete p.jornada; }
  lista(comp).push(p);
  U.cambio();
}
/* Antes, crear un play-off/play-in exigía dos pasos: crear una jornada
   regular y luego cambiarle la fase a mano en su fila. Y ni siquiera eso
   funcionaba a la primera, porque la pestaña «Eliminatorias» sólo aparece
   cuando YA existe una: con cero, no había dónde ir a crear la primera.
   Este botón crea el partido ya con fase puesta y salta directo a esa vista. */
function nuevaEliminatoria(comp){
  var usadas = lista(comp).filter(function(p){ return !C.esRegular(p); }).map(function(p){ return p.fase; });
  var fase = C.FASES_LIGA.filter(function(f){ return usadas.indexOf(f)<0; })[0] || C.FASES_LIGA[0];
  /* Necesita jornada aunque su tarjeta muestre la fase, no «Jornada N»: es lo
     que usa Resultados para tener una pestaña donde enseñarla (ver el aviso
     de partidos:campo más abajo). Se le da la siguiente libre, jornadas
     regulares y otras eliminatorias incluidas, para no chocar con ninguna. */
  var todas = lista(comp).map(function(p){ return parseInt(p.jornada)||0; }).concat(0);
  var p = {
    jornada: String(Math.max.apply(null, todas)+1),
    fecha:'', estado:'PENDIENTE', local:'', visitante:'',
    goles_l:0, goles_v:0, detalles:' / ', fase: fase
  };
  lista(comp).push(p);
  st.vista = 'elim';
  U.cambio();
  U.aviso('«'+fase+'» creado. Ponle equipos y resultado.', 'ok');
}
function borrarPartido(comp, i){
  var p = lista(comp)[i];
  /* En Copa, borrar un cruce mueve los índices de todos los siguientes y
     rompería las vinculaciones, que se guardan por posición en el array. */
  var dependientes = comp!=='copa' ? [] : d().partidos_copa.map(function(q,qi){ return {q:q,qi:qi}; })
    .filter(function(o){ return String(o.q.origen_local)===String(i) || String(o.q.origen_visitante)===String(i); });
  U.confirmar({
    titulo:'Eliminar partido',
    html:esc((p.local||'?')+' – '+(p.visitante||'?'))+
      (dependientes.length ? '<br><br><b style="color:var(--gold)">'+dependientes.length+' cruce(s) se alimentan de éste</b> y quedarán sin origen.' : '')+
      (comp==='copa' ? '<br><br>Los cruces de Copa se vinculan por su posición en la lista: al borrar uno, las vinculaciones posteriores se reajustan solas.' : ''),
    ok:'Eliminar', peligro:true
  }).then(function(si){
    if(!si) return;
    lista(comp).splice(i,1);
    if(comp==='copa') reajustarOrigenes(i);
    trasResultado();
  });
}
/* Tras borrar el cruce `k`, todo origen que apuntara a un índice mayor pasa a
   valer uno menos; el que apuntaba al borrado se queda sin vincular. */
function reajustarOrigenes(k){
  d().partidos_copa.forEach(function(p){
    ['origen_local','origen_visitante'].forEach(function(campo){
      var o = p[campo];
      if(o==null || o==='') return;
      o = Number(o);
      if(o===k) p[campo] = null;
      else if(o>k) p[campo] = o-1;
    });
  });
}

/* --------------------------------------------------------------------------
   SIMULACIÓN DE JORNADA
   Resultados hipotéticos sobre los partidos pendientes, para ver a dónde
   llevaría la tabla. NO se toca el archivo: la simulación se calcula sobre una
   copia y sólo «Aplicar» la vuelca sobre los partidos de verdad.
   -------------------------------------------------------------------------- */
var sim = null;      // {jornada, comp, res:{idx:{l,v}}}

function abrirSimulacion(){
  var pend = lista(st.comp).map(function(p,i){ return {p:p,i:i}; })
    .filter(function(o){ return !C.isFin(o.p) && C.esRegular(o.p) && (st.j===TODAS || o.p.jornada===st.j); });
  if(!pend.length) return U.aviso('No hay partidos pendientes en esta vista para simular.', 'ojo');

  sim = {comp:st.comp, res:{}};
  pend.forEach(function(o){ sim.res[o.i] = {l:0, v:0}; });
  U.modal({
    titulo:'Simular '+(st.j&&st.j!==TODAS?('jornada '+st.j):'los partidos pendientes'),
    ancho:true,
    cuerpo:cuerpoSim(pend),
    pie:[
      {txt:'Al azar', cls:'btn-secondary', izq:true, fn:function(){ azarSim(pend); }},
      {txt:'Descartar', fn:function(){ sim = null; U.cerrarModal(); }},
      {txt:'Aplicar resultados', cls:'btn-primary', fn:function(){ aplicarSim(pend); }}
    ],
    alCerrar:function(){ sim = null; }
  });
}
function cuerpoSim(pend){
  /* Clasificación resultante: se clona el archivo, se aplican los resultados
     de mentira a la copia y se recalcula con la misma fórmula de siempre. */
  var copia = JSON.parse(JSON.stringify(d()));
  var listaCopia = sim.comp==='ascenso' ? copia.partidos_ascenso : copia.partidos_liga;
  pend.forEach(function(o){
    var r = sim.res[o.i];
    listaCopia[o.i].estado = 'FINALIZADO';
    listaCopia[o.i].goles_l = r.l;
    listaCopia[o.i].goles_v = r.v;
  });
  var previo = SFG.d();
  SFG.setD(copia);
  var div = sim.comp==='ascenso' ? 'ASCENSO' : 'SUPERLIGA';
  var calc = C.tablaCalculada();
  copia.equipos.forEach(function(e){
    var c = calc[e.nombre]; if(c) C.CAMPOS_TABLA.forEach(function(k){ e[k] = c[k]; });
  });
  var despues = C.clasificacion(div).map(function(e){ return e.nombre; });
  SFG.setD(previo);
  var antes = C.clasificacion(div).map(function(e){ return e.nombre; });

  return '<p class="ayuda" style="margin-bottom:var(--g4)">Nada de esto se guarda hasta que pulses «Aplicar resultados».</p>'+
    '<div class="rejilla" style="--min:280px;align-items:start">'+
      '<div>'+
        '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">RESULTADOS HIPOTÉTICOS</div>'+
        pend.map(function(o){
          var r = sim.res[o.i];
          return '<div style="display:flex;align-items:center;gap:.35rem;margin-bottom:.35rem">'+
            '<span style="flex:1;text-align:right;font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(o.p.local||'?')+'</span>'+
            '<input class="inp inp-sm inp-num" type="number" min="0" max="20" value="'+r.l+'" data-c="partidos:simGol" data-i="'+o.i+'" data-k="l" aria-label="Goles de '+esc(o.p.local||'')+'">'+
            '<span style="color:var(--ink-5)">–</span>'+
            '<input class="inp inp-sm inp-num" type="number" min="0" max="20" value="'+r.v+'" data-c="partidos:simGol" data-i="'+o.i+'" data-k="v" aria-label="Goles de '+esc(o.p.visitante||'')+'">'+
            '<span style="flex:1;font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(o.p.visitante||'?')+'</span>'+
          '</div>';
        }).join('')+
      '</div>'+
      '<div>'+
        '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">CÓMO QUEDARÍA '+div+'</div>'+
        '<div class="tabla-caja"><table class="tabla"><tbody>'+
          despues.map(function(nombre, i){
            var e = C.equipo(nombre);
            var antesPos = antes.indexOf(nombre);
            var mov = antesPos<0 ? 0 : antesPos - i;
            return '<tr><td class="num" style="width:1%;color:var(--ink-3)">'+(i+1)+'</td>'+
              '<td>'+U.celdaEquipo(e)+'</td>'+
              '<td class="num" style="width:1%">'+
                (mov>0 ? '<span style="color:#6FD98A">▲'+mov+'</span>'
                       : mov<0 ? '<span style="color:#FF7B7B">▼'+(-mov)+'</span>'
                       : '<span style="color:var(--ink-5)">·</span>')+
              '</td></tr>';
          }).join('')+
        '</tbody></table></div>'+
      '</div>'+
    '</div>';
}
function repintarSim(pend){
  document.getElementById('ov-cuerpo').innerHTML = cuerpoSim(pend);
}
function azarSim(pend){
  /* Marcadores plausibles: la mayoría de partidos de esta liga acaban con
     pocos goles, así que se tira hacia abajo en vez de uniforme. */
  pend.forEach(function(o){
    sim.res[o.i] = {l:Math.floor(Math.pow(Math.random(),1.7)*5), v:Math.floor(Math.pow(Math.random(),1.7)*5)};
  });
  repintarSim(pend);
}
function aplicarSim(pend){
  var n = pend.length;
  U.cerrarModal();
  U.confirmar({
    titulo:'Aplicar la simulación',
    html:'Se escribirán <b>'+n+' resultados</b> en los partidos, que pasarán a FINALIZADO, y se recalculará la clasificación.<br><br>'+
      'Los goleadores <b>no</b> se rellenan: hay que meterlos a mano en el editor de eventos, porque la web los enlaza por nombre.',
    ok:'Aplicar', peligro:true
  }).then(function(si){
    if(!si){ sim = null; return; }
    var ls = lista(sim.comp);
    pend.forEach(function(o){
      var r = sim.res[o.i];
      ls[o.i].estado = 'FINALIZADO';
      ls[o.i].goles_l = r.l;
      ls[o.i].goles_v = r.v;
      if(ls[o.i].golesl!=null) ls[o.i].golesl = r.l;
      if(ls[o.i].golesv!=null) ls[o.i].golesv = r.v;
    });
    sim = null;
    trasResultado();
    U.aviso(n+' resultados aplicados. Recuerda cargar los goleadores.', 'ok', 8000);
  });
}

var A = {
  comp:      function(el){ st.comp = el.dataset.v; st.j = null; U.refrescar(); },
  simular:   function(){ abrirSimulacion(); },
  simGol:    function(el){
    if(!sim) return;
    sim.res[el.dataset.i][el.dataset.k] = Math.max(0, Number(el.value)||0);
    var pend = lista(sim.comp).map(function(p,i){ return {p:p,i:i}; })
      .filter(function(o){ return sim.res[o.i]!==undefined; });
    repintarSim(pend);
  },
  jor:       function(el){ st.j = el.value || TODAS; U.refrescar(); },
  jorMenos:  function(){ mueveJornada(-1); },
  jorMas:    function(){ mueveJornada(1); },
  nuevo:     function(){ nuevoPartido(st.comp); },
  nuevaElim: function(){ nuevaEliminatoria(st.comp); },
  vista:     function(el){ st.vista = el.dataset.v; lote = {}; U.refrescar(); },
  crearManual: function(){
    crearPartido((document.getElementById('crear-local')||{}).value,
                 (document.getElementById('crear-visitante')||{}).value);
  },

  lote:      function(el){
    lote[el.dataset.i] = el.checked;
    refrescarBarra();
  },
  lotesTodos:function(el){
    lista(st.comp).forEach(function(p,i){
      if(st.j===TODAS || p.jornada===st.j) lote[i] = el.checked;
    });
    document.querySelectorAll('[data-c="partidos:lote"]').forEach(function(c){ c.checked = el.checked; });
    refrescarBarra();
  },
  loteNada:  function(){
    lote = {};
    document.querySelectorAll('[data-c="partidos:lote"]').forEach(function(c){ c.checked = false; });
    var t = document.querySelector('[data-c="partidos:lotesTodos"]');
    if(t) t.checked = false;
    refrescarBarra();
  },
  loteEstado:function(el){
    var v = el.dataset.v, ls = lista(st.comp), sel = seleccionados();
    sel.forEach(function(i){ if(ls[i]) ls[i].estado = v; });
    lote = {};
    trasResultado();
    U.aviso(sel.length+' partidos a '+v+'.', 'ok');
  },
  loteJornada:function(){
    var j = (document.getElementById('lote-jor')||{}).value;
    if(!j) return U.aviso('Escribe el número de jornada.', 'ojo');
    var ls = lista(st.comp), sel = seleccionados();
    sel.forEach(function(i){ if(ls[i]) ls[i].jornada = String(j); });
    lote = {};
    U.cambio();
    U.aviso(sel.length+' partidos movidos a la jornada '+j+'.', 'ok');
  },
  loteNoJugado: function(){
    var ls = lista(st.comp), sel = seleccionados();
    var conGoleadores = sel.filter(function(i){
      var ev = C.parseDetalles(ls[i].detalles);
      return ev.local.length + ev.visitante.length > 0;
    });
    /* El marcador NO se toca. La victoria administrativa ya está anotada en
       los datos y puede ser para cualquiera de los dos lados: forzar un 3-0
       al local voltearía todos los partidos que ganó el visitante y movería
       la clasificación. Sólo se marcan los que están a cero, y se dice. */
    var sinMarcador = sel.filter(function(i){
      var p = ls[i];
      return (Number(C.gl(p))||0)===0 && (Number(C.gv(p))||0)===0;
    });
    U.confirmar({
      titulo:'Marcar '+sel.length+' partidos como no jugados',
      html:'Se marcan como <b>no disputados</b>. <b>El marcador se respeta tal cual está</b>: '+
        'la victoria administrativa ya está anotada y puede ser para cualquiera de los dos lados.<br><br>'+
        'Siguen contando para la clasificación igual que antes, y ésta no se mueve. Lo que cambia es que los informes '+
        'dejan de contarlos como «goles sin anotar quién los marcó», que es la cifra que sirve para saber qué falta de verdad.'+
        (sinMarcador.length
          ? '<br><br><b style="color:var(--gold)">'+sinMarcador.length+' están a 0-0</b> y se quedarán así: '+
            'ponles tú el resultado que corresponda.'
          : '')+
        (conGoleadores.length
          ? '<br><br><b style="color:var(--gold)">'+conGoleadores.length+' tienen goleadores anotados.</b> '+
            'Si no se jugaron, esos goles no existieron y se borrarán.'
          : ''),
      ok:'Marcar como no jugados', peligro:!!conGoleadores.length
    }).then(function(si){
      if(!si) return;
      sel.forEach(function(i){
        var p = ls[i];
        if(!p) return;
        p.no_jugado = true;
        p.estado = 'FINALIZADO';
        p.detalles = ' / ';
        if(p.goleadores_texto!=null) p.goleadores_texto = '';
        if(p.goleadores_local_texto!=null) p.goleadores_local_texto = '';
        if(p.goleadores_visitante_texto!=null) p.goleadores_visitante_texto = '';
      });
      lote = {};
      trasResultado();
      U.aviso(sel.length+' partidos marcados como no jugados. El marcador no se ha tocado.', 'ok', 6000);
    });
  },
  quitarNoJugado: function(el){
    var p = lista(st.comp)[Number(el.dataset.i)];
    delete p.no_jugado;
    U.cambio();
    U.aviso('Vuelve a contar como partido disputado.', 'ok');
  },
  nuevaJornada: function(){
    var js = jornadas(st.comp).map(Number).concat(0);
    st.j = String(Math.max.apply(null, js)+1);
    nuevoPartido(st.comp);
    U.aviso('Jornada '+st.j+' creada con un partido vacío.', 'ok');
  },
  borrar:    function(el){ borrarPartido(el.dataset.comp, Number(el.dataset.i)); },
  recalcular:function(){
    var n = cascada();
    U.cambio();
    U.aviso(n ? n+' valores de clasificación corregidos.' : 'La clasificación ya cuadraba con los partidos.', n?'ok':'info');
  },

  /* Lo mismo que «Recalcular goles» de la ficha de club (equipos:recalcular),
     pero para TODOS los equipos a la vez: aquí es donde se editan los goles
     de los partidos, así que es donde más falta hace verlo de golpe en vez de
     club a club. Enseña qué va a cambiar antes de tocar nada. */
  recalcularGoles:function(){
    var difs = C.diferenciasGoles(d());
    if(!difs.length) return U.aviso('Los goles de todos los jugadores ya cuadran con los partidos.', 'ok');
    U.modal({
      titulo:'Recalcular goles de jugador',
      ancho:true,
      cuerpo:'<p class="ayuda" style="margin-bottom:var(--g4)">'+difs.length+
        (difs.length===1?' ficha no coincide':' fichas no coinciden')+' con los goles de los partidos, en '+
        (new Set(difs.map(function(x){ return x.e; })).size)+' club(es). '+
        'Los goles marcados con otra camiseta cuentan igual: son del jugador.</p>'+
        '<div class="tabla-caja"><table class="tabla"><thead><tr>'+
          '<th>Jugador</th><th>Club</th><th class="num">Ficha</th><th class="num">Partidos</th><th class="num">Cambio</th>'+
        '</tr></thead><tbody>'+difs.map(function(x){
          var dif = x.ahora-x.antes;
          return '<tr><td>'+esc(x.j.nombre||'sin nombre')+'</td><td style="color:var(--ink-3)">'+esc(x.e.nombre)+'</td>'+
            '<td class="num" style="color:var(--ink-3)">'+x.antes+'</td>'+
            '<td class="num" style="font-weight:600">'+x.ahora+'</td>'+
            '<td class="num" style="color:'+(dif>0?'#6FD98A':'#FF7B7B')+'">'+(dif>0?'+':'')+dif+'</td></tr>';
        }).join('')+'</tbody></table></div>',
      pie:[
        {txt:'Cancelar', fn:U.cerrarModal},
        {txt:'Aplicar a '+(difs.length===1?'1 ficha':'las '+difs.length+' fichas'), cls:'btn-primary', fn:function(){
          difs.forEach(function(x){ x.j.goles = x.ahora; });
          U.cerrarModal(); U.cambio();
          U.aviso(difs.length+' fichas actualizadas desde los partidos.', 'ok');
        }}
      ]
    });
  },

  campo: function(el){
    var p = lista(st.comp)[Number(el.dataset.i)];
    var k = el.dataset.k;
    p[k] = el.value;
    if(k==='fase' && el.value && (p.jornada==null||p.jornada===''))
      U.aviso('Ponle jornada: sin ella, la web no muestra el partido en Resultados.', 'ojo', 8000);
    /* Cambiar de equipo cambia quién suma puntos, y marcar una fase saca el
       partido del reparto: las dos cosas rehacen la tabla. */
    if(k==='local'||k==='visitante'||k==='fase') trasResultado();
    else U.cambio();
  },
  gol: function(el){
    var p = lista(st.comp)[Number(el.dataset.i)];
    var k = el.dataset.k, v = Number(el.value)||0;
    p[k] = v;
    /* El alias sólo se toca si el partido ya lo traía; normalizar hará el
       resto al guardar. */
    var alias = k==='goles_l' ? 'golesl' : 'golesv';
    if(p[alias]!=null) p[alias] = v;
    trasResultado();
  },
  estado: function(el){
    lista(st.comp)[Number(el.dataset.i)].estado = el.value;
    trasResultado();
  },

  eventos: function(el){ abrirEditor(el.dataset.comp, Number(el.dataset.i)); },
  evAdd: function(el){
    edit.ev[el.dataset.lado].push({tipo:'gol', nombre:'', minuto:''});
    repintarEditor();
  },
  evDel: function(el){
    edit.ev[el.dataset.lado].splice(Number(el.dataset.i),1);
    repintarEditor();
  },
  ev: function(el){
    var e = edit.ev[el.dataset.lado][Number(el.dataset.i)];
    e[el.dataset.k] = el.value;
    /* Cambiar de tipo o de jugador altera el recuento de goles del cabecero,
       así que se repinta; el minuto no, para no perder el foco al teclear. */
    if(el.dataset.k!=='minuto') repintarEditor();
  },
  penOn: function(el){
    edit.ev.pen = el.checked ? {l:0, v:0} : null;
    repintarEditor();
  },
  pen: function(el){
    if(edit.ev.pen) edit.ev.pen[el.dataset.k] = Number(el.value)||0;
  }
};

function mueveJornada(paso){
  var js = jornadas(st.comp);
  if(!js.length) return;
  var i = js.indexOf(st.j);
  if(i<0) i = js.length-1;      // desde «todas», se entra por la ultima
  st.j = js[Math.min(js.length-1, Math.max(0, i+paso))];
  U.refrescar();
}

/* --------------------------------------------------------------------------
   COPA: acciones propias
   -------------------------------------------------------------------------- */
var AC = {
  fase:   function(el){ copaFase = el.value; U.refrescar(); },
  nuevo:  function(){ nuevoPartido('copa'); },
  borrar: function(el){ borrarPartido('copa', Number(el.dataset.i)); },
  campo:  function(el){
    d().partidos_copa[Number(el.dataset.i)][el.dataset.k] = el.value;
    U.cambio();
  },
  gol: function(el){
    var p = d().partidos_copa[Number(el.dataset.i)];
    var k = el.dataset.k, v = Number(el.value)||0;
    p[k] = v;
    var alias = k==='goles_l' ? 'golesl' : 'golesv';
    if(p[alias]!=null) p[alias] = v;
    /* La Copa no cuenta para la clasificación de liga, así que no hay
       cascada; pero sí puede cambiar quién pasa de ronda. */
    U.cambio();
  },
  estado: function(el){
    d().partidos_copa[Number(el.dataset.i)].estado = el.value;
    U.cambio();
  },
  grupoDe: function(el){ ponerEnGrupo(el.dataset.nombre, el.value); },

  vaciarGrupos: function(){
    U.confirmar({titulo:'Vaciar los grupos', texto:'Se deshace el reparto. Los partidos que ya tengan grupo asignado no se tocan.', ok:'Vaciar'})
      .then(function(si){ if(si){ d().config.grupos_copa = {}; U.cambio(); U.aviso('Reparto deshecho.','ok'); } });
  },

  repartirGrupos: function(){
    var D = d(), letras = C.letrasGrupo(D);
    var ya = Object.keys(D.config.grupos_copa||{}).reduce(function(a,g){ return a+(D.config.grupos_copa[g]||[]).length; }, 0);
    U.confirmar({
      titulo:'Repartir en '+letras.length+' grupos',
      html: (ya ? 'Se rehará el reparto actual de '+ya+' clubes.<br><br>' : '')+
        'Se reparten los clubes activos por serpiente según su posición en la clasificación: el 1.º al grupo A, el 2.º al B… y al llegar al final se vuelve hacia atrás. '+
        'Así no se juntan los mejores de cada división en el mismo grupo.<br><br>'+
        'Nada se escribe en los partidos hasta que pulses «Aplicar a los partidos».',
      ok:'Repartir'
    }).then(function(si){
      if(!si) return;
      /* Serpiente sobre la clasificación de las dos divisiones: es el reparto
         por siembra habitual y evita que el bombo junte a los tres primeros
         de Superliga en el mismo grupo. */
      var orden = C.clasificacion('SUPERLIGA').concat(C.clasificacion('ASCENSO'));
      var gc = {};
      letras.forEach(function(g){ gc[g] = []; });
      orden.forEach(function(e, i){
        var vuelta = Math.floor(i/letras.length);
        var pos = i % letras.length;
        var g = letras[vuelta%2 ? letras.length-1-pos : pos];
        gc[g].push(e.nombre);
      });
      D.config.grupos_copa = gc;
      U.cambio();
      U.aviso(orden.length+' clubes repartidos en '+letras.length+' grupos.', 'ok');
    });
  },

  aplicarGrupos: function(){
    var D = d(), gc = D.config.grupos_copa||{};
    var letras = Object.keys(gc).filter(function(g){ return (gc[g]||[]).length>=2; });
    if(!letras.length) return U.aviso('No hay grupos con al menos dos clubes.', 'ojo');
    var existentes = D.partidos_copa.filter(function(p){ return p.fase==='FASE DE GRUPOS'; });
    /* Todos contra todos dentro de cada grupo, una vuelta (o dos, según el
       formato). Se cuenta antes para poder decir cuántos van a salir. */
    var vueltas = (D.config.formatos.COPA||{}).ida_vuelta ? 2 : 1;
    var n = letras.reduce(function(a,g){ var k=gc[g].length; return a + k*(k-1)/2*vueltas; }, 0);
    U.confirmar({
      titulo:'Generar los partidos de la fase de grupos',
      html:'Se crearán <b>'+n+' cruces</b> ('+(vueltas===2?'ida y vuelta':'una vuelta')+') en '+letras.length+' grupos.'+
        (existentes.length ? '<br><br><b style="color:var(--gold)">Ya hay '+existentes.length+' partidos de fase de grupos</b>, y se reemplazarán. Los resultados que tengan se perderán.' : ''),
      ok:'Generar', peligro:!!existentes.length
    }).then(function(si){
      if(!si) return;
      /* Se conservan los índices de los cruces que NO son de grupos, porque
         origen_local/origen_visitante apuntan por posición: reordenar el
         array rompería el cuadro. Los de grupos se quitan y se añaden al
         final, que es donde no estorban a nadie. */
      var quitados = [];
      for(var i=D.partidos_copa.length-1;i>=0;i--){
        if(D.partidos_copa[i].fase==='FASE DE GRUPOS'){ D.partidos_copa.splice(i,1); quitados.push(i); }
      }
      quitados.forEach(function(k){ reajustarOrigenes(k); });
      letras.forEach(function(g){
        var eqs = gc[g];
        for(var v=0; v<vueltas; v++){
          for(var a=0; a<eqs.length; a++) for(var b=a+1; b<eqs.length; b++){
            D.partidos_copa.push({
              fase:'FASE DE GRUPOS', grupo:g, fecha:'', estado:'PENDIENTE',
              local: v===0?eqs[a]:eqs[b], visitante: v===0?eqs[b]:eqs[a],
              goles_l:0, goles_v:0, detalles:' / ', origen_local:null, origen_visitante:null
            });
          }
        }
      });
      copaFase = 'FASE DE GRUPOS';
      U.cambio();
      U.aviso(n+' partidos de fase de grupos generados.', 'ok');
    });
  },

  origen: function(el){
    var p = d().partidos_copa[Number(el.dataset.i)], lado = el.dataset.k;
    var k = lado==='local' ? 'origen_local' : 'origen_visitante';
    if(el.value===''){ p[k] = null; }
    else {
      p[k] = Number(el.value);
      /* Vinculado, el nombre fijo sobra: dejarlo puesto sería una segunda
         verdad que contradice al ganador de la ronda anterior. */
      p[lado] = '';
    }
    U.cambio();
  }
};

U.registrar('partidos', {
  acciones: A,
  render: function(el, param){
    if(param && param.comp && param.comp!=='copa') st.comp = param.comp;
    if(param && param.jornada) st.j = param.jornada;
    if(param && param.idx!=null){
      var p = lista(st.comp)[param.idx];
      if(p && p.jornada) st.j = p.jornada;
    }
    pintar(el);
  }
});
U.registrar('copa', {
  acciones: AC,
  render: function(el, param){
    if(param && param.fase) copaFase = param.fase;
    pintarCopa(el);
  }
});

})();
