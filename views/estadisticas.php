<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Estadísticas';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Estadísticas</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Estadísticas</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>estadisticas/ayuda"
                data-ayuda-titulo="Ayuda — Estadísticas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
    </div>
</div>

<!-- ============================================================
     FILTROS GLOBALES
     ============================================================ -->
<div class="est-filtros">
    <div class="form-group">
        <label for="est_desde">DESDE</label>
        <input type="date" id="est_desde" value="<?= date('Y-01-01') ?>">
    </div>
    <div class="form-group">
        <label for="est_hasta">HASTA</label>
        <input type="date" id="est_hasta" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="est-filtros-acciones">
        <button type="button" class="btn btn-outline btn-sm" id="btnEstRangoActual">Año actual</button>
        <button type="button" class="btn btn-outline btn-sm" id="btnEstUltimos30">Últimos 30 días</button>
        <button type="button" class="btn btn-outline btn-sm" id="btnEstAnioAnterior">Año anterior</button>
    </div>
</div>

<!-- ============================================================
     TABS
     ============================================================ -->
<div class="fac-tabs">
    <button type="button" class="fac-tab active" data-tab="facturacion">
        Facturación por emisor
    </button>
    <button type="button" class="fac-tab" data-tab="ventas">
        Ventas mes vs mes
    </button>
    <button type="button" class="fac-tab" data-tab="formatos">
        Formatos
    </button>
    <button type="button" class="fac-tab" data-tab="clientes">
        Cajas por cliente
    </button>
    <button type="button" class="fac-tab" data-tab="sabores">
        Share de sabores
    </button>
</div>

<!-- ============================================================
     TAB 1: FACTURACIÓN POR EMISOR
     ============================================================ -->
<div class="fac-tab-content active" id="tab-facturacion">
    <div class="est-card">
        <div class="est-card-header">
            <div>
                <h3>Facturación por emisor</h3>
                <p>Total facturado en el período, agrupado por razón social.</p>
            </div>
            <div class="est-card-total">
                <span class="est-card-total-label">TOTAL</span>
                <span class="est-card-total-valor" id="estFacTotal">$ 0,00</span>
            </div>
        </div>
        <div class="est-card-body">
            <div id="chartFacturacion"></div>
        </div>
    </div>
</div>

<!-- ============================================================
     TAB 2: VENTAS MES vs MES
     ============================================================ -->
<div class="fac-tab-content" id="tab-ventas">
    <div class="est-card">
        <div class="est-card-header">
            <div>
                <h3>Ventas en cajas — mes vs mes (año a año)</h3>
                <p>Comparación por año de las cajas equivalentes vendidas cada mes.</p>
            </div>
            <div class="est-card-actions">
                <div class="est-anios" id="estAniosSelector"></div>
            </div>
        </div>
        <div class="est-card-body">
            <div id="chartVentas"></div>
        </div>
    </div>
</div>

<!-- ============================================================
     TAB 3: FORMATOS
     ============================================================ -->
<div class="fac-tab-content" id="tab-formatos">
    <div class="est-card">
        <div class="est-card-header">
            <div>
                <h3>Formatos — cajas equivalentes</h3>
                <p>Distribución de cajas por formato en el período.</p>
            </div>
        </div>
        <div class="est-card-body">
            <div class="est-grid-2">
                <div id="chartFormatos"></div>
                <div id="chartFormatosPct"></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     TAB 4: CAJAS POR CLIENTE
     ============================================================ -->
<div class="fac-tab-content" id="tab-clientes">
    <div class="est-card">
        <div class="est-card-header">
            <div>
                <h3>Cajas por cliente</h3>
                <p>Top clientes por cajas equivalentes en el período.</p>
            </div>
            <div class="est-card-actions">
                <label for="est_clientes_limite" style="font-size:12px;color:var(--krown-text-muted);margin-right:6px;">Top</label>
                <select id="est_clientes_limite" class="est-select-sm">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <div class="est-card-body">
            <div id="chartClientes"></div>
        </div>
    </div>
</div>

<!-- ============================================================
     TAB 5: SHARE DE SABORES
     ============================================================ -->
<div class="fac-tab-content" id="tab-sabores">
    <div class="est-card">
        <div class="est-card-header">
            <div>
                <h3>Share de sabores</h3>
                <p>Distribución de unidades por sabor. Podés filtrar por formato o ver el total.</p>
            </div>
            <div class="est-card-actions">
                <label for="est_sabores_formato" style="font-size:12px;color:var(--krown-text-muted);margin-right:6px;">Formato</label>
                <select id="est_sabores_formato" class="est-select-sm">
                    <option value="">Todos</option>
                    <option value="200">200 cc</option>
                    <option value="750">750 cc</option>
                </select>
            </div>
        </div>
        <div class="est-card-body">
            <div class="est-grid-2">
                <div id="chartSabores"></div>
                <div id="tablaSabores" class="est-tabla-sabores"></div>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar-style: carga ApexCharts y el JS del módulo -->
<link rel="stylesheet" href="<?= BASE_URL ?>dist/css/apexcharts.css">
<script src="<?= BASE_URL ?>dist/js/apexcharts.min.js"></script>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>