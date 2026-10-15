<?php
/**
 * controllers/FacturasController.php
 * Solo Admin/Dev.
 */

require_once ROOT_PATH . 'includes' . DS . 'ArcaClient.php';

class FacturasController
{
    private Factura $factura;
    private Pedido $pedido;
    private Emisor $emisor;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->factura = new Factura();
        $this->pedido  = new Pedido();
        $this->emisor  = new Emisor();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        // Pedidos en estado PREPARADO listos para facturar
        $pedidos = $this->pedido->listar('', 'PREPARADO', null, '', '');

        // Facturas ya emitidas (últimos 30 días por defecto)
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $facturas = $this->factura->listar($desde, $hasta);

        $emisores = $this->emisor->listar();

        $page_title     = 'Facturación';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'facturas.js';
        $pageStyle      = 'factura.css';

        require_once ROOT_PATH . 'views' . DS . 'factura.php';
    }

    public function buscarPedidos(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $search = trim($_POST['search'] ?? '');
        $pedidos = $this->pedido->listar($search, 'PREPARADO', null, '', '');

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'facturas_pedidos_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($pedidos)]);
    }

    public function buscarFacturas(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $desde      = trim($_POST['desde'] ?? '');
        $hasta      = trim($_POST['hasta'] ?? '');
        $emisorId   = (int)($_POST['emisor_id'] ?? 0);
        $busqueda   = trim($_POST['busqueda'] ?? '');
        $estado     = trim($_POST['estado'] ?? '');
        $arcaEstado = trim($_POST['arca_estado'] ?? '');

        $facturas = $this->factura->listar($desde, $hasta, $emisorId, 0, $busqueda, $estado, $arcaEstado);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'facturas_emitidas_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($facturas)]);
    }

    /* ==========================================================
       FORM — venta inmediata o desde pedido
       ========================================================== */
    public function crear(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
            return;
        }

        $pedidoId = (int)($_GET['pedido_id'] ?? 0);
        $pedido   = null;
        $detalle  = [];

        if ($pedidoId > 0) {
            $pedido = $this->pedido->obtenerPorId($pedidoId);
            if (!$pedido) {
                header('Location: ' . BASE_URL . 'facturas');
                exit;
            }
            if ($pedido['estado'] !== 'PREPARADO') {
                header('Location: ' . BASE_URL . 'facturas');
                exit;
            }
            if ($this->factura->obtenerActivaPorPedido($pedidoId)) {
                header('Location: ' . BASE_URL . 'facturas');
                exit;
            }
            $detalle = $this->pedido->obtenerDetalle($pedidoId);
        }

        $emisores = $this->emisor->listar();
        $combos   = $this->pedido->combos();

        $modo           = $pedidoId > 0 ? 'desde_pedido' : 'venta_inmediata';
        $page_title     = $pedidoId > 0 ? 'Facturar pedido #' . $pedidoId : 'Nueva factura (venta inmediata)';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'facturas.js';
        $pageStyle      = 'factura.css';

        require_once ROOT_PATH . 'views' . DS . 'factura_form.php';
    }

    private function guardar(): void
    {
        $detalleRaw = $_POST['detalle'] ?? '[]';
        $detalle = json_decode($detalleRaw, true);
        if (!is_array($detalle) || empty($detalle)) {
            jsonResponse(['ok' => false, 'msg' => 'Cargá al menos un artículo.']);
        }

        $emisorId  = (int)($_POST['emisor_id'] ?? 0);
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $pedidoId  = (int)($_POST['pedido_id'] ?? 0);
        $catOverride = (int)($_POST['categoria_override'] ?? 0) ?: null;
        $descGral  = (float)($_POST['descuento_general_porcentaje'] ?? 0);
        $notas     = trim($_POST['notas'] ?? '') ?: null;

        if ($emisorId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná el emisor que factura.']);
        }
        if ($clienteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un cliente.']);
        }

        $usuarioId = (int)$_SESSION['user_id'];

        foreach ($detalle as &$linea) {
            $linea['articulo_id']          = (int)($linea['articulo_id'] ?? 0);
            $linea['pakaging_id']          = !empty($linea['pakaging_id']) ? (int)$linea['pakaging_id'] : null;
            $linea['cantidad_pakaging']    = (float)($linea['cantidad_pakaging'] ?? 0);
            $linea['cantidad_unidades']    = (float)($linea['cantidad_unidades'] ?? 0);
            $linea['precio_unitario']      = (float)($linea['precio_unitario'] ?? 0);
            $linea['descuento_porcentaje'] = (float)($linea['descuento_porcentaje'] ?? 0);
            $linea['sin_precio']           = (int)($linea['sin_precio'] ?? 0);

            // Fiscal
            $cond = strtoupper((string)($linea['condicion_iva'] ?? 'GRAVADO'));
            if (!in_array($cond, ['GRAVADO','NO_GRAVADO','EXENTO'], true)) {
                $cond = 'GRAVADO';
            }
            $linea['condicion_iva'] = $cond;
            $linea['alicuota_iva']  = (float)($linea['alicuota_iva'] ?? 21.00);

            if ($linea['articulo_id'] <= 0 || $linea['cantidad_unidades'] <= 0) {
                jsonResponse(['ok' => false, 'msg' => 'Cada línea debe tener artículo y cantidad válidos.']);
            }
        }
        unset($linea);

        if ($pedidoId > 0) {
            // Facturar pedido existente
            $res = $this->factura->crearDesdePedido($pedidoId, $emisorId, $usuarioId);
        } else {
            // Venta inmediata
            $cabecera = [
                'cliente_id'                   => $clienteId,
                'categoria_override'           => $catOverride,
                'fecha'                        => date('Y-m-d'),
                'descuento_general_porcentaje' => $descGral,
                'perc_iva'                     => (float)($_POST['perc_iva']  ?? 0),
                'perc_iibb'                    => (float)($_POST['perc_iibb'] ?? 0),
                'notas'                        => $notas,
            ];
            $res = $this->factura->crearVentaInmediata($cabecera, $detalle, $emisorId, $usuarioId);
        }

        if (!$res['ok']) {
            jsonResponse($res);
        }

        jsonResponse([
            'ok'       => true,
            'msg'      => $res['msg'],
            'redirect' => BASE_URL . 'facturas/ver?id=' . $res['factura_id'],
        ]);
    }

    /* ==========================================================
       VER
       ========================================================== */
    public function ver(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'facturas');
            exit;
        }

        $factura = $this->factura->obtenerPorId($id);
        if (!$factura) {
            header('Location: ' . BASE_URL . 'facturas');
            exit;
        }

        $detalle    = $this->factura->obtenerDetalle($id);
        $movimientos = $this->factura->obtenerMovimientosStock($id);
        $pedido     = $factura['pedido_id'] ? $this->pedido->obtenerPorId((int)$factura['pedido_id']) : null;

        $page_title     = 'Factura #' . $id;
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'facturas.js';
        $pageStyle      = 'factura.css';

        require_once ROOT_PATH . 'views' . DS . 'factura_ver.php';
    }

    /* ==========================================================
       ENVIAR A ARCA
       ========================================================== */
    public function enviarArca(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        jsonResponse($this->factura->enviarAArca($id));
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

        jsonResponse($this->factura->anular($id, $motivo, (int)$_SESSION['user_id']));
    }

    /* ==========================================================
       PDFs — factura y remitos
       (los métodos reales vienen en el próximo mensaje)
       ========================================================== */
        /* ==========================================================
       PDFs
       ========================================================== */
    public function imprimirFactura(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'facturas');
            exit;
        }

        require_once ROOT_PATH . 'includes' . DS . 'factura_pdf.php';

        try {
            $pdf = generarPdfFactura($id);
            $this->_streamPdf($pdf, 'Factura-' . $id . '.pdf');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '<h2>No se pudo generar el PDF</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><a href="' . BASE_URL . 'facturas/ver?id=' . $id . '">← Volver a la factura</a></p>';
        }
        exit;
    }

    public function remitoConPrecio(): void
    {
        $this->_remito(true);
    }

    public function remitoSinPrecio(): void
    {
        $this->_remito(false);
    }

    private function _remito(bool $conPrecio): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'facturas');
            exit;
        }

        require_once ROOT_PATH . 'includes' . DS . 'factura_pdf.php';

        try {
            $pdf = generarPdfRemito($id, $conPrecio);
            $sufijo = $conPrecio ? 'con-precio' : 'sin-precio';
            $this->_streamPdf($pdf, 'Remito-' . $id . '-' . $sufijo . '.pdf');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '<h2>No se pudo generar el remito</h2>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><a href="' . BASE_URL . 'facturas/ver?id=' . $id . '">← Volver a la factura</a></p>';
        }
        exit;
    }

    /**
     * Envía un PDF al navegador en modo inline (para previsualización).
     * Si se pasa ?descargar=1, se fuerza descarga.
     */
    private function _streamPdf(string $bytes, string $nombre): void
    {
        $descargar = !empty($_GET['descargar']);
        $disposicion = $descargar ? 'attachment' : 'inline';

        // Limpiar cualquier output accidental antes
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposicion . '; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $bytes;
        exit;
    }

    /* ==========================================================
       EXPORTAR
       ========================================================== */
    public function exportar(): void
    {
        $desde      = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta      = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId   = (int)($_GET['emisor_id'] ?? 0);
        $busqueda   = trim($_GET['busqueda'] ?? '');
        $estado     = trim($_GET['estado'] ?? '');
        $arcaEstado = trim($_GET['arca_estado'] ?? '');

        $facturas = $this->factura->listar($desde, $hasta, $emisorId, 0, $busqueda, $estado, $arcaEstado);

        $filename = 'facturas_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'N° Interno', 'Fecha', 'Emisor', 'Cliente', 'CUIT',
            'Remito', 'N° ARCA', 'CAE', 'Estado ARCA', 'Estado',
            'Subtotal', 'Desc. gral', 'IVA', 'Imp. Internos', 'Total',
        ], ';');

        foreach ($facturas as $f) {
            $remito = ($f['remito_prefijo'] ? $f['remito_prefijo'] . ' ' : '')
                    . str_pad((string)$f['remito_punto_venta'], 4, '0', STR_PAD_LEFT) . '-'
                    . str_pad((string)$f['remito_numero'], 8, '0', STR_PAD_LEFT);

            fputcsv($out, [
                $f['id'],
                date('d/m/Y', strtotime($f['fecha'])),
                $f['emisor_nombre'],
                $f['cliente_razon_social'],
                $f['cliente_cuit'] ?? '',
                $remito,
                $f['arca_numero_comprobante'] ?? '',
                $f['cae'] ?? '',
                $f['arca_estado'],
                $f['estado'],
                number_format((float)$f['subtotal'], 2, ',', '.'),
                number_format((float)$f['descuento_general_porcentaje'], 2, ',', '.') . '%',
                number_format((float)$f['iva'], 2, ',', '.'),
                number_format((float)$f['impuestos_internos'], 2, ',', '.'),
                number_format((float)$f['total'], 2, ',', '.'),
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'factura.php';
    }
}