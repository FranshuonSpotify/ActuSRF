/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — preferencias.js
   Tamaño de tipografía, alto contraste y atajos de teclado.

   Las preferencias viven en localStorage, no en datos_oficiales.json: son de
   quien usa el programa, no de la competición, y no tienen por qué viajar en
   el archivo que descarga la web.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, U = SFG.ui;
var K = 'sfg:prefs';
var pref = {escala:1, contraste:false};

function leer(){
  try { pref = Object.assign(pref, JSON.parse(localStorage.getItem(K)) || {}); } catch(e){}
}
function guardar(){
  try { localStorage.setItem(K, JSON.stringify(pref)); } catch(e){}
}
function aplicar(){
  /* Se escala la raíz, que es de donde cuelgan los rem de todo el sistema, y
     también el body, porque styles.css le fija 16px explícitos y si no se
     tocara el texto corriente no cambiaría de tamaño. */
  var px = Math.round(16*pref.escala);
  document.documentElement.style.fontSize = px+'px';
  document.body.style.fontSize = px+'px';
  document.body.classList.toggle('alto-contraste', !!pref.contraste);
}

/* --------------------------------------------------------------------------
   ATAJOS
   Sólo se disparan fuera de un campo de texto: escribir "3" en el marcador de
   un partido no puede llevarte a otra sección.
   -------------------------------------------------------------------------- */
var SECCIONES = [
  ['1','resumen','Resumen'], ['2','equipos','Equipos'], ['3','partidos','Partidos'],
  ['4','copa','Copa'], ['5','traspasos','Traspasos'], ['6','estadisticas','Estadísticas'],
  ['7','graficos','Gráficos'], ['8','noticias','Noticias'], ['9','datos','Datos']
];
function editando(t){
  return t && (/^(INPUT|SELECT|TEXTAREA)$/.test(t.tagName) || t.isContentEditable);
}
document.addEventListener('keydown', function(ev){
  if(ev.ctrlKey || ev.metaKey || ev.altKey) return;
  if(editando(ev.target)) return;
  var tv = document.getElementById('tv');
  if(tv && tv.classList.contains('on')) return;      // la presentación tiene los suyos

  if(ev.key==='?' || (ev.key==='/' && ev.shiftKey)){ ev.preventDefault(); return ayuda(); }
  if(ev.key==='+'){ ev.preventDefault(); return escalar(0.1); }
  if(ev.key==='-'){ ev.preventDefault(); return escalar(-0.1); }
  var s = SECCIONES.find(function(x){ return x[0]===ev.key; });
  if(s && SFG.d()){ ev.preventDefault(); U.irA(s[1]); }
});

function escalar(delta){
  pref.escala = Math.min(1.5, Math.max(0.85, Math.round((pref.escala+delta)*100)/100));
  guardar(); aplicar();
  U.aviso('Tamaño de texto al '+Math.round(pref.escala*100)+'%.', 'info', 1800);
}

/* --------------------------------------------------------------------------
   PANEL DE AYUDA
   -------------------------------------------------------------------------- */
function ayuda(){
  U.modal({
    titulo:'Atajos y accesibilidad',
    ancho:true,
    cuerpo:
      '<div class="rejilla rejilla-2" style="align-items:start">'+
        '<div>'+
          bloque('Ir a', SECCIONES.map(function(s){ return [s[0], s[2]]; }))+
          bloque('Archivo', [['Ctrl S','Guardar'], ['Ctrl K','Buscar en todo']])+
        '</div>'+
        '<div>'+
          bloque('Ventanas', [['Esc','Cerrar ventana o presentación'], ['Tab','Recorrer, sin salirse de la ventana abierta'], ['?','Esta ayuda']])+
          bloque('Arrastrar sin ratón', [
            ['Flechas','Mover el elemento enfocado'],
            ['Ctrl + flechas','Moverlo a otra lista'],
            ['+ / −','Tamaño del texto']])+
        '</div>'+
      '</div>'+
      '<div style="margin-top:var(--g5);padding-top:var(--g4);border-top:1px solid var(--line)">'+
        '<h4 style="font-size:.8125rem;margin-bottom:var(--g3)">Accesibilidad</h4>'+
        '<div class="rejilla rejilla-2">'+
          U.campo('Tamaño del texto',
            '<div class="color-par">'+
              '<button class="btn btn-secondary btn-sm" data-a="prefs:menos" aria-label="Reducir">−</button>'+
              '<span class="mono" id="pref-escala" style="min-width:52px;text-align:center">'+Math.round(pref.escala*100)+'%</span>'+
              '<button class="btn btn-secondary btn-sm" data-a="prefs:mas" aria-label="Aumentar">+</button>'+
              '<button class="btn btn-secondary btn-sm" data-a="prefs:reset">Normal</button>'+
            '</div>')+
          U.campo('Contraste',
            '<label class="sw"><input type="checkbox"'+(pref.contraste?' checked':'')+' data-c="prefs:contraste">'+
            '<span class="pista"></span> Alto contraste</label>')+
        '</div>'+
        '<p class="ayuda" style="margin-top:var(--g3)">Se guardan en este navegador, no en el archivo de la competición. '+
          'Si tienes activada la reducción de movimiento del sistema, el gestor ya la respeta: no hay animaciones ni rotación automática en el modo presentación.</p>'+
      '</div>',
    pie:[{txt:'Cerrar', cls:'btn-primary', fn:U.cerrarModal}]
  });
}
function bloque(titulo, filas){
  return '<h4 style="font-size:.8125rem;margin-bottom:var(--g2)">'+titulo+'</h4>'+
    '<div class="tabla-caja" style="margin-bottom:var(--g4)">'+filas.map(function(f){
      return '<div class="problema" style="padding:.4rem .6rem">'+
        '<kbd class="tecla">'+f[0]+'</kbd><span>'+f[1]+'</span></div>';
    }).join('')+'</div>';
}

U.acciones.prefs = {
  mas:   function(){ escalar(0.1); refrescarPanel(); },
  menos: function(){ escalar(-0.1); refrescarPanel(); },
  reset: function(){ pref.escala = 1; guardar(); aplicar(); refrescarPanel(); },
  contraste: function(el){
    pref.contraste = el.checked;
    guardar(); aplicar();
    U.aviso(pref.contraste ? 'Alto contraste activado.' : 'Contraste normal.', 'info', 1800);
  },
  ayuda: ayuda
};
function refrescarPanel(){
  var e = document.getElementById('pref-escala');
  if(e) e.textContent = Math.round(pref.escala*100)+'%';
}

leer();
aplicar();
SFG.prefs = {ayuda:ayuda, aplicar:aplicar};

})();
