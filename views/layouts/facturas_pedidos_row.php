<?php if (empty($pedidos)): ?>
    <tr><td colspan="6" style="text-align:center;padding:2rem;color:#666;">
        No hay pedidos listos para facturar.
    </td></tr>
<?php else: ?>
    <?php foreach ($pedidos as $p): ?>
        <tr data-id="<?= (int)$p['id'] ?>">
            <td data-label="N°"><strong>#<?= (int)$p['id'] ?></strong></td>
            <td data-label="Cliente">
                <div class="razon-social"><?= htmlspecialchars($p['cliente_razon_social']) ?></div>
                <div style="font-size:12px;color:var(--krown-text-muted);">
                    <?= htmlspecialchars($p['cliente_categoria'] ?? '—') ?>
                </div>
            </td>
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?></td>
            <td data-label="Items"><?= (int)$p['items_count'] ?> item(s)</td>
            <td data-label="Total" style="text-align:right;font-weight:600;">
                $ <?= number_format((float)$p['total'], 2, ',', '.') ?>
            </td>
            <td style="text-align:right;">
                <a href="<?= BASE_URL ?>facturas/crear?pedido_id=<?= (int)$p['id'] ?>"
                   class="btn btn-primary btn-sm">
                    Facturar
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>