<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Configuración contable';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

// Agrupar claves por área funcional para mostrarlas ordenadas
$grupos = [
    'Ventas' => [
        'cta_ventas',
        'cta_deudores_ventas',
        'cta_iva_debito',
        'cta_impuestos_internos',
    ],
    'Compras' => [
        'cta_compras_insumos',
        'cta_compras_envases',
        'cta_fraccionamiento',
        'cta_fletes',
        'cta_otros_gastos',
        'cta_proveedores',
        'cta_iva_credito',
    ],
    'Tesorería' => [
        'cta_caja',
        'cta_banco',
        'cta_cheques_cartera',
        'cta_cheques_diferidos',
    ],
    'Retenciones' => [
        'cta_ret_iva',
        'cta_ret_iibb',
        'cta_ret_ganancias',
        'cta_ret_a_depositar',
    ],
    'Cierre' => [
        'cta_resultado_ejercicio',
    ],
];

// Indexar config por clave para acceso rápido
$conf = [];
foreach ($config as $c) {
    $conf[$c['clave']] = $c;
}

$cantFaltantes = count($faltantes);
?>

<div class="page-header">
    <div>
        <h2>Configuración contable</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Configuración
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
    </div>
</div>

<?= flashRender() ?>

<?php if ($cantFaltantes > 0): ?>
    <div class="msg-box msg-info" style="display:block;margin-bottom:16px;">
        <strong>Faltan <?= $cantFaltantes ?> cuenta(s) por asignar.</strong>
        Mientras haya claves sin cuenta, las operaciones (facturas, compras, etc.)
        se guardan normalmente pero <strong>no generan asiento contable</strong>.
    </div>
<?php else: ?>
    <div class="msg-box msg-ok" style="display:block;margin-bottom:16px;">
        ✓ La configuración está completa. Los asientos automáticos se están generando.
    </div>
<?php endif; ?>

<form id="form_cont_configuracion" method="post" autocomplete="off">
    <?php foreach ($grupos as $titulo => $claves): ?>
        <div class="cont-card">
            <div class="cont-card-header">
                <div>
                    <h3><?= htmlspecialchars($titulo) ?></h3>
                </div>
            </div>
            <div class="cont-card-body">
                <div class="cont-config-grid">
                    <?php foreach ($claves as $clave):
                        $c = $conf[$clave] ?? null;
                        if (!$c) continue;
                    ?>
                        <div class="form-group">
                            <label for="cfg_<?= htmlspecialchars($clave) ?>">
                                <?= htmlspecialchars($c['descripcion'] ?: $clave) ?>
                            </label>
                            <select id="cfg_<?= htmlspecialchars($clave) ?>"
                                    name="cuentas[<?= htmlspecialchars($clave) ?>]">
                                <option value="">— Sin asignar —</option>
                                <?php foreach ($cuentasImputables as $cta): ?>
                                    <option value="<?= (int)$cta['id'] ?>"
                                        <?= ((int)$cta['id'] === (int)$c['cuenta_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cta['codigo'] . ' · ' . $cta['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint" style="margin-top:4px;display:block;">
                                clave: <code><?= htmlspecialchars($clave) ?></code>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>contabilidad" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar configuración</button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>