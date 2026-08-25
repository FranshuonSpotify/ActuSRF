<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$participacionId = (int) ($_GET['participacion_id'] ?? $_POST['participacion_id'] ?? 0);
$participacion = $participaciones->buscarPorId($participacionId);

if ($participacion === null) {
    http_response_code(404);
    exit('La participación no existe.');
}

if ($participacion['estado'] === 'RETIRADA') {
    http_response_code(409);
    exit('Este club ya está retirado de esta temporada.');
}

$club = $clubes->buscarPorId($participacion['club_id']);
$totalContratados = $contratoRepositorio->contarActivosPorParticipacion($participacionId);

// Resumen de impacto (05-transfer_room_docs/01_bloqueante_primer_mercado/08, punto 32):
// contar ANTES de ejecutar, para que el admin sepa exactamente qué va a pasar.
$ofertasAgenteLibrePropias = array_filter($mercado->listarMisOfertas($participacionId), fn ($o) => $o['estado'] === 'PENDIENTE');
$ventanasRfaComoOrigen = $mercado->listarVentanasIgualacionDeClub($participacion['club_id']);
$traspasosComoComprador = $traspasos->listarMisOfertasEnviadas($participacionId);
$traspasosComoVendedor = $traspasos->listarOfertasRecibidas($participacionId);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            ModoPruebas::bloquearSiActivo();
            // Orden: cancelar ofertas propias/recibidas → resolver ventanas RFA como
            // origen → liberar plantilla → retirar participación. Cada pieza es de un
            // motor distinto (Mercado, Contratos, Clubes — Ley 19: ninguno depende de
            // otro), así que se orquesta aquí, no dentro de un solo motor.
            $ofertasCanceladas = $mercado->cancelarOfertasPendientesDeParticipacion($participacionId, (int) $usuario['id']);
            $traspasosCancelados = $traspasos->cancelarOfertasPendientesDeParticipacion($participacionId, (int) $usuario['id']);
            $ventanasResueltas = $mercado->resolverVentanasPorRetiradaDeClub($participacion['club_id'], (int) $usuario['id']);
            $totalLiberados = $contratos->liberarPlantillaCompleta($participacionId, (int) $usuario['id']);
            $participaciones->retirarClub($participacionId, (int) $usuario['id']);

            foreach ($traspasosCancelados as $t) {
                $contraparte = $participaciones->buscarPorId($t['contraparte_participacion_id']);
                $notificaciones->crear(
                    $contraparte['usuario_presidente_id'] ?? null,
                    'TRASPASO_CANCELADO_POR_RETIRADA',
                    "Tu oferta de traspaso por {$t['jugador_nombre']} se ha cancelado: el otro club se ha retirado de la temporada.",
                    'dashboard.php'
                );
            }
            foreach ($ventanasResueltas as $v) {
                $ganadora = $participaciones->buscarPorId($v['participacion_ganadora_id']);
                $notificaciones->crear(
                    $ganadora['usuario_presidente_id'] ?? null,
                    'FICHAJE_AGENTE_LIBRE_GANADO',
                    "Fichaje de {$v['jugador_nombre']} confirmado: el club de origen se ha retirado antes de poder igualar.",
                    "plantilla.php?participacion_id={$v['participacion_ganadora_id']}"
                );
            }

            $resumen = "{$club['nombre']} retirado. {$totalLiberados} jugadores liberados como UFA, "
                . count($ofertasCanceladas) . ' pujas propias canceladas, '
                . count($traspasosCancelados) . ' ofertas de traspaso canceladas, '
                . count($ventanasResueltas) . ' ventanas RFA resueltas.';
            header('Location: clubes.php?retirado=' . urlencode($resumen));
            exit;
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}
$paginaTitulo = 'Retirar club';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_clubes';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec" style="max-width:560px">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Retirar club de la temporada</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card" style="border-color:rgba(255,59,59,.3)">
        <div class="alert alert-danger" style="margin-bottom:0">
            <i class="ph ph-warning"></i>
            <span>
                Vas a retirar a <strong><?= htmlspecialchars($club['nombre']) ?></strong> de esta temporada.
                Esta acción no se puede deshacer.
            </span>
        </div>
        <ul style="margin-top:1.25rem;display:flex;flex-direction:column;gap:.4rem">
            <li><strong><?= $totalContratados ?></strong> jugadores contratados pasarán a agentes libres UFA de inmediato.</li>
            <li><strong><?= count($ofertasAgenteLibrePropias) ?></strong> pujas propias en el mercado de agentes libres se cancelarán.</li>
            <li><strong><?= count($traspasosComoComprador) + count($traspasosComoVendedor) ?></strong> ofertas de traspaso (enviadas o recibidas) se cancelarán.</li>
            <li><strong><?= count($ventanasRfaComoOrigen) ?></strong> ventanas RFA donde este club es el de origen se resolverán a favor del ofertante.</li>
        </ul>
        <form method="post" style="margin-top:1.5rem;display:flex;gap:.75rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="participacion_id" value="<?= (int) $participacionId ?>">
            <button type="submit" class="btn btn-danger">Confirmar retirada</button>
            <a href="clubes.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
