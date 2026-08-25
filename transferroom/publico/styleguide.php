<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$paginaTitulo = 'Guía de estilo';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'styleguide';
include __DIR__ . '/../partials/nav.php';
?>

<main id="contenido" class="wrap sec">
  <div class="page-head">
    <div>
      <span class="overline">Cap. XVI · Design System</span>
      <h1 class="h1" style="margin-top:.75rem">Guía de estilo</h1>
      <p>Una sola versión de cada componente. Identidad heredada de Superliga Frontier — mismo negro real, mismo acento naranja único, misma tipografía.</p>
    </div>
  </div>

  <!-- ============ COLOR ============ -->
  <section class="sec" style="padding-top:0">
    <h2 class="h2">Color</h2>
    <p class="lede" style="margin-top:.5rem">Tokens en <code class="mono">tokens.css</code>. El naranja es el único acento — nunca decorativo.</p>
    <div class="grid-3" style="margin-top:2rem">
      <?php foreach ([
        ['Background', ['--bg' => '#000000', '--bg-2' => '#0A0A0A', '--surface' => '#0E0E0E', '--surface-2' => '#141414']],
        ['Acento', ['--accent' => '#FF5100', '--accent-hover' => '#FF7A38', '--accent-active' => '#E44700']],
        ['Semánticos', ['--success' => '#3DDC9B', '--warning' => '#F2B134', '--danger' => '#FF3B3B', '--info' => '#3E7BFF']],
        ['Franquicia', ['--gold' => '#FFC94A', '--silver' => '#C8C8C8', '--bronze' => '#CD8B5A']],
        ['Texto', ['--ink' => '#EDEDED', '--ink-2' => '#A1A1A1', '--ink-3' => '#7A7A7A', '--ink-4' => '#525252']],
      ] as [$grupo, $swatches]): ?>
        <div class="card">
          <h3 class="h4"><?= htmlspecialchars($grupo) ?></h3>
          <div style="margin-top:1rem;display:flex;flex-direction:column;gap:.6rem">
            <?php foreach ($swatches as $var => $hex): ?>
              <div style="display:flex;align-items:center;gap:.75rem">
                <span style="width:28px;height:28px;border-radius:8px;border:1px solid var(--line-2);background:var(<?= $var ?>);flex-shrink:0"></span>
                <span class="mono body-sm"><?= $var ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============ TIPOGRAFÍA ============ -->
  <section class="sec">
    <h2 class="h2">Tipografía</h2>
    <div class="card" style="margin-top:2rem;display:flex;flex-direction:column;gap:1.25rem">
      <div class="h-display">Display</div>
      <div class="h1">H1 — Título de página</div>
      <div class="h2">H2 — Título de sección</div>
      <div class="h3">H3 — Título de bloque</div>
      <div class="h4">H4 — Título de tarjeta</div>
      <div class="body-lg">Body Large — para lede y texto destacado.</div>
      <div>Body — texto de párrafo estándar, 15px.</div>
      <div class="body-sm">Body Small — metadatos y ayudas de formulario.</div>
      <div class="caption">Caption — pies y notas</div>
      <div class="overline">Overline — Etiqueta De Sección</div>
    </div>
  </section>

  <!-- ============ BOTONES ============ -->
  <section class="sec">
    <h2 class="h2">Botones</h2>
    <div class="card" style="margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
      <button class="btn btn-primary">Primario</button>
      <button class="btn btn-secondary">Secundario</button>
      <button class="btn btn-danger">Peligro</button>
      <button class="btn btn-ghost">Ghost</button>
      <button class="btn btn-primary" disabled>Deshabilitado</button>
      <button class="btn btn-primary btn-lg">Grande</button>
      <button class="btn btn-primary btn-sm">Pequeño</button>
      <button class="btn btn-icon tt" data-tt="Editar"><i class="ph ph-pencil-simple"></i></button>
    </div>
  </section>

  <!-- ============ BADGES ============ -->
  <section class="sec">
    <h2 class="h2">Badges &amp; chips</h2>
    <div class="card" style="margin-top:2rem;display:flex;gap:.75rem;flex-wrap:wrap">
      <span class="badge">Neutro</span>
      <span class="badge badge-success">Activo</span>
      <span class="badge badge-warning">Pendiente</span>
      <span class="badge badge-danger">Expirado</span>
      <span class="badge badge-info">Info</span>
      <span class="badge badge-gold">Franquicia</span>
      <span class="chip">Tier S+</span>
      <span class="chip">RFA</span>
    </div>
  </section>

  <!-- ============ FORMULARIOS ============ -->
  <section class="sec">
    <h2 class="h2">Formularios</h2>
    <div class="card" style="margin-top:2rem;max-width:420px">
      <div class="field">
        <label class="field-label" for="sg-nombre">Nombre del jugador</label>
        <input class="input" id="sg-nombre" type="text" placeholder="Mark Evans">
      </div>
      <div class="field">
        <label class="field-label" for="sg-tier">Tier</label>
        <select class="select" id="sg-tier">
          <option>S++</option><option>S+</option><option>S</option>
        </select>
      </div>
      <div class="field has-error">
        <label class="field-label" for="sg-salario">Salario anual <span class="req">*</span></label>
        <input class="input" id="sg-salario" type="number" value="1000000">
        <span class="field-error"><i class="ph ph-warning-circle"></i> Por debajo del salario base del tier.</span>
      </div>
      <label class="checkbox-row"><input type="checkbox"> Marcar como franquicia</label>
    </div>
  </section>

  <!-- ============ TABLA ============ -->
  <section class="sec">
    <h2 class="h2">Tabla</h2>
    <div class="tbl-wrap" style="margin-top:2rem">
      <div class="tbl-scroll">
        <table class="tbl">
          <thead><tr><th>Jugador</th><th>Tier</th><th class="num">Salario</th><th>Estado</th></tr></thead>
          <tbody>
            <tr><td>Mark Evans</td><td>S++</td><td class="num mono">75.000.000 €</td><td><span class="badge badge-success">Activo</span></td></tr>
            <tr><td>Axel Blaze</td><td>S</td><td class="num mono">40.000.000 €</td><td><span class="badge badge-gold">Franquicia</span></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ============ EMPTY STATE ============ -->
  <section class="sec">
    <h2 class="h2">Empty state</h2>
    <div class="tbl-wrap" style="margin-top:2rem">
      <div class="empty-state">
        <i class="ph ph-users-three"></i>
        <h3>Sin fichas todavía</h3>
        <p>Este club no tiene ningún jugador contratado en la temporada actual.</p>
        <a href="mercado.php" class="btn btn-primary btn-sm">Ir al mercado</a>
      </div>
    </div>
  </section>

  <!-- ============ ALERTAS ============ -->
  <section class="sec">
    <h2 class="h2">Alertas en línea</h2>
    <div style="margin-top:2rem;max-width:520px">
      <div class="alert alert-danger"><i class="ph ph-x-circle"></i> El salario supera el margen de Salary Cap disponible.</div>
      <div class="alert alert-success"><i class="ph ph-check-circle"></i> Contrato firmado correctamente.</div>
      <div class="alert alert-warning"><i class="ph ph-warning"></i> Ventana de igualación abierta durante 48h.</div>
      <div class="alert alert-info"><i class="ph ph-info"></i> Este jugador queda pendiente de revisión administrativa.</div>
    </div>
  </section>

  <!-- ============ SKELETON ============ -->
  <section class="sec">
    <h2 class="h2">Skeleton loader</h2>
    <div class="card" style="margin-top:2rem;max-width:420px">
      <div class="skel skel-title"></div>
      <div class="skel skel-text" style="width:90%"></div>
      <div class="skel skel-text" style="width:75%"></div>
      <div class="skel skel-text" style="width:60%"></div>
    </div>
  </section>

  <!-- ============ TABS ============ -->
  <section class="sec">
    <h2 class="h2">Tabs &amp; segmentado</h2>
    <div class="card" style="margin-top:2rem">
      <div class="tabs" data-tab-group>
        <button class="on" data-tab-target="a">Plantilla</button>
        <button data-tab-target="b">Finanzas</button>
        <button data-tab-target="c">Historial</button>
      </div>
      <div style="padding-top:1.25rem">
        <div data-tab-panel="a">Panel de plantilla.</div>
        <div data-tab-panel="b" hidden>Panel de finanzas.</div>
        <div data-tab-panel="c" hidden>Panel de historial.</div>
      </div>
      <div class="seg" style="margin-top:1.5rem">
        <button class="on">Superliga</button>
        <button>Ascenso</button>
      </div>
    </div>
  </section>

  <!-- ============ MODAL Y TOASTS ============ -->
  <section class="sec">
    <h2 class="h2">Modal de confirmación &amp; toasts</h2>
    <div class="card" style="margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap">
      <button class="btn btn-danger" data-modal-open="sg-modal">Abrir modal de confirmación</button>
      <button class="btn btn-secondary" onclick="TR.toast('Contrato firmado.', 'success', 'Listo')">Toast éxito</button>
      <button class="btn btn-secondary" onclick="TR.toast('El salario supera el Salary Cap.', 'danger', 'Error')">Toast error</button>
      <div class="dropdown">
        <button class="btn btn-secondary" data-dropdown="sg-dropdown">Menú <i class="ph ph-caret-down"></i></button>
        <div class="dropdown-menu" id="sg-dropdown">
          <a href="#"><i class="ph ph-pencil-simple"></i> Editar</a>
          <a href="#"><i class="ph ph-eye"></i> Ver ficha</a>
          <div class="dropdown-sep"></div>
          <button type="button"><i class="ph ph-trash"></i> Eliminar</button>
        </div>
      </div>
    </div>
  </section>

</main>

<div class="modal-bg" id="sg-modal">
  <div class="modal modal-danger" role="dialog" aria-modal="true" aria-labelledby="sg-modal-title">
    <div class="modal-head">
      <h2 id="sg-modal-title">¿Retirar al club de la liga?</h2>
      <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
    </div>
    <div class="modal-body">
      Esta acción libera <strong>toda la plantilla</strong> a agentes libres y no se puede deshacer. Así se ve un modal de confirmación para una operación con consecuencia económica (Cap. XVI).
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" data-modal-close>Cancelar</button>
      <button class="btn btn-danger" data-modal-close>Retirar club</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
