<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Factura';

$esActiva    = $factura['estado'] === 'ACTIVA';
$arcaAprobado = $factura['arca_estado'] === 'APROBADO';
$arcaPendiente = in_array($factura['arca_estado'], ['PENDIENTE', 'ERROR'], true);

$remito = ($factura['remito_prefijo'] ? $factura['remito_prefijo'] . ' ' : '')
        . str_pad((string)$factura['remito_punto_venta'], 4, '0', STR_PAD_LEFT) . '-'
        . str_pad((string)$factura['remito_numero'], 8, '0', STR_PAD_LEFT);

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Factura #<?= (int)$factura['id'] ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>facturas">Facturación</a> /
            Ver
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>facturas" class="btn btn-outline">← Volver</a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<?php if (!$esActiva): ?>
    <div class="msg-box msg-error" style="display:block;margin-bottom:16px;">
        <strong>Esta factura está ANULADA.</strong>
        <?php if ($factura['anulada_motivo']): ?>
            Motivo: <?= htmlspecialchars($factura['anulada_motivo']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- CABECERA -->
<div class="fac-header-card">
    <div class="fac-header-left">
        <div class="fac-header-emisor">
            <div class="fac-header-label">EMISOR</div>
            <div class="fac-header-nombre"><?= htmlspecialchars($factura['emisor_razon_social']) ?></div>
            <div class="fac-header-sub">CUIT <?= htmlspecialchars($factura['emisor_cuit']) ?></div>
        </div>
    </div>
    <div class="fac-header-center">
        <div class="fac-header-label">CLIENTE</div>
        <div class="fac-header-nombre"><?= htmlspecialchars($factura['cliente_razon_social']) ?></div>
        <?php if (!empty($factura['cliente_cuit'])): ?>
            <div class="fac-header-sub">CUIT <?= htmlspecialchars($factura['cliente_cuit']) ?></div>
        <?php endif; ?>
    </div>
    <div class="fac-header-right">
        <div class="fac-header-stat">
            <span class="fac-header-label">FECHA</span>
            <span class="fac-header-valor"><?= date('d/m/Y', strtotime($factura['fecha'])) ?></span>
        </div>
        <div class="fac-header-stat">
            <span class="fac-header-label">REMITO</span>
            <span class="fac-header-valor mono"><?= htmlspecialchars($remito) ?></span>
        </div>
        <div class="fac-header-stat">
            <span class="fac-header-label">TOTAL</span>
            <span class="fac-header-valor fac-header-total">$ <?= number_format((float)$factura['total'], 2, ',', '.') ?></span>
        </div>
    </div>
</div>

<!-- ESTADO ARCA -->
<div class="fac-arca-status fac-arca-<?= strtolower($factura['arca_estado']) ?>">
    <?php if ($arcaAprobado): ?>
        <strong>✓ Factura aprobada por ARCA</strong>
        <span>CAE <?= htmlspecialchars($factura['cae']) ?> · Vto. <?= $factura['cae_vencimiento'] ? date('d/m/Y', strtotime($factura['cae_vencimiento'])) : '—' ?></span>
    <?php elseif ($factura['arca_estado'] === 'ERROR'): ?>
        <strong>✗ Error al enviar a ARCA</strong>
        <span><?= htmlspecialchars($factura['arca_observaciones'] ?? '') ?></span>
    <?php else: ?>
        <strong>○ Pendiente de envío a ARCA</strong>
        <span>El stock ya fue descontado. Cuando quieras, presioná "Enviar a ARCA".</span>
    <?php endif; ?>
</div>

<!-- ACCIONES -->
<div class="fac-actions">
    <?php if ($arcaAprobado): ?>
        <a href="<?= BASE_URL ?>facturas/imprimir?id=<?= (int)$factura['id'] ?>" target="_blank" class="btn btn-primary">
            🖨 Imprimir Factura
        </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>facturas/remito-con-precio?id=<?= (int)$factura['id'] ?>" target="_blank" class="btn btn-outline">
        📄 Remito con precio
    </a>
    <a href="<?= BASE_URL ?>facturas/remito-sin-precio?id=<?= (int)$factura['id'] ?>" target="_blank" class="btn btn-outline">
        📄 Remito sin precio
    </a>

    <?php if ($esActiva && $arcaPendiente): ?>
        <button type="button" class="btn btn-primary" id="btnEnviarArca"
                data-id="<?= (int)$factura['id'] ?>">
            📡 Enviar a ARCA
        </button>
    <?php endif; ?>

    <?php if ($esActiva): ?>
        <button type="button" class="btn btn-outline" id="btnAnularFactura"
                data-id="<?= (int)$factura['id'] ?>"
                data-info="Factura #<?= (int)$factura['id'] ?> · $<?= number_format((float)$factura['total'], 2, ',', '.') ?>"
                style="color:#B23A48;border-color:rgba(178,58,72,.3);">
            ⊘ Anular factura
        </button>
    <?php endif; ?>
</div>

<!-- DETALLE -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Artículos facturados</h3>
    </div>
    <div class="card-body card-body-stack">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th style="text-align:center;width:110px;">Empaque</th>
                        <th style="text-align:right;width:80px;">Cant.</th>
                        <th style="text-align:right;width:80px;">Unid.</th>
                        <th style="text-align:right;width:120px;">Precio</th>
                        <th style="text-align:right;width:80px;">Desc. %</th>
                        <th style="text-align:right;width:130px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalle as $d): ?>
                        <?php $sinCargo = (int)$d['sin_precio'] === 1; ?>
                        <tr class="<?= $sinCargo ? 'fila-sin-cargo' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($d['articulo_nombre']) ?></strong>
                                <?php if ($sinCargo): ?>
                                    <span class="badge-sin-cargo">S/C</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;font-size:12px;"><?= htmlspecialchars($d['pakaging_desc'] ?? 'Unidad') ?></td>
                            <td style="text-align:right;"><?= number_format((float)$d['cantidad_pakaging'], 2, ',', '.') ?></td>
                            <td style="text-align:right;"><?= number_format((float)$d['cantidad_unidades'], 2, ',', '.') ?></td>
                            <td style="text-align:right;">
                                <?= $sinCargo ? '<em style="color:#0F5C52;">Sin cargo</em>' : '$ ' . number_format((float)$d['precio_unitario'], 2, ',', '.') ?>
                            </td>
                            <td style="text-align:right;"><?= $sinCargo ? '—' : number_format((float)$d['descuento_porcentaje'], 2, ',', '.') . '%' ?></td>
                            <td style="text-align:right;font-weight:600;">
                                <?= $sinCargo ? '—' : '$ ' . number_format((float)$d['subtotal_linea'], 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TOTALES -->
<div class="fac-totales">
    <table>
        <tr>
            <td>Subtotal</td>
            <td class="text-right">$ <?= number_format((float)$factura['subtotal'], 2, ',', '.') ?></td>
        </tr>
        <?php if ((float)$factura['descuento_general_porcentaje'] > 0): ?>
            <tr>
                <td>Descuento general (<?= number_format((float)$factura['descuento_general_porcentaje'], 2, ',', '.') ?>%)</td>
                <td class="text-right">—</td>
            </tr>
        <?php endif; ?>
        <?php if ((float)$factura['iva'] > 0): ?>
            <tr>
                <td>IVA</td>
                <td class="text-right">$ <?= number_format((float)$factura['iva'], 2, ',', '.') ?></td>
            </tr>
        <?php endif; ?>
        <?php if ((float)$factura['impuestos_internos'] > 0): ?>
            <tr>
                <td>Impuestos internos</td>
                <td class="text-right">$ <?= number_format((float)$factura['impuestos_internos'], 2, ',', '.') ?></td>
            </tr>
        <?php endif; ?>
        <tr class="fac-totales-total">
            <td>TOTAL</td>
            <td class="text-right">$ <?= number_format((float)$factura['total'], 2, ',', '.') ?></td>
        </tr>
    </table>
</div>

<?php if (!empty($factura['notas'])): ?>
    <div class="card" style="margin-top:20px;">
        <div class="card-header"><h3>Notas</h3></div>
        <div class="card-body card-body-stack">
            <p><?= nl2br(htmlspecialchars($factura['notas'])) ?></p>
        </div>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>