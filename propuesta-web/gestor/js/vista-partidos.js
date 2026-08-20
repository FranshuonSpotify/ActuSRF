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

var st = {comp:'liga', j:null};        // competición y jornada visibles
var copaFase = '';                     // filtro de fase en el cuadro
var edit = null;                       // {comp, idx, ev} mientras el editor está abierto

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
  if(st.j==null || js.indexOf(st.j)<0) st.j = js.length ? js[js.length-1] : null;
  var todos = lista(st.comp);
  var vis = todos.map(function(p,i){ return {p:p,i:i}; })
    .filter(function(o){ return st.j==null || o.p.jornada===st.j; });

  var pend = vis.filter(function(o){ return !C.isFin(o.p); }).length;

  el.innerHTML =
    U.cabecera('Partidos', 'Calendario y resultados de Liga y Ascenso',
      '<button class="btn btn-secondary btn-sm" data-a="partidos:recalcular"><i class="ph ph-calculator"></i> Recalcular clasificación</button>'+
      '<button class="btn btn-primary btn-sm" data-a="partidos:nuevo"><i class="ph-bold ph-plus"></i> Añadir partido</button>')+

    '<div class="g-filtros">'+
      '<div style="display:flex;gap:.25rem">'+
        [['liga','Superliga'],['ascenso','Ascenso']].map(function(c){
          return '<button class="btn btn-sm '+(st.comp===c[0]?'btn-primary':'btn-secondary')+'" data-a="partidos:comp" data-v="'+c[0]+'">'+c[1]+'</button>';
        }).join('')+
      '</div>'+
      (js.length ? '<span style="display:flex;align-items:center;gap:.25rem;margin-left:var(--g3)">'+
        '<button class="btn btn-secondary btn-sm" data-a="partidos:jorMenos" aria-label="Jornada anterior"><i class="ph ph-caret-left"></i></button>'+
        '<select class="inp inp-sm" style="width:auto" data-c="partidos:jor">'+
          '<option value="">Todas las jornadas</option>'+
          js.map(function(j){ return '<option value="'+esc(j)+'"'+(st.j===j?' selected':'')+'>Jornada '+esc(j)+'</option>'; }).join('')+
        '</select>'+
        '<button class="btn btn-secondary btn-sm" data-a="partidos:jorMas" aria-label="Jornada siguiente"><i class="ph ph-caret-right"></i></button>'+
      '</span>' : '')+
      '<span class="ayuda" style="margin-left:auto">'+vis.length+' partidos'+(pend?' · '+pend+' sin resultado':'')+'</span>'+
    '</div>'+

    (vis.length ? tablaPartidos(vis, st.comp) : '<div class="vacio">No hay partidos en esta vista.</div>');
}

function tablaPartidos(vis, comp){
  return '<div class="tabla-caja"><div class="tabla-scroll"><table class="tabla"><thead><tr>'+
    '<th class="num">J</th><th>Fecha</th><th>Local</th><th class="num">Goles</th><th></th><th class="num">Goles</th><th>Visitante</th>'+
    '<th>Estado</th><th>Eventos</th><th class="acc"></th></tr></thead><tbody>'+
    vis.map(function(o){ return filaPartido(o.p, o.i, comp); }).join('')+
  '</tbody></table></div></div>';
}

function filaPartido(p, i, comp){
  var ev = C.parseDetalles(p.detalles);
  var goles = ev.local.filter(esGol).length + ev.visitante.filter(esGol).length;
  var marcados = (Number(C.gl(p))||0) + (Number(C.gv(p))||0);
  /* Marcador y goleadores tienen que decir lo mismo: si no, la ficha del
     partido en la web enseña un 3-1 con dos goleadores. */
  var descuadre = C.isFin(p) && goles!==marcados;
  return '<tr'+(descuadre?' class="ojo"':'')+'>'+
    '<td class="num"><input class="inp inp-sm inp-num" value="'+esc(p.jornada||'')+'" data-c="partidos:campo" data-i="'+i+'" data-k="jornada"></td>'+
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
      (descuadre?' <span class="pastilla pastilla-ojo" title="El marcador dice '+marcados+' goles y hay '+goles+' goleadores">'+goles+'/'+marcados+'</span>':'')+
    '</td>'+
    '<td class="acc"><button class="btn btn-secondary btn-sm" data-a="partidos:borrar" data-comp="'+comp+'" data-i="'+i+'">×</button></td>'+
  '</tr>';
}
function esGol(e){ return e.tipo==='gol'; }

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
    cuadroPrevio(ms, fases);
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

/* Vista previa del cuadro tal y como lo pintará la web, resolviendo la
   cascada. Sirve para comprobar de un vistazo que las vinculaciones tienen
   sentido antes de guardar. */
function cuadroPrevio(ms, fases){
  if(!fases.length) return '';
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Cómo lo verá la web</h3>'+
    '<div style="display:flex;gap:var(--g5);overflow-x:auto;padding-bottom:var(--g2)">'+
    fases.filter(function(f){ return f!=='FASE DE GRUPOS'; }).map(function(f){
      var cruces = ms.map(function(p,i){ return {p:p,i:i}; }).filter(function(o){ return o.p.fase===f; });
      return '<div style="min-width:200px">'+
        '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-4);margin-bottom:var(--g2)">'+esc(f)+'</div>'+
        cruces.map(function(o){
          var L = C.resolveSide(o.p,'local'), V = C.resolveSide(o.p,'visitante');
          var w = C.winnerOf(o.p), fin = C.isFin(o.p);
          function fila(r, gol, nombre){
            var gana = fin && w===nombre;
            return '<div style="display:flex;gap:.4rem;align-items:center;padding:.35rem .5rem;font-size:.75rem;'+
              (r.pend?'color:var(--ink-4)':(fin&&!gana?'color:var(--ink-4)':'color:var(--ink)'))+
              (gana?';font-weight:600':'')+'">'+
              '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(r.n||'—')+'</span>'+
              (fin?'<span class="mono">'+gol+'</span>':'')+'</div>';
          }
          return '<div class="card" style="margin-bottom:var(--g2);padding:.2rem">'+
            fila(L, C.gl(o.p), o.p.local)+fila(V, C.gv(o.p), o.p.visitante)+'</div>';
        }).join('')+'</div>';
    }).join('')+'</div></div>';
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
    '<select class="inp inp-sm" style="width:96px" data-c="partidos:ev" data-lado="'+lado+'" data-i="'+k+'" data-k="tipo">'+
      C.TIPOS_EVENTO.map(function(t){ return '<option value="'+t+'"'+(e.tipo===t?' selected':'')+'>'+C.TIPO_LABEL[t]+'</option>'; }).join('')+
    '</select>'+
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

var A = {
  comp:      function(el){ st.comp = el.dataset.v; st.j = null; U.refrescar(); },
  jor:       function(el){ st.j = el.value || null; U.refrescar(); },
  jorMenos:  function(){ mueveJornada(-1); },
  jorMas:    function(){ mueveJornada(1); },
  nuevo:     function(){ nuevoPartido(st.comp); },
  borrar:    function(el){ borrarPartido(el.dataset.comp, Number(el.dataset.i)); },
  recalcular:function(){
    var n = cascada();
    U.cambio();
    U.aviso(n ? n+' valores de clasificación corregidos.' : 'La clasificación ya cuadraba con los partidos.', n?'ok':'info');
  },

  campo: function(el){
    lista(st.comp)[Number(el.dataset.i)][el.dataset.k] = el.value;
    /* Cambiar de equipo cambia quién suma puntos: hay que rehacer la tabla. */
    if(el.dataset.k==='local'||el.dataset.k==='visitante') trasResultado();
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
  if(i<0) i = js.length-1;
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
