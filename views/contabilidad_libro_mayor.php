<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Libro Mayor';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Libro Mayor</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Libro Mayor
        </div>
    </div>
</div>

<?= flashRender() ?>

<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/libro-mayor">
    <div class="form-group">
        <label for="cuenta_id">Cuenta</label>
        <select id="cuenta_id" name="cuenta_id" required>
            <option value="">— Seleccionar cuenta —</option>
            <?php foreach ($cuentas as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                    <?= ((int)($cuentaId ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['codigo'] . ' · ' . $c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
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
        <button type="submit" class="btn btn-primary">Ver mayor</button>
    </div>
</form>

<?php if (!$cuenta): ?>
    <div class="cont-card">
        <div class="cont-card-body" style="text-align:center;padding:50px 24px;color:var(--krown-text-muted);">
            Seleccioná una cuenta para ver su movimiento.
        </div>
    </div>
<?php else: ?>
    <div class="cont-card">
        <div class="cont-card-header">
            <div>
                <h3><?= htmlspecialchars($cuenta['codigo'] . ' · ' . $cuenta['nombre']) ?></h3>
                <p>
                    Del <?= date('d/m/Y', strtotime($desde)) ?>
                    al <?= date('d/m/Y', strtotime($hasta)) ?>
                </p>
            </div>
        </div>
        <div class="cont-card-body">
            <div class="cont-mayor-saldo-inicial">
                <span>Saldo inicial al <?= date('d/m/Y', strtotime($desde)) ?></span>
                <strong>$ <?= number_format($data['saldo_inicial'], 2, ',', '.') ?></strong>
            </div>

            <?php if (empty($data['movimientos'])): ?>
                <p style="text-align:center;color:var(--krown-text-muted);padding:30px 0;">
                    Sin movimientos en el período.
                </p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="cont-tabla-reporte">
                        <thead>
                            <tr>
                                <th style="width:80px;">N°</th>
                                <th style="width:100px;">Fecha</th>
                                <th>Detalle</th>
                                <th class="num" style="width:130px;">Debe</th>
                                <th class="num" style="width:130px;">Haber</th>
                                <th class="num" style="width:140px;">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['movimientos'] as $m): ?>
                                <tr>
                                    <td class="mono"><?= str_pad((string)$m['numero'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($m['asiento_desc']) ?>
                                        <?php if (!empty($m['linea_detalle'])): ?>
                                            <span style="color:var(--krown-text-muted);">
                                                — <?= htmlspecialchars($m['linea_detalle']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span style="color:var(--krown-text-muted);font-size:12px;display:block;">
                                            <?= htmlspecialchars($m['emisor_nombre']) ?> · <?= htmlspecialchars($m['tipo']) ?>
                                        </span>
                                    </td>
                                    <td class="num">
                                        <?= (float)$m['debe'] > 0 ? '$ ' . number_format((float)$m['debe'], 2, ',', '.') : '' ?>
                                    </td>
                                    <td class="num">
                                        <?= (float)$m['haber'] > 0 ? '$ ' . number_format((float)$m['haber'], 2, ',', '.') : '' ?>
                                    </td>
                                    <td class="num" style="font-weight:700;">
                                        $ <?= number_format($m['saldo'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fila-totales">
                                <td colspan="5" style="text-align:right;">Saldo final</td>
                                <td class="num">$ <?= number_format($data['saldo_final'], 2, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>