<?php
if (strlen(session_id()) < 1) {
    session_start();
}
if (empty($_SESSION['liquicont_ok'])) {
    header('Location: ../login');
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Cliente.php';

if (!puedeAccederClientes()) {
    http_response_code(403);
    echo 'No tenés permiso para exportar este listado.';
    exit;
}
$user = currentUser();

$buscar = trim($_GET['buscar'] ?? '');
$estado = trim($_GET['estado'] ?? '');
$userId = ($user['rol'] === 'vendedor') ? $user['id'] : null;

$clienteModel = new Cliente();

$filas = $clienteModel->listarParaExportar($buscar, $estado, $userId);

$esHttps  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$appPath  = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$baseHref = ($esHttps ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $appPath;

?>
<!DOCTYPE html>
<html lang="es">
<head>
<base href="<?= htmlspecialchars($baseHref) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="dist/img/icon.png">
<title>Listado de Clientes — LiquiCont</title>
<link rel="stylesheet" href="dist/css/style.css">
<style>
    body { background: #fff; padding: 28px; }
    .reporte-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--liqui-gold);
        padding-bottom: 16px;
    }
    .reporte-header h1 { font-family: var(--font-heading); font-size: 22px; margin: 0 0 4px; }
    .reporte-header p { color: var(--liqui-text-muted); font-size: 13px; margin: 0; }
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
        <h1>LiquiCont Spirits — Listado de clientes</h1>
        <p>
            Generado el <?= date('d/m/Y H:i') ?>
            <?php if ($buscar !== ''): ?> · Búsqueda: "<?= htmlspecialchars($buscar) ?>"<?php endif; ?>
            <?php if ($estado !== ''): ?> · Estado: <?= $estado === 'ACTIVO' ? 'Activos' : 'Inactivos' ?><?php endif; ?>
            · <?= count($filas) ?> cliente(s)
        </p>
    </div>
    <div class="reporte-acciones">
        <button type="button" class="btn btn-outline btn-w-auto" onclick="window.print()">Imprimir / Guardar como PDF</button>
         <a href="<?= BASE_URL; ?>clientes" class="btn btn-outline">← Volver al listado</a>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Razón Social / Nombre</th>
                <th>Categoría</th>
                <th>Documento</th>
                <th>Ubicación</th>
                <th>Categoría IVA</th>
                <th>Vendedor</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$filas): ?>
                <tr><td colspan="7" class="empty-state">No hay artículos que matcheen ese criterio.</td></tr>
            <?php else: foreach ($filas as $c): ?>
                <tr>
                <td class="cell-title">
                    <div class="user-cell">
                        <div class="razon-social">
                            <div class="razon-social"><?= htmlspecialchars($c['razon_social']); ?></div>
                            <?php if ($c['nombre_fantasia']): ?><span class="fantasia"><?= $c['nombre_fantasia'] ?? ''; ?></span><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td data-label="Categoría"><?= htmlspecialchars($c['categoria_nombre'] ?? '—'); ?></td>
                <td data-label="Documento">CUIT: <?= htmlspecialchars($c['categoria_nombre'] ?? '—'); ?></td>
                <td data-label="Ubicación"><?= $c['localidad'] ?? '—'; ?></td>
                <td data-label="Categoría IVA"><?= $c['condicion_iva_nombre'] ?? '—'; ?></td>
                <td data-label="Vendedor"><?= $c['vendedor_nombre'] ?? 'Sin asignar'; ?></td>
                <td data-label="Estado"><?= $c['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></td>
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
