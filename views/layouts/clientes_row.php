<?php if (empty($clientes)): ?>
    <tr>
        <td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
            No se encontraron clientes que coincidan con la búsqueda.
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($clientes as $c): ?>
        <tr data-id="<?= (int)$c['id'] ?>">
            <td class="cell-title razon-social" data-label="Razón Social">
                <div><?= htmlspecialchars($c['razon_social']) ?></div>
                <?php if (!empty($c['nombre_fantasia']) && $c['nombre_fantasia'] !== $c['razon_social']): ?>
                    <div style="font-size:12px;color:var(--krown-text-muted);font-weight:400;">
                        <?= htmlspecialchars($c['nombre_fantasia']) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td data-label="Categoría"><?= htmlspecialchars($c['categoria_desc'] ?? '—') ?></td>
            <td data-label="Documento">
                <?= htmlspecialchars(($c['tipo_doc_desc'] ?? '') . ': ' . ($c['cuit'] ?? '')) ?>
            </td>
            <td data-label="Ubicación"><?= htmlspecialchars($c['localidad'] ?? '—') ?></td>
            <td data-label="IVA"><?= htmlspecialchars($c['condicion_iva_desc'] ?? '—') ?></td>
            <td data-label="Vendedor"><?= htmlspecialchars($c['vendedor_nombre'] ?? 'Sin asignar') ?></td>
            <td data-label="Estado">
                <?php if ($c['estado'] === 'ACTIVO'): ?>
                    <span class="badge badge-active">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Inactivo</span>
                <?php endif; ?>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;">
                <a href="<?= BASE_URL ?>clientes/editar?id=<?= (int)$c['id'] ?>"
                   class="btn-icon" title="Editar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </a>
                <?php if (puedeVerTodosLosClientes()): ?>
                    <button class="btn-icon btn-eliminar"
                            data-id="<?= (int)$c['id'] ?>"
                            data-nombre="<?= htmlspecialchars($c['razon_social']) ?>"
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