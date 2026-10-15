<?php
/**
 * Variable esperada: $row  (['insumo_id','insumo_nombre','cantidad','costo_unitario','unidad_abrev'])
 *                    $index (número de fila, opcional)
 */
?>
<tr data-index="<?= (int)($index ?? 0) ?>" data-insumo-id="<?= (int)$row['insumo_id'] ?>">
    <td data-label="Insumo">
        <strong><?= htmlspecialchars($row['insumo_nombre']) ?></strong>
        <?php if (!empty($row['insumo_codigo'])): ?>
            <br><span style="font-size:12px;color:var(--liqui-text-muted);">Cód: <?= htmlspecialchars($row['insumo_codigo']) ?></span>
        <?php endif; ?>
    </td>
    <td data-label="Cantidad" style="text-align:right;">
        <?= number_format((float)$row['cantidad'], 3, ',', '.') ?>
        <?= htmlspecialchars($row['unidad_abrev'] ?? '') ?>
    </td>
    <td data-label="Costo unit." style="text-align:right;">
        $ <?= number_format((float)$row['costo_unitario'], 2, ',', '.') ?>
    </td>
    <td data-label="Subtotal" style="text-align:right;font-weight:600;">
        $ <?= number_format((float)$row['cantidad'] * (float)$row['costo_unitario'], 2, ',', '.') ?>
    </td>
    <td style="text-align:right;width:50px;">
        <button type="button" class="btn-icon btn-quitar-linea" data-insumo-id="<?= (int)$row['insumo_id'] ?>" title="Quitar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6 6 18M6 6l12 12"/>
            </svg>
        </button>
    </td>
</tr>