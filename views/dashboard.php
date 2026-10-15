<?php
/**
 * views/dashboard.php
 * Contenido del escritorio. El controller definió:
 *   - $page_title
 *   - $headerGreeting
 */

$headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
$page_title     = 'Escritorio';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<h2 style="margin-top:0;">¡Bienvenido, <?= htmlspecialchars($_SESSION['user_nombre']) ?>!</h2>
<p style="color:var(--krown-text-muted);">
    Rol: <strong><?= htmlspecialchars($_SESSION['user_rol_nombre'] ?? '—') ?></strong>
</p>

<div class="content-card">
    <p>Este es el escritorio. A medida que agregues módulos vas a ver acá los accesos rápidos y widgets.</p>
</div>
<?php if (!empty($chequesManana)): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>⚠ Cheques que vencen mañana (<?= date('d/m/Y', strtotime('+1 day')) ?>)</h3>
        <p><?= count($chequesManana) ?> cheque<?= count($chequesManana) === 1 ? '' : 's' ?> · Total: <strong>$ <?= number_format($totalManana, 2, ',', '.') ?></strong></p>
    </div>
    <div class="card-body card-body-stack">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Banco</th>
                        <th>N° Cheque</th>
                        <th style="text-align:right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chequesManana as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['descripcion']) ?></td>
                            <td><?= htmlspecialchars($c['banco'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($c['nro_cheque'] ?? '—') ?></td>
                            <td style="text-align:right;font-weight:600;">
                                $ <?= number_format((float)$c['importe'], 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;text-align:right;">
            <a href="<?= BASE_URL ?>calendario" class="btn btn-outline">Ver calendario completo →</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php';