<?php if (empty($cobros)): ?>
    <tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">
        No hay cobros en el período seleccionado.
    </td></tr>
<?php else: ?>
    <?php foreach ($cobros as $c): ?>
        <?php $esAnulado = $c['estado'] === 'ANULADO'; ?>
        <tr data-id="<?= (int)$c['id'] ?>" class="<?= $esAnulado ? 'fila-anulada' : '' ?>">
            <td data-label="N°"><strong>#<?= (int)$c['id'] ?></strong></td>
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
            <td data-label="Cliente" class="cell-title">
                <div class="razon-social"><?= htmlspecialchars($c['cliente_razon_social']) ?></div>
                <?php if (!empty($c['cliente_cuit'])): ?>
                    <div style="font-size:12px;color:var(--krown-text-muted);">
                        CUIT <?= htmlspecialchars($c['cliente_cuit']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Facturas" style="text-align:center;">
                <?= (int)$c['facturas_count'] ?>
            </td>
            <td data-label="Total" style="text-align:right;font-weight:600;">
                $ <?= number_format((float)$c['total'], 2, ',', '.') ?>
            </td>
            <td data-label="Estado">
                <?php if ($esAnulado): ?>
                    <span class="badge badge-inactive">Anulado</span>
                <?php else: ?>
                    <span class="badge badge-active">Activo</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;">
                <a href="<?= BASE_URL ?>cobros/ver?id=<?= (int)$c['id'] ?>" class="btn-icon" title="Ver">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>