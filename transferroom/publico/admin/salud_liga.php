<?php

declare(strict_types=1);

/**
 * Salud de la Liga (Fase 2, hub v3, admin): semáforo por club (reutiliza
 * SaludClubCalculadora, ya construido en la tarea 30) + analítica financiera
 * global agregada. Sin especificación previa — diseño propio.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$temporadaActiva = $temporadas->obtenerActiva();
$clubesConSalud = [];

if ($temporadaActiva !== null) {
    foreach ($participaciones->listarPorTemporada((int) $temporadaActiva['id']) as $p) {
        if ($p['estado'] === 'RETIRADA') {
            continue;
        }

        $fichas = $contratoRepositorio->contarActivosPorParticipacion((int) $p['id']);
        $capUsado = $contratoRepositorio->sumaSalariosActivos((int) $p['id']);
        $capMaximo = (float) ($p['salary_cap_override'] ?? $temporadaActiva['salary_cap']);
        $salud = SaludClubCalculadora::calcular($fichas, $capUsado, $capMaximo);

        $clubesConSalud[] = $p + [
            'fichas' => $fichas,
            'cap_usado' => $capUsado,
            'cap_maximo' => $capMaximo,
            'salud_estado' => $salud['estado'],
            'salud_motivo' => $salud['motivo'],
        ];
    }
}

$ordenEstado = ['rojo' => 0, 'ambar' => 1, 'verde' => 2];
usort($clubesConSalud, fn ($a, $b) => $ordenEstado[$a['salud_estado']] <=> $ordenEstado[$b['salud_estado']]);

$totalClubes = count($clubesConSalud);
$porEstado = ['verde' => 0, 'ambar' => 0, 'rojo' => 0];
foreach ($clubesConSalud as $c) {
    $porEstado[$c['salud_estado']]++;
}

$capTotalUsado = array_sum(array_column($clubesConSalud, 'cap_usado'));
$capTotalMaximo = array_sum(array_column($clubesConSalud, 'cap_maximo'));
$promedioFichas = $totalClubes > 0 ? array_sum(array_column($clubesConSalud, 'fichas')) / $totalClubes : 0;
$ratioLigaCap = $capTotalMaximo > 0 ? $capTotalUsado / $capTotalMaximo : 0;

$coloresSemaforo = ['verde' => 'var(--success,#3ddc9b)', 'ambar' => 'var(--warning,#f2b134)', 'rojo' => 'var(--danger,#f0554a)'];

$paginaTitulo = 'Salud de la Liga';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_salud';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Salud de la Liga</h1>
            <p class="caption"><?= $temporadaActiva !== null ? htmlspecialchars($temporadaActiva['nombre']) : 'Sin temporada activa' ?></p>
        </div>
    </div>

    <?php if ($temporadaActiva === null): ?>
        <div class="alert alert-danger"><i class="ph ph-warning"></i> No hay ninguna temporada activa todavía.</div>
    <?php else: ?>
        <div class="grid-3" style="margin-bottom:2rem">
            <div class="card">
                <span class="caption">Clubes por semáforo</span>
                <div style="display:flex;gap:1rem;margin-top:.5rem;align-items:baseline">
                    <span class="mono" style="color:<?= $coloresSemaforo['verde'] ?>;font-size:1.4rem;font-weight:700"><?= $porEstado['verde'] ?></span>
                    <span class="mono" style="color:<?= $coloresSemaforo['ambar'] ?>;font-size:1.4rem;font-weight:700"><?= $porEstado['ambar'] ?></span>
                    <span class="mono" style="color:<?= $coloresSemaforo['rojo'] ?>;font-size:1.4rem;font-weight:700"><?= $porEstado['rojo'] ?></span>
                </div>
                <span class="caption">verde · ámbar · rojo, de <?= $totalClubes ?> club(es)</span>
            </div>
            <div class="card">
                <span class="caption">Gasto salarial de la liga</span>
                <p class="h3 mono" style="margin-top:.3rem"><?= number_format($capTotalUsado, 0, ',', '.') ?> € <span class="caption">de <?= number_format($capTotalMaximo, 0, ',', '.') ?> €</span></p>
                <span class="caption"><?= number_format($ratioLigaCap * 100, 1) ?>% del Salary Cap agregado</span>
            </div>
            <div class="card">
                <span class="caption">Fichas medias por club</span>
                <p class="h3 mono" style="margin-top:.3rem"><?= number_format($promedioFichas, 1) ?> <span class="caption">/ 20</span></p>
            </div>
        </div>

        <?php if ($clubesConSalud === []): ?>
            <div class="empty-state"><i class="ph ph-shield"></i><h3>Todavía no hay clubes inscritos</h3></div>
        <?php else: ?>
            <div class="tbl-wrap">
                <div class="tbl-scroll">
                <table class="tbl">
                    <thead><tr><th></th><th>Club</th><th>Presidente</th><th class="num">Fichas</th><th class="num">Cap usado</th><th class="num">% Cap</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach ($clubesConSalud as $c): ?>
                        <?php $porcentajeCap = $c['cap_maximo'] > 0 ? ($c['cap_usado'] / $c['cap_maximo']) * 100 : 0; ?>
                        <tr>
                            <td><span aria-hidden="true" style="width:10px;height:10px;border-radius:50%;display:inline-block;background:<?= $coloresSemaforo[$c['salud_estado']] ?>"></span></td>
                            <td>
                                <div class="fila-club">
                                    <?php if (!empty($c['escudo_url'])): ?>
                                        <img referrerpolicy="no-referrer" class="escudo" src="<?= htmlspecialchars($c['escudo_url']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($c['club_nombre']) ?>
                                </div>
                            </td>
                            <td class="caption"><?= htmlspecialchars($c['presidente_nombre'] ?? 'Sin presidente') ?></td>
                            <td class="num mono"><?= $c['fichas'] ?>/20</td>
                            <td class="num mono"><?= number_format($c['cap_usado'], 0, ',', '.') ?> €</td>
                            <td class="num mono"><?= number_format($porcentajeCap, 1) ?>%</td>
                            <td class="caption"><?= htmlspecialchars($c['salud_motivo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
