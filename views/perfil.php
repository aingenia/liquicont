<?php
$headerGreeting = $headerGreeting ?? 'HOLA.';
$page_title     = $page_title     ?? 'Mi Perfil';

require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'header.php';
?>

<div class="page-header">
    <div>
        <h2>Mi Perfil</h2>
        <div class="breadcrumb">Inicio / Mi Perfil</div>
    </div>
</div>

<div id="formMsg" class="msg-box" style="display:none;"></div>

<form id="perfilForm" autocomplete="off">
    <div class="card">
        <div class="card-header">
            <h3>Datos personales</h3>
            <p>Podés actualizar tus datos. El rol solo puede cambiarlo un administrador.</p>
        </div>
        <div class="card-body">
            <div class="form-group field-full">
                <label for="nombre">Nombre completo *</label>
                <input type="text" id="nombre" name="nombre" required
                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="tel">Teléfono</label>
                <input type="text" id="tel" name="tel"
                       value="<?= htmlspecialchars($usuario['tel'] ?? '') ?>">
            </div>

            <div class="form-group field-full">
                <label>Rol</label>
                <input type="text" value="<?= htmlspecialchars($usuario['nombre_rol'] ?? '') ?>" disabled>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Cambiar contraseña</h3>
            <p>Dejá en blanco si no querés cambiarla.</p>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6">
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</form>

<?php require_once ROOT_PATH . 'views' . DS . 'layouts' . DS . 'footer.php'; ?>