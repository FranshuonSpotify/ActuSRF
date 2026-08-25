<?php
/** Pie + aviso legal + carga de ui.js. $base ya viene definido por el llamador. */
$base = $base ?? '';
?>
<footer class="wrap">
  <div class="legal-note">Transfer Room es un proyecto fan-made sin ánimo de lucro para la Superliga Frontier. Sin afiliación con Level-5 / Inazuma Eleven.</div>
  <a href="<?= $base ?>reportar_problema.php?origen=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" class="caption">Reportar un problema</a>
</footer>

<script>
  // Bug real: ui.js pedía notificaciones_recientes.php con una ruta relativa
  // fija ("notificaciones_recientes.php"), que desde publico/admin/*.php
  // resolvía a admin/notificaciones_recientes.php (404) — el sondeo de
  // notificaciones y los avisos de cierre de mercado no funcionaban nunca
  // dentro de admin/. TR_BASE deja que ui.js reconstruya la ruta correcta.
  window.TR_BASE = '<?= htmlspecialchars($base, ENT_QUOTES) ?>';
</script>
<script src="<?= asset_version($base, 'assets/js/ui.js') ?>"></script>
<script src="<?= asset_version($base, 'assets/js/modal_jugador.js') ?>"></script>
</body>
</html>
