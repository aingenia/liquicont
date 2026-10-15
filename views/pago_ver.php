<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Pago';

$esActivo = $pago['estado'] === 'ACTIVO';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Pago #<?= (int)$pago['id'] ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>pagos">Pagos</a> / Ver
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>pagos" class="btn btn-outline">← Volver</a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<?php if (!$esActivo): ?>
    <div class="msg-box msg-error" style="display:block;margin-bottom:16px;">
        <strong>Este pago está ANULADO.</strong>
        <?php if ($pago['anulado_motivo']): ?>
            Motivo: <?= htmlspecialchars($pago['anulado_motivo']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- CABECERA -->
<div class="ped-topbar">
    <div class="ped-topbar-left">
        <div class="ped-topbar-label">PROVEEDOR</div>
        <div class="ped-topbar-cliente"><?= htmlspecialchars($pago['proveedor_razon_social']) ?></div>
    </div>
    <div class="ped-topbar-right">
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">FECHA</span>
            <span class="ped-topbar-valor"><?= date('d/m/Y', strtotime($pago['fecha'])) ?></span>
        </div>
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">TOTAL PAGADO</span>
            <span class="ped-topbar-valor ped-topbar-total">$ <?= number_format((float)$pago['total'], 2, ',', '.') ?></span>
        </div>
    </div>
</div>

<!-- ACCIONES -->
<?php if ($esActivo && esAdmin()): ?>
    <div class="fac-actions">
        <button type="button" class="btn btn-outline" id="btnAnularPago"
                data-id="<?= (int)$pago['id'] ?>"
                data-info="Pago #<?= (int)$pago['id'] ?> · $<?= number_format((float)$pago['total'], 2, ',', '.') ?>"
                style="color:#B23A48;border-color:rgba(178,58,72,.3);">
            ⊘ Anular pago
        </button>
    </div>
<?php endif; ?>

<!-- MEDIOS DE PAGO -->
<div class="card" style="margin-top:16px;">
    <div class="card-header">
        <h3>Medios de pago</h3>
    </div>
    <div class="card-body card-body-stack">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Medio</th>
                        <th>Detalle</th>
                        <th style="text-align:right;width:140px;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medios as $m): ?>
                        <tr>
                            <td>
                                <span class="cob-medio-badge cob-medio-<?= strtolower($m['medio']) ?>">
                                    <?= ucfirst(strtolower($m['medio'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($m['medio'] === 'CHEQUE'): ?>
                                    <div>
                                        <strong>Banco:</strong> <?= htmlspecialchars($m['cheque_banco'] ?? '—') ?>
                                        · <strong>N°:</strong> <?= htmlspecialchars($m['cheque_numero'] ?? '—') ?>
                                    </div>
                                    <div style="font-size:12px;color:var(--liqui-text-muted);">
                                        Fecha de cobro: <?= $m['cheque_fecha_cobro'] ? date('d/m/Y', strtotime($m['cheque_fecha_cobro'])) : '—' ?>
                                        <?php if ($m['cheque_titular']): ?>
                                            · Titular: <?= htmlspecialchars($m['cheque_titular']) ?>
                                        <?php endif; ?>
                                        <br>
                                        <em>(Cheque propio — ya está en el Calendario de Cheques)</em>
                                    </div>
                                <?php elseif ($m['medio'] === 'RETENCION'): ?>
                                    <div>
                                        <strong><?= htmlspecialchars($m['retencion_tipo'] ?? 'Retención') ?></strong>
                                        <?php if ($m['retencion_nro']): ?>
                                            · N° <?= htmlspecialchars($m['retencion_nro']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?= htmlspecialchars($m['observaciones'] ?? '—') ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;font-weight:600;">
                                $ <?= number_format((float)$m['importe'], 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- COMPRAS IMPUTADAS -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Compras imputadas</h3>
        <p>Este pago salda las siguientes compras.</p>
    </div>
    <div class="card-body card-body-stack">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:100px;">Fecha</th>
                        <th>Comprobante</th>
                        <th style="text-align:right;width:120px;">Total compra</th>
                        <th style="text-align:right;width:120px;">Imputado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $c):
                        $pv = str_pad((string)$c['punto_venta'], 4, '0', STR_PAD_LEFT);
                        $nro = str_pad((string)$c['numero'], 8, '0', STR_PAD_LEFT);
                        $comp = $c['punto_venta'] || $c['numero']
                            ? ($c['tipo_comprobante_desc'] ?? '—') . ' ' . $pv . '-' . $nro
                            : ($c['tipo_comprobante_desc'] ?? '—');
                    ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($c['fecha_comprobante'])) ?></td>
                            <td><?= htmlspecialchars($comp) ?></td>
                            <td style="text-align:right;">$ <?= number_format((float)$c['compra_total'], 2, ',', '.') ?></td>
                            <td style="text-align:right;font-weight:600;">$ <?= number_format((float)$c['importe_aplicado'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- NOTAS + SALDO -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Notas y saldo del proveedor</h3>
    </div>
    <div class="card-body cob-grid-2">
        <div class="form-group">
            <label>Notas del pago</label>
            <div class="fac-readonly">
                <?= !empty($pago['notas']) ? nl2br(htmlspecialchars($pago['notas'])) : '—' ?>
            </div>
        </div>
        <div class="form-group">
            <label>Saldo actual del proveedor</label>
            <div class="fac-readonly" style="font-size:22px;font-weight:800;color:<?= $saldo > 0 ? 'var(--liqui-crimson)' : 'var(--liqui-teal)' ?>;">
                $ <?= number_format((float)$saldo, 2, ',', '.') ?>
            </div>
            <div class="form-hint">
                <?= $saldo > 0 ? 'Le debemos este monto al proveedor.' : ($saldo < 0 ? 'Tenemos saldo a favor con el proveedor.' : 'Cuenta al día.') ?>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>