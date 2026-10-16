<?php
/**
 * controllers/AuthController.php
 * 
 * Controlador de autenticación.
 * Maneja: login, logout, forgot (solicitar reset), reset (aplicar nueva contraseña).
 * 
 * Convención: GET → muestra vista, POST → procesa y responde JSON.
 */

class AuthController
{
    private Usuario $usuario;

    public function __construct()
    {
        $this->usuario = new Usuario();
    }

    /* ==========================================================
       LOGIN
       ========================================================== */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleLogin();
            return;
        }

        // Si ya está logueado, redirigir al dashboard
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        require_once ROOT_PATH . 'views' . DS . 'login.php';
    }

    private function handleLogin(): void
    {
        $this->jsonHeader();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->json(['ok' => false, 'msg' => 'Por favor, complete todos los campos.']);
        }

        // ---- Detectar tenant actual ----
        $tenantId = Tenant::id();

        if ($tenantId === null) {
            // En producción significa que no hay subdominio válido.
            // En desarrollo, con el fallback, esto no debería pasar.
            $this->json([
                'ok'  => false,
                'msg' => 'No se pudo identificar la empresa. Verifique el dominio de acceso.',
            ]);
        }

        // ---- Buscar usuario en el tenant ----
        $user = $this->usuario->obtenerPorEmailYTenant($email, $tenantId);
        $esSuperAdmin = false;

        if (!$user) {
            // No pertenece a este tenant. Puede ser super admin.
            $candidatoSuper = $this->usuario->obtenerSuperAdminPorEmail($email);

            if ($candidatoSuper && password_verify($password, $candidatoSuper['password'])) {
                // Es super admin: dejarlo pasar como si fuera admin del tenant
                $user = $candidatoSuper;
                $user['nombre_rol']     = 'Developer';
                $user['rol_slug']       = 'developer';
                $user['tenant_role_id'] = 1; // developer
                $user['tenant_id']      = $tenantId;
                $esSuperAdmin           = true;
            }
        }

        // ---- Validar credenciales ----
        if (!$user || !password_verify($password, $user['password'])) {
            // Mensaje genérico: no revela si existe, si está en otro tenant, etc.
            $this->json(['ok' => false, 'msg' => 'El correo electrónico o la contraseña son incorrectos.']);
        }

        if ((int)$user['estado'] !== 1) {
            $this->json(['ok' => false, 'msg' => 'Su cuenta está inactiva. Contacte al administrador.']);
        }

        // ---- Regenerar sesión ----
        session_regenerate_id(true);

        // ---- Guardar sesión ----
        $_SESSION['user_id']          = (int)$user['id'];
        $_SESSION['user_nombre']      = $user['nombre'];
        $_SESSION['user_email']       = $user['email'];
        $_SESSION['user_rol']         = (int)$user['tenant_role_id'];
        $_SESSION['user_rol_nombre']  = $user['nombre_rol'] ?? '';
        $_SESSION['user_rol_slug']    = $user['rol_slug'] ?? '';
        $_SESSION['user_avatar']      = $user['avatar'] ?? null;

        $_SESSION['tenant_id']        = (int)$user['tenant_id'];
        $_SESSION['tenant_name']      = Tenant::name();

        $_SESSION['is_super_admin']   = $esSuperAdmin ? 1 : 0;

        $this->json([
            'ok'       => true,
            'msg'      => 'Acceso concedido. Redireccionando...',
            'redirect' => BASE_URL . 'dashboard',
        ]);
    }

    /* ==========================================================
       LOGOUT
       ========================================================== */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        header('Location: ' . BASE_URL . 'login');
        exit;
    }

    /* ==========================================================
       FORGOT (solicitar link de reset)
       ========================================================== */
    public function forgot(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleForgot();
            return;
        }
        require_once ROOT_PATH . 'views' . DS . 'forgot.php';
    }

     // HandleForgot

    private function handleForgot(): void
    {
        $this->jsonHeader();

        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $this->json(['ok' => false, 'msg' => 'Ingrese su correo electrónico.']);
        }

        $user = $this->usuario->obtenerPorEmail($email);

        // Respuesta genérica por seguridad (no revelar si el email existe)
        $okMsg = 'Si el correo está registrado, recibirá un enlace para restablecer su contraseña.';

        if (!$user) {
            $this->json(['ok' => true, 'msg' => $okMsg]);
        }

        $token       = bin2hex(random_bytes(32));
        $expiracion  = date('Y-m-d H:i:s', strtotime('+1 hour'));

        if (!$this->usuario->guardarTokenReset($user['id'], $token, $expiracion)) {
            $this->json(['ok' => false, 'msg' => 'Error al procesar la solicitud. Intente nuevamente.']);
        }

        $link = BASE_URL . 'reset?token=' . urlencode($token);

        // ------------------------------------------------------------------
        // 1) Renderizar el template del email
        // ------------------------------------------------------------------
        $nombre = $user['nombre'];

                // ------------------------------------------------------------------
        // 1) Renderizar el template del email (con verificación previa)
        // ------------------------------------------------------------------
        $tplPath = ROOT_PATH . 'views' . DS . 'emails' . DS . 'reset_password.php';
        $html    = '';

        if (is_file($tplPath)) {
            $nombre = $user['nombre'];
            ob_start();
            require $tplPath;
            $html = ob_get_clean();
        } else {
            // Fallback: si el template no existe, al menos mandamos el link
            error_log('[handleForgot] Template no encontrado: ' . $tplPath);
            $html = '<p>Hola ' . htmlspecialchars($user['nombre']) . ',</p>'
                  . '<p>Para restablecer tu contraseña, ingresá a este enlace:</p>'
                  . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>';
        }


        // ------------------------------------------------------------------
        // 2) Enviar el email (con logging detallado)
        // ------------------------------------------------------------------
        error_log('[handleForgot] Intentando enviar email a: ' . $user['email']);

        $enviado = false;

        try {
            require_once ROOT_PATH . 'includes' . DS . 'Mailer.php';

            error_log('[handleForgot] Mailer.php cargado, llamando a Mailer::enviar()');

            $enviado = Mailer::enviar(
                $user['email'],
                'Restablecer contraseña — ' . APP_NAME,
                $html,
                null,
                $user['nombre']
            );

            error_log('[handleForgot] Mailer::enviar() devolvió: ' . ($enviado ? 'TRUE' : 'FALSE'));
        } catch (\Throwable $e) {
            error_log('[handleForgot] EXCEPCIÓN: ' . $e->getMessage()
                    . ' en ' . $e->getFile() . ':' . $e->getLine());
        }

        // ------------------------------------------------------------------
        // 3) Respuesta al cliente
        // ------------------------------------------------------------------
        $payload = ['ok' => true, 'msg' => $okMsg];

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $payload['debug_link']  = $link;
            $payload['debug_envio'] = $enviado ? 'ok' : 'falló';
        }

        $this->json($payload);
    }
    /* ==========================================================
       RESET (aplicar nueva contraseña)
       ========================================================== */
    public function reset(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleReset();
            return;
        }
        require_once ROOT_PATH . 'views' . DS . 'reset.php';
    }

    private function handleReset(): void
    {
        $this->jsonHeader();

        $token    = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($token === '' || $password === '' || $confirm === '') {
            $this->json(['ok' => false, 'msg' => 'Complete todos los campos.']);
        }

        if ($password !== $confirm) {
            $this->json(['ok' => false, 'msg' => 'Las contraseñas no coinciden.']);
        }

        if (strlen($password) < 6) {
            $this->json(['ok' => false, 'msg' => 'La contraseña debe tener al menos 6 caracteres.']);
        }

        $resetReq = $this->usuario->obtenerResetPorToken($token);

        if (!$resetReq) {
            $this->json(['ok' => false, 'msg' => 'El enlace es inválido o ha expirado. Solicite uno nuevo.']);
        }

        if (strtotime($resetReq['expira_en']) < time()) {
            $this->json(['ok' => false, 'msg' => 'El enlace ha expirado. Solicite uno nuevo.']);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        if (!$this->usuario->actualizarPassword($resetReq['user_id'], $hash)) {
            $this->json(['ok' => false, 'msg' => 'No se pudo actualizar la contraseña.']);
        }

        // (Opcional) invalidar otros tokens del mismo usuario
        //$this->usuario->borrarTokensDeUsuario($resetReq['user_id']);

        $this->json([
            'ok'       => true,
            'msg'      => 'Contraseña actualizada con éxito. Redirigiendo al login...',
            'redirect' => BASE_URL . 'login',
        ]);
    }

    /* ==========================================================
       HELPERS
       ========================================================== */
    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    private function json(array $data): void
    {
        echo json_encode($data);
        exit;
    }
}