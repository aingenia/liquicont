<?php
$token = $_GET['token'] ?? '';
if ($token === '') {
    header('Location: ' . BASE_URL . 'login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= BASE_URL; ?>dist/img/icon.png">
    <title>Nueva contraseña — <?= APP_NAME; ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>dist/css/auth.css">
    
</head>
<body>
    <div class="auth-screen">
        <div class="auth-brand">
            <div class="brand-content">
                <img src="<?= BASE_URL; ?>dist/img/logo-krown.png" alt="<?= APP_NAME; ?>">
                <p>Sistema de gestión</p>
            </div>

            <footer class="auth-footer">
                <span>Desarrollado por</span>
                <a href="https://xentsoft.com" target="_blank" rel="noopener noreferrer">
                    <img src="<?= BASE_URL; ?>dist/img/design-by-white.png" alt="XentSoft">
                </a>
            </footer>
        </div>

        <div class="auth-panel">
            <div class="auth-card">
                <h1>Nueva contraseña</h1>
                <p class="subtitle">Ingresá tu nueva contraseña para acceder.</p>

                <div id="formMsg" class="msg-box" style="display:none;"></div>

                <!-- Pasamos el token como campo oculto -->
                <form id="resetForm">
                    <!-- Asegúrate de que esta línea esté en el formulario -->
                    <input type="hidden" id="token" name="token" value="<?php echo isset($token) ? htmlspecialchars($token) : ''; ?>">

                    <div class="form-group pass-container">
                        <label for="new_password">Nueva contraseña</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Mínimo 6 caracteres" required minlength="6" autocomplete="new-password">
                        <button id="togglePasswordn" tyoe="button" class="pass-btn">
                            <img src="<?= BASE_URL; ?>dist/img/icon-eye-closed.png" id="imgpassn" alt="Password">
                        </button>
                    </div>
                    <div class="form-group pass-container">
                        <label for="confirm_password">Confirmar contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repetí la contraseña" required minlength="6" autocomplete="new-password">
                        <button id="togglePasswordc" type="button" class="pass-btn">
                            <img src="<?= BASE_URL; ?>dist/img/icon-eye-closed.png" id="imgpassc" alt="Password">
                        </button>
                    </div>
                    <button type="submit" class="btn btn-primary" id="resetBtn">Cambiar contraseña</button>
                </form>

                <a href="login" class="auth-link">Volver al inicio de sesión</a>
            </div>
        </div>
    </div>
    <script>
        const BASE_URL = '<?= BASE_URL; ?>';
    </script>
    <script src="<?= BASE_URL; ?>dist/js/jquery-3.7.1.min.js"></script>
    <script src="<?= BASE_URL; ?>dist/js/auth.js"></script>
</body>
</html>