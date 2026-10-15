<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Asiento contable';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$editando = !empty($as);
$accion   = $editando ? ('Editar asiento N° ' . str_pad((string)$as['numero'], 5, '0', STR_PAD_LEFT)) : 'Nuevo asiento';

// Valores iniciales
$val = [
    'ejercicio_id' => $as['ejercicio_id'] ?? ($ejercicioActual['id'] ?? ''),
    'emisor_id'    => $as['emisor_id']    ?? '',
    'fecha'        => $as['fecha']        ?? date('Y-m-d'),
    'tipo'         => $as['tipo']         ?? 'DIARIO',
    'descripcion'  => $as['descripcion']  ?? '',
];

$lineas = $as['lineas'] ?? [];

$tipos = ['APERTURA','DIARIO','VENTA','COMPRA','COBRO','PAGO','AJUSTE','CIERRE'];
?>

<div class="page-header">
    <div>
        <h2><?= htmlspecialchars($accion) ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> /
            <a href="<?= BASE_URL ?>contabilidad/asientos">Asientos</a> /
            <?= $editando ? 'Editar' : 'Nuevo' ?>
        </div>
    </div>
</div>

<?= flashRender() ?>

<!-- Select oculto con TODAS las cuentas imputables (el JS lo lee para armar las opciones) -->
<div style="display:none;">
    <select id="cont_cuentas_src">
        <?php foreach ($cuentas as $c): ?>
            <option value="<?= (int)$c['id'] ?>"
                    data-codigo="<?= htmlspecialchars($c['codigo']) ?>"
                    data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                    data-tipo="<?= htmlspecialchars($c['tipo']) ?>"></option>
        <?php endforeach; ?>
    </select>
</div>

<form id="form_cont_asiento" method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= $editando ? (int)$as['id'] : 0 ?>">

    <div class="card">
        <div class="card-header">
            <h3>Cabecera del asiento</h3>
            <p>La numeración se asigna automáticamente por ejercicio + emisor.</p>
        </div>
        <div class="card-body card-body-stack">
            <div class="cont-asiento-header">
                <div class="form-group">
                    <label for="ejercicio_id">Ejercicio *</label>
                    <select id="ejercicio_id" name="ejercicio_id" required <?= $editando ? 'disabled' : '' ?>>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($ejercicios as $ej): ?>
                            <option value="<?= (int)$ej['id'] ?>"
                                <?= ((int)$val['ejercicio_id'] === (int)$ej['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ej['nombre']) ?>
                                (<?= date('d/m/Y', strtotime($ej['fecha_desde'])) ?> —
                                <?= date('d/m/Y', strtotime($ej['fecha_hasta'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editando): ?>
                        <input type="hidden" name="ejercicio_id" value="<?= (int)$val['ejercicio_id'] ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="emisor_id">Emisor *</label>
                    <select id="emisor_id" name="emisor_id" required <?= $editando ? 'disabled' : '' ?>>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($emisores as $em): ?>
                            <option value="<?= (int)$em['id'] ?>"
                                <?= ((int)$val['emisor_id'] === (int)$em['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($em['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editando): ?>
                        <input type="hidden" name="emisor_id" value="<?= (int)$val['emisor_id'] ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha *</label>
                    <input type="date" id="fecha" name="fecha" required
                           value="<?= htmlspecialchars($val['fecha']) ?>">
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo *</label>
                    <select id="tipo" name="tipo" required>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t ?>" <?= ($val['tipo'] === $t) ? 'selected' : '' ?>>
                                <?= $t ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="descripcion">Descripción *</label>
                    <input type="text" id="descripcion" name="descripcion" required maxlength="500"
                           placeholder="Ej: Pago de servicios de octubre"
                           value="<?= htmlspecialchars($val['descripcion']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3>Líneas del asiento</h3>
                <p>El total del Debe debe coincidir con el total del Haber.</p>
            </div>
            <button type="button" id="btn_cont_add_linea" class="btn btn-outline">
                + Agregar línea
            </button>
        </div>
        <div class="card-body card-body-stack" style="padding-bottom:0;">
            <table class="cont-asiento-tabla">
                <thead>
                    <tr>
                        <th class="col-cuenta">Cuenta</th>
                        <th class="col-detalle">Detalle</th>
                        <th class="col-importe" style="text-align:right;">Debe</th>
                        <th class="col-importe" style="text-align:right;">Haber</th>
                        <th class="col-accion"></th>
                    </tr>
                </thead>
                <tbody id="cont_lineas_tbody">
                    <?php if (!empty($lineas)): ?>
                        <?php foreach ($lineas as $ln): ?>
                            <tr>
                                <td class="col-cuenta">
                                    <select name="linea_cuenta_id[]" class="linea-cuenta">
                                        <option value="">— Seleccionar cuenta —</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?= (int)$c['id'] ?>"
                                                <?= ((int)$c['id'] === (int)$ln['cuenta_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['codigo'] . ' · ' . $c['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="col-detalle">
                                    <input type="text" name="linea_detalle[]" maxlength="255"
                                           value="<?= htmlspecialchars($ln['detalle'] ?? '') ?>">
                                </td>
                                <td class="col-importe">
                                    <input type="text" inputmode="decimal" name="linea_debe[]"
                                           class="num linea-debe"
                                           value="<?= (float)$ln['debe'] > 0 ? number_format((float)$ln['debe'], 2, '.', '') : '' ?>">
                                </td>
                                <td class="col-importe">
                                    <input type="text" inputmode="decimal" name="linea_haber[]"
                                           class="num linea-haber"
                                           value="<?= (float)$ln['haber'] > 0 ? number_format((float)$ln['haber'], 2, '.', '') : '' ?>">
                                </td>
                                <td class="col-accion">
                                    <button type="button" class="btn-remove-line" title="Quitar">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="cont-asiento-totales" id="cont_asiento_totales">
                <div>
                    <span class="label">Debe</span>
                    <span class="valor" id="cont_total_debe">$ 0,00</span>
                </div>
                <div>
                    <span class="label">Haber</span>
                    <span class="valor" id="cont_total_haber">$ 0,00</span>
                </div>
                <div>
                    <span class="label">Estado</span>
                    <span class="valor" id="cont_estado_balance">Sin importes</span>
                </div>
            </div>
        </div>
    </div>

    <div class="cont-asiento-acciones">
        <a href="<?= BASE_URL ?>contabilidad/asientos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <?= $editando ? 'Guardar cambios' : 'Guardar asiento' ?>
        </button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>