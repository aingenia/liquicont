<?php
/**
 * controllers/PedidosController.php
 * Admin/Dev: total.
 * Vendedor: sus clientes (crear/editar/eliminar en RECIBIDO/PROCESANDO).
 * Productor: ver sin precios, editar/eliminar en RECIBIDO/PROCESANDO.
 */

class PedidosController
{
    private Pedido $pedido;

    public function __construct()
    {
        if (!puedeVerPedidos()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->pedido = new Pedido();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $desde  = trim($_GET['desde'] ?? '');
        $hasta  = trim($_GET['hasta'] ?? '');

        // Vendedor ve solo sus clientes
        $vendedorFiltro = esVendedor() ? (int)$_SESSION['user_id'] : null;

        $pedidos = $this->pedido->listar($search, $estado, $vendedorFiltro, $desde, $hasta);
        $combos  = $this->pedido->combos($vendedorFiltro);

        $page_title     = 'Pedidos';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'pedidos.js';
        $pageStyle      = 'pedido.css';
        $sinPrecios     = esProductor(); // Productor ve sin precios

        require_once ROOT_PATH . 'views' . DS . 'pedido.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $search = trim($_POST['search'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $desde  = trim($_POST['desde'] ?? '');
        $hasta  = trim($_POST['hasta'] ?? '');
        $vendedorFiltro = esVendedor() ? (int)$_SESSION['user_id'] : null;

        $pedidos = $this->pedido->listar($search, $estado, $vendedorFiltro, $desde, $hasta);
        $sinPrecios = esProductor();

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'pedidos_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html, 'total' => count($pedidos)]);
    }

    /* ==========================================================
       CREAR
       ========================================================== */
    public function crear(): void
    {
        if (esProductor()) {
            header('Location: ' . BASE_URL . 'pedidos');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
            return;
        }

        $modo            = 'crear';
        $pedido          = null;
        $detalle         = [];
        $vendedorFiltro  = esVendedor() ? (int)$_SESSION['user_id'] : null;
        $combos          = $this->pedido->combos($vendedorFiltro);
        $page_title      = 'Nuevo pedido';
        $headerGreeting  = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript      = 'pedidos.js';
        $pageStyle       = 'pedido.css';
        $puedeEditar     = true;

        require_once ROOT_PATH . 'views' . DS . 'pedido_form.php';
    }

    /* ==========================================================
       EDITAR
       ========================================================== */
    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { header('Location: ' . BASE_URL . 'pedidos'); exit; }

        $pedido = $this->pedido->obtenerPorId($id);
        if (!$pedido) { header('Location: ' . BASE_URL . 'pedidos'); exit; }

        // Vendedor solo sus clientes
        if (esVendedor() && (int)$pedido['cliente_id'] !== 0) {
            // Verificar por el cliente
            $combosCli = $this->pedido->combos((int)$_SESSION['user_id']);
            $ids = array_column($combosCli['clientes'], 'id');
            if (!in_array((int)$pedido['cliente_id'], $ids, true)) {
                header('Location: ' . BASE_URL . 'pedidos');
                exit;
            }
        }

        // Reglas de edición por estado
        $puedeEditar = false;
        $estado = $pedido['estado'];
        if (esAdmin()) {
            $puedeEditar = !in_array($estado, ['FACTURADO','ENTREGADO','ANULADO'], true);
        } elseif (esVendedor()) {
            $puedeEditar = in_array($estado, ['RECIBIDO','PROCESANDO'], true);
        } elseif (esProductor()) {
            $puedeEditar = in_array($estado, ['RECIBIDO','PROCESANDO'], true);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$puedeEditar) {
                jsonResponse(['ok' => false, 'msg' => 'No puede editar el pedido en este estado.'], 403);
            }
            $this->guardar($id);
            return;
        }

        // Marcar como visto por admin/dev/productor (no vendedor)
        if (!esVendedor() && (int)$pedido['modificado_por_vendedor_visto'] === 0 && !empty($pedido['modificado_por_vendedor_el'])) {
            $this->pedido->marcarModificadoVisto($id);
        }

        $detalle         = $this->pedido->obtenerDetalle($id);
        $vendedorFiltro  = esVendedor() ? (int)$_SESSION['user_id'] : null;
        $combos          = $this->pedido->combos($vendedorFiltro);
        $modo            = 'editar';
        $page_title      = 'Editar pedido #' . $id;
        $headerGreeting  = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript      = 'pedidos.js';
        $pageStyle       = 'pedido.css';

        require_once ROOT_PATH . 'views' . DS . 'pedido_form.php';
    }

    /* ==========================================================
       VER (solo lectura)
       ========================================================== */
    public function ver(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { header('Location: ' . BASE_URL . 'pedidos'); exit; }

        $pedido = $this->pedido->obtenerPorId($id);
        if (!$pedido) { header('Location: ' . BASE_URL . 'pedidos'); exit; }

        if (esVendedor()) {
            $combosCli = $this->pedido->combos((int)$_SESSION['user_id']);
            $ids = array_column($combosCli['clientes'], 'id');
            if (!in_array((int)$pedido['cliente_id'], $ids, true)) {
                header('Location: ' . BASE_URL . 'pedidos');
                exit;
            }
        }

        // Marcar visto
        if (!esVendedor() && (int)$pedido['modificado_por_vendedor_visto'] === 0 && !empty($pedido['modificado_por_vendedor_el'])) {
            $this->pedido->marcarModificadoVisto($id);
        }

        $detalle         = $this->pedido->obtenerDetalle($id);
        $vendedorFiltro  = esVendedor() ? (int)$_SESSION['user_id'] : null;
        $combos          = $this->pedido->combos($vendedorFiltro);
        $modo            = 'ver';
        $puedeEditar     = false;
        $page_title      = 'Pedido #' . $id;
        $headerGreeting  = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript      = 'pedidos.js';
        $pageStyle       = 'pedido.css';

        require_once ROOT_PATH . 'views' . DS . 'pedido_form.php';
    }

    private function guardar(?int $id = null): void
    {
        $detalleRaw = $_POST['detalle'] ?? '[]';
        $detalle = json_decode($detalleRaw, true);
        if (!is_array($detalle) || empty($detalle)) {
            jsonResponse(['ok' => false, 'msg' => 'Cargá al menos un artículo en el pedido.']);
        }

        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un cliente.']);
        }

        $fecha = trim($_POST['fecha_pedido'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'Fecha inválida.']);
        }

        // Recalcular en el servidor (no confiar en el cliente)
        $subtotal = 0.0;
        foreach ($detalle as &$linea) {
            $unidades = (float)($linea['cantidad_unidades'] ?? 0);
            $precio   = (float)($linea['precio_unitario'] ?? 0);
            $desc     = (float)($linea['descuento_porcentaje'] ?? 0);
            if ($unidades <= 0 || $precio < 0) {
                jsonResponse(['ok' => false, 'msg' => 'Cantidad y precio deben ser válidos.']);
            }
            $linea['cantidad_unidades'] = $unidades;
            $linea['precio_unitario']   = $precio;
            $linea['descuento_porcentaje'] = $desc;
            $subtotal += $unidades * $precio * (1 - $desc / 100);
        }
        unset($linea);

        $descGral = (float)($_POST['descuento_general_porcentaje'] ?? 0);
        $subtotal = round($subtotal, 2);
        $total    = round($subtotal * (1 - $descGral / 100), 2);

        $cabecera = [
            'cliente_id'                    => $clienteId,
            'fecha_pedido'                  => $fecha,
            'subtotal'                      => $subtotal,
            'descuento_general_porcentaje'  => $descGral,
            'total'                         => $total,
            'notas'                         => trim($_POST['notas'] ?? '') ?: null,
            'notas_internas'                => trim($_POST['notas_internas'] ?? '') ?: null,
        ];

        $esVend = esVendedor();

        if ($id === null) {
            $pedidoId = $this->pedido->crear($cabecera, $detalle, (int)$_SESSION['user_id']);
            if ($pedidoId === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear el pedido.']);
            }
            jsonResponse([
                'ok'       => true,
                'msg'      => 'Pedido creado con éxito.',
                'redirect' => BASE_URL . 'pedidos/ver?id=' . $pedidoId,
            ]);
        }

        if (!$this->pedido->actualizar($id, $cabecera, $detalle, (int)$_SESSION['user_id'], $esVend)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar el pedido.']);
        }

        jsonResponse([
            'ok'       => true,
            'msg'      => 'Pedido actualizado con éxito.',
            'redirect' => BASE_URL . 'pedidos/ver?id=' . $id,
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

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        // Vendedor solo puede anular en RECIBIDO/PROCESANDO
        $pedido = $this->pedido->obtenerPorId($id);
        if (!$pedido) {
            jsonResponse(['ok' => false, 'msg' => 'Pedido no encontrado.']);
        }

        if (esVendedor() && !in_array($pedido['estado'], ['RECIBIDO','PROCESANDO'], true)) {
            jsonResponse(['ok' => false, 'msg' => 'No puede anular en este estado.'], 403);
        }
        if (esProductor() && !in_array($pedido['estado'], ['RECIBIDO','PROCESANDO'], true)) {
            jsonResponse(['ok' => false, 'msg' => 'No puede anular en este estado.'], 403);
        }

        jsonResponse($this->pedido->anular($id));
    }

    /* ==========================================================
       CAMBIAR ESTADO
       ========================================================== */
    public function cambiarEstado(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id     = (int)($_POST['id'] ?? 0);
        $nuevo  = trim($_POST['estado'] ?? '');

        if ($id <= 0 || $nuevo === '') {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }

        $pedido = $this->pedido->obtenerPorId($id);
        if (!$pedido) {
            jsonResponse(['ok' => false, 'msg' => 'Pedido no encontrado.']);
        }

        $actual = $pedido['estado'];

        // Permisos según rol
        $puede = false;
        if (esAdmin()) {
            $puede = !in_array($actual, ['ENTREGADO','ANULADO'], true);
        } elseif (esVendedor()) {
            $puede = in_array($actual, ['RECIBIDO'], true) && $nuevo === 'PROCESANDO';
        } elseif (esProductor()) {
            $puede = (in_array($actual, ['RECIBIDO'], true) && $nuevo === 'PROCESANDO')
                  || ($actual === 'PROCESANDO' && $nuevo === 'PREPARADO');
        }

        if (!$puede) {
            jsonResponse(['ok' => false, 'msg' => 'No puede cambiar a ese estado.'], 403);
        }

        jsonResponse($this->pedido->cambiarEstado($id, $nuevo));
    }

    /* ==========================================================
       AJAX: Precio del artículo según cliente
       ========================================================== */
    public function precioArticulo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $articuloId       = (int)($_POST['articulo_id'] ?? 0);
        $clienteId        = (int)($_POST['cliente_id'] ?? 0);
        $categoriaOverride = (int)($_POST['categoria_id'] ?? 0) ?: null;

        if ($articuloId <= 0 || $clienteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }

        $info = $this->pedido->precioArticulo($articuloId, $clienteId, $categoriaOverride);
        if (empty($info)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo obtener el precio.']);
        }

        jsonResponse([
            'ok'                  => true,
            'articulo_nombre'     => $info['nombre'],
            'articulo_codigo'     => $info['codigo'],
            'categoria_desc'      => $info['categoria_desc'],
            'pakaging_id'         => $info['pakaging_id'],
            'pakaging_desc'       => $info['pakaging_desc'],
            'pakaging_factor'     => (float)($info['pakaging_factor'] ?? 1),
            'precio_unitario'     => (float)($info['precio_categoria'] ?? 0),
            'tiene_precio'        => $info['precio_categoria'] !== null,
        ]);
    }

    /* ==========================================================
       EXPORTAR / IMPRIMIR / PDF / AYUDA
       ========================================================== */
    public function exportar(): void
    {
        $search = trim($_GET['search'] ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $desde  = trim($_GET['desde'] ?? '');
        $hasta  = trim($_GET['hasta'] ?? '');
        $vendedorFiltro = esVendedor() ? (int)$_SESSION['user_id'] : null;
        $pedidos = $this->pedido->listar($search, $estado, $vendedorFiltro, $desde, $hasta);

        $sinPrecios = esProductor();
        $filename = 'pedidos_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        $cols = ['ID', 'Fecha', 'Cliente', 'Categoría', 'Items', 'Estado'];
        if (!$sinPrecios) {
            $cols[] = 'Subtotal';
            $cols[] = 'Desc. gral %';
            $cols[] = 'Total';
        }
        $cols[] = 'Creado por';
        fputcsv($out, $cols, ';');

        foreach ($pedidos as $p) {
            $row = [
                $p['id'],
                date('d/m/Y', strtotime($p['fecha_pedido'])),
                $p['cliente_razon_social'],
                $p['cliente_categoria'] ?? '',
                (int)$p['items_count'],
                $p['estado'],
            ];
            if (!$sinPrecios) {
                $row[] = number_format((float)$p['subtotal'], 2, ',', '.');
                $row[] = number_format((float)$p['descuento_general_porcentaje'], 2, ',', '.') . '%';
                $row[] = number_format((float)$p['total'], 2, ',', '.');
            }
            $row[] = $p['creado_por_nombre'] ?? '';
            fputcsv($out, $row, ';');
        }

        fclose($out);
        exit;
    }

    public function imprimir(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { header('Location: ' . BASE_URL . 'pedidos'); exit; }

        $pedido = $this->pedido->obtenerPorId($id);
        if (!$pedido) { header('Location: ' . BASE_URL . 'pedidos'); exit; }

        // Vendedor solo sus clientes
        if (esVendedor()) {
            $combosCli = $this->pedido->combos((int)$_SESSION['user_id']);
            $ids = array_column($combosCli['clientes'], 'id');
            if (!in_array((int)$pedido['cliente_id'], $ids, true)) {
                header('Location: ' . BASE_URL . 'pedidos');
                exit;
            }
        }

        $detalle = $this->pedido->obtenerDetalle($id);

        // Productor siempre sin precio. Admin/Dev puede elegir por query string.
        $sinPrecios = esProductor() || (($_GET['sin_precio'] ?? '') === '1');

        require_once ROOT_PATH . 'views' . DS . 'pedido_print.php';
    }

    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'pedido.php';
    }
}