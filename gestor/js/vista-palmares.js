/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-palmares.js
   Fotos para el Salón de presidentes y el palmarés del club de la web
   pública. No añade entidades nuevas al esquema: `d().presidentes` y
   `d().trofeos` son dos diccionarios sueltos ({clave: {foto}}), aditivos,
   que app.js lee si existen y se apaña sin ellos si no (icono/inicial por
   defecto). No hay alta manual de presidente: se listan los que ya
   aparecen en algún equipo (actual o de una temporada archivada), que son
   justo los que puede llegar a mostrar el Salón de presidentes.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

function d(){ return SFG.d(); }

var TROFEOS = [
  ['SUPERLIGA', 'Superliga Frontier'],
  ['ASCENSO', 'Ascenso Frontier'],
  ['COPA', 'Copa Fútbol Frontier']
];

/* Un presidente es, hoy, solo un nombre: equipo.ciudad. Se recogen los de
   los equipos actuales y los de cada snapshot de historial_temporadas —
   así aparece también quien ya no dirige ningún equipo pero tiene título. */
function presidentesConocidos(D){
  var set = {};
  (D.equipos||[]).forEach(function(e){ var c=(e.ciudad||'').trim(); if(c) set[c]=true; });
  (D.historial_temporadas||[]).forEach(function(t){
    (t.equipos||[]).forEach(function(e){ var c=(e.ciudad||'').trim(); if(c) set[c]=true; });
  });
  return Object.keys(set).sort(function(a,b){ return a.localeCompare(b,'es'); });
}

/* acciones se registra una sola vez (U.registrar guarda esta referencia),
   así que en vez de reasignarla en cada pintado se vacía y se rellena —
   una entrada por presidente conocido, más las tres fijas de trofeo. */
var A = {};

function pintar(el){
  var D = d();
  var nombres = presidentesConocidos(D);

  Object.keys(A).forEach(function(k){ delete A[k]; });
  nombres.forEach(function(nombre){
    A['presFoto|'+nombre] = function(ev){
      D.presidentes = D.presidentes||{};
      D.presidentes[nombre] = D.presidentes[nombre]||{};
      D.presidentes[nombre].foto = ev.value;
      U.cambio();
    };
  });
  TROFEOS.forEach(function(t){
    A['trofeoFoto|'+t[0]] = function(ev){
      D.trofeos = D.trofeos||{};
      D.trofeos[t[0]] = D.trofeos[t[0]]||{};
      D.trofeos[t[0]].foto = ev.value;
      U.cambio();
    };
  });

  el.innerHTML =
    U.cabecera('Palmarés', 'Fotos de presidentes y trofeos para el Salón de presidentes de la web pública.')+

    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Trofeos</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g4)">Una foto por competición: sustituye el icono genérico en cualquier tarjeta de título de esa competición, para todas las temporadas.</p>'+
    '<div class="rejilla" style="--min:260px;margin-bottom:var(--g6)">'+
      TROFEOS.map(function(t){
        var foto = (D.trofeos && D.trofeos[t[0]] && D.trofeos[t[0]].foto) || '';
        return '<div class="card" style="padding:var(--g5)">'+
          U.campoImagen(t[1], foto, 'palmares:trofeoFoto|'+t[0])+
        '</div>';
      }).join('')+
    '</div>'+

    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Presidentes</h3>'+
    (nombres.length
      ? '<p class="ayuda" style="margin-bottom:var(--g4)">Presidentes que aparecen en algún equipo, actual o de una temporada archivada — son los que puede mostrar el Salón de presidentes.</p>'+
        '<div class="rejilla" style="--min:220px">'+
          nombres.map(function(nombre){
            var foto = (D.presidentes && D.presidentes[nombre] && D.presidentes[nombre].foto) || '';
            return '<div class="card" style="padding:var(--g5)">'+
              U.campoImagen(nombre, foto, 'palmares:presFoto|'+nombre)+
            '</div>';
          }).join('')+
        '</div>'
      : '<div class="vacio">Todavía no hay ningún presidente: ningún equipo tiene "ciudad" rellena.</div>');
}

U.registrar('palmares', {acciones:A, render:pintar});

})();
