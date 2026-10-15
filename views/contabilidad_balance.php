<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Balance de Sumas y Saldos';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$t = $data['totales'];
$balancea = abs($t['sumas_debe'] - $t['sumas_haber']) < 0.01;
?>

<div class="page-header">
    <div>
        <h2>Balance de Sumas y Saldos</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Balance
        </div>
    </div>
</div>

<?= flashRender() ?>

<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/balance">
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

<?php if (!$balancea): ?>
    <div class="msg-box msg-error" style="margin-bottom:16px;">
        <strong>Atención:</strong> las sumas del Debe y del Haber no coinciden.
        Diferencia: $ <?= number_format(abs($t['sumas_debe'] - $t['sumas_haber']), 2, ',', '.') ?>
    </div>
<?php endif; ?>

<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Movimiento por cuenta</h3>
            <p>
                Del <?= date('d/m/Y', strtotime($desde)) ?>
                al <?= date('d/m/Y', strtotime($hasta)) ?>
            </p>
        </div>
    </div>
    <div class="cont-card-body">
        <?php if (empty($data['filas'])): ?>
            <p style="text-align:center;color:var(--krown-text-muted);padding:30px 0;">
                No hay movimientos en el período.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="cont-tabla-reporte">
                    <thead>
                        <tr>
                            <th style="width:100px;">Código</th>
                            <th>Cuenta</th>
                            <th class="num" style="width:130px;">Sumas Debe</th>
                            <th class="num" style="width:130px;">Sumas Haber</th>
                            <th class="num" style="width:130px;">Saldo Deudor</th>
                            <th class="num" style="width:130px;">Saldo Acreedor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['filas'] as $f): ?>
                            <tr>
                                <td class="codigo"><?= htmlspecialchars($f['codigo']) ?></td>
                                <td><?= htmlspecialchars($f['nombre']) ?></td>
                                <td class="num">
                                    <?= $f['sumas_debe'] > 0 ? '$ ' . number_format($f['sumas_debe'], 2, ',', '.') : '' ?>
                                </td>
                                <td class="num">
                                    <?= $f['sumas_haber'] > 0 ? '$ ' . number_format($f['sumas_haber'], 2, ',', '.') : '' ?>
                                </td>
                                <td class="num" style="font-weight:700;">
                                    <?= $f['saldo_deudor'] > 0 ? '$ ' . number_format($f['saldo_deudor'], 2, ',', '.') : '' ?>
                                </td>
                                <td class="num" style="font-weight:700;">
                                    <?= $f['saldo_acreedor'] > 0 ? '$ ' . number_format($f['saldo_acreedor'], 2, ',', '.') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fila-totales">
                            <td colspan="2" style="text-align:right;">Totales</td>
                            <td class="num">$ <?= number_format($t['sumas_debe'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['sumas_haber'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['saldo_deudor'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['saldo_acreedor'], 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>