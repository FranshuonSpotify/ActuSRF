/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-papelera.js
   Lo que existe en el archivo pero no sale en la web.

   Antes este fichero tenia tambien una pantalla de Sanciones calculada desde
   las tarjetas. Se retiro: en esta liga las tarjetas no se registran ni se
   muestran en ninguna parte, asi que era una pantalla condenada a salir
   vacia siempre. Una pantalla que nunca tiene nada que decir es ruido en la
   navegacion.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

function d(){ return SFG.d(); }

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
/* La pestaña de Sanciones se retira: se calculaba desde las tarjetas, y en
   esta liga las tarjetas no se registran ni se muestran en ninguna parte, así
   que era una pantalla condenada a salir vacía siempre. El cálculo se queda
   aquí escrito por si algún día se empiezan a anotar —no cuesta nada— pero no
   se registra como vista. */

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
