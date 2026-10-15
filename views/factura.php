<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Facturación';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Facturación</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Ventas / Facturación</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>facturas/ayuda"
                data-ayuda-titulo="Ayuda — Facturación">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>facturas/crear" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Nueva factura (venta inmediata)
        </a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<!-- TABS -->
<div class="fac-tabs">
    <button type="button" class="fac-tab active" data-tab="pedidos">
        Pedidos listos para facturar
        <span class="fac-tab-count"><?= count($pedidos) ?></span>
    </button>
    <button type="button" class="fac-tab" data-tab="facturas">
        Facturas emitidas
    </button>
</div>

<!-- TAB 1: Pedidos preparados -->
<div class="fac-tab-content active" id="tab-pedidos">
    <div class="content-card">
        <div class="filter-section">
            <div class="form-group" style="max-width:400px;">
                <label for="ped_search">BUSCAR PEDIDO / CLIENTE</label>
                <input type="text" id="ped_search" placeholder="N° de pedido o razón social...">
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">N°</th>
                        <th>Cliente</th>
                        <th style="width:110px;">Fecha</th>
                        <th style="width:90px;">Items</th>
                        <th style="text-align:right;width:130px;">Total</th>
                        <th style="text-align:right;width:140px;">Acción</th>
                    </tr>
                </thead>
                <tbody id="pedidosTableBody">
                    <?php include __DIR__ . '/layouts/facturas_pedidos_row.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 2: Facturas emitidas -->
<div class="fac-tab-content" id="tab-facturas">
    <div class="content-card">
        <div class="fac-filters">
            <div class="form-group">
                <label for="fac_desde">DESDE</label>
                <input type="date" id="fac_desde" value="<?= htmlspecialchars($desde) ?>">
            </div>
            <div class="form-group">
                <label for="fac_hasta">HASTA</label>
                <input type="date" id="fac_hasta" value="<?= htmlspecialchars($hasta) ?>">
            </div>
            <div class="form-group">
                <label for="fac_emisor">EMISOR</label>
                <select id="fac_emisor">
                    <option value="0">Todos</option>
                    <?php foreach ($emisores as $e): ?>
                        <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-group" style="flex:0 0 130px;">
                    <label for="fac_estado">ESTADO</label>
                    <select id="fac_estado">
                        <option value="">Todas</option>
                        <option value="ACTIVA">Activas</option>
                        <option value="ANULADA">Anuladas</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="fac_busqueda">BUSCAR</label>
                <input type="text" id="fac_busqueda" placeholder="Cliente, CUIT, N°...">
            </div>
            <div class="form-group" style="flex:0 0 140px;">
                <label for="fac_arca">ARCA</label>
                <select id="fac_arca">
                    <option value="">Todos</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="APROBADO">Aprobado</option>
                    <option value="ERROR">Error</option>
                </select>
            </div>
        </div>

        <div class="comp-actions">
            <div class="comp-actions-info">
                <span id="fac_total"><?= count($facturas) ?></span> factura(s)
            </div>
            <div class="comp-actions-buttons">
                <button type="button" id="btnExportarExcel" class="btn btn-outline">Exportar Excel</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">N°</th>
                        <th style="width:100px;">Fecha</th>
                        <th>Cliente</th>
                        <th style="width:140px;">Remito</th>
                        <th style="width:130px;">N° ARCA / CAE</th>
                        <th style="width:110px;">Emisor</th>
                        <th style="text-align:right;width:120px;">Total</th>
                        <th style="width:100px;">Estado</th>
                        <th style="text-align:right;width:100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="facturasTableBody">
                    <?php include __DIR__ . '/layouts/facturas_emitidas_row.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>