<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Artículo';

$esEdicion = ($modo === 'editar');
$a = $articulo ?? [];
$val = fn($k, $def = '') => htmlspecialchars($a[$k] ?? $def);
$chk = fn($k, $def = 0)   => ((int)($a[$k] ?? $def) === 1) ? 'checked' : '';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2><?= $esEdicion ? 'Editar artículo' : 'Nuevo artículo' ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>articulos">Artículos</a> /
            <?= $esEdicion ? 'Editar' : 'Nuevo' ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>articulos/ayuda"
                data-ayuda-titulo="Cómo cargar un artículo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>articulos" class="btn btn-outline">← Volver al listado</a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="articuloForm" autocomplete="off" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $esEdicion ? (int)$a['id'] : '' ?>">

    <div class="art-grid">

        <!-- COLUMNA IZQUIERDA -->
        <div class="art-col-left">

            <div class="card">
                <div class="card-header">
                    <h3>Datos principales</h3>
                    <p>Completá los campos obligatorios (*).</p>
                </div>
                <div class="card-body card-body-stack">

                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" name="codigo" value="<?= $val('codigo') ?>"
                               placeholder="Código interno (opcional)">
                    </div>

                    <div class="art-row-2col">
                        <div class="form-group">
                            <label for="formato">Formato</label>
                            <input type="text" id="formato" name="formato" maxlength="20"
                                   value="<?= $val('formato') ?>"
                                   placeholder="Ej: 200, 750">
                            <div class="form-hint" style="margin-top: 8px;">Los dígitos iniciales del código. Se usa para agrupar en la Lista de Precios y Estadísticas.</div>
                        </div>
                        <div class="form-group">
                            <label for="sabor">Sabor</label>
                            <input type="text" id="sabor" name="sabor" maxlength="50"
                                   value="<?= $val('sabor') ?>"
                                   placeholder="Ej: CC, VL, W">
                            <div class="form-hint" style="margin-top: 8px;">El resto del código (letras). Se usa en el Share de Sabores.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required value="<?= $val('nombre') ?>">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="3" maxlength="255" data-maxlength="255" placeholder="Ej: Un clásico italiano reinventado. Frescura cítrica y dulzura equilibrada.">
                            <?= $val('descripcion') ?>
                        </textarea>
                    </div>

                    <div class="art-row-2col">
                        <div class="form-group">
                            <label for="ppedido">Punto de pedido</label>
                            <input type="number" id="ppedido" name="ppedido" min="0"
                                   value="<?= $val('ppedido', '0') ?>">
                        </div>
                        <div class="form-group">
                            <label for="pakaging_id">Empaque (opcional)</label>
                            <select id="pakaging_id" name="pakaging_id">
                                <option value="">— Solo unidad —</option>
                                <?php foreach ($combos['packagings'] as $pk): ?>
                                    <option value="<?= (int)$pk['id'] ?>"
                                        <?= (int)($a['pakaging_id'] ?? 0) === (int)$pk['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pk['descripcion']) ?>
                                        (<?= (int)$pk['cantidad'] ?> unidades)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint" style="margin-top: 8px;">Si el artículo se vende en cajas, elegí el tipo. Sino dejalo en "Solo unidad".</div>
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado">
                                <option value="ACTIVO"   <?= ($a['estado'] ?? 'ACTIVO') === 'ACTIVO'   ? 'selected' : '' ?>>Activo</option>
                                <option value="INACTIVO" <?= ($a['estado'] ?? '')       === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA -->
        <div class="art-col-right">
             <div class="card">
            <div class="card-header">
                    <h3>Imagen del artículo</h3>
                    <p>Se recorta a 600×600 píxeles. Formatos: JPG, PNG o WebP (máx 2 MB).</p>
                </div>
                <div class="card-body card-body-stack">
                    <?php if (!$esEdicion): ?>
                        <div class="art-placeholder">Guardá el artículo primero para poder subir la imagen.</div>
                    <?php else: ?>
                        <div class="art-imagen-wrap">
                            <?php
                            $imgPath = $a['imagen_path'] ?? '';
                            $imgAbs  = $imgPath !== '' ? ROOT_PATH . str_replace('/', DS, $imgPath) : '';
                            $tieneImagen = $imgPath !== '' && is_file($imgAbs);
                            ?>
                            <div class="art-imagen-preview" id="imagenPreview">
                                <?php if ($tieneImagen): ?>
                                    <img src="<?= BASE_URL . htmlspecialchars($imgPath) ?>"
                                        alt="Imagen del artículo"
                                        id="imagenActual"
                                        onerror="this.style.display='none';document.getElementById('imagenVaciaFallback').style.display='flex';">
                                    <div class="art-imagen-vacia" id="imagenVaciaFallback" style="display:none;">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                        <span>Sin imagen</span>
                                    </div>
                                <?php else: ?>
                                    <div class="art-imagen-vacia" id="imagenVacia">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <path d="m21 15-5-5L5 21"/>
                                        </svg>
                                        <span>Sin imagen</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="art-imagen-acciones">
                                <input type="file" id="imagenInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                                <button type="button" class="btn btn-outline" id="btnSubirImagen">
                                    <?= !empty($a['imagen_path']) ? 'Cambiar imagen' : 'Subir imagen' ?>
                                </button>
                                <?php if (!empty($a['imagen_path'])): ?>
                                    <button type="button" class="btn btn-outline" id="btnEliminarImagen"
                                            style="color:#B23A48;border-color:rgba(178,58,72,.3);">
                                        Eliminar imagen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
           

           
                <div class="card-header">
                    <h3>Costo</h3>
                    <p>El costo se calcula desde la fórmula si está habilitado el modo automático.</p>
                </div>
                <div class="card-body card-body-stack">

                    <label class="art-check">
                        <input type="checkbox" id="costo_automatico" name="costo_automatico" value="1"
                               <?= $chk('costo_automatico', 1) ?>>
                        Calcular costo automáticamente desde la fórmula
                    </label>

                    <div class="art-costo-box">
                        <div class="art-costo-label">COSTO ACTUAL</div>
                        <div class="art-costo-valor" id="costoDisplay">
                            $ <?= number_format((float)($a['costo'] ?? 0), 2, ',', '.') ?>
                        </div>
                    </div>

                    <div class="form-group" id="bloqueCostoManual"
                         style="<?= $chk('costo_automatico', 1) ? 'display:none;margin-top:14px;' : 'margin-top:14px;' ?>">
                        <label for="costo_manual">Costo manual</label>
                        <input type="number" id="costo_manual" name="costo_manual" step="0.01" min="0"
                               value="<?= $val('costo', '0') ?>">
                        <div class="form-hint" style="margin-top: 8px;">Ingresá el costo final del artículo.</div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php if ($esEdicion): ?>

        <!-- FÓRMULA -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h3>Fórmula del artículo</h3>
                <p>Insumos que componen este artículo. La cantidad se expresa en la unidad de medida del insumo.</p>
            </div>
            <div class="card-body card-body-stack">
                <div class="table-wrap">
                    <table class="data-table" id="tablaFormula">
                        <thead>
                            <tr>
                                <th>Insumo</th>
                                <th style="width:180px;">Cantidad</th>
                                <th style="width:130px;">Subtotal</th>
                                <th style="width:100px;"></th>
                            </tr>
                        </thead>
                        <tbody id="formulaBody">
                            <?php if (empty($formula)): ?>
                                <tr class="fila-vacia">
                                    <td colspan="4" style="text-align:center;color:#7A7266;padding:1.5rem;">
                                        Todavía no hay insumos en la fórmula.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($formula as $row): ?>
                                    <?php require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'articulo_formula_row.php'; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="art-add-row" style="margin-top:14px;">
                    <div class="art-add-grid">
                        <div class="form-group">
                            <label for="formula_insumo">Insumo *</label>
                            <select id="formula_insumo">
                                <option value="">— Seleccione —</option>
                                <?php foreach ($combos['insumos'] as $ins): ?>
                                    <option value="<?= (int)$ins['id'] ?>"
                                            data-unidad="<?= htmlspecialchars($ins['unidad_abrev']) ?>">
                                        <?= htmlspecialchars($ins['nombre']) ?>
                                        (<?= htmlspecialchars($ins['unidad_abrev']) ?> ·
                                        $<?= number_format((float)$ins['costo'], 2, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="formula_cantidad">Cantidad *</label>
                            <input type="number" id="formula_cantidad" step="0.001" min="0.001">
                        </div>
                        <div class="form-group" style="flex:0 0 auto;">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" id="btnAgregarInsumo">
                                + Agregar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRECIOS POR CATEGORÍA -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h3>Precios por categoría</h3>
                <p>Ingresá el precio para cada categoría de cliente. El checkbox define si el artículo aparece en la lista de precios de esa categoría.</p>
            </div>
            <div class="card-body card-body-stack">

                <div class="table-wrap">
                    <table class="data-table" id="tablaPrecios">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th style="width:180px;">Precio</th>
                                <th style="width:140px;text-align:center;">Visible en lista</th>
                                <th style="width:90px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($combos['categorias'] as $cat):
                                $cid = (int)$cat['id'];
                                $precioRow = $precios[$cid] ?? null;
                            ?>
                                <tr data-cat-id="<?= $cid ?>">
                                    <td><strong><?= htmlspecialchars($cat['descripcion']) ?></strong></td>
                                    <td>
                                        <input type="number" class="precio-input" step="0.01" min="0"
                                               value="<?= $precioRow ? number_format((float)$precioRow['precio'], 2, '.', '') : '' ?>"
                                               placeholder="0.00">
                                    </td>
                                    <td style="text-align:center;">
                                        <input type="checkbox" class="precio-visible"
                                               <?= ($precioRow && (int)$precioRow['visible'] === 1) ? 'checked' : '' ?>>
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="btn-icon btn-guardar-precio" title="Guardar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-hint" style="margin-top: 8px;" style="margin-top:12px;">
                    Los precios se guardan automáticamente al hacer click en <strong>Guardar artículo</strong>,
                    o podés guardar una fila individual con el botón <strong>✓</strong>.
                </div>
            </div>
        </div>

        <!-- HISTORIAL DE COSTO -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h3>Historial de cambios de costo</h3>
                <p>Últimos 15 movimientos. Podés anular una entrada errónea.</p>
            </div>
            <div class="card-body card-body-stack">
                <div class="table-wrap">
                    <table class="data-table" id="tablaHistorialCosto">
                        <thead>
                            <tr>
                                <th style="width:140px;">Fecha</th>
                                <th>Cambio</th>
                                <th style="width:100px;">Variación</th>
                                <th style="width:150px;">Usuario</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="historialCostoBody">
                            <tr><td colspan="5" style="text-align:center;padding:1rem;color:#7A7266;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>articulos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $esEdicion ? 'Guardar artículo' : 'Crear artículo' ?>
        </button>
    </div>

</form>

<!-- Modal de Cropper.js -->
<div id="cropperModal" class="cropper-modal" style="display:none;">
    <div class="cropper-modal-content">
        <div class="cropper-modal-header">
            <h3>Recortar imagen</h3>
            <button type="button" class="cropper-close" id="cropperClose">&times;</button>
        </div>
        <div class="cropper-body">
            <img id="cropperImg" src="" alt="">
        </div>
        <div class="cropper-footer">
            <button type="button" class="btn btn-outline" id="cropperCancelar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="cropperGuardar">Guardar recorte</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= BASE_URL ?>dist/css/cropper.min.css">
<script src="<?= BASE_URL ?>dist/js/cropper.min.js"></script>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>