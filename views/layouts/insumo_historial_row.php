<?php
/**
 * views/layouts/insumo_historial_row.php
 * Variables: $historial (array de filas)
 */
?>
<?php if (empty($historial)): ?>
    <tr>
        <td colspan="5" style="text-align:center; color:#7A7266; padding:1rem;">
            Todavía no hay cambios de costo registrados.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($historial as $h): ?>
        <?php
            $variacion = $h['variacion_pct'];
            $claseVar = '';
            if ($variacion !== null) {
                $claseVar = ((float)$variacion > 0) ? 'variacion-up'
                          : (((float)$variacion) < 0 ? 'variacion-down' : '');
            }
        ?>
        <tr data-hist-id="<?= (int)$h['id'] ?>">
            <td data-label="Fecha" style="font-size:12px;">
                <?= date('d/m/Y H:i', strtotime($h['fecha'])) ?>
            </td>
            <td data-label="Cambio">
                $ <?= number_format((float)$h['valor_anterior'], 2, ',', '.') ?>
                →
                <strong>$ <?= number_format((float)$h['valor_nuevo'], 2, ',', '.') ?></strong>
            </td>
            <td data-label="Variación">
                <?php if ($variacion !== null): ?>
                    <span class="<?= $claseVar ?>">
                        <?= ((float)$variacion > 0 ? '+' : '') . number_format((float)$variacion, 2, ',', '.') ?>%
                    </span>
                <?php else: ?>
                    <span style="color:#7A7266;">—</span>
                <?php endif; ?>
            </td>
            <td data-label="Usuario" style="font-size:12px; color:#7A7266;">
                <?= htmlspecialchars($h['usuario_nombre'] ?? 'Sistema') ?>
            </td>
            <td style="text-align:right; width:40px;">
                <?php if (puedeEditarInsumos()): ?>
                    <button type="button" class="btn-icon btn-anular-hist"
                            data-id="<?= (int)$h['id'] ?>"
                            title="Anular esta entrada">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>