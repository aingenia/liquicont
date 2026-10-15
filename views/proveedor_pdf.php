<?php
/**
 * views/proveedor_pdf.php
 * Variables: $proveedores, $filtros
 */
$logoPath = ROOT_PATH . 'dist' . DS . 'img' . DS . 'logo-krown-small.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proveedores — <?= APP_NAME ?></title>
    <style>
        @page { margin: 100px 30px 60px 30px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #211B14; }

        .header { position: fixed; top: -80px; left: 0; right: 0; height: 70px; border-bottom: 2px solid #14110E; }
        .header table { width: 100%; }
        .header td { vertical-align: middle; }
        .header .logo { height: 36px; }
        .header .brand { font-size: 15px; font-weight: bold; letter-spacing: 2px; color: #14110E; padding-left: 10px; }
        .header .brand-sub { font-size: 9px; color: #7A7266; letter-spacing: 1px; }
        .header .meta { text-align: right; font-size: 9px; color: #7A7266; line-height: 1.5; }

        .footer { position: fixed; bottom: -40px; left: 0; right: 0; height: 28px; border-top: 1px solid #cbd5e1; text-align: center; font-size: 8px; color: #7A7266; padding-top: 5px; }
        .footer .page:after { content: counter(page); }

        h1 { font-size: 14px; margin: 0 0 4px; color: #14110E; }
        .subtitle { font-size: 9px; color: #7A7266; margin: 0 0 12px; }

        .filters { background: #F1F1F1; border: 1px solid #cbd5e1; padding: 6px 8px; margin-bottom: 12px; font-size: 9px; }
        .filters strong { color: #14110E; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data thead th { background: #14110E; color: #F1F1F1; padding: 6px 5px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .3px; border: 1px solid #14110E; }
        table.data td { padding: 5px; border: 1px solid #cbd5e1; font-size: 9px; vertical-align: middle; }
        table.data tbody tr:nth-child(even) { background: #fafafa; }

        .badge-active   { color: #0F5C52; font-weight: bold; }
        .badge-inactive { color: #B23A48; font-weight: bold; }

        .text-center { text-align: center; }
        .text-muted  { color: #7A7266; }
    </style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td style="width:60%;">
                <table>
                    <tr>
                        <?php if ($logoBase64): ?>
                            <td style="width:44px;"><img src="<?= $logoBase64 ?>" class="logo" alt="Krown"></td>
                        <?php endif; ?>
                        <td>
                            <div class="brand"><?= APP_NAME ?></div>
                            <div class="brand-sub">LISTADO DE PROVEEDORES</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="meta" style="width:40%;">
                <div>Fecha: <?= date('d/m/Y H:i') ?></div>
                <div>Total: <?= count($proveedores) ?> proveedor(es)</div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    <?= APP_NAME ?> — Sistema de gestión · Página <span class="page"></span>
</div>

<h1>Listado de Proveedores</h1>
<p class="subtitle">Generado el <?= date('d/m/Y \a \l\a\s H:i') ?></p>

<?php if (!empty($filtros)): ?>
    <div class="filters"><strong>Filtros aplicados:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></div>
<?php endif; ?>

<table class="data">
    <thead>
        <tr>
            <th style="width:30px;">ID</th>
            <th>Razón social</th>
            <th style="width:90px;">Documento</th>
            <th style="width:90px;">IVA</th>
            <th style="width:100px;">IIBB</th>
            <th style="width:100px;">Ubicación</th>
            <th style="width:60px;">Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($proveedores)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No se encontraron proveedores.</td></tr>
    <?php else: ?>
        <?php foreach ($proveedores as $p): ?>
            <tr>
                <td><?= (int)$p['id'] ?></td>
                <td>
                    <?= htmlspecialchars($p['razon_social']) ?>
                    <?php if (!empty($p['nombre_fantasia']) && $p['nombre_fantasia'] !== $p['razon_social']): ?>
                        <br><span class="text-muted" style="font-size:8px;"><?= htmlspecialchars($p['nombre_fantasia']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(($p['tipo_doc_desc'] ?? '') . ' ' . ($p['cuit'] ?? '')) ?></td>
                <td><?= htmlspecialchars($p['condicion_iva_desc'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['condicion_iibb_desc'] ?? '—') ?></td>
                <td><?= htmlspecialchars($p['localidad'] ?? '—') ?></td>
                <td class="<?= $p['estado'] === 'ACTIVO' ? 'badge-active' : 'badge-inactive' ?>">
                    <?= $p['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

</body>
</html>