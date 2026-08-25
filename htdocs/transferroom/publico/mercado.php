<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$esAdmin = $permisos->esAdministrador($usuario);

$error = null;
$exito = null;

// Alertas de seguimiento (Fase 2, Scouting): avisa a cualquier usuario que
// tenga este jugador en su watchlist de que alguien acaba de ofertar por
// él — nunca al propio ofertante, ya lo sabe. MarketEngine/TransferEngine
// no pueden depender de Notificaciones (Cap. III, Ley 19), así que esto
// vive en la página, igual que el resto de notificaciones de mercado.php.
$avisarSeguidores = function (int $jugadorId, string $mensaje) use ($estrategia, $notificaciones, $usuario): void {
    foreach ($estrategia->listarUsuariosQueSiguenA($jugadorId) as $usuarioSeguidorId) {
        if ((int) $usuarioSeguidorId === (int) $usuario['id']) {
            continue;
        }
        $notificaciones->crear((int) $usuarioSeguidorId, 'OFERTA_SOBRE_JUGADOR_SEGUIDO', $mensaje, 'scouting.php');
    }
};

// Auditoría de seguridad (reglamento de fichajes, previa al primer mercado):
// ofertar/ofertar_traspaso/igualar_oferta/fichar_sin_tier/crear_y_fichar_externo
// tomaban participacion_id directamente del POST y lo pasaban al motor sin
// comprobar que perteneciera a quien hace la petición — un POST manual con el
// participacion_id de otro club bastaba para pujar, traspasar, igualar una
// ventana RFA o fichar en su nombre. Mismo criterio que ya usa plantilla.php.
$puedeActuarComoParticipacion = function (int $participacionId) use ($permisos, $usuario, $participaciones): bool {
    $participacion = $participaciones->buscarPorId($participacionId);

    return $participacion !== null && $permisos->puedeGestionarParticipacion($usuario, $participacion);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validar($_POST['csrf_token'] ?? null)) {
    $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ofertar') {
    try {
        if (!$puedeActuarComoParticipacion((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para ofertar en nombre de este club.');
        }
        $mercado->ofertarPorAgenteLibre(
            (int) ($_POST['jugador_id'] ?? 0),
            (int) ($_POST['participacion_id'] ?? 0),
            (float) ($_POST['salario_ofertado'] ?? 0),
            (int) ($_POST['duracion_temporadas'] ?? 0),
            (int) $usuario['id']
        );
        $jugadorOfertadoLibre = $jugadores->buscarPorId((int) ($_POST['jugador_id'] ?? 0));
        if ($jugadorOfertadoLibre !== null) {
            $avisarSeguidores((int) $_POST['jugador_id'], "Nueva puja por {$jugadorOfertadoLibre['nombre']}, un jugador que sigues.");
        }
        $exito = 'Oferta registrada.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ofertar_traspaso') {
    try {
        if (!$puedeActuarComoParticipacion((int) ($_POST['participacion_compradora_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para ofertar en nombre de este club.');
        }
        $jugadorOfertadoTraspaso = $jugadores->buscarPorId((int) ($_POST['jugador_id'] ?? 0));
        if ($jugadorOfertadoTraspaso === null || $jugadorOfertadoTraspaso['participacion_actual_id'] === null) {
            throw new DomainException('Este jugador no pertenece actualmente a ningún club.');
        }
        $traspasos->ofertarTraspaso(
            (int) ($_POST['jugador_id'] ?? 0),
            (int) ($_POST['participacion_compradora_id'] ?? 0),
            (float) ($_POST['importe_traspaso'] ?? 0),
            array_map('intval', $_POST['jugadores_ofrecidos'] ?? []),
            (int) $usuario['id']
        );
        $participacionVendedoraMercado = $participaciones->buscarPorId((int) $jugadorOfertadoTraspaso['participacion_actual_id']);
        $notificaciones->crear(
            (int) ($participacionVendedoraMercado['usuario_presidente_id'] ?? 0) ?: null,
            'OFERTA_TRASPASO_RECIBIDA',
            "Nueva oferta de traspaso por {$jugadorOfertadoTraspaso['nombre']}.",
            "plantilla.php?participacion_id={$jugadorOfertadoTraspaso['participacion_actual_id']}"
        );
        $avisarSeguidores((int) $_POST['jugador_id'], "Nueva oferta de traspaso por {$jugadorOfertadoTraspaso['nombre']}, un jugador que sigues.");
        $exito = 'Oferta de traspaso enviada. El club vendedor debe aceptarla.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'resolver' && $esAdmin) {
    try {
        ModoPruebas::bloquearSiActivo();
        $jugadorIdResuelto = (int) ($_POST['jugador_id'] ?? 0);
        $jugadorAntes = $jugadores->buscarPorId($jugadorIdResuelto);
        $ofertasAntesDeResolver = $mercado->listarOfertasPendientes($jugadorIdResuelto);
        $contratoId = $mercado->resolverOfertas($jugadorIdResuelto, (int) $usuario['id']);

        if ($contratoId !== null && $jugadorAntes !== null) {
            $contratoGanador = $contratoRepositorio->buscarPorId($contratoId);
            $participacionGanadora = $participaciones->buscarPorId((int) $contratoGanador['participacion_id']);
            $notificaciones->crear(
                (int) ($participacionGanadora['usuario_presidente_id'] ?? 0) ?: null,
                'FICHAJE_AGENTE_LIBRE_GANADO',
                "Has ganado el fichaje de {$jugadorAntes['nombre']} en el mercado de agentes libres.",
                "plantilla.php?participacion_id={$contratoGanador['participacion_id']}"
            );

            // Quejas esperables #1: "he pujado y no sé si gané o perdí" — avisar
            // también a quien perdió la puja, no solo al ganador.
            foreach ($ofertasAntesDeResolver as $ofertaPerdida) {
                if ((int) $ofertaPerdida['participacion_id'] === (int) $contratoGanador['participacion_id']) {
                    continue;
                }
                $participacionPerdedora = $participaciones->buscarPorId((int) $ofertaPerdida['participacion_id']);
                $notificaciones->crear(
                    (int) ($participacionPerdedora['usuario_presidente_id'] ?? 0) ?: null,
                    'FICHAJE_AGENTE_LIBRE_PERDIDO',
                    "Has perdido la puja por {$jugadorAntes['nombre']}: fichó por otro club.",
                    'mercado.php'
                );
            }
        }

        if ($contratoId === null && $jugadorAntes !== null && $jugadorAntes['origen_club_agencia_libre'] !== null) {
            $temporadaParaAviso = $temporadas->obtenerActiva();
            $participacionOrigen = $temporadaParaAviso !== null
                ? array_values(array_filter(
                    $participaciones->listarPorTemporada((int) $temporadaParaAviso['id']),
                    fn ($p) => $p['club_id'] === $jugadorAntes['origen_club_agencia_libre']
                ))[0] ?? null
                : null;

            $notificaciones->crear(
                (int) ($participacionOrigen['usuario_presidente_id'] ?? 0) ?: null,
                'VENTANA_IGUALACION_ABIERTA',
                "Tienes 48h para igualar una oferta por {$jugadorAntes['nombre']}.",
                'mercado.php'
            );
        }

        $exito = $contratoId !== null
            ? 'Mercado resuelto: el jugador ha fichado por la mejor oferta.'
            : 'Es un agente libre restringido de otro club: se ha abierto la ventana de igualación de 48h.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'igualar_oferta') {
    try {
        if (!$puedeActuarComoParticipacion((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para igualar esta oferta en nombre de este club.');
        }
        $mercado->igualarOferta(
            (int) ($_POST['jugador_id'] ?? 0),
            (int) ($_POST['participacion_id'] ?? 0),
            (int) $usuario['id']
        );
        $exito = 'Oferta igualada: el jugador se queda en su club de origen.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'confirmar_sin_igualar' && $esAdmin) {
    try {
        ModoPruebas::bloquearSiActivo();
        $mercado->confirmarSinIgualacion((int) ($_POST['jugador_id'] ?? 0), (int) $usuario['id']);
        $exito = 'Plazo de igualación terminado: el jugador ficha por el club que ganó la puja.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'fichar_sin_tier') {
    try {
        if (!$puedeActuarComoParticipacion((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para fichar en nombre de este club.');
        }
        $contratos->ficharAgenteLibreSinTier(
            (int) ($_POST['jugador_id'] ?? 0),
            (int) ($_POST['participacion_id'] ?? 0),
            (int) ($_POST['tier_id'] ?? 0),
            (float) ($_POST['salario_anual'] ?? 0),
            (int) ($_POST['duracion_temporadas'] ?? 0),
            (int) $usuario['id']
        );
        $jugadorFichado = $jugadores->buscarPorId((int) ($_POST['jugador_id'] ?? 0));
        foreach ($usuarioRepositorio->listarTodos() as $u) {
            if ($u['rol'] === 'ADMINISTRADOR') {
                $notificaciones->crear(
                    (int) $u['id'],
                    'CONTRATO_PENDIENTE_REVISION',
                    "Nuevo contrato pendiente de revisión: {$jugadorFichado['nombre']} (fichaje sin tier).",
                    'admin/revision_contratos.php'
                );
            }
        }

        $exito = 'Jugador fichado al instante. Queda pendiente de revisión administrativa.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_y_fichar_externo') {
    try {
        if (!$puedeActuarComoParticipacion((int) ($_POST['participacion_id'] ?? 0))) {
            throw new DomainException('No tienes permiso para fichar en nombre de este club.');
        }
        $jugadorId = $jugadores->crearJugadorExterno(
            (string) ($_POST['nombre'] ?? ''),
            (string) ($_POST['posicion'] ?? ''),
            (string) ($_POST['afinidad'] ?? '') !== '' ? (string) $_POST['afinidad'] : null,
            (string) ($_POST['foto_url'] ?? '') !== '' ? (string) $_POST['foto_url'] : null,
            (int) $usuario['id']
        );
        $contratos->ficharAgenteLibreSinTier(
            $jugadorId,
            (int) ($_POST['participacion_id'] ?? 0),
            (int) ($_POST['tier_id'] ?? 0),
            (float) ($_POST['salario_anual'] ?? 0),
            (int) ($_POST['duracion_temporadas'] ?? 0),
            (int) $usuario['id']
        );
        foreach ($usuarioRepositorio->listarTodos() as $u) {
            if ($u['rol'] === 'ADMINISTRADOR') {
                $notificaciones->crear(
                    (int) $u['id'],
                    'CONTRATO_PENDIENTE_REVISION',
                    "Nuevo contrato pendiente de revisión: {$_POST['nombre']} (jugador externo, fichaje sin tier).",
                    'admin/revision_contratos.php'
                );
            }
        }

        $exito = 'Jugador externo creado y fichado al instante. Queda pendiente de revisión administrativa.';
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}

$agentesLibres = $jugadores->listarAgentesLibresConTier();
$agentesLibresSinTier = $jugadores->listarAgentesLibresSinTier();
$tiersDisponibles = $contratos->listarTiers();

// Paginación real de agentes libres (rendimiento, no solo estética): cada
// fila de la tabla dispara listarOfertasPendientes() y, si es RFA,
// buscarPendienteIgualacion() — con la lista completa sin paginar eso es
// un N+1 real (100+ consultas extra en una liga con muchos agentes
// libres), la causa de fondo del lag reportado al entrar en Mercado, no
// solo un problema de pintado en el navegador. Paginando en servidor esas
// consultas solo se ejecutan para los 10 jugadores que se ven de verdad.
$agentesLibresPorPagina = 10;
$totalAgentesLibres = count($agentesLibres);
$totalPaginasAgentesLibres = max(1, (int) ceil($totalAgentesLibres / $agentesLibresPorPagina));
$paginaAgentesLibres = max(1, min($totalPaginasAgentesLibres, (int) ($_GET['pagina'] ?? 1)));
$agentesLibresPagina = array_slice($agentesLibres, ($paginaAgentesLibres - 1) * $agentesLibresPorPagina, $agentesLibresPorPagina);

/** Controles de paginación reutilizados en las 3 listas de mercado.php (con tier, sin tier de la liga, sin tier externos). Conserva el resto de la query string y ancla al hash de pestaña para que la pestaña correcta siga abierta al recargar. */
$renderizarPaginacionMercado = function (int $paginaActual, int $totalPaginas, string $paramNombre, string $anclaTab): void {
    if ($totalPaginas <= 1) {
        return;
    }
    $construirUrl = function (int $pagina) use ($paramNombre, $anclaTab): string {
        $params = $_GET;
        $params[$paramNombre] = $pagina;
        return '?' . http_build_query($params) . '#' . $anclaTab;
    };
    echo '<div style="display:flex;align-items:center;justify-content:center;gap:.75rem;margin-top:1.25rem">';
    if ($paginaActual > 1) {
        echo '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($construirUrl($paginaActual - 1)) . '"><i class="ph ph-caret-left"></i> Anterior</a>';
    }
    echo '<span class="caption mono">Página ' . $paginaActual . ' de ' . $totalPaginas . '</span>';
    if ($paginaActual < $totalPaginas) {
        echo '<a class="btn btn-secondary btn-sm" href="' . htmlspecialchars($construirUrl($paginaActual + 1)) . '">Siguiente <i class="ph ph-caret-right"></i></a>';
    }
    echo '</div>';
};

// Solo la temporada activa: una oferta nunca tiene sentido contra una
// participación de una temporada ya cerrada. Para un presidente normal esto
// deja como mucho una sola fila (su único club) — es lo que permite quitar
// el selector de "Tu club" del modal (Fase 2, ronda de feedback): un
// presidente solo gestiona un club, elegir entre uno era teatro. Un admin
// sigue viendo todos los clubes de la temporada activa (le hace falta para
// actuar en nombre de cualquiera desde Modo pruebas).
$misParticipaciones = [];
$temporadaActivaMercado = $temporadas->obtenerActiva();
if ($temporadaActivaMercado !== null) {
    foreach ($participaciones->listarPorTemporada((int) $temporadaActivaMercado['id']) as $p) {
        if ($esAdmin || (int) ($p['usuario_presidente_id'] ?? 0) === (int) $usuario['id']) {
            $misParticipaciones[] = $p;
        }
    }
}
$misParticipacionIdsMercado = array_column($misParticipaciones, 'id');

// Salary Cap de cada posible club comprador (modal de traspaso rediseñado),
// calculado una vez para todo el listado, igual que en plantilla.php.
$capPorClubCompradorMercado = [];
// Plantilla propia por cada club, para poder elegir jugadores a cambio en un
// traspaso (intercambio, a petición de Franshu): id => lista de sus jugadores.
$misJugadoresPorClubMercado = [];
if ($temporadaActivaMercado !== null) {
    foreach ($misParticipaciones as $p) {
        $capPorClubCompradorMercado[(int) $p['id']] = [
            'gastado' => $contratos->gastoSalarial((int) $p['id']),
            'cap' => (float) ($p['salary_cap_override'] ?? $temporadaActivaMercado['salary_cap']),
        ];
        $misJugadoresPorClubMercado[(int) $p['id']] = array_map(fn ($j) => [
            'id' => (int) $j['id'],
            'nombre' => $j['nombre'],
            'posicion' => $j['posicion'],
            'tier' => $j['tier_nombre'],
        ], $jugadores->listarPlantilla((int) $p['id']));
    }
}

// Buscador global (Fase 2, rediseño estético): todos los jugadores de la
// liga, contratados o libres, en un único listado con los mismos filtros
// que antes solo tenía scouting.php (decisión confirmada: Mercado absorbe
// el buscador global, Scouting se especializa en seguimiento con alertas).
$todosLosJugadoresMercado = $jugadores->listarTodosParaScouting();
$contratoPorJugadorGlobal = array_column($contratos->listarActivosGlobal(), null, 'jugador_id');
$afinidadesDisponiblesMercado = array_unique(array_filter(array_column($todosLosJugadoresMercado, 'afinidad_nombre')));
sort($afinidadesDisponiblesMercado);
$clubesDisponiblesMercado = array_unique(array_filter(array_column($todosLosJugadoresMercado, 'club_nombre')));
sort($clubesDisponiblesMercado);

$paginaTitulo = 'Mercado';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'mercado';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Mercado</span>
            <h1 class="h1" style="margin-top:.5rem">Mercado</h1>
            <p class="caption">Todos los jugadores de la liga, contratados o libres.</p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="todos-jugadores">Todos los jugadores</button>
        <button data-tab-target="agentes-libres">Agentes libres</button>
        <?php if ($misParticipaciones !== []): ?>
            <button data-tab-target="jugador-externo">Jugador externo</button>
        <?php endif; ?>
    </div>

    <div data-tab-panel="todos-jugadores">
        <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
            <div class="field" style="margin-bottom:0;min-width:220px">
                <label class="field-label" for="filtro-todos-nombre">Nombre</label>
                <input class="input" type="search" id="filtro-todos-nombre" placeholder="Nombre del jugador" oninput="TRmercadoTodos.filtrar()">
            </div>
            <div class="field" style="margin-bottom:0;min-width:140px">
                <label class="field-label" for="filtro-todos-tier">Tier</label>
                <select class="select" id="filtro-todos-tier" onchange="TRmercadoTodos.filtrar()">
                    <option value="">Todos</option>
                    <?php foreach (['C', 'B-', 'B', 'B+', 'A-', 'A', 'A+', 'S', 'S+', 'S++'] as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:140px">
                <label class="field-label" for="filtro-todos-posicion">Posición</label>
                <select class="select" id="filtro-todos-posicion" onchange="TRmercadoTodos.filtrar()">
                    <option value="">Todas</option>
                    <option value="POR">POR</option>
                    <option value="DEF">DEF</option>
                    <option value="MED">MED</option>
                    <option value="DEL">DEL</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:160px">
                <label class="field-label" for="filtro-todos-afinidad">Afinidad</label>
                <select class="select" id="filtro-todos-afinidad" onchange="TRmercadoTodos.filtrar()">
                    <option value="">Todas</option>
                    <?php foreach ($afinidadesDisponiblesMercado as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:160px">
                <label class="field-label" for="filtro-todos-estado">Estado</label>
                <select class="select" id="filtro-todos-estado" onchange="TRmercadoTodos.filtrar()">
                    <option value="">Todos</option>
                    <option value="CONTRATADO">Contratado</option>
                    <option value="AGENTE_LIBRE">Agente libre</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;min-width:180px">
                <label class="field-label" for="filtro-todos-club">Club</label>
                <select class="select" id="filtro-todos-club" onchange="TRmercadoTodos.filtrar()">
                    <option value="">Todos</option>
                    <?php foreach ($clubesDisponiblesMercado as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="TRmercadoTodos.limpiarFiltros()">Limpiar</button>
        </div>
        <p class="caption" id="filtro-todos-resumen" style="margin-bottom:1rem"></p>

        <div class="tbl-wrap">
            <div class="tbl-scroll">
            <table class="tbl" id="tabla-todos-jugadores">
                <thead><tr><th>Jugador</th><th>Posición</th><th>Afinidad</th><th>Tier</th><th>Club</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($todosLosJugadoresMercado as $j): ?>
                    <?php
                        $estadoFiltroTodos = $j['estado'] === 'ACTIVO' ? 'CONTRATADO' : 'AGENTE_LIBRE';
                        $contratoGlobalJugador = $contratoPorJugadorGlobal[$j['id']] ?? null;

                        if ($estadoFiltroTodos === 'CONTRATADO' && $contratoGlobalJugador !== null) {
                            $esMioTodos = in_array((int) $contratoGlobalJugador['participacion_id'], $misParticipacionIdsMercado, true);
                            $ofertasTraspasoTodos = [];
                            $jugadoresOfrecidosPorOfertaTodos = [];
                            if (!$esMioTodos) {
                                $ofertasTraspasoTodos = $traspasos->listarOfertasPendientesPorJugador((int) $j['id']);
                                usort($ofertasTraspasoTodos, fn ($a, $b) => (float) $b['importe_traspaso'] <=> (float) $a['importe_traspaso']);
                                $jugadoresOfrecidosPorOfertaTodos = $traspasos->listarJugadoresOfrecidosPorOfertas(array_column($ofertasTraspasoTodos, 'id'));
                            }
                            $datosModalTodos = [
                                'tipo' => $esMioTodos ? 'propio' : 'traspaso',
                                'jugadorId' => (int) $j['id'],
                                'nombre' => $j['nombre'],
                                'fotoUrl' => $j['foto_url'] ?? null,
                                'posicion' => $j['posicion'],
                                'tier' => $j['tier_nombre'],
                                'esFranquicia' => (bool) $j['es_franquicia'],
                                'contratoId' => (int) $contratoGlobalJugador['id'],
                                'salarioActual' => (float) $contratoGlobalJugador['salario_anual'],
                                'duracionRestante' => (int) $contratoGlobalJugador['duracion_temporadas'] . ' temporada(s)',
                                'clubActual' => $contratoGlobalJugador['club_nombre'],
                                'clubActualEscudo' => $contratoGlobalJugador['club_escudo_url'] ?? null,
                                'puedeGestionar' => $esMioTodos,
                                'puedeOfertar' => !$esMioTodos && $misParticipaciones !== [],
                                'wikiUrl' => wiki_url_publica($j),
                                'ofertasTraspaso' => array_map(fn ($o) => [
                                    'club' => $o['club_comprador_nombre'],
                                    'escudo' => $o['club_comprador_escudo_url'] ?? null,
                                    'importe' => (float) $o['importe_traspaso'],
                                    'fecha' => $o['creado_en'],
                                    'jugadoresOfrecidos' => array_map(fn ($jo) => $jo['jugador_nombre'], $jugadoresOfrecidosPorOfertaTodos[(int) $o['id']] ?? []),
                                ], $ofertasTraspasoTodos),
                                'capPorClub' => $capPorClubCompradorMercado,
                                'misJugadoresPorClub' => $misJugadoresPorClubMercado,
                            ];
                        } else {
                            $ofertasPendientesTodos = $j['estado'] !== 'ACTIVO' ? $mercado->listarOfertasPendientes((int) $j['id']) : [];
                            $pendienteIgualacionTodos = $j['estado'] !== 'ACTIVO' && $j['tipo_agencia_libre'] === 'RESTRINGIDA' ? $mercado->buscarPendienteIgualacion((int) $j['id']) : null;
                            $datosModalTodos = [
                                'tipo' => 'agente_libre',
                                'jugadorId' => (int) $j['id'],
                                'nombre' => $j['nombre'],
                                'fotoUrl' => $j['foto_url'] ?? null,
                                'posicion' => $j['posicion'],
                                'tier' => $j['tier_nombre'],
                                'esFranquicia' => false,
                                'salarioBase' => (float) ($j['salario_base'] ?? 0),
                                'agenciaLibre' => $j['tipo_agencia_libre'] ?? null,
                                'clubOrigen' => null,
                                'ofertas' => array_map(fn ($o) => [
                                    'club' => $o['club_nombre'],
                                    'salario' => (float) $o['salario_ofertado'],
                                    'duracion' => (int) $o['duracion_temporadas'],
                                ], $ofertasPendientesTodos),
                                'puedeOfertar' => $misParticipaciones !== [] && $pendienteIgualacionTodos === null,
                                'wikiUrl' => wiki_url_publica($j),
                            ];
                        }
                    ?>
                    <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>" data-tier="<?= htmlspecialchars($j['tier_nombre']) ?>" data-posicion="<?= htmlspecialchars($j['posicion']) ?>" data-afinidad="<?= htmlspecialchars($j['afinidad_nombre'] ?? '') ?>" data-estado="<?= $estadoFiltroTodos ?>" data-club="<?= htmlspecialchars($j['club_nombre'] ?? '') ?>">
                        <td>
                            <button type="button" class="fila-jugador" style="text-align:left" onclick='TRjugador.abrirModal(<?= json_encode($datosModalTodos, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <?php if (!empty($j['foto_url'])): ?>
                                    <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($j['nombre']) ?>
                            </button>
                        </td>
                        <td><?= htmlspecialchars($j['posicion']) ?></td>
                        <td class="caption"><?= htmlspecialchars($j['afinidad_nombre'] ?? '—') ?></td>
                        <td><span class="chip <?= tier_chip_class($j['tier_nombre']) ?>"><?= htmlspecialchars($j['tier_nombre']) ?></span></td>
                        <td>
                            <?php if ($estadoFiltroTodos === 'CONTRATADO'): ?>
                                <div class="fila-club">
                                    <?php if (!empty($j['escudo_url'])): ?>
                                        <img referrerpolicy="no-referrer" class="escudo" src="<?= htmlspecialchars($j['escudo_url']) ?>" alt="" loading="lazy">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($j['club_nombre'] ?? '—') ?>
                                </div>
                            <?php else: ?>
                                <span class="badge badge-success">Agente libre</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-secondary" onclick='TRjugador.abrirModal(<?= json_encode($datosModalTodos, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ver</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div data-tab-panel="agentes-libres" hidden>
    <?php if ($agentesLibres === []): ?>
        <div class="tbl-wrap">
            <div class="empty-state">
                <i class="ph ph-users-three"></i>
                <h3>No hay agentes libres con tier todavía</h3>
                <p>Aparecerán aquí en cuanto algún jugador con tier asignado quede sin contrato.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div class="field" style="margin-bottom:0;min-width:180px">
            <label class="field-label" for="filtro-nombre">Buscar</label>
            <input class="input" id="filtro-nombre" type="search" placeholder="Nombre del jugador" oninput="TRmercado.filtrar()">
        </div>
        <div class="field" style="margin-bottom:0;min-width:140px">
            <label class="field-label" for="filtro-tier">Tier</label>
            <select class="select" id="filtro-tier" onchange="TRmercado.filtrar()">
                <option value="">Todos</option>
                <?php foreach ($tiersDisponibles as $t): ?>
                    <option value="<?= htmlspecialchars($t['nombre']) ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;min-width:140px">
            <label class="field-label" for="filtro-posicion">Posición</label>
            <select class="select" id="filtro-posicion" onchange="TRmercado.filtrar()">
                <option value="">Todas</option>
                <option value="POR">POR</option>
                <option value="DEF">DEF</option>
                <option value="MED">MED</option>
                <option value="DEL">DEL</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;min-width:160px">
            <label class="field-label" for="filtro-estado">Agencia libre</label>
            <select class="select" id="filtro-estado" onchange="TRmercado.filtrar()">
                <option value="">Todas</option>
                <option value="RESTRINGIDA">RFA</option>
                <option value="NO_RESTRINGIDA">UFA</option>
                <option value="ARCHIVADO">Club archivado</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;min-width:130px">
            <label class="field-label" for="filtro-salario-min">Salario mínimo</label>
            <input class="input" id="filtro-salario-min" type="number" min="0" step="1" placeholder="0" oninput="TRmercado.filtrar()">
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="TRmercado.limpiarFiltros()">Limpiar</button>
    </div>
    <p class="caption" id="filtro-resumen" style="margin-bottom:1rem"></p>
    <div class="tbl-wrap">
        <div class="tbl-scroll">
        <table class="tbl" id="tabla-agentes-libres">
            <thead><tr><th>Nombre</th><th>Posición</th><th>Tier</th><th class="num">Salario base</th><th>Agencia libre</th><th>Ofertas / estado</th><th>Ofertar</th></tr></thead>
            <tbody>
            <?php foreach ($agentesLibresPagina as $j): ?>
                <?php
                    $ofertasPendientes = $mercado->listarOfertasPendientes((int) $j['id']);
                    $pendienteIgualacion = $j['tipo_agencia_libre'] === 'RESTRINGIDA' ? $mercado->buscarPendienteIgualacion((int) $j['id']) : null;
                    $puedeIgualar = $pendienteIgualacion !== null
                        && in_array($j['origen_club_agencia_libre'], array_column($misParticipaciones, 'club_id'), true);
                    $plazoVencido = $pendienteIgualacion !== null && strtotime((string) $pendienteIgualacion['fecha_limite_igualacion']) < time();
                    $estadoFiltro = $j['tipo_agencia_libre'] ?? (($j['procedencia_archivado'] ?? null) !== null ? 'ARCHIVADO' : '');
                ?>
                <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>" data-tier="<?= htmlspecialchars($j['tier_nombre']) ?>" data-posicion="<?= htmlspecialchars($j['posicion']) ?>" data-estado="<?= htmlspecialchars($estadoFiltro) ?>" data-salario="<?= (float) $j['salario_base'] ?>">
                    <td>
                        <?php
                            // Modal unificado de jugador (05-transfer_room_docs/02_ux_diseno): el clic
                            // vive en el jugador, no en un botón "Pujar" aparte. Los datos del modal
                            // viajan en el propio trigger — nada de fetch/AJAX nuevo, ya están
                            // renderizados en esta misma carga de página.
                            $datosModal = [
                                'tipo' => 'agente_libre',
                                'jugadorId' => (int) $j['id'],
                                'nombre' => $j['nombre'],
                                'fotoUrl' => $j['foto_url'] ?? null,
                                'posicion' => $j['posicion'],
                                'tier' => $j['tier_nombre'],
                                'esFranquicia' => false,
                                'salarioBase' => (float) $j['salario_base'],
                                'agenciaLibre' => $j['tipo_agencia_libre'],
                                'clubOrigen' => $j['club_origen_nombre'] ?? null,
                                'ofertas' => array_map(fn ($o) => [
                                    'club' => $o['club_nombre'],
                                    'salario' => (float) $o['salario_ofertado'],
                                    'duracion' => (int) $o['duracion_temporadas'],
                                ], $ofertasPendientes),
                                'puedeOfertar' => $misParticipaciones !== [] && $pendienteIgualacion === null,
                                'wikiUrl' => wiki_url_publica($j),
                            ];
                        ?>
                        <button type="button" class="fila-jugador" style="text-align:left" onclick='TRjugador.abrirModal(<?= json_encode($datosModal, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <?php if (!empty($j['foto_url'])): ?>
                                <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($j['nombre']) ?>
                        </button>
                    </td>
                    <td><?= htmlspecialchars($j['posicion']) ?></td>
                    <td><span class="chip <?= tier_chip_class($j['tier_nombre']) ?>"><?= htmlspecialchars($j['tier_nombre']) ?></span></td>
                    <td class="num mono"><?= number_format((float) $j['salario_base'], 0, ',', '.') ?> €</td>
                    <td>
                        <?php if ($j['tipo_agencia_libre'] === 'RESTRINGIDA'): ?>
                            <span class="badge badge-warning">RFA</span> <span class="caption"><?= htmlspecialchars($j['club_origen_nombre'] ?? '—') ?></span>
                        <?php elseif ($j['tipo_agencia_libre'] === 'NO_RESTRINGIDA' && !empty($j['club_origen_retirado'])): ?>
                            <span class="badge badge-success">UFA</span> <span class="badge badge-info">Agente libre — club retirado</span>
                        <?php elseif ($j['tipo_agencia_libre'] === 'NO_RESTRINGIDA'): ?>
                            <span class="badge badge-success">UFA</span>
                        <?php elseif (($j['procedencia_archivado'] ?? null) !== null): ?>
                            <span class="badge badge-info">Club archivado</span> <span class="caption"><?= htmlspecialchars($j['procedencia_archivado']) ?></span>
                        <?php else: ?>
                            <span class="caption">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pendienteIgualacion !== null): ?>
                            <?php
                                $horasRestantes = (strtotime((string) $pendienteIgualacion['fecha_limite_igualacion']) - time()) / 3600;
                                $urgencia = $horasRestantes <= 0 ? 'badge-danger' : ($horasRestantes < 6 ? 'badge-danger' : ($horasRestantes < 24 ? 'badge-warning' : 'badge-info'));
                                $textoCuentaAtras = $horasRestantes <= 0 ? 'Plazo vencido' : (
                                    $horasRestantes < 1 ? round($horasRestantes * 60) . ' min restantes' : round($horasRestantes) . 'h restantes'
                                );
                            ?>
                            <span class="badge js-cuenta-atras <?= $urgencia ?>" data-fecha-limite-epoch="<?= strtotime((string) $pendienteIgualacion['fecha_limite_igualacion']) * 1000 ?>"><i class="ph ph-timer"></i> <span class="js-cuenta-atras-texto"><?= htmlspecialchars($textoCuentaAtras) ?></span></span>
                            <div style="margin-top:.5rem">
                                <?php
                                    $datosRfa = [
                                        'jugadorId' => (int) $j['id'],
                                        'jugadorNombre' => $j['nombre'],
                                        'clubOfertante' => $pendienteIgualacion['club_nombre'],
                                        'salarioOfertado' => (float) $pendienteIgualacion['salario_ofertado'],
                                        'duracion' => (int) $pendienteIgualacion['duracion_temporadas'],
                                        'fechaLimite' => $pendienteIgualacion['fecha_limite_igualacion'],
                                        'fechaLimiteEpoch' => strtotime((string) $pendienteIgualacion['fecha_limite_igualacion']) * 1000,
                                        'textoCuentaAtras' => $textoCuentaAtras,
                                        'urgencia' => $urgencia,
                                        'puedeIgualar' => $puedeIgualar && !$plazoVencido,
                                        'confirmarSinIgualar' => $esAdmin && $plazoVencido,
                                        'participacionOrigenId' => $puedeIgualar
                                            ? (int) array_values(array_filter($misParticipaciones, fn ($p) => $p['club_id'] === $j['origen_club_agencia_libre']))[0]['id']
                                            : null,
                                    ];
                                ?>
                                <button type="button" class="btn btn-sm btn-secondary" onclick='TRmercado.abrirModalRfa(<?= json_encode($datosRfa, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ver ventana RFA</button>
                            </div>
                        <?php elseif ($ofertasPendientes === []): ?>
                            <span class="caption">Ninguna</span>
                        <?php else: ?>
                            <div class="body-sm">
                                <?php foreach ($ofertasPendientes as $o): ?>
                                    <strong><?= htmlspecialchars($o['club_nombre']) ?></strong>:
                                    <span class="mono"><?= number_format((float) $o['salario_ofertado'], 0, ',', '.') ?> €</span> (<?= (int) $o['duracion_temporadas'] ?>t)<br>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($esAdmin): ?>
                                <form method="post" style="margin-top:.5rem">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="accion" value="resolver">
                                    <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Resolver mercado</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($misParticipaciones !== [] && $pendienteIgualacion === null): ?>
                            <button type="button" class="btn btn-sm btn-primary" onclick='TRjugador.abrirModal(<?= json_encode($datosModal, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Ver / Ofertar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php $renderizarPaginacionMercado($paginaAgentesLibres, $totalPaginasAgentesLibres, 'pagina', 'agentes-libres'); ?>
    <?php endif; ?>

    <div style="margin-top:3rem">
        <h2 class="h2">Agentes libres sin tier todavía</h2>
        <p class="caption" style="margin-top:.4rem;max-width:70ch">Recién importados del JSON oficial o creados a mano. Se fichan al instante por quien llegue primero, con la misma ficha que cualquier otro jugador: el tier y el salario los elige quien ficha, y queda marcado para revisión administrativa.</p>
    </div>

    <?php
        $agentesLibresSinTierLiga = array_values(array_filter($agentesLibresSinTier, fn ($j) => $j['origen'] === 'JSON_OFICIAL'));
        $agentesLibresSinTierExternos = array_values(array_filter($agentesLibresSinTier, fn ($j) => $j['origen'] !== 'JSON_OFICIAL'));

        // Mismo criterio de paginación que la lista con tier: 10 por página,
        // una página independiente para cada una de las dos sub-pestañas.
        $sinTierPorPagina = 10;
        $paginacionSinTier = [];
        $listasSinTierPagina = [];
        foreach (['sin-tier-liga' => $agentesLibresSinTierLiga, 'sin-tier-externos' => $agentesLibresSinTierExternos] as $panelIdSinTier => $listaSinTier) {
            $paramPaginaSinTier = 'pagina_' . str_replace('-', '_', $panelIdSinTier);
            $totalPanelSinTier = max(1, (int) ceil(count($listaSinTier) / $sinTierPorPagina));
            $paginaPanelSinTier = max(1, min($totalPanelSinTier, (int) ($_GET[$paramPaginaSinTier] ?? 1)));
            $paginacionSinTier[$panelIdSinTier] = ['pagina' => $paginaPanelSinTier, 'total' => $totalPanelSinTier, 'param' => $paramPaginaSinTier];
            $listasSinTierPagina[$panelIdSinTier] = array_slice($listaSinTier, ($paginaPanelSinTier - 1) * $sinTierPorPagina, $sinTierPorPagina);
        }
    ?>

    <?php if ($agentesLibresSinTier === []): ?>
        <div class="tbl-wrap" style="margin-top:1.25rem">
            <div class="empty-state">
                <i class="ph ph-user-plus"></i>
                <h3>Sin agentes libres pendientes de tier</h3>
                <p>Todos los jugadores importados ya tienen tier asignado.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="tabs" data-tab-group style="margin-top:1.25rem;margin-bottom:1rem">
            <button class="on" data-tab-target="sin-tier-liga">De la liga (<?= count($agentesLibresSinTierLiga) ?>)</button>
            <button data-tab-target="sin-tier-externos">Externos (<?= count($agentesLibresSinTierExternos) ?>)</button>
        </div>
        <?php foreach (['sin-tier-liga' => $agentesLibresSinTierLiga, 'sin-tier-externos' => $agentesLibresSinTierExternos] as $panelId => $lista): ?>
            <div data-tab-panel="<?= $panelId ?>"<?= $panelId === 'sin-tier-externos' ? ' hidden' : '' ?>>
                <?php if ($lista === []): ?>
                    <div class="tbl-wrap">
                        <div class="empty-state">
                            <i class="ph ph-user-plus"></i>
                            <h3>Nada por aquí</h3>
                            <p>No hay agentes libres <?= $panelId === 'sin-tier-liga' ? 'de la liga' : 'externos' ?> pendientes de tier.</p>
                        </div>
                    </div>
                <?php else: ?>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
                    <div class="field" style="margin-bottom:0;min-width:200px">
                        <label class="field-label" for="filtro-<?= $panelId ?>-nombre">Buscar</label>
                        <input class="input" type="search" id="filtro-<?= $panelId ?>-nombre" placeholder="Nombre del jugador" oninput="TRmercado.filtrarSinTier('<?= $panelId ?>')">
                    </div>
                    <div class="field" style="margin-bottom:0;min-width:140px">
                        <label class="field-label" for="filtro-<?= $panelId ?>-posicion">Posición</label>
                        <select class="select" id="filtro-<?= $panelId ?>-posicion" onchange="TRmercado.filtrarSinTier('<?= $panelId ?>')">
                            <option value="">Todas</option>
                            <option value="POR">POR</option>
                            <option value="DEF">DEF</option>
                            <option value="MED">MED</option>
                            <option value="DEL">DEL</option>
                        </select>
                    </div>
                </div>
                <div class="tbl-wrap">
                    <div class="tbl-scroll">
                    <table class="tbl" id="tabla-<?= $panelId ?>">
                        <thead><tr><th>Jugador</th><th>Posición</th><th>Afinidad</th><th>Fichar</th></tr></thead>
                        <tbody>
                        <?php foreach ($listasSinTierPagina[$panelId] as $j): ?>
                            <tr data-nombre="<?= htmlspecialchars(mb_strtolower($j['nombre'])) ?>" data-posicion="<?= htmlspecialchars($j['posicion']) ?>">
                                <td>
                                    <div class="fila-jugador">
                                        <?php if (!empty($j['foto_url'])): ?>
                                            <img referrerpolicy="no-referrer" class="jugador-foto" src="<?= htmlspecialchars($j['foto_url']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span class="jugador-foto" aria-hidden="true" style="display:grid;place-items:center;color:var(--ink-4)"><i class="ph ph-user"></i></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($j['nombre']) ?>
                                        <?php $wikiUrlSinTier = wiki_url_publica($j); ?>
                                        <?php if ($wikiUrlSinTier !== null): ?>
                                            <a href="<?= htmlspecialchars($wikiUrlSinTier) ?>" target="_blank" rel="noopener noreferrer" class="btn-icon" title="Ver en la Wiki" aria-label="Ver en la Wiki"><i class="ph ph-book-open-text"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($j['posicion']) ?></td>
                                <td class="caption"><?= htmlspecialchars($j['afinidad_nombre'] ?? '—') ?></td>
                                <td>
                                    <?php if ($misParticipaciones !== []): ?>
                                        <form method="post" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;min-width:340px">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                            <input type="hidden" name="accion" value="fichar_sin_tier">
                                            <input type="hidden" name="jugador_id" value="<?= (int) $j['id'] ?>">
                                            <select name="participacion_id" required class="select" style="height:32px;font-size:var(--fs-caption)">
                                                <?php foreach ($misParticipaciones as $p): ?>
                                                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['club_nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="tier_id" required class="select" style="height:32px;font-size:var(--fs-caption)" onchange="TRmercado.sugerirSalarioMinimo(this)">
                                                <?php foreach ($tiersDisponibles as $t): ?>
                                                    <option value="<?= (int) $t['id'] ?>" data-salario-base="<?= (float) $t['salario_base'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="number" name="salario_anual" min="1" step="1" placeholder="Salario" required class="input" style="height:32px;width:110px;font-size:var(--fs-caption)">
                                            <select name="duracion_temporadas" required class="select" style="height:32px;font-size:var(--fs-caption)" title="1 = conservas tanteo (RFA) al terminar. 2 = lo pierdes (UFA) al terminar.">
                                                <option value="1">1 temp. (RFA después)</option>
                                                <option value="2">2 temp. (UFA después)</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Fichar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <?php $renderizarPaginacionMercado($paginacionSinTier[$panelId]['pagina'], $paginacionSinTier[$panelId]['total'], $paginacionSinTier[$panelId]['param'], 'agentes-libres'); ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    </div>

    <?php if ($misParticipaciones !== []): ?>
        <div data-tab-panel="jugador-externo" hidden>
            <div class="card" style="max-width:560px">
                <h2 class="h3">Crear jugador externo y ficharlo</h2>
                <p class="caption" style="margin-top:.4rem">Un jugador que no existe en el JSON oficial — fuera de la liga. Se crea y se ficha en el mismo acto.</p>
                <form method="post" style="margin-top:1.5rem">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="accion" value="crear_y_fichar_externo">
                    <div class="field">
                        <label class="field-label" for="me-nombre">Nombre</label>
                        <input class="input" id="me-nombre" type="text" name="nombre" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-posicion">Posición</label>
                        <select class="select" id="me-posicion" name="posicion" required>
                            <option value="POR">POR</option>
                            <option value="DEF">DEF</option>
                            <option value="MED">MED</option>
                            <option value="DEL">DEL</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-afinidad">Afinidad</label>
                        <input class="input" id="me-afinidad" type="text" name="afinidad" placeholder="Opcional">
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-foto">Foto (URL)</label>
                        <input class="input" id="me-foto" type="url" name="foto_url" placeholder="Opcional">
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-club">Club</label>
                        <select class="select" id="me-club" name="participacion_id" required>
                            <?php foreach ($misParticipaciones as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['club_nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-tier">Tier</label>
                        <select class="select" id="me-tier" name="tier_id" required onchange="TRmercado.sugerirSalarioMinimo(this)">
                            <?php foreach ($tiersDisponibles as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" data-salario-base="<?= (float) $t['salario_base'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-salario">Salario anual</label>
                        <input class="input" id="me-salario" type="number" name="salario_anual" min="1" step="1" required>
                        <span class="caption" id="me-salario-ayuda"></span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="me-duracion">Duración</label>
                        <select class="select" id="me-duracion" name="duracion_temporadas" required>
                            <option value="1">1 temporada — conservas el derecho de tanteo (RFA) al terminar</option>
                            <option value="2">2 temporadas — pierdes el derecho de tanteo (UFA) al terminar</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Crear y fichar</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Modal unificado de jugador (Fase 2, componente compartido — ver partials/modal_jugador.php). -->
<?php include __DIR__ . '/../partials/modal_jugador.php'; ?>

<!-- Modal: ventana de igualación RFA (05-transfer_room_docs/02_ux_diseno/01, §1.3):
     cuenta atrás grande con color por urgencia, Offer Sheet recibida, Igualar/Dejar
     marchar con confirmación reforzada (el propio modal ya es la confirmación). -->
<div class="modal-bg" id="modal-rfa">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-rfa-title">
        <div class="modal-head">
            <h2 id="modal-rfa-title">Ventana de igualación RFA</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;padding:1rem 0">
                <span class="badge" id="modal-rfa-cuenta" style="font-size:1rem;padding:.6rem 1.2rem"></span>
            </div>
            <p><strong id="modal-rfa-jugador"></strong>: Offer Sheet recibida de <strong id="modal-rfa-club"></strong>.</p>
            <div class="card" style="margin-top:1rem">
                <span class="caption">Términos de la oferta</span>
                <div class="body-sm" style="margin-top:.5rem">
                    Salario: <span class="mono" id="modal-rfa-salario"></span><br>
                    Duración: <span id="modal-rfa-duracion"></span><br>
                    Plazo límite: <span class="mono" id="modal-rfa-fecha"></span>
                </div>
            </div>
        </div>
        <div class="modal-foot" id="modal-rfa-acciones"></div>
    </div>
</div>

<script>
var TRmercado = {
    /* Al elegir tier en un fichaje sin tier (o al crear un jugador externo),
       sugerir el salario mínimo de ese tier en vez de dejar el campo vacío y
       que el usuario tenga que adivinarlo por prueba y error contra el
       mensaje de error del servidor (que ya validaba este mínimo, pero solo
       lo comunicaba después de fallar el envío). */
    sugerirSalarioMinimo: function (selectTier) {
        var opt = selectTier.options[selectTier.selectedIndex];
        var base = parseFloat(opt.getAttribute('data-salario-base')) || 0;
        var form = selectTier.closest('form');
        if (!form) return;
        var input = form.querySelector('input[name="salario_anual"]');
        if (!input) return;
        input.min = base;
        if (!input.value || parseFloat(input.value) < base) {
            input.value = base;
        }
        var ayuda = form.querySelector('#me-salario-ayuda');
        if (ayuda) ayuda.textContent = 'Mínimo para ' + opt.textContent + ': ' + base.toLocaleString('es-ES') + ' €';
    },

    filtrarSinTier: function (panelId) {
        var tabla = document.getElementById('tabla-' + panelId);
        if (!tabla) return;
        var nombre = document.getElementById('filtro-' + panelId + '-nombre').value.trim().toLowerCase();
        var posicion = document.getElementById('filtro-' + panelId + '-posicion').value;
        tabla.querySelectorAll('tbody tr').forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1 && (posicion === '' || fila.dataset.posicion === posicion);
            fila.style.display = coincide ? '' : 'none';
        });
    },

    abrirModalRfa: function (datos) {
        var cuenta = document.getElementById('modal-rfa-cuenta');
        cuenta.textContent = datos.textoCuentaAtras;
        cuenta.className = 'badge ' + datos.urgencia;
        cuenta.setAttribute('data-fecha-limite-epoch', datos.fechaLimiteEpoch);
        TRmercado.iniciarTickeoModal();

        document.getElementById('modal-rfa-jugador').textContent = datos.jugadorNombre;
        document.getElementById('modal-rfa-club').textContent = datos.clubOfertante;
        document.getElementById('modal-rfa-salario').textContent = datos.salarioOfertado.toLocaleString('es-ES') + ' €';
        document.getElementById('modal-rfa-duracion').textContent = datos.duracion + ' temporada(s)';
        document.getElementById('modal-rfa-fecha').textContent = datos.fechaLimite;

        var csrf = '<?= htmlspecialchars(Csrf::token()) ?>';
        var acciones = document.getElementById('modal-rfa-acciones');
        acciones.innerHTML = '';

        if (datos.puedeIgualar) {
            acciones.innerHTML =
                '<form method="post"><input type="hidden" name="csrf_token" value="' + csrf + '">'
                + '<input type="hidden" name="accion" value="igualar_oferta">'
                + '<input type="hidden" name="jugador_id" value="' + datos.jugadorId + '">'
                + '<input type="hidden" name="participacion_id" value="' + datos.participacionOrigenId + '">'
                + '<button type="button" class="btn btn-secondary" data-modal-close style="margin-right:.5rem">Dejar marchar</button>'
                + '<button type="submit" class="btn btn-primary">Igualar oferta</button></form>';
        } else if (datos.confirmarSinIgualar) {
            acciones.innerHTML =
                '<form method="post"><input type="hidden" name="csrf_token" value="' + csrf + '">'
                + '<input type="hidden" name="accion" value="confirmar_sin_igualar">'
                + '<input type="hidden" name="jugador_id" value="' + datos.jugadorId + '">'
                + '<button type="button" class="btn btn-secondary" data-modal-close style="margin-right:.5rem">Cancelar</button>'
                + '<button type="submit" class="btn btn-primary">Confirmar sin igualar</button></form>';
        } else {
            acciones.innerHTML = '<button type="button" class="btn btn-secondary" data-modal-close>Cerrar</button>';
        }

        TR.abrirModal('modal-rfa');
    },

    filtrar: function () {
        var tabla = document.getElementById('tabla-agentes-libres');
        if (!tabla) return;

        var nombre = document.getElementById('filtro-nombre').value.trim().toLowerCase();
        var tier = document.getElementById('filtro-tier').value;
        var posicion = document.getElementById('filtro-posicion').value;
        var estado = document.getElementById('filtro-estado').value;
        var salarioMin = parseFloat(document.getElementById('filtro-salario-min').value) || 0;

        var filas = tabla.querySelectorAll('tbody tr');
        var visibles = 0;

        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1
                && (tier === '' || fila.dataset.tier === tier)
                && (posicion === '' || fila.dataset.posicion === posicion)
                && (estado === '' || fila.dataset.estado === estado)
                && parseFloat(fila.dataset.salario) >= salarioMin;

            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('filtro-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    },

    limpiarFiltros: function () {
        document.getElementById('filtro-nombre').value = '';
        document.getElementById('filtro-tier').value = '';
        document.getElementById('filtro-posicion').value = '';
        document.getElementById('filtro-estado').value = '';
        document.getElementById('filtro-salario-min').value = '';
        TRmercado.filtrar();
    },

    /* Punto 10 del inventario: el plazo YA se calcula en servidor (fuente de
       verdad); esto solo hace que el texto tickee en pantalla cada segundo en
       vez de quedarse congelado desde la carga de la página. Misma tabla de
       urgencia que mercado.php (<=0 y <6h: rojo, <24h: ámbar, resto: info). */
    formatearCuentaAtras: function (epochMs) {
        var msRestantes = epochMs - Date.now();
        var horasRestantes = msRestantes / 3600000;
        var urgencia = horasRestantes <= 0 ? 'badge-danger' : (horasRestantes < 6 ? 'badge-danger' : (horasRestantes < 24 ? 'badge-warning' : 'badge-info'));
        var texto;
        if (horasRestantes <= 0) {
            texto = 'Plazo vencido';
        } else if (horasRestantes < 1) {
            var minRestantes = Math.floor(msRestantes / 60000);
            var segRestantes = Math.floor((msRestantes % 60000) / 1000);
            texto = minRestantes + ' min ' + (segRestantes < 10 ? '0' : '') + segRestantes + 's restantes';
        } else {
            texto = Math.round(horasRestantes) + 'h restantes';
        }
        return { texto: texto, urgencia: urgencia };
    },

    tickearBadgesTabla: function () {
        document.querySelectorAll('.js-cuenta-atras[data-fecha-limite-epoch]').forEach(function (badge) {
            var epoch = parseInt(badge.getAttribute('data-fecha-limite-epoch'), 10);
            var r = TRmercado.formatearCuentaAtras(epoch);
            badge.querySelector('.js-cuenta-atras-texto').textContent = r.texto;
            badge.className = 'badge js-cuenta-atras ' + r.urgencia;
        });
    },

    _intervaloModalRfa: null,

    /* Se detiene sola en cuanto el modal se cierra, sin dejar el intervalo
       colgado (mismo criterio que exige el proyecto para las timelines de §14
       de otros módulos: nunca dejar un temporizador vivo sin su UI). */
    iniciarTickeoModal: function () {
        if (TRmercado._intervaloModalRfa) clearInterval(TRmercado._intervaloModalRfa);
        var tick = function () {
            var modal = document.getElementById('modal-rfa');
            if (!modal || !modal.classList.contains('open')) {
                clearInterval(TRmercado._intervaloModalRfa);
                TRmercado._intervaloModalRfa = null;
                return;
            }
            var cuenta = document.getElementById('modal-rfa-cuenta');
            var epoch = parseInt(cuenta.getAttribute('data-fecha-limite-epoch'), 10);
            var r = TRmercado.formatearCuentaAtras(epoch);
            cuenta.textContent = r.texto;
            cuenta.className = 'badge ' + r.urgencia;
        };
        tick();
        TRmercado._intervaloModalRfa = setInterval(tick, 1000);
    }
};

var TRmercadoTodos = {
    filtrar: function () {
        var tabla = document.getElementById('tabla-todos-jugadores');
        if (!tabla) return;

        var nombre = document.getElementById('filtro-todos-nombre').value.trim().toLowerCase();
        var tier = document.getElementById('filtro-todos-tier').value;
        var posicion = document.getElementById('filtro-todos-posicion').value;
        var afinidad = document.getElementById('filtro-todos-afinidad').value;
        var estado = document.getElementById('filtro-todos-estado').value;
        var club = document.getElementById('filtro-todos-club').value;

        var filas = tabla.querySelectorAll('tbody tr');
        var visibles = 0;

        filas.forEach(function (fila) {
            var coincide = fila.dataset.nombre.indexOf(nombre) !== -1
                && (tier === '' || fila.dataset.tier === tier)
                && (posicion === '' || fila.dataset.posicion === posicion)
                && (afinidad === '' || fila.dataset.afinidad === afinidad)
                && (estado === '' || fila.dataset.estado === estado)
                && (club === '' || fila.dataset.club === club);

            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });

        document.getElementById('filtro-todos-resumen').textContent = visibles + ' de ' + filas.length + ' jugadores';
    },

    limpiarFiltros: function () {
        document.getElementById('filtro-todos-nombre').value = '';
        document.getElementById('filtro-todos-tier').value = '';
        document.getElementById('filtro-todos-posicion').value = '';
        document.getElementById('filtro-todos-afinidad').value = '';
        document.getElementById('filtro-todos-estado').value = '';
        document.getElementById('filtro-todos-club').value = '';
        TRmercadoTodos.filtrar();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('tabla-agentes-libres')) TRmercado.filtrar();
    if (document.getElementById('tabla-todos-jugadores')) TRmercadoTodos.filtrar();
    if (document.querySelector('.js-cuenta-atras')) setInterval(TRmercado.tickearBadgesTabla, 1000);
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
