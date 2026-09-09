/* ==========================================================================
   GESTOR SUPERLIGA FRONTIER — vista-redes.js
   Generador de imágenes para redes sociales.

   El lienzo, el marco, las pastillas y el ajuste de texto están PORTADOS de
   _fuente/app.js, que ya trae un generador de tarjetas de partido y de
   jugador funcionando. Reimplementarlos habría producido dos estéticas
   distintas para lo mismo; así lo que salga de aquí se parece a lo que la web
   ya deja descargar.

   NOTA SOBRE LAS FOTOS: el CDN que sirve los retratos no manda cabecera
   Access-Control-Allow-Origin, así que cargarlas con crossOrigin falla y sin
   crossOrigin contaminan el lienzo y toDataURL revienta. Se reintenta por un
   proxy de imágenes que sí la manda. Si también falla, la tarjeta sale con las
   iniciales en vez de con la foto. Es el mismo apaño que ya hace app.js.
   ========================================================================== */
(function(){
'use strict';

var SFG = window.SFG, C = SFG.core, U = SFG.ui;
var esc = C.esc;

/* foto/fotoImg/fotoY viven aquí y no en el JSON: son sólo para maquetar la
   imagen que se va a exportar, no datos de la liga. */
var sel = {plantilla:'resultado', comp:'liga', idx:0, formato:'16:9', foto:null, fotoImg:null, fotoY:50};
var FORMATOS = {'16:9':[1200,675], '1:1':[1080,1080], '9:16':[1080,1920]};
var F_SANS = 'Inter, -apple-system, sans-serif';
var F_MONO = '"JetBrains Mono", ui-monospace, monospace';
/* Teko es la fuente de "número enorme" de la propia web (--f-display, la
   usan .metric y los titulares de cifra): un condensado de verdad, no un
   mono ancho puesto a tamaño gigante. El marcador se lee como un cartel de
   estadio con ésta y como una app genérica con JetBrains Mono a 90px. */
var F_DISPLAY = '"Teko", '+F_SANS;
/* Fraunces italic es el otro rasgo de firma de la marca (.hero-title .l2,
   .serif-it, la letra capital de los artículos): el toque editorial que
   rompe la monotonía del sans+mono. Sin él, cualquier cosa que se dibuje
   aquí puede ser correcta pero nunca se lee como "de esta marca". */
var F_SERIF = '"Fraunces", Georgia, serif';
var PROXY = 'https://images.weserv.nl/?url=';

function d(){ return SFG.d(); }
function lista(){ return C.pool(sel.comp); }
function jugadores(){
  var out = [];
  d().equipos.filter(function(e){ return !e.archivado; }).forEach(function(e){
    (e.jugadores||[]).forEach(function(j){ out.push({j:j, e:e}); });
  });
  return out.sort(function(a,b){ return String(a.j.nombre).localeCompare(String(b.j.nombre),'es'); });
}

/* --------------------------------------------------------------------------
   LIENZO — portado de app.js
   -------------------------------------------------------------------------- */
function rr(ctx,x,y,w,h,r){
  r = Math.min(r, w/2, h/2);
  ctx.beginPath();
  ctx.moveTo(x+r,y);
  ctx.arcTo(x+w,y,x+w,y+h,r); ctx.arcTo(x+w,y+h,x,y+h,r);
  ctx.arcTo(x,y+h,x,y,r);     ctx.arcTo(x,y,x+w,y,r);
  ctx.closePath();
}
function hex2rgba(h,a){
  var c = String(h||'').replace('#','');
  if(c.length===3) c = c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
  var n = parseInt(c,16);
  if(isNaN(n)||c.length!==6) return 'rgba(255,255,255,'+a+')';
  return 'rgba('+((n>>16)&255)+','+((n>>8)&255)+','+(n&255)+','+a+')';
}
/* Luminancia, para elegir el color de lavado del club: varios tienen el
   primario casi negro y sobre fondo negro no teñiría nada. */
function lum(h){
  var c = String(h||'').replace('#','');
  if(c.length===3) c = c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
  var n = parseInt(c,16);
  if(isNaN(n)||c.length!==6) return 0;
  return (0.2126*((n>>16)&255)+0.7152*((n>>8)&255)+0.0722*(n&255))/255;
}
function lavado(e, porDefecto){
  var mejor = [e&&e.color1, e&&e.color2].filter(Boolean)
    .map(function(c){ return {c:c, l:lum(c)}; })
    .sort(function(a,b){ return b.l-a.l; })[0];
  return (mejor && mejor.l>0.14) ? mejor.c : porDefecto;
}
/* Grano de película, no plano digital perfecto: es el detalle más barato y
   con más efecto para que un degradado liso deje de oler a "renderizado por
   una IA" y empiece a oler a algo impreso. Un tile de 96×96 con ruido
   aleatorio, repetido como patrón y mezclado en "overlay" a baja opacidad;
   se genera una sola vez porque Math.random() por píxel en cada redibujada
   sería tirar CPU sin necesidad. */
var granoPatron = null;
function grano(ctx,W,H){
  if(!granoPatron){
    var t = document.createElement('canvas'); t.width = t.height = 96;
    var tctx = t.getContext('2d');
    var id = tctx.createImageData(96,96);
    for(var i=0;i<id.data.length;i+=4){
      var v = Math.random()*255;
      id.data[i]=v; id.data[i+1]=v; id.data[i+2]=v; id.data[i+3]=Math.random()*40;
    }
    tctx.putImageData(id,0,0);
    granoPatron = ctx.createPattern(t,'repeat');
  }
  ctx.save();
  ctx.globalCompositeOperation = 'overlay';
  ctx.globalAlpha = 0.5;
  ctx.fillStyle = granoPatron;
  ctx.fillRect(0,0,W,H);
  ctx.restore();
}
/* El manual de marca es explícito: el acento naranja "con cuentagotas", 95%
   monocromo. Un radial al 26% de opacidad tapando media pantalla más una
   rejilla de fondo es lo contrario — es la textura por defecto de cualquier
   plantilla de IA para "cartel deportivo". Aquí el color es casi un rumor:
   una viñeta neutra que da profundidad y un lavado de marca que apenas se
   nota si no se busca. */
function marco(ctx,W,H,tinte){
  ctx.fillStyle = '#000'; ctx.fillRect(0,0,W,H);
  var g = ctx.createRadialGradient(W*.5,0,0,W*.5,0,H*.85);
  g.addColorStop(0, hex2rgba(tinte||'#FF5100',.07)); g.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle = g; ctx.fillRect(0,0,W,H);
  var v = ctx.createRadialGradient(W*.5,H*.55,H*.2,W*.5,H*.55,H*.9);
  v.addColorStop(0,'rgba(0,0,0,0)'); v.addColorStop(1,'rgba(0,0,0,.6)');
  ctx.fillStyle = v; ctx.fillRect(0,0,W,H);
  grano(ctx,W,H);
}
function firma(ctx,W,H){
  ctx.textAlign = 'center';
  ctx.font = '500 '+Math.round(H*.022)+'px '+F_MONO;
  ctx.fillStyle = 'rgba(255,255,255,.4)';
  ctx.fillText('SUPERLIGA FRONTIER  ·  superligafrontier.es', W/2, H-Math.round(H*.055));
}
/* Reduce el cuerpo hasta que el texto quepa: los nombres de esta liga van de
   "Gar" a "Raleigh Greenstreet" y no puede salirse ninguno. */
function ajustar(ctx,texto,max,peso,inicio,min){
  var s = inicio;
  do { ctx.font = peso+' '+s+'px '+F_SANS; s -= 2; }
  while(ctx.measureText(texto).width>max && s>min);
  return ctx.font;
}
function cargar(src, cors){
  return new Promise(function(res){
    if(!src) return res(null);
    var i = new Image();
    if(cors) i.crossOrigin = 'anonymous';
    i.onload = function(){ res(i); };
    i.onerror = function(){ res(null); };
    i.referrerPolicy = 'no-referrer';
    i.src = src;
  });
}
function cargarImg(src){
  if(!src) return Promise.resolve(null);
  if(!/^https?:/.test(src)) return cargar(src,false);        // data: URI propio
  return cargar(src,true).then(function(i){
    if(i) return i;
    return cargar(PROXY+encodeURIComponent(String(src).replace(/^https?:\/\//,''))+'&output=png&n=-1', true);
  });
}
function contener(ctx,img,cx,cy,max){
  var s = Math.min(max/img.width, max/img.height);
  ctx.drawImage(img, cx-img.width*s/2, cy-img.height*s/2, img.width*s, img.height*s);
}
/* "cover": llena W×H recortando lo que sobre, para la foto de fondo a
   sangre de la plantilla de resultado. `foco` (0..1) es qué fracción de lo
   recortado verticalmente se quita por arriba: 0 pega la imagen al techo
   (recorta sólo por abajo, para retratos donde la cara está arriba), 1 la
   pega al suelo, 0.5 la centra. Ajustable por el usuario porque no hay forma
   de adivinar dónde está la cara en cualquier foto que suba. */
function cubrir(ctx,img,W,H,foco){
  var s = Math.max(W/img.width, H/img.height);
  var iw = img.width*s, ih = img.height*s;
  var y = (H-ih) * (foco==null?0.5:foco);
  ctx.drawImage(img, (W-iw)/2, y, iw, ih);
}
/* La marca en la esquina, igual que ".eyebrow" en el sitio real: puntito
   del acento con resplandor + mayúsculas en mono con tracking, no un
   escudo dentro de una chapa circular. El sitio no lleva insignias gráficas
   en su propia UI — lleva tipografía y un punto de color — así que el
   cartel exportado tampoco debería inventarse una. */
function marcaSF(ctx,W,H){
  var fs = Math.max(10, Math.round(H*0.018)), x = W*0.06, y = H*0.065;
  var r = fs*0.3;
  ctx.save();
  ctx.shadowColor = '#FF5100'; ctx.shadowBlur = r*3.2;
  ctx.fillStyle = '#FF5100';
  ctx.beginPath(); ctx.arc(x+r, y, r, 0, Math.PI*2); ctx.fill();
  ctx.restore();
  ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
  ctx.fillStyle = 'rgba(255,255,255,.85)';
  ctx.font = '600 '+fs+'px '+F_MONO;
  if('letterSpacing' in ctx) ctx.letterSpacing = Math.round(fs*.12)+'px';
  ctx.fillText('SUPERLIGA FRONTIER', x+r*3.2, y+1);
  if('letterSpacing' in ctx) ctx.letterSpacing = '0px';
  ctx.textBaseline = 'alphabetic';
}
function escudoEn(ctx,img,e,cx,cy,tam){
  if(img) return contener(ctx,img,cx,cy,tam);
  ctx.fillStyle = '#141414'; rr(ctx,cx-tam/2,cy-tam/2,tam,tam,tam*.22); ctx.fill();
  ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1; ctx.stroke();
  ctx.fillStyle = '#EDEDED'; ctx.textAlign = 'center';
  ctx.font = '700 '+Math.round(tam*.3)+'px '+F_SANS;
  ctx.fillText(C.abbr3(e&&e.nombre, e&&e.abreviatura), cx, cy+tam*.11);
}
function fuentes(){
  if(!document.fonts || !document.fonts.load) return Promise.resolve();
  return Promise.all([
    document.fonts.load('700 64px Inter'), document.fonts.load('600 24px Inter'),
    document.fonts.load('500 20px Inter'), document.fonts.load('600 90px "JetBrains Mono"'),
    document.fonts.load('500 18px "JetBrains Mono"'),
    document.fonts.load('600 160px Teko'), document.fonts.load('500 40px Teko'),
    document.fonts.load('italic 300 32px Fraunces')
  ]).catch(function(){});
}

/* --------------------------------------------------------------------------
   PLANTILLAS
   -------------------------------------------------------------------------- */
/* Una fila de goleador se dibuja como UNA sola cadena ("23'  Nombre" o
   "Nombre  23'"), no como dos fillText en x fijas: dos textos a distancias
   fijas se pisan en cuanto el nombre es más largo de lo previsto (pasó con
   "Raleigh Greenstreet"). Con ajustar() sobre la cadena completa, el ancho
   real manda y nunca puede solaparse consigo misma. */
function filaGoleador(ctx,texto,x,alinear,maxW,fs){
  ctx.textAlign = alinear;
  ctx.font = ajustar(ctx, texto, maxW, '500', fs, 9);
  return ctx.font;
}
function dibujarResultado(ctx,W,H,p){
  var L = C.equipo(p.local), V = C.equipo(p.visitante);
  var cl = lavado(L,'#FF5100'), cv = lavado(V,'#3E7BFF');
  var heroImg = sel.foto ? sel.fotoImg : null;

  return Promise.all([
    cargarImg(L&&L.escudo), cargarImg(V&&V.escudo)
  ]).then(function(imgs){
    /* Fondo: la foto que suba el usuario a sangre si hay una (con la
       posición vertical que elija); si no, un "versus" partido en diagonal
       con el color de cada club — el póster de un derbi de verdad, no dos
       manchas radiales suaves desvaneciéndose hacia el centro (que es lo
       que hacía que el fondo sin foto pareciera un salvapantallas). */
    var cutX = W*0.52, sesgo = H*0.22;
    if(heroImg){
      /* La foto va a color, sin virados ni duotonos: el cartel de
         referencia usa la fotografía tal cual, sólo con un degradado
         oscuro debajo para que el texto se lea. Cualquier efecto encima
         es un adorno que ella no necesita. */
      ctx.fillStyle = '#000'; ctx.fillRect(0,0,W,H);
      cubrir(ctx, heroImg, W, H, (sel.fotoY==null?50:sel.fotoY)/100);
      var arriba = ctx.createLinearGradient(0,0,0,H*0.28);
      arriba.addColorStop(0,'rgba(0,0,0,.5)'); arriba.addColorStop(1,'rgba(0,0,0,0)');
      ctx.fillStyle = arriba; ctx.fillRect(0,0,W,H*0.28);
      var abajo = ctx.createLinearGradient(0,H*0.38,0,H);
      abajo.addColorStop(0,'rgba(0,0,0,0)'); abajo.addColorStop(.55,'rgba(0,0,0,.72)'); abajo.addColorStop(1,'rgba(0,0,0,.97)');
      ctx.fillStyle = abajo; ctx.fillRect(0,H*0.38,W,H*0.62);
      grano(ctx,W,H);
    } else {
      marco(ctx,W,H,cl);
      ctx.save();
      ctx.beginPath();
      ctx.moveTo(0,0); ctx.lineTo(cutX+sesgo,0); ctx.lineTo(cutX-sesgo,H); ctx.lineTo(0,H); ctx.closePath();
      ctx.clip();
      var g1 = ctx.createLinearGradient(0,0,W*0.75,H*0.3);
      g1.addColorStop(0, hex2rgba(cl,.6)); g1.addColorStop(1,'rgba(0,0,0,.1)');
      ctx.fillStyle = g1; ctx.fillRect(0,0,W,H);
      ctx.restore();
      ctx.save();
      ctx.beginPath();
      ctx.moveTo(cutX+sesgo,0); ctx.lineTo(W,0); ctx.lineTo(W,H); ctx.lineTo(cutX-sesgo,H); ctx.closePath();
      ctx.clip();
      var g2 = ctx.createLinearGradient(W,0,W*0.25,H*0.3);
      g2.addColorStop(0, hex2rgba(cv,.6)); g2.addColorStop(1,'rgba(0,0,0,.1)');
      ctx.fillStyle = g2; ctx.fillRect(0,0,W,H);
      ctx.restore();
      ctx.save();
      ctx.strokeStyle = 'rgba(255,255,255,.4)'; ctx.lineWidth = 2;
      ctx.shadowColor = 'rgba(255,255,255,.5)'; ctx.shadowBlur = H*0.012;
      ctx.beginPath(); ctx.moveTo(cutX+sesgo,0); ctx.lineTo(cutX-sesgo,H); ctx.stroke();
      ctx.restore();
      grano(ctx,W,H);
    }
    marcaSF(ctx,W,H);

    /* A partir de aquí, la estructura sigue al milímetro el cartel de
       referencia: escudos en chapas oscuras redondeadas (no flotando
       sueltos), marcador enorme entre ellas, una línea pequeña de
       competición ENCIMA del marcador y una pastilla de estado SÓLIDA
       debajo — no una traducida a "acento tenue", porque en el original
       es precisamente la única mancha de color sólido de todo el cartel,
       y funciona porque es la única. Sólo en el fondo sin foto, que no
       tiene la energía de una fotografía real detrás, se mantiene la
       diagonal y el acento en itálica como recurso propio para que no se
       quede plano. */
    var panelY = H*0.58, panelH = H*0.40;
    if(!heroImg){
      var tiltY = H*0.018;
      ctx.strokeStyle = 'rgba(255,255,255,.16)'; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(W*0.06,panelY-tiltY); ctx.lineTo(W*0.94,panelY+tiltY); ctx.stroke();
      ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.4)';
      ctx.font = 'italic 300 '+Math.round(H*0.024)+'px '+F_SERIF;
      ctx.fillText('Resultado oficial', W/2, panelY-tiltY-H*0.05);
    }

    var tam = Math.min(W,H)*0.105, cy = panelY+panelH*0.26;
    [[W*0.155, imgs[0], L], [W*0.845, imgs[1], V]].forEach(function(par){
      var caja = tam*1.34;
      ctx.save();
      ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = tam*0.22; ctx.shadowOffsetY = tam*0.06;
      ctx.fillStyle = 'rgba(12,14,20,.6)';
      rr(ctx, par[0]-caja/2, cy-caja/2, caja, caja, caja*.2); ctx.fill();
      ctx.restore();
      ctx.strokeStyle = 'rgba(255,255,255,.16)'; ctx.lineWidth = 1;
      rr(ctx, par[0]-caja/2, cy-caja/2, caja, caja, caja*.2); ctx.stroke();
      escudoEn(ctx, par[1], par[2], par[0], cy, tam*.78);
    });

    /* Competición, pequeña y discreta, justo encima del marcador — el
       mismo sitio donde el cartel de referencia pone el logo del torneo. */
    ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.55)';
    ctx.font = '600 '+Math.round(panelH*0.072)+'px '+F_MONO;
    if('letterSpacing' in ctx) ctx.letterSpacing = Math.round(panelH*0.006)+'px';
    ctx.fillText((p.fase||('JORNADA '+(p.jornada||'?'))).toUpperCase(), W/2, cy-tam*0.85);
    if('letterSpacing' in ctx) ctx.letterSpacing = '0px';

    /* Marcador: Teko, la fuente condensada de la propia web para cifras
       grandes (--f-display), con sombra para que se despegue de la foto. */
    ctx.textAlign = 'center';
    if('letterSpacing' in ctx) ctx.letterSpacing = '-0.02em';
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = panelH*0.05; ctx.shadowOffsetY = panelH*0.015;
    if(C.isFin(p)){
      ctx.fillStyle = '#fff';
      ctx.font = '600 '+Math.round(panelH*0.5)+'px '+F_DISPLAY;
      ctx.fillText(C.gl(p)+' – '+C.gv(p), W/2, cy+panelH*0.16);
    } else {
      ctx.fillStyle = 'rgba(255,255,255,.7)';
      ctx.font = '600 '+Math.round(panelH*0.24)+'px '+F_DISPLAY;
      ctx.fillText('VS', W/2, cy+panelH*0.07);
    }
    ctx.restore();
    if('letterSpacing' in ctx) ctx.letterSpacing = '0px';

    /* Pastilla de estado: rellena de naranja sólido, blanco encima — la
       única mancha de color sólido de todo el cartel, a propósito. */
    var etq = C.isFin(p) ? 'FINALIZADO' : 'POR JUGAR';
    var altoP = Math.round(panelH*0.115);
    ctx.font = '700 '+Math.round(altoP*.46)+'px '+F_MONO;
    var anchoP = ctx.measureText(etq).width + altoP*0.9;
    var pillX = (W-anchoP)/2, pillY = cy+panelH*0.22;
    ctx.fillStyle = '#FF5100';
    rr(ctx, pillX, pillY, anchoP, altoP, altoP/2); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(etq, pillX+anchoP/2, pillY+altoP/2+1);
    ctx.textBaseline = 'alphabetic';

    /* Goleadores en dos columnas espejadas, con un balón delante del
       minuto como en el cartel de referencia: local a la izquierda con el
       minuto por delante, visitante a la derecha con el minuto detrás. Como
       mucho 4 por lado, con un "+N" si sobran, para que nunca se apilen. */
    var ev = C.parseDetalles(p.detalles);
    var golesL = ev.local.filter(esGol), golesV = ev.visitante.filter(esGol);
    var filas = Math.min(4, Math.max(golesL.length, golesV.length, 0));
    if(filas){
      var y0 = pillY+altoP+panelH*0.16, disponible = panelY+panelH*0.96-y0;
      var filaH = Math.max(disponible/filas, Math.round(panelH*0.09));
      var fs = Math.round(Math.min(filaH*0.42, panelH*0.045));
      var maxW = W*0.32;
      [[golesL,'left',W*0.09],[golesV,'right',W*0.91]].forEach(function(col){
        var lista = col[0], alinear = col[1], x = col[2];
        lista.slice(0,4).forEach(function(g,i){
          var y = y0+i*filaH;
          var texto = alinear==='left' ? ('⚽ '+g.minuto+"'  "+g.nombre) : (g.nombre+"  "+g.minuto+"' ⚽");
          ctx.fillStyle = '#EDEDED';
          filaGoleador(ctx, texto, x, alinear, maxW, fs);
          ctx.fillText(texto, x, y);
        });
        if(lista.length>4){
          ctx.fillStyle = 'rgba(255,255,255,.5)';
          ctx.font = '500 '+Math.round(fs*0.82)+'px '+F_MONO;
          ctx.textAlign = alinear;
          ctx.fillText('+'+(lista.length-4), x, y0+4*filaH);
        }
      });
    }
    firma(ctx,W,H);
  });
}
function esGol(e){ return e.tipo==='gol'; }

function dibujarClasificacion(ctx,W,H,div){
  marco(ctx,W,H,'#FF5100');
  var ord = C.clasificacion(div);
  marcaSF(ctx,W,H);
  /* El título va DEBAJO de la marca, no a su misma altura: centrado a
     toda página, un título grande empieza casi en el borde izquierdo y
     se comía el logo si compartían banda. */
  ctx.textAlign = 'center'; ctx.fillStyle = '#EDEDED';
  ctx.font = '700 '+Math.round(H*0.038)+'px '+F_SANS;
  ctx.fillText(div==='SUPERLIGA'?'SUPERLIGA FRONTIER':'ASCENSO FRONTIER', W/2, H*0.155);
  ctx.font = 'italic 300 '+Math.round(H*0.02)+'px '+F_SERIF;
  ctx.fillStyle = 'rgba(255,255,255,.4)';
  ctx.fillText('Clasificación', W/2, H*0.128);
  ctx.font = '500 '+Math.round(H*0.017)+'px '+F_MONO;
  ctx.fillStyle = 'rgba(255,255,255,.45)';
  ctx.fillText('TEMPORADA '+(d().config.temporada||'?')+' · JORNADA '+(d().config.jornada_actual||'?'), W/2, H*0.185);
  ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(W*0.08,H*0.205); ctx.lineTo(W*0.92,H*0.215); ctx.stroke();

  var top = ord.slice(0, Math.min(ord.length, H>1400?14:10));
  var y0 = H*0.245, alto = (H*0.665)/top.length;
  return Promise.all(top.map(function(e){ return cargarImg(e.escudo); })).then(function(imgs){
      top.forEach(function(e,i){
        var y = y0 + i*alto, cy = y+alto*0.5;
        if(i%2===0){ ctx.fillStyle = 'rgba(255,255,255,.035)'; rr(ctx, W*0.06, y+alto*0.06, W*0.88, alto*0.88, 10); ctx.fill(); }
        /* El color en el número de puesto, no una chapa rellena detrás:
           mismo criterio de "acento con cuentagotas" que el resto. */
        ctx.textAlign = 'center'; ctx.fillStyle = i<3 ? '#FF7A38' : 'rgba(255,255,255,.4)';
        ctx.font = '700 '+Math.round(alto*0.32)+'px '+F_MONO;
        ctx.fillText(String(i+1), W*0.115, cy+alto*0.11);
        escudoEn(ctx, imgs[i], e, W*0.195, cy, alto*0.58);
        ctx.textAlign = 'left'; ctx.fillStyle = '#EDEDED';
        ctx.font = ajustar(ctx, e.nombre, W*0.46, '600', Math.round(alto*0.34), 10);
        ctx.fillText(e.nombre, W*0.255, cy+alto*0.12);
        ctx.textAlign = 'right'; ctx.fillStyle = '#fff';
        ctx.font = '700 '+Math.round(alto*0.38)+'px '+F_MONO;
        ctx.fillText(String(e.pts||0), W*0.92, cy+alto*0.12);
        ctx.fillStyle = 'rgba(255,255,255,.35)';
        ctx.font = '500 '+Math.round(alto*0.16)+'px '+F_MONO;
        ctx.fillText('PTS', W*0.92, cy-alto*0.16);
      });
      firma(ctx,W,H);
  });
}

function dibujarMvp(ctx,W,H,r){
  var e = r.e;
  marco(ctx,W,H, lavado(e,'#FF5100'));
  return Promise.all([cargarImg(r.j&&r.j.foto), cargarImg(e&&e.escudo)]).then(function(imgs){
    var cx = W/2, cy = H*0.38, rad = Math.min(W,H)*0.16;
    ctx.save();
    ctx.beginPath(); ctx.arc(cx,cy,rad,0,Math.PI*2); ctx.closePath(); ctx.clip();
    if(imgs[0]){
      var s = Math.max(rad*2/imgs[0].width, rad*2/imgs[0].height);
      ctx.drawImage(imgs[0], cx-imgs[0].width*s/2, cy-imgs[0].height*s/2, imgs[0].width*s, imgs[0].height*s);
    } else {
      ctx.fillStyle = '#1C1C1C'; ctx.fillRect(cx-rad,cy-rad,rad*2,rad*2);
      ctx.fillStyle = '#7A7A7A'; ctx.textAlign = 'center';
      ctx.font = '700 '+Math.round(rad)+'px '+F_SANS;
      ctx.fillText(((r.j&&r.j.nombre)||'?').trim()[0].toUpperCase(), cx, cy+rad*0.35);
    }
    ctx.restore();
    ctx.beginPath(); ctx.arc(cx,cy,rad,0,Math.PI*2);
    ctx.strokeStyle = 'rgba(255,255,255,.25)'; ctx.lineWidth = 4; ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#EDEDED';
    ctx.font = ajustar(ctx, r.j?r.j.nombre:r.nombre, W*0.82, '700', Math.round(H*0.062), 16);
    ctx.fillText(r.j?r.j.nombre:r.nombre, cx, cy+rad+H*0.10);

    if(e){
      escudoEn(ctx, imgs[1], e, cx-W*0.14, cy+rad+H*0.155, H*0.05);
      ctx.textAlign = 'left'; ctx.fillStyle = 'rgba(255,255,255,.6)';
      ctx.font = '500 '+Math.round(H*0.028)+'px '+F_SANS;
      ctx.fillText(e.nombre, cx-W*0.10, cy+rad+H*0.168);
    }
    ctx.textAlign = 'center'; ctx.fillStyle = '#FF7A38';
    ctx.font = '600 '+Math.round(H*0.10)+'px '+F_MONO;
    ctx.fillText(String(r.goles), cx, cy+rad+H*0.30);
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '500 '+Math.round(H*0.024)+'px '+F_MONO;
    ctx.fillText(r.goles===1?'GOL':'GOLES', cx, cy+rad+H*0.34);
    firma(ctx,W,H);
  });
}

/* --- Previa del partido: forma reciente y cara a cara ------------------ */
function forma(nombre, div, n){
  var ms = (div==='ASCENSO' ? d().partidos_ascenso : d().partidos_liga)
    .filter(function(p){ return C.isFin(p) && C.esRegular(p) && (p.local===nombre||p.visitante===nombre); })
    .sort(function(a,b){ return (parseInt(b.jornada)||0)-(parseInt(a.jornada)||0); });
  return ms.slice(0,n).map(function(p){
    var casa = p.local===nombre;
    var f = casa?C.gl(p):C.gv(p), c = casa?C.gv(p):C.gl(p);
    return f>c?'V':(f<c?'D':'E');
  }).reverse();
}
function caraACara(a, b){
  return d().partidos_liga.concat(d().partidos_ascenso, d().partidos_copa).filter(function(p){
    return C.isFin(p) && ((p.local===a&&p.visitante===b)||(p.local===b&&p.visitante===a));
  });
}
function dibujarPrevia(ctx,W,H,p){
  var L = C.equipo(p.local), V = C.equipo(p.visitante);
  marco(ctx,W,H, lavado(L,'#FF5100'));
  var duelos = caraACara(p.local, p.visitante);
  var ga=0, gb=0, emp=0;
  duelos.forEach(function(q){
    var x = q.local===p.local ? Number(C.gl(q))||0 : Number(C.gv(q))||0;
    var y = q.local===p.local ? Number(C.gv(q))||0 : Number(C.gl(q))||0;
    if(x>y) ga++; else if(y>x) gb++; else emp++;
  });
  var divL = L?L.division:'SUPERLIGA';

  return Promise.all([cargarImg(L&&L.escudo), cargarImg(V&&V.escudo)]).then(function(imgs){
    ctx.textAlign = 'center';
    ctx.fillStyle = 'rgba(255,255,255,.5)';
    ctx.font = '600 '+Math.round(H*0.026)+'px '+F_MONO;
    ctx.fillText('PREVIA · '+(p.fase||('JORNADA '+(p.jornada||'?'))).toUpperCase(), W/2, H*0.115);

    var tam = Math.min(W,H)*0.15, cy = H*0.33;
    escudoEn(ctx, imgs[0], L, W*0.27, cy, tam);
    escudoEn(ctx, imgs[1], V, W*0.73, cy, tam);
    ctx.fillStyle = '#EDEDED';
    ctx.font = ajustar(ctx, p.local||'', W*0.36, '600', Math.round(H*0.04), 12);
    ctx.fillText(p.local||'', W*0.27, cy+tam*0.85);
    ctx.font = ajustar(ctx, p.visitante||'', W*0.36, '600', Math.round(H*0.04), 12);
    ctx.fillText(p.visitante||'', W*0.73, cy+tam*0.85);
    ctx.fillStyle = 'rgba(255,255,255,.3)';
    ctx.font = '600 '+Math.round(H*0.05)+'px '+F_SANS;
    ctx.fillText('VS', W/2, cy+H*0.01);

    /* Forma reciente: cinco puntos por equipo, verde/gris/rojo. */
    var y0 = cy+tam*0.85+H*0.09;
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '500 '+Math.round(H*0.022)+'px '+F_MONO;
    ctx.fillText('ÚLTIMOS 5', W/2, y0-H*0.025);
    [[W*0.27, forma(p.local, divL, 5)], [W*0.73, forma(p.visitante, V?V.division:divL, 5)]].forEach(function(par){
      var r = Math.round(H*0.014), sep = r*3;
      par[1].forEach(function(res, i){
        var x = par[0] + (i-(par[1].length-1)/2)*sep;
        ctx.fillStyle = res==='V' ? '#46B45F' : (res==='D' ? '#F0554A' : '#525252');
        ctx.beginPath(); ctx.arc(x, y0, r, 0, Math.PI*2); ctx.fill();
      });
      if(!par[1].length){
        ctx.fillStyle = 'rgba(255,255,255,.25)';
        ctx.font = '500 '+Math.round(H*0.022)+'px '+F_SANS;
        ctx.fillText('sin partidos', par[0], y0+r);
      }
    });

    /* Historial de enfrentamientos. */
    var y1 = y0 + H*0.10;
    ctx.fillStyle = 'rgba(255,255,255,.45)';
    ctx.font = '500 '+Math.round(H*0.022)+'px '+F_MONO;
    ctx.fillText(duelos.length ? 'CARA A CARA · '+duelos.length : 'NUNCA SE HAN ENFRENTADO', W/2, y1);
    if(duelos.length){
      ctx.fillStyle = '#EDEDED';
      ctx.font = '600 '+Math.round(H*0.055)+'px '+F_MONO;
      ctx.fillText(ga+'  '+emp+'  '+gb, W/2, y1+H*0.075);
      ctx.fillStyle = 'rgba(255,255,255,.35)';
      ctx.font = '500 '+Math.round(H*0.02)+'px '+F_MONO;
      ctx.fillText('VICTORIAS    EMPATES    VICTORIAS', W/2, y1+H*0.105);
    }
    firma(ctx,W,H);
  });
}

/* --- Cartel de sorteo de Copa ----------------------------------------- */
function dibujarSorteo(ctx,W,H){
  marco(ctx,W,H,'#FF5100');
  var ms = d().partidos_copa;
  var fase = C.FASES_TODAS.filter(function(f){ return ms.some(function(p){ return p.fase===f && !C.isFin(p); }); })[0]
          || C.FASES_TODAS.filter(function(f){ return ms.some(function(p){ return p.fase===f; }); }).pop();

  marcaSF(ctx,W,H);
  ctx.textAlign = 'center';
  ctx.fillStyle = 'rgba(255,255,255,.4)';
  ctx.font = 'italic 300 '+Math.round(H*0.022)+'px '+F_SERIF;
  ctx.fillText('Sorteo oficial', W/2, H*0.075);
  ctx.fillStyle = '#EDEDED';
  ctx.font = '700 '+Math.round(H*0.045)+'px '+F_SANS;
  ctx.fillText('COPA FÚTBOL FRONTIER', W/2, H*0.10);
  ctx.fillStyle = '#FF7A38';
  ctx.font = '600 '+Math.round(H*0.028)+'px '+F_MONO;
  ctx.fillText(String(fase||'SORTEO').toUpperCase(), W/2, H*0.145);

  /* La fase de grupos no son cruces 1 contra 1: son bolsas de equipos por
     grupo, y forzarla al mismo layout de "cruce" dejaba huecos sin sentido
     (un cruce por partido cuando en un grupo de 4 hay 6). Cartel aparte. */
  if(fase==='FASE DE GRUPOS'){
    return dibujarSorteoGrupos(ctx, W, H, ms.filter(function(p){ return p.fase===fase; }));
  }

  var cruces = ms.filter(function(p){ return p.fase===fase; }).slice(0,8);
  if(!cruces.length){
    ctx.fillStyle = 'rgba(255,255,255,.35)';
    ctx.font = '500 '+Math.round(H*0.03)+'px '+F_SANS;
    ctx.fillText('Sin cruces sorteados', W/2, H/2);
    firma(ctx,W,H);
    return Promise.resolve();
  }
  var escudos = cruces.reduce(function(a,p){
    return a.concat([C.equipo(C.resolveSide(p,'local').n), C.equipo(C.resolveSide(p,'visitante').n)]);
  }, []);
  return Promise.all(escudos.map(function(e){ return cargarImg(e&&e.escudo); })).then(function(imgs){
    var y0 = H*0.21, alto = (H*0.66)/cruces.length;
    /* Con pocos cruces (la Final es uno solo) `alto` crece muchísimo; si el
       tamaño de letra escalara con él, los dos nombres largos y el "VS"
       acaban peleándose por el mismo hueco central. Se limita aparte del
       alto de la fila, que sigue creciendo para que la caja no quede
       enana. */
    var filaTexto = Math.min(alto, H*0.16);
    cruces.forEach(function(p, i){
      var y = y0 + i*alto, cy = y + alto*0.42;
      ctx.fillStyle = 'rgba(255,255,255,.03)';
      rr(ctx, W*0.07, y, W*0.86, alto*0.82, 10); ctx.fill();
      var L = C.resolveSide(p,'local'), V = C.resolveSide(p,'visitante');
      var tam = Math.min(alto*0.5, W*0.07);
      escudoEn(ctx, imgs[i*2], C.equipo(L.n), W*0.16, cy, tam);
      escudoEn(ctx, imgs[i*2+1], C.equipo(V.n), W*0.84, cy, tam);
      ctx.fillStyle = '#EDEDED'; ctx.textAlign = 'left';
      ctx.font = ajustar(ctx, L.n||'Por definir', W*0.2, '600', Math.round(filaTexto*0.26), 10);
      ctx.fillText(L.n||'Por definir', W*0.23, cy+filaTexto*0.09);
      ctx.textAlign = 'right';
      ctx.font = ajustar(ctx, V.n||'Por definir', W*0.2, '600', Math.round(filaTexto*0.26), 10);
      ctx.fillText(V.n||'Por definir', W*0.77, cy+filaTexto*0.09);
      ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.28)';
      ctx.font = '600 '+Math.round(filaTexto*0.24)+'px '+F_SANS;
      ctx.fillText('VS', W/2, cy+filaTexto*0.09);
    });
    firma(ctx,W,H);
  });
}
/* Un cartel por grupo, en rejilla: cada grupo lista los equipos que
   participan en él (sacados de local/visitante de sus propios cruces, sin
   depender de que ya tengan partidos jugados). */
function dibujarSorteoGrupos(ctx,W,H,ms){
  var grupos = {};
  ms.forEach(function(p){
    var g = p.grupo||'?';
    grupos[g] = grupos[g] || [];
    [C.resolveSide(p,'local').n, C.resolveSide(p,'visitante').n].forEach(function(n){
      if(n && grupos[g].indexOf(n)<0) grupos[g].push(n);
    });
  });
  var letras = Object.keys(grupos).sort();
  if(!letras.length){
    ctx.fillStyle = 'rgba(255,255,255,.35)';
    ctx.font = '500 '+Math.round(H*0.03)+'px '+F_SANS;
    ctx.fillText('Sin grupos sorteados', W/2, H/2);
    firma(ctx,W,H);
    return Promise.resolve();
  }
  var equiposFlat = letras.reduce(function(a,g){ return a.concat(grupos[g]); }, []);
  return Promise.all(equiposFlat.map(function(n){ var e=C.equipo(n); return cargarImg(e&&e.escudo); })).then(function(imgs){
    var mapaImg = {};
    equiposFlat.forEach(function(n,i){ mapaImg[n] = imgs[i]; });

    var cols = letras.length<=2 ? letras.length : (W>=H ? Math.min(3,letras.length) : Math.min(2,letras.length));
    cols = Math.max(1,cols);
    var filas = Math.ceil(letras.length/cols);
    var padX = W*0.06, padTop = H*0.19, padBottom = H*0.08, gap = W*0.03;
    var cw = (W - padX*2 - gap*(cols-1))/cols;
    var ch = (H - padTop - padBottom - gap*(filas-1))/filas;

    letras.forEach(function(g,gi){
      var col = gi%cols, fila = Math.floor(gi/cols);
      var x = padX + col*(cw+gap), y = padTop + fila*(ch+gap);
      ctx.fillStyle = 'rgba(255,255,255,.04)'; rr(ctx,x,y,cw,ch,14); ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1; rr(ctx,x,y,cw,ch,14); ctx.stroke();
      ctx.textAlign = 'left'; ctx.fillStyle = '#FF7A38';
      ctx.font = '700 '+Math.round(ch*0.09)+'px '+F_MONO;
      ctx.fillText('GRUPO '+g, x+cw*0.07, y+ch*0.12);

      var equipos = grupos[g];
      var rowH = (ch*0.80)/Math.max(equipos.length,1);
      equipos.forEach(function(n,i){
        var ry0 = y+ch*0.19+i*rowH, tam = Math.min(rowH*0.66, cw*0.16);
        escudoEn(ctx, mapaImg[n], C.equipo(n), x+cw*0.13, ry0+rowH*0.5, tam);
        ctx.fillStyle = '#EDEDED'; ctx.textAlign = 'left';
        ctx.font = ajustar(ctx, n||'Por definir', cw*0.64, '600', Math.round(rowH*0.36), 9);
        ctx.fillText(n||'Por definir', x+cw*0.24, ry0+rowH*0.6);
      });
    });
    firma(ctx,W,H);
  });
}

/* --- Ficha de fichaje ---------------------------------------------------
   El club de origen es la última etapa cerrada del historial del jugador
   (`j.historial`): la etapa actual en el club de destino no se apunta ahí
   (ver la nota del esquema en core.js), así que "de dónde viene" es
   simplemente el último tramo de esa lista. Si no hay historial, es un
   fichaje sin procedencia registrada y se rotula como agente libre. */
function clubOrigen(j){
  var h = j&&j.historial;
  if(!h || !h.length) return null;
  var u = h[h.length-1];
  return C.equipoPorId(u.equipo_id) || {nombre:u.equipo, escudo:null};
}
function dibujarFichaje(ctx,W,H,j,e){
  var origen = clubOrigen(j);
  marco(ctx,W,H, lavado(e,'#FF5100'));
  return Promise.all([cargarImg(j&&j.foto), cargarImg(e&&e.escudo), cargarImg(origen&&origen.escudo)]).then(function(imgs){
    ctx.textAlign = 'center';
    ctx.fillStyle = '#FF7A38';
    ctx.font = '600 '+Math.round(H*0.026)+'px '+F_MONO;
    ctx.fillText('FICHAJE OFICIAL', W/2, H*0.09);

    var rad = Math.min(W,H)*0.15, cx = W/2, cy = H*0.30;
    ctx.save();
    ctx.beginPath(); ctx.arc(cx,cy,rad,0,Math.PI*2); ctx.clip();
    if(imgs[0]){
      var s = Math.max(rad*2/imgs[0].width, rad*2/imgs[0].height);
      ctx.drawImage(imgs[0], cx-imgs[0].width*s/2, cy-imgs[0].height*s/2, imgs[0].width*s, imgs[0].height*s);
    } else {
      ctx.fillStyle = '#1C1C1C'; ctx.fillRect(cx-rad,cy-rad,rad*2,rad*2);
      ctx.fillStyle = '#7A7A7A';
      ctx.font = '700 '+Math.round(rad)+'px '+F_SANS;
      ctx.fillText(((j&&j.nombre)||'?').trim()[0].toUpperCase(), cx, cy+rad*0.35);
    }
    ctx.restore();
    ctx.beginPath(); ctx.arc(cx,cy,rad,0,Math.PI*2);
    ctx.strokeStyle = 'rgba(255,255,255,.25)'; ctx.lineWidth = 4; ctx.stroke();

    ctx.fillStyle = '#EDEDED';
    ctx.font = ajustar(ctx, j.nombre||'', W*0.82, '700', Math.round(H*0.052), 16);
    ctx.fillText(j.nombre||'', cx, cy+rad+H*0.08);

    ctx.fillStyle = 'rgba(255,255,255,.5)';
    ctx.font = '500 '+Math.round(H*0.022)+'px '+F_MONO;
    ctx.fillText([j.posicion||'', j.dorsal?('DORSAL '+j.dorsal):'', C.afName(j.afinidad).toUpperCase()]
      .filter(Boolean).join('  ·  '), cx, cy+rad+H*0.115);

    /* La ruta del traspaso, origen -> destino: dos reglas finas arriba y
       abajo, no una tarjeta de cristal flotante encima de otra. */
    var panelY = cy+rad+H*0.165, panelH = H*0.16;
    ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(W*0.08,panelY); ctx.lineTo(W*0.92,panelY); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(W*0.08,panelY+panelH); ctx.lineTo(W*0.92,panelY+panelH); ctx.stroke();

    /* Izquierda = origen (de dónde viene), derecha = destino (adónde va),
       flecha apuntando en ese sentido: se lee como una noticia de fichaje. */
    var tam = Math.min(W,H)*0.085, midY = panelY+panelH*0.42;
    if(origen){
      escudoEn(ctx, imgs[2], origen, W*0.22, midY, tam);
    } else {
      ctx.fillStyle = '#141414'; rr(ctx, W*0.22-tam/2, midY-tam/2, tam, tam, tam*.22); ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1; ctx.stroke();
      ctx.fillStyle = 'rgba(255,255,255,.4)'; ctx.font = '700 '+Math.round(tam*.4)+'px '+F_SANS;
      ctx.textAlign = 'center'; ctx.fillText('?', W*0.22, midY+tam*.14);
    }
    ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.3)';
    ctx.font = '700 '+Math.round(panelH*0.3)+'px '+F_SANS;
    ctx.fillText('→', W/2, midY+panelH*0.08);
    escudoEn(ctx, imgs[1], e, W*0.78, midY, tam);

    ctx.fillStyle = 'rgba(255,255,255,.4)'; ctx.textAlign = 'center';
    ctx.font = '600 '+Math.round(panelH*0.14)+'px '+F_MONO;
    ctx.fillText(origen?'ORIGEN':'AGENTE LIBRE', W*0.22, panelY+panelH*0.82);
    ctx.fillText('DESTINO', W*0.78, panelY+panelH*0.82);

    ctx.fillStyle = '#EDEDED';
    if(origen){
      ctx.font = ajustar(ctx, origen.nombre, W*0.32, '600', Math.round(panelH*0.22), 9);
      ctx.fillText(origen.nombre, W*0.22, panelY+panelH*0.68);
    }
    ctx.font = ajustar(ctx, e.nombre, W*0.32, '600', Math.round(panelH*0.22), 9);
    ctx.fillText(e.nombre, W*0.78, panelY+panelH*0.68);
    firma(ctx,W,H);
  });
}

/* --- Hilo de jornada en texto ------------------------------------------
   No es una imagen: es el texto listo para pegar. Se redacta desde los
   resultados, sin inventar nada que no esté en el archivo. */
function hiloJornada(){
  var D = d();
  var jor = parseInt(D.config.jornada_actual)||0;
  var ms = D.partidos_liga.concat(D.partidos_ascenso)
    .filter(function(p){ return (parseInt(p.jornada)||0)===jor; });
  var fin = ms.filter(C.isFin);
  if(!ms.length) return 'La jornada '+jor+' no tiene partidos cargados.';

  var lineas = ['⚽ JORNADA '+jor+' · '+(D.config.nombre_liga||'Superliga Frontier')+'', ''];
  fin.forEach(function(p){
    var ev = C.parseDetalles(p.detalles);
    var goles = ev.local.filter(esGol).concat(ev.visitante.filter(esGol));
    lineas.push((p.local||'?')+' '+C.gl(p)+'-'+C.gv(p)+' '+(p.visitante||'?')+
      (goles.length ? '\n   ' + goles.map(function(e){ return e.nombre+' '+e.minuto+"'"; }).join(', ') : ''));
  });
  var pend = ms.filter(function(p){ return !C.isFin(p); });
  if(pend.length){
    lineas.push('', 'Pendientes: '+pend.map(function(p){ return (p.local||'?')+' vs '+(p.visitante||'?'); }).join(' · '));
  }
  /* Máximo goleador de la jornada, si lo hay. */
  var t = {};
  fin.forEach(function(p){
    var ev = C.parseDetalles(p.detalles);
    ev.local.concat(ev.visitante).forEach(function(e){ if(e.tipo==='gol') t[e.nombre] = (t[e.nombre]||0)+1; });
  });
  var top = Object.keys(t).sort(function(a,b){ return t[b]-t[a]; })[0];
  if(top && t[top]>1) lineas.push('', '🎯 '+top+', '+t[top]+' goles en la jornada.');

  var lider = C.clasificacion('SUPERLIGA')[0];
  if(lider) lineas.push('', '📊 Líder: '+lider.nombre+' con '+(lider.pts||0)+' puntos.');
  return lineas.join('\n');
}

/* --- Resultados de toda la jornada -------------------------------------
   Cartel único con todos los partidos de la jornada actual de una
   competición, no partido a partido: es lo que se publica al cerrar la
   jornada, cuando ya no tiene sentido subir un cartel por encuentro. */
function partidosJornada(comp){
  var D = d(), jor = parseInt(D.config.jornada_actual)||0;
  if(comp==='copa'){
    var ms = D.partidos_copa;
    var fase = C.FASES_TODAS.filter(function(f){ return ms.some(function(p){ return p.fase===f && !C.isFin(p); }); })[0]
            || C.FASES_TODAS.filter(function(f){ return ms.some(function(p){ return p.fase===f; }); }).pop();
    return {etiqueta:String(fase||'COPA').toUpperCase(), ms:ms.filter(function(p){ return p.fase===fase; }).slice(0,10)};
  }
  var pool = comp==='ascenso'?D.partidos_ascenso:D.partidos_liga;
  return {etiqueta:'JORNADA '+jor, ms:pool.filter(function(p){ return (parseInt(p.jornada)||0)===jor; })};
}
function dibujarJornada(ctx,W,H,comp){
  marco(ctx,W,H,'#FF5100');
  var D = d(), j = partidosJornada(comp);
  marcaSF(ctx,W,H);
  ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.4)';
  ctx.font = 'italic 300 '+Math.round(H*0.02)+'px '+F_SERIF;
  ctx.fillText('Resultados', W/2, H*0.128);
  ctx.fillStyle = '#EDEDED';
  ctx.font = '700 '+Math.round(H*0.038)+'px '+F_SANS;
  ctx.fillText(D.config.nombre_liga||'Superliga Frontier', W/2, H*0.155);
  ctx.fillStyle = '#FF7A38';
  ctx.font = '600 '+Math.round(H*0.024)+'px '+F_MONO;
  ctx.fillText(j.etiqueta, W/2, H*0.19);
  ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1;
  ctx.beginPath(); ctx.moveTo(W*0.08,H*0.205); ctx.lineTo(W*0.92,H*0.215); ctx.stroke();

  if(!j.ms.length){
    ctx.fillStyle = 'rgba(255,255,255,.35)';
    ctx.font = '500 '+Math.round(H*0.03)+'px '+F_SANS;
    ctx.fillText('Sin partidos en esta jornada', W/2, H/2);
    firma(ctx,W,H);
    return Promise.resolve();
  }
  var escudos = j.ms.reduce(function(a,p){ return a.concat([C.equipo(p.local), C.equipo(p.visitante)]); }, []);
  return Promise.all(escudos.map(function(e){ return cargarImg(e&&e.escudo); })).then(function(imgs){
      var y0 = H*0.25, alto = Math.min((H*0.665)/j.ms.length, H*0.11);
      j.ms.forEach(function(p,i){
        var y = y0 + i*alto, cy = y+alto*0.5;
        if(i%2===0){ ctx.fillStyle = 'rgba(255,255,255,.035)'; rr(ctx, W*0.06, y+alto*0.06, W*0.88, alto*0.88, 10); ctx.fill(); }
        var tam = Math.min(alto*0.6, W*0.08);
        escudoEn(ctx, imgs[i*2], C.equipo(p.local), W*0.15, cy, tam);
        escudoEn(ctx, imgs[i*2+1], C.equipo(p.visitante), W*0.85, cy, tam);
        ctx.fillStyle = '#EDEDED'; ctx.textAlign = 'left';
        ctx.font = ajustar(ctx, p.local||'?', W*0.26, '600', Math.round(alto*0.24), 9);
        ctx.fillText(p.local||'?', W*0.21, cy+alto*0.09);
        ctx.textAlign = 'right';
        ctx.font = ajustar(ctx, p.visitante||'?', W*0.26, '600', Math.round(alto*0.24), 9);
        ctx.fillText(p.visitante||'?', W*0.79, cy+alto*0.09);
        ctx.textAlign = 'center';
        if('letterSpacing' in ctx) ctx.letterSpacing = '-0.02em';
        if(C.isFin(p)){
          ctx.fillStyle = '#fff'; ctx.font = '600 '+Math.round(alto*0.5)+'px '+F_DISPLAY;
          ctx.fillText(C.gl(p)+' – '+C.gv(p), W/2, cy+alto*0.13);
        } else {
          ctx.fillStyle = 'rgba(255,255,255,.4)'; ctx.font = '600 '+Math.round(alto*0.26)+'px '+F_DISPLAY;
          ctx.fillText('VS', W/2, cy+alto*0.09);
        }
        if('letterSpacing' in ctx) ctx.letterSpacing = '0px';
      });
      firma(ctx,W,H);
  });
}

/* --------------------------------------------------------------------------
   RENDER Y DESCARGA
   -------------------------------------------------------------------------- */
/* Encolada: si se pide una redibujada mientras otra sigue esperando a que
   carguen sus imágenes, dibujar directamente encima corrompe el lienzo (la
   segunda cambia cv.width/height a medio camino y dibuja con las medidas de
   la primera). Una a la vez, en orden. */
var colaDibujo = Promise.resolve();
function dibujar(){
  colaDibujo = colaDibujo.catch(function(){}).then(dibujarReal);
  return colaDibujo;
}
function dibujarReal(){
  var dim = FORMATOS[sel.formato];
  var cv = document.getElementById('redes-lienzo');
  if(!cv) return Promise.resolve();
  cv.width = dim[0]; cv.height = dim[1];
  var ctx = cv.getContext('2d');
  return fuentes().then(function(){
    if(sel.plantilla==='clasificacion') return dibujarClasificacion(ctx, dim[0], dim[1], sel.comp==='ascenso'?'ASCENSO':'SUPERLIGA');
    if(sel.plantilla==='sorteo') return dibujarSorteo(ctx, dim[0], dim[1]);
    if(sel.plantilla==='jornada') return dibujarJornada(ctx, dim[0], dim[1], sel.comp);
    if(sel.plantilla==='mvp'){
      var top = C.calcScorers(lista().filter(C.isFin));
      if(!top.length) return vacio(ctx, dim[0], dim[1], 'Sin goleadores en esta competición');
      return dibujarMvp(ctx, dim[0], dim[1], top[Math.min(sel.idx, top.length-1)]);
    }
    if(sel.plantilla==='fichaje'){
      var js = jugadores();
      if(!js.length) return vacio(ctx, dim[0], dim[1], 'Sin jugadores');
      var x = js[Math.min(sel.idx, js.length-1)];
      return dibujarFichaje(ctx, dim[0], dim[1], x.j, x.e);
    }
    var p = lista()[sel.idx];
    if(!p) return vacio(ctx, dim[0], dim[1], 'Sin partidos');
    if(sel.plantilla==='previa') return dibujarPrevia(ctx, dim[0], dim[1], p);
    return dibujarResultado(ctx, dim[0], dim[1], p);
  });
}
function vacio(ctx,W,H,texto){
  marco(ctx,W,H,'#FF5100');
  ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.4)';
  ctx.font = '500 '+Math.round(H*0.035)+'px '+F_SANS;
  ctx.fillText(texto, W/2, H/2);
}
function descargar(){
  var cv = document.getElementById('redes-lienzo');
  try {
    var a = document.createElement('a');
    a.download = (nombreArchivo()+'.png').replace(/[\\/:*?"<>|]/g,'-');
    a.href = cv.toDataURL('image/png');
    document.body.appendChild(a); a.click(); a.remove();
    U.aviso('Imagen descargada.', 'ok');
  } catch(e){
    /* Pasa cuando una foto externa contaminó el lienzo y el proxy tampoco
       pudo servirla. Se dice qué hacer en vez de dejar un error mudo. */
    U.aviso('El navegador no deja exportar: alguna imagen externa bloquea el lienzo. Prueba a incrustarla arrastrándola en la ficha del club o del jugador.', 'mal', 12000);
  }
}
function nombreArchivo(){
  if(sel.plantilla==='clasificacion') return 'clasificacion-'+(sel.comp==='ascenso'?'ascenso':'superliga');
  if(sel.plantilla==='sorteo') return 'sorteo-copa';
  if(sel.plantilla==='jornada') return 'jornada-'+(d().config.jornada_actual||'')+'-'+sel.comp;
  if(sel.plantilla==='mvp') return 'mvp';
  if(sel.plantilla==='fichaje'){
    var js = jugadores(), x = js[Math.min(sel.idx, js.length-1)];
    return x ? ('fichaje-'+x.j.nombre) : 'fichaje';
  }
  var p = lista()[sel.idx];
  if(!p) return 'tarjeta';
  return (sel.plantilla==='previa' ? 'previa-' : '')+p.local+'-'+p.visitante;
}

/* --------------------------------------------------------------------------
   VISTA
   -------------------------------------------------------------------------- */
function pintar(el){
  var ms = lista();
  var goleadores = sel.plantilla==='mvp' ? C.calcScorers(ms.filter(C.isFin)) : [];

  el.innerHTML =
    U.cabecera('Redes sociales', 'Imágenes con el mismo lenguaje que las tarjetas de la web')+
    '<div class="rejilla" style="--min:280px;align-items:start">'+
      '<div class="card" style="padding:var(--g5)">'+
        '<h3 style="font-size:.9375rem;margin-bottom:var(--g4)">Plantilla</h3>'+
        '<div class="rejilla rejilla-2" style="margin-bottom:var(--g4)">'+
          U.campo('Tipo', '<select class="inp" data-c="redes:plantilla">'+
            [['resultado','Resultado del partido'],['previa','Previa del partido'],
             ['jornada','Resultados de la jornada'],
             ['clasificacion','Clasificación'],['sorteo','Cartel de sorteo de Copa'],
             ['mvp','MVP / goleador'],['fichaje','Ficha de fichaje']]
            .map(function(t){ return '<option value="'+t[0]+'"'+(sel.plantilla===t[0]?' selected':'')+'>'+t[1]+'</option>'; }).join('')+'</select>')+
          U.campo('Formato', '<select class="inp" data-c="redes:formato">'+
            Object.keys(FORMATOS).map(function(f){
              return '<option value="'+f+'"'+(sel.formato===f?' selected':'')+'>'+f+' · '+FORMATOS[f].join('×')+'</option>'; }).join('')+'</select>')+
        '</div>'+
        (sel.plantilla==='resultado'||sel.plantilla==='previa'||sel.plantilla==='clasificacion'||sel.plantilla==='jornada'
          ? U.campo('Competición', '<select class="inp" data-c="redes:comp">'+
              [['liga','Superliga'],['ascenso','Ascenso'],['copa','Copa']].map(function(c){
                return '<option value="'+c[0]+'"'+(sel.comp===c[0]?' selected':'')+'>'+c[1]+'</option>'; }).join('')+'</select>')
          : '')+
        (sel.plantilla==='resultado'||sel.plantilla==='previa'
          ? '<div class="g-hueco"></div>'+U.campo('Partido', '<select class="inp" data-c="redes:idx">'+
              ms.map(function(p,i){
                return '<option value="'+i+'"'+(sel.idx===i?' selected':'')+'>'+
                  esc((p.local||'?')+' '+(C.isFin(p)?C.gl(p)+'-'+C.gv(p):'vs')+' '+(p.visitante||'?'))+
                  ' · '+esc(p.fase||('J'+(p.jornada||'?')))+'</option>'; }).join('')+'</select>')
          : '')+
        (sel.plantilla==='resultado'
          ? '<div class="g-hueco"></div>'+U.campo('Foto de fondo (opcional)', '<input type="file" accept="image/*" class="inp" data-c="redes:fotoArchivo">')+
            (sel.foto
              ? U.campo('Posición vertical de la foto', '<input type="range" min="0" max="100" value="'+sel.fotoY+'" data-c="redes:fotoY">')+
                '<button class="btn btn-secondary btn-sm" style="margin-top:.4rem" data-a="redes:quitarFoto"><i class="ph ph-x"></i> Quitar foto</button>'
              : '<p class="ayuda" style="margin-top:.35rem">Sin foto, el cartel usa los colores del club. Sube una imagen tuya para que aparezca a sangre detrás del marcador.</p>')
          : '')+
        (sel.plantilla==='mvp'
          ? '<div class="g-hueco"></div>'+U.campo('Jugador', '<select class="inp" data-c="redes:idx">'+
              goleadores.slice(0,30).map(function(r,i){
                return '<option value="'+i+'"'+(sel.idx===i?' selected':'')+'>'+esc(r.nombre)+' · '+r.goles+' goles</option>'; }).join('')+'</select>')
          : '')+
        (sel.plantilla==='fichaje'
          ? '<div class="g-hueco"></div>'+U.campo('Jugador', '<select class="inp" data-c="redes:idx">'+
              jugadores().map(function(x,i){
                return '<option value="'+i+'"'+(sel.idx===i?' selected':'')+'>'+esc(x.j.nombre)+' · '+esc(x.e.nombre)+'</option>'; }).join('')+'</select>')
          : '')+
        '<div class="g-hueco"></div>'+
        '<div style="display:flex;gap:.4rem;flex-wrap:wrap">'+
          '<button class="btn btn-secondary btn-sm" data-a="redes:refrescar"><i class="ph ph-arrows-clockwise"></i> Redibujar</button>'+
          '<button class="btn btn-primary btn-sm" data-a="redes:descargar"><i class="ph-bold ph-download-simple"></i> Descargar PNG</button>'+
        '</div>'+
        '<p class="ayuda" style="margin-top:var(--g4)">El marco, la rejilla y el ajuste de texto están portados de la web, para que lo que publiques y lo que se descarga desde la ficha del partido se parezcan.</p>'+
      '</div>'+

      '<div class="card" style="padding:var(--g4)">'+
        '<canvas id="redes-lienzo" style="width:100%;height:auto;display:block;border-radius:var(--r-sm);background:#000"></canvas>'+
        '<p class="ayuda" style="margin-top:var(--g3)" id="redes-estado">Dibujando…</p>'+
      '</div>'+
    '</div>'+
    '<div class="g-hueco"></div>'+
    '<div class="card" style="padding:var(--g5)">'+
      '<div style="display:flex;align-items:center;gap:var(--g3);margin-bottom:.35rem;flex-wrap:wrap">'+
        '<h3 style="font-size:.9375rem">Hilo de jornada</h3>'+
        '<button class="btn btn-secondary btn-sm" style="margin-left:auto" data-a="redes:copiarHilo">'+
          '<i class="ph ph-copy"></i> Copiar</button></div>'+
      '<p class="ayuda" style="margin-bottom:var(--g3)">Texto redactado desde los resultados de la jornada actual. No inventa nada que no esté en el archivo.</p>'+
      '<textarea class="inp" id="redes-hilo" style="min-height:180px;font-family:var(--f-mono);font-size:.75rem" readonly>'+
        esc(hiloJornada())+'</textarea>'+
    '</div>';

  dibujar().then(function(){
    var e = document.getElementById('redes-estado');
    if(e) e.textContent = FORMATOS[sel.formato].join(' × ')+' px · '+nombreArchivo()+'.png';
  });
}

var A = {
  plantilla: function(el){ sel.plantilla = el.value; sel.idx = 0; U.refrescar(); },
  formato:   function(el){ sel.formato = el.value; U.refrescar(); },
  comp:      function(el){ sel.comp = el.value; sel.idx = 0; U.refrescar(); },
  idx:       function(el){ sel.idx = Number(el.value); U.refrescar(); },
  fotoArchivo: function(el){
    var f = el.files && el.files[0];
    if(!f) return;
    var lector = new FileReader();
    lector.onload = function(){
      sel.foto = lector.result;
      /* Se decodifica una vez aquí y se guarda el Image ya listo: así
         mover el slider de posición no vuelve a leer el archivo en cada
         tirón, sólo redibuja con la imagen que ya está en memoria. */
      cargarImg(sel.foto).then(function(img){ sel.fotoImg = img; U.refrescar(); });
    };
    lector.readAsDataURL(f);
  },
  fotoY: function(el){ sel.fotoY = Number(el.value); dibujar(); },
  quitarFoto: function(){ sel.foto = null; sel.fotoImg = null; sel.fotoY = 50; U.refrescar(); },
  copiarHilo: function(){
    var t = document.getElementById('redes-hilo');
    t.select();
    /* navigator.clipboard exige contexto seguro y permiso; execCommand sigue
       funcionando abriendo el gestor por file://, que es un caso real aquí. */
    var ok = false;
    try { ok = document.execCommand('copy'); } catch(e){}
    if(!ok && navigator.clipboard) return navigator.clipboard.writeText(t.value)
      .then(function(){ U.aviso('Hilo copiado.', 'ok'); })
      .catch(function(){ U.aviso('No se pudo copiar; el texto queda seleccionado.', 'ojo'); });
    U.aviso(ok ? 'Hilo copiado.' : 'El texto queda seleccionado para copiarlo a mano.', ok ? 'ok' : 'ojo');
  },
  refrescar: function(){ dibujar(); },
  descargar: function(){ dibujar().then(descargar); }
};

U.registrar('redes', {acciones:A, render:pintar});

})();
