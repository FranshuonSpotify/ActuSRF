'use strict';
/**
 * Modal unificado de jugador (Fase 2). Sustituye a las copias privadas que
 * antes vivían en mercado.php y plantilla.php: un único componente, con el
 * cuerpo adaptado según datos.tipo. API pública en window.TRjugador.
 */
(function () {
  var TRjugador = {};

  var tierClases = { A: 'tier-alto', 'A+': 'tier-alto', S: 'tier-alto', 'S+': 'tier-top', 'S++': 'tier-top', B: 'tier-medio', 'B+': 'tier-medio', 'A-': 'tier-medio' };

  // Estado del cuerpo de traspaso actualmente abierto: lo leen los handlers
  // inline (oninput/onchange) del propio HTML, así que vive a nivel de
  // módulo en vez de pasarse como parámetro.
  var estadoTraspaso = { mejorOferta: 0, capPorClub: {}, salarioJugador: 0, misJugadoresPorClub: {} };

  function formatearMoneda(n) {
    return Math.round(n).toLocaleString('es-ES') + ' €';
  }

  /** Nombres de jugador pueden venir de un "jugador externo" (texto libre de un admin) — nunca a innerHTML sin escapar. */
  function escaparHtml(texto) {
    var div = document.createElement('div');
    div.textContent = texto == null ? '' : String(texto);
    return div.innerHTML;
  }

  function tiempoRelativo(fechaStr) {
    if (!fechaStr) return '';
    var entonces = new Date(fechaStr.replace(' ', 'T'));
    if (isNaN(entonces.getTime())) return '';
    var dias = Math.floor((Date.now() - entonces.getTime()) / 86400000);
    if (dias <= 0) return 'hoy';
    if (dias === 1) return 'hace 1 día';
    return 'hace ' + dias + ' días';
  }

  function rellenarCabecera(datos) {
    document.getElementById('modal-jugador-nombre').textContent = datos.nombre;
    document.getElementById('modal-jugador-foto').src = datos.fotoUrl || '';
    var tierEl = document.getElementById('modal-jugador-tier');
    tierEl.textContent = datos.tier;
    tierEl.className = 'chip ' + (tierClases[datos.tier] || '');
    document.getElementById('modal-jugador-posicion').textContent = datos.posicion;

    var franquicia = document.getElementById('modal-jugador-franquicia');
    franquicia.style.display = datos.esFranquicia ? '' : 'none';

    var agencia = document.getElementById('modal-jugador-agencia');
    if (datos.agenciaLibre === 'RESTRINGIDA') {
      agencia.textContent = 'RFA';
      agencia.className = 'badge badge-warning';
      agencia.style.display = '';
    } else if (datos.agenciaLibre === 'NO_RESTRINGIDA') {
      agencia.textContent = 'UFA';
      agencia.className = 'badge badge-success';
      agencia.style.display = '';
    } else {
      agencia.style.display = 'none';
    }

    var origen = document.getElementById('modal-jugador-club-origen');
    if (datos.clubOrigen) {
      origen.textContent = 'Procedente de: ' + datos.clubOrigen;
      origen.style.display = '';
    } else {
      origen.style.display = 'none';
    }

    // El botón solo aparece si el servidor ya resolvió y validó la URL
    // (wiki_status matched/manual). Nunca se construye una URL en cliente.
    var wiki = document.getElementById('modal-jugador-wiki');
    if (datos.wikiUrl) {
      wiki.href = datos.wikiUrl;
      wiki.style.display = '';
    } else {
      wiki.removeAttribute('href');
      wiki.style.display = 'none';
    }
  }

  function ocultarTodosLosCuerpos() {
    ['agente-libre', 'traspaso', 'propio'].forEach(function (id) {
      document.getElementById('modal-jugador-cuerpo-' + id).style.display = 'none';
    });
  }

  function cuerpoAgenteLibre(datos) {
    document.getElementById('modal-jugador-cuerpo-agente-libre').style.display = '';
    document.getElementById('modal-jugador-id').value = datos.jugadorId;

    var listaOfertas = document.getElementById('modal-jugador-ofertas');
    if (!datos.ofertas || datos.ofertas.length === 0) {
      listaOfertas.innerHTML = '<span class="caption">Todavía no hay ninguna puja.</span>';
    } else {
      listaOfertas.innerHTML = datos.ofertas.map(function (o, i) {
        var lider = i === 0 ? ' <span class="badge badge-accent">Puja líder</span>' : '';
        return '<div style="padding:.5rem 0;border-bottom:1px solid var(--line)"><strong>' + o.club + '</strong>' + lider
          + '<br><span class="mono">' + o.salario.toLocaleString('es-ES') + ' €</span> (' + o.duracion + ' temp.)</div>';
      }).join('');
    }

    var salarioInput = document.getElementById('modal-jugador-salario');
    salarioInput.min = datos.salarioBase;
    salarioInput.value = datos.salarioBase;

    document.getElementById('modal-jugador-form-agente-libre').style.display = datos.puedeOfertar ? '' : 'none';
  }

  function cuerpoTraspaso(datos) {
    // La caja real del modal (#modal-jugador-caja) trae un ancho fijado por
    // estilo inline en el HTML (width:min(92vw,620px)) para los otros dos
    // tipos de cuerpo; una clase CSS normal nunca le ganaría en especificidad,
    // así que aquí se sobrescribe directamente el estilo del elemento. Se
    // convierte además en columna flex con alto máximo, para que sea
    // .modal-body (y no la ventana) quien controle el scroll — así la barra
    // de acciones sticky del final siempre queda dentro de la pantalla.
    var caja = document.getElementById('modal-jugador-caja');
    caja.style.width = 'min(96vw, 1240px)';
    caja.style.display = 'flex';
    caja.style.flexDirection = 'column';
    caja.style.maxHeight = 'min(90vh, 860px)';
    document.getElementById('modal-jugador-cuerpo-traspaso').style.display = '';
    document.getElementById('modal-jugador-traspaso-id').value = datos.jugadorId;
    document.getElementById('modal-jugador-traspaso-club').textContent = datos.clubActual || '—';
    document.getElementById('modal-jugador-traspaso-salario').textContent = formatearMoneda(datos.salarioActual);
    document.getElementById('modal-jugador-traspaso-duracion').textContent = datos.duracionRestante;
    document.getElementById('modal-jugador-form-traspaso').style.display = datos.puedeOfertar ? '' : 'none';

    var escudo = document.getElementById('modal-jugador-traspaso-club-escudo');
    if (datos.clubActualEscudo) {
      escudo.src = datos.clubActualEscudo;
      escudo.hidden = false;
    } else {
      escudo.hidden = true;
    }

    document.getElementById('modal-jugador-traspaso-franquicia-nota').style.display = datos.esFranquicia ? '' : 'none';

    var lista = document.getElementById('modal-jugador-traspaso-ofertas-lista');
    var ofertas = datos.ofertasTraspaso || [];
    if (ofertas.length === 0) {
      lista.innerHTML = '<div class="traspaso-empty-ofertas"><i class="ph ph-tray"></i>Todavía no hay ninguna oferta por este jugador.</div>';
    } else {
      lista.innerHTML = ofertas.map(function (o, i) {
        var jugadoresTexto = (o.jugadoresOfrecidos && o.jugadoresOfrecidos.length)
          ? (' · + ' + o.jugadoresOfrecidos.map(escaparHtml).join(', ')) : '';
        return '<div class="traspaso-oferta-item' + (i === 0 ? ' lider' : '') + '">'
          + '<span class="traspaso-oferta-rank">' + (i + 1) + '</span>'
          + (o.escudo ? '<img class="escudo-sm" src="' + o.escudo + '" alt="" loading="lazy">' : '')
          + '<div class="traspaso-oferta-club"><strong>' + escaparHtml(o.club) + '</strong><span>' + tiempoRelativo(o.fecha) + (i === 0 ? ' · Oferta líder' : '') + jugadoresTexto + '</span></div>'
          + '<span class="traspaso-oferta-importe">' + (o.importe > 0 ? formatearMoneda(o.importe) : '—') + '</span>'
          + '</div>';
      }).join('');
    }

    estadoTraspaso = {
      mejorOferta: ofertas.length ? ofertas[0].importe : 0,
      capPorClub: datos.capPorClub || {},
      salarioJugador: datos.salarioActual || 0,
      misJugadoresPorClub: datos.misJugadoresPorClub || {},
    };

    document.getElementById('modal-jugador-traspaso-quick-fills').style.display = ofertas.length ? '' : 'none';
    document.getElementById('modal-jugador-traspaso-importe').value = '0';
    TRjugador.actualizarCapTraspaso();
    TRjugador.compararOfertaTraspaso();
    TRjugador.renderizarPickerJugadores();
  }

  function cuerpoPropio(datos) {
    document.getElementById('modal-jugador-cuerpo-propio').style.display = '';
    document.getElementById('modal-jugador-propio-salario').textContent = datos.salarioActual.toLocaleString('es-ES') + ' €';
    document.getElementById('modal-jugador-propio-duracion').textContent = datos.duracionRestante;

    var acciones = document.getElementById('modal-jugador-propio-acciones');
    acciones.innerHTML = '';
    if (!datos.puedeGestionar) return;

    // Checklist de primer mercado (A.3): sin confirmación reforzada, sin
    // consecuencia económica — envío directo del formulario oculto.
    var btnTransferible = document.createElement('button');
    btnTransferible.type = 'button';
    btnTransferible.className = 'btn btn-sm ' + (datos.transferible ? 'btn-secondary' : 'btn-ghost');
    btnTransferible.innerHTML = datos.transferible
      ? '<i class="ph ph-tag"></i> Transferible'
      : '<i class="ph ph-tag-simple"></i> No transferible';
    btnTransferible.title = datos.transferible
      ? 'El resto de la liga lo ve como transferible. Pulsa para marcarlo como no transferible.'
      : 'Marcado como no transferible. Pulsa para volver a marcarlo como transferible.';
    btnTransferible.addEventListener('click', function () {
      document.getElementById('form-alternar-transferible-contrato-id').value = datos.contratoId;
      document.getElementById('form-alternar-transferible').submit();
    });
    acciones.appendChild(btnTransferible);

    if (!datos.esFranquicia) {
      var btnProteger = document.createElement('button');
      btnProteger.type = 'button';
      btnProteger.className = 'btn btn-sm btn-secondary';
      btnProteger.textContent = 'Proteger (+10M)';
      btnProteger.addEventListener('click', function () {
        document.getElementById('modal-proteger-unificado-contrato-id').value = datos.contratoId;
        document.getElementById('modal-proteger-unificado-texto').textContent = 'Vas a proteger a ' + datos.nombre + '.';
        TR.abrirModal('modal-proteger-unificado');
      });
      acciones.appendChild(btnProteger);
    }

    var btnFinalizar = document.createElement('button');
    btnFinalizar.type = 'button';
    btnFinalizar.className = 'btn btn-sm btn-danger';
    btnFinalizar.textContent = 'Finalizar contrato';
    btnFinalizar.addEventListener('click', function () {
      document.getElementById('modal-finalizar-unificado-contrato-id').value = datos.contratoId;
      document.getElementById('modal-finalizar-unificado-texto').textContent = 'Vas a finalizar el contrato de ' + datos.nombre + '.';
      TR.abrirModal('modal-finalizar-unificado');
    });
    acciones.appendChild(btnFinalizar);
  }

  function clubCompradorSeleccionado() {
    var select = document.getElementById('modal-jugador-traspaso-club-comprador');
    var hiddenInput = document.querySelector('#modal-jugador-form-traspaso input[name="participacion_compradora_id"]');
    return select ? select.value : (hiddenInput ? hiddenInput.value : null);
  }

  /** Lista de checkboxes con la plantilla propia del club elegido, para incluir jugadores en el trato además del dinero (o en vez de él). */
  TRjugador.renderizarPickerJugadores = function () {
    var contenedor = document.getElementById('modal-jugador-traspaso-jugadores-picker');
    if (!contenedor) return;

    var participacionId = clubCompradorSeleccionado();
    var jugadores = participacionId ? (estadoTraspaso.misJugadoresPorClub[participacionId] || []) : [];

    if (jugadores.length === 0) {
      contenedor.innerHTML = '<span class="caption">Tu club todavía no tiene jugadores fichados.</span>';
      return;
    }

    contenedor.innerHTML = jugadores.map(function (j) {
      var etiqueta = escaparHtml(j.nombre) + ' (' + escaparHtml(j.posicion) + (j.tier ? ', ' + escaparHtml(j.tier) : '') + ')';
      return '<label class="traspaso-jugador-picker-item">'
        + '<input type="checkbox" name="jugadores_ofrecidos[]" value="' + j.id + '">'
        + '<span>' + etiqueta + '</span>'
        + '</label>';
    }).join('');
  };

  /** El select de "Tu club" cambia dos cosas a la vez: el margen de Salary Cap y qué jugadores hay disponibles para ofrecer. */
  TRjugador.alCambiarClubComprador = function () {
    TRjugador.actualizarCapTraspaso();
    TRjugador.renderizarPickerJugadores();
  };

  /** Barra de Salary Cap del club comprador, con proyección tras absorber el salario de este jugador (mismo salario, el traspaso nunca renegocia el contrato). */
  TRjugador.actualizarCapTraspaso = function () {
    var contenedor = document.getElementById('modal-jugador-traspaso-cap');
    var participacionId = clubCompradorSeleccionado();
    var info = participacionId ? estadoTraspaso.capPorClub[participacionId] : null;

    if (!contenedor || !info) {
      if (contenedor) contenedor.style.display = 'none';
      return;
    }

    contenedor.style.display = '';
    var proyectado = info.gastado + estadoTraspaso.salarioJugador;
    var pct = info.cap > 0 ? Math.min(100, (proyectado / info.cap) * 100) : 0;

    var fill = document.getElementById('modal-jugador-traspaso-cap-fill');
    fill.style.width = pct + '%';
    fill.classList.toggle('al-limite', proyectado > info.cap);
    fill.classList.toggle('cerca', proyectado <= info.cap && pct >= 85);

    document.getElementById('modal-jugador-traspaso-cap-gastado').textContent =
      formatearMoneda(proyectado) + ' / ' + formatearMoneda(info.cap) + ' (' + Math.round(pct) + '%)';
    document.getElementById('modal-jugador-traspaso-cap-margen').textContent = proyectado > info.cap
      ? 'Supera el cap por ' + formatearMoneda(proyectado - info.cap)
      : 'Margen: ' + formatearMoneda(info.cap - proyectado);
  };

  /** Rellena el importe como un múltiplo de la oferta líder actual (1 = igualarla, 1.1 = superarla un 10%). */
  TRjugador.rellenarImporteTraspaso = function (factor) {
    if (!estadoTraspaso.mejorOferta) return;
    var input = document.getElementById('modal-jugador-traspaso-importe');
    input.value = Math.round(estadoTraspaso.mejorOferta * factor);
    TRjugador.compararOfertaTraspaso();
  };

  /** Compara en vivo el importe que se está escribiendo contra la oferta líder actual. */
  TRjugador.compararOfertaTraspaso = function () {
    var input = document.getElementById('modal-jugador-traspaso-importe');
    var hint = document.getElementById('modal-jugador-traspaso-hint');
    if (!input || !hint) return;

    var valor = parseFloat(input.value);
    if (!valor || !estadoTraspaso.mejorOferta) {
      hint.textContent = '';
      hint.className = 'traspaso-importe-hint';
      return;
    }

    if (valor > estadoTraspaso.mejorOferta) {
      hint.textContent = 'Superarías la oferta líder actual (' + formatearMoneda(estadoTraspaso.mejorOferta) + ').';
      hint.className = 'traspaso-importe-hint supera';
    } else {
      hint.textContent = 'Hay una oferta mayor en curso: ' + formatearMoneda(estadoTraspaso.mejorOferta) + '.';
      hint.className = 'traspaso-importe-hint no-supera';
    }
  };

  TRjugador.abrirModal = function (datos) {
    var caja = document.getElementById('modal-jugador-caja');
    caja.style.width = '';
    caja.style.display = '';
    caja.style.flexDirection = '';
    caja.style.maxHeight = '';
    rellenarCabecera(datos);
    ocultarTodosLosCuerpos();

    if (datos.tipo === 'agente_libre') cuerpoAgenteLibre(datos);
    else if (datos.tipo === 'traspaso') cuerpoTraspaso(datos);
    else if (datos.tipo === 'propio') cuerpoPropio(datos);

    TR.abrirModal('modal-jugador');
  };

  window.TRjugador = TRjugador;
})();
