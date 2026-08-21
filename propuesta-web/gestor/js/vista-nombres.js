/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-nombres.js
   Corrector de consistencia de nombres.

   Por qué existe: los eventos de un partido guardan el nombre del jugador
   COMO TEXTO, y la web lo resuelve con findPlayer(), que acepta el nombre
   completo, el primer nombre o un prefijo. Una errata no da error — engancha
   el gol a otro jugador y nadie se entera. Esta pantalla busca las cinco
   formas de que eso ocurra y ofrece unificar.

   El análisis vive en core.analizarNombres(); aquí sólo se pinta y se decide.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

/* La comparación por parejas de los 791 jugadores tarda medio segundo largo,
   así que no se hace en cada repintado: se pide con un botón y se recuerda. */
var parejas = null;

function d(){ return SFG.d(); }

function pintar(el){
  var r = C.analizarNombres(d(), {parejas:false});
  var sinNombre = [];
  d().equipos.forEach(function(e){
    (e.jugadores||[]).forEach(function(j,k){
      if(!String(j.nombre||'').trim()) sinNombre.push({e:e, j:j, k:k});
    });
  });
  var problemas = r.huerfanos.length + r.difusos.length + r.ambiguos.length +
                  r.otroClub.length + sinNombre.length;

  el.innerHTML =
    U.cabecera('Nombres', r.nombresDistintos+' nombres distintos en '+r.eventos+' eventos de partido')+

    '<div class="card" style="padding:var(--g5);margin-bottom:var(--g5);border-color:'+
      (problemas?'rgba(255,201,74,.3)':'rgba(70,180,95,.3)')+'">'+
      '<div style="display:flex;gap:var(--g3);align-items:flex-start">'+
        '<i class="'+(problemas?'ph-bold ph-warning" style="color:var(--gold)':'ph-bold ph-check-circle" style="color:#6FD98A')+
          ';font-size:1.25rem;flex-shrink:0;margin-top:.1rem"></i>'+
        '<div><b style="font-size:.9375rem">'+
          (problemas ? problemas+' cosas que revisar' : 'Los nombres de los eventos cuadran con las plantillas')+'</b>'+
          '<p class="ayuda" style="margin-top:.25rem">La web enlaza cada gol con su jugador comparando el nombre en texto, '+
            'aceptando también el primer nombre o un prefijo. Por eso una errata no da error: cuelga el gol de otro.</p>'+
        '</div></div></div>'+

    bloqueOtroClub(r.otroClub)+
    bloqueTraspasos(r.traspasos)+
    bloqueHuerfanos(r.huerfanos)+
    bloqueDifusos(r.difusos)+
    bloqueAmbiguos(r.ambiguos)+
    bloqueSinNombre(sinNombre)+
    bloqueParejas();
}

/* --- 1. Goles colgados de un jugador de otro club ---------------------- */
function bloqueOtroClub(lista){
  return caja('Atribuidos a un jugador de otro club', lista.length, lista.length?'mal':'ok',
    'El club que anotó el evento no tiene a ese jugador en plantilla, así que la web enseña la ficha, la foto y el enlace de otro.',
    lista.length
      ? '<div class="tabla-caja">'+lista.map(function(x,i){
          return '<div class="problema err"><i class="ph-bold ph-warning-octagon"></i>'+
            '<span><b>'+esc(x.nombre)+'</b> marcó para <b>'+esc(x.anotadoPor)+'</b>, '+
              'pero la web lo resuelve al '+esc(x.nombre)+' del <b>'+esc(x.clubReal)+'</b>'+
              (x.plantillaVacia ? ' · <span class="pastilla pastilla-ojo">'+esc(x.anotadoPor)+' no tiene plantilla</span>' : '')+
            '</span>'+
            '<button class="ir" data-a="nombres:corregir" data-i="'+i+'">Corregir</button>'+
            '<button class="ir" data-a="nombres:irPartido" data-comp="'+esc(x.comp)+'" data-idx="'+x.idx+'">Partido</button></div>';
        }).join('')+'</div>'+
        '<p class="ayuda" style="margin-top:var(--g3)">Suele tener dos causas: el club se quedó sin plantilla al archivarse, '+
        'o el nombre del evento está mal escrito. Lo primero se arregla dando de alta al jugador; lo segundo, unificando el nombre.</p>'
      : '<p class="ayuda">Todos los eventos apuntan a un jugador del club que los anotó.</p>');
}

/* --- 1b. Goles con otra camiseta, explicados por un traspaso ----------
   No son un fallo: en esta liga se ficha a mitad de temporada, así que marcar
   para un club y acabar en otro es lo normal. Se separan de los sospechosos
   porque el historial del jugador lo respalda. */
function bloqueTraspasos(lista){
  if(!lista.length) return '';
  return caja('Goles con la camiseta anterior', lista.length, 'ok',
    'El jugador marcó para otro club y después fichó. Su historial lo confirma, así que el dato está bien: se listan sólo para que no sorprenda verlos.',
    '<div class="tabla-caja">'+lista.map(function(x){
      var t = x.etapa && (x.etapa.temporada_inicio||x.etapa.temporada||'');
      return '<div class="problema"><i class="ph ph-arrows-left-right" style="color:var(--ink-3)"></i>'+
        '<span><b>'+esc(x.nombre)+'</b> marcó para <b>'+esc(x.anotadoPor)+'</b>'+
          (t?' en '+esc(String(t)):'')+' y ahora está en <b>'+esc(x.clubReal)+'</b></span>'+
        '<button class="ir" data-a="nombres:irPartido" data-comp="'+esc(x.comp)+'" data-idx="'+x.idx+'">Partido</button></div>';
    }).join('')+'</div>');
}

/* --- 2. Nombres que no encajan con nadie ------------------------------- */
function bloqueHuerfanos(lista){
  if(!lista.length) return caja('Sin jugador', 0, 'ok', '',
    '<p class="ayuda">Ningún evento apunta a un nombre que no exista.</p>');
  return caja('Sin jugador', lista.length, 'mal',
    'Estos nombres no coinciden con ningún jugador. La web los muestra como texto suelto, sin foto ni ficha, y no suman en el ranking.',
    '<div class="tabla-caja">'+lista.map(function(x){
      return '<div class="problema err"><i class="ph-bold ph-warning-octagon"></i>'+
        '<span><b>'+esc(x.nombre)+'</b> · '+esc(x.ejemplo.club||'?')+'</span>'+
        '<button class="ir" data-a="nombres:unificar" data-n="'+esc(x.nombre)+'">Unificar</button></div>';
    }).join('')+'</div>');
}

/* --- 3. Nombres que sólo casan de forma difusa ------------------------- */
function bloqueDifusos(lista){
  if(!lista.length) return caja('Sólo por coincidencia parcial', 0, 'ok', '',
    '<p class="ayuda">Ningún evento depende de una coincidencia parcial: todos escriben el nombre completo.</p>');
  return caja('Sólo por coincidencia parcial', lista.length, 'ojo',
    'No existe ningún jugador con ese nombre exacto. La web lo engancha por el primer nombre o por prefijo, que funciona hasta que dos jugadores empiezan igual.',
    '<div class="tabla-caja">'+lista.map(function(x){
      return '<div class="problema avi"><i class="ph-bold ph-warning"></i>'+
        '<span><b>'+esc(x.nombre)+'</b> se resuelve a <b>'+esc(x.resuelve.nombre)+'</b> ('+esc(x.club)+')</span>'+
        '<button class="ir" data-a="nombres:unificarA" data-n="'+esc(x.nombre)+'" data-a2="'+esc(x.resuelve.nombre)+'">Escribir el nombre completo</button></div>';
    }).join('')+'</div>');
}

/* --- 4. Nombres que llevan dos jugadores ------------------------------- */
function bloqueAmbiguos(lista){
  if(!lista.length) return caja('Nombres repetidos', 0, 'ok', '',
    '<p class="ayuda">Ningún nombre lo llevan dos jugadores a la vez.</p>');
  return caja('Nombres repetidos', lista.length, 'mal',
    'Dos jugadores distintos comparten nombre. findPlayer() devuelve el primero que encuentra, así que sus goles pueden acabar en el jugador equivocado.',
    '<div class="tabla-caja">'+lista.map(function(x){
      return '<div class="problema err"><i class="ph-bold ph-warning-octagon"></i>'+
        '<span><b>'+esc(x.nombre)+'</b> está en '+x.donde.map(function(c){ return esc(c||'(sin club)'); }).join(' y en ')+'</span></div>';
    }).join('')+'</div>'+
    '<p class="ayuda" style="margin-top:var(--g3)">Hay que diferenciarlos: añade el apellido o el club a uno de los dos desde su ficha.</p>');
}

/* --- 5. Jugadores sin nombre ------------------------------------------- */
function bloqueSinNombre(lista){
  if(!lista.length) return '';
  return caja('Jugadores sin nombre', lista.length, 'ojo',
    'Una ficha sin nombre no se puede enlazar con ningún gol y sale en blanco en la plantilla de la web.',
    '<div class="tabla-caja">'+lista.map(function(x){
      return '<div class="problema avi"><i class="ph-bold ph-warning"></i>'+
        '<span>'+esc(x.e.nombre)+' · dorsal '+esc(x.j.dorsal||'—')+' · '+esc(x.j.posicion||'—')+'</span>'+
        '<button class="ir" data-a="nombres:irEquipo" data-id="'+esc(x.e.id)+'">Abrir el club</button></div>';
    }).join('')+'</div>');
}

/* --- 6. Jugadores con nombre casi igual -------------------------------- */
function bloqueParejas(){
  if(!parejas) return caja('Jugadores con nombre casi igual', null, '',
    'Busca fichas que podrían ser la misma persona escrita de dos formas. Compara los nombres por parejas, así que tarda un momento.',
    '<button class="btn btn-secondary btn-sm" data-a="nombres:buscarParejas"><i class="ph ph-magnifying-glass"></i> Buscar parecidos</button>');

  return caja('Jugadores con nombre casi igual', parejas.length, parejas.length?'ojo':'ok',
    'Fichas que podrían ser la misma persona escrita de dos formas. Ojo: dos jugadores pueden llamarse parecido de verdad.',
    (parejas.length
      ? '<div class="tabla-caja">'+parejas.slice(0,30).map(function(x,i){
          return '<div class="problema avi"><i class="ph-bold ph-warning"></i>'+
            '<span><b>'+esc(x.a.j.nombre)+'</b> ('+esc(x.a.libre?'agente libre':x.a.club)+')'+
            '  ~  <b>'+esc(x.b.j.nombre)+'</b> ('+esc(x.b.libre?'agente libre':x.b.club)+')</span>'+
            '<span class="mono" style="color:var(--ink-3);font-size:.75rem">'+Math.round(x.similitud*100)+'%</span>'+
            '<button class="ir" data-a="nombres:fusionar" data-i="'+i+'">Ver</button></div>';
        }).join('')+'</div>'+
        (parejas.length>30 ? '<p class="ayuda" style="margin-top:var(--g3)">y '+(parejas.length-30)+' parejas más.</p>' : '')
      : '<p class="ayuda">Ninguna pareja de nombres se parece lo bastante como para sospechar.</p>')+
    '<button class="btn btn-secondary btn-sm" style="margin-top:var(--g3)" data-a="nombres:buscarParejas">Volver a buscar</button>');
}

function caja(titulo, n, cls, ayuda, cuerpo){
  return '<div class="card" style="padding:var(--g5);margin-bottom:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:'+(ayuda?'.35rem':'var(--g4)')+';flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">'+esc(titulo)+'</h3>'+
      (n!=null ? '<span class="pastilla'+(cls?' pastilla-'+cls:'')+'">'+n+'</span>' : '')+
    '</div>'+
    (ayuda ? '<p class="ayuda" style="margin-bottom:var(--g4)">'+esc(ayuda)+'</p>' : '')+
    cuerpo+
  '</div>';
}

/* --------------------------------------------------------------------------
   UNIFICAR
   -------------------------------------------------------------------------- */
/* Candidatos ordenados por parecido: lo que se busca casi siempre está en las
   tres primeras filas, y así no hay que recorrer 791 nombres. */
function candidatos(nombre, club){
  var out = [];
  d().equipos.forEach(function(e){
    (e.jugadores||[]).forEach(function(j){
      if(!String(j.nombre||'').trim()) return;
      out.push({j:j, club:e.nombre, mismoClub:e.nombre===club, s:C.parecido(nombre, j.nombre)});
    });
  });
  return out.sort(function(a,b){
    /* Primero los del club que anotó: es donde debería estar el jugador. */
    return (b.mismoClub-a.mismoClub) || (b.s-a.s);
  }).slice(0, 40);
}

function abrirUnificar(nombre, club, sugerido){
  var lista = candidatos(nombre, club);
  var eventos = contarEventos(nombre);
  U.modal({
    titulo:'Unificar «'+nombre+'»',
    ancho:true,
    cuerpo:
      '<p class="ayuda" style="margin-bottom:var(--g4)">Ese nombre aparece en <b>'+eventos+' eventos</b> de partido'+
        (club?' (el último, anotado por '+esc(club)+')':'')+'. '+
        'Al unificar se reescribe en todos ellos y se regeneran los textos de goleadores. '+
        '<b>No</b> se toca ninguna ficha de jugador.</p>'+
      U.campo('Escribir en los eventos',
        '<input class="inp" id="uni-nombre" value="'+esc(sugerido||nombre)+'">',
        'Tiene que coincidir exactamente con el nombre de la ficha del jugador.')+
      '<div class="g-hueco"></div>'+
      '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">'+
        'JUGADORES PARECIDOS'+(club?' · PRIMERO LOS DE '+esc(club).toUpperCase():'')+'</div>'+
      '<div class="tabla-caja" style="max-height:260px;overflow-y:auto">'+
        lista.map(function(x){
          return '<button class="problema" style="width:100%;text-align:left" data-a="nombres:elegir" data-n="'+esc(x.j.nombre)+'">'+
            '<span>'+esc(x.j.nombre)+'</span>'+
            '<span style="color:var(--ink-3);font-size:.75rem;margin-left:1rem">'+esc(x.club)+
              (x.mismoClub?' <span class="pastilla pastilla-ok">su club</span>':'')+'</span>'+
            '<span class="mono" style="margin-left:auto;color:var(--ink-3);font-size:.75rem">'+Math.round(x.s*100)+'%</span>'+
          '</button>';
        }).join('')+
      '</div>',
    pie:[
      {txt:'Cancelar', fn:U.cerrarModal},
      {txt:'Reescribir en los eventos', cls:'btn-primary', fn:function(){
        var nuevo = (document.getElementById('uni-nombre').value||'').trim();
        if(!nuevo) return U.aviso('Escribe un nombre.', 'ojo');
        var existe = C.findPlayer(nuevo);
        var seguir = function(){
          U.cerrarModal();
          var n = C.renombrarEnEventos(d(), nombre, nuevo);
          U.cambio();
          U.aviso(n+' eventos reescritos como «'+nuevo+'».', 'ok');
        };
        if(existe && C.norm(existe.j.nombre)===C.norm(nuevo)) return seguir();
        /* Si el nombre nuevo tampoco casa con nadie, se avisa en vez de
           cambiar un problema por otro. */
        U.confirmar({
          titulo:'Ese nombre tampoco existe',
          html:'No hay ningún jugador que se llame exactamente <b>'+esc(nuevo)+'</b>'+
            (existe ? ', aunque la web lo resolvería a <b>'+esc(existe.j.nombre)+'</b> por parecido.' : '.')+
            '<br><br>Los goles seguirían sin engancharse a una ficha. ¿Aun así?',
          ok:'Reescribir igualmente', peligro:true
        }).then(function(si){ if(si) seguir(); });
      }}
    ]
  });
}
function contarEventos(nombre){
  var n = 0, k = C.norm(nombre);
  ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(clave){
    d()[clave].forEach(function(p){
      var ev = C.parseDetalles(p.detalles);
      ev.local.concat(ev.visitante).forEach(function(e){ if(C.norm(e.nombre)===k) n++; });
    });
  });
  return n;
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
var A = {
  buscarParejas: function(){
    U.aviso('Comparando los nombres por parejas…', 'info', 1500);
    /* Se cede un fotograma para que el aviso llegue a pintarse antes de
       bloquear el hilo medio segundo. */
    setTimeout(function(){
      parejas = C.analizarNombres(d(), {parejas:true}).parecidos;
      U.refrescar();
      U.aviso(parejas.length ? parejas.length+' parejas parecidas.' : 'Ninguna pareja sospechosa.', 'ok');
    }, 60);
  },

  corregir: function(el){
    var x = C.analizarNombres(d(), {parejas:false}).otroClub[Number(el.dataset.i)];
    if(!x) return U.refrescar();
    abrirUnificar(x.nombre, x.anotadoPor);
  },
  unificar:   function(el){ abrirUnificar(el.dataset.n, ''); },
  unificarA:  function(el){ abrirUnificar(el.dataset.n, '', el.dataset.a2); },
  elegir:     function(el){
    var i = document.getElementById('uni-nombre');
    if(i) i.value = el.dataset.n;
  },

  fusionar: function(el){
    var x = parejas && parejas[Number(el.dataset.i)];
    if(!x) return;
    var ea = contarEventos(x.a.j.nombre), eb = contarEventos(x.b.j.nombre);
    U.modal({
      titulo:'¿La misma persona?',
      ancho:true,
      cuerpo:
        '<p class="ayuda" style="margin-bottom:var(--g4)">Dos fichas con nombre parecido. '+
          'Pueden ser la misma persona escrita de dos formas, o dos jugadores distintos que se llaman parecido. '+
          '<b>Sólo tú puedes saberlo.</b></p>'+
        '<div class="rejilla rejilla-2">'+
          [x.a, x.b].map(function(y, k){
            var ev = k===0?ea:eb;
            return '<div class="card" style="padding:var(--g4)">'+
              '<div style="font-size:.9375rem;font-weight:600;margin-bottom:.35rem">'+esc(y.j.nombre)+'</div>'+
              '<p class="ayuda">'+esc(y.libre?'Agente libre':y.club)+' · dorsal '+esc(y.j.dorsal||'—')+
                ' · '+esc(y.j.posicion||'—')+'</p>'+
              '<p class="ayuda">'+(y.j.goles||0)+' goles esta temporada · '+ev+' eventos con su nombre</p>'+
              '<p class="ayuda">'+((y.j.historial||[]).length)+' etapas de historial</p>'+
              (y.equipo ? '<button class="btn btn-secondary btn-sm" style="margin-top:var(--g3)" '+
                'data-a="nombres:irEquipo" data-id="'+esc(y.equipo.id)+'">Abrir el club</button>' : '')+
            '</div>';
          }).join('')+
        '</div>'+
        '<p class="ayuda" style="margin-top:var(--g4)"><i class="ph ph-info"></i> '+
          'El gestor no fusiona fichas automáticamente: juntarlas mal perdería el historial de una de las dos. '+
          'Si son la misma persona, traspasa a una sus datos desde la ficha del club y borra la otra.</p>',
      pie:[{txt:'Entendido', cls:'btn-primary', fn:U.cerrarModal}]
    });
  },

  irPartido: function(el){
    U.irA(el.dataset.comp==='copa'?'copa':'partidos', {comp:el.dataset.comp, idx:Number(el.dataset.idx)});
  },
  irEquipo: function(el){ U.cerrarModal(); U.irA('equipos', {id:el.dataset.id}); }
};

U.registrar('nombres', {acciones:A, render:pintar});

})();
