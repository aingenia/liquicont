<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Plan de cuentas';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Plan de cuentas</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Plan de cuentas
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>contabilidad/ayuda"
                data-ayuda-titulo="Ayuda — Contabilidad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>contabilidad/cuenta-form" class="btn btn-primary">
            + Nueva cuenta
        </a>
    </div>
</div>

<?= flashRender() ?>

<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Árbol jerárquico</h3>
            <p>Hacé click en ▾ para expandir. Las cuentas <strong>imputables</strong> (último nivel) son las que reciben asientos.</p>
        </div>
    </div>
    <div class="cont-card-body">
        <?php if (empty($arbol)): ?>
            <p style="text-align:center;color:var(--krown-text-muted);padding:30px 0;">
                No hay cuentas cargadas.
            </p>
        <?php else: ?>
            <div class="cont-tree">
                <ul>
                    <?php foreach ($arbol as $raiz): ?>
                        <?php
                            $nodo = $raiz;
                            require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'contabilidad_tree_node.php';
                        ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>