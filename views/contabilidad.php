<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Contabilidad';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';

$ej = $ejercicioActual ?? null;
$cantAsientos = count($ultimos ?? []);

// Calcular totales rápidos del ejercicio actual
$totDebe = 0; $totHaber = 0;
foreach (($ultimos ?? []) as $a) {
    if ($a['estado'] === 'CONFIRMADO') {
        $totDebe  += (float)$a['total_debe'];
        $totHaber += (float)$a['total_haber'];
    }
}
?>

<div class="page-header">
    <div>
        <h2>Contabilidad</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> / Contabilidad
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn-ayuda"
                data-ayuda-url="<?= BASE_URL ?>contabilidad/ayuda"
                data-ayuda-titulo="Ayuda — Contabilidad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Ayuda
        </button>
    </div>
</div>

<?= flashRender() ?>

<?php if (!$ej): ?>
    <div class="cont-card">
        <div class="cont-card-body" style="text-align:center;padding:60px 24px;">
            <h3 style="margin:0 0 8px;">Aún no hay un ejercicio contable abierto</h3>
            <p style="color:var(--krown-text-muted);margin:0 0 20px;">
                Para empezar a registrar asientos, creá un ejercicio contable.
            </p>
            <a href="<?= BASE_URL ?>contabilidad/ejercicio-form" class="btn btn-primary">
                Crear ejercicio
            </a>
        </div>
    </div>
<?php else: ?>

    <div class="cont-dashboard-grid">
        <div class="cont-stat cont-stat-dark">
            <span class="cont-stat-label">Ejercicio actual</span>
            <span class="cont-stat-valor"><?= htmlspecialchars($ej['nombre']) ?></span>
            <span class="cont-stat-sub">
                <?= date('d/m/Y', strtotime($ej['fecha_desde'])) ?> —
                <?= date('d/m/Y', strtotime($ej['fecha_hasta'])) ?>
            </span>
        </div>
        <div class="cont-stat cont-stat-teal">
            <span class="cont-stat-label">Total Debe (confirmado)</span>
            <span class="cont-stat-valor">
                $ <?= number_format($totDebe, 2, ',', '.') ?>
            </span>
            <span class="cont-stat-sub">En este ejercicio</span>
        </div>
        <div class="cont-stat cont-stat-crimson">
            <span class="cont-stat-label">Total Haber (confirmado)</span>
            <span class="cont-stat-valor">
                $ <?= number_format($totHaber, 2, ',', '.') ?>
            </span>
            <span class="cont-stat-sub">En este ejercicio</span>
        </div>
        <div class="cont-stat">
            <span class="cont-stat-label">Asientos cargados</span>
            <span class="cont-stat-valor"><?= $cantAsientos ?></span>
            <span class="cont-stat-sub">Últimos 500</span>
        </div>
    </div>

    <!-- Tabs -->
    <div class="fac-tabs">
        <button type="button" class="fac-tab active" data-tab="ultimos">Últimos asientos</button>
        <button type="button" class="fac-tab" data-tab="accesos">Accesos rápidos</button>
    </div>

    <!-- Tab Últimos asientos -->
    <div class="fac-tab-content active" id="tab-ultimos">
        <div class="cont-card">
            <div class="cont-card-header">
                <div>
                    <h3>Últimos asientos</h3>
                    <p>Movimientos más recientes del ejercicio <strong><?= htmlspecialchars($ej['nombre']) ?></strong>.</p>
                </div>
                <a href="<?= BASE_URL ?>contabilidad/asiento-form" class="btn btn-primary">
                    + Nuevo asiento
                </a>
            </div>
            <div class="cont-card-body">
                <?php if (empty($ultimos)): ?>
                    <p style="color:var(--krown-text-muted);text-align:center;padding:20px;">
                        Todavía no hay asientos cargados.
                    </p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Emisor</th>
                                    <th>Descripción</th>
                                    <th class="num" style="text-align:right;">Debe</th>
                                    <th class="num" style="text-align:right;">Haber</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos as $a): ?>
                                    <tr>
                                        <td data-label="N°" class="mono">
                                            <?= str_pad((string)$a['numero'], 5, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td data-label="Fecha"><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
                                        <td data-label="Tipo">
                                            <span class="badge"><?= htmlspecialchars($a['tipo']) ?></span>
                                        </td>
                                        <td data-label="Emisor"><?= htmlspecialchars($a['emisor_nombre']) ?></td>
                                        <td data-label="Descripción" class="cell-title">
                                            <?= htmlspecialchars($a['descripcion']) ?>
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
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab Accesos rápidos -->
    <div class="fac-tab-content" id="tab-accesos">
        <div class="cont-dashboard-grid">
            <a href="<?= BASE_URL ?>contabilidad/asiento-form" class="cont-stat cont-stat-dark" style="text-decoration:none;">
                <span class="cont-stat-label">Registrar</span>
                <span class="cont-stat-valor">Nuevo asiento</span>
                <span class="cont-stat-sub">Carga manual con partida doble</span>
            </a>
            <a href="<?= BASE_URL ?>contabilidad/plan-cuentas" class="cont-stat cont-stat-teal" style="text-decoration:none;">
                <span class="cont-stat-label">Configuración</span>
                <span class="cont-stat-valor">Plan de cuentas</span>
                <span class="cont-stat-sub">Árbol jerárquico de cuentas</span>
            </a>
            <a href="<?= BASE_URL ?>contabilidad/libro-diario" class="cont-stat" style="text-decoration:none;">
                <span class="cont-stat-label">Reportes</span>
                <span class="cont-stat-valor">Libro Diario</span>
                <span class="cont-stat-sub">Movimientos por rango de fechas</span>
            </a>
            <a href="<?= BASE_URL ?>contabilidad/libro-mayor" class="cont-stat cont-stat-crimson" style="text-decoration:none;">
                <span class="cont-stat-label">Reportes</span>
                <span class="cont-stat-valor">Libro Mayor</span>
                <span class="cont-stat-sub">Por cuenta y con saldo corriente</span>
            </a>
            <a href="<?= BASE_URL ?>contabilidad/balance" class="cont-stat" style="text-decoration:none;">
                <span class="cont-stat-label">Reportes</span>
                <span class="cont-stat-valor">Sumas y Saldos</span>
                <span class="cont-stat-sub">Balance de comprobación</span>
            </a>
            <a href="<?= BASE_URL ?>contabilidad/ejercicios" class="cont-stat" style="text-decoration:none;">
                <span class="cont-stat-label">Configuración</span>
                <span class="cont-stat-valor">Ejercicios</span>
                <span class="cont-stat-sub">Alta, cierre y reapertura</span>
            </a>
        </div>
    </div>

<?php endif; ?>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>