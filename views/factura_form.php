<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Factura';

$esDesdePedido = ($modo === 'desde_pedido');
$clienteId     = $pedido['cliente_id'] ?? 0;
$clienteNombre = $pedido['cliente_razon_social'] ?? '';
$clienteCatId  = $pedido['cliente_categoria_id'] ?? 0;
$clienteCat    = $pedido['cliente_categoria'] ?? '';
$pedidoId      = $pedido['id'] ?? 0;

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esDesdePedido ? 'Facturar pedido #' . (int)$pedidoId : 'Nueva factura (venta inmediata)' ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>facturas">Facturación</a> /
            <?= $esDesdePedido ? 'Facturar pedido' : 'Nueva' ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>facturas" class="btn btn-outline">← Volver al listado</a>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="facturaForm" autocomplete="off">
    <input type="hidden" name="pedido_id" value="<?= (int)$pedidoId ?>">

    <!-- BARRA SUPERIOR -->
    <div class="ped-topbar">
        <div class="ped-topbar-left">
            <div class="ped-topbar-label">CLIENTE</div>
            <div class="ped-topbar-cliente" id="pedClienteNombre">
                <?= $clienteNombre ?: 'Sin elegir' ?>
            </div>
        </div>
        <div class="ped-topbar-right">
            <div class="ped-topbar-stat">
                <span class="ped-topbar-label">SUBTOTAL</span>
                <span class="ped-topbar-valor" id="pedSubtotal">$ 0,00</span>
            </div>
            <div class="ped-topbar-stat">
                <span class="ped-topbar-label">IVA</span>
                <span class="ped-topbar-valor" id="pedIva">$ 0,00</span>
            </div>
            <div class="ped-topbar-stat">
                <span class="ped-topbar-label">TOTAL</span>
                <span class="ped-topbar-valor ped-topbar-total" id="pedTotal">$ 0,00</span>
            </div>
        </div>
    </div>

    <!-- DATOS -->
    <div class="card">
        <div class="card-header">
            <h3>Datos del comprobante</h3>
            <p>Completá los campos obligatorios (*).</p>
        </div>
        <div class="card-body ped-grid-3">

            <div class="form-group">
                <label for="emisor_id">Emisor que factura *</label>
                <select id="emisor_id" name="emisor_id" required>
                    <?php foreach ($emisores as $e): ?>
                        <option value="<?= (int)$e['id'] ?>">
                            <?= htmlspecialchars($e['nombre']) ?>
                            · <?= htmlspecialchars($e['razon_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint" style="margin-top:8px;">Determina qué certificado y punto de venta se usan.</div>
            </div>

            <?php if ($esDesdePedido): ?>
                <div class="form-group">
                    <label>Cliente</label>
                    <div class="fac-readonly">
                        <?= htmlspecialchars($clienteNombre) ?> · <?= htmlspecialchars($clienteCat) ?>
                    </div>
                    <input type="hidden" name="cliente_id" value="<?= (int)$clienteId ?>">
                </div>
                <div class="form-group">
                    <label>Pedido origen</label>
                    <div class="fac-readonly">#<?= (int)$pedidoId ?></div>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="cliente_id">Cliente *</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($combos['clientes'] as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"
                                    data-categoria-id="<?= (int)$cl['categoria'] ?>"
                                    data-categoria="<?= htmlspecialchars($cl['categoria_desc'] ?? '') ?>">
                                <?= htmlspecialchars($cl['razon_social']) ?>
                                · <?= htmlspecialchars($cl['categoria_desc'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="categoria_override">Categoría (override)</label>
                    <select id="categoria_override" name="categoria_override">
                        <option value="">— Usar la del cliente —</option>
                        <?php foreach ($combos['categorias'] as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>">
                                <?= htmlspecialchars($cat['descripcion']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" style="margin-top:8px;">Podés forzar otra lista de precios.</div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="fecha">Fecha *</label>
                <input type="date" id="fecha" value="<?= date('Y-m-d') ?>" disabled>
            </div>

            <div class="form-group">
                <label for="descuento_general_porcentaje">Descuento general (%)</label>
                <input type="number" id="descuento_general_porcentaje" name="descuento_general_porcentaje"
                       step="0.01" min="0" max="100"
                       value="<?= number_format((float)($pedido['descuento_general_porcentaje'] ?? 0), 2, '.', '') ?>">
            </div>

        </div>
    </div>

    <!-- ARTÍCULOS -->
    <div class="card">
        <div class="card-header">
            <h3>Artículos</h3>
            <?php if ($esDesdePedido): ?>
                <p>Los artículos vienen del pedido. Podés ajustar cantidades o quitarlos antes de facturar.</p>
            <?php else: ?>
                <p>Elegí el artículo: el precio se completa según la categoría del cliente.</p>
            <?php endif; ?>
        </div>
        <div class="card-body card-body-stack">

            <?php if (!$esDesdePedido): ?>
                <div class="ped-add-grid" style="margin-bottom:14px;">
                    <div class="form-group">
                        <label for="add_articulo">Artículo *</label>
                        <select id="add_articulo">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($combos['articulos'] as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($a['nombre']) ?>"
                                        data-codigo="<?= htmlspecialchars($a['codigo'] ?? '') ?>"
                                        data-pakaging-id="<?= (int)($a['pakaging_id'] ?? 0) ?>"
                                        data-pakaging-desc="<?= htmlspecialchars($a['pakaging_desc'] ?? '') ?>"
                                        data-pakaging-factor="<?= (float)($a['pakaging_factor'] ?? 1) ?>">
                                    <?= htmlspecialchars($a['nombre']) ?>
                                    <?= !empty($a['pakaging_desc']) ? ' · ' . htmlspecialchars($a['pakaging_desc']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 90px;">
                        <label for="add_cantidad">Cantidad *</label>
                        <input type="number" id="add_cantidad" value="1" step="1" min="1">
                    </div>
                    <div class="form-group" style="flex:0 0 130px;">
                        <label for="add_pakaging">Empaque</label>
                        <select id="add_pakaging">
                            <option value="">Unidad</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 120px;">
                        <label for="add_precio">Precio *</label>
                        <input type="number" id="add_precio" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group" style="flex:0 0 80px;">
                        <label for="add_desc">Desc. %</label>
                        <input type="number" id="add_desc" step="0.01" min="0" max="100" value="0">
                    </div>
                    <div class="form-group" style="flex:0 0 130px;">
                        <label for="add_condicion_iva">Cond. IVA</label>
                        <select id="add_condicion_iva">
                            <option value="GRAVADO">Gravado</option>
                            <option value="NO_GRAVADO">No gravado</option>
                            <option value="EXENTO">Exento</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 100px;">
                        <label for="add_alicuota">Alíc. %</label>
                        <select id="add_alicuota">
                            <option value="21">21%</option>
                            <option value="10.5">10,5%</option>
                            <option value="27">27%</option>
                            <option value="0">0%</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <label class="ped-check-sin-cargo">
                            <input type="checkbox" id="add_sin_cargo" value="1">
                            Sin cargo
                        </label>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-primary" id="btnAgregarArticulo">+ Agregar</button>
                    </div>
                </div>
                <div id="facAvisoPrecio" class="ped-aviso" style="display:none;"></div>
            <?php endif; ?>

            <div class="table-wrap" style="margin-top:16px;">
                <table class="data-table" id="tablaDetalle">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th style="width:90px;text-align:center;">Empaque</th>
                            <th style="width:70px;text-align:right;">Cant.</th>
                            <th style="width:70px;text-align:right;">Unid.</th>
                            <th style="width:100px;text-align:right;">Precio</th>
                            <th style="width:70px;text-align:right;">Desc. %</th>
                            <th style="width:110px;text-align:right;">Subtotal</th>
                            <th style="width:90px;">Cond. IVA</th>
                            <th style="width:70px;text-align:right;">Alíc. %</th>
                            <th style="width:100px;text-align:right;">IVA</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="detalleBody">
                        <tr class="fila-vacia">
                            <td colspan="11" style="text-align:center;color:#7A7266;padding:1.5rem;">
                                Todavía no hay artículos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PERCEPCIONES -->
    <div class="card">
        <div class="card-header">
            <h3>Percepciones e impuestos</h3>
            <p>Cargá manualmente si la factura incluye percepciones de IVA, IIBB o impuestos internos.</p>
        </div>
        <div class="card-body ped-grid-3">
            <div class="form-group">
                <label for="perc_iva">Percepción IVA</label>
                <input type="number" id="perc_iva" name="perc_iva" step="0.01" min="0" value="0">
            </div>
            <div class="form-group">
                <label for="perc_iibb">Percepción IIBB</label>
                <input type="number" id="perc_iibb" name="perc_iibb" step="0.01" min="0" value="0">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <div class="fac-resumen-fiscal">
                    <div><span>Neto gravado:</span> <strong id="facNetoGrav">$ 0,00</strong></div>
                    <div><span>IVA:</span> <strong id="facIva">$ 0,00</strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTAS -->
    <div class="card">
        <div class="card-header">
            <h3>Notas</h3>
            <p>Observaciones internas o para el cliente.</p>
        </div>
        <div class="card-body card-body-stack">
            <div class="form-group">
                <textarea id="notas" name="notas" rows="3" maxlength="1000"
                          placeholder="Observaciones..."><?= htmlspecialchars($pedido['notas'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>facturas" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            Guardar factura (sin CAE)
        </button>
    </div>
</form>

<?php if ($esDesdePedido): ?>
<script>
window.FACTURA_DETALLE_INICIAL = <?= json_encode(array_map(function($d) {
    return [
        'articulo_id'          => (int)$d['articulo_id'],
        'articulo_nombre'      => $d['articulo_nombre'],
        'articulo_codigo'      => $d['articulo_codigo'],
        'pakaging_id'          => !empty($d['pakaging_id']) ? (int)$d['pakaging_id'] : null,
        'pakaging_desc'        => $d['pakaging_desc'] ?? 'Unidad',
        'cantidad_pakaging'    => (float)$d['cantidad_pakaging'],
        'cantidad_unidades'    => (float)$d['cantidad_unidades'],
        'precio_unitario'      => (float)$d['precio_unitario'],
        'descuento_porcentaje' => (float)$d['descuento_porcentaje'],
        'sin_precio'           => (int)($d['sin_precio'] ?? 0),
        'condicion_iva'        => 'GRAVADO',
        'alicuota_iva'         => 21.00,
    ];
}, $detalle), JSON_UNESCAPED_UNICODE) ?>;
window.FACTURA_CLIENTE_ID = <?= (int)$clienteId ?>;
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>