<?php
/**
 * views/pedido_print.php
 * Variable: $sinPrecios (bool)
 */
$logoPath = ROOT_PATH . 'dist' . DS . 'img' . DS . 'logo-liquicont-small.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedido #<?= (int)$pedido['id'] ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/print.css">
    <style>
        .ped-print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .ped-print-table th,
        .ped-print-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 11px;
        }
        .ped-print-table thead th {
            background: #14110E;
            color: #F1F1F1;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: .5px;
        }
        .ped-print-info {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #14110E;
        }
        .ped-print-info > div { flex: 1; font-size: 11px; }
        .ped-print-info strong { color: #14110E; }
        .text-right { text-align: right; }
        .totales-box {
            margin-top: 20px;
            float: right;
            width: 320px;
            font-size: 12px;
        }
        .totales-box table { width: 100%; }
        .totales-box td { padding: 6px 8px; }
        .totales-box tr:last-child td {
            font-size: 15px;
            font-weight: 800;
            border-top: 2px solid #14110E;
            padding-top: 10px;
        }
        .badge-estado {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #14110E;
            color: #E4C077;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .clearfix::after { content: ''; display: table; clear: both; }
    </style>
</head>
<body class="print-page">

<div class="print-toolbar no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
    <button type="button" class="btn btn-outline" onclick="window.close()">Cerrar</button>
</div>

<header class="print-header">
    <div class="print-brand">
        <?php if ($logoBase64): ?>
            <img src="<?= $logoBase64 ?>" alt="LiquiCont" class="print-logo">
        <?php endif; ?>
        <div>
            <h1><?= APP_NAME ?></h1>
            <p>Orden de Pedido <?= $sinPrecios ? '(sin precios)' : '' ?></p>
        </div>
    </div>
    <div class="print-meta">
        <p><strong>Pedido:</strong> #<?= (int)$pedido['id'] ?></p>
        <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($pedido['fecha_pedido'])) ?></p>
        <p><strong>Estado:</strong> <span class="badge-estado"><?= estadoPedidoLabel($pedido['estado']) ?></span></p>
    </div>
</header>

<div class="ped-print-info">
    <div>
        <strong>Cliente:</strong><br>
        <?= htmlspecialchars($pedido['cliente_razon_social']) ?><br>
        <?php if (!empty($pedido['cliente_cuit'])): ?>CUIT: <?= htmlspecialchars($pedido['cliente_cuit']) ?><br><?php endif; ?>
        <?php if (!empty($pedido['cliente_domicilio_entrega'])): ?>
            <?= htmlspecialchars($pedido['cliente_domicilio_entrega']) ?><br>
        <?php endif; ?>
        <?php if (!empty($pedido['cliente_localidad'])): ?>
            <?= htmlspecialchars($pedido['cliente_localidad']) ?>
        <?php endif; ?>
    </div>
    <div class="text-right">
        <strong>Categoría:</strong> <?= htmlspecialchars($pedido['cliente_categoria'] ?? '—') ?><br>
        <strong>Creado por:</strong> <?= htmlspecialchars($pedido['creado_por_nombre'] ?? '—') ?><br>
        <?php if (!empty($pedido['fecha_preparacion'])): ?>
            <strong>Preparación:</strong> <?= date('d/m/Y', strtotime($pedido['fecha_preparacion'])) ?>
        <?php endif; ?>
    </div>
</div>

<table class="ped-print-table">
    <thead>
        <tr>
            <th style="width:60px;">Código</th>
            <th>Artículo</th>
            <th style="width:100px;text-align:center;">Empaque</th>
            <th style="width:70px;text-align:right;">Cant.</th>
            <th style="width:70px;text-align:right;">Unid.</th>
            <?php if (!$sinPrecios): ?>
                <th style="width:90px;text-align:right;">Precio</th>
                <th style="width:70px;text-align:right;">Desc.</th>
                <th style="width:100px;text-align:right;">Subtotal</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($detalle)): ?>
            <tr><td colspan="<?= $sinPrecios ? 5 : 8 ?>" style="text-align:center;">Sin artículos.</td></tr>
        <?php else: ?>
            <?php foreach ($detalle as $d): ?>
            <?php
                $sinCargo = (int)($d['sin_precio'] ?? 0) === 1;
                $sub = $sinCargo ? 0 : (float)$d['cantidad_unidades'] * (float)$d['precio_unitario']
                    * (1 - (float)$d['descuento_porcentaje'] / 100);
            ?>
            <tr<?= $sinCargo ? ' style="background:#F0F7F4;"' : '' ?>>
                <td><?= htmlspecialchars($d['articulo_codigo'] ?? '—') ?></td>
                <td>
                    <?= htmlspecialchars($d['articulo_nombre']) ?>
                    <?php if ($sinCargo): ?>
                        <strong style="color:#0F5C52;font-size:10px;margin-left:6px;">(SIN CARGO)</strong>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;"><?= htmlspecialchars($d['pakaging_desc'] ?? 'Unidad') ?></td>
                <td class="text-right"><?= number_format((float)$d['cantidad_pakaging'], 2, ',', '.') ?></td>
                <td class="text-right"><?= number_format((float)$d['cantidad_unidades'], 2, ',', '.') ?></td>
                <?php if (!$sinPrecios): ?>
                    <?php if ($sinCargo): ?>
                        <td colspan="3" style="text-align:center;color:#0F5C52;font-style:italic;">
                            Sin cargo — no se factura
                        </td>
                    <?php else: ?>
                        <td class="text-right">$ <?= number_format((float)$d['precio_unitario'], 2, ',', '.') ?></td>
                        <td class="text-right"><?= number_format((float)$d['descuento_porcentaje'], 2, ',', '.') ?>%</td>
                        <td class="text-right">$ <?= number_format($sub, 2, ',', '.') ?></td>
                    <?php endif; ?>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="clearfix">
    <?php if (!$sinPrecios): ?>
        <div class="totales-box">
            <table>
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">$ <?= number_format((float)$pedido['subtotal'], 2, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Descuento general</td>
                    <td class="text-right"><?= number_format((float)$pedido['descuento_general_porcentaje'], 2, ',', '.') ?>%</td>
                </tr>
                <tr>
                    <td><strong>TOTAL</strong></td>
                    <td class="text-right"><strong>$ <?= number_format((float)$pedido['total'], 2, ',', '.') ?></strong></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($pedido['notas'])): ?>
    <div style="clear:both; margin-top:30px; padding:14px; background:#F1F1F1; border-radius:6px; font-size:11px;">
        <strong>Notas:</strong><br>
        <?= nl2br(htmlspecialchars($pedido['notas'])) ?>
    </div>
<?php endif; ?>

<?php if (!$sinPrecios && !empty($pedido['notas_internas']) && !esVendedor()): ?>
    <div style="clear:both; margin-top:14px; padding:14px; background:#FFF8E1; border-radius:6px; font-size:11px; border-left:3px solid #C9973A;">
        <strong>Notas internas:</strong><br>
        <?= nl2br(htmlspecialchars($pedido['notas_internas'])) ?>
    </div>
<?php endif; ?>

<footer class="print-footer" style="clear:both; margin-top:30px;">
    <p><?= APP_NAME ?> — Sistema de gestión</p>
</footer>

</body>
</html>