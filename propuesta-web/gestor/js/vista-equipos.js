/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-equipos.js
   Listado de clubes, ficha y plantilla.

   La sección tiene dos estados: la lista y la ficha de un club. No es un
   modal porque una plantilla son treinta jugadores y editarlos dentro de una
   ventana flotante obliga a cerrarla para ver cualquier otra cosa.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var sel = null;                                  // id del club abierto, o null
var f = {div:'', arch:'activos', q:''};          // filtros de la lista

function d(){ return SFG.d(); }
function club(){ return sel ? C.equipoPorId(sel) : null; }

/* --------------------------------------------------------------------------
   LISTA
   -------------------------------------------------------------------------- */
function pintarLista(el){
  var lista = d().equipos.filter(function(e){
    if(f.div && e.division!==f.div) return false;
    if(f.arch==='activos' && e.archivado) return false;
    if(f.arch==='archivados' && !e.archivado) return false;
    if(f.q && C.norm(e.nombre+' '+(e.ciudad||'')+' '+(e.entrenador||'')).indexOf(C.norm(f.q))<0) return false;
    return true;
  });
  /* Se ordena por la fórmula real de la web, no por puntos a secas: así la
     lista del gestor y la clasificación pública coinciden fila a fila. */
  lista = C.orderStandings(lista);

  var calc = C.tablaCalculada();

  el.innerHTML =
    U.cabecera('Equipos', d().equipos.length+' clubes en el archivo',
      '<button class="btn btn-primary btn-sm" data-a="equipos:nuevo"><i class="ph-bold ph-plus"></i> Nuevo equipo</button>')+
    '<div class="g-filtros">'+
      '<input class="inp inp-sm" style="width:220px" type="search" placeholder="Buscar club, ciudad, entrenador…" value="'+esc(f.q)+'" data-c="equipos:filtroQ">'+
      '<select class="inp inp-sm" style="width:auto" data-c="equipos:filtroDiv">'+
        '<option value="">Todas las divisiones</option>'+
        C.DIVISIONES.map(function(x){ return '<option value="'+x+'"'+(f.div===x?' selected':'')+'>'+x+'</option>'; }).join('')+
      '</select>'+
      '<select class="inp inp-sm" style="width:auto" data-c="equipos:filtroArch">'+
        ['activos','archivados','todos'].map(function(x){
          return '<option value="'+x+'"'+(f.arch===x?' selected':'')+'>'+({activos:'Activos',archivados:'Archivados',todos:'Todos'})[x]+'</option>';
        }).join('')+
      '</select>'+
      '<span class="ayuda" style="margin-left:auto">'+lista.length+' visibles</span>'+
    '</div>'+
    (lista.length ? tablaClubes(lista, calc) : '<div class="vacio">Ningún club coincide con el filtro.</div>');
}

function tablaClubes(lista, calc){
  return '<div class="tabla-caja"><div class="tabla-scroll"><table class="tabla"><thead><tr>'+
    '<th>Club</th><th>División</th>'+
    '<th class="num">PJ</th><th class="num">G</th><th class="num">E</th><th class="num">P</th>'+
    '<th class="num">GF</th><th class="num">GC</th><th class="num">Pts</th>'+
    '<th class="num">Plantilla</th><th class="acc"></th>'+
  '</tr></thead><tbody>'+
  lista.map(function(e){
    var c = calc[e.nombre] || {};
    /* Un desajuste entre lo guardado y lo que dicen los partidos es la señal
       de que alguien editó a mano y se olvidó de recalcular. */
    var mal = C.CAMPOS_TABLA.some(function(k){ return (e[k]||0)!==(c[k]||0); });
    function td(k){
      var dif = (e[k]||0)!==(c[k]||0);
      return '<td class="num"'+(dif?' title="Los partidos dicen '+c[k]+'"':'')+'>'+(e[k]||0)+
        (dif?'<span style="color:var(--accent)"> ≠</span>':'')+'</td>';
    }
    return '<tr class="'+(mal?'ojo':'')+(e.archivado?' apagado':'')+'">'+
      '<td><button style="all:unset;cursor:pointer;display:block" data-a="equipos:ver" data-id="'+esc(e.id)+'">'+
        U.celdaEquipo(e)+'</button></td>'+
      '<td><span class="badge '+(e.division==='ASCENSO'?'badge-ascenso':'badge-superliga')+'">'+esc(e.division||'—')+'</span>'+
        (e.archivado?' <span class="pastilla">archivado</span>':'')+'</td>'+
      td('pj')+td('g')+td('e')+td('p')+td('gf')+td('gc')+td('pts')+
      '<td class="num">'+((e.jugadores||[]).length)+'</td>'+
      '<td class="acc">'+
        '<button class="btn btn-secondary btn-sm" data-a="equipos:ver" data-id="'+esc(e.id)+'">Abrir</button>'+
      '</td></tr>';
  }).join('')+'</tbody></table></div></div>';
}

/* --------------------------------------------------------------------------
   FICHA DE CLUB
   -------------------------------------------------------------------------- */
function pintarFicha(el, e){
  var calc = C.tablaCalculada()[e.nombre] || {};
  var desc = C.CAMPOS_TABLA.filter(function(k){ return (e[k]||0)!==(calc[k]||0); });

  el.innerHTML =
    '<div class="g-cab"><div>'+
      '<button class="btn btn-secondary btn-sm" data-a="equipos:volver" style="margin-bottom:.75rem"><i class="ph ph-arrow-left"></i> Equipos</button>'+
      '<h1>'+esc(e.nombre||'Sin nombre')+'</h1>'+
      '<p><span class="mono">'+esc(e.id)+'</span> · '+esc(e.division||'sin división')+' · '+((e.jugadores||[]).length)+' jugadores</p>'+
    '</div><div class="acciones">'+
      '<button class="btn btn-secondary btn-sm" data-a="equipos:archivar">'+(e.archivado?'Desarchivar':'Archivar')+'</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="equipos:borrar"><i class="ph ph-trash"></i> Eliminar</button>'+
    '</div></div>'+

    fichaDatos(e)+
    '<div class="g-hueco"></div>'+
    fichaEstadisticas(e, calc, desc)+
    '<div class="g-hueco"></div>'+
    plantilla(e);
}

function fichaDatos(e){
  var v = function(k){ return esc(e[k]==null?'':e[k]); };
  var c1 = e.color1||'#333333', c2 = e.color2||'#111111';
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Ficha del club</h3>'+
    '<div class="rejilla rejilla-2">'+
      U.campo('Nombre', '<input class="inp" value="'+v('nombre')+'" data-c="equipos:campo" data-k="nombre">',
        'Los partidos referencian al club por este nombre: al cambiarlo se actualizan solos.')+
      U.campo('Abreviatura', '<input class="inp inp-mono" maxlength="3" value="'+v('abreviatura')+'" data-c="equipos:campo" data-k="abreviatura" placeholder="'+esc(C.abbr3(e.nombre))+'">',
        'Si se deja vacía, la web la deduce del nombre.')+
      U.campo('División', '<select class="inp" data-c="equipos:campo" data-k="division">'+
        C.DIVISIONES.map(function(x){ return '<option'+(e.division===x?' selected':'')+'>'+x+'</option>'; }).join('')+'</select>')+
      U.campo('Formación', '<select class="inp" data-c="equipos:campo" data-k="formacion">'+
        ['','3-5-2','4-3-3','5-3-2','4-4-2','4-2-3-1'].map(function(x){
          return '<option value="'+x+'"'+(e.formacion===x?' selected':'')+'>'+(x||'—')+'</option>'; }).join('')+'</select>')+
      U.campo('Ciudad', '<input class="inp" value="'+v('ciudad')+'" data-c="equipos:campo" data-k="ciudad">')+
      U.campo('Estadio', '<input class="inp" value="'+v('estadio')+'" data-c="equipos:campo" data-k="estadio">')+
      U.campo('Entrenador', '<input class="inp" value="'+v('entrenador')+'" data-c="equipos:campo" data-k="entrenador">')+
      U.campo('Gerente', '<input class="inp" value="'+v('gerente')+'" data-c="equipos:campo" data-k="gerente">')+
    '</div>'+
    '<div class="g-hueco"></div>'+
    '<div class="rejilla rejilla-2">'+
      U.campo('Escudo (URL)',
        '<div class="color-par"><input class="inp" value="'+v('escudo')+'" data-c="equipos:campo" data-k="escudo" placeholder="https://…">'+
        '<span class="eq-cel" style="flex-shrink:0">'+U.escudo(e)+'</span></div>')+
      U.campo('Colores del club',
        '<div class="color-par">'+
          '<input type="color" value="'+esc(hex(c1))+'" data-c="equipos:color" data-k="color1" aria-label="Color primario">'+
          '<input type="color" value="'+esc(hex(c2))+'" data-c="equipos:color" data-k="color2" aria-label="Color secundario">'+
          '<span class="grad-prev" id="grad-prev" style="background:linear-gradient(90deg,'+esc(c1)+','+esc(c2)+')"></span>'+
        '</div>'+
        '<div class="color-par" style="margin-top:.35rem">'+
          '<input class="inp inp-sm inp-mono" value="'+v('color1')+'" data-c="equipos:campo" data-k="color1" placeholder="#000000">'+
          '<input class="inp inp-sm inp-mono" value="'+v('color2')+'" data-c="equipos:campo" data-k="color2" placeholder="#000000">'+
        '</div>',
        'La web usa el más luminoso de los dos para teñir la ficha del partido.')+
    '</div></div>';
}
/* <input type=color> sólo acepta #rrggbb; el JSON trae también formatos de
   tres cifras y algún vacío. Se traduce sólo para el selector, sin tocar el
   valor guardado. */
function hex(x){
  var s = String(x||'').trim();
  if(/^#[0-9a-f]{6}$/i.test(s)) return s;
  if(/^#[0-9a-f]{3}$/i.test(s)) return '#'+s[1]+s[1]+s[2]+s[2]+s[3]+s[3];
  return '#000000';
}

function fichaEstadisticas(e, calc, desc){
  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4)">'+
      '<h3 style="font-size:.9375rem">Clasificación</h3>'+
      (desc.length
        ? '<span class="pastilla pastilla-ojo">'+desc.length+' no cuadran</span>'+
          '<button class="btn btn-accent btn-sm" style="margin-left:auto" data-a="equipos:aplicarCalc">Usar lo que dicen los partidos</button>'
        : '<span class="pastilla pastilla-ok">cuadra con los partidos</span>')+
    '</div>'+
    '<div class="rejilla rejilla-4">'+
      C.CAMPOS_TABLA.map(function(k){
        var dif = (e[k]||0)!==(calc[k]||0);
        return U.campo(k.toUpperCase(),
          '<input class="inp inp-mono" type="number" value="'+(e[k]||0)+'" data-c="equipos:num" data-k="'+k+'"'+(dif?' style="border-color:var(--accent)"':'')+'>',
          dif ? 'los partidos dicen '+(calc[k]||0) : '');
      }).join('')+
    '</div>'+
    '<p class="ayuda" style="margin-top:var(--g4)">Estos campos son los que la web lee para pintar la tabla. El valor calculado sale de los partidos con estado FINALIZADO de Liga y Ascenso.</p>'+
  '</div>';
}

/* --------------------------------------------------------------------------
   PLANTILLA
   -------------------------------------------------------------------------- */
function ordenarPlantilla(js){
  return js.map(function(j,i){ return {j:j,i:i}; }).sort(function(a,b){
    var pa = C.POS_ORDER[a.j.posicion], pb = C.POS_ORDER[b.j.posicion];
    if(pa==null) pa=9; if(pb==null) pb=9;
    if(a.j.titular!==b.j.titular) return a.j.titular?-1:1;
    if(pa!==pb) return pa-pb;
    var da = parseInt(a.j.dorsal), db = parseInt(b.j.dorsal);
    if(isNaN(da)) da=999; if(isNaN(db)) db=999;
    return da-db || String(a.j.nombre).localeCompare(String(b.j.nombre),'es');
  });
}
function plantilla(e){
  var js = e.jugadores || [];
  var tit = js.filter(function(j){ return j.titular; }).length;
  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Plantilla</h3>'+
      '<span class="pastilla">'+js.length+' jugadores</span>'+
      '<span class="pastilla'+(tit===11?' pastilla-ok':(tit>11?' pastilla-mal':' pastilla-ojo'))+'">'+tit+' titulares</span>'+
      '<button class="btn btn-primary btn-sm" style="margin-left:auto" data-a="equipos:nuevoJugador"><i class="ph-bold ph-plus"></i> Añadir jugador</button>'+
    '</div>'+
    (js.length ? '<div class="tabla-scroll"><table class="tabla"><thead><tr>'+
      '<th class="num">#</th><th>Jugador</th><th>Pos</th><th>Afinidad</th><th>Tit.</th>'+
      '<th class="num">G</th><th class="num">A</th><th class="num">TA</th><th class="num">TR</th>'+
      '<th class="num">Carrera</th><th class="acc"></th></tr></thead><tbody>'+
      ordenarPlantilla(js).map(function(o){
        var j = o.j;
        return '<tr>'+
          '<td class="num">'+esc(j.dorsal||'')+'</td>'+
          '<td><button style="all:unset;cursor:pointer" data-a="equipos:editarJugador" data-i="'+o.i+'">'+esc(j.nombre||'Sin nombre')+'</button></td>'+
          '<td><span class="chip chip-'+String(j.posicion||'').toLowerCase()+'">'+esc(j.posicion||'—')+'</span></td>'+
          '<td>'+afinidadCel(j.afinidad)+'</td>'+
          '<td>'+(j.titular?'<i class="ph-bold ph-check" style="color:#6FD98A"></i>':'')+'</td>'+
          '<td class="num">'+(j.goles||0)+'</td><td class="num">'+(j.asistencias||0)+'</td>'+
          '<td class="num">'+(j.amarillas||0)+'</td><td class="num">'+(j.rojas||0)+'</td>'+
          /* Carrera = histórico cerrado + temporada en curso, igual que golesCarrera() en app.js. */
          '<td class="num" title="goles_totales + goles de esta temporada">'+((j.goles_totales||0)+(j.goles||0))+'</td>'+
          '<td class="acc"><button class="btn btn-secondary btn-sm" data-a="equipos:editarJugador" data-i="'+o.i+'">Editar</button></td>'+
        '</tr>';
      }).join('')+'</tbody></table></div>'
    : '<div class="vacio">Este club no tiene jugadores.</div>')+
  '</div>';
}
function afinidadCel(a){
  var k = C.afKey(a), limpia = C.afinidadLimpia(a);
  return '<span style="display:inline-flex;align-items:center;gap:.35rem">'+
    '<span style="width:8px;height:8px;border-radius:50%;background:'+C.AF_HEX[k]+'"></span>'+
    esc(C.afName(a))+
    (limpia ? '' : '<span class="pastilla pastilla-ojo" title="Guardado como '+esc(String(a))+'">≈</span>')+
  '</span>';
}

/* --------------------------------------------------------------------------
   FICHA DE JUGADOR (modal)
   -------------------------------------------------------------------------- */
function editarJugador(i){
  var e = club(), j = e.jugadores[i];
  U.modal({
    titulo: j.nombre || 'Nuevo jugador',
    ancho: true,
    cuerpo: formJugador(j),
    pie: [
      {txt:'<i class="ph ph-trash"></i> Eliminar', cls:'btn-secondary', izq:true, fn:function(){
        U.cerrarModal();
        U.confirmar({titulo:'Eliminar jugador', texto:'Se borrará «'+(j.nombre||'sin nombre')+'» de la plantilla, con su historial y sus supertécnicas.', ok:'Eliminar', peligro:true})
          .then(function(si){ if(si){ e.jugadores.splice(i,1); U.cambio(); U.aviso('Jugador eliminado.','ok'); } });
      }},
      {txt:'Hecho', cls:'btn-primary', fn:function(){ U.cerrarModal(); U.cambio(); }}
    ],
    alCerrar: function(){ U.refrescar(); }
  });
}
function formJugador(j){
  var v = function(k){ return esc(j[k]==null?'':j[k]); };
  var n = function(k){ return j[k]||0; };
  return '<div class="rejilla rejilla-2">'+
      U.campo('Nombre', '<input class="inp" value="'+v('nombre')+'" data-c="equipos:jCampo" data-k="nombre">',
        'Los goles de los partidos se enlazan por este nombre exacto.')+
      U.campo('Dorsal', '<input class="inp inp-mono" value="'+v('dorsal')+'" data-c="equipos:jCampo" data-k="dorsal">')+
      U.campo('Posición', '<select class="inp" data-c="equipos:jCampo" data-k="posicion">'+
        [''].concat(C.POS).map(function(x){ return '<option value="'+x+'"'+(j.posicion===x?' selected':'')+'>'+(x||'—')+'</option>'; }).join('')+'</select>')+
      U.campo('Afinidad', '<select class="inp" data-c="equipos:jCampo" data-k="afinidad">'+
        [''].concat(C.AFINIDADES).map(function(x){ return '<option value="'+x+'"'+(j.afinidad===x?' selected':'')+'>'+(x||'—')+'</option>'; }).join('')+
        (C.afinidadLimpia(j.afinidad)||!j.afinidad ? '' : '<option value="'+esc(j.afinidad)+'" selected>'+esc(String(j.afinidad).slice(0,40))+' (sin normalizar)</option>')+
        '</select>', C.afinidadLimpia(j.afinidad)||!j.afinidad ? '' : 'La web lo interpreta como '+C.afName(j.afinidad)+'.')+
    '</div>'+
    '<div class="g-hueco"></div>'+
    '<label class="sw"><input type="checkbox"'+(j.titular?' checked':'')+' data-c="equipos:jBool" data-k="titular"><span class="pista"></span> Titular</label>'+
    '<div class="g-hueco"></div>'+
    U.campo('Foto (URL)', '<div class="color-par"><input class="inp" value="'+v('foto')+'" data-c="equipos:jCampo" data-k="foto" placeholder="https://…">'+
      (/^https?:/.test(j.foto||'') ? '<img src="'+esc(j.foto)+'" alt="" style="width:38px;height:38px;border-radius:var(--r-sm);object-fit:cover;flex-shrink:0" referrerpolicy="no-referrer">' : '')+'</div>')+

    '<div class="g-hueco"></div>'+
    '<h4 style="font-size:.8125rem;color:var(--ink-2);margin-bottom:var(--g3)">Temporada en curso</h4>'+
    '<div class="rejilla rejilla-4">'+
      [['goles','Goles'],['asistencias','Asistencias'],['amarillas','Amarillas'],['rojas','Rojas']].map(function(p){
        return U.campo(p[1], '<input class="inp inp-mono" type="number" min="0" value="'+n(p[0])+'" data-c="equipos:jNum" data-k="'+p[0]+'">');
      }).join('')+
    '</div>'+
    '<div class="g-hueco"></div>'+
    '<h4 style="font-size:.8125rem;color:var(--ink-2);margin-bottom:var(--g3)">Histórico (temporadas ya cerradas)</h4>'+
    '<div class="rejilla rejilla-4">'+
      [['goles_totales','Goles'],['asistencias_totales','Asist.'],['amarillas_totales','Amarillas'],['rojas_totales','Rojas'],['pj','Partidos']].map(function(p){
        return U.campo(p[1], '<input class="inp inp-mono" type="number" min="0" value="'+n(p[0])+'" data-c="equipos:jNum" data-k="'+p[0]+'">');
      }).join('')+
    '</div>'+
    '<p class="ayuda" style="margin-top:.5rem">La web suma histórico + temporada para la cifra de carrera: '+
      ((j.goles_totales||0)+(j.goles||0))+' goles.</p>'+

    '<div class="g-hueco"></div>'+
    '<h4 style="font-size:.8125rem;color:var(--ink-2);margin-bottom:var(--g3)">Historial de clubes '+
      '<button class="btn btn-secondary btn-sm" style="margin-left:.5rem" data-a="equipos:jHistAdd">Añadir etapa</button></h4>'+
    listaHistorial(j)+

    '<div class="g-hueco"></div>'+
    '<h4 style="font-size:.8125rem;color:var(--ink-2);margin-bottom:var(--g3)">Supertécnicas '+
      '<button class="btn btn-secondary btn-sm" style="margin-left:.5rem" data-a="equipos:jStAdd">Añadir</button></h4>'+
    listaSupertecnicas(j);
}

function listaHistorial(j){
  var h = j.historial || [];
  if(!h.length) return '<div class="vacio" style="padding:1.25rem">Sin etapas registradas.</div>';
  return '<div class="tabla-caja"><div class="tabla-scroll"><table class="tabla"><thead><tr>'+
    '<th>Club</th><th>División</th><th>Temporadas</th><th class="num">PJ</th><th class="num">G</th><th class="num">A</th><th>Abierta</th><th class="acc"></th>'+
    '</tr></thead><tbody>'+h.map(function(x,k){
      var e = C.equipoPorId(x.equipo_id);
      return '<tr>'+
        '<td>'+SFG.ui.selectEquipos(e?e.nombre:x.equipo, 'class="inp inp-sm" data-c="equipos:jHist" data-i="'+k+'" data-k="club"')+'</td>'+
        '<td><select class="inp inp-sm" data-c="equipos:jHist" data-i="'+k+'" data-k="division">'+
          C.DIVISIONES.map(function(dv){ return '<option'+(x.division===dv?' selected':'')+'>'+dv+'</option>'; }).join('')+'</select></td>'+
        '<td style="display:flex;gap:.25rem"><input class="inp inp-sm" style="width:88px" value="'+esc(x.temporada_inicio||'')+'" data-c="equipos:jHist" data-i="'+k+'" data-k="temporada_inicio" placeholder="Temporada 1">'+
          '<input class="inp inp-sm" style="width:88px" value="'+esc(x.temporada_fin||'')+'" data-c="equipos:jHist" data-i="'+k+'" data-k="temporada_fin" placeholder="Temporada 2"></td>'+
        ['pj','goles','asistencias'].map(function(kk){
          return '<td class="num"><input class="inp inp-sm inp-num" type="number" min="0" value="'+(x[kk]||0)+'" data-c="equipos:jHist" data-i="'+k+'" data-k="'+kk+'"></td>';
        }).join('')+
        '<td><label class="sw"><input type="checkbox"'+(x.abierto?' checked':'')+' data-c="equipos:jHistBool" data-i="'+k+'" data-k="abierto"><span class="pista"></span></label></td>'+
        '<td class="acc"><button class="btn btn-secondary btn-sm" data-a="equipos:jHistDel" data-i="'+k+'">×</button></td>'+
      '</tr>';
    }).join('')+'</tbody></table></div></div>';
}

function listaSupertecnicas(j){
  var st = j.supertecnicas || [];
  if(!st.length) return '<div class="vacio" style="padding:1.25rem">Sin supertécnicas.</div>';
  return st.map(function(x,k){
    return '<div class="card" style="padding:var(--g4);margin-bottom:var(--g2)">'+
      '<div class="rejilla rejilla-4" style="margin-bottom:var(--g3)">'+
        U.campo('Nombre','<input class="inp inp-sm" value="'+esc(x.nombre||'')+'" data-c="equipos:jSt" data-i="'+k+'" data-k="nombre">')+
        U.campo('Tipo','<select class="inp inp-sm" data-c="equipos:jSt" data-i="'+k+'" data-k="tipo">'+
          ['','tiro','regate','bloqueo','parada'].map(function(t){ return '<option value="'+t+'"'+(x.tipo===t?' selected':'')+'>'+(t||'—')+'</option>'; }).join('')+'</select>')+
        U.campo('Afinidad','<select class="inp inp-sm" data-c="equipos:jSt" data-i="'+k+'" data-k="afinidad">'+
          ['','neutro','fuego','montaña','bosque','aire'].map(function(t){ return '<option value="'+t+'"'+(x.afinidad===t?' selected':'')+'>'+(t||'—')+'</option>'; }).join('')+'</select>')+
        U.campo('Especial','<input class="inp inp-sm inp-mono" value="'+esc(x.especial||'')+'" data-c="equipos:jSt" data-i="'+k+'" data-k="especial" placeholder="miximax, totem…">')+
      '</div>'+
      U.campo('Descripción','<textarea class="inp" data-c="equipos:jSt" data-i="'+k+'" data-k="descripcion" style="min-height:64px">'+esc(x.descripcion||'')+'</textarea>')+
      '<div style="text-align:right;margin-top:var(--g2)"><button class="btn btn-secondary btn-sm" data-a="equipos:jStDel" data-i="'+k+'">Eliminar</button></div>'+
    '</div>';
  }).join('');
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
var jugadorAbierto = null;     // índice del jugador que edita el modal

function repintarModal(){
  var e = club();
  document.getElementById('ov-cuerpo').innerHTML = formJugador(e.jugadores[jugadorAbierto]);
}

var A = {
  /* --- lista --- */
  filtroQ:    function(el){ f.q = el.value; U.refrescar(); },
  filtroDiv:  function(el){ f.div = el.value; U.refrescar(); },
  filtroArch: function(el){ f.arch = el.value; U.refrescar(); },
  ver:        function(el){ sel = el.dataset.id; U.refrescar(); },
  volver:     function(){ sel = null; U.refrescar(); },

  nuevo: function(){
    /* Valores por defecto sensatos: un club nuevo entra a Superliga, con la
       formación más usada de la liga y el marcador a cero. */
    var e = {
      id:'eq_'+Date.now(), nombre:'Club nuevo', escudo:'', division:'SUPERLIGA',
      ciudad:'', estadio:'', entrenador:'', gerente:'', formacion:'4-3-3',
      abreviatura:'', color1:'#FF5100', color2:'#111111',
      pj:0, g:0, e:0, p:0, gf:0, gc:0, pts:0, jugadores:[]
    };
    SFG.d().equipos.push(e);
    sel = e.id;
    U.cambio();
    U.aviso('Club creado. Ponle nombre antes de guardar.', 'ok');
  },

  archivar: function(){
    var e = club();
    e.archivado = !e.archivado;
    U.cambio();
    U.aviso(e.archivado ? 'Archivado: deja de aparecer en la clasificación, pero sus partidos siguen contando.' : 'Desarchivado.', 'ok', 6000);
  },

  borrar: function(){
    var e = club();
    var usados = ['partidos_liga','partidos_ascenso','partidos_copa'].reduce(function(a,k){
      return a + SFG.d()[k].filter(function(p){ return p.local===e.nombre||p.visitante===e.nombre; }).length;
    }, 0);
    var citas = 0;
    SFG.d().equipos.forEach(function(x){ (x.jugadores||[]).forEach(function(j){
      (j.historial||[]).forEach(function(h){ if(h.equipo_id===e.id) citas++; }); }); });
    U.confirmar({
      titulo:'Eliminar «'+(e.nombre||'sin nombre')+'»',
      html:'Se borrará el club con sus '+((e.jugadores||[]).length)+' jugadores.<br><br>'+
        (usados ? '<b style="color:var(--gold)">Aparece en '+usados+' partidos</b>, que quedarán apuntando a un equipo inexistente y bloquearán el guardado.<br>' : '')+
        (citas ? '<b style="color:var(--gold)">'+citas+' entradas de historial</b> de otros jugadores lo citan y también quedarán rotas.<br>' : '')+
        (usados||citas ? '<br>Archivarlo en vez de borrarlo conserva todo eso.' : 'No lo referencia nada más.'),
      ok:'Eliminar', peligro:true
    }).then(function(si){
      if(!si) return;
      var d = SFG.d();
      d.equipos.splice(d.equipos.indexOf(e), 1);
      sel = null; U.cambio();
      U.aviso('Club eliminado.', 'ok');
    });
  },

  aplicarCalc: function(){
    var e = club(), calc = C.tablaCalculada()[e.nombre] || {};
    C.CAMPOS_TABLA.forEach(function(k){ e[k] = calc[k]||0; });
    U.cambio();
    U.aviso('Clasificación de «'+e.nombre+'» recalculada desde los partidos.', 'ok');
  },

  /* --- ficha --- */
  campo: function(el){
    var e = club(), k = el.dataset.k, val = el.value;
    if(k==='nombre'){
      var antes = e.nombre;
      if(val && val!==antes) renombrar(antes, val);
    }
    e[k] = val;
    /* El degradado se actualiza en vivo sin repintar la sección entera: si
       repintáramos, el campo perdería el foco a media escritura. */
    if(k==='color1'||k==='color2') refrescarGradiente(e);
    U.cambio(k==='color1'||k==='color2');
  },
  color: function(el){
    var e = club();
    e[el.dataset.k] = el.value;
    refrescarGradiente(e);
    var txt = document.querySelector('[data-c="equipos:campo"][data-k="'+el.dataset.k+'"]');
    if(txt) txt.value = el.value;
    U.cambio(true);
  },
  num: function(el){
    club()[el.dataset.k] = Number(el.value)||0;
    U.cambio();
  },

  /* --- plantilla --- */
  nuevoJugador: function(){
    var e = club();
    if(!e.jugadores) e.jugadores = [];
    e.jugadores.push({nombre:'', dorsal:'', posicion:'MED', titular:false,
      goles:0, asistencias:0, amarillas:0, rojas:0, foto:'', afinidad:'Neutro'});
    jugadorAbierto = e.jugadores.length-1;
    editarJugador(jugadorAbierto);
  },
  editarJugador: function(el){
    jugadorAbierto = Number(el.dataset.i);
    editarJugador(jugadorAbierto);
  },
  jCampo: function(el){
    var j = club().jugadores[jugadorAbierto];
    if(el.dataset.k==='nombre'){
      /* Renombrar a un jugador desconecta sus goles: los eventos de partido
         guardan el nombre en texto. Se avisa, no se toca nada por detrás. */
      var goles = contarEventos(j.nombre);
      if(j.nombre && goles && el.value!==j.nombre)
        U.aviso('«'+j.nombre+'» aparece en '+goles+' eventos de partido con el nombre antiguo. Actualízalos o los goles dejarán de enlazar.', 'ojo', 11000);
    }
    j[el.dataset.k] = el.value;
    SFG.io.marcarSucio();
  },
  jNum: function(el){ club().jugadores[jugadorAbierto][el.dataset.k] = Number(el.value)||0; SFG.io.marcarSucio(); },
  jBool: function(el){ club().jugadores[jugadorAbierto][el.dataset.k] = el.checked; SFG.io.marcarSucio(); },

  jHistAdd: function(){
    var j = club().jugadores[jugadorAbierto], e = club();
    if(!j.historial) j.historial = [];
    j.historial.push({equipo:e.nombre, equipo_id:e.id, division:e.division,
      temporada:'', temporada_inicio:'', temporada_fin:'', fecha:new Date().toLocaleDateString('es-ES'),
      goles:0, asistencias:0, amarillas:0, rojas:0, pj:0, abierto:false});
    SFG.io.marcarSucio(); repintarModal();
  },
  jHist: function(el){
    var h = club().jugadores[jugadorAbierto].historial[Number(el.dataset.i)];
    var k = el.dataset.k;
    if(k==='club'){
      /* Se guardan los dos: equipo_id para enlazar con el club de hoy y
         `equipo` con el nombre de entonces, que la web muestra como contexto
         cuando un club se ha renombrado. */
      var e = C.equipo(el.value);
      h.equipo_id = e ? e.id : '';
      h.equipo = el.value;
      if(e) h.division = e.division;
    }
    else if(['pj','goles','asistencias','amarillas','rojas'].indexOf(k)>=0) h[k] = Number(el.value)||0;
    else h[k] = el.value;
    if(k==='temporada_inicio'||k==='temporada_fin') h.temporada = h.temporada_inicio||h.temporada_fin||'';
    SFG.io.marcarSucio();
  },
  jHistBool: function(el){
    club().jugadores[jugadorAbierto].historial[Number(el.dataset.i)][el.dataset.k] = el.checked;
    SFG.io.marcarSucio();
  },
  jHistDel: function(el){
    club().jugadores[jugadorAbierto].historial.splice(Number(el.dataset.i),1);
    SFG.io.marcarSucio(); repintarModal();
  },

  jStAdd: function(){
    var j = club().jugadores[jugadorAbierto];
    if(!j.supertecnicas) j.supertecnicas = [];
    j.supertecnicas.push({nombre:'', descripcion:'', afinidad:'', tipo:'', especial:''});
    SFG.io.marcarSucio(); repintarModal();
  },
  jSt: function(el){
    club().jugadores[jugadorAbierto].supertecnicas[Number(el.dataset.i)][el.dataset.k] = el.value;
    SFG.io.marcarSucio();
  },
  jStDel: function(el){
    club().jugadores[jugadorAbierto].supertecnicas.splice(Number(el.dataset.i),1);
    SFG.io.marcarSucio(); repintarModal();
  }
};

function refrescarGradiente(e){
  var g = document.getElementById('grad-prev');
  if(g) g.style.background = 'linear-gradient(90deg,'+(e.color1||'#333')+','+(e.color2||'#111')+')';
}
/* Al renombrar un club hay que arrastrar el nombre por todos los partidos:
   la web los referencia por nombre, no por id, y dejarlos atrás romperia el
   calendario entero. */
function renombrar(antes, ahora){
  if(!antes) return;
  var d = SFG.d(), n = 0;
  ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(k){
    d[k].forEach(function(p){
      if(p.local===antes){ p.local = ahora; n++; }
      if(p.visitante===antes){ p.visitante = ahora; n++; }
    });
  });
  if(n) U.aviso('Nombre actualizado en '+n+' referencias de partidos.', 'ok');
}
function contarEventos(nombre){
  if(!nombre) return 0;
  var d = SFG.d(), n = 0;
  ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(k){
    d[k].forEach(function(p){
      var ev = C.parseDetalles(p.detalles);
      ev.local.concat(ev.visitante).forEach(function(x){ if(x.nombre===nombre) n++; });
    });
  });
  return n;
}

U.registrar('equipos', {
  acciones: A,
  render: function(el, param){
    if(param && param.id) sel = param.id;
    var e = club();
    if(sel && !e) sel = null;
    if(e) pintarFicha(el, e); else pintarLista(el);
  }
});

})();
