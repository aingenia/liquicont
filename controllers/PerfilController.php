<?php
/**
 * controllers/PerfilController.php
 * Edición del perfil propio. Todos los roles.
 */

class PerfilController
{
    private Usuario $usuario;

    public function __construct()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }
        $this->usuario = new Usuario();
    }

    public function index(): void
    {
        $id = (int)$_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar($id);
            return;
        }

        $usuario        = $this->usuario->obtenerPorId($id);
        $page_title     = 'Mi Perfil';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'perfil.js';

        require_once ROOT_PATH . 'views' . DS . 'perfil.php';
    }

    private function guardar(int $id): void
    {
        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = trim($_POST['email']  ?? '');
        $tel      = trim($_POST['tel']    ?? '');
        $password = $_POST['password']    ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($nombre === '' || $email === '') {
            jsonResponse(['ok' => false, 'msg' => 'Nombre y email son obligatorios.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['ok' => false, 'msg' => 'El email no tiene un formato válido.']);
        }
        if ($password !== '' && strlen($password) < 6) {
            jsonResponse(['ok' => false, 'msg' => 'La contraseña debe tener al menos 6 caracteres.']);
        }
        if ($password !== '' && $password !== $confirm) {
            jsonResponse(['ok' => false, 'msg' => 'Las contraseñas no coinciden.']);
        }

        $data = [
            'nombre' => $nombre,
            'email'  => $email,
            'tel'    => $tel,
        ];
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (!$this->usuario->actualizarPerfil($id, $data)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar (el email ya está en uso).']);
        }

        // Refrescar sesión por si cambió nombre/email
        $_SESSION['user_nombre'] = $nombre;
        $_SESSION['user_email']  = $email;

        jsonResponse(['ok' => true, 'msg' => 'Perfil actualizado con éxito.']);
    }
}