<?php
if (strlen(session_id()) < 1) {
    session_start();
}
if (empty($_SESSION['krown_ok'])) {
    header('Location: ../login');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Articulo.php';

if (!tieneAccesoCompletoArticulos()) {
    header('Location: ../articulos');
    exit;
}

$buscar = trim($_GET['buscar'] ?? '');
$estado = trim($_GET['estado'] ?? '');

$articuloModel = new Articulo();
$filas = $articuloModel->listarParaExportar($buscar, $estado);

$esHttps  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$appPath  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$baseHref = ($esHttps ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $appPath;

function margenTexto(float $costo, float $precio): string
{
    if ($costo <= 0 || $precio <= 0) {
        return '—';
    }
    return number_format((($precio - $costo) / $costo) * 100, 1, ',', '.') . '%';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<base href="<?= htmlspecialchars($baseHref) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="dist/img/icon.png">
<title>Listado de artículos — Krown</title>
<link rel="stylesheet" href="dist/css/style.css">
<style>
    body { background: #fff; padding: 28px; }
    .reporte-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--krown-gold);
        padding-bottom: 16px;
    }
    .reporte-header h1 { font-family: var(--font-heading); font-size: 22px; margin: 0 0 4px; }
    .reporte-header p { color: var(--krown-text-muted); font-size: 13px; margin: 0; }
    .reporte-acciones { display: flex; gap: 10px; }
    @media print {
        .reporte-acciones { display: none; }
        body { padding: 0; }
    }
</style>
</head>
<body>

<div class="reporte-header">
    <div>
        <h1>Krown Spirits — Listado de artículos</h1>
        <p>
            Generado el <?= date('d/m/Y H:i') ?>
            <?php if ($buscar !== ''): ?> · Búsqueda: "<?= htmlspecialchars($buscar) ?>"<?php endif; ?>
            <?php if ($estado !== ''): ?> · Estado: <?= $estado === 'ACTIVO' ? 'Activos' : 'Inactivos' ?><?php endif; ?>
            · <?= count($filas) ?> artículo(s)
        </p>
    </div>
    <div class="reporte-acciones">
        <button type="button" class="btn btn-outline btn-w-auto" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Código</th>
                <th>Costo</th>
                <th>Precio</th>
                <th>Margen</th>
                <th>Stock</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$filas): ?>
                <tr><td colspan="7" class="empty-state">No hay artículos que matcheen ese criterio.</td></tr>
            <?php else: foreach ($filas as $a): ?>
                <tr>
                    <td class="cell-title">
                        <div class="razon-social"><?= htmlspecialchars($a['nombre']) ?></div>
                        <?php if ($a['descripcion']): ?><div class="subdato"><?= htmlspecialchars($a['descripcion']) ?></div><?php endif; ?>
                    </td>
                    <td data-label="Código"><?= htmlspecialchars($a['codigo'] ?: '—') ?></td>
                    <td data-label="Costo">$ <?= number_format((float) $a['costo'], 2, ',', '.') ?></td>
                    <td data-label="Precio">$ <?= number_format((float) $a['precio'], 2, ',', '.') ?></td>
                    <td data-label="Margen"><?= margenTexto((float) $a['costo'], (float) $a['precio']) ?></td>
                    <td data-label="Stock"><?= (int) $a['stock'] ?></td>
                    <td data-label="Estado"><?= $a['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
    window.onload = function () { window.print(); };
</script>

</body>
</html>
