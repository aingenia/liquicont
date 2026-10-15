<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Proveedor';

$esEdicion = ($modo === 'editar');
$p = $proveedor ?? [];

$val = fn($k, $def = '') => htmlspecialchars($p[$k] ?? $def);

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esEdicion ? 'Editar proveedor' : 'Nuevo proveedor' ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>proveedores">Proveedores</a> /
            <?= $esEdicion ? 'Editar' : 'Crear' ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>proveedores" class="btn btn-outline">← Volver al listado</a>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="proveedorForm" autocomplete="off">
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$p['id'] : '' ?>">

    <!-- CARD 1: DATOS PRINCIPALES -->
    <div class="card">
        <div class="card-header">
            <h3>Datos principales</h3>
            <p>Completá los campos obligatorios (*). Usá el botón ARCA para autocompletar a partir del CUIT.</p>
        </div>
        <div class="card-body">

            <div class="form-group">
                <label for="tipo_documento_id">Tipo de documento</label>
                <select id="tipo_documento_id" name="tipo_documento_id" required>
                    <?php foreach ($combos['tipos_documento'] as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"
                            <?= (int)($p['tipo_documento_id'] ?? 1) === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="cuit">Número</label>
                <div class="input-group">
                    <input type="text" id="cuit" name="cuit" required value="<?= $val('cuit') ?>">
                    <button type="button" class="btn-arca" id="btnArca">ARCA</button>
                </div>
            </div>

            <div class="form-group field-full">
                <label for="razon_social">Razón social / Nombre *</label>
                <input type="text" id="razon_social" name="razon_social" required value="<?= $val('razon_social') ?>">
            </div>

            <div class="form-group">
                <label for="nombre_fantasia">Nombre de fantasía</label>
                <input type="text" id="nombre_fantasia" name="nombre_fantasia" value="<?= $val('nombre_fantasia') ?>">
            </div>

            <div class="form-group">
                <label for="codigo">Código</label>
                <input type="text" id="codigo" name="codigo" value="<?= $val('codigo') ?>" placeholder="Código interno (opcional)">
            </div>

            <div class="form-group">
                <label for="personeria">Personería *</label>
                <select id="personeria" name="personeria" required>
                    <option value="FISICA"   <?= ($p['personeria'] ?? 'FISICA') === 'FISICA'   ? 'selected' : '' ?>>Física</option>
                    <option value="JURIDICA" <?= ($p['personeria'] ?? '')       === 'JURIDICA' ? 'selected' : '' ?>>Jurídica</option>
                </select>
            </div>

            <div class="form-group">
                <label for="id_condicion_iva">Categoría impositiva *</label>
                <select id="id_condicion_iva" name="id_condicion_iva" required>
                    <?php foreach ($combos['condiciones_iva'] as $iv): ?>
                        <option value="<?= (int)$iv['id'] ?>"
                            <?= (int)($p['id_condicion_iva'] ?? 0) === (int)$iv['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($iv['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <option value="ACTIVO"   <?= ($p['estado'] ?? 'ACTIVO') === 'ACTIVO'   ? 'selected' : '' ?>>Activo</option>
                    <option value="INACTIVO" <?= ($p['estado'] ?? '')       === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= $val('email') ?>" placeholder="ejemplo@ejemplo.com">
            </div>

            <div class="form-group">
                <label for="email_facturacion">Email facturación</label>
                <input type="text" id="email_facturacion" name="email_facturacion" value="<?= $val('email_facturacion') ?>" placeholder="ejemplo@ejemplo.com">
                <div class="form-hint">Si querés más de una dirección, separalas con coma.</div>
            </div>

        </div>
    </div>

    <!-- CARD 2: DATOS FISCALES Y CONTACTO -->
    <div class="card">
        <div class="card-header">
            <h3>Datos fiscales y de contacto</h3>
            <p>Se completan solos si usás el botón ARCA.</p>
        </div>
        <div class="card-body">

            <div class="form-group">
                <label for="id_condicion_iibb">Condición IIBB</label>
                <select id="id_condicion_iibb" name="id_condicion_iibb">
                    <option value="">— Ninguna —</option>
                    <?php foreach ($combos['condiciones_iibb'] as $ib): ?>
                        <option value="<?= (int)$ib['id'] ?>"
                            <?= (int)($p['id_condicion_iibb'] ?? 0) === (int)$ib['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ib['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nro_inscripcion_iibb">Nro. inscripción IIBB</label>
                <input type="text" id="nro_inscripcion_iibb" name="nro_inscripcion_iibb" value="<?= $val('nro_inscripcion_iibb') ?>">
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" value="<?= $val('telefono') ?>">
            </div>

            <div class="form-group field-full">
                <label for="domicilio_fiscal">Domicilio fiscal</label>
                <input type="text" id="domicilio_fiscal" name="domicilio_fiscal" value="<?= $val('domicilio_fiscal') ?>">
            </div>

            <div class="form-group">
                <label for="localidad">Localidad</label>
                <input type="text" id="localidad" name="localidad" value="<?= $val('localidad') ?>">
            </div>

            <div class="form-group">
                <label for="provincia_id">Provincia</label>
                <select id="provincia_id" name="provincia_id">
                    <option value="">— Seleccione —</option>
                    <?php foreach ($combos['provincias'] as $pr): ?>
                        <option value="<?= (int)$pr['id'] ?>"
                            <?= (int)($p['provincia_id'] ?? 0) === (int)$pr['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pr['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>proveedores" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $esEdicion ? 'Guardar proveedor' : 'Crear proveedor' ?>
        </button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>