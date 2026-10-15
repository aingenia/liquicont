<?php if (empty($pedidos)): ?>
    <tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">
        No hay pedidos que coincidan con la búsqueda.
    </td></tr>
<?php else: ?>
    <?php foreach ($pedidos as $p): ?>
        <?php
            $esAnulado = $p['estado'] === 'ANULADO';
            $modificado = !empty($p['modificado_por_vendedor_el']) && (int)$p['modificado_por_vendedor_visto'] === 0;
        ?>
        <tr data-id="<?= (int)$p['id'] ?>" class="<?= $esAnulado ? 'fila-anulada' : '' ?>">
            <td data-label="N°" class="mono">
                <strong>#<?= (int)$p['id'] ?></strong>
                <?php if ($modificado): ?>
                    <span class="ped-dot-modificado" title="Modificado por el vendedor"></span>
                <?php endif; ?>
            </td>
            <td data-label="Cliente" class="cell-title">
                <div class="razon-social"><?= htmlspecialchars($p['cliente_razon_social']) ?></div>
                <div style="font-size:12px;color:var(--krown-text-muted);">
                    <?= htmlspecialchars($p['cliente_categoria'] ?? '—') ?>
                    · Creado por <?= htmlspecialchars($p['creado_por_nombre'] ?? '—') ?>
                </div>
            </td>
            <td data-label="Fecha"><?= date('d/m/Y', strtotime($p['fecha_pedido'])) ?></td>
            <td data-label="Items"><?= (int)$p['items_count'] ?> item(s)</td>
            <?php if (!$sinPrecios): ?>
                <td data-label="Total" style="text-align:right;font-weight:600;">
                    $ <?= number_format((float)$p['total'], 2, ',', '.') ?>
                </td>
            <?php endif; ?>
            <td data-label="Estado">
                <span class="ped-badge <?= estadoPedidoClase($p['estado']) ?>">
                    <?= estadoPedidoLabel($p['estado']) ?>
                </span>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;white-space:nowrap;">
                <?php if (!$sinPrecios): ?>
                    <a href="<?= BASE_URL ?>pedidos/ver?id=<?= (int)$p['id'] ?>" class="btn-icon" title="Ver">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </a>
                    <a href="<?= BASE_URL ?>pedidos/editar?id=<?= (int)$p['id'] ?>" class="btn-icon" title="Editar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>pedidos/ver?id=<?= (int)$p['id'] ?>" class="btn-icon" title="Ver detalle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>