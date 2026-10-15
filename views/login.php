
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL; ?>dist/img/icon.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>dist/css/auth.css">
    <style>
    .mensaje {
       text-align:center;
    font-size: 13.5px;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    margin-bottom: 16px;
    display: block;
   background: rgba(178, 58, 72, .1); color: var(--krown-crimson); border: 1px solid rgba(178,58,72,.25); }
  
</style>
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
                <h1>Bienvenido</h1>
                <p class="subtitle">Ingresá con tu cuenta para acceder al sistema.</p>

                <div id="formMsg" class="msg-box" style="display:none;"></div>

                <form id="loginForm" name="loginForm" novalidate>
                    <div class="form-group">
                        <label for="loginemail">Email</label>
                        <input type="email" id="loginemail" name="loginemail" placeholder="tu@email.com" required autocomplete="username">
                    </div>
                    <div class="form-group pass-container">
                        <label for="loginpass">Contraseña</label>
                        <input type="password" id="loginpass" name="loginpass" placeholder="••••••••" required autocomplete="current-password">
                        <button id="togglePassword" type="button" class="pass-btn">
                            <img src="<?= BASE_URL; ?>dist/img/icon-eye-closed.png" id="imgpass" alt="Password">
                        </button>
                    </div>
                    <button type="submit" class="btn btn-primary">Ingresar</button>
                </form>

                <a href="<?= BASE_URL ?>forgot" class="auth-link">¿Olvidó su contraseña?</a>
            </div>
        </div>
    </div>
    <script>
        const BASE_URL = "<?php require_once 'config/conexion.php'; echo BASE_URL; ?>";
    </script>
    <script src="<?= BASE_URL; ?>dist/js/jquery-3.7.1.min.js"></script>
    <script src="<?= BASE_URL ?>dist/js/auth.js"></script>
</body>
</html>