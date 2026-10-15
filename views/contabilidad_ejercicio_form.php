<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Ejercicio contable';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$editando = !empty($ej);
$accion   = $editando ? 'Editar ejercicio' : 'Nuevo ejercicio';
?>

<div class="page-header">
    <div>
        <h2><?= $accion ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> /
            <a href="<?= BASE_URL ?>contabilidad/ejercicios">Ejercicios</a> /
            <?= $editando ? 'Editar' : 'Nuevo' ?>
        </div>
    </div>
</div>

<?= flashRender() ?>

<form id="form_cont_ejercicio" method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= $editando ? (int)$ej['id'] : 0 ?>">

    <div class="card">
        <div class="card-header">
            <h3>Datos del ejercicio</h3>
            <p>Definí el rango de fechas. Los asientos deben caer dentro de este período.</p>
        </div>
        <div class="card-body">
            <div class="form-group field-full">
                <label for="nombre">Nombre del ejercicio *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="50"
                       placeholder="Ej: 2026, 2025-2026"
                       value="<?= htmlspecialchars($ej['nombre'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="fecha_desde">Fecha desde *</label>
                <input type="date" id="fecha_desde" name="fecha_desde" required
                       value="<?= htmlspecialchars($ej['fecha_desde'] ?? date('Y-01-01')) ?>">
            </div>
            <div class="form-group">
                <label for="fecha_hasta">Fecha hasta *</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" required
                       value="<?= htmlspecialchars($ej['fecha_hasta'] ?? date('Y-12-31')) ?>">
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>contabilidad/ejercicios" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $editando ? 'Guardar cambios' : 'Crear ejercicio' ?>
        </button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>