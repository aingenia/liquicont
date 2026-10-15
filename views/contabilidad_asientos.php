<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Asientos contables';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$f = $filtros ?? [];
$estados = ['BORRADOR' => 'Borrador', 'CONFIRMADO' => 'Confirmado', 'ANULADO' => 'Anulado'];
$tipos   = ['APERTURA','DIARIO','VENTA','COMPRA','COBRO','PAGO','AJUSTE','CIERRE'];
?>

<div class="page-header">
    <div>
        <h2>Asientos contables</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Asientos
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
        <a href="<?= BASE_URL ?>contabilidad/asiento-form" class="btn btn-primary">
            + Nuevo asiento
        </a>
    </div>
</div>

<?= flashRender() ?>

<!-- Filtros -->
<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/asientos">
    <div class="form-group">
        <label for="f_ejercicio">Ejercicio</label>
        <select id="f_ejercicio" name="ejercicio_id">
            <option value="">Todos</option>
            <?php foreach (($ejercicios ?? []) as $ej): ?>
                <option value="<?= (int)$ej['id'] ?>"
                    <?= ((int)($f['ejercicio_id'] ?? 0) === (int)$ej['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ej['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="f_emisor">Emisor</label>
        <select id="f_emisor" name="emisor_id">
            <option value="">Todos</option>
            <?php foreach (($emisores ?? []) as $em): ?>
                <option value="<?= (int)$em['id'] ?>"
                    <?= ((int)($f['emisor_id'] ?? 0) === (int)$em['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($em['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="f_estado">Estado</label>
        <select id="f_estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($estados as $k => $v): ?>
                <option value="<?= $k ?>" <?= (($f['estado'] ?? '') === $k) ? 'selected' : '' ?>>
                    <?= $v ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="f_tipo">Tipo</label>
        <select id="f_tipo" name="tipo">
            <option value="">Todos</option>
            <?php foreach ($tipos as $t): ?>
                <option value="<?= $t ?>" <?= (($f['tipo'] ?? '') === $t) ? 'selected' : '' ?>>
                    <?= $t ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="f_desde">Desde</label>
        <input type="date" id="f_desde" name="desde" value="<?= htmlspecialchars($f['desde'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="f_hasta">Hasta</label>
        <input type="date" id="f_hasta" name="hasta" value="<?= htmlspecialchars($f['hasta'] ?? '') ?>">
    </div>
    <div class="form-group grow">
        <label for="f_q">Buscar</label>
        <input type="text" id="f_q" name="q" maxlength="100"
               placeholder="Descripción o número…"
               value="<?= htmlspecialchars($f['texto'] ?? '') ?>">
    </div>
    <div class="acciones">
        <a href="<?= BASE_URL ?>contabilidad/asientos" class="btn btn-outline">Limpiar</a>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </div>
</form>

<div class="cont-card">
    <div class="cont-card-body" style="padding-top:0;">
        <?php if (empty($lista)): ?>
            <p style="text-align:center;color:var(--liqui-text-muted);padding:40px 0;">
                No se encontraron asientos con esos filtros.
            </p>
        <?php else: ?>
            <div class="table-wrap" style="margin-top:20px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Emisor</th>
                            <th>Descripción</th>
                            <th style="text-align:right;">Debe</th>
                            <th style="text-align:right;">Haber</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $a): ?>
                            <tr>
                                <td data-label="N°" class="mono">
                                    <?= str_pad((string)$a['numero'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td data-label="Fecha"><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
                                <td data-label="Tipo"><span class="badge"><?= htmlspecialchars($a['tipo']) ?></span></td>
                                <td data-label="Emisor"><?= htmlspecialchars($a['emisor_nombre']) ?></td>
                                <td data-label="Descripción" class="cell-title">
                                    <?= htmlspecialchars(mb_strimwidth($a['descripcion'], 0, 80, '…')) ?>
                                </td>
                                <td data-label="Debe" class="num" style="text-align:right;">
                                    $ <?= number_format((float)$a['total_debe'], 2, ',', '.') ?>
                                </td>
                                <td data-label="Haber" class="num" style="text-align:right;">
                                    $ <?= number_format((float)$a['total_haber'], 2, ',', '.') ?>
                                </td>
                                <td data-label="Estado">
                                    <?php if ($a['estado'] === 'CONFIRMADO'): ?>
                                        <span class="badge badge-confirmado">Confirmado</span>
                                    <?php elseif ($a['estado'] === 'ANULADO'): ?>
                                        <span class="badge badge-anulado">Anulado</span>
                                    <?php else: ?>
                                        <span class="badge badge-borrador">Borrador</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-acciones">
                                    <a href="<?= BASE_URL ?>contabilidad/asiento-ver?id=<?= (int)$a['id'] ?>"
                                       class="btn-icon" title="Ver">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </a>
                                    <?php if ($a['estado'] === 'BORRADOR'): ?>
                                        <a href="<?= BASE_URL ?>contabilidad/asiento-form?id=<?= (int)$a['id'] ?>"
                                           class="btn-icon" title="Editar">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"/>
                                            </svg>
                                        </a>
                                        <button type="button" class="btn-icon btn-cont-confirmar"
                                                data-id="<?= (int)$a['id'] ?>" title="Confirmar">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                        </button>
                                        <button type="button" class="btn-icon btn-cont-eliminar"
                                                data-id="<?= (int)$a['id'] ?>" title="Eliminar">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                            </svg>
                                        </button>
                                    <?php elseif ($a['estado'] === 'CONFIRMADO'): ?>
                                        <button type="button" class="btn-icon btn-cont-anular"
                                                data-id="<?= (int)$a['id'] ?>" title="Anular">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
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