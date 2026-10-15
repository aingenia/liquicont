<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Calendario de Cheques';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Calendario de Cheques</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Calendario de Cheques</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>calendario/ayuda"
                data-ayuda-titulo="Ayuda — Calendario de Cheques">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<div class="content-card">
    <div id="calendar"></div>
</div>

<!-- ============================================================
     MODAL: lista del día + form para agregar/editar cheque
     ============================================================ -->
<div class="cal-modal-overlay" id="modalCheque" style="display:none;">
    <div class="cal-modal">
        <div class="cal-modal-header">
            <div>
                <h3 id="modalTitulo">Cheques del día</h3>
                <span class="cal-modal-fecha" id="modalFechaLabel"></span>
            </div>
            <button type="button" class="cal-modal-close" id="modalCerrar" title="Cerrar">&times;</button>
        </div>

        <div class="cal-modal-body">

            <!-- Lista de cheques existentes -->
            <div class="cal-cheques-lista" id="chequesLista">
                <!-- Se llena por AJAX -->
            </div>

            <!-- Total del día -->
            <div class="cal-total-dia" id="calTotalDia" style="display:none;">
                Total del día: <strong id="calTotalValor">$ 0,00</strong>
            </div>

            <!-- Separador -->
            <hr class="cal-separador">

            <!-- Form para nuevo cheque / editar -->
            <h4 id="calFormTitulo" class="cal-form-titulo">Agregar cheque</h4>
            <form id="formCheque" autocomplete="off">
                <input type="hidden" id="evento_id" name="id">
                <input type="hidden" id="evento_fecha" name="fecha">

                <div class="cal-form-row">
                    <div class="form-group">
                        <label for="evento_importe">Importe *</label>
                        <input type="number" id="evento_importe" name="importe"
                               step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="evento_tipo">Tipo</label>
                        <select id="evento_tipo" name="tipo">
                            <option value="TERCERO">Tercero</option>
                            <option value="PROPIO">Propio</option>
                        </select>
                    </div>
                </div>

                <div class="cal-form-row">
                    <div class="form-group">
                        <label for="evento_banco">Banco</label>
                        <input type="text" id="evento_banco" name="banco" maxlength="100" placeholder="Ej: Galicia">
                    </div>
                    <div class="form-group">
                        <label for="evento_nro_cheque">N° Cheque</label>
                        <input type="text" id="evento_nro_cheque" name="nro_cheque" maxlength="50" placeholder="Ej: 12345678">
                    </div>
                </div>

                <div class="form-group">
                    <label for="evento_descripcion">Descripción *</label>
                    <textarea id="evento_descripcion" name="descripcion" rows="2"
                              maxlength="500" 
                              placeholder="Aclaraciones o detalles del cheque..."></textarea>
                </div>

                <div class="cal-form-actions">
                    <button type="button" class="btn btn-outline" id="btnCancelarEdicion" style="display:none;">
                        Cancelar edición
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCheque">
                        Agregar cheque
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- FullCalendar (skeleton + lib + locales) -->
<script src="<?= BASE_URL ?>dist/js/fullcalendar.js"></script>


<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>