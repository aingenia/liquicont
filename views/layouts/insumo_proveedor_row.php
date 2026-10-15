<?php
/**
 * views/layouts/insumo_proveedor_row.php
 * Variable esperada: $row (fila de insumo_proveedor con JOIN a proveedor)
 */
?>
<tr data-row-id="<?= (int)$row['id'] ?>">
    <td data-label="Proveedor" class="cell-prov">
        <div style="font-weight:600;"><?= htmlspecialchars($row['razon_social']) ?></div>
        <?php if (!empty($row['nombre_fantasia']) && $row['nombre_fantasia'] !== $row['razon_social']): ?>
            <div style="font-size:12px;color:var(--liqui-text-muted);"><?= htmlspecialchars($row['nombre_fantasia']) ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--liqui-text-muted);">CUIT <?= htmlspecialchars($row['cuit']) ?></div>
    </td>

    <td data-label="Costo" class="cell-costo">
        <span class="valor">$ <?= number_format((float)$row['costo'], 2, ',', '.') ?></span>
        <input type="number" class="editor" step="0.0001" min="0"
               value="<?= htmlspecialchars((string)$row['costo']) ?>"
               style="display:none; width:130px;">
    </td>

    <td data-label="Código proveedor" class="cell-codigo">
        <span class="valor"><?= htmlspecialchars($row['codigo_proveedor'] ?? '—') ?></span>
        <input type="text" class="editor" maxlength="50"
               value="<?= htmlspecialchars($row['codigo_proveedor'] ?? '') ?>"
               style="display:none; width:100%;">
    </td>

    <td class="col-acciones" data-label="" style="text-align:right; white-space:nowrap;">
        <div class="acciones-normal" style="<?= puedeEditarInsumos() ? '' : 'display:none;' ?>">
            <button type="button" class="btn-icon btn-editar-prov"
                    data-id="<?= (int)$row['id'] ?>" title="Editar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                </svg>
            </button>
            <button type="button" class="btn-icon btn-eliminar-prov"
                    data-id="<?= (int)$row['id'] ?>" title="Eliminar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
            </button>
        </div>

        <div class="acciones-edicion" style="display:none;">
            <button type="button" class="btn-icon btn-guardar-prov"
                    data-id="<?= (int)$row['id'] ?>" title="Guardar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </button>
            <button type="button" class="btn-icon btn-cancelar-prov" title="Cancelar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </td>
</tr>