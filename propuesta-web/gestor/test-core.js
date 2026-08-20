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
