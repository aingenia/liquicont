<?php
/**
 * views/cliente_pdf.php
 * Variables: $clientes, $filtros
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
    <title>Clientes — <?= APP_NAME ?></title>
    <style>
        @page { margin: 100px 30px 60px 30px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #211B14;
        }

        .header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 70px;
            border-bottom: 2px solid #14110E;
        }
        .header table { width: 100%; }
        .header td { vertical-align: middle; }
        .header .logo { height: 36px; }
        .header .brand {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #14110E;
            padding-left: 10px;
        }
        .header .brand-sub {
            font-size: 9px;
            color: #7A7266;
            letter-spacing: 1px;
        }
        .header .meta {
            text-align: right;
            font-size: 9px;
            color: #7A7266;
            line-height: 1.5;
        }

        .footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 28px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 8px;
            color: #7A7266;
            padding-top: 5px;
        }
        .footer .page:after { content: counter(page); }

        h1 { font-size: 14px; margin: 0 0 4px; color: #14110E; }
        .subtitle { font-size: 9px; color: #7A7266; margin: 0 0 12px; }

        .filters {
            background: #F1F1F1;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            margin-bottom: 12px;
            font-size: 9px;
        }
        .filters strong { color: #14110E; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data thead th {
            background: #14110E;
            color: #F1F1F1;
            padding: 6px 5px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .3px;
            border: 1px solid #14110E;
        }
        table.data td {
            padding: 5px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
            vertical-align: middle;
        }
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
                            <div class="brand-sub">LISTADO DE CLIENTES</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="meta" style="width:40%;">
                <div>Fecha: <?= date('d/m/Y H:i') ?></div>
                <div>Total: <?= count($clientes) ?> cliente(s)</div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    <?= APP_NAME ?> — Sistema de gestión · Página <span class="page"></span>
</div>

<h1>Listado de Clientes</h1>
<p class="subtitle">Generado el <?= date('d/m/Y \a \l\a\s H:i') ?></p>

<?php if (!empty($filtros)): ?>
    <div class="filters"><strong>Filtros aplicados:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?></div>
<?php endif; ?>

<table class="data">
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
        <tr><td colspan="8" class="text-center text-muted" style="padding:20px;">No se encontraron comprobantes.</td></tr>
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

</body>
</html>