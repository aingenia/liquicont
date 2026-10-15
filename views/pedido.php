<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Pedidos';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Pedidos</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Pedidos</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>pedidos/ayuda"
                data-ayuda-titulo="Ayuda — Pedidos">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <?php if (!esProductor()): ?>
            <a href="<?= BASE_URL ?>pedidos/crear" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Nuevo pedido
            </a>
        <?php endif; ?>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<div class="content-card">
    <div class="filter-section">
        <div class="ped-filters">
            <div class="form-group">
                <label for="filtro_search">CLIENTE / N° PEDIDO</label>
                <input type="text" id="filtro_search" placeholder="Buscar..." value="<?= htmlspecialchars($search ?? '') ?>">
            </div>
            <div class="form-group" style="flex:0 0 160px;">
                <label for="filtro_estado">ESTADO</label>
                <select id="filtro_estado">
                    <option value="">Todos</option>
                    <?php foreach (['RECIBIDO','PROCESANDO','PREPARADO','FACTURADO','ENTREGADO','ANULADO'] as $est): ?>
                        <option value="<?= $est ?>" <?= ($estado ?? '') === $est ? 'selected' : '' ?>>
                            <?= estadoPedidoLabel($est) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0 0 140px;">
                <label for="filtro_desde">DESDE</label>
                <input type="date" id="filtro_desde" value="<?= htmlspecialchars($desde ?? '') ?>">
            </div>
            <div class="form-group" style="flex:0 0 140px;">
                <label for="filtro_hasta">HASTA</label>
                <input type="date" id="filtro_hasta" value="<?= htmlspecialchars($hasta ?? '') ?>">
            </div>
        </div>

        <div class="comp-actions">
            <div class="comp-actions-info">
                <span id="totalPedidos"><?= count($pedidos) ?></span> pedido(s) encontrado(s).
            </div>
            <div class="comp-actions-buttons">
                <button type="button" id="btnExportarExcel" class="btn btn-outline">Exportar Excel</button>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="tablaPedidos">
            <thead>
                <tr>
                    <th style="width:70px;">N°</th>
                    <th>Cliente</th>
                    <th style="width:110px;">Fecha</th>
                    <th style="width:90px;">Items</th>
                    <?php if (!$sinPrecios): ?>
                        <th style="text-align:right;width:110px;">Total</th>
                    <?php endif; ?>
                    <th style="width:120px;">Estado</th>
                    <th style="text-align:right;width:140px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="pedidosTableBody">
                <?php include __DIR__ . '/layouts/pedidos_row.php'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>