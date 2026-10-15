<?php
/**
 * Partial recursivo: dibuja un nodo del plan de cuentas y sus hijos.
 * Requiere: $nodo (array con id, codigo, nombre, tipo, estado, imputable, hijos)
 */
$tieneHijos = !empty($nodo['hijos']);
$clasePadre = $nodo['imputable'] ? '' : ' no-imputable';
?>
<li data-id="<?= (int)$nodo['id'] ?>">
    <div class="cont-tree-node<?= $clasePadre ?>">
        <?php if ($tieneHijos): ?>
            <button type="button" class="cont-tree-toggle" aria-label="Expandir">▾</button>
        <?php else: ?>
            <span class="cont-tree-toggle oculto">·</span>
        <?php endif; ?>

        <span class="cont-tree-codigo"><?= htmlspecialchars($nodo['codigo']) ?></span>
        <span class="cont-tree-nombre">
            <?= htmlspecialchars($nodo['nombre']) ?>
            <?php if ($nodo['estado'] === 'INACTIVA'): ?>
                <span class="badge badge-inactive" style="margin-left:8px;">Inactiva</span>
            <?php endif; ?>
        </span>
        <span class="cont-tree-tipo t-<?= htmlspecialchars($nodo['tipo']) ?>">
            <?= htmlspecialchars(str_replace('_', ' ', $nodo['tipo'])) ?>
        </span>

        <div class="cont-tree-acciones">
            <a href="<?= BASE_URL ?>contabilidad/cuenta-form?id=<?= (int)$nodo['id'] ?>"
               class="btn-icon" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"/>
                </svg>
            </a>
            <button type="button"
                    class="btn-icon btn-cont-toggle-cuenta"
                    data-id="<?= (int)$nodo['id'] ?>"
                    data-estado="<?= htmlspecialchars($nodo['estado']) ?>"
                    title="<?= $nodo['estado'] === 'ACTIVA' ? 'Desactivar' : 'Activar' ?>">
                <?php if ($nodo['estado'] === 'ACTIVA'): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                    </svg>
                <?php else: ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <?php if ($tieneHijos): ?>
        <ul class="cont-tree-children">
            <?php foreach ($nodo['hijos'] as $hijo): ?>
                <?php
                    $nodo = $hijo; // reasignamos para el include recursivo
                    require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'contabilidad_tree_node.php';
                ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>