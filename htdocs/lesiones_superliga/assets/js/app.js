
'use strict';

const API = 'api/';
const SPEEDS = { slow: 2.8, normal: 1.5, fast: 0.6 };
let speedMult = SPEEDS.normal;
let soundOn = true;
let G = { equipos: [], semana: '', procesados: [], resultados: {}, lesionesActivas: [] };
const ROT = { main: 0, event: 0 };

const MAIN_SEGS = [
  { cod: 'no_evento', pct: 85, color: '#1a5c35', textColor: '#d5ffe9', label: 'No ocurre nada' },
  { cod: 'evento_inesperado', pct: 15, color: '#7a171f', textColor: '#ffe0e3', label: 'Eventos inesperados' }
];
const EVENT_SEGS = [
  { cod: '1j_1p', pct: 25.871, color: '#155843', textColor: '#dffff1', label: '1 jugador, 1 partido' },
  { cod: '2j_1p', pct: 21.890, color: '#7a5520', textColor: '#ffe9c6', label: '2 jugadores, 1 partido' },
  { cod: '1j_2p', pct: 17.910, color: '#21498a', textColor: '#e4eeff', label: '1 jugador, 2 partidos' },
  { cod: '2j_2p', pct: 13.930, color: '#6e2a8f', textColor: '#f0dcff', label: '2 jugadores, 2 partidos' },
  { cod: '1j_3p', pct: 10.945, color: '#4332a8', textColor: '#ebebff', label: '1 jugador, 3 partidos' },
  { cod: '2j_3p', pct: 8.954, color: '#8b1f2d', textColor: '#ffe0e6', label: '2 jugadores, 3 partidos' },
  { cod: '1j_temp', pct: 0.500, color: '#4b1010', textColor: '#ffd3d3', label: '1 jugador, temporada' }
];

const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));
const sleep = ms => new Promise(r => setTimeout(r, ms));
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function toast(msg, type = 'success', dur = 3200) {
  const wrap = $('#toastWrap');
  if (!wrap) return;
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = msg;
  wrap.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(10px)';
    setTimeout(() => el.remove(), 280);
  }, dur);
}

let audioCtx = null;
function getAC() {
  if (!soundOn) return null;
  if (!audioCtx) {
    try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { audioCtx = null; }
  }
  return audioCtx;
}
function beep(f, d, v = 0.16, type = 'square') {
  const c = getAC();
  if (!c) return;
  const o = c.createOscillator();
  const g = c.createGain();
  o.type = type; o.frequency.value = f;
  g.gain.setValueAtTime(v, c.currentTime);
  g.gain.exponentialRampToValueAtTime(0.001, c.currentTime + d);
  o.connect(g); g.connect(c.destination);
  o.start(); o.stop(c.currentTime + d);
}
const tickSound = () => beep(420 + Math.random() * 180, 0.04, 0.08, 'square');
const dingSound = () => { beep(900, 0.08, 0.18, 'sine'); setTimeout(() => beep(1100, 0.06, 0.12, 'sine'), 100); };

async function apiFetch(ep, opts = {}) {
  try {
    const resp = await fetch(API + ep, Object.assign({ headers: { 'Content-Type': 'application/json' } }, opts));
    const txt = await resp.text();
    let data = null;
    try { data = JSON.parse(txt); } catch (e) {
      return { success: false, error: 'invalid_json', status: resp.status, raw: txt.slice(0, 500) };
    }
    if (!resp.ok && data && data.success !== true) return data;
    return data;
  } catch (e) {
    return { success: false, error: 'network_error', message: String(e) };
  }
}

function computeSegAngles(segs) {
  const total = segs.reduce((s, o) => s + o.pct, 0);
  let acc = -90;
  return segs.map(s => {
    const sweep = s.pct / total * 360;
    const out = Object.assign({}, s, { start: acc, sweep, mid: acc + sweep / 2, end: acc + sweep });
    acc += sweep;
    return out;
  });
}

const MAIN_COMPUTED = computeSegAngles(MAIN_SEGS);
const EVENT_COMPUTED = computeSegAngles(EVENT_SEGS);

function buildWheelSVG(svg, segs, fontSize) {
  if (!svg) return;
  const CX = 150, CY = 150, R = 138;
  const toRad = d => d * Math.PI / 180;
  let html = '';
  for (const s of segs) {
    const x1 = CX + R * Math.cos(toRad(s.start));
    const y1 = CY + R * Math.sin(toRad(s.start));
    const x2 = CX + R * Math.cos(toRad(s.end));
    const y2 = CY + R * Math.sin(toRad(s.end));
    const large = s.sweep > 180 ? 1 : 0;
    const tx = CX + R * 0.56 * Math.cos(toRad(s.mid));
    const ty = CY + R * 0.56 * Math.sin(toRad(s.mid));
    const label = s.label.length > 14 ? s.label.replace(', ', ',\n') : s.label;
    html += `<path d="M ${CX} ${CY} L ${x1.toFixed(3)} ${y1.toFixed(3)} A ${R} ${R} 0 ${large} 1 ${x2.toFixed(3)} ${y2.toFixed(3)} Z" fill="${s.color}" stroke="#090b10" stroke-width="1.5"></path>`;
    html += `<text x="${tx.toFixed(2)}" y="${ty.toFixed(2)}" fill="${s.textColor}" text-anchor="middle" dominant-baseline="middle" font-family="Rajdhani, sans-serif" font-weight="700" font-size="${fontSize}">` +
      label.split('\n').map((line, i) => `<tspan x="${tx.toFixed(2)}" dy="${i === 0 ? 0 : fontSize * 0.95}">${esc(line)}</tspan>`).join('') +
      `</text>`;
  }
  html += `<circle cx="150" cy="150" r="18" fill="#090b10" stroke="#ff6b1a" stroke-width="2"></circle>`;
  html += `<circle cx="150" cy="150" r="7" fill="#ff6b1a"></circle>`;
  svg.innerHTML = html;
}

function weightedPick(segs) {
  const total = segs.reduce((s, o) => s + o.pct, 0);
  let r = Math.random() * total;
  for (const s of segs) {
    r -= s.pct;
    if (r <= 0) return s;
  }
  return segs[segs.length - 1];
}

function resetSpinDisplays() {
  const main = $('#mainSVG'), event = $('#eventSVG');
  if (main) main.style.transform = 'rotate(0deg)';
  if (event) event.style.transform = 'rotate(0deg)';
  ROT.main = 0; ROT.event = 0;
  $('#mainResult').textContent = 'Esperando tirada…';
  $('#mainResult').className = 'wheel-result';
  $('#eventResult').textContent = 'Esperando…';
  $('#eventResult').className = 'wheel-result';
  $('#eventSection').style.display = 'none';
  $('#slotCard').style.display = 'none';
  $('#slotTrack').innerHTML = '';
  $('#slotTrack').style.transform = 'translateX(0px)';
  $('#slotWinner').textContent = '';
  $('#slotPhase').textContent = 'En espera';
  $('#currentTeamName').textContent = 'Esperando tirada…';
  $('#currentTeamState').textContent = 'Sin actividad';
}

async function spinWheel(which, winner, allSegs) {
  const svg = which === 'main' ? $('#mainSVG') : $('#eventSVG');
  if (!svg) return;
  const rotNeeded = -90 - winner.mid;
  const jitter = (Math.random() * 2 - 1) * (winner.sweep * 0.30);
  const start = 0;
  const target = 360 * (3 + Math.floor(Math.random() * 2)) + rotNeeded + jitter;
  const dur = Math.round(2400 / speedMult);
  const startAt = performance.now();
  let lastTick = 0;
  function frame(now) {
    const p = Math.min((now - startAt) / dur, 1);
    const ease = 1 - Math.pow(1 - p, 4);
    const angle = start + (target - start) * ease;
    svg.style.transform = `rotate(${angle}deg)`;
    if (now - lastTick > 55 && p < 1) { tickSound(); lastTick = now; }
    if (p < 1) return requestAnimationFrame(frame);
    svg.style.transform = `rotate(${rotNeeded + jitter}deg)`;
    ROT[which] = rotNeeded + jitter;
    dingSound();
  }
  requestAnimationFrame(frame);
  await sleep(dur + 80);
}

function addLog(msg, type = 'info', target = '#liveLog') {
  const wrap = $(target);
  if (!wrap) return;
  const el = document.createElement('div');
  el.className = 'log-entry ' + type;
  el.innerHTML = msg;
  wrap.appendChild(el);
  wrap.scrollTop = wrap.scrollHeight;
}

function equipoNombre(id) {
  const eq = G.equipos.find(e => String(e.id) === String(id));
  return eq ? eq.nombre : id;
}

function populateTeamSelects() {
  const sels = ['#filtroActivasEquipo', '#filtroHistorialEquipo', '#selectEquipoPartido', '#selectEquipoAdmin'];
  for (const sel of sels) {
    const node = $(sel);
    if (!node) continue;
    const first = node.querySelector('option') ? node.querySelector('option').outerHTML : '<option value="">Todos</option>';
    node.innerHTML = first;
    for (const eq of G.equipos) {
      const opt = document.createElement('option');
      opt.value = eq.id;
      opt.textContent = eq.nombre;
      node.appendChild(opt);
    }
  }
}

function buildTeamsGrid() {
  const grid = $('#equiposGrid');
  if (!grid) return;
  grid.innerHTML = '';
  for (const eq of G.equipos) {
    const res = G.resultados[eq.id] || null;
    const state = !G.procesados.includes(eq.id) ? 'pending' : (res && res.resultado_principal === 'evento_inesperado' ? 'done-bad' : 'done-ok');
    const badge = !G.procesados.includes(eq.id) ? 'Pendiente' : (res && res.resultado_principal === 'evento_inesperado' ? 'Lesión' : 'Sin evento');
    const card = document.createElement('div');
    card.className = 'team-card ' + state;
    card.dataset.teamId = eq.id;
    card.innerHTML = `<div class="tc-name">${esc(eq.nombre)}</div><div class="tc-badge ${state === 'done-ok' ? 'ok' : state === 'done-bad' ? 'bad' : ''}">${badge}</div>`;
    grid.appendChild(card);
  }
}

function setTeamStatus(teamId, state, text) {
  const card = document.querySelector(`.team-card[data-team-id="${CSS.escape(String(teamId))}"]`);
  if (!card) return;
  card.className = 'team-card ' + state;
  const badge = card.querySelector('.tc-badge');
  if (badge) {
    badge.className = 'tc-badge ' + (state === 'done-ok' ? 'ok' : state === 'done-bad' ? 'bad' : '');
    badge.textContent = text;
  }
}

function renderResultadosFeed() {
  const box = $('#liveLog');
  if (!box) return;
  if (!box.children.length) box.innerHTML = '<div class="log-entry info">Todavía no se ha ejecutado ninguna tirada esta semana.</div>';
}

function renderActivas() {
  const list = $('#listaLesionesActivas');
  if (!list) return;
  const teamFilter = $('#filtroActivasEquipo').value || '';
  const lesiones = (G.lesionesActivas || []).filter(l => !teamFilter || String(l.equipo_id) === teamFilter);
  if (!lesiones.length) {
    list.innerHTML = '<div class="empty-state">No hay lesiones activas.</div>';
    return;
  }
  const grid = document.createElement('div');
  grid.className = 'injuries-grid';
  for (const l of lesiones) {
    const avatar = l.foto ? `<div class="injury-avatar"><img src="${esc(l.foto)}" alt="${esc(l.jugador_nombre)}"></div>` : `<div class="injury-avatar">#${esc(l.dorsal)}</div>`;
    const card = document.createElement('div');
    card.className = 'injury-card activa';
    card.innerHTML = `${avatar}<div><div class="injury-name">${esc(l.jugador_nombre)}</div><div class="injury-team">${esc(l.equipo_nombre)} #${esc(l.dorsal)}</div><div class="injury-desc">${esc(l.descripcion_resultado || '')}</div><div class="injury-counter">${Number(l.partidos_restantes || 0)} restante(s)</div><div class="injury-date">${new Date(l.fecha).toLocaleString('es-ES')}</div></div>`;
    grid.appendChild(card);
  }
  list.innerHTML = '';
  list.appendChild(grid);
}

async function loadActivas() {
  const r = await apiFetch('obtener_lesiones_activas.php');
  G.lesionesActivas = r.success ? (r.lesiones || []) : [];
  $('#statLesionesActivas').textContent = String(G.lesionesActivas.length);
  renderActivas();
}

async function loadHistorial() {
  const r = await apiFetch('obtener_historial.php');
  const list = $('#listaHistorial');
  if (!list) return;
  let lesiones = r.success ? (r.lesiones || []) : [];
  const eq = $('#filtroHistorialEquipo').value || '';
  const est = $('#filtroHistorialEstado').value || '';
  const txt = ($('#filtroHistorialJugador').value || '').trim().toLowerCase();
  if (eq) lesiones = lesiones.filter(l => String(l.equipo_id) === eq);
  if (est) lesiones = lesiones.filter(l => String(l.estado) === est);
  if (txt) lesiones = lesiones.filter(l => String(l.jugador_nombre || '').toLowerCase().includes(txt));
  if (!lesiones.length) {
    list.innerHTML = '<div class="empty-state">Sin registros.</div>';
    return;
  }
  const grid = document.createElement('div');
  grid.className = 'injuries-grid';
  for (const l of lesiones) {
    const avatar = l.foto ? `<div class="injury-avatar"><img src="${esc(l.foto)}" alt="${esc(l.jugador_nombre)}"></div>` : `<div class="injury-avatar">#${esc(l.dorsal)}</div>`;
    const rec = String(l.estado) === 'recuperado';
    const card = document.createElement('div');
    card.className = 'injury-card ' + (rec ? 'recuperado' : 'activa');
    card.innerHTML = `${avatar}<div><div class="injury-name">${esc(l.jugador_nombre)}</div><div class="injury-team">${esc(l.equipo_nombre)} #${esc(l.dorsal)}</div><div class="injury-desc">${esc(l.descripcion_resultado || '')}</div><div class="injury-counter ${rec ? 'zero' : ''}">${rec ? 'Recuperado' : Number(l.partidos_restantes || 0) + ' restante(s)'}</div><div class="injury-date">${new Date(l.fecha).toLocaleString('es-ES')}</div></div>`;
    grid.appendChild(card);
  }
  list.innerHTML = '';
  list.appendChild(grid);
}

function renderServerError(prefix, res) {
  const msg = res && (res.message || res.error || res.raw) ? String(res.message || res.error || res.raw) : 'Error desconocido';
  toast(prefix + ': ' + msg, 'error', 5000);
  addLog(`${esc(prefix)}: ${esc(msg)}`, 'bad');
}

function buildSlotHTML(players) {
  return players.map(j => {
    const avatar = j.foto ? `<div class="slot-avatar"><img src="${esc(j.foto)}" alt="${esc(j.nombre)}"></div>` : `<div class="slot-avatar">#${esc(j.dorsal)}</div>`;
    return `<div class="slot-item"><div>${avatar}</div><div class="slot-info"><div class="slot-name">${esc(j.nombre)}</div><div class="slot-pos">#${esc(j.dorsal)} · ${esc(j.posicion || '')}</div></div></div>`;
  }).join('');
}

async function runSlot(pool, winner) {
  const card = $('#slotCard');
  const machine = $('#slotMachine');
  const track = $('#slotTrack');
  const winnerEl = $('#slotWinner');
  card.style.display = 'block';
  winnerEl.textContent = '';
  const base = pool.slice().sort(() => Math.random() - 0.5);
  const extended = base.concat(base).concat(base).concat([winner]);
  track.innerHTML = buildSlotHTML(extended);
  track.style.transition = 'none';
  track.style.transform = 'translateX(0px)';
  void track.getBoundingClientRect();
  const itemW = window.innerWidth <= 640 ? 160 : 180;
  const centerOffset = machine.clientWidth / 2 - itemW / 2;
  const targetIdx = extended.length - 1;
  const finalX = -(targetIdx * itemW - centerOffset);
  const dur = Math.round(2200 / speedMult);
  track.style.transition = `transform ${dur}ms cubic-bezier(.17,.67,.12,1)`;
  track.style.transform = `translateX(${finalX}px)`;
  let ticks = 0;
  const ti = setInterval(() => { if (++ticks < 30) tickSound(); }, Math.max(35, Math.round(90 / speedMult)));
  await sleep(dur + 60);
  clearInterval(ti);
  dingSound();
  winnerEl.textContent = `${winner.nombre} #${winner.dorsal}`;
}

async function loadData() {
  const d = await apiFetch('cargar_datos.php');
  if (!d.success) {
    renderServerError('Error al cargar datos', d);
    return;
  }
  G.equipos = d.equipos || [];
  G.semana = d.semana || '';
  G.procesados = d.procesados || [];
  G.resultados = d.resultados || {};
  $('#statSemana').textContent = 'Semana ' + G.semana;
  $('#statProcesados').textContent = String(G.procesados.length);
  $('#statTotal').textContent = String(G.equipos.length);
  $('#progressLabel').textContent = `${G.procesados.length} / ${G.equipos.length} equipos`;
  $('#progressFill').style.width = G.equipos.length ? `${(G.procesados.length / G.equipos.length) * 100}%` : '0%';
  const done = G.procesados.length >= G.equipos.length && G.equipos.length > 0;
  $('#statusBadge').textContent = done ? 'Semana completa' : `${G.procesados.length}/${G.equipos.length} procesados`;
  $('#statusBadge').className = 'badge' + (done ? ' green' : '');
  populateTeamSelects();
  buildTeamsGrid();
  renderResultadosFeed();
  await loadActivas();
  await loadHistorial();
}

async function commitResultado(eq, mainCode, eventCode, force = false) {
  return apiFetch('commit_resultado.php', {
    method: 'POST',
    body: JSON.stringify({
      equipo_id: eq.id,
      resultado_principal: mainCode,
      codigo_secundario: eventCode,
      forzar: force
    })
  });
}

async function procesarEquipo(eq, force = false) {
  $('#currentTeamName').textContent = eq.nombre;
  $('#currentTeamState').textContent = 'Girando ruleta principal…';
  $('#slotCard').style.display = 'none';
  $('#slotTrack').innerHTML = '';
  $('#slotWinner').textContent = '';
  $('#slotPhase').textContent = 'En espera';
  $('#eventSection').style.display = 'none';

  const winnerMain = weightedPick(MAIN_COMPUTED);
  await spinWheel('main', winnerMain, MAIN_COMPUTED);

  const isEvent = winnerMain.cod === 'evento_inesperado';
  $('#mainResult').textContent = winnerMain.label;
  $('#mainResult').className = 'wheel-result ' + (isEvent ? 'res-bad' : 'res-ok');

  let winnerEvent = null;
  if (isEvent) {
    $('#currentTeamState').textContent = 'Girando ruleta de eventos…';
    $('#eventSection').style.display = 'flex';
    winnerEvent = weightedPick(EVENT_COMPUTED);
    await spinWheel('event', winnerEvent, EVENT_COMPUTED);
    $('#eventResult').textContent = winnerEvent.label;
    $('#eventResult').className = 'wheel-result res-bad';
  }

  $('#currentTeamState').textContent = 'Guardando resultado…';
  const server = await commitResultado(eq, winnerMain.cod, winnerEvent ? winnerEvent.cod : null, force);
  if (!server.success) {
    if (server.error === 'ya_procesado') {
      setTeamStatus(eq.id, 'done-ok', 'Ya procesado');
      addLog(`${esc(eq.nombre)}: ya procesado`, 'info');
      return;
    }
    setTeamStatus(eq.id, 'pending', 'Error');
    renderServerError(`Error guardando ${eq.nombre}`, server);
    return;
  }

  G.procesados = Array.from(new Set(G.procesados.concat([eq.id])));
  G.resultados[eq.id] = server;
  $('#statProcesados').textContent = String(G.procesados.length);
  $('#progressLabel').textContent = `${G.procesados.length} / ${G.equipos.length} equipos`;
  $('#progressFill').style.width = G.equipos.length ? `${(G.procesados.length / G.equipos.length) * 100}%` : '0%';

  if (server.resultado_principal === 'evento_inesperado') {
    setTeamStatus(eq.id, 'done-bad', 'Lesión');
    addLog(`${esc(eq.nombre)}: ${esc(server.descripcion_resultado || winnerEvent.label)}`, 'bad');
    const jugadores = (eq.jugadores || []).slice();
    for (const les of (server.lesiones || [])) {
      $('#slotPhase').textContent = 'Seleccionando…';
      const pool = jugadores.length >= 3 ? jugadores : [les].concat(jugadores);
      await runSlot(pool, { id: les.jugador_id, nombre: les.jugador_nombre, dorsal: les.dorsal, foto: les.foto, posicion: '' });
      $('#slotPhase').textContent = 'Seleccionado';
      addLog(`&nbsp;&nbsp;${esc(les.jugador_nombre)} #${esc(les.dorsal)} — ${Number(les.partidos_restantes)} partido(s)`, 'bad');
      await sleep(Math.round(300 / speedMult));
    }
  } else {
    setTeamStatus(eq.id, 'done-ok', 'Sin evento');
    addLog(`${esc(eq.nombre)}: sin evento`, 'ok');
  }

  await loadActivas();
  await loadHistorial();
  const done = G.procesados.length >= G.equipos.length && G.equipos.length > 0;
  $('#statusBadge').textContent = done ? 'Semana completa' : `${G.procesados.length}/${G.equipos.length} procesados`;
  $('#statusBadge').className = 'badge' + (done ? ' green' : '');
}

async function runWeekly(forceAll = false) {
  const btn = $('#btnEjecutarTirada');
  btn.disabled = true;
  $('#liveLog').innerHTML = '';
  resetSpinDisplays();
  await loadData();
  if (!G.equipos.length) {
    btn.disabled = false;
    toast('No hay equipos cargados', 'error');
    return;
  }
  for (const eq of G.equipos) {
    if (!forceAll && G.procesados.includes(eq.id)) {
      setTeamStatus(eq.id, 'done-ok', 'Ya procesado');
      addLog(`${esc(eq.nombre)}: ya procesado`, 'info');
      continue;
    }
    resetSpinDisplays();
    $('#currentTeamName').textContent = eq.nombre;
    await procesarEquipo(eq, forceAll);
    await sleep(Math.round(350 / speedMult));
  }
  btn.disabled = false;
  await loadData();
  toast('Tirada completada', 'success');
}

async function simularEquipo() {
  const id = $('#selectEquipoAdmin').value;
  if (!id) return toast('Selecciona un equipo', 'error');
  const eq = G.equipos.find(e => String(e.id) === id);
  if (!eq) return toast('Equipo no encontrado', 'error');
  $('#liveLog').innerHTML = '';
  resetSpinDisplays();
  await procesarEquipo(eq, true);
  await loadData();
}

async function registrarPartidosIndividual() {
  const id = $('#selectEquipoPartido').value;
  const n = Math.max(1, parseInt($('#inputCantidadPartidos').value || '1', 10));
  if (!id) return toast('Selecciona un equipo', 'error');
  const r = await apiFetch('registrar_partido.php', { method: 'POST', body: JSON.stringify({ equipo_id: id, partidos: n }) });
  if (!r.success) return renderServerError('Error al registrar partidos', r);
  $('#resultadoRegistro').innerHTML = '';
  addLog(`${esc(equipoNombre(id))}: ${n} partido(s) registrados`, 'ok', '#resultadoRegistro');
  await loadData();
}

async function registrarPartidosTodos() {
  const n = Math.max(1, parseInt($('#inputCantidadPartidosTodos').value || '1', 10));
  const r = await apiFetch('registrar_partido.php', { method: 'POST', body: JSON.stringify({ todos: true, partidos: n }) });
  if (!r.success) return renderServerError('Error al registrar partidos', r);
  $('#resultadoRegistro').innerHTML = '';
  addLog(`Todos los equipos: ${n} partido(s) registrados`, 'ok', '#resultadoRegistro');
  await loadData();
}

async function adminResetSemana() {
  const r = await apiFetch('reset_semana.php', { method: 'POST', body: JSON.stringify({}) });
  $('#adminOutput').textContent = JSON.stringify(r, null, 2);
  if (!r.success) return renderServerError('Error reseteando semana', r);
  await loadData();
  toast('Semana reseteada', 'info');
}

async function adminBorrarSemana() {
  const semana = ($('#inputSemanaBorrar').value || '').trim();
  if (!semana) return toast('Indica una semana ISO', 'error');
  const r = await apiFetch('reset_semana.php', { method: 'POST', body: JSON.stringify({ semana }) });
  $('#adminOutput').textContent = JSON.stringify(r, null, 2);
  if (!r.success) return renderServerError('Error borrando semana', r);
  await loadData();
  toast('Semana borrada', 'info');
}

async function adminBorrarTodo() {
  const r = await apiFetch('admin_borrar_datos.php', { method: 'POST', body: JSON.stringify({ confirmar: true }) });
  $('#adminOutput').textContent = JSON.stringify(r, null, 2);
  if (!r.success) return renderServerError('Error borrando datos', r);
  resetSpinDisplays();
  await loadData();
  toast('Datos borrados', 'info');
}

function bindUI() {
  $$('.tab').forEach(tab => tab.addEventListener('click', () => {
    $$('.tab').forEach(t => t.classList.remove('active'));
    $$('.section').forEach(s => s.classList.remove('active'));
    tab.classList.add('active');
    const panel = $('#sec-' + tab.dataset.tab);
    if (panel) panel.classList.add('active');
    if (tab.dataset.tab === 'activas') renderActivas();
    if (tab.dataset.tab === 'historial') loadHistorial();
  }));
  $$('.speed-opt').forEach(btn => btn.addEventListener('click', () => {
    $$('.speed-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    speedMult = SPEEDS[btn.dataset.speed] || SPEEDS.normal;
  }));
  $('#soundToggle').addEventListener('change', e => { soundOn = !!e.target.checked; });
  $('#btnEjecutarTirada').addEventListener('click', () => runWeekly(false));
  $('#btnForzarTirada').addEventListener('click', () => runWeekly(true));
  $('#btnSimularEquipo').addEventListener('click', simularEquipo);
  $('#btnRegistrarPartido').addEventListener('click', registrarPartidosIndividual);
  $('#btnRegistrarPartidoTodos').addEventListener('click', registrarPartidosTodos);
  $('#btnResetSemana').addEventListener('click', adminResetSemana);
  $('#btnBorrarSemana').addEventListener('click', adminBorrarSemana);
  $('#btnBorrarTodo').addEventListener('click', adminBorrarTodo);
  $('#btnClearLog').addEventListener('click', () => { $('#liveLog').innerHTML = ''; });
  $('#filtroActivasEquipo').addEventListener('change', renderActivas);
  $('#filtroHistorialEquipo').addEventListener('change', loadHistorial);
  $('#filtroHistorialEstado').addEventListener('change', loadHistorial);
  $('#filtroHistorialJugador').addEventListener('input', loadHistorial);
}

async function init() {
  buildWheelSVG($('#mainSVG'), MAIN_COMPUTED, 13);
  buildWheelSVG($('#eventSVG'), EVENT_COMPUTED, 11);
  bindUI();
  resetSpinDisplays();
  await loadData();
}

document.addEventListener('DOMContentLoaded', init);
