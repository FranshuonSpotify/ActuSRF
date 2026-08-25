<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$permisos->requerirAdministrador($usuario);

$error = null;
$exito = isset($_GET['retirado']) ? (string) $_GET['retirado'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'sincronizar') {
    try {
        $total = $clubes->sincronizarDesdeJson((int) $usuario['id']);
        $exito = "Sincronizados {$total} clubes activos desde el JSON oficial.";
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar_agentes_libres') {
    try {
        $total = $jugadores->importarAgentesLibresOficiales((int) $usuario['id']);
        $exito = "Importados {$total} agentes libres oficiales nuevos.";
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'resolver_plantilla') {
    try {
        $idParticipacionResolver = (int) ($_POST['participacion_id'] ?? 0);
        $participaciones->cambiarOrigenPlantilla($idParticipacionResolver, (string) ($_POST['origen_plantilla'] ?? ''), (int) $usuario['id']);
        $totalJugadoresResueltos = $jugadores->aplicarOrigenPlantilla($idParticipacionResolver, (int) $usuario['id']);

        if ($financiero->listarMovimientos($idParticipacionResolver) === []) {
            $dineroInicialResolver = $configuracion->obtenerDecimal('dinero_traspasos_defecto');
            $financiero->registrarDotacionInicial($idParticipacionResolver, $dineroInicialResolver, (int) $usuario['id']);
        }

        $exito = "Plantilla resuelta con {$totalJugadoresResueltos} jugadores.";
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reactivar_archivado') {
    try {
        $clubes->reactivarClubArchivado((string) ($_POST['club_id'] ?? ''), (int) $usuario['id']);
        $exito = 'Club reactivado. Ya aparece en el catálogo para inscribirlo como participante nuevo.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_club') {
    try {
        $clubes->crearClubManual(
            (string) ($_POST['nombre'] ?? ''),
            (string) ($_POST['ciudad'] ?? '') !== '' ? (string) $_POST['ciudad'] : null,
            (string) ($_POST['escudo_url'] ?? '') !== '' ? (string) $_POST['escudo_url'] : null,
            (string) ($_POST['color1'] ?? '') !== '' ? (string) $_POST['color1'] : null,
            (string) ($_POST['color2'] ?? '') !== '' ? (string) $_POST['color2'] : null,
            (string) ($_POST['abreviatura'] ?? '') !== '' ? (string) $_POST['abreviatura'] : null,
            (int) $usuario['id']
        );
        $exito = 'Club creado. Ya puedes inscribirlo en una temporada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_presidente') {
    try {
        $participaciones->asignarPresidente(
            (int) ($_POST['participacion_id'] ?? 0),
            (int) ($_POST['usuario_presidente_id'] ?? 0),
            (int) $usuario['id']
        );
        $exito = 'Presidente asignado correctamente.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_presidente_reiniciar') {
    // A petición de Franshu: el club SIGUE en la liga (no se retira), pero
    // cambia de presidente y toda la plantilla del anterior sale a agente
    // libre para que el nuevo la reconstruya desde cero (plantilla_desde_cero.php).
    // Reutiliza liberarPlantillaCompleta(), el mismo núcleo que ya usa
    // retirar_club.php — un jugador liberado así siempre es UFA, nunca RFA,
    // porque el club que lo tenía deja de tener cualquier derecho de tanteo
    // sobre él en cuanto cambia de manos.
    try {
        ModoPruebas::bloquearSiActivo();
        $participacionIdCambio = (int) ($_POST['participacion_id'] ?? 0);
        $liberados = $contratos->liberarPlantillaCompleta($participacionIdCambio, (int) $usuario['id']);
        $participaciones->asignarPresidente($participacionIdCambio, (int) ($_POST['usuario_presidente_id'] ?? 0), (int) $usuario['id']);
        $exito = "Presidente cambiado y plantilla reiniciada: {$liberados} jugadores liberados como agentes libres. El club sigue en la liga, listo para montarse desde cero.";
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'inscribir') {
    try {
        $idParticipacion = $participaciones->inscribirClub(
            (int) ($_POST['temporada_id'] ?? 0),
            (string) ($_POST['club_id'] ?? ''),
            (string) ($_POST['division'] ?? ''),
            null,
            (string) ($_POST['origen_plantilla'] ?? ''),
            (int) $usuario['id']
        );
        $totalJugadores = $jugadores->aplicarOrigenPlantilla($idParticipacion, (int) $usuario['id']);
        // Orquestado aquí, no dentro de un motor: la dotación inicial es Finanzas,
        // inscribir un club es Clubes, y Clubes nunca debe depender de Finanzas (Ley 19).
        $dineroInicial = $configuracion->obtenerDecimal('dinero_traspasos_defecto');
        $financiero->registrarDotacionInicial($idParticipacion, $dineroInicial, (int) $usuario['id']);
        $exito = "Club inscrito. Plantilla resuelta con {$totalJugadores} jugadores.";
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$previaSincronizacion = $clubes->previsualizarSincronizacion();
$catalogo = $clubes->listarCatalogo();
$idsEnCatalogo = array_column($catalogo, 'id');
$equiposArchivadosDisponibles = array_filter(
    $sincronizadorJson->obtenerEquiposArchivados(),
    fn ($e) => !in_array($e['id'], $idsEnCatalogo, true)
);
$temporadaActiva = $temporadas->obtenerActiva();
$inscritos = $temporadaActiva !== null ? $participaciones->listarPorTemporada((int) $temporadaActiva['id']) : [];
$idsInscritos = array_column($inscritos, 'club_id');
$presidentesDisponibles = array_filter($autenticacion->listarUsuarios(), fn ($u) => $u['rol'] === 'PRESIDENTE');
$paginaTitulo = 'Clubes';
$base = '../';
include __DIR__ . '/../../partials/head.php';

$activePage = 'admin_clubes';
include __DIR__ . '/../../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Administración</span>
            <h1 class="h1" style="margin-top:.5rem">Clubes</h1>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <?php if ($previaSincronizacion['nuevos'] !== [] || $previaSincronizacion['modificados'] !== []): ?>
        <div class="card" style="max-width:560px;border-color:rgba(242,177,52,.4)">
            <span class="caption">Vista previa: esto cambiaría el JSON oficial</span>
            <ul style="margin-top:.6rem;display:flex;flex-direction:column;gap:.3rem">
                <?php if ($previaSincronizacion['nuevos'] !== []): ?>
                    <li><strong><?= count($previaSincronizacion['nuevos']) ?> club(es) nuevo(s)</strong>: <?= htmlspecialchars(implode(', ', $previaSincronizacion['nuevos'])) ?></li>
                <?php endif; ?>
                <?php if ($previaSincronizacion['modificados'] !== []): ?>
                    <li><strong><?= count($previaSincronizacion['modificados']) ?> club(es) modificado(s)</strong>: <?= htmlspecialchars(implode(', ', $previaSincronizacion['modificados'])) ?></li>
                <?php endif; ?>
                <li class="caption"><?= $previaSincronizacion['sin_cambios'] ?> sin cambios</li>
            </ul>
        </div>
    <?php else: ?>
        <div class="card" style="max-width:400px">
            <span class="body-sm"><i class="ph ph-check-circle"></i> El catálogo ya está al día con el JSON oficial. Nada que sincronizar.</span>
        </div>
    <?php endif; ?>

    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1rem">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="sincronizar">
            <button type="submit" class="btn btn-secondary" <?= ($previaSincronizacion['nuevos'] === [] && $previaSincronizacion['modificados'] === []) ? 'disabled' : '' ?>><i class="ph ph-arrows-clockwise"></i> Aplicar sincronización</button>
        </form>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="importar_agentes_libres">
            <button type="submit" class="btn btn-secondary"><i class="ph ph-download-simple"></i> Importar agentes libres oficiales</button>
        </form>
    </div>

    <?php if ($equiposArchivadosDisponibles !== []): ?>
        <div class="card" style="margin-top:2rem;max-width:520px">
            <h2 class="h3">Reactivar club archivado</h2>
            <p class="caption" style="margin-top:.4rem">Un club que ya no compite en el JSON oficial, pero vuelve a la liga. El JSON no se toca — solo entra al catálogo de la base de datos.</p>
            <form method="post" style="margin-top:1.25rem">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="reactivar_archivado">
                <div class="field">
                    <label class="field-label" for="ra-club">Club archivado</label>
                    <select class="select" id="ra-club" name="club_id" required>
                        <option value="">-- selecciona --</option>
                        <?php foreach ($equiposArchivadosDisponibles as $e): ?>
                            <option value="<?= htmlspecialchars($e['id']) ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Reactivar</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-top:2rem;max-width:520px">
        <h2 class="h3">Crear club nuevo</h2>
        <p class="caption" style="margin-top:.4rem">Para clubes que entran a la liga a mitad de camino y no existen en datos_oficiales.json.</p>
        <form method="post" style="margin-top:1.25rem">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="crear_club">
            <div class="field"><label class="field-label" for="cc-nombre">Nombre</label><input class="input" id="cc-nombre" type="text" name="nombre" required></div>
            <div class="field"><label class="field-label" for="cc-ciudad">Ciudad</label><input class="input" id="cc-ciudad" type="text" name="ciudad"></div>
            <div class="field"><label class="field-label" for="cc-escudo">Escudo (URL)</label><input class="input" id="cc-escudo" type="url" name="escudo_url"></div>
            <div class="grid-2">
                <div class="field"><label class="field-label" for="cc-color1">Color 1</label><input class="input" id="cc-color1" type="text" name="color1" placeholder="#000000"></div>
                <div class="field"><label class="field-label" for="cc-color2">Color 2</label><input class="input" id="cc-color2" type="text" name="color2" placeholder="#ffffff"></div>
            </div>
            <div class="field"><label class="field-label" for="cc-abrev">Abreviatura</label><input class="input" id="cc-abrev" type="text" name="abreviatura" maxlength="10"></div>
            <button type="submit" class="btn btn-primary btn-block">Crear club</button>
        </form>
    </div>

    <h2 class="h2" style="margin-top:2.5rem">Catálogo (<?= count($catalogo) ?> clubes)</h2>
    <div class="tbl-wrap" style="margin-top:1rem">
        <div class="tbl-scroll">
        <table class="tbl">
            <thead><tr><th>Club</th><th>Ciudad</th><th>Inscrito en temporada activa</th></tr></thead>
            <tbody>
            <?php foreach ($catalogo as $club): ?>
                <tr>
                    <td>
                        <a href="../historial_club.php?club_id=<?= urlencode($club['id']) ?>" class="fila-club">
                            <?php if (!empty($club['escudo_url'])): ?>
                                <img referrerpolicy="no-referrer" class="escudo" src="<?= htmlspecialchars($club['escudo_url']) ?>" alt="" loading="lazy">
                            <?php endif; ?>
                            <?= htmlspecialchars($club['nombre']) ?>
                        </a>
                    </td>
                    <td class="caption"><?= htmlspecialchars($club['ciudad'] ?? '') ?></td>
                    <td><?= in_array($club['id'], $idsInscritos, true) ? '<span class="badge badge-success">Sí</span>' : '<span class="caption">No</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($temporadaActiva === null): ?>
        <div class="alert alert-warning" style="margin-top:2.5rem"><i class="ph ph-warning"></i> Crea primero una temporada para poder inscribir clubes.</div>
    <?php else: ?>
        <h2 class="h2" style="margin-top:2.5rem">Inscribir club en <?= htmlspecialchars($temporadaActiva['nombre']) ?></h2>
        <div class="card" style="margin-top:1rem;max-width:480px">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="accion" value="inscribir">
                <input type="hidden" name="temporada_id" value="<?= (int) $temporadaActiva['id'] ?>">
                <div class="field">
                    <label class="field-label" for="in-club">Club</label>
                    <select class="select" id="in-club" name="club_id" required>
                        <option value="">-- selecciona --</option>
                        <?php foreach ($catalogo as $club): ?>
                            <option value="<?= htmlspecialchars($club['id']) ?>"><?= htmlspecialchars($club['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="in-division">División</label>
                    <select class="select" id="in-division" name="division" required>
                        <option value="SUPERLIGA">Superliga</option>
                        <option value="ASCENSO">Ascenso</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="in-origen">Origen de la plantilla</label>
                    <select class="select" id="in-origen" name="origen_plantilla" required>
                        <option value="CONTINUAR_ANTERIOR">Continuar desde la temporada anterior</option>
                        <option value="IMPORTAR_JSON">Importar plantilla del JSON oficial</option>
                        <option value="VACIA">Plantilla vacía</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Inscribir</button>
            </form>
        </div>

        <h3 class="h3" style="margin-top:2.5rem">Participaciones de esta temporada</h3>
        <?php if ($inscritos === []): ?>
            <div class="tbl-wrap" style="margin-top:1rem">
                <div class="empty-state">
                    <i class="ph ph-shield"></i>
                    <h3>Sin clubes inscritos</h3>
                    <p>Inscribe el primero con el formulario de arriba.</p>
                </div>
            </div>
        <?php else: ?>
        <div class="tbl-wrap" style="margin-top:1rem">
            <div class="tbl-scroll">
            <table class="tbl">
                <thead><tr><th>Club</th><th>División</th><th>Estado</th><th>Presidente</th><th>Plantilla</th></tr></thead>
                <tbody>
                <?php foreach ($inscritos as $p): ?>
                    <?php $fichasInscrito = $contratoRepositorio->contarActivosPorParticipacion((int) $p['id']); ?>
                    <tr>
                        <td><?= htmlspecialchars($p['club_nombre']) ?></td>
                        <td><span class="chip"><?= htmlspecialchars($p['division']) ?></span></td>
                        <td><span class="badge <?= $p['estado'] === 'ACTIVA' ? 'badge-success' : ($p['estado'] === 'RETIRADA' ? 'badge-danger' : '') ?>"><?= htmlspecialchars(etiqueta_legible($p['estado'])) ?></span></td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:.5rem">
                                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                                    <span class="body-sm"><?= htmlspecialchars($p['presidente_nombre'] ?? 'Sin asignar') ?></span>
                                    <form method="post" style="display:flex;gap:.4rem;align-items:center">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="accion" value="asignar_presidente">
                                        <input type="hidden" name="participacion_id" value="<?= (int) $p['id'] ?>">
                                        <select name="usuario_presidente_id" required class="select" style="height:32px;font-size:var(--fs-caption)">
                                            <option value="">-- elegir --</option>
                                            <?php foreach ($presidentesDisponibles as $pr): ?>
                                                <option value="<?= (int) $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-secondary">Asignar</button>
                                    </form>
                                </div>
                                <?php if ($p['presidente_nombre'] !== null && $fichasInscrito > 0): ?>
                                    <form method="post" style="display:flex;gap:.4rem;align-items:center" onsubmit="return confirm('Esto libera a los <?= $fichasInscrito ?> jugadores de <?= htmlspecialchars(addslashes($p['club_nombre'])) ?> como agentes libres y cambia el presidente. El club sigue en la liga, con la plantilla vacía. ¿Continuar?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="accion" value="cambiar_presidente_reiniciar">
                                        <input type="hidden" name="participacion_id" value="<?= (int) $p['id'] ?>">
                                        <select name="usuario_presidente_id" required class="select" style="height:32px;font-size:var(--fs-caption)">
                                            <option value="">-- nuevo presidente --</option>
                                            <?php foreach ($presidentesDisponibles as $pr): ?>
                                                <option value="<?= (int) $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-danger">Cambiar y reiniciar plantilla</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                            <a href="../plantilla.php?participacion_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-ghost">Ver plantilla</a>
                            <a href="asignacion_masiva.php?participacion_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-ghost">Asignar contrato inicial</a>
                            <a href="plantilla_desde_cero.php?participacion_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-ghost">Plantilla desde cero</a>
                            <?php if ($fichasInscrito === 0 && $p['estado'] !== 'RETIRADA'): ?>
                                <form method="post" style="display:flex;gap:.4rem;align-items:center" onsubmit="return confirm('¿Resolver la plantilla de este club con el origen elegido?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="resolver_plantilla">
                                    <input type="hidden" name="participacion_id" value="<?= (int) $p['id'] ?>">
                                    <select name="origen_plantilla" class="select" style="height:32px;font-size:var(--fs-caption)">
                                        <option value="IMPORTAR_JSON">Importar del JSON oficial</option>
                                        <option value="CONTINUAR_ANTERIOR">Continuar desde la anterior</option>
                                        <option value="VACIA">Plantilla vacía</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary tt" data-tt="0 fichas: la resolución de plantilla falló o no se completó al inscribir">Resolver plantilla</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($p['estado'] !== 'RETIRADA'): ?>
                                <a href="retirar_club.php?participacion_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-danger">Retirar club</a>
                            <?php endif; ?>
                        </td>
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
