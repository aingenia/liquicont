<?php
/**
 * controllers/ProveedoresController.php
 * Solo Admin/Developer.
 */

require_once ROOT_PATH . 'includes' . DS . 'ArcaClient.php';

class ProveedoresController
{
    private Proveedor $proveedor;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para esta acción.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->proveedor = new Proveedor();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $proveedores = $this->proveedor->listar($search, $status);

        $page_title     = 'Proveedores';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'proveedores.js';

        require_once ROOT_PATH . 'views' . DS . 'proveedor.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $proveedores = $this->proveedor->listar($search, $status);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'proveedores_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html]);
    }

    /* ==========================================================
       CREAR / EDITAR
       ========================================================== */
    public function crear(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
            return;
        }

        $modo           = 'crear';
        $proveedor      = null;
        $combos         = $this->proveedor->combos();
        $page_title     = 'Nuevo Proveedor';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'proveedores.js';

        require_once ROOT_PATH . 'views' . DS . 'proveedor_form.php';
    }

    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'proveedores');
            exit;
        }

        $proveedor = $this->proveedor->obtenerPorId($id);
        if (!$proveedor) {
            header('Location: ' . BASE_URL . 'proveedores');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar($id);
            return;
        }

        $modo           = 'editar';
        $combos         = $this->proveedor->combos();
        $page_title     = 'Editar Proveedor';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'proveedores.js';

        require_once ROOT_PATH . 'views' . DS . 'proveedor_form.php';
    }

    private function guardar(?int $id = null): void
    {
        $d = [
            'tipo_documento_id'    => (int)($_POST['tipo_documento_id'] ?? 0),
            'cuit'                 => trim($_POST['cuit'] ?? ''),
            'razon_social'         => trim($_POST['razon_social'] ?? ''),
            'nombre_fantasia'      => trim($_POST['nombre_fantasia'] ?? ''),
            'codigo'               => trim($_POST['codigo'] ?? ''),
            'personeria'           => $_POST['personeria'] ?? 'FISICA',
            'id_condicion_iva'     => (int)($_POST['id_condicion_iva'] ?? 0),
            'id_condicion_iibb'    => (int)($_POST['id_condicion_iibb'] ?? 0) ?: null,
            'nro_inscripcion_iibb' => trim($_POST['nro_inscripcion_iibb'] ?? ''),
            'email'                => trim($_POST['email'] ?? ''),
            'email_facturacion'    => trim($_POST['email_facturacion'] ?? ''),
            'telefono'             => trim($_POST['telefono'] ?? ''),
            'domicilio_fiscal'     => trim($_POST['domicilio_fiscal'] ?? ''),
            'localidad'            => trim($_POST['localidad'] ?? ''),
            'provincia_id'         => (int)($_POST['provincia_id'] ?? 0) ?: null,
            'estado'               => $_POST['estado'] ?? 'ACTIVO',
        ];

        if ($d['razon_social'] === '' || $d['cuit'] === '') {
            jsonResponse(['ok' => false, 'msg' => 'Razón social y CUIT son obligatorios.']);
        }
        if ($d['tipo_documento_id'] <= 0 || $d['id_condicion_iva'] <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Tipo de documento y categoría impositiva son obligatorios.']);
        }
        if (!in_array($d['estado'], ['ACTIVO', 'INACTIVO'], true)) {
            $d['estado'] = 'ACTIVO';
        }

        if ($id === null) {
            $nuevoId = $this->proveedor->crear($d);
            if ($nuevoId === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear (el CUIT ya existe).']);
            }
            jsonResponse(['ok' => true, 'msg' => 'Proveedor creado con éxito.']);
        }

        if (!$this->proveedor->actualizar($id, $d)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar (el CUIT ya está en uso).']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Proveedor actualizado con éxito.']);
    }

    public function eliminar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $proveedor = $this->proveedor->obtenerPorId($id);
        if (!$proveedor) {
            jsonResponse(['ok' => false, 'msg' => 'El proveedor no existe.']);
        }

        if (!$this->proveedor->cambiarEstado($id, 'INACTIVO')) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo desactivar el proveedor.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Proveedor desactivado.']);
    }

    /* ==========================================================
       ARCA — autocompletar por CUIT
       ========================================================== */
    public function arca(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $cuit = trim($_POST['cuit'] ?? '');
        if ($cuit === '') {
            jsonResponse(['ok' => false, 'msg' => 'Ingresá un CUIT para consultar.']);
        }

        try {
            $arca = new ArcaClient();
            $res  = $arca->consultarPersona($cuit);
        } catch (\Throwable $e) {
            jsonResponse(['ok' => false, 'msg' => 'Error al consultar ARCA: ' . $e->getMessage()]);
        }

        if (!$res['ok']) {
            jsonResponse(['ok' => false, 'msg' => $res['msg']]);
        }

        jsonResponse(['ok' => true, 'msg' => $res['msg'], 'datos' => $res['datos']]);
    }

    /* ==========================================================
       EXPORTAR CSV / IMPRIMIR / PDF
       ========================================================== */
    public function exportar(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $proveedores = $this->proveedor->listar($search, $status);

        $filename = 'proveedores_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['ID', 'Razón social', 'Nombre fantasía', 'CUIT',
                       'Categoría IVA', 'IIBB', 'Nro IIBB',
                       'Email', 'Teléfono',
                       'Domicilio', 'Localidad', 'Provincia', 'Estado'], ';');

        foreach ($proveedores as $p) {
            fputcsv($out, [
                $p['id'],
                $p['razon_social'],
                $p['nombre_fantasia'] ?? '',
                $p['cuit'] ?? '',
                $p['condicion_iva_desc'] ?? '',
                $p['condicion_iibb_desc'] ?? '',
                $p['nro_inscripcion_iibb'] ?? '',
                $p['email'] ?? '',
                $p['telefono'] ?? '',
                $p['domicilio_fiscal'] ?? '',
                $p['localidad'] ?? '',
                $p['provincia_desc'] ?? '',
                $p['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function imprimir(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $proveedores = $this->proveedor->listar($search, $status);

        $filtros = [];
        if ($search !== '') $filtros[] = 'Búsqueda: "' . $search . '"';
        if ($status !== '') $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');

        require_once ROOT_PATH . 'views' . DS . 'proveedor_print.php';
    }

    public function pdf(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $proveedores = $this->proveedor->listar($search, $status);

        $filtros = [];
        if ($search !== '') $filtros[] = 'Búsqueda: "' . $search . '"';
        if ($status !== '') $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');

        ob_start();
        require ROOT_PATH . 'views' . DS . 'proveedor_pdf.php';
        $html = ob_get_clean();

        generarPdf($html, 'proveedores', 'landscape');
    }
}