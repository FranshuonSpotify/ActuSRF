/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-sanciones.js
   Sanciones por acumulación de tarjetas, y papelera de lo archivado.

   Las dos son vistas derivadas: no guardan un estado propio en el archivo.
   Las sanciones se calculan desde los eventos de los partidos, no desde un
   campo nuevo, para que no exista una segunda verdad que mantener a mano.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

/* Reglas por defecto. Son configurables porque cada liga usa las suyas, y
   viven en config.formatos para no inventar otra clave de primer nivel. */
function reglas(){
  var f = d().config.formatos;
  if(!f.SANCIONES) f.SANCIONES = {amarillas_ciclo:5, partidos_amarillas:1, partidos_roja:1};
  return f.SANCIONES;
}
function d(){ return SFG.d(); }

/* --------------------------------------------------------------------------
   SANCIONES
   -------------------------------------------------------------------------- */
/* Tarjetas por jugador, contadas desde los eventos y en orden de jornada:
   hace falta saber CUÁNDO se sacó cada una para decir en qué jornada cumple. */
function acumulado(){
  var t = {};
  function anota(nombre, club, tipo, jornada, comp){
    var f = C.findPlayer(nombre);
    var clave = f ? f.e.nombre+'|'+f.j.nombre : club+'|'+nombre;
    var x = t[clave] || (t[clave] = {
      nombre: f?f.j.nombre:nombre, j: f?f.j:null, e: f?f.e:C.equipo(club),
      amarillas:0, rojas:0, eventos:[]
    });
    if(tipo==='amarilla') x.amarillas++;
    if(tipo==='roja') x.rojas++;
    x.eventos.push({tipo:tipo, jornada:jornada, comp:comp});
  }
  [['partidos_liga','Superliga'],['partidos_ascenso','Ascenso'],['partidos_copa','Copa']].forEach(function(par){
    (d()[par[0]]||[]).slice()
      .sort(function(a,b){ return (parseInt(a.jornada)||0)-(parseInt(b.jornada)||0); })
      .forEach(function(p){
        if(!C.isFin(p)) return;
        var ev = C.parseDetalles(p.detalles);
        ev.local.forEach(function(e){ if(e.tipo==='amarilla'||e.tipo==='roja') anota(e.nombre, p.local, e.tipo, p.jornada, par[1]); });
        ev.visitante.forEach(function(e){ if(e.tipo==='amarilla'||e.tipo==='roja') anota(e.nombre, p.visitante, e.tipo, p.jornada, par[1]); });
      });
  });
  return t;
}

function sanciones(){
  var r = reglas(), t = acumulado(), out = [];
  Object.keys(t).forEach(function(k){
    var x = t[k];
    /* Ciclos de amarillas cumplidos + una sanción por cada roja. */
    var ciclos = r.amarillas_ciclo>0 ? Math.floor(x.amarillas/r.amarillas_ciclo) : 0;
    var partidos = ciclos*(r.partidos_amarillas||1) + x.rojas*(r.partidos_roja||1);
    var faltan = r.amarillas_ciclo>0 ? (r.amarillas_ciclo - (x.amarillas % r.amarillas_ciclo)) % r.amarillas_ciclo : 0;
    if(!partidos && !x.amarillas && !x.rojas) return;
    out.push({x:x, ciclos:ciclos, partidos:partidos, faltan:faltan});
  });
  return out.sort(function(a,b){ return b.partidos-a.partidos || b.x.amarillas-a.x.amarillas; });
}

function pintarSanciones(el){
  var r = reglas(), lista = sanciones();
  var cumplen = lista.filter(function(s){ return s.partidos>0; });
  var alBorde = lista.filter(function(s){ return s.partidos===0 && s.faltan===1; });

  el.innerHTML =
    U.cabecera('Sanciones', 'Calculadas desde las tarjetas de los partidos finalizados')+

    '<div class="card" style="padding:var(--g5);margin-bottom:var(--g5)">'+
      '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Reglas</h3>'+
      '<div class="rejilla rejilla-4">'+
        U.campo('Amarillas por ciclo', '<input class="inp inp-mono" type="number" min="0" value="'+r.amarillas_ciclo+'" data-c="sanciones:regla" data-k="amarillas_ciclo">',
          '0 desactiva la acumulación')+
        U.campo('Partidos por ciclo', '<input class="inp inp-mono" type="number" min="0" value="'+r.partidos_amarillas+'" data-c="sanciones:regla" data-k="partidos_amarillas">')+
        U.campo('Partidos por roja', '<input class="inp inp-mono" type="number" min="0" value="'+r.partidos_roja+'" data-c="sanciones:regla" data-k="partidos_roja">')+
      '</div>'+
      '<p class="ayuda" style="margin-top:var(--g4)">Las reglas se guardan en <span class="mono">config.formatos.SANCIONES</span>. '+
        'La web pública no las lee: esto es una herramienta de gestión, no cambia lo que se ve.</p>'+
    '</div>'+

    (!lista.length
      ? '<div class="card" style="padding:var(--g5)">'+
          '<p class="ayuda"><i class="ph ph-info"></i> No hay ninguna tarjeta registrada en todo el archivo, '+
          'así que no hay nada que acumular. Las tarjetas se anotan en el editor de eventos de cada partido, '+
          'junto a los goles: elige el tipo «Amarilla» o «Roja».</p></div>'
      : bloque('Cumplen sanción', cumplen, 'mal', 'Nadie cumple sanción ahora mismo.')+
        '<div class="g-hueco"></div>'+
        bloque('A una tarjeta del ciclo', alBorde, 'ojo', 'Nadie está a punto de ciclo.')+
        '<div class="g-hueco"></div>'+
        bloque('Todas las tarjetas', lista, '', ''));
}

function bloque(titulo, lista, cls, vacio){
  return '<div class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g4)">'+
      '<h3 style="font-size:.9375rem">'+esc(titulo)+'</h3>'+
      '<span class="pastilla'+(cls?' pastilla-'+cls:'')+'">'+lista.length+'</span></div>'+
    (lista.length
      ? '<div class="tabla-scroll"><table class="tabla"><thead><tr>'+
          '<th>Jugador</th><th>Club</th><th class="num">TA</th><th class="num">TR</th>'+
          '<th class="num">Partidos</th><th>Última</th></tr></thead><tbody>'+
        lista.map(function(s){
          var ult = s.x.eventos[s.x.eventos.length-1];
          return '<tr'+(s.partidos?' class="ojo"':'')+'>'+
            '<td>'+esc(s.x.nombre)+(s.x.j?'':' <span class="pastilla pastilla-mal">sin ficha</span>')+'</td>'+
            '<td>'+(s.x.e?U.celdaEquipo(s.x.e):'—')+'</td>'+
            '<td class="num" style="color:var(--gold)">'+s.x.amarillas+'</td>'+
            '<td class="num" style="color:var(--c-copa)">'+s.x.rojas+'</td>'+
            '<td class="num" style="font-weight:600">'+(s.partidos||'')+'</td>'+
            '<td style="color:var(--ink-3);font-size:.75rem">'+esc(ult?(ult.comp+' J'+(ult.jornada||'?')):'—')+'</td>'+
          '</tr>';
        }).join('')+'</tbody></table></div>'
      : '<p class="ayuda">'+esc(vacio)+'</p>')+
  '</div>';
}

/* --------------------------------------------------------------------------
   PAPELERA
   No hay borrado lógico en el esquema: lo único recuperable son los equipos
   archivados y los jugadores sin club. Se juntan aquí para no tener que
   buscarlos por tres pantallas distintas.
   -------------------------------------------------------------------------- */
function pintarPapelera(el){
  var D = d();
  var arch = D.equipos.filter(function(e){ return e.archivado; });
  var libres = D.agentes_libres || [];

  el.innerHTML =
    U.cabecera('Papelera', 'Lo que existe en el archivo pero no sale en la web')+

    '<div class="card" style="padding:var(--g5)">'+
      '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
        '<h3 style="font-size:.9375rem">Clubes archivados</h3>'+
        '<span class="pastilla">'+arch.length+'</span></div>'+
      '<p class="ayuda" style="margin-bottom:var(--g4)">No aparecen en la clasificación ni en Equipos, pero '+
        '<b>sus partidos siguen contando</b> para los rivales. Por eso se archivan en vez de borrarse.</p>'+
      (arch.length
        ? '<div class="tabla-scroll"><table class="tabla"><thead><tr>'+
            '<th>Club</th><th>División</th><th class="num">Jugadores</th><th class="num">Partidos</th><th class="acc"></th>'+
          '</tr></thead><tbody>'+arch.map(function(e){
            var n = ['partidos_liga','partidos_ascenso','partidos_copa'].reduce(function(a,k){
              return a + D[k].filter(function(p){ return p.local===e.nombre||p.visitante===e.nombre; }).length;
            }, 0);
            return '<tr><td>'+U.celdaEquipo(e)+'</td>'+
              '<td><span class="badge '+(e.division==='ASCENSO'?'badge-ascenso':'badge-superliga')+'">'+esc(e.division||'—')+'</span></td>'+
              '<td class="num">'+((e.jugadores||[]).length)+'</td>'+
              '<td class="num">'+n+'</td>'+
              '<td class="acc">'+
                '<button class="btn btn-secondary btn-sm" data-a="papelera:restaurar" data-id="'+esc(e.id)+'">Restaurar</button>'+
                ' <button class="btn btn-secondary btn-sm" data-a="papelera:ver" data-id="'+esc(e.id)+'">Abrir</button>'+
              '</td></tr>';
          }).join('')+'</tbody></table></div>'
        : '<p class="ayuda">Ningún club archivado.</p>')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g5)">'+
      '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
        '<h3 style="font-size:.9375rem">Jugadores sin club</h3>'+
        '<span class="pastilla">'+libres.length+'</span>'+
        '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="papelera:irTraspasos">Ir a Traspasos</button></div>'+
      '<p class="ayuda">Están en <span class="mono">agentes_libres</span> y la web pública no los muestra. '+
        'Se les da club desde Traspasos, arrastrándolos a una plantilla.</p>'+
      (libres.length
        ? '<p class="ayuda" style="margin-top:var(--g3)">Los cinco más recientes: '+
          libres.slice(-5).map(function(j){ return esc(j.nombre)+(j.fecha_agente_libre?' ('+esc(j.fecha_agente_libre)+')':''); }).join(' · ')+'</p>'
        : '')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g5)">'+
      '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Copias de seguridad</h3>'+
      '<p class="ayuda">Si lo que buscas es deshacer un borrado que ya guardaste, está en '+
        '<b>Datos → Copias de seguridad</b>: se guarda una antes de cada escritura.</p>'+
    '</div>';
}

/* --------------------------------------------------------------------------
   ACCIONES
   -------------------------------------------------------------------------- */
U.registrar('sanciones', {
  acciones: {
    regla: function(el){
      reglas()[el.dataset.k] = Math.max(0, Number(el.value)||0);
      U.cambio();
    }
  },
  render: pintarSanciones
});

U.registrar('papelera', {
  acciones: {
    restaurar: function(el){
      var e = C.equipoPorId(el.dataset.id);
      U.confirmar({
        titulo:'Restaurar «'+esc(e.nombre)+'»',
        texto:'Volverá a aparecer en la clasificación y en Equipos de la web pública.',
        ok:'Restaurar'
      }).then(function(si){
        if(!si) return;
        e.archivado = false;
        U.cambio();
        U.aviso('«'+e.nombre+'» restaurado.', 'ok');
      });
    },
    ver: function(el){ U.irA('equipos', {id:el.dataset.id}); },
    irTraspasos: function(){ U.irA('traspasos'); }
  },
  render: pintarPapelera
});

})();
