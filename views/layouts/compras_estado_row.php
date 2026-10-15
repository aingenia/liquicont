<?php if (empty($compras)): ?>
    <tr><td colspan="8" style="text-align:center;padding:2rem;color:#666;">
        No hay compras en el período seleccionado.
    </td></tr>
<?php else: ?>
    <?php foreach ($compras as $c): ?>
        <?php
            // Determinar estado visual del pago
            $pagado   = (float)$c['pagado'];
            $pendiente = (float)$c['saldo_pendiente'];
            $esAnulada = $c['estado'] === 'ANULADA';

            if ($esAnulada) {
                $estadoLabel = 'Anulada';
                $estadoClase = 'badge-inactive';
            } elseif ($pendiente <= 0.004) {
                $estadoLabel = 'Pagada';
                $estadoClase = 'badge-active';
            } elseif ($pagado > 0) {
                $estadoLabel = 'Pago parcial';
                $estadoClase = 'badge-warning';
            } else {
                $estadoLabel = 'Pendiente';
                $estadoClase = 'badge-pending';
            }

            // Armar comprobante
            $tipo = $c['tipo_comprobante_desc'] ?? 'Compra';
            $pv   = $c['punto_venta'] ? str_pad((string)$c['punto_venta'], 4, '0', STR_PAD_LEFT) : '';
            $nro  = $c['numero']      ? str_pad((string)$c['numero'], 8, '0', STR_PAD_LEFT)      : '';
            $comp = ($pv || $nro) ? "$tipo $pv-$nro" : "$tipo #" . (int)$c['id'];
        ?>
        <tr data-id="<?= (int)$c['id'] ?>" class="<?= $esAnulada ? 'fila-anulada' : '' ?>">
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($c['fecha_comprobante'])) ?></td>
            <td data-label="Proveedor" class="cell-title">
                <div class="razon-social"><?= htmlspecialchars($c['proveedor_razon_social']) ?></div>
                <?php if (!empty($c['proveedor_cuit'])): ?>
                    <div style="font-size:12px;color:var(--krown-text-muted);">
                        CUIT <?= htmlspecialchars($c['proveedor_cuit']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Comprobante" style="font-size:13px;"><?= htmlspecialchars($comp) ?></td>
            <td data-label="Total" style="text-align:right;">
                $ <?= number_format((float)$c['total'], 2, ',', '.') ?>
            </td>
            <td data-label="Pagado" style="text-align:right;color:var(--krown-teal);">
                $ <?= number_format($pagado, 2, ',', '.') ?>
            </td>
            <td data-label="Pendiente" style="text-align:right;font-weight:600;color:<?= $pendiente > 0.004 ? 'var(--krown-crimson)' : 'var(--krown-text-muted)' ?>;">
                $ <?= number_format($pendiente, 2, ',', '.') ?>
            </td>
            <td data-label="Estado pago">
                <span class="badge <?= $estadoClase ?>"><?= $estadoLabel ?></span>
            </td>
            <td style="text-align:right;">
                <?php if (!$esAnulada && $pendiente > 0.004): ?>
                    <a href="<?= BASE_URL ?>pagos/crear?proveedor_id=<?= (int)$c['proveedor_id'] ?>"
                       class="btn btn-primary btn-sm" title="Pagar esta compra">
                        Pagar
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>