<?php
/**
 * views/usuario_print.php
 * Vista standalone (sin layout) para impresión / guardar como PDF.
 * Variables: $usuarios, $filtros
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/print.css">
</head>
<body class="print-page">

<!-- Barra superior: NO se imprime -->
<div class="print-toolbar no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Imprimir / Guardar PDF
    </button>
    <button type="button" class="btn btn-outline" onclick="window.close()">
        Cerrar
    </button>
</div>

<!-- Encabezado del reporte -->
<header class="print-header">
    <div class="print-brand">
        <img src="<?= BASE_URL ?>dist/img/logo-liquicont-small.png" alt="LiquiCont" class="print-logo">
        <div>
            <h1><?= APP_NAME ?></h1>
            <p>Listado de Usuarios</p>
        </div>
    </div>

    <div class="print-meta">
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i') ?></p>
        <?php if (!empty($filtros)): ?>
            <p><strong>Filtros:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></p>
        <?php endif; ?>
        <p><strong>Total:</strong> <?= count($usuarios) ?> usuario(s)</p>
    </div>
</header>

<!-- Tabla -->
<table class="print-table">
    <thead>
        <tr>
            <th style="width:50px;">ID</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th style="width:80px;">Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($usuarios)): ?>
        <tr>
            <td colspan="6" style="text-align:center; padding:20px;">
                No se encontraron usuarios.
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['nombre']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['tel'] ?? '—') ?></td>
                <td><?= htmlspecialchars($u['nombre_rol']) ?></td>
                <td><?= (int)$u['estado'] === 1 ? 'Activo' : 'Inactivo' ?></td>
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