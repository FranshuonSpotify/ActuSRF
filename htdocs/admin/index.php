<?php
require_once __DIR__ . '/../includes/auth.php';
requiereRol(['ADMINISTRADOR']);

sincronizarClubesDesdeJson($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_temporada'])) {
    procesarFinDeTemporada($pdo);
    $mensaje = 'Temporada finalizada: contratos reducidos, jugadores sin contrato liberados a agencia libre.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_mercado'])) {
    $actual = $pdo->query("SELECT estado FROM mercado_estado WHERE id = 1")->fetch();
    $nuevo = ($actual['estado'] ?? 'CERRADO') === 'ABIERTO' ? 'CERRADO' : 'ABIERTO';
    $pdo->prepare("UPDATE mercado_estado SET estado = ? WHERE id = 1")->execute([$nuevo]);
    $mensaje = 'Mercado ahora: ' . $nuevo;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actuar_como'])) {
    $_SESSION['club_actuando_id'] = (int) $_POST['club_id'];
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$clubes = $pdo->query(
    "SELECT c.*, u.nombre AS presidente_nombre, u.email AS presidente_email,
     (SELECT COUNT(*) FROM jugadores_club j WHERE j.club_id = c.id AND j.estado='ACTIVO') AS total_jugadores,
     (SELECT COUNT(*) FROM jugadores_club j WHERE j.club_id = c.id AND j.es_franquicia=1) AS total_franquicia
     FROM clubes c LEFT JOIN usuarios u ON u.id = c.usuario_id ORDER BY c.nombre"
)->fetchAll();

$ofertasPendientesExternas = $pdo->query(
    "SELECT o.*, c.nombre AS club_ofertante_nombre FROM ofertas o JOIN clubes c ON c.id = o.club_ofertante_id
     WHERE o.tipo IN ('AGENTE_LIBRE','EXTERNO') AND o.estado = 'PENDIENTE' ORDER BY o.creado_en DESC"
)->fetchAll();

$mercadoEstado = $pdo->query("SELECT estado FROM mercado_estado WHERE id = 1")->fetch()['estado'] ?? 'CERRADO';

include __DIR__ . '/../includes/header.php';
?>
<section>
  <div class="page-head"><h2>Panel de Administración</h2><p>Gestión global de la liga: clubes, mercado, fin de temporada y validaciones.</p></div>
  <?php if (!empty($mensaje)): ?><p class="success"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>

  <div class="kpi-row">
    <div class="kpi-card"><div class="kpi-icon">🏟️</div><div><div class="kpi-value"><?php echo count($clubes); ?></div><div class="kpi-label">Clubes</div></div></div>
    <div class="kpi-card"><div class="kpi-icon">🛒</div><div><div class="kpi-value"><?php echo $mercadoEstado; ?></div><div class="kpi-label">Mercado</div></div></div>
    <div class="kpi-card"><div class="kpi-icon">📨</div><div><div class="kpi-value"><?php echo count($ofertasPendientesExternas); ?></div><div class="kpi-label">Externas x validar</div></div></div>
  </div>

  <div class="grid-2">
    <form method="POST" class="card">
      <div class="card-head"><h3>Estado del mercado</h3></div>
      <span class="status-pill status-<?php echo strtolower($mercadoEstado); ?>"><?php echo $mercadoEstado; ?></span>
      <button type="submit" name="toggle_mercado" value="1" class="btn-orange" style="margin-top:1rem;width:auto;">Cambiar a <?php echo $mercadoEstado==='ABIERTO'?'CERRADO':'ABIERTO'; ?></button>
    </form>
    <form method="POST" class="card">
      <div class="card-head"><h3>Fin de temporada</h3></div>
      <p style="color:var(--text2);font-size:0.82rem;">Reduce 1 temporada de contrato a todos los jugadores activos; libera a los que llegan a 0.</p>
      <button type="submit" name="finalizar_temporada" value="1" class="btn-secondary" style="margin-top:1rem;width:auto;" onclick="return confirm('¿Seguro? Esta acción afecta a toda la liga.')">Finalizar temporada</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Clubes y presidentes</h3></div>
    <div class="table-scroll">
      <table class="tbl">
        <thead><tr><th>Club</th><th>Presidente</th><th>Jugadores</th><th>Franquicia</th><th>Salary Cap</th><th>Acción</th></tr></thead>
        <tbody>
          <?php foreach ($clubes as $c): ?>
          <tr>
            <td class="player-cell"><?php if($c['escudo']):?><img src="<?php echo htmlspecialchars($c['escudo']); ?>"><?php endif; ?><span><?php echo htmlspecialchars($c['nombre']); ?></span></td>
            <td><?php echo $c['presidente_nombre'] ? htmlspecialchars($c['presidente_nombre']) : '<span style="color:var(--text3);">Sin asignar</span>'; ?></td>
            <td><?php echo (int)$c['total_jugadores']; ?></td>
            <td><?php echo (int)$c['total_franquicia']; ?>/<?php echo FRANQUICIA_OBJETIVO; ?> <?php if((int)$c['total_franquicia']!==FRANQUICIA_OBJETIVO):?>⚠️<?php endif; ?></td>
            <td><?php echo number_format((float)$c['salary_cap'],0); ?> €</td>
            <td>
              <?php if ($c['usuario_id']): ?>
              <form method="POST"><input type="hidden" name="club_id" value="<?php echo $c['id']; ?>"><button type="submit" name="actuar_como" value="1" class="btn-outline btn-sm">Actuar como (pruebas)</button></form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Ofertas externas / agentes libres por validar</h3></div>
    <?php if (empty($ofertasPendientesExternas)): ?><p style="color:var(--text2);">Nada pendiente.</p><?php endif; ?>
    <?php foreach ($ofertasPendientesExternas as $o): ?>
      <div class="nego-item">
        <div class="nego-photo"></div>
        <div class="nego-info"><strong><?php echo htmlspecialchars($o['nombre_jugador']); ?></strong><span><?php echo htmlspecialchars($o['club_ofertante_nombre']); ?> · <?php echo number_format($o['salario_ofrecido'],0); ?> €</span></div>
        <a href="<?php echo BASE_URL; ?>/admin/validar_oferta.php?id=<?php echo $o['id']; ?>&accion=aprobar" class="btn-outline btn-sm">Aprobar</a>
        <a href="<?php echo BASE_URL; ?>/admin/validar_oferta.php?id=<?php echo $o['id']; ?>&accion=rechazar" class="btn-secondary btn-sm">Rechazar</a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
