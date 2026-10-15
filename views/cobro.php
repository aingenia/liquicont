<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Cobros';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Cobros</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Cobros</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>cobros/ayuda"
                data-ayuda-titulo="Ayuda — Cobros">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>cobros/crear" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Nuevo cobro
        </a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

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
                <label for="filtro_cliente">CLIENTE</label>
                <select id="filtro_cliente">
                    <option value="0">Todos</option>
                    <?php foreach ($combos['clientes'] as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>" <?= $clienteId === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filtro_busqueda">BUSCAR</label>
                <input type="text" id="filtro_busqueda" placeholder="N° o cliente..."
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
                <span id="cob_total"><?= count($cobros) ?></span> cobro(s)
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
                    <th>Cliente</th>
                    <th style="width:110px;text-align:center;">Facturas</th>
                    <th style="text-align:right;width:130px;">Total</th>
                    <th style="width:110px;">Estado</th>
                    <th style="text-align:right;width:80px;">Ver</th>
                </tr>
            </thead>
            <tbody id="cobrosTableBody">
                <?php include __DIR__ . '/layouts/cobros_row.php'; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>