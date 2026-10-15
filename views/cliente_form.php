<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Cliente';

$esEdicion = ($modo === 'editar');
$esVend    = esVendedor();  // vendedor puro
$c = $cliente ?? [];

// Helper local para repoblar inputs
$val = fn($k, $def = '') => htmlspecialchars($c[$k] ?? $def);

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esEdicion ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>clientes">Clientes</a> /
            <?= $esEdicion ? 'Editar' : 'Crear' ?>
        </div>
    </div>
    <a href="<?= BASE_URL ?>clientes" class="btn btn-outline">← Volver al listado</a>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="clienteForm" autocomplete="off">
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$c['id'] : '' ?>">

    <!-- ================= CARD 1: DATOS PRINCIPALES ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Datos principales</h3>
            <p>Completá los campos obligatorios (*). Usá el botón ARCA para autocompletar a partir del CUIT.</p>
        </div>
        <div class="card-body">
             <div class="form-group">
                <label for="codigo">Código interno</label>
                <input type="text" id="codigo" name="codigo"
                       value="<?= $val('codigo') ?>"
                       placeholder="Código interno (opcional)">
            </div>

            <div class="form-group">
                <label for="personeria">Personería *</label>
                <select id="personeria" name="personeria" required>
                    <option value="FISICA"   <?= ($c['personeria'] ?? 'FISICA') === 'FISICA'   ? 'selected' : '' ?>>Física</option>
                    <option value="JURIDICA" <?= ($c['personeria'] ?? '')       === 'JURIDICA' ? 'selected' : '' ?>>Jurídica</option>
                </select>
            </div>

            <div class="form-group">
                <label for="tipo_documento_id">Tipo de documento</label>
                <select id="tipo_documento_id" name="tipo_documento_id" required>
                    <?php foreach ($combos['tipos_documento'] as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"
                            <?= (int)($c['tipo_documento_id'] ?? 1) === (int)$t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="cuit">Número</label>
                <div class="input-group">
                    <input type="text" id="cuit" name="cuit" required
                           value="<?= $val('cuit') ?>"
                           placeholder="30162490330">
                    <button type="button" id="btnArca" class="btn btn-arca">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        ARCA
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="razon_social">Razón social / Nombre *</label>
                <input type="text" id="razon_social" name="razon_social" required
                       value="<?= $val('razon_social') ?>">
            </div>

            <div class="form-group">
                <label for="nombre_fantasia">Nombre de fantasía</label>
                <input type="text" id="nombre_fantasia" name="nombre_fantasia"
                       value="<?= $val('nombre_fantasia') ?>">
            </div>

            <div class="form-group">
                <label for="categoria">Categoría impositiva</label>
                <select id="categoria" name="categoria" required <?= $esVend ? 'disabled' : '' ?>>
                    <?php foreach ($combos['categorias'] as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"
                            <?= (int)($c['categoria'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($esVend): ?>
                    <input type="hidden" name="categoria" value="<?= (int)($c['categoria'] ?? 3) ?>">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="id_condicion_iva">Categoría IVA *</label>
                <select id="id_condicion_iva" name="id_condicion_iva" required>
                    <?php foreach ($combos['condiciones_iva'] as $iv): ?>
                        <option value="<?= (int)$iv['id'] ?>"
                            <?= (int)($c['id_condicion_iva'] ?? 0) === (int)$iv['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($iv['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

             <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= $val('email') ?>">
            </div>

            <div class="form-group">
                <label for="email_facturacion">Email facturación</label>
                <input type="text" id="email_facturacion" name="email_facturacion"
                       value="<?= $val('email_facturacion') ?>"
                       placeholder="ejemplo@ejemplo.com">
                <div class="form-hint" style="margin-top: 6px;">Si querés más de una dirección, separalas con coma.</div>
            </div>

        </div>
    </div>

    <!-- ================= CARD 2: DATOS FISCALES Y CONTACTO ================= -->
    <div class="card">
        <div class="card-header">
            <h3>Datos fiscales y de contacto</h3>
            <p>Se completan solos si usás el botón ARCA.</p>
        </div>
        <div class="card-body">

            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" value="<?= $val('telefono') ?>">
            </div>

            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required <?= $esVend ? 'disabled' : '' ?>>
                    <option value="ACTIVO"   <?= ($c['estado'] ?? 'ACTIVO') === 'ACTIVO'   ? 'selected' : '' ?>>Activo</option>
                    <option value="INACTIVO" <?= ($c['estado'] ?? '')       === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <?php if ($esVend): ?>
                    <input type="hidden" name="estado" value="<?= htmlspecialchars($c['estado'] ?? 'ACTIVO') ?>">
                <?php endif; ?>
            </div>


            <div class="form-group">
                <label for="id_condicion_iibb">Condición IIBB</label>
                <select id="id_condicion_iibb" name="id_condicion_iibb">
                    <option value="">— Ninguna —</option>
                    <?php foreach ($combos['condiciones_iibb'] as $ib): ?>
                        <option value="<?= (int)$ib['id'] ?>"
                            <?= (int)($c['id_condicion_iibb'] ?? 0) === (int)$ib['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ib['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nro_inscripcion_iibb">Nro. inscripción IIBB</label>
                <input type="text" id="nro_inscripcion_iibb" name="nro_inscripcion_iibb"
                       value="<?= $val('nro_inscripcion_iibb') ?>">
            </div>

            <div class="form-group">
                <label for="domicilio_fiscal">Domicilio fiscal</label>
                <input type="text" id="domicilio_fiscal" name="domicilio_fiscal"
                       value="<?= $val('domicilio_fiscal') ?>">
            </div>

            <div class="form-group">
                <label for="domicilio_entrega">Domicilio entrega</label>
                <input type="text" id="domicilio_entrega" name="domicilio_entrega"
                       value="<?= $val('domicilio_entrega') ?>">
            </div>

            <div class="form-group">
                <label for="localidad">Localidad</label>
                <input type="text" id="localidad" name="localidad" value="<?= $val('localidad') ?>">
            </div>

            <div class="form-group">
                <label for="provincia_id">Provincia</label>
                <select id="provincia_id" name="provincia_id">
                    <option value="">— Seleccione —</option>
                    <?php foreach ($combos['provincias'] as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                            <?= (int)($c['provincia_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="condicion_venta">Condición de venta</label>
                <select id="condicion_venta" name="condicion_venta">
                    <option value="CONTADO"          <?= ($c['condicion_venta'] ?? 'CONTADO') === 'CONTADO'          ? 'selected' : '' ?>>Contado</option>
                    <option value="CUENTA_CORRIENTE" <?= ($c['condicion_venta'] ?? '')       === 'CUENTA_CORRIENTE' ? 'selected' : '' ?>>Cuenta corriente</option>
                </select>
            </div>

            <div class="form-group">
                <label for="limite_credito">Límite crédito</label>
                <input type="number" id="limite_credito" name="limite_credito" step="0.01" min="0"
                       value="<?= htmlspecialchars($c['limite_credito'] ?? '0.00') ?>">
            </div>

            <div class="form-group field-full">
                <label for="vendedor_id">Vendedor asignado</label>
                <select id="vendedor_id" name="vendedor_id" <?= $esVend ? 'disabled' : '' ?>>
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($combos['vendedores'] as $v): ?>
                        <option value="<?= (int)$v['id'] ?>"
                            <?= (int)($c['vendedor_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($esVend): ?>
                    <input type="hidden" name="vendedor_id" value="<?= (int)$_SESSION['user_id'] ?>">
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>clientes" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $esEdicion ? 'Guardar cliente' : 'Crear cliente' ?>
        </button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>