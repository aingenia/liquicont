<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Libro Diario';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

// Agrupar por asiento_id
$grupos = [];
foreach ($filas as $f) {
    $grupos[$f['asiento_id']][] = $f;
}
$totD = 0; $totH = 0;
?>

<div class="page-header">
    <div>
        <h2>Libro Diario</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Libro Diario
        </div>
    </div>
</div>

<?= flashRender() ?>

<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/libro-diario">
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
    </div>
</form>

<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Movimientos del período</h3>
            <p>
                Del <?= date('d/m/Y', strtotime($desde)) ?>
                al <?= date('d/m/Y', strtotime($hasta)) ?> —
                <?= count($grupos) ?> asiento(s)
            </p>
        </div>
    </div>
    <div class="cont-card-body">
        <?php if (empty($grupos)): ?>
            <p style="text-align:center;color:var(--liqui-text-muted);padding:30px 0;">
                No hay movimientos en el período.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="cont-tabla-reporte">
                    <thead>
                        <tr>
                            <th style="width:80px;">N°</th>
                            <th style="width:100px;">Fecha</th>
                            <th style="width:120px;">Código</th>
                            <th>Cuenta / Detalle</th>
                            <th class="num" style="width:130px;">Debe</th>
                            <th class="num" style="width:130px;">Haber</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grupos as $asId => $lns):
                            $cab = $lns[0];
                            $sumD = 0; $sumH = 0;
                            foreach ($lns as $l) { $sumD += (float)$l['debe']; $sumH += (float)$l['haber']; }
                            $totD += $sumD; $totH += $sumH;
                        ?>
                            <tr class="fila-asiento">
                                <td class="mono"><?= str_pad((string)$cab['numero'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td><?= date('d/m/Y', strtotime($cab['fecha'])) ?></td>
                                <td colspan="4">
                                    <strong><?= htmlspecialchars($cab['asiento_desc']) ?></strong>
                                    <span style="color:var(--liqui-text-muted);font-weight:400;margin-left:8px;">
                                        · <?= htmlspecialchars($cab['emisor_nombre']) ?>
                                        · <?= htmlspecialchars($cab['tipo']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php foreach ($lns as $l): ?>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td class="codigo"><?= htmlspecialchars($l['cuenta_codigo']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($l['cuenta_nombre']) ?>
                                        <?php if (!empty($l['linea_detalle'])): ?>
                                            <span style="color:var(--liqui-text-muted);">
                                                — <?= htmlspecialchars($l['linea_detalle']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <?= (float)$l['debe'] > 0 ? '$ ' . number_format((float)$l['debe'], 2, ',', '.') : '' ?>
                                    </td>
                                    <td class="num">
                                        <?= (float)$l['haber'] > 0 ? '$ ' . number_format((float)$l['haber'], 2, ',', '.') : '' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fila-totales">
                            <td colspan="4" style="text-align:right;">Totales del período</td>
                            <td class="num">$ <?= number_format($totD, 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($totH, 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>