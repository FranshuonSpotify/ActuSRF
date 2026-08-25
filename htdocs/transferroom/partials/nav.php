<?php
/**
 * Navegación única de la app (Cap. XVI: una sola versión de cada
 * componente). Requiere $usuario, $base y opcionalmente $activePage ya
 * definidos por el llamador. $esAdmin se deriva aquí mismo con $permisos,
 * ya disponible desde bootstrap.php.
 *
 * Fase 2 (02_ux_diseno/05_rediseno_pantalla_inicio_hub_navegacion_v3.md):
 * hub de navegación con iconos de primer nivel + favoritos + semáforo de
 * salud del club. Los iconos de Mensajes/Notificaciones/Cuenta ya vivían en
 * la barra superior desde antes de la Fase 2 y no se duplican aquí como
 * iconos de hub — sería el mismo destino dos veces (simplificación propia
 * sobre el documento v3, que sí los lista como iconos de hub separados).
 */
$esAdmin = $permisos->esAdministrador($usuario);
$noLeidas = $notificaciones->contarNoLeidas((int) $usuario['id']);
$mensajesNoLeidos = $mensajes->contarNoLeidos($usuario);
$activePage = $activePage ?? '';

$temporadaActivaNav = $temporadas->obtenerActiva();
$miParticipacionNav = null;
$saludClubNav = null;
$miClubNav = null;
if (!$esAdmin && $temporadaActivaNav !== null) {
    $participacionModoPruebasNav = ModoPruebas::participacionActiva();
    $miParticipacionNav = $participacionModoPruebasNav !== null
        ? $participaciones->buscarPorId($participacionModoPruebasNav)
        : $participaciones->buscarPorUsuarioYTemporada((int) $usuario['id'], (int) $temporadaActivaNav['id']);

    if ($miParticipacionNav !== null) {
        $fichasNav = $contratoRepositorio->contarActivosPorParticipacion((int) $miParticipacionNav['id']);
        $capUsadoNav = $contratoRepositorio->sumaSalariosActivos((int) $miParticipacionNav['id']);
        $capMaximoNav = (float) ($miParticipacionNav['salary_cap_override'] ?? $temporadaActivaNav['salary_cap']);
        $saludClubNav = SaludClubCalculadora::calcular($fichasNav, $capUsadoNav, $capMaximoNav);
        $miClubNav = $clubes->buscarPorId((string) $miParticipacionNav['club_id']);
    }
}

// Degradado sutil de marca (Fase 2, rediseño estético): colores reales del
// club (clubes.color1/color2) inyectados como variables CSS. Sin club (o
// admin), body.css cae a --accent por defecto — un patrón de "override si
// hay dato, si no hay valor por defecto razonable", igual que el resto de
// la app trata lo opcional.
$colorClub1Nav = $miClubNav['color1'] ?? null;
$colorClub2Nav = $miClubNav['color2'] ?? null;

$favoritosNav = $favoritos->listarPorUsuario((int) $usuario['id']);

// Un favorito ya no es solo "una de las páginas del hub": puede ser
// cualquier subsección con su propia query string (plantilla.php?participacion_id=X,
// historial_jugador.php?jugador_id=Y) o incluso una pestaña concreta dentro
// de una página con pestañas cliente (mercado.php#agentes-libres, ver ui.js).
// $rutaBaseNav reconstruye la ruta real desde la raíz del sitio (las páginas
// de admin/ perdían el prefijo "admin/" al guardarse solo con basename(),
// bug real: el enlace guardado apuntaba a la raíz en vez de a admin/).
$rutaBaseNav = ($base === '../' ? 'admin/' : '') . basename($_SERVER['PHP_SELF'] ?? '');
$queryActualNav = $_SERVER['QUERY_STRING'] ?? '';
$rutaActualNav = $rutaBaseNav . ($queryActualNav !== '' ? '?' . $queryActualNav : '');
$esFavoritoActualNav = in_array($rutaActualNav, array_column($favoritosNav, 'ruta'), true);
$mantenimientoActivoNav = $configuracion->obtenerBool('modo_mantenimiento_activo');
$mantenimientoMensajeNav = $mantenimientoActivoNav ? $configuracion->obtenerString('modo_mantenimiento_mensaje') : '';

// plantilla.php e historial_club.php exigen un id en la query string (no
// son páginas "sin argumentos" como el resto del hub): sin esto, el icono
// del hub llevaba a un 404 real cada vez que un presidente pulsaba "Mi
// Plantilla" o "Historial" (encontrado en la verificación final, tarea 47).
$rutaPlantillaNav = $miParticipacionNav !== null
    ? 'plantilla.php?participacion_id=' . (int) $miParticipacionNav['id']
    : 'liga_clubes.php';
$rutaHistorialNav = $miParticipacionNav !== null
    ? 'historial_club.php?club_id=' . urlencode((string) $miParticipacionNav['club_id'])
    : 'liga_clubes.php';

$hubPresidente = [
    ['id' => 'resumen', 'icono' => 'ph-house', 'etiqueta' => 'Resumen', 'ruta' => 'dashboard.php', 'desc' => 'El panel de tu club: plantilla, Salary Cap y actividad reciente de un vistazo.'],
    ['id' => 'plantilla', 'icono' => 'ph-users-three', 'etiqueta' => 'Mi Plantilla', 'ruta' => $rutaPlantillaNav, 'desc' => 'Tus jugadores, sus contratos y el estado de tu Salary Cap.'],
    ['id' => 'mercado', 'icono' => 'ph-storefront', 'etiqueta' => 'Mercado', 'ruta' => 'mercado.php', 'desc' => 'Todos los jugadores de la liga: contratados y agentes libres, con pujas.'],
    ['id' => 'liga_clubes', 'icono' => 'ph-shield', 'etiqueta' => 'Liga / Clubes', 'ruta' => 'liga_clubes.php', 'desc' => 'Todos los clubes de la temporada, por división.'],
    ['id' => 'peticiones', 'icono' => 'ph-handshake', 'etiqueta' => 'Peticiones', 'ruta' => 'peticiones.php', 'desc' => 'Tablón donde pides o propones traspasos abiertamente.'],
    ['id' => 'franquicias', 'icono' => 'ph-crown-simple', 'etiqueta' => 'Franquicias', 'ruta' => 'franquicias.php', 'desc' => 'Gestiona tus hasta 4 jugadores franquicia.'],
    ['id' => 'finanzas', 'icono' => 'ph-coins', 'etiqueta' => 'Finanzas', 'ruta' => 'finanzas.php', 'desc' => 'Tu Salary Cap, tu dinero de traspasos y la salud de tu club.'],
    ['id' => 'historial', 'icono' => 'ph-clock-counter-clockwise', 'etiqueta' => 'Historial', 'ruta' => $rutaHistorialNav, 'desc' => 'El historial deportivo y de fichajes de tu club.'],
    ['id' => 'scouting', 'icono' => 'ph-binoculars', 'etiqueta' => 'Scouting', 'ruta' => 'scouting.php', 'desc' => 'Sigue jugadores concretos y recibe aviso si alguien puja por ellos.'],
    ['id' => 'actividad_liga', 'icono' => 'ph-newspaper', 'etiqueta' => 'Actividad de la Liga', 'ruta' => 'actividad_liga.php', 'desc' => 'Feed, prensa, ranking dinámico y calendario de vencimientos.'],
    ['id' => 'mi_estrategia', 'icono' => 'ph-compass', 'etiqueta' => 'Mi Estrategia', 'ruta' => 'mi_estrategia.php', 'desc' => 'Watchlist, objetivos, diario y tu planificador táctico visual.'],
    ['id' => 'ayuda', 'icono' => 'ph-question', 'etiqueta' => 'Ayuda', 'ruta' => 'ayuda.php', 'desc' => 'Guía de reglas, tour guiado del hub y soporte.'],
];

$hubAdmin = [
    ['id' => 'admin_mercado', 'icono' => 'ph-calendar-blank', 'etiqueta' => 'Control de Mercado', 'ruta' => 'admin/temporadas.php', 'desc' => 'Ciclo de vida de la temporada: abrir, cerrar y pausar el mercado.'],
    ['id' => 'admin_clubes', 'icono' => 'ph-shield', 'etiqueta' => 'Gestión de Clubes', 'ruta' => 'admin/clubes.php', 'desc' => 'Alta de clubes, inscripción por temporada y asignación de presidente.'],
    ['id' => 'admin_usuarios', 'icono' => 'ph-users', 'etiqueta' => 'Gestión de Usuarios', 'ruta' => 'admin/usuarios.php', 'desc' => 'Alta manual de cuentas y su rol (administrador/presidente).'],
    ['id' => 'admin_datos', 'icono' => 'ph-database', 'etiqueta' => 'Datos y Sistema', 'ruta' => 'admin/datos_sistema.php', 'desc' => 'Backup, log de errores, estado del entorno y moderación.'],
    ['id' => 'admin_wiki', 'icono' => 'ph-book-open-text', 'etiqueta' => 'Wiki de Jugadores', 'ruta' => 'admin/wiki_jugadores.php', 'desc' => 'Enlaces automáticos a Fandom: estado, revisión manual y backfill.'],
    ['id' => 'admin_fotos', 'icono' => 'ph-image', 'etiqueta' => 'Fotos de Jugadores', 'ruta' => 'admin/fotos_jugadores.php', 'desc' => 'Edita la foto de cualquier jugador. Prioriza sin foto oficial y externos creados por usuarios.'],
    ['id' => 'admin_cambiar_tier', 'icono' => 'ph-arrows-left-right', 'etiqueta' => 'Cambiar Tier / Versión', 'ruta' => 'admin/cambiar_tier.php', 'desc' => 'Cambia el tier de un jugador que cambia de versión; recalcula el salario de su contrato activo si tiene uno.'],
    ['id' => 'admin_config', 'icono' => 'ph-gear', 'etiqueta' => 'Configuración y Reglas', 'ruta' => 'admin/configuracion.php', 'desc' => 'Parámetros de la liga, versionado y plantillas guardadas.'],
    ['id' => 'admin_pendientes', 'icono' => 'ph-tray', 'etiqueta' => 'Bandeja de Pendientes', 'ruta' => 'admin/pendientes.php', 'desc' => 'Fichajes sin revisar, ventanas RFA vencidas y anuncios.'],
    ['id' => 'admin_salud', 'icono' => 'ph-heartbeat', 'etiqueta' => 'Salud de la Liga', 'ruta' => 'admin/salud_liga.php', 'desc' => 'Semáforo por club y analítica financiera de toda la liga.'],
    ['id' => 'admin_pruebas', 'icono' => 'ph-flask', 'etiqueta' => 'Modo pruebas', 'ruta' => 'admin/modo_pruebas.php', 'desc' => 'Actúa como un club sin ejecutar acciones irreversibles.'],
];

$hubActivo = $esAdmin ? $hubAdmin : $hubPresidente;

// Icono real de la página favorita, no una estrella genérica repetida: se
// busca por la ruta base (sin query string) contra el propio hub — así
// favoritar "Ayuda" muestra el icono de interrogación de Ayuda, tal como
// se ve en el resto del riel. Si el favorito es una subsección sin match
// exacto (p. ej. plantilla.php?participacion_id=5), cae a una estrella.
$iconoPorRutaBaseNav = [];
foreach ($hubActivo as $itemNav) {
    $iconoPorRutaBaseNav[strtok($itemNav['ruta'], '?')] = $itemNav['icono'];
}
?>
<?php if ($colorClub1Nav !== null || $colorClub2Nav !== null): ?>
<style>:root{
  <?php if ($colorClub1Nav !== null): ?>--club-c1:<?= htmlspecialchars($colorClub1Nav) ?>;<?php endif; ?>
  <?php if ($colorClub2Nav !== null): ?>--club-c2:<?= htmlspecialchars($colorClub2Nav) ?>;<?php endif; ?>
}</style>
<?php endif; ?>

<?php if ($mantenimientoActivoNav): ?>
  <div style="background:var(--danger, #f0554a);color:#fff;padding:.5rem 1rem;display:flex;justify-content:center;align-items:center;gap:.5rem;font-size:var(--fs-caption);font-weight:600" role="alert">
    <i class="ph ph-warning-circle"></i>
    <span><?= htmlspecialchars($mantenimientoMensajeNav) ?></span>
  </div>
<?php endif; ?>

<?php $modoPruebasParticipacionId = ModoPruebas::participacionActiva(); ?>
<?php if ($modoPruebasParticipacionId !== null): ?>
  <?php
      $modoPruebasParticipacion = $participaciones->buscarPorId($modoPruebasParticipacionId);
      $modoPruebasClub = $modoPruebasParticipacion !== null ? $clubes->buscarPorId($modoPruebasParticipacion['club_id']) : null;
  ?>
  <div style="background:var(--warning, #f2b134);color:#2b1204;padding:.5rem 1rem;display:flex;justify-content:center;align-items:center;gap:.75rem;font-size:var(--fs-caption)">
    <i class="ph ph-flask"></i>
    <span>Estás actuando como <strong><?= htmlspecialchars($modoPruebasClub['nombre'] ?? '—') ?></strong> (modo pruebas, sin acciones irreversibles)</span>
    <form method="post" action="<?= $base ?>admin/modo_pruebas.php" style="margin:0">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
      <input type="hidden" name="accion" value="desactivar">
      <button type="submit" style="background:none;border:none;text-decoration:underline;cursor:pointer;font-size:inherit;color:inherit">Salir</button>
    </form>
  </div>
<?php endif; ?>

<!-- ============ SIDEBAR (escritorio, ≥768px — estilo Football Manager) ============ -->
<aside class="sidebar" aria-label="Navegación principal">
  <a href="<?= $base ?>dashboard.php" class="sidebar-brand tt-rich" data-tt="Transfer Room" data-tt-desc="Ir al panel de tu club">
    <img src="<?= asset_version($base, 'assets/img/icono.png') ?>" alt="Transfer Room" style="height:30px;width:auto">
  </a>

  <?php if ($miClubNav !== null): ?>
    <?php if (!empty($miClubNav['escudo_url'])): ?>
      <img referrerpolicy="no-referrer" class="escudo sidebar-club-escudo" src="<?= htmlspecialchars($miClubNav['escudo_url']) ?>" alt="Escudo de <?= htmlspecialchars($miClubNav['nombre']) ?>" loading="lazy">
    <?php else: ?>
      <span class="escudo sidebar-club-escudo" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-shield"></i></span>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($saludClubNav !== null): ?>
    <?php $colorSaludNav = ['verde' => 'var(--success,#3ddc9b)', 'ambar' => 'var(--warning,#f2b134)', 'rojo' => 'var(--danger,#f0554a)'][$saludClubNav['estado']]; ?>
    <a href="<?= $base ?>finanzas.php" class="sidebar-icon tt-rich" style="position:relative;margin-bottom:var(--space-1);color:<?= $colorSaludNav ?>" data-tt="Salud del club" data-tt-desc="<?= htmlspecialchars($saludClubNav['motivo']) ?>">
      <i class="ph ph-heartbeat"></i>
      <span aria-hidden="true" style="position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:<?= $colorSaludNav ?>;box-shadow:0 0 0 2px rgba(0,0,0,.6)"></span>
    </a>
  <?php endif; ?>

  <div class="sidebar-divider"></div>

  <nav class="sidebar-hub">
    <?php foreach ($hubActivo as $item): ?>
      <a href="<?= $base . $item['ruta'] ?>" class="sidebar-icon tt-rich <?= $activePage === $item['id'] ? 'on' : '' ?>" data-tt="<?= htmlspecialchars($item['etiqueta']) ?>" data-tt-desc="<?= htmlspecialchars($item['desc']) ?>">
        <i class="ph <?= $item['icono'] ?>"></i>
      </a>
    <?php endforeach; ?>

    <?php if ($favoritosNav !== []): ?>
      <!-- Rediseño: nada de flyout flotante (se rompía: bugs reales de
           overflow y de posición fuera de pantalla en dos rondas seguidas).
           Cada favorito es un .sidebar-icon más, en el flujo normal del
           riel — empuja lo de abajo hacia abajo, sin JS de posicionamiento
           y sin nada que se pueda desbordar. El icono es el de la propia
           página (Ayuda favoritada = el icono de interrogación de Ayuda). -->
      <div class="sidebar-divider"></div>
      <span class="sidebar-icon tt-rich" style="color:var(--accent);cursor:default" data-tt="Favoritos" data-tt-desc="<?= count($favoritosNav) ?> página<?= count($favoritosNav) === 1 ? '' : 's' ?> fijada<?= count($favoritosNav) === 1 ? '' : 's' ?>.">
        <i class="ph-fill ph-star"></i>
      </span>
      <?php foreach ($favoritosNav as $fav): ?>
        <?php $iconoFav = $iconoPorRutaBaseNav[strtok($fav['ruta'], '?')] ?? 'ph-star'; ?>
        <a href="<?= $base . htmlspecialchars($fav['ruta']) ?>" class="sidebar-icon tt-rich" data-tt="<?= htmlspecialchars($fav['etiqueta']) ?>" data-tt-desc="Favorito.">
          <i class="ph <?= $iconoFav ?>"></i>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-spacer"></div>

  <button type="button" class="sidebar-icon tt-rich" data-fav-boton data-tt="<?= $esFavoritoActualNav ? 'Quitar de favoritos' : 'Fijar como favorito' ?>" data-tt-desc="Acceso directo a esta página (o a esta pestaña, si la página tiene) desde cualquier parte del hub." onclick="TRnav.alternarFavorito()">
    <i class="<?= $esFavoritoActualNav ? 'ph-fill ph-star' : 'ph ph-star' ?>"></i>
  </button>

  <div class="sidebar-divider"></div>

  <a href="<?= $base ?>mensajes.php" class="sidebar-icon tt-rich" style="position:relative" data-tt="Mensajes" data-tt-desc="Conversaciones con administración y otros presidentes.">
    <i class="ph ph-chat-circle"></i>
    <?php if ($mensajesNoLeidos > 0): ?><span class="mono badge-dot"><?= $mensajesNoLeidos > 9 ? '9+' : $mensajesNoLeidos ?></span><?php endif; ?>
  </a>
  <a href="<?= $base ?>notificaciones.php" class="sidebar-icon tt-rich" style="position:relative" data-tt="Notificaciones" data-tt-desc="Eventos de mercado, contratos y ventanas RFA.">
    <i class="ph ph-bell"></i>
    <?php if ($noLeidas > 0): ?><span class="mono badge-dot"><?= $noLeidas > 9 ? '9+' : $noLeidas ?></span><?php endif; ?>
  </a>
  <a href="<?= $base ?>cuenta.php" class="sidebar-icon tt-rich" data-tt="<?= htmlspecialchars($usuario['nombre']) ?>" data-tt-desc="Tu cuenta: contraseña, notificaciones, accesibilidad y sesiones.">
    <i class="ph ph-user-circle"></i>
  </a>
  <a href="<?= $base ?>logout.php" class="sidebar-icon tt-rich" data-tt="Cerrar sesión" data-tt-desc="Salir de tu cuenta en este dispositivo.">
    <i class="ph ph-sign-out"></i>
  </a>
</aside>

<!-- ============ TOPBAR (móvil, <768px) — rediseño completo =============
     Antes: barra fija + una tira de iconos con scroll horizontal debajo,
     colapsada/expandida con el hamburger. Se retira entero: competía con el
     gesto de scroll, recortaba desplegables contra su propio overflow, y no
     es un patrón móvil reconocible. Ahora: barra fija con solo lo esencial
     (marca, salud, mensajes, notificaciones, hamburguesa) + un menú de
     pantalla completa (drawer) con el hub entero en una sola columna
     vertical. Nada se desborda nunca porque nada intenta caber en una fila:
     nunca hay más de una capa de scroll (vertical, en el propio drawer), y
     cada fila cumple el objetivo táctil de 44px sin necesitar overrides. -->
<nav class="nav nav-topbar-mobile" data-sesion-max-segundos="<?= (int) (ini_get('session.gc_maxlifetime') ?: 1440) ?>">
  <div class="wrap nav-in">
    <!-- Icono sin el texto "Transfer Room" en móvil (nav-brand-text): con el
         texto completo + 3-4 iconos a 44px mínimo (objetivo táctil), el
         contenido no cabía en un iPhone real — se salía del contenedor y,
         como el body usa overflow-x:clip, la hamburguesa (el último icono,
         más a la derecha) quedaba recortada e invisible del todo. Bug real,
         reportado con captura: "no la puedes ni llegar a ver". El icono solo
         basta para identificar la marca; el texto completo ya está en el
         propio drawer al abrirlo. -->
    <a href="<?= $base ?>dashboard.php" class="nav-brand"><img src="<?= asset_version($base, 'assets/img/icono.png') ?>" alt="" style="height:22px;width:auto"><span class="nav-brand-text"> Transfer Room</span></a>

    <?php if ($saludClubNav !== null): ?>
      <span class="tt" data-tt="<?= htmlspecialchars($saludClubNav['motivo']) ?>" style="display:inline-flex;align-items:center;gap:.3rem;margin-left:.5rem" aria-label="Salud del club: <?= htmlspecialchars($saludClubNav['motivo']) ?>">
        <span aria-hidden="true" style="width:10px;height:10px;border-radius:50%;display:inline-block;background:<?= ['verde' => 'var(--success,#3ddc9b)', 'ambar' => 'var(--warning,#f2b134)', 'rojo' => 'var(--danger,#f0554a)'][$saludClubNav['estado']] ?>"></span>
      </span>
    <?php endif; ?>

    <div class="nav-right">
      <!-- Favoritos: solo dentro del drawer (sección "Favoritos"), no
         duplicado aquí. La versión en la topbar combinaba en el mismo botón
         el sistema de tooltip (.tt/data-tt) y el de desplegable
         (data-dropdown) — en un tap los dos se disparaban a la vez y se
         superponían ("panel raro", reportado con captura). El drawer ya
         resuelve el acceso a favoritos sin ese conflicto; no hace falta
         repetirlo. -->
      <a href="<?= $base ?>mensajes.php" class="btn-icon tt" data-tt="Mensajes" style="position:relative">
        <i class="ph ph-chat-circle"></i>
        <?php if ($mensajesNoLeidos > 0): ?>
          <span class="mono" style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;padding:0 .2rem;border-radius:var(--r-full);background:var(--accent);color:#fff;font-size:.5625rem;font-weight:600;display:grid;place-items:center;line-height:1"><?= $mensajesNoLeidos > 9 ? '9+' : $mensajesNoLeidos ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= $base ?>notificaciones.php" class="btn-icon tt" data-tt="Notificaciones" style="position:relative">
        <i class="ph ph-bell"></i>
        <?php if ($noLeidas > 0): ?>
          <span class="mono" style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;padding:0 .2rem;border-radius:var(--r-full);background:var(--accent);color:#fff;font-size:.5625rem;font-weight:600;display:grid;place-items:center;line-height:1"><?= $noLeidas > 9 ? '9+' : $noLeidas ?></span>
        <?php endif; ?>
      </a>
      <button type="button" class="btn-icon" id="nav-burger" aria-expanded="false" aria-controls="nav-drawer" aria-label="Abrir menú">
        <i class="ph ph-list"></i>
      </button>
    </div>
  </div>
</nav>

<div class="nav-drawer-backdrop" id="nav-drawer-backdrop" hidden></div>
<aside class="nav-drawer" id="nav-drawer" aria-label="Menú de navegación" aria-hidden="true">
  <div class="nav-drawer-head">
    <span class="nav-drawer-brand"><img src="<?= asset_version($base, 'assets/img/transferroom.png') ?>" alt="Transfer Room" style="height:26px;width:auto"></span>
    <button type="button" class="btn-icon" id="nav-drawer-close" aria-label="Cerrar menú"><i class="ph ph-x"></i></button>
  </div>
  <div class="nav-drawer-body">
    <nav aria-label="Secciones">
      <?php foreach ($hubActivo as $item): ?>
        <a href="<?= $base . $item['ruta'] ?>" class="nav-drawer-item <?= $activePage === $item['id'] ? 'on' : '' ?>">
          <i class="ph <?= $item['icono'] ?>"></i> <?= htmlspecialchars($item['etiqueta']) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <?php if ($favoritosNav !== []): ?>
      <div class="nav-drawer-sep"></div>
      <div class="nav-drawer-section-label">Favoritos</div>
      <nav aria-label="Páginas favoritas">
        <?php foreach ($favoritosNav as $fav): ?>
          <a href="<?= $base . htmlspecialchars($fav['ruta']) ?>" class="nav-drawer-item"><i class="ph-fill ph-star"></i> <?= htmlspecialchars($fav['etiqueta']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <div class="nav-drawer-sep"></div>
    <button type="button" class="nav-drawer-item" data-fav-boton onclick="TRnav.alternarFavorito()">
      <i class="<?= $esFavoritoActualNav ? 'ph-fill ph-star' : 'ph ph-star' ?>"></i> <?= $esFavoritoActualNav ? 'Quitar de favoritos' : 'Fijar como favorito' ?>
    </button>
    <a href="<?= $base ?>cuenta.php" class="nav-drawer-item"><i class="ph ph-user-circle"></i> <?= htmlspecialchars($usuario['nombre']) ?></a>
    <a href="<?= $base ?>logout.php" class="nav-drawer-item"><i class="ph ph-sign-out"></i> Cerrar sesión</a>
  </div>
</aside>
<script>
(function () {
  var burger = document.getElementById('nav-burger');
  var drawer = document.getElementById('nav-drawer');
  var backdrop = document.getElementById('nav-drawer-backdrop');
  var closeBtn = document.getElementById('nav-drawer-close');
  if (!burger || !drawer || !backdrop) return;

  function abrir() {
    drawer.classList.add('open');
    backdrop.hidden = false;
    // Doble rAF: hidden->visible necesita un frame pintado antes de animar
    // opacity, si no la transición del backdrop no se ve (salta directo a 1).
    requestAnimationFrame(function () { requestAnimationFrame(function () { backdrop.classList.add('open'); }); });
    drawer.setAttribute('aria-hidden', 'false');
    burger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    // Reutiliza el mismo apagado de backdrop-filter que los modales (ver
    // ui.js abrirModal): con el drawer a pantalla completa encima, el blur
    // fijo de .sidebar/.nav no aporta nada y sólo cuesta recomposición.
    document.documentElement.classList.add('modal-open');
    if (closeBtn) closeBtn.focus();
  }
  function cerrar() {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    burger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    document.documentElement.classList.remove('modal-open');
    backdrop.hidden = true;
    burger.focus();
  }

  burger.addEventListener('click', abrir);
  if (closeBtn) closeBtn.addEventListener('click', cerrar);
  backdrop.addEventListener('click', cerrar);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('open')) cerrar();
  });
})();

var TRnav = {
    // Base del sitio para esta página (incluye "admin/" si aplica) — sin
    // esto, favoritar una página de admin guardaba el enlace roto (apuntaba
    // a la raíz, no a admin/), bug real corregido junto con esto.
    rutaBase: '<?= htmlspecialchars($rutaBaseNav, ENT_QUOTES) ?>',
    favoritos: <?= json_encode(array_column($favoritosNav, 'ruta')) ?>,

    // La ruta completa incluye la query string (?participacion_id=5) y el
    // hash de pestaña client-side (#agentes-libres, ver la persistencia de
    // pestañas en el bloque de tabs de este mismo fichero): así se puede
    // favoritar una subsección concreta, no solo la página entera.
    rutaCompleta: function () {
        return TRnav.rutaBase + location.search + location.hash;
    },

    alternarFavorito: function () {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '<?= $base ?>favoritos.php';

        var campos = {
            csrf_token: '<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>',
            accion: 'alternar',
            ruta: TRnav.rutaCompleta(),
            etiqueta: '<?= htmlspecialchars($paginaTitulo ?? 'Página', ENT_QUOTES) ?>',
            volver: location.pathname + location.search + location.hash
        };
        Object.keys(campos).forEach(function (nombre) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = nombre;
            input.value = campos[nombre];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    },

    // Corrige el estado de la estrella tras cargar: el servidor solo conoce
    // ruta + query en el primer render, nunca el hash de pestaña (el
    // navegador no lo envía en la petición). Sin esto, favoritar
    // "mercado.php#agentes-libres" mostraba la estrella vacía aunque esa
    // pestaña concreta ya estuviera fijada.
    actualizarEstrellas: function () {
        var actual = TRnav.rutaCompleta();
        var esFavorito = TRnav.favoritos.indexOf(actual) !== -1;
        document.querySelectorAll('[data-fav-boton]').forEach(function (boton) {
            var icono = boton.querySelector('i');
            if (icono) icono.className = esFavorito ? 'ph-fill ph-star' : 'ph ph-star';
            boton.setAttribute('data-tt', esFavorito ? 'Quitar de favoritos' : 'Fijar como favorito');
        });
    }
};
document.addEventListener('DOMContentLoaded', TRnav.actualizarEstrellas);
window.addEventListener('hashchange', TRnav.actualizarEstrellas);
</script>
