<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Ejercicios contables';
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Ejercicios contables</h2>
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>dashboard">Inicio</a> /
            <a href="<?= BASE_URL ?>contabilidad">Contabilidad</a> / Ejercicios
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
        <a href="<?= BASE_URL ?>contabilidad/ejercicio-form" class="btn btn-primary">
            + Nuevo ejercicio
        </a>
    </div>
</div>

<?= flashRender() ?>

<div class="cont-card">
    <div class="cont-card-header">
        <div>
            <h3>Listado de ejercicios</h3>
            <p>El ejercicio <strong>abierto</strong> más reciente es el que se usa por defecto al cargar asientos.</p>
        </div>
    </div>
    <div class="cont-card-body">
        <?php if (empty($lista)): ?>
            <p style="text-align:center;color:var(--krown-text-muted);padding:30px 0;">
                No hay ejercicios creados.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Asientos</th>
                            <th>Estado</th>
                            <th>Creado por</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $e): ?>
                            <tr>
                                <td data-label="Nombre" class="cell-title">
                                    <span class="razon-social"><?= htmlspecialchars($e['nombre']) ?></span>
                                </td>
                                <td data-label="Desde"><?= date('d/m/Y', strtotime($e['fecha_desde'])) ?></td>
                                <td data-label="Hasta"><?= date('d/m/Y', strtotime($e['fecha_hasta'])) ?></td>
                                <td data-label="Asientos" class="mono"><?= (int)$e['cantidad_asientos'] ?></td>
                                <td data-label="Estado">
                                    <?php if ($e['estado'] === 'ABIERTO'): ?>
                                        <span class="badge badge-active">Abierto</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Cerrado</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Creado por"><?= htmlspecialchars($e['creado_por_nombre'] ?? '—') ?></td>
                                <td class="col-acciones">
                                    <a href="<?= BASE_URL ?>contabilidad/ejercicio-form?id=<?= (int)$e['id'] ?>"
                                       class="btn-icon" title="Editar">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4Z"/>
                                        </svg>
                                    </a>
                                    <?php if ($e['estado'] === 'ABIERTO'): ?>
                                        <button type="button" class="btn-icon btn-cont-cerrar-ejercicio"
                                                data-id="<?= (int)$e['id'] ?>" title="Cerrar ejercicio">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-icon btn-cont-reabrir-ejercicio"
                                                data-id="<?= (int)$e['id'] ?>" title="Reabrir ejercicio">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ((int)$e['cantidad_asientos'] === 0): ?>
                                        <button type="button" class="btn-icon btn-cont-eliminar-ejercicio"
                                                data-id="<?= (int)$e['id'] ?>" title="Eliminar">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                <path d="M10 11v6M14 11v6"/>
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