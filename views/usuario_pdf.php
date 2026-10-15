<?php
/**
 * views/usuario_pdf.php
 * Vista para Dompdf — usa tablas y CSS básico.
 * Variables: $usuarios, $filtros
 */

// Logo como base64 embebido — más confiable que URL en Dompdf
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
    <title>Usuarios — <?= APP_NAME ?></title>
    <style>
        @page { margin: 100px 40px 60px 40px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #211B14;
        }

        /* ---------- ENCABEZADO FIJO ---------- */
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
        .header .logo { height: 40px; }
        .header .brand {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #14110E;
            padding-left: 12px;
        }
        .header .brand-sub {
            font-size: 10px;
            color: #7A7266;
            letter-spacing: 1px;
        }
        .header .meta {
            text-align: right;
            font-size: 10px;
            color: #7A7266;
            line-height: 1.5;
        }

        /* ---------- PIE FIJO ---------- */
        .footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 30px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 9px;
            color: #7A7266;
            padding-top: 6px;
        }
        .footer .page:after {
            content: counter(page);
        }

        /* ---------- CONTENIDO ---------- */
        h1 {
            font-size: 15px;
            margin: 0 0 4px;
            color: #14110E;
        }
        .subtitle {
            font-size: 10px;
            color: #7A7266;
            margin: 0 0 14px;
        }

        .filters {
            background: #F1F1F1;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            margin-bottom: 14px;
            font-size: 10px;
        }
        .filters strong { color: #14110E; }

        /* ---------- TABLA ---------- */
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data thead th {
            background: #14110E;
            color: #F1F1F1;
            padding: 7px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
            border: 1px solid #14110E;
        }
        table.data td {
            padding: 6px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
            vertical-align: middle;
        }
        table.data tbody tr:nth-child(even) { background: #fafafa; }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-muted  { color: #7A7266; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-active   { color: #0F5C52; }
        .badge-inactive { color: #B23A48; }
    </style>
</head>
<body>

<!-- ENCABEZADO (se repite en cada página) -->
<div class="header">
    <table>
        <tr>
            <td style="width:60%;">
                <table>
                    <tr>
                        <?php if ($logoBase64): ?>
                            <td style="width:50px;">
                                <img src="<?= $logoBase64 ?>" class="logo" alt="Krown">
                            </td>
                        <?php endif; ?>
                        <td>
                            <div class="brand"><?= APP_NAME ?></div>
                            <div class="brand-sub">LISTADO DE USUARIOS</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="meta" style="width:40%;">
                <div>Fecha: <?= date('d/m/Y H:i') ?></div>
                <div>Total: <?= count($usuarios) ?> usuario(s)</div>
            </td>
        </tr>
    </table>
</div>

<!-- PIE (se repite en cada página) -->
<div class="footer">
    <?= APP_NAME ?> — Sistema de gestión · Página <span class="page"></span>
</div>

<!-- CONTENIDO -->
<h1>Listado de Usuarios</h1>
<p class="subtitle">Generado el <?= date('d/m/Y \a \l\a\s H:i') ?></p>

<?php if (!empty($filtros)): ?>
    <div class="filters">
        <strong>Filtros aplicados:</strong> <?= htmlspecialchars(implode(' — ', $filtros)) ?>
    </div>
<?php endif; ?>

<table class="data">
    <thead>
        <tr>
            <th style="width:40px;">ID</th>
            <th>Nombre</th>
            <th>Email</th>
            <th style="width:110px;">Teléfono</th>
            <th style="width:110px;">Rol</th>
            <th style="width:70px;">Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($usuarios)): ?>
        <tr>
            <td colspan="6" class="text-center text-muted" style="padding:20px;">
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
                <td>
                    <span class="badge <?= (int)$u['estado'] === 1 ? 'badge-active' : 'badge-inactive' ?>">
                        <?= (int)$u['estado'] === 1 ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

</body>
</html>