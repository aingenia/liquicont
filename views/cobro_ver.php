<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Cobro';

$esActivo = $cobro['estado'] === 'ACTIVO';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Cobro #<?= (int)$cobro['id'] ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>cobros">Cobros</a> / Ver
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>cobros" class="btn btn-outline">← Volver</a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<?php if (!$esActivo): ?>
    <div class="msg-box msg-error" style="display:block;margin-bottom:16px;">
        <strong>Este cobro está ANULADO.</strong>
        <?php if ($cobro['anulado_motivo']): ?>
            Motivo: <?= htmlspecialchars($cobro['anulado_motivo']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- CABECERA -->
<div class="ped-topbar">
    <div class="ped-topbar-left">
        <div class="ped-topbar-label">CLIENTE</div>
        <div class="ped-topbar-cliente"><?= htmlspecialchars($cobro['cliente_razon_social']) ?></div>
    </div>
    <div class="ped-topbar-right">
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">FECHA</span>
            <span class="ped-topbar-valor"><?= date('d/m/Y', strtotime($cobro['fecha'])) ?></span>
        </div>
        <div class="ped-topbar-stat">
            <span class="ped-topbar-label">TOTAL COBRADO</span>
            <span class="ped-topbar-valor ped-topbar-total">$ <?= number_format((float)$cobro['total'], 2, ',', '.') ?></span>
        </div>
    </div>
</div>

<!-- ACCIONES -->
<?php if ($esActivo && esAdmin()): ?>
    <div class="fac-actions">
        <button type="button" class="btn btn-outline" id="btnAnularCobro"
                data-id="<?= (int)$cobro['id'] ?>"
                data-info="Cobro #<?= (int)$cobro['id'] ?> · $<?= number_format((float)$cobro['total'], 2, ',', '.') ?>"
                style="color:#B23A48;border-color:rgba(178,58,72,.3);">
            ⊘ Anular cobro
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
                                    <div style="font-size:12px;color:var(--krown-text-muted);">
                                        Fecha de cobro: <?= $m['cheque_fecha_cobro'] ? date('d/m/Y', strtotime($m['cheque_fecha_cobro'])) : '—' ?>
                                        <?php if ($m['cheque_titular']): ?>
                                            · Titular: <?= htmlspecialchars($m['cheque_titular']) ?>
                                        <?php endif; ?>
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

<!-- FACTURAS IMPUTADAS -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Facturas imputadas</h3>
        <p>Este cobro salda las siguientes facturas.</p>
    </div>
    <div class="card-body card-body-stack">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:100px;">Fecha</th>
                        <th>Comprobante</th>
                        <th style="text-align:right;width:120px;">Total factura</th>
                        <th style="text-align:right;width:120px;">Imputado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facturas as $f):
                            // Elegir entre comprobante ARCA (si existe) o remito interno
                            if (!empty($f['arca_numero_comprobante'])) {
                                $letra = $f['arca_tipo_comprobante_letra'] ?? 'X';
                                $comp = $letra . ' '
                                    . str_pad((string)$f['arca_punto_venta'], 4, '0', STR_PAD_LEFT) . '-'
                                    . str_pad((string)$f['arca_numero_comprobante'], 8, '0', STR_PAD_LEFT);
                            } else {
                                // Fallback: usar el remito
                                $prefijo = $f['remito_prefijo'] ? $f['remito_prefijo'] . ' ' : '';
                                $comp = $prefijo
                                    . str_pad((string)$f['remito_punto_venta'], 4, '0', STR_PAD_LEFT) . '-'
                                    . str_pad((string)$f['remito_numero'], 8, '0', STR_PAD_LEFT);
                                $comp .= ' (remito)';
                            }
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($f['factura_fecha'])) ?></td>
                            <td><?= htmlspecialchars($comp) ?></td>
                            <td style="text-align:right;">$ <?= number_format((float)$f['factura_total'], 2, ',', '.') ?></td>
                            <td style="text-align:right;font-weight:600;">$ <?= number_format((float)$f['importe_aplicado'], 2, ',', '.') ?></td>
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
        <h3>Notas y saldo del cliente</h3>
    </div>
    <div class="card-body cob-grid-2">
        <div class="form-group">
            <label>Notas del cobro</label>
            <div class="fac-readonly">
                <?= !empty($cobro['notas']) ? nl2br(htmlspecialchars($cobro['notas'])) : '—' ?>
            </div>
        </div>
        <div class="form-group">
            <label>Saldo actual del cliente</label>
            <div class="fac-readonly" style="font-size:22px;font-weight:800;color:<?= $saldo > 0 ? 'var(--krown-crimson)' : 'var(--krown-teal)' ?>;">
                $ <?= number_format((float)$saldo, 2, ',', '.') ?>
            </div>
            <div class="form-hint" style="margin-top:8px;">
                <?= $saldo > 0 ? 'El cliente nos debe este monto.' : ($saldo < 0 ? 'El cliente tiene saldo a favor.' : 'Cuenta al día.') ?>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>