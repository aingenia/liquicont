<?php if (empty($insumos)): ?>
    <tr><td colspan="8" style="text-align:center;padding:2rem;color:#666;">
        No se encontraron insumos.
    </td></tr>
<?php else: ?>
    <?php foreach ($insumos as $i): ?>
        <tr data-id="<?= (int)$i['id'] ?>">
            <td class="cell-title razon-social" data-label="Nombre">
                <div><?= htmlspecialchars($i['nombre']) ?></div>
                <?php if (!empty($i['codigo'])): ?>
                    <div style="font-size:12px;color:var(--liqui-text-muted);font-weight:400;">
                        Código: <?= htmlspecialchars($i['codigo']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Unidad"><?= htmlspecialchars($i['unidad_abrev'] ?? '—') ?></td>
            <td data-label="Medida"><?= number_format((float)$i['medida'], 3, ',', '.') ?></td>
            <td data-label="Stock"><?= number_format((float)$i['stock'], 3, ',', '.') ?></td>
            <td data-label="Pto. pedido"><?= number_format((float)$i['ppedido'], 3, ',', '.') ?></td>
            <td data-label="Costo">$ <?= number_format((float)$i['costo'], 2, ',', '.') ?></td>
            <td data-label="Estado">
                <?php if ($i['estado'] === 'ACTIVO'): ?>
                    <span class="badge badge-active">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Inactivo</span>
                <?php endif; ?>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;">
                <a href="<?= BASE_URL ?>insumos/editar?id=<?= (int)$i['id'] ?>"
                   class="btn-icon" title="Ver/Editar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </a>
                <?php if (puedeEditarInsumos()): ?>
                    <button class="btn-icon btn-eliminar"
                            data-id="<?= (int)$i['id'] ?>"
                            data-nombre="<?= htmlspecialchars($i['nombre']) ?>"
                            title="Eliminar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>