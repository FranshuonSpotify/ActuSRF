<?php
// dashboard/admin_tiers.php
// Admin → Tiers: editar el salario de los diez tiers oficiales. Los códigos
// son fijos —ni se crean, ni se renombran, ni se borran—, y los salarios se
// CONGELAN dentro de cada temporada al crearla. Por eso esta pantalla no puede
// romper nada: cambiar un salario solo afecta a las temporadas futuras, y la
// en curso conserva los suyos.
//
// En español y sin pasar por el diccionario de i18n: el panel de admin lo usa
// una sola persona.

// El Basic Auth va antes que cualquier otra cosa.
require_once __DIR__ . '/../config/admin_auth.php';
requerirAdminBasicAuthConClaves('admin_dashboard_user', 'admin_dashboard_pass_hash', 'Dashboard Admin');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';
require_once __DIR__ . '/chrome.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, 'Token CSRF invalido.');
    }

    // Llega un mapa código => salario. plGuardarSalariosTiers() ignora
    // cualquier código que no sea uno de los diez y rechaza el lote entero si
    // un solo salario es negativo o no entero.
    $recibido = $_POST['salario'] ?? [];
    $r = plGuardarSalariosTiers(is_array($recibido) ? $recibido : []);

    $_SESSION['pl_admin_flash'] = $r['ok']
        ? ['mensaje' => 'Salarios guardados. Se aplicarán a las temporadas que se creen a partir de ahora.', 'error' => '']
        : ['mensaje' => '', 'error' => $r['mensaje']];
    plRedirigir('admin_tiers.php');
}

$flash = $_SESSION['pl_admin_flash'] ?? ['mensaje' => '', 'error' => ''];
unset($_SESSION['pl_admin_flash']);

$tiers     = plCargarTiers()['tiers'];
$temporada = plTemporadaActiva();
// Los salarios congelados de la temporada en curso, para enseñarlos al lado:
// el admin tiene que ver que editar aquí no los cambia.
$congelados = [];
if ($temporada !== null) {
    foreach (plCargarTemporada($temporada['id'])['ajustes']['tiers'] ?? [] as $t) {
        $congelados[(string) ($t['codigo'] ?? '')] = (int) ($t['salario'] ?? 0);
    }
}

plCabeceraAdmin('Tiers', 'tiers');
?>
<header class="dash-cabecera">
  <h1>Tiers</h1>
</header>

<?php // El aviso va visible y permanente, arriba, y no en una nota al pie: es
      // la explicación de por qué esta pantalla no puede romper nada, y hay que
      // leerla antes de tocar un número. ?>
<p class="aviso" role="note">
  Estos salarios se congelan al crear una temporada. Cambiarlos solo afecta a
  las temporadas que se creen a partir de ahora; la temporada en curso
  conserva los suyos.
</p>

<?php if (($flash['mensaje'] ?? '') !== ''): ?>
  <p class="banda-ok" role="status"><?= plEsc($flash['mensaje']) ?></p>
<?php endif; ?>
<?php if (($flash['error'] ?? '') !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($flash['error']) ?></p>
<?php endif; ?>

<form method="post" action="admin_tiers.php">
  <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
  <div class="tabla-scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th scope="col">Tier</th>
          <th scope="col">Salario para temporadas nuevas</th>
          <?php if ($temporada !== null): ?>
            <th scope="col">En la temporada <?= plEsc($temporada['nombre'] ?? $temporada['id']) ?></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tiers as $t): $codigo = (string) ($t['codigo'] ?? ''); ?>
          <tr>
            <th scope="row" class="cifra"><?= plEsc($codigo) ?></th>
            <td>
              <label class="sr-only" for="s-<?= plEsc(md5($codigo)) ?>">Salario de <?= plEsc($codigo) ?></label>
              <input class="inp inp-sm inp-mono" id="s-<?= plEsc(md5($codigo)) ?>" type="number" min="0" step="1" inputmode="numeric"
                     name="salario[<?= plEsc($codigo) ?>]" value="<?= plEsc((int) ($t['salario'] ?? 0)) ?>" required>
              <span class="ayuda">M</span>
            </td>
            <?php if ($temporada !== null): ?>
              <td class="cifra"><?= isset($congelados[$codigo]) ? plEsc(plM($congelados[$codigo])) : '—' ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="ayuda">
    Solo existen estos diez tiers. No se pueden crear otros ni borrar ninguno.
  </p>
  <button class="btn btn-accent" type="submit">Guardar salarios</button>
</form>

<?php
plPieAdmin();
