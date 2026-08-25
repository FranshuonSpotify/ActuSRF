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
      '<td><button class="cel-btn" data-a="equipos:ver" data-id="'+esc(e.id)+'">'+
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
    alineacion(e)+
    '<div class="g-hueco"></div>'+
    fichaEstadisticas(e, calc, desc)+
    '<div class="g-hueco"></div>'+
    plantilla(e);

  montarAlineacion();
}

/* --------------------------------------------------------------------------
   ALINEACIÓN
   Arrastrar entre franjas cambia la posición; arrastrar al banquillo quita el
   titular. Son los dos únicos movimientos que corresponden a un dato real.

   El orden HORIZONTAL no se puede arrastrar, y no es una limitación que se
   pueda tapar: la web coloca a cada jugador dentro de su línea ordenando por
   DORSAL (sortSquad() de app.js), no por su orden en el array. Reordenar
   arrastrando no cambiaría nada en la web, así que en vez de fingir que sí,
   se dice y se ofrece el botón que sí lo consigue: renumerar la línea.
   -------------------------------------------------------------------------- */
var LINEAS = ['DEL','MED','DEF','POR'];
function ordenWeb(js){
  return js.slice().sort(function(a,b){
    var da=parseInt(a.dorsal), db=parseInt(b.dorsal);
    if(isNaN(da)) da=999; if(isNaN(db)) db=999;
    return da-db || String(a.nombre).localeCompare(String(b.nombre),'es');
  });
}
function alineacion(e){
  var js = e.jugadores || [];
  var tit = js.filter(function(j){ return j.titular; });
  var sup = js.filter(function(j){ return !j.titular; });
  var porLinea = {};
  LINEAS.forEach(function(p){ porLinea[p] = ordenWeb(tit.filter(function(j){ return j.posicion===p; })); });
  var otros = tit.filter(function(j){ return LINEAS.indexOf(j.posicion)<0; });

  /* Formación real frente a la declarada: DEF-MED-DEL, sin contar al portero,
     que es el orden en que se escriben las formaciones. */
  var real = [porLinea.DEF.length, porLinea.MED.length, porLinea.DEL.length].join('-');
  var descuadre = e.formacion && e.formacion!==real;

  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Alineación</h3>'+
      '<span class="pastilla'+(tit.length===11?' pastilla-ok':' pastilla-mal')+'">'+tit.length+' titulares</span>'+
      '<span class="pastilla'+(descuadre?' pastilla-ojo':'')+'" title="Defensas-Medios-Delanteros">'+real+'</span>'+
      (descuadre ? '<span class="ayuda">declarada <b>'+esc(e.formacion)+'</b> '+
        '<button class="ir" data-a="equipos:usarFormacionReal">usar '+real+'</button></span>' : '')+
    '</div>'+

    '<div class="alin">'+
      '<div class="alin-campo"><div class="pitch">'+
        '<div class="pitch-lines"></div>'+
        '<span class="pitch-box pb-top"></span><span class="pitch-box pb-top-s"></span>'+
        '<span class="pitch-box pb-bot"></span><span class="pitch-box pb-bot-s"></span>'+
        '<div class="pitch-circle"></div>'+
        LINEAS.map(function(p){
          return '<div class="alin-linea" data-pos="'+p+'">'+
            '<span class="alin-et">'+p+' · '+porLinea[p].length+'</span>'+
            porLinea[p].map(ficha).join('')+
          '</div>';
        }).join('')+
      '</div>'+
      (otros.length ? '<p class="mal" style="margin-top:var(--g3);font-size:.75rem">'+otros.length+
        ' titulares sin posición reconocida no se pintan en el campo.</p>' : '')+
      '</div>'+

      '<div>'+
        '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">'+
          'BANQUILLO · '+sup.length+'</div>'+
        '<div class="dnd-col alin-banq" data-pos="BANQ">'+
          (sup.length ? ordenWeb(sup).map(fichaBanco).join('') : '<div class="vacio">Nadie en el banquillo.</div>')+
        '</div>'+
        '<p class="ayuda" style="margin-top:var(--g3)">Arrastra entre líneas para cambiar la posición, o al banquillo para quitar el titular. '+
          'Sin ratón: enfoca a un jugador y usa ← → para moverlo de línea.</p>'+
        '<div style="margin-top:var(--g4);padding-top:var(--g4);border-top:1px solid var(--line)">'+
          '<p class="ayuda" style="margin-bottom:var(--g3)">Dentro de cada línea, la web coloca a los jugadores <b>por dorsal</b>, de menor a mayor. '+
            'Arrastrar de lado no cambiaría nada; para cambiar el orden hay que renumerar.</p>'+
          '<div style="display:flex;gap:.35rem;flex-wrap:wrap">'+
            LINEAS.filter(function(p){ return porLinea[p].length>1; }).map(function(p){
              return '<button class="btn btn-secondary btn-sm" data-a="equipos:renumerar" data-pos="'+p+'">Renumerar '+p+'</button>';
            }).join('')+
          '</div></div>'+
      '</div>'+
    '</div></div>';
}
function ficha(j){
  var i = club().jugadores.indexOf(j);
  return '<div class="alin-j dnd-item p-'+String(j.posicion||'').toLowerCase()+'" data-i="'+i+'" role="button" tabindex="0" '+
      'aria-label="'+esc((j.nombre||'sin nombre')+', '+(j.posicion||'')+', dorsal '+(j.dorsal||'sin dorsal'))+'">'+
    '<span class="tok">'+(/^https?:/.test(j.foto||'')
      ? '<img src="'+esc(j.foto)+'" alt="" loading="lazy" referrerpolicy="no-referrer">'
      : esc(((j.nombre||'?').trim()[0]||'?').toUpperCase()))+'</span>'+
    '<span class="nm"><span class="dor">'+esc(j.dorsal||'')+'</span> '+esc(apellido(j.nombre))+'</span>'+
  '</div>';
}
function fichaBanco(j){
  var i = club().jugadores.indexOf(j);
  return '<div class="dnd-ficha alin-j-b" data-i="'+i+'" role="button" tabindex="0" '+
      'aria-label="'+esc((j.nombre||'sin nombre')+', suplente')+'">'+
    '<i class="ph ph-dots-six-vertical dnd-asa" aria-hidden="true"></i>'+
    '<span class="mono" style="color:var(--ink-4);min-width:18px">'+esc(j.dorsal||'')+'</span>'+
    '<span class="chip chip-'+String(j.posicion||'').toLowerCase()+'">'+esc(j.posicion||'—')+'</span>'+
    '<span class="nm">'+esc(j.nombre||'Sin nombre')+'</span>'+
  '</div>';
}
function apellido(n){
  var w = String(n||'').trim().split(/\s+/);
  return w.length>1 ? w[w.length-1] : (w[0]||'');
}
function montarAlineacion(){
  var cols = document.querySelectorAll('.alin-linea, .alin-banq');
  if(!cols.length) return;
  SFG.dnd.sortable({
    grupo:'alineacion', item:'.alin-j, .alin-j-b',
    contenedores:Array.prototype.slice.call(cols),
    alSoltar:function(dd){
      var j = club().jugadores[Number(dd.item.dataset.i)];
      var pos = dd.hasta.dataset.pos;
      if(pos==='BANQ'){
        if(!j.titular) return;
        j.titular = false;
        U.aviso(j.nombre+' pasa al banquillo.', 'ok');
      } else {
        var eraSuplente = !j.titular, cambioPos = j.posicion!==pos;
        if(!eraSuplente && !cambioPos) return;   // misma línea: sin efecto real
        j.titular = true;
        j.posicion = pos;
        U.aviso(j.nombre+(eraSuplente?' entra al once como ':' pasa a ')+pos+'.', 'ok');
      }
      U.cambio();
    }
  });
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
      U.campoImagen('Escudo', e.escudo||'', 'equipos:campoEscudo')+
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
      (function(){
        /* Cuántas fichas de ESTE club no cuadran con los partidos. Se enseña
           siempre, cuadre o no: saber que cuadra también es información. */
        var difs = C.diferenciasGoles(d()).filter(function(x){ return x.e===e; });
        return '<span class="pastilla'+(difs.length?' pastilla-ojo':' pastilla-ok')+'" '+
          'title="Goles de la ficha frente a los goles que le dan los partidos">'+
          (difs.length ? difs.length+' sin cuadrar' : 'goles al día')+'</span>';
      })()+
      '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="equipos:recalcular">'+
        '<i class="ph ph-calculator"></i> Recalcular goles</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="equipos:importar"><i class="ph ph-upload-simple"></i> Importar CSV</button>'+
      '<button class="btn btn-primary btn-sm" data-a="equipos:nuevoJugador"><i class="ph-bold ph-plus"></i> Añadir jugador</button>'+
    '</div>'+
    (js.length ? '<div class="tabla-scroll"><table class="tabla"><thead><tr>'+
      '<th class="num">#</th><th>Jugador</th><th>Pos</th><th>Afinidad</th><th>Tit.</th>'+
      '<th class="num">Goles</th><th class="num">Carrera</th><th class="acc"></th></tr></thead><tbody>'+
      ordenarPlantilla(js).map(function(o){
        var j = o.j;
        return '<tr>'+
          '<td class="num">'+esc(j.dorsal||'')+'</td>'+
          '<td><button class="cel-btn" data-a="equipos:editarJugador" data-i="'+o.i+'">'+esc(j.nombre||'Sin nombre')+'</button></td>'+
          '<td><span class="chip chip-'+String(j.posicion||'').toLowerCase()+'">'+esc(j.posicion||'—')+'</span></td>'+
          '<td>'+afinidadCel(j.afinidad)+'</td>'+
          '<td>'+(j.titular?'<i class="ph-bold ph-check" style="color:#6FD98A"></i>':'')+'</td>'+
          '<td class="num">'+(j.goles||0)+'</td>'+
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
    U.campoImagen('Foto', j.foto||'', 'equipos:jFoto')+

    '<div class="g-hueco"></div>'+
    /* Sólo goles: las asistencias y las tarjetas no se registran nunca en
       esta liga ni se muestran en ninguna pantalla de la web. Pedirlas era
       pedir un dato que nadie iba a rellenar. */
    '<h4 style="font-size:.8125rem;color:var(--ink-2);margin-bottom:var(--g3)">Temporada en curso</h4>'+
    '<div class="rejilla rejilla-4">'+
      U.campo('Goles', '<input class="inp inp-mono" type="number" min="0" value="'+n('goles')+'" data-c="equipos:jNum" data-k="goles">')+
    '</div>'+
    '<div class="g-hueco"></div>'+
    '<h4 style="font-size:.8125rem;color:var(--ink-2);margin-bottom:var(--g3)">Histórico (temporadas ya cerradas)</h4>'+
    '<div class="rejilla rejilla-4">'+
      [['goles_totales','Goles'],['pj','Partidos']].map(function(p){
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
   IMPORTACIÓN MASIVA DE PLANTILLA (CSV / pegado desde una hoja de cálculo)

   Escribir treinta jugadores a mano es el trabajo más aburrido de este
   programa y donde más erratas entran. Se acepta lo que sale de copiar un
   rango de Excel (separado por tabulaciones) igual que un .csv de verdad, sin
   pedirle al usuario que sepa la diferencia.
   -------------------------------------------------------------------------- */
var COLS = {
  nombre:'nombre', jugador:'nombre',
  dorsal:'dorsal', numero:'dorsal', 'nº':'dorsal', n:'dorsal',
  posicion:'posicion', pos:'posicion',
  titular:'titular',
  goles:'goles', g:'goles',
  foto:'foto', afinidad:'afinidad', elemento:'afinidad'
};
var NUMERICOS = ['goles'];

/* Separador: se elige el que más veces aparece en la cabecera. Excel en
   español exporta con punto y coma y al copiar pega con tabuladores. */
function separador(linea){
  return [['\t',(linea.match(/\t/g)||[]).length], [';',(linea.match(/;/g)||[]).length], [',',(linea.match(/,/g)||[]).length]]
    .sort(function(a,b){ return b[1]-a[1]; })[0][0];
}
/* Partidor que respeta las comillas: los nombres con coma dentro son raros
   pero las descripciones copiadas de una hoja no lo son. */
function partir(linea, sep){
  var out=[], act='', dentro=false;
  for(var i=0;i<linea.length;i++){
    var c=linea[i];
    if(c==='"'){
      if(dentro && linea[i+1]==='"'){ act+='"'; i++; }
      else dentro=!dentro;
    }
    else if(c===sep && !dentro){ out.push(act); act=''; }
    else act+=c;
  }
  out.push(act);
  return out.map(function(s){ return s.trim(); });
}
function parseCSV(texto){
  var lineas = String(texto||'').split(/\r?\n/).filter(function(l){ return l.trim(); });
  if(lineas.length<2) return {err:'Hacen falta al menos una fila de cabecera y una de datos.'};
  var sep = separador(lineas[0]);
  var cab = partir(lineas[0], sep).map(function(h){ return COLS[C.norm(h).replace(/[^a-zñº]/g,'')] || null; });
  if(cab.indexOf('nombre')<0) return {err:'No encuentro una columna "nombre". Cabecera leída: '+partir(lineas[0],sep).join(' | ')};

  var filas = [], avisos = [];
  lineas.slice(1).forEach(function(l, n){
    var celdas = partir(l, sep);
    var j = {nombre:'', dorsal:'', posicion:'MED', titular:false,
             goles:0, foto:'', afinidad:'Neutro'};
    cab.forEach(function(k, i){
      if(!k) return;
      var v = celdas[i]!=null ? celdas[i] : '';
      if(k==='titular') j.titular = /^(s[ií]|true|1|x|titular)$/i.test(v.trim());
      else if(NUMERICOS.indexOf(k)>=0) j[k] = parseInt(v,10)||0;
      else if(k==='posicion'){
        var p = v.trim().toUpperCase().slice(0,3);
        if(C.POS.indexOf(p)>=0) j.posicion = p;
        else if(v.trim()) avisos.push('Fila '+(n+2)+': posición «'+v+'» no reconocida, se deja MED.');
      }
      else if(k==='afinidad'){
        /* Se corrige contra las cinco oficiales aquí, en la entrada, que es
           donde barato: si entra sucia se queda sucia para siempre. */
        if(v.trim()){
          j.afinidad = C.afName(v);
          if(!C.afinidadLimpia(v.trim())) avisos.push('Fila '+(n+2)+': afinidad «'+v+'» normalizada a '+j.afinidad+'.');
        }
      }
      else j[k] = v;
    });
    if(!j.nombre){ avisos.push('Fila '+(n+2)+': sin nombre, se descarta.'); return; }
    j.dorsal = String(j.dorsal||'');
    filas.push(j);
  });
  if(!filas.length) return {err:'Ninguna fila tenía nombre de jugador.'};
  return {filas:filas, avisos:avisos};
}

function abrirImportador(){
  U.modal({
    titulo:'Importar plantilla',
    ancho:true,
    cuerpo:
      '<p class="ayuda" style="margin-bottom:var(--g4)">Pega un rango de Excel o el contenido de un .csv. '+
        'La primera fila son los nombres de columna. Sólo «nombre» es obligatoria; el resto se rellena con valores por defecto.</p>'+
      '<p class="ayuda" style="margin-bottom:var(--g3)">Columnas reconocidas: '+
        '<span class="mono">nombre, dorsal, posicion, titular, goles, foto, afinidad</span></p>'+
      '<div class="color-par" style="margin-bottom:var(--g3)">'+
        '<input type="file" id="csv-file" accept=".csv,.tsv,.txt" class="inp inp-sm">'+
      '</div>'+
      '<textarea class="inp" id="csv-txt" style="min-height:180px;font-family:var(--f-mono);font-size:.75rem" '+
        'placeholder="nombre;dorsal;posicion;titular;goles&#10;Endo Mamoru;1;POR;sí;0"></textarea>'+
      '<div id="csv-prev"></div>',
    pie:[
      {txt:'Cancelar', fn:U.cerrarModal},
      {txt:'Añadir a la plantilla', cls:'btn-primary', id:'csv-ok', fn:function(){ aplicarCSV(false); }},
      {txt:'Reemplazar plantilla', cls:'btn-secondary', fn:function(){ aplicarCSV(true); }}
    ],
    tras: function(cuerpo){
      var ta = cuerpo.querySelector('#csv-txt');
      ta.addEventListener('input', previsualizarCSV);
      cuerpo.querySelector('#csv-file').addEventListener('change', function(){
        var f = this.files[0]; if(!f) return;
        f.text().then(function(t){ ta.value = t; previsualizarCSV(); });
      });
    }
  });
}
function previsualizarCSV(){
  var r = parseCSV(document.getElementById('csv-txt').value);
  var prev = document.getElementById('csv-prev');
  if(r.err){ prev.innerHTML = '<p class="mal" style="margin-top:var(--g3)">'+esc(r.err)+'</p>'; return; }
  prev.innerHTML =
    '<p class="ayuda" style="margin:var(--g4) 0 var(--g2)">'+r.filas.length+' jugadores detectados'+
      (r.avisos.length ? ' · '+r.avisos.length+' avisos' : '')+'</p>'+
    '<div class="tabla-caja"><div class="tabla-scroll"><table class="tabla"><thead><tr>'+
      '<th class="num">#</th><th>Nombre</th><th>Pos</th><th>Afinidad</th><th>Tit.</th><th class="num">Goles</th>'+
    '</tr></thead><tbody>'+r.filas.slice(0,15).map(function(j){
      return '<tr><td class="num">'+esc(j.dorsal)+'</td><td>'+esc(j.nombre)+'</td>'+
        '<td><span class="chip chip-'+j.posicion.toLowerCase()+'">'+j.posicion+'</span></td>'+
        '<td>'+esc(j.afinidad)+'</td><td>'+(j.titular?'sí':'')+'</td>'+
        '<td class="num">'+j.goles+'</td></tr>';
    }).join('')+'</tbody></table></div></div>'+
    (r.filas.length>15 ? '<p class="ayuda" style="margin-top:.5rem">y '+(r.filas.length-15)+' más.</p>' : '')+
    (r.avisos.length ? '<div class="tabla-caja" style="margin-top:var(--g3)">'+r.avisos.slice(0,8).map(function(a){
      return '<div class="problema avi"><i class="ph-bold ph-warning"></i><span>'+esc(a)+'</span></div>'; }).join('')+'</div>' : '');
}
function aplicarCSV(reemplazar){
  var r = parseCSV(document.getElementById('csv-txt').value);
  if(r.err) return U.aviso(r.err, 'mal', 9000);
  var e = club();
  if(!e.jugadores) e.jugadores = [];
  U.cerrarModal();
  U.confirmar({
    titulo: reemplazar ? 'Reemplazar la plantilla entera' : 'Añadir '+r.filas.length+' jugadores',
    html: reemplazar
      ? 'Se borrarán los '+e.jugadores.length+' jugadores actuales de «'+esc(e.nombre)+'», <b>con su historial y sus supertécnicas</b>, y se pondrán los '+r.filas.length+' del CSV en su lugar.'
      : 'Se añadirán '+r.filas.length+' jugadores a los '+e.jugadores.length+' que ya tiene «'+esc(e.nombre)+'».',
    ok: reemplazar ? 'Reemplazar' : 'Añadir', peligro: reemplazar
  }).then(function(si){
    if(!si) return;
    if(reemplazar) e.jugadores = r.filas;
    else e.jugadores = e.jugadores.concat(r.filas);
    U.cambio();
    U.aviso(r.filas.length+' jugadores importados.', 'ok');
  });
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
  /* campoImagen no lleva data-k: el destino va en el nombre de la accion. */
  campoEscudo: function(el){ club().escudo = el.value; U.cambio(); },
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
      goles:0, foto:'', afinidad:'Neutro'});
    jugadorAbierto = e.jugadores.length-1;
    editarJugador(jugadorAbierto);
  },
  editarJugador: function(el){
    jugadorAbierto = Number(el.dataset.i);
    editarJugador(jugadorAbierto);
  },
  importar: function(){ abrirImportador(); },

  /* Recalcula los goles de este club desde los eventos de los partidos.
     Enseña qué va a cambiar antes de tocar nada: es lo que separa un botón
     útil de uno que da miedo pulsar. */
  recalcular: function(){
    var e = club();
    var difs = C.diferenciasGoles(d()).filter(function(x){ return x.e===e; });
    if(!difs.length) return U.aviso('Los goles de «'+e.nombre+'» ya cuadran con los partidos.', 'ok');
    U.modal({
      titulo:'Recalcular los goles de '+e.nombre,
      ancho:true,
      cuerpo:'<p class="ayuda" style="margin-bottom:var(--g4)">'+difs.length+
        (difs.length===1?' ficha no coincide':' fichas no coinciden')+' con los goles de los partidos. '+
        'Los goles marcados con otra camiseta cuentan igual: son del jugador.</p>'+
        '<div class="tabla-caja"><table class="tabla"><thead><tr>'+
          '<th>Jugador</th><th class="num">Ficha</th><th class="num">Partidos</th><th class="num">Cambio</th>'+
        '</tr></thead><tbody>'+difs.map(function(x){
          var dif = x.ahora-x.antes;
          return '<tr><td>'+esc(x.j.nombre||'sin nombre')+'</td>'+
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

  usarFormacionReal: function(){
    var e = club(), tit = (e.jugadores||[]).filter(function(j){ return j.titular; });
    e.formacion = ['DEF','MED','DEL'].map(function(p){
      return tit.filter(function(j){ return j.posicion===p; }).length;
    }).join('-');
    U.cambio();
    U.aviso('Formación declarada actualizada a '+e.formacion+'.', 'ok');
  },

  /* Renumerar una línea es la ÚNICA forma de cambiar el orden horizontal que
     pinta la web, porque ordena por dorsal. Se reparten los dorsales que ya
     tenía esa línea entre los mismos jugadores, así que no se inventa ningún
     número ni se pisa el de nadie de fuera. */
  renumerar: function(el){
    var e = club(), pos = el.dataset.pos;
    var linea = (e.jugadores||[]).filter(function(j){ return j.titular && j.posicion===pos; });
    var dorsales = linea.map(function(j){ return j.dorsal; })
      .sort(function(a,b){ return (parseInt(a)||999)-(parseInt(b)||999); });
    U.modal({
      titulo:'Orden de la línea '+pos,
      ancho:true,
      cuerpo:'<p class="ayuda" style="margin-bottom:var(--g4)">Arrastra para fijar el orden de izquierda a derecha. '+
        'Al aceptar, los dorsales <span class="mono">'+dorsales.join(', ')+'</span> se reparten en ese mismo orden. '+
        'Sin ratón: enfoca a un jugador y usa ↑ ↓.</p>'+
        '<div class="dnd-col" id="renum">'+
          ordenWeb(linea).map(function(j){
            return '<div class="dnd-ficha renum-j" data-n="'+esc(j.nombre)+'" role="button" tabindex="0">'+
              '<i class="ph ph-dots-six-vertical dnd-asa" aria-hidden="true"></i>'+
              '<span class="mono" style="color:var(--ink-4);min-width:18px">'+esc(j.dorsal||'')+'</span>'+
              '<span class="nm">'+esc(j.nombre)+'</span></div>';
          }).join('')+
        '</div>',
      pie:[
        {txt:'Cancelar', fn:U.cerrarModal},
        {txt:'Aplicar dorsales', cls:'btn-primary', fn:function(){
          var orden = Array.prototype.map.call(document.querySelectorAll('#renum .renum-j'), function(x){ return x.dataset.n; });
          orden.forEach(function(nombre, k){
            var j = linea.find(function(x){ return x.nombre===nombre; });
            if(j) j.dorsal = String(dorsales[k]);
          });
          U.cerrarModal(); U.cambio();
          U.aviso('Dorsales de la línea '+pos+' reasignados.', 'ok');
        }}
      ],
      tras: function(){
        SFG.dnd.sortable({grupo:'renum', item:'.renum-j', contenedores:[document.getElementById('renum')]});
      }
    });
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
  jFoto: function(el){ club().jugadores[jugadorAbierto].foto = el.value; SFG.io.marcarSucio(); repintarModal(); },
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
