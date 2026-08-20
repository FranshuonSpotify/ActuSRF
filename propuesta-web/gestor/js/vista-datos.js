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
      [[act.length,'Clubes activos'], [jug,'Jugadores'], [fin.length+'/'+todos.length,'Partidos jugados'],
       [goles,'Goles'], [D.noticias.length,'Noticias'], [D.agentes_libres.length,'Agentes libres']]
      .map(function(k){
        return '<div class="card" style="padding:var(--g4)">'+
          '<div class="mono" style="font-size:1.75rem;font-weight:600;letter-spacing:-.03em">'+esc(k[0])+'</div>'+
          '<div class="ayuda" style="margin-top:.15rem">'+esc(k[1])+'</div></div>';
      }).join('')+
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
  reintentar: function(){ U.guardar(); },
  olvidar: function(){ SFG.io.limpiarErrores(); U.refrescar(); }
};

U.registrar('resumen', {acciones:A, render:pintarResumen});
U.registrar('datos', {acciones:A, render:pintarDatos});

})();
