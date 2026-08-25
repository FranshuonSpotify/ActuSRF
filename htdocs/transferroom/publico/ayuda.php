<?php

declare(strict_types=1);

/**
 * Ayuda / Centro de Soporte (Fase 2, hub v3): guía de reglas, sala de
 * práctica explicativa, tour guiado del hub y soporte consolidado (enlaza
 * con reportar_problema.php, ya existente, en vez de duplicarlo). Sin
 * especificación previa — diseño propio.
 */

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = $autenticacion->requerirSesion();

$paginaTitulo = 'Ayuda';
$base = '';
include __DIR__ . '/../partials/head.php';

$activePage = 'ayuda';
include __DIR__ . '/../partials/nav.php';
?>
<main id="contenido" class="wrap sec">
    <div class="page-head">
        <div>
            <span class="overline">Ayuda</span>
            <h1 class="h1" style="margin-top:.5rem">Centro de Ayuda</h1>
        </div>
    </div>

    <div class="tabs" data-tab-group style="margin-bottom:1.5rem">
        <button class="on" data-tab-target="guia">Guía</button>
        <button data-tab-target="practica">Sala de práctica</button>
        <button data-tab-target="tour">Tour guiado</button>
        <button data-tab-target="soporte">Soporte</button>
    </div>

    <div data-tab-panel="guia">
        <div style="display:flex;flex-direction:column;gap:.75rem;max-width:760px">
            <?php
                $preguntas = [
                    '¿Cómo funciona el Salary Cap?' => 'Cada club tiene un tope fijo de salarios activos (250.000.000 por defecto, configurable por temporada). Ningún fichaje ni traspaso puede completarse si el salario del jugador supera el margen disponible.',
                    '¿Qué diferencia hay entre RFA y UFA?' => 'Cuando un contrato termina, el jugador sale siempre a agencia libre: nunca hay renovación directa. Si el contrato era de 1 temporada, sale como RFA (Restricted Free Agent): su club de origen tiene una ventana de 48h para igualar la mejor oferta antes de perderlo. Si era de 2 temporadas, sale como UFA (Unrestricted Free Agent): cualquier club puede ficharlo sin restricciones.',
                    '¿Cómo funciona la ventana de igualación RFA?' => 'Al cerrarse el mercado, si la mejor oferta por un jugador RFA no es de su propio club, se abre una ventana de 48h en la que solo ese club puede igualar la oferta y quedarse con el jugador. Pasado el plazo, un administrador confirma el traspaso a quien ganó la puja.',
                    '¿Qué es un jugador Franquicia?' => 'Cada club puede designar hasta 4 jugadores como Franquicia. Al terminar su contrato, en vez de salir a RFA/UFA se gira una ruleta con 3 desenlaces equiprobables: retención con 20% de descuento, retención al mismo precio, o salida directa sin derecho de igualación.',
                    '¿Qué es la Protección Franchise clásica?' => 'Una alternativa a designar franquicia: paga un extra del +10% sobre el salario del jugador para blindarlo. Es incompatible con la designación de Franquicia sobre el mismo jugador: son dos mecanismos distintos, no acumulables.',
                    '¿Puedo ofertar por un jugador con contrato en otro club?' => 'Sí, es un traspaso: se dirige la oferta al club que lo tiene contratado, que puede aceptarla, rechazarla o hacer una contraoferta. Aceptar un traspaso finaliza el contrato viejo y crea uno nuevo en el club comprador — nunca se edita el contrato existente.',
                    '¿Cuándo se resuelven las ofertas del mercado?' => 'Todas las ofertas (agentes libres y traspasos) se resuelven al cierre general del mercado, nunca por expiración individual. El cierre del mercado también fuerza el cierre de cualquier ventana RFA que siguiera abierta.',
                    '¿Puedo pujar dos veces por el mismo jugador?' => 'No como dos ofertas en paralelo: si ya tienes una oferta activa por ese jugador, una nueva puja la sustituye. Solo puede haber una oferta activa por jugador y club.',
                ];
            ?>
            <?php foreach ($preguntas as $pregunta => $respuesta): ?>
                <details class="card" style="padding:1rem 1.2rem">
                    <summary class="body-sm" style="cursor:pointer;font-weight:600"><?= htmlspecialchars($pregunta) ?></summary>
                    <p class="caption" style="margin-top:.6rem"><?= htmlspecialchars($respuesta) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>

    <div data-tab-panel="practica" hidden>
        <p class="caption" style="margin-bottom:1rem;max-width:640px">
            No hay un entorno de pruebas separado para presidentes: cualquier ficha, puja o traspaso que hagas es real. Esto es un paseo explicado de cómo se resuelve una puja de agente libre, paso a paso, sin tocar nada.
        </p>
        <div style="display:flex;flex-direction:column;gap:1rem;max-width:640px">
            <div class="card" style="display:flex;gap:1rem;align-items:flex-start">
                <span class="chip">1</span>
                <div><strong class="body-sm">Entras en Mercado y pujas</strong><p class="caption" style="margin-top:.3rem">Eliges un agente libre y ofreces un salario dentro de tu Salary Cap disponible. La oferta queda visible para todos los presidentes.</p></div>
            </div>
            <div class="card" style="display:flex;gap:1rem;align-items:flex-start">
                <span class="chip">2</span>
                <div><strong class="body-sm">Otros presidentes pueden pujar más alto</strong><p class="caption" style="margin-top:.3rem">Si ya tenías una oferta activa por ese jugador y vuelves a pujar, se actualiza la misma oferta: no se crean dos en paralelo.</p></div>
            </div>
            <div class="card" style="display:flex;gap:1rem;align-items:flex-start">
                <span class="chip">3</span>
                <div><strong class="body-sm">El mercado se cierra</strong><p class="caption" style="margin-top:.3rem">Un administrador cierra el mercado. Todas las ofertas se resuelven a la vez: gana la más alta por jugador (en empate, la más antigua).</p></div>
            </div>
            <div class="card" style="display:flex;gap:1rem;align-items:flex-start">
                <span class="chip">4</span>
                <div><strong class="body-sm">Si el jugador era RFA de otro club</strong><p class="caption" style="margin-top:.3rem">Se abre una ventana de 48h para que su club de origen iguale tu oferta. Si no la iguala a tiempo, el jugador pasa a tu plantilla.</p></div>
            </div>
        </div>
    </div>

    <div data-tab-panel="tour" hidden>
        <p class="caption" style="margin-bottom:1rem;max-width:640px">Un recorrido guiado por los iconos del menú de arriba, uno a uno, con lo que hace cada uno.</p>
        <button type="button" class="btn btn-primary" onclick="TRtour.iniciar()">Iniciar tour</button>
        <div id="tour-overlay" hidden style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000"></div>
        <div id="tour-tooltip" hidden role="dialog" aria-live="polite" class="card" style="position:fixed;z-index:1001;max-width:320px;padding:1rem 1.2rem">
            <p class="body-sm" id="tour-texto" style="margin-bottom:.75rem"></p>
            <div style="display:flex;justify-content:space-between;gap:.5rem">
                <button type="button" class="btn btn-ghost btn-sm" onclick="TRtour.cerrar()">Saltar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="TRtour.siguiente()" id="tour-siguiente">Siguiente</button>
            </div>
        </div>
    </div>

    <div data-tab-panel="soporte" hidden>
        <div class="card" style="max-width:520px">
            <h2 class="h4" style="margin-bottom:.5rem">¿Sigues con dudas o un problema técnico?</h2>
            <p class="caption" style="margin-bottom:1rem">Reporta el problema y quedará registrado con tu usuario, la página de origen y la hora, para que un administrador lo revise.</p>
            <a class="btn btn-primary" href="reportar_problema.php?origen=ayuda.php">Reportar un problema</a>
        </div>
    </div>
</main>
<script>
var TRtour = {
    pasos: [
        { ruta: 'dashboard.php', texto: 'Resumen: tu panel de inicio, con lo más relevante de tu club de un vistazo.' },
        { ruta: 'plantilla.php', texto: 'Mi Plantilla: tus jugadores, sus contratos y el estado de tu Salary Cap.' },
        { ruta: 'mercado.php', texto: 'Mercado: agentes libres disponibles y tus pujas activas.' },
        { ruta: 'liga_clubes.php', texto: 'Liga / Clubes: todos los clubes de la temporada, por división.' },
        { ruta: 'peticiones.php', texto: 'Peticiones: el tablón donde pides o propones traspasos abiertamente.' },
        { ruta: 'franquicias.php', texto: 'Franquicias: gestiona tus hasta 4 jugadores franquicia.' },
        { ruta: 'finanzas.php', texto: 'Finanzas: tu Salary Cap, tu dinero de traspasos y tu salud como club.' },
        { ruta: 'scouting.php', texto: 'Scouting: busca cualquier jugador de la liga, contratado o libre.' },
        { ruta: 'actividad_liga.php', texto: 'Actividad de la Liga: feed, prensa, ranking y calendario de vencimientos.' },
        { ruta: 'mi_estrategia.php', texto: 'Mi Estrategia: tu espacio personal de planificación, sin afectar a nadie más.' }
    ],
    indice: 0,

    iniciar: function () {
        this.indice = 0;
        document.getElementById('tour-overlay').hidden = false;
        this.mostrar();
    },

    mostrar: function () {
        var paso = this.pasos[this.indice];
        var enlace = document.querySelector('.nav-hub a[href$="' + paso.ruta + '"]');
        var tooltip = document.getElementById('tour-tooltip');
        document.getElementById('tour-texto').textContent = paso.texto;
        document.getElementById('tour-siguiente').textContent = (this.indice === this.pasos.length - 1) ? 'Terminar' : 'Siguiente';
        tooltip.hidden = false;

        if (enlace) {
            enlace.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
            var r = enlace.getBoundingClientRect();
            tooltip.style.top = (r.bottom + 10) + 'px';
            tooltip.style.left = Math.max(10, Math.min(r.left, window.innerWidth - 330)) + 'px';
        } else {
            tooltip.style.top = '100px';
            tooltip.style.left = '20px';
        }
    },

    siguiente: function () {
        this.indice++;
        if (this.indice >= this.pasos.length) { this.cerrar(); return; }
        this.mostrar();
    },

    cerrar: function () {
        document.getElementById('tour-overlay').hidden = true;
        document.getElementById('tour-tooltip').hidden = true;
    }
};
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
