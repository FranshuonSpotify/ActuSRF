<?php

declare(strict_types=1);

/** Finanzas (Fase 2, hub v3): cap tracker, presupuesto de traspasos e informe de movimientos, en su propia página. */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$esAdmin = $permisos->esAdministrador($usuario);

if ($esAdmin) {
    header('Location: admin/salud_liga.php');
    exit;
}

$temporadaActiva = $temporadas->obtenerActiva();
$participacionModoPruebas = ModoPruebas::participacionActiva();
$miParticipacion = null;
if ($temporadaActiva !== null) {
    $miParticipacion = $participacionModoPruebas !== null
        ? $participaciones->buscarPorId($participacionModoPruebas)
        : $participaciones->buscarPorUsuarioYTemporada((int) $usuario['id'], (int) $temporadaActiva['id']);
}

if ($miParticipacion !== null) {
    $participacionId = (int) $miParticipacion['id'];
    $salaryCap = (float) ($miParticipacion['salary_cap_override'] ?? $temporadaActiva['salary_cap']);
    $gastoSalarial = $contratos->gastoSalarial($participacionId);
    $porcentajeCap = $salaryCap > 0 ? min(100, round($gastoSalarial / $salaryCap * 100)) : 0;
    $saludClub = SaludClubCalculadora::calcular(
        $contratoRepositorio->contarActivosPorParticipacion($participacionId),
        $gastoSalarial,
        $salaryCap
    );

    $saldoTraspasos = $financiero->saldoActual($participacionId);
    $movimientos = $financiero->listarMovimientos($participacionId);
}

$paginaTitulo = 'Finanzas';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'finanzas';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Mi club</span>
            <h1 class="h1" style="margin-top:.5rem">Finanzas</h1>
        </div>
    </div>

    <?php if ($miParticipacion === null): ?>
        <div class="empty-state">
            <i class="ph ph-coins"></i>
            <h3>Todavía no presides ningún club</h3>
            <p>Contacta con la administración para que te asignen uno en esta temporada.</p>
        </div>
    <?php else: ?>
        <div class="grid-3">
            <div class="card">
                <span class="caption">Salary Cap</span>
                <div class="h4 mono" style="margin-top:.35rem"><?= number_format($gastoSalarial, 0, ',', '.') ?> € / <?= number_format($salaryCap, 0, ',', '.') ?> €</div>
                <div class="cap-tracker"><div class="cap-tracker-fill <?= $porcentajeCap >= 95 ? 'peligro' : ($porcentajeCap >= 80 ? 'aviso' : '') ?>" style="width:<?= $porcentajeCap ?>%"></div></div>
                <span class="caption"><?= $porcentajeCap ?>% usado</span>
            </div>
            <div class="card">
                <span class="caption">Dinero de traspasos</span>
                <div class="h3 mono" style="margin-top:.35rem"><?= number_format($saldoTraspasos, 0, ',', '.') ?> €</div>
            </div>
            <div class="card">
                <span class="caption">Salud del club</span>
                <div style="display:flex;align-items:center;gap:.5rem;margin-top:.5rem">
                    <span aria-hidden="true" style="width:12px;height:12px;border-radius:50%;display:inline-block;background:<?= ['verde' => 'var(--success,#3ddc9b)', 'ambar' => 'var(--warning,#f2b134)', 'rojo' => 'var(--danger,#f0554a)'][$saludClub['estado']] ?>"></span>
                    <span class="body-sm"><?= htmlspecialchars($saludClub['motivo']) ?></span>
                </div>
            </div>
        </div>

        <h2 class="h2" style="margin-top:2.5rem">Movimientos de dinero de traspasos</h2>
        <?php if ($movimientos === []): ?>
            <div class="empty-state" style="margin-top:1rem">
                <i class="ph ph-receipt"></i>
                <h3>Todavía no hay movimientos</h3>
            </div>
        <?php else: ?>
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th class="num">Importe</th></tr></thead>
                    <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td class="mono caption"><?= htmlspecialchars($m['creado_en']) ?></td>
                            <td><span class="badge <?= (float) $m['importe'] >= 0 ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars(etiqueta_legible($m['tipo'])) ?></span></td>
                            <td><?= htmlspecialchars($m['concepto']) ?></td>
                            <td class="num mono"><?= number_format((float) $m['importe'], 0, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
