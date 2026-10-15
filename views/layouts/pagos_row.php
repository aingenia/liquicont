<?php if (empty($pagos)): ?>
    <tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">
        No hay pagos en el período seleccionado.
    </td></tr>
<?php else: ?>
    <?php foreach ($pagos as $p): ?>
        <?php $esAnulado = $p['estado'] === 'ANULADO'; ?>
        <tr data-id="<?= (int)$p['id'] ?>" class="<?= $esAnulado ? 'fila-anulada' : '' ?>">
            <td data-label="N°"><strong>#<?= (int)$p['id'] ?></strong></td>
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
            <td data-label="Proveedor" class="cell-title">
                <div class="razon-social"><?= htmlspecialchars($p['proveedor_razon_social']) ?></div>
                <?php if (!empty($p['proveedor_cuit'])): ?>
                    <div style="font-size:12px;color:var(--krown-text-muted);">
                        CUIT <?= htmlspecialchars($p['proveedor_cuit']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Compras" style="text-align:center;">
                <?= (int)$p['compras_count'] ?>
            </td>
            <td data-label="Total" style="text-align:right;font-weight:600;">
                $ <?= number_format((float)$p['total'], 2, ',', '.') ?>
            </td>
            <td data-label="Estado">
                <?php if ($esAnulado): ?>
                    <span class="badge badge-inactive">Anulado</span>
                <?php else: ?>
                    <span class="badge badge-active">Activo</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;">
                <a href="<?= BASE_URL ?>pagos/ver?id=<?= (int)$p['id'] ?>" class="btn-icon" title="Ver">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>