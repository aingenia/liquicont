<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Configuración';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Configuración</h2>
        <div class="breadcrumb"><a href="<?= BASE_URL ?>dashboard">Inicio</a> / Configuración</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>configuracion/ayuda"
                data-ayuda-titulo="Ayuda — Configuración">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<!-- TABS -->
<div class="cfg-tabs">
    <button type="button" class="cfg-tab active" data-tab="parametros">
        Parámetros del sistema
    </button>
    <button type="button" class="cfg-tab" data-tab="emisores">
        Emisores
        <span class="cfg-tab-count"><?= count($emisores) ?></span>
    </button>
</div>

<!-- ============================================================
     TAB 1: PARÁMETROS
     ============================================================ -->
<div class="cfg-tab-content active" id="tab-parametros">
    <form id="formParametros" autocomplete="off">

        <?php foreach ($categorias as $catKey => $cat): ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3><?= htmlspecialchars($cat['titulo']) ?></h3>
                    <p><?= htmlspecialchars($cat['descripcion']) ?></p>
                </div>
                <div class="card-body cfg-grid-2">

                    <?php foreach ($cat['campos'] as $clave => $def):
                        $valor = $valores[$clave] ?? '';
                        $tipo = $def['tipo'] ?? 'text';
                        $full = !empty($def['obligatorio']) ? 'field-full' : '';
                    ?>
                        <div class="form-group <?= $full ?>">
                            <label for="<?= $clave ?>">
                                <?= htmlspecialchars($def['label']) ?>
                                <?= !empty($def['obligatorio']) ? ' *' : '' ?>
                            </label>

                            <?php if ($tipo === 'select'): ?>
                                <select id="<?= $clave ?>" name="param[<?= $clave ?>]">
                                    <option value="">— Seleccione —</option>
                                    <?php foreach (($def['opciones'] ?? []) as $optVal => $optLabel): ?>
                                        <option value="<?= htmlspecialchars((string)$optVal) ?>"
                                            <?= (string)$valor === (string)$optVal ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(is_int($optVal) ? $optLabel : (string)$optVal) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($tipo === 'number'): ?>
                                <input type="number" id="<?= $clave ?>" name="param[<?= $clave ?>]"
                                       value="<?= htmlspecialchars((string)$valor) ?>"
                                       step="<?= $def['step'] ?? '0.01' ?>"
                                       min="<?= $def['min'] ?? '' ?>"
                                       max="<?= $def['max'] ?? '' ?>">
                            <?php elseif ($tipo === 'date'): ?>
                                <input type="date" id="<?= $clave ?>" name="param[<?= $clave ?>]"
                                       value="<?= htmlspecialchars((string)$valor) ?>">
                            <?php elseif ($tipo === 'email'): ?>
                                <input type="email" id="<?= $clave ?>" name="param[<?= $clave ?>]"
                                       value="<?= htmlspecialchars((string)$valor) ?>">
                            <?php else: ?>
                                <input type="text" id="<?= $clave ?>" name="param[<?= $clave ?>]"
                                       value="<?= htmlspecialchars((string)$valor) ?>">
                            <?php endif; ?>

                            <?php if (!empty($def['help'])): ?>
                                <div class="form-hint" style="margin-top:8px;"><?= htmlspecialchars($def['help']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnGuardarParametros">
                Guardar todos los parámetros
            </button>
        </div>

    </form>
</div>

<!-- ============================================================
     TAB 2: EMISORES
     ============================================================ -->
<div class="cfg-tab-content" id="tab-emisores">
    <div class="content-card">

        <div class="cfg-emisores-header">
            <div>
                <h3 style="margin:0 0 4px;">Emisores de comprobantes</h3>
                <p style="margin:0;font-size:13px;color:var(--liqui-text-muted);">
                    Cada emisor tiene su propio CUIT, certificado ARCA, punto de venta y contador de remitos.
                </p>
            </div>
            <a href="<?= BASE_URL ?>configuracion/emisor-crear" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Nuevo emisor
            </a>
        </div>

        <?php if (empty($emisores)): ?>
            <div class="cfg-empty">
                Todavía no hay emisores cargados.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">ID</th>
                            <th>Nombre</th>
                            <th>Razón social</th>
                            <th>CUIT</th>
                            <th style="width:130px;">ARCA</th>
                            <th style="width:120px;">Remito</th>
                            <th style="width:100px;">Estado</th>
                            <th style="text-align:right;width:180px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emisores as $e):
                            $activo = (int)$e['activo'] === 1;
                        ?>
                            <tr data-id="<?= (int)$e['id'] ?>">
                                <td><strong>#<?= (int)$e['id'] ?></strong></td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($e['nombre']) ?></div>
                                    <?php if (!empty($e['nombre_fantasia'])): ?>
                                        <div style="font-size:12px;color:var(--liqui-text-muted);">
                                            <?= htmlspecialchars($e['nombre_fantasia']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px;"><?= htmlspecialchars($e['razon_social']) ?></td>
                                <td class="mono" style="font-size:12px;"><?= htmlspecialchars($e['cuit']) ?></td>
                                <td style="font-size:12px;">
                                    <div>
                                        <strong><?= strtoupper($e['arca_entorno']) ?></strong>
                                    </div>
                                    <div style="color:var(--liqui-text-muted);">
                                        PV <?= (int)$e['arca_punto_venta'] ?>
                                        · Letra <?= htmlspecialchars($e['arca_tipo_comprobante_letra'] ?? '—') ?>
                                    </div>
                                </td>
                                <td class="mono" style="font-size:12px;">
                                    <?= htmlspecialchars($e['remito_prefijo'] ?? '') ?>
                                    <?= str_pad((string)$e['remito_punto_venta'], 4, '0', STR_PAD_LEFT) ?>
                                    <br>
                                    <span style="color:var(--liqui-text-muted);">
                                        Último: <?= (int)$e['remito_ultimo_nro'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($activo): ?>
                                        <span class="badge badge-active">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <a href="<?= BASE_URL ?>configuracion/emisor-editar?id=<?= (int)$e['id'] ?>"
                                       class="btn-icon" title="Editar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                        </svg>
                                    </a>
                                    <button type="button" class="btn-icon btn-toggle-emisor"
                                            data-id="<?= (int)$e['id'] ?>"
                                            data-activo="<?= $activo ? 1 : 0 ?>"
                                            data-nombre="<?= htmlspecialchars($e['nombre']) ?>"
                                            title="<?= $activo ? 'Desactivar' : 'Activar' ?>">
                                        <?php if ($activo): ?>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>