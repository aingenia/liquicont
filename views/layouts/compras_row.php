<?php if (empty($compras)): ?>
    <tr><td colspan="8" style="text-align:center;padding:2rem;color:#666;">
        No encontramos comprobantes con ese criterio.
    </td></tr>
<?php else: ?>
    <?php foreach ($compras as $c): ?>
        <?php $esAnulada = $c['estado'] === 'ANULADA'; ?>
        <tr data-id="<?= (int)$c['id'] ?>" class="<?= $esAnulada ? 'fila-anulada' : '' ?>">
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($c['fecha_comprobante'])) ?></td>
            <td data-label="Proveedor" class="cell-title">
                <div class="razon-social"><?= htmlspecialchars($c['proveedor_razon_social']) ?></div>
                <?php if (!empty($c['proveedor_cuit'])): ?>
                    <div style="font-size:12px;color:var(--liqui-text-muted);">CUIT <?= htmlspecialchars($c['proveedor_cuit']) ?></div>
                <?php endif; ?>
            </td>
            <td data-label="Comprobante">
                <?= htmlspecialchars($c['tipo_comprobante_desc'] ?? '—') ?>
                <?php if ($c['punto_venta'] || $c['numero']): ?>
                    <br><span class="mono" style="font-size:12px;">
                        <?= str_pad($c['punto_venta'] ?? '', 4, '0', STR_PAD_LEFT) ?>-
                        <?= str_pad($c['numero'] ?? '', 8, '0', STR_PAD_LEFT) ?>
                    </span>
                <?php endif; ?>
            </td>
            <td data-label="CAE" class="mono" style="font-size:12px;">
                <?= htmlspecialchars($c['cae'] ?? '—') ?>
            </td>
            <td data-label="Total" style="font-weight:600;">
                $ <?= number_format((float)$c['total'], 2, ',', '.') ?>
            </td>
            <td data-label="Estado">
                <?php if ($esAnulada): ?>
                    <span class="badge badge-inactive">Anulada</span>
                <?php else: ?>
                    <span class="badge badge-active">Registrada</span>
                <?php endif; ?>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;white-space:nowrap;">
                <a href="<?= BASE_URL ?>comprobantes/ver?id=<?= (int)$c['id'] ?>"
                class="btn-icon" title="Ver">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </a>
                <?php if (!$esAnulada): ?>
                    <a href="<?= BASE_URL ?>comprobantes/editar?id=<?= (int)$c['id'] ?>"
                    class="btn-icon" title="Editar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                    </a>
                    <button type="button" class="btn-icon btn-anular-compra"
                            data-id="<?= (int)$c['id'] ?>"
                            data-info="<?= htmlspecialchars($c['proveedor_razon_social'] . ' - $' . number_format((float)$c['total'], 2, ',', '.')) ?>"
                            title="Anular">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>