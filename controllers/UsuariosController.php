<?php
/**
 * controllers/UsuariosController.php
 * CRUD de usuarios. Solo accesible para admin y developer.
 */

class UsuariosController
{
    private Usuario $usuario;

    public function __construct()
    {
        if (!puedeGestionarUsuarios()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para esta acción.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->usuario = new Usuario();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $usuarios = $this->usuario->listar($search, $status);

        $page_title     = 'Usuarios';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'usuarios.js';

        require_once ROOT_PATH . 'views' . DS . 'usuario.php';
    }

     /* ==========================================================
       BUSCAR
       ========================================================== */

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');

        // Importante: la vista parcial espera la variable $usuarios
        $usuarios = $this->usuario->listar($search, $status);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'usuarios_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html]);
    }

        /* ==========================================================
       EXPORTAR CSV
       ========================================================== */
    public function exportar(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $usuarios = $this->usuario->listar($search, $status);

        $filename = 'usuarios_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        // BOM UTF-8: sin esto, Excel muestra "Ã¡" en vez de "á"
        fwrite($out, "\xEF\xBB\xBF");

        // Encabezados (separador ";" para Excel en español)
        fputcsv($out, ['ID', 'Nombre', 'Email', 'Teléfono', 'Rol', 'Estado'], ';');

        foreach ($usuarios as $u) {
            fputcsv($out, [
                $u['id'],
                $u['nombre'],
                $u['email'],
                $u['tel'] ?? '',
                $u['nombre_rol'],
                (int)$u['estado'] === 1 ? 'Activo' : 'Inactivo',
            ], ';');
        }

        fclose($out);
        exit; // ← imprescindible: evita que el buffer de index.php agregue nada
    }

    /* ==========================================================
       IMPRIMIR / PDF
       ========================================================== */
    public function imprimir(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $usuarios = $this->usuario->listar($search, $status);

        // Filtros aplicados (para mostrar en el encabezado del reporte)
        $filtros = [];
        if ($search !== '') {
            $filtros[] = 'Búsqueda: "' . $search . '"';
        }
        if ($status !== '') {
            $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');
        }

        require_once ROOT_PATH . 'views' . DS . 'usuario_print.php';
    }

        /* ==========================================================
       DESCARGA PDF
       ========================================================== */
    public function pdf(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $usuarios = $this->usuario->listar($search, $status);

        $filtros = [];
        if ($search !== '') {
            $filtros[] = 'Búsqueda: "' . $search . '"';
        }
        if ($status !== '') {
            $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');
        }

        // Renderizar la vista a un string
        ob_start();
        require ROOT_PATH . 'views' . DS . 'usuario_pdf.php';
        $html = ob_get_clean();

        // Genera y descarga el PDF
        generarPdf($html, 'usuarios');
    }

    /* ==========================================================
       CREAR
       ========================================================== */
    public function crear(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
            return;
        }

        $modo           = 'crear';
        $usuario        = null;
        $roles          = $this->usuario->listarRoles();
        $page_title     = 'Nuevo Usuario';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'usuarios.js';

        require_once ROOT_PATH . 'views' . DS . 'usuario_form.php';
    }

    /* ==========================================================
       EDITAR
       ========================================================== */
    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'usuarios');
            exit;
        }

        $usuario = $this->usuario->obtenerPorId($id);
        if (!$usuario) {
            header('Location: ' . BASE_URL . 'usuarios');
            exit;
        }

        // Un admin NO puede tocar al developer
        if ((int)$usuario['rol'] === 1 && !esDeveloper()) {
            header('Location: ' . BASE_URL . 'usuarios');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar($id);
            return;
        }

        $modo           = 'editar';
        $roles          = $this->usuario->listarRoles();
        $page_title     = 'Editar Usuario';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'usuarios.js';

        require_once ROOT_PATH . 'views' . DS . 'usuario_form.php';
    }

    /* ==========================================================
       GUARDAR (crear o editar)
       ========================================================== */
    private function guardar(?int $id = null): void
    {
        $nombre   = trim($_POST['nombre']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $tel      = trim($_POST['tel']      ?? '');
        $password = $_POST['password']      ?? '';
        $rol      = (int)($_POST['rol']     ?? 0);
        $estado   = (int)($_POST['estado']  ?? 1);

        // Validaciones básicas
        if ($nombre === '' || $email === '') {
            jsonResponse(['ok' => false, 'msg' => 'Nombre y email son obligatorios.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['ok' => false, 'msg' => 'El email no tiene un formato válido.']);
        }
        if ($id === null && $password === '') {
            jsonResponse(['ok' => false, 'msg' => 'La contraseña es obligatoria para usuarios nuevos.']);
        }
        if ($password !== '' && strlen($password) < 6) {
            jsonResponse(['ok' => false, 'msg' => 'La contraseña debe tener al menos 6 caracteres.']);
        }

        $rolesValidos = array_column($this->usuario->listarRoles(), 'id');

        // En edición, si es developer, permitimos mantener su rol
        if ($id !== null) {
            $actual = $this->usuario->obtenerPorId($id);
            if ($actual && (int)$actual['rol'] === 1) {
                $rol = 1; // no se puede cambiar
            }
        }

        if (!in_array($rol, $rolesValidos, true)) {
            jsonResponse(['ok' => false, 'msg' => 'Rol inválido.']);
        }

        $data = [
            'nombre' => $nombre,
            'email'  => $email,
            'tel'    => $tel,
            'rol'    => $rol,
            'estado' => $estado,
        ];

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($id === null) {
            $nuevoId = $this->usuario->crear($data);
            if ($nuevoId === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear el usuario (el email ya existe).']);
            }
            jsonResponse(['ok' => true, 'msg' => 'Usuario creado con éxito.']);
        }

        if (!$this->usuario->actualizar($id, $data)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar (el email ya está en uso).']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Usuario actualizado con éxito.']);
    }

    /* ==========================================================
       ELIMINAR (soft delete)
       ========================================================== */
    public function eliminar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        // No me elimino a mí mismo
        if ($id === (int)$_SESSION['user_id']) {
            jsonResponse(['ok' => false, 'msg' => 'No puede eliminarse a sí mismo.']);
        }

        $target = $this->usuario->obtenerPorId($id);
        if (!$target) {
            jsonResponse(['ok' => false, 'msg' => 'El usuario no existe.']);
        }

        // Un admin no puede eliminar al developer
        if ((int)$target['rol'] === 1 && !esDeveloper()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para eliminar a este usuario.']);
        }

        if (!$this->usuario->cambiarEstado($id, 0)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo eliminar el usuario.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Usuario desactivado.']);
    }
}