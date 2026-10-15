<?php if (empty($facturas)): ?>
    <tr><td colspan="9" style="text-align:center;padding:2rem;color:#666;">
        No hay facturas en el período seleccionado.
    </td></tr>
<?php else: ?>
    <?php foreach ($facturas as $f): ?>
        <?php
            $esAnulada = $f['estado'] === 'ANULADA';
            $remito = ($f['remito_prefijo'] ? $f['remito_prefijo'] . ' ' : '')
                    . str_pad((string)$f['remito_punto_venta'], 4, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string)$f['remito_numero'], 8, '0', STR_PAD_LEFT);

            $arcaBadge = [
                'PENDIENTE' => ['label' => 'Pendiente', 'clase' => 'badge-warning'],
                'APROBADO'  => ['label' => 'Aprobado',  'clase' => 'badge-active'],
                'ERROR'     => ['label' => 'Error',     'clase' => 'badge-inactive'],
            ][$f['arca_estado']] ?? ['label' => $f['arca_estado'], 'clase' => ''];
        ?>
        <tr data-id="<?= (int)$f['id'] ?>" class="<?= $esAnulada ? 'fila-anulada' : '' ?>">
            <td data-label="N°"><strong>#<?= (int)$f['id'] ?></strong></td>
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
            <td data-label="Cliente">
                <div class="razon-social"><?= htmlspecialchars($f['cliente_razon_social']) ?></div>
                <?php if (!empty($f['cliente_cuit'])): ?>
                    <div style="font-size:12px;color:var(--liqui-text-muted);">
                        CUIT <?= htmlspecialchars($f['cliente_cuit']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Remito" class="mono" style="font-size:12px;"><?= htmlspecialchars($remito) ?></td>
            <td data-label="N° ARCA / CAE" class="mono" style="font-size:12px;">
                <?php if (!empty($f['arca_numero_comprobante'])): ?>
                    <div><?= str_pad((string)$f['arca_punto_venta'], 4, '0', STR_PAD_LEFT) ?>-<?= str_pad((string)$f['arca_numero_comprobante'], 8, '0', STR_PAD_LEFT) ?></div>
                    <div style="color:#7A7266;">CAE <?= htmlspecialchars($f['cae'] ?? '') ?></div>
                <?php else: ?>
                    <span class="badge <?= $arcaBadge['clase'] ?>"><?= $arcaBadge['label'] ?></span>
                <?php endif; ?>
            </td>
            <td data-label="Emisor" style="font-size:12px;"><?= htmlspecialchars($f['emisor_nombre']) ?></td>
            <td data-label="Total" style="text-align:right;font-weight:600;">
                $ <?= number_format((float)$f['total'], 2, ',', '.') ?>
            </td>
            <td data-label="Estado">
                <?php if ($esAnulada): ?>
                    <span class="badge badge-inactive">Anulada</span>
                <?php else: ?>
                    <span class="badge badge-active">Activa</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;">
                <a href="<?= BASE_URL ?>facturas/ver?id=<?= (int)$f['id'] ?>" class="btn-icon" title="Ver">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>