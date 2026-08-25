/* Comprobación de core.js contra los datos reales.
   Uso:  node propuesta-web/gestor/test-core.js
   No es un framework de tests: es el mínimo que falla si core.js deja de
   reproducir el comportamiento de la web pública. */
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');

global.window = {};
require('./js/core.js');
const C = global.window.SFG.core;
const setD = global.window.SFG.setD;

const RAIZ = path.join(__dirname, '..', '..');
const d = JSON.parse(fs.readFileSync(path.join(RAIZ, 'datos_oficiales.json'), 'utf8'));
C.completarEsquema(d);
setD(d);

let n = 0;
const ok = (m) => { n++; console.log('  ok  ' + m); };

/* -- 1. Esquema del archivo real -------------------------------------- */
{
  const v = C.validarEsquema(d);
  assert.deepStrictEqual(v.err, [], 'el archivo real no debería dar errores de esquema');
  ok('validarEsquema: 0 errores sobre el archivo real (' + v.avi.length + ' avisos)');
}

/* -- 2. detalles: ida y vuelta ----------------------------------------
   Serializar lo que se ha parseado tiene que devolver la misma cadena.
   Se compara contra la forma normalizada, porque el archivo trae espaciados
   irregulares que el parser absorbe a propósito. */
{
  const todos = [...d.partidos_liga, ...d.partidos_ascenso, ...d.partidos_copa];
  let comprobados = 0;
  for (const p of todos) {
    const ev = C.parseDetalles(p.detalles);
    const ida = C.serializarDetalles(ev);
    const vuelta = C.serializarDetalles(C.parseDetalles(ida));
    assert.strictEqual(vuelta, ida, 'no es estable: ' + JSON.stringify(p.detalles));
    /* Ningún evento puede perderse por el camino. */
    const antes = (p.detalles || '').split(':').length;
    const despues = ida.split(':').length;
    if (antes > 1) assert.strictEqual(despues, antes, 'eventos perdidos en ' + JSON.stringify(p.detalles));
    comprobados++;
  }
  ok('parseDetalles/serializarDetalles: estable y sin pérdidas en ' + comprobados + ' partidos');
}

/* -- 2b. La tanda de penaltis sobrevive a una edición de eventos -------
   Es el unico dato del partido que vive suelto dentro de `detalles`, y es lo
   que winnerOf() necesita para resolver una eliminatoria empatada. */
{
  const orig = 'gol:Ana:12 / gol:Bea:80 PEN: 4-3';
  const ev = C.parseDetalles(orig);
  assert.deepStrictEqual(ev.pen, { l: 4, v: 3 }, 'la tanda se extrae');
  assert.strictEqual(ev.local.length, 1, 'el PEN no se cuela como evento del local');
  assert.strictEqual(ev.visitante.length, 1, 'ni del visitante');

  /* Editar los eventos no puede tirar la tanda por el camino. */
  ev.visitante.push({ tipo: 'amarilla', nombre: 'Cris', minuto: '85' });
  const nuevo = C.serializarDetalles(ev);
  assert.deepStrictEqual(C.parseDetalles(nuevo).pen, { l: 4, v: 3 }, 'la tanda se conserva tras editar');

  const partido = { estado: 'FINALIZADO', local: 'A', visitante: 'B', goles_l: 1, goles_v: 1, detalles: nuevo };
  assert.strictEqual(C.winnerOf(partido), 'A', 'winnerOf sigue resolviendo por penaltis');

  /* Y el ranking de goleadores no cuenta la tanda como goles. */
  const sc = C.calcScorers([partido]);
  assert.strictEqual(sc.reduce((s, r) => s + r.goles, 0), 2, 'los penaltis de la tanda no son goles del partido');

  /* Sin tanda no se inventa ninguna. */
  assert.strictEqual(C.parseDetalles(' / gol:X:5').pen, null);
  assert.ok(!/PEN/.test(C.serializarDetalles(C.parseDetalles(' / gol:X:5'))));
  ok('penaltis: se extraen, sobreviven a la edicion, no cuentan como goles');
}

/* -- 3. Textos derivados contra producción ----------------------------
   La prueba de fuego del formato: regenerar goleadores_texto y compararlo
   con el que ya está guardado en los partidos que lo traen. Si el formato
   estuviera mal, aquí saltaría. */
{
  const conTexto = [...d.partidos_liga, ...d.partidos_ascenso, ...d.partidos_copa]
    .filter(p => p.goleadores_texto !== undefined);
  assert.ok(conTexto.length > 50, 'esperaba decenas de partidos con textos derivados');
  for (const p of conTexto) {
    const t = C.textosDerivados(C.parseDetalles(p.detalles));
    assert.strictEqual(t.goleadores_texto, p.goleadores_texto, 'goleadores_texto en ' + p.local + '-' + p.visitante);
    assert.strictEqual(t.goleadores_local_texto, p.goleadores_local_texto, 'local en ' + p.local + '-' + p.visitante);
    assert.strictEqual(t.goleadores_visitante_texto, p.goleadores_visitante_texto, 'visitante en ' + p.local + '-' + p.visitante);
  }
  ok('textosDerivados: reproduce los 3 textos de los ' + conTexto.length + ' partidos que ya los traían');
}

/* -- 4. orderStandings: la cadena de desempates ------------------------ */
{
  const base = { nombre: 'Z', pts: 10, gf: 5, gc: 5, g: 1, e: 1, p: 1, pj: 3 };
  const con = (o) => Object.assign({}, base, o);
  const primero = (a, b) => C.orderStandings([a, b])[0].nombre;

  assert.strictEqual(primero(con({ nombre: 'A', pts: 11 }), con({ nombre: 'B' })), 'A', 'puntos');
  assert.strictEqual(primero(con({ nombre: 'A', gf: 9, gc: 5 }), con({ nombre: 'B' })), 'A', 'diferencia de goles');
  assert.strictEqual(primero(con({ nombre: 'A', gf: 9, gc: 9 }), con({ nombre: 'B', gf: 4, gc: 4 })), 'A', 'goles a favor a igual diferencia');
  /* El 4.o criterio de app.js (goles en contra) es inalcanzable por aritmetica:
     si dos equipos empatan en diferencia Y en goles a favor, sus goles en
     contra son iguales por fuerza. Se conserva en core.js porque esta en la
     web, pero no hay caso que lo ejercite. */
  assert.strictEqual(primero(con({ nombre: 'A', g: 2 }), con({ nombre: 'B', g: 1 })), 'A', 'victorias');
  assert.strictEqual(primero(con({ nombre: 'A', g: 1, e: 2 }), con({ nombre: 'B', g: 1, e: 1 })), 'A', 'empates');
  assert.strictEqual(primero(con({ nombre: 'A', p: 0 }), con({ nombre: 'B', p: 1 })), 'A', 'derrotas, menos es mejor');
  assert.strictEqual(primero(con({ nombre: 'A', pj: 2 }), con({ nombre: 'B', pj: 3 })), 'A', 'partidos jugados (8.o criterio, el que CLAUDE.md omitia)');
  assert.strictEqual(primero(con({ nombre: 'Alpino' }), con({ nombre: 'Zanark' })), 'Alpino', 'alfabetico');
  ok('orderStandings: los 9 criterios en orden');
}

/* -- 5. Copa: ganador y resolución en cascada -------------------------- */
{
  assert.strictEqual(C.winnerOf({ estado: 'FINALIZADO', local: 'A', visitante: 'B', goles_l: 2, goles_v: 1 }), 'A');
  assert.strictEqual(C.winnerOf({ estado: 'FINALIZADO', local: 'A', visitante: 'B', goles_l: 0, goles_v: 3 }), 'B');
  assert.strictEqual(C.winnerOf({ estado: 'PENDIENTE', local: 'A', visitante: 'B', goles_l: 2, goles_v: 1 }), null, 'un pendiente no tiene ganador');
  assert.strictEqual(C.winnerOf({ estado: 'FINALIZADO', local: 'A', visitante: 'B', goles_l: 1, goles_v: 1 }), null, 'empate sin penaltis no resuelve');
  assert.strictEqual(C.winnerOf({ estado: 'FINALIZADO', local: 'A', visitante: 'B', goles_l: 1, goles_v: 1, detalles: 'PEN: 4-2' }), 'A', 'penaltis en detalles');
  assert.strictEqual(C.winnerOf({ estado: 'FINALIZADO', local: 'A', visitante: 'B', goles_l: 1, goles_v: 1, detalles: ' / gol:X:9 PEN 2-5' }), 'B', 'penaltis sin dos puntos');

  const previo = d.partidos_copa.findIndex(p => C.isFin(p) && C.winnerOf(p));
  assert.ok(previo >= 0, 'hace falta al menos un cruce resuelto en los datos reales');
  const ganador = C.winnerOf(d.partidos_copa[previo]);
  const falso = { local: '', visitante: '', origen_local: previo, origen_visitante: null };
  assert.strictEqual(C.resolveSide(falso, 'local').n, ganador, 'resolveSide arrastra el ganador de la ronda previa');
  assert.strictEqual(C.resolveSide(falso, 'local').pend, false);
  assert.strictEqual(C.resolveSide(falso, 'visitante').n, '', 'sin origen se queda con el nombre guardado');

  const pendIdx = d.partidos_copa.findIndex(p => !C.isFin(p));
  if (pendIdx >= 0) {
    const r = C.resolveSide({ local: '', visitante: '', origen_local: pendIdx, origen_visitante: null }, 'local');
    assert.strictEqual(r.pend, true, 'sin ganador aun, se muestran los dos candidatos');
    assert.ok(r.n.includes(' / '), 'formato "ABR / ABR"');
  }
  ok('winnerOf/resolveSide: goles, penaltis, pendientes y cascada');
}

/* -- 6. resolveSide NO escribe en los datos ---------------------------- */
{
  const antes = JSON.stringify(d.partidos_copa);
  d.partidos_copa.forEach(p => { C.resolveSide(p, 'local'); C.resolveSide(p, 'visitante'); });
  assert.strictEqual(JSON.stringify(d.partidos_copa), antes,
    'resolveSide debe ser de solo lectura (el prototipo anterior volcaba el ganador en p.local)');
  ok('resolveSide: no muta los datos');
}

/* -- 7. Goleadores ----------------------------------------------------- */
{
  const liga = C.calcScorers(d.partidos_liga.filter(C.isFin));
  assert.ok(liga.length > 0, 'la liga tiene goleadores');
  /* Orden: goles descendente, alfabetico a igualdad. */
  for (let i = 1; i < liga.length; i++) {
    const a = liga[i - 1], b = liga[i];
    assert.ok(a.goles > b.goles || (a.goles === b.goles && a.nombre.localeCompare(b.nombre, 'es') <= 0),
      'orden roto entre ' + a.nombre + ' y ' + b.nombre);
  }
  /* El total de goles del ranking tiene que cuadrar con los goles marcados. */
  const golesEnDetalles = d.partidos_liga.filter(C.isFin)
    .reduce((s, p) => s + (p.detalles || '').split('gol:').length - 1, 0);
  const golesEnRanking = liga.reduce((s, r) => s + r.goles, 0);
  assert.strictEqual(golesEnRanking, golesEnDetalles, 'el ranking pierde o duplica goles');
  ok('calcScorers: ' + liga.length + ' goleadores, ' + golesEnRanking + ' goles, orden y totales correctos');
}

/* -- 8. Normalización: sincroniza sin destruir ------------------------- */
{
  const reg = [];
  /* Sólo el alias heredado: se recupera el canónico. */
  const j1 = { nombre: 'Solo alias', tarjetasAmarillas: 3, tarjetasRojas: 1 };
  C.normalizar({ equipos: [{ jugadores: [j1] }] });
  assert.strictEqual(j1.amarillas, 3);
  assert.strictEqual(j1.rojas, 1);
  assert.strictEqual(j1.tarjetasAmarillas, 3, 'el alias se conserva, no se borra');

  /* Los dos, en conflicto: gana el canónico y queda registrado. */
  const j2 = { nombre: 'Conflicto', amarillas: 2, tarjetasAmarillas: 7, rojas: 0, tarjetasRojas: 0 };
  const r2 = C.normalizar({ equipos: [{ jugadores: [j2] }] });
  assert.strictEqual(j2.amarillas, 2);
  assert.strictEqual(j2.tarjetasAmarillas, 2, 'el alias se alinea con el canonico');
  assert.ok(r2.some(x => x.includes('Conflicto')), 'el conflicto se registra para poder avisar');

  /* Sólo el canónico: no se inventa el alias. */
  const j3 = { nombre: 'Solo canonico', amarillas: 1, rojas: 0 };
  C.normalizar({ equipos: [{ jugadores: [j3] }] });
  assert.ok(!('tarjetasAmarillas' in j3), 'no se anaden campos que no estaban');

  /* Partidos: el alias golesl sólo se toca si ya existía. */
  const p1 = { local: 'A', visitante: 'B', goles_l: 2, goles_v: 1, detalles: ' / ' };
  const p2 = { local: 'A', visitante: 'B', golesl: 4, golesv: 0, detalles: ' / ' };
  C.normalizar({ partidos_liga: [p1, p2] });
  assert.ok(!('golesl' in p1), 'no se anade el alias donde no estaba');
  assert.strictEqual(p2.goles_l, 4, 'el canonico se recupera desde el alias');
  assert.strictEqual(p2.golesl, 4);
  ok('normalizar: alias sincronizados, nada inventado, nada perdido');
}

/* -- 9. Normalizar los datos reales no cambia ningún SIGNIFICADO -------
   Sí cambia representación: hay 2 partidos con goles_l guardado como texto
   ("3") y 3 de Ascenso sin goles_l, sólo con el alias golesl. Normalizar
   arregla ambos. Lo que no puede cambiar es el valor que la web lee, ni un
   dígito de la clasificación, ni un evento de ningún partido. */
{
  const copia = JSON.parse(JSON.stringify(d));
  const reg = C.normalizar(copia);
  const num = (v) => (v == null ? 0 : Number(v));
  const significado = (x) => JSON.stringify({
    tabla: x.equipos.map(e => [e.nombre, e.pj, e.g, e.e, e.p, e.gf, e.gc, e.pts]),
    marcadores: [...x.partidos_liga, ...x.partidos_ascenso, ...x.partidos_copa]
      .map(p => [p.local, num(C.gl(p)), num(C.gv(p)), p.visitante, p.estado, p.jornada, p.fase]),
    detalles: [...x.partidos_liga, ...x.partidos_ascenso, ...x.partidos_copa].map(p => p.detalles),
    plantillas: x.equipos.map(e => (e.jugadores || []).map(j => [j.nombre, j.dorsal, j.posicion, j.goles, j.asistencias]))
  });
  assert.strictEqual(significado(copia), significado(d), 'normalizar ha cambiado un dato de competicion');

  /* Y los cambios de representación son exactamente los esperados: sólo
     tipos y alias, nunca un campo nuevo que no fuera derivable. */
  const cambios = [];
  ['partidos_liga', 'partidos_ascenso', 'partidos_copa'].forEach(k => {
    d[k].forEach((p, i) => {
      ['goles_l', 'goles_v', 'golesl', 'golesv'].forEach(f => {
        if (JSON.stringify(p[f]) !== JSON.stringify(copia[k][i][f])) cambios.push(k + '#' + i + '.' + f);
      });
    });
  });
  assert.ok(cambios.length > 0 && cambios.length < 20, 'esperaba un punado de correcciones de tipo, hubo ' + cambios.length);
  cambios.forEach(c => assert.ok(/goles_?[lv]$/.test(c), 'cambio inesperado en ' + c));

  const dosVeces = JSON.parse(JSON.stringify(copia));
  C.normalizar(dosVeces);
  assert.strictEqual(JSON.stringify(dosVeces), JSON.stringify(copia), 'normalizar no es idempotente');
  ok('normalizar datos reales: idempotente, ' + cambios.length + ' correcciones de tipo/alias, 0 cambios de significado (' + reg.length + ' notas)');
}

/* -- 10. Integridad del archivo real ----------------------------------- */
{
  const v = C.validarIntegridad(d);
  assert.deepStrictEqual(v.err.map(e => e.m), [], 'el archivo real no deberia tener errores criticos');
  ok('validarIntegridad: 0 criticos sobre el archivo real (' + v.avi.length + ' avisos)');
}

/* -- 11. El detector encuentra lo que se rompe a propósito ------------- */
{
  /* Se comparan los mensajes sin tildes: lo que se comprueba es que el fallo
     se detecta, no cómo está redactado el aviso. */
  const roto = (mut) => {
    const c = JSON.parse(JSON.stringify(d));
    mut(c);
    return C.validarIntegridad(c).err.map(e => e.m).join(' | ')
      .normalize('NFD').replace(/[̀-ͯ]/g, '');
  };
  assert.ok(roto(c => { c.partidos_liga[0].local = 'Equipo Inventado'; }).includes('no existe'), 'equipo inexistente');
  assert.ok(roto(c => { c.equipos[1].id = c.equipos[0].id; }).includes('duplicado'), 'id duplicado');
  assert.ok(roto(c => { c.equipos[0].division = 'TERCERA'; }).includes('SUPERLIGA'), 'division invalida');
  assert.ok(roto(c => { c.partidos_copa[0].origen_local = 999; }).includes('no es un cruce valido'), 'origen fuera de rango');
  assert.ok(roto(c => { c.partidos_copa[0].origen_local = 0; }).includes('a si mismo'), 'origen circular directo');
  assert.ok(roto(c => {
    c.partidos_copa[0].origen_local = 1;
    c.partidos_copa[1].origen_local = 0;
  }).includes('ciclo'), 'ciclo indirecto en el cuadro');
  assert.ok(roto(c => { c.partidos_liga[0].estado = 'JUGADO'; }).includes('estado'), 'estado desconocido');
  assert.ok(roto(c => { c.partidos_liga[0].visitante = c.partidos_liga[0].local; }).includes('contra si mismo'), 'equipo contra si mismo');
  assert.ok(roto(c => { c.equipos[0].jugadores[0].historial = [{ equipo_id: 'no_existe' }]; }).includes('no existe'), 'equipo_id huerfano');
  ok('validarIntegridad: detecta los 9 fallos introducidos a proposito');
}

/* -- 12. Tabla calculada vs. guardada ---------------------------------- */
{
  const des = C.desajustesTabla();
  const t = C.tablaCalculada();
  /* Invariante: los goles a favor totales igualan a los goles en contra
     totales, porque cada gol lo marca alguien y lo encaja alguien. */
  const gf = Object.values(t).reduce((s, x) => s + x.gf, 0);
  const gc = Object.values(t).reduce((s, x) => s + x.gc, 0);
  assert.strictEqual(gf, gc, 'los goles a favor y en contra no cuadran');
  const pjTotal = Object.values(t).reduce((s, x) => s + x.pj, 0);
  const finalizados = [...d.partidos_liga, ...d.partidos_ascenso].filter(C.isFin).length;
  assert.strictEqual(pjTotal, finalizados * 2, 'cada partido finalizado suma 2 participaciones');
  ok('tablaCalculada: invariantes correctas (' + finalizados + ' partidos, ' + des.length + ' desajustes con lo guardado)');
}

/* -- 13. Fases de Liga: una eliminatoria no reparte puntos -------------
   Es la regla que hace que marcar un partido como PLAY OFF sea seguro. */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c);
  setD(c);
  const antes = C.tablaCalculada();
  /* Se elige uno que ganara el local, para poder comprobar que pierde los
     tres puntos: con una derrota no habria nada que restar. */
  const reg = c.partidos_liga.find(p => C.isFin(p) && C.gl(p) > C.gv(p));
  const local = reg.local;

  /* El mismo partido, ya finalizado, pasa a ser eliminatoria. */
  reg.fase = 'SEMIFINALES';
  const despues = C.tablaCalculada();
  assert.strictEqual(despues[local].pj, antes[local].pj - 1, 'deja de contar como partido jugado');
  assert.strictEqual(despues[local].pts, antes[local].pts - 3, 'deja de repartir los 3 puntos de la victoria');
  assert.strictEqual(despues[local].g, antes[local].g - 1, 'deja de contar como victoria');
  assert.strictEqual(despues[reg.visitante].p, antes[reg.visitante].p - 1, 'y como derrota del rival');
  assert.strictEqual(C.esRegular(reg), false);
  reg.fase = '';
  assert.deepStrictEqual(C.tablaCalculada()[local], antes[local], 'al quitar la fase vuelve a contar');
  setD(d);
  ok('un partido de Liga con fase no suma a la clasificacion regular');
}

/* -- 14. Fase de Liga sin jornada: la web no lo mostraria -------------- */
{
  const roto = (mut) => {
    const c = JSON.parse(JSON.stringify(d)); C.completarEsquema(c); mut(c);
    const v = C.validarIntegridad(c);
    return { err: v.err.map(e => e.m).join(' | '), avi: v.avi.map(e => e.m).join(' | ') };
  };
  assert.ok(roto(c => { c.partidos_liga[0].fase = 'FINAL'; c.partidos_liga[0].jornada = ''; }).err.includes('sin jornada'),
    'una eliminatoria sin jornada es error: initJornadas() la descartaria');
  assert.ok(!roto(c => { c.partidos_liga[0].fase = 'FINAL'; }).err.includes('sin jornada'),
    'con jornada no hay error');
  assert.ok(roto(c => { c.partidos_liga[0].fase = 'OCTAVOS DE ALGO'; }).avi.includes('no es una de las conocidas'),
    'una fase inventada es aviso');
  ok('validacion de fases de Liga: sin jornada bloquea, fase rara avisa');
}

/* -- 15. Grupos de Copa ------------------------------------------------ */
{
  const roto = (mut) => {
    const c = JSON.parse(JSON.stringify(d)); C.completarEsquema(c); mut(c);
    return C.validarIntegridad(c).err.map(e => e.m).join(' | ');
  };
  const eqA = d.equipos[0].nombre, eqB = d.equipos[1].nombre;
  assert.ok(roto(c => { c.config.grupos_copa = { A: ['Equipo Fantasma'] }; }).includes('no existe'), 'equipo inexistente en un grupo');
  assert.ok(roto(c => { c.config.grupos_copa = { A: [eqA], B: [eqA] }; }).includes('a la vez'), 'el mismo equipo en dos grupos');
  assert.strictEqual(roto(c => { c.config.grupos_copa = { A: [eqA], B: [eqB] }; }), '', 'una asignacion valida no da error');
  ok('validacion de grupos de Copa: inexistentes y duplicados');
}

/* -- 16. Formatos: describen, no mandan -------------------------------- */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c);
  assert.ok(c.config.formatos.SUPERLIGA && c.config.formatos.COPA, 'se crean con valores por defecto');
  assert.deepStrictEqual(c.config.grupos_copa, {}, 'y los grupos vacios');
  /* Idempotente: volver a completar no pisa lo que el usuario haya puesto. */
  c.config.formatos.SUPERLIGA.vueltas = 1;
  C.completarEsquema(c);
  assert.strictEqual(c.config.formatos.SUPERLIGA.vueltas, 1, 'completarEsquema no pisa lo ya configurado');
  /* Si el formato contradice lo que app.js tiene escrito a mano, se avisa. */
  c.config.formatos.SUPERLIGA.playoff = 8;
  const avi = C.validarIntegridad(c).avi.map(x => x.m).join(' | ');
  assert.ok(/no lo lee del archivo/.test(avi), 'avisa de que la web no lee el formato');
  assert.strictEqual(C.letrasGrupo(c).join(''), 'ABCD', '4 grupos -> A B C D');
  c.config.formatos.COPA.grupos = 2;
  assert.strictEqual(C.letrasGrupo(c).join(''), 'AB');
  ok('formatos: por defecto, idempotentes, y avisan de lo que la web ignora');
}

/* -- 17. Cierre de temporada ------------------------------------------- */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);

  /* Un jugador con goles esta temporada y una etapa abierta en su club. */
  const eq = c.equipos.find(e => (e.jugadores || []).some(j => (j.goles || 0) > 0 && (j.historial || []).length));
  assert.ok(eq, 'hace falta un jugador con goles y con historial');
  const j = eq.jugadores.find(x => (x.goles || 0) > 0 && (x.historial || []).length);
  const carreraAntes = (j.goles_totales || 0) + (j.goles || 0);
  const golesTemp = j.goles;

  const archivo = C.instantaneaTemporada(c, 'Temporada de prueba');
  c.historial_temporadas.push(archivo);
  const res = C.cerrarTemporada(c, { etiqueta: 'Temporada de prueba', vaciarCalendario: true });

  assert.strictEqual(j.goles, 0, 'la temporada se pone a cero');
  assert.strictEqual((j.goles_totales || 0) + (j.goles || 0), carreraAntes, 'la carrera no cambia al cerrar');
  const suma = (j.historial || []).reduce((s, h) => s + (h.goles || 0), 0);
  assert.strictEqual(suma, j.goles_totales,
    'goles_totales sigue siendo exactamente la suma del historial, que es lo que app.js da por hecho');
  assert.ok(golesTemp > 0 && suma >= golesTemp, 'los goles de la temporada acabaron en el historial');

  assert.ok(c.equipos.every(e => C.CAMPOS_TABLA.every(k => e[k] === 0)), 'la clasificacion queda a cero');
  assert.strictEqual(c.partidos_liga.length, 0, 'el calendario se vacia');
  assert.strictEqual(c.config.jornada_actual, '1');
  assert.strictEqual(c.config.temporada, String(parseInt(d.config.temporada, 10) + 1), 'la temporada avanza');
  assert.ok(res.jugadores > 0 && res.partidos > 0);

  /* La copia archivada no comparte objetos con la temporada viva. */
  assert.ok(archivo.equipos.some(e => C.CAMPOS_TABLA.some(k => e[k] > 0)),
    'el archivo conserva la clasificacion aunque la viva se haya puesto a cero');

  /* Y palmares() de app.js sabra leerla. */
  const camp = C.campeones(archivo);
  assert.ok(camp.length >= 1, 'la instantanea produce al menos un campeon');
  assert.ok(camp[0].e && camp[0].e.nombre, 'con equipo identificado');
  setD(d);
  ok('cerrar temporada: carrera intacta, historial cuadrado, archivo independiente y con campeones');
}

/* -- 18. Traspasos ------------------------------------------------------
   Es la operación que más fácil desajusta el archivo: toca plantilla,
   historial y estadísticas a la vez. */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);

  const origen = c.equipos.find(e => !e.archivado && (e.jugadores || []).some(j => (j.goles || 0) > 0));
  const destino = c.equipos.find(e => !e.archivado && e.id !== origen.id);
  const j = origen.jugadores.find(x => (x.goles || 0) > 0);
  const nombre = j.nombre;
  const carreraAntes = (j.goles_totales || 0) + (j.goles || 0);
  const golesTemp = j.goles;
  const nOrigen = origen.jugadores.length, nDestino = destino.jugadores.length;

  C.traspasar(c, j, origen, destino);

  assert.strictEqual(origen.jugadores.length, nOrigen - 1, 'sale de la plantilla de origen');
  assert.strictEqual(destino.jugadores.length, nDestino + 1, 'entra en la de destino');
  assert.ok(destino.jugadores.includes(j));
  assert.strictEqual(j.titular, false, 'llega al banquillo, no al once');
  assert.strictEqual((j.goles_totales || 0) + (j.goles || 0), carreraAntes, 'la carrera no cambia con el traspaso');
  assert.strictEqual(j.goles, 0, 'la temporada empieza de cero en el club nuevo');
  assert.strictEqual((j.historial || []).reduce((s, h) => s + (h.goles || 0), 0), j.goles_totales,
    'goles_totales sigue siendo la suma del historial');

  const etapas = j.historial.filter(h => h.abierto);
  assert.strictEqual(etapas.length, 1, 'solo una etapa abierta a la vez');
  assert.strictEqual(etapas[0].equipo_id, destino.id, 'y es la del club nuevo');
  const cerrada = j.historial.filter(h => h.equipo_id === origen.id).pop();
  assert.strictEqual(cerrada.abierto, false, 'la etapa de origen queda cerrada');
  assert.ok(cerrada.goles >= golesTemp, 'los goles de la temporada se quedaron en el club donde se marcaron');

  /* El ranking de goleadores de la web no depende de este campo: se calcula
     desde los eventos de los partidos. Poner j.goles a cero no puede moverlo. */
  const antesRanking = C.calcScorers(c.partidos_liga.filter(C.isFin)).reduce((s, r) => s + r.goles, 0);
  assert.strictEqual(antesRanking, 65, 'el ranking sigue saliendo de los partidos, no de la ficha');

  /* A agente libre y de vuelta. */
  const nLibres = c.agentes_libres.length;
  C.traspasar(c, j, destino, null);
  assert.strictEqual(c.agentes_libres.length, nLibres + 1, 'pasa a agentes libres');
  assert.ok(!destino.jugadores.includes(j), 'y sale del club');
  assert.ok(j.historial.every(h => !h.abierto), 'sin club, ninguna etapa queda abierta');
  assert.ok(j.fecha_agente_libre, 'se apunta la fecha, como el resto de agentes libres');

  C.traspasar(c, j, null, origen);
  assert.strictEqual(c.agentes_libres.length, nLibres, 'vuelve a un club y sale de agentes libres');
  assert.ok(origen.jugadores.includes(j));
  assert.strictEqual((j.goles_totales || 0) + (j.goles || 0), carreraAntes, 'tres traspasos despues, la carrera sigue intacta');
  assert.strictEqual((j.historial || []).reduce((s, h) => s + (h.goles || 0), 0), j.goles_totales,
    'y el historial sigue cuadrando');

  assert.deepStrictEqual(C.validarIntegridad(c).err.map(x => x.m), [], 'el traspaso no rompe la integridad');
  assert.strictEqual(nombre, j.nombre, 'el jugador es el mismo objeto, no una copia');
  setD(d);
  ok('traspasos: club a club, a agente libre y de vuelta, con la carrera y el historial cuadrados');
}

/* -- 19. Mover equipos dentro del cuadro de Copa -----------------------
   Es lo que hace el arrastre del cuadro. Se prueba aquí porque el efecto
   importante no se ve: intercambio, ruptura de la vinculacion y rechazo de
   los movimientos que dejarian un cruce imposible. */
{
  const base = () => {
    const c = { config: { temporada: '3' }, equipos: [], partidos_copa: [
      { fase: 'RONDA 1 (PREVIA)', local: 'A', visitante: 'B', goles_l: 2, goles_v: 0, estado: 'FINALIZADO', detalles: ' / ', origen_local: null, origen_visitante: null },
      { fase: 'RONDA 2', local: 'C', visitante: '', goles_l: 0, goles_v: 0, estado: 'PENDIENTE', detalles: ' / ', origen_local: null, origen_visitante: null },
      { fase: 'RONDA 2', local: '', visitante: 'D', goles_l: 0, goles_v: 0, estado: 'PENDIENTE', detalles: ' / ', origen_local: 0, origen_visitante: null }
    ] };
    return c;
  };

  /* Hueco vacio: se mueve sin mas. */
  let c = base();
  let r = C.moverEnCuadro(c, { idx: 1, lado: 'local' }, { idx: 1, lado: 'visitante' });
  assert.deepStrictEqual(r, { movido: 'C', ocupante: '' });
  assert.strictEqual(c.partidos_copa[1].local, '');
  assert.strictEqual(c.partidos_copa[1].visitante, 'C');

  /* Hueco ocupado: intercambio en los dos sentidos. */
  c = base();
  r = C.moverEnCuadro(c, { idx: 0, lado: 'local' }, { idx: 1, lado: 'local' });
  assert.deepStrictEqual(r, { movido: 'A', ocupante: 'C' });
  assert.strictEqual(c.partidos_copa[1].local, 'A', 'el movido ocupa el destino');
  assert.strictEqual(c.partidos_copa[0].local, 'C', 'y el ocupante se va al origen');
  assert.strictEqual(c.partidos_copa[0].visitante, 'B', 'el otro lado no se toca');

  /* Colocar a mano rompe la vinculacion: si no, la web seguiria pintando el
     ganador de la ronda previa y el cambio seria invisible. */
  c = base();
  r = C.moverEnCuadro(c, { idx: 2, lado: 'visitante' }, { idx: 1, lado: 'visitante' });
  assert.ok(r, 'mover el lado NO vinculado del cruce 2 es valido');
  assert.strictEqual(c.partidos_copa[2].origen_visitante, null);
  assert.strictEqual(c.partidos_copa[1].origen_visitante, null);

  /* Un hueco vinculado no contiene un equipo, contiene una regla. */
  c = base();
  assert.strictEqual(C.moverEnCuadro(c, { idx: 2, lado: 'local' }, { idx: 1, lado: 'visitante' }), null,
    'no se puede coger de un hueco vinculado');
  assert.strictEqual(C.moverEnCuadro(c, { idx: 0, lado: 'local' }, { idx: 2, lado: 'local' }), null,
    'ni soltar encima de uno');
  assert.strictEqual(c.partidos_copa[2].origen_local, 0, 'y la vinculacion sigue intacta');

  /* Nadie juega contra si mismo. */
  c = base();
  c.partidos_copa[1].visitante = 'A';
  assert.strictEqual(C.moverEnCuadro(c, { idx: 0, lado: 'local' }, { idx: 1, lado: 'local' }), null,
    'el movimiento que enfrentaria a A consigo mismo se rechaza entero');
  assert.strictEqual(c.partidos_copa[0].local, 'A', 'y no deja el cuadro a medias');
  assert.strictEqual(c.partidos_copa[1].local, 'C');

  /* Casos que no son movimiento. */
  c = base();
  assert.strictEqual(C.moverEnCuadro(c, { idx: 1, lado: 'local' }, { idx: 1, lado: 'local' }), null, 'al mismo sitio');
  assert.strictEqual(C.moverEnCuadro(c, { idx: 1, lado: 'visitante' }, { idx: 0, lado: 'local' }), null, 'desde un hueco vacio');
  assert.strictEqual(C.moverEnCuadro(c, { idx: 9, lado: 'local' }, { idx: 0, lado: 'local' }), null, 'cruce inexistente');

  /* Sobre los datos reales, un intercambio no rompe la integridad. */
  const real = JSON.parse(JSON.stringify(d));
  C.completarEsquema(real); setD(real);
  const libres = [];
  real.partidos_copa.forEach((p, i) => {
    ['local', 'visitante'].forEach(l => {
      const k = l === 'local' ? 'origen_local' : 'origen_visitante';
      if (p[l] && (p[k] == null || p[k] === '')) libres.push({ idx: i, lado: l });
    });
  });
  assert.ok(libres.length >= 2, 'el cuadro real tiene huecos no vinculados con equipo');
  const antes = C.validarIntegridad(real).err.length;
  assert.ok(C.moverEnCuadro(real, libres[0], libres[1]), 'el intercambio se aplica');
  assert.strictEqual(C.validarIntegridad(real).err.length, antes, 'y no introduce errores');
  setD(d);
  ok('cuadro de Copa: intercambio, ruptura de vinculacion y rechazo de cruces imposibles');
}

/* -- 20. Generador de calendario ---------------------------------------
   Un calendario mal hecho no se ve a ojo: hay que contar. */
{
  const comprobar = (nombres, vueltas) => {
    const ms = C.generarCalendario(nombres, { vueltas, semilla: 7 });
    const N = nombres.length;
    /* Cada pareja se enfrenta exactamente `vueltas` veces. */
    const pares = {};
    ms.forEach(p => {
      const k = [p.local, p.visitante].sort().join('|');
      pares[k] = (pares[k] || 0) + 1;
      assert.notStrictEqual(p.local, p.visitante, 'nadie juega contra si mismo');
      assert.ok(nombres.includes(p.local) && nombres.includes(p.visitante), 'sin equipos inventados');
    });
    assert.strictEqual(Object.keys(pares).length, N * (N - 1) / 2, 'faltan o sobran emparejamientos');
    Object.keys(pares).forEach(k => assert.strictEqual(pares[k], vueltas, 'la pareja ' + k + ' se repite mal'));
    assert.strictEqual(ms.length, (N * (N - 1) / 2) * vueltas, 'numero total de partidos');

    /* Nadie juega dos veces en la misma jornada. */
    const porJor = {};
    ms.forEach(p => {
      porJor[p.jornada] = porJor[p.jornada] || [];
      porJor[p.jornada].push(p.local, p.visitante);
    });
    Object.keys(porJor).forEach(j => {
      assert.strictEqual(new Set(porJor[j]).size, porJor[j].length, 'jornada ' + j + ': alguien juega dos veces');
    });
    const jornadas = Object.keys(porJor).length;
    assert.strictEqual(jornadas, (N % 2 ? N : N - 1) * vueltas, 'numero de jornadas');

    /* Reparto de campo: con ida y vuelta tiene que quedar equilibrado. */
    const casa = {};
    nombres.forEach(x => { casa[x] = 0; });
    ms.forEach(p => { casa[p.local]++; });
    const partidosPorEquipo = (N - 1) * vueltas;
    nombres.forEach(x => {
      const fuera = partidosPorEquipo - casa[x];
      assert.ok(Math.abs(casa[x] - fuera) <= (vueltas === 2 ? 1 : N),
        x + ' juega ' + casa[x] + ' en casa y ' + fuera + ' fuera');
    });
    return { ms, jornadas };
  };

  const par = comprobar(['A', 'B', 'C', 'D', 'E', 'F'], 2);
  assert.strictEqual(par.jornadas, 10);
  comprobar(['A', 'B', 'C', 'D', 'E'], 2);          // impar, con descanso
  comprobar(['A', 'B', 'C', 'D'], 1);
  comprobar(['A', 'B'], 2);

  /* Sobre los equipos reales de una division. */
  const reales = d.equipos.filter(e => e.division === 'SUPERLIGA' && !e.archivado).map(e => e.nombre);
  const r = comprobar(reales, 2);
  /* La misma semilla da el mismo calendario; otra da uno distinto. */
  const a = C.generarCalendario(reales, { vueltas: 2, semilla: 42 });
  const b = C.generarCalendario(reales, { vueltas: 2, semilla: 42 });
  const c = C.generarCalendario(reales, { vueltas: 2, semilla: 43 });
  assert.strictEqual(JSON.stringify(a), JSON.stringify(b), 'misma semilla, mismo sorteo');
  assert.notStrictEqual(JSON.stringify(a), JSON.stringify(c), 'otra semilla, otro sorteo');
  ok('generarCalendario: ' + reales.length + ' equipos, ' + r.ms.length + ' partidos en ' + r.jornadas + ' jornadas, sin repetidos ni solapes');
}

/* -- 21. Sorteo de Copa: el encadenado tiene que resolver ---------------
   Lo que importa no es que salgan cruces, sino que origen_local y
   origen_visitante apunten a donde deben. Se comprueba simulando la
   competicion entera con resolveSide(), que es como la lee la web. */
{
  const simular = (nombres, opciones) => {
    const { partidos, avisos } = C.generarCopa(nombres, Object.assign({ semilla: 11 }, opciones || {}));
    const c = { config: { temporada: '3' }, equipos: nombres.map((x, i) => ({ id: 'e' + i, nombre: x, division: 'SUPERLIGA' })), partidos_copa: partidos };
    C.completarEsquema(c); setD(c);
    assert.deepStrictEqual(C.validarIntegridad(c).err.map(e => e.m), [], 'el sorteo no puede nacer con errores');

    /* Todo indice de origen apunta hacia atras: si apuntara hacia delante, la
       cascada de la web no podria resolverse en un solo recorrido. */
    partidos.forEach((p, i) => {
      ['origen_local', 'origen_visitante'].forEach(k => {
        if (p[k] != null) assert.ok(p[k] < i, 'el cruce ' + i + ' se alimenta de uno posterior (' + p[k] + ')');
      });
    });

    /* Se juega entera: gana siempre el local, y al final debe quedar un unico
       campeon y ningun hueco sin resolver. */
    partidos.forEach(p => {
      const L = C.resolveSide(p, 'local'), V = C.resolveSide(p, 'visitante');
      assert.ok(!L.pend || L.origen != null, 'un lado pendiente sin origen');
      if (L.origen != null) p.local = L.n;
      if (V.origen != null) p.visitante = V.n;
      assert.ok(p.local, 'todo cruce acaba con local');
      assert.ok(p.visitante, 'y con visitante: ' + JSON.stringify(p));
      assert.notStrictEqual(p.local, p.visitante, 'nadie se enfrenta a si mismo');
      p.estado = 'FINALIZADO'; p.goles_l = 1; p.goles_v = 0;
    });
    const final = partidos.filter(p => p.fase === 'FINAL');
    assert.strictEqual(final.length, 1, 'una sola final');
    const campeon = C.winnerOf(final[0]);
    assert.ok(nombres.includes(campeon), 'el campeon es uno de los inscritos');

    /* Cada equipo entra en el cuadro exactamente una vez. */
    const entradas = [];
    partidos.forEach(p => {
      if (p.origen_local == null) entradas.push(p.local);
      if (p.origen_visitante == null) entradas.push(p.visitante);
    });
    setD(d);
    return { partidos, avisos, campeon, entradas };
  };

  /* Potencia de dos exacta: sin previa. */
  let r = simular(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
  assert.strictEqual(r.partidos.filter(p => p.fase === 'RONDA 1 (PREVIA)').length, 0, '8 equipos no necesitan previa');
  assert.strictEqual(r.partidos.length, 7, '8 equipos = 7 cruces');
  assert.deepStrictEqual(r.partidos.map(p => p.fase).filter((v, i, a) => a.indexOf(v) === i),
    ['CUARTOS DE FINAL', 'SEMIFINALES', 'FINAL']);

  /* Numero que no es potencia de dos: previa para los que sobran. */
  r = simular(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K']);
  assert.strictEqual(r.partidos.filter(p => p.fase === 'RONDA 1 (PREVIA)').length, 3, '11 equipos: 3 previas para bajar a 8');
  assert.strictEqual(r.partidos.length, 10, '11 equipos = 10 cruces');

  /* Los 16 del formato de la liga. */
  r = simular('ABCDEFGHIJKLMNOP'.split(''));
  assert.strictEqual(r.partidos.length, 15);
  assert.ok(r.partidos.some(p => p.fase === 'RONDA 2'));

  /* Dos equipos: solo final. */
  r = simular(['A', 'B']);
  assert.strictEqual(r.partidos.length, 1);
  assert.strictEqual(r.partidos[0].fase, 'FINAL');

  /* Rivalidades: no deben cruzarse en la primera ronda que se juegue, sea la
     previa o la primera del cuadro. Se comprueban las dos situaciones. */
  const enfrenta = (ms, x, y) => ms.some(p => (p.local === x && p.visitante === y) || (p.local === y && p.visitante === x));

  /* Caso 1: los rivales son los peor sembrados y caen en la previa. */
  const r1 = C.generarCopa(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'],
    { semilla: 5, siembra: true, rivalidades: [['J', 'K']] }).partidos;
  assert.ok(!enfrenta(r1.filter(p => p.fase === 'RONDA 1 (PREVIA)'), 'J', 'K'),
    'la rivalidad se esquiva en la previa');

  /* Caso 2 —el que fallaba—: los rivales estan bien sembrados, se libran de
     la previa y se cruzarian en la primera ronda del cuadro. */
  const r2 = C.generarCopa(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L',
                            'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'],
    { semilla: 99, siembra: true, rivalidades: [['A', 'T']] }).partidos;
  const primeraDelCuadro = r2.filter(p => p.fase === 'RONDA 2');
  assert.ok(!enfrenta(primeraDelCuadro, 'A', 'T'), 'la rivalidad se esquiva tambien en la primera ronda del cuadro');
  assert.ok(!enfrenta(r2.filter(p => p.fase === 'RONDA 1 (PREVIA)'), 'A', 'T'));

  /* Los ganadores de la previa NO pueden emparejarse entre si: el sentido de
     la previa es que se crucen con los cabezas de serie. */
  {
    const ms = C.generarCopa('ABCDEFGHIJKLMNOPQRST'.split(''), { semilla: 4, siembra: true }).partidos;
    const nPrevias = ms.filter(p => p.fase === 'RONDA 1 (PREVIA)').length;
    assert.strictEqual(nPrevias, 4, '20 equipos: 4 previas para bajar a 16');
    const r2ms = ms.filter(p => p.fase === 'RONDA 2');
    const ambosDePrevia = r2ms.filter(p => p.origen_local != null && p.origen_visitante != null);
    assert.strictEqual(ambosDePrevia.length, 0,
      'ningun cruce del cuadro puede alimentarse de dos previas: los ganadores deben ir contra sembrados');
    const conUnaPrevia = r2ms.filter(p => p.origen_local != null || p.origen_visitante != null);
    assert.strictEqual(conUnaPrevia.length, nPrevias, 'cada ganador de previa cae en un cruce distinto');
  }

  /* Fase de grupos. */
  const g = C.generarCopa('ABCDEFGHIJKLMNOP'.split(''), { tipo: 'grupos', grupos: 4, semilla: 3 });
  assert.strictEqual(g.partidos.length, 4 * (4 * 3 / 2), '4 grupos de 4 a una vuelta = 24 partidos');
  assert.ok(g.partidos.every(p => p.fase === 'FASE DE GRUPOS' && p.grupo), 'todos con fase y grupo');
  Object.keys(g.reparto).forEach(k => assert.strictEqual(g.reparto[k].length, 4, 'grupo ' + k + ' con 4 equipos'));
  const repartidos = Object.keys(g.reparto).reduce((a, k) => a.concat(g.reparto[k]), []);
  assert.strictEqual(new Set(repartidos).size, 16, 'nadie repetido entre grupos');
  ok('generarCopa: encadenado hacia atras, se juega entera y sale un unico campeon');
}

/* -- 22. Estadisticas de jugador desde los eventos ---------------------
   Esta comprobacion existe por un fallo real: la version anterior indexaba
   por la cadena "club<separador>nombre" y el separador era un byte NUL en vez
   de un espacio. Quien consultaba con un espacio recibia cero para TODOS los
   jugadores, sin ningun error: parecia que el archivo tenia 53 descuadres
   cuando en realidad solo tenia uno. Ahora se indexa por el objeto. */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);

  const mapa = C.statsJugadoresCalculadas();
  assert.ok(mapa instanceof Map, 'se indexa por objeto, no por una cadena con separador');

  /* Contraste contra calcScorers, que es la cuenta que hace la web: el maximo
     goleador tiene que salir con los mismos goles por las dos vias. */
  const top = C.calcScorers([...c.partidos_liga, ...c.partidos_ascenso, ...c.partidos_copa].filter(C.isFin))[0];
  assert.ok(top && top.j, 'hace falta un goleador con ficha');
  assert.strictEqual(C.eventosDe(mapa, top.j).goles, top.goles,
    'los goles de ' + top.nombre + ' no coinciden entre statsJugadoresCalculadas y calcScorers');

  /* El total tiene que cuadrar con los goles anotados en los detalles. */
  let suma = 0;
  mapa.forEach(x => { suma += x.goles; });
  const enDetalles = [...c.partidos_liga, ...c.partidos_ascenso, ...c.partidos_copa]
    .filter(C.isFin)
    .reduce((a, p) => {
      const ev = C.parseDetalles(p.detalles);
      return a + ev.local.filter(e => e.tipo === 'gol').length + ev.visitante.filter(e => e.tipo === 'gol').length;
    }, 0);
  assert.strictEqual(suma, enDetalles, 'se pierden o duplican goles al atribuirlos a jugadores');
  assert.ok(suma > 0, 'el archivo real tiene goles con goleador anotado');

  /* Un jugador que no aparece en ningun evento devuelve ceros, no undefined:
     quien consulte no tiene que defenderse de un hueco. */
  const sinEventos = { nombre: 'Nadie De Nadie', goles: 0 };
  assert.deepStrictEqual(C.eventosDe(mapa, sinEventos),
    { goles: 0, asistencias: 0, amarillas: 0, rojas: 0 });

  /* Y el codigo fuente no puede volver a colar un byte de control donde va un
     separador legible. */
  const fuente = fs.readFileSync(path.join(__dirname, 'js', 'core.js'), 'utf8');
  const control = fuente.split('').filter(ch => {
    const k = ch.charCodeAt(0);
    return k < 32 && ch !== '\n' && ch !== '\r' && ch !== '\t';
  });
  assert.strictEqual(control.length, 0, 'core.js tiene ' + control.length + ' caracteres de control invisibles');

  setD(d);
  ok('statsJugadoresCalculadas: indexado por objeto, cuadra con calcScorers (' + suma + ' goles atribuidos)');
}

/* -- 23. Consistencia de nombres --------------------------------------- */
{
  /* Distancia y parecido. */
  assert.strictEqual(C.distancia('Mike', 'Mike'), 0);
  assert.strictEqual(C.distancia('Mike', 'Mike '), 0, 'ignora espacios de sobra');
  assert.strictEqual(C.distancia('Bump Trungus', 'Lump Trungus'), 1);
  assert.strictEqual(C.distancia('Muller', 'Müller'), 0, 'ignora tildes y dieresis');
  assert.strictEqual(C.distancia('', 'abc'), 3);
  assert.ok(C.parecido('Bump Trungus', 'Lump Trungus') > 0.9);
  assert.ok(C.parecido('Mike', 'Zanark') < 0.4);

  /* Sobre el archivo real. */
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);
  const r = C.analizarNombres(c);
  assert.deepStrictEqual(r.huerfanos, [], 'ningun evento del archivo real apunta a la nada');
  assert.deepStrictEqual(r.difusos, [], 'ningun evento depende de coincidencia difusa');
  assert.deepStrictEqual(r.ambiguos, [], 'ningun nombre lo llevan dos jugadores');
  /* El caso de Mike NO es un fallo: marco para Raimon y despues ficho por el
     Royal Academy en la misma temporada, y su historial lo demuestra. Un gol
     con otra camiseta es lo normal en esta liga, asi que se separa de los
     casos sospechosos en vez de dar la voz de alarma. */
  assert.deepStrictEqual(r.otroClub, [], 'ningun gol queda como atribucion sospechosa');
  assert.strictEqual(r.traspasos.length, 1, 'y uno se explica por un traspaso');
  assert.strictEqual(r.traspasos[0].nombre, 'Mike');
  assert.strictEqual(r.traspasos[0].anotadoPor, 'Raimon');
  assert.strictEqual(r.traspasos[0].clubReal, 'Royal Academy');
  assert.ok(r.traspasos[0].etapa, 'con la etapa del historial que lo confirma');
  assert.strictEqual(r.traspasos[0].etapa.equipo, 'Raimon');

  /* Si esa etapa no existiera, si seria sospechoso. */
  const sinEtapa = JSON.parse(JSON.stringify(d));
  C.completarEsquema(sinEtapa);
  sinEtapa.equipos.forEach(e => (e.jugadores || []).forEach(j => {
    if (j.nombre === 'Mike') j.historial = [];
  }));
  const r2 = C.analizarNombres(sinEtapa, { parejas: false });
  assert.strictEqual(r2.otroClub.length, 1, 'sin historial que lo respalde, vuelve a ser sospechoso');
  assert.strictEqual(r2.traspasos.length, 0);
  assert.ok(r.parecidos.length >= 2, 'detecta las parejas de nombres casi iguales');
  assert.ok(r.parecidos.some(x => /Trungus/.test(x.a.j.nombre)), 'entre ellas Bump/Lump Trungus');

  /* Los detecta cuando se introducen a proposito. */
  const roto = (mut) => {
    const x = JSON.parse(JSON.stringify(d));
    C.completarEsquema(x); mut(x);
    return C.analizarNombres(x, { parejas: false });
  };
  const primerGol = (x) => {
    const p = x.partidos_liga.find(q => C.isFin(q) && /gol:/.test(q.detalles || ''));
    return { p, ev: C.parseDetalles(p.detalles) };
  };
  /* Un nombre que no existe en ninguna parte. */
  let v = roto(x => {
    const { p, ev } = primerGol(x);
    ev.visitante[0].nombre = 'Zzyzx Nadieson';
    p.detalles = C.serializarDetalles(ev);
  });
  assert.ok(v.huerfanos.some(h => h.nombre === 'Zzyzx Nadieson'), 'detecta el nombre huerfano');

  /* Un nombre que solo casa por prefijo: findPlayer lo acepta y nadie se
     entera, que es exactamente el fallo silencioso a cazar. */
  v = roto(x => {
    const { p, ev } = primerGol(x);
    ev.visitante[0].nombre = 'Raleigh';
    p.detalles = C.serializarDetalles(ev);
  });
  assert.ok(v.difusos.some(h => h.nombre === 'Raleigh'), 'detecta la coincidencia difusa');
  assert.strictEqual(v.difusos.find(h => h.nombre === 'Raleigh').resuelve.nombre, 'Raleigh Greenstreet');

  /* Dos jugadores de clubes distintos con el mismo nombre. */
  v = roto(x => {
    const otro = x.equipos.find(e => (e.jugadores || []).length && e.nombre !== 'Zanark Domain');
    otro.jugadores.push({ nombre: 'Raleigh Greenstreet', dorsal: '99', posicion: 'DEL', titular: false, goles: 0, asistencias: 0, amarillas: 0, rojas: 0 });
  });
  assert.ok(v.ambiguos.some(h => h.nombre === 'Raleigh Greenstreet'), 'detecta el nombre ambiguo');

  ok('analizarNombres: 0 huerfanos, 0 difusos, 0 ambiguos y 1 atribucion cruzada en el archivo real');
}

/* -- 24. Unificar un nombre -------------------------------------------- */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);

  /* Renombrar en los eventos mueve los goles al nombre nuevo, y ni uno se
     pierde por el camino. */
  const antes = C.calcScorers([...c.partidos_liga, ...c.partidos_ascenso, ...c.partidos_copa].filter(C.isFin));
  const total = antes.reduce((s, r) => s + r.goles, 0);
  const victima = antes[0];
  const n = C.renombrarEnEventos(c, victima.nombre, 'Nombre Nuevo Del Todo');
  assert.strictEqual(n, victima.goles, 'toca exactamente los eventos de ese jugador');

  const despues = C.calcScorers([...c.partidos_liga, ...c.partidos_ascenso, ...c.partidos_copa].filter(C.isFin));
  assert.strictEqual(despues.reduce((s, r) => s + r.goles, 0), total, 'no se pierde ningun gol');
  assert.ok(despues.some(r => r.nombre === 'Nombre Nuevo Del Todo' || (r.j && r.j.nombre === victima.j.nombre)),
    'los goles siguen contandose');
  assert.ok(!despues.some(r => r.textoCrudo === victima.nombre), 'el nombre viejo ya no aparece en los eventos');

  /* Los textos derivados se regeneran con el nombre nuevo. */
  const conTexto = [...c.partidos_liga, ...c.partidos_ascenso].filter(p => p.goleadores_texto);
  assert.ok(!conTexto.some(p => p.goleadores_texto.includes(victima.nombre)),
    'el nombre viejo tampoco queda en los textos de goleadores');

  /* Renombrar al jugador arrastra sus eventos: si no, se le desenganchan
     todos los goles de golpe. */
  const c2 = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c2); setD(c2);
  const top = C.calcScorers(c2.partidos_liga.filter(C.isFin))[0];
  const golesAntes = top.goles;
  const res = C.renombrarJugador(c2, top.j, 'Renombrado Total');
  assert.strictEqual(res.eventos, golesAntes, 'se arrastran sus eventos');
  assert.strictEqual(top.j.nombre, 'Renombrado Total');
  const rank = C.calcScorers(c2.partidos_liga.filter(C.isFin));
  const suyo = rank.find(r => r.nombre === 'Renombrado Total');
  assert.ok(suyo && suyo.j === top.j, 'sus goles siguen enganchados a su ficha tras el renombrado');
  assert.strictEqual(suyo.goles, golesAntes, 'y son los mismos goles');

  /* Renombrar a algo que ya se llamaba igual no toca nada. */
  assert.strictEqual(C.renombrarEnEventos(c2, 'Renombrado Total', 'Renombrado Total'), 0);
  setD(d);
  ok('unificar nombres: arrastra eventos y textos, sin perder ni un gol');
}

/* -- 25. Partidos no jugados -------------------------------------------
   En esta liga hay partidos que no se disputan y se resuelven con victoria
   administrativa. Marcarlos no puede tocar ni el marcador ni la tabla: lo
   unico que cambia es que dejan de contarse como goles sin goleador. */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);

  const antesTabla = JSON.stringify(C.tablaCalculada());
  const p = c.partidos_liga.find(x => C.isFin(x) && (Number(C.gl(x)) || 0) + (Number(C.gv(x)) || 0) > 0);
  const marcador = [C.gl(p), C.gv(p)];

  assert.strictEqual(C.esNoJugado(p), false, 'por defecto todo partido cuenta como disputado');
  p.no_jugado = true;
  assert.strictEqual(C.esNoJugado(p), true);

  /* Lo que NO puede pasar: que marcar cambie el resultado o la clasificacion. */
  assert.deepStrictEqual([C.gl(p), C.gv(p)], marcador, 'el marcador no se toca al marcar');
  assert.strictEqual(JSON.stringify(C.tablaCalculada()), antesTabla,
    'un partido no jugado sigue sumando en la clasificacion exactamente igual');

  /* Y la web no se entera: el campo es aditivo. */
  assert.strictEqual(C.isFin(p), true, 'sigue siendo FINALIZADO para la web');
  delete p.no_jugado;
  assert.strictEqual(C.esNoJugado(p), false);
  setD(d);
  ok('no_jugado: marca sin tocar marcador ni clasificacion');
}

/* -- 26. Limpiar los campos que la liga no usa ------------------------- */
{
  const c = JSON.parse(JSON.stringify(d));
  C.completarEsquema(c); setD(c);

  const antes = C.contarCamposSinUso(c);
  assert.ok(antes.campos > 1000, 'el archivo real arrastra miles de campos sin uso');

  const golesAntes = C.calcScorers([...c.partidos_liga, ...c.partidos_ascenso, ...c.partidos_copa].filter(C.isFin))
    .reduce((s, r) => s + r.goles, 0);
  const tablaAntes = JSON.stringify(C.tablaCalculada());
  const carreras = c.equipos.flatMap(e => (e.jugadores || []).map(j => (j.goles_totales || 0) + (j.goles || 0)));

  const n = C.limpiarCamposSinUso(c);
  assert.strictEqual(n, antes.campos + contarEnHistorial(d), 'quita los que dijo que iba a quitar');
  assert.strictEqual(C.contarCamposSinUso(c).campos, 0, 'no queda ninguno');

  /* Nada de lo que la web usa puede haberse movido. */
  assert.strictEqual(JSON.stringify(C.tablaCalculada()), tablaAntes, 'la clasificacion no cambia');
  assert.strictEqual(
    C.calcScorers([...c.partidos_liga, ...c.partidos_ascenso, ...c.partidos_copa].filter(C.isFin))
      .reduce((s, r) => s + r.goles, 0), golesAntes, 'los goleadores no cambian');
  assert.deepStrictEqual(
    c.equipos.flatMap(e => (e.jugadores || []).map(j => (j.goles_totales || 0) + (j.goles || 0))),
    carreras, 'las carreras no cambian');
  assert.deepStrictEqual(C.validarIntegridad(c).err.map(x => x.m), [], 'no rompe la integridad');

  /* Y `goles` sobrevive: es el unico de esa familia que si se usa. */
  assert.ok(c.equipos.some(e => (e.jugadores || []).some(j => 'goles' in j)), 'goles se conserva');
  assert.ok(!c.equipos.some(e => (e.jugadores || []).some(j => 'asistencias' in j)), 'asistencias fuera');

  /* Volver a limpiar no encuentra nada: es idempotente. */
  assert.strictEqual(C.limpiarCamposSinUso(c), 0);
  setD(d);
  ok('limpiarCamposSinUso: quita ' + n + ' campos sin mover un solo dato de competicion');
}
function contarEnHistorial(x) {
  let n = 0;
  const mirar = (j) => (j.historial || []).forEach(h => {
    ['asistencias', 'amarillas', 'rojas'].forEach(k => { if (k in h) n++; });
  });
  (x.equipos || []).forEach(e => (e.jugadores || []).forEach(mirar));
  (x.agentes_libres || []).forEach(mirar);
  return n;
}

console.log('\n' + n + ' comprobaciones OK.');

/* Informe de contexto, no es una comprobación: lo que el gestor debería
   ofrecerse a arreglar la primera vez que se abra el archivo. */
const des = C.desajustesTabla();
if (des.length) {
  const eq = [...new Set(des.map(x => x.equipo.nombre))];
  console.log('\nAVISO — ' + des.length + ' desajustes de clasificacion en ' + eq.length + ' equipos.');
  des.slice(0, 6).forEach(x => console.log('   ' + x.equipo.nombre + '.' + x.campo + ': guardado ' + x.guardado + ', calculado ' + x.calculado));
  if (des.length > 6) console.log('   ... y ' + (des.length - 6) + ' mas');
}
const vi = C.validarIntegridad(d);
if (vi.avi.length) {
  console.log('\nAVISOS del archivo real: ' + vi.avi.length);
  vi.avi.slice(0, 8).forEach(a => console.log('   ' + a.m));
  if (vi.avi.length > 8) console.log('   ... y ' + (vi.avi.length - 8) + ' mas');
}
