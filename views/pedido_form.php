<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Pedido';

$esVer     = ($modo === 'ver');
$esEdicion = ($modo === 'editar');
$p         = $pedido ?? [];
$sinPrecios = esProductor();

$val = fn($k, $def = '') => htmlspecialchars((string)($p[$k] ?? $def));

$bloqueado = $esVer || (isset($puedeEditar) && !$puedeEditar);
$modificado = !empty($p['modificado_por_vendedor_el']);

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esVer ? 'Pedido #' . (int)$p['id'] : ($esEdicion ? 'Editar pedido #' . (int)$p['id'] : 'Nuevo pedido') ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>pedidos">Pedidos</a> /
            <?= $esVer ? 'Ver' : ($esEdicion ? 'Editar' : 'Nuevo') ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>pedidos/ayuda"
                data-ayuda-titulo="Cómo cargar un pedido">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>pedidos" class="btn btn-outline">← Volver al listado</a>
    </div>
</div>

<?php if ($modificado && !$esVer): ?>
    <div class="msg-box msg-info" style="display:block;margin-bottom:16px;">
        <strong>Modificado por el vendedor:</strong>
        <?= date('d/m/Y H:i', strtotime($p['modificado_por_vendedor_el'])) ?>
    </div>
<?php endif; ?>

<?php if ($bloqueado && !$esVer): ?>
    <div class="msg-box msg-info" style="display:block;margin-bottom:16px;">
        Este pedido está en estado <strong><?= estadoPedidoLabel($p['estado']) ?></strong>. Solo lectura.
    </div>
<?php endif; ?>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<!-- BARRA SUPERIOR NEGRA -->
<div class="ped-topbar">
    <div class="ped-topbar-left">
        <div class="ped-topbar-label">CLIENTE</div>
        <div class="ped-topbar-cliente" id="pedClienteNombre">
            <?= !empty($p['cliente_razon_social']) ? htmlspecialchars($p['cliente_razon_social']) : 'Sin elegir' ?>
        </div>
    </div>
    <div class="ped-topbar-right">
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">SUBTOTAL</span>
            <span class="ped-topbar-valor" id="pedSubtotal">$ 0,00</span>
        </div>
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">DESC. GRAL</span>
            <span class="ped-topbar-valor" id="pedDescGral">0%</span>
        </div>
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">TOTAL</span>
            <span class="ped-topbar-valor ped-topbar-total" id="pedTotal">$ 0,00</span>
        </div>
    </div>
</div>

<form id="pedidoForm" autocomplete="off" data-modo="<?= $modo ?>" data-sin-precios="<?= $sinPrecios ? '1' : '0' ?>">
    <input type="hidden" name="id" value="<?= $esEdicion || $esVer ? (int)$p['id'] : '' ?>">

    <!-- DATOS DEL PEDIDO -->
    <div class="card">
        <div class="card-header">
            <h3>Datos del pedido</h3>
            <p>Completá los campos obligatorios (*).</p>
        </div>
        <div class="card-body ped-grid-3">
            <div class="form-group">
                <label for="cliente_id">Cliente *</label>
                <select id="cliente_id" name="cliente_id" required <?= $bloqueado ? 'disabled' : '' ?>>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($combos['clientes'] as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>" <?= (int)($p['cliente_id'] ?? 0) === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['razon_social']) ?>
                            · <?= htmlspecialchars($cl['categoria_desc'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($bloqueado): ?>
                    <input type="hidden" name="cliente_id" value="<?= (int)($p['cliente_id'] ?? 0) ?>">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="estado_display">Estado</label>
                <?php if ($modo === 'crear' || $esVer || $bloqueado): ?>
                    <div class="ped-estado-view <?= estadoPedidoClase($p['estado'] ?? 'RECIBIDO') ?>">
                        <?= estadoPedidoLabel($p['estado'] ?? 'RECIBIDO') ?>
                    </div>
                <?php else: ?>
                    <select id="estado_display">
                        <?php foreach (['RECIBIDO','PROCESANDO','PREPARADO','FACTURADO','ENTREGADO'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($p['estado'] ?? '') === $est ? 'selected' : '' ?>>
                                <?= estadoPedidoLabel($est) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" style="margin-top: 8px;">El cambio de estado se aplica al guardar.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="fecha_pedido">Fecha del pedido *</label>
                <input type="date" id="fecha_pedido" name="fecha_pedido" required
                       value="<?= $val('fecha_pedido', date('Y-m-d')) ?>" <?= $bloqueado ? 'disabled' : '' ?>>
            </div>

            <?php if (!empty($p['fecha_preparacion'])): ?>
                <div class="form-group">
                    <label>Fecha de preparación</label>
                    <input type="text" value="<?= date('d/m/Y', strtotime($p['fecha_preparacion'])) ?>" disabled>
                </div>
            <?php endif; ?>
            <?php if (!empty($p['fecha_facturacion'])): ?>
                <div class="form-group">
                    <label>Fecha de facturación</label>
                    <input type="text" value="<?= date('d/m/Y', strtotime($p['fecha_facturacion'])) ?>" disabled>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ARTÍCULOS -->
    <div class="card">
        <div class="card-header">
            <h3>Artículos</h3>
            <p>Elegí el artículo: el precio se completa según la categoría del cliente, pero podés ajustarlo.</p>
        </div>
        <div class="card-body card-body-stack">

            <?php if (!$bloqueado): ?>
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="buscar_articulo">Buscar artículo</label>
                    <input type="text" id="buscar_articulo" placeholder="Escribí para filtrar el selector de abajo...">
                </div>

                <div class="ped-add-grid">
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
                    <div class="form-group" style="flex:0 0 100px;">
                        <label for="add_cantidad">Cantidad *</label>
                        <input type="number" id="add_cantidad" value="1" step="1" min="1">
                    </div>
                    <div class="form-group" style="flex:0 0 150px;">
                        <label for="add_pakaging">Empaque</label>
                        <select id="add_pakaging">
                            <option value="">Unidad</option>
                            <!-- Se puebla dinámicamente según el artículo elegido -->
                        </select>
                    </div>
                    <?php if (!$sinPrecios): ?>
                        <div class="form-group" style="flex:0 0 130px;">
                            <label for="add_precio">Precio *</label>
                            <input type="number" id="add_precio" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group" style="flex:0 0 90px;">
                            <label for="add_desc">Desc. %</label>
                            <input type="number" id="add_desc" step="0.01" min="0" max="100" value="0">
                        </div>
                        <div class="form-group" style="flex:0 0 auto;">
                            <label>&nbsp;</label>
                            <label class="ped-check-sin-cargo">
                                <input type="checkbox" id="add_sin_cargo" value="1">
                                Sin cargo
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-primary" id="btnAgregarArticulo">+ Agregar</button>
                    </div>
                </div>
                <div id="pedAvisoPrecio" class="ped-aviso" style="display:none;"></div>
            <?php endif; ?>

            <div class="table-wrap" style="margin-top:16px;">
                <table class="data-table" id="tablaDetalle">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th style="width:100px;text-align:center;">Empaque</th>
                            <th style="width:90px;text-align:right;">Cant.</th>
                            <th style="width:90px;text-align:right;">Unid.</th>
                            <?php if (!$sinPrecios): ?>
                                <th style="width:110px;text-align:right;">Precio</th>
                                <th style="width:80px;text-align:right;">Desc. %</th>
                                <th style="width:120px;text-align:right;">Subtotal</th>
                            <?php endif; ?>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="detalleBody">
                        <tr class="fila-vacia">
                            <td colspan="<?= $sinPrecios ? 5 : 8 ?>" style="text-align:center;color:#7A7266;padding:1.5rem;">
                                Todavía no hay artículos en este pedido.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DESCUENTO Y NOTAS -->
    <div class="card">
        <div class="card-header">
            <h3>Descuento general y notas</h3>
            <p>El descuento general se aplica sobre el neto del pedido (después de los descuentos por línea).</p>
        </div>
        <div class="card-body ped-grid-2">
            <?php if (!$sinPrecios): ?>
                <div class="form-group">
                    <label for="descuento_general_porcentaje">Descuento general (%)</label>
                    <input type="number" id="descuento_general_porcentaje" name="descuento_general_porcentaje"
                           step="0.01" min="0" max="100"
                           value="<?= $val('descuento_general_porcentaje', '0') ?>"
                           <?= $bloqueado ? 'disabled' : '' ?>>
                </div>
            <?php endif; ?>

            <div class="form-group field-full">
                <label for="notas">Notas (visibles para todos)</label>
                <textarea id="notas" name="notas" rows="2" maxlength="1000"
                          placeholder="Aclaraciones o recordatorios para todos..."
                          <?= $bloqueado ? 'disabled' : '' ?>><?= $val('notas') ?></textarea>
            </div>

            <?php if (!esVendedor()): ?>
                <div class="form-group field-full">
                    <label for="notas_internas">Notas internas <span style="font-weight:400;text-transform:none;letter-spacing:0;">(solo admin/dev/productor)</span></label>
                    <textarea id="notas_internas" name="notas_internas" rows="2" maxlength="1000"
                              placeholder="Observaciones internas..."
                              <?= $bloqueado ? 'disabled' : '' ?>><?= $val('notas_internas') ?></textarea>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACCIONES -->
    <div class="form-actions">
        <a href="<?= BASE_URL ?>pedidos" class="btn btn-outline"><?= $esVer ? 'Volver' : 'Cancelar' ?></a>

        <?php if ($esVer || $esEdicion): ?>
            <?php if (!$sinPrecios): ?>
                <a href="<?= BASE_URL ?>pedidos/imprimir?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-outline">
                    Imprimir con precio
                </a>
            <?php endif; ?>
            <?php if (esAdmin() || esProductor()): ?>
                <a href="<?= BASE_URL ?>pedidos/imprimir?id=<?= (int)$p['id'] ?>&sin_precio=1" target="_blank" class="btn btn-outline">
                    Imprimir sin precio
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$bloqueado): ?>
            <button type="submit" class="btn btn-primary">
                <?= $esEdicion ? 'Guardar cambios' : 'Guardar pedido' ?>
            </button>
        <?php endif; ?>
    </div>
</form>
<?php if ($esEdicion || $esVer): ?>
<script>
window.PEDIDO_DETALLE_INICIAL = <?= json_encode(array_map(function($d) {
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
    ];
}, $detalle ?? []), JSON_UNESCAPED_UNICODE) ?>;
window.PEDIDO_MODO = '<?= $esVer ? 'ver' : 'editar' ?>';
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>