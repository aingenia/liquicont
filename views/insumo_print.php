<?php
$logoPath = ROOT_PATH . 'dist' . DS . 'img' . DS . 'logo-krown-small.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Insumos — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/print.css">
</head>
<body class="print-page">

<div class="print-toolbar no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
    <button type="button" class="btn btn-outline" onclick="window.close()">Cerrar</button>
</div>

<header class="print-header">
    <div class="print-brand">
        <img src="<?= BASE_URL ?>dist/img/logo-krown-small.png" alt="Krown" class="print-logo">
        <div>
            <h1><?= APP_NAME ?></h1>
            <p>Listado de Insumos</p>
        </div>
    </div>
    <div class="print-meta">
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
        <?php if (!empty($filtros)): ?>
            <p><strong>Filtros:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></p>
        <?php endif; ?>
        <p><strong>Total:</strong> <?= count($insumos) ?> insumo(s)</p>
    </div>
</header>

<table class="print-table">
    <thead>
        <tr>
            <th style="width:40px;">ID</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Unidad</th>
            <th>Stock</th>
            <th>Pto. pedido</th>
            <th>Costo</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($insumos)): ?>
        <tr><td colspan="8" style="text-align:center;padding:20px;">No se encontraron insumos.</td></tr>
    <?php else: ?>
        <?php foreach ($insumos as $i): ?>
            <tr>
                <td><?= (int)$i['id'] ?></td>
                <td><?= htmlspecialchars($i['nombre']) ?></td>
                <td><?= htmlspecialchars($i['codigo'] ?? '—') ?></td>
                <td><?= htmlspecialchars($i['unidad_abrev'] ?? '—') ?></td>
                <td><?= number_format((float)$i['stock'], 3, ',', '.') ?></td>
                <td><?= number_format((float)$i['ppedido'], 3, ',', '.') ?></td>
                <td>$ <?= number_format((float)$i['costo'], 2, ',', '.') ?></td>
                <td><?= $i['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<footer class="print-footer">
    <p><?= APP_NAME ?> — Sistema de gestión</p>
</footer>

</body>
</html>