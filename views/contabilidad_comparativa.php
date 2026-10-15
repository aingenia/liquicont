<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Comparativa Ventas vs Compras';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$t = $data['totales'];
?>

<div class="page-header">
    <div>
        <h2>Comparativa Ventas vs Compras</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Comparativa
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>contabilidad/ayuda"
                data-ayuda-titulo="Ayuda — Comparativa">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
    </div>
</div>

<?= flashRender() ?>

<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/comparativa">
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
        <a href="<?= BASE_URL ?>contabilidad/comparativa-csv?desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?><?= !empty($emisorId) ? '&emisor_id=' . (int)$emisorId : '' ?>"
   class="btn btn-outline">Exportar CSV</a>
    </div>
</form>

<!-- TOTALES GENERALES -->
<div class="cont-dashboard-grid" style="margin-bottom:20px;">
    <div class="cont-stat cont-stat-teal">
        <span class="cont-stat-label">VENTAS TOTALES</span>
        <span class="cont-stat-valor">$ <?= number_format($t['ventas']['total'], 2, ',', '.') ?></span>
        <span class="cont-stat-sub">
            Neto $ <?= number_format($t['ventas']['neto'], 2, ',', '.') ?> ·
            IVA $ <?= number_format($t['ventas']['iva'], 2, ',', '.') ?>
        </span>
    </div>
    <div class="cont-stat cont-stat-crimson">
        <span class="cont-stat-label">COMPRAS TOTALES</span>
        <span class="cont-stat-valor">$ <?= number_format($t['compras']['total'], 2, ',', '.') ?></span>
        <span class="cont-stat-sub">
            Neto $ <?= number_format($t['compras']['neto'], 2, ',', '.') ?> ·
            IVA $ <?= number_format($t['compras']['iva'], 2, ',', '.') ?>
        </span>
    </div>
    <?php
    $posIva = $t['posicion']['iva'];
    $claseIva = $posIva < 0 ? 'cont-stat-dark' : 'cont-stat';
    ?>
    <div class="cont-stat <?= $claseIva ?>">
        <span class="cont-stat-label">POSICIÓN IVA</span>
        <span class="cont-stat-valor" style="color:<?= $posIva < 0 ? '#7BE7B8' : '#B23A48' ?>">
            $ <?= number_format(abs($posIva), 2, ',', '.') ?>
        </span>
        <span class="cont-stat-sub">
            <?= $posIva < 0 ? 'Saldo a favor (pagar menos)' : 'Saldo a pagar' ?>
        </span>
    </div>
    <div class="cont-stat">
        <span class="cont-stat-label">PERÍODO</span>
        <span class="cont-stat-valor"><?= count($data['filas']) ?> meses</span>
        <span class="cont-stat-sub">
            <?= date('m/Y', strtotime($data['desde'])) ?> a
            <?= date('m/Y', strtotime($data['hasta'])) ?>
        </span>
    </div>
</div>

<!-- TABLA COMPARATIVA -->
<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Detalle por mes</h3>
            <p>Cada mes muestra 3 filas: Ventas, Compras y la Posición (Ventas − Compras).</p>
        </div>
    </div>
    <div class="cont-card-body">
        <?php if (empty($data['filas'])): ?>
            <p style="text-align:center;color:var(--krown-text-muted);padding:30px 0;">
                No hay datos en el período.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="cont-tabla-reporte">
                    <thead>
                        <tr>
                            <th style="width:140px;">Mes</th>
                            <th style="width:100px;">Concepto</th>
                            <th class="num" style="width:130px;">Neto</th>
                            <th class="num" style="width:110px;">IVA</th>
                            <th class="num" style="width:110px;">Perc. IIBB</th>
                            <th class="num" style="width:120px;">Imp. Internos</th>
                            <th class="num" style="width:130px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['filas'] as $f): ?>
                            <!-- Fila VENTAS -->
                            <tr class="fila-asiento" style="background:#E8F5F0;">
                                <td rowspan="3" style="vertical-align:middle;font-weight:600;font-size:14px;">
                                    <?= htmlspecialchars($f['mes_label']) ?>
                                </td>
                                <td style="font-weight:700;color:var(--krown-teal);">VENTAS</td>
                                <td class="num">$ <?= number_format($f['ventas']['neto'], 2, ',', '.') ?></td>
                                <td class="num">$ <?= number_format($f['ventas']['iva'], 2, ',', '.') ?></td>
                                <td class="num">$ <?= number_format($f['ventas']['perc_iibb'], 2, ',', '.') ?></td>
                                <td class="num">$ <?= number_format($f['ventas']['imp_int'], 2, ',', '.') ?></td>
                                <td class="num" style="font-weight:700;">$ <?= number_format($f['ventas']['total'], 2, ',', '.') ?></td>
                            </tr>
                            <!-- Fila COMPRAS -->
                            <tr style="background:#FDF2F4;">
                                <td style="font-weight:700;color:var(--krown-crimson);">COMPRAS</td>
                                <td class="num">$ <?= number_format($f['compras']['neto'], 2, ',', '.') ?></td>
                                <td class="num">$ <?= number_format($f['compras']['iva'], 2, ',', '.') ?></td>
                                <td class="num">$ <?= number_format($f['compras']['perc_iibb'], 2, ',', '.') ?></td>
                                <td class="num">$ <?= number_format($f['compras']['imp_int'], 2, ',', '.') ?></td>
                                <td class="num" style="font-weight:700;">$ <?= number_format($f['compras']['total'], 2, ',', '.') ?></td>
                            </tr>
                            <!-- Fila POSICIÓN -->
                            <tr style="background:#FAFAFA;">
                                <td style="font-weight:700;">POSICIÓN</td>
                                <?php
                                $posNeto = $f['posicion']['neto'];
                                $posIva  = $f['posicion']['iva'];
                                $posIibb = $f['posicion']['perc_iibb'];
                                $posInt  = $f['posicion']['imp_int'];
                                $posTot  = $f['posicion']['total'];
                                ?>
                                <td class="num" style="color:<?= $posNeto < 0 ? 'var(--krown-crimson)' : 'var(--krown-teal)' ?>;font-weight:700;">
                                    $ <?= number_format($posNeto, 2, ',', '.') ?>
                                </td>
                                <td class="num" style="color:<?= $posIva < 0 ? 'var(--krown-crimson)' : 'var(--krown-teal)' ?>;font-weight:700;">
                                    $ <?= number_format($posIva, 2, ',', '.') ?>
                                </td>
                                <td class="num" style="color:<?= $posIibb < 0 ? 'var(--krown-crimson)' : 'var(--krown-teal)' ?>;font-weight:700;">
                                    $ <?= number_format($posIibb, 2, ',', '.') ?>
                                </td>
                                <td class="num" style="color:<?= $posInt < 0 ? 'var(--krown-crimson)' : 'var(--krown-teal)' ?>;font-weight:700;">
                                    $ <?= number_format($posInt, 2, ',', '.') ?>
                                </td>
                                <td class="num" style="color:<?= $posTot < 0 ? 'var(--krown-crimson)' : 'var(--krown-teal)' ?>;font-weight:700;">
                                    $ <?= number_format($posTot, 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fila-totales">
                            <td colspan="2" style="text-align:right;">TOTAL VENTAS</td>
                            <td class="num">$ <?= number_format($t['ventas']['neto'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['ventas']['iva'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['ventas']['perc_iibb'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['ventas']['imp_int'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['ventas']['total'], 2, ',', '.') ?></td>
                        </tr>
                        <tr class="fila-totales" style="background:#8a2030;">
                            <td colspan="2" style="text-align:right;">TOTAL COMPRAS</td>
                            <td class="num">$ <?= number_format($t['compras']['neto'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['compras']['iva'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['compras']['perc_iibb'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['compras']['imp_int'], 2, ',', '.') ?></td>
                            <td class="num">$ <?= number_format($t['compras']['total'], 2, ',', '.') ?></td>
                        </tr>
                        <tr class="fila-totales" style="background:var(--krown-gold);color:var(--krown-black);">
                            <td colspan="2" style="text-align:right;">POSICIÓN FINAL</td>
                            <td class="num" style="color:var(--krown-black);">$ <?= number_format($t['posicion']['neto'], 2, ',', '.') ?></td>
                            <td class="num" style="color:var(--krown-black);">$ <?= number_format($t['posicion']['iva'], 2, ',', '.') ?></td>
                            <td class="num" style="color:var(--krown-black);">$ <?= number_format($t['posicion']['perc_iibb'], 2, ',', '.') ?></td>
                            <td class="num" style="color:var(--krown-black);">$ <?= number_format($t['posicion']['imp_int'], 2, ',', '.') ?></td>
                            <td class="num" style="color:var(--krown-black);">$ <?= number_format($t['posicion']['total'], 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="msg-box msg-info" style="display:block;margin-top:20px;">
                <strong>¿Cómo leer la posición?</strong><br>
                <strong>Posición negativa</strong> (rojo) → saldo a favor: las compras superan las ventas en ese concepto. Es IVA que podés compensar o pedir devolución.<br>
                <strong>Posición positiva</strong> (verde) → saldo a pagar: las ventas superan las compras. Es IVA que tenés que ingresar al fisco.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>