/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-temporadas.js
   Archivo de temporadas y palmarés.

   `historial_temporadas` es la única de las cuatro claves «no documentadas»
   que la web pública SÍ lee: palmares() saca de ahí los campeones de cada
   temporada. La forma de cada entrada no es libre —necesita `equipos` para el
   campeón de liga y `partidos_copa` para la final de Copa—, así que el gestor
   la escribe tal y como app.js la espera y enseña el palmarés resultante para
   que se vea antes de guardar.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

function d(){ return SFG.d(); }

function pintar(el){
  var D = d(), ts = D.historial_temporadas || [];

  el.innerHTML =
    U.cabecera('Temporadas', 'Temporada '+(D.config.temporada||'—')+' en curso · '+ts.length+' archivadas',
      '<button class="btn btn-secondary btn-sm" data-a="temporadas:archivar"><i class="ph ph-archive"></i> Archivar sin cerrar</button>'+
      '<button class="btn btn-primary btn-sm" data-a="temporadas:cerrar"><i class="ph-bold ph-flag-checkered"></i> Cerrar temporada</button>')+

    bloqueActual(D)+
    '<div class="g-hueco"></div>'+

    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Archivo</h3>'+
    (ts.length
      ? '<div class="rejilla" style="--min:300px">'+ts.map(tarjeta).join('')+'</div>'
      : '<div class="vacio">Ninguna temporada archivada todavía. El palmarés de la web sale de aquí.</div>');
}

/* Qué se llevaría el archivo si se cerrara ahora. Se enseña antes porque
   cerrar es la operación más destructiva del programa. */
function bloqueActual(D){
  var instantanea = C.instantaneaTemporada(D);
  var camp = C.campeones(instantanea);
  var jugados = D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa).filter(C.isFin).length;
  var pendientes = D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa).filter(function(p){ return !C.isFin(p); }).length;
  var finalCopa = D.partidos_copa.filter(function(p){ return p.fase==='FINAL'; })[0];

  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Temporada '+esc(D.config.temporada||'?')+', en curso</h3>'+
    '<div class="rejilla rejilla-4" style="margin-bottom:var(--g5)">'+
      [[D.equipos.filter(function(e){ return !e.archivado; }).length,'Clubes'],
       [jugados,'Partidos jugados'],
       [pendientes,'Sin resultado'],
       [D.noticias.length,'Noticias']].map(function(k){
        return '<div><div class="mono" style="font-size:1.5rem;font-weight:600">'+k[0]+'</div>'+
          '<div class="ayuda">'+k[1]+'</div></div>';
      }).join('')+
    '</div>'+
    '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g2)">'+
      'PALMARÉS QUE SE ARCHIVARÍA</div>'+
    (camp.length
      ? '<div class="tabla-caja">'+camp.map(filaCampeon).join('')+'</div>'
      : '<p class="ayuda">Todavía no hay campeones que archivar.</p>')+
    (!finalCopa ? '<p class="ayuda" style="margin-top:var(--g3)"><i class="ph ph-info"></i> No hay ningún cruce con fase FINAL en la Copa, así que el palmarés no incluirá campeón de Copa.</p>'
     : !C.isFin(finalCopa) ? '<p class="ayuda" style="margin-top:var(--g3)"><i class="ph ph-info"></i> La final de Copa está pendiente: hasta que se marque como finalizada no habrá campeón de Copa.</p>' : '')+
    (pendientes ? '<p class="ayuda" style="margin-top:var(--g3)" ><i class="ph ph-warning"></i> Quedan '+pendientes+' partidos sin resultado. Se archivarán como pendientes.</p>' : '')+
  '</div>';
}

function filaCampeon(c){
  var e = C.equipoPorId(c.e.id) || c.e;
  return '<div class="problema">'+
    '<i class="ph-fill ph-trophy" style="color:var(--gold)"></i>'+
    '<span style="color:var(--ink-3);min-width:150px">'+esc(c.comp)+'</span>'+
    U.celdaEquipo(e, c.e.nombre)+
    (c.marcador ? '<span class="mono" style="margin-left:auto;color:var(--ink-4);font-size:.75rem">'+esc(c.marcador)+'</span>' : '')+
  '</div>';
}

function tarjeta(t, i){
  var camp = C.campeones(t);
  var np = (t.partidos_liga||[]).length + (t.partidos_ascenso||[]).length + (t.partidos_copa||[]).length;
  return '<article class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:flex-start;gap:var(--g3);margin-bottom:var(--g4)">'+
      '<div><h3 style="font-size:.9375rem">'+esc(t.nombre||'Sin nombre')+'</h3>'+
        '<p class="ayuda">'+esc(t.fecha||'')+' · '+((t.equipos||[]).length)+' clubes · '+np+' partidos</p></div>'+
    '</div>'+
    (camp.length
      ? '<div class="tabla-caja" style="margin-bottom:var(--g4)">'+camp.map(filaCampeon).join('')+'</div>'
      : '<p class="ayuda" style="margin-bottom:var(--g4)">Sin campeones registrados: la web no la mostrará en el palmarés.</p>')+
    '<div style="display:flex;gap:.4rem;flex-wrap:wrap">'+
      '<button class="btn btn-secondary btn-sm" data-a="temporadas:renombrar" data-i="'+i+'">Renombrar</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="temporadas:exportar" data-i="'+i+'"><i class="ph ph-download-simple"></i> Exportar</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="temporadas:borrar" data-i="'+i+'">Eliminar</button>'+
    '</div></article>';
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
var A = {
  archivar: function(){
    pedirNombre('Archivar la temporada en curso',
      'Se guarda una copia en el palmarés. <b>La temporada sigue como está</b>: no se resetea nada ni se vacía el calendario.',
      'Archivar', function(nombre){
        d().historial_temporadas.push(C.instantaneaTemporada(d(), nombre));
        U.cambio();
        U.aviso('«'+nombre+'» archivada. La temporada en curso no se ha tocado.', 'ok', 6000);
      });
  },

  cerrar: function(){
    var D = d();
    var pendientes = D.partidos_liga.concat(D.partidos_ascenso, D.partidos_copa).filter(function(p){ return !C.isFin(p); }).length;
    var siguiente = parseInt(D.config.temporada,10);
    pedirNombre('Cerrar la temporada '+esc(D.config.temporada||'?'),
      'Esto hace cuatro cosas, en este orden:'+
      '<ol style="margin:.75rem 0 .75rem 1.1rem;line-height:1.9">'+
        '<li>Archiva una copia completa en el palmarés.</li>'+
        '<li>Vuelca las estadísticas de cada jugador a su historial y las pone a cero.</li>'+
        '<li>Pone a cero la clasificación de todos los clubes.</li>'+
        '<li>Vacía el calendario y avanza a la temporada '+(isNaN(siguiente)?'siguiente':siguiente+1)+'.</li>'+
      '</ol>'+
      (pendientes ? '<b style="color:var(--gold)">Quedan '+pendientes+' partidos sin resultado</b> que se archivarán como pendientes y desaparecerán del calendario.<br><br>' : '')+
      'Las plantillas, los historiales y los agentes libres <b>no se tocan</b>. Nada se escribe en disco hasta que pulses Guardar.',
      'Cerrar temporada', function(nombre){
        var D = d();
        D.historial_temporadas.push(C.instantaneaTemporada(D, nombre));
        var r = C.cerrarTemporada(D, {etiqueta:nombre, vaciarCalendario:true});
        U.cambio();
        U.modal({
          titulo:'Temporada cerrada',
          cuerpo:'<p style="font-size:.875rem;color:var(--ink-2);line-height:1.7">'+
            '«'+esc(nombre)+'» está en el palmarés.<br><br>'+
            'Se volcaron las estadísticas de <b>'+r.jugadores+' jugadores</b> a su historial'+
            (r.etapas ? ' (creando '+r.etapas+' etapas nuevas para quien no tenía)' : '')+', '+
            'se puso a cero la clasificación y se retiraron <b>'+r.partidos+' partidos</b> del calendario.<br><br>'+
            'Ahora estás en la temporada <b>'+esc(d().config.temporada)+'</b>, jornada 1.<br><br>'+
            '<span style="color:var(--ink-3);font-size:.8125rem">Revisa el resultado y guarda. Si algo no cuadra, cierra sin guardar y no habrá pasado nada.</span></p>',
          pie:[{txt:'Entendido', cls:'btn-primary', fn:U.cerrarModal}]
        });
      }, true);
  },

  renombrar: function(el){
    var i = Number(el.dataset.i), t = d().historial_temporadas[i];
    pedirNombre('Renombrar', 'Es el nombre con el que aparece en el palmarés de la web.', 'Guardar',
      function(nombre){ t.nombre = nombre; U.cambio(); }, false, t.nombre);
  },

  exportar: function(el){
    var t = d().historial_temporadas[Number(el.dataset.i)];
    var blob = new Blob([JSON.stringify(t, null, 4)], {type:'application/json'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = String(t.nombre||'temporada').replace(/[\\/:*?"<>|]/g,'-')+'.json';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function(){ URL.revokeObjectURL(url); }, 4000);
    U.aviso('Temporada exportada.', 'ok');
  },

  borrar: function(el){
    var i = Number(el.dataset.i), t = d().historial_temporadas[i];
    U.confirmar({
      titulo:'Eliminar «'+esc(t.nombre||'sin nombre')+'» del palmarés',
      html:'Se pierde la copia completa de esa temporada: '+((t.equipos||[]).length)+' clubes con sus plantillas de entonces. '+
        '<b>No hay forma de recuperarla</b> salvo por una copia de seguridad.<br><br>Exportarla antes deja un archivo suelto por si acaso.',
      ok:'Eliminar', peligro:true
    }).then(function(si){
      if(!si) return;
      d().historial_temporadas.splice(i,1);
      U.cambio();
      U.aviso('Temporada eliminada del palmarés.', 'ok');
    });
  }
};

/* Modal de un solo campo con confirmación. Se reutiliza para archivar, cerrar
   y renombrar porque las tres piden lo mismo: un nombre. */
function pedirNombre(titulo, html, ok, cb, peligro, valor){
  var sugerido = valor!=null ? valor : ('Temporada '+(d().config.temporada||'?'));
  U.modal({
    titulo:titulo, ancho:true,
    cuerpo:'<p style="font-size:.875rem;color:var(--ink-2);line-height:1.6;margin-bottom:var(--g4)">'+html+'</p>'+
      U.campo('Nombre de la temporada', '<input class="inp" id="temp-nombre" value="'+esc(sugerido)+'">',
        'Es lo que se lee en el palmarés.'),
    pie:[
      {txt:'Cancelar', fn:U.cerrarModal},
      {txt:ok, cls: peligro?'btn-accent':'btn-primary', fn:function(){
        var n = (document.getElementById('temp-nombre').value||'').trim();
        if(!n) return U.aviso('Ponle un nombre.', 'ojo');
        U.cerrarModal();
        cb(n);
      }}
    ]
  });
}

U.registrar('temporadas', {acciones:A, render:pintar});

})();
