<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Insumo';

$esEdicion    = ($modo === 'editar');
$soloLectura  = !puedeEditarInsumos();
$i = $insumo ?? [];

$val = fn($k, $def = '') => htmlspecialchars($i[$k] ?? $def);
$sel = fn($k, $v)         => ((string)($i[$k] ?? '') === (string)$v) ? 'selected' : '';
$chk = fn($k, $def = 0)   => ((int)($i[$k] ?? $def) === 1) ? 'checked' : '';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esEdicion ? 'Editar insumo' : 'Nuevo insumo' ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>insumos">Insumos</a> /
            <?= $esEdicion ? 'Editar' : 'Nuevo' ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
            data-ayuda-url="<?= BASE_URL ?>insumos/ayuda"
            data-ayuda-titulo="Cómo cargar un insumo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>insumos" class="btn btn-outline">← Volver al listado</a>
    </div>
</div>

<?php if ($soloLectura): ?>
    <div class="msg-box msg-info" style="display:block;margin-bottom:16px;">
        Estás viendo el insumo en modo lectura. Solo los administradores pueden editarlo.
    </div>
<?php endif; ?>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="insumoForm" autocomplete="off" data-solo-lectura="<?= $soloLectura ? '1' : '0' ?>">
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$i['id'] : '' ?>">

    <div class="insumo-grid">

        <!-- COLUMNA IZQUIERDA -->
        <div class="insumo-col-left">
            <div class="card">
                <div class="card-header">
                    <h3>Datos principales</h3>
                    <p>Completá los campos obligatorios (*).</p>
                </div>
                <div class="card-body card-body-stack-1col">

                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" name="codigo"
                               value="<?= $val('codigo') ?>"
                               placeholder="Código interno (opcional)"
                               <?= $soloLectura ? 'disabled' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required
                               value="<?= $val('nombre') ?>"
                               placeholder="Ej: Alcohol etílico 96°"
                               <?= $soloLectura ? 'disabled' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <input type="text" id="descripcion" name="descripcion"
                               value="<?= $val('descripcion') ?>"
                               <?= $soloLectura ? 'disabled' : '' ?>>
                    </div>

                    <div class="insumo-row-3col">
                        <div class="form-group">
                            <label for="unidad_medida_id">Unidad de medida *</label>
                            <select id="unidad_medida_id" name="unidad_medida_id" required
                                    <?= $soloLectura ? 'disabled' : '' ?>>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($combos['unidades_medida'] as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= $sel('unidad_medida_id', $u['id']) ?>>
                                        <?= htmlspecialchars($u['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="medida">Medida por unidad de stock</label>
                            <input type="number" id="medida" name="medida" step="0.001" min="0.001"
                                   value="<?= $val('medida', '1') ?>"
                                   <?= $soloLectura ? 'disabled' : '' ?>>
                            <div class="form-hint" style="margin-top:6px;">Ej: botella 750ml → Mililitro × 750.</div>
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock actual</label>
                            <input type="number" id="stock" step="0.001" disabled
                                   value="<?= $val('stock', '0') ?>">
                        </div>
                    </div>

                    <div class="form-group" style="max-width:250px;">
                        <label for="ppedido">Punto de pedido</label>
                        <input type="number" id="ppedido" name="ppedido" step="0.001" min="0"
                               value="<?= $val('ppedido', '0') ?>"
                               <?= $soloLectura ? 'disabled' : '' ?>>
                        <div class="form-hint" style="margin-top:6px;">Stock mínimo antes de avisar que hay que reponer.</div>
                    </div>

                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA -->
        <div class="insumo-col-right">

            <div class="card">
                <div class="card-header">
                    <h3>Bebida alcohólica / Impuestos internos</h3>
                    <p>El impuesto interno es no recuperable y se suma al costo de adquisición.</p>
                </div>
                <div class="card-body card-body-stack">
                    <label class="insumo-check">
                        <input type="checkbox" id="es_alcoholico" name="es_alcoholico" value="1"
                               <?= $chk('es_alcoholico') ?>
                               <?= $soloLectura ? 'disabled' : '' ?>>
                        Es una bebida alcohólica para envasar
                    </label>

                    <div id="bloqueImpuestosInternos" style="<?= ((int)($i['es_alcoholico'] ?? 0) === 1) ? '' : 'display:none;' ?>">
                        <label class="insumo-check" style="margin-top:10px;">
                            <input type="checkbox" id="aplica_impuestos_internos" name="aplica_impuestos_internos" value="1"
                                   <?= $chk('aplica_impuestos_internos') ?>
                                   <?= $soloLectura ? 'disabled' : '' ?>>
                            Aplica impuestos internos
                        </label>

                        <div class="form-group" style="margin-top:10px;">
                            <label for="alicuota_impuestos_internos">Alícuota sugerida (%)</label>
                            <input type="number" id="alicuota_impuestos_internos" name="alicuota_impuestos_internos"
                                   step="0.01" min="0" max="999.99"
                                   value="<?= $val('alicuota_impuestos_internos', (string)$alicuotaDefault) ?>"
                                   placeholder="Ej: <?= (int)$alicuotaDefault ?>"
                                   <?= $soloLectura ? 'disabled' : '' ?>>
                            <div class="form-hint" style="margin-top:6px;">Valor por defecto: <?= number_format($alicuotaDefault, 2, ',', '.') ?>% (configurable).</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Costo</h3>
                    <p>Cómo se calcula el costo final cuando hay más de un proveedor cargado.</p>
                </div>
                <div class="card-body card-body-stack">
                    <div class="insumo-radios">
                        <label>
                            <input type="radio" name="metodo_costo" value="PROMEDIO"
                                   <?= ((($i['metodo_costo'] ?? 'PROMEDIO') === 'PROMEDIO') ? 'checked' : '') ?>
                                   <?= $soloLectura ? 'disabled' : '' ?>>
                            Promedio entre proveedores
                        </label>
                        <label>
                            <input type="radio" name="metodo_costo" value="MAYOR"
                                   <?= ((($i['metodo_costo'] ?? '') === 'MAYOR') ? 'checked' : '') ?>
                                   <?= $soloLectura ? 'disabled' : '' ?>>
                            El mayor costo cargado
                        </label>
                    </div>

                    <div class="insumo-costo-box">
                        <div class="insumo-costo-label">COSTO ACTUAL (CALCULADO)</div>
                        <div class="insumo-costo-valor" id="costoDisplay">
                            $ <?= number_format((float)($i['costo'] ?? 0), 2, ',', '.') ?>
                        </div>
                    </div>

                    <div class="insumo-costo-desglose" id="costoDesglose"></div>
                </div>
            </div>

            <div class="card" style="margin-bottom:0;">
                <div class="card-body card-body-stack">
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" <?= $soloLectura ? 'disabled' : '' ?>>
                            <option value="ACTIVO"   <?= $sel('estado', 'ACTIVO') ?>>Activo</option>
                            <option value="INACTIVO" <?= $sel('estado', 'INACTIVO') ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /.insumo-grid -->

    <?php if (!$soloLectura): ?>
        <div class="form-actions">
            <a href="<?= BASE_URL ?>insumos" class="btn btn-outline">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <?= $esEdicion ? 'Guardar insumo' : 'Crear insumo' ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- PROVEEDORES Y COSTOS -->
    <div class="card" style="margin-top:20px;">
        <div class="card-header">
            <h3>Proveedores y costos</h3>
            <p>
                <?= $esEdicion
                    ? 'Cargá cada proveedor con el costo al que te vende este insumo.'
                    : 'Guardá el insumo primero para poder asociar proveedores y sus costos.' ?>
            </p>
        </div>
        <div class="card-body card-body-stack-1col">

            <?php if (!$esEdicion): ?>
                <div class="insumo-placeholder">
                    Guardá el insumo primero para poder asociar proveedores.
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table" id="tablaProveedoresInsumo">
                        <thead>
                            <tr>
                                <th>Proveedor</th>
                                <th style="width:140px;">Costo</th>
                                <th style="width:180px;">Código proveedor</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="proveedoresInsumoBody">
                            <?php if (empty($proveedoresDelInsumo)): ?>
                                <tr class="fila-vacia"><td colspan="4" style="text-align:center;color:#7A7266;padding:1.5rem;">
                                    Todavía no hay proveedores cargados.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($proveedoresDelInsumo as $row): ?>
                                    <?php require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_proveedor_row.php'; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$soloLectura): ?>
                    <div class="insumo-add-prov" id="bloqueAgregarProveedor" style="margin-top:14px;">
                        <div class="insumo-add-prov-grid">
                            <div class="form-group">
                                <label for="prov_select">Proveedor *</label>
                                <select id="prov_select">
                                    <option value="">— Seleccione —</option>
                                    <?php foreach ($combos['proveedores'] ?? [] as $prov): ?>
                                        <option value="<?= (int)$prov['id'] ?>">
                                            <?= htmlspecialchars($prov['razon_social']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="prov_costo">Costo *</label>
                                <input type="number" id="prov_costo" step="0.0001" min="0">
                            </div>
                            <div class="form-group">
                                <label for="prov_codigo">Código del proveedor</label>
                                <input type="text" id="prov_codigo" placeholder="Opcional">
                            </div>
                            <div class="form-group" style="flex:0 0 auto;">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary" id="btnAgregarProveedor">
                                    + Agregar proveedor
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- HISTORIAL DE CAMBIOS DE COSTO -->
    <?php if ($esEdicion && puedeEditarInsumos()): ?>
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h3>Historial de cambios de costo</h3>
                <p>
                    Últimos 15 movimientos. Los valores que se muestran son el
                    <strong>costo base (sin impuestos internos)</strong>.
                    El costo final se calcula sumando el impuesto configurado.
                </p>
            </div>
            <div class="card-body card-body-stack">
                <div class="table-wrap">
                    <table class="data-table" id="tablaHistorialCosto">
                        <thead>
                            <tr>
                                <th style="width:140px;">Fecha</th>
                                <th>Cambio (costo base)</th>
                                <th style="width:100px;">Variación</th>
                                <th style="width:150px;">Usuario</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="historialCostoBody">
                            <tr><td colspan="5" style="text-align:center;padding:1rem;color:#7A7266;">
                                Cargando...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>