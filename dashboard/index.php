<?php
// dashboard/index.php
// Puerta de entrada del presidente: login por correo y contraseña. En el paso
// 06 esta misma pantalla pasa a mostrar el dashboard una vez autenticado.

// !headers_sent() ademas del estado: no se puede abrir una sesion despues
// de enviar cabeceras, y el arnes de tests renderiza la pantalla cuando ya
// ha impreso. En una peticion real esta pantalla es el punto de entrada y
// nada ha salido todavia, asi que la sesion se abre igual.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

plEstablecerIdioma(plResolverIdioma());

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $email = (string) ($_POST['email'] ?? '');
    $clave = (string) ($_POST['clave'] ?? '');
    $usuario = plBuscarUsuarioPorEmail($email);

    // Las tres razones de fallo —no existe, contraseña mala, cuenta
    // desactivada— dan el MISMO mensaje. Distinguirlas convertiría el
    // formulario en un oráculo de qué correos están dados de alta.
    if ($usuario !== null
        && !empty($usuario['activo'])
        && password_verify($clave, (string) ($usuario['hash'] ?? ''))) {
        // Regenerar ANTES de escribir el id en la sesión: si se hiciera
        // después, el id ya habría viajado con el identificador de sesión
        // viejo y la protección contra fijación no serviría de nada.
        session_regenerate_id(true);
        $_SESSION['pl_usuario_id'] = $usuario['id'];
        plRedirigir('index.php');
    }

    $error = plT('login.error');
}

$usuario = plUsuarioActual();

// if/else y no un `exit` a mitad de fichero: un exit dentro de un include
// mata el proceso, y el arnés de tests renderiza esta pantalla con include.
// Además, aquí un else dice lo mismo y se lee mejor.
if ($usuario === null):
    plCabecera(plT('login.boton_entrar'));
    ?>
    <?php // El titular copia el del hero de la web: la marca con degradado
          // y, debajo, la línea en Fraunces cursiva y naranja. ?>
    <section class="dash-login">
      <h1 class="hero-title"><span class="grad-text">Superliga Frontier</span><span class="l2"><?= plEsc(plT('login.titulo')) ?></span></h1>
      <p class="ayuda"><?= plEsc(plT('login.subtitulo')) ?></p>

      <?php if ($error !== ''): ?>
        <p class="mal"><?= plEsc($error) ?></p>
      <?php endif; ?>

      <form method="post" class="dash-form">
        <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
        <label class="campo">
          <span><?= plEsc(plT('login.campo_email')) ?></span>
          <input class="inp" type="email" name="email" autocomplete="username" required autofocus>
        </label>
        <label class="campo">
          <span><?= plEsc(plT('login.campo_clave')) ?></span>
          <input class="inp" type="password" name="clave" autocomplete="current-password" required>
        </label>
        <button class="btn btn-primary" type="submit"><?= plEsc(plT('login.boton_entrar')) ?></button>
      </form>
    </section>
    <?php
    plPie();

else:

// -------- autenticado: dashboard del presidente -------------------------
// Todas las cifras se CALCULAN al pintar; ninguna se lee de un campo guardado.
// Un total de salarios almacenado es un total que tarde o temprano se
// desincroniza de la lista de jugadores que dice resumir.

$temporada = plTemporadaActiva();
$fase      = plFaseActiva();
$equipoId  = (string) ($usuario['equipoId'] ?? '');
$equipo    = $equipoId !== '' ? plBuscarEquipo($equipoId) : null;
$enTemp    = ($temporada !== null && $equipoId !== '') ? plEquipoEnTemporada($temporada['id'], $equipoId) : null;

$jugadores   = $enTemp['jugadores'] ?? [];
$ajustes     = $enTemp['ajustes'] ?? [];
$maximo      = (int) ($ajustes['maxJugadores'] ?? 20);
$cap         = (int) ($ajustes['salaryCap'] ?? 250);
$presupuesto = (int) ($ajustes['presupuestoClausulas'] ?? 650);
$salarios    = plTotalSalarios($jugadores);
$clausulas   = plTotalClausulas($jugadores);
$estadoPres  = plEstadoPresupuesto($clausulas, $presupuesto);

// El estado del mercado se DERIVA de la fase, no de un campo aparte: dos
// fuentes para el mismo hecho es una fuente de más.
$mercadoAbierto = $fase === 'MERCADO';

plCabecera(plT('nav.dashboard'), 'dashboard', $fase);
?>
<header class="dash-cabecera">
  <div>
    <?php if ($temporada !== null): ?>
      <span class="eyebrow"><?= plEsc(plT('dash.temporada', ['nombre' => $temporada['nombre'] ?? $temporada['id']])) ?></span>
    <?php endif; ?>
    <h1><?= plEsc(plNombreEquipo($equipo, plT('nav.dashboard'))) ?></h1>
    <p class="lede"><?= plEsc(plT('dash.presidente', ['nombre' => $usuario['nombre'] ?? ''])) ?></p>
  </div>
</header>

<?php if ($equipo === null): ?>

  <p class="mal"><?= plEsc(plT('aviso.sin_equipo')) ?></p>

<?php elseif ($temporada === null): ?>

  <p class="ayuda"><?= plEsc(plT('aviso.sin_temporada')) ?></p>

<?php elseif ($enTemp === null): ?>

  <p class="mal"><?= plEsc(plT('aviso.equipo_fuera')) ?></p>

<?php else: ?>

  <?php
    // Las cuatro tarjetas de la especificación, en su orden. Se describen como
    // datos y se pintan con un solo bucle para no repetir el marcado cuatro veces.
    $tarjetas = [
        [
            'titulo' => plT('dash.tarjeta_plantilla'),
            'cifra'  => count($jugadores) . ' / ' . $maximo,
            'nota'   => '',
        ],
        [
            'titulo' => plT('dash.tarjeta_cap'),
            'cifra'  => plM($salarios) . ' / ' . plM($cap),
            'nota'   => plT('dash.disponibles', ['cifra' => plM(max(0, $cap - $salarios))]),
        ],
        [
            'titulo' => plT('dash.tarjeta_clausulas'),
            'cifra'  => plM($clausulas) . ' / ' . plM($presupuesto),
            'nota'   => $estadoPres === 'COMPLETO'
                ? plT('dash.presupuesto_completo')
                : plT('dash.disponibles', ['cifra' => plM(max(0, $presupuesto - $clausulas))]),
        ],
        [
            'titulo' => plT('dash.tarjeta_mercado'),
            'cifra'  => $mercadoAbierto ? plT('dash.mercado_abierto') : plT('dash.mercado_cerrado'),
            'nota'   => '',
            'estado' => $mercadoAbierto ? 'abierto' : 'cerrado',
        ],
    ];
  ?>
  <section class="dash-tarjetas" aria-label="<?= plEsc(plT('nav.dashboard')) ?>">
    <?php foreach ($tarjetas as $t): ?>
      <div class="card dash-tarjeta"<?= isset($t['estado']) ? ' data-estado="' . plEsc($t['estado']) . '"' : '' ?>>
        <div class="dash-tarjeta-titulo"><?= plEsc($t['titulo']) ?></div>
        <div class="cifra"><?= plEsc($t['cifra']) ?></div>
        <?php if ($t['nota'] !== ''): ?>
          <div class="ayuda"><?= plEsc($t['nota']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="dash-seccion">
    <h2><?= plEsc(plT('dash.mi_plantilla')) ?></h2>

    <?php if ($jugadores === []): ?>
      <?php if (plPuedeEditarPlantilla($fase)): ?>
        <p class="ayuda"><?= plEsc(plT('dash.vacio_roster')) ?></p>
        <a class="btn btn-primary" href="plantilla.php"><?= plEsc(plT('dash.vacio_accion')) ?></a>
      <?php else: ?>
        <p class="ayuda"><?= plEsc(plT('dash.vacio_cerrado')) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col"><?= plEsc(plT('tabla.jugador')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.pos')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.tier')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.salario')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.clausula')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.estado')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jugadores as $j): $pos = (string) ($j['posicion'] ?? ''); ?>
              <tr<?= ($j['estado'] ?? '') === 'CLAUSULADO' ? ' class="clausulado"' : '' ?>>
                <td><?= plEsc($j['nombre'] ?? '') ?></td>
                <td><span class="chip chip-<?= plEsc(strtolower($pos)) ?>"><?= plEsc(plPosicionTexto($pos)) ?></span></td>
                <td class="cifra"><?= plEsc($j['tier'] ?? '') ?></td>
                <td class="cifra"><?= plEsc(plM((int) ($j['salario'] ?? 0))) ?></td>
                <td class="cifra"><?= plEsc(plM((int) ($j['clausula'] ?? 0))) ?></td>
                <td><?= plEsc(plEstadoJugadorTexto($j)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<?php endif; ?>

<?php
plPie();

endif;
