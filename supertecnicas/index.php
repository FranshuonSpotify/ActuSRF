<?php
session_start();
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/i18n.php';

stEstablecerIdioma(stResolverIdioma());

$error = '';

// Generar el código de invitación para el copresidente. Lleva CSRF porque es
// una acción con la sesión ya iniciada; el login de abajo no puede llevarlo,
// porque se envía justo antes de tener sesión.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'invitar') {
    if (!stCsrfValido()) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }
    $yo = stUsuarioActual();
    if ($yo !== null) {
        // El equipo sale de la CUENTA, nunca del formulario: así nadie genera
        // una invitación para un club que no es el suyo.
        $miEquipo = (string) ($yo['equipoId'] ?? '');
        if ($miEquipo !== '' && (stPresidentesPorEquipo()[$miEquipo] ?? 0) < ST_MAX_PRESIDENTES_POR_EQUIPO) {
            stCrearInvitacion($miEquipo, (string) ($yo['id'] ?? ''), (string) ($yo['nombre'] ?? ''));
        }
    }
    header('Location: index.php');
    exit;
}

// Login por cuenta propia (correo + contraseña), igual que dashboard/. El
// código y el PIN por equipo ya no se usan: cada presidente se registra él
// mismo en registro.php y el admin no reparte credenciales una a una.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
    $usuarioIntento = stBuscarUsuarioPorEmail($_POST['email'] ?? '');

    // Las tres razones de fallo —no existe, contraseña mala, cuenta
    // desactivada— dan el MISMO mensaje. Distinguirlas convertiría el
    // formulario en un oráculo de qué correos están dados de alta.
    if ($usuarioIntento !== null
        && !empty($usuarioIntento['activo'])
        && password_verify((string) ($_POST['clave'] ?? ''), (string) ($usuarioIntento['hash'] ?? ''))) {
        // Regenerar ANTES de escribir el id: si se hiciera después, el id ya
        // habría viajado con el identificador de sesión viejo.
        session_regenerate_id(true);
        $_SESSION['st_usuario_id'] = $usuarioIntento['id'];
        header('Location: index.php');
        exit;
    }
    $error = stT('login.error');
}

// El equipo sale de la CUENTA y se resuelve en cada petición, no de un valor
// guardado en la sesión: así, desactivar una cuenta corta el acceso en el
// siguiente clic en vez de esperar a que caduque la sesión.
$usuario = stUsuarioActual();
$equipoId = $usuario === null ? null : (string) ($usuario['equipoId'] ?? '');
$equipo = null;

if ($equipoId !== null && $equipoId !== '') {
    $data = stCargarDatosOficiales();
    $idx = stBuscarEquipoPorId($data, $equipoId);
    if ($idx === null || !empty($data['equipos'][$idx]['archivado'])) {
        $equipoId = null;
    } else {
        $equipo = $data['equipos'][$idx];
    }
} else {
    $equipoId = null;
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
<html lang="<?= stEsc($GLOBALS['ST_IDIOMA_ACTUAL']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= stEsc(stT('login.titulo')) ?> — Superliga Frontier</title>
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
    <?php stRenderSelectorIdioma(); ?>
    <div class="card st-login-card">
      <h1><?= stEsc(stT('login.titulo')) ?></h1>
      <p class="ayuda"><?= stEsc(stT('login.subtitulo')) ?></p>
      <?php if ($error !== ''): ?>
        <p class="mal">
          <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12.01" y2="16.5"/></svg>
          <?= stEsc($error) ?>
        </p>
      <?php endif; ?>
      <form method="post" action="index.php" class="st-form">
        <input type="hidden" name="accion" value="login">
        <label class="campo"><span><?= stEsc(stT('login.campo_email')) ?></span>
          <input class="inp" type="email" name="email" required autofocus autocomplete="username">
        </label>
        <label class="campo"><span><?= stEsc(stT('login.campo_clave')) ?></span>
          <input class="inp" type="password" name="clave" required autocomplete="current-password">
        </label>
        <button class="btn btn-accent btn-lg btn-icon-txt" type="submit">
          <?= stEsc(stT('login.boton_entrar')) ?>
          <svg class="icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </form>
      <p class="ayuda"><a href="registro.php"><?= stEsc(stT('registro.desde_login')) ?></a></p>
    </div>
  </main>
<?php else: ?>
  <main class="st-shell">
    <?php stRenderSelectorIdioma(); ?>
    <header class="st-cabecera">
      <div>
        <h1><?= stEsc($equipo['nombre'] ?? '') ?></h1>
        <p class="ayuda"><?= stEsc(stT('roster.subtitulo')) ?></p>
      </div>
      <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <span class="st-estado" data-abierta="<?= $ventanaAbierta ? '1' : '0' ?>">
          <span class="punto"></span>
          <?= $ventanaAbierta ? stEsc(stT('roster.ventana_abierta')) : stEsc(stT('roster.ventana_cerrada')) ?>
        </span>
        <a class="btn btn-secondary btn-icon-txt" href="logout.php">
          <svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <?= stEsc(stT('roster.cerrar_sesion')) ?>
        </a>
      </div>
    </header>

    <?php
      // La invitación no depende de la ventana: un presidente recién
      // registrado tiene que poder invitar a su copresidente aunque la ventana
      // de supertécnicas esté cerrada.
      $presidentesEquipo = stPresidentesPorEquipo()[$equipoId] ?? 0;
      $invitacion = stInvitacionDe($equipoId);
    ?>
    <div class="st-panel">
      <div class="st-panel-texto">
        <b><?= stEsc(stT('invitacion.titulo')) ?></b>
        <?php if ($presidentesEquipo >= ST_MAX_PRESIDENTES_POR_EQUIPO): ?>
          <span class="ayuda"><?= stEsc(stT('invitacion.completo', ['maximo' => ST_MAX_PRESIDENTES_POR_EQUIPO])) ?></span>
        <?php else: ?>
          <span class="ayuda"><?= stEsc(stT('invitacion.explicacion', ['equipo' => $equipo['nombre'] ?? ''])) ?></span>
          <?php if ($invitacion !== null): ?>
            <span class="mono" style="font-size:1.25rem;letter-spacing:.12em"><?= stEsc($invitacion['codigo'] ?? '') ?></span>
            <span class="ayuda"><?= stEsc(stT('invitacion.aviso_regenerar')) ?></span>
          <?php else: ?>
            <span class="ayuda"><?= stEsc(stT('invitacion.sin_codigo')) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($presidentesEquipo < ST_MAX_PRESIDENTES_POR_EQUIPO): ?>
        <form method="post" action="index.php">
          <input type="hidden" name="csrf" value="<?= stEsc(stTokenCsrf()) ?>">
          <input type="hidden" name="accion" value="invitar">
          <button class="btn btn-secondary btn-icon-txt" type="submit">
            <?= stEsc(stT($invitacion !== null ? 'invitacion.regenerar' : 'invitacion.generar')) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($guardado): ?>
      <div class="st-banda st-banda-ok">
        <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?= stEsc(stT('roster.guardado_ok')) ?>
      </div>
    <?php endif; ?>

    <?php if (!$ventanaAbierta): ?>
      <div class="st-banda st-banda-aviso">
        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <?= stEsc(stT('roster.ventana_cerrada_aviso')) ?>
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
                  <span class="chip chip-vacio"><?= stEsc(stT('roster.sin_asignar')) ?></span>
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
                  <span class="st-slot-num"><?= stEsc(stT('roster.supertecnica')) ?> <?= $s + 1 ?></span>
                  <div class="rejilla rejilla-4">
                    <label class="campo"><span><?= stEsc(stT('campo.nombre')) ?></span>
                      <input class="inp inp-sm" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][nombre]" value="<?= stEsc($st['nombre'] ?? '') ?>" placeholder="<?= stEsc(stT('placeholder.nombre')) ?>">
                    </label>
                    <label class="campo"><span><?= stEsc(stT('campo.tipo')) ?></span>
                      <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][tipo]">
                        <?php foreach (ST_TIPOS as $t): ?>
                          <option value="<?= stEsc($t) ?>" <?= ($st['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? '—' : stEsc(stTipoLabel($t)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="campo"><span><?= stEsc(stT('campo.afinidad')) ?></span>
                      <select class="inp inp-sm" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][afinidad]">
                        <?php foreach (ST_AFINIDADES as $a): ?>
                          <option value="<?= stEsc($a) ?>" <?= ($st['afinidad'] ?? '') === $a ? 'selected' : '' ?>><?= $a === '' ? '—' : stEsc(stAfinidadLabel($a)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="campo"><span><?= stEsc(stT('campo.especial')) ?></span>
                      <input class="inp inp-sm inp-mono" type="text" maxlength="40" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][especial]" value="<?= stEsc($st['especial'] ?? '') ?>" placeholder="<?= stEsc(stT('placeholder.especial')) ?>">
                    </label>
                  </div>
                  <label class="campo"><span><?= stEsc(stT('campo.descripcion')) ?></span>
                    <textarea class="inp" maxlength="300" name="jugadores[<?= (int) $i ?>][st][<?= $s ?>][descripcion]" placeholder="<?= stEsc(stT('placeholder.descripcion')) ?>"><?= stEsc($st['descripcion'] ?? '') ?></textarea>
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
            <?= stEsc(stT('roster.guardar')) ?>
          </button>
        </div>
      <?php endif; ?>
    </form>
  </main>
<?php endif; ?>
</body>
</html>
