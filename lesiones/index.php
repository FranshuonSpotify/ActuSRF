<?php
// lesiones/index.php
// Vista pública, sin login: lesionados activos, historial de la temporada y
// ranking global de la liga. Cualquier visitante puede verla.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';

leSincronizarRecuperacion();

// Se lee cada fichero una sola vez por petición y se reparte a las tres
// consultas: sin esto, listar equipos con lesionados releía lesiones.json y
// datos_oficiales.json una vez por equipo mostrado.
$equipos   = leCargarEquiposOficiales();
$lesiones  = leCargarLesiones();
$temporada = leTemporadaActual();
usort($equipos, static fn(array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

// is_string y no un cast: ?equipo[]=x llega como array y (string) avisaría
// "Array to string conversion" en una página que puede abrir cualquiera.
$equipoSeleccionado = is_string($_GET['equipo'] ?? null) ? $_GET['equipo'] : '';
if ($equipoSeleccionado !== '' && !in_array($equipoSeleccionado, array_column($equipos, 'id'), true)) {
    $equipoSeleccionado = '';
}

// Mismo motivo que $equipoSeleccionado: is_string(), no cast, para que
// ?division[]=x o ?q[]=x no avisen. leFiltrarEquiposVista() ya ignora
// cualquier $division que no sea SUPERLIGA/ASCENSO, así que aquí no hace
// falta validarla contra una lista.
$division = is_string($_GET['division'] ?? null) ? $_GET['division'] : '';
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';

$ranking = leRankingGlobal($equipos, $lesiones, $temporada);

// En producción config.temporada es solo un número ("4"): sin la palabra
// delante se lee como una cifra suelta, no como el nombre de una temporada.
$temporadaMostrar = $temporada !== '' && ctype_digit($temporada) ? "Temporada $temporada" : $temporada;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lesiones · Superliga Frontier</title>
<meta name="description" content="Lesionados activos, historial y ranking de lesiones de la Superliga Frontier.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Teko:wght@500;600;700&family=Fraunces:ital,opsz,wght@1,9..144,300;1,9..144,400&family=JetBrains+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="../dashboard/css/dashboard.css">
<link rel="stylesheet" href="css/lesiones.css">
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="nav stuck dash-nav-top">
  <div class="wrap nav-in">
    <a class="nav-logo" href="/"><img src="../assets/sf-logo-blanco.png" alt="Superliga Frontier" width="43" height="24"></a>
    <span class="dash-producto">Lesiones</span>
  </div>
</header>
<main class="wrap dash-main" id="contenido">
  <header class="dash-cabecera">
    <div>
      <h1>Lesiones de la temporada</h1>
      <p class="ayuda">
        Lesionados activos, historial y ranking de toda la liga<?php if ($temporadaMostrar !== ''): ?> · <?= leEsc($temporadaMostrar) ?><?php endif; ?>.
      </p>
    </div>
  </header>

  <section class="dash-tarjetas" aria-label="Resumen de la liga">
    <div class="card dash-tarjeta">
      <div class="dash-tarjeta-titulo">Lesiones esta temporada</div>
      <div class="cifra"><?= leEsc((string) $ranking['total_lesiones']) ?></div>
    </div>
    <?php if ($ranking['equipo_mas_golpeado'] !== null): ?>
    <div class="card dash-tarjeta">
      <div class="dash-tarjeta-titulo">Equipo más golpeado</div>
      <div class="cifra"><?= leEsc($ranking['equipo_mas_golpeado']['equipo']) ?></div>
      <div class="ayuda"><?= leEsc((string) $ranking['equipo_mas_golpeado']['partidos_perdidos']) ?> partidos-jugador perdidos</div>
    </div>
    <?php endif; ?>
    <?php if ($ranking['jugador_top'] !== null): ?>
    <div class="card dash-tarjeta">
      <div class="dash-tarjeta-titulo">Jugador con más lesiones</div>
      <div class="cifra"><?= leEsc($ranking['jugador_top']['nombre']) ?></div>
      <div class="ayuda"><?= leEsc((string) $ranking['jugador_top']['cuenta']) ?> lesiones · <?= leEsc($ranking['jugador_top']['equipo']) ?></div>
    </div>
    <?php endif; ?>
  </section>

  <section class="dash-seccion">
    <h2>Filtrar por equipo</h2>
    <form method="get" class="dash-form-fila">
      <label class="campo">
        <span>Equipo</span>
        <select class="inp" name="equipo">
          <option value="">Todos los equipos</option>
          <?php foreach ($equipos as $eq): ?>
            <option value="<?= leEsc($eq['id']) ?>" <?= leEsc($eq['id'] === $equipoSeleccionado ? 'selected' : '') ?>><?= leEsc($eq['nombre']) ?> (<?= leEsc($eq['division']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="campo">
        <span>División</span>
        <select class="inp" name="division">
          <option value="">Todas</option>
          <option value="SUPERLIGA" <?= leEsc($division === 'SUPERLIGA' ? 'selected' : '') ?>>Superliga</option>
          <option value="ASCENSO" <?= leEsc($division === 'ASCENSO' ? 'selected' : '') ?>>Ascenso</option>
        </select>
      </label>
      <label class="campo">
        <span>Buscar equipo o jugador</span>
        <input class="inp" type="search" name="q" value="<?= leEsc($q) ?>">
      </label>
      <button class="btn btn-secondary" type="submit">Filtrar</button>
    </form>
  </section>

  <?php
  $equiposAMostrar = $equipoSeleccionado !== '' ? array_filter($equipos, static fn(array $e): bool => $e['id'] === $equipoSeleccionado) : $equipos;
  // Solo la temporada actual: una coincidencia de nombre en una lesión de una
  // temporada ya cerrada no debe hacer aparecer un equipo en esta vista.
  $lesionesTemporada = array_values(array_filter($lesiones, static fn(array $l): bool => (string) ($l['temporada'] ?? '') === $temporada));
  $vista = leFiltrarEquiposVista($equiposAMostrar, $lesionesTemporada, $division, $q);
  ?>

  <?php if ($vista === []): ?>
    <p class="ayuda">No hay equipos que coincidan con el filtro.</p>
  <?php endif; ?>

  <?php foreach ($vista as $item):
    $eq = $item['equipo'];
    $jugadoresFiltro = $item['jugadores'];
    $activas = leLesionesActivasPorEquipo($eq['id'], $lesiones, $temporada);
    $historial = leHistorialPorEquipo($eq['id'], $lesiones, $temporada);
    if ($jugadoresFiltro !== null) {
        $activas = array_values(array_filter($activas, static fn(array $l): bool => in_array((string) ($l['jugador_id'] ?? ''), $jugadoresFiltro, true)));
        $historial = array_values(array_filter($historial, static fn(array $l): bool => in_array((string) ($l['jugador_id'] ?? ''), $jugadoresFiltro, true)));
    }
  ?>
  <section class="dash-seccion card">
    <h2><?= leEsc($eq['nombre']) ?> <span class="chip"><?= leEsc($eq['division']) ?></span></h2>

    <h3>Lesionados activos</h3>
    <?php if ($activas === []): ?>
      <p class="ayuda">Sin lesionados activos.</p>
    <?php else: ?>
      <ul class="lista-lesionados">
        <?php foreach ($activas as $l): ?>
          <li class="tarjeta-lesionado">
            <?php if (!empty($l['foto'])): ?><img src="<?= leEsc($l['foto']) ?>" alt=""><?php endif; ?>
            <span><?= leEsc($l['jugador_nombre']) ?></span>
            <span class="badge <?= !empty($l['toda_temporada']) ? 'badge-copa' : 'badge-superliga' ?>">
              <?= !empty($l['toda_temporada']) ? 'Fuera toda la temporada' : leEsc((string) $l['partidos_restantes']) . ' partido(s) restantes' ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h3>Historial de la temporada</h3>
    <?php if ($historial === []): ?>
      <p class="ayuda">Sin lesiones registradas esta temporada.</p>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr><th scope="col">Fecha</th><th scope="col">Jugador</th><th scope="col">Duración</th><th scope="col">Estado</th></tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $l): ?>
              <tr>
                <td class="cifra"><?= leEsc(leFormatearFecha((string) ($l['fecha_inicio'] ?? ''))) ?></td>
                <td><?= leEsc($l['jugador_nombre']) ?></td>
                <td class="cifra"><?= !empty($l['toda_temporada']) ? 'Toda la temporada' : leEsc((string) $l['partidos_totales']) . ' partido(s)' ?></td>
                <td><?= leEsc(($l['estado'] ?? '') === 'activa' ? 'Activa' : 'Recuperada') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

</main>
<footer class="dash-pie">
  <div class="wrap dash-pie-in">
    <img src="../assets/sf-logo-blanco.png" alt="" width="43" height="24">
    <a href="/">superligafrontier.es</a>
  </div>
</footer>
</body>
</html>
