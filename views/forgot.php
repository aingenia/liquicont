
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= BASE_URL; ?>dist/img/icon.png">
    <title>Recuperar — <?= APP_NAME; ?></title>
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
                <h1>Recuperar contraseña</h1>
                <p class="subtitle">Ingresá tu email y te enviaremos las instrucciones.</p>

                <div id="formMsg" class="msg-box" style="display:none;"></div>

                <form id="forgotForm" >
                    <div class="form-group">
                        <label for="forgotemail">EMAIL</label>
                        <input type="email" id="forgotemail" name="forgotemail" placeholder="tu@email.com" required autocomplete="email">
                    </div>
                    
                    <button type="submit"  class="btn btn-primary" id="forgotBtn">Enviar instrucciones</button>
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