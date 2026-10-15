<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Libro IVA Compras';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Libro IVA Compras</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Libro IVA Compras
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>contabilidad/ayuda"
                data-ayuda-titulo="Ayuda — Libro IVA">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
    </div>
</div>

<?= flashRender() ?>

<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/libro-iva-compras">
    <div class="form-group">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" required>
    </div>
    <div class="form-group">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required>
    </div>
    <div class="form-group">
        <label for="emisor_id">Emisor</label>
        <select id="emisor_id" name="emisor_id">
            <option value="">Consolidado</option>
            <?php foreach ($emisores as $em): ?>
                <option value="<?= (int)$em['id'] ?>"
                    <?= ((int)($emisorId ?? 0) === (int)$em['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($em['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="acciones">
        <button type="submit" class="btn btn-primary">Aplicar</button>
        <a href="<?= BASE_URL ?>contabilidad/libro-iva-compras-csv?<?= http_build_query($_GET) ?>"
           class="btn btn-outline">Exportar CSV</a>
    </div>
</form>

<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Detalle de compras</h3>
            <p>
                Del <?= date('d/m/Y', strtotime($desde)) ?>
                al <?= date('d/m/Y', strtotime($hasta)) ?> —
                <?= count($filas) ?> comprobante(s)
            </p>
        </div>
    </div>
    <div class="cont-card-body">
        <?php if (empty($filas)): ?>
            <p style="text-align:center;color:var(--liqui-text-muted);padding:30px 0;">
                No hay comprobantes en el período.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="cont-tabla-reporte">
                    <thead>
                        <tr>
                            <th style="width:90px;">Fecha</th>
                            <th style="width:130px;">Comprobante</th>
                            <th>Razón Social</th>
                            <th style="width:110px;">CUIT</th>
                            <th style="width:130px;">Cond. IVA</th>
                            <th style="width:110px;">Provincia</th>
                            <th class="num" style="width:110px;">Neto Grav.</th>
                            <th class="num" style="width:90px;">No Grav.</th>
                            <th class="num" style="width:90px;">Exento</th>
                            <th class="num" style="width:100px;">IVA</th>
                            <th class="num" style="width:90px;">Perc. IVA</th>
                            <th class="num" style="width:100px;">Perc. IIBB</th>
                            <th class="num" style="width:110px;">Imp. Int.</th>
                            <th class="num" style="width:120px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($f['tipo_comprobante'] ?? '—') ?>
                                    <br><small class="mono" style="color:var(--liqui-text-muted);">
                                        <?= htmlspecialchars($f['numero'] ?? '') ?>
                                    </small>
                                </td>
                                <td><?= htmlspecialchars($f['razon_social']) ?></td>
                                <td class="mono" style="font-size:12px;"><?= htmlspecialchars($f['cuit'] ?? '') ?></td>
                                <td style="font-size:12.5px;"><?= htmlspecialchars($f['condicion_iva'] ?? '—') ?></td>
                                <td style="font-size:12.5px;"><?= htmlspecialchars($f['provincia'] ?? '—') ?></td>
                                <td class="num"><?= $f['neto_gravado'] > 0 ? '$ ' . number_format((float)$f['neto_gravado'], 2, ',', '.') : '' ?></td>
                                <td class="num"><?= $f['neto_no_gravado'] > 0 ? '$ ' . number_format((float)$f['neto_no_gravado'], 2, ',', '.') : '' ?></td>
                                <td class="num"><?= $f['neto_exento'] > 0 ? '$ ' . number_format((float)$f['neto_exento'], 2, ',', '.') : '' ?></td>
                                <td class="num"><?= $f['iva'] > 0 ? '$ ' . number_format((float)$f['iva'], 2, ',', '.') : '' ?></td>
                                <td class="num"><?= $f['perc_iva'] > 0 ? '$ ' . number_format((float)$f['perc_iva'], 2, ',', '.') : '' ?></td>
                                <td class="num"><?= $f['perc_iibb'] > 0 ? '$ ' . number_format((float)$f['perc_iibb'], 2, ',', '.') : '' ?></td>
                                <td class="num"><?= $f['impuestos_internos'] > 0 ? '$ ' . number_format((float)$f['impuestos_internos'], 2, ',', '.') : '' ?></td>
                                <td class="num" style="font-weight:700;">$ <?= number_format((float)$f['total'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fila-totales">
                            <td colspan="6" style="text-align:right;">
                                Totales (<?= $totales['cantidad'] ?>)
                            </td>
                            <td class="num">$ <?= number_format($totales['neto_gravado'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['neto_no_gravado'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['neto_exento'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['iva'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['perc_iva'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['perc_iibb'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['impuestos_internos'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totales['total'], 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>