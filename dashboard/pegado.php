<?php
// dashboard/pegado.php
// Pegado masivo de plantilla: una línea por jugador, "Nombre;POS;TIER", con
// previsualización antes de guardar. Existe porque teclear veinte jugadores
// por equipo en formularios es más lento que la hoja de cálculo que esta app
// sustituye, y una herramienta más lenta que lo que reemplaza no se adopta.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/almacen.php';   // arrastra lib.php y dominio.php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/chrome.php';

plEstablecerIdioma(plResolverIdioma());

$usuario = plUsuarioActual();
if ($usuario === null) {
    plRedirigir('index.php');
}

$temporada = plTemporadaActiva();
$fase      = plFaseActiva();
$equipoId  = (string) ($usuario['equipoId'] ?? '');
$enTemp    = ($temporada !== null && $equipoId !== '') ? plEquipoEnTemporada($temporada['id'], $equipoId) : null;
$editable  = $enTemp !== null && plPuedeEditarPlantilla($fase);

$texto    = '';
$analisis = null;
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!plCsrfValido()) {
        plCortar(403, plT('error.csrf'));
    }

    $texto = (string) ($_POST['texto'] ?? '');

    if (!$editable) {
        $error = plT('aviso.plantilla_cerrada');
    } else {
        // Se analiza SIEMPRE en el servidor, también al confirmar. La
        // previsualización anterior es solo lo que el presidente vio; el texto
        // viaja otra vez en el formulario de confirmación y podría no ser el
        // mismo, así que lo que manda es este análisis, no aquel.
        $analisis = plParsearPegado($texto, $enTemp['jugadores'], $enTemp['ajustes']);

        if (($_POST['accion'] ?? '') === 'confirmar' && $analisis['ok']) {
            $jugadores = $enTemp['jugadores'];
            foreach ($analisis['lineas'] as $l) {
                $jugadores[] = [
                    'id'            => plNuevoIdJugador(),
                    'nombre'        => $l['nombre'],
                    'posicion'      => $l['posicion'],
                    'tier'          => $l['tier'],
                    'salario'       => $l['salario'],
                    'clausula'      => 0,
                    'estado'        => 'DISPONIBLE',
                    'clausuladoPor' => null,
                    'clausuladoEn'  => null,
                ];
            }

            // Una sola escritura para todo el lote: o entran los veinte o no
            // entra ninguno, y el rev sube exactamente una vez.
            $g = plGuardarEquipoTemporada($temporada['id'], $equipoId, ['jugadores' => $jugadores], (int) ($_POST['rev'] ?? -1));
            if ($g['ok']) {
                $_SESSION['pl_flash'] = plT('pegado.guardado', ['n' => $analisis['validas']]);
                plRedirigir('plantilla.php');
            }
            $error = plT($g['error'] ?? 'error.escritura');

            // Tras un rechazo, el análisis se rehace contra el estado real del
            // disco: si fue un rev desfasado, el copresidente pudo cambiarlo.
            $enTemp   = plEquipoEnTemporada($temporada['id'], $equipoId);
            $analisis = plParsearPegado($texto, $enTemp['jugadores'] ?? [], $enTemp['ajustes'] ?? []);
        }
    }
}

$rev = (int) ($enTemp['rev'] ?? 0);

plCabecera(plT('pegado.titulo'), 'plantilla', $fase);
?>
<header class="dash-cabecera">
  <h1><?= plEsc(plT('pegado.titulo')) ?></h1>
  <p><a href="plantilla.php"><?= plEsc(plT('pegado.volver')) ?></a></p>
</header>

<?php if ($error !== ''): ?>
  <p class="mal" role="alert"><?= plEsc($error) ?></p>
<?php endif; ?>

<?php if ($equipoId === ''): ?>
  <p class="mal"><?= plEsc(plT('aviso.sin_equipo')) ?></p>
<?php elseif ($temporada === null): ?>
  <p class="ayuda"><?= plEsc(plT('aviso.sin_temporada')) ?></p>
<?php elseif ($enTemp === null): ?>
  <p class="mal"><?= plEsc(plT('aviso.equipo_fuera')) ?></p>
<?php elseif (!$editable): ?>
  <?php if ($error === ''): ?>
    <p class="aviso" role="status"><?= plEsc(plT('aviso.plantilla_cerrada')) ?></p>
  <?php endif; ?>
<?php else: ?>

  <?php
    // Reconocimiento de capturas de pantalla, en el propio navegador (Tesseract.js
    // por CDN — única excepción documentada a la regla de "sin CDN de JS" de
    // dashboard/, decidida expresamente para esto). La imagen nunca sale del
    // dispositivo del presidente ni pasa por el servidor: el resultado solo
    // rellena el mismo cuadro de texto de abajo, y de ahí en adelante es el
    // mismo camino de siempre — "Previsualizar" revalida todo en el servidor,
    // igual que con un pegado manual. Si Tesseract.js no llega a cargar (CDN
    // caído, sin conexión), esta sección deja de servir para nada, pero el
    // pegado de texto de siempre sigue funcionando exactamente igual.
  ?>
  <section class="card dash-seccion" id="ocr-seccion">
    <h2><?= plEsc(plT('pegado.ocr_titulo')) ?></h2>
    <p class="ayuda"><?= plEsc(plT('pegado.ocr_explicacion')) ?></p>
    <div class="dash-form-fila">
      <label class="campo">
        <span class="sr-only"><?= plEsc(plT('pegado.ocr_titulo')) ?></span>
        <input class="inp" type="file" id="ocr-archivo" accept="image/*">
      </label>
      <button class="btn btn-secondary" type="button" id="ocr-boton" disabled><?= plEsc(plT('pegado.ocr_boton')) ?></button>
    </div>
    <?php // data-txt-*, no texto embebido en el <script>: un apóstrofo en
          // francés o italiano rompería una cadena JS de comillas simples. ?>
    <p id="ocr-estado" role="status" aria-live="polite"
       data-txt-procesando="<?= plEsc(plT('pegado.ocr_procesando')) ?>"
       data-txt-resultado="<?= plEsc(plT('pegado.ocr_resultado')) ?>"
       data-txt-vacio="<?= plEsc(plT('pegado.ocr_vacio')) ?>"
       data-txt-error="<?= plEsc(plT('pegado.ocr_error')) ?>"></p>
  </section>

  <section class="card dash-seccion">
    <p class="ayuda"><?= plEsc(plT('pegado.explicacion')) ?></p>
    <p class="ayuda"><code><?= plEsc(plT('pegado.ejemplo')) ?></code></p>
    <form method="post" action="pegado.php">
      <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
      <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
      <input type="hidden" name="accion" value="previsualizar">
      <label class="campo">
        <span><?= plEsc(plT('pegado.campo')) ?></span>
        <textarea class="inp inp-mono" id="pegado-texto" name="texto" rows="12" spellcheck="false"><?= plEsc($texto) ?></textarea>
      </label>
      <button class="btn btn-secondary" type="submit"><?= plEsc(plT('pegado.previsualizar')) ?></button>
    </form>
  </section>

  <?php if ($analisis !== null && $analisis['lineas'] !== []): ?>
    <section class="dash-seccion" aria-live="polite">
      <div class="tabla-scroll">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col"><?= plEsc(plT('pegado.col_linea')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.jugador')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.pos')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.tier')) ?></th>
              <th scope="col"><?= plEsc(plT('tabla.salario')) ?></th>
              <th scope="col"><?= plEsc(plT('pegado.col_resultado')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($analisis['lineas'] as $l): ?>
              <tr<?= $l['error'] !== null ? ' class="con-error"' : '' ?>>
                <td class="cifra"><?= plEsc($l['n']) ?></td>
                <td><?= plEsc($l['nombre'] !== '' ? $l['nombre'] : $l['texto']) ?></td>
                <td><?= plEsc(plPosicionTexto($l['posicion'])) ?></td>
                <td class="cifra"><?= plEsc($l['tier']) ?></td>
                <td class="cifra"><?= $l['error'] === null ? plEsc(plM($l['salario'])) : '' ?></td>
                <td><?= plEsc($l['error'] === null ? plT('pegado.linea_ok') : plT($l['error'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="cifras">
        <?= plEsc(plT('pegado.resumen', [
            'n'        => $analisis['validas'],
            'total'    => $analisis['totalJugadores'],
            'max'      => $analisis['maximo'],
            'salarios' => plM($analisis['totalSalarios']),
            'cap'      => plM($analisis['cap']),
        ])) ?>
      </p>

      <?php if ($analisis['errores'] > 0): ?>
        <p class="mal"><?= plEsc(plT('pegado.errores_lineas', ['n' => $analisis['errores']])) ?></p>
      <?php elseif ($analisis['errorLote'] !== null): ?>
        <p class="mal"><?= plEsc(plT($analisis['errorLote'], $analisis['datosLote'])) ?></p>
      <?php elseif ($analisis['ok']): ?>
        <?php // El botón de confirmar SOLO existe si el lote entero es aceptable. ?>
        <form method="post" action="pegado.php">
          <input type="hidden" name="csrf" value="<?= plEsc(plTokenCsrf()) ?>">
          <input type="hidden" name="rev" value="<?= plEsc($rev) ?>">
          <input type="hidden" name="accion" value="confirmar">
          <input type="hidden" name="texto" value="<?= plEsc($texto) ?>">
          <button class="btn btn-primary" type="submit"><?= plEsc(plT('pegado.confirmar', ['n' => $analisis['validas']])) ?></button>
        </form>
      <?php endif; ?>
    </section>
  <?php elseif ($analisis !== null && $analisis['errorLote'] !== null): ?>
    <p class="mal"><?= plEsc(plT($analisis['errorLote'], $analisis['datosLote'])) ?></p>
  <?php endif; ?>

<script src="js/pegado_ocr.js"></script>
<script>
// Cableado con el DOM y con Tesseract.js. La lógica de "líneas reconocidas
// -> filas Nombre;POS;TIER" vive aparte, en js/pegado_ocr.js, que es lo que
// prueba dashboard/tests/test_pegado_ocr.js (un script de Node normal, sin
// framework — igual que _fuente/test-intl-equipos.js).
//
// Todo ocurre en el navegador, con Tesseract.js cargado bajo demanda (nunca
// en páginas que no usan este botón). La imagen no se sube a ningún sitio.
// El resultado solo rellena el <textarea> de pegado.php — "Previsualizar"
// revalida todo en el servidor exactamente igual que con un pegado manual,
// así que un reconocimiento imperfecto nunca puede colar un jugador inválido.
(function () {
  'use strict';
  var input    = document.getElementById('ocr-archivo');
  var boton    = document.getElementById('ocr-boton');
  var estado   = document.getElementById('ocr-estado');
  var textarea = document.getElementById('pegado-texto');
  if (!input || !boton || !estado || !textarea || !window.PLPegadoOcr) { return; }

  input.addEventListener('change', function () {
    boton.disabled = !input.files || input.files.length === 0;
  });

  // Pinnado a una versión exacta, como el resto de librerías externas del
  // sitio (Google Fonts, Phosphor Icons en tcg_srf/): nunca @latest.
  var TESSERACT_SRC = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
  var cargando = null;
  function cargarTesseract() {
    if (window.Tesseract) { return Promise.resolve(); }
    if (cargando) { return cargando; }
    cargando = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = TESSERACT_SRC;
      script.onload = function () { resolve(); };
      script.onerror = function () { reject(new Error('no se pudo cargar Tesseract.js')); };
      document.head.appendChild(script);
    });
    return cargando;
  }

  boton.addEventListener('click', function () {
    var archivo = input.files && input.files[0];
    if (!archivo) { return; }

    boton.disabled = true;
    estado.textContent = estado.getAttribute('data-txt-procesando');

    cargarTesseract()
      .then(function () { return window.Tesseract.recognize(archivo, 'eng'); })
      .then(function (resultado) {
        var lineas = (resultado.data && resultado.data.lines) || [];
        var filas  = window.PLPegadoOcr.filasDesdeLineas(lineas);
        if (filas.length === 0) {
          estado.textContent = estado.getAttribute('data-txt-vacio');
          return;
        }
        // Se AÑADE al final de lo que ya hubiera escrito: una segunda
        // captura no debe borrar la primera.
        var previo = textarea.value.trim();
        textarea.value = (previo ? previo + '\n' : '') + filas.join('\n');
        estado.textContent = estado.getAttribute('data-txt-resultado').replace('{n}', String(filas.length));
      })
      .catch(function () {
        estado.textContent = estado.getAttribute('data-txt-error');
      })
      .finally(function () {
        boton.disabled = !input.files || input.files.length === 0;
      });
  });
})();
</script>

<?php endif; ?>

<?php
plPie();
