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

var sel = {plantilla:'resultado', comp:'liga', idx:0, formato:'16:9'};
var FORMATOS = {'16:9':[1200,675], '1:1':[1080,1080], '9:16':[1080,1920]};
var F_SANS = 'Inter, -apple-system, sans-serif';
var F_MONO = '"JetBrains Mono", ui-monospace, monospace';
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
function marco(ctx,W,H,tinte){
  ctx.fillStyle = '#000'; ctx.fillRect(0,0,W,H);
  ctx.save();
  ctx.strokeStyle = 'rgba(255,255,255,.05)'; ctx.lineWidth = 1;
  for(var x=0;x<W;x+=64){ ctx.beginPath(); ctx.moveTo(x+.5,0); ctx.lineTo(x+.5,H); ctx.stroke(); }
  for(var y=0;y<H;y+=64){ ctx.beginPath(); ctx.moveTo(0,y+.5); ctx.lineTo(W,y+.5); ctx.stroke(); }
  ctx.restore();
  var g = ctx.createRadialGradient(W*.5,-H*.1,0,W*.5,-H*.1,H*.95);
  g.addColorStop(0, hex2rgba(tinte||'#FF5100',.26)); g.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle = g; ctx.fillRect(0,0,W,H);
  var v = ctx.createRadialGradient(W*.5,H*.45,H*.25,W*.5,H*.5,H*.95);
  v.addColorStop(0,'rgba(0,0,0,0)'); v.addColorStop(1,'rgba(0,0,0,.75)');
  ctx.fillStyle = v; ctx.fillRect(0,0,W,H);
  ctx.strokeStyle = 'rgba(255,255,255,.14)'; ctx.lineWidth = 2;
  rr(ctx,14,14,W-28,H-28,26); ctx.stroke();
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
function pastilla(ctx,x,y,texto,fg,bg,alto){
  ctx.font = '600 '+Math.round(alto*.42)+'px '+F_SANS;
  var w = ctx.measureText(texto).width + alto*.7;
  ctx.fillStyle = bg; rr(ctx,x,y,w,alto,alto/2); ctx.fill();
  ctx.fillStyle = fg; ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
  ctx.fillText(texto, x+alto*.35, y+alto/2+.5);
  ctx.textBaseline = 'alphabetic';
  return w;
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
    document.fonts.load('500 18px "JetBrains Mono"')
  ]).catch(function(){});
}

/* --------------------------------------------------------------------------
   PLANTILLAS
   -------------------------------------------------------------------------- */
function dibujarResultado(ctx,W,H,p){
  var L = C.equipo(p.local), V = C.equipo(p.visitante);
  var cl = lavado(L,'#FF5100'), cv = lavado(V,'#3E7BFF');
  marco(ctx,W,H,cl);

  /* Lavado por mitades: cada lado teñido con el color de su club. */
  var g1 = ctx.createRadialGradient(0,H/2,0,0,H/2,W*.62);
  g1.addColorStop(0, hex2rgba(cl,.30)); g1.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle = g1; ctx.fillRect(0,0,W,H);
  var g2 = ctx.createRadialGradient(W,H/2,0,W,H/2,W*.62);
  g2.addColorStop(0, hex2rgba(cv,.30)); g2.addColorStop(1,'rgba(0,0,0,0)');
  ctx.fillStyle = g2; ctx.fillRect(0,0,W,H);

  var cy = H*0.5, tam = Math.min(W,H)*0.19;
  return Promise.all([cargarImg(L&&L.escudo), cargarImg(V&&V.escudo)]).then(function(imgs){
    escudoEn(ctx, imgs[0], L, W*0.22, cy-H*0.06, tam);
    escudoEn(ctx, imgs[1], V, W*0.78, cy-H*0.06, tam);

    ctx.textAlign = 'center'; ctx.fillStyle = '#EDEDED';
    ctx.font = ajustar(ctx, p.local||'', W*0.3, '600', Math.round(H*.045), 12);
    ctx.fillText(p.local||'', W*0.22, cy+H*0.09);
    ctx.font = ajustar(ctx, p.visitante||'', W*0.3, '600', Math.round(H*.045), 12);
    ctx.fillText(p.visitante||'', W*0.78, cy+H*0.09);

    /* Marcador o VS. */
    if(C.isFin(p)){
      ctx.fillStyle = '#fff';
      ctx.font = '600 '+Math.round(H*0.16)+'px '+F_MONO;
      ctx.fillText(C.gl(p)+'  '+C.gv(p), W/2, cy+H*0.02);
      ctx.fillStyle = 'rgba(255,255,255,.28)';
      ctx.fillText('–', W/2, cy+H*0.02);
    } else {
      ctx.fillStyle = 'rgba(255,255,255,.5)';
      ctx.font = '600 '+Math.round(H*0.07)+'px '+F_SANS;
      ctx.fillText('VS', W/2, cy);
    }

    /* Etiqueta de competición y fase. */
    var etq = (p.fase || ('JORNADA '+(p.jornada||'?'))).toUpperCase();
    ctx.textAlign = 'left';
    var alto = Math.round(H*0.055);
    var anchoP = pastilla(ctx, 0, 0, etq, '#fff', 'rgba(255,81,0,.9)', alto);
    ctx.clearRect(0,0,0,0);
    marcoPastilla(ctx, (W-anchoP)/2, H*0.13, etq, alto);

    /* Goleadores, si caben. */
    var ev = C.parseDetalles(p.detalles);
    var goles = ev.local.filter(esGol).concat(ev.visitante.filter(esGol));
    if(goles.length && H>=675){
      ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.62)';
      ctx.font = '500 '+Math.round(H*0.028)+'px '+F_SANS;
      var texto = goles.map(function(e){ return e.nombre+' '+e.minuto+"'"; }).join('   ·   ');
      ctx.font = ajustar(ctx, texto, W*0.86, '500', Math.round(H*0.028), 10);
      ctx.fillStyle = 'rgba(255,255,255,.62)';
      ctx.fillText(texto, W/2, H*0.80);
    }
    firma(ctx,W,H);
  });
}
function marcoPastilla(ctx,x,y,texto,alto){
  pastilla(ctx,x,y,texto,'#fff','rgba(255,81,0,.9)',alto);
}
function esGol(e){ return e.tipo==='gol'; }

function dibujarClasificacion(ctx,W,H,div){
  marco(ctx,W,H,'#FF5100');
  var ord = C.clasificacion(div);
  ctx.textAlign = 'center'; ctx.fillStyle = '#EDEDED';
  ctx.font = '700 '+Math.round(H*0.042)+'px '+F_SANS;
  ctx.fillText(div==='SUPERLIGA'?'SUPERLIGA FRONTIER':'ASCENSO FRONTIER', W/2, H*0.10);
  ctx.font = '500 '+Math.round(H*0.018)+'px '+F_MONO;
  ctx.fillStyle = 'rgba(255,255,255,.45)';
  ctx.fillText('TEMPORADA '+(d().config.temporada||'?')+' · JORNADA '+(d().config.jornada_actual||'?'), W/2, H*0.135);

  var top = ord.slice(0, Math.min(ord.length, H>1400?14:10));
  var y0 = H*0.19, alto = (H*0.72)/top.length;
  return Promise.all(top.map(function(e){ return cargarImg(e.escudo); })).then(function(imgs){
    top.forEach(function(e,i){
      var y = y0 + i*alto;
      if(i%2===0){ ctx.fillStyle = 'rgba(255,255,255,.03)'; rr(ctx, W*0.06, y, W*0.88, alto*0.9, 8); ctx.fill(); }
      ctx.textAlign = 'right'; ctx.fillStyle = 'rgba(255,255,255,.4)';
      ctx.font = '500 '+Math.round(alto*0.34)+'px '+F_MONO;
      ctx.fillText(String(i+1), W*0.115, y+alto*0.58);
      escudoEn(ctx, imgs[i], e, W*0.175, y+alto*0.45, alto*0.6);
      ctx.textAlign = 'left'; ctx.fillStyle = '#EDEDED';
      ctx.font = ajustar(ctx, e.nombre, W*0.5, '600', Math.round(alto*0.36), 10);
      ctx.fillText(e.nombre, W*0.235, y+alto*0.58);
      ctx.textAlign = 'right'; ctx.fillStyle = '#fff';
      ctx.font = '700 '+Math.round(alto*0.40)+'px '+F_MONO;
      ctx.fillText(String(e.pts||0), W*0.92, y+alto*0.58);
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
  var cruces = ms.filter(function(p){ return p.fase===fase; }).slice(0,8);

  ctx.textAlign = 'center';
  ctx.fillStyle = '#EDEDED';
  ctx.font = '700 '+Math.round(H*0.045)+'px '+F_SANS;
  ctx.fillText('COPA FÚTBOL FRONTIER', W/2, H*0.10);
  ctx.fillStyle = '#FF7A38';
  ctx.font = '600 '+Math.round(H*0.028)+'px '+F_MONO;
  ctx.fillText(String(fase||'SORTEO').toUpperCase(), W/2, H*0.145);

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
    cruces.forEach(function(p, i){
      var y = y0 + i*alto, cy = y + alto*0.42;
      ctx.fillStyle = 'rgba(255,255,255,.03)';
      rr(ctx, W*0.07, y, W*0.86, alto*0.82, 10); ctx.fill();
      var L = C.resolveSide(p,'local'), V = C.resolveSide(p,'visitante');
      var tam = Math.min(alto*0.5, W*0.07);
      escudoEn(ctx, imgs[i*2], C.equipo(L.n), W*0.16, cy, tam);
      escudoEn(ctx, imgs[i*2+1], C.equipo(V.n), W*0.84, cy, tam);
      ctx.fillStyle = '#EDEDED'; ctx.textAlign = 'left';
      ctx.font = ajustar(ctx, L.n||'Por definir', W*0.26, '600', Math.round(alto*0.26), 10);
      ctx.fillText(L.n||'Por definir', W*0.23, cy+alto*0.09);
      ctx.textAlign = 'right';
      ctx.font = ajustar(ctx, V.n||'Por definir', W*0.26, '600', Math.round(alto*0.26), 10);
      ctx.fillText(V.n||'Por definir', W*0.77, cy+alto*0.09);
      ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.28)';
      ctx.font = '600 '+Math.round(alto*0.24)+'px '+F_SANS;
      ctx.fillText('VS', W/2, cy+alto*0.09);
    });
    firma(ctx,W,H);
  });
}

/* --- Ficha de fichaje -------------------------------------------------- */
function dibujarFichaje(ctx,W,H,j,e){
  marco(ctx,W,H, lavado(e,'#FF5100'));
  return Promise.all([cargarImg(j&&j.foto), cargarImg(e&&e.escudo)]).then(function(imgs){
    ctx.textAlign = 'center';
    ctx.fillStyle = '#FF7A38';
    ctx.font = '600 '+Math.round(H*0.028)+'px '+F_MONO;
    ctx.fillText('FICHAJE OFICIAL', W/2, H*0.11);

    var rad = Math.min(W,H)*0.17, cx = W/2, cy = H*0.36;
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
    ctx.font = ajustar(ctx, j.nombre||'', W*0.82, '700', Math.round(H*0.058), 16);
    ctx.fillText(j.nombre||'', cx, cy+rad+H*0.095);

    ctx.fillStyle = 'rgba(255,255,255,.5)';
    ctx.font = '500 '+Math.round(H*0.026)+'px '+F_MONO;
    ctx.fillText([j.posicion||'', j.dorsal?('DORSAL '+j.dorsal):'', C.afName(j.afinidad).toUpperCase()]
      .filter(Boolean).join('  ·  '), cx, cy+rad+H*0.135);

    if(e){
      escudoEn(ctx, imgs[1], e, cx, cy+rad+H*0.225, H*0.10);
      ctx.fillStyle = '#EDEDED';
      ctx.font = ajustar(ctx, e.nombre, W*0.7, '600', Math.round(H*0.038), 12);
      ctx.fillText(e.nombre, cx, cy+rad+H*0.30);
    }
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

/* --------------------------------------------------------------------------
   RENDER Y DESCARGA
   -------------------------------------------------------------------------- */
function dibujar(){
  var dim = FORMATOS[sel.formato];
  var cv = document.getElementById('redes-lienzo');
  if(!cv) return Promise.resolve();
  cv.width = dim[0]; cv.height = dim[1];
  var ctx = cv.getContext('2d');
  return fuentes().then(function(){
    if(sel.plantilla==='clasificacion') return dibujarClasificacion(ctx, dim[0], dim[1], sel.comp==='ascenso'?'ASCENSO':'SUPERLIGA');
    if(sel.plantilla==='sorteo') return dibujarSorteo(ctx, dim[0], dim[1]);
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
             ['clasificacion','Clasificación'],['sorteo','Cartel de sorteo de Copa'],
             ['mvp','MVP / goleador'],['fichaje','Ficha de fichaje']]
            .map(function(t){ return '<option value="'+t[0]+'"'+(sel.plantilla===t[0]?' selected':'')+'>'+t[1]+'</option>'; }).join('')+'</select>')+
          U.campo('Formato', '<select class="inp" data-c="redes:formato">'+
            Object.keys(FORMATOS).map(function(f){
              return '<option value="'+f+'"'+(sel.formato===f?' selected':'')+'>'+f+' · '+FORMATOS[f].join('×')+'</option>'; }).join('')+'</select>')+
        '</div>'+
        (sel.plantilla==='resultado'||sel.plantilla==='previa'||sel.plantilla==='clasificacion'
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
