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
    $idioma = $GLOBALS['PL_IDIOMA_ACTUAL'] ?? 'es';
    ?><!doctype html>
<html lang="<?= plEsc($idioma) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= plEsc($titulo) ?> · <?= plEsc(plT('login.titulo')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- Mismas familias que _fuente/shell.html: el dashboard usa la tipografia de
     la marca, no la del sistema. -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Teko:wght@500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
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
    ?></main>
<script>
// Confirmación para los botones marcados con data-confirmar (borrar un
// jugador). Se usa el confirm() nativo y no un modal propio porque la acción
// es barata de deshacer en ROSTER —se vuelve a añadir— y un <dialog> por fila
// sería marcado de sobra. Las confirmaciones con consecuencias de verdad (nueva
// temporada, clausulación) sí van en <dialog>. Sin JavaScript, el botón envía
// igual: es una confirmación, no una puerta.
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
</body>
</html>
<?php
}
