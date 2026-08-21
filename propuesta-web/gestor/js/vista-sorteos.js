/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-sorteos.js
   Generadores de calendario, sorteo de Copa y ayudas de jornada.

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
var copa = {tipo:'directa', grupos:4, idaVuelta:false, evitarRiv:true, siembra:true,
            semilla:semillaNueva(), previa:null, sel:{}};

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
    '<div class="g-hueco"></div>'+bloqueCopa()+
    '<div class="g-hueco"></div>'+bloquePlayoff()+
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

/* --- Sorteo de Copa --------------------------------------------------- */
function bloqueCopa(){
  var todos = activos();
  var elegidos = todos.filter(function(n){ return copa.sel[n]!==false; });
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Sorteo de Copa</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">En eliminatoria directa, los cruces quedan encadenados por '+
      '<span class="mono">origen_local</span> y <span class="mono">origen_visitante</span>: el ganador de cada ronda pasa solo a la siguiente.</p>'+

    '<div class="rejilla rejilla-4" style="margin-bottom:var(--g4)">'+
      U.campo('Formato', '<select class="inp" data-c="sorteos:copaTipo">'+
        [['directa','Eliminatoria directa'],['grupos','Fase de grupos']].map(function(t){
          return '<option value="'+t[0]+'"'+(copa.tipo===t[0]?' selected':'')+'>'+t[1]+'</option>'; }).join('')+'</select>')+
      (copa.tipo==='grupos'
        ? U.campo('Grupos', '<input class="inp inp-mono" type="number" min="1" max="12" value="'+copa.grupos+'" data-c="sorteos:copaGrupos">')+
          U.campo('Ida y vuelta', '<label class="sw"><input type="checkbox"'+(copa.idaVuelta?' checked':'')+' data-c="sorteos:copaIV"><span class="pista"></span> Doble</label>')
        : U.campo('Siembra', '<label class="sw"><input type="checkbox"'+(copa.siembra?' checked':'')+' data-c="sorteos:copaSiembra"><span class="pista"></span> Por clasificación</label>',
            'Los mejores entran más tarde y se cruzan con los peores')+
          U.campo('Rivalidades', '<label class="sw"><input type="checkbox"'+(copa.evitarRiv?' checked':'')+' data-c="sorteos:copaRiv"><span class="pista"></span> Evitar en la previa</label>',
            'Alpino – Academia Plenilunio'))+
      U.campo('Inscritos', '<div class="inp" style="display:flex;align-items:center;color:var(--ink-3)">'+elegidos.length+' de '+todos.length+'</div>')+
    '</div>'+

    '<details style="margin-bottom:var(--g4)"><summary style="cursor:pointer;font-size:.8125rem;color:var(--ink-2)">Elegir quién participa</summary>'+
      '<div class="rejilla" style="--min:190px;margin-top:var(--g3)">'+
        todos.map(function(n){
          return '<label class="sw"><input type="checkbox"'+(copa.sel[n]!==false?' checked':'')+
            ' data-c="sorteos:copaEq" data-n="'+esc(n)+'"><span class="pista"></span> '+esc(n)+'</label>';
        }).join('')+
      '</div></details>'+

    (elegidos.length<2 ? '<p class="mal">Hacen falta al menos dos equipos inscritos.</p>' :
      '<div style="display:flex;gap:.4rem;flex-wrap:wrap">'+
        '<button class="btn btn-secondary btn-sm" data-a="sorteos:copaSortear"><i class="ph ph-shuffle"></i> '+
          (copa.previa?'Repetir sorteo':'Sortear')+'</button>'+
        (copa.previa ? '<button class="btn btn-primary btn-sm" data-a="sorteos:copaAplicar">Aplicar '+copa.previa.partidos.length+' cruces</button>'+
          '<button class="btn btn-secondary btn-sm" data-a="sorteos:copaDescartar">Descartar</button>' : '')+
      '</div>'+
      (copa.previa ? previaCopa() : ''));
}
function previaCopa(){
  var r = copa.previa;
  var porFase = {};
  r.partidos.forEach(function(p, i){ (porFase[p.fase] = porFase[p.fase]||[]).push({p:p, i:i}); });
  var orden = C.FASES_TODAS.filter(function(f){ return porFase[f]; });
  return '<div style="margin-top:var(--g4)">'+
    (r.avisos||[]).map(function(a){
      return '<p class="mal" style="margin-bottom:var(--g2)"><i class="ph-bold ph-warning"></i> '+esc(a)+'</p>'; }).join('')+
    (d().partidos_copa.length
      ? '<p class="mal" style="margin-bottom:var(--g3)"><i class="ph-bold ph-warning"></i> '+
        'La Copa ya tiene '+d().partidos_copa.length+' cruces. Aplicar los reemplaza por completo, con sus resultados.</p>'
      : '')+
    '<div style="display:flex;gap:var(--g5);overflow-x:auto;padding-bottom:var(--g2)">'+
      orden.map(function(f){
        return '<div style="min-width:200px;flex-shrink:0">'+
          '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">'+esc(f)+'</div>'+
          porFase[f].map(function(o){
            function lado(k, ok){
              if(o.p[ok]!=null) return '<span style="color:var(--ink-4)"><i class="ph ph-arrow-elbow-down-right"></i> ganador #'+o.p[ok]+'</span>';
              return esc(o.p[k]||'—');
            }
            return '<div class="dnd-ficha" style="cursor:default;flex-direction:column;align-items:flex-start;gap:.15rem">'+
              '<span class="mono" style="font-size:.5625rem;color:var(--ink-4)">#'+o.i+(o.p.grupo?' · grupo '+esc(o.p.grupo):'')+'</span>'+
              '<span class="nm">'+lado('local','origen_local')+'</span>'+
              '<span class="nm">'+lado('visitante','origen_visitante')+'</span></div>';
          }).join('')+'</div>';
      }).join('')+
    '</div></div>';
}

/* --- Play-off desde la clasificación ---------------------------------- */
function bloquePlayoff(){
  var ord = C.clasificacion('SUPERLIGA');
  var z = C.ZONAS_APP.SUPERLIGA;
  var ya = d().partidos_liga.filter(function(p){ return !C.esRegular(p); });
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Play-off de la Superliga</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Genera los cruces con la estructura que ya dibuja la web: '+
      '5.º–6.º, el ganador contra el 4.º, y las semifinales del 1.º y del 2.º–3.º.</p>'+
    (ord.length<6
      ? '<p class="mal">Hacen falta al menos 6 equipos clasificados.</p>'
      : '<div class="tabla-caja" style="margin-bottom:var(--g4)">'+
          ord.slice(0,6).map(function(e,i){
            return '<div class="problema"><span class="mono" style="color:var(--ink-3);min-width:20px">'+(i+1)+'º</span>'+
              U.celdaEquipo(e)+'<span class="mono" style="margin-left:auto">'+(e.pts||0)+' pts</span></div>';
          }).join('')+'</div>'+
        (ya.length ? '<p class="mal" style="margin-bottom:var(--g3)"><i class="ph-bold ph-warning"></i> Ya hay '+ya.length+' partidos de eliminatoria; se reemplazarán.</p>' : '')+
        '<button class="btn btn-primary btn-sm" data-a="sorteos:playoff">Generar 4 eliminatorias</button>'+
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

  copaTipo:    function(el){ copa.tipo = el.value; copa.previa = null; U.refrescar(); },
  copaGrupos:  function(el){ copa.grupos = Math.max(1, Math.min(12, Number(el.value)||4)); copa.previa = null; U.refrescar(); },
  copaIV:      function(el){ copa.idaVuelta = el.checked; copa.previa = null; U.refrescar(); },
  copaSiembra: function(el){ copa.siembra = el.checked; copa.previa = null; U.refrescar(); },
  copaRiv:     function(el){ copa.evitarRiv = el.checked; copa.previa = null; U.refrescar(); },
  copaEq:      function(el){ copa.sel[el.dataset.n] = el.checked; copa.previa = null; U.refrescar(); },
  copaDescartar: function(){ copa.previa = null; U.refrescar(); },
  copaSortear: function(){
    var inscritos = activos().filter(function(n){ return copa.sel[n]!==false; });
    /* Con siembra, el orden de entrada es el de la clasificación de las dos
       divisiones: es la referencia de fuerza que hay en el archivo. */
    if(copa.siembra){
      var orden = C.clasificacion('SUPERLIGA').concat(C.clasificacion('ASCENSO')).map(function(e){ return e.nombre; });
      inscritos.sort(function(a,b){
        var ia = orden.indexOf(a), ib = orden.indexOf(b);
        return (ia<0?999:ia) - (ib<0?999:ib);
      });
    }
    copa.semilla = semillaNueva();
    copa.previa = C.generarCopa(inscritos, {
      tipo: copa.tipo, grupos: copa.grupos, ida_vuelta: copa.idaVuelta,
      siembra: copa.siembra, semilla: copa.semilla,
      /* La única rivalidad que la web reconoce está escrita en su código. */
      rivalidades: copa.evitarRiv ? [['Alpino','Academia Plenilunio']] : []
    });
    U.refrescar();
  },
  copaAplicar: function(){
    var previos = d().partidos_copa.length;
    var seguir = function(){
      var D = d();
      D.partidos_copa = copa.previa.partidos;
      /* En fase de grupos, el reparto también se guarda en config para que la
         pestaña de Copa lo enseñe y se pueda retocar arrastrando. */
      if(copa.previa.reparto) D.config.grupos_copa = copa.previa.reparto;
      copa.previa = null;
      U.cambio();
      U.aviso('Sorteo aplicado. Revísalo en Copa antes de guardar.', 'ok', 7000);
    };
    if(!previos) return seguir();
    U.confirmar({
      titulo:'Reemplazar el cuadro de Copa',
      html:'Se borran los <b>'+previos+' cruces</b> actuales y sus resultados. '+
        'Las vinculaciones entre rondas se rehacen desde cero.',
      ok:'Reemplazar', peligro:true
    }).then(function(si){ if(si) seguir(); });
  },

  playoff: function(){
    var ord = C.clasificacion('SUPERLIGA');
    if(ord.length<6) return;
    var D = d();
    var maxJ = D.partidos_liga.reduce(function(m,p){ return Math.max(m, parseInt(p.jornada)||0); }, 0);
    var j = maxJ+1;
    /* La misma estructura que renderPlayoff() de app.js dibuja a partir de la
       clasificación. Los cruces posteriores quedan sin equipos porque dependen
       de resultados que aún no existen; se rellenan al jugarse. */
    var nuevos = [
      cruce('PARTIDO POR EL PLAY IN', ord[4].nombre, ord[5].nombre, j),
      cruce('PLAY IN', ord[3].nombre, '', j+1),
      cruce('SEMIFINALES', ord[1].nombre, ord[2].nombre, j+2),
      cruce('SEMIFINALES', ord[0].nombre, '', j+2),
      cruce('FINAL', '', '', j+3)
    ];
    U.confirmar({
      titulo:'Generar el play-off',
      html:'Se crean <b>'+nuevos.length+' eliminatorias</b> en las jornadas '+j+' a '+(j+3)+'.<br><br>'+
        'Los cruces que dependen de un resultado anterior nacen sin equipos: los rellenas al jugarse.<br><br>'+
        '<span style="color:var(--gold)">Aviso:</span> la web muestra la etiqueta de fase en Resultados, pero su cuadro de play-off '+
        'lo dibuja desde la clasificación, no desde estos partidos.',
      ok:'Generar'
    }).then(function(si){
      if(!si) return;
      D.partidos_liga = D.partidos_liga.filter(C.esRegular).concat(nuevos);
      U.cambio();
      U.aviso('Play-off generado en las jornadas '+j+'–'+(j+3)+'.', 'ok', 7000);
    });
  }
};
function cruce(fase, local, visitante, jornada){
  return {jornada:String(jornada), fase:fase, fecha:'', estado:'PENDIENTE',
          local:local, visitante:visitante, goles_l:0, goles_v:0, detalles:' / '};
}

U.registrar('sorteos', {acciones:A, render:pintar});

})();
