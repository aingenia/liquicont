<?php if (empty($usuarios)): ?>
    <tr>
        <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
            No se encontraron usuarios que coincidan con la búsqueda.
        </td>
    </tr>
<?php else: ?>     
    <?php foreach ($usuarios as $u): ?>
        <tr data-id="<?= (int)$u['id'] ?>">
            <td class="cell-title razon-social" data-label="Nombre">
                <?= htmlspecialchars($u['nombre']) ?>
            </td>
            <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
            <td data-label="Teléfono"><?= htmlspecialchars($u['tel'] ?? '—') ?></td>
            <td data-label="Rol"><?= htmlspecialchars($u['nombre_rol']) ?></td>
            <td data-label="Estado">
                <?php if ((int)$u['estado'] === 1): ?>
                    <span class="badge badge-active">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Inactivo</span>
                <?php endif; ?>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;">
                <a href="<?= BASE_URL ?>usuarios/editar?id=<?= (int)$u['id'] ?>"
                    class="btn-icon" title="Editar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </a>
                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <button class="btn-icon btn-eliminar"
                            data-id="<?= (int)$u['id'] ?>"
                            data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
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