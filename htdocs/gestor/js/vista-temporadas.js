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
      : '<div class="vacio">Ninguna temporada archivada todavía. El palmarés de la web sale de aquí.</div>')+

    '<div class="g-hueco"></div>'+comparador(D);
}

/* --------------------------------------------------------------------------
   COMPARADOR DE TEMPORADAS
   La temporada en curso entra como una más, para no tener que archivarla sólo
   para poder compararla. Cada instantánea guarda los equipos con su
   clasificación de entonces, así que la comparación es directa.
   -------------------------------------------------------------------------- */
var cmp = {a:0, b:-1, que:'equipos'};      // -1 = temporada en curso

function instantaneas(D){
  return (D.historial_temporadas||[]).map(function(t,i){ return {i:i, nombre:t.nombre||('Temporada #'+(i+1)), t:t}; })
    .concat([{i:-1, nombre:'Temporada '+(D.config.temporada||'?')+' (en curso)', t:C.instantaneaTemporada(D)}]);
}
function comparador(D){
  var ins = instantaneas(D);
  if(ins.length<2) return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Comparador de temporadas</h3>'+
    '<p class="ayuda">Hace falta al menos una temporada archivada para comparar con la actual. '+
    'Archiva la temporada en curso y podrás enfrentarlas.</p></div>';

  var A = ins.find(function(x){ return x.i===cmp.a; }) || ins[0];
  var B = ins.find(function(x){ return x.i===cmp.b; }) || ins[ins.length-1];

  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4);flex-wrap:wrap">'+
      '<h3 style="font-size:.9375rem">Comparador de temporadas</h3>'+
      '<div style="display:flex;gap:.25rem;margin-left:auto">'+
        [['equipos','Clubes'],['jugadores','Jugadores']].map(function(q){
          return '<button class="btn btn-sm '+(cmp.que===q[0]?'btn-primary':'btn-secondary')+
            '" data-a="temporadas:cmpQue" data-v="'+q[0]+'">'+q[1]+'</button>';
        }).join('')+
      '</div></div>'+
    '<div class="rejilla rejilla-2" style="margin-bottom:var(--g5)">'+
      U.campo('Temporada A', selTemp(ins, cmp.a, 'a'))+
      U.campo('Temporada B', selTemp(ins, cmp.b, 'b'))+
    '</div>'+
    avisoDesigual(A, B)+
    (cmp.que==='equipos' ? compararEquipos(A,B) : compararJugadores(A,B))+
  '</div>';
}
/* Comparar una temporada cerrada con otra a medias es la forma más fácil de
   leer mal esta tabla: la de en curso siempre parecerá peor. Se dice antes de
   que alguien saque conclusiones. */
function avisoDesigual(A, B){
  function jugados(t){
    var l = (t.equipos||[]).map(function(e){ return e.pj||0; }).filter(Boolean);
    return l.length ? Math.round(l.reduce(function(a,b){ return a+b; },0)/l.length) : 0;
  }
  var ja = jugados(A.t), jb = jugados(B.t);
  if(!ja || !jb || Math.abs(ja-jb) < 3) return '';
  var corta = ja<jb ? A : B;
  return '<p class="mal" style="margin-bottom:var(--g4);font-size:.8125rem">'+
    '<i class="ph-bold ph-warning"></i> Los clubes llevan '+ja+' partidos de media en «'+esc(A.nombre)+
    '» y '+jb+' en «'+esc(B.nombre)+'». «'+esc(corta.nombre)+'» va por detrás, así que sus cifras '+
    'saldrán más bajas por haber jugado menos, no por jugar peor. Mira la columna PJ.</p>';
}

function selTemp(ins, valor, lado){
  return '<select class="inp" data-c="temporadas:cmpSel" data-lado="'+lado+'">'+
    ins.map(function(x){ return '<option value="'+x.i+'"'+(x.i===valor?' selected':'')+'>'+esc(x.nombre)+'</option>'; }).join('')+
  '</select>';
}

function compararEquipos(A, B){
  /* Se cruzan por NOMBRE y no por id: un club renombrado entre temporadas
     tiene el mismo id pero la gente lo busca por como se llamaba. Se avisa
     de los que sólo aparecen en una de las dos. */
  var mapa = {};
  function meter(lado, lista){
    (lista||[]).forEach(function(e){
      var k = e.nombre;
      (mapa[k] = mapa[k] || {nombre:k, id:e.id})[lado] = e;
    });
  }
  meter('a', A.t.equipos); meter('b', B.t.equipos);
  var filas = Object.keys(mapa).map(function(k){ return mapa[k]; })
    .filter(function(x){ return x.a || x.b; })
    .sort(function(x,y){
      var px = x.b?(x.b.pts||0):-1, py = y.b?(y.b.pts||0):-1;
      return py-px || x.nombre.localeCompare(y.nombre,'es');
    });
  var soloA = filas.filter(function(x){ return x.a && !x.b; });
  var soloB = filas.filter(function(x){ return x.b && !x.a; });
  var ambas = filas.filter(function(x){ return x.a && x.b; });

  return (ambas.length
    ? '<div class="tabla-scroll"><table class="tabla"><thead><tr>'+
        '<th>Club</th><th class="num">Pts A</th><th class="num">Pts B</th><th class="num">Δ</th>'+
        '<th class="num">GF A</th><th class="num">GF B</th><th class="num">PJ A</th><th class="num">PJ B</th>'+
      '</tr></thead><tbody>'+ambas.map(function(x){
        var dif = (x.b.pts||0)-(x.a.pts||0);
        return '<tr><td>'+U.celdaEquipo(C.equipoPorId(x.id)||x.b, x.nombre)+'</td>'+
          '<td class="num">'+(x.a.pts||0)+'</td><td class="num">'+(x.b.pts||0)+'</td>'+
          '<td class="num" style="font-weight:600;color:'+(dif>0?'#6FD98A':(dif<0?'#FF7B7B':'var(--ink-4)'))+'">'+
            (dif>0?'+':'')+dif+'</td>'+
          '<td class="num" style="color:var(--ink-3)">'+(x.a.gf||0)+'</td><td class="num" style="color:var(--ink-3)">'+(x.b.gf||0)+'</td>'+
          '<td class="num" style="color:var(--ink-3)">'+(x.a.pj||0)+'</td><td class="num" style="color:var(--ink-3)">'+(x.b.pj||0)+'</td></tr>';
      }).join('')+'</tbody></table></div>'
    : '<p class="ayuda">Ningún club aparece en las dos temporadas.</p>')+
    avisoSolo(soloA, A.nombre)+avisoSolo(soloB, B.nombre);
}
function avisoSolo(lista, nombre){
  if(!lista.length) return '';
  return '<p class="ayuda" style="margin-top:var(--g3)"><i class="ph ph-info"></i> Sólo en '+esc(nombre)+': '+
    lista.slice(0,10).map(function(x){ return esc(x.nombre); }).join(', ')+
    (lista.length>10 ? ' y '+(lista.length-10)+' más' : '')+'.</p>';
}

function compararJugadores(A, B){
  function recoger(t){
    var m = {};
    (t.equipos||[]).forEach(function(e){
      (e.jugadores||[]).forEach(function(j){ m[j.nombre] = {j:j, club:e.nombre}; });
    });
    return m;
  }
  var ma = recoger(A.t), mb = recoger(B.t);
  var filas = Object.keys(mb).filter(function(k){ return ma[k]; }).map(function(k){
    return {nombre:k, a:ma[k], b:mb[k], dif:(mb[k].j.goles||0)-(ma[k].j.goles||0)};
  }).filter(function(x){ return (x.a.j.goles||0) || (x.b.j.goles||0); })
    .sort(function(x,y){ return Math.abs(y.dif)-Math.abs(x.dif) || y.b.j.goles-x.b.j.goles; });

  if(!filas.length) return '<p class="ayuda">Ningún jugador con goles aparece en las dos temporadas.</p>';
  return '<div class="tabla-scroll"><table class="tabla"><thead><tr>'+
      '<th>Jugador</th><th>Club A</th><th>Club B</th><th class="num">Goles A</th><th class="num">Goles B</th><th class="num">Δ</th>'+
    '</tr></thead><tbody>'+filas.slice(0,30).map(function(x){
      var cambio = x.a.club!==x.b.club;
      return '<tr><td>'+esc(x.nombre)+'</td>'+
        '<td style="color:var(--ink-3);font-size:.75rem">'+esc(x.a.club)+'</td>'+
        '<td style="font-size:.75rem'+(cambio?';color:var(--accent)':';color:var(--ink-3)')+'">'+esc(x.b.club)+
          (cambio?' <span class="pastilla pastilla-ojo">cambió</span>':'')+'</td>'+
        '<td class="num">'+(x.a.j.goles||0)+'</td><td class="num">'+(x.b.j.goles||0)+'</td>'+
        '<td class="num" style="font-weight:600;color:'+(x.dif>0?'#6FD98A':(x.dif<0?'#FF7B7B':'var(--ink-4)'))+'">'+
          (x.dif>0?'+':'')+x.dif+'</td></tr>';
    }).join('')+'</tbody></table></div>'+
    (filas.length>30 ? '<p class="ayuda" style="margin-top:var(--g3)">y '+(filas.length-30)+' jugadores más.</p>' : '');
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
      ? '<div class="tabla-caja">'+camp.map(function(c){ return filaCampeon(c, -1); }).join('')+'</div>'+
        (camp.some(function(c){ return c.dudoso; })
          ? '<p class="mal" style="margin-top:var(--g3);font-size:.8125rem"><i class="ph-bold ph-warning"></i> '+
            'Hay eliminatorias jugadas: el campeón por puntos casi seguro no es el de verdad. '+
            'Al archivar podrás apuntar quién ganó de verdad.</p>'
          : '')
      : '<p class="ayuda">Todavía no hay campeones que archivar.</p>')+
    (!finalCopa ? '<p class="ayuda" style="margin-top:var(--g3)"><i class="ph ph-info"></i> No hay ningún cruce con fase FINAL en la Copa, así que el palmarés no incluirá campeón de Copa.</p>'
     : !C.isFin(finalCopa) ? '<p class="ayuda" style="margin-top:var(--g3)"><i class="ph ph-info"></i> La final de Copa está pendiente: hasta que se marque como finalizada no habrá campeón de Copa.</p>' : '')+
    (pendientes ? '<p class="ayuda" style="margin-top:var(--g3)" ><i class="ph ph-warning"></i> Quedan '+pendientes+' partidos sin resultado. Se archivarán como pendientes.</p>' : '')+
  '</div>';
}

/* `ti` es el índice de la temporada archivada, o -1 para la que está en
   curso (que todavía no se puede editar porque aún no existe como entrada). */
function filaCampeon(c, ti){
  var e = C.equipoPorId(c.e.id) || c.e;
  return '<div class="problema">'+
    '<i class="ph-fill ph-trophy" style="color:'+(c.guardado?'var(--gold)':'var(--ink-4)')+'"></i>'+
    '<span style="color:var(--ink-3);min-width:130px">'+esc(c.comp)+'</span>'+
    U.celdaEquipo(e, c.e.nombre)+
    (c.marcador ? '<span class="mono" style="margin-left:.75rem;color:var(--ink-3);font-size:.75rem">'+esc(c.marcador)+'</span>' : '')+
    '<span style="margin-left:auto;display:flex;align-items:center;gap:.4rem">'+
      (c.guardado
        ? '<span class="pastilla pastilla-ok">apuntado</span>'
        : (function(){
            /* En Copa el derivado sale de la FINAL, no de los puntos: decir
               «por puntos» ahí sería mentir sobre de dónde viene el dato. */
            var deLaFinal = c.clave==='COPA';
            var etq = deLaFinal ? 'de la final' : (c.dudoso ? 'por puntos · revisar' : 'por puntos');
            var tit = deLaFinal
              ? 'Sale de quién ganó el cruce marcado como FINAL'
              : (c.dudoso
                  ? 'Esta división tiene eliminatorias jugadas: el campeón por puntos casi seguro no es el de verdad'
                  : 'Sale del que más puntos tiene, que es como lo calcula la web');
            return '<span class="pastilla'+(c.dudoso&&!deLaFinal?' pastilla-mal':'')+'" title="'+tit+'">'+etq+'</span>';
          })())+
      (ti>=0 ? '<button class="ir" data-a="temporadas:campeon" data-i="'+ti+'" data-c="'+esc(c.clave)+'">Cambiar</button>' : '')+
    '</span>'+
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
      ? '<div class="tabla-caja" style="margin-bottom:var(--g4)">'+camp.map(function(c){ return filaCampeon(c, i); }).join('')+'</div>'
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
  /* Apuntar el campeón a mano. Hace falta porque el derivado es «el que más
     puntos tiene», y con play-off el campeón es quien gana la final. */
  campeon: function(el){
    var ti = Number(el.dataset.i), clave = el.dataset.c;
    var t = d().historial_temporadas[ti];
    if(!t) return;
    var comp = C.COMPETICIONES.filter(function(x){ return x.clave===clave; })[0];
    var actual = (t.campeones||[]).filter(function(x){ return x.comp===clave; })[0];
    var der = C.campeonDerivado(t, clave);
    /* Los equipos que se ofrecen son los de ESA temporada, no los de hoy: un
       club pudo desaparecer o cambiar de división desde entonces. */
    var eqs = (t.equipos||[]).filter(function(e){ return clave==='COPA' || e.division===clave; })
      .sort(function(a,b){ return String(a.nombre).localeCompare(String(b.nombre),'es'); });

    U.modal({
      titulo:'Campeón de '+comp.nombre,
      cuerpo:
        '<p class="ayuda" style="margin-bottom:var(--g4)">'+esc(t.nombre||'')+'. '+
          (der ? 'Por puntos saldría <b>'+esc(der.e.nombre)+'</b>. ' : '')+
          'Si la competición se decidió en una final, apunta aquí a quien la ganó.</p>'+
        U.campo('Campeón', '<select class="inp" id="camp-eq">'+
          '<option value="">— calcularlo por puntos —</option>'+
          eqs.map(function(e){
            return '<option value="'+esc(e.nombre)+'"'+(actual&&actual.equipo===e.nombre?' selected':'')+'>'+esc(e.nombre)+'</option>';
          }).join('')+'</select>')+
        '<div class="g-hueco"></div>'+
        U.campo('Marcador de la final', '<input class="inp" id="camp-marc" value="'+esc(actual?actual.marcador||'':'')+'" placeholder="opcional, p. ej. 2-1">',
          'Se enseña al lado del campeón.'),
      pie:[
        {txt:'Cancelar', fn:U.cerrarModal},
        {txt:'Guardar', cls:'btn-primary', fn:function(){
          var nom = document.getElementById('camp-eq').value;
          var marc = (document.getElementById('camp-marc').value||'').trim();
          var e = nom ? (t.equipos||[]).filter(function(x){ return x.nombre===nom; })[0] : null;
          C.fijarCampeon(t, clave, e, marc);
          U.cerrarModal(); U.cambio();
          U.aviso(e ? 'Campeón de '+comp.nombre+': '+e.nombre+'.' : 'Vuelve a calcularse por puntos.', 'ok');
        }}
      ]
    });
  },

  cmpQue: function(el){ cmp.que = el.dataset.v; U.refrescar(); },
  cmpSel: function(el){ cmp[el.dataset.lado] = Number(el.value); U.refrescar(); },

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
