<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Nuevo cobro';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Nuevo cobro</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>cobros">Cobros</a> / Nuevo
        </div>
    </div>
    <a href="<?= BASE_URL ?>cobros" class="btn btn-outline">← Volver al listado</a>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="cobroForm" autocomplete="off">

    <!-- BARRA SUPERIOR CON TOTALES -->
    <div class="cob-totales">
        <div class="cob-totales-item">
            <span class="cob-totales-label">TOTAL A COBRAR</span>
            <span class="cob-totales-valor" id="cobTotalMedios">$ 0,00</span>
        </div>
        <div class="cob-totales-item">
            <span class="cob-totales-label">TOTAL IMPUTADO</span>
            <span class="cob-totales-valor" id="cobTotalImputado">$ 0,00</span>
        </div>
        <div class="cob-totales-item">
            <span class="cob-totales-label">DIFERENCIA</span>
            <span class="cob-totales-valor cob-totales-total" id="cobDiferencia">$ 0,00</span>
        </div>
    </div>

    <div id="cobAlertaDiferencia" style="display:none;"></div>

    <!-- ============================================================
         DATOS DEL COBRO
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Datos del cobro</h3>
            <p>Elegí el cliente y la fecha del cobro.</p>
        </div>
        <div class="card-body cob-grid-2">

            <div class="form-group">
                <label for="cliente_id">Cliente *</label>
                <select id="cliente_id" name="cliente_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($combos['clientes'] as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>" <?= $clienteIdPre === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['razon_social']) ?>
                            · <?= $cl['condicion_venta'] === 'CUENTA_CORRIENTE' ? 'Cta. Cte.' : 'Contado' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="fecha">Fecha del cobro *</label>
                <input type="date" id="fecha" name="fecha" required value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group field-full" id="bloqueSaldoCliente" style="display:none;">
                <label>Saldo actual del cliente</label>
                <div class="fac-readonly" id="cobSaldoCliente"></div>
            </div>

        </div>
    </div>

    <!-- ============================================================
         FACTURAS PENDIENTES
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Facturas pendientes</h3>
            <p>Marcá las facturas que se cobran e ingresá el monto a imputar a cada una.</p>
        </div>
        <div class="card-body card-body-stack">

            <div id="cobFacturasLoader" class="cob-empty" style="display:none;">
                Cargando facturas...
            </div>

            <div id="cobFacturasEmpty" class="cob-empty" style="display:none;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <p id="cobFacturasEmptyMsg">Este cliente no tiene facturas pendientes.</p>
            </div>

            <div id="cobFacturasLista" class="cob-facturas-lista" style="display:none;"></div>

            <div id="cobFacturasAcciones" style="display:none; text-align:right; margin-top:12px;">
                <button type="button" class="btn btn-outline" id="btnImputarAuto">
                    ⚡ Imputar automáticamente (más antiguas primero)
                </button>
                <button type="button" class="btn btn-outline" id="btnLimpiarImputacion">
                    Limpiar selección
                </button>
            </div>

        </div>
    </div>

    <!-- ============================================================
         MEDIOS DE PAGO
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Medios de pago</h3>
            <p>Un cobro puede tener varios medios (ej: mitad efectivo, mitad transferencia).</p>
        </div>
        <div class="card-body card-body-stack">

            <div id="cobMediosLista" class="cob-medios-lista"></div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" id="btnAgregarMedio">
                    + Agregar medio de pago
                </button>
                <button type="button" class="btn btn-outline" data-add-medio="EFECTIVO">Efectivo</button>
                <button type="button" class="btn btn-outline" data-add-medio="TRANSFERENCIA">Transferencia</button>
                <button type="button" class="btn btn-outline" data-add-medio="CHEQUE">Cheque</button>
                <button type="button" class="btn btn-outline" data-add-medio="RETENCION">Retención</button>
            </div>

        </div>
    </div>

    <!-- ============================================================
         NOTAS
         ============================================================ -->
    <div class="card">
        <div class="card-header">
            <h3>Notas</h3>
        </div>
        <div class="card-body card-body-stack">
            <div class="form-group">
                <textarea id="notas" name="notas" rows="2" maxlength="500"
                          placeholder="Observaciones del cobro..."></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>cobros" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary" id="btnGuardarCobro">
            Guardar cobro
        </button>
    </div>

</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>