<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Asiento';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$puedeEditar    = $as['estado'] === 'BORRADOR';
$puedeConfirmar = $as['estado'] === 'BORRADOR';
$puedeAnular    = $as['estado'] === 'CONFIRMADO';
?>

<div class="page-header">
    <div>
        <h2>Asiento N° <?= str_pad((string)$as['numero'], 5, '0', STR_PAD_LEFT) ?></h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> /
            <a href="<?= BASE_URL ?>contabilidad/asientos">Asientos</a> / Ver
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($puedeEditar): ?>
            <a href="<?= BASE_URL ?>contabilidad/asiento-form?id=<?= (int)$as['id'] ?>" class="btn btn-outline">
                Editar
            </a>
        <?php endif; ?>
        <?php if ($puedeConfirmar): ?>
            <button type="button" class="btn btn-primary btn-cont-confirmar"
                    data-id="<?= (int)$as['id'] ?>">
                Confirmar
            </button>
        <?php endif; ?>
        <?php if ($puedeAnular): ?>
            <button type="button" class="btn btn-outline btn-cont-anular"
                    data-id="<?= (int)$as['id'] ?>">
                Anular
            </button>
        <?php endif; ?>
    </div>
</div>

<?= flashRender() ?>

<?php if ($as['estado'] === 'ANULADO'): ?>
    <div class="msg-box msg-error" style="margin-bottom:16px;">
        <strong>Asiento anulado</strong> el <?= date('d/m/Y H:i', strtotime($as['anulado_el'])) ?>.
        <?php if (!empty($as['anulado_motivo'])): ?>
            Motivo: <?= htmlspecialchars($as['anulado_motivo']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="cont-card">
    <div class="cont-card-body">
        <div class="cont-asiento-ver-header">
            <div class="item">
                <span class="lbl">Ejercicio</span>
                <span class="val"><?= htmlspecialchars($as['ejercicio_nombre']) ?></span>
            </div>
            <div class="item">
                <span class="lbl">Emisor</span>
                <span class="val"><?= htmlspecialchars($as['emisor_nombre']) ?></span>
            </div>
            <div class="item">
                <span class="lbl">Fecha</span>
                <span class="val"><?= date('d/m/Y', strtotime($as['fecha'])) ?></span>
            </div>
            <div class="item">
                <span class="lbl">Tipo</span>
                <span class="val"><?= htmlspecialchars($as['tipo']) ?></span>
            </div>
            <div class="item" style="grid-column: 1 / -1;">
                <span class="lbl">Descripción</span>
                <span class="val"><?= htmlspecialchars($as['descripcion']) ?></span>
            </div>
            <div class="item">
                <span class="lbl">Estado</span>
                <span class="val">
                    <?php if ($as['estado'] === 'CONFIRMADO'): ?>
                        <span class="badge badge-confirmado">Confirmado</span>
                    <?php elseif ($as['estado'] === 'ANULADO'): ?>
                        <span class="badge badge-anulado">Anulado</span>
                    <?php else: ?>
                        <span class="badge badge-borrador">Borrador</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="item">
                <span class="lbl">Creado por</span>
                <span class="val"><?= htmlspecialchars($as['creado_por_nombre']) ?></span>
            </div>
            <div class="item">
                <span class="lbl">Creado el</span>
                <span class="val"><?= date('d/m/Y H:i', strtotime($as['creado_el'])) ?></span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="cont-tabla-reporte">
                <thead>
                    <tr>
                        <th style="width:120px;">Código</th>
                        <th>Cuenta</th>
                        <th>Detalle</th>
                        <th class="num" style="width:130px;">Debe</th>
                        <th class="num" style="width:130px;">Haber</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($as['lineas'] as $ln): ?>
                        <tr>
                            <td class="codigo"><?= htmlspecialchars($ln['cuenta_codigo']) ?></td>
                            <td><?= htmlspecialchars($ln['cuenta_nombre']) ?></td>
                            <td style="color:var(--liqui-text-muted);">
                                <?= htmlspecialchars($ln['detalle'] ?? '') ?>
                            </td>
                            <td class="num">
                                <?= (float)$ln['debe'] > 0 ? '$ ' . number_format((float)$ln['debe'], 2, ',', '.') : '' ?>
                            </td>
                            <td class="num">
                                <?= (float)$ln['haber'] > 0 ? '$ ' . number_format((float)$ln['haber'], 2, ',', '.') : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fila-totales">
                        <td colspan="3" style="text-align:right;">Totales</td>
                        <td class="num">$ <?= number_format((float)$as['total_debe'], 2, ',', '.') ?></td>
                        <td class="num">$ <?= number_format((float)$as['total_haber'], 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>