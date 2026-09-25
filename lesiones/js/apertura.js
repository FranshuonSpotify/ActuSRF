// lesiones/js/apertura.js
// La ceremonia, en tres tiempos que usan la MISMA tira estilo apertura de
// cajas de CS2: una pista de casillas se desliza por debajo de un marcador
// fijo y frena hasta dejar el resultado bajo él. Primero «¿evento o no?»,
// después el tipo de lesión, y luego una tira por cada jugador lesionado con
// las caras de toda la plantilla pasando por delante.
//
// El resultado YA viene decidido y guardado por el servidor (ver ruleta.js).
// Lo único aleatorio aquí es el relleno de la pista y el pequeño desvío de la
// parada: decoración, nunca el resultado.
//
// «Saltar animación» y prefers-reduced-motion llevan exactamente al mismo
// estado final que la animación completa, solo que sin transiciones.
window.LE_apertura = (function () {
  'use strict';

  var TEXTOS_EVENTO = {
    '1j_1p': '1 jugador — 1 partido',
    '1j_2p': '1 jugador — 2 partidos',
    '2j_1p': '2 jugadores — 1 partido',
    '1j_3p': '1 jugador — 3 partidos',
    '2j_2p': '2 jugadores — 2 partidos',
    '2j_3p': '2 jugadores — 3 partidos',
    'temporada': 'Toda la temporada',
  };
  // Solo para que el relleno de la pista se parezca a la proporción real
  // (quien decide es dominio.php). "temporada" lleva 1 para que se vea pasar.
  var PESOS_EVENTO = { '1j_1p': 38, '1j_2p': 22, '2j_1p': 18, '1j_3p': 11, '2j_2p': 7, '2j_3p': 4, 'temporada': 1 };

  var CASILLAS = 48;
  // El resultado va cerca del final para que la pista coja velocidad antes de frenar.
  var CASILLA_GANADORA = 40;
  var DURACION_MS = 4200;

  function reduceMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function ponderados(pesos) {
    var lista = [];
    Object.keys(pesos).forEach(function (clave) {
      for (var i = 0; i < pesos[clave]; i++) lista.push(clave);
    });
    return lista;
  }

  function pintarPrincipal(celda, valor) {
    celda.classList.add(valor === 'evento' ? 'evento' : 'nada');
    celda.textContent = valor === 'evento' ? 'Evento inesperado' : 'No ocurre nada';
  }

  function pintarEvento(celda, codigo) {
    celda.textContent = TEXTOS_EVENTO[codigo] || codigo;
  }

  // Sin innerHTML: nombre y foto vienen de datos_oficiales.json y se asignan
  // como texto y como propiedad, así que no pueden colar marcado.
  function pintarJugador(celda, jugador) {
    if (jugador.foto) {
      var img = document.createElement('img');
      img.alt = '';
      img.src = jugador.foto;
      celda.appendChild(img);
    } else {
      var hueco = document.createElement('span');
      hueco.className = 'tira-sin-foto';
      celda.appendChild(hueco);
    }
    var nombre = document.createElement('span');
    nombre.textContent = jugador.nombre;
    celda.appendChild(nombre);
  }

  // Lo que le queda al jugador, no la duración total: si ya estaba lesionado,
  // la baja nueva se suma y el total no dice cuántos partidos se pierde.
  // partidos_totales solo como respaldo para respuestas sin el campo.
  function partidos(lesionado) {
    return lesionado.partidos_restantes !== undefined ? lesionado.partidos_restantes : lesionado.partidos_totales;
  }

  function claseGravedad(lesionado) {
    if (lesionado.toda_temporada) return 'gravedad-temporada';
    return partidos(lesionado) >= 2 ? 'gravedad-alta' : 'gravedad-baja';
  }

  function textoDuracion(lesionado) {
    if (lesionado.toda_temporada) return 'Fuera toda la temporada';
    var n = partidos(lesionado);
    return n === 1 ? '1 partido de baja' : n + ' partidos de baja';
  }

  function reproducir(resultado, alTerminar) {
    var el = {
      ceremonia: document.getElementById('ceremonia'),
      titulo: document.getElementById('ceremonia-titulo'),
      tira: document.getElementById('tira'),
      pista: document.getElementById('tira-pista'),
      texto: document.getElementById('ceremonia-resultado'),
      reveladas: document.getElementById('reveladas'),
      saltar: document.getElementById('btn-saltar'),
      cerrar: document.getElementById('btn-cerrar-ceremonia'),
    };
    var saltando = reduceMotion();
    var enCurso = null;

    // Visible ANTES de construir la primera tira: sin esto las medidas de las
    // casillas salen a 0 y la parada se calcula mal.
    el.ceremonia.hidden = false;
    el.reveladas.textContent = '';
    el.texto.textContent = '';
    el.cerrar.hidden = true;
    el.saltar.hidden = saltando;

    // Una tira: la rellena, calcula dónde tiene que parar y la anima. Llama a
    // despues() una sola vez, tanto si acaba la transición como si se salta.
    function girar(titulo, candidatos, ganador, pintar, despues) {
      el.titulo.textContent = titulo;
      el.pista.style.transition = 'none';
      el.pista.style.transform = 'translateX(0px)';
      el.pista.textContent = '';
      for (var i = 0; i < CASILLAS; i++) {
        var celda = document.createElement('div');
        celda.className = 'tira-celda';
        pintar(celda, i === CASILLA_GANADORA ? ganador : candidatos[Math.floor(Math.random() * candidatos.length)]);
        el.pista.appendChild(celda);
      }
      var objetivo = el.pista.children[CASILLA_GANADORA];
      // Como en CS2, la aguja no cae siempre en el centro exacto de la casilla.
      var desvio = (Math.random() - 0.5) * objetivo.offsetWidth * 0.6;
      var destino = -(objetivo.offsetLeft + objetivo.offsetWidth / 2 + desvio - el.tira.clientWidth / 2);

      var hecho = false;
      var reserva = null;
      function terminar() {
        if (hecho) return;
        hecho = true;
        enCurso = null;
        window.clearTimeout(reserva);
        el.pista.removeEventListener('transitionend', terminar);
        el.pista.style.transition = 'none';
        el.pista.style.transform = 'translateX(' + destino + 'px)';
        despues();
      }

      if (saltando) {
        terminar();
        return;
      }
      void el.pista.offsetWidth;
      el.pista.style.transition = 'transform ' + DURACION_MS + 'ms cubic-bezier(.12,.8,.2,1)';
      el.pista.style.transform = 'translateX(' + destino + 'px)';
      el.pista.addEventListener('transitionend', terminar);
      // Por si transitionend no llega (pestaña en segundo plano, etc.).
      reserva = window.setTimeout(terminar, DURACION_MS + 250);
      enCurso = terminar;
    }

    function acabar(mensaje) {
      el.texto.textContent = mensaje;
      el.saltar.hidden = true;
      el.cerrar.hidden = false;
      el.cerrar.focus();
    }

    function revelarJugador(i) {
      var lesionados = resultado.lesionados || [];
      if (i >= lesionados.length) {
        acabar(lesionados.length === 0
          ? 'Evento inesperado, pero el equipo no tiene jugadores en plantilla.'
          : 'Tirada guardada.');
        return;
      }
      var lesionado = lesionados[i];
      var plantilla = (resultado.plantilla && resultado.plantilla.length) ? resultado.plantilla : [lesionado.jugador];
      girar('Jugador lesionado ' + (i + 1) + ' de ' + lesionados.length, plantilla, lesionado.jugador, pintarJugador, function () {
        var item = document.createElement('li');
        item.className = 'revelada ' + claseGravedad(lesionado);
        var nombre = document.createElement('span');
        nombre.className = 'revelada-nombre';
        nombre.textContent = lesionado.jugador.nombre;
        var duracion = document.createElement('span');
        duracion.className = 'revelada-duracion';
        duracion.textContent = textoDuracion(lesionado);
        item.appendChild(nombre);
        item.appendChild(duracion);
        el.reveladas.appendChild(item);
        el.texto.textContent = lesionado.jugador.nombre + ': ' + textoDuracion(lesionado);
        revelarJugador(i + 1);
      });
    }

    // onclick y no addEventListener: cada tirada sustituye el manejador de la
    // anterior, así que repetir tiradas nunca acumula manejadores.
    el.saltar.onclick = function () {
      saltando = true;
      if (enCurso) enCurso();
    };
    el.cerrar.onclick = function () {
      el.ceremonia.hidden = true;
      if (typeof alTerminar === 'function') alTerminar();
    };
    // El modal (role=dialog aria-modal=true) tiene que atrapar el foco: si no,
    // Tab escapa a la tabla y deja pulsar otra fila con la ceremonia abierta.
    // Escape nunca cancela (el servidor ya guardó el resultado): salta al
    // final si aún anima, o cierra si ya está en el estado final.
    el.ceremonia.onkeydown = function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        if (!el.cerrar.hidden) el.cerrar.onclick();
        else if (!el.saltar.hidden) el.saltar.onclick();
        return;
      }
      if (e.key === 'Tab') {
        e.preventDefault();
        var visibles = [];
        if (!el.saltar.hidden) visibles.push(el.saltar);
        if (!el.cerrar.hidden) visibles.push(el.cerrar);
        if (visibles.length === 0) return;
        var siguiente = (visibles.length === 2 && document.activeElement === visibles[0]) ? visibles[1] : visibles[0];
        siguiente.focus();
      }
    };
    if (!saltando) el.saltar.focus();

    girar('¿Evento inesperado?', ['nada', 'nada', 'nada', 'nada', 'evento'], resultado.principal, pintarPrincipal, function () {
      if (resultado.principal !== 'evento') {
        acabar('No ocurre nada: el equipo se libra esta semana.');
        return;
      }
      girar('Tipo de lesión', ponderados(PESOS_EVENTO), resultado.codigo, pintarEvento, function () {
        el.texto.textContent = TEXTOS_EVENTO[resultado.codigo] || resultado.codigo;
        revelarJugador(0);
      });
    });
  }

  return { reproducir: reproducir };
})();
