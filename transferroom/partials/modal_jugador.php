<?php
/**
 * Modal unificado de jugador (Fase 2, 02_ux_diseno/02_vision_estetica_modal_jugador.md):
 * un único componente, sin variaciones copiadas, para las 3 situaciones en
 * las que se hace clic sobre un jugador en cualquier pantalla:
 *   - agente_libre: formulario de oferta + pujas actuales (mercado.php)
 *   - traspaso: formulario de oferta de traspaso + resumen del contrato actual (plantilla.php, club ajeno)
 *   - propio: acciones de gestión, sin formulario de oferta (plantilla.php, club propio)
 * Los datos viajan ya renderizados desde el servidor en el propio trigger
 * (misma convención que la ceremonia de sobres del otro proyecto hermano:
 * nunca se reconstruye en JS lo que el servidor ya sabe).
 * Requiere $misParticipaciones ya definido por el llamador (para el selector
 * de club al ofertar); si no aplica, pásalo como [].
 */
?>
<div class="modal-bg" id="modal-jugador">
    <div class="modal" id="modal-jugador-caja" role="dialog" aria-modal="true" aria-labelledby="modal-jugador-nombre" style="width:min(92vw,620px)">
        <div class="modal-head modal-jugador-head">
            <div class="fila-jugador" style="gap:1.25rem">
                <img class="jugador-foto-xl" id="modal-jugador-foto" src="" alt="" loading="lazy" referrerpolicy="no-referrer">
                <div>
                    <h2 id="modal-jugador-nombre" class="modal-jugador-nombre-grande"></h2>
                    <div style="display:flex;gap:.4rem;margin-top:.5rem;flex-wrap:wrap">
                        <span class="chip" id="modal-jugador-tier"></span>
                        <span class="badge" id="modal-jugador-posicion"></span>
                        <span class="badge" id="modal-jugador-agencia" style="display:none"></span>
                        <span class="badge badge-accent" id="modal-jugador-franquicia" style="display:none"><i class="ph-fill ph-crown-simple"></i> Franquicia</span>
                    </div>
                </div>
            </div>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <a id="modal-jugador-wiki" href="#" target="_blank" rel="noopener noreferrer"
               class="btn btn-secondary btn-sm" style="display:none;margin-bottom:1.25rem;width:fit-content">
                <i class="ph ph-book-open-text"></i> Ver en la Wiki
            </a>
            <div id="modal-jugador-club-origen" class="body-sm" style="display:none;margin-bottom:1rem"></div>

            <!-- Cuerpo: agente libre (mercado.php) -->
            <div id="modal-jugador-cuerpo-agente-libre" style="display:none">
                <h3 class="h4" style="margin-bottom:.75rem">Pujas activas</h3>
                <div id="modal-jugador-ofertas" class="body-sm" style="margin-bottom:1.25rem"></div>

                <form method="post" id="modal-jugador-form-agente-libre">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="accion" value="ofertar">
                    <input type="hidden" name="jugador_id" id="modal-jugador-id" value="">
                    <?php if (count($misParticipaciones) === 1): ?>
                        <input type="hidden" name="participacion_id" value="<?= (int) $misParticipaciones[0]['id'] ?>">
                        <p class="caption" style="margin-bottom:1rem">Ofertando en nombre de <strong><?= htmlspecialchars($misParticipaciones[0]['club_nombre']) ?></strong></p>
                    <?php else: ?>
                        <div class="field">
                            <label class="field-label" for="modal-jugador-club">Tu club</label>
                            <select class="select" id="modal-jugador-club" name="participacion_id" required>
                                <?php foreach ($misParticipaciones as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['club_nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="field">
                        <label class="field-label" for="modal-jugador-salario">Tu oferta (mínimo el salario base del tier)</label>
                        <input class="input" id="modal-jugador-salario" type="number" name="salario_ofertado" min="1" step="1" required>
                    </div>
                    <div class="field">
                        <label class="field-label" for="modal-jugador-duracion">Duración</label>
                        <select class="select" id="modal-jugador-duracion" name="duracion_temporadas" required>
                            <option value="1">1 temporada — conservas el derecho de tanteo (RFA) al terminar</option>
                            <option value="2">2 temporadas — pierdes el derecho de tanteo (UFA) al terminar</option>
                        </select>
                        <span class="caption" style="margin-top:.3rem;display:block">1 año = puedes igualar cualquier oferta rival cuando termine. 2 años = al terminar, cualquier club puede ficharlo sin que tú tengas ninguna preferencia.</span>
                    </div>
                    <div class="modal-foot" style="padding:0;margin-top:1.5rem">
                        <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                        <button type="submit" class="btn btn-primary">Enviar oferta</button>
                    </div>
                </form>
            </div>

            <!-- Cuerpo: traspaso (plantilla.php / mercado.php, jugador con contrato en otro club) -->
            <div id="modal-jugador-cuerpo-traspaso" style="display:none">
                    <div class="traspaso-grid">
                        <div>
                            <span class="traspaso-col-eyebrow">Contexto</span>
                            <div class="card traspaso-card con-filo">
                                <div class="traspaso-section">
                                    <div class="traspaso-section-label"><i class="ph ph-file-text"></i> Contrato actual</div>
                                    <div class="fila-club">
                                        <img id="modal-jugador-traspaso-club-escudo" class="escudo" src="" alt="" loading="lazy" hidden>
                                        <strong id="modal-jugador-traspaso-club"></strong>
                                    </div>
                                    <div class="traspaso-stats-grid">
                                        <div class="traspaso-stat">
                                            <span class="caption"><i class="ph ph-coin"></i> Salario</span>
                                            <strong class="mono" id="modal-jugador-traspaso-salario"></strong>
                                        </div>
                                        <div class="traspaso-stat">
                                            <span class="caption"><i class="ph ph-hourglass-medium"></i> Duración restante</span>
                                            <strong id="modal-jugador-traspaso-duracion"></strong>
                                        </div>
                                    </div>
                                </div>

                                <div id="modal-jugador-traspaso-cap" class="traspaso-section" style="display:none">
                                    <div class="traspaso-section-label"><i class="ph ph-gauge"></i> Salary Cap de tu club, tras este fichaje</div>
                                    <div class="traspaso-cap-bar"><div class="traspaso-cap-bar-fill" id="modal-jugador-traspaso-cap-fill" style="width:0%"></div></div>
                                    <div class="traspaso-cap-footer">
                                        <span id="modal-jugador-traspaso-cap-gastado"></span>
                                        <span id="modal-jugador-traspaso-cap-margen"></span>
                                    </div>
                                </div>

                                <div id="modal-jugador-traspaso-franquicia-nota" class="alert alert-warning traspaso-franquicia-nota" style="display:none">
                                    <i class="ph-fill ph-crown-simple"></i>
                                    <span>Jugador franquicia: su club puede pagar un impuesto de +10M sobre su salario base para protegerlo cuando termine el contrato, en vez de dejarlo salir a agencia libre.</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span class="traspaso-col-eyebrow">Mercado en vivo</span>
                            <div class="card traspaso-card con-filo">
                                <div class="traspaso-section">
                                    <div class="traspaso-section-label"><i class="ph ph-handshake"></i> Ofertas de traspaso recibidas</div>
                                    <div class="traspaso-ofertas-lista" id="modal-jugador-traspaso-ofertas-lista"></div>
                                </div>

                                <div class="traspaso-section">
                                    <div class="traspaso-section-label"><i class="ph ph-target"></i> Tu oferta</div>
                                    <form id="modal-jugador-form-traspaso">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                        <input type="hidden" name="accion" value="ofertar_traspaso">
                                        <input type="hidden" name="jugador_id" id="modal-jugador-traspaso-id" value="">
                                        <?php if (count($misParticipaciones) === 1): ?>
                                            <input type="hidden" name="participacion_compradora_id" value="<?= (int) $misParticipaciones[0]['id'] ?>">
                                            <p class="caption" style="margin-bottom:1rem">Ofertando en nombre de <strong><?= htmlspecialchars($misParticipaciones[0]['club_nombre']) ?></strong></p>
                                        <?php else: ?>
                                            <div class="field">
                                                <label class="field-label" for="modal-jugador-traspaso-club-comprador">Tu club</label>
                                                <select class="select" id="modal-jugador-traspaso-club-comprador" name="participacion_compradora_id" required onchange="TRjugador.alCambiarClubComprador()">
                                                    <?php foreach ($misParticipaciones as $p): ?>
                                                        <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['club_nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                        <div class="field">
                                            <label class="field-label" for="modal-jugador-traspaso-importe">Importe en dinero (opcional si ofreces jugadores)</label>
                                            <input class="input" id="modal-jugador-traspaso-importe" type="number" name="importe_traspaso" min="0" step="1" value="0" oninput="TRjugador.compararOfertaTraspaso()">
                                            <div class="traspaso-importe-hint" id="modal-jugador-traspaso-hint"></div>
                                            <div class="traspaso-quick-fills" id="modal-jugador-traspaso-quick-fills" style="display:none">
                                                <button type="button" class="chip traspaso-quick-fill" onclick="TRjugador.rellenarImporteTraspaso(1)">Igualar líder</button>
                                                <button type="button" class="chip traspaso-quick-fill" onclick="TRjugador.rellenarImporteTraspaso(1.1)">+10% sobre el líder</button>
                                            </div>
                                        </div>
                                        <div class="field" style="margin-bottom:0">
                                            <label class="field-label">Jugadores a cambio (opcional)</label>
                                            <div id="modal-jugador-traspaso-jugadores-picker" class="traspaso-jugadores-picker"></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <div class="traspaso-actions">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" form="modal-jugador-form-traspaso" class="btn btn-primary">Ofertar traspaso</button>
                </div>
            </div>

            <!-- Cuerpo: jugador propio (plantilla.php, club propio) -->
            <div id="modal-jugador-cuerpo-propio" style="display:none">
                <div class="card" style="margin-bottom:1.25rem">
                    <span class="caption">Contrato</span>
                    <div class="body-sm" style="margin-top:.5rem">
                        Salario: <span class="mono" id="modal-jugador-propio-salario"></span><br>
                        Duración restante: <span id="modal-jugador-propio-duracion"></span>
                    </div>
                </div>
                <div class="modal-foot" style="padding:0;justify-content:flex-start;gap:.5rem;flex-wrap:wrap" id="modal-jugador-propio-acciones"></div>
            </div>
        </div>
    </div>
</div>

<!-- Checklist de primer mercado (A.3): marcar/desmarcar transferible. Sin
     confirmación reforzada (no tiene ninguna consecuencia económica ni
     irreversible, a diferencia de proteger/finalizar) — mismo criterio que
     el botón de favoritos, envío directo. -->
<form method="post" id="form-alternar-transferible" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="accion" value="alternar_transferible">
    <input type="hidden" name="contrato_id" id="form-alternar-transferible-contrato-id" value="">
</form>

<!-- Confirmación reforzada de Protección Franchise clásica, reutilizada por el modal unificado (Cap. XVI: nunca confirm() del navegador). -->
<div class="modal-bg" id="modal-proteger-unificado">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-proteger-unificado-title">
        <div class="modal-head">
            <h2 id="modal-proteger-unificado-title">¿Proteger este jugador (+10M)?</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <span id="modal-proteger-unificado-texto"></span> Se renovará 1 temporada más pagando un impuesto fijo de +10M sobre el salario base de su tier. Esta acción no se puede deshacer.
        </div>
        <form method="post" id="form-proteger-unificado">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="proteger_franchise_clasica">
            <input type="hidden" name="contrato_id" id="modal-proteger-unificado-contrato-id" value="">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Proteger (+10M)</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirmación reforzada de Finalizar contrato, reutilizada por el modal unificado. -->
<div class="modal-bg" id="modal-finalizar-unificado">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-finalizar-unificado-title">
        <div class="modal-head">
            <h2 id="modal-finalizar-unificado-title">¿Finalizar este contrato?</h2>
            <button class="btn-icon" data-modal-close aria-label="Cerrar"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <span id="modal-finalizar-unificado-texto"></span> Esta acción no se puede deshacer. Si el jugador es franquicia, se resolverá con la ruleta de retención; si no, pasará a agente libre.
        </div>
        <form method="post" id="form-finalizar-unificado">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="accion" value="finalizar_contrato">
            <input type="hidden" name="contrato_id" id="modal-finalizar-unificado-contrato-id" value="">
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-danger">Finalizar contrato</button>
            </div>
        </form>
    </div>
</div>
