<?php
/**
 * views/proveedor_print.php
 * Variables: $proveedores, $filtros
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proveedores — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/print.css">
</head>
<body class="print-page">

<div class="print-toolbar no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Imprimir / Guardar PDF
    </button>
    <button type="button" class="btn btn-outline" onclick="window.close()">Cerrar</button>
</div>

<header class="print-header">
    <div class="print-brand">
        <img src="<?= BASE_URL ?>dist/img/logo-liquicont-small.png" alt="LiquiCont" class="print-logo">
        <div>
            <h1><?= APP_NAME ?></h1>
            <p>Listado de Proveedores</p>
        </div>
    </div>
    <div class="print-meta">
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
        <?php if (!empty($filtros)): ?>
            <p><strong>Filtros:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></p>
        <?php endif; ?>
        <p><strong>Total:</strong> <?= count($proveedores) ?> proveedor(es)</p>
    </div>
</header>

<table class="print-table">
    <thead>
        <tr>
            <th style="width:40px;">ID</th>
            <th>Razón social</th>
            <th>Documento</th>
            <th>IVA</th>
            <th>IIBB</th>
            <th>Ubicación</th>
            <th style="width:70px;">Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($proveedores)): ?>
        <tr><td colspan="7" style="text-align:center; padding:20px;">No se encontraron proveedores.</td></tr>
    <?php else: ?>
        <?php foreach ($proveedores as $p): ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td>
                    <?= htmlspecialchars($p['razon_social']) ?>
                    <?php if (!empty($p['nombre_fantasia']) && $p['nombre_fantasia'] !== $p['razon_social']): ?>
                        <br><small style="color:#7A7266;"><?= htmlspecialchars($p['nombre_fantasia']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(($p['tipo_doc_desc'] ?? '') . ' ' . ($p['cuit'] ?? '')) ?></td>
                <td><?= htmlspecialchars($p['condicion_iva_desc'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['condicion_iibb_desc'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['localidad'] ?? '—') ?></td>
                <td><?= $p['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></td>
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