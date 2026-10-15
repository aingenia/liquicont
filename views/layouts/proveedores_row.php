<?php if (empty($proveedores)): ?>
    <tr>
        <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
            No se encontraron proveedores que coincidan con la búsqueda.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($proveedores as $p): ?>
        <tr data-id="<?= (int)$p['id'] ?>">
            <td class="cell-title razon-social" data-label="Razón Social">
                <div><?= htmlspecialchars($p['razon_social']) ?></div>
                <?php if (!empty($p['nombre_fantasia']) && $p['nombre_fantasia'] !== $p['razon_social']): ?>
                    <div style="font-size:12px;color:var(--krown-text-muted);font-weight:400;">
                        <?= htmlspecialchars($p['nombre_fantasia']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Documento">
                <?= htmlspecialchars(($p['tipo_doc_desc'] ?? '') . ': ' . ($p['cuit'] ?? '')) ?>
            </td>
            <td data-label="Ubicación"><?= htmlspecialchars($p['localidad'] ?? '—') ?></td>
            <td data-label="IVA"><?= htmlspecialchars($p['condicion_iva_desc'] ?? '—') ?></td>
            <td data-label="Estado">
                <?php if ($p['estado'] === 'ACTIVO'): ?>
                    <span class="badge badge-active">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Inactivo</span>
                <?php endif; ?>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;">
                <a href="<?= BASE_URL ?>proveedores/editar?id=<?= (int)$p['id'] ?>"
                   class="btn-icon" title="Editar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </a>
                <button class="btn-icon btn-eliminar"
                        data-id="<?= (int)$p['id'] ?>"
                        data-nombre="<?= htmlspecialchars($p['razon_social']) ?>"
                        title="Eliminar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    </svg>
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>