<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notificaciones.php';
requiereRol(['ADMINISTRADOR']);

$id = (int) ($_GET['id'] ?? 0);
$accion = $_GET['accion'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM ofertas WHERE id = ? AND estado = 'PENDIENTE'");
$stmt->execute([$id]);
$oferta = $stmt->fetch();

if ($oferta) {
    if ($accion === 'aprobar') {
        $pdo->prepare("UPDATE ofertas SET estado = 'ACEPTADA', resuelto_en = NOW() WHERE id = ?")->execute([$id]);
        $tipoOrigen = $oferta['tipo'] === 'EXTERNO' ? 'EXTERNO' : 'AGENTE_LIBRE';
        $jugadorId = ficharAgenteLibre($pdo, ['nombre' => $oferta['nombre_jugador'], 'posicion' => $oferta['posicion'], 'foto' => $oferta['foto']], (int) $oferta['club_ofertante_id'], (float) $oferta['salario_ofrecido'], (int) $oferta['contrato_ofrecido_temporadas']);
        registrarHistorial($pdo, (int) $oferta['club_ofertante_id'], $jugadorId, $tipoOrigen === 'EXTERNO' ? 'FICHAJE_EXTERNO' : 'FICHAJE_AGENTE_LIBRE',
            htmlspecialchars($oferta['nombre_jugador']) . ' ficha (validado por administración).', null, (int) $oferta['club_ofertante_id']);
        notificarClub($pdo, (int) $oferta['club_ofertante_id'], 'OFERTA', 'Fichaje aprobado', htmlspecialchars($oferta['nombre_jugador']) . ' ha sido aprobado por la administración.', '/mi_plantilla.php');
    } else {
        $pdo->prepare("UPDATE ofertas SET estado = 'RECHAZADA', resuelto_en = NOW() WHERE id = ?")->execute([$id]);
        notificarClub($pdo, (int) $oferta['club_ofertante_id'], 'OFERTA', 'Fichaje rechazado', htmlspecialchars($oferta['nombre_jugador']) . ' fue rechazado por la administración.', '/ofertas.php');
    }
}
header('Location: ' . BASE_URL . '/admin/index.php');
exit;
