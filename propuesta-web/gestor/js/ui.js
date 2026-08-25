/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — ui.js
   Armazón: navegación, avisos, modal, buscador global y el pegamento entre
   las vistas y el archivo.

   Las vistas no tocan el DOM fuera de su propia sección ni escuchan eventos
   por su cuenta: pintan una cadena de HTML con atributos data-a / data-c y
   aquí se despacha. Es el mismo patrón que app.js adoptó tras romperse con
   los onclick que llevaban datos dentro.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG;
var C = SFG.core, IO = SFG.io;
var $ = function(id){ return document.getElementById(id); };
var esc = C.esc;

/* --------------------------------------------------------------------------
   VISTAS
   -------------------------------------------------------------------------- */
var vistas = {};       // nombre -> {render, buscar, irA}
var acciones = {};     // nombre -> {accion: fn}
var actual = null;

function registrar(nombre, def){
  vistas[nombre] = def;
  acciones[nombre] = def.acciones || {};
}

function irA(nombre, param){
  if(!SFG.d() && nombre!=='inicio') return;
  var sec = $('s-'+nombre);
  if(!sec) return;
  document.querySelectorAll('.g-vista > .seccion').forEach(function(s){ s.classList.remove('on'); });
  sec.classList.add('on');
  document.querySelectorAll('.g-nav a').forEach(function(a){ a.classList.toggle('on', a.dataset.s===nombre); });
  actual = nombre;
  if(location.hash.slice(1)!==nombre) history.replaceState(null,'','#'+nombre);
  pintar(nombre, param);
  $('vista').scrollTop = 0;
}

function pintar(nombre, param){
  var v = vistas[nombre];
  if(!v || !SFG.d()) return;
  try { v.render($('s-'+nombre), param); }
  catch(e){
    console.error('Fallo al pintar "'+nombre+'"', e);
    $('s-'+nombre).innerHTML = '<div class="vacio">No se pudo pintar esta sección: '+esc(e.message)+'</div>';
  }
}

/* Repinta lo que se esté viendo y actualiza los contadores de la navegación.
   Se llama tras cualquier cambio de datos. */
function refrescar(){
  if(actual) pintar(actual);
  contadores();
}
/* Un cambio en los datos: marca sucio y repinta. Todo lo que edite algo pasa
   por aquí, para que el estado del archivo nunca mienta. */
function cambio(soloMarcar){
  IO.marcarSucio();
  if(soloMarcar) contadores(); else refrescar();
}

/* Los contadores se parten en dos por una razón medida: validar la integridad
   y analizar los nombres cuestan decenas de milisegundos cada uno, y esto
   corre después de CADA edición. Hacerlo todo aquí metía 230 ms entre pulsar
   una tecla y ver el resultado. Lo barato se actualiza al instante; lo caro,
   un momento después y sólo cuando se ha dejado de escribir. */
function contadores(){
  var d = SFG.d(); if(!d) return;
  $('n-equipos').textContent = d.equipos.filter(function(e){ return !e.archivado; }).length;
  $('n-partidos').textContent = d.partidos_liga.length + d.partidos_ascenso.length;
  $('n-copa').textContent = d.partidos_copa.length;
  $('n-noticias').textContent = d.noticias.length;
  $('n-resenas').textContent = (d.config.resenas||[]).length || '';
  $('n-temporadas').textContent = d.historial_temporadas.length || '';
  $('n-traspasos').textContent = d.agentes_libres.length || '';
  $('n-papelera').textContent = d.equipos.filter(function(e){ return e.archivado; }).length || '';
  programarAvisos();
}

var relojAvisos = null;
function programarAvisos(){
  clearTimeout(relojAvisos);
  relojAvisos = setTimeout(avisosCaros, 400);
}
function avisosCaros(){
  var d = SFG.d(); if(!d) return;
  var v = C.validarIntegridad(d);
  $('n-datos').textContent = v.err.length ? v.err.length : '';
  $('n-datos').title = v.err.length ? v.err.length+' problemas críticos' : '';
  /* Sin la comparación por parejas, que es la parte de verdad cara: eso sólo
     se hace a petición desde la propia pantalla de Nombres. */
  var nb = C.analizarNombres(d, {parejas:false});
  var nMal = nb.huerfanos.length + nb.difusos.length + nb.ambiguos.length + nb.otroClub.length;
  $('n-nombres').textContent = nMal || '';
  $('n-nombres').title = nMal ? nMal+' nombres que revisar' : '';
}

/* --------------------------------------------------------------------------
   DESPACHO DE EVENTOS
   data-a="vista:accion"  -> clic
   data-c="vista:campo"   -> change / input sobre un control
   data-i / data-j        -> índices que el manejador lee de el.dataset
   -------------------------------------------------------------------------- */
function despachar(attr, el, ev){
  var partes = String(el.dataset[attr]||'').split(':');
  var v = acciones[partes[0]];
  var fn = v && v[partes[1]];
  if(fn) fn(el, ev);
  return !!fn;
}
document.addEventListener('click', function(ev){
  var el = ev.target.closest && ev.target.closest('[data-a]');
  if(!el || document.body.classList.contains('bloqueado')) return;
  if(despachar('a', el, ev)) ev.preventDefault();
});
['change','input'].forEach(function(tipo){
  document.addEventListener(tipo, function(ev){
    var el = ev.target.closest && ev.target.closest('[data-c]');
    if(!el) return;
    /* Los campos de texto se aplican al salir (change) para no repintar en
       cada tecla; los selectores y casillas, al instante. */
    var vivo = el.tagName==='SELECT' || el.type==='checkbox' || el.type==='color' || el.type==='range';
    if(tipo==='input' && !vivo) return;
    if(tipo==='change' && vivo && el.type==='color') return;   // color ya llegó por input
    despachar('c', el, ev);
  });
});

/* --------------------------------------------------------------------------
   AVISOS
   -------------------------------------------------------------------------- */
var ICONO = {ok:'ph-bold ph-check-circle', mal:'ph-bold ph-warning-octagon', ojo:'ph-bold ph-warning', info:'ph ph-info'};
function aviso(texto, tipo, ms){
  tipo = tipo || 'ok';
  var el = document.createElement('div');
  el.className = 'g-aviso '+tipo;
  /* Un error tiene que interrumpir al lector de pantalla; un "guardado"
     puede esperar a que termine la frase en curso. La region contenedora es
     polite, asi que los errores llevan su propio role=alert. */
  if(tipo==='mal'){ el.setAttribute('role','alert'); el.setAttribute('aria-live','assertive'); }
  el.innerHTML = '<i class="'+ICONO[tipo]+'" aria-hidden="true"></i><span>'+esc(texto)+'</span>';
  $('avisos').appendChild(el);
  setTimeout(function(){
    el.classList.add('saliendo');
    setTimeout(function(){ el.remove(); }, 320);
  }, ms || (tipo==='mal' ? 7000 : 3800));
}

/* --------------------------------------------------------------------------
   MODAL
   Uno solo para todo. Atrapa el foco, cierra con Esc y lo devuelve donde
   estaba: es lo mínimo para que se pueda usar sin ratón.
   -------------------------------------------------------------------------- */
var focoPrevio = null, alCerrar = null;
function modal(cfg){
  focoPrevio = document.activeElement;
  alCerrar = cfg.alCerrar || null;
  $('ov-tit').textContent = cfg.titulo || '';
  $('ov-cuerpo').innerHTML = cfg.cuerpo || '';
  $('ov-panel').className = 'ov-panel '+(cfg.ancho ? 'ancho' : 'medio');
  var pie = $('ov-pie');
  pie.innerHTML = '';
  (cfg.pie||[]).forEach(function(b){
    var el = document.createElement('button');
    el.className = 'btn btn-sm '+(b.cls||'btn-secondary');
    el.innerHTML = b.txt;
    if(b.izq) el.classList.add('izq');
    if(b.id) el.id = b.id;
    el.addEventListener('click', function(){ b.fn && b.fn(); });
    pie.appendChild(el);
  });
  $('ov').classList.add('open');
  var primero = $('ov-cuerpo').querySelector('input,select,textarea,button') || $('ov-x');
  setTimeout(function(){ primero.focus(); }, 60);
  if(cfg.tras) cfg.tras($('ov-cuerpo'));
}
function cerrarModal(){
  if(!$('ov').classList.contains('open')) return;
  $('ov').classList.remove('open');
  $('ov-cuerpo').innerHTML = '';
  var cb = alCerrar; alCerrar = null;
  if(focoPrevio && focoPrevio.isConnected) focoPrevio.focus();
  if(cb) cb();
}
$('ov-x').addEventListener('click', cerrarModal);
$('ov').addEventListener('click', function(e){ if(e.target===this) cerrarModal(); });

/* Foco atrapado dentro del modal abierto. */
document.addEventListener('keydown', function(e){
  if(e.key==='Escape'){
    if($('ov-buscar').classList.contains('open')) return cerrarBuscar();
    if($('ov').classList.contains('open')) return cerrarModal();
  }
  if(e.key!=='Tab') return;
  var ov = $('ov').classList.contains('open') ? $('ov') : ($('ov-buscar').classList.contains('open') ? $('ov-buscar') : null);
  if(!ov) return;
  var f = ov.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])');
  if(!f.length) return;
  var pri = f[0], ult = f[f.length-1];
  if(e.shiftKey && document.activeElement===pri){ e.preventDefault(); ult.focus(); }
  else if(!e.shiftKey && document.activeElement===ult){ e.preventDefault(); pri.focus(); }
});

/* Confirmación en modal propio. Nunca confirm() del navegador: no se puede
   dar contexto ni distinguir una acción destructiva de una rutinaria. */
function confirmar(cfg){
  return new Promise(function(res){
    var resuelto = false;
    modal({
      titulo: cfg.titulo,
      cuerpo: '<p style="font-size:.875rem;color:var(--ink-2);line-height:1.6">'+(cfg.html || esc(cfg.texto||''))+'</p>',
      alCerrar: function(){ if(!resuelto) res(false); },
      pie: [
        {txt:'Cancelar', fn:function(){ cerrarModal(); }},
        {txt: cfg.ok || 'Continuar', cls: cfg.peligro ? 'btn-accent' : 'btn-primary', fn:function(){ resuelto=true; cerrarModal(); res(true); }}
      ]
    });
  });
}

/* --------------------------------------------------------------------------
   ESTADO DEL ARCHIVO
   -------------------------------------------------------------------------- */
document.addEventListener('sfg:estado', function(e){
  var s = e.detail;
  var el = $('estado');
  var modo = s.guardando ? 'guardando' : (!s.cargado ? 'vacio' : (s.sucio ? 'sucio' : 'limpio'));
  el.dataset.e = modo;
  $('estado-txt').textContent = ({
    vacio:'Sin datos', sucio:'Sin guardar', limpio:'Guardado', guardando:'Guardando…'
  })[modo];
  $('estado-nom').textContent = s.archivo || '';
  $('b-guardar').disabled = !s.cargado || s.guardando;
  $('b-buscar').disabled = !s.cargado;
  document.body.classList.toggle('bloqueado', s.guardando);
  var m = $('modo');
  if(!s.cargado){ m.textContent=''; m.classList.remove('aviso'); }
  else if(s.directo){ m.textContent='escritura directa'; m.classList.remove('aviso'); }
  else { m.textContent='modo descarga'; m.classList.add('aviso'); m.title='Cada guardado baja un archivo que hay que mover a su sitio a mano.'; }
});

/* --------------------------------------------------------------------------
   ABRIR
   -------------------------------------------------------------------------- */
function trasCargar(r){
  contadores();
  document.querySelectorAll('.g-nav a').forEach(function(a){ a.style.pointerEvents=''; });
  var d = SFG.d();
  aviso(d.equipos.length+' equipos y '+(d.partidos_liga.length+d.partidos_ascenso.length+d.partidos_copa.length)+' partidos cargados.', 'ok');
  r.avisosEsquema.forEach(function(a){ aviso(a, 'ojo', 9000); });
  if(r.integridad.err.length) aviso(r.integridad.err.length+' problemas críticos: revísalos en Datos antes de guardar.', 'mal', 12000);
  irA(location.hash.slice(1) && vistas[location.hash.slice(1)] ? location.hash.slice(1) : 'resumen');
}
function fallo(e){
  if(e && e.message==='SIN_API') return;
  console.error(e);
  aviso(e && e.message ? e.message : 'No se pudo abrir el archivo.', 'mal', 10000);
}
function abrir(){
  if(IO.hayApi()){
    IO.abrir().then(trasCargar).catch(function(e){
      /* AbortError = el usuario cerró el selector. No es un fallo. */
      if(e && (e.name==='AbortError' || e.message==='SIN_API')) return;
      if(e && (e.name==='SecurityError' || e.name==='NotAllowedError')){
        aviso('El navegador no permite escritura directa aquí; se usará el modo descarga.', 'ojo', 8000);
        return $('f-abrir').click();
      }
      fallo(e);
    });
  } else {
    $('f-abrir').click();
  }
}
$('b-abrir').addEventListener('click', abrir);
$('b-abrir-2').addEventListener('click', abrir);
$('f-abrir').addEventListener('change', function(){
  var f = this.files[0];
  if(f) IO.abrirDesdeFichero(f).then(trasCargar).catch(fallo);
  this.value = '';
});

/* --------------------------------------------------------------------------
   GUARDAR
   -------------------------------------------------------------------------- */
function guardar(opciones){
  IO.guardar(opciones).then(function(r){
    aviso(r.directo ? 'Guardado en el archivo.' : 'Archivo descargado: muévelo a propuesta-web/.', 'ok');
    if(r.notas.length) aviso(r.notas.length+' campos normalizados al guardar.', 'info');
    if(r.integridad.avi.length) contadores();
    refrescar();
  }).catch(function(e){
    if(e.message==='EN_CURSO') return;
    if(e.message==='INTEGRIDAD') return bloqueoIntegridad(e.integridad);
    aviso('No se pudo guardar: '+e.message+' — se puede reintentar.', 'mal', 12000);
  });
}
/* Guardado bloqueado por integridad. Se explica qué falla y se ofrece ir a
   arreglarlo; forzar existe, pero detrás de una segunda decisión. */
function bloqueoIntegridad(v){
  modal({
    titulo:'No se puede guardar todavía',
    ancho:true,
    cuerpo:'<p style="font-size:.875rem;color:var(--ink-2);margin-bottom:1rem">'+
      v.err.length+' problemas dejarían el archivo en un estado que la web no sabe leer.</p>'+
      '<div class="tabla-caja">'+v.err.slice(0,12).map(function(x){
        return '<div class="problema err"><i class="ph-bold ph-warning-octagon"></i><span>'+esc(x.m)+'</span></div>';
      }).join('')+'</div>'+
      (v.err.length>12 ? '<p class="ayuda" style="margin-top:.75rem">y '+(v.err.length-12)+' más.</p>' : ''),
    pie:[
      {txt:'Guardar de todos modos', cls:'btn-secondary', izq:true, fn:function(){
        cerrarModal();
        confirmar({
          titulo:'Guardar con errores',
          texto:'La web pública puede dejar de mostrar partes de la competición. ¿Seguro?',
          ok:'Guardar igualmente', peligro:true
        }).then(function(si){ if(si) guardar({forzar:true}); });
      }},
      {txt:'Revisar en Datos', cls:'btn-primary', fn:function(){ cerrarModal(); irA('datos'); }}
    ]
  });
}
$('b-guardar').addEventListener('click', function(){ guardar(); });
document.addEventListener('sfg:guardar', function(){ if(SFG.d()) guardar(); });

/* --------------------------------------------------------------------------
   BUSCADOR GLOBAL (Ctrl/Cmd+K)
   -------------------------------------------------------------------------- */
function abrirBuscar(){
  if(!SFG.d()) return;
  $('ov-buscar').classList.add('open');
  $('q').value=''; $('q-res').innerHTML='';
  setTimeout(function(){ $('q').focus(); }, 60);
}
function cerrarBuscar(){ $('ov-buscar').classList.remove('open'); }
$('b-buscar').addEventListener('click', abrirBuscar);
$('ov-buscar').addEventListener('click', function(e){ if(e.target===this) cerrarBuscar(); });
document.addEventListener('keydown', function(e){
  if((e.ctrlKey||e.metaKey) && (e.key==='k'||e.key==='K')){ e.preventDefault(); abrirBuscar(); }
});

$('q').addEventListener('input', function(){
  var q = this.value.trim();
  if(q.length<2){ $('q-res').innerHTML=''; return; }
  var n = C.norm(q), d = SFG.d(), grupos = [];

  var eq = d.equipos.filter(function(e){ return C.norm(e.nombre).indexOf(n)>=0; }).slice(0,6);
  if(eq.length) grupos.push(['Equipos', eq.map(function(e){
    return {t:e.nombre, s:e.division+(e.archivado?' · archivado':''), v:'equipos', p:{id:e.id}};
  })]);

  var jug = [];
  d.equipos.forEach(function(e){
    (e.jugadores||[]).forEach(function(j){
      if(jug.length<8 && C.norm(j.nombre).indexOf(n)>=0) jug.push({t:j.nombre, s:j.posicion+' · '+e.nombre, v:'equipos', p:{id:e.id, jugador:j.nombre}});
    });
  });
  if(jug.length) grupos.push(['Jugadores', jug]);

  var par = [];
  [['liga','partidos_liga'],['ascenso','partidos_ascenso'],['copa','partidos_copa']].forEach(function(c){
    d[c[1]].forEach(function(p,i){
      if(par.length>=8) return;
      if(C.norm(p.local+' '+p.visitante).indexOf(n)>=0)
        par.push({t:(p.local||'?')+' – '+(p.visitante||'?'), s:(p.fase||('Jornada '+p.jornada))+' · '+p.estado,
                  v:c[0]==='copa'?'copa':'partidos', p:{comp:c[0], idx:i}});
    });
  });
  if(par.length) grupos.push(['Partidos', par]);

  var not = d.noticias.map(function(x,i){ return {x:x,i:i}; })
    .filter(function(o){ return C.norm(o.x.titulo).indexOf(n)>=0; }).slice(0,5)
    .map(function(o){ return {t:o.x.titulo, s:o.x.tag, v:'noticias', p:{idx:o.i}}; });
  if(not.length) grupos.push(['Noticias', not]);

  if(!grupos.length){ $('q-res').innerHTML='<div class="vacio">Nada coincide con «'+esc(q)+'».</div>'; return; }
  $('q-res').innerHTML = grupos.map(function(g){
    return '<div class="grupo" style="font-family:var(--f-mono);font-size:.625rem;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-4);padding:.75rem 0 .35rem">'+g[0]+'</div>'+
      '<div class="tabla-caja">'+g[1].map(function(r){
        return '<button class="problema" style="width:100%;text-align:left" data-a="ui:ir" data-v="'+esc(r.v)+'" data-p="'+esc(JSON.stringify(r.p))+'">'+
          '<span style="color:var(--ink)">'+esc(r.t)+'</span>'+
          '<span style="margin-left:auto;color:var(--ink-4);font-size:.75rem">'+esc(r.s)+'</span></button>';
      }).join('')+'</div>';
  }).join('');
});

acciones.ui = {
  ir: function(el){
    cerrarBuscar();
    irA(el.dataset.v, JSON.parse(el.dataset.p));
  }
};

/* --------------------------------------------------------------------------
   NAVEGACIÓN
   -------------------------------------------------------------------------- */
$('nav').addEventListener('click', function(e){
  var a = e.target.closest('a[data-s]');
  if(!a) return;
  e.preventDefault();
  if(!SFG.d()) return aviso('Abre primero el archivo de datos.', 'ojo');
  irA(a.dataset.s);
});

/* --------------------------------------------------------------------------
   COPIAS DE SEGURIDAD EN LA PANTALLA DE ENTRADA
   Si una sesión anterior se cortó, la copia local es lo único que queda.
   -------------------------------------------------------------------------- */
function pintarCopiasInicio(){
  var s = IO.snapshots();
  if(!s.length) return;
  $('copias-inicio').innerHTML =
    '<div class="card" style="padding:1.5rem;max-width:640px">'+
      '<h3 style="font-size:.9375rem;margin-bottom:.35rem">Copias locales de esta sesión</h3>'+
      '<p class="ayuda" style="margin-bottom:1rem">Se guardan en este navegador antes de cada escritura. Sirven si algo salió mal.</p>'+
      '<div class="tabla-caja">'+s.map(function(x){
        return '<div class="problema"><i class="ph ph-clock-counter-clockwise"></i>'+
          '<span>'+new Date(x.ts).toLocaleString('es-ES')+'</span>'+
          '<span style="margin-left:auto;color:var(--ink-4);font-size:.75rem" class="mono">'+Math.round(x.bytes/1024)+' KB</span>'+
          '<button class="ir" data-a="ui:restaurar" data-ts="'+x.ts+'">Restaurar</button></div>';
      }).join('')+'</div></div>';
}
acciones.ui.restaurar = function(el){
  confirmar({
    titulo:'Restaurar copia local',
    texto:'Se cargará el contenido de esa copia. Quedará como cambio sin guardar: nada se escribe hasta que pulses Guardar.',
    ok:'Restaurar'
  }).then(function(si){
    if(!si) return;
    try {
      IO.restaurar(Number(el.dataset.ts));
      contadores(); aviso('Copia restaurada. Revísala y guarda si es correcta.', 'ok');
      irA('resumen');
    } catch(e){ aviso(e.message, 'mal'); }
  });
};

/* --------------------------------------------------------------------------
   AYUDANTES PARA LAS VISTAS
   -------------------------------------------------------------------------- */
/* Escudo de club con recambio: la mitad de los escudos son URLs externas que
   pueden no cargar, y un hueco roto es peor que unas iniciales. */
/* El escudo SIEMPRE sale con su clase de tamaño. Antes salía como un <img>
   desnudo y confiaba en que el contenedor lo dimensionara: donde no había
   regla —los grupos de Copa, el árbol de traspasos— se pintaba al tamaño
   natural del archivo del CDN, que son cientos de píxeles. Un componente no
   puede depender de que alguien se acuerde de medirlo desde fuera.
   `tam` acepta 'sm' | 'md' | 'lg'. */
function escudo(e, tam){
  var cls = 'escudo'+(tam?' escudo-'+tam:'');
  if(e && /^https?:\/\//.test(e.escudo||''))
    return '<img class="'+cls+'" src="'+esc(e.escudo)+'" alt="" loading="lazy" referrerpolicy="no-referrer" '+
      'onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\''+cls+' noimg\',textContent:this.dataset.ab}))" '+
      'data-ab="'+esc(C.abbr3(e.nombre,e.abreviatura))+'">';
  return '<span class="'+cls+' noimg">'+esc(C.abbr3(e?e.nombre:'?', e&&e.abreviatura))+'</span>';
}

/* Tarjeta de indicador con variación, al estilo de las referencias: cifra
   grande, etiqueta pequeña y, si hay con qué compararlo, la diferencia. */
function kpi(cfg){
  var d = cfg.delta;
  var signo = d==null ? '' : (d>0?'sube':(d<0?'baja':'igual'));
  return '<div class="kpi'+(cfg.destacado?' kpi-on':'')+'"'+(cfg.titulo?' title="'+esc(cfg.titulo)+'"':'')+'>'+
    (cfg.icono?'<span class="kpi-ico"><i class="'+cfg.icono+'"></i></span>':'')+
    '<div class="kpi-val mono">'+esc(cfg.valor)+'</div>'+
    '<div class="kpi-pie">'+
      '<span class="kpi-etq">'+esc(cfg.etiqueta)+'</span>'+
      (d!=null ? '<span class="kpi-delta '+signo+'">'+
        (d>0?'<i class="ph-bold ph-trend-up"></i>':(d<0?'<i class="ph-bold ph-trend-down"></i>':''))+
        (d>0?'+':'')+esc(cfg.deltaTexto!=null?cfg.deltaTexto:d)+'</span>' : '')+
    '</div>'+
  '</div>';
}
function celdaEquipo(e, nombre){
  return '<span class="eq-cel">'+escudo(e)+'<span>'+esc(nombre!=null?nombre:(e?e.nombre:'—'))+'</span></span>';
}
/* <select> de equipos. Los archivados van al final y marcados: siguen siendo
   elegibles porque sus partidos históricos existen. */
function selectEquipos(valor, attrs){
  var d = SFG.d();
  var act = d.equipos.filter(function(e){ return !e.archivado; });
  var arc = d.equipos.filter(function(e){ return e.archivado; });
  function ops(l, suf){
    return l.slice().sort(function(a,b){ return a.nombre.localeCompare(b.nombre,'es'); }).map(function(e){
      return '<option value="'+esc(e.nombre)+'"'+(e.nombre===valor?' selected':'')+'>'+esc(e.nombre)+suf+'</option>';
    }).join('');
  }
  var desconocido = valor && !d.equipos.some(function(e){ return e.nombre===valor; })
    ? '<option value="'+esc(valor)+'" selected>'+esc(valor)+' (no existe)</option>' : '';
  return '<select '+(attrs||'')+'><option value="">—</option>'+desconocido+ops(act,'')+(arc.length?'<optgroup label="Archivados">'+ops(arc,'')+'</optgroup>':'')+'</select>';
}
/* --------------------------------------------------------------------------
   CAMPOS DE IMAGEN CON SOLTAR
   Un campo de URL que además acepta que se le suelte un archivo encima. La
   imagen se recorta cuadrada y se incrusta como data URI, porque sin servidor
   no hay dónde subirla.

   Se avisa del peso a propósito: esto entra dentro de datos_oficiales.json,
   que la web descarga entera en cada visita. Un enlace externo pesa cien
   bytes; una foto incrustada, decenas de miles.
   -------------------------------------------------------------------------- */
function campoImagen(label, valor, ref, ayuda){
  var esDato = /^data:/.test(valor||'');
  var previa = (valor && (esDato || /^https?:/.test(valor)))
    ? '<img src="'+esc(valor)+'" alt="" referrerpolicy="no-referrer" style="width:44px;height:44px;object-fit:cover;border-radius:var(--r-sm);border:1px solid var(--line);flex-shrink:0">'
    : '<span style="width:44px;height:44px;display:grid;place-items:center;border:1px dashed var(--line-2);border-radius:var(--r-sm);color:var(--ink-4);flex-shrink:0"><i class="ph ph-image"></i></span>';
  return '<div class="campo"><label>'+esc(label)+'</label>'+
    '<div class="color-par zona-img" data-img="'+esc(ref)+'">'+
      previa+
      '<input class="inp" value="'+esc(valor||'')+'" data-c="'+esc(ref)+'" placeholder="https://… o suelta una imagen aquí">'+
    '</div>'+
    '<span class="ayuda">'+(esDato
      ? '<b style="color:var(--gold)">Imagen incrustada</b> · '+Math.round((valor.length*3/4)/1024)+' KB dentro del archivo'
      : (ayuda||'Arrastra un archivo encima para incrustarlo, o pega una URL.'))+'</span>'+
  '</div>';
}
/* Un solo juego de oyentes para todos los campos de imagen que existan o
   lleguen a existir, en vez de recablear en cada repintado. */
['dragenter','dragover'].forEach(function(t){
  document.addEventListener(t, function(ev){
    var z = ev.target.closest && ev.target.closest('[data-img]');
    if(!z) return;
    ev.preventDefault();
    z.classList.add('dnd-encima');
  });
});
document.addEventListener('dragleave', function(ev){
  var z = ev.target.closest && ev.target.closest('[data-img]');
  if(z && !z.contains(ev.relatedTarget)) z.classList.remove('dnd-encima');
});
document.addEventListener('drop', function(ev){
  var z = ev.target.closest && ev.target.closest('[data-img]');
  if(!z) return;
  ev.preventDefault();
  z.classList.remove('dnd-encima');
  var f = ev.dataTransfer.files[0];
  if(!f) return;
  if(!/^image\//.test(f.type)) return aviso('Eso no es una imagen.', 'ojo');
  SFG.dnd.procesarImagen(f, {recortar:true, max:256}, function(url){
    var kb = Math.round((url.length*3/4)/1024);
    var partes = String(z.dataset.img).split(':');
    var fn = (acciones[partes[0]]||{})[partes[1]];
    if(!fn) return;
    /* Se reutiliza el mismo manejador que el campo de texto: para el modelo de
       datos, soltar una imagen es escribir un valor en ese campo. */
    var falso = {value:url, dataset:Object.assign({}, z.querySelector('input').dataset)};
    fn(falso);
    aviso('Imagen incrustada, '+kb+' KB. Con muchas, el archivo que descarga la web crece deprisa.', kb>120?'ojo':'ok', 8000);
    refrescar();
  });
});

function campo(label, control, ayuda){
  return '<div class="campo"><label>'+esc(label)+'</label>'+control+(ayuda?'<span class="ayuda">'+esc(ayuda)+'</span>':'')+'</div>';
}
function cabecera(titulo, sub, acciones){
  return '<div class="g-cab"><div><h1>'+esc(titulo)+'</h1>'+(sub?'<p>'+esc(sub)+'</p>':'')+'</div>'+
    (acciones?'<div class="acciones">'+acciones+'</div>':'')+'</div>';
}

SFG.ui = {
  registrar:registrar, irA:irA, refrescar:refrescar, cambio:cambio, contadores:contadores,
  aviso:aviso, modal:modal, cerrarModal:cerrarModal, confirmar:confirmar,
  acciones:acciones, esc:esc,
  escudo:escudo, kpi:kpi, celdaEquipo:celdaEquipo, selectEquipos:selectEquipos, campo:campo, campoImagen:campoImagen, cabecera:cabecera,
  guardar:guardar
};

/* Arranque: sin datos sólo existe la pantalla de entrada. */
pintarCopiasInicio();
$('aviso-modo').textContent = IO.hayApi()
  ? 'Este navegador permite escribir encima del archivo original: Ctrl+S guarda sin diálogos.'
  : 'Este navegador (o abrir la página con doble clic en vez de por un servidor) no permite escritura directa: cada guardado descargará el archivo y habrá que moverlo a su sitio.';

})();
