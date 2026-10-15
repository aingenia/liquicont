<?php
/**
 * controllers/ClientesController.php
 * Admin/Dev: CRUD completo.
 * Vendedor: CRUD limitado (sin estado, categoría, vendedor ni eliminar).
 */

require_once ROOT_PATH . 'includes' . DS . 'ArcaClient.php';

class ClientesController
{
    private Cliente $cliente;

    public function __construct()
    {
        if (!puedeGestionarClientes()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para esta acción.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->cliente = new Cliente();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        // Vendedor: solo los suyos
        $vendedorFiltro = puedeVerTodosLosClientes() ? null : (int)$_SESSION['user_id'];

        $clientes = $this->cliente->listar($search, $status, $vendedorFiltro);

        $page_title     = 'Clientes';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'clientes.js';

        require_once ROOT_PATH . 'views' . DS . 'cliente.php';
    }

    /* ==========================================================
       BUSCAR (AJAX)
       ========================================================== */
    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $vendedorFiltro = puedeVerTodosLosClientes() ? null : (int)$_SESSION['user_id'];

        $clientes = $this->cliente->listar($search, $status, $vendedorFiltro);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'clientes_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html]);
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
        $cliente        = null;
        $combos         = $this->cliente->combos();
        $page_title     = 'Nuevo Cliente';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'clientes.js';

        require_once ROOT_PATH . 'views' . DS . 'cliente_form.php';
    }

    /* ==========================================================
       EDITAR
       ========================================================== */
    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'clientes');
            exit;
        }

        $cliente = $this->cliente->obtenerPorId($id);
        if (!$cliente) {
            header('Location: ' . BASE_URL . 'clientes');
            exit;
        }

        // Vendedor solo puede editar los suyos
        if (esVendedor() && (int)$cliente['vendedor_id'] !== (int)$_SESSION['user_id']) {
            header('Location: ' . BASE_URL . 'clientes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar($id);
            return;
        }

        $modo           = 'editar';
        $combos         = $this->cliente->combos();
        $page_title     = 'Editar Cliente';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'clientes.js';

        require_once ROOT_PATH . 'views' . DS . 'cliente_form.php';
    }

    /* ==========================================================
       GUARDAR
       ========================================================== */
    private function guardar(?int $id = null): void
    {
        $d = [
            'tipo_documento_id'    => (int)($_POST['tipo_documento_id'] ?? 0),
            'cuit'                 => trim($_POST['cuit'] ?? ''),
            'razon_social'         => trim($_POST['razon_social'] ?? ''),
            'nombre_fantasia'      => trim($_POST['nombre_fantasia'] ?? ''),
            'codigo'               => trim($_POST['codigo'] ?? ''),
            'personeria'           => $_POST['personeria'] ?? 'FISICA',
            'categoria'            => (int)($_POST['categoria'] ?? 0),
            'id_condicion_iva'     => (int)($_POST['id_condicion_iva'] ?? 0),
            'id_condicion_iibb'    => (int)($_POST['id_condicion_iibb'] ?? 0) ?: null,
            'nro_inscripcion_iibb' => trim($_POST['nro_inscripcion_iibb'] ?? ''),
            'email'                => trim($_POST['email'] ?? ''),
            'email_facturacion'    => trim($_POST['email_facturacion'] ?? ''),
            'telefono'             => trim($_POST['telefono'] ?? ''),
            'domicilio_fiscal'     => trim($_POST['domicilio_fiscal'] ?? ''),
            'domicilio_entrega'    => trim($_POST['domicilio_entrega'] ?? ''),
            'localidad'            => trim($_POST['localidad'] ?? ''),
            'provincia_id'         => (int)($_POST['provincia_id'] ?? 0) ?: null,
            'condicion_venta'      => $_POST['condicion_venta'] ?? 'CONTADO',
            'limite_credito'       => (float)($_POST['limite_credito'] ?? 0),
            'vendedor_id'          => (int)($_POST['vendedor_id'] ?? 0) ?: null,
            'estado'               => $_POST['estado'] ?? 'ACTIVO',
        ];

        // Validaciones básicas
        if ($d['razon_social'] === '' || $d['cuit'] === '') {
            jsonResponse(['ok' => false, 'msg' => 'Razón social y número de documento son obligatorios.']);
        }
        if ($d['tipo_documento_id'] <= 0 || $d['id_condicion_iva'] <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Tipo de documento y condición IVA son obligatorios.']);
        }

        // VENDEDOR: forzar reglas
        if (esVendedor()) {
            $d['estado']      = null; // no aplica
            $d['categoria']   = null; // no aplica
            $d['vendedor_id'] = (int)$_SESSION['user_id'];

            if ($id === null) {
                // Al crear, un vendedor no puede elegir categoría ni estado.
                // Forzamos categoría "Local" por defecto (id 3 según SQL).
                $d['categoria'] = 3;
                $d['estado']    = 'ACTIVO';
            }
        } else {
            // Admin/Dev
            if ($d['categoria'] <= 0) {
                jsonResponse(['ok' => false, 'msg' => 'La categoría es obligatoria.']);
            }
            if (!in_array($d['estado'], ['ACTIVO', 'INACTIVO'], true)) {
                $d['estado'] = 'ACTIVO';
            }
            if ($id === null && $d['vendedor_id'] === null) {
                // Admin puede dejarlo sin vendedor
            }
        }

        if ($id === null) {
            $nuevoId = $this->cliente->crear($d);
            if ($nuevoId === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear (el CUIT ya existe).']);
            }
            jsonResponse(['ok' => true, 'msg' => 'Cliente creado con éxito.']);
        }

        // Editar
        if (esVendedor()) {
            $ok = $this->cliente->actualizarComoVendedor($id, $d);
        } else {
            $ok = $this->cliente->actualizar($id, $d);
        }

        if (!$ok) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar (el CUIT ya está en uso).']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Cliente actualizado con éxito.']);
    }

    /* ==========================================================
       ELIMINAR (soft delete) — solo Admin/Dev
       ========================================================== */
    public function eliminar(): void
    {
        if (!puedeVerTodosLosClientes()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para eliminar clientes.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $cliente = $this->cliente->obtenerPorId($id);
        if (!$cliente) {
            jsonResponse(['ok' => false, 'msg' => 'El cliente no existe.']);
        }

        if (!$this->cliente->cambiarEstado($id, 'INACTIVO')) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo desactivar el cliente.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Cliente desactivado.']);
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

        // Mapeamos los datos devueltos por ARCA a los campos del form.
        // El campo `datos` trae: razon_social, personeria, domicilio_fiscal,
        // localidad, provincia_nombre, estado.
        jsonResponse(['ok' => true, 'msg' => $res['msg'], 'datos' => $res['datos']]);
    }

    /* ==========================================================
       EXPORTAR CSV / IMPRIMIR / PDF  
       ========================================================== */
           /* ==========================================================
       EXPORTAR CSV
       ========================================================== */
    public function exportar(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $vendedorFiltro = puedeVerTodosLosClientes() ? null : (int)$_SESSION['user_id'];

        $clientes = $this->cliente->listar($search, $status, $vendedorFiltro);

        $filename = 'clientes_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

        fputcsv($out, [
            'ID', 'Razón social', 'Nombre fantasía', 'CUIT',
            'Categoría', 'Categoría IVA', 'IIBB',
            'Email', 'Teléfono',
            'Domicilio fiscal', 'Domicilio entrega', 'Localidad', 'Provincia',
            'Condición venta', 'Límite crédito', 'Vendedor', 'Estado'
        ], ';');

        foreach ($clientes as $c) {
            fputcsv($out, [
                $c['id'],
                $c['razon_social'],
                $c['nombre_fantasia'] ?? '',
                $c['cuit'] ?? '',
                $c['categoria_desc'] ?? '',
                $c['condicion_iva_desc'] ?? '',
                $c['condicion_iibb_desc'] ?? '',
                $c['email'] ?? '',
                $c['telefono'] ?? '',
                $c['domicilio_fiscal'] ?? '',
                $c['domicilio_entrega'] ?? '',
                $c['localidad'] ?? '',
                $c['provincia_desc'] ?? '',
                $c['condicion_venta'] === 'CUENTA_CORRIENTE' ? 'Cuenta corriente' : 'Contado',
                number_format((float)($c['limite_credito'] ?? 0), 2, ',', '.'),
                $c['vendedor_nombre'] ?? 'Sin asignar',
                $c['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo',
            ], ';');
        }

        fclose($out);
        exit;
    }

    /* ==========================================================
       IMPRIMIR
       ========================================================== */
    public function imprimir(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $vendedorFiltro = puedeVerTodosLosClientes() ? null : (int)$_SESSION['user_id'];

        $clientes = $this->cliente->listar($search, $status, $vendedorFiltro);

        $filtros = [];
        if ($search !== '') {
            $filtros[] = 'Búsqueda: "' . $search . '"';
        }
        if ($status !== '') {
            $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');
        }
        if ($vendedorFiltro !== null) {
            $filtros[] = 'Vendedor: ' . ($_SESSION['user_nombre'] ?? '');
        }

        require_once ROOT_PATH . 'views' . DS . 'cliente_print.php';
    }

    /* ==========================================================
       PDF
       ========================================================== */
    public function pdf(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $vendedorFiltro = puedeVerTodosLosClientes() ? null : (int)$_SESSION['user_id'];

        $clientes = $this->cliente->listar($search, $status, $vendedorFiltro);

        $filtros = [];
        if ($search !== '') {
            $filtros[] = 'Búsqueda: "' . $search . '"';
        }
        if ($status !== '') {
            $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');
        }
        if ($vendedorFiltro !== null) {
            $filtros[] = 'Vendedor: ' . ($_SESSION['user_nombre'] ?? '');
        }

        ob_start();
        require ROOT_PATH . 'views' . DS . 'cliente_pdf.php';
        $html = ob_get_clean();

        generarPdf($html, 'clientes', 'landscape'); // landscape: hay muchas columnas
    }

}