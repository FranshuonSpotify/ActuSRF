<?php
// dashboard/chrome.php
// Las dos mitades del envoltorio de una pantalla de presidente. Existe para
// que ninguna pantalla repita el <head>, los landmarks ni la navegación: si
// cada una llevara su copia, corregir el skip-link obligaría a tocar seis
// ficheros y se olvidaría uno.

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/i18n.php';

// Las cuatro entradas del presidente. Ni una más (§33 de la especificación).
// NO se ocultan según la fase: la pantalla que no toca editar se sirve
// deshabilitada o en solo lectura, con su aviso. Ocultar el enlace haría
// creer que la sección no existe; deshabilitarla y explicar por qué enseña en
// qué fase está la liga.
const PL_NAV = [
    'dashboard' => ['href' => 'index.php',     'clave' => 'nav.dashboard'],
    'plantilla' => ['href' => 'plantilla.php', 'clave' => 'nav.plantilla'],
    'clausulas' => ['href' => 'clausulas.php', 'clave' => 'nav.clausulas'],
    'mercado'   => ['href' => 'mercado.php',   'clave' => 'nav.mercado'],
];

// Nombre de un equipo para enseñarlo en el idioma activo. En inglés usa
// nombre_en si existe —el importador lo trae de datos_oficiales.json, así el
// mercado dice «Mount Olympus» y no «Monte Olimpo»—; si no existe (un equipo
// creado a mano aquí), cae al nombre en español en vez de dejar la celda vacía.
function plNombreEquipo(?array $equipo, string $porDefecto = ''): string
{
    if ($equipo === null) {
        return $porDefecto;
    }
    $idioma = $GLOBALS['PL_IDIOMA_ACTUAL'] ?? 'es';
    if ($idioma === 'en' && trim((string) ($equipo['nombre_en'] ?? '')) !== '') {
        return (string) $equipo['nombre_en'];
    }
    return (string) ($equipo['nombre'] ?? $porDefecto);
}

// Texto del estado de un jugador. Siempre texto, nunca solo un color: «Clausulado
// por Beta» tiene que leerse igual en blanco y negro o con daltonismo.
function plEstadoJugadorTexto(array $jugador): string
{
    if (($jugador['estado'] ?? 'DISPONIBLE') === 'CLAUSULADO') {
        $comprador = plBuscarEquipo((string) ($jugador['clausuladoPor'] ?? ''));
        return plT('estado.clausulado_por', [
            'equipo' => plNombreEquipo($comprador, (string) ($jugador['clausuladoPor'] ?? '?')),
        ]);
    }
    return plT('estado.disponible');
}

// Clase CSS del badge de una fase. El color nunca es la única señal: el badge
// lleva siempre su texto al lado.
function plClaseFase(string $fase): string
{
    return 'fase-' . strtolower($fase);
}

// Imprime el documento hasta <main id="contenido"> abierto.
// $activo es la clave de PL_NAV que se marca con aria-current, o '' si la
// pantalla no está en la navegación (el login).
function plCabecera(string $titulo, string $activo = '', ?string $fase = null): void
{
    plImprimirHead($titulo . ' · ' . plT('login.titulo'), (string) ($GLOBALS['PL_IDIOMA_ACTUAL'] ?? 'es'));
    ?><body>
<a class="skip-link" href="#contenido"><?= plEsc(plT('nav.saltar')) ?></a>
<header class="dash-top">
  <div class="wrap dash-top-int">
    <span class="dash-marca"><?= plEsc(plT('login.titulo')) ?></span>
    <?php if ($activo !== ''): ?>
      <nav class="dash-nav" aria-label="<?= plEsc(plT('nav.dashboard')) ?>">
        <?php foreach (PL_NAV as $clave => $item): ?>
          <a href="<?= plEsc($item['href']) ?>"<?= $clave === $activo ? ' aria-current="page"' : '' ?>><?= plEsc(plT($item['clave'])) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <div class="dash-top-fin">
      <?php if ($fase !== null): ?>
        <span class="fase <?= plEsc(plClaseFase($fase)) ?>"><?= plEsc(plFaseTexto($fase)) ?></span>
      <?php endif; ?>
      <?php plRenderSelectorIdioma(); ?>
      <?php if ($activo !== ''): ?>
        <a class="btn btn-secondary btn-sm" href="logout.php"><?= plEsc(plT('login.cerrar_sesion')) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="wrap dash-main" id="contenido">
<?php
}

function plPie(): void
{
    echo "</main>\n";
    plScriptConfirmar();
    echo "</body>\n</html>\n";
}

// Confirmación para los botones marcados con data-confirmar (borrar un
// jugador, archivar un equipo). Se usa el confirm() nativo y no un modal propio
// porque son acciones baratas de deshacer, y un <dialog> por fila sería marcado
// de sobra. Las que no se pueden deshacer tienen otra vía: la clausulación se
// confirma en el servidor, y la nueva temporada con su propio <dialog>. Sin
// JavaScript el botón envía igual: es una confirmación, no una puerta.
function plScriptConfirmar(): void
{
    ?><script>
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var boton = e.target.closest ? e.target.closest('[data-confirmar]') : null;
    if (boton && !window.confirm(boton.getAttribute('data-confirmar'))) {
      e.preventDefault();
    }
  });
})();
</script>
<?php
}

// ------------------------------------------------------------ panel de admin

// Las siete entradas de la navegación del admin, en el orden de §34 de la
// especificación. Dashboard y Temporada viven en la misma pantalla, y Mercado
// es la vista de corrección de clausulaciones dentro de Plantillas: son siete
// entradas porque son siete cosas que el admin va a buscar, no siete ficheros.
const PL_NAV_ADMIN = [
    'dashboard'   => ['href' => 'admin.php',                         'texto' => 'Dashboard'],
    'equipos'     => ['href' => 'admin_equipos.php',                 'texto' => 'Equipos'],
    'presidentes' => ['href' => 'admin_presidentes.php',             'texto' => 'Presidentes'],
    'plantillas'  => ['href' => 'admin_plantillas.php',              'texto' => 'Plantillas'],
    'tiers'       => ['href' => 'admin_tiers.php',                   'texto' => 'Tiers'],
    'mercado'     => ['href' => 'admin_plantillas.php?vista=mercado', 'texto' => 'Mercado'],
    'temporada'   => ['href' => 'admin.php#temporada',               'texto' => 'Temporada'],
];

// El <head> común. Lo comparten las dos carcasas para que el enlace a las
// fuentes y a las dos hojas de estilo exista en un solo sitio.
function plImprimirHead(string $titulo, string $idioma): void
{
    ?><!doctype html>
<html lang="<?= plEsc($idioma) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= plEsc($titulo) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- Mismas familias que _fuente/shell.html: el dashboard usa la tipografía de
     la marca, no la del sistema. -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Teko:wght@500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/dashboard.css">
</head>
<?php
}

// Carcasa del panel de admin. En español y sin plT(), igual que las pantallas
// que la usan: lo maneja una sola persona hispanohablante.
function plCabeceraAdmin(string $titulo, string $activo): void
{
    plImprimirHead($titulo . ' · Admin · Dashboard de plantillas', 'es');
    $fase = plFaseActiva();
    ?><body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="dash-top dash-top-admin">
  <div class="wrap dash-top-int">
    <span class="dash-marca">Admin · Dashboard de plantillas</span>
    <nav class="dash-nav" aria-label="Panel de administración">
      <?php foreach (PL_NAV_ADMIN as $clave => $item): ?>
        <a href="<?= plEsc($item['href']) ?>"<?= $clave === $activo ? ' aria-current="page"' : '' ?>><?= plEsc($item['texto']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="dash-top-fin">
      <span class="fase <?= plEsc(plClaseFase($fase)) ?>"><?= plEsc($fase) ?></span>
    </div>
  </div>
</header>
<main class="wrap dash-main" id="contenido">
<?php
}

function plPieAdmin(): void
{
    echo "</main>\n";
    plScriptConfirmar();
    echo "</body>\n</html>\n";
}
