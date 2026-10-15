<?php
/**
 * views/layouts/articulo_formula_row.php
 * Variable: $row (fila de formula con JOIN a insumo)
 */
?>
<tr data-row-id="<?= (int)$row['id'] ?>">
    <td data-label="Insumo" class="cell-insumo">
        <div style="font-weight:600;"><?= htmlspecialchars($row['insumo_nombre']) ?></div>
        <?php if (!empty($row['insumo_codigo'])): ?>
            <div style="font-size:12px;color:var(--krown-text-muted);">
                Código: <?= htmlspecialchars($row['insumo_codigo']) ?>
            </div>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--krown-text-muted);">
            Costo unitario: $ <?= number_format((float)$row['insumo_costo'], 2, ',', '.') ?> / <?= htmlspecialchars($row['unidad_abrev']) ?>
        </div>
    </td>
    <td data-label="Cantidad" class="cell-cantidad">
        <span class="valor">
            <?= number_format((float)$row['cantidad'], 3, ',', '.') ?>
            <?= htmlspecialchars($row['unidad_abrev']) ?>
        </span>
        <input type="number" class="editor" step="0.001" min="0.001"
               value="<?= htmlspecialchars((string)$row['cantidad']) ?>"
               style="display:none; width:120px;">
    </td>
    <td data-label="Subtotal" class="cell-subtotal" style="font-weight:600;">
        $ <?= number_format((float)$row['cantidad'] * (float)$row['insumo_costo'], 2, ',', '.') ?>
    </td>
    <td class="col-acciones" data-label="" style="text-align:right; white-space:nowrap;">
        <div class="acciones-normal">
            <button type="button" class="btn-icon btn-editar-formula" title="Editar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                </svg>
            </button>
            <button type="button" class="btn-icon btn-eliminar-formula"
                    data-id="<?= (int)$row['id'] ?>" title="Eliminar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                </svg>
            </button>
        </div>
        <div class="acciones-edicion" style="display:none;">
            <button type="button" class="btn-icon btn-guardar-formula" data-id="<?= (int)$row['id'] ?>" title="Guardar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </button>
            <button type="button" class="btn-icon btn-cancelar-formula" title="Cancelar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </td>
</tr>