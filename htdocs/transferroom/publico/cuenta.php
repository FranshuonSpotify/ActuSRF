<?php

declare(strict_types=1);

/**
 * Cuenta (Fase 2, hub v3, ampliación): contraseña + notificaciones
 * granulares + accesibilidad + sesiones activas + exportar datos + perfil
 * público. Sin especificación previa para lo nuevo — diseño propio.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();
$usuarioId = (int) $usuario['id'];

// Tipos de notificación reales usados en la app (mismo mapa que notificaciones.php),
// con etiqueta legible para el opt-out granular.
const TIPOS_NOTIFICACION = [
    'OFERTA_TRASPASO_RECIBIDA' => 'Oferta de traspaso recibida',
    'TRASPASO_ACEPTADO' => 'Traspaso aceptado',
    'TRASPASO_RECHAZADO' => 'Traspaso rechazado',
    'CONTRAOFERTA_TRASPASO_RECIBIDA' => 'Contraoferta de traspaso recibida',
    'TRASPASO_CANCELADO_POR_RETIRADA' => 'Traspaso cancelado por retirada de un club',
    'VENTANA_IGUALACION_ABIERTA' => 'Ventana de igualación RFA abierta',
    'RULETA_FRANQUICIA_RESUELTA' => 'Ruleta de franquicia resuelta',
    'FICHAJE_AGENTE_LIBRE_GANADO' => 'Fichaje de agente libre ganado',
    'FICHAJE_AGENTE_LIBRE_PERDIDO' => 'Fichaje de agente libre perdido',
    'CONTRATO_PENDIENTE_REVISION' => 'Contrato pendiente de revisión',
    'CONTRATO_FINALIZADO' => 'Contrato finalizado',
    'CONTRATO_REVISADO' => 'Contrato revisado',
    'CONTRATO_RECHAZADO' => 'Contrato rechazado',
    'ESTADO_MERCADO' => 'Cambios de estado del mercado',
    'CIERRE_MERCADO_INMINENTE' => 'Cierre de mercado inminente',
    'PLANTILLA_INICIAL_ASIGNADA' => 'Plantilla inicial asignada',
    'PROPUESTA_PETICION_RECIBIDA' => 'Propuesta recibida en el tablón de Peticiones',
    'PROPUESTA_PETICION_ACEPTADA' => 'Propuesta aceptada en el tablón de Peticiones',
    'OFERTA_SOBRE_JUGADOR_SEGUIDO' => 'Oferta sobre un jugador que sigues en Scouting',
];

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validar($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario ha caducado. Inténtalo de nuevo.';
    } else {
        try {
            switch ($_POST['accion'] ?? '') {
                case 'cambiar_password':
                    if ((string) ($_POST['password_nueva'] ?? '') !== (string) ($_POST['password_nueva_confirmar'] ?? '')) {
                        throw new DomainException('Las dos contraseñas nuevas no coinciden.');
                    }
                    $autenticacion->cambiarPasswordPropia(
                        $usuarioId,
                        (string) ($_POST['password_actual'] ?? ''),
                        (string) ($_POST['password_nueva'] ?? '')
                    );
                    $exito = 'Contraseña actualizada.';
                    break;

                case 'guardar_notificaciones':
                    $activas = (array) ($_POST['notif'] ?? []);
                    foreach (array_keys(TIPOS_NOTIFICACION) as $tipo) {
                        $preferencias->establecerBool($usuarioId, 'notif_' . $tipo, in_array($tipo, $activas, true));
                    }
                    $exito = 'Preferencias de notificación guardadas.';
                    break;

                case 'cerrar_otras_sesiones':
                    $autenticacion->cerrarOtrasSesiones($usuarioId);
                    $exito = 'Se han cerrado todas las demás sesiones abiertas con tu cuenta.';
                    break;

                case 'guardar_perfil':
                    $preferencias->establecerBool($usuarioId, 'perfil_publico', isset($_POST['perfil_publico']));
                    $preferencias->establecerTexto($usuarioId, 'perfil_bio', trim((string) ($_POST['perfil_bio'] ?? '')));
                    $exito = 'Perfil público actualizado.';
                    break;

                default:
                    $error = 'Acción no reconocida.';
            }
        } catch (DomainException $e) {
            $error = $e->getMessage();
        }
    }
}

$preferenciasUsuario = $preferencias->listarPorUsuario($usuarioId);

$paginaTitulo = 'Cuenta';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'cuenta';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec" style="max-width:640px">
    <div class="page-head">
        <div>
            <span class="overline">Cuenta</span>
            <h1 class="h1" style="margin-top:.5rem"><?= htmlspecialchars($usuario['nombre']) ?></h1>
            <p class="caption"><?= htmlspecialchars($usuario['email']) ?></p>
        </div>
    </div>

    <?php if ($error !== null): ?><div class="alert alert-danger" role="alert" style="margin-bottom:1rem"><i class="ph ph-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($exito !== null): ?><div class="alert alert-success" style="margin-bottom:1rem"><i class="ph ph-check-circle"></i> <?= htmlspecialchars($exito) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:1.5rem">
        <h2 class="h3" style="margin-bottom:1rem">Cambiar contraseña</h2>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="field">
                <label class="field-label" for="cuenta-password-actual">Contraseña actual</label>
                <input class="input" type="password" id="cuenta-password-actual" name="password_actual" required>
            </div>
            <div class="field">
                <label class="field-label" for="cuenta-password-nueva">Contraseña nueva</label>
                <input class="input" type="password" id="cuenta-password-nueva" name="password_nueva" minlength="8" required>
            </div>
            <div class="field">
                <label class="field-label" for="cuenta-password-nueva-confirmar">Repite la contraseña nueva</label>
                <input class="input" type="password" id="cuenta-password-nueva-confirmar" name="password_nueva_confirmar" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary">Guardar contraseña</button>
        </form>
    </div>

    <div class="card" style="margin-bottom:1.5rem">
        <h2 class="h3" style="margin-bottom:.4rem">Notificaciones</h2>
        <p class="caption" style="margin-bottom:1rem">Desmarca los eventos que no quieras recibir. Todo está activado por defecto.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="guardar_notificaciones">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.5rem 1.5rem;margin-bottom:1rem">
                <?php foreach (TIPOS_NOTIFICACION as $tipo => $etiqueta): ?>
                    <?php $activo = ($preferenciasUsuario['notif_' . $tipo] ?? '1') !== '0'; ?>
                    <label style="display:flex;align-items:center;gap:.6rem;min-height:44px">
                        <input type="checkbox" name="notif[]" value="<?= htmlspecialchars($tipo) ?>" <?= $activo ? 'checked' : '' ?>>
                        <span class="body-sm"><?= htmlspecialchars($etiqueta) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">Guardar preferencias</button>
        </form>
    </div>

    <div class="card" style="margin-bottom:1.5rem">
        <h2 class="h3" style="margin-bottom:.4rem">Accesibilidad</h2>
        <p class="caption" style="margin-bottom:1rem">Se guarda solo en este navegador, se aplica al instante en toda la web.</p>
        <div style="display:flex;flex-direction:column;gap:.75rem">
            <label style="display:flex;align-items:center;gap:.6rem;min-height:44px">
                <input type="checkbox" id="a11y-reducir-movimiento">
                <span class="body-sm">Reducir el movimiento (además de lo que ya respeta tu sistema operativo)</span>
            </label>
            <label style="display:flex;align-items:center;gap:.6rem;min-height:44px">
                <input type="checkbox" id="a11y-alto-contraste">
                <span class="body-sm">Aumentar el contraste</span>
            </label>
        </div>
    </div>

    <div class="card" style="margin-bottom:1.5rem">
        <h2 class="h3" style="margin-bottom:.4rem">Sesiones activas</h2>
        <p class="caption" style="margin-bottom:1rem">
            Sesión actual iniciada el <?= htmlspecialchars(date('d/m/Y H:i', (int) ($_SESSION['autenticado_en'] ?? time()))) ?>.
            No hay un listado de dispositivos: esto invalida cualquier otra sesión abierta con tu cuenta, en cualquier dispositivo, sin cerrar la tuya.
        </p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="cerrar_otras_sesiones">
            <button type="submit" class="btn btn-ghost">Cerrar sesión en otros dispositivos</button>
        </form>
    </div>

    <div class="card" style="margin-bottom:1.5rem">
        <h2 class="h3" style="margin-bottom:.4rem">Exportar mis datos</h2>
        <p class="caption" style="margin-bottom:1rem">Descarga un archivo JSON con tu perfil, tus clubes gestionados, tus notificaciones y tus preferencias.</p>
        <a class="btn btn-ghost" href="exportar_datos.php"><i class="ph ph-download-simple"></i> Descargar mis datos</a>
    </div>

    <div class="card">
        <h2 class="h3" style="margin-bottom:.4rem">Perfil público</h2>
        <p class="caption" style="margin-bottom:1rem">Si lo activas, otros presidentes verán esta descripción en la Liga/Clubes junto a tu club.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="guardar_perfil">
            <label style="display:flex;align-items:center;gap:.6rem;min-height:44px;margin-bottom:.75rem">
                <input type="checkbox" name="perfil_publico" <?= ($preferenciasUsuario['perfil_publico'] ?? '0') === '1' ? 'checked' : '' ?>>
                <span class="body-sm">Mostrar mi descripción a otros presidentes</span>
            </label>
            <div class="field">
                <label class="field-label" for="perfil-bio">Descripción</label>
                <textarea class="input" id="perfil-bio" name="perfil_bio" rows="3" maxlength="500"><?= htmlspecialchars($preferenciasUsuario['perfil_bio'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar perfil</button>
        </form>
    </div>
</main>
<script>
(function () {
    var movimiento = document.getElementById('a11y-reducir-movimiento');
    var contraste = document.getElementById('a11y-alto-contraste');
    movimiento.checked = localStorage.getItem('tr_a11y_reducir_movimiento') === '1';
    contraste.checked = localStorage.getItem('tr_a11y_alto_contraste') === '1';

    movimiento.addEventListener('change', function () {
        localStorage.setItem('tr_a11y_reducir_movimiento', movimiento.checked ? '1' : '0');
        document.documentElement.classList.toggle('a11y-reducir-movimiento', movimiento.checked);
    });
    contraste.addEventListener('change', function () {
        localStorage.setItem('tr_a11y_alto_contraste', contraste.checked ? '1' : '0');
        document.documentElement.classList.toggle('a11y-alto-contraste', contraste.checked);
    });
})();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
