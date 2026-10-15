<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Cuenta contable';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$editando = !empty($cta);
$accion   = $editando ? 'Editar cuenta' : 'Nueva cuenta';

// Tipos y naturalezas válidas
$tipos = [
    'ACTIVO'              => 'Activo',
    'PASIVO'              => 'Pasivo',
    'PATRIMONIO'          => 'Patrimonio Neto',
    'RESULTADO_POSITIVO'  => 'Resultado Positivo (Ganancia)',
    'RESULTADO_NEGATIVO'  => 'Resultado Negativo (Pérdida)',
];
$naturalezas = ['DEUDORA' => 'Deudora', 'ACREEDORA' => 'Acreedora'];

// Excluir del select de padres: la propia cuenta (al editar)
$padresDisponibles = array_filter($padres ?? [], function ($p) use ($cta) {
    return !$editando || (int)$p['id'] !== (int)$cta['id'];
});
?>

<div class="page-header">
    <div>
        <h2><?= $accion ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> /
            <a href="<?= BASE_URL ?>contabilidad/plan-cuentas">Plan de cuentas</a> /
            <?= $editando ? 'Editar' : 'Nueva' ?>
        </div>
    </div>
</div>

<?= flashRender() ?>

<form id="form_cont_cuenta" method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= $editando ? (int)$cta['id'] : 0 ?>">

    <div class="card">
        <div class="card-header">
            <h3>Datos de la cuenta</h3>
            <p>El código se suele escribir de forma jerárquica (ej: <code>1.1.03</code>).</p>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="codigo">Código *</label>
                <input type="text" id="codigo" name="codigo" required maxlength="30"
                       placeholder="Ej: 1.1.03"
                       value="<?= htmlspecialchars($cta['codigo'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="orden">Orden (opcional)</label>
                <input type="number" id="orden" name="orden" min="0" step="1"
                       value="<?= (int)($cta['orden'] ?? 0) ?>">
            </div>

            <div class="form-group field-full">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="150"
                       placeholder="Ej: Banco Galicia Cta Cte"
                       value="<?= htmlspecialchars($cta['nombre'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="tipo">Tipo *</label>
                <select id="tipo" name="tipo" required>
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($tipos as $k => $label): ?>
                        <option value="<?= $k ?>"
                            <?= ($editando && $cta['tipo'] === $k) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="naturaleza">Naturaleza *</label>
                <select id="naturaleza" name="naturaleza" required>
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($naturalezas as $k => $label): ?>
                        <option value="<?= $k ?>"
                            <?= ($editando && $cta['naturaleza'] === $k) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group field-full">
                <label for="padre_id">Cuenta padre (opcional)</label>
                <select id="padre_id" name="padre_id">
                    <option value="">— Sin padre (cuenta raíz) —</option>
                    <?php foreach ($padresDisponibles as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                            <?= ($editando && (int)$cta['padre_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['codigo'] . ' · ' . $p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group field-full">
                <label style="display:flex;align-items:center;gap:8px;text-transform:none;font-size:14px;color:var(--liqui-text);font-weight:500;letter-spacing:0;">
                    <input type="checkbox" name="imputable" value="1"
                        <?= (!$editando || (int)$cta['imputable'] === 1) ? 'checked' : '' ?>>
                    Es imputable (puede recibir asientos)
                </label>
                <p class="form-hint" style="margin-top:6px;">
                    Desmarcá si es una cuenta de <strong>título</strong> (solo agrupa otras cuentas).
                </p>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>contabilidad/plan-cuentas" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $editando ? 'Guardar cambios' : 'Crear cuenta' ?>
        </button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>