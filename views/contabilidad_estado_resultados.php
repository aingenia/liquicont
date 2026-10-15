<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Estado de Resultados';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$tieneComp = !empty($data['grupos_ant']);
$ejActual  = $data['ejercicio']  ?? null;
$ejAnt     = $data['ejercicio_ant'] ?? null;
?>

<div class="page-header">
    <div>
        <h2>Estado de Resultados</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Estado de Resultados
        </div>
    </div>
    <button type="button" class="btn-ayuda"
            data-ayuda-url="<?= BASE_URL ?>contabilidad/ayuda"
            data-ayuda-titulo="Ayuda — Estados Contables">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Ayuda
    </button>
</div>

<?= flashRender() ?>

<form class="cont-toolbar" method="get" action="<?= BASE_URL ?>contabilidad/estado-resultados">
    <div class="form-group">
        <label for="ejercicio_id">Ejercicio</label>
        <select id="ejercicio_id" name="ejercicio_id" required>
            <?php foreach ($ejercicios as $ej): ?>
                <option value="<?= (int)$ej['id'] ?>"
                    <?= ((int)($ejActual['id'] ?? 0) === (int)$ej['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ej['nombre']) ?>
                    (<?= date('d/m/Y', strtotime($ej['fecha_desde'])) ?> — <?= date('d/m/Y', strtotime($ej['fecha_hasta'])) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="comparar_id">Comparar con</label>
        <select id="comparar_id" name="comparar_id">
            <option value="">— Sin comparación —</option>
            <?php foreach ($ejercicios as $ej):
                if ($ejActual && (int)$ej['id'] === (int)$ejActual['id']) continue;
            ?>
                <option value="<?= (int)$ej['id'] ?>"
                    <?= ((int)($ejAnt['id'] ?? 0) === (int)$ej['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ej['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="emisor_id">Emisor</label>
        <select id="emisor_id" name="emisor_id">
            <option value="">Consolidado</option>
            <?php foreach ($emisores as $em): ?>
                <option value="<?= (int)$em['id'] ?>"
                    <?= ((int)($emisorId ?? 0) === (int)$em['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($em['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="acciones">
        <button type="submit" class="btn btn-primary">Aplicar</button>
        <a href="<?= urlConFiltros('contabilidad/estado-resultados-csv', [
            'ejercicio_id' => $ejActual['id'] ?? null,
            'comparar_id'  => $ejAnt['id'] ?? null,
            'emisor_id'    => $emisorId,
        ]) ?>" class="btn btn-outline">Exportar CSV</a>
    </div>
</form>

<?php if (empty($data['ok'])): ?>
    <div class="msg-box msg-error" style="display:block;"><?= htmlspecialchars($data['msg'] ?? 'Error.') ?></div>
<?php else: ?>

<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Resultado del Ejercicio</h3>
            <p>
                <strong><?= htmlspecialchars($ejActual['nombre']) ?></strong>
                · <?= date('d/m/Y', strtotime($ejActual['fecha_desde'])) ?>
                → <?= date('d/m/Y', strtotime($ejActual['fecha_hasta'])) ?>
                <?php if ($tieneComp): ?>
                    · Comparando con <strong><?= htmlspecialchars($ejAnt['nombre']) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <div class="estado-resultado-total">
            <span class="estado-resultado-total-label">
                <?= $data['total'] >= 0 ? 'GANANCIA' : 'PÉRDIDA' ?>
            </span>
            <span class="estado-resultado-total-valor" style="color:<?= $data['total'] >= 0 ? 'var(--liqui-teal)' : 'var(--liqui-crimson)' ?>">
                $ <?= number_format(abs($data['total']), 2, ',', '.') ?>
            </span>
            <?php if ($tieneComp && $data['variacion_pct'] !== null): ?>
                <span class="estado-resultado-total-var">
                    <?= $data['variacion_pct'] >= 0 ? '▲' : '▼' ?>
                    <?= number_format(abs($data['variacion_pct']), 2, ',', '.') ?>%
                    vs ejercicio anterior
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="cont-card-body">
        <div class="table-wrap">
            <table class="cont-tabla-reporte estado-tabla">
                <thead>
                    <tr>
                        <th style="width:100px;">Código</th>
                        <th>Cuenta</th>
                        <th class="num" style="width:140px;">Ejercicio actual</th>
                        <?php if ($tieneComp): ?>
                            <th class="num" style="width:140px;">Comparativo</th>
                            <th class="num" style="width:110px;">Variación</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- INGRESOS -->
                    <tr class="estado-seccion">
                        <td colspan="<?= $tieneComp ? 5 : 3 ?>">INGRESOS</td>
                    </tr>
                    <?php foreach ($data['grupos']['ingresos'] as $g): ?>
                        <tr class="estado-rubro">
                            <td class="codigo"><?= htmlspecialchars($g['codigo']) ?></td>
                            <td><strong><?= htmlspecialchars($g['nombre']) ?></strong></td>
                            <td class="num"><strong>$ <?= number_format($g['total'], 2, ',', '.') ?></strong></td>
                            <?php if ($tieneComp): ?>
                                <td class="num"></td>
                                <td class="num"></td>
                            <?php endif; ?>
                        </tr>
                        <?php foreach ($g['cuentas'] as $c): ?>
                            <tr>
                                <td class="codigo"><?= htmlspecialchars($c['codigo']) ?></td>
                                <td style="padding-left:30px;"><?= htmlspecialchars($c['nombre']) ?></td>
                                <td class="num">$ <?= number_format($c['saldo'], 2, ',', '.') ?></td>
                                <?php if ($tieneComp): ?>
                                    <td class="num" style="color:var(--liqui-text-muted);">
                                        $ <?= number_format($c['saldo_anterior'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td class="num">
                                        <?php if (($c['variacion_pct'] ?? null) !== null): ?>
                                            <span style="color:<?= $c['variacion_pct'] >= 0 ? 'var(--liqui-teal)' : 'var(--liqui-crimson)' ?>">
                                                <?= $c['variacion_pct'] >= 0 ? '+' : '' ?><?= number_format($c['variacion_pct'], 2, ',', '.') ?>%
                                            </span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <!-- EGRESOS -->
                    <tr class="estado-seccion">
                        <td colspan="<?= $tieneComp ? 5 : 3 ?>">EGRESOS</td>
                    </tr>
                    <?php foreach ($data['grupos']['egresos'] as $g): ?>
                        <tr class="estado-rubro">
                            <td class="codigo"><?= htmlspecialchars($g['codigo']) ?></td>
                            <td><strong><?= htmlspecialchars($g['nombre']) ?></strong></td>
                            <td class="num"><strong>$ <?= number_format($g['total'], 2, ',', '.') ?></strong></td>
                            <?php if ($tieneComp): ?>
                                <td class="num"></td>
                                <td class="num"></td>
                            <?php endif; ?>
                        </tr>
                        <?php foreach ($g['cuentas'] as $c): ?>
                            <tr>
                                <td class="codigo"><?= htmlspecialchars($c['codigo']) ?></td>
                                <td style="padding-left:30px;"><?= htmlspecialchars($c['nombre']) ?></td>
                                <td class="num">$ <?= number_format($c['saldo'], 2, ',', '.') ?></td>
                                <?php if ($tieneComp): ?>
                                    <td class="num" style="color:var(--liqui-text-muted);">
                                        $ <?= number_format($c['saldo_anterior'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td class="num">
                                        <?php if (($c['variacion_pct'] ?? null) !== null): ?>
                                            <span style="color:<?= $c['variacion_pct'] >= 0 ? 'var(--liqui-teal)' : 'var(--liqui-crimson)' ?>">
                                                <?= $c['variacion_pct'] >= 0 ? '+' : '' ?><?= number_format($c['variacion_pct'], 2, ',', '.') ?>%
                                            </span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fila-totales">
                        <td colspan="2" style="text-align:right;">
                            <?= $data['total'] >= 0 ? 'RESULTADO (GANANCIA)' : 'RESULTADO (PÉRDIDA)' ?>
                        </td>
                        <td class="num">$ <?= number_format(abs($data['total']), 2, ',', '.') ?></td>
                        <?php if ($tieneComp): ?>
                            <td class="num">
                                $ <?= number_format(abs($data['total_ant'] ?? 0), 2, ',', '.') ?>
                            </td>
                            <td class="num">
                                <?php if ($data['variacion_pct'] !== null): ?>
                                    <?= $data['variacion_pct'] >= 0 ? '+' : '' ?><?= number_format($data['variacion_pct'], 2, ',', '.') ?>%
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>