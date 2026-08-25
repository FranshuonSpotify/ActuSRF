/* Criterio de salida de la Fase 1, en un solo paso:
   crear equipo con plantilla -> jugar jornada con goleadores -> publicar
   noticia -> guardar, y comprobar que el archivo resultante es el que la web
   pública sabe leer.

   Uso:  node propuesta-web/gestor/test-ciclo.js
   Escribe .prueba.json al lado, que es lo que carga la comprobación en
   navegador contra propuesta-web/index.html. */
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

const antesPts = Object.fromEntries(d.equipos.map(e => [e.nombre, e.pts || 0]));

/* -- 1. Crear equipo con plantilla ------------------------------------- */
const eq = {
  id: 'eq_prueba_ciclo', nombre: 'Prueba FC', escudo: '', division: 'ASCENSO',
  ciudad: 'Pruebilla', estadio: '', entrenador: 'Tester', gerente: '', formacion: '4-3-3',
  abreviatura: 'PRB', color1: '#FF5100', color2: '#111111',
  pj: 0, g: 0, e: 0, p: 0, gf: 0, gc: 0, pts: 0,
  jugadores: [
    { nombre: 'Portero Uno', dorsal: '1', posicion: 'POR', titular: true, goles: 0, asistencias: 0, amarillas: 0, rojas: 0, foto: '', afinidad: 'Aire' },
    { nombre: 'Defensa Dos', dorsal: '2', posicion: 'DEF', titular: true, goles: 0, asistencias: 0, amarillas: 0, rojas: 0, foto: '', afinidad: 'Montaña' },
    { nombre: 'Delantero Tres', dorsal: '9', posicion: 'DEL', titular: true, goles: 0, asistencias: 0, amarillas: 0, rojas: 0, foto: '', afinidad: 'Fuego' }
  ]
};
d.equipos.push(eq);

/* -- 2. Jugar una jornada con goleadores ------------------------------- */
const rival = d.equipos.find(e => e.division === 'ASCENSO' && !e.archivado && e.id !== eq.id);
assert.ok(rival, 'hace falta un rival de Ascenso');
const golRival = rival.jugadores[0].nombre;

const ev = {
  local: [
    { tipo: 'gol', nombre: 'Delantero Tres', minuto: '23' },
    { tipo: 'gol', nombre: 'Defensa Dos', minuto: '67' },
    { tipo: 'amarilla', nombre: 'Portero Uno', minuto: '71' }
  ],
  visitante: [{ tipo: 'gol', nombre: golRival, minuto: '80' }],
  pen: null
};
const partido = {
  jornada: '99', fecha: '20/08/2026', estado: 'FINALIZADO',
  local: eq.nombre, visitante: rival.nombre,
  goles_l: 2, goles_v: 1,
  detalles: C.serializarDetalles(ev)
};
Object.assign(partido, C.textosDerivados(ev));
d.partidos_ascenso.push(partido);

/* Cascada: la clasificación se rehace desde los partidos, como hace la vista. */
const calc = C.tablaCalculada();
d.equipos.forEach(e => { const c = calc[e.nombre]; if (c) C.CAMPOS_TABLA.forEach(k => { e[k] = c[k]; }); });

/* -- 3. Publicar noticia ----------------------------------------------- */
d.noticias.unshift({
  tag: 'PARTIDOS', color: '#FF5100', titulo: 'Prueba FC gana su estreno',
  resumen: 'Dos goles y tres puntos en el debut.',
  cuerpo: 'El Prueba FC se estrenó con victoria por 2-1.',
  imagen: '', autor: 'Gestor', fecha: '20/08/2026'
});

/* -- 4. Guardar: normalizar + validar ---------------------------------- */
const notas = C.normalizar(d);
const v = C.validarIntegridad(d);
assert.deepStrictEqual(v.err.map(x => x.m), [], 'el ciclo no puede dejar errores criticos');

/* -- 5. Comprobar el resultado ----------------------------------------- */
let n = 0;
const ok = (m) => { n++; console.log('  ok  ' + m); };

assert.strictEqual(eq.pj, 1); assert.strictEqual(eq.g, 1); assert.strictEqual(eq.pts, 3);
assert.strictEqual(eq.gf, 2); assert.strictEqual(eq.gc, 1);
ok('el equipo nuevo suma 3 puntos, 2 a favor y 1 en contra');

assert.strictEqual(d.equipos.find(e => e.nombre === rival.nombre).pts, antesPts[rival.nombre],
  'el rival pierde, asi que no suma puntos');
ok('la cascada solo movio a quien jugo');

/* El formato de `detalles` tiene que ser exactamente el que la web parsea. */
assert.strictEqual(partido.detalles,
  'gol:Delantero Tres:23, gol:Defensa Dos:67, amarilla:Portero Uno:71 / gol:' + golRival + ':80');
ok('detalles con el formato exacto de la web, tarjeta incluida');

assert.strictEqual(partido.goleadores_texto, "Delantero Tres 23', Defensa Dos 67', " + golRival + " 80'");
assert.strictEqual(partido.goleadores_local_texto, "Delantero Tres 23', Defensa Dos 67'");
assert.strictEqual(partido.goleadores_visitante_texto, golRival + " 80'");
ok('textos derivados correctos, y la amarilla no se cuela entre los goleadores');

const sc = C.calcScorers([partido]);
assert.strictEqual(sc.length, 3);
assert.ok(sc.every(r => r.j), 'los tres goleadores enlazan con una ficha de jugador');
ok('los goleadores enlazan con su ficha');

/* La clasificación de la web tiene que colocar al equipo nuevo donde toca. */
const tabla = C.clasificacion('ASCENSO');
const pos = tabla.findIndex(e => e.id === eq.id) + 1;
assert.ok(pos > 0, 'el equipo nuevo aparece en la clasificacion de Ascenso');
ok('aparece en la clasificacion de Ascenso, puesto ' + pos + ' de ' + tabla.length);

/* Idempotencia del guardado: guardar dos veces no cambia nada. */
const unaVez = JSON.stringify(d);
C.normalizar(d);
assert.strictEqual(JSON.stringify(d), unaVez, 'guardar dos veces seguidas cambia el archivo');
ok('guardar dos veces seguidas produce el mismo archivo (' + notas.length + ' notas de normalizacion)');

const salida = path.join(__dirname, '.prueba.json');
fs.writeFileSync(salida, JSON.stringify(d, null, 4));
console.log('\n' + n + ' comprobaciones OK.');
console.log('Escrito ' + path.relative(RAIZ, salida) + ' (' + Math.round(fs.statSync(salida).size / 1024) + ' KB)');
console.log('Marcadores para la comprobacion en navegador: equipo "Prueba FC", goleador "Delantero Tres", noticia "Prueba FC gana su estreno".');
