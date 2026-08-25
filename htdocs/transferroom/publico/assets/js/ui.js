'use strict';
/**
 * Transfer Room — utilidades de interfaz compartidas (Cap. XVI).
 * Sin framework, sin build. API pública en window.TR.
 */
(function () {
  var TR = {};

  /* ---- Modales: abren, atrapan el foco, cierran con Esc, devuelven el foco. ---- */
  var modalOpenedBy = null;

  TR.abrirModal = function (id) {
    var bg = document.getElementById(id);
    if (!bg) return;
    modalOpenedBy = document.activeElement;
    bg.classList.add('open');
    document.body.style.overflow = 'hidden';
    /* Rendimiento: sidebar y nav llevan backdrop-filter:blur() fijo en
       pantalla; el propio .modal-bg añade OTRO blur encima mientras hace su
       transición de opacidad. Blur-sobre-blur en capas fixed superpuestas es
       el motivo real de que la web "se quedase pillada" al abrir un modal
       (recompone el desenfoque compuesto en cada frame) — se desactiva el de
       debajo mientras el modal está abierto, ya lo tapa igual el overlay
       oscuro del propio modal. */
    document.documentElement.classList.add('modal-open');
    var focusable = bg.querySelector('.modal [href],.modal button,.modal input,.modal select,.modal textarea');
    if (focusable) focusable.focus();
  };

  TR.cerrarModal = function (id) {
    var bg = document.getElementById(id);
    if (!bg) return;
    bg.classList.remove('open');
    document.body.style.overflow = '';
    document.documentElement.classList.remove('modal-open');
    if (modalOpenedBy && typeof modalOpenedBy.focus === 'function') modalOpenedBy.focus();
    modalOpenedBy = null;
  };

  document.addEventListener('click', function (e) {
    var bg = e.target.closest('.modal-bg');
    if (bg && e.target === bg) TR.cerrarModal(bg.id);
    var closer = e.target.closest('[data-modal-close]');
    if (closer) TR.cerrarModal(closer.closest('.modal-bg').id);
    var opener = e.target.closest('[data-modal-open]');
    if (opener) TR.abrirModal(opener.getAttribute('data-modal-open'));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var open = document.querySelector('.modal-bg.open');
    if (open) TR.cerrarModal(open.id);
  });

  /* Atrapa el Tab dentro del modal abierto. */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var open = document.querySelector('.modal-bg.open .modal');
    if (!open) return;
    var focusables = open.querySelectorAll('[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])');
    if (!focusables.length) return;
    var first = focusables[0], last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  /* ---- Toasts ---- */
  TR.toast = function (mensaje, tipo, titulo) {
    tipo = tipo || 'info';
    var stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    var icon = { success: 'ph-check-circle', danger: 'ph-x-circle', info: 'ph-info' }[tipo] || 'ph-info';
    var el = document.createElement('div');
    el.className = 'toast toast-' + tipo;
    el.innerHTML =
      '<i class="ph ' + icon + '"></i>' +
      '<div>' + (titulo ? '<strong>' + titulo + '</strong>' : '') + mensaje + '</div>' +
      '<button class="toast-close" aria-label="Cerrar"><i class="ph ph-x"></i></button>';
    stack.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('in'); });
    var quitar = function () {
      el.classList.remove('in');
      setTimeout(function () { el.remove(); }, 280);
    };
    el.querySelector('.toast-close').addEventListener('click', quitar);
    setTimeout(quitar, 5000);
  };

  /* ---- Dropdowns ---- */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-dropdown]');
    document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
      if (!trigger || m.id !== trigger.getAttribute('data-dropdown')) m.classList.remove('open');
    });
    if (trigger) {
      var menu = document.getElementById(trigger.getAttribute('data-dropdown'));
      if (menu) menu.classList.toggle('open');
    }
  });

  /* ---- Tabs (segmentadas / .tabs con data-tab-target) ----
     Los paneles ([data-tab-panel]) nunca viven dentro del propio contenedor
     de pestañas — a veces son hermanos directos, a veces nietos dentro de
     un wrapper (styleguide.php) — así que se buscan en todo el documento.
     Una página puede tener más de un grupo de pestañas independiente
     (p. ej. mercado.php: "Todos/Agentes libres" y, más abajo, "De la
     liga/Externos") — bug real: buscar en todo el documento sin acotar por
     grupo apagaba también los paneles de OTRO grupo con un id distinto al
     recién activado. Se acota a los ids de panel que pertenecen al mismo
     grupo que el botón pulsado.

     Persistencia entre recargas: las pestañas con formularios (Mi Estrategia,
     etc.) hacen POST a la misma página — la recarga completa perdía la
     pestaña activa y siempre volvía a la primera. Se guarda la pestaña activa
     de cada grupo en sessionStorage (por ruta + ids del grupo, para no
     mezclar grupos entre sí ni entre páginas) y se restaura al cargar.

     Además, cada cambio de pestaña actualiza el hash de la URL
     (mercado.php#agentes-libres): así una pestaña concreta se puede
     favoritar desde el hub de navegación (nav.php, TRnav.rutaCompleta) y
     compartir como enlace directo, no solo la página entera. Al cargar, el
     hash manda sobre sessionStorage si coincide con alguna pestaña del grupo. */
  function grupoTabsKey(group) {
    var ids = Array.prototype.map.call(group.querySelectorAll('[data-tab-target]'), function (t) {
      return t.getAttribute('data-tab-target');
    });
    return 'tr_tab_activa:' + location.pathname + ':' + ids.join(',');
  }

  function activarTab(tab, actualizarHash) {
    var group = tab.closest('[data-tab-group]');
    if (!group) return;
    group.querySelectorAll('[data-tab-target]').forEach(function (t) { t.classList.remove('on'); });
    tab.classList.add('on');
    var targetSel = tab.getAttribute('data-tab-target');
    var idsDelGrupo = Array.prototype.map.call(group.querySelectorAll('[data-tab-target]'), function (t) {
      return t.getAttribute('data-tab-target');
    });
    document.querySelectorAll('[data-tab-panel]').forEach(function (p) {
      var id = p.getAttribute('data-tab-panel');
      if (idsDelGrupo.indexOf(id) === -1) return;
      p.hidden = id !== targetSel;
    });
    try { sessionStorage.setItem(grupoTabsKey(group), targetSel); } catch (e) { /* modo privado: sin persistencia, sin romper nada */ }
    if (actualizarHash && history.replaceState) {
      history.replaceState(null, '', location.pathname + location.search + '#' + targetSel);
      if (window.TRnav && TRnav.actualizarEstrellas) TRnav.actualizarEstrellas();
    }
  }

  document.addEventListener('click', function (e) {
    var tab = e.target.closest('[data-tab-target]');
    if (!tab) return;
    activarTab(tab, true);
  });

  // El hash manda si apunta a una pestaña real de este grupo (enlace
  // favorito o compartido); si no, se restaura la última pestaña guardada.
  document.querySelectorAll('[data-tab-group]').forEach(function (group) {
    var idsDelGrupo = Array.prototype.map.call(group.querySelectorAll('[data-tab-target]'), function (t) {
      return t.getAttribute('data-tab-target');
    });
    var deHash = location.hash ? location.hash.slice(1) : '';
    if (deHash !== '' && idsDelGrupo.indexOf(deHash) !== -1) {
      var tabDeHash = group.querySelector('[data-tab-target="' + deHash + '"]');
      if (tabDeHash && !tabDeHash.classList.contains('on')) activarTab(tabDeHash, false);
      return;
    }

    var guardada;
    try { guardada = sessionStorage.getItem(grupoTabsKey(group)); } catch (e) { guardada = null; }
    if (!guardada) return;
    var tab = group.querySelector('[data-tab-target="' + CSS.escape(guardada) + '"]');
    if (tab && !tab.classList.contains('on')) activarTab(tab);
  });

  /* ---- Protección contra doble clic (quejas esperables #5): deshabilita el
     botón de envío en cuanto se manda el formulario, para que una respuesta
     lenta del servidor no invite a mandar la misma oferta dos veces. El
     propio submit ya viajó; deshabilitar después no lo cancela. ---- */
  document.addEventListener('submit', function (e) {
    var boton = e.target.querySelector('button[type="submit"]');
    if (boton && !boton.disabled) {
      window.setTimeout(function () { boton.disabled = true; }, 0);
    }
  });

  /* ---- Avisos del navegador (quejas esperables #3: "no sabía que cerraba
     en 15 minutos"). Permiso explícito de Notification API + sondeo ligero
     mientras la pestaña está abierta; nunca sustituye al centro de
     notificaciones interno, que sigue siendo la fuente completa. ---- */
  var POLL_MS = 45000;
  var ultimoIdVisto = null;

  function avisoNavegadorDisponible() {
    return 'Notification' in window;
  }

  TR.avisosNavegador = {
    estado: function () {
      return avisoNavegadorDisponible() ? Notification.permission : 'no-soportado';
    },
    solicitarPermiso: function (callback) {
      if (!avisoNavegadorDisponible()) {
        callback('no-soportado');
        return;
      }
      Notification.requestPermission().then(function (resultado) {
        callback(resultado);
      });
    }
  };

  function sondearNotificaciones() {
    var url = (window.TR_BASE || '') + 'notificaciones_recientes.php' + (ultimoIdVisto !== null ? '?desde_id=' + ultimoIdVisto : '');
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (nuevas) {
        if (!Array.isArray(nuevas) || nuevas.length === 0) return;
        var primeraVez = ultimoIdVisto === null;
        nuevas.forEach(function (n) {
          ultimoIdVisto = n.id;
          // La primera pasada solo establece la marca de agua: no se avisa
          // de notificaciones que ya existían antes de activar los avisos.
          if (primeraVez) return;
          if (n.tipo === 'CIERRE_MERCADO_INMINENTE' && avisoNavegadorDisponible() && Notification.permission === 'granted') {
            new Notification('Transfer Room', { body: n.mensaje, tag: 'cierre-mercado-' + n.id });
          }
        });
      })
      .catch(function () { /* el sondeo es un extra, nunca debe romper la página si falla */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Sondear solo en páginas con sesión iniciada (la campana solo se pinta autenticado).
    if (!document.querySelector('a[href$="notificaciones.php"]')) return;
    sondearNotificaciones();
    setInterval(sondearNotificaciones, POLL_MS);

    var boton = document.getElementById('boton-activar-avisos');
    var texto = document.getElementById('texto-estado-avisos');
    if (!boton || !texto) return;

    function reflejarEstado() {
      var estado = TR.avisosNavegador.estado();
      if (estado === 'no-soportado') {
        boton.disabled = true;
        boton.textContent = 'No disponible';
        texto.textContent = 'Este navegador no admite avisos del sistema.';
      } else if (estado === 'granted') {
        boton.disabled = true;
        boton.textContent = 'Activados';
        texto.textContent = 'Avisos del navegador activados: recibirás un aviso cuando el mercado esté a punto de cerrar.';
      } else if (estado === 'denied') {
        boton.disabled = true;
        boton.textContent = 'Bloqueados';
        texto.textContent = 'Bloqueaste los avisos en el navegador. Actívalos desde los ajustes del sitio en tu navegador si cambias de idea.';
      } else {
        boton.disabled = false;
        boton.textContent = 'Activar avisos';
        texto.textContent = 'Recibe un aviso del sistema cuando el mercado esté a punto de cerrar, aunque tengas la pestaña en segundo plano.';
      }
    }

    boton.addEventListener('click', function () {
      TR.avisosNavegador.solicitarPermiso(function () { reflejarEstado(); });
    });
    reflejarEstado();
  });

  /* ---- Aviso de sesión caducando (punto 11 del inventario, verificación
     técnica #7: "sesión caducando en formularios largos"). No hay forma de
     saber desde JS el instante exacto en que expirará (gc_maxlifetime es
     probabilístico, no un temporizador fijo), así que se avisa 2 minutos
     antes del máximo configurado y se ofrece "seguir conectado", que hace
     una petición autenticada real (reutiliza notificaciones_recientes.php,
     ya existente) para refrescar la sesión sin perder lo que el usuario
     esté escribiendo en un formulario largo. ---- */
  var AVISO_ANTES_DE_EXPIRAR_S = 120;

  function programarAvisoSesion() {
    var nav = document.querySelector('[data-sesion-max-segundos]');
    if (!nav) return;
    var maxSegundos = parseInt(nav.getAttribute('data-sesion-max-segundos'), 10);
    if (!maxSegundos || maxSegundos <= AVISO_ANTES_DE_EXPIRAR_S) return;

    var esperaMs = (maxSegundos - AVISO_ANTES_DE_EXPIRAR_S) * 1000;
    setTimeout(function () {
      var stack = document.querySelector('.toast-stack') || (function () {
        var s = document.createElement('div');
        s.className = 'toast-stack';
        s.setAttribute('aria-live', 'polite');
        document.body.appendChild(s);
        return s;
      })();
      var el = document.createElement('div');
      el.className = 'toast toast-info';
      el.innerHTML =
        '<i class="ph ph-clock"></i>'
        + '<div>Tu sesión está a punto de caducar por inactividad. Si estás rellenando un formulario largo, guárdalo pronto. '
        + '<button type="button" class="btn btn-sm btn-secondary" data-mantener-sesion style="margin-top:.5rem">Seguir conectado</button></div>'
        + '<button class="toast-close" aria-label="Cerrar"><i class="ph ph-x"></i></button>';
      stack.appendChild(el);
      requestAnimationFrame(function () { el.classList.add('in'); });

      var quitar = function () {
        el.classList.remove('in');
        setTimeout(function () { el.remove(); }, 280);
      };
      el.querySelector('.toast-close').addEventListener('click', quitar);

      el.querySelector('[data-mantener-sesion]').addEventListener('click', function () {
        fetch((window.TR_BASE || '') + 'notificaciones_recientes.php', { credentials: 'same-origin' }).then(function () {
          TR.toast('Sesión renovada.', 'success');
        });
        quitar();
        programarAvisoSesion(); // otro ciclo, por si el formulario sigue abierto
      });
      // Este aviso no se autodescarta a los 5s como un toast normal: necesita
      // que el usuario decida, así que no lleva el setTimeout de auto-cierre.
    }, esperaMs);
  }

  document.addEventListener('DOMContentLoaded', programarAvisoSesion);

  /* ---- Tooltips flotantes (.tt / .tt-rich) ----
     Antes eran pseudo-elementos ::after posicionados con CSS puro. Se
     rompían en cualquier contenedor con overflow (la sidebar, con muchos
     iconos, necesita overflow-y:auto — y CSS obliga a que overflow-x pase
     también a 'auto' en ese caso, así que el tooltip que salía hacia la
     derecha quedaba recortado e invisible). Un único tooltip flotante
     anclado a <body> con position:fixed nunca puede ser recortado por un
     ancestro, sea cual sea su overflow. */
  var ttFlotante = null;
  function crearTtFlotante() {
    if (ttFlotante) return ttFlotante;
    ttFlotante = document.createElement('div');
    ttFlotante.className = 'tt-flotante';
    ttFlotante.setAttribute('role', 'tooltip');
    document.body.appendChild(ttFlotante);
    return ttFlotante;
  }

  function mostrarTt(el) {
    var titulo = el.getAttribute('data-tt');
    if (!titulo) return;
    var desc = el.getAttribute('data-tt-desc');
    var tt = crearTtFlotante();
    tt.innerHTML = desc
      ? '<strong>' + titulo.replace(/</g, '&lt;') + '</strong><span>' + desc.replace(/</g, '&lt;') + '</span>'
      : titulo.replace(/</g, '&lt;');
    tt.classList.add('on');

    var r = el.getBoundingClientRect();
    var enSidebar = !!el.closest('.sidebar');
    tt.style.maxWidth = desc ? '220px' : 'none';
    if (enSidebar) {
      // A la derecha del icono, centrado verticalmente (estilo FM).
      tt.style.left = (r.right + 12) + 'px';
      tt.style.top = Math.max(8, r.top + r.height / 2) + 'px';
      tt.style.transform = 'translateY(-50%)';
    } else {
      // Encima del elemento, centrado horizontalmente (resto de la app).
      var ttRect = tt.getBoundingClientRect();
      tt.style.left = Math.min(Math.max(8, r.left + r.width / 2 - ttRect.width / 2), window.innerWidth - ttRect.width - 8) + 'px';
      tt.style.top = (r.top - ttRect.height - 8) + 'px';
      tt.style.transform = 'none';
    }
  }

  function ocultarTt() {
    if (ttFlotante) ttFlotante.classList.remove('on');
  }

  document.addEventListener('mouseover', function (e) {
    var el = e.target.closest('[data-tt]');
    if (el) mostrarTt(el);
  });
  document.addEventListener('mouseout', function (e) {
    if (e.target.closest('[data-tt]')) ocultarTt();
  });
  document.addEventListener('focusin', function (e) {
    var el = e.target.closest('[data-tt]');
    if (el) mostrarTt(el);
  });
  document.addEventListener('focusout', function (e) {
    if (e.target.closest('[data-tt]')) ocultarTt();
  });
  window.addEventListener('scroll', ocultarTt, true);

  /* ---- Spotlight de tarjetas (04-web-superliga/.spotlight): el brillo
     radial sigue al cursor mediante --mx/--my. El rework de estética hizo el
     spotlight comportamiento por defecto de CUALQUIER .card (antes solo
     .con-spotlight) — este listener tiene que apuntar a .card ahora, si no
     el brillo se quedaba fijo en el centro (fallback 50%) en vez de seguir
     el cursor en cualquier tarjeta que no llevara la clase opt-in de antes. */
  document.addEventListener('mousemove', function (e) {
    var c = e.target.closest ? e.target.closest('.card') : null;
    if (!c) return;
    var r = c.getBoundingClientRect();
    c.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    c.style.setProperty('--my', (e.clientY - r.top) + 'px');
  }, { passive: true });

  /* ---- Cursor personalizado: copia exacta del de 04-web-superliga (un solo
     anillo .cur que sigue al ratón y crece sobre lo interactivo, no punto+
     anillo). Solo se activa con puntero fino (nunca en touch) y solo si el
     elemento existe — si algo falla, la clase que oculta el cursor nativo
     (en base.css) nunca se añade, así que el cursor de siempre nunca
     desaparece. */
  (function () {
    if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) return;
    var cur = document.getElementById('cur');
    if (!cur) return;

    document.documentElement.classList.add('cur-custom');

    /* Sin inercia ni easing: 04-web-superliga/_fuente/app.js:1626 mueve el
       anillo con un transform directo en cada mousemove, sin GSAP ni
       lag alguno — el anillo pega exactamente con el puntero real, frame a
       frame. Cualquier duration/ease aquí (se probó .4s power2.out) se nota
       como que "el ratón no va a la misma velocidad" porque literalmente no
       va: hay que copiar esto tal cual, no reinterpretarlo con GSAP. */
    window.addEventListener('mousemove', function (e) {
      cur.style.transform = 'translate(' + e.clientX + 'px,' + e.clientY + 'px) translate(-50%,-50%)';
    }, { passive: true });

    /* Selector idéntico al de la web de marca (app.js:1627-1628): .card
       incluido a propósito — es lo que hace que el anillo crezca al pasar
       por un panel, la señal de "esto reacciona" que faltaba. */
    document.addEventListener('mouseover', function (e) {
      var interactivo = e.target.closest ? e.target.closest('a,button,.card,tr,.squad-row,.sc-row,.br-match,input,select,textarea,[role="button"],.sidebar-icon,.btn-icon') : null;
      cur.classList.toggle('on', !!interactivo);
    }, { passive: true });
  })();

  /* ---- Reveal al hacer scroll (mismo patrón que 04-web-superliga): sin
     GSAP, solo IntersectionObserver + la transición CSS de .rv en base.css.
     Respeta prefers-reduced-motion dejando que la propia CSS anule la
     animación; aquí solo hace falta no perder ningún .rv si el observer
     falla o el navegador no lo soporta (failsafe a los 2500ms). */
  (function () {
    // Sin tocar las 39 páginas una a una: las cabeceras y tarjetas de nivel
    // superior ya llevan clases estables (.page-head, .card) en toda la app,
    // así que se marcan como "rv" en runtime en vez de añadir la clase a mano
    // en cada plantilla.
    document.querySelectorAll('.page-head').forEach(function (el) { el.classList.add('rv'); });
    document.querySelectorAll('main > .card, main > .grid-2 > .card, main > .grid-3 > .card').forEach(function (el, i) {
      el.classList.add('rv', 'rv-d' + ((i % 4) + 1));
    });

    var elementos = document.querySelectorAll('.rv');
    if (!elementos.length) return;
    document.documentElement.classList.add('anim');

    function revelarTodos() {
      elementos.forEach(function (el) { el.classList.add('in'); });
    }

    if (!window.IntersectionObserver || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
      revelarTodos();
      return;
    }

    var observer = new IntersectionObserver(function (entradas, obs) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('in');
          obs.unobserve(entrada.target);
        }
      });
    }, { threshold: .05, rootMargin: '0px 0px -30px 0px' });

    elementos.forEach(function (el) { observer.observe(el); });
    setTimeout(revelarTodos, 2500);
  })();

  window.TR = TR;
})();
