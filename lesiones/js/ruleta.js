// lesiones/js/ruleta.js
// Orquesta: clic en "Tirar ruleta" -> modal de confirmación -> POST al
// servidor (que YA decide el resultado real) -> pasa el JSON recibido a
// window.LE_apertura.reproducir() para animarlo. Vainilla, sin librerías.
(function () {
  'use strict';

  var filaActual = null;
  // #ceremonia es un singleton en el DOM: si se deja abrir una segunda
  // tirada mientras la primera ceremonia sigue animando, reproducir() se
  // reentra sobre el mismo marcado (listeners de la tira anterior colgados,
  // saltar/cerrar reasignados) y la primera se queda huérfana. Bloquea
  // ".btn-tirar" desde que se envía el POST hasta que la ceremonia termina.
  var ocupado = false;

  function elementos() {
    return {
      modal: document.getElementById('modal-confirmar'),
      modalEquipo: document.getElementById('modal-equipo'),
      modalCancelar: document.getElementById('modal-cancelar'),
      modalConfirmar: document.getElementById('modal-confirmar-btn'),
      ceremonia: document.getElementById('ceremonia'),
    };
  }

  function abrirModal(fila) {
    filaActual = fila;
    var el = elementos();
    el.modalEquipo.textContent = fila.getAttribute('data-equipo-nombre') || '';
    el.modal.hidden = false;
    el.modalConfirmar.focus();
  }

  function cerrarModal() {
    elementos().modal.hidden = true;
    filaActual = null;
  }

  var MENSAJES_ERROR = {
    ya_tirado: 'Este equipo ya se ha tirado esta semana.',
    escritura: 'No se pudo guardar la tirada, así que no cuenta: inténtalo de nuevo.',
    equipo_no_encontrado: 'Ese equipo ya no existe o está archivado.',
    csrf_invalido: 'La sesión ha caducado. Recarga la página.',
    no_autorizado: 'Tu acceso de administrador ha caducado. Recarga la página.',
  };

  function marcarTirado(fila) {
    if (!fila) return;
    fila.querySelector('.estado-semana').textContent = 'Ya tirado';
    var boton = fila.querySelector('.btn-tirar');
    if (boton) boton.disabled = true;
    var anular = fila.querySelector('.form-anular');
    if (anular) anular.hidden = false;
  }

  function tirar() {
    if (!filaActual) return;
    var equipoId = filaActual.getAttribute('data-equipo-id');
    var csrf = document.getElementById('csrf-token').value;
    cerrarModal();
    ocupado = true;

    var cuerpo = new URLSearchParams();
    cuerpo.set('equipo_id', equipoId);
    cuerpo.set('csrf', csrf);

    fetch('admin.php?accion=tirar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: cuerpo.toString(),
    })
      .then(function (r) { return r.json(); })
      .then(function (resultado) {
        // filaActual ya es null: cerrarModal() la limpió antes del fetch.
        var fila = document.querySelector('tr[data-equipo-id="' + CSS.escape(equipoId) + '"]');
        if (!resultado.ok) {
          ocupado = false;
          // Si otra pestaña ya lo tiró, la tabla de esta se queda al día.
          if (resultado.error === 'ya_tirado') marcarTirado(fila);
          window.alert(MENSAJES_ERROR[resultado.error] || 'No se pudo tirar la ruleta.');
          return;
        }
        window.LE_apertura.reproducir(resultado, function () {
          marcarTirado(fila);
          moverFocoAFila(fila);
          ocupado = false;
        });
      })
      .catch(function () {
        ocupado = false;
        window.alert('Fallo de red al tirar la ruleta.');
      });
  }

  // Deja el foco en algo estable de la fila tras cerrar la ceremonia (si no,
  // cae a <body>: marcarTirado() deshabilita el botón que tenía el foco).
  function moverFocoAFila(fila) {
    if (!fila) return;
    var celda = fila.querySelector('td');
    if (!celda) return;
    if (!celda.hasAttribute('tabindex')) celda.setAttribute('tabindex', '-1');
    celda.focus();
  }

  document.addEventListener('click', function (e) {
    // Mismo patrón que plScriptConfirmar() de dashboard/chrome.php: anular y
    // dar por recuperada son formularios normales; sin JS envían igual.
    var confirmar = e.target.closest ? e.target.closest('[data-confirmar]') : null;
    if (confirmar) {
      if (!window.confirm(confirmar.getAttribute('data-confirmar'))) e.preventDefault();
      return;
    }
    var boton = e.target.closest ? e.target.closest('.btn-tirar') : null;
    if (boton) {
      if (ocupado) return; // ya hay una tirada o ceremonia en curso
      abrirModal(boton.closest('tr'));
      return;
    }
    if (e.target.id === 'modal-cancelar') {
      cerrarModal();
      return;
    }
    if (e.target.id === 'modal-confirmar-btn') {
      tirar();
    }
  });

  document.addEventListener('keydown', function (e) {
    var el = elementos();
    if (e.key === 'Escape' && !el.modal.hidden) {
      cerrarModal();
    }
  });
})();
