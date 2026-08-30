<?php
session_start();
require_once __DIR__ . '/lib.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
    $codigoIntento = stNormalizarTexto($_POST['codigo'] ?? '');
    $pinIntento = stNormalizarTexto($_POST['pin'] ?? '');
    $codigos = stCargarCodigos();

    $equipoEncontrado = null;
    if ($codigoIntento !== '') {
        foreach ($codigos as $id => $c) {
            $codigoGuardado = stNormalizarTexto($c['codigo'] ?? '');
            $pinGuardado = stNormalizarTexto($c['pin'] ?? '');
            if (hash_equals($codigoGuardado, $codigoIntento) && hash_equals($pinGuardado, $pinIntento)) {
                $equipoEncontrado = $id;
                break;
            }
        }
    }

    if ($equipoEncontrado !== null) {
        $_SESSION['st_equipo_id'] = $equipoEncontrado;
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
    }
    $error = 'Código o PIN incorrectos.';
}

$equipoId = $_SESSION['st_equipo_id'] ?? null;
$equipo = null;

if ($equipoId !== null) {
    $data = stCargarDatosOficiales();
    $idx = stBuscarEquipoPorId($data, $equipoId);
    if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
        unset($_SESSION['st_equipo_id']);
        $equipoId = null;
    } else {
        $equipo = $data['equipos'][$idx];
    }
}

$config = stCargarConfig();
$ventanaAbierta = $config['ventana_abierta'];
$guardado = isset($_GET['guardado']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supertécnicas — Superliga Frontier</title>
<link rel="stylesheet" href="../_fuente/styles.css">
<link rel="stylesheet" href="css/supertecnicas.css">
</head>
<body>
<?php if ($equipoId === null): ?>
  <main class="st-login">
    <div class="card st-login-card">
      <h1>Supertécnicas</h1>
      <p class="ayuda">Entra con el código y el PIN de tu equipo.</p>
      <?php if ($error !== ''): ?><p class="mal"><?= stEsc($error) ?></p><?php endif; ?>
      <form method="post" action="index.php" class="st-form">
        <input type="hidden" name="accion" value="login">
        <label class="campo">Código de equipo
          <input class="inp" type="text" name="codigo" required autofocus>
        </label>
        <label class="campo">PIN
          <input class="inp" type="text" name="pin" required>
        </label>
        <button class="btn btn-accent btn-lg" type="submit">Entrar</button>
      </form>
    </div>
  </main>
<?php else: ?>
  <main class="st-roster">
    <header class="st-cabecera">
      <div>
        <h1><?= stEsc($equipo['nombre'] ?? '') ?></h1>
        <p class="ayuda">Asigna hasta 4 supertécnicas por jugador.</p>
      </div>
      <a class="btn btn-secondary" href="logout.php">Cerrar sesión</a>
    </header>

    <?php if ($guardado): ?>
      <p class="ayuda">Guardado.</p>
    <?php endif; ?>

    <?php if (!$ventanaAbierta): ?>
      <p class="st-aviso">La ventana de supertécnicas está cerrada. Puedes ver lo asignado, pero no editarlo.</p>
    <?php endif; ?>

    <form method="post" action="guardar.php">
      <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
      <?php foreach (($equipo['jugadores'] ?? []) as $i => $j): ?>
        <fieldset class="card st-jugador" <?= $ventanaAbierta ? '' : 'disabled' ?>>
          <legend><?= stEsc($j['nombre'] ?? '') ?> · #<?= stEsc($j['dorsal'] ?? '') ?> · <?= stEsc($j['posicion'] ?? '') ?></legend>
          <input type="hidden" name="jugadores[<?= (int) $i ?>][nombre_check]" value="<?= stEsc($j['nombre'] ?? '') ?>">
          <?php
            $slots = $j['supertecnicas'] ?? [];
            for ($s = 0; $s < ST_MAX_SUPERTECNICAS; $s++):
              $st = $slots[$s] ?? ['nombre' => '', 'tipo' => '', 'afinidad' => '', 'especial' => '', 'descripcion' => ''];
          ?>
            <div class="rejilla rejilla-4 st-slot">
              <label class="campo">Nombre
                <input class="inp inp-sm" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][nombre]" value="<?= stEsc($st['nombre'] ?? '') ?>">
              </label>
              <label class="campo">Tipo
                <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][tipo]">
                  <?php foreach (ST_TIPOS as $t): ?>
                    <option value="<?= stEsc($t) ?>" <?= ($st['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? '—' : stEsc($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="campo">Afinidad
                <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][afinidad]">
                  <?php foreach (ST_AFINIDADES as $a): ?>
                    <option value="<?= stEsc($a) ?>" <?= ($st['afinidad'] ?? '') === $a ? 'selected' : '' ?>><?= $a === '' ? '—' : stEsc($a) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="campo">Especial
                <input class="inp inp-sm inp-mono" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][especial]" value="<?= stEsc($st['especial'] ?? '') ?>" placeholder="miximax, tótem…">
              </label>
            </div>
            <label class="campo">Descripción
              <textarea class="inp" maxlength="300" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][descripcion]"><?= stEsc($st['descripcion'] ?? '') ?></textarea>
            </label>
          <?php endfor; ?>
        </fieldset>
      <?php endforeach; ?>

      <?php if ($ventanaAbierta): ?>
        <button class="btn btn-accent btn-lg" type="submit">Guardar supertécnicas</button>
      <?php endif; ?>
    </form>
  </main>
<?php endif; ?>
</body>
</html>
