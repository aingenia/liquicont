<?php if (empty($emisores)): ?>
    <tr>
        <td colspan="7" style="text-align:center;padding:2rem;color:#666;">
            No hay emisores cargados. Creá el primero con el botón "Nuevo emisor".
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($emisores as $e): ?>
        <tr data-id="<?= (int)$e['id'] ?>">
            <td><strong><?= htmlspecialchars($e['nombre']) ?></strong></td>
            <td>
                <?= htmlspecialchars($e['razon_social']) ?>
                <?php if (!empty($e['nombre_fantasia'])): ?>
                    <br><span style="font-size:12px;color:#7A7266;"><?= htmlspecialchars($e['nombre_fantasia']) ?></span>
                <?php endif; ?>
            </td>
            <td class="mono" style="font-size:13px;"><?= htmlspecialchars($e['cuit']) ?></td>
            <td>
                <div style="font-size:12px;">
                    <?php if ($e['arca_punto_venta']): ?>
                        PV <?= (int)$e['arca_punto_venta'] ?>
                        · <?= htmlspecialchars($e['arca_tipo_comprobante_letra'] ?? '—') ?>
                    <?php else: ?>
                        <span style="color:#7A7266;">Sin configurar</span>
                    <?php endif; ?>
                    <br>
                    <span class="badge <?= $e['arca_entorno'] === 'produccion' ? 'badge-active' : 'badge-warning' ?>" style="font-size:10px;">
                        <?= $e['arca_entorno'] === 'produccion' ? 'Producción' : 'Homologación' ?>
                    </span>
                </div>
            </td>
            <td class="mono" style="font-size:12px;">
                <?= $e['remito_prefijo'] ?: '—' ?>
                <?= str_pad((string)$e['remito_punto_venta'], 4, '0', STR_PAD_LEFT) ?>
                <br>
                <span style="color:#7A7266;">Último: <?= (int)$e['remito_ultimo_nro'] ?></span>
            </td>
            <td>
                <?php if ((int)$e['activo'] === 1): ?>
                    <span class="badge badge-active">Activo</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Inactivo</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;white-space:nowrap;">
                <a href="<?= BASE_URL ?>configuracion/emisor-editar?id=<?= (int)$e['id'] ?>"
                   class="btn-icon" title="Editar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                    </svg>
                </a>
                <button type="button" class="btn-icon btn-toggle-emisor"
                        data-id="<?= (int)$e['id'] ?>"
                        data-activo="<?= (int)$e['activo'] ?>"
                        data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                        title="<?= (int)$e['activo'] === 1 ? 'Desactivar' : 'Activar' ?>">
                    <?php if ((int)$e['activo'] === 1): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                    <?php else: ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    <?php endif; ?>
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>