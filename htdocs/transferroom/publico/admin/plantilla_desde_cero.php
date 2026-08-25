<?php

declare(strict_types=1);

/**
 * Montar la plantilla de un club desde cero (a petición de Franshu): para
 * clubes creados manualmente con origen "VACIA" que no vienen del JSON
 * oficial. Mezcla en un solo lote atómico dos orígenes de jugador:
 * - Externos recién creados aquí mismo (crearJugadorExterno) — aparecen sin
 *   tier en el mismo selector que el resto, no hace falta un flujo aparte.
 * - Agentes libres que YA existen en la liga (con o sin tier).
 * firmarContratosInicialesEnLote() ya sabe tratar ambos casos (ver
 * ContractEngine): todo o nada, igual que asignacion_masiva.php.
 */

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$participacionId = (int) ($_GET['participacion_id'] ?? $_POST['participacion_id'] ?? 0);
$participacion = $participaciones->buscarPorId($participacionId);
if ($participacion === null) {
    http_response_code(404);
    exit('Participación no encontrada.');
}
$club = $clubes->buscarPorId($participacion['club_id']);

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            ModoPruebas::bloquearSiActivo();
            switch ($_POST['accion'] ?? '') {
                case 'crear_externo':
                    $jugadores->crearJugadorExterno(
                        (string) ($_POST['nombre'] ?? ''),
                        (string) ($_POST['posicion'] ?? ''),
                        trim((string) ($_POST['afinidad'] ?? '')) ?: null,
                        trim((string) ($_POST['foto_url'] ?? '')) ?: null,
                        (int) $usuario['id']
                    );
                    $exito = 'Jugador externo creado. Ya aparece abajo, sin tier, listo para añadir al lote.';
                    break;

                case 'asignar_lote':
                    $jugadorIds = array_map('intval', $_POST['jugador_id'] ?? []);
                    if ($jugadorIds === []) {
                        throw new DomainException('No has marcado ningún jugador para añadir a la plantilla.');
                    }
                    $tierPorJugador = $_POST['tier_id'] ?? [];
                    $salarioPorJugador = $_POST['salario_anual'] ?? [];
                    $duracionPorJugador = $_POST['duracion'] ?? [];

                    $asignaciones = array_map(fn (int $id) => [
                        'jugador_id' => $id,
                        'tier_id' => (int) ($tierPorJugador[$id] ?? 0),
                        'salario_anual' => isset($salarioPorJugador[$id]) && $salarioPorJugador[$id] !== ''
                            ? (float) $salarioPorJugador[$id]
                            : null,
                        'duracion_temporadas' => (int) ($duracionPorJugador[$id] ?? 0),
                    ], $jugadorIds);
                    // Sin salario explícito: firmarContratosInicialesEnLote usa el base del tier.
                    $asignaciones = array_map(static function (array $a): array {
                        if ($a['salario_anual'] === null) {
                            unset($a['salario_anual']);
                        }
                        return $a;
                    }, $asignaciones);

                    $firmados = $contratos->firmarContratosInicialesEnLote($participacionId, $asignaciones, (int) $usuario['id']);
                    $exito = 'Plantilla ampliada: ' . count($firmados) . ' jugadores añadidos con contrato. Ninguno quedó a medias.';
                    break;

                default:
                    $error = 'Acción no reconocida.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$sinTier = $jugadores->listarAgentesLibresSinTier();
$conTier = $jugadores->listarAgentesLibresConTier();
$tiers = $contratos->listarTiers();
$contratados = $contratoRepositorio->contarActivosPorParticipacion($participacionId);
$temporada = $temporadas->buscarPorId((int) $participacion['temporada_id']);
$salarioCap = (float) ($participacion['salary_cap_override'] ?? $temporada['salary_cap']);
$salarioActual = $contratos->gastoSalarial($participacionId);

$paginaTitulo = 'Plantilla desde cero';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_clubes';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Plantilla desde cero — <?= htmlspecialchars($club['nombre'] ?? $participacion['club_id']) ?></h1>
            <p class="caption" style="margin-top:.4rem">Añade jugadores a este club creando externos nuevos o tomándolos de los agentes libres ya existentes en la liga. Todo se firma en un único lote atómico.</p>
        </div>
        <a href="clubes.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Volver a clubes</a>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="grid-3" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <div class="card"><span class="caption">Contratados</span><div class="h2 mono" style="margin-top:.3rem"><?= $contratados ?> / 20</div></div>
        <div class="card"><span class="caption">Salary Cap usado</span><div class="h2 mono" style="margin-top:.3rem"><?= number_format($salarioActual, 0, ',', '.') ?> €</div></div>
        <div class="card"><span class="caption">Salary Cap disponible</span><div class="h2 mono" style="margin-top:.3rem"><?= number_format(max($salarioCap - $salarioActual, 0), 0, ',', '.') ?> €</div></div>
    </div>

    <h2 class="h2" style="margin-top:2rem">Crear jugador externo</h2>
    <p class="caption" style="margin-top:.4rem">Un jugador que no existe en el JSON oficial. Se crea sin club ni tier; aparece abajo, en la pestaña "Sin tier", listo para añadirlo al lote.</p>
    <form method="post" style="margin-top:1rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="accion" value="crear_externo">
        <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">
        <div class="field" style="margin-bottom:0"><label class="field-label" for="pdc-nombre">Nombre</label><input class="input" id="pdc-nombre" name="nombre" required style="height:36px"></div>
        <div class="field" style="margin-bottom:0">
            <label class="field-label" for="pdc-posicion">Posición</label>
            <select class="select" id="pdc-posicion" name="posicion" required style="height:36px">
                <option value="POR">POR</option><option value="DEF">DEF</option><option value="MED">MED</option><option value="DEL">DEL</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0"><label class="field-label" for="pdc-afinidad">Afinidad</label><input class="input" id="pdc-afinidad" name="afinidad" placeholder="Opcional" style="height:36px"></div>
        <div class="field" style="margin-bottom:0"><label class="field-label" for="pdc-foto">Foto (URL)</label><input class="input" id="pdc-foto" name="foto_url" type="url" placeholder="Opcional" style="height:36px"></div>
        <button type="submit" class="btn btn-secondary">Crear externo</button>
    </form>

    <h2 class="h2" style="margin-top:2rem">Añadir al lote (<?= count($sinTier) + count($conTier) ?> agentes libres disponibles)</h2>
    <p class="caption" style="margin-top:.4rem">Marca a quién quieres fichar, ajusta salario/duración y firma todo de una vez. Si uno solo no cabe en el Salary Cap, no se firma ninguno.</p>

    <div class="field" style="max-width:360px;margin-top:1rem">
        <label class="field-label" for="filtro-pdc-nombre">Buscar jugador</label>
        <input class="input" type="search" id="filtro-pdc-nombre" placeholder="Nombre del jugador" oninput="TRpdc.filtrar()">
    </div>

    <div class="tabs" data-tab-group style="margin-top:1rem">
        <button class="on" data-tab-target="pdc-sin-tier">Sin tier (<?= count($sinTier) ?>)</button>
        <button data-tab-target="pdc-con-tier">Con tier (<?= count($conTier) ?>)</button>
    </div>

    <form method="post" id="form-pdc-lote">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="accion" value="asignar_lote">
        <input type="hidden" name="participacion_id" value="<?= $participacionId ?>">

        <div data-tab-panel="pdc-sin-tier">
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="tbl-scroll">
                <table class="tbl" id="tabla-pdc-sin-tier">
                    <thead><tr><th></th><th>Jugador</th><th>Pos.</th><th>Origen</th><th>Tier</th><th>Salario anual</th><th>Duración</th></tr></thead>
                    <tbody>
                    <?php foreach ($sinTier as $j): ?>
                        <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>">
                            <td><input type="checkbox" name="jugador_id[]" value="<?= (int) $j['id'] ?>"></td>
                            <td><strong><?= htmlspecialchars($j['nombre']) ?></strong></td>
                            <td class="caption"><?= htmlspecialchars($j['posicion']) ?></td>
                            <td><span class="badge <?= $j['origen'] === 'EXTERNO' ? 'badge-accent' : '' ?>"><?= $j['origen'] === 'EXTERNO' ? 'Externo' : 'JSON oficial' ?></span></td>
                            <td>
                                <select class="select" name="tier_id[<?= (int) $j['id'] ?>]" style="height:32px;font-size:var(--fs-caption)">
                                    <?php foreach ($tiers as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>" data-salario-base="<?= (float) $t['salario_base'] ?>"><?= htmlspecialchars($t['nombre']) ?> (<?= number_format((float) $t['salario_base'], 0, ',', '.') ?> €)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="input" type="number" name="salario_anual[<?= (int) $j['id'] ?>]" placeholder="Base del tier" min="1" step="1" style="height:32px;width:130px;font-size:var(--fs-caption)"></td>
                            <td>
                                <select class="select" name="duracion[<?= (int) $j['id'] ?>]" style="height:32px;font-size:var(--fs-caption)">
                                    <option value="1">1 temp. (RFA después)</option>
                                    <option value="2">2 temp. (UFA después)</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div data-tab-panel="pdc-con-tier" hidden>
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="tbl-scroll">
                <table class="tbl" id="tabla-pdc-con-tier">
                    <thead><tr><th></th><th>Jugador</th><th>Pos.</th><th>Tier</th><th>Salario anual</th><th>Duración</th></tr></thead>
                    <tbody>
                    <?php foreach ($conTier as $j): ?>
                        <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>">
                            <td><input type="checkbox" name="jugador_id[]" value="<?= (int) $j['id'] ?>"></td>
                            <td><strong><?= htmlspecialchars($j['nombre']) ?></strong></td>
                            <td class="caption"><?= htmlspecialchars($j['posicion']) ?></td>
                            <td class="caption"><?= htmlspecialchars($j['tier_nombre']) ?></td>
                            <td><input class="input" type="number" name="salario_anual[<?= (int) $j['id'] ?>]" placeholder="<?= number_format((float) $j['salario_base'], 0, ',', '.') ?> € (base)" min="1" step="1" style="height:32px;width:150px;font-size:var(--fs-caption)"></td>
                            <td>
                                <select class="select" name="duracion[<?= (int) $j['id'] ?>]" style="height:32px;font-size:var(--fs-caption)">
                                    <option value="1">1 temp. (RFA después)</option>
                                    <option value="2">2 temp. (UFA después)</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:1.5rem">Firmar seleccionados</button>
    </form>
</main>

<script>
var TRpdc = {
    filtrar: function () {
        var nombre = document.getElementById('filtro-pdc-nombre').value.trim().toLowerCase();
        document.querySelectorAll('#tabla-pdc-sin-tier tbody tr, #tabla-pdc-con-tier tbody tr').forEach(function (fila) {
            fila.style.display = fila.dataset.nombre.indexOf(nombre) !== -1 ? '' : 'none';
        });
    }
};
</script>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
