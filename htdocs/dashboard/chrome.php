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
</body>
</html>
<?php
}
