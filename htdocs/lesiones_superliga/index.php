<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sistema de Lesiones v5.2</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<header class="app-header">
  <div class="header-inner">
    <div class="brand">
      <div class="brand-mark">SF</div>
      <div>
        <div class="brand-name">Superliga Frontier</div>
        <div class="brand-sub">Sistema de lesiones v5.2</div>
      </div>
    </div>
    <div class="header-right">
      <label class="sound-label"><input type="checkbox" id="soundToggle" checked> Sonido</label>
      <div class="speed-group">
        <button class="speed-opt" data-speed="slow">Lenta</button>
        <button class="speed-opt active" data-speed="normal">Normal</button>
        <button class="speed-opt" data-speed="fast">Rápida</button>
      </div>
      <span class="badge week-badge" id="statSemana">Semana —</span>
      <span class="badge" id="statusBadge">0/0 procesados</span>
    </div>
  </div>
</header>

<nav class="tab-bar" role="tablist" aria-label="Secciones">
  <button class="tab active" data-tab="tirada">Tirada semanal</button>
  <button class="tab" data-tab="activas">Lesiones activas</button>
  <button class="tab" data-tab="historial">Historial</button>
  <button class="tab" data-tab="partidos">Registrar partidos</button>
  <button class="tab" data-tab="admin">Admin</button>
</nav>

<main class="main-content">
  <section class="section active" id="sec-tirada">
    <div class="section-header">
      <div>
        <h2 class="section-title">Tirada Semanal</h2>
        <p class="section-desc">85% sin evento · 15% lesión. La ruleta y el texto usan el mismo resultado, y el guardado se confirma en el backend.</p>
      </div>
      <div class="header-controls">
        <button class="btn btn-primary btn-xl" id="btnEjecutarTirada">Ejecutar tirada semanal</button>
      </div>
    </div>

    <div class="kpi-row">
      <div class="kpi-card"><div class="kpi-val" id="statProcesados">0</div><div class="kpi-label">Procesados</div></div>
      <div class="kpi-card"><div class="kpi-val" id="statTotal">0</div><div class="kpi-label">Total equipos</div></div>
      <div class="kpi-card"><div class="kpi-val" id="statLesionesActivas">0</div><div class="kpi-label">Lesiones activas</div></div>
    </div>

    <div class="progress-wrap">
      <div class="progress-label" id="progressLabel">0 / 0 equipos</div>
      <div class="progress-track"><div class="progress-bar" id="progressFill"></div></div>
    </div>

    <div class="current-team glass" id="currentTeamBox">
      <div class="current-team-title">Equipo actual</div>
      <div class="current-team-name" id="currentTeamName">Esperando tirada…</div>
      <div class="current-team-state" id="currentTeamState">Sin actividad</div>
    </div>

    <div class="wheels-layout">
      <div class="wheel-panel glass">
        <div class="wheel-title">Ruleta principal</div>
        <div class="wheel-container">
          <div class="wheel-pointer"></div>
          <svg id="mainSVG" class="wheel-svg" viewBox="0 0 300 300" aria-label="Ruleta principal"></svg>
        </div>
        <div class="wheel-result" id="mainResult">Esperando tirada…</div>
      </div>

      <div class="wheel-panel glass" id="eventSection">
        <div class="wheel-title">Ruleta de eventos</div>
        <div class="wheel-container">
          <div class="wheel-pointer"></div>
          <svg id="eventSVG" class="wheel-svg" viewBox="0 0 300 300" aria-label="Ruleta de eventos"></svg>
        </div>
        <div class="wheel-result" id="eventResult">Esperando…</div>
      </div>
    </div>

    <div class="slot-card glass" id="slotCard">
      <div class="slot-head">
        <div>
          <div class="slot-title">Selección de jugador</div>
          <div class="slot-subtitle" id="slotSubtitle">Solo se muestran y seleccionan jugadores del equipo actual.</div>
        </div>
        <span class="badge" id="slotPhase">En espera</span>
      </div>
      <div class="slot-machine-wrap">
        <div class="slot-window"></div>
        <div class="slot-machine" id="slotMachine"><div class="slot-track" id="slotTrack"></div></div>
      </div>
      <div class="slot-winner" id="slotWinner"></div>
    </div>

    <div class="section-block">
      <h3 class="section-block-title">Progreso por equipos</h3>
      <div class="teams-grid" id="equiposGrid"></div>
    </div>

    <div class="section-block">
      <div class="log-header"><span>Feed de resultados</span><button class="btn btn-ghost btn-sm" id="btnClearLog">Limpiar</button></div>
      <div class="live-log" id="liveLog"></div>
    </div>
  </section>

  <section class="section" id="sec-activas">
    <div class="section-header">
      <div>
        <h2 class="section-title">Lesiones Activas</h2>
        <p class="section-desc">Jugadores lesionados actualmente, con partidos restantes.</p>
      </div>
      <div class="filter-row">
        <select class="select-input" id="filtroActivasEquipo"><option value="">Todos los equipos</option></select>
      </div>
    </div>
    <div id="listaLesionesActivas"></div>
  </section>

  <section class="section" id="sec-historial">
    <div class="section-header">
      <div>
        <h2 class="section-title">Historial de Lesiones</h2>
        <p class="section-desc">Activas y recuperadas, con filtros por equipo, estado y jugador.</p>
      </div>
      <div class="filter-row">
        <select class="select-input" id="filtroHistorialEquipo"><option value="">Todos los equipos</option></select>
        <select class="select-input" id="filtroHistorialEstado"><option value="">Todos los estados</option><option value="activa">Activa</option><option value="recuperado">Recuperado</option></select>
        <input class="input-field" id="filtroHistorialJugador" placeholder="Buscar jugador">
      </div>
    </div>
    <div id="listaHistorial"></div>
  </section>

  <section class="section" id="sec-partidos">
    <div class="section-header">
      <div>
        <h2 class="section-title">Registrar Partidos</h2>
        <p class="section-desc">Descuenta partidos restantes y mueve lesiones a recuperadas cuando lleguen a cero.</p>
      </div>
    </div>
    <div class="partidos-grid">
      <div class="card glass">
        <div class="card-title">Equipo individual</div>
        <div class="form-group"><label class="form-label" for="selectEquipoPartido">Equipo</label><select class="select-input" id="selectEquipoPartido"><option value="">Selecciona un equipo</option></select></div>
        <div class="form-group"><label class="form-label" for="inputCantidadPartidos">Nº de partidos</label><input class="input-field" id="inputCantidadPartidos" type="number" min="1" value="1"></div>
        <button class="btn btn-primary" id="btnRegistrarPartido">Registrar partidos</button>
      </div>
      <div class="card glass">
        <div class="card-title">Todos los equipos</div>
        <div class="form-group"><label class="form-label" for="inputCantidadPartidosTodos">Nº de partidos</label><input class="input-field" id="inputCantidadPartidosTodos" type="number" min="1" value="1"></div>
        <button class="btn btn-warning" id="btnRegistrarPartidoTodos">Registrar en todos</button>
      </div>
    </div>
    <div class="section-block">
      <h3 class="section-block-title">Últimas actualizaciones</h3>
      <div class="live-log" id="resultadoRegistro"></div>
    </div>
  </section>

  <section class="section" id="sec-admin">
    <div class="section-header">
      <div>
        <h2 class="section-title">Administración</h2>
        <p class="section-desc">Simulación, forzado, reset semanal y borrado seguro de datos generados.</p>
      </div>
    </div>
    <div class="admin-grid">
      <div class="card glass">
        <div class="card-title">Simular equipo</div>
        <div class="form-group"><label class="form-label" for="selectEquipoAdmin">Equipo</label><select class="select-input" id="selectEquipoAdmin"><option value="">Selecciona un equipo</option></select></div>
        <button class="btn btn-primary" id="btnSimularEquipo">Simular un solo equipo</button>
      </div>
      <div class="card glass">
        <div class="card-title">Forzar tirada completa</div>
        <button class="btn btn-warning" id="btnForzarTirada">Forzar nueva tirada semanal</button>
      </div>
      <div class="card glass">
        <div class="card-title">Reset semanal</div>
        <button class="btn btn-ghost" id="btnResetSemana">Reset estado semanal</button>
      </div>
      <div class="card glass">
        <div class="card-title">Borrar semana concreta</div>
        <div class="form-group"><label class="form-label" for="inputSemanaBorrar">Semana ISO</label><input class="input-field" id="inputSemanaBorrar" placeholder="2026-W28"></div>
        <button class="btn btn-ghost" id="btnBorrarSemana">Borrar semana</button>
      </div>
      <div class="card glass danger-zone">
        <div class="card-title">Zona de peligro</div>
        <button class="btn btn-danger" id="btnBorrarTodo">Borrar todos los datos</button>
      </div>
    </div>
    <div class="section-block">
      <h3 class="section-block-title">Salida admin</h3>
      <pre class="admin-output" id="adminOutput">Sin acciones ejecutadas todavía.</pre>
    </div>
  </section>
</main>

<div id="toastWrap"></div>
<script src="assets/js/app.js"></script>
</body>
</html>
