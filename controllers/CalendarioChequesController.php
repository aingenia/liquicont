<?php
/**
 * controllers/CalendarioChequesController.php
 * Admin/Dev y Vendedor pueden agregar/editar cheques.
 * Productor solo lectura.
 */

class CalendarioChequesController
{
    private CalendarioCheque $model;

    public function __construct()
    {
        if (!puedeVerCalendario()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->model = new CalendarioCheque();
    }

    /* ==========================================================
       INDEX — vista del calendario
       ========================================================== */
    public function index(): void
    {
        $page_title     = 'Calendario de Cheques';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'calendario.js';
        $pageStyle      = 'calendario.css';

        require_once ROOT_PATH . 'views' . DS . 'calendario.php';
    }

    /* ==========================================================
       LISTAR por rango (AJAX — para el calendario)
       ========================================================== */
    public function listar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $desde = trim($_POST['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            jsonResponse(['ok' => false, 'msg' => 'Rango de fechas inválido.']);
        }

        $eventos = $this->model->listarRango($desde, $hasta);

        // Agrupar por fecha
        $porFecha = [];
        foreach ($eventos as $e) {
            $porFecha[$e['fecha']][] = $e;
        }

        jsonResponse(['ok' => true, 'eventos' => $porFecha]);
    }

        /* ==========================================================
       LISTAR por fecha específica (AJAX — panel del modal)
       ========================================================== */
    public function listarPorFecha(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $fecha = trim($_POST['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }

        $cheques = $this->model->listarPorFecha($fecha);
        $total   = array_sum(array_column($cheques, 'importe'));

        jsonResponse([
            'ok'      => true,
            'cheques' => $cheques,
            'total'   => $total,
        ]);
    }

    /* ==========================================================
       CREAR
       ========================================================== */
    public function crear(): void
    {
        if (!puedeEditarCalendario()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $d = [
            'fecha'       => trim($_POST['fecha'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'importe'     => (float)($_POST['importe'] ?? 0),
            'banco'       => trim($_POST['banco'] ?? '') ?: null,
            'nro_cheque'  => trim($_POST['nro_cheque'] ?? '') ?: null,
            'tipo'        => in_array($_POST['tipo'] ?? '', ['PROPIO', 'TERCERO'], true) ? $_POST['tipo'] : 'TERCERO',
        ];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['fecha'])) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }
        
        if ($d['importe'] <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'El importe debe ser mayor a 0.']);
        }

        $id = $this->model->crear($d, (int)$_SESSION['user_id']);
        if ($id === false) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo guardar el cheque.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Cheque registrado.', 'id' => $id]);
    }

    /* ==========================================================
       ACTUALIZAR
       ========================================================== */
    public function actualizar(): void
    {
        if (!puedeEditarCalendario()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->model->obtenerPorId($id)) {
            jsonResponse(['ok' => false, 'msg' => 'El cheque no existe.']);
        }

        $d = [
            'fecha'       => trim($_POST['fecha'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'importe'     => (float)($_POST['importe'] ?? 0),
            'banco'       => trim($_POST['banco'] ?? '') ?: null,
            'nro_cheque'  => trim($_POST['nro_cheque'] ?? '') ?: null,
            'tipo'        => in_array($_POST['tipo'] ?? '', ['PROPIO', 'TERCERO'], true) ? $_POST['tipo'] : 'TERCERO',
        ];

        if (!$this->model->actualizar($id, $d)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Cheque actualizado.']);
    }

    /* ==========================================================
       ELIMINAR
       ========================================================== */
    public function eliminar(): void
    {
        if (!puedeEditarCalendario()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        if (!$this->model->eliminar($id)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo eliminar.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Cheque eliminado.']);
    }

    /* ==========================================================
       AYUDA
       ========================================================== */
    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'calendario.php';
    }
}