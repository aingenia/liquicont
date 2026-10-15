<?php
/**
 * controllers/CobrosController.php
 * Admin/Dev: todo.
 * Vendedor: cobra solo a sus clientes asignados.
 */

class CobrosController
{
    private Cobro $cobro;
    private CuentaCorriente $cc;

    public function __construct()
    {
        if (!puedeVerCobros()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->cobro = new Cobro();
        $this->cc    = new CuentaCorriente();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $desde    = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        $busqueda = trim($_GET['busqueda'] ?? '');
        $estado   = trim($_GET['estado'] ?? '');

        $vendedorFiltro = esVendedor() ? (int)$_SESSION['user_id'] : null;

        $cobros   = $this->cobro->listar($desde, $hasta, $clienteId, $busqueda, $estado);
        $combos   = $this->cobro->combos($vendedorFiltro);

        $page_title     = 'Cobros';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'cobros.js';
        $pageStyle      = 'cobros.css';

        require_once ROOT_PATH . 'views' . DS . 'cobro.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $desde    = trim($_POST['desde'] ?? '');
        $hasta    = trim($_POST['hasta'] ?? '');
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $busqueda = trim($_POST['busqueda'] ?? '');
        $estado   = trim($_POST['estado'] ?? '');

        $cobros = $this->cobro->listar($desde, $hasta, $clienteId, $busqueda, $estado);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'cobros_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($cobros)]);
    }

    /* ==========================================================
       VER
       ========================================================== */
    public function ver(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { header('Location: ' . BASE_URL . 'cobros'); exit; }

        $cobro = $this->cobro->obtenerPorId($id);
        if (!$cobro) { header('Location: ' . BASE_URL . 'cobros'); exit; }

        if (esVendedor()) {
            $combosCli = $this->cobro->combos((int)$_SESSION['user_id']);
            $ids = array_column($combosCli['clientes'], 'id');
            if (!in_array((int)$cobro['cliente_id'], $ids, true)) {
                header('Location: ' . BASE_URL . 'cobros');
                exit;
            }
        }

        $medios    = $this->cobro->obtenerMedios($id);
        $facturas  = $this->cobro->obtenerFacturasAplicadas($id);
        $saldo     = $this->cc->obtenerSaldo((int)$cobro['cliente_id']);

        $page_title     = 'Cobro #' . $id;
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'cobros.js';
        $pageStyle      = 'cobros.css';

        require_once ROOT_PATH . 'views' . DS . 'cobro_ver.php';
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

        $vendedorFiltro = esVendedor() ? (int)$_SESSION['user_id'] : null;
        $combos         = $this->cobro->combos($vendedorFiltro);

        $clienteIdPre   = (int)($_GET['cliente_id'] ?? 0);

        $page_title     = 'Nuevo cobro';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'cobros.js';
        $pageStyle      = 'cobros.css';

        require_once ROOT_PATH . 'views' . DS . 'cobro_form.php';
    }

    private function guardar(): void
    {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $fecha     = trim($_POST['fecha'] ?? '');
        $notas     = trim($_POST['notas'] ?? '') ?: null;

        if ($clienteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un cliente.']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }

        $mediosRaw     = $_POST['medios'] ?? '[]';
        $imputacionRaw = $_POST['imputacion'] ?? '[]';
        $medios     = json_decode($mediosRaw, true) ?: [];
        $imputacion = json_decode($imputacionRaw, true) ?: [];

        if (empty($medios)) {
            jsonResponse(['ok' => false, 'msg' => 'Cargá al menos un medio de pago.']);
        }
        if (empty($imputacion)) {
            jsonResponse(['ok' => false, 'msg' => 'Imputá el cobro a al menos una factura.']);
        }

        $res = $this->cobro->crear(
            [
                'cliente_id' => $clienteId,
                'fecha'      => $fecha,
                'notas'      => $notas,
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
            'redirect' => BASE_URL . 'cobros/ver?id=' . $res['cobro_id'],
        ]);
    }

    /* ==========================================================
       ANULAR
       ========================================================== */
    public function anular(): void
    {
        if (!esAdmin()) {
            jsonResponse(['ok' => false, 'msg' => 'Solo el administrador puede anular cobros.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id     = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');

        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        jsonResponse($this->cobro->anular($id, $motivo, (int)$_SESSION['user_id']));
    }

    /* ==========================================================
       AJAX: facturas pendientes de un cliente
       ========================================================== */
    public function facturasPendientes(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Cliente inválido.']);
        }

        $facturas = $this->cobro->facturasPendientes($clienteId);
        $saldo    = $this->cc->obtenerSaldo($clienteId);

        jsonResponse([
            'ok'       => true,
            'facturas' => $facturas,
            'saldo'    => $saldo,
        ]);
    }

    /* ==========================================================
       EXPORTAR / AYUDA
       ========================================================== */
    public function exportar(): void
    {
        $desde    = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $clienteId = (int)($_GET['cliente_id'] ?? 0);
        $busqueda = trim($_GET['busqueda'] ?? '');
        $estado   = trim($_GET['estado'] ?? '');

        $cobros = $this->cobro->listar($desde, $hasta, $clienteId, $busqueda, $estado);

        $filename = 'cobros_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'ID', 'Fecha', 'Cliente', 'CUIT', 'Total',
            'Facturas', 'Estado', 'Creado por',
        ], ';');

        foreach ($cobros as $c) {
            fputcsv($out, [
                $c['id'],
                date('d/m/Y', strtotime($c['fecha'])),
                $c['cliente_razon_social'],
                $c['cliente_cuit'] ?? '',
                number_format((float)$c['total'], 2, ',', '.'),
                (int)$c['facturas_count'],
                $c['estado'],
                $c['creado_por_nombre'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'cobros.php';
    }
}