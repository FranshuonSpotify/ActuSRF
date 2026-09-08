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

// Iniciales para el avatar del jugador ("Fran Dictador" -> "FD").
function stIniciales($nombre) {
    $partes = preg_split('/\s+/', trim((string) $nombre));
    $partes = array_filter($partes);
    if (!$partes) return '?';
    $ini = mb_substr(reset($partes), 0, 1, 'UTF-8');
    if (count($partes) > 1) $ini .= mb_substr(end($partes), 0, 1, 'UTF-8');
    return mb_strtoupper($ini, 'UTF-8');
}
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
    <div class="st-marca">
      <span class="pip"></span>
      <span>Superliga Frontier</span>
    </div>
    <div class="card st-login-card">
      <h1>Supertécnicas</h1>
      <p class="ayuda">Entra con el código y el PIN de tu equipo.</p>
      <?php if ($error !== ''): ?>
        <p class="mal">
          <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12.01" y2="16.5"/></svg>
          <?= stEsc($error) ?>
        </p>
      <?php endif; ?>
      <form method="post" action="index.php" class="st-form">
        <input type="hidden" name="accion" value="login">
        <label class="campo"><span>Código de equipo</span>
          <input class="inp" type="text" name="codigo" required autofocus autocomplete="off">
        </label>
        <label class="campo"><span>PIN</span>
          <input class="inp inp-mono" type="text" name="pin" required autocomplete="off">
        </label>
        <button class="btn btn-accent btn-lg btn-icon-txt" type="submit">
          Entrar
          <svg class="icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </form>
    </div>
  </main>
<?php else: ?>
  <main class="st-shell">
    <header class="st-cabecera">
      <div>
        <h1><?= stEsc($equipo['nombre'] ?? '') ?></h1>
        <p class="ayuda">Asigna hasta 4 supertécnicas por jugador. Los cambios se publican en la web al guardar.</p>
      </div>
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <span class="st-estado" data-abierta="<?= $ventanaAbierta ? '1' : '0' ?>">
          <span class="punto"></span>
          <?= $ventanaAbierta ? 'Ventana abierta' : 'Ventana cerrada' ?>
        </span>
        <a class="btn btn-secondary btn-icon-txt" href="logout.php">
          <svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Cerrar sesión
        </a>
      </div>
    </header>

    <?php if ($guardado): ?>
      <div class="st-banda st-banda-ok">
        <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Cambios guardados correctamente.
      </div>
    <?php endif; ?>

    <?php if (!$ventanaAbierta): ?>
      <div class="st-banda st-banda-aviso">
        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        La ventana de supertécnicas está cerrada. Puedes ver lo asignado, pero no editarlo.
      </div>
    <?php endif; ?>

    <form method="post" action="guardar.php">
      <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
      <div class="st-lista">
        <?php foreach (($equipo['jugadores'] ?? []) as $i => $j): ?>
          <?php
            $slots = $j['supertecnicas'] ?? [];
            $asignadas = array_values(array_filter($slots, function ($x) { return trim((string) ($x['nombre'] ?? '')) !== ''; }));
          ?>
          <details class="jugador-card">
            <summary class="jugador-resumen">
              <span class="jugador-avatar"><?= stEsc(stIniciales($j['nombre'] ?? '')) ?></span>
              <span class="jugador-info">
                <span class="jugador-nombre"><?= stEsc($j['nombre'] ?? '') ?></span>
                <span class="jugador-meta">#<?= stEsc($j['dorsal'] ?? '') ?> · <?= stEsc($j['posicion'] ?? '') ?></span>
              </span>
              <span class="jugador-chips">
                <?php if (!$asignadas): ?>
                  <span class="chip chip-vacio">Sin asignar</span>
                <?php else: ?>
                  <?php foreach (array_slice($asignadas, 0, 2) as $x): ?>
                    <span class="chip"><?= stEsc($x['nombre']) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($asignadas) > 2): ?>
                    <span class="chip chip-mas">+<?= count($asignadas) - 2 ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </span>
              <svg class="icon icon-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </summary>
            <fieldset class="jugador-editor" <?= $ventanaAbierta ? '' : 'disabled' ?>>
              <input type="hidden" name="jugadores[<?= (int) $i ?>][nombre_check]" value="<?= stEsc($j['nombre'] ?? '') ?>">
              <?php for ($s = 0; $s < ST_MAX_SUPERTECNICAS; $s++):
                $st = $slots[$s] ?? ['nombre' => '', 'tipo' => '', 'afinidad' => '', 'especial' => '', 'descripcion' => ''];
              ?>
                <div class="st-slot">
                  <span class="st-slot-num">Supertécnica <?= $s + 1 ?></span>
                  <div class="rejilla rejilla-4">
                    <label class="campo"><span>Nombre</span>
                      <input class="inp inp-sm" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][nombre]" value="<?= stEsc($st['nombre'] ?? '') ?>" placeholder="Sin usar">
                    </label>
                    <label class="campo"><span>Tipo</span>
                      <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][tipo]">
                        <?php foreach (ST_TIPOS as $t): ?>
                          <option value="<?= stEsc($t) ?>" <?= ($st['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? '—' : stEsc($t) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="campo"><span>Afinidad</span>
                      <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][afinidad]">
                        <?php foreach (ST_AFINIDADES as $a): ?>
                          <option value="<?= stEsc($a) ?>" <?= ($st['afinidad'] ?? '') === $a ? 'selected' : '' ?>><?= $a === '' ? '—' : stEsc($a) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="campo"><span>Especial</span>
                      <input class="inp inp-sm inp-mono" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][especial]" value="<?= stEsc($st['especial'] ?? '') ?>" placeholder="miximax, tótem…">
                    </label>
                  </div>
                  <label class="campo"><span>Descripción</span>
                    <textarea class="inp" maxlength="300" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][descripcion]" placeholder="Efecto de la supertécnica…"><?= stEsc($st['descripcion'] ?? '') ?></textarea>
                  </label>
                </div>
              <?php endfor; ?>
            </fieldset>
          </details>
        <?php endforeach; ?>
      </div>

      <?php if ($ventanaAbierta): ?>
        <div class="st-acciones">
          <button class="btn btn-accent btn-lg btn-icon-txt" type="submit">
            <svg class="icon" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Guardar cambios
          </button>
        </div>
      <?php endif; ?>
    </form>
  </main>
<?php endif; ?>
</body>
</html>
