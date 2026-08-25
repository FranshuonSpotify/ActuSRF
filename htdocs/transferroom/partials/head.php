<?php
/**
 * Abre el documento: fuentes/CSS/iconos, <body>, skip-link.
 * Variables esperadas del llamador: $paginaTitulo (string).
 * $base: '' en la raíz de publico/, '../' desde publico/admin/.
 */
$base = $base ?? '';

/**
 * Jerarquía visual de tier (02_ux_diseno/02, §1): clase CSS adicional para
 * el .chip del tier, creciente por categoría. Única función compartida de
 * vista del proyecto — no hace falta un sistema de componentes para una
 * sola función de 10 líneas (YAGNI).
 */
if (!function_exists('tier_chip_class')) {
    function tier_chip_class(?string $tierNombre): string
    {
        return match ($tierNombre) {
            'A', 'A+', 'S' => 'tier-alto',
            'S+', 'S++' => 'tier-top',
            'B', 'B+', 'A-' => 'tier-medio',
            default => '',
        };
    }
}

/**
 * Convierte un valor de ENUM/estado tal cual viene de la base de datos
 * (MAYUSCULAS_CON_GUION_BAJO) en texto legible para la interfaz — nunca se
 * imprime el valor crudo (Fase 2, ronda de feedback: "DOTACION_INICIAL" se
 * veía tal cual en Finanzas). Diccionario explícito para los valores reales
 * del proyecto (para que lleven tilde/mayúscula correctas); genérico como
 * red de seguridad para cualquier ENUM nuevo que se añada más adelante.
 */
if (!function_exists('etiqueta_legible')) {
    function etiqueta_legible(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        static $diccionario = [
            'DOTACION_INICIAL' => 'Dotación inicial',
            'PAGO_TRASPASO' => 'Pago de traspaso',
            'COBRO_TRASPASO' => 'Cobro de traspaso',
            'AJUSTE_ADMINISTRATIVO' => 'Ajuste administrativo',
            'JSON_OFICIAL' => 'JSON oficial',
            'EXTERNO' => 'Externo',
            'ACTIVO' => 'Activo',
            'BLOQUEADO' => 'Bloqueado',
            'AGENTE_LIBRE' => 'Agente libre',
            'PENDIENTE' => 'Pendiente',
            'CONFIRMADA' => 'Confirmada',
            'ACTIVA' => 'Activa',
            'SUSPENDIDA' => 'Suspendida',
            'RETIRADA' => 'Retirada',
            'FINALIZADA' => 'Finalizada',
            'ARCHIVADA' => 'Archivada',
            'ABIERTA' => 'Abierta',
            'CERRADA' => 'Cerrada',
            'CERRADA_AUTOMATICAMENTE' => 'Cerrada automáticamente',
            'ACEPTADA' => 'Aceptada',
            'RECHAZADA' => 'Rechazada',
            'CANCELADA' => 'Cancelada',
            'CONFIGURACION' => 'Configuración',
            'PRETEMPORADA' => 'Pretemporada',
            'MERCADO_ABIERTO' => 'Mercado abierto',
            'COMPETICION' => 'Competición',
            'MERCADO_EXTRAORDINARIO' => 'Mercado extraordinario',
            'CIERRE' => 'Cierre',
        ];

        if (isset($diccionario[$valor])) {
            return $diccionario[$valor];
        }

        // Red de seguridad genérica: MAYUSCULAS_CON_GUION -> "Mayusculas con guion".
        return ucfirst(mb_strtolower(str_replace('_', ' ', $valor)));
    }
}

/**
 * URL pública de la wiki para un jugador (ESPECIFICACION_CLAUDE_WIKI_INAZUMA.md
 * §32): solo se expone si wiki_status es matched o manual. needs_review,
 * not_found, pending y error se comportan como "sin enlace" de cara al
 * público — nunca se muestra una coincidencia dudosa (§29).
 */
if (!function_exists('wiki_url_publica')) {
    function wiki_url_publica(array $jugador): ?string
    {
        $estado = $jugador['wiki_status'] ?? null;

        return in_array($estado, ['matched', 'manual'], true) ? ($jugador['wiki_url'] ?? null) : null;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Transfer Room · <?= htmlspecialchars($paginaTitulo ?? '') ?></title>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
<!-- El peso "fill" (ph-fill) se usa en toda la app (favoritos, franquicia, login...)
     pero nunca se cargaba su hoja de estilo: los glifos ph-fill no existían y
     el icono no se pintaba. Bug real — encontrado en los iconos de favoritos. -->
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
<?php
// Cache-busting real (no solo Ctrl+F5 de por vida): la versión en la URL es
// la fecha de modificación del propio fichero, así que un cambio en el CSS
// cambia la URL y el navegador no puede servir la copia vieja de caché.
if (!function_exists('asset_version')) {
    /**
     * Cache-busting real (no solo "acuérdate de Ctrl+F5"): la versión en la
     * URL es la fecha de modificación del propio fichero, así que editar el
     * CSS/JS cambia la URL y el navegador no puede servir la copia vieja de
     * caché. La ruta en disco es siempre publico/assets/..., sea cual sea
     * $base (que solo cambia cómo el NAVEGADOR resuelve la URL según si la
     * página vive en publico/ o en publico/admin/) — partials/ está fuera de
     * la raíz servida, un nivel por encima de publico/.
     */
    function asset_version(string $base, string $rutaRelativa): string
    {
        $rutaAbsoluta = __DIR__ . '/../publico/' . $rutaRelativa;

        return $base . $rutaRelativa . '?v=' . (is_file($rutaAbsoluta) ? filemtime($rutaAbsoluta) : time());
    }
}
?>
<link rel="icon" type="image/svg+xml" href="<?= asset_version($base, 'assets/favicon/favicon.svg') ?>">
<link rel="icon" type="image/png" sizes="96x96" href="<?= asset_version($base, 'assets/favicon/favicon-96x96.png') ?>">
<link rel="shortcut icon" href="<?= asset_version($base, 'assets/favicon/favicon.ico') ?>">
<link rel="apple-touch-icon" href="<?= asset_version($base, 'assets/favicon/apple-touch-icon.png') ?>">
<link rel="manifest" href="<?= asset_version($base, 'assets/favicon/site.webmanifest') ?>">
<link rel="stylesheet" href="<?= asset_version($base, 'assets/css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset_version($base, 'assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= asset_version($base, 'assets/css/components.css') ?>">
<script src="<?= asset_version($base, 'assets/js/vendor/gsap/gsap.min.js') ?>"></script>
<script>
// Accesibilidad (Fase 2, Cuenta): preferencia solo de cliente, antes del primer
// pintado para no dar un parpadeo. No usa el servidor: es puramente visual.
(function () {
    var raiz = document.documentElement;
    if (localStorage.getItem('tr_a11y_reducir_movimiento') === '1') raiz.classList.add('a11y-reducir-movimiento');
    if (localStorage.getItem('tr_a11y_alto_contraste') === '1') raiz.classList.add('a11y-alto-contraste');
})();
</script>
</head>
<body>
<a href="#contenido" class="skip-link">Saltar al contenido</a>
<div class="cur" id="cur" aria-hidden="true"></div>
