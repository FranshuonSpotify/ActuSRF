'use strict';
/**
 * Simulador de supertécnicas y metarráfagas (Mi Estrategia, dentro de la
 * pestaña Planificador táctico). Sin framework, sin build. Cada jugador
 * simulado / cada técnica vive en el servidor (tablas jugadores_simulados y
 * tecnicas_simuladas, scopeadas a usuario_id — privado, nunca visible a otros
 * usuarios ni conectado a los jugadores reales del mercado). El servidor
 * pre-carga el roster en TR_SUPER_ROSTER_INICIAL; cada mutación real (crear
 * jugador, borrar jugador, añadir/quitar técnica) se envía por un formulario
 * oculto y recarga la página, igual que el resto de mi_estrategia.php.
 * La única cosa que vive solo en el cliente es "añadir un jugador real a la
 * vista": no se persiste hasta que se le añade una técnica de verdad.
 */
(function () {
  var AFINIDAD_COLOR = {
    aire: 'var(--info)',
    fuego: 'var(--danger)',
    bosque: 'var(--success)',
    montana: 'var(--warning)',
    neutra: '#A78BFA',
    sin_afinidad: 'var(--ink-4)'
  };
  var AFINIDAD_ETIQUETA = {
    aire: 'Aire', fuego: 'Fuego', bosque: 'Bosque', montana: 'Montaña',
    neutra: 'Neutra', sin_afinidad: 'Sin afinidad'
  };
  var TENSION_POTENCIA = { 40: 30, 50: 50, 60: 60, 70: 70, 80: 85, 100: 100 };
  var MAX_TECNICAS_POR_JUGADOR = 4;
  var CATEGORIA_ETIQUETA = { parada: 'Parada', defensa: 'Defensa', regate: 'Regate', tiro: 'Tiro' };
  var SUBCATEGORIA_POR_CATEGORIA = {
    parada: { despeje: 'Despeje' },
    defensa: { bloqueo_tiro: 'Bloqueo de tiro' },
    regate: {},
    tiro: { tiro_largo: 'Tiro largo', contraataque: 'Tiro de contraataque' }
  };
  var SUBCATEGORIA_ETIQUETA = { despeje: 'Despeje', bloqueo_tiro: 'Bloqueo de tiro', tiro_largo: 'Tiro largo', contraataque: 'Tiro de contraataque' };
  /* Hipertécnicas: concepto aparte de categoría/subcategoría, no una quinta
     categoría más. */
  var HIPERTECNICA_ETIQUETA = { espiritu_guerrero: 'Espíritu guerrero', mixi_max: 'Mixi Max', totem: 'Tótem', despertar: 'Despertar' };
  var DESPERTAR_VARIANTE_ETIQUETA = {
    porteria: 'Determinación de portero', guardian: 'Guardián Férreo',
    impulso: 'Impulso Temporal', catalizador: 'Catalizador Elemental'
  };

  /* Base de metarráfagas: mejor lectura de las capturas aportadas. "Cadena de
     tiros" aparecía como paso intermedio en varias combinaciones de tiro,
     pero no es una supertécnica en sí (no se aprende, no se elige afinidad):
     se muestra en la nota como contexto, no se exige como requisito propio.
     requisitos: [{ tecnica, cantidad, afinidadesExcluidas? }]
     vetoSiExiste: si CUALQUIER jugador tiene una de estas técnicas, la
     metarráfaga nunca se activa aunque se cumplan los requisitos. */
  var METARRAFAGAS = [
    { nombre: 'Trimano celestial', requisitos: [{ tecnica: 'Mano celestial', cantidad: 3, afinidadesExcluidas: ['bosque', 'fuego'] }],
      nota: 'Necesitas 3 o más jugadores con Mano celestial (sin contar la versión de Bosque ni la de Fuego).' },
    { nombre: 'Parada de Asura', requisitos: [{ tecnica: 'Ultramanos mágicas', cantidad: 1 }, { tecnica: 'Escudo lunar', cantidad: 1 }, { tecnica: 'Destrozataladros', cantidad: 1 }],
      nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Dimensión Kamakura', requisitos: [{ tecnica: 'Escudo lunar', cantidad: 1 }, { tecnica: 'Red de contención', cantidad: 1 }, { tecnica: 'Cañón de meteoritos A', cantidad: 1 }],
      nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Presa', requisitos: [{ tecnica: 'El muro', cantidad: 1 }, { tecnica: 'Mano celestial', cantidad: 1, afinidadesExcluidas: ['bosque', 'fuego'] }],
      nota: 'No se puede realizar con la Mano celestial de Bosque o de Fuego.' },
    { nombre: 'Muro colectivo', requisitos: [{ tecnica: 'El muro', cantidad: 4 }], nota: 'Necesitas 4 o más jugadores con esta supertécnica.' },
    { nombre: 'Ciclón doble', requisitos: [{ tecnica: 'Ciclón', cantidad: 2 }], nota: 'Necesitas 2 o más jugadores con esta supertécnica.' },
    { nombre: 'Corro de la patata', requisitos: [{ tecnica: 'Sorpresa', cantidad: 3 }], nota: 'Necesitas 3 o más jugadores con esta supertécnica.' },
    { nombre: 'Esprint triple', requisitos: [{ tecnica: 'Acelerón', cantidad: 3 }], nota: 'Necesitas 3 o más jugadores con esta supertécnica.' },
    { nombre: 'Armadillo circense', requisitos: [{ tecnica: 'Equilibrismo', cantidad: 1 }, { tecnica: 'Superarmadillo', cantidad: 1 }], nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Coz 3', requisitos: [{ tecnica: 'Espejismo de balón', cantidad: 1 }, { tecnica: 'Coz', cantidad: 1 }], nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Rueda infernal', requisitos: [{ tecnica: 'Superarmadillo', cantidad: 2 }], nota: 'Necesitas 2 o más jugadores con esta supertécnica.' },
    { nombre: 'Tornado dragón', requisitos: [{ tecnica: 'Remate dragón', cantidad: 1 }, { tecnica: 'Tornado de fuego', cantidad: 1 }], cadena: true },
    { nombre: 'Remate combinado F', requisitos: [{ tecnica: 'Tornado de fuego', cantidad: 1 }, { tecnica: 'Remate combinado', cantidad: 1 }], cadena: true },
    { nombre: 'Supertrampolín relámpago', requisitos: [{ tecnica: 'Superrelámpago', cantidad: 1 }, { tecnica: 'Trampolín relámpago', cantidad: 1 }], cadena: true },
    { nombre: 'Chut de 200 toques', requisitos: [{ tecnica: 'Chut de 100 toques', cantidad: 2 }], cadena: true },
    { nombre: 'Ventisca guiverno', requisitos: [{ tecnica: 'Remate guiverno', cantidad: 1 }, { tecnica: 'Ventisca eterna', cantidad: 1 }], cadena: true },
    { nombre: 'Tormenta del tigre', requisitos: [{ tecnica: 'Remate del tigre', cantidad: 1 }, { tecnica: 'Tormenta de fuego', cantidad: 1 }], cadena: true },
    { nombre: 'Remate caótico', requisitos: [{ tecnica: 'Disparo sagrado', cantidad: 1 }, { tecnica: 'Ventisca de fuego', cantidad: 2 }], nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Huracán', requisitos: [{ tecnica: 'Ventisca eterna', cantidad: 1 }, { tecnica: 'Danza del viento', cantidad: 1 }], nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Triple amenaza', requisitos: [{ tecnica: 'Lluvia de azufre', cantidad: 1 }, { tecnica: 'Tiro vendaval', cantidad: 1 }], cadena: true },
    { nombre: 'Tornado de fuego DD', requisitos: [{ tecnica: 'Tornado de fuego', cantidad: 2 }], cadena: true },
    { nombre: 'Tornado de fuego DT', requisitos: [{ tecnica: 'Tornado de fuego', cantidad: 3 }], nota: 'Solo requiere tener esas supertécnicas aprendidas.' },
    { nombre: 'Tormenta de fuego (con Triángulo Z)', requisitos: [{ tecnica: 'Triángulo Z', cantidad: 1 }, { tecnica: 'Tornado de fuego', cantidad: 1 }], cadena: true },
    { nombre: 'Tormenta de fuego (con Ave goleadora)', requisitos: [{ tecnica: 'Ave goleadora', cantidad: 1 }, { tecnica: 'Tornado de fuego A', cantidad: 1 }], cadena: true },
    { nombre: 'Tornado doble', requisitos: [{ tecnica: 'Tornado de fuego', cantidad: 1 }, { tecnica: 'Tornado inverso', cantidad: 1 }],
      vetoSiExiste: ['Tornado oscuro'], nota: 'No se puede realizar con el Tornado oscuro.', cadena: true },
    { nombre: 'Frente frío', requisitos: [{ tecnica: 'Ventisca eterna', cantidad: 2 }], cadena: true },
    { nombre: 'Ventisca triple', requisitos: [{ tecnica: 'Liebre blanca', cantidad: 1 }, { tecnica: 'Frente frío', cantidad: 1 }], cadena: true },
    { nombre: 'Combo cósmico', requisitos: [{ tecnica: 'Detonador', cantidad: 1 }, { tecnica: 'Cañón de meteoritos A', cantidad: 1 }], cadena: true },
    { nombre: 'Triángulo letal triturador', requisitos: [{ tecnica: 'Lanza polar', cantidad: 1 }, { tecnica: 'Triángulo letal', cantidad: 1 }], cadena: true },
    { nombre: 'Triángulo letal final', requisitos: [{ tecnica: 'Triángulo letal', cantidad: 3 }], nota: 'Necesitas 3 o más jugadores con esta supertécnica.' },
    { nombre: 'Lanza gélida', requisitos: [{ tecnica: 'Ventisca eterna', cantidad: 1 }, { tecnica: 'Remate cazaosos', cantidad: 1 }], cadena: true },
    { nombre: 'Pingüino emperador n.º 2 + Tiburón', requisitos: [{ tecnica: 'Pingüino emperador n.º 2', cantidad: 1 }, { tecnica: 'Tiburón abisal', cantidad: 1 }], cadena: true }
  ];

  var roster = [];

  function normalizar(texto) {
    return (texto || '').trim().toLowerCase();
  }

  function contarJugadoresConTecnica(nombreTecnica, afinidadesExcluidas) {
    var buscado = normalizar(nombreTecnica);
    var n = 0;
    roster.forEach(function (jugador) {
      var tiene = jugador.tecnicas.some(function (t) {
        if (normalizar(t.nombre) !== buscado) return false;
        if (afinidadesExcluidas && afinidadesExcluidas.indexOf(t.afinidad) !== -1) return false;
        return true;
      });
      if (tiene) n++;
    });
    return n;
  }

  function evaluarMetarrafaga(mr) {
    if (mr.vetoSiExiste) {
      var vetado = mr.vetoSiExiste.some(function (nombreVeto) {
        var buscado = normalizar(nombreVeto);
        return roster.some(function (j) {
          return j.tecnicas.some(function (t) { return normalizar(t.nombre) === buscado; });
        });
      });
      if (vetado) return false;
    }
    return mr.requisitos.every(function (req) {
      return contarJugadoresConTecnica(req.tecnica, req.afinidadesExcluidas) >= req.cantidad;
    });
  }

  function escaparHtml(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  function renderizarTecnica(clave, tecnica) {
    var color = AFINIDAD_COLOR[tecnica.afinidad] || 'var(--accent)';
    var badges = '<span class="badge" style="border-color:' + color + '"><span class="super-afinidad-dot" style="--afinidad-color:' + color + '"></span> ' + escaparHtml(AFINIDAD_ETIQUETA[tecnica.afinidad] || tecnica.afinidad) + '</span>';
    if (tecnica.categoria) badges += '<span class="badge">' + escaparHtml(CATEGORIA_ETIQUETA[tecnica.categoria] || tecnica.categoria) + '</span>';
    if (tecnica.subcategoria) badges += '<span class="badge">' + escaparHtml(SUBCATEGORIA_ETIQUETA[tecnica.subcategoria] || tecnica.subcategoria) + '</span>';
    if (tecnica.hipertecnica) {
      var etiquetaHiper = HIPERTECNICA_ETIQUETA[tecnica.hipertecnica] || tecnica.hipertecnica;
      if (tecnica.hipertecnica === 'espiritu_guerrero' && tecnica.hipertecnicaVariante === 'con_armadura') etiquetaHiper += ': con armadura';
      if (tecnica.hipertecnica === 'despertar' && tecnica.hipertecnicaVariante) etiquetaHiper += ': ' + (DESPERTAR_VARIANTE_ETIQUETA[tecnica.hipertecnicaVariante] || tecnica.hipertecnicaVariante);
      badges += '<span class="badge badge-accent"><i class="ph-fill ph-sparkle"></i> ' + escaparHtml(etiquetaHiper) + '</span>';
    }
    var potencia = TENSION_POTENCIA[tecnica.tension] != null ? TENSION_POTENCIA[tecnica.tension] : '—';
    // Las hipertécnicas no cuestan tensión: no se les pinta ese renglón.
    var pieTension = tecnica.hipertecnica ? '' : '  <span class="caption mono">Tensión ' + tecnica.tension + ' · Potencia ' + potencia + '</span>';
    return '' +
      '<div class="super-tecnica-item" style="--afinidad-color:' + color + '" data-tecnica-id="' + tecnica.id + '">' +
      '  <div class="super-tecnica-item-head">' +
      '    <span class="super-tecnica-nombre">' + escaparHtml(tecnica.nombre) + '</span>' +
      '    <button type="button" class="btn-icon" data-quitar-tecnica="' + tecnica.id + '" aria-label="Quitar supertécnica"><i class="ph ph-x"></i></button>' +
      '  </div>' +
      '  <div class="super-tecnica-badges">' + badges + '</div>' +
      pieTension +
      '</div>';
  }

  function renderizarJugador(jugador) {
    var tecnicasHtml = jugador.tecnicas.length
      ? jugador.tecnicas.map(function (t) { return renderizarTecnica(jugador.clave, t); }).join('')
      : '<p class="caption">Sin supertécnicas todavía.</p>';
    var esSimulado = jugador.jugadorSimuladoId != null;
    var botonQuitar = esSimulado
      ? '<button type="button" class="btn-icon" data-quitar-jugador="' + jugador.clave + '" aria-label="Quitar jugador"><i class="ph ph-trash"></i></button>'
      : (jugador.tecnicas.length === 0
        ? '<button type="button" class="btn-icon" data-quitar-jugador="' + jugador.clave + '" aria-label="Quitar de la lista"><i class="ph ph-x"></i></button>'
        : '');
    return '' +
      '<div class="card super-jugador-card" data-clave="' + jugador.clave + '" data-jugador-id="' + (jugador.jugadorId || '') + '" data-jugador-simulado-id="' + (jugador.jugadorSimuladoId || '') + '">' +
      '  <div class="super-jugador-head">' +
      '    <span class="super-jugador-nombre"><i class="ph ph-user-circle"></i> ' + escaparHtml(jugador.nombre) + (esSimulado ? ' <span class="caption">(prueba)</span>' : '') + '</span>' +
      '    ' + botonQuitar +
      '  </div>' +
      '  <div class="super-tecnicas-lista">' + tecnicasHtml + '</div>' +
      (jugador.tecnicas.length < MAX_TECNICAS_POR_JUGADOR
        ? '  <button type="button" class="btn btn-sm btn-secondary" data-anadir-tecnica="' + jugador.clave + '"><i class="ph ph-plus"></i> Supertécnica</button>'
        : '  <p class="caption">Máximo de ' + MAX_TECNICAS_POR_JUGADOR + ' supertécnicas alcanzado.</p>') +
      '</div>';
  }

  function renderizarMetarrafagas() {
    var activas = METARRAFAGAS.filter(evaluarMetarrafaga);
    var wrap = document.getElementById('super-metarrafagas-wrap');
    var cont = document.getElementById('super-metarrafagas-activas');
    if (wrap && cont) {
      if (activas.length === 0) {
        wrap.hidden = true;
        cont.innerHTML = '';
      } else {
        wrap.hidden = false;
        cont.innerHTML = activas.map(function (mr) {
          return '<div class="super-mr-chip tt-rich" data-tt="' + escaparHtml(mr.nombre) + '" data-tt-desc="' + escaparHtml(mr.nota || 'Requisitos cumplidos.') + '"><i class="ph-fill ph-lightning"></i> ' + escaparHtml(mr.nombre) + '</div>';
        }).join('');
      }
    }
    renderizarChecklistMetarrafagas();
  }

  /* Checklist de TODAS las metarráfagas (no solo las activas), con estado por
     requisito: gris (nada), ámbar (algún requisito cumplido pero no todos),
     verde (completa). Reutiliza contarJugadoresConTecnica por requisito para
     mostrar progreso real, no solo sí/no. */
  function estadoMetarrafaga(mr) {
    var vetado = mr.vetoSiExiste && mr.vetoSiExiste.some(function (nombreVeto) {
      var buscado = normalizar(nombreVeto);
      return roster.some(function (j) { return j.tecnicas.some(function (t) { return normalizar(t.nombre) === buscado; }); });
    });
    var cumplidos = 0;
    var detalles = mr.requisitos.map(function (req) {
      var actual = contarJugadoresConTecnica(req.tecnica, req.afinidadesExcluidas);
      var cumplido = !vetado && actual >= req.cantidad;
      if (cumplido) cumplidos++;
      return { texto: req.tecnica + ' (' + Math.min(actual, req.cantidad) + '/' + req.cantidad + ')', cumplido: cumplido };
    });
    var estado = vetado ? 'ninguno' : (cumplidos === mr.requisitos.length ? 'completo' : (cumplidos > 0 ? 'parcial' : 'ninguno'));
    return { estado: estado, detalles: detalles };
  }

  function renderizarChecklistMetarrafagas() {
    var wrap = document.getElementById('super-mr-checklist-wrap');
    var cont = document.getElementById('super-mr-checklist');
    if (!wrap || !cont) return;
    wrap.hidden = false;
    cont.innerHTML = METARRAFAGAS.map(function (mr) {
      var r = estadoMetarrafaga(mr);
      var icono = r.estado === 'completo' ? 'ph-fill ph-check-circle' : (r.estado === 'parcial' ? 'ph-fill ph-warning-circle' : 'ph ph-circle-dashed');
      var reqsHtml = r.detalles.map(function (d) {
        return '<li class="super-mr-check-req" data-cumplido="' + (d.cumplido ? '1' : '0') + '"><i class="ph ' + (d.cumplido ? 'ph-check' : 'ph-minus') + '"></i> ' + escaparHtml(d.texto) + '</li>';
      }).join('');
      return '' +
        '<div class="super-mr-check" data-estado="' + r.estado + '">' +
        '  <div class="super-mr-check-head"><i class="super-mr-check-icono ' + icono + '"></i> <span class="super-mr-check-nombre">' + escaparHtml(mr.nombre) + '</span></div>' +
        '  <ul>' + reqsHtml + '</ul>' +
        '</div>';
    }).join('');
  }

  function render() {
    var cont = document.getElementById('super-roster');
    var vacio = document.getElementById('super-empty-state');
    if (!cont) return;
    if (roster.length === 0) {
      cont.innerHTML = '';
      if (vacio) vacio.hidden = false;
    } else {
      if (vacio) vacio.hidden = true;
      cont.innerHTML = roster.map(renderizarJugador).join('');
    }
    renderizarMetarrafagas();
  }

  function anadirJugadorReal(jugadorId, nombre, fotoUrl) {
    var clave = 'real:' + jugadorId;
    if (roster.some(function (j) { return j.clave === clave; })) return;
    roster.push({ clave: clave, jugadorId: jugadorId, jugadorSimuladoId: null, nombre: nombre, fotoUrl: fotoUrl || null, tecnicas: [] });
    render();
  }

  function quitarJugador(clave) {
    var jugador = roster.find(function (j) { return j.clave === clave; });
    if (!jugador) return;
    if (jugador.jugadorSimuladoId != null) {
      document.getElementById('super-form-jugador-simulado-id').value = jugador.jugadorSimuladoId;
      document.getElementById('form-super-jugador-eliminar').submit();
      return;
    }
    roster = roster.filter(function (j) { return j.clave !== clave; });
    render();
  }

  function quitarTecnica(tecnicaId) {
    document.getElementById('super-form-tecnica-id').value = tecnicaId;
    document.getElementById('form-super-tecnica-eliminar').submit();
  }

  function agregarTecnica(clave, datosForm) {
    var jugador = roster.find(function (j) { return j.clave === clave; });
    if (!jugador) return;
    if (jugador.tecnicas.length >= MAX_TECNICAS_POR_JUGADOR) {
      alert('Máximo ' + MAX_TECNICAS_POR_JUGADOR + ' supertécnicas por jugador (incluidas las hipertécnicas).');
      return;
    }
    document.getElementById('super-form-jugador-id').value = jugador.jugadorId || '';
    document.getElementById('super-form-tecnica-jugador-simulado-id').value = jugador.jugadorSimuladoId || '';
    document.getElementById('super-form-tecnica-nombre').value = datosForm.nombre || '';
    document.getElementById('super-form-tecnica-afinidad').value = datosForm.afinidad || '';
    document.getElementById('super-form-tecnica-tension').value = datosForm.tension || '';
    document.getElementById('super-form-tecnica-categoria').value = datosForm.categoria || '';
    document.getElementById('super-form-tecnica-subcategoria').value = datosForm.subcategoria || '';
    document.getElementById('super-form-tecnica-hipertecnica').value = datosForm.hipertecnica || '';
    document.getElementById('super-form-tecnica-despertar-variante').value = datosForm.despertar_variante || '';
    document.getElementById('super-form-tecnica-armadura').value = datosForm.armadura ? '1' : '';
    // Confirmación explícita de guardado (se lee tras la recarga, ver DOMContentLoaded).
    sessionStorage.setItem('tr_super_tecnica_guardada', '1');
    document.getElementById('form-super-tecnica-agregar').submit();
  }

  var claveModalActiva = null;

  function abrirFormularioTecnica(clave) {
    var jugador = roster.find(function (j) { return j.clave === clave; });
    if (!jugador) return;
    claveModalActiva = clave;

    var modal = document.getElementById('modal-super-tecnica');
    if (!modal) return;
    var form = document.getElementById('form-super-tecnica-modal');
    form.reset();

    var foto = document.getElementById('super-modal-foto');
    var placeholder = document.getElementById('super-modal-foto-placeholder');
    if (jugador.fotoUrl) {
      foto.src = jugador.fotoUrl;
      foto.alt = jugador.nombre;
      foto.style.display = '';
      placeholder.style.display = 'none';
    } else {
      foto.style.display = 'none';
      placeholder.style.display = '';
    }
    document.getElementById('super-modal-nombre').textContent = jugador.nombre;

    // Vuelve siempre a "Supertécnica" al abrir, sin arrastrar el tipo de la vez anterior.
    modal.querySelectorAll('[data-tipo-tecnica]').forEach(function (btn) {
      btn.classList.toggle('on', btn.dataset.tipoTecnica === 'supertecnica');
    });
    modal.querySelector('[data-grupo-supertecnica]').hidden = false;
    modal.querySelector('[data-grupo-hiper]').hidden = true;
    modal.querySelector('[data-campo-despertar]').hidden = true;
    modal.querySelector('[data-campo-armadura]').hidden = true;
    var selSubcategoria = form.querySelector('select[name="subcategoria"]');
    selSubcategoria.innerHTML = '<option value="">Sin subcategoría</option>';
    selSubcategoria.closest('.field').hidden = true;

    TR.abrirModal('modal-super-tecnica');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('super-roster');
    if (!panel) return; // esta página no tiene el simulador (no es mi_estrategia.php)

    roster = Array.isArray(window.TR_SUPER_ROSTER_INICIAL) ? window.TR_SUPER_ROSTER_INICIAL : [];
    render();

    if (sessionStorage.getItem('tr_super_tecnica_guardada') === '1') {
      sessionStorage.removeItem('tr_super_tecnica_guardada');
      if (window.TR && TR.toast) TR.toast('La supertécnica se ha guardado en tu cuenta. Solo tú la ves.', 'success', 'Guardado');
    }

    var btnAnadirReal = document.getElementById('btn-super-anadir-real');
    if (btnAnadirReal) {
      btnAnadirReal.addEventListener('click', function () {
        var select = document.getElementById('super-jugador-real');
        var id = parseInt(select.value, 10);
        if (!id) return;
        var opt = select.options[select.selectedIndex];
        anadirJugadorReal(id, opt.textContent, opt.dataset.foto);
        select.value = '';
      });
    }

    var modal = document.getElementById('modal-super-tecnica');
    if (modal) {
      var form = document.getElementById('form-super-tecnica-modal');
      var selCategoria = form.querySelector('select[name="categoria"]');
      var selSubcategoria = form.querySelector('select[name="subcategoria"]');
      var selHiper = form.querySelector('select[name="hipertecnica"]');
      var campoDespertar = modal.querySelector('[data-campo-despertar]');
      var campoArmadura = modal.querySelector('[data-campo-armadura]');

      selCategoria.addEventListener('change', function () {
        var opciones = SUBCATEGORIA_POR_CATEGORIA[selCategoria.value] || {};
        var claves = Object.keys(opciones);
        selSubcategoria.innerHTML = '<option value="">Sin subcategoría</option>' +
          claves.map(function (k) { return '<option value="' + k + '">' + escaparHtml(opciones[k]) + '</option>'; }).join('');
        selSubcategoria.closest('.field').hidden = claves.length === 0;
      });

      selHiper.addEventListener('change', function () {
        campoDespertar.hidden = selHiper.value !== 'despertar';
        campoArmadura.hidden = selHiper.value !== 'espiritu_guerrero';
      });

      modal.querySelectorAll('[data-tipo-tecnica]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var esHiper = btn.dataset.tipoTecnica === 'hiper';
          modal.querySelectorAll('[data-tipo-tecnica]').forEach(function (b) { b.classList.toggle('on', b === btn); });
          modal.querySelector('[data-grupo-supertecnica]').hidden = esHiper;
          modal.querySelector('[data-grupo-hiper]').hidden = !esHiper;
          if (esHiper) {
            campoDespertar.hidden = selHiper.value !== 'despertar';
            campoArmadura.hidden = selHiper.value !== 'espiritu_guerrero';
          }
        });
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!claveModalActiva) return;
        var esHiper = !modal.querySelector('[data-grupo-hiper]').hidden;
        var fd = new FormData(form);
        var datos = { nombre: fd.get('nombre'), afinidad: fd.get('afinidad') };
        if (esHiper) {
          datos.hipertecnica = fd.get('hipertecnica');
          datos.despertar_variante = datos.hipertecnica === 'despertar' ? fd.get('despertar_variante') : '';
          datos.armadura = datos.hipertecnica === 'espiritu_guerrero' && fd.get('armadura') ? '1' : '';
          datos.tension = '';
          datos.categoria = '';
          datos.subcategoria = '';
        } else {
          datos.tension = fd.get('tension');
          datos.categoria = fd.get('categoria');
          datos.subcategoria = fd.get('subcategoria');
          datos.hipertecnica = '';
          datos.despertar_variante = '';
        }
        agregarTecnica(claveModalActiva, datos);
        TR.cerrarModal('modal-super-tecnica');
      });
    }

    document.addEventListener('click', function (e) {
      var btnQuitarJugador = e.target.closest('[data-quitar-jugador]');
      if (btnQuitarJugador) { quitarJugador(btnQuitarJugador.dataset.quitarJugador); return; }

      var btnQuitarTecnica = e.target.closest('[data-quitar-tecnica]');
      if (btnQuitarTecnica) { quitarTecnica(parseInt(btnQuitarTecnica.dataset.quitarTecnica, 10)); return; }

      var btnAnadirTecnica = e.target.closest('[data-anadir-tecnica]');
      if (btnAnadirTecnica) { abrirFormularioTecnica(btnAnadirTecnica.dataset.anadirTecnica); return; }
    });
  });
})();
