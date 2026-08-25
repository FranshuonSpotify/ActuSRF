# Dos parches propuestos para `app.js`

**No aplicados.** `CLAUDE.md` §7 pide confirmación explícita antes de tocar la
web pública, así que quedan aquí escritos para que Alejandro decida.

Los dos son **aditivos y con vuelta atrás**: si el dato nuevo no está en el
archivo, `app.js` se comporta exactamente igual que hoy. Ninguno cambia nada
para un `datos_oficiales.json` que no los use.

---

## Parche 1 — Que el play-off real mande sobre el cuadro calculado

### El problema

`renderPlayoff()` construye el cuadro **entero desde la clasificación**:

```js
var ord = orderStandings(bd.equipos.filter(...));
...side(ord[4],'5º')+side(ord[5],'6º')...   // 5º vs 6º
...side(ord[3],'4º')+ 'Ganador 5º-6º' ...   // 4º vs ganador
...side(ord[0],'1º')+ 'Ganador Play In' ...
...tbd+tbd...                                // la final, siempre vacía
```

Nunca mira `bd.partidos_liga`. Así que aunque se marque un partido como
`PLAY IN` o `FINAL` desde el gestor, ese widget **sigue enseñando el cuadro
calculado y la final siempre en blanco**. Los resultados reales del play-off
no aparecen en ninguna parte del cuadro.

### El parche

Reemplazar `renderPlayoff()` (línea ~267) por:

```js
/* FASES DEL PLAY-OFF, en el orden en que se juegan. Coinciden con las
   etiquetas que ya usaban las rondas calculadas. */
var FASES_PO = ['PARTIDO POR EL PLAY IN','PLAY IN','SEMIFINALES','FINAL'];

function renderPlayoff(){
  var el=$('bracket-playoff'); if(!el) return;

  /* Si hay partidos de play-off cargados, mandan ellos: son lo que ha
     pasado de verdad, frente a un cuadro deducido de la clasificación. */
  var reales=bd.partidos_liga.filter(function(p){ return FASES_PO.indexOf(p.fase)>=0; });
  if(reales.length) return renderPlayoffReal(el, reales);

  /* Sin partidos cargados se sigue dibujando el cuadro previsto a partir de
     la clasificación, exactamente como antes. */
  var ord=orderStandings(bd.equipos.filter(function(e){ return e.division==='SUPERLIGA'&&!e.archivado; }));
  if(ord.length<6){ var w=$('playoff-wrap'); if(w) w.style.display='none'; return; }
  function side(e,sub){
    if(!e) return '<div class="br-side br-tbd"><span class="nm">'+T('br.tbd','Por definir')+'</span></div>';
    return '<div class="br-side">'+crest(e,18)+'<span class="nm">'+esc(X(e.nombre))+'</span><span class="sc" style="color:var(--ink-5);font-size:.6875rem">'+sub+'</span></div>';
  }
  var tbd='<div class="br-side br-tbd"><span class="nm">'+T('br.tbd','Por definir')+'</span></div>';
  el.innerHTML=
    '<div class="br-round"><div class="br-label">'+T('zone.playin.part','Partido por el Play In')+'</div><div class="br-match">'+side(ord[4],'5º')+side(ord[5],'6º')+'</div></div>'+
    '<div class="br-round"><div class="br-label">'+T('zone.playin','Play In')+'</div><div class="br-match">'+side(ord[3],'4º')+'<div class="br-side br-tbd"><span class="nm">'+T('br.ganador','Ganador')+' 5º-6º</span></div></div></div>'+
    '<div class="br-round"><div class="br-label">'+T('br.semis','Semifinales')+'</div><div class="br-match">'+side(ord[0],'1º')+'<div class="br-side br-tbd"><span class="nm">'+T('br.ganador','Ganador')+' '+T('zone.playin','Play In')+'</span></div></div><div class="br-match">'+side(ord[1],'2º')+side(ord[2],'3º')+'</div></div>'+
    '<div class="br-round"><div class="br-label">'+T('br.final','Final')+'</div><div class="br-match">'+tbd+tbd+'</div></div>';
}

/* Cuadro dibujado desde los partidos cargados. Reutiliza las mismas clases
   que el cuadro de Copa, así que no hace falta CSS nuevo. */
function renderPlayoffReal(el, ms){
  var w=$('playoff-wrap'); if(w) w.style.display='';
  var h='';
  FASES_PO.forEach(function(f){
    var ronda=ms.filter(function(p){ return p.fase===f; });
    if(!ronda.length) return;
    h+='<div class="br-round"><div class="br-label">'+esc(f)+'</div>';
    ronda.forEach(function(p){
      var i=bd.partidos_liga.indexOf(p), fin=isFin(p);
      var a=gl(p), b=gv(p);
      function lado(nombre, gol, gana){
        if(!nombre) return '<div class="br-side br-tbd"><span class="nm">'+T('br.tbd','Por definir')+'</span></div>';
        var t=team(nombre);
        return '<div class="br-side '+(fin?(gana?'br-win':'br-lose'):'')+'">'+
          crest(t,18)+'<span class="nm">'+esc(X(nombre))+'</span>'+
          (fin?'<span class="sc">'+gol+'</span>':'')+'</div>';
      }
      /* data-comp/data-idx: la ficha del partido ya se abre por delegación
         con esos dos atributos, igual que en Resultados y en Copa. */
      h+='<div class="br-match" data-comp="liga" data-idx="'+i+'">'+
        lado(p.local,a,fin&&a>b)+lado(p.visitante,b,fin&&b>a)+'</div>';
    });
    h+='</div>';
  });
  el.innerHTML=h;
}
```

### Qué cambia

- **Con play-off cargado**: el widget enseña los cruces reales con su marcador,
  el ganador resaltado, y cada cruce abre la ficha del partido al pulsarlo.
- **Sin play-off cargado**: byte por byte lo mismo que hoy.

---

## Parche 2 — Campeones apuntados a mano en el palmarés

### El problema

`palmares()` deduce el campeón de cada división como **el que más puntos
tiene**:

```js
function champ(div){
  var l=(t.equipos||[]).filter(...).sort(function(a,b){ return (b.pts||0)-(a.pts||0)||... });
  return l[0]||null;
}
```

En una liga con play-off eso es **falso**: el campeón es quien gana la final,
no el primero de la fase regular. Y no hay forma de corregirlo, porque el dato
no existe en ninguna parte del archivo.

### El parche

En `palmares()` (línea ~943), añadir al principio:

```js
function palmares(idx){
  var t=(bd.historial_temporadas||[])[idx];
  if(!t) return null;

  /* Campeones apuntados a mano. Con play-off, el campeón no es el primero de
     la fase regular, así que si están escritos mandan ellos. */
  var COMPS=[
    {clave:'SUPERLIGA', comp:'Superliga Frontier', cls:'badge-superliga'},
    {clave:'ASCENSO',   comp:'Ascenso Frontier',   cls:'badge-ascenso'},
    {clave:'COPA',      comp:'Copa Fútbol Frontier', cls:'badge-copa'}
  ];
  if(Array.isArray(t.campeones) && t.campeones.length){
    var out=[];
    COMPS.forEach(function(c){
      var g=t.campeones.filter(function(x){ return x.comp===c.clave; })[0];
      if(!g) return;
      var e=(t.equipos||[]).filter(function(x){ return x.id===g.equipo_id||x.nombre===g.equipo; })[0];
      if(e) out.push({comp:c.comp, cls:c.cls, e:e, marcador:g.marcador||''});
    });
    if(out.length) return out;
  }

  /* … el resto de la función, sin tocar … */
```

### Qué cambia

- **Con campeones apuntados**: el palmarés enseña a quien ganó de verdad.
- **Sin ellos**: se deduce por puntos, exactamente como hoy.

---

## Cómo aplicarlos

Los dos van sobre `propuesta-web/_fuente/app.js`, y después hay que
regenerar el `index.html`:

```bash
cd propuesta-web && node _fuente/build.js
```

El gestor **ya escribe los dos datos**: las fases de play-off en
`partidos_liga[].fase` y los campeones en `historial_temporadas[].campeones`.
Sin los parches se guardan igual y no molestan a nadie; simplemente la web no
los mira.
