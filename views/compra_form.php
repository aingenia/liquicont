<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Comprobante';

$esVer    = ($modo === 'ver');
$esEditar = ($modo === 'editar');
$c        = $compra ?? [];
$val      = fn($k, $def = '') => htmlspecialchars((string)($c[$k] ?? $def));
$sel      = fn($k, $v)         => ((string)($c[$k] ?? '') === (string)$v) ? 'selected' : '';
$disabled = $esVer ? 'disabled' : '';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>
            <?php if ($esVer): ?>
                Comprobante #<?= (int)$c['id'] ?>
            <?php elseif ($esEditar): ?>
                Editar comprobante #<?= (int)$c['id'] ?>
            <?php else: ?>
                Nuevo comprobante
            <?php endif; ?>
        </h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>comprobantes">Comprobantes</a> /
            <?= $esVer ? 'Ver' : ($esEditar ? 'Editar' : 'Nuevo') ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>comprobantes/ayuda"
                data-ayuda-titulo="Cómo cargar un comprobante">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
        <a href="<?= BASE_URL ?>comprobantes" class="btn btn-outline">← Volver al listado</a>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>
<?php if ($esEditar): ?>
    <div class="msg-box msg-info" style="display:block;margin-bottom:16px;">
        Estás editando el comprobante <strong>#<?= (int)$c['id'] ?></strong>.
        Los cambios en el detalle van a <strong>revertir y reaplicar</strong> el stock.
        Se guarda auditoría de la edición.
        <?php if ((int)($c['cantidad_ediciones'] ?? 0) > 0): ?>
            <br><small>Este comprobante ya fue editado
                <?= (int)$c['cantidad_ediciones'] ?> vez/veces.</small>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($esVer): ?>
    <div class="msg-box msg-info" style="display:block;margin-bottom:16px;">
        Este comprobante está en modo <strong>solo lectura</strong>. Las compras son inmutables — si hay un error, anulá el comprobante y cargá uno nuevo.
    </div>
<?php endif; ?>


<form id="compraForm" autocomplete="off"
      data-modo="<?= $esVer ? 'ver' : ($esEditar ? 'editar' : 'crear') ?>">
    <?php if (!empty($c['id'])): ?>
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <?php endif; ?>

    <!-- DATOS DEL COMPROBANTE -->
    <div class="card">
        <div class="card-header">
            <h3>Datos del comprobante</h3>
            <p>Completá los campos obligatorios (*).</p>
        </div>
        <div class="card-body comp-grid-6">

            <div class="form-group col-4">
                <label for="proveedor_id">Proveedor *</label>
                <select id="proveedor_id" name="proveedor_id" required <?= $disabled ?>>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($combos['proveedores'] as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $sel('proveedor_id', $p['id']) ?>>
                            <?= htmlspecialchars($p['razon_social']) ?>
                            <?= !empty($p['cuit']) ? ' — ' . htmlspecialchars($p['cuit']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-2">
                <label for="tipo_comprobante_id">Tipo *</label>
                <select id="tipo_comprobante_id" name="tipo_comprobante_id" required <?= $disabled ?>>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($combos['tipos_comprobante'] as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $sel('tipo_comprobante_id', $t['id']) ?>>
                            <?= htmlspecialchars($t['descripcion']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-1">
                <label for="punto_venta">Pto. venta</label>
                <input type="text" id="punto_venta" name="punto_venta"
                       value="<?= $val('punto_venta') ?>" maxlength="5" placeholder="0001" <?= $disabled ?>>
            </div>

            <div class="form-group col-2">
                <label for="numero">Número</label>
                <input type="text" id="numero" name="numero"
                       value="<?= $val('numero') ?>" maxlength="9" placeholder="00001234" <?= $disabled ?>>
            </div>

            <div class="form-group col-3">
                <label for="fecha_comprobante">Fecha *</label>
                <input type="date" id="fecha_comprobante" name="fecha_comprobante" required
                       value="<?= $val('fecha_comprobante', date('Y-m-d')) ?>" <?= $disabled ?>>
            </div>

            <div class="form-group col-3">
                <label for="cae">CAE</label>
                <input type="text" id="cae" name="cae" value="<?= $val('cae') ?>" maxlength="20" <?= $disabled ?>>
            </div>

            <div class="form-group col-3">
                <label for="cae_vencimiento">Vencimiento CAE</label>
                <input type="date" id="cae_vencimiento" name="cae_vencimiento"
                       value="<?= $val('cae_vencimiento') ?>" <?= $disabled ?>>
            </div>

            <?php if (!$esVer): ?>
                <div class="col-6 comp-arca-row">
                    <button type="button" id="btnArcaConstatar" class="btn-arca-inline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        Constatar en ARCA
                    </button>
                </div>
            <?php endif; ?>

            <div class="form-group col-6">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="2" maxlength="500" <?= $disabled ?>><?= $val('observaciones') ?></textarea>
            </div>

        </div>
    </div>

    <!-- IMPORTES -->
    <div class="card">
        <div class="card-header">
            <h3>Importes</h3>
            <p>El IVA se calcula automáticamente según la alícuota de cada línea. Podés desglosar en neto gravado, no gravado y exento.</p>
        </div>
        <div class="card-body comp-grid-4">

            <div class="form-group">
                <label>Subtotal</label>
                <input type="number" id="subtotal" step="0.01" readonly
                       value="<?= number_format((float)($c['subtotal'] ?? 0), 2, '.', '') ?>">
            </div>

            <div class="form-group">
                <label>Neto gravado</label>
                <input type="number" id="neto_gravado" step="0.01" readonly
                       value="<?= number_format((float)($c['neto_gravado'] ?? 0), 2, '.', '') ?>">
            </div>

            <div class="form-group">
                <label for="neto_no_gravado">Neto no gravado</label>
                <input type="number" id="neto_no_gravado" name="neto_no_gravado" step="0.01" min="0"
                       value="<?= number_format((float)($c['neto_no_gravado'] ?? 0), 2, '.', '') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group">
                <label for="neto_exento">Neto exento</label>
                <input type="number" id="neto_exento" name="neto_exento" step="0.01" min="0"
                       value="<?= number_format((float)($c['neto_exento'] ?? 0), 2, '.', '') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group">
                <label for="iva">IVA</label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <input type="number" id="iva" name="iva" step="0.01" min="0"
                           value="<?= number_format((float)($c['iva'] ?? 0), 2, '.', '') ?>" <?= $disabled ?>>
                    <label style="font-size:11px;white-space:nowrap;text-transform:none;letter-spacing:0;display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="checkbox" id="iva_manual_chk" value="1" style="width:auto;">
                        Manual
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="perc_iva">Percepción IVA</label>
                <input type="number" id="perc_iva" name="perc_iva" step="0.01" min="0"
                       value="<?= number_format((float)($c['perc_iva'] ?? 0), 2, '.', '') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group">
                <label for="perc_iibb">Percepción IIBB</label>
                <input type="number" id="perc_iibb" name="perc_iibb" step="0.01" min="0"
                       value="<?= number_format((float)($c['perc_iibb'] ?? 0), 2, '.', '') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group">
                <label for="impuestos_internos">Imp. internos</label>
                <input type="number" id="impuestos_internos" name="impuestos_internos" step="0.01" min="0"
                       value="<?= number_format((float)($c['impuestos_internos'] ?? 0), 2, '.', '') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group">
                <span class="comp-total-compact-label">TOTAL</span>
                <div class="comp-total-compact" id="totalDisplay">
                    $ <?= number_format((float)($c['total'] ?? 0), 2, ',', '.') ?>
                </div>
            </div>

        </div>
    </div>

    <!-- CUENTA CONTABLE Y ADJUNTO -->
    <div class="card">
        <div class="card-header">
            <h3>Cuenta contable, vencimientos y adjunto</h3>
            <p>Todos los campos de esta sección son opcionales.</p>
        </div>
        <div class="card-body comp-grid-3">

            <div class="form-group">
                <label for="cuenta_contable_id">Cuenta contable</label>
                <select id="cuenta_contable_id" name="cuenta_contable_id" <?= $disabled ?>>
                    <option value="">Sin asignar</option>
                    <?php foreach ($combos['cuentas_contables'] as $cc): ?>
                        <option value="<?= (int)$cc['id'] ?>" <?= $sel('cuenta_contable_id', $cc['id']) ?>>
                            <?= htmlspecialchars($cc['codigo']) ?> — <?= htmlspecialchars($cc['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="primer_vencimiento">1er vencimiento</label>
                <input type="date" id="primer_vencimiento" name="primer_vencimiento"
                       value="<?= $val('primer_vencimiento') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group">
                <label for="segundo_vencimiento">2do vencimiento</label>
                <input type="date" id="segundo_vencimiento" name="segundo_vencimiento"
                       value="<?= $val('segundo_vencimiento') ?>" <?= $disabled ?>>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Comprobante adjunto <span style="font-weight:400;text-transform:none;letter-spacing:0;">(JPG, PNG, WebP o PDF — máx 8 MB)</span></label>

                <?php if (!$esVer): ?>
                    <input type="file" id="archivoInput" accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none;">
                    <div class="comp-adjunto">
                        <div class="comp-adjunto-info" id="adjuntoInfo">
                            <?php if (!empty($c['archivo_path'])): ?>
                                <a href="<?= BASE_URL . htmlspecialchars($c['archivo_path']) ?>" target="_blank">
                                    📎 <?= htmlspecialchars($c['archivo_nombre_original'] ?? 'Ver adjunto') ?>
                                </a>
                            <?php else: ?>
                                <span style="color:#7A7266;">Sin adjunto</span>
                            <?php endif; ?>
                        </div>
                        <div class="comp-adjunto-botones">
                            <button type="button" class="btn btn-outline" id="btnSubirArchivo">
                                <?= !empty($c['archivo_path']) ? 'Cambiar archivo' : 'Adjuntar archivo' ?>
                            </button>
                            <?php if (!empty($c['archivo_path'])): ?>
                                <button type="button" class="btn btn-outline" id="btnEliminarArchivo"
                                        style="color:#B23A48;border-color:rgba(178,58,72,.3);">Eliminar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!empty($c['archivo_path'])): ?>
                        <a href="<?= BASE_URL . htmlspecialchars($c['archivo_path']) ?>" target="_blank" class="btn btn-outline">
                            📎 Ver adjunto: <?= htmlspecialchars($c['archivo_nombre_original'] ?? 'archivo') ?>
                        </a>
                    <?php else: ?>
                        <span style="color:#7A7266;">Sin adjunto.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="form-actions">
        <a href="<?= BASE_URL ?>comprobantes" class="btn btn-outline"><?= $esVer ? 'Volver' : 'Cancelar' ?></a>
        <button type="submit" class="btn btn-primary">
            <?= $esEditar ? 'Guardar cambios' : 'Guardar comprobante' ?>
        </button>
    </div>

    <!-- INSUMOS COMPRADOS -->
    <?php if (!$esVer): ?>
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h3>Insumos comprados</h3>
                <p>Cargá cada insumo del comprobante con su cantidad, costo y condición fiscal.</p>
            </div>
            <div class="card-body card-body-stack">
                <div class="table-wrap">
                    <table class="data-table" id="tablaDetalle">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th style="width:130px;text-align:right;">Cantidad</th>
                                <th style="width:120px;text-align:right;">Costo unit.</th>
                                <th style="width:120px;text-align:right;">Subtotal</th>
                                <th style="width:130px;">Cond. IVA</th>
                                <th style="width:90px;text-align:right;">Alíc. %</th>
                                <th style="width:120px;text-align:right;">IVA</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="detalleBody">
                            <tr class="fila-vacia">
                                <td colspan="8" style="text-align:center;color:#7A7266;padding:1.5rem;">
                                    Todavía no cargaste líneas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Alta de INSUMO -->
                <div class="comp-add-row">
                    <div class="comp-add-grid">
                        <div class="form-group">
                            <label for="add_insumo">Insumo *</label>
                            <select id="add_insumo">
                                <option value="">— Seleccione —</option>
                                <?php foreach ($combos['insumos'] as $ins): ?>
                                    <option value="<?= (int)$ins['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($ins['nombre']) ?>"
                                            data-codigo="<?= htmlspecialchars($ins['codigo'] ?? '') ?>"
                                            data-unidad="<?= htmlspecialchars($ins['unidad_abrev']) ?>"
                                            data-costo="<?= htmlspecialchars((string)$ins['costo']) ?>">
                                        <?= htmlspecialchars($ins['nombre']) ?>
                                        (<?= htmlspecialchars($ins['unidad_abrev']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_cantidad">Cantidad *</label>
                            <input type="number" id="add_cantidad" step="0.001" min="0.001">
                        </div>
                        <div class="form-group">
                            <label for="add_costo">Costo unitario *</label>
                            <input type="number" id="add_costo" step="0.01" min="0.01">
                        </div>
                        <div class="form-group">
                            <label for="add_condicion_iva">Cond. IVA</label>
                            <select id="add_condicion_iva">
                                <option value="GRAVADO">Gravado</option>
                                <option value="NO_GRAVADO">No gravado</option>
                                <option value="EXENTO">Exento</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_alicuota">Alícuota %</label>
                            <select id="add_alicuota">
                                <option value="21">21%</option>
                                <option value="10.5">10,5%</option>
                                <option value="27">27%</option>
                                <option value="0">0%</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:0 0 auto;">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" id="btnAgregarLinea">
                                + Agregar insumo
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alta de CONCEPTO / SERVICIO -->
                <div class="comp-add-row" style="border-top:1px dashed var(--liqui-border);padding-top:16px;margin-top:12px;">
                    <div class="comp-add-grid" style="grid-template-columns: 2fr 1fr 1fr 1fr auto;">
                        <div class="form-group">
                            <label for="add_concepto">Concepto / Servicio</label>
                            <input type="text" id="add_concepto" maxlength="255"
                                placeholder="Ej: Edenor — Servicio eléctrico, Flete, Honorarios…">
                        </div>
                        <div class="form-group">
                            <label for="add_importe">Importe</label>
                            <input type="number" id="add_importe" step="0.01" min="0.01">
                        </div>
                        <div class="form-group">
                            <label for="add_condicion_iva_serv">Cond. IVA</label>
                            <select id="add_condicion_iva_serv">
                                <option value="GRAVADO">Gravado</option>
                                <option value="NO_GRAVADO">No gravado</option>
                                <option value="EXENTO">Exento</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_alicuota_serv">Alícuota %</label>
                            <select id="add_alicuota_serv">
                                <option value="21">21%</option>
                                <option value="10.5">10,5%</option>
                                <option value="27">27%</option>
                                <option value="0">0%</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:0 0 auto;">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-outline" id="btnAgregarConcepto">
                                + Agregar concepto
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Modo VER -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <h3>Detalle del comprobante</h3>
            </div>
            <div class="card-body card-body-stack">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th style="text-align:right;">Cantidad</th>
                                <th style="text-align:right;">Costo unit.</th>
                                <th style="text-align:right;">Subtotal</th>
                                <th>Cond. IVA</th>
                                <th style="text-align:right;">Alíc. %</th>
                                <th style="text-align:right;">IVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($detalle)): ?>
                                <tr><td colspan="7" style="text-align:center;color:#7A7266;padding:1.5rem;">Sin detalle.</td></tr>
                            <?php else: ?>
                                <?php foreach ($detalle as $d): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($d['insumo_nombre'])): ?>
                                                <?= htmlspecialchars($d['insumo_nombre']) ?>
                                            <?php else: ?>
                                                <em><?= htmlspecialchars($d['concepto'] ?? '—') ?></em>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <?php if (!empty($d['insumo_id'])): ?>
                                                <?= number_format((float)$d['cantidad'], 3, ',', '.') ?>
                                                <?= htmlspecialchars($d['unidad_abrev'] ?? '') ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <?php if (!empty($d['insumo_id'])): ?>
                                                $ <?= number_format((float)$d['costo_unitario'], 2, ',', '.') ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;font-weight:600;">
                                            $ <?= number_format((float)$d['subtotal_linea'], 2, ',', '.') ?>
                                        </td>
                                        <td>
                                            <?php
                                            $cond = $d['condicion_iva'] ?? 'GRAVADO';
                                            echo $cond === 'GRAVADO' ? 'Gravado'
                                               : ($cond === 'NO_GRAVADO' ? 'No gravado' : 'Exento');
                                            ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <?= number_format((float)($d['alicuota_iva'] ?? 0), 2, ',', '.') ?>%
                                        </td>
                                        <td style="text-align:right;">
                                            $ <?= number_format((float)($d['importe_iva'] ?? 0), 2, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</form>
<script>
window.__DETALLE_INICIAL__ = <?= json_encode($detalle ?? [], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>