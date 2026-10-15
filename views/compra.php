<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Comprobantes de compra';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Comprobantes de compra</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> / Compras / Comprobantes
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>comprobantes/ayuda"
                data-ayuda-titulo="Ayuda — Comprobantes de compra">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>comprobantes/crear" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo comprobante
        </a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<div class="content-card">

    <div class="filter-section">
        <div class="comp-filters">
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
                    <?php foreach ($combos['proveedores'] as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $proveedorId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filtro_busqueda">NÚMERO / CAE</label>
                <input type="text" id="filtro_busqueda" placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <div class="form-group">
                <label for="filtro_estado">ESTADO</label>
                <select id="filtro_estado">
                    <option value="">Todos</option>
                    <option value="REGISTRADA" <?= $estado === 'REGISTRADA' ? 'selected' : '' ?>>Registradas</option>
                    <option value="ANULADA"    <?= $estado === 'ANULADA'    ? 'selected' : '' ?>>Anuladas</option>
                </select>
            </div>
        </div>

        <div class="comp-actions">
            <div class="comp-actions-info">
                Mostrando comprobantes entre <strong id="rangoInfo"><?= htmlspecialchars($desde) ?></strong> y <strong id="rangoInfo2"><?= htmlspecialchars($hasta) ?></strong>.
            </div>
            <div class="comp-actions-buttons">
                <button type="button" id="btnExportarExcel" class="btn btn-outline">Exportar Excel</button>
                <button type="button" id="btnImprimir" class="btn btn-outline">Imprimir</button>
                <button type="button" id="btnPdf" class="btn btn-outline">Descargar PDF</button>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="tablaCompras">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Comprobante</th>
                    <th>CAE</th>
                    <th style="text-align:right;">Total</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody id="comprasTableBody">
                <?php include __DIR__ . '/layouts/compras_row.php'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>