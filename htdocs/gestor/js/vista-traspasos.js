/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-traspasos.js
   Mercado: mover jugadores entre dos clubes y la bolsa de agentes libres.

   Tres columnas y no dos porque `agentes_libres` es un dato real del archivo
   —133 jugadores sin club— y sin él un traspaso sólo podría ser un
   intercambio directo, que no es como funciona una liga.

   La mecánica de mover vive en core.traspasar(), no aquí: es la operación que
   más fácil desajusta el archivo (plantilla, historial y estadísticas a la
   vez) y tiene que poder comprobarse fuera del navegador.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var izq = null, der = null, q = '';

function d(){ return SFG.d(); }

function pintar(el, param){
  var D = d();
  var activos = D.equipos.filter(function(e){ return !e.archivado; })
    .sort(function(a,b){ return a.nombre.localeCompare(b.nombre,'es'); });
  if(param && param.id) izq = param.id;
  if(!izq && activos[0]) izq = activos[0].id;
  if(!der && activos[1]) der = activos[1].id;

  var A = C.equipoPorId(izq), B = C.equipoPorId(der);

  el.innerHTML =
    U.cabecera('Traspasos', 'Arrastra jugadores entre columnas. Cada movimiento cierra la etapa del club de origen y abre la del destino.')+
    '<div class="g-filtros">'+
      '<input class="inp inp-sm" style="width:240px" type="search" placeholder="Filtrar jugadores…" value="'+esc(q)+'" data-c="traspasos:filtro">'+
      '<span class="ayuda" style="margin-left:auto">'+D.agentes_libres.length+' agentes libres</span>'+
    '</div>'+

    '<div class="rejilla" style="--min:280px;align-items:start">'+
      columnaClub(A, 'izq', activos)+
      columnaLibres(D)+
      columnaClub(B, 'der', activos)+
    '</div>'+

    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g4)">'+
      '<div style="display:flex;gap:var(--g3);align-items:flex-start">'+
        '<i class="ph ph-info" style="color:var(--ink-3);flex-shrink:0;margin-top:.15rem"></i>'+
        '<p class="ayuda">Al traspasar, los goles y tarjetas de esta temporada se quedan apuntados en el club donde se hicieron y el jugador empieza de cero en el nuevo. '+
        'Su cifra de carrera no cambia, y el ranking de goleadores de la web tampoco: ése sale de los eventos de los partidos, no de la ficha.</p>'+
      '</div></div>';

  montar();
}

function columnaClub(e, lado, activos){
  if(!e) return '<div class="card" style="padding:var(--g5)"><p class="ayuda">Sin club seleccionado.</p></div>';
  var js = filtrar(e.jugadores||[]);
  return '<div class="card" style="padding:var(--g4)">'+
    '<select class="inp inp-sm" style="margin-bottom:var(--g3)" data-c="traspasos:club" data-lado="'+lado+'" aria-label="Club de la columna">'+
      activos.map(function(x){ return '<option value="'+esc(x.id)+'"'+(x.id===e.id?' selected':'')+'>'+esc(x.nombre)+'</option>'; }).join('')+
    '</select>'+
    '<div style="display:flex;align-items:center;gap:.4rem;margin-bottom:var(--g2)">'+
      U.escudo(e)+
      '<span class="ayuda">'+((e.jugadores||[]).length)+' jugadores · '+
        ((e.jugadores||[]).filter(function(j){ return j.titular; }).length)+' titulares</span>'+
    '</div>'+
    '<div class="dnd-col" data-destino="'+esc(e.id)+'" style="min-height:180px">'+
      (js.length ? js.map(function(j){ return ficha(j, e); }).join('')
                 : '<div class="vacio">'+(q?'Nadie coincide con el filtro.':'Plantilla vacía.')+'</div>')+
    '</div></div>';
}

function columnaLibres(D){
  var js = filtrar(D.agentes_libres||[]);
  /* Se limita la lista pintada: 133 fichas arrastrables de golpe hacen la
     pantalla pesada y no aportan nada frente a filtrar. */
  var tope = js.slice(0, 40);
  return '<div class="card" style="padding:var(--g4)">'+
    '<div style="height:34px;display:flex;align-items:center;margin-bottom:var(--g3)">'+
      '<b style="font-size:.8125rem">Agentes libres</b>'+
      '<span class="pastilla" style="margin-left:auto">'+js.length+'</span>'+
    '</div>'+
    '<p class="ayuda" style="margin-bottom:var(--g2)">Sin club. No aparecen en la web.</p>'+
    '<div class="dnd-col" data-destino="" style="min-height:180px">'+
      (tope.length ? tope.map(function(j){ return ficha(j, null); }).join('')
                   : '<div class="vacio">'+(q?'Nadie coincide con el filtro.':'Sin agentes libres.')+'</div>')+
    '</div>'+
    (js.length>tope.length ? '<p class="ayuda" style="margin-top:var(--g2)">Se muestran '+tope.length+' de '+js.length+'. Usa el filtro para llegar al resto.</p>' : '')+
  '</div>';
}

function filtrar(js){
  if(!q) return js;
  var n = C.norm(q);
  return js.filter(function(j){ return C.norm(j.nombre+' '+(j.posicion||'')).indexOf(n)>=0; });
}

/* La ficha lleva el club de origen dentro: al soltarla, es lo único que hace
   falta para saber de dónde sale. */
function ficha(j, e){
  return '<div class="dnd-ficha tr-j" data-n="'+esc(j.nombre)+'" data-origen="'+esc(e?e.id:'')+'" role="button" tabindex="0" '+
      'aria-label="'+esc(j.nombre+', '+(j.posicion||'')+(e?', '+e.nombre:', agente libre'))+'">'+
    '<i class="ph ph-dots-six-vertical dnd-asa" aria-hidden="true"></i>'+
    '<span class="mono" style="color:var(--ink-4);min-width:16px">'+esc(j.dorsal||'')+'</span>'+
    '<span class="chip chip-'+String(j.posicion||'').toLowerCase()+'">'+esc(j.posicion||'—')+'</span>'+
    '<span class="nm">'+esc(j.nombre)+'</span>'+
    '<span class="tras"><span class="pastilla" title="Goles de carrera">'+((j.goles_totales||0)+(j.goles||0))+'G</span></span>'+
  '</div>';
}

function montar(){
  var cols = document.querySelectorAll('.dnd-col[data-destino]');
  if(!cols.length) return;
  SFG.dnd.sortable({
    grupo:'traspasos', item:'.tr-j',
    contenedores:Array.prototype.slice.call(cols),
    alSoltar:function(dd){
      var origenId = dd.item.dataset.origen;
      var destinoId = dd.hasta.dataset.destino;
      if(origenId===destinoId) return;          // misma columna: sin efecto
      confirmarTraspaso(dd.item.dataset.n, origenId, destinoId);
    }
  });
}

function buscar(nombre, clubId){
  if(clubId){
    var e = C.equipoPorId(clubId);
    return {j:(e.jugadores||[]).find(function(x){ return x.nombre===nombre; }), e:e};
  }
  return {j:(d().agentes_libres||[]).find(function(x){ return x.nombre===nombre; }), e:null};
}

function confirmarTraspaso(nombre, origenId, destinoId){
  var o = buscar(nombre, origenId);
  if(!o.j){ U.refrescar(); return; }
  var destino = destinoId ? C.equipoPorId(destinoId) : null;
  var etiqueta = 'Temporada '+(d().config.temporada||'?');
  var golesTemp = o.j.goles||0;

  U.confirmar({
    titulo: destino ? 'Fichaje de '+nombre : nombre+' se queda sin club',
    html: '<b>'+esc(nombre)+'</b> pasa de <b>'+esc(o.e?o.e.nombre:'agente libre')+'</b> a <b>'+esc(destino?destino.nombre:'agente libre')+'</b>.<br><br>'+
      'Se registrará en su historial:'+
      '<ul style="margin:.5rem 0 .5rem 1.1rem;line-height:1.8">'+
        (o.e ? '<li>Cierre de su etapa en '+esc(o.e.nombre)+', fin en «'+esc(etiqueta)+'».</li>' : '')+
        (destino ? '<li>Etapa nueva en '+esc(destino.nombre)+', abierta desde «'+esc(etiqueta)+'».</li>' : '')+
      '</ul>'+
      (golesTemp && o.e
        ? 'Sus <b>'+golesTemp+' goles</b> de esta temporada se quedan apuntados en '+esc(o.e.nombre)+' y su ficha empieza de cero. La cifra de carrera no cambia.<br><br>'
        : '')+
      (destino ? 'Llega al banquillo: repásale la posición y el dorsal en la ficha del club.' : 'Los agentes libres no salen en la web pública.'),
    ok: destino ? 'Fichar' : 'Dejar libre'
  }).then(function(si){
    if(!si) return U.refrescar();
    C.traspasar(d(), o.j, o.e, destino);
    U.cambio();
    U.aviso(nombre+' → '+(destino?destino.nombre:'agentes libres')+'.', 'ok');
  });
}

var A = {
  filtro: function(el){ q = el.value; U.refrescar(); },
  club: function(el){
    if(el.dataset.lado==='izq') izq = el.value; else der = el.value;
    U.refrescar();
  }
};

U.registrar('traspasos', {acciones:A, render:pintar});

})();
