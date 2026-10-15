<?php if (empty($articulos)): ?>
    <tr><td colspan="7" style="text-align:center;padding:2rem;color:#666;">
        No se encontraron artículos.
    </td></tr>
<?php else: ?>
    <?php foreach ($articulos as $a): ?>
        <tr data-id="<?= (int)$a['id'] ?>">
            <td class="cell-title" data-label="Artículo">
                <div style="display:flex;align-items:center;gap:12px;">
                    <?php
                    // Verificar que el archivo exista en disco antes de renderizar
                    $imgPath = $a['imagen_path'] ?? '';
                    $imgAbs  = $imgPath !== '' ? ROOT_PATH . str_replace('/', DS, $imgPath) : '';
                    $tieneImagen = $imgPath !== '' && is_file($imgAbs);
                    ?>
                    <?php if ($tieneImagen): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($imgPath) ?>"
                            alt=""
                            class="art-thumb"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="art-thumb art-thumb-empty" style="display:none;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <path d="m21 15-5-5L5 21"/>
                            </svg>
                        </div>
                    <?php else: ?>
                        <div class="art-thumb art-thumb-empty">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <path d="m21 15-5-5L5 21"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="razon-social"><?= htmlspecialchars($a['nombre']) ?></div>
                        <?php if (!empty($a['codigo'])): ?>
                            <div style="font-size:12px;color:var(--krown-text-muted);">
                                Código: <?= htmlspecialchars($a['codigo']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td data-label="Insumos">
                <?php if ((int)$a['insumos_count'] > 0): ?>
                    <?= (int)$a['insumos_count'] ?> insumo(s)
                <?php else: ?>
                    <span style="color:#7A7266;">Sin fórmula</span>
                <?php endif; ?>
            </td>
            <td data-label="Costo">$ <?= number_format((float)$a['costo'], 2, ',', '.') ?></td>
            <td data-label="Stock"><?= (int)$a['stock'] ?></td>
            <td data-label="Pto. pedido"><?= (int)$a['ppedido'] ?></td>
            <td data-label="Estado">
                <?php if ($a['estado'] === 'ACTIVO'): ?>
                    <span class="badge badge-active">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Inactivo</span>
                <?php endif; ?>
            </td>
            <td class="col-acciones" data-label="Acciones" style="text-align:right;">
                <a href="<?= BASE_URL ?>articulos/editar?id=<?= (int)$a['id'] ?>"
                   class="btn-icon" title="Editar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </a>
                <button class="btn-icon btn-eliminar"
                        data-id="<?= (int)$a['id'] ?>"
                        data-nombre="<?= htmlspecialchars($a['nombre']) ?>"
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