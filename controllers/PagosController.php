<?php
/**
 * controllers/PagosController.php
 * Solo Admin/Dev.
 */

class PagosController
{
    private Pago $pago;
    private CuentaCorrienteProveedor $cc;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->pago = new Pago();
        $this->cc   = new CuentaCorrienteProveedor();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $desde       = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta       = $_GET['hasta'] ?? date('Y-m-d');
        $proveedorId = (int)($_GET['proveedor_id'] ?? 0);
        $busqueda    = trim($_GET['busqueda'] ?? '');
        $estado      = trim($_GET['estado'] ?? '');

        $pagos  = $this->pago->listar($desde, $hasta, $proveedorId, $busqueda, $estado);
        $combos = $this->pago->combos();

        // ← NUEVO: carga inicial del listado de estado de compras
        // Carga inicial del listado de estado de compras
        $compras = $this->pago->listarComprasConEstado('', '', 0, 'POR_PAGAR', '');

        $page_title     = 'Pagos a Proveedores';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'pagos.js';
        $pageStyle      = 'pagos.css';

        require_once ROOT_PATH . 'views' . DS . 'pago.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $desde       = trim($_POST['desde'] ?? '');
        $hasta       = trim($_POST['hasta'] ?? '');
        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        $busqueda    = trim($_POST['busqueda'] ?? '');
        $estado      = trim($_POST['estado'] ?? '');

        $pagos = $this->pago->listar($desde, $hasta, $proveedorId, $busqueda, $estado);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'pagos_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($pagos)]);
    }

    /* ==========================================================
       VER
       ========================================================== */
    public function ver(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { header('Location: ' . BASE_URL . 'pagos'); exit; }

        $pago = $this->pago->obtenerPorId($id);
        if (!$pago) { header('Location: ' . BASE_URL . 'pagos'); exit; }

        $medios  = $this->pago->obtenerMedios($id);
        $compras = $this->pago->obtenerComprasAplicadas($id);
        $saldo   = $this->cc->obtenerSaldo((int)$pago['proveedor_id']);

        $page_title     = 'Pago #' . $id;
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'pagos.js';
        $pageStyle      = 'pagos.css';

        require_once ROOT_PATH . 'views' . DS . 'pago_ver.php';
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

        $combos         = $this->pago->combos();
        $proveedorIdPre = (int)($_GET['proveedor_id'] ?? 0);

        $page_title     = 'Nuevo pago';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'pagos.js';
        $pageStyle      = 'pagos.css';

        require_once ROOT_PATH . 'views' . DS . 'pago_form.php';
    }

    private function guardar(): void
    {
        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        $fecha       = trim($_POST['fecha'] ?? '');
        $notas       = trim($_POST['notas'] ?? '') ?: null;

        if ($proveedorId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un proveedor.']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }

        $medios     = json_decode($_POST['medios'] ?? '[]', true) ?: [];
        $imputacion = json_decode($_POST['imputacion'] ?? '[]', true) ?: [];

        if (empty($medios)) {
            jsonResponse(['ok' => false, 'msg' => 'Cargá al menos un medio de pago.']);
        }
        if (empty($imputacion)) {
            jsonResponse(['ok' => false, 'msg' => 'Imputá el pago a al menos una compra.']);
        }

        $res = $this->pago->crear(
            [
                'proveedor_id' => $proveedorId,
                'fecha'        => $fecha,
                'notas'        => $notas,
            ],
            $medios,
            $imputacion,
            (int)$_SESSION['user_id']
        );

        if (!$res['ok']) {
            jsonResponse($res);
        }

        jsonResponse([
            'ok'       => true,
            'msg'      => $res['msg'],
            'redirect' => BASE_URL . 'pagos/ver?id=' . $res['pago_id'],
        ]);
    }

    /* ==========================================================
       ANULAR
       ========================================================== */
    public function anular(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id     = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');

        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        jsonResponse($this->pago->anular($id, $motivo, (int)$_SESSION['user_id']));
    }

    /* ==========================================================
       AJAX: compras pendientes de un proveedor
       ========================================================== */
    public function comprasPendientes(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Proveedor inválido.']);
        }

        $compras = $this->pago->comprasPendientes($proveedorId);
        $saldo   = $this->cc->obtenerSaldo($proveedorId);

        jsonResponse([
            'ok'      => true,
            'compras' => $compras,
            'saldo'   => $saldo,
        ]);
    }

    /* ==========================================================
       EXPORTAR / AYUDA
       ========================================================== */
    public function exportar(): void
    {
        $desde       = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta       = $_GET['hasta'] ?? date('Y-m-d');
        $proveedorId = (int)($_GET['proveedor_id'] ?? 0);
        $busqueda    = trim($_GET['busqueda'] ?? '');
        $estado      = trim($_GET['estado'] ?? '');

        $pagos = $this->pago->listar($desde, $hasta, $proveedorId, $busqueda, $estado);

        $filename = 'pagos_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'ID', 'Fecha', 'Proveedor', 'CUIT', 'Total',
            'Compras', 'Estado', 'Creado por',
        ], ';');

        foreach ($pagos as $p) {
            fputcsv($out, [
                $p['id'],
                date('d/m/Y', strtotime($p['fecha'])),
                $p['proveedor_razon_social'],
                $p['proveedor_cuit'] ?? '',
                number_format((float)$p['total'], 2, ',', '.'),
                (int)$p['compras_count'],
                $p['estado'],
                $p['creado_por_nombre'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'pagos.php';
    }

      /* ==========================================================
       LISTAR COMPRAS CON ESTADO DE PAGO (AJAX)
       ========================================================== */
    public function buscarCompras(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $desde       = trim($_POST['desde'] ?? '');
        $hasta       = trim($_POST['hasta'] ?? '');
        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        $busqueda    = trim($_POST['busqueda'] ?? '');
        $estadoPago  = trim($_POST['estado_pago'] ?? '');

        $compras = $this->pago->listarComprasConEstado(
            $desde, $hasta, $proveedorId, $estadoPago, $busqueda
        );

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'compras_estado_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($compras)]);
    }
}