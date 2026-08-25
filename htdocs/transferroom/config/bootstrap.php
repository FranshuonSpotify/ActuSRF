<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Quejas esperables #8: "volví atrás y vi datos viejos". Todo dato de mercado,
// plantilla y ofertas es sensible al instante; nunca se sirve desde caché ni
// desde el historial del navegador.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

spl_autoload_register(static function (string $clase): void {
    static $carpetas = [
        __DIR__ . '/../dominio/Configuracion/',
        __DIR__ . '/../dominio/Autenticacion/',
        __DIR__ . '/../dominio/Permisos/',
        __DIR__ . '/../dominio/Temporadas/',
        __DIR__ . '/../dominio/Clubes/',
        __DIR__ . '/../dominio/Jugadores/',
        __DIR__ . '/../dominio/Contratos/',
        __DIR__ . '/../dominio/Mercado/',
        __DIR__ . '/../dominio/Finanzas/',
        __DIR__ . '/../dominio/Notificaciones/',
        __DIR__ . '/../dominio/Peticiones/',
        __DIR__ . '/../dominio/Mensajes/',
        __DIR__ . '/../dominio/Favoritos/',
        __DIR__ . '/../dominio/Estrategia/',
        __DIR__ . '/../dominio/Preferencias/',
        __DIR__ . '/../dominio/Anuncios/',
        __DIR__ . '/../dominio/Wiki/',
        __DIR__ . '/../infraestructura/Wiki/',
        __DIR__ . '/../infraestructura/Repositorios/',
        __DIR__ . '/../infraestructura/Auditoria/',
        __DIR__ . '/../infraestructura/Seguridad/',
        __DIR__ . '/../infraestructura/JsonOficial/',
        __DIR__ . '/../infraestructura/Backup/',
        __DIR__ . '/../infraestructura/Errores/',
    ];

    foreach ($carpetas as $carpeta) {
        $archivo = $carpeta . $clase . '.php';
        if (is_file($archivo)) {
            require $archivo;
            return;
        }
    }
});

require_once __DIR__ . '/conexion.php'; // define $db

ErrorLogger::instalar($db);

$rutaJsonOficial = __DIR__ . '/../02-datos_oficiales/datos_oficiales.json';

$auditoriaRepositorio = new AuditoriaRepository($db);
$auditoria = new AuditEngine($auditoriaRepositorio);

$configuracionRepositorio = new ConfiguracionRepository($db);
$configuracion = new ConfigurationEngine($configuracionRepositorio, $auditoria);

$usuarioRepositorio = new UsuarioRepository($db);
$tokenRecuperacionRepositorio = new TokenRecuperacionRepository($db);
$autenticacion = new AuthenticationEngine($usuarioRepositorio, $auditoria, $tokenRecuperacionRepositorio);
$permisos = new PermissionEngine();

$temporadaRepositorio = new TemporadaRepository($db);

$clubRepositorio = new ClubRepository($db);
$sincronizadorJson = new SincronizadorJson($rutaJsonOficial);
$clubes = new ClubEngine($clubRepositorio, $sincronizadorJson, $auditoria);

$participacionRepositorio = new ParticipacionRepository($db);
$participaciones = new ParticipationEngine(
    $participacionRepositorio,
    $clubRepositorio,
    $temporadaRepositorio,
    $auditoria
);

$jugadorRepositorio = new JugadorRepository($db);
$afinidadRepositorio = new AfinidadRepository($db);
$tierRepositorio = new TierRepository($db);
$wikiProvider = new FandomWikiProvider();
$wikiResolver = new WikiResolverEngine($wikiProvider);
$jugadores = new JugadorEngine(
    $db,
    $jugadorRepositorio,
    $afinidadRepositorio,
    $participacionRepositorio,
    $temporadaRepositorio,
    $clubRepositorio,
    $sincronizadorJson,
    $auditoria,
    $wikiResolver
);

$movimientoFinancieroRepositorio = new MovimientoFinancieroRepository($db);
$financiero = new FinancialEngine($movimientoFinancieroRepositorio, $auditoria);

$contratoRepositorio = new ContratoRepository($db);
$contratos = new ContractEngine(
    $db,
    $contratoRepositorio,
    $jugadorRepositorio,
    $tierRepositorio,
    $participacionRepositorio,
    $temporadaRepositorio,
    $auditoria,
    $configuracion,
    $financiero
);

$snapshotsTemporadaRepositorio = new SnapshotTemporadaRepository($db);

$temporadas = new SeasonEngine(
    $db,
    $temporadaRepositorio,
    $configuracion,
    $auditoria,
    $participacionRepositorio,
    $contratoRepositorio,
    $movimientoFinancieroRepositorio,
    $snapshotsTemporadaRepositorio,
    $jugadorRepositorio
);

$ofertaAgenteLibreRepositorio = new OfertaAgenteLibreRepository($db);
$mercado = new MarketEngine(
    $ofertaAgenteLibreRepositorio,
    $jugadorRepositorio,
    $tierRepositorio,
    $participacionRepositorio,
    $contratos,
    $auditoria,
    $temporadaRepositorio,
    $configuracion
);

$financiero = new FinancialEngine($movimientoFinancieroRepositorio, $auditoria);

$ofertaTraspasoRepositorio = new OfertaTraspasoRepository($db);
$ofertaTraspasoJugadorRepositorio = new OfertaTraspasoJugadorRepository($db);
$traspasos = new TransferEngine(
    $db,
    $ofertaTraspasoRepositorio,
    $ofertaTraspasoJugadorRepositorio,
    $contratoRepositorio,
    $jugadorRepositorio,
    $participacionRepositorio,
    $temporadaRepositorio,
    $financiero,
    $auditoria
);

$peticionRepositorio = new PeticionRepository($db);
$peticionPropuestaRepositorio = new PeticionPropuestaRepository($db);
$peticiones = new PeticionEngine($peticionRepositorio, $peticionPropuestaRepositorio, $participacionRepositorio, $auditoria);

$preferenciaRepositorio = new PreferenciaRepository($db);
$preferencias = new PreferenciaEngine($preferenciaRepositorio);

$notificacionRepositorio = new NotificacionRepository($db);
$notificaciones = new NotificationEngine($notificacionRepositorio, $preferenciaRepositorio);

$conversacionRepositorio = new ConversacionRepository($db);
$mensajeRepositorio = new MensajeRepository($db);
$mensajes = new MessageEngine($conversacionRepositorio, $mensajeRepositorio, $usuarioRepositorio);

$favoritoRepositorio = new FavoritoRepository($db);
$favoritos = new FavoritoEngine($favoritoRepositorio);

$estrategiaRepositorio = new EstrategiaRepository($db);
$estrategia = new EstrategiaEngine($estrategiaRepositorio);

$anuncioRepositorio = new AnuncioRepository($db);
$anuncios = new AnuncioEngine($anuncioRepositorio);
