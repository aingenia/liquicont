<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Insumos';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Insumos</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Insumos</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>insumos/ayuda"
                data-ayuda-titulo="Ayuda — Insumos">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <?php if (puedeEditarInsumos()): ?>
            <a href="<?= BASE_URL ?>insumos/crear" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Nuevo insumo
            </a>
        <?php endif; ?>
        </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<div class="content-card">
    <div class="filter-section">
        <div class="filters-wrapper">
            <div class="form-group">
                <label for="search">NOMBRE / CÓDIGO</label>
                <input type="text" id="search" name="search"
                       value="<?= htmlspecialchars($search ?? '') ?>"
                       placeholder="Ingresá nombre o código...">
            </div>
            <div class="form-group" style="flex: 0 0 180px;">
                <label for="status">ESTADO</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <option value="ACTIVO"   <?= ($status ?? '') === 'ACTIVO'   ? 'selected' : '' ?>>Activos</option>
                    <option value="INACTIVO" <?= ($status ?? '') === 'INACTIVO' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>

            <button type="button" id="btnExportarExcel" class="btn btn-outline">Exportar Excel</button>
            <button type="button" id="btnImprimir" class="btn btn-outline">Imprimir</button>
            <button type="button" id="btnPdf" class="btn btn-outline">Descargar PDF</button>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="tablaInsumos">
            <thead>
                <tr>
                    <th>Nombre / Código</th>
                    <th>Unidad</th>
                    <th>Medida</th>
                    <th>Stock</th>
                    <th>Pto. pedido</th>
                    <th>Costo</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody id="insumosTableBody">
                <?php include __DIR__ . '/layouts/insumos_row.php'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>