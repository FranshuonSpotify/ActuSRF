/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — core.js
   Capa de datos: esquema, normalización, validación y los algoritmos de la
   web pública.

   REGLA DE ORO DE ESTE FICHERO: todo lo que aquí se calcula (clasificación,
   goleadores, resolución del cuadro de Copa) está portado 1:1 desde
   _fuente/app.js. Si el gestor y la web discrepan, el gestor miente. Cuando
   app.js cambie, este fichero se actualiza a mano — no hay import posible
   porque app.js vive dentro de una IIFE cerrada y no exporta nada.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG = window.SFG || {};

/* Datos en memoria. Un único objeto vivo: todas las vistas mutan éste y
   ninguna guarda su propia copia, para que no existan dos verdades. */
var D = null;
SFG.d = function(){ return D; };
SFG.setD = function(nuevo){ D = nuevo; };

/* --------------------------------------------------------------------------
   1. UTILIDADES — copiadas de app.js
   -------------------------------------------------------------------------- */
function norm(s){ return String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,''); }
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
/* goles_l / golesl: el JSON usa los dos alias. Igual que app.js, se lee el
   canónico primero y se cae al alias. */
function gl(p){ return p.goles_l!==undefined?p.goles_l:(p.golesl!==undefined?p.golesl:0); }
function gv(p){ return p.goles_v!==undefined?p.goles_v:(p.golesv!==undefined?p.golesv:0); }
function isFin(p){ return p.estado==='FINALIZADO'; }

function abbr3(name,ab){
  if(ab&&ab.trim()) return ab.trim().toUpperCase().slice(0,3);
  var c=String(name||'').replace(/[^\p{L}\s]/gu,'').trim();
  if(!c) return '???';
  var w=c.split(/\s+/);
  if(w.length>=3) return (w[0][0]+w[1][0]+w[2][0]).toUpperCase();
  if(w.length===2) return (w[0].slice(0,2)+w[1][0]).toUpperCase();
  return c.slice(0,3).toUpperCase();
}

/* Afinidades: los cinco nombres oficiales. El JSON trae alias sueltos, una
   URL metida por error y algún null; se resuelven contra este mapa y lo que
   no encaje cae en Neutro, exactamente como hace la web. */
var AF_MAP={
  fuego:'fuego', fire:'fuego',
  montana:'montana', 'montaña':'montana', tierra:'montana', mountain:'montana',
  bosque:'bosque', forest:'bosque', wood:'bosque',
  aire:'aire', air:'aire', viento:'aire', wind:'aire',
  neutro:'neutro', neutral:'neutro', vacio:'neutro', 'vacío':'neutro'
};
var AF_LABEL={fuego:'Fuego',montana:'Montaña',bosque:'Bosque',aire:'Aire',neutro:'Neutro'};
var AF_HEX={fuego:'#FF5A3C',montana:'#C08A3E',bosque:'#46B45F',aire:'#35D0C6',neutro:'#9AA0A6'};
function afKey(a){ return AF_MAP[norm(a||'').replace(/[^a-zñ]/g,'')]||'neutro'; }
function afName(a){ return AF_LABEL[afKey(a)]; }
/* ¿El valor guardado es ya uno de los cinco oficiales, tal cual? Sirve para
   marcar en la interfaz lo que la web está reinterpretando en silencio. */
function afinidadLimpia(a){ return AF_LABEL[afKey(a)]===a; }

var POS=['POR','DEF','MED','DEL'];
var POS_ORDER={POR:0,DEF:1,MED:2,DEL:3};
var DIVISIONES=['SUPERLIGA','ASCENSO'];
var FASES=['RONDA 1 (PREVIA)','RONDA 2','CUARTOS DE FINAL','SEMIFINALES','FINAL'];
var FASES_TODAS=['FASE DE GRUPOS'].concat(FASES);
var AFINIDADES=['Fuego','Montaña','Bosque','Aire','Neutro'];

/* FASES DE LIGA Y ASCENSO
   Un partido de liga sin `fase` es jornada regular. Con `fase`, es una
   eliminatoria posterior: play-in, play-off, final.

   Por qué se reutiliza `fase` en vez de inventar un campo nuevo: app.js ya lo
   lee para el pie de la tarjeta de partido y para la insignia de su ficha
   —`p.fase ? p.fase : 'Jornada N'`—, así que la web pública muestra la
   etiqueta correcta sin tocar una línea de app.js. Un campo nuevo habría
   necesitado modificarla.

   Los nombres salen de renderPlayoff() de app.js, que es quien define el
   cuadro de la Superliga; escribir otros crearía dos vocabularios. */
var FASES_LIGA=['PARTIDO POR EL PLAY IN','PLAY IN','SEMIFINALES','FINAL','DESEMPATE'];

/* Un partido cuenta para la clasificación sólo si es de jornada regular. Una
   eliminatoria no reparte puntos: si los sumara, el campeón del play-off
   adelantaría en la tabla al primero de la fase regular. */
function esRegular(p){ return !p.fase; }

function equipo(nombre){ return D&&D.equipos.find(function(e){ return e.nombre===nombre; }); }
function equipoPorId(id){ return D&&D.equipos.find(function(e){ return e.id===id; }); }
function pool(comp){ return comp==='ascenso'?D.partidos_ascenso:comp==='copa'?D.partidos_copa:D.partidos_liga; }

/* --------------------------------------------------------------------------
   2. CLASIFICACIÓN — orderStandings() de app.js, criterio por criterio
   Ocho desempates, no siete: entre derrotas y alfabético hay uno por partidos
   jugados (menos jugados, más arriba). Está en el código de la web y por
   tanto es la fórmula real.
   -------------------------------------------------------------------------- */
function orderStandings(list){
  return list.slice().sort(function(a,b){
    if(b.pts!==a.pts) return b.pts-a.pts;
    var dA=(a.gf||0)-(a.gc||0), dB=(b.gf||0)-(b.gc||0);
    if(dB!==dA) return dB-dA;
    if((b.gf||0)!==(a.gf||0)) return (b.gf||0)-(a.gf||0);
    if((a.gc||0)!==(b.gc||0)) return (a.gc||0)-(b.gc||0);
    if((b.g||0)!==(a.g||0)) return (b.g||0)-(a.g||0);
    if((b.e||0)!==(a.e||0)) return (b.e||0)-(a.e||0);
    if((a.p||0)!==(b.p||0)) return (a.p||0)-(b.p||0);
    if((a.pj||0)!==(b.pj||0)) return (a.pj||0)-(b.pj||0);
    return a.nombre.localeCompare(b.nombre,'es');
  });
}
function clasificacion(div){
  return orderStandings(D.equipos.filter(function(e){ return e.division===div&&!e.archivado; }));
}

/* --------------------------------------------------------------------------
   3. COPA — winnerOf() y resolveSide() de app.js
   -------------------------------------------------------------------------- */
function winnerOf(p){
  if(!p||!isFin(p)) return null;
  var a=gl(p), b=gv(p);
  if(a>b) return p.local;
  if(b>a) return p.visitante;
  /* Empate en eliminatoria: la tanda se anota dentro de `detalles` como
     "PEN: 3-2". Es el único sitio donde vive ese dato. */
  var m=/PEN[: ]?\s*(\d+)\s*-\s*(\d+)/i.exec(p.detalles||'');
  if(m) return parseInt(m[1])>parseInt(m[2])?p.local:p.visitante;
  return null;
}
/* Resuelve quién juega un lado de un cruce: si está vinculado a una ronda
   anterior manda el ganador de aquélla; si aún no se conoce, se devuelven los
   dos candidatos. NO escribe nada en los datos: el prototipo anterior volcaba
   el ganador dentro de p.local y eso duplica la verdad en dos sitios. */
function resolveSide(p,side){
  var k=side==='local'?'origen_local':'origen_visitante';
  var oi=p[k];
  if(oi!=null&&oi!==''&&D.partidos_copa[Number(oi)]){
    var f=D.partidos_copa[Number(oi)], w=winnerOf(f);
    if(w) return {n:w,pend:false,origen:Number(oi)};
    return {n:abbr3(f.local)+' / '+abbr3(f.visitante),pend:true,origen:Number(oi)};
  }
  return {n:p[side],pend:false,origen:null};
}

/* --------------------------------------------------------------------------
   4. DETALLES DE PARTIDO
   Formato exacto: "<eventos local> / <eventos visitante>", eventos separados
   por ", ", cada uno "tipo:Nombre:minuto". Lo que va antes de la barra es del
   local. Los extremos vacíos dejan el espacio: " / gol:X:23".
   app.js reconoce gol, amarilla y roja, y pinta cualquier otro tipo como
   cambio; el prototipo además usaba asistencia. Se soportan los cinco.
   -------------------------------------------------------------------------- */
/* Tipos que el PARSER reconoce: se conservan los cinco porque app.js los
   pinta y podría haberlos en datos antiguos. Otra cosa es cuáles se ofrecen
   al editar: en esta liga sólo se registran goles —las asistencias y las
   tarjetas nunca se han puesto ni se muestran en ninguna pantalla—, así que
   ofrecer cinco tipos era pedir un dato que nadie va a rellenar. */
var TIPOS_EVENTO=['gol','asistencia','amarilla','roja','cambio'];
var TIPOS_EDITABLES=['gol'];
var TIPO_LABEL={gol:'Gol',asistencia:'Asistencia',amarilla:'Amarilla',roja:'Roja',cambio:'Cambio'};

/* PARTIDOS NO JUGADOS
   En esta liga hay partidos que no se disputan y se resuelven con una
   victoria administrativa, normalmente 3-0. Cuentan para la clasificación
   igual que cualquier otro, pero no tienen goleadores porque no hubo goles.
   Sin marcarlos, los informes los cuentan como «goles sin anotar quién los
   marcó» y ensucian la única cifra que sirve para saber qué falta de verdad.

   Campo aditivo: app.js lo ignora y sigue mostrando el marcador tal cual. */
function esNoJugado(p){ return !!(p && p.no_jugado); }

function parseLado(raw){
  return String(raw||'').split(',').map(function(s){ return s.trim(); }).filter(Boolean).map(function(ev){
    var q=ev.split(':');
    if(q.length<2) return null;
    var tipo=q[0].trim();
    if(TIPOS_EVENTO.indexOf(tipo)<0) return null;
    var nombre=(q[1]||'').trim();
    if(!nombre) return null;
    return {tipo:tipo,nombre:nombre,minuto:(q[2]||'').trim()};
  }).filter(Boolean);
}
/* La tanda de penaltis no es un evento: vive suelta dentro de `detalles` como
   "PEN: 3-2" y es el ÚNICO sitio donde se guarda. winnerOf() la lee para
   resolver una eliminatoria empatada, así que se extrae aparte y se vuelve a
   escribir al serializar. Sin esto, editar los eventos de un cruce de Copa
   borraría el resultado de la tanda. */
var RE_PEN=/PEN[: ]?\s*(\d+)\s*-\s*(\d+)/i;
/* Se parte por la PRIMERA barra, no por todas: un nombre con "/" dentro
   rompería el reparto local/visitante si partiéramos por cualquiera. */
function parseDetalles(det){
  var s=String(det||'');
  var mp=RE_PEN.exec(s);
  var pen=mp?{l:parseInt(mp[1],10),v:parseInt(mp[2],10)}:null;
  if(mp) s=s.replace(RE_PEN,'');
  var i=s.indexOf('/');
  if(i<0) return {local:parseLado(s),visitante:[],pen:pen};
  return {local:parseLado(s.slice(0,i)),visitante:parseLado(s.slice(i+1)),pen:pen};
}
function evToStr(e){ return e.tipo+':'+e.nombre+':'+(e.minuto===''||e.minuto==null?'':e.minuto); }
function serializarDetalles(ev){
  var s=(ev.local||[]).map(evToStr).join(', ')+' / '+(ev.visitante||[]).map(evToStr).join(', ');
  /* Al final, fuera de los dos lados: ningún parser de eventos lo confunde
     con un evento porque "PEN" no es un tipo conocido. */
  if(ev.pen) s+=' PEN: '+ev.pen.l+'-'+ev.pen.v;
  return s;
}
/* Textos derivados que la web guarda junto al partido. Sólo goles, en el
   orden local -> visitante, con el apóstrofe de minuto. */
function textoGoles(lista){
  return lista.filter(function(e){ return e.tipo==='gol'; })
    .map(function(e){ return e.nombre+' '+e.minuto+"'"; }).join(', ');
}
function textosDerivados(ev){
  var l=textoGoles(ev.local||[]), v=textoGoles(ev.visitante||[]);
  return {
    goleadores_local_texto:l,
    goleadores_visitante_texto:v,
    goleadores_texto:[l,v].filter(Boolean).join(', ')
  };
}

/* --------------------------------------------------------------------------
   5. GOLEADORES — calcScorers() y findPlayer() de app.js
   Los goles se enlazan al jugador por su NOMBRE en texto, con coincidencia
   difusa. Cualquier errata rompe el enlace en silencio: por eso el editor de
   eventos del gestor obliga a elegir de una lista en vez de teclear.
   -------------------------------------------------------------------------- */
function findPlayer(short){
  var n=norm(short);
  for(var i=0;i<D.equipos.length;i++){
    var e=D.equipos[i], js=e.jugadores||[];
    for(var j=0;j<js.length;j++){
      var p=js[j], pn=norm(p.nombre);
      if(pn===n||pn.split(' ')[0]===n||(n.split(' ')[0]===pn.split(' ')[0]&&pn.indexOf(n)===0)) return {j:p,e:e};
    }
  }
  return null;
}
function calcScorers(ms){
  var t={};
  ms.forEach(function(p){
    /* app.js parte por TODAS las barras aquí porque para el ranking da igual
       de qué lado vino el gol. Se replica tal cual para que los totales
       coincidan al dígito con los de la web. */
    (p.detalles||'').split('/').forEach(function(half){
      half.split(',').forEach(function(ev){
        var parts=ev.trim().split(':');
        if(parts.length>=2&&parts[0].trim()==='gol'){
          var n=parts[1].trim(); if(n) t[n]=(t[n]||0)+1;
        }
      });
    });
  });
  return Object.keys(t).map(function(n){
    var f=findPlayer(n);
    return { nombre:f?f.j.nombre:n, goles:t[n], j:f?f.j:null, e:f?f.e:null, textoCrudo:n };
  }).sort(function(a,b){
    return b.goles-a.goles || String(a.nombre).localeCompare(String(b.nombre),'es');
  });
}

/* --------------------------------------------------------------------------
   6. RECÁLCULO DE LA TABLA
   Devuelve lo que DEBERÍAN valer pj/g/e/p/gf/gc/pts según los partidos
   finalizados. No escribe: la comparación con lo guardado es la que detecta
   desajustes, y sobrescribir es siempre una decisión explícita.
   -------------------------------------------------------------------------- */
function tablaCalculada(){
  var t={};
  D.equipos.forEach(function(e){ t[e.nombre]={pj:0,g:0,e:0,p:0,gf:0,gc:0,pts:0}; });
  [D.partidos_liga,D.partidos_ascenso].forEach(function(lista){
    (lista||[]).forEach(function(p){
      /* Las eliminatorias (play-in, play-off, final) no reparten puntos. */
      if(!isFin(p) || !esRegular(p)) return;
      var a=parseInt(gl(p),10), b=parseInt(gv(p),10);
      if(isNaN(a)||isNaN(b)) return;
      var L=t[p.local], V=t[p.visitante];
      if(L){ L.pj++; L.gf+=a; L.gc+=b; if(a>b){L.g++;L.pts+=3;} else if(a===b){L.e++;L.pts++;} else L.p++; }
      if(V){ V.pj++; V.gf+=b; V.gc+=a; if(b>a){V.g++;V.pts+=3;} else if(a===b){V.e++;V.pts++;} else V.p++; }
    });
  });
  return t;
}
var CAMPOS_TABLA=['pj','g','e','p','gf','gc','pts'];
function desajustesTabla(){
  var t=tablaCalculada(), out=[];
  D.equipos.forEach(function(e){
    var c=t[e.nombre]; if(!c) return;
    CAMPOS_TABLA.forEach(function(k){
      if((e[k]||0)!==c[k]) out.push({equipo:e,campo:k,guardado:e[k]||0,calculado:c[k]});
    });
  });
  return out;
}

/* Estadísticas de jugador recalculadas desde los eventos de todos los
   partidos finalizados, incluida la Copa. */
function statsJugadoresCalculadas(){
  /* Se indexa por el OBJETO del jugador, no por una cadena "club|nombre".
     Una clave de texto obliga a que quien consulta conozca el separador
     exacto, y basta con equivocarse en él para que todo devuelva cero sin
     dar ningún error. Un Map no tiene ese problema. */
  var m=new Map();
  function fila(j,eq){
    var c=m.get(j);
    if(!c){ c={jugador:j, equipo:eq, goles:0, asistencias:0, amarillas:0, rojas:0}; m.set(j,c); }
    return c;
  }
  ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(k){
    (D[k]||[]).forEach(function(p){
      if(!isFin(p)) return;
      var ev=parseDetalles(p.detalles);
      [['local',ev.local],['visitante',ev.visitante]].forEach(function(par){
        var eq=equipo(p[par[0]]);
        par[1].forEach(function(e){
          /* Se busca primero dentro del club que anotó el evento y sólo
             después en toda la liga, igual que playerIn() en app.js. */
          var j=eq&&(eq.jugadores||[]).find(function(x){ return norm(x.nombre)===norm(e.nombre); });
          var duenyo=eq;
          if(!j){ var f=findPlayer(e.nombre); if(f){ j=f.j; duenyo=f.e; } }
          if(!j||!duenyo) return;
          var c=fila(j,duenyo);
          if(e.tipo==='gol') c.goles++;
          else if(e.tipo==='asistencia') c.asistencias++;
          else if(e.tipo==='amarilla') c.amarillas++;
          else if(e.tipo==='roja') c.rojas++;
        });
      });
    });
  });
  return m;
}
/* Lo que dicen los eventos de UN jugador concreto. Devuelve ceros si no
   aparece en ninguno, que es distinto de que no exista. */
var SIN_EVENTOS={goles:0,asistencias:0,amarillas:0,rojas:0};
function eventosDe(mapa,j){ return mapa.get(j)||SIN_EVENTOS; }

/* Cierra la temporada en curso sobre los datos vivos.
   No archiva: eso lo hace instantaneaTemporada() antes, y por separado, para
   que quede claro que son dos pasos y que el archivado ocurre primero.

   Lo delicado es el vuelco de estadísticas de jugador. La web calcula la
   carrera como `goles_totales + goles` y `goles_totales` es exactamente la
   suma del historial. Así que al cerrar hay que sumar los goles de la
   temporada a LAS DOS cosas —al total y a la etapa abierta del historial— y
   sólo entonces poner la temporada a cero. Sumar a una sola desajustaría la
   carrera; no poner a cero la contaría dos veces. */
var STATS_TEMP=[['goles','goles_totales'],['asistencias','asistencias_totales'],
                ['amarillas','amarillas_totales'],['rojas','rojas_totales']];
function cerrarTemporada(d, opciones){
  opciones=opciones||{};
  var etiqueta=opciones.etiqueta||('Temporada '+(d.config.temporada||'?'));
  var resumen={equipos:0, jugadores:0, etapas:0, partidos:0};

  d.equipos.forEach(function(e){
    CAMPOS_TABLA.forEach(function(k){ if(e[k]) resumen.equipos++; e[k]=0; });
    (e.jugadores||[]).forEach(function(j){
      var tuvo=STATS_TEMP.some(function(par){ return (j[par[0]]||0)>0; });
      if(!j.historial) j.historial=[];
      /* La etapa abierta en este club es donde se acumula. Si no existe se
         crea: un jugador fichado a mitad de temporada no tenía ninguna. */
      var et=null;
      for(var i=j.historial.length-1;i>=0;i--){
        var h=j.historial[i];
        if(h.abierto && (h.equipo_id===e.id || h.equipo===e.nombre)){ et=h; break; }
      }
      if(!et){
        et={equipo:e.nombre, equipo_id:e.id, division:e.division,
            temporada:etiqueta, temporada_inicio:etiqueta, temporada_fin:etiqueta,
            fecha:new Date().toLocaleDateString('es-ES'),
            goles:0, asistencias:0, amarillas:0, rojas:0, pj:0, abierto:true};
        j.historial.push(et);
        resumen.etapas++;
      }
      STATS_TEMP.forEach(function(par){
        var v=j[par[0]]||0;
        if(!v) return;
        j[par[1]]=(j[par[1]]||0)+v;
        et[par[0]]=(et[par[0]]||0)+v;
        j[par[0]]=0;
      });
      et.temporada_fin=etiqueta;
      if(!et.temporada_inicio) et.temporada_inicio=etiqueta;
      if(tuvo) resumen.jugadores++;
    });
  });

  if(opciones.vaciarCalendario){
    ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(k){
      resumen.partidos+=d[k].length;
      d[k]=[];
    });
    d.config.grupos_copa={};
  }
  var n=parseInt(d.config.temporada,10);
  d.config.temporada=isNaN(n)?d.config.temporada:String(n+1);
  d.config.jornada_actual='1';
  return resumen;
}

/* --------------------------------------------------------------------------
   CONSISTENCIA DE NOMBRES

   El punto más frágil de este archivo: los eventos de un partido guardan el
   nombre del jugador COMO TEXTO, y la web lo resuelve con findPlayer(), que
   acepta coincidencia por nombre completo, por primer nombre o por prefijo.
   Eso significa que una errata no da error: engancha el gol a otro jugador, o
   al de otro club, y nadie se entera.

   Este análisis busca las cinco formas en que eso puede pasar, ordenadas por
   lo caro que sale cada una.
   -------------------------------------------------------------------------- */
function distancia(a,b){
  /* Se recorta: aquí se comparan identidades, no bytes. Un espacio de más al
     final no es una persona distinta. */
  a=norm(a).trim(); b=norm(b).trim();
  if(a===b) return 0;
  var m=a.length, n=b.length;
  if(!m) return n;
  if(!n) return m;
  /* Sólo se guardan dos filas de la matriz: con 791 jugadores comparados por
     parejas, reservar la matriz entera cada vez cuesta más que la cuenta. */
  var prev=new Array(n+1), cur=new Array(n+1), i, j;
  for(j=0;j<=n;j++) prev[j]=j;
  for(i=1;i<=m;i++){
    cur[0]=i;
    for(j=1;j<=n;j++){
      cur[j]= a.charAt(i-1)===b.charAt(j-1) ? prev[j-1]
            : 1+Math.min(prev[j], cur[j-1], prev[j-1]);
    }
    var t=prev; prev=cur; cur=t;
  }
  return prev[n];
}
function parecido(a,b){
  var max=Math.max(String(a||'').length, String(b||'').length);
  return max ? 1 - distancia(a,b)/max : 0;
}

/* Todos los jugadores del archivo, con el club donde están. */
function todosLosJugadores(d){
  var out=[];
  (d.equipos||[]).forEach(function(e){
    (e.jugadores||[]).forEach(function(j){ out.push({j:j, club:e.nombre, equipo:e, libre:false}); });
  });
  (d.agentes_libres||[]).forEach(function(j){ out.push({j:j, club:'', equipo:null, libre:true}); });
  return out;
}
/* Todos los eventos con el nombre que llevan escrito y de dónde salen. */
function todosLosEventos(d){
  var out=[];
  [['liga','partidos_liga'],['ascenso','partidos_ascenso'],['copa','partidos_copa']].forEach(function(par){
    (d[par[1]]||[]).forEach(function(p,i){
      if(!isFin(p)) return;
      var ev=parseDetalles(p.detalles);
      ev.local.forEach(function(e){ out.push({nombre:e.nombre, tipo:e.tipo, club:p.local, p:p, comp:par[0], idx:i}); });
      ev.visitante.forEach(function(e){ out.push({nombre:e.nombre, tipo:e.tipo, club:p.visitante, p:p, comp:par[0], idx:i}); });
    });
  });
  return out;
}

/* Índice de nombres ya normalizados, en el MISMO orden en que los recorre
   findPlayer(). Se construye una vez por análisis en vez de normalizar los
   791 nombres en cada consulta: es la diferencia entre 127 ms y unos pocos.
   La resolución replica las tres condiciones de findPlayer —nombre completo,
   primer nombre, prefijo— y devuelve el primero que encaje, como ella. */
function indiceNombres(d){
  var arr=[];
  (d.equipos||[]).forEach(function(e){
    (e.jugadores||[]).forEach(function(j){
      var pn=norm(j.nombre);
      arr.push({j:j, e:e, pn:pn, pri:pn.split(' ')[0]});
    });
  });
  return {
    arr:arr,
    resolver:function(nombre){
      var n=norm(nombre), nPri=n.split(' ')[0];
      for(var i=0;i<arr.length;i++){
        var x=arr[i];
        if(x.pn===n || x.pri===n || (nPri===x.pri && x.pn.indexOf(n)===0)) return {j:x.j, e:x.e};
      }
      return null;
    }
  };
}

function analizarNombres(d, opciones){
  opciones=opciones||{};
  var prev=D; D=d;
  try {
    var plantilla=todosLosJugadores(d);
    var eventos=todosLosEventos(d);
    var indice=indiceNombres(d);
    var porNombre={};
    plantilla.forEach(function(x){
      var k=norm(x.j.nombre);
      if(!k) return;
      (porNombre[k]=porNombre[k]||[]).push(x);
    });
    /* El club que anota se busca una vez, no en cada evento. */
    var porClub={};
    (d.equipos||[]).forEach(function(e){
      var set={};
      (e.jugadores||[]).forEach(function(j){ set[norm(j.nombre)]=1; });
      porClub[e.nombre]={equipo:e, nombres:set, vacia:!(e.jugadores||[]).length};
    });

    var huerfanos=[], difusos=[], ambiguos=[], otroClub=[], traspasos=[];
    var vistos={}, cache={};
    eventos.forEach(function(ev){
      var k=norm(ev.nombre);
      var exactos=porNombre[k]||[];
      /* Un mismo nombre se resuelve una sola vez aunque aparezca 16 veces. */
      var f = (k in cache) ? cache[k] : (cache[k]=indice.resolver(ev.nombre));

      if(!vistos[k]){
        vistos[k]=1;
        if(!f) huerfanos.push({nombre:ev.nombre, ejemplo:ev});
        else if(!exactos.length) difusos.push({nombre:ev.nombre, resuelve:f.j, club:f.e.nombre, ejemplo:ev});
        if(exactos.length>1) ambiguos.push({nombre:ev.nombre, donde:exactos.map(function(x){ return x.libre?'(agente libre)':x.club; })});
      }
      /* El caso que de verdad hace daño: el club que anotó el evento no tiene
         a ese jugador, así que la web enseña la ficha de otro. */
      if(f){
        var info=porClub[ev.club];
        if(!info || !info.nombres[k]){
          /* Antes de dar la voz de alarma hay que mirar el historial: en esta
             liga se ficha a mitad de temporada, así que un gol marcado con
             otra camiseta es lo NORMAL, no un fallo. Si el jugador tiene una
             etapa en el club que anotó, el dato está bien y sólo se informa. */
          var etapa=(f.j.historial||[]).find(function(h){
            return (info && h.equipo_id===info.equipo.id) || h.equipo===ev.club;
          });
          var caso={nombre:ev.nombre, anotadoPor:ev.club, resuelve:f.j, clubReal:f.e.nombre,
                    comp:ev.comp, idx:ev.idx, p:ev.p, tipo:ev.tipo,
                    plantillaVacia: !!(info && info.vacia), etapa:etapa||null};
          if(etapa) traspasos.push(caso); else otroClub.push(caso);
        }
      }
    });

    /* Parejas de nombres casi iguales. Es lo único caro —comparación por
       parejas— así que se hace sólo si se pide. El filtro por diferencia de
       longitud descarta la inmensa mayoría antes de calcular nada. */
    var parecidos=[];
    if(opciones.parejas!==false){
      var umbral=opciones.umbral||0.85;
      for(var i=0;i<plantilla.length;i++){
        var a=plantilla[i].j.nombre||'';
        if(a.length<4) continue;
        for(var k2=i+1;k2<plantilla.length;k2++){
          var b=plantilla[k2].j.nombre||'';
          if(b.length<4) continue;
          if(Math.abs(a.length-b.length)>3) continue;
          var s=parecido(a,b);
          if(s>=umbral) parecidos.push({a:plantilla[i], b:plantilla[k2], similitud:s, dist:distancia(a,b)});
        }
      }
      parecidos.sort(function(x,y){ return y.similitud-x.similitud; });
    }

    return {huerfanos:huerfanos, difusos:difusos, ambiguos:ambiguos,
            otroClub:otroClub, traspasos:traspasos, parecidos:parecidos,
            nombresDistintos:Object.keys(vistos).length, eventos:eventos.length};
  } finally { D=prev; }
}

/* Cambia un nombre dentro de los eventos de todos los partidos. Devuelve
   cuántos eventos ha tocado. Se compara normalizado para que también atrape
   la variante con otra tilde o mayúscula, que es justo el caso a corregir. */
function renombrarEnEventos(d, viejo, nuevo){
  var n=0, k=norm(viejo);
  ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(clave){
    (d[clave]||[]).forEach(function(p){
      var ev=parseDetalles(p.detalles), tocado=false;
      ['local','visitante'].forEach(function(lado){
        ev[lado].forEach(function(e){
          if(norm(e.nombre)===k && e.nombre!==nuevo){ e.nombre=nuevo; n++; tocado=true; }
        });
      });
      if(!tocado) return;
      p.detalles=serializarDetalles(ev);
      if(p.goleadores_texto!=null||p.goleadores_local_texto!=null||p.goleadores_visitante_texto!=null){
        var t=textosDerivados(ev);
        p.goleadores_texto=t.goleadores_texto;
        p.goleadores_local_texto=t.goleadores_local_texto;
        p.goleadores_visitante_texto=t.goleadores_visitante_texto;
      }
    });
  });
  return n;
}

/* Renombrar a un jugador arrastrando el cambio por sus eventos, que es lo que
   hay que hacer para que no se desenganchen sus goles. */
function renombrarJugador(d, jugador, nuevo){
  var viejo=jugador.nombre;
  jugador.nombre=nuevo;
  return {eventos: viejo ? renombrarEnEventos(d, viejo, nuevo) : 0, viejo:viejo};
}

/* --------------------------------------------------------------------------
   GENERADORES
   Producen partidos, no los escriben: devuelven el array y quien llama decide
   si lo aplica. Así se puede enseñar el resultado antes de tocar el archivo,
   que es justo lo que pide «repetir el sorteo antes de confirmar».
   -------------------------------------------------------------------------- */

/* Azar reproducible. Con la misma semilla sale el mismo sorteo, que es lo que
   permite repetirlo y luego volver a uno anterior. Math.random() no serviría:
   no hay forma de volver a él. */
function azar(semilla){
  var s = semilla>>>0 || 1;
  return function(){
    /* xorshift32: cuatro líneas y reparte lo bastante bien para un sorteo. */
    s ^= s<<13; s>>>=0; s ^= s>>17; s ^= s<<5; s>>>=0;
    return s/4294967296;
  };
}
function barajar(lista, rnd){
  var a = lista.slice();
  for(var i=a.length-1;i>0;i--){
    var j = Math.floor(rnd()*(i+1));
    var t = a[i]; a[i] = a[j]; a[j] = t;
  }
  return a;
}

/* CALENDARIO DE LIGA — todos contra todos por el método del círculo.
   Se fija el primer equipo y los demás rotan; en cada ronda se emparejan por
   los extremos. Con número impar entra un descanso, y quien lo tiene esa
   jornada no juega en vez de generarse un partido fantasma. */
function generarCalendario(nombres, opciones){
  opciones = opciones || {};
  var vueltas = opciones.vueltas===1 ? 1 : 2;
  var j0 = parseInt(opciones.jornadaInicial,10) || 1;
  var rnd = azar(opciones.semilla);
  var eq = opciones.ordenar===false ? nombres.slice() : barajar(nombres, rnd);
  if(eq.length<2) return [];

  var descanso = null;
  if(eq.length%2){ descanso = '--descanso--'; eq.push(descanso); }
  var n = eq.length, rondas = n-1;
  var out = [];

  for(var v=0; v<vueltas; v++){
    for(var r=0; r<rondas; r++){
      var jornada = String(j0 + v*rondas + r);
      for(var i=0; i<n/2; i++){
        var a = eq[(i===0) ? 0 : ((r+i-1)%(n-1))+1];
        var b = eq[((n-1) - i + r - 1)%(n-1)+1];
        if(i===0) b = eq[(r+n-2)%(n-1)+1];
        if(a===descanso || b===descanso) continue;
        /* Alternancia de campo: dentro de una vuelta se invierte en rondas
           impares, y en la vuelta de vuelta se invierte todo. Sin esto, el
           equipo fijo del círculo jugaría siempre en casa. */
        var invertir = (r%2===1);
        if(v%2===1) invertir = !invertir;
        out.push(nuevoPartidoLiga(invertir?b:a, invertir?a:b, jornada));
      }
    }
  }
  return out;
}
function nuevoPartidoLiga(local, visitante, jornada){
  return {jornada:jornada, fecha:'', estado:'PENDIENTE',
          local:local, visitante:visitante, goles_l:0, goles_v:0, detalles:' / '};
}

/* Nombre de la ronda según cuántos equipos quedan en ella. */
function nombreFase(enRonda, esPrevia){
  if(esPrevia) return 'RONDA 1 (PREVIA)';
  if(enRonda<=2) return 'FINAL';
  if(enRonda<=4) return 'SEMIFINALES';
  if(enRonda<=8) return 'CUARTOS DE FINAL';
  return 'RONDA 2';
}

/* SORTEO DE COPA.
   Devuelve la lista completa de cruces con `origen_local`/`origen_visitante`
   ya encadenados por POSICIÓN dentro de esa misma lista, que es como los lee
   la web. Por eso se construye entera de una vez y no se puede reordenar
   después sin reajustar los índices.

   `siembra` es el orden de fuerza (normalmente la clasificación): se empareja
   el primero con el último, el segundo con el penúltimo, etc.
   `rivalidades` son parejas que no deben cruzarse en la primera ronda. */
function generarCopa(nombres, opciones){
  opciones = opciones || {};
  var rnd = azar(opciones.semilla);
  var eq = opciones.siembra ? nombres.slice() : barajar(nombres, rnd);
  if(eq.length<2) return {partidos:[], avisos:['Hacen falta al menos dos equipos.']};
  var avisos = [];

  if(opciones.tipo==='grupos') return generarGrupos(eq, opciones, rnd);

  /* Se baja a la potencia de dos inferior con una previa: los que sobran se
     eliminan entre sí y el resto pasa directo. */
  var N = eq.length;
  var P = Math.pow(2, Math.floor(Math.log2(N)));
  var previas = N - P;
  var partidos = [];
  var entranEnR2 = [];          // {nombre} o {origen:índice}

  if(previas>0){
    /* Juegan la previa los peor sembrados: los mejores tienen el pase. */
    var conPase = eq.slice(0, N - previas*2);
    var aPrevia = eq.slice(N - previas*2);
    emparejar(aPrevia, opciones.rivalidades, avisos).forEach(function(par){
      partidos.push(nuevoCruce(nombreFase(0,true), par[0], par[1]));
      entranEnR2.push({origen:partidos.length-1});
    });
    /* Los ganadores de la previa entran como los peor sembrados, DETRÁS de
       los que tenían pase. Ponerlos delante los emparejaría entre sí y la
       previa no habría servido de nada: su sentido es que se crucen con los
       cabezas de serie. */
    entranEnR2 = conPase.map(function(n){ return {nombre:n}; }).concat(entranEnR2);
  } else {
    entranEnR2 = eq.map(function(n){ return {nombre:n}; });
  }

  /* Primera ronda del cuadro: se empareja por extremos —el mejor sembrado
     contra el peor— y ahí sí se esquivan las rivalidades, porque es la ronda
     que de verdad se sortea. Las siguientes ya vienen encadenadas. */
  var slots = porExtremos(entranEnR2, opciones.rivalidades, avisos);

  var actual = slots, primera = true;
  while(actual.length>1){
    var fase = nombreFase(actual.length, false);
    var siguiente = [];
    for(var i=0;i<actual.length;i+=2){
      var A = actual[i], B = actual[i+1];
      var p = nuevoCruce(fase, A.nombre||'', B?(B.nombre||''):'');
      if(A.origen!=null) p.origen_local = A.origen;
      if(B && B.origen!=null) p.origen_visitante = B.origen;
      partidos.push(p);
      siguiente.push({origen:partidos.length-1});
    }
    actual = siguiente;
    primera = false;
  }
  return {partidos:partidos, avisos:avisos};
}

/* Reordena una lista de participantes para que se enfrenten por extremos
   (1º-último, 2º-penúltimo…) y para que ninguna pareja sea una rivalidad
   declarada. Devuelve la lista ya en orden de emparejamiento. */
function porExtremos(entradas, rivalidades, avisos){
  var a = entradas.slice(), out = [];
  while(a.length>1){
    var x = a.shift();
    var k = a.length-1;
    if(rivalidades && rivalidades.length && x.nombre){
      var intentos = 0;
      while(k>=0 && a[k].nombre && esRival(x.nombre, a[k].nombre, rivalidades) && intentos<a.length){
        k--; intentos++;
      }
      if(k<0){
        k = a.length-1;
        avisos.push('No se pudo evitar el cruce entre "'+x.nombre+'" y "'+(a[k].nombre||'un clasificado')+'" en la primera ronda.');
      }
    }
    out.push(x, a.splice(k,1)[0]);
  }
  if(a.length) out.push(a[0]);
  return out;
}

/* Emparejar por extremos, esquivando rivalidades declaradas. */
function emparejar(lista, rivalidades, avisos){
  var a = lista.slice(), pares = [];
  while(a.length>1){
    var x = a.shift();
    var k = a.length-1;                       // por defecto, el del otro extremo
    if(rivalidades && rivalidades.length){
      var intentos = 0;
      while(k>=0 && esRival(x, a[k], rivalidades) && intentos<a.length){ k--; intentos++; }
      if(k<0){
        k = a.length-1;
        avisos.push('No se pudo evitar el cruce entre "'+x+'" y "'+a[k]+'" en la primera ronda.');
      }
    }
    pares.push([x, a.splice(k,1)[0]]);
  }
  return pares;
}
function esRival(a, b, rivalidades){
  return rivalidades.some(function(r){ return (r[0]===a&&r[1]===b)||(r[0]===b&&r[1]===a); });
}
function nuevoCruce(fase, local, visitante){
  return {fase:fase, grupo:'', fecha:'', estado:'PENDIENTE',
          local:local||'', visitante:visitante||'',
          goles_l:0, goles_v:0, detalles:' / ', origen_local:null, origen_visitante:null};
}

/* Fase de grupos: reparto por serpiente y todos contra todos dentro de cada
   grupo. No se encadena nada, porque quién pasa lo decide la clasificación
   del grupo y eso no se puede expresar con `origen_*`. */
function generarGrupos(eq, opciones, rnd){
  var nGrupos = Math.max(1, opciones.grupos||4);
  var letras = Array.from({length:nGrupos},function(_,i){ return String.fromCharCode(65+i); });
  var reparto = {};
  letras.forEach(function(g){ reparto[g] = []; });
  eq.forEach(function(nombre, i){
    var vuelta = Math.floor(i/nGrupos), pos = i%nGrupos;
    reparto[letras[vuelta%2 ? nGrupos-1-pos : pos]].push(nombre);
  });
  var partidos = [], vueltas = opciones.ida_vuelta ? 2 : 1;
  letras.forEach(function(g){
    var l = reparto[g];
    for(var v=0; v<vueltas; v++)
      for(var a=0;a<l.length;a++) for(var b=a+1;b<l.length;b++){
        var p = nuevoCruce('FASE DE GRUPOS', v===0?l[a]:l[b], v===0?l[b]:l[a]);
        p.grupo = g;
        partidos.push(p);
      }
  });
  return {partidos:partidos, reparto:reparto, avisos:[]};
}

/* --------------------------------------------------------------------------
   MOVER UN EQUIPO DENTRO DEL CUADRO DE COPA
   Vive aquí y no en la vista porque tiene dos efectos que no se ven: si el
   hueco de destino estaba ocupado hay intercambio, y colocar a mano tiene que
   romper la vinculación con la ronda previa. Si no se rompiera, la web
   seguiría pintando el ganador de aquélla y el cambio sería invisible.

   Devuelve null si el movimiento no procede, para que la vista no tenga que
   repetir las comprobaciones.
   -------------------------------------------------------------------------- */
function moverEnCuadro(d, origen, destino){
  var po=d.partidos_copa[origen.idx], pd=d.partidos_copa[destino.idx];
  if(!po||!pd) return null;
  if(po===pd && origen.lado===destino.lado) return null;
  var ko=origen.lado==='local'?'origen_local':'origen_visitante';
  var kd=destino.lado==='local'?'origen_local':'origen_visitante';
  /* Un hueco vinculado no contiene un equipo, contiene una regla: ni se coge
     de él ni se suelta encima. */
  if(po[ko]!=null&&po[ko]!=='') return null;
  if(pd[kd]!=null&&pd[kd]!=='') return null;

  var movido=po[origen.lado]||'';
  if(!movido) return null;
  var ocupante=pd[destino.lado]||'';
  /* Un equipo no puede jugar contra sí mismo: el intercambio que lo produjera
     se rechaza entero en vez de dejar el cuadro en un estado imposible. */
  var futuroDestino=destino.lado==='local'?[movido,pd.visitante]:[pd.local,movido];
  var futuroOrigen=origen.lado==='local'?[ocupante,po.visitante]:[po.local,ocupante];
  if(po===pd){
    futuroOrigen=futuroDestino=destino.lado==='local'?[movido,ocupante]:[ocupante,movido];
  }
  if(futuroDestino[0]&&futuroDestino[0]===futuroDestino[1]) return null;
  if(futuroOrigen[0]&&futuroOrigen[0]===futuroOrigen[1]) return null;

  pd[destino.lado]=movido;
  po[origen.lado]=ocupante;      // vacío si el destino estaba libre
  po[ko]=null; pd[kd]=null;
  return {movido:movido, ocupante:ocupante};
}

/* --------------------------------------------------------------------------
   TRASPASOS
   Mover a un jugador de club es lo que más fácil desajusta el archivo, porque
   toca tres cosas a la vez: la plantilla, el historial y las estadísticas.

   El orden importa:
   1. Se cierra la etapa abierta en el club de origen y se le vuelcan los
      goles de la temporada, que son suyos, no del club nuevo.
   2. Se suman esos mismos goles a goles_totales, para que siga cumpliéndose
      que goles_totales es la suma del historial (app.js lo da por hecho).
   3. Se ponen a cero las estadísticas de temporada: en el club nuevo empieza
      de cero. Esto NO altera el ranking de goleadores de la web, que se
      calcula desde los eventos de los partidos, no desde este campo.
   4. Se abre la etapa nueva en el destino.

   `null` como destino significa quedarse sin club: agente libre.
   -------------------------------------------------------------------------- */
function traspasar(d, jugador, origen, destino, opciones){
  opciones = opciones || {};
  var etiqueta = opciones.temporada || ('Temporada '+(d.config.temporada||'?'));
  var hoy = new Date().toLocaleDateString('es-ES');
  if(!jugador.historial) jugador.historial = [];

  /* 1-3. Cerrar la etapa de origen. */
  if(origen){
    var et = null;
    for(var i=jugador.historial.length-1;i>=0;i--){
      var h = jugador.historial[i];
      if(h.abierto && (h.equipo_id===origen.id || h.equipo===origen.nombre)){ et = h; break; }
    }
    if(!et){
      et = {equipo:origen.nombre, equipo_id:origen.id, division:origen.division,
            temporada:etiqueta, temporada_inicio:etiqueta, temporada_fin:etiqueta, fecha:hoy,
            goles:0, asistencias:0, amarillas:0, rojas:0, pj:0, abierto:true};
      jugador.historial.push(et);
    }
    STATS_TEMP.forEach(function(par){
      var v = jugador[par[0]]||0;
      if(!v) return;
      et[par[0]] = (et[par[0]]||0)+v;
      jugador[par[1]] = (jugador[par[1]]||0)+v;
      jugador[par[0]] = 0;
    });
    et.temporada_fin = etiqueta;
    et.abierto = false;
    /* Fuera de la plantilla de origen. */
    origen.jugadores = (origen.jugadores||[]).filter(function(x){ return x!==jugador; });
  } else {
    d.agentes_libres = (d.agentes_libres||[]).filter(function(x){ return x!==jugador; });
  }

  /* 4. Abrir la etapa nueva. */
  if(destino){
    jugador.historial.push({
      equipo:destino.nombre, equipo_id:destino.id, division:destino.division,
      temporada:etiqueta, temporada_inicio:etiqueta, temporada_fin:etiqueta, fecha:hoy,
      goles:0, asistencias:0, amarillas:0, rojas:0, pj:0, abierto:true
    });
    /* Llega al banquillo: meterlo de titular sin mirar descuadraría el once. */
    jugador.titular = false;
    if(!destino.jugadores) destino.jugadores = [];
    destino.jugadores.push(jugador);
  } else {
    /* Sin club, la última etapa queda cerrada: no hay nada donde seguir
       acumulando hasta que alguien lo fiche. */
    if(jugador.historial.length) jugador.historial[jugador.historial.length-1].abierto = false;
    jugador.titular = false;
    jugador.fecha_agente_libre = hoy;
    if(!d.agentes_libres) d.agentes_libres = [];
    d.agentes_libres.push(jugador);
  }
  return jugador;
}

/* --------------------------------------------------------------------------
   RECALCULAR LOS GOLES DE LOS JUGADORES

   El campo `goles` de cada ficha debería ser el número de goles que le
   atribuyen los eventos de los partidos. Se puede desincronizar de mil formas:
   editando el marcador sin tocar los goleadores, corrigiendo un nombre, o
   traspasando a alguien.

   `soloDiferencias:false` recorre TODAS las fichas, también las que se quedan
   a cero: si un jugador tenía goles y sus eventos desaparecieron, hay que
   bajarlo, no dejarlo con la cifra vieja.

   Los goles cuentan para el jugador aunque los marcara con otra camiseta: son
   suyos, y así es como los suma la web en su ranking.
   -------------------------------------------------------------------------- */
function diferenciasGoles(d){
  var prev=D; D=d;
  try {
    var mapa=statsJugadoresCalculadas();
    var out=[];
    (d.equipos||[]).forEach(function(e){
      (e.jugadores||[]).forEach(function(j){
        var real=eventosDe(mapa,j).goles;
        if((j.goles||0)!==real) out.push({j:j, e:e, antes:j.goles||0, ahora:real});
      });
    });
    return out;
  } finally { D=prev; }
}
function recalcularGoles(d){
  var difs=diferenciasGoles(d);
  difs.forEach(function(x){ x.j.goles = x.ahora; });
  return difs;
}

/* --------------------------------------------------------------------------
   7. NORMALIZACIÓN
   Se ejecuta antes de cada guardado. Nunca destruye un dato: cuando dos
   campos dicen lo mismo se propaga el que exista, y si los dos existen y
   discrepan gana el canónico pero se registra el conflicto para avisar.
   -------------------------------------------------------------------------- */
function normalizarJugador(j,reg){
  /* amarillas/rojas es el par canónico; tarjetasAmarillas/tarjetasRojas es un
     alias heredado presente en 17 jugadores. Se mantienen los dos en el
     archivo (borrarlos rompería cualquier consumidor que aún los lea) pero
     sincronizados. */
  [['amarillas','tarjetasAmarillas'],['rojas','tarjetasRojas']].forEach(function(par){
    var k=par[0], alias=par[1];
    var tieneK=j[k]!=null, tieneA=j[alias]!=null;
    if(tieneK&&tieneA){
      if(j[k]!==j[alias]){ reg.push('Jugador "'+j.nombre+'": '+k+'='+j[k]+' pero '+alias+'='+j[alias]+'; gana '+k+'.'); j[alias]=j[k]; }
    } else if(tieneA&&!tieneK){
      j[k]=j[alias];
      reg.push('Jugador "'+j.nombre+'": '+k+' recuperado desde '+alias+'.');
    }
    /* Si sólo existe el canónico se deja así: no se inventa el alias. */
  });
  /* Sólo se garantiza `goles`. Asistencias y tarjetas no se registran nunca
     en esta liga ni se muestran en ninguna pantalla, así que crearlas a cero
     en fichas que no las traen sería engordar el archivo con ruido. Las que ya
     existen no se tocan: borrarlas es una decisión aparte y explícita. */
  if(j.goles==null) j.goles=0;
  if(j.dorsal!=null) j.dorsal=String(j.dorsal);      // la web asume string
  j.titular=!!j.titular;
  /* El historial guarda `temporada` en unos sitios y temporada_inicio/fin en
     otros; se completa lo que falte sin tocar lo que ya hay. */
  (j.historial||[]).forEach(function(h){
    if(!h.temporada_inicio) h.temporada_inicio=h.temporada||h.temporada_fin||'';
    if(!h.temporada_fin) h.temporada_fin=h.temporada||h.temporada_inicio||'';
    if(!h.temporada) h.temporada=h.temporada_inicio||h.temporada_fin||'';
  });
}
function normalizarPartido(p,reg,etiqueta){
  /* goles_l/golesl: se sincronizan los dos alias sólo si el partido ya traía
     el alias. Añadirlo donde no estaba engordaría el archivo sin motivo, y
     app.js lee bien cualquiera de los dos. */
  [['goles_l','golesl'],['goles_v','golesv']].forEach(function(par){
    var k=par[0], a=par[1], tk=p[k]!=null, ta=p[a]!=null;
    if(tk&&ta&&p[k]!==p[a]){ reg.push(etiqueta+': '+k+'='+p[k]+' y '+a+'='+p[a]+' no coincidían; gana '+k+'.'); p[a]=p[k]; }
    else if(ta&&!tk) p[k]=p[a];
  });
  p.goles_l=Number(p.goles_l)||0;
  p.goles_v=Number(p.goles_v)||0;
  if(p.golesl!=null) p.golesl=p.goles_l;
  if(p.golesv!=null) p.golesv=p.goles_v;
  /* Textos de goleadores: derivados de `detalles`, siempre regenerables. Se
     reescriben sólo si el partido ya los traía, por el mismo motivo. */
  if(p.goleadores_texto!=null||p.goleadores_local_texto!=null||p.goleadores_visitante_texto!=null){
    var t=textosDerivados(parseDetalles(p.detalles));
    p.goleadores_texto=t.goleadores_texto;
    p.goleadores_local_texto=t.goleadores_local_texto;
    p.goleadores_visitante_texto=t.goleadores_visitante_texto;
  }
}
function normalizar(d){
  var reg=[];
  (d.equipos||[]).forEach(function(e){
    (e.jugadores||[]).forEach(function(j){ normalizarJugador(j,reg); });
  });
  (d.agentes_libres||[]).forEach(function(j){ normalizarJugador(j,reg); });
  [['partidos_liga','Liga'],['partidos_ascenso','Ascenso'],['partidos_copa','Copa']].forEach(function(par){
    (d[par[0]]||[]).forEach(function(p,i){
      normalizarPartido(p,reg,par[1]+' #'+(i+1)+' '+(p.local||'?')+'-'+(p.visitante||'?'));
    });
  });
  return reg;
}

/* Quita de las fichas los campos que esta liga no usa. Es destructivo y por
   eso no se hace solo: se ofrece como acción con su recuento por delante. */
var CAMPOS_SIN_USO=['asistencias','amarillas','rojas','tarjetasAmarillas','tarjetasRojas',
                    'asistencias_totales','amarillas_totales','rojas_totales'];
function contarCamposSinUso(d){
  var n=0, conValor=0;
  function mirar(j){
    CAMPOS_SIN_USO.forEach(function(k){
      if(k in j){ n++; if(j[k]) conValor++; }
    });
  }
  (d.equipos||[]).forEach(function(e){ (e.jugadores||[]).forEach(mirar); });
  (d.agentes_libres||[]).forEach(mirar);
  return {campos:n, conValor:conValor};
}
function limpiarCamposSinUso(d){
  var n=0;
  function limpiar(j){
    CAMPOS_SIN_USO.forEach(function(k){ if(k in j){ delete j[k]; n++; } });
    (j.historial||[]).forEach(function(h){
      ['asistencias','amarillas','rojas'].forEach(function(k){ if(k in h){ delete h[k]; n++; } });
    });
  }
  (d.equipos||[]).forEach(function(e){ (e.jugadores||[]).forEach(limpiar); });
  (d.agentes_libres||[]).forEach(limpiar);
  return n;
}

/* --------------------------------------------------------------------------
   8. VALIDACIÓN
   -------------------------------------------------------------------------- */
/* Las diez claves de primer nivel del archivo real. historial_temporadas lo
   consume la web (Palmarés); agentes_libres, historial y clasificacion_copa
   sólo los usa el gestor, pero son parte del esquema y se preservan. */
var CLAVES=['config','equipos','partidos_liga','partidos_ascenso','partidos_copa',
            'historial','noticias','historial_temporadas','agentes_libres','clasificacion_copa'];
/* clasificacion_copa queda fuera de CLAVES_ARRAY: el gestor no la lee ni la
   escribe, y en el archivo real es un objeto {letra_de_grupo: [tabla]}, no una
   lista. Exigirle forma de array rechazaba el archivo bueno al abrirlo y,
   peor, la habria sobrescrito con [] al normalizar (mas abajo). Se conserva
   tal cual llegue, como una clave desconocida mas. */
var CLAVES_ARRAY=CLAVES.filter(function(k){ return k!=='config'&&k!=='clasificacion_copa'; });

function validarEsquema(d){
  var err=[], avi=[];
  if(!d||typeof d!=='object'||Array.isArray(d)) return {err:['El archivo no contiene un objeto JSON.'],avi:[]};
  if(!d.config||typeof d.config!=='object') err.push('Falta el bloque "config".');
  if(!Array.isArray(d.equipos)) err.push('Falta "equipos" o no es una lista.');
  CLAVES_ARRAY.forEach(function(k){
    if(d[k]===undefined) avi.push('Falta la clave "'+k+'"; se creará vacía.');
    else if(!Array.isArray(d[k])) err.push('"'+k+'" existe pero no es una lista.');
  });
  Object.keys(d).forEach(function(k){
    if(CLAVES.indexOf(k)<0) avi.push('Clave desconocida "'+k+'": se conservará sin tocar.');
  });
  return {err:err,avi:avi};
}
/* FORMATOS DE COMPETICIÓN
   Viven en config.formatos y son metadatos del gestor: describen cómo está
   montada cada competición.

   Honestidad sobre su alcance: app.js NO los lee. Las zonas de la tabla
   (play-off, play-in, descenso, ascenso) están escritas a mano en renderClas()
   —tres primeros, cuarto, quinto y sexto, últimos tres— y cambiarlas aquí no
   cambia la web. Lo que sí hacen es alimentar las comprobaciones del gestor
   (¿está el calendario completo?, ¿sobran o faltan equipos?) y, en la Fase 3,
   los generadores de calendario y de sorteo. Cuando el formato no coincide con
   lo que la web da por hecho, el gestor lo dice en vez de callárselo. */
var ZONAS_APP={SUPERLIGA:{playoff:3,playin:4,partido_playin:6,descenso:3},ASCENSO:{ascenso:3}};
function formatoDefecto(){
  return {
    SUPERLIGA:{vueltas:2, equipos:12, playoff:3, playin:4, partido_playin:6, descenso:3},
    ASCENSO:  {vueltas:2, equipos:10, ascenso:3},
    COPA:     {tipo:'grupos', equipos:16, grupos:4, clasifican_por_grupo:2, ida_vuelta:false}
  };
}

/* Rellena lo que falte para que el resto del gestor no tenga que comprobar
   nulos en cada línea. No borra ni reordena nada. */
function completarEsquema(d){
  if(!d.config) d.config={};
  ['ticker_superliga','ticker_ascenso','ticker_copa'].forEach(function(k){ if(!Array.isArray(d.config[k])) d.config[k]=[]; });
  if(!d.config.medios) d.config.medios={};
  /* Aditivo: si el archivo no traía formatos ni grupos, se crean con valores
     que describen la competición tal y como está montada hoy. */
  var def=formatoDefecto();
  if(!d.config.formatos) d.config.formatos={};
  Object.keys(def).forEach(function(k){
    if(!d.config.formatos[k]) d.config.formatos[k]={};
    Object.keys(def[k]).forEach(function(x){
      if(d.config.formatos[k][x]===undefined) d.config.formatos[k][x]=def[k][x];
    });
  });
  if(!d.config.grupos_copa || typeof d.config.grupos_copa!=='object' || Array.isArray(d.config.grupos_copa))
    d.config.grupos_copa={};
  CLAVES_ARRAY.forEach(function(k){ if(!Array.isArray(d[k])) d[k]=[]; });
  d.partidos_copa.forEach(function(p){
    if(p.origen_local===undefined) p.origen_local=null;
    if(p.origen_visitante===undefined) p.origen_visitante=null;
  });
  return d;
}

/* Letras de grupo según el formato: 4 grupos -> A, B, C, D. */
function letrasGrupo(d){
  var n=(d.config.formatos&&d.config.formatos.COPA&&d.config.formatos.COPA.grupos)||4;
  return Array.from({length:Math.max(1,Math.min(12,n))},function(_,i){ return String.fromCharCode(65+i); });
}

/* --------------------------------------------------------------------------
   TEMPORADAS
   Archivar la temporada en curso mete una copia en historial_temporadas, que
   es de donde palmares() de app.js saca los campeones. La forma de cada
   entrada la fija app.js: necesita `equipos` (para el campeón de cada
   división, por puntos) y `partidos_copa` (para la final de Copa).
   -------------------------------------------------------------------------- */
function instantaneaTemporada(d, nombre){
  return {
    nombre: nombre || ('Temporada '+(d.config.temporada||'?')),
    fecha: new Date().toLocaleDateString('es-ES'),
    /* Copia profunda: el histórico no puede compartir objetos con la
       temporada viva, o resetear las estadísticas lo vaciaría también. */
    equipos: JSON.parse(JSON.stringify(d.equipos)),
    partidos_liga: JSON.parse(JSON.stringify(d.partidos_liga)),
    partidos_ascenso: JSON.parse(JSON.stringify(d.partidos_ascenso)),
    partidos_copa: JSON.parse(JSON.stringify(d.partidos_copa)),
    config: JSON.parse(JSON.stringify(d.config))
  };
}
/* CAMPEONES DE UNA TEMPORADA

   Hay dos formas de saber quién ganó, y no dan lo mismo:

   - DERIVADO: el que más puntos tiene. Es lo que hace palmares() de app.js
     hoy, y por tanto lo que la web enseña.
   - GUARDADO: el campeón apuntado a mano en `t.campeones`.

   En una liga con play-off el campeón NO es el primero de la fase regular,
   es quien gana la final. El derivado se equivoca en cuanto haya
   eliminatorias, y por eso hace falta poder apuntarlo.

   Si hay campeones guardados mandan ellos; si no, se deriva como siempre.
   Cada entrada dice de dónde viene, para que la interfaz pueda avisar. */
var COMPETICIONES=[
  {clave:'SUPERLIGA', nombre:'Superliga Frontier'},
  {clave:'ASCENSO',   nombre:'Ascenso Frontier'},
  {clave:'COPA',      nombre:'Copa Fútbol Frontier'}
];
function campeonDerivado(t, clave){
  if(clave==='COPA'){
    var fin=(t.partidos_copa||[]).filter(function(p){ return p.fase==='FINAL'&&isFin(p); })[0];
    if(!fin) return null;
    var wn=gl(fin)>gv(fin)?fin.local:(gv(fin)>gl(fin)?fin.visitante:winnerOf(fin));
    var ce=(t.equipos||[]).find(function(e){ return e.nombre===wn; });
    return ce ? {e:ce, marcador:fin.local+' '+gl(fin)+'-'+gv(fin)+' '+fin.visitante} : null;
  }
  var e=(t.equipos||[]).filter(function(x){ return x.division===clave; })
    .sort(function(a,b){ return (b.pts||0)-(a.pts||0)||((b.gf-b.gc)-(a.gf-a.gc))||(b.gf-a.gf); })[0];
  return e ? {e:e} : null;
}
/* ¿Hay eliminatorias jugadas en esa división? Entonces el campeón por puntos
   es sospechoso y hay que decirlo en vez de darlo por bueno. */
function tieneEliminatorias(t, clave){
  if(clave==='COPA') return false;
  var ms=(clave==='ASCENSO'?t.partidos_ascenso:t.partidos_liga)||[];
  return ms.some(function(p){ return p.fase && isFin(p); });
}
function campeones(t){
  var guardados=Array.isArray(t.campeones)?t.campeones:[];
  var out=[];
  COMPETICIONES.forEach(function(c){
    var g=guardados.filter(function(x){ return x.comp===c.clave; })[0];
    if(g && g.equipo){
      var e=(t.equipos||[]).find(function(x){ return x.id===g.equipo_id; })
         || (t.equipos||[]).find(function(x){ return x.nombre===g.equipo; })
         || {nombre:g.equipo, id:g.equipo_id};
      out.push({comp:c.nombre, clave:c.clave, e:e, marcador:g.marcador||'', guardado:true});
      return;
    }
    var der=campeonDerivado(t, c.clave);
    if(der) out.push({comp:c.nombre, clave:c.clave, e:der.e, marcador:der.marcador||'',
                      guardado:false, dudoso:tieneEliminatorias(t, c.clave)});
  });
  return out;
}
/* Apunta o borra el campeón de una competición dentro de una temporada. */
function fijarCampeon(t, clave, equipo, marcador){
  if(!Array.isArray(t.campeones)) t.campeones=[];
  t.campeones=t.campeones.filter(function(x){ return x.comp!==clave; });
  if(equipo) t.campeones.push({comp:clave, equipo:equipo.nombre, equipo_id:equipo.id, marcador:marcador||''});
  if(!t.campeones.length) delete t.campeones;
  return t;
}

/* calcScorers necesita D para findPlayer; en validación se trabaja sobre el
   objeto que se está por guardar, que puede no ser aún el activo. */
function calcScorersSobre(d,ms){
  var prev=D; D=d;
  try { return calcScorers(ms); } finally { D=prev; }
}

/* Integridad referencial. `err` bloquea el guardado, `avi` sólo informa. */
function validarIntegridad(d){
  var err=[], avi=[];
  var nombres={}, ids={};
  d.equipos.forEach(function(e,i){
    var ir={v:'equipos',id:e.id};
    if(!e.id) err.push({m:'Equipo #'+(i+1)+' ("'+(e.nombre||'sin nombre')+'") no tiene id.',ir:ir});
    else if(ids[e.id]) err.push({m:'Id de equipo duplicado: "'+e.id+'".',ir:ir});
    else ids[e.id]=e;
    if(!e.nombre) err.push({m:'Equipo #'+(i+1)+' no tiene nombre.',ir:ir});
    else if(nombres[e.nombre]) err.push({m:'Nombre de equipo duplicado: "'+e.nombre+'". Los partidos referencian por nombre y no podrían distinguirlos.',ir:ir});
    else nombres[e.nombre]=e;
    if(DIVISIONES.indexOf(e.division)<0) err.push({m:'Equipo "'+(e.nombre||'#'+(i+1))+'" tiene división "'+e.division+'", que no es SUPERLIGA ni ASCENSO.',ir:ir});
  });

  [['partidos_liga','Liga','liga'],['partidos_ascenso','Ascenso','ascenso'],['partidos_copa','Copa','copa']].forEach(function(par){
    (d[par[0]]||[]).forEach(function(p,i){
      var et=par[1]+' #'+(i+1);
      var ir={v:'partidos',comp:par[2],idx:i};
      /* En Copa un lado vacío es normal si está vinculado a la ronda previa. */
      var lVinc=par[2]==='copa'&&p.origen_local!=null&&p.origen_local!=='';
      var vVinc=par[2]==='copa'&&p.origen_visitante!=null&&p.origen_visitante!=='';
      if(p.local&&!nombres[p.local]) err.push({m:et+': el equipo local "'+p.local+'" no existe.',ir:ir});
      else if(!p.local&&!lVinc) avi.push({m:et+': sin equipo local.',ir:ir});
      if(p.visitante&&!nombres[p.visitante]) err.push({m:et+': el equipo visitante "'+p.visitante+'" no existe.',ir:ir});
      else if(!p.visitante&&!vVinc) avi.push({m:et+': sin equipo visitante.',ir:ir});
      if(p.local&&p.local===p.visitante) err.push({m:et+': un equipo no puede jugar contra sí mismo.',ir:ir});
      if(p.estado!=='FINALIZADO'&&p.estado!=='PENDIENTE') err.push({m:et+': estado "'+p.estado+'" desconocido.',ir:ir});
      if(nombres[p.local]&&nombres[p.local].archivado) avi.push({m:et+': "'+p.local+'" está archivado y no aparece en la clasificación.',ir:ir});
      if(nombres[p.visitante]&&nombres[p.visitante].archivado) avi.push({m:et+': "'+p.visitante+'" está archivado y no aparece en la clasificación.',ir:ir});
    });
  });

  (d.partidos_copa||[]).forEach(function(p,i){
    var ir={v:'partidos',comp:'copa',idx:i};
    ['origen_local','origen_visitante'].forEach(function(k){
      var o=p[k];
      if(o==null||o==='') return;
      var n=Number(o);
      if(isNaN(n)||!d.partidos_copa[n]) err.push({m:'Copa #'+(i+1)+': '+k+' apunta a "'+o+'", que no es un cruce válido.',ir:ir});
      else if(n===i) err.push({m:'Copa #'+(i+1)+': '+k+' se apunta a sí mismo.',ir:ir});
    });
    if(p.fase&&FASES_TODAS.indexOf(p.fase)<0) avi.push({m:'Copa #'+(i+1)+': fase "'+p.fase+'" no es una de las conocidas.',ir:ir});
    if(p.fase==='FASE DE GRUPOS'&&!p.grupo) avi.push({m:'Copa #'+(i+1)+': está en fase de grupos pero no tiene grupo asignado.',ir:ir});
  });
  /* Ciclos en el cuadro: un origen que acabe volviendo sobre sí mismo colgaría
     la resolución en cascada de la web. */
  (d.partidos_copa||[]).forEach(function(p,i){
    ['origen_local','origen_visitante'].forEach(function(k){
      var visto={}, cur=i, lado=k;
      for(var n=0;n<=d.partidos_copa.length;n++){
        var q=d.partidos_copa[cur]; if(!q) break;
        var o=q[lado];
        if(o==null||o==='') break;
        o=Number(o);
        if(isNaN(o)||!d.partidos_copa[o]) break;   // ya lo reporta el bloque anterior
        if(visto[o]){ err.push({m:'Copa #'+(i+1)+': la cadena de '+k+' forma un ciclo.',ir:{v:'partidos',comp:'copa',idx:i}}); break; }
        visto[o]=1; cur=o; lado='origen_local';
      }
    });
  });

  var todos=(d.equipos||[]).reduce(function(a,e){ return a.concat(e.jugadores||[]); },[]).concat(d.agentes_libres||[]);
  todos.forEach(function(j){
    (j.historial||[]).forEach(function(h){
      if(h.equipo_id&&!ids[h.equipo_id]) err.push({m:'Historial de "'+j.nombre+'": equipo_id "'+h.equipo_id+'" no existe.',ir:{v:'equipos',id:h.equipo_id}});
    });
    if(j.posicion&&POS.indexOf(j.posicion)<0) avi.push({m:'Jugador "'+j.nombre+'": posición "'+j.posicion+'" desconocida.',ir:null});
    if(!afinidadLimpia(j.afinidad)) avi.push({m:'Jugador "'+j.nombre+'": afinidad "'+j.afinidad+'" no es oficial; la web la mostrará como '+afName(j.afinidad)+'.',ir:null});
  });

  /* Fases de Liga y Ascenso. Un partido con `fase` es una eliminatoria: no
     suma puntos y la web muestra la etiqueta en vez de "Jornada N". */
  [['partidos_liga','Liga','liga'],['partidos_ascenso','Ascenso','ascenso']].forEach(function(par){
    (d[par[0]]||[]).forEach(function(p,i){
      if(!p.fase) return;
      var ir={v:'partidos',comp:par[2],idx:i};
      if(FASES_LIGA.indexOf(p.fase)<0)
        avi.push({m:par[1]+' #'+(i+1)+': fase "'+p.fase+'" no es una de las conocidas.',ir:ir});
      /* Sin jornada, la web NO lo enseña: initJornadas() descarta los
         partidos sin jornada y renderMatches() filtra por ella. */
      if(p.jornada==null||p.jornada==='')
        err.push({m:par[1]+' #'+(i+1)+' ('+p.fase+'): sin jornada, la web no lo mostrará en Resultados. Ponle un número que continúe el calendario.',ir:ir});
    });
  });

  /* Grupos de Copa: la asignación de config.grupos_copa tiene que apuntar a
     equipos que existan, y nadie puede estar en dos grupos. */
  var gc=(d.config&&d.config.grupos_copa)||{};
  var yaEn={};
  Object.keys(gc).forEach(function(g){
    (gc[g]||[]).forEach(function(nom){
      var ir={v:'copa',grupo:g};
      if(!nombres[nom]) err.push({m:'Grupo '+g+' de Copa: el equipo "'+nom+'" no existe.',ir:ir});
      else if(yaEn[nom]) err.push({m:'"'+nom+'" está en el grupo '+yaEn[nom]+' y en el '+g+' a la vez.',ir:ir});
      else yaEn[nom]=g;
    });
  });

  /* Formatos: no los lee la web, pero si describen una competición distinta
     de la que hay montada, algo va a salir mal más adelante. */
  var fmt=(d.config&&d.config.formatos)||{};
  DIVISIONES.forEach(function(div){
    var f=fmt[div]; if(!f) return;
    var n=d.equipos.filter(function(e){ return e.division===div&&!e.archivado; }).length;
    if(f.equipos && n!==f.equipos)
      avi.push({m:'El formato de '+div+' dice '+f.equipos+' equipos y hay '+n+' activos.',ir:{v:'config'}});
    /* Las zonas de la tabla están escritas a mano en app.js: si el formato
       dice otra cosa, la web seguirá pintando las suyas. */
    var z=ZONAS_APP[div]||{};
    Object.keys(z).forEach(function(k){
      if(f[k]!=null && f[k]!==z[k])
        avi.push({m:'El formato de '+div+' pone '+k.replace(/_/g,' ')+' en '+f[k]+', pero la web tiene ese corte fijo en '+z[k]+' y no lo lee del archivo.',ir:{v:'config'}});
    });
  });
  if(fmt.COPA && fmt.COPA.tipo==='grupos'){
    var conGrupo=(d.partidos_copa||[]).filter(function(p){ return p.fase==='FASE DE GRUPOS'; });
    var gruposUsados=Array.from(new Set(conGrupo.map(function(p){ return p.grupo; }).filter(Boolean)));
    if(conGrupo.length && fmt.COPA.grupos && gruposUsados.length!==fmt.COPA.grupos)
      avi.push({m:'El formato de Copa dice '+fmt.COPA.grupos+' grupos y los partidos usan '+gruposUsados.length+'.',ir:{v:'copa'}});
  }

  /* Jornadas a medias: aviso, no error — es el estado normal a mitad de
     jornada. */
  [['partidos_liga','Liga','liga'],['partidos_ascenso','Ascenso','ascenso']].forEach(function(par){
    var jn={};
    (d[par[0]]||[]).forEach(function(p){ var j=p.jornada; if(j==null||j==='') return; (jn[j]=jn[j]||[]).push(p); });
    Object.keys(jn).forEach(function(j){
      var pend=jn[j].filter(function(p){ return !isFin(p); }).length;
      if(pend&&pend<jn[j].length) avi.push({m:par[1]+' jornada '+j+': '+pend+' de '+jn[j].length+' partidos sin resultado.',ir:{v:'partidos',comp:par[2],jornada:j}});
    });
  });

  /* Goleadores que no encajan con ningún jugador: la web los mostrará como
     texto suelto, sin foto ni ficha. */
  var sinFicha={};
  ['partidos_liga','partidos_ascenso','partidos_copa'].forEach(function(k){
    calcScorersSobre(d,(d[k]||[]).filter(isFin)).forEach(function(r){ if(!r.j) sinFicha[r.textoCrudo]=1; });
  });
  Object.keys(sinFicha).forEach(function(n){
    avi.push({m:'El goleador "'+n+'" no corresponde a ningún jugador registrado.',ir:null});
  });

  return {err:err,avi:avi};
}

/* --------------------------------------------------------------------------
   9. API
   -------------------------------------------------------------------------- */
SFG.core={
  norm:norm, esc:esc, gl:gl, gv:gv, isFin:isFin, abbr3:abbr3,
  afKey:afKey, afName:afName, afinidadLimpia:afinidadLimpia, AF_HEX:AF_HEX, AFINIDADES:AFINIDADES,
  POS:POS, POS_ORDER:POS_ORDER, DIVISIONES:DIVISIONES, FASES:FASES, FASES_TODAS:FASES_TODAS,
  FASES_LIGA:FASES_LIGA, esRegular:esRegular, ZONAS_APP:ZONAS_APP, letrasGrupo:letrasGrupo,
  TIPOS_EVENTO:TIPOS_EVENTO, TIPOS_EDITABLES:TIPOS_EDITABLES, TIPO_LABEL:TIPO_LABEL,
  esNoJugado:esNoJugado, contarCamposSinUso:contarCamposSinUso, limpiarCamposSinUso:limpiarCamposSinUso, CAMPOS_TABLA:CAMPOS_TABLA, CLAVES:CLAVES,
  instantaneaTemporada:instantaneaTemporada, campeones:campeones, cerrarTemporada:cerrarTemporada,
  COMPETICIONES:COMPETICIONES, campeonDerivado:campeonDerivado, fijarCampeon:fijarCampeon,
  traspasar:traspasar, moverEnCuadro:moverEnCuadro,
  generarCalendario:generarCalendario, generarCopa:generarCopa, azar:azar, barajar:barajar,
  equipo:equipo, equipoPorId:equipoPorId, pool:pool,
  orderStandings:orderStandings, clasificacion:clasificacion,
  winnerOf:winnerOf, resolveSide:resolveSide,
  parseDetalles:parseDetalles, serializarDetalles:serializarDetalles, textosDerivados:textosDerivados,
  findPlayer:findPlayer, calcScorers:calcScorers,
  tablaCalculada:tablaCalculada, desajustesTabla:desajustesTabla, statsJugadoresCalculadas:statsJugadoresCalculadas,
  eventosDe:eventosDe, diferenciasGoles:diferenciasGoles, recalcularGoles:recalcularGoles,
  distancia:distancia, parecido:parecido, analizarNombres:analizarNombres,
  renombrarEnEventos:renombrarEnEventos, renombrarJugador:renombrarJugador,
  normalizar:normalizar, validarEsquema:validarEsquema, completarEsquema:completarEsquema, validarIntegridad:validarIntegridad
};

})();
