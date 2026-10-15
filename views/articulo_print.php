<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Artículos — <?= APP_NAME ?></title>
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
        <div><h1><?= APP_NAME ?></h1><p>Listado de Artículos</p></div>
    </div>
    <div class="print-meta">
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
        <?php if (!empty($filtros)): ?>
            <p><strong>Filtros:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></p>
        <?php endif; ?>
        <p><strong>Total:</strong> <?= count($articulos) ?> artículo(s)</p>
    </div>
</header>
<table class="print-table">
    <thead>
        <tr>
            <th style="width:40px;">ID</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Costo</th>
            <th>Stock</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($articulos)): ?>
        <tr><td colspan="6" style="text-align:center;padding:20px;">No se encontraron artículos.</td></tr>
    <?php else: ?>
        <?php foreach ($articulos as $a): ?>
            <tr>
                <td><?= (int)$a['id'] ?></td>
                <td><?= htmlspecialchars($a['nombre']) ?></td>
                <td><?= htmlspecialchars($a['codigo'] ?? '—') ?></td>
                <td>$ <?= number_format((float)$a['costo'], 2, ',', '.') ?></td>
                <td><?= (int)$a['stock'] ?></td>
                <td><?= $a['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
<footer class="print-footer"><p><?= APP_NAME ?> — Sistema de gestión</p></footer>
</body>
</html>