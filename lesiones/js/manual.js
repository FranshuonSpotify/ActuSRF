// lesiones/js/manual.js
// Panel secreto (Ctrl+4): asigna a mano el resultado de la tirada de un equipo.
// El servidor revalida todo (leTirarParaEquipo); aquí solo se arma el formulario
// y se reutiliza la ceremonia de siempre para que quede como una tirada normal.
(function () {
  'use strict';

  var datos = JSON.parse(document.getElementById('manual-datos').textContent);
  var panel = document.getElementById('panel-manual');
  var selEquipo = document.getElementById('manual-equipo');
  var selCodigo = document.getElementById('manual-codigo');
  var cajaJug = document.getElementById('manual-jugadores');
  var ocupado = false;

  function nPartidos(f) { return f.partidos === null ? 'toda la temporada' : f.partidos + ' partido(s)'; }

  function pintarJugadores() {
    var fila = datos.tabla.filter(function (f) { return f.codigo === selCodigo.value; })[0];
    var eq = datos.equipos.filter(function (e) { return e.id === selEquipo.value; })[0];
    cajaJug.innerHTML = '';
    cajaJug.hidden = !fila || !eq;
    if (!fila || !eq) return;
    var t = document.createElement('p');
    t.className = 'les-nota';
    t.textContent = 'Elige ' + fila.jugadores + ' jugador(es):';
    cajaJug.appendChild(t);
    eq.jugadores.forEach(function (j) {
      var l = document.createElement('label');
      l.style.display = 'block';
      var c = document.createElement('input');
      c.type = 'checkbox'; c.value = j.id;
      l.appendChild(c);
      l.appendChild(document.createTextNode(' ' + j.nombre));
      cajaJug.appendChild(l);
    });
  }

  function abrir() {
    var pendientes = {};
    document.querySelectorAll('[data-equipo-id] .btn-tirar:not([disabled])').forEach(function (b) {
      pendientes[b.closest('[data-equipo-id]').getAttribute('data-equipo-id')] = 1;
    });
    selEquipo.innerHTML = '';
    datos.equipos.filter(function (e) { return pendientes[e.id]; }).forEach(function (e) {
      selEquipo.appendChild(new Option(e.nombre, e.id));
    });
    if (!selEquipo.options.length) { window.alert('Todos los equipos han tirado ya esta semana. Anula una tirada primero.'); return; }
    selCodigo.innerHTML = '';
    selCodigo.appendChild(new Option('Nada (no hay lesión)', 'nada'));
    datos.tabla.forEach(function (f) {
      selCodigo.appendChild(new Option(f.jugadores + ' jugador(es) · ' + nPartidos(f), f.codigo));
    });
    pintarJugadores();
    panel.hidden = false;
    selEquipo.focus();
  }

  function cerrar() { panel.hidden = true; }

  selEquipo.addEventListener('change', pintarJugadores);
  selCodigo.addEventListener('change', pintarJugadores);
  document.getElementById('manual-cancelar').addEventListener('click', cerrar);

  document.getElementById('form-manual').addEventListener('submit', function (e) {
    e.preventDefault();
    if (ocupado) return;
    var cuerpo = new URLSearchParams();
    cuerpo.set('equipo_id', selEquipo.value);
    cuerpo.set('csrf', document.getElementById('csrf-token').value);
    cuerpo.set('manual_codigo', selCodigo.value);
    cajaJug.querySelectorAll('input:checked').forEach(function (c) { cuerpo.append('manual_jugadores[]', c.value); });
    ocupado = true;
    fetch('admin.php?accion=tirar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: cuerpo.toString(),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) {
          ocupado = false;
          window.alert(res.error === 'manual_invalido' ? 'Elige exactamente los jugadores que pide el resultado.'
            : res.error === 'ya_tirado' ? 'Ese equipo ya ha tirado esta semana.' : 'No se pudo aplicar: ' + res.error);
          return;
        }
        cerrar();
        window.LE_apertura.reproducir(res, function () { window.location.reload(); });
      })
      .catch(function () { ocupado = false; window.alert('Fallo de red.'); });
  });

  // Chrome y Firefox reservan Ctrl+4 (saltar a la 4.ª pestaña) y no dejan
  // interceptarlo en una pestaña normal; Ctrl+Mayús+4 funciona siempre.
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key !== undefined && (e.code === 'Digit4' || e.code === 'Numpad4')) {
      e.preventDefault();
      if (panel.hidden) abrir(); else cerrar();
    } else if (e.key === 'Escape' && !panel.hidden) {
      cerrar();
    }
  });
})();
