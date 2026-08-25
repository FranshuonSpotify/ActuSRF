/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-contenido.js
   Noticias y configuración de la competición.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

var abierta = null;      // índice de la noticia en edición
var resAbierta = null;   // índice de la reseña en edición

/* Las reseñas de la portada viven hoy CODIFICADAS dentro de app.js, en un
   array `QUOTES` marcado como «Contenido de ejemplo». No están en el archivo
   de datos, así que desde aquí no se pueden cambiar todavía.

   Se guardan en `config.resenas`, que es aditivo y no molesta a nadie, y la
   pantalla dice con todas las letras que la web no las leerá hasta aplicar
   un parche de tres líneas en `renderQuotes()`. Prefiero eso a un editor que
   parezca funcionar y no cambie nada de lo que se ve. */
function resenas(){
  var c = d().config;
  if(!Array.isArray(c.resenas)) c.resenas = [];
  return c.resenas;
}

function d(){ return SFG.d(); }

/* --------------------------------------------------------------------------
   NOTICIAS
   -------------------------------------------------------------------------- */
function pintarNoticias(el){
  var ns = d().noticias;
  el.innerHTML =
    U.cabecera('Noticias', ns.length+' publicadas · la primera es la que abre la portada',
      '<button class="btn btn-primary btn-sm" data-a="noticias:nueva"><i class="ph-bold ph-plus"></i> Nueva noticia</button>')+
    /* El orden del array ES el orden de la web: renderNews() recorre
       bd.noticias tal cual. No hace falta ningún campo "fijada": subir una
       noticia al puesto 1 es fijarla. */
    '<p class="ayuda" style="margin-bottom:var(--g4)">Arrastra para reordenar; el orden es el que verá la web. Sin ratón: enfoca una tarjeta y usa las flechas, o el botón de fijar arriba.</p>'+
    (ns.length
      ? '<div class="rejilla" style="--min:300px" id="not-lista">'+ns.map(tarjeta).join('')+'</div>'
      : '<div class="vacio">Todavía no hay noticias.</div>');

  var cont = document.getElementById('not-lista');
  if(cont) SFG.dnd.sortable({
    grupo:'noticias', item:'.not-c', contenedores:[cont],
    alSoltar:function(dd){
      var ns = d().noticias;
      var n = ns.splice(Number(dd.item.dataset.i), 1)[0];
      ns.splice(dd.indice, 0, n);
      U.cambio();
      U.aviso('«'+(n.titulo||'sin título')+'» ahora es la '+(dd.indice+1)+'.ª.', 'ok');
    }
  });
}
function tarjeta(n, i){
  return '<article class="card not-c" style="overflow:hidden" data-i="'+i+'" role="button" tabindex="0" '+
      'aria-label="'+esc((n.titulo||'sin título')+', posición '+(i+1))+'">'+
    '<div style="display:flex;align-items:center;gap:.35rem;padding:.35rem .5rem;border-bottom:1px solid var(--line);background:var(--bg-raised)">'+
      '<i class="ph ph-dots-six-vertical dnd-asa" aria-hidden="true"></i>'+
      '<span class="mono" style="font-size:.625rem;color:'+(i===0?'var(--accent)':'var(--ink-4)')+'">'+
        (i===0?'PORTADA':'#'+(i+1))+'</span>'+
      (i>0 ? '<button class="ir" style="margin-left:auto" data-a="noticias:fijar" data-i="'+i+'">Fijar arriba</button>' : '')+
    '</div>'+
    (/^https?:/.test(n.imagen||'')
      ? '<img src="'+esc(n.imagen)+'" alt="" referrerpolicy="no-referrer" style="width:100%;height:132px;object-fit:cover" loading="lazy">'
      : '<div style="height:132px;display:grid;place-items:center;color:var(--ink-5);background:var(--surface-2)"><i class="ph ph-image" style="font-size:1.5rem"></i></div>')+
    '<div style="padding:var(--g4)">'+
      '<div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.5rem">'+
        '<span class="pastilla" style="background:'+esc(n.color||'#333')+'22;color:'+esc(n.color||'#999')+'">'+esc(n.tag||'SIN ETIQUETA')+'</span>'+
        (n.video ? '<i class="ph ph-video" title="Tiene vídeo" style="color:var(--ink-4)"></i>' : '')+
        '<span class="ayuda" style="margin-left:auto">'+esc(n.fecha||'')+'</span>'+
      '</div>'+
      '<h3 style="font-size:.9375rem;line-height:1.3;margin-bottom:.35rem">'+esc(n.titulo||'Sin título')+'</h3>'+
      '<p style="font-size:.8125rem;color:var(--ink-3);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">'+esc(n.resumen||'')+'</p>'+
      '<div style="display:flex;gap:.4rem;margin-top:var(--g3)">'+
        '<button class="btn btn-secondary btn-sm" data-a="noticias:editar" data-i="'+i+'">Editar</button>'+
        '<button class="btn btn-secondary btn-sm" data-a="noticias:borrar" data-i="'+i+'">Eliminar</button>'+
      '</div>'+
    '</div></article>';
}

function editar(i){
  abierta = i;
  var n = d().noticias[i];
  U.modal({
    titulo: n.titulo || 'Nueva noticia',
    ancho: true,
    cuerpo: formNoticia(n),
    pie: [{txt:'Hecho', cls:'btn-primary', fn:function(){ U.cerrarModal(); }}],
    alCerrar: function(){ abierta = null; U.refrescar(); }
  });
}
function formNoticia(n){
  /* Las etiquetas y colores existentes se ofrecen como sugerencia para que no
     se multipliquen variantes de la misma sección por una tilde de más. */
  var tags = Array.from(new Set(d().noticias.map(function(x){ return x.tag; }).filter(Boolean)));
  return '<div class="rejilla rejilla-2">'+
      U.campo('Etiqueta',
        '<input class="inp" list="tags-noticia" value="'+esc(n.tag||'')+'" data-c="noticias:campo" data-k="tag">'+
        '<datalist id="tags-noticia">'+tags.map(function(t){ return '<option value="'+esc(t)+'">'; }).join('')+'</datalist>')+
      U.campo('Color de la etiqueta',
        '<div class="color-par">'+
          '<input type="color" value="'+esc(hex(n.color))+'" data-c="noticias:color">'+
          '<input class="inp inp-mono" value="'+esc(n.color||'')+'" data-c="noticias:campo" data-k="color" placeholder="#FF5100">'+
        '</div>')+
      U.campo('Autor', '<input class="inp" value="'+esc(n.autor||'')+'" data-c="noticias:campo" data-k="autor">')+
      U.campo('Fecha', '<input class="inp" value="'+esc(n.fecha||'')+'" data-c="noticias:campo" data-k="fecha" placeholder="dd/mm/aaaa">')+
    '</div>'+
    '<div class="g-hueco"></div>'+
    U.campo('Titular', '<input class="inp" value="'+esc(n.titulo||'')+'" data-c="noticias:campo" data-k="titulo">')+
    '<div class="g-hueco"></div>'+
    U.campo('Entradilla', '<textarea class="inp" data-c="noticias:campo" data-k="resumen" style="min-height:64px">'+esc(n.resumen||'')+'</textarea>',
      'Es lo que se lee en el listado y en el carrusel de portada.')+
    '<div class="g-hueco"></div>'+
    U.campo('Cuerpo', '<textarea class="inp" data-c="noticias:campo" data-k="cuerpo" style="min-height:200px">'+esc(n.cuerpo||'')+'</textarea>')+
    '<div class="g-hueco"></div>'+
    '<div class="rejilla rejilla-2">'+
      U.campoImagen('Imagen', n.imagen||'', 'noticias:imagen')+
      U.campo('Vídeo (URL)', '<input class="inp" value="'+esc(n.video||'')+'" data-c="noticias:campo" data-k="video" placeholder="opcional">')+
    '</div>';
}
function hex(x){
  var s = String(x||'').trim();
  if(/^#[0-9a-f]{6}$/i.test(s)) return s;
  if(/^#[0-9a-f]{3}$/i.test(s)) return '#'+s[1]+s[1]+s[2]+s[2]+s[3]+s[3];
  return '#FF5100';
}

var AN = {
  nueva: function(){
    d().noticias.unshift({
      tag:'NOTICIAS', color:'#FF5100', titulo:'', resumen:'', cuerpo:'',
      imagen:'', autor:'', fecha:new Date().toLocaleDateString('es-ES')
    });
    U.cambio();
    editar(0);
  },
  editar: function(el){ editar(Number(el.dataset.i)); },
  fijar: function(el){
    var ns = d().noticias, n = ns.splice(Number(el.dataset.i), 1)[0];
    ns.unshift(n);
    U.cambio();
    U.aviso('«'+(n.titulo||'sin título')+'» abre la portada.', 'ok');
  },
  borrar: function(el){
    var i = Number(el.dataset.i), n = d().noticias[i];
    U.confirmar({titulo:'Eliminar noticia', texto:'«'+(n.titulo||'sin título')+'» dejará de aparecer en la web.', ok:'Eliminar', peligro:true})
      .then(function(si){ if(si){ d().noticias.splice(i,1); U.cambio(); U.aviso('Noticia eliminada.','ok'); } });
  },
  campo: function(el){
    d().noticias[abierta][el.dataset.k] = el.value;
    SFG.io.marcarSucio();
    if(el.dataset.k==='color'){
      var p = document.querySelector('[data-c="noticias:color"]');
      if(p && /^#[0-9a-f]{6}$/i.test(el.value)) p.value = el.value;
    }
  },
  imagen: function(el){ d().noticias[abierta].imagen = el.value; SFG.io.marcarSucio(); },
  color: function(el){
    d().noticias[abierta].color = el.value;
    var t = document.querySelector('[data-c="noticias:campo"][data-k="color"]');
    if(t) t.value = el.value;
    SFG.io.marcarSucio();
  }
};

/* --------------------------------------------------------------------------
   CONFIGURACIÓN
   -------------------------------------------------------------------------- */
function pintarConfig(el){
  var c = d().config;
  var maxJ = ['partidos_liga','partidos_ascenso'].reduce(function(m,k){
    return d()[k].reduce(function(a,p){ return Math.max(a, parseInt(p.jornada)||0); }, m);
  }, 0);
  var jorActual = parseInt(c.jornada_actual)||0;

  el.innerHTML =
    U.cabecera('Configuración', 'Ajustes globales de la temporada')+

    '<div class="card" style="padding:var(--g5)">'+
      '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Competición</h3>'+
      '<div class="rejilla rejilla-2">'+
        U.campo('Nombre de la liga', '<input class="inp" value="'+esc(c.nombre_liga||'')+'" data-c="config:campo" data-k="nombre_liga">')+
        U.campo('Temporada', '<input class="inp inp-mono" value="'+esc(c.temporada||'')+'" data-c="config:campo" data-k="temporada">',
          'Se guarda como texto: la web lo lee con parseInt.')+
        U.campo('Jornada actual', '<input class="inp inp-mono" value="'+esc(c.jornada_actual||'')+'" data-c="config:campo" data-k="jornada_actual">',
          maxJ ? 'El calendario llega hasta la jornada '+maxJ+'.' : '')+
        U.campo('Twitter / X', '<input class="inp" value="'+esc((c.medios&&c.medios.twitter)||'')+'" data-c="config:medio" data-k="twitter" placeholder="https://x.com/…">')+
      '</div>'+
      (jorActual>maxJ && maxJ ? '<p class="mal" style="margin-top:var(--g4);font-size:.8125rem">'+
        '<i class="ph-bold ph-warning"></i> La jornada actual ('+jorActual+') va por delante del calendario, que acaba en la '+maxJ+'.</p>' : '')+
    '</div>'+

    '<div class="g-hueco"></div>'+
    bloqueFormatos(c)+
    '<div class="g-hueco"></div>'+

    '<div class="card" style="padding:var(--g5)">'+
      '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Tickers</h3>'+
      /* Honestidad con el usuario: se conservan porque son parte del archivo,
         pero hoy la web no los lee en ningún sitio. */
      '<p class="ayuda" style="margin-bottom:var(--g4)">Se conservan en el archivo, pero la web pública no los muestra en ninguna pantalla ahora mismo.</p>'+
      [['ticker_superliga','Superliga'],['ticker_ascenso','Ascenso'],['ticker_copa','Copa']].map(function(t){
        var arr = c[t[0]]||[];
        return '<div style="margin-bottom:var(--g5)">'+
          '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g2)">'+
            '<label style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-4)">'+t[1]+'</label>'+
            '<button class="btn btn-secondary btn-sm" data-a="config:tickerAdd" data-k="'+t[0]+'"><i class="ph ph-plus"></i></button>'+
          '</div>'+
          (arr.length ? arr.map(function(x,i){
            return '<div style="display:flex;gap:.35rem;margin-bottom:.35rem">'+
              '<input class="inp inp-sm" value="'+esc(x)+'" data-c="config:ticker" data-k="'+t[0]+'" data-i="'+i+'">'+
              '<button class="btn btn-secondary btn-sm" data-a="config:tickerDel" data-k="'+t[0]+'" data-i="'+i+'">×</button></div>';
          }).join('') : '<p class="ayuda">Vacío.</p>')+
        '</div>';
      }).join('')+
    '</div>';
}

/* --------------------------------------------------------------------------
   FORMATOS DE COMPETICIÓN

   Qué son y qué no: describen cómo está montada cada competición y alimentan
   las comprobaciones del gestor y, más adelante, los generadores de calendario
   y de sorteo. NO los lee la web pública: los cortes de la tabla (play-off,
   play-in, descenso, ascenso) están escritos a mano dentro de renderClas() de
   app.js. Cuando lo que se pone aquí contradice a lo que la web tiene fijo, el
   gestor lo dice en la misma línea en vez de dejar creer que ha cambiado algo.
   -------------------------------------------------------------------------- */
var CAMPOS_FMT = {
  SUPERLIGA:[['vueltas','Vueltas','Cuántas veces se enfrentan dos clubes en la fase regular'],
             ['equipos','Equipos',''],
             ['playoff','Plazas de play-off',''],
             ['playin','Puesto de play-in',''],
             ['partido_playin','Último puesto que juega el partido por el play-in',''],
             ['descenso','Plazas de descenso','']],
  ASCENSO:  [['vueltas','Vueltas',''],['equipos','Equipos',''],['ascenso','Plazas de ascenso directo','']],
  COPA:     [['equipos','Equipos',''],['grupos','Número de grupos','Define las letras disponibles al repartir'],
             ['clasifican_por_grupo','Pasan por grupo','']]
};
function bloqueFormatos(c){
  var fmt = c.formatos || {};
  return '<div class="card" style="padding:var(--g5)">'+
    '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Formato de las competiciones</h3>'+
    '<p class="ayuda" style="margin-bottom:var(--g5)">Describe cómo está montada cada competición. Lo usa el gestor para avisarte de descuadres y para repartir los grupos de Copa. '+
      '<b>La web pública no lo lee:</b> los cortes de la tabla están fijos en su código, así que cambiarlos aquí no cambia lo que se ve.</p>'+

    Object.keys(CAMPOS_FMT).map(function(comp){
      var f = fmt[comp] || {};
      var z = C.ZONAS_APP[comp] || {};
      return '<div style="margin-bottom:var(--g5)">'+
        '<div style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.12em;color:var(--ink-3);margin-bottom:var(--g3)">'+comp+'</div>'+
        '<div class="rejilla rejilla-4">'+
          CAMPOS_FMT[comp].map(function(campo){
            var k = campo[0];
            /* Si la web tiene ese corte escrito a mano y aquí dice otra cosa,
               se marca el campo: es el punto exacto donde el gestor y la web
               dejarían de contar lo mismo. */
            var fijo = z[k]!=null && f[k]!=null && f[k]!==z[k];
            return U.campo(campo[1],
              '<input class="inp inp-mono" type="number" min="0" max="99" value="'+(f[k]!=null?f[k]:'')+'" '+
                'data-c="config:formato" data-comp="'+comp+'" data-k="'+k+'"'+(fijo?' style="border-color:var(--gold)"':'')+'>',
              fijo ? 'la web lo tiene fijo en '+z[k] : campo[2]);
          }).join('')+
          (comp==='COPA'
            ? '<div class="campo"><label>Formato</label>'+
                '<select class="inp" data-c="config:formatoTxt" data-comp="COPA" data-k="tipo">'+
                  [['grupos','Grupos + eliminatoria'],['directa','Eliminatoria directa']].map(function(t){
                    return '<option value="'+t[0]+'"'+(f.tipo===t[0]?' selected':'')+'>'+t[1]+'</option>'; }).join('')+
                '</select></div>'+
              '<div class="campo"><label>Ida y vuelta en grupos</label>'+
                '<label class="sw"><input type="checkbox"'+(f.ida_vuelta?' checked':'')+' data-c="config:formatoBool" data-comp="COPA" data-k="ida_vuelta">'+
                '<span class="pista"></span> Doble enfrentamiento</label></div>'
            : '')+
        '</div></div>';
    }).join('')+
  '</div>';
}

var AG = {
  campo: function(el){ d().config[el.dataset.k] = el.value; U.cambio(true); },
  formato: function(el){
    fmt(el)[el.dataset.k] = el.value===''? null : (Number(el.value)||0);
    U.cambio(true);
  },
  formatoTxt: function(el){ fmt(el)[el.dataset.k] = el.value; U.cambio(); },
  formatoBool: function(el){ fmt(el)[el.dataset.k] = el.checked; U.cambio(true); },
  medio: function(el){
    if(!d().config.medios) d().config.medios = {};
    d().config.medios[el.dataset.k] = el.value;
    U.cambio(true);
  },
  tickerAdd: function(el){ d().config[el.dataset.k].push(''); U.cambio(); },
  tickerDel: function(el){ d().config[el.dataset.k].splice(Number(el.dataset.i),1); U.cambio(); },
  ticker: function(el){ d().config[el.dataset.k][Number(el.dataset.i)] = el.value; SFG.io.marcarSucio(); }
};

function fmt(el){
  var f = d().config.formatos;
  if(!f[el.dataset.comp]) f[el.dataset.comp] = {};
  return f[el.dataset.comp];
}

/* --------------------------------------------------------------------------
   RESEÑAS DE LA PORTADA
   -------------------------------------------------------------------------- */
function pintarResenas(el){
  var rs = resenas();
  el.innerHTML =
    U.cabecera('Reseñas', 'Las citas de manager que salen en la portada',
      '<button class="btn btn-primary btn-sm" data-a="resenas:nueva"><i class="ph-bold ph-plus"></i> Nueva reseña</button>')+

    '<div class="card" style="padding:var(--g5);margin-bottom:var(--g5);border-color:rgba(255,201,74,.3)">'+
      '<div style="display:flex;gap:var(--g3);align-items:flex-start">'+
        '<i class="ph-bold ph-warning" style="color:var(--gold);font-size:1.25rem;flex-shrink:0;margin-top:.1rem"></i>'+
        '<div><b style="font-size:.9375rem">La web todavía no lee estas reseñas</b>'+
        '<p class="ayuda" style="margin-top:.25rem">Hoy están escritas a mano dentro de '+
          '<span class="mono">_fuente/app.js</span>, en un array <span class="mono">QUOTES</span> '+
          'marcado como «Contenido de ejemplo». Lo que guardes aquí va a '+
          '<span class="mono">config.resenas</span> y queda listo, pero la portada seguirá enseñando las de siempre '+
          'hasta que se aplique un cambio de tres líneas en <span class="mono">renderQuotes()</span> para que las lea del archivo. '+
          '<b>Ese cambio toca la web pública, así que no lo he hecho sin preguntar.</b></p></div>'+
      '</div></div>'+

    (rs.length
      ? '<div class="rejilla" style="--min:300px">'+rs.map(tarjetaResena).join('')+'</div>'
      : '<div class="vacio">Sin reseñas propias. Las tres de la portada son las de ejemplo de app.js.</div>');
}
function tarjetaResena(r, i){
  var nota = Math.max(0, Math.min(10, Number(r.n)||0));
  return '<article class="card" style="padding:var(--g5)">'+
    '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:var(--g3)">'+
      '<span class="mono" style="font-size:1.5rem;font-weight:600;letter-spacing:-.03em">'+nota.toFixed(1)+
        '<span style="font-size:.75rem;color:var(--ink-3)">/10</span></span>'+
      '<span class="pastilla" style="margin-left:auto">#'+(i+1)+'</span></div>'+
    '<div style="height:4px;border-radius:2px;background:var(--surface-3);overflow:hidden;margin-bottom:var(--g4)">'+
      '<div style="height:100%;width:'+(nota*10)+'%;background:var(--accent)"></div></div>'+
    '<p style="font-size:.8125rem;color:var(--ink-2);line-height:1.55;margin-bottom:var(--g3);'+
      'display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">'+esc(r.t||'')+'</p>'+
    '<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:var(--g3)">'+
      (/^(https?:|data:|assets\/)/.test(r.i||'')
        ? '<img src="'+esc(r.i)+'" alt="" class="escudo escudo-lg" style="border-radius:50%;object-fit:cover" referrerpolicy="no-referrer">'
        : '<span class="escudo escudo-lg noimg" style="border-radius:50%">?</span>')+
      '<div style="min-width:0"><b style="font-size:.8125rem;display:block">'+esc(r.a||'Sin autor')+'</b>'+
        '<span class="ayuda">'+esc(r.s||'')+'</span></div></div>'+
    '<div style="display:flex;gap:.4rem">'+
      '<button class="btn btn-secondary btn-sm" data-a="resenas:editar" data-i="'+i+'">Editar</button>'+
      '<button class="btn btn-secondary btn-sm" data-a="resenas:borrar" data-i="'+i+'">Eliminar</button>'+
    '</div></article>';
}
function editarResena(i){
  resAbierta = i;
  var r = resenas()[i];
  U.modal({
    titulo: r.a || 'Nueva reseña',
    ancho: true,
    cuerpo:
      U.campo('Cita', '<textarea class="inp" data-c="resenas:campo" data-k="t" style="min-height:120px">'+esc(r.t||'')+'</textarea>',
        'Sin comillas: la web se las pone.')+
      '<div class="g-hueco"></div>'+
      '<div class="rejilla rejilla-2">'+
        U.campo('Autor', '<input class="inp" value="'+esc(r.a||'')+'" data-c="resenas:campo" data-k="a">')+
        U.campo('Debajo del nombre', '<input class="inp" value="'+esc(r.s||'')+'" data-c="resenas:campo" data-k="s">')+
      '</div>'+
      '<div class="g-hueco"></div>'+
      '<div class="rejilla rejilla-2">'+
        U.campo('Nota sobre 10',
          '<input class="inp inp-mono" type="number" min="0" max="10" step="0.1" value="'+(Number(r.n)||0)+'" data-c="resenas:nota">',
          'La web dibuja una barra que crece hasta esta nota.')+
        U.campoImagen('Foto', r.i||'', 'resenas:foto')+
      '</div>',
    pie:[{txt:'Hecho', cls:'btn-primary', fn:function(){ U.cerrarModal(); }}],
    alCerrar:function(){ resAbierta = null; U.refrescar(); }
  });
}

var AR = {
  nueva: function(){
    resenas().push({t:'', a:'', s:'', i:'', n:9});
    U.cambio();
    editarResena(resenas().length-1);
  },
  editar: function(el){ editarResena(Number(el.dataset.i)); },
  borrar: function(el){
    var i = Number(el.dataset.i), r = resenas()[i];
    U.confirmar({titulo:'Eliminar reseña', texto:'Se borrará la cita de «'+(r.a||'sin autor')+'».', ok:'Eliminar', peligro:true})
      .then(function(si){ if(si){ resenas().splice(i,1); U.cambio(); U.aviso('Reseña eliminada.','ok'); } });
  },
  campo: function(el){ resenas()[resAbierta][el.dataset.k] = el.value; SFG.io.marcarSucio(); },
  nota:  function(el){ resenas()[resAbierta].n = Math.max(0, Math.min(10, Number(el.value)||0)); SFG.io.marcarSucio(); },
  foto:  function(el){ resenas()[resAbierta].i = el.value; SFG.io.marcarSucio(); }
};

U.registrar('resenas', {acciones:AR, render:pintarResenas});

U.registrar('noticias', {
  acciones: AN,
  render: function(el, param){
    pintarNoticias(el);
    if(param && param.idx!=null) editar(param.idx);
  }
});
U.registrar('config', {acciones:AG, render:pintarConfig});

})();
