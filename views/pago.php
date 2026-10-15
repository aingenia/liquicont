<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Pagos a Proveedores';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Pagos a Proveedores</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Compras / Pagos</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>pagos/ayuda"
                data-ayuda-titulo="Ayuda — Pagos a Proveedores">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>pagos/crear" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Nuevo pago
        </a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<!-- ============================================================
     TABS
     ============================================================ -->
<div class="fac-tabs">
    <button type="button" class="fac-tab active" data-tab="pagos">
        Pagos realizados
        <span class="fac-tab-count"><?= count($pagos) ?></span>
    </button>
    <button type="button" class="fac-tab" data-tab="compras">
        Estado de compras
        <span class="fac-tab-count" id="facTabComprasCount"><?= count($compras) ?></span>
    </button>
</div>

<!-- ============================================================
     TAB 1: PAGOS REALIZADOS
     ============================================================ -->
<div class="fac-tab-content active" id="tab-pagos">
    <div class="content-card">
        <div class="filter-section">
            <div class="cob-filters">
                <div class="form-group">
                    <label for="filtro_desde">DESDE</label>
                    <input type="date" id="filtro_desde" value="<?= htmlspecialchars($desde) ?>">
                </div>
                <div class="form-group">
                    <label for="filtro_hasta">HASTA</label>
                    <input type="date" id="filtro_hasta" value="<?= htmlspecialchars($hasta) ?>">
                </div>
                <div class="form-group">
                    <label for="filtro_proveedor">PROVEEDOR</label>
                    <select id="filtro_proveedor">
                        <option value="0">Todos</option>
                        <?php foreach ($combos['proveedores'] as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>" <?= $proveedorId === (int)$pr['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pr['razon_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filtro_busqueda">BUSCAR</label>
                    <input type="text" id="filtro_busqueda" placeholder="N° o proveedor..."
                           value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <div class="form-group" style="flex:0 0 130px;">
                    <label for="filtro_estado">ESTADO</label>
                    <select id="filtro_estado">
                        <option value="">Todos</option>
                        <option value="ACTIVO"  <?= $estado === 'ACTIVO'  ? 'selected' : '' ?>>Activos</option>
                        <option value="ANULADO" <?= $estado === 'ANULADO' ? 'selected' : '' ?>>Anulados</option>
                    </select>
                </div>
            </div>

            <div class="comp-actions">
                <div class="comp-actions-info">
                    <span id="pag_total"><?= count($pagos) ?></span> pago(s)
                </div>
                <div class="comp-actions-buttons">
                    <button type="button" id="btnExportarExcel" class="btn btn-outline">Exportar Excel</button>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:60px;">N°</th>
                        <th style="width:110px;">Fecha</th>
                        <th>Proveedor</th>
                        <th style="width:110px;text-align:center;">Compras</th>
                        <th style="text-align:right;width:130px;">Total</th>
                        <th style="width:110px;">Estado</th>
                        <th style="text-align:right;width:80px;">Ver</th>
                    </tr>
                </thead>
                <tbody id="pagosTableBody">
                    <?php include __DIR__ . '/layouts/pagos_row.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
     TAB 2: ESTADO DE COMPRAS
     ============================================================ -->
<div class="fac-tab-content" id="tab-compras">
    <div class="content-card">
        <div class="filter-section">
            <div class="cob-filters">
                <div class="form-group">
                    <label for="com_desde">DESDE</label>
                    <input type="date" id="com_desde" value="">
                </div>
                <div class="form-group">
                    <label for="com_hasta">HASTA</label>
                    <input type="date" id="com_hasta" value="">
                </div>
                <div class="form-group">
                    <label for="com_proveedor_filtro">PROVEEDOR</label>
                    <select id="com_proveedor_filtro">
                        <option value="0">Todos</option>
                        <?php foreach ($combos['proveedores'] as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>">
                                <?= htmlspecialchars($pr['razon_social']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="com_busqueda">BUSCAR</label>
                    <input type="text" id="com_busqueda" placeholder="N°, CAE o proveedor...">
                </div>
                <div class="form-group" style="flex:0 0 180px;">
                    <label for="com_estado_pago">ESTADO DE PAGO</label>
                    <select id="com_estado_pago">
                        <option value="POR_PAGAR" selected>Por pagar (pendientes + parciales)</option>
                        <option value="PENDIENTE">Solo pendientes (sin pagos)</option>
                        <option value="PARCIAL">Solo con pago parcial</option>
                        <option value="PAGADA">Pagadas</option>
                        <option value="ANULADA">Anuladas</option>
                        <option value="">Todas</option>
                    </select>
                </div>
            </div>

            <div class="comp-actions">
                <div class="comp-actions-info">
                    <span id="com_total"><?= count($compras) ?></span> compra(s)
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:100px;">Fecha</th>
                        <th>Proveedor</th>
                        <th>Comprobante</th>
                        <th style="text-align:right;width:110px;">Total</th>
                        <th style="text-align:right;width:110px;">Pagado</th>
                        <th style="text-align:right;width:110px;">Pendiente</th>
                        <th style="width:110px;">Estado pago</th>
                        <th style="text-align:right;width:90px;">Acción</th>
                    </tr>
                </thead>
                <tbody id="comprasTableBody">
                    <?php include __DIR__ . '/layouts/compras_estado_row.php'; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>