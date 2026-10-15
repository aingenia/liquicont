<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Proveedores';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Proveedores</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Proveedores</div>
    </div>
    <a href="<?= BASE_URL ?>proveedores/crear" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Agregar proveedor
    </a>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<div class="content-card">
    <div class="filter-section">
        <div class="filters-wrapper">
            <div class="form-group">
                <label for="search">RAZÓN SOCIAL / CÓDIGO / CUIT</label>
                <input type="text" id="search" name="search"
                       value="<?= htmlspecialchars($search ?? '') ?>"
                       placeholder="Ingresá nombre, código o CUIT...">
            </div>
            <div class="form-group" style="flex: 0 0 180px;">
                <label for="status">ESTADO</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <option value="ACTIVO"   <?= ($status ?? '') === 'ACTIVO'   ? 'selected' : '' ?>>Activos</option>
                    <option value="INACTIVO" <?= ($status ?? '') === 'INACTIVO' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>

            <button type="button" id="btnExportarExcel" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M8 13h8M8 17h8M10 9h4"/>
                </svg>
                Exportar Excel
            </button>
            <button type="button" id="btnImprimir" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Imprimir
            </button>
            <button type="button" id="btnPdf" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M9 15h6M9 11h2M9 19h6"/>
                </svg>
                Descargar PDF
            </button>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="tablaProveedores">
            <thead>
                <tr>
                    <th>Razón Social / Nombre</th>
                    <th>Documento</th>
                    <th>Ubicación</th>
                    <th>Categoría IVA</th>
                    <th>Estado</th>
                    <th style="text-align:right;">Acciones</th>
                </tr>
            </thead>
            <tbody id="proveedoresTableBody">
                <?php include __DIR__ . '/layouts/proveedores_row.php'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>