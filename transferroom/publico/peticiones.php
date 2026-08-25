<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$esAdmin = $permisos->esAdministrador($usuario);

$error = null;
$exito = null;

// Bug real corregido (Fase 2, ronda de feedback): antes un admin siempre
// acababa con la primera participación de la temporada, sin mirar Modo
// Pruebas — inconsistente con mercado.php/finanzas.php/dashboard.php, que sí
// respetan qué club está actuando el admin de verdad.
$temporadaActiva = $temporadas->obtenerActiva();
$miParticipacion = null;
if ($temporadaActiva !== null) {
    $participacionModoPruebasPeticiones = ModoPruebas::participacionActiva();
    if ($esAdmin && $participacionModoPruebasPeticiones !== null) {
        $miParticipacion = $participaciones->buscarPorId($participacionModoPruebasPeticiones);
    } else {
        foreach ($participaciones->listarPorTemporada((int) $temporadaActiva['id']) as $p) {
            if ((int) ($p['usuario_presidente_id'] ?? 0) === (int) $usuario['id']) {
                $miParticipacion = $p;
                break;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear' && $miParticipacion !== null) {
    try {
        $posicionesPost = array_filter((array) ($_POST['posiciones'] ?? []));
        $topeSalarialPost = (string) ($_POST['tope_salarial'] ?? '') !== '' ? (float) $_POST['tope_salarial'] : null;
        $afinidadPost = (string) ($_POST['afinidad'] ?? '') !== '' ? (string) $_POST['afinidad'] : null;
        $tierMinimoPost = (string) ($_POST['tier_minimo_id'] ?? '') !== '' ? (int) $_POST['tier_minimo_id'] : null;

        $peticiones->crear(
            (int) $miParticipacion['id'],
            (string) ($_POST['descripcion'] ?? ''),
            (int) $usuario['id'],
            $posicionesPost !== [] ? array_values($posicionesPost) : null,
            $topeSalarialPost,
            $afinidadPost,
            $tierMinimoPost
        );
        $exito = 'Petición publicada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar' && $miParticipacion !== null) {
    try {
        $peticiones->cancelar((int) ($_POST['peticion_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Petición cancelada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'proponer' && $miParticipacion !== null) {
    try {
        $jugadorPropuestoId = (string) ($_POST['jugador_id'] ?? '') !== '' ? (int) $_POST['jugador_id'] : null;
        $peticionOrigen = $peticiones->buscarPorId((int) ($_POST['peticion_id'] ?? 0));
        $peticiones->proponer(
            (int) ($_POST['peticion_id'] ?? 0),
            (int) $miParticipacion['id'],
            $jugadorPropuestoId,
            (string) ($_POST['mensaje'] ?? ''),
            (int) $usuario['id']
        );
        if ($peticionOrigen !== null) {
            $participacionDestino = $participaciones->buscarPorId((int) $peticionOrigen['participacion_id']);
            $notificaciones->crear(
                $participacionDestino['usuario_presidente_id'] ?? null,
                'PROPUESTA_PETICION_RECIBIDA',
                'Tienes una nueva propuesta a tu petición.',
                'peticiones.php'
            );
        }
        $exito = 'Propuesta enviada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'aceptar_propuesta' && $miParticipacion !== null) {
    try {
        $propuestaId = (int) ($_POST['propuesta_id'] ?? 0);
        $propuestaAceptada = $peticiones->listarPropuestas((int) ($_POST['peticion_id'] ?? 0));
        $peticiones->aceptarPropuesta($propuestaId, (int) $usuario['id']);

        foreach ($propuestaAceptada as $prop) {
            if ((int) $prop['id'] === $propuestaId) {
                $participacionProponente = $participaciones->buscarPorId((int) $prop['participacion_id']);
                $notificaciones->crear(
                    $participacionProponente['usuario_presidente_id'] ?? null,
                    'PROPUESTA_PETICION_ACEPTADA',
                    'Tu propuesta ha sido aceptada. Formaliza el traspaso o fichaje por el canal normal.',
                    'dashboard.php'
                );
                break;
            }
        }
        $exito = 'Propuesta aceptada. El resto de propuestas de esta petición se han cerrado automáticamente.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$misPeticiones = $miParticipacion !== null ? $peticiones->listarPorParticipacion((int) $miParticipacion['id']) : [];
$peticionesAjenas = array_filter(
    $peticiones->listarAbiertas(),
    fn ($p) => $miParticipacion === null || (int) $p['participacion_id'] !== (int) $miParticipacion['id']
);
$miPlantilla = $miParticipacion !== null ? $jugadores->listarPlantilla((int) $miParticipacion['id']) : [];
$tiersDisponibles = $contratos->listarTiers();

// Salario actual por jugador (Fase 2, peticiones estructuradas): para poder
// comparar contra el tope salarial que pida una petición ajena.
$salarioPorJugador = [];
if ($miParticipacion !== null) {
    foreach ($contratoRepositorio->listarPorParticipacion((int) $miParticipacion['id']) as $c) {
        $salarioPorJugador[(int) $c['jugador_id']] = (float) $c['salario_anual'];
    }
}
$miPlantillaConDetalle = array_map(function (array $j) use ($salarioPorJugador): array {
    $j['salario_anual'] = $salarioPorJugador[(int) $j['id']] ?? null;

    return $j;
}, $miPlantilla);

$paginaTitulo = 'Peticiones';
$base = '';
include __DIR__ . '/../partials/head.php';

// Renderiza los filtros estructurados de una petición como chips — se usa
// tanto en "Mis peticiones" como en "Peticiones abiertas de otros clubes".
function renderizar_filtros_peticion(array $p): void
{
    $posiciones = !empty($p['posiciones']) ? (json_decode((string) $p['posiciones'], true) ?? []) : [];
    if ($posiciones === [] && $p['tope_salarial'] === null && empty($p['afinidad']) && $p['tier_minimo_id'] === null) {
        return;
    }
    echo '<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.5rem">';
    foreach ($posiciones as $pos) {
        echo '<span class="chip">' . htmlspecialchars($pos) . '</span>';
    }
    if ($p['tope_salarial'] !== null) {
        echo '<span class="chip">Tope ' . number_format((float) $p['tope_salarial'], 0, ',', '.') . ' €</span>';
    }
    if (!empty($p['afinidad'])) {
        echo '<span class="chip">' . htmlspecialchars($p['afinidad']) . '</span>';
    }
    if (!empty($p['tier_minimo_nombre'])) {
        echo '<span class="chip">' . htmlspecialchars($p['tier_minimo_nombre']) . ' o superior</span>';
    }
    echo '</div>';
}

$activePage = 'peticiones';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Tablón</span>
            <h1 class="h1" style="margin-top:.5rem">Peticiones</h1>
            <p class="body-sm" style="margin-top:.4rem;max-width:70ch">Publica una necesidad de tu club y recibe propuestas de otros presidentes. Una petición nunca es una negociación: aceptar una propuesta solo cierra el resto, el traspaso o fichaje real se formaliza aparte, en Mercado.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ($miParticipacion !== null): ?>
        <div class="card" style="max-width:640px">
            <h2 class="h3">Publicar una necesidad</h2>
            <p class="caption" style="margin-top:.3rem">Los filtros son opcionales: si los rellenas, quien vea tu petición sabrá al instante qué jugadores suyos encajan, en vez de tener que leer y adivinar.</p>
            <form method="post" style="margin-top:1.25rem">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="crear">
                <div class="field">
                    <label class="field-label" for="pet-desc">Descripción</label>
                    <input class="input" id="pet-desc" type="text" name="descripcion" maxlength="500" placeholder="Ej: busco defensa tier B o superior" required>
                </div>
                <div class="field">
                    <span class="field-label">Posiciones (opcional)</span>
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.3rem">
                        <?php foreach (['POR', 'DEF', 'MED', 'DEL'] as $pos): ?>
                            <label style="display:flex;align-items:center;gap:.4rem;min-height:24px">
                                <input type="checkbox" name="posiciones[]" value="<?= $pos ?>"> <?= $pos ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="pet-tope">Tope salarial (opcional)</label>
                    <input class="input" id="pet-tope" type="number" name="tope_salarial" min="1" step="1" placeholder="Sin límite">
                </div>
                <div class="field">
                    <label class="field-label" for="pet-afinidad">Afinidad (opcional)</label>
                    <input class="input" id="pet-afinidad" type="text" name="afinidad" maxlength="60" placeholder="Cualquiera">
                </div>
                <div class="field">
                    <label class="field-label" for="pet-tier">Tier mínimo (opcional)</label>
                    <select class="select" id="pet-tier" name="tier_minimo_id">
                        <option value="">Cualquiera</option>
                        <?php foreach ($tiersDisponibles as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?> o superior</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Publicar</button>
            </form>
        </div>

        <h2 class="h2" style="margin-top:2.5rem">Mis peticiones</h2>
        <?php if ($misPeticiones === []): ?>
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="empty-state">
                    <i class="ph ph-clipboard-text"></i>
                    <h3>Sin peticiones todavía</h3>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($misPeticiones as $p): ?>
                <div class="card" style="margin-top:1rem">
                    <div style="display:flex;justify-content:space-between;align-items:start;gap:1rem;flex-wrap:wrap">
                        <div>
                            <span class="badge <?= $p['estado'] === 'ABIERTA' ? 'badge-success' : '' ?>"><?= htmlspecialchars(etiqueta_legible($p['estado'])) ?></span>
                            <p class="body-sm" style="margin-top:.5rem"><?= htmlspecialchars($p['descripcion']) ?></p>
                            <?php renderizar_filtros_peticion($p); ?>
                        </div>
                        <?php if ($p['estado'] === 'ABIERTA'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <input type="hidden" name="accion" value="cancelar">
                                <input type="hidden" name="peticion_id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Cancelar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php $propuestas = $peticiones->listarPropuestas((int) $p['id']); ?>
                    <?php if ($propuestas !== []): ?>
                        <table class="tbl" style="margin-top:1rem">
                            <thead><tr><th>Club</th><th>Jugador propuesto</th><th>Mensaje</th><th>Estado</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php foreach ($propuestas as $prop): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($prop['club_nombre']) ?></td>
                                        <td><?= htmlspecialchars($prop['jugador_nombre'] ?? '—') ?></td>
                                        <td class="body-sm"><?= htmlspecialchars($prop['mensaje']) ?></td>
                                        <td><span class="badge"><?= htmlspecialchars(etiqueta_legible($prop['estado'])) ?></span></td>
                                        <td>
                                            <?php if ($prop['estado'] === 'PENDIENTE' && $p['estado'] === 'ABIERTA'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                                    <input type="hidden" name="accion" value="aceptar_propuesta">
                                                    <input type="hidden" name="peticion_id" value="<?= (int) $p['id'] ?>">
                                                    <input type="hidden" name="propuesta_id" value="<?= (int) $prop['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary">Aceptar</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="caption">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="h2" style="margin-top:3rem">Peticiones abiertas de otros clubes</h2>
    <?php if ($peticionesAjenas === []): ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="empty-state">
                <i class="ph ph-clipboard-text"></i>
                <h3>No hay peticiones abiertas ahora mismo</h3>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($peticionesAjenas as $p): ?>
            <?php
                $jugadoresQueEncajan = $miParticipacion !== null
                    ? array_values(array_filter($miPlantillaConDetalle, fn ($j) => $peticiones->jugadorEncaja($p, $j)))
                    : [];
            ?>
            <div class="card" style="margin-top:1rem">
                <span class="caption"><?= htmlspecialchars($p['club_nombre']) ?></span>
                <p class="body-sm" style="margin-top:.4rem"><?= htmlspecialchars($p['descripcion']) ?></p>
                <?php renderizar_filtros_peticion($p); ?>
                <?php if ($miParticipacion !== null): ?>
                    <?php if ($jugadoresQueEncajan !== []): ?>
                        <p class="caption" style="margin-top:.6rem;color:var(--success)"><i class="ph ph-check-circle"></i> <?= count($jugadoresQueEncajan) ?> jugador(es) tuyo(s) encajan con lo que piden</p>
                    <?php endif; ?>
                    <form method="post" style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <input type="hidden" name="accion" value="proponer">
                        <input type="hidden" name="peticion_id" value="<?= (int) $p['id'] ?>">
                        <select name="jugador_id" class="select" style="height:32px;font-size:var(--fs-caption)">
                            <option value="">Sin jugador concreto</option>
                            <?php if ($jugadoresQueEncajan !== []): ?>
                                <optgroup label="Encajan con el filtro">
                                    <?php foreach ($jugadoresQueEncajan as $j): ?>
                                        <option value="<?= (int) $j['id'] ?>"><?= htmlspecialchars($j['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <optgroup label="Resto de tu plantilla">
                                <?php foreach ($miPlantilla as $j): ?>
                                    <?php if (in_array((int) $j['id'], array_column($jugadoresQueEncajan, 'id'), true)) continue; ?>
                                    <option value="<?= (int) $j['id'] ?>"><?= htmlspecialchars($j['nombre']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <input type="text" name="mensaje" placeholder="Mensaje" maxlength="500" required class="input" style="height:32px;flex:1;min-width:200px;font-size:var(--fs-caption)">
                        <button type="submit" class="btn btn-sm btn-primary">Proponer</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
