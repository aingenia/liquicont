<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobantes — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/print.css">
</head>
<body class="print-page">
<div class="print-toolbar no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
    <button type="button" class="btn btn-outline" onclick="window.close()">Cerrar</button>
</div>
<header class="print-header">
    <div class="print-brand">
        <img src="<?= BASE_URL ?>dist/img/logo-liquicont-small.png" alt="LiquiCont" class="print-logo">
        <div><h1><?= APP_NAME ?></h1><p>Comprobantes de compra</p></div>
    </div>
    <div class="print-meta">
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
        <?php if (!empty($filtros)): ?>
            <p><strong>Filtros:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></p>
        <?php endif; ?>
        <p><strong>Total:</strong> <?= count($compras) ?> comprobante(s)</p>
    </div>
</header>
<table class="print-table">
    <thead>
        <tr>
            <th style="width:80px;">Fecha</th>
            <th>Proveedor</th>
            <th>Comprobante</th>
            <th>CAE</th>
            <th style="text-align:right;">Total</th>
            <th style="width:80px;">Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($compras)): ?>
        <tr><td colspan="6" style="text-align:center;padding:20px;">No hay comprobantes en el período.</td></tr>
    <?php else: ?>
        <?php foreach ($compras as $c): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($c['fecha_comprobante'])) ?></td>
                <td><?= htmlspecialchars($c['proveedor_razon_social']) ?></td>
                <td>
                    <?= htmlspecialchars($c['tipo_comprobante_desc'] ?? '—') ?>
                    <?php if ($c['punto_venta'] || $c['numero']): ?>
                        <br><small><?= htmlspecialchars($c['punto_venta'] ?? '') ?>-<?= htmlspecialchars($c['numero'] ?? '') ?></small>
                    <?php endif; ?>
                </td>
                <td style="font-family:monospace;font-size:10px;"><?= htmlspecialchars($c['cae'] ?? '—') ?></td>
                <td style="text-align:right;">$ <?= number_format((float)$c['total'], 2, ',', '.') ?></td>
                <td><?= $c['estado'] === 'ANULADA' ? 'Anulada' : 'Registrada' ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
<footer class="print-footer"><p><?= APP_NAME ?> — Sistema de gestión</p></footer>
</body>
</html>