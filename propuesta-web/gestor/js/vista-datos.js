/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-datos.js
   Resumen de la temporada y salud del archivo.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

function d(){ return SFG.d(); }

/* --------------------------------------------------------------------------
   RESUMEN
   -------------------------------------------------------------------------- */
function pintarResumen(el){
  var D = d();
  var act = D.equipos.filter(function(e){ return !e.archivado; });
  var jug = act.reduce(function(a,e){ return a+(e.jugadores||[]).length; }, 0);
  var todos = D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa);
  var fin = todos.filter(C.isFin);
  var goles = fin.reduce(function(a,p){ return a+(Number(C.gl(p))||0)+(Number(C.gv(p))||0); }, 0);
  var jor = parseInt(D.config.jornada_actual)||0;

  /* Lo que falta por cerrar de la jornada en curso: es la pregunta que se
     hace quien abre este programa. */
  var pendientes = [];
  [['liga','partidos_liga','Superliga'],['ascenso','partidos_ascenso','Ascenso']].forEach(function(c){
    D[c[1]].forEach(function(p,i){
      if(!C.isFin(p) && (parseInt(p.jornada)||0)<=jor) pendientes.push({comp:c[0], nom:c[2], p:p, i:i});
    });
  });
  pendientes.sort(function(a,b){ return (parseInt(a.p.jornada)||0)-(parseInt(b.p.jornada)||0); });

  var integridad = C.validarIntegridad(D);
  var desajustes = C.desajustesTabla();

  el.innerHTML =
    U.cabecera(D.config.nombre_liga || 'Superliga Frontier',
      'Temporada '+(D.config.temporada||'—')+' · jornada '+(D.config.jornada_actual||'—'))+

    '<div class="rejilla rejilla-4">'+
      U.kpi({valor:fin.length+'/'+todos.length, etiqueta:'Partidos jugados', icono:'ph-bold ph-soccer-ball',
             destacado:true, delta:pendientes.length?-pendientes.length:null,
             deltaTexto:pendientes.length?pendientes.length+' pendientes':null,
             titulo:'Pendientes hasta la jornada '+jor})+
      U.kpi({valor:goles, etiqueta:'Goles', icono:'ph ph-target',
             delta:fin.length?0:null, deltaTexto:fin.length?(goles/fin.length).toFixed(2)+' por partido':null})+
      U.kpi({valor:act.length, etiqueta:'Clubes activos', icono:'ph ph-shield'})+
      U.kpi({valor:jug, etiqueta:'Jugadores', icono:'ph ph-users'})+
      U.kpi({valor:D.noticias.length, etiqueta:'Noticias', icono:'ph ph-newspaper'})+
      U.kpi({valor:D.agentes_libres.length, etiqueta:'Agentes libres', icono:'ph ph-arrows-left-right'})+
    '</div>'+

    (integridad.err.length || desajustes.length ? '<div class="g-hueco"></div>'+alertas(integridad, desajustes) : '')+

    '<div class="g-hueco"></div>'+
    '<div class="rejilla" style="--min:320px">'+
      tablaMini('SUPERLIGA')+tablaMini('ASCENSO')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g5)">'+
      '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Pendientes hasta la jornada '+jor+'</h3>'+
      (pendientes.length
        ? '<div class="tabla-caja">'+pendientes.slice(0,12).map(function(o){
            return '<div class="problema"><i class="ph ph-clock" style="color:var(--gold)"></i>'+
              '<span>'+esc(o.p.local||'?')+' – '+esc(o.p.visitante||'?')+'</span>'+
              '<span style="color:var(--ink-4);font-size:.75rem;margin-left:1rem">'+esc(o.nom)+' · J'+esc(o.p.jornada||'?')+'</span>'+
              '<button class="ir" data-a="datos:ir" data-v="partidos" data-p=\''+esc(JSON.stringify({comp:o.comp, idx:o.i}))+'\'>Abrir</button></div>';
          }).join('')+'</div>'+
          (pendientes.length>12 ? '<p class="ayuda" style="margin-top:.75rem">y '+(pendientes.length-12)+' más.</p>' : '')
        : '<p class="ayuda">Todo al día: no queda ningún partido sin resultado hasta la jornada actual.</p>')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    goleadores();
}

function alertas(integridad, desajustes){
  return '<div class="card" style="padding:var(--g5);border-color:'+(integridad.err.length?'rgba(255,59,59,.35)':'rgba(255,201,74,.3)')+'">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);flex-wrap:wrap">'+
      '<i class="ph-bold ph-warning" style="color:'+(integridad.err.length?'#FF7B7B':'var(--gold)')+';font-size:1.25rem"></i>'+
      '<div><b style="font-size:.9375rem">'+
        (integridad.err.length ? integridad.err.length+' problemas bloquean el guardado' : desajustes.length+' desajustes de clasificación')+
      '</b><p class="ayuda">'+
        (integridad.err.length ? 'La web no sabría leer el archivo en este estado.' : 'La tabla guardada no coincide con lo que dicen los partidos.')+
      '</p></div>'+
      '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="datos:ir" data-v="datos" data-p="{}">Ver detalle</button>'+
    '</div></div>';
}

function tablaMini(div){
  var ord = C.clasificacion(div);
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">'+
      '<span class="badge '+(div==='ASCENSO'?'badge-ascenso':'badge-superliga')+'">'+div+'</span></h3>'+
    (ord.length ? '<table class="tabla"><tbody>'+ord.slice(0,6).map(function(e,i){
      return '<tr><td class="num" style="width:1%;color:var(--ink-4)">'+(i+1)+'</td>'+
        '<td><button class="cel-btn" data-a="datos:ir" data-v="equipos" data-p=\''+esc(JSON.stringify({id:e.id}))+'\'>'+
          U.celdaEquipo(e)+'</button></td>'+
        '<td class="num" style="color:var(--ink-4)">'+(e.pj||0)+'</td>'+
        '<td class="num" style="font-weight:600">'+(e.pts||0)+'</td></tr>';
    }).join('')+'</tbody></table>' : '<p class="ayuda">Sin equipos en esta división.</p>')+
  '</div>';
}

function goleadores(){
  var top = C.calcScorers(d().partidos_liga.concat(d().partidos_ascenso, d().partidos_copa).filter(C.isFin)).slice(0,10);
  if(!top.length) return '';
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Goleadores</h3>'+
    '<table class="tabla"><tbody>'+top.map(function(r,i){
      return '<tr><td class="num" style="width:1%;color:var(--ink-4)">'+(i+1)+'</td>'+
        '<td>'+esc(r.nombre)+
          /* Un goleador que no enlaza con ninguna ficha sale en la web sin
             foto ni enlace: es un dato roto, aunque el ranking cuadre. */
          (r.j ? '' : ' <span class="pastilla pastilla-mal" title="No coincide con ningún jugador registrado">sin ficha</span>')+'</td>'+
        '<td style="color:var(--ink-4);font-size:.75rem">'+esc(r.e?r.e.nombre:'—')+'</td>'+
        '<td class="num" style="font-weight:600">'+r.goles+'</td></tr>';
    }).join('')+'</tbody></table></div>';
}

/* --------------------------------------------------------------------------
   DATOS: validación, copias y enlaces
   -------------------------------------------------------------------------- */
var imgs = null;         // resultado de la última comprobación de enlaces

function pintarDatos(el){
  var v = C.validarIntegridad(d());
  var des = C.desajustesTabla();
  var snaps = SFG.io.snapshots();
  var errs = SFG.io.errores();

  el.innerHTML =
    U.cabecera('Datos', 'Salud del archivo y copias de seguridad',
      '<button class="btn btn-secondary btn-sm" data-a="datos:normalizar"><i class="ph ph-broom"></i> Normalizar ahora</button>')+

    (errs.length ? bloqueErrores(errs) + '<div class="g-hueco"></div>' : '')+

    '<div class="card" style="padding:var(--g5)">'+
      '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
        '<h3 style="font-size:.9375rem">Integridad</h3>'+
        '<span class="pastilla '+(v.err.length?'pastilla-mal':'pastilla-ok')+'">'+
          (v.err.length ? v.err.length+' críticos' : 'sin errores')+'</span>'+
        (v.avi.length ? '<span class="pastilla pastilla-ojo">'+v.avi.length+' avisos</span>' : '')+
      '</div>'+
      (v.err.length||v.avi.length
        ? '<div class="tabla-caja">'+
            v.err.map(function(x){ return problema(x,'err'); }).join('')+
            v.avi.slice(0,40).map(function(x){ return problema(x,'avi'); }).join('')+
          '</div>'+(v.avi.length>40?'<p class="ayuda" style="margin-top:.75rem">y '+(v.avi.length-40)+' avisos más.</p>':'')
        : '<p class="ayuda">Todas las referencias entre equipos, partidos e historiales resuelven.</p>')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g5)">'+
      '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
        '<h3 style="font-size:.9375rem">Clasificación frente a los partidos</h3>'+
        '<span class="pastilla '+(des.length?'pastilla-ojo':'pastilla-ok')+'">'+
          (des.length ? des.length+' desajustes' : 'cuadra')+'</span>'+
        (des.length ? '<button class="btn btn-accent btn-sm" style="margin-left:auto" data-a="datos:recalcular">Corregir todo</button>' : '')+
      '</div>'+
      (des.length
        ? '<div class="tabla-caja">'+des.slice(0,30).map(function(x){
            return '<div class="problema avi"><i class="ph-bold ph-warning"></i>'+
              '<span>'+esc(x.equipo.nombre)+' · '+x.campo.toUpperCase()+': guardado <b class="mono">'+x.guardado+'</b>, los partidos dicen <b class="mono">'+x.calculado+'</b></span>'+
              '<button class="ir" data-a="datos:ir" data-v="equipos" data-p=\''+esc(JSON.stringify({id:x.equipo.id}))+'\'>Abrir</button></div>';
          }).join('')+'</div>'
        : '<p class="ayuda">Los siete campos de los '+d().equipos.length+' clubes coinciden con el resultado de los partidos finalizados.</p>')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    bloqueGoleadores()+

    '<div class="g-hueco"></div>'+
    bloqueCamposMuertos()+

    '<div class="g-hueco"></div>'+
    bloqueEnlaces()+

    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g5)">'+
      '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Copias de seguridad</h3>'+
      '<p class="ayuda" style="margin-bottom:var(--g4)">Se guarda una en este navegador antes de cada escritura. Sólo viven aquí: no viajan con el archivo.</p>'+
      (snaps.length
        ? '<div class="tabla-caja">'+snaps.map(function(x){
            return '<div class="problema"><i class="ph ph-clock-counter-clockwise"></i>'+
              '<span>'+esc(new Date(x.ts).toLocaleString('es-ES'))+'</span>'+
              '<span class="mono" style="margin-left:1rem;color:var(--ink-4);font-size:.75rem">'+Math.round(x.bytes/1024)+' KB</span>'+
              '<button class="ir" data-a="ui:restaurar" data-ts="'+x.ts+'">Restaurar</button></div>';
          }).join('')+'</div>'
        : '<p class="ayuda">Todavía no hay copias: se crean al guardar.</p>')+
    '</div>';
}

function problema(x, cls){
  return '<div class="problema '+cls+'">'+
    '<i class="'+(cls==='err'?'ph-bold ph-warning-octagon':'ph-bold ph-warning')+'"></i>'+
    '<span>'+esc(x.m)+'</span>'+
    (x.ir ? '<button class="ir" data-a="datos:ir" data-v="'+esc(x.ir.v)+'" data-p=\''+esc(JSON.stringify(x.ir))+'\'>Ir</button>' : '')+
  '</div>';
}

function bloqueErrores(errs){
  return '<div class="card" style="padding:var(--g5);border-color:rgba(255,59,59,.35)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);flex-wrap:wrap">'+
      '<i class="ph-bold ph-warning-octagon" style="color:#FF7B7B;font-size:1.25rem"></i>'+
      '<div><b style="font-size:.9375rem">El último guardado falló</b>'+
        '<p class="ayuda">'+esc(errs[0].msg)+' — '+esc(new Date(errs[0].ts).toLocaleString('es-ES'))+'</p></div>'+
      '<button class="btn btn-primary btn-sm" style="margin-left:auto" data-a="datos:reintentar">Reintentar</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="datos:olvidar">Descartar</button>'+
    '</div></div>';
}

/* --------------------------------------------------------------------------
   GOLEADORES FRENTE A EVENTOS
   Dos cosas distintas que la gente confunde:

   - COBERTURA: cuántos de los goles del marcador tienen goleador anotado en
     `detalles`. Un partido puede estar 3-0 y no decir quién marcó; la web lo
     enseña igual, pero su cronología sale vacía y esos goles no cuentan para
     el ranking de goleadores.
   - DESCUADRE: cuando el campo `goles` de la ficha de un jugador no coincide
     con los goles que le atribuyen los eventos. Ahí hay dos números que
     deberían decir lo mismo y no lo dicen.
   -------------------------------------------------------------------------- */
function analisisGoleadores(){
  var D = d();
  var fin = D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa).filter(C.isFin);
  var golesMarcador = 0, golesAnotados = 0, noJugados = 0, golesNoJugados = 0;
  var vacios = [], descuadrados = [];

  [['liga','partidos_liga'],['ascenso','partidos_ascenso'],['copa','partidos_copa']].forEach(function(par){
    D[par[1]].forEach(function(p, i){
      if(!C.isFin(p)) return;
      var m = (Number(C.gl(p))||0) + (Number(C.gv(p))||0);
      /* Un partido que no se disputó no tuvo goles que anotar: contarlo como
         «gol sin goleador» falsea la única cifra que dice qué falta de verdad. */
      if(C.esNoJugado(p)){ noJugados++; golesNoJugados += m; return; }
      var ev = C.parseDetalles(p.detalles);
      var g = ev.local.filter(esGol).length + ev.visitante.filter(esGol).length;
      golesMarcador += m; golesAnotados += g;
      if(m>0 && ev.local.length+ev.visitante.length===0) vacios.push({p:p, comp:par[0], idx:i, m:m});
      else if(m!==g) descuadrados.push({p:p, comp:par[0], idx:i, m:m, g:g});
    });
  });

  /* Ficha del jugador frente a lo que dicen los eventos. */
  var mapa = C.statsJugadoresCalculadas();
  var fichas = [];
  D.equipos.forEach(function(e){
    (e.jugadores||[]).forEach(function(j){
      var c = C.eventosDe(mapa, j);
      if((j.goles||0)!==c.goles) fichas.push({j:j, e:e, ficha:j.goles||0, eventos:c.goles});
    });
  });

  return {fin:fin.length, golesMarcador:golesMarcador, golesAnotados:golesAnotados,
          noJugados:noJugados, golesNoJugados:golesNoJugados,
          vacios:vacios, descuadrados:descuadrados, fichas:fichas};
}
function esGol(e){ return e.tipo==='gol'; }

function bloqueGoleadores(){
  var a = analisisGoleadores();
  var pct = a.golesMarcador ? Math.round(a.golesAnotados/a.golesMarcador*100) : 100;

  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Goleadores frente a los partidos</h3>'+
      '<span class="pastilla '+(pct===100?'pastilla-ok':(pct>=70?'pastilla-ojo':'pastilla-mal'))+'">'+pct+'% anotados</span>'+
      (a.fichas.length ? '<span class="pastilla pastilla-mal">'+a.fichas.length+' fichas descuadradas</span>' : '')+
    '</div>'+

    /* Barra de cobertura: se ve de un vistazo cuánto falta por documentar. */
    '<div style="margin-bottom:var(--g4)">'+
      '<div style="display:flex;font-size:.75rem;margin-bottom:.25rem">'+
        '<span>'+a.golesAnotados+' goles con goleador</span>'+
        '<span class="ayuda" style="margin-left:auto">'+(a.golesMarcador-a.golesAnotados)+' sin anotar de '+a.golesMarcador+'</span></div>'+
      '<div style="height:8px;border-radius:4px;background:var(--surface-3);overflow:hidden">'+
        '<div style="height:100%;width:'+pct+'%;background:var(--accent)"></div></div>'+
      '<p class="ayuda" style="margin-top:var(--g2)">Un gol sin goleador anotado no aparece en la cronología del partido ni suma en el ranking de la web. '+
        'El marcador sí se muestra bien.</p>'+
      (a.noJugados
        ? '<p class="ayuda" style="margin-top:var(--g2)"><i class="ph ph-info"></i> '+
          'No se cuentan '+a.noJugados+' partidos marcados como no disputados ('+a.golesNoJugados+
          ' goles de victoria administrativa): no hubo goles que anotar.</p>'
        : '')+
      (a.vacios.length
        ? '<p class="ayuda" style="margin-top:var(--g2)"><i class="ph ph-lightbulb"></i> '+
          'Si alguno de los '+a.vacios.length+' partidos de abajo no llegó a jugarse, márcalo desde '+
          '<b>Partidos</b>: selecciónalo y usa «Marcar como no jugado». Dejará de contar aquí.</p>'
        : '')+
    '</div>'+

    (a.fichas.length
      ? '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin:var(--g4) 0 var(--g2)">'+
          'FICHA CONTRA EVENTOS</div>'+
        '<div class="tabla-caja" style="margin-bottom:var(--g3)">'+a.fichas.slice(0,20).map(function(f){
          return '<div class="problema err"><i class="ph-bold ph-warning-octagon"></i>'+
            '<span>'+esc(f.j.nombre)+' · '+esc(f.e.nombre)+': la ficha dice <b class="mono">'+f.ficha+
            '</b> y los partidos le dan <b class="mono">'+f.eventos+'</b></span>'+
            '<button class="ir" data-a="datos:ir" data-v="equipos" data-p=\''+esc(JSON.stringify({id:f.e.id, jugador:f.j.nombre}))+'\'>Abrir</button></div>';
        }).join('')+'</div>'+
        '<button class="btn btn-accent btn-sm" data-a="datos:aplicarFichas">Poner las '+a.fichas.length+' fichas al valor de los partidos</button>'
      : '<p class="ayuda"><i class="ph ph-check-circle" style="color:#6FD98A"></i> Las fichas de jugador cuadran con los goles que les dan los partidos.</p>')+

    (a.vacios.length
      ? '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin:var(--g5) 0 var(--g2)">'+
          'PARTIDOS CON GOLES Y SIN NINGÚN EVENTO · '+a.vacios.length+'</div>'+
        '<div class="tabla-caja">'+a.vacios.slice(0,15).map(function(x){
          return '<div class="problema avi"><i class="ph-bold ph-warning"></i>'+
            '<span>'+esc(x.p.local||'?')+' <b class="mono">'+C.gl(x.p)+'–'+C.gv(x.p)+'</b> '+esc(x.p.visitante||'?')+
            ' · '+x.m+' goles sin goleador</span>'+
            '<button class="ir" data-a="datos:ir" data-v="'+(x.comp==='copa'?'copa':'partidos')+
              '" data-p=\''+esc(JSON.stringify({comp:x.comp, idx:x.idx}))+'\'>Abrir</button></div>';
        }).join('')+'</div>'+
        (a.vacios.length>15 ? '<p class="ayuda" style="margin-top:var(--g2)">y '+(a.vacios.length-15)+' más.</p>' : '')
      : '')+

    (a.descuadrados.length
      ? '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin:var(--g5) 0 var(--g2)">'+
          'MARCADOR CONTRA GOLEADORES · '+a.descuadrados.length+'</div>'+
        '<div class="tabla-caja">'+a.descuadrados.slice(0,15).map(function(x){
          return '<div class="problema avi"><i class="ph-bold ph-warning"></i>'+
            '<span>'+esc(x.p.local||'?')+' – '+esc(x.p.visitante||'?')+': marcador de <b class="mono">'+x.m+
            '</b> goles y <b class="mono">'+x.g+'</b> goleadores</span>'+
            '<button class="ir" data-a="datos:ir" data-v="'+(x.comp==='copa'?'copa':'partidos')+
              '" data-p=\''+esc(JSON.stringify({comp:x.comp, idx:x.idx}))+'\'>Abrir</button></div>';
        }).join('')+'</div>'
      : '')+
  '</div>';
}

/* --------------------------------------------------------------------------
   CAMPOS QUE ESTA LIGA NO USA
   Asistencias y tarjetas están en las fichas porque el esquema las trae, pero
   aquí no se anotan nunca ni se muestran en ninguna pantalla de la web. Son
   peso muerto dentro del archivo que el visitante descarga entero.

   Borrarlas es irreversible, así que no se hace solo: se cuenta primero y se
   dice cuántas llevan un valor distinto de cero por si alguna guardaba algo.
   -------------------------------------------------------------------------- */
function bloqueCamposMuertos(){
  var c = C.contarCamposSinUso(d());
  if(!c.campos) return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Campos sin uso</h3>'+
    '<p class="ayuda">Las fichas no llevan asistencias ni tarjetas. Nada que limpiar.</p></div>';

  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Campos sin uso</h3>'+
      '<span class="pastilla pastilla-ojo">'+c.campos+'</span></div>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">'+
      'Las fichas guardan <span class="mono">asistencias</span>, <span class="mono">amarillas</span>, '+
      '<span class="mono">rojas</span> y sus totales. En esta liga no se anotan nunca y la web no los muestra '+
      'en ninguna pantalla: son '+c.campos+' campos de peso muerto dentro del archivo que el visitante descarga entero.'+
      (c.conValor
        ? ' <b style="color:var(--gold)">'+c.conValor+' llevan un valor distinto de cero</b>, así que revísalos antes.'
        : ' <b>Todos valen cero</b>, así que no se pierde ningún dato al quitarlos.')+
    '</p>'+
    '<button class="btn btn-'+(c.conValor?'secondary':'accent')+' btn-sm" data-a="datos:limpiarCampos">'+
      '<i class="ph ph-broom"></i> Quitar los '+c.campos+' campos</button>'+
  '</div>';
}

/* --------------------------------------------------------------------------
   ENLACES DE IMAGEN
   Se comprueban cargándolas de verdad: no hay forma de preguntar por un
   estado HTTP desde el navegador sin que el otro dominio lo permita, y estos
   CDN no lo permiten. De cinco en cinco para no abrir 800 peticiones a la vez.
   -------------------------------------------------------------------------- */
function recogerImagenes(){
  var out = [], vistos = {};
  function mete(url, que){
    if(!/^https?:\/\//.test(url||'') || vistos[url]) return;
    vistos[url] = 1;
    out.push({url:url, que:que});
  }
  d().equipos.forEach(function(e){
    mete(e.escudo, 'Escudo de '+e.nombre);
    (e.jugadores||[]).forEach(function(j){ mete(j.foto, j.nombre+' ('+e.nombre+')'); });
  });
  (d().agentes_libres||[]).forEach(function(j){ mete(j.foto, j.nombre+' (agente libre)'); });
  d().noticias.forEach(function(n){ mete(n.imagen, 'Imagen de «'+(n.titulo||'sin título')+'»'); });
  return out;
}
function comprobarImagenes(){
  var lista = recogerImagenes();
  imgs = {total:lista.length, hechas:0, rotas:[]};
  pintarProgreso();
  var i = 0, activos = 0, TOPE = 5;
  function siguiente(){
    while(activos<TOPE && i<lista.length){
      (function(item){
        activos++;
        var img = new Image();
        var fin = function(ok){
          if(!img) return;
          img = null; activos--; imgs.hechas++;
          if(!ok) imgs.rotas.push(item);
          if(imgs.hechas%20===0 || imgs.hechas===imgs.total) pintarProgreso();
          if(imgs.hechas===imgs.total) U.refrescar(); else siguiente();
        };
        img.onload = function(){ fin(true); };
        img.onerror = function(){ fin(false); };
        img.referrerPolicy = 'no-referrer';
        img.src = item.url;
        /* Un CDN caído puede no contestar nunca: sin este corte la comprobación
           no terminaría jamás. */
        setTimeout(function(){ if(img) fin(false); }, 12000);
      })(lista[i++]);
    }
  }
  siguiente();
}
function pintarProgreso(){
  var el = document.getElementById('img-estado');
  if(el && imgs) el.textContent = imgs.hechas+' de '+imgs.total+(imgs.rotas.length?' · '+imgs.rotas.length+' rotas':'');
}
function bloqueEnlaces(){
  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Enlaces de imagen</h3>'+
      '<span class="ayuda" id="img-estado">'+(imgs ? imgs.hechas+' de '+imgs.total : recogerImagenes().length+' enlaces distintos')+'</span>'+
      '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="datos:comprobarImgs">'+
        (imgs&&imgs.hechas===imgs.total ? 'Volver a comprobar' : 'Comprobar ahora')+'</button>'+
    '</div>'+
    (imgs && imgs.hechas===imgs.total
      ? (imgs.rotas.length
          ? '<div class="tabla-caja">'+imgs.rotas.slice(0,40).map(function(r){
              return '<div class="problema avi"><i class="ph-bold ph-image-broken"></i>'+
                '<span>'+esc(r.que)+'</span>'+
                '<a class="ir" href="'+esc(r.url)+'" target="_blank" rel="noreferrer">abrir</a></div>';
            }).join('')+'</div>'+(imgs.rotas.length>40?'<p class="ayuda" style="margin-top:.75rem">y '+(imgs.rotas.length-40)+' más.</p>':'')
          : '<p class="ayuda">Los '+imgs.total+' enlaces responden.</p>')
      : '<p class="ayuda">La comprobación carga cada imagen de verdad, así que tarda. Se puede seguir trabajando mientras.</p>')+
  '</div>';
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
var A = {
  ir: function(el){ U.irA(el.dataset.v, JSON.parse(el.dataset.p)); },
  recalcular: function(){
    var calc = C.tablaCalculada(), n = 0;
    d().equipos.forEach(function(e){
      var c = calc[e.nombre]; if(!c) return;
      C.CAMPOS_TABLA.forEach(function(k){ if((e[k]||0)!==c[k]){ e[k] = c[k]; n++; } });
    });
    U.cambio();
    U.aviso(n+' valores corregidos desde los partidos.', 'ok');
  },
  normalizar: function(){
    var reg = C.normalizar(d());
    U.cambio();
    if(!reg.length) return U.aviso('No hacía falta normalizar nada.', 'info');
    U.modal({
      titulo:'Normalización aplicada', ancho:true,
      cuerpo:'<p class="ayuda" style="margin-bottom:1rem">'+reg.length+' campos sincronizados. Ningún dato se ha perdido.</p>'+
        '<div class="tabla-caja">'+reg.slice(0,50).map(function(x){
          return '<div class="problema"><i class="ph ph-arrows-left-right"></i><span>'+esc(x)+'</span></div>';
        }).join('')+'</div>',
      pie:[{txt:'Entendido', cls:'btn-primary', fn:U.cerrarModal}]
    });
  },
  comprobarImgs: function(){ comprobarImagenes(); },
  limpiarCampos: function(){
    var c = C.contarCamposSinUso(d());
    U.confirmar({
      titulo:'Quitar los campos sin uso',
      html:'Se borrarán <b>'+c.campos+' campos</b> de las fichas de jugador: asistencias, tarjetas y sus totales, '+
        'también dentro del historial.<br><br>'+
        (c.conValor
          ? '<b style="color:var(--gold)">'+c.conValor+' de ellos no valen cero.</b> Ese dato se pierde.<br><br>'
          : 'Todos valen cero, así que no se pierde ningún dato.<br><br>')+
        'La web pública no los lee, así que no cambiará nada de lo que se ve. '+
        'Nada se escribe en disco hasta que pulses Guardar.',
      ok:'Quitar', peligro:!!c.conValor
    }).then(function(si){
      if(!si) return;
      var n = C.limpiarCamposSinUso(d());
      U.cambio();
      U.aviso(n+' campos quitados de las fichas.', 'ok');
    });
  },
  aplicarFichas: function(){
    var a = analisisGoleadores();
    /* Sólo se toca `goles`: asistencias y tarjetas no tienen ni un evento en
       el archivo, así que ponerlas a cero desde los eventos borraría datos que
       podrían estar bien puestos a mano. */
    U.confirmar({
      titulo:'Poner las fichas al valor de los partidos',
      html:'Se cambiará el campo <span class="mono">goles</span> de <b>'+a.fichas.length+' jugadores</b> '+
        'para que coincida con los goles que les atribuyen los eventos de los partidos.<br><br>'+
        'Ojo: hay <b>'+(a.golesMarcador-a.golesAnotados)+' goles sin goleador anotado</b>. Si aplicas esto ahora, '+
        'esos jugadores se quedarán con menos goles de los que marcaron de verdad. '+
        'Tiene sentido cuando los eventos están completos, no antes.<br><br>'+
        'Sólo se toca <span class="mono">goles</span>: asistencias y tarjetas se dejan como están.',
      ok:'Aplicar', peligro:true
    }).then(function(si){
      if(!si) return;
      a.fichas.forEach(function(f){ f.j.goles = f.eventos; });
      U.cambio();
      U.aviso(a.fichas.length+' fichas puestas al valor de los partidos.', 'ok');
    });
  },
  reintentar: function(){ U.guardar(); },
  olvidar: function(){ SFG.io.limpiarErrores(); U.refrescar(); }
};

U.registrar('resumen', {acciones:A, render:pintarResumen});
U.registrar('datos', {acciones:A, render:pintarDatos});

})();
