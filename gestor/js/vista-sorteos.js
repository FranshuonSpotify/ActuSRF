/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-sorteos.js
   Generadores de calendario, Fútbol Frontier, Torneo Frontier, Play-off y
   ayudas de jornada.

   Regla de la pantalla: NADA se escribe hasta pulsar «Aplicar». Los
   generadores de core devuelven la lista de partidos y aquí se enseña antes;
   por eso «repetir el sorteo» es sólo cambiar la semilla y volver a pintar,
   sin haber tocado el archivo en ningún momento.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var cal = {div:'SUPERLIGA', vueltas:2, desde:1, semilla:semillaNueva(), previa:null};
var ff = {sel:null, semilla:semillaNueva(), previa:null};   // sel: {nombre:true} de la preliminar
var tf = {semilla:semillaNueva(), previa:null};

function d(){ return SFG.d(); }
function semillaNueva(){ return Math.floor(Math.random()*1e9)+1; }
function activos(div){
  return d().equipos.filter(function(e){ return !e.archivado && (!div || e.division===div); })
    .map(function(e){ return e.nombre; });
}

/* --------------------------------------------------------------------------
   VISTA
   -------------------------------------------------------------------------- */
function pintar(el){
  el.innerHTML =
    U.cabecera('Sorteos y generadores', 'Nada se escribe en el archivo hasta que pulses Aplicar.')+
    bloqueCalendario()+
    '<div class="g-hueco"></div>'+bloqueFF()+
    '<div class="g-hueco"></div>'+bloqueTorneo()+
    '<div class="g-hueco"></div>'+bloquePlayoff('SUPERLIGA')+
    '<div class="g-hueco"></div>'+bloquePlayoff('ASCENSO')+
    '<div class="g-hueco"></div><div class="rejilla" style="--min:320px;align-items:start">'+
      bloqueJornada()+bloqueDerbis()+
    '</div>';
}

/* --- Calendario de liga ---------------------------------------------- */
function bloqueCalendario(){
  var eq = activos(cal.div);
  var existentes = (cal.div==='ASCENSO'?d().partidos_ascenso:d().partidos_liga).filter(C.esRegular);
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Calendario de liga</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Todos contra todos por el método del círculo, alternando campo. '+
      'Nadie juega dos veces la misma jornada y el reparto casa/fuera queda equilibrado.</p>'+
    '<div class="rejilla rejilla-4" style="margin-bottom:var(--g4)">'+
      U.campo('División', '<select class="inp" data-c="sorteos:calDiv">'+
        C.DIVISIONES.map(function(x){ return '<option'+(cal.div===x?' selected':'')+'>'+x+'</option>'; }).join('')+'</select>',
        eq.length+' equipos activos')+
      U.campo('Vueltas', '<select class="inp" data-c="sorteos:calVueltas">'+
        [[1,'Una vuelta'],[2,'Ida y vuelta']].map(function(v){
          return '<option value="'+v[0]+'"'+(cal.vueltas===v[0]?' selected':'')+'>'+v[1]+'</option>'; }).join('')+'</select>')+
      U.campo('Primera jornada', '<input class="inp inp-mono" type="number" min="1" value="'+cal.desde+'" data-c="sorteos:calDesde">')+
      U.campo('Resultado',
        '<div class="inp" style="display:flex;align-items:center;color:var(--ink-3)">'+
        (eq.length>1 ? (eq.length*(eq.length-1)/2*cal.vueltas)+' partidos · '+((eq.length%2?eq.length:eq.length-1)*cal.vueltas)+' jornadas' : '—')+
        '</div>')+
    '</div>'+
    (eq.length<2 ? '<p class="mal">Hacen falta al menos dos equipos activos en la división.</p>' :
      '<div style="display:flex;gap:.4rem;flex-wrap:wrap">'+
        '<button class="btn btn-secondary btn-sm" data-a="sorteos:calSortear"><i class="ph ph-shuffle"></i> '+
          (cal.previa?'Repetir sorteo':'Sortear')+'</button>'+
        (cal.previa ? '<button class="btn btn-primary btn-sm" data-a="sorteos:calAplicar">Aplicar '+cal.previa.length+' partidos</button>' : '')+
        (cal.previa ? '<button class="btn btn-secondary btn-sm" data-a="sorteos:calDescartar">Descartar</button>' : '')+
      '</div>'+
      (cal.previa ? previaCalendario(existentes) : ''));
}
function previaCalendario(existentes){
  var porJor = {};
  cal.previa.forEach(function(p){ (porJor[p.jornada] = porJor[p.jornada]||[]).push(p); });
  var casa = {};
  cal.previa.forEach(function(p){ casa[p.local] = (casa[p.local]||0)+1; });
  return '<div style="margin-top:var(--g4)">'+
    (existentes.length
      ? '<p class="mal" style="margin-bottom:var(--g3)"><i class="ph-bold ph-warning"></i> '+
        'La división ya tiene '+existentes.length+' partidos de jornada regular. Al aplicar te preguntaré si reemplazarlos o añadir a continuación.</p>'
      : '')+
    '<div style="display:flex;gap:var(--g3);overflow-x:auto;padding-bottom:var(--g2)">'+
      Object.keys(porJor).sort(function(a,b){ return a-b; }).map(function(j){
        return '<div style="min-width:170px;flex-shrink:0">'+
          '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">JORNADA '+esc(j)+'</div>'+
          porJor[j].map(function(p){
            return '<div class="dnd-ficha" style="cursor:default">'+
              '<span class="nm">'+esc(C.abbr3(p.local,(C.equipo(p.local)||{}).abreviatura))+
              ' <span style="color:var(--ink-4)">vs</span> '+
              esc(C.abbr3(p.visitante,(C.equipo(p.visitante)||{}).abreviatura))+'</span></div>';
          }).join('')+'</div>';
      }).join('')+
    '</div>'+
    '<p class="ayuda" style="margin-top:var(--g3)">Reparto en casa: '+
      Object.keys(casa).sort().map(function(k){ return esc(C.abbr3(k))+' '+casa[k]; }).join(' · ')+'</p>'+
  '</div>';
}

/* --- Fútbol Frontier ------------------------------------------------- */
/* Los 6 de la preliminar son los peores de Segunda de la temporada ANTERIOR:
   se proponen desde la última temporada archivada, contando sólo a los que
   siguen hoy en Ascenso, y se completa con la tabla actual si faltan (clubes
   nuevos). Es una propuesta: se marcan y desmarcan a mano. */
function propuestaPreliminar(){
  var hoy = activos('ASCENSO');
  var hist = d().historial_temporadas || [], t = hist[hist.length-1];
  var antes = t ? C.orderStandings((t.equipos||[]).filter(function(e){ return e.division==='ASCENSO'; }))
    .map(function(e){ return e.nombre; }).filter(function(n){ return hoy.indexOf(n)>=0; }) : [];
  var peores = antes.slice(-6);
  C.clasificacion('ASCENSO').map(function(e){ return e.nombre; }).reverse().forEach(function(n){
    if(peores.length<6 && peores.indexOf(n)<0) peores.push(n);
  });
  var sel = {};
  peores.forEach(function(n){ sel[n] = true; });
  return sel;
}
function preliminarElegida(){
  return activos('ASCENSO').filter(function(n){ return ff.sel[n]; });
}
function etiqueta(txt){
  return '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">'+txt+'</div>';
}
function bloqueFF(){
  if(!ff.sel) ff.sel = propuestaPreliminar();
  var asc = activos('ASCENSO'), prelim = preliminarElegida();
  var directos = activos().filter(function(n){ return prelim.indexOf(n)<0; });
  var gc = d().config.grupos_copa || {};
  var errores = C.erroresGruposFF(gc, prelim, directos);
  if(prelim.length!==6) errores.unshift('Marca exactamente 6 equipos para la preliminar (hay '+prelim.length+').');
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Fútbol Frontier</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">El único sorteo del torneo es la preliminar entre los 6 peores de Segunda de la temporada anterior. '+
      'Los grupos no se sortean: se leen del reparto hecho a mano en <a href="#copa">Copa</a>. '+
      'Al aplicar se crean los 50 partidos, con cuartos, semifinales y final ya encadenados.</p>'+
    etiqueta('PRELIMINAR · '+prelim.length+' DE 6')+
    '<div class="rejilla" style="--min:190px;margin-bottom:var(--g4)">'+
      asc.map(function(n){
        return '<label class="sw"><input type="checkbox"'+(ff.sel[n]?' checked':'')+
          ' data-c="sorteos:ffEq" data-n="'+esc(n)+'"><span class="pista"></span> '+esc(n)+'</label>';
      }).join('')+
    '</div>'+
    etiqueta('GRUPOS · '+directos.length+' DIRECTOS + 3 PLAZAS DE LA PRELIMINAR')+
    '<div class="rejilla rejilla-4" style="margin-bottom:var(--g4)">'+
      C.LETRAS_FF.map(function(g){
        return '<div class="tabla-caja"><div class="problema" style="color:var(--ink-3)">Grupo '+g+'</div>'+
          ((gc[g]||[]).length ? (gc[g]||[]).map(function(n,i){
            return '<div class="problema"><span class="mono" style="color:var(--ink-4);min-width:14px">'+(i+1)+'</span>'+esc(n)+'</div>';
          }).join('') : '<div class="problema" style="color:var(--ink-4)">Vacío</div>')+'</div>';
      }).join('')+
    '</div>'+
    (errores.length
      ? errores.slice(0,6).map(function(e){
          return '<p class="mal" style="margin-bottom:var(--g2)"><i class="ph-bold ph-warning"></i> '+esc(e)+'</p>';
        }).join('')+
        (errores.length>6 ? '<p class="ayuda">Y '+(errores.length-6)+' avisos más.</p>' : '')
      : '<div style="display:flex;gap:.4rem;flex-wrap:wrap">'+
          '<button class="btn btn-secondary btn-sm" data-a="sorteos:ffSortear"><i class="ph ph-shuffle"></i> '+
            (ff.previa?'Repetir sorteo':'Sortear preliminar')+'</button>'+
          (ff.previa ? '<button class="btn btn-primary btn-sm" data-a="sorteos:ffAplicar">Aplicar '+ff.previa.length+' partidos</button>'+
            '<button class="btn btn-secondary btn-sm" data-a="sorteos:ffDescartar">Descartar</button>' : '')+
        '</div>'+
        (ff.previa ? previaFF() : ''));
}
function previaFF(){
  var ya = d().partidos_copa.length;
  return '<div style="margin-top:var(--g4)">'+
    (ya ? '<p class="mal" style="margin-bottom:var(--g3)"><i class="ph-bold ph-warning"></i> '+
      'Fútbol Frontier ya tiene '+ya+' partidos. Aplicar los reemplaza por completo, con sus resultados.</p>' : '')+
    '<div class="tabla-caja">'+
      ff.previa.filter(function(p){ return p.fase==='PRELIMINAR'; }).map(function(p,i){
        return '<div class="problema"><span class="mono" style="color:var(--ink-3);min-width:110px">PRELIMINAR '+(i+1)+'</span>'+
          esc(p.local)+' <span style="color:var(--ink-4)">vs</span> '+esc(p.visitante)+'</div>';
      }).join('')+
    '</div>'+
    '<p class="ayuda" style="margin-top:var(--g3)">El ganador de la preliminar N ocupa la plaza «Ganador preliminar N» de su grupo.</p></div>';
}

/* --- Torneo Frontier -------------------------------------------------- */
function bloqueTorneo(){
  var p1 = C.clasificacion('SUPERLIGA'), s2 = C.clasificacion('ASCENSO');
  var ya = d().partidos_torneo || [];
  var semis = ya.filter(function(p){ return p.fase==='SEMIFINALES'; });
  var faltanSemis = semis.length===2 && semis.some(function(p){ return p.origen_visitante==null || p.origen_visitante===''; });
  function fila(pos, e, ronda){
    return '<div class="problema"><span class="mono" style="color:var(--ink-3);min-width:64px">'+pos+'</span>'+
      (e ? U.celdaEquipo(e) : '—')+'<span class="ayuda" style="margin-left:auto">'+ronda+'</span></div>';
  }
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Torneo Frontier</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Del 6.º al 10.º de Primera y el 1.º de Segunda, a eliminación directa y con la clasificación final. '+
      'Dos sorteos: los cuartos entre 8.º, 9.º, 10.º y 1.º de Segunda; y, jugados los cuartos, qué ganador se cruza con el 6.º y cuál con el 7.º.</p>'+
    (p1.length<10 || !s2.length
      ? '<p class="mal">Hacen falta 10 clasificados en Primera y 1 en Segunda.</p>'
      : '<div class="tabla-caja" style="margin-bottom:var(--g4)">'+
          fila('6.º', p1[5], 'semifinal')+fila('7.º', p1[6], 'semifinal')+
          fila('8.º', p1[7], 'cuartos')+fila('9.º', p1[8], 'cuartos')+fila('10.º', p1[9], 'cuartos')+
          fila('1.º Seg.', s2[0], 'cuartos')+
        '</div>'+
        '<div style="display:flex;gap:.4rem;flex-wrap:wrap">'+
          '<button class="btn btn-secondary btn-sm" data-a="sorteos:tfSortear"><i class="ph ph-shuffle"></i> '+
            (tf.previa?'Repetir sorteo':'Sortear cuartos')+'</button>'+
          (tf.previa ? '<button class="btn btn-primary btn-sm" data-a="sorteos:tfAplicar">Aplicar 5 partidos</button>'+
            '<button class="btn btn-secondary btn-sm" data-a="sorteos:tfDescartar">Descartar</button>' : '')+
          (faltanSemis && !tf.previa ? '<button class="btn btn-primary btn-sm" data-a="sorteos:tfSemis"><i class="ph ph-shuffle"></i> Sortear semifinales</button>' : '')+
        '</div>'+
        (tf.previa
          ? '<div class="tabla-caja" style="margin-top:var(--g4)">'+
              tf.previa.filter(function(p){ return p.fase==='CUARTOS DE FINAL'; }).map(function(p,i){
                return '<div class="problema"><span class="mono" style="color:var(--ink-3);min-width:90px">CUARTOS '+(i+1)+'</span>'+
                  esc(p.local)+' <span style="color:var(--ink-4)">vs</span> '+esc(p.visitante)+'</div>';
              }).join('')+'</div>'+
            (ya.length ? '<p class="mal" style="margin-top:var(--g3)"><i class="ph-bold ph-warning"></i> El Torneo ya tiene '+ya.length+' partidos; aplicar los reemplaza.</p>' : '')
          : ''));
}

/* --- Play-off desde la clasificación --------------------------------- */
function bloquePlayoff(div){
  var asc = div==='ASCENSO', ord = C.clasificacion(div), desde = asc ? 1 : 0;
  var ya = (asc ? d().partidos_ascenso : d().partidos_liga).filter(function(p){ return !C.esRegular(p); });
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">'+(asc ? 'Play-off de ascenso' : 'Play-off de la Superliga')+'</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">'+(asc
      ? 'El 1.º asciende directo y no juega. Semifinales 2.º–5.º y 3.º–4.º, en casa del mejor clasificado, y final: quien la gana asciende.'
      : 'Play-in 4.º–5.º; su ganador juega contra el 1.º y el 2.º contra el 3.º en semifinales, y final.')+
      ' Las rondas quedan encadenadas: el ganador de cada cruce se escribe solo en el siguiente.</p>'+
    (ord.length<5
      ? '<p class="mal">Hacen falta al menos 5 equipos clasificados.</p>'
      : '<div class="tabla-caja" style="margin-bottom:var(--g4)">'+
          ord.slice(desde,5).map(function(e,i){
            return '<div class="problema"><span class="mono" style="color:var(--ink-3);min-width:20px">'+(desde+i+1)+'º</span>'+
              U.celdaEquipo(e)+'<span class="mono" style="margin-left:auto">'+(e.pts||0)+' pts</span></div>';
          }).join('')+'</div>'+
        (ya.length ? '<p class="mal" style="margin-bottom:var(--g3)"><i class="ph-bold ph-warning"></i> Ya hay '+ya.length+' partidos de eliminatoria; se reemplazarán.</p>' : '')+
        '<button class="btn btn-primary btn-sm" data-a="sorteos:playoff" data-div="'+div+'">Generar '+(asc?3:4)+' eliminatorias</button>'+
        '<p class="ayuda" style="margin-top:var(--g3)">Se crean a partir de la jornada siguiente a la última del calendario, porque sin jornada la web no los mostraría en Resultados.</p>');
}

/* --- Ayudas de jornada ------------------------------------------------ */
function bloqueJornada(){
  var jor = parseInt(d().config.jornada_actual)||0;
  var ms = d().partidos_liga.concat(d().partidos_ascenso)
    .filter(function(p){ return (parseInt(p.jornada)||0)===jor; });
  var fin = ms.filter(C.isFin);

  /* Partido de la semana: el más igualado entre los pendientes por
     clasificación, o el de más goles entre los jugados. */
  var destacado = fin.length
    ? fin.slice().sort(function(a,b){
        return ((C.gl(b)+C.gv(b))-(C.gl(a)+C.gv(a))) || (Math.abs(C.gl(a)-C.gv(a))-Math.abs(C.gl(b)-C.gv(b)));
      })[0]
    : ms[0];

  /* MVP ponderado por goles y asistencias del propio partido. */
  var mvp = null;
  if(destacado){
    var ev = C.parseDetalles(destacado.detalles);
    var t = {};
    ev.local.concat(ev.visitante).forEach(function(e){
      if(e.tipo!=='gol' && e.tipo!=='asistencia') return;
      t[e.nombre] = (t[e.nombre]||0) + (e.tipo==='gol'?3:2);
    });
    var mejor = Object.keys(t).sort(function(a,b){ return t[b]-t[a]; })[0];
    if(mejor) mvp = {nombre:mejor, puntos:t[mejor]};
  }

  /* Nombre temático según la afinidad que más goles ha marcado en la jornada. */
  var af = {};
  ms.filter(C.isFin).forEach(function(p){
    var ev = C.parseDetalles(p.detalles);
    ev.local.concat(ev.visitante).forEach(function(e){
      if(e.tipo!=='gol') return;
      var f = C.findPlayer(e.nombre);
      if(f) af[C.afKey(f.j.afinidad)] = (af[C.afKey(f.j.afinidad)]||0)+1;
    });
  });
  var dominante = Object.keys(af).sort(function(a,b){ return af[b]-af[a]; })[0];
  var NOMBRES = {fuego:'Jornada de Fuego', montana:'Jornada de Montaña', bosque:'Jornada de Bosque',
                 aire:'Jornada de Aire', neutro:'Jornada Neutra'};

  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Jornada '+jor+'</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Sugerencias calculadas, para copiar. No se guarda nada.</p>'+
    (!ms.length ? '<p class="ayuda">La jornada '+jor+' no tiene partidos.</p>' :
      '<div class="tabla-caja">'+
        fila('ph-star', 'Partido de la semana', destacado
          ? (destacado.local||'?')+' '+(C.isFin(destacado)?C.gl(destacado)+'–'+C.gv(destacado):'vs')+' '+(destacado.visitante||'?')
          : '—')+
        fila('ph-medal', 'MVP sugerido', mvp ? mvp.nombre+' ('+mvp.puntos+' pts: 3 por gol, 2 por asistencia)' : 'Sin goles ni asistencias registrados')+
        fila('ph-flame', 'Nombre de jornada', dominante ? NOMBRES[dominante]+' · '+af[dominante]+' goles' : 'Sin goles registrados')+
        fila('ph-check-circle', 'Estado', fin.length+' de '+ms.length+' con resultado')+
      '</div>');
}
function fila(icono, etq, valor){
  return '<div class="problema"><i class="ph '+icono+'" style="color:var(--ink-3)"></i>'+
    '<span style="color:var(--ink-3);min-width:150px">'+esc(etq)+'</span>'+
    '<span>'+esc(valor)+'</span></div>';
}

/* --- Derbis por proximidad -------------------------------------------- */
function bloqueDerbis(){
  var porCiudad = {};
  d().equipos.filter(function(e){ return !e.archivado && (e.ciudad||'').trim(); })
    .forEach(function(e){
      var k = C.norm(e.ciudad);
      (porCiudad[k] = porCiudad[k]||{ciudad:e.ciudad, eq:[]}).eq.push(e);
    });
  var derbis = Object.keys(porCiudad).map(function(k){ return porCiudad[k]; })
    .filter(function(x){ return x.eq.length>1; });

  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Derbis sugeridos</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Clubes que comparten ciudad. La web sólo etiqueta como derbi los que tiene escritos en su lista <span class="mono">RIVALIDADES</span>, que hoy es sólo Alpino – Academia Plenilunio.</p>'+
    (derbis.length
      ? '<div class="tabla-caja">'+derbis.map(function(x){
          return '<div class="problema"><i class="ph ph-map-pin" style="color:var(--accent)"></i>'+
            '<span style="color:var(--ink-3);min-width:110px">'+esc(x.ciudad)+'</span>'+
            '<span>'+x.eq.map(function(e){ return esc(e.nombre); }).join(' · ')+'</span></div>';
        }).join('')+'</div>'
      : '<p class="ayuda">Ningún par de clubes activos comparte ciudad. '+
        d().equipos.filter(function(e){ return !e.archivado && !(e.ciudad||'').trim(); }).length+
        ' clubes no tienen ciudad puesta.</p>');
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
var A = {
  calDiv:     function(el){ cal.div = el.value; cal.previa = null; U.refrescar(); },
  calVueltas: function(el){ cal.vueltas = Number(el.value); cal.previa = null; U.refrescar(); },
  calDesde:   function(el){ cal.desde = Math.max(1, Number(el.value)||1); cal.previa = null; U.refrescar(); },
  calDescartar: function(){ cal.previa = null; U.refrescar(); },
  calSortear: function(){
    cal.semilla = semillaNueva();
    cal.previa = C.generarCalendario(activos(cal.div), {vueltas:cal.vueltas, jornadaInicial:cal.desde, semilla:cal.semilla});
    U.refrescar();
  },
  calAplicar: function(){
    var clave = cal.div==='ASCENSO' ? 'partidos_ascenso' : 'partidos_liga';
    var existentes = d()[clave].filter(C.esRegular);
    var hacer = function(reemplazar){
      var D = d();
      if(reemplazar) D[clave] = D[clave].filter(function(p){ return !C.esRegular(p); });
      D[clave] = D[clave].concat(cal.previa);
      cal.previa = null;
      U.cambio();
      U.aviso('Calendario generado. Revísalo en Partidos antes de guardar.', 'ok', 7000);
    };
    if(!existentes.length) return hacer(false);
    U.modal({
      titulo:'La división ya tiene calendario',
      cuerpo:'<p style="font-size:.875rem;color:var(--ink-2);line-height:1.6">'+
        'Hay <b>'+existentes.length+' partidos de jornada regular</b> en '+esc(cal.div)+
        ', de los cuales '+existentes.filter(C.isFin).length+' tienen resultado.<br><br>'+
        'Las eliminatorias no se tocan en ningún caso.</p>',
      pie:[
        {txt:'Cancelar', fn:U.cerrarModal},
        {txt:'Añadir al final', cls:'btn-secondary', fn:function(){ U.cerrarModal(); hacer(false); }},
        {txt:'Reemplazar', cls:'btn-accent', fn:function(){
          U.cerrarModal();
          U.confirmar({titulo:'Reemplazar el calendario', texto:'Se perderán '+existentes.filter(C.isFin).length+' resultados ya cargados.', ok:'Reemplazar', peligro:true})
            .then(function(si){ if(si) hacer(true); });
        }}
      ]
    });
  },

  ffEq:        function(el){ ff.sel[el.dataset.n] = el.checked; ff.previa = null; U.refrescar(); },
  ffDescartar: function(){ ff.previa = null; U.refrescar(); },
  ffSortear: function(){
    var prelim = preliminarElegida();
    ff.semilla = semillaNueva();
    var r = C.generarFutbolFrontier(prelim, d().config.grupos_copa, {
      semilla: ff.semilla,
      obligados: activos().filter(function(n){ return prelim.indexOf(n)<0; })
    });
    if(r.avisos.length) return U.aviso(r.avisos[0], 'ojo', 8000);
    ff.previa = r.partidos;
    U.refrescar();
  },
  ffAplicar: function(){
    var previos = d().partidos_copa.length;
    var seguir = function(){
      d().partidos_copa = ff.previa;
      ff.previa = null;
      U.cambio();
      U.aviso('Fútbol Frontier generado. Revísalo en Copa antes de guardar.', 'ok', 7000);
    };
    if(!previos) return seguir();
    U.confirmar({
      titulo:'Reemplazar Fútbol Frontier',
      html:'Se borran los <b>'+previos+' partidos</b> actuales y sus resultados.',
      ok:'Reemplazar', peligro:true
    }).then(function(si){ if(si) seguir(); });
  },

  tfDescartar: function(){ tf.previa = null; U.refrescar(); },
  tfSortear: function(){
    var nombres = function(e){ return e.nombre; };
    tf.semilla = semillaNueva();
    var r = C.generarTorneoFrontier(C.clasificacion('SUPERLIGA').map(nombres), C.clasificacion('ASCENSO').map(nombres), {semilla:tf.semilla});
    if(r.avisos.length) return U.aviso(r.avisos[0], 'ojo');
    tf.previa = r.partidos;
    U.refrescar();
  },
  tfAplicar: function(){
    var previos = (d().partidos_torneo||[]).length;
    var seguir = function(){
      d().partidos_torneo = tf.previa;
      tf.previa = null;
      U.cambio();
      U.aviso('Torneo Frontier generado. Cuando se jueguen los cuartos, vuelve aquí para sortear las semifinales.', 'ok', 8000);
    };
    if(!previos) return seguir();
    U.confirmar({
      titulo:'Reemplazar el Torneo Frontier',
      html:'Se borran los <b>'+previos+' partidos</b> actuales y sus resultados.',
      ok:'Reemplazar', peligro:true
    }).then(function(si){ if(si) seguir(); });
  },
  tfSemis: function(){
    U.confirmar({
      titulo:'Sorteo de semifinales del Torneo Frontier',
      html:'Se sortea qué ganador de cuartos juega contra el 6.º y cuál contra el 7.º. El resultado queda fijado en el cuadro.',
      ok:'Sortear'
    }).then(function(si){
      if(!si) return;
      if(!C.sortearSemisTorneo(d().partidos_torneo||[], semillaNueva()))
        return U.aviso('El Torneo no tiene 2 cuartos y 2 semifinales.', 'ojo');
      U.cambio();
      U.aviso('Semifinales del Torneo Frontier sorteadas.', 'ok');
    });
  },

  playoff: function(el){
    var div = el.dataset.div, asc = div==='ASCENSO', ord = C.clasificacion(div);
    if(ord.length<5) return;
    var D = d(), clave = asc ? 'partidos_ascenso' : 'partidos_liga';
    var regulares = D[clave].filter(C.esRegular), b = regulares.length;
    var j = regulares.reduce(function(m,p){ return Math.max(m, parseInt(p.jornada)||0); }, 0)+1;
    var n = function(i){ return ord[i].nombre; };
    /* origen_* apunta por índice dentro de la lista final, y los nuevos van
       detrás de los regulares: b es donde empiezan. */
    var nuevos = asc
      ? [cruce('SEMIFINALES', n(1), n(4), j), cruce('SEMIFINALES', n(2), n(3), j),
         cruce('FINAL', '', '', j+1, b, b+1)]
      : [cruce('PLAY IN', n(3), n(4), j), cruce('SEMIFINALES', n(0), '', j+1, null, b),
         cruce('SEMIFINALES', n(1), n(2), j+1), cruce('FINAL', '', '', j+2, b+1, b+2)];
    U.confirmar({
      titulo: asc ? 'Generar el Play-off de ascenso' : 'Generar el Play-off de la Superliga',
      html:'Se crean <b>'+nuevos.length+' eliminatorias</b> a partir de la jornada '+j+'.<br><br>'+
        'Los cruces que dependen de otro nacen sin equipo y se rellenan solos en cuanto hay ganador, tanto desde el gestor como desde el bot.',
      ok:'Generar'
    }).then(function(si){
      if(!si) return;
      D[clave] = regulares.concat(nuevos);
      U.cambio();
      U.aviso('Play-off generado desde la jornada '+j+'.', 'ok', 7000);
    });
  }
};
function cruce(fase, local, visitante, jornada, origenLocal, origenVisitante){
  var p = {jornada:String(jornada), fase:fase, fecha:'', estado:'PENDIENTE',
           local:local, visitante:visitante, goles_l:0, goles_v:0, detalles:' / '};
  if(origenLocal!=null) p.origen_local = origenLocal;
  if(origenVisitante!=null) p.origen_visitante = origenVisitante;
  return p;
}

U.registrar('sorteos', {acciones:A, render:pintar});

})();
