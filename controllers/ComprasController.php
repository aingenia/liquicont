<?php
/**
 * controllers/ComprasController.php
 * Solo Admin/Dev.
 */

require_once ROOT_PATH . 'includes' . DS . 'ArcaClient.php';

class ComprasController
{
    private Compra $compra;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->compra = new Compra();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $proveedorId = (int)($_GET['proveedor_id'] ?? 0);
        $busqueda = trim($_GET['busqueda'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        $compras = $this->compra->listar($desde, $hasta, $proveedorId, $busqueda, $estado);
        $combos  = $this->compra->combos();

        $page_title     = 'Comprobantes de compra';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'compras.js';
        $pageStyle      = 'compra.css';

        require_once ROOT_PATH . 'views' . DS . 'compra.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }

        $desde = $_POST['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_POST['hasta'] ?? date('Y-m-d');
        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        $busqueda = trim($_POST['busqueda'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        $compras = $this->compra->listar($desde, $hasta, $proveedorId, $busqueda, $estado);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'compras_row.php';
        $html = ob_get_clean();

        jsonResponse([
            'status' => 'success',
            'html'   => $html,
            'desde'  => $desde,
            'hasta'  => $hasta,
            'total'  => count($compras),
        ]);
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

        $modo            = 'crear';
        $compra          = null;
        $detalle         = [];
        $combos          = $this->compra->combos();
        $page_title      = 'Nuevo comprobante';
        $headerGreeting  = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript      = 'compras.js';
        $pageStyle       = 'compra.css';

        require_once ROOT_PATH . 'views' . DS . 'compra_form.php';
    }

    /* ==========================================================
       EDITAR
       ========================================================== */
    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'comprobantes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardarEdicion($id);
            return;
        }

        $compra = $this->compra->obtenerPorId($id);
        if (!$compra) {
            flashSet('error', 'Compra no encontrada.');
            header('Location: ' . BASE_URL . 'comprobantes');
            exit;
        }
        if ($compra['estado'] === 'ANULADA') {
            flashSet('error', 'No se puede editar una compra anulada.');
            header('Location: ' . BASE_URL . 'comprobantes/ver?id=' . $id);
            exit;
        }

        $detalle = $this->compra->obtenerDetalle($id);
        $combos  = $this->compra->combos();

        $modo           = 'editar';
        $page_title     = 'Editar comprobante #' . $id;
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'compras.js';
        $pageStyle      = 'compra.css';

        require_once ROOT_PATH . 'views' . DS . 'compra_form.php';
    }

    /* ==========================================================
       VER (solo lectura)
       ========================================================== */
    public function ver(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'comprobantes');
            exit;
        }

        $compra = $this->compra->obtenerPorId($id);
        if (!$compra) {
            header('Location: ' . BASE_URL . 'comprobantes');
            exit;
        }

        $detalle = $this->compra->obtenerDetalle($id);
        $combos  = $this->compra->combos();

        $modo            = 'ver';
        $page_title      = 'Comprobante #' . $id;
        $headerGreeting  = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript      = 'compras.js';
        $pageStyle       = 'compra.css';

        require_once ROOT_PATH . 'views' . DS . 'compra_form.php';
    }

    /* ==========================================================
       GUARDAR (alta)
       ========================================================== */
    private function guardar(): void
    {
        $detalleRaw = $_POST['detalle'] ?? '[]';
        $detalle = json_decode($detalleRaw, true);
        if (!is_array($detalle) || empty($detalle)) {
            jsonResponse(['ok' => false, 'msg' => 'Cargá al menos una línea en el detalle.']);
        }

        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un proveedor.']);
        }

        $tipoComprobanteId = (int)($_POST['tipo_comprobante_id'] ?? 0);
        if ($tipoComprobanteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un tipo de comprobante.']);
        }

        $fecha = trim($_POST['fecha_comprobante'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'La fecha del comprobante es inválida.']);
        }

        $cuentaId = (int)($_POST['cuenta_contable_id'] ?? 0);

        // Calcular subtotal + validar líneas
                // Calcular subtotal + validar líneas + fiscales
        $subtotal    = 0.0;
        $netoGravado = 0.0;
        $netoNoGrav  = 0.0;
        $netoExento  = 0.0;
        $ivaTotal    = 0.0;

        foreach ($detalle as &$linea) {
            $cantidad = (float)($linea['cantidad'] ?? 0);
            $costo    = (float)($linea['costo_unitario'] ?? 0);
            if ($cantidad <= 0 || $costo <= 0) {
                jsonResponse(['ok' => false, 'msg' => 'Cantidad y costo deben ser mayores a 0.']);
            }

            $esServicio = empty($linea['insumo_id']);
            if ($esServicio && trim((string)($linea['concepto'] ?? '')) === '') {
                jsonResponse(['ok' => false, 'msg' => 'Las líneas de servicio necesitan una descripción.']);
            }

            // Fiscal
            $cond = strtoupper((string)($linea['condicion_iva'] ?? 'GRAVADO'));
            if (!in_array($cond, ['GRAVADO','NO_GRAVADO','EXENTO'], true)) $cond = 'GRAVADO';
            $al   = (float)($linea['alicuota_iva'] ?? 21.00);

            $lineSubtotal = round($cantidad * $costo, 4);

            if ($cond === 'GRAVADO') {
                $al = max(0, min(100, $al));
                $impIva = round($lineSubtotal * $al / 100, 4);
                $netoGravado += $lineSubtotal;
            } elseif ($cond === 'NO_GRAVADO') {
                $al = 0;
                $impIva = 0;
                $netoNoGrav += $lineSubtotal;
            } else { // EXENTO
                $al = 0;
                $impIva = 0;
                $netoExento += $lineSubtotal;
            }

            $ivaTotal += $impIva;
            $subtotal += $lineSubtotal;

            // Guardar en la línea para que Compra::aplicarDetalle lo reciba
            $linea['cantidad']       = $cantidad;
            $linea['costo_unitario'] = $costo;
            $linea['condicion_iva']  = $cond;
            $linea['alicuota_iva']   = $al;
            $linea['importe_iva']    = $impIva;
        }
        unset($linea);

        $netoGravado = round($netoGravado, 2);
        $netoNoGrav  = round($netoNoGrav, 2);
        $netoExento  = round($netoExento, 2);
        $ivaTotal    = round($ivaTotal, 2);

        // IVA cabecera: usar el calculado salvo que el usuario lo haya forzado
        $ivaForzado = isset($_POST['iva_manual']) && $_POST['iva_manual'] === '1';
        $iva = $ivaForzado ? (float)($_POST['iva'] ?? 0) : $ivaTotal;

        $percIva  = (float)($_POST['perc_iva'] ?? 0);
        $percIibb = (float)($_POST['perc_iibb'] ?? 0);
        $impInt   = (float)($_POST['impuestos_internos'] ?? 0);

        $total = round($subtotal + $iva + $percIva + $percIibb + $impInt, 2);

                $cabecera = [
            'proveedor_id'            => $proveedorId,
            'tipo_comprobante_id'     => $tipoComprobanteId,
            'punto_venta'             => trim($_POST['punto_venta'] ?? '') ?: null,
            'numero'                  => trim($_POST['numero'] ?? '') ?: null,
            'fecha_comprobante'       => $fecha,
            'cae'                     => trim($_POST['cae'] ?? '') ?: null,
            'cae_vencimiento'         => trim($_POST['cae_vencimiento'] ?? '') ?: null,
            'cuenta_contable_id'      => $cuentaId ?: null,
            'primer_vencimiento'      => trim($_POST['primer_vencimiento'] ?? '') ?: null,
            'segundo_vencimiento'     => trim($_POST['segundo_vencimiento'] ?? '') ?: null,
            'archivo_path'            => null,
            'archivo_nombre_original' => null,
            'subtotal'                => round($subtotal, 2),
            'neto_gravado'            => $netoGravado,
            'neto_no_gravado'         => $netoNoGrav,
            'neto_exento'             => $netoExento,
            'iva'                     => round($iva, 2),
            'perc_iva'                => round($percIva, 2),
            'perc_iibb'               => round($percIibb, 2),
            'impuestos_internos'      => round($impInt, 2),
            'total'                   => $total,
              'observaciones'           => trim($_POST['observaciones'] ?? '') ?: null,
        ];

        $compraId = $this->compra->crear($cabecera, $detalle);
        if ($compraId === false) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo guardar la compra.']);
        }

        jsonResponse([
            'ok'       => true,
            'msg'      => 'Compra registrada con éxito.',
            'redirect' => BASE_URL . 'comprobantes/ver?id=' . $compraId,
        ]);
    }

    /* ==========================================================
       GUARDAR EDICIÓN
       ========================================================== */
    private function guardarEdicion(int $id): void
    {
        $detalleRaw = $_POST['detalle'] ?? '[]';
        $detalle = json_decode($detalleRaw, true);
        if (!is_array($detalle) || empty($detalle)) {
            jsonResponse(['ok' => false, 'msg' => 'Cargá al menos una línea en el detalle.']);
        }

        $proveedorId = (int)($_POST['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un proveedor.']);
        }

        $tipoComprobanteId = (int)($_POST['tipo_comprobante_id'] ?? 0);
        if ($tipoComprobanteId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Seleccioná un tipo de comprobante.']);
        }

        $fecha = trim($_POST['fecha_comprobante'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            jsonResponse(['ok' => false, 'msg' => 'La fecha del comprobante es inválida.']);
        }

        // Validar y sumar detalle
                // Calcular subtotal + validar líneas + fiscales
        $subtotal    = 0.0;
        $netoGravado = 0.0;
        $netoNoGrav  = 0.0;
        $netoExento  = 0.0;
        $ivaTotal    = 0.0;

        foreach ($detalle as &$linea) {
            $cantidad = (float)($linea['cantidad'] ?? 0);
            $costo    = (float)($linea['costo_unitario'] ?? 0);
            if ($cantidad <= 0 || $costo <= 0) {
                jsonResponse(['ok' => false, 'msg' => 'Cantidad y costo deben ser mayores a 0.']);
            }

            $esServicio = empty($linea['insumo_id']);
            if ($esServicio && trim((string)($linea['concepto'] ?? '')) === '') {
                jsonResponse(['ok' => false, 'msg' => 'Las líneas de servicio necesitan una descripción.']);
            }

            // Fiscal
            $cond = strtoupper((string)($linea['condicion_iva'] ?? 'GRAVADO'));
            if (!in_array($cond, ['GRAVADO','NO_GRAVADO','EXENTO'], true)) $cond = 'GRAVADO';
            $al   = (float)($linea['alicuota_iva'] ?? 21.00);

            $lineSubtotal = round($cantidad * $costo, 4);

            if ($cond === 'GRAVADO') {
                $al = max(0, min(100, $al));
                $impIva = round($lineSubtotal * $al / 100, 4);
                $netoGravado += $lineSubtotal;
            } elseif ($cond === 'NO_GRAVADO') {
                $al = 0;
                $impIva = 0;
                $netoNoGrav += $lineSubtotal;
            } else { // EXENTO
                $al = 0;
                $impIva = 0;
                $netoExento += $lineSubtotal;
            }

            $ivaTotal += $impIva;
            $subtotal += $lineSubtotal;

            // Guardar en la línea para que Compra::aplicarDetalle lo reciba
            $linea['cantidad']       = $cantidad;
            $linea['costo_unitario'] = $costo;
            $linea['condicion_iva']  = $cond;
            $linea['alicuota_iva']   = $al;
            $linea['importe_iva']    = $impIva;
        }
        unset($linea);

        $netoGravado = round($netoGravado, 2);
        $netoNoGrav  = round($netoNoGrav, 2);
        $netoExento  = round($netoExento, 2);
        $ivaTotal    = round($ivaTotal, 2);

        // IVA cabecera: usar el calculado salvo que el usuario lo haya forzado
        $ivaForzado = isset($_POST['iva_manual']) && $_POST['iva_manual'] === '1';
        $iva = $ivaForzado ? (float)($_POST['iva'] ?? 0) : $ivaTotal;

        $percIva  = (float)($_POST['perc_iva'] ?? 0);
        $percIibb = (float)($_POST['perc_iibb'] ?? 0);
        $impInt   = (float)($_POST['impuestos_internos'] ?? 0);

        $total = round($subtotal + $iva + $percIva + $percIibb + $impInt, 2);

        $iva    = (float)($_POST['iva'] ?? 0);
        $impInt = (float)($_POST['impuestos_internos'] ?? 0);
        $total  = round($subtotal + $iva + $impInt, 2);

        $cabecera = [
            'proveedor_id'            => $proveedorId,
            'tipo_comprobante_id'     => $tipoComprobanteId,
            'punto_venta'             => trim($_POST['punto_venta'] ?? '') ?: null,
            'numero'                  => trim($_POST['numero'] ?? '') ?: null,
            'fecha_comprobante'       => $fecha,
            'cae'                     => trim($_POST['cae'] ?? '') ?: null,
            'cae_vencimiento'         => trim($_POST['cae_vencimiento'] ?? '') ?: null,
            'cuenta_contable_id'      => $cuentaId ?: null,
            'primer_vencimiento'      => trim($_POST['primer_vencimiento'] ?? '') ?: null,
            'segundo_vencimiento'     => trim($_POST['segundo_vencimiento'] ?? '') ?: null,
            'archivo_path'            => null,
            'archivo_nombre_original' => null,
            'subtotal'                => round($subtotal, 2),
            'neto_gravado'            => $netoGravado,
            'neto_no_gravado'         => $netoNoGrav,
            'neto_exento'             => $netoExento,
            'iva'                     => round($iva, 2),
            'perc_iva'                => round($percIva, 2),
            'perc_iibb'               => round($percIibb, 2),
            'impuestos_internos'      => round($impInt, 2),
            'total'                   => $total,
            'observaciones'           => trim($_POST['observaciones'] ?? '') ?: null,
        ];

        $r = $this->compra->actualizar($id, $cabecera, $detalle);
        if ($r['ok']) {
            $r['msg']      = 'Comprobante actualizado.';
            $r['redirect'] = BASE_URL . 'comprobantes/ver?id=' . $id;
        }
        jsonResponse($r);
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

        jsonResponse($this->compra->anular($id));
    }

    /* ==========================================================
       ARCA — constatar comprobante
       ========================================================== */
    public function arcaConstatar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $cuitEmisor = trim($_POST['cuit_emisor'] ?? '');
        $cae        = trim($_POST['cae'] ?? '');
        $pv         = (int)($_POST['punto_venta'] ?? 0);
        $numero     = (int)($_POST['numero'] ?? 0);
        $fecha      = trim($_POST['fecha'] ?? '');
        $tipoCbte   = (int)($_POST['tipo_comprobante_id'] ?? 0);
        $importe    = (float)($_POST['importe_total'] ?? 0);

        if ($cuitEmisor === '' || $cae === '') {
            jsonResponse(['ok' => false, 'msg' => 'CUIT del emisor y CAE son obligatorios.']);
        }

        $codigoAfip = 1;
        if ($tipoCbte > 0) {
            $db = Conexion::getConnection();
            $s = $db->prepare("SELECT codigo_afip FROM tipos_comprobante_compra WHERE id = :id");
            $s->execute([':id' => $tipoCbte]);
            $codigoAfip = (int)$s->fetchColumn() ?: 1;
        }

        try {
            $arca = new ArcaClient();
            $res = $arca->constatarComprobante([
                'cuit_emisor'              => $cuitEmisor,
                'punto_venta'              => $pv,
                'tipo_comprobante_codigo'  => $codigoAfip,
                'numero'                   => $numero,
                'fecha'                    => $fecha,
                'importe_total'            => $importe,
                'cae'                      => $cae,
            ]);
        } catch (\Throwable $e) {
            jsonResponse(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }

        jsonResponse($res);
    }

    /* ==========================================================
       ADJUNTO
       ========================================================== */
    public function subirArchivo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $compraId = (int)($_POST['compra_id'] ?? 0);
        if ($compraId <= 0 || !$this->compra->obtenerPorId($compraId)) {
            jsonResponse(['ok' => false, 'msg' => 'Compra inválida.']);
        }

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'msg' => 'No se recibió el archivo.']);
        }

        $file = $_FILES['archivo'];
        if ($file['size'] > 8 * 1024 * 1024) {
            jsonResponse(['ok' => false, 'msg' => 'El archivo no puede superar 8 MB.']);
        }

        $mime = mime_content_type($file['tmp_name']);
        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($permitidos[$mime])) {
            jsonResponse(['ok' => false, 'msg' => 'Formato no permitido (JPG, PNG, WebP o PDF).']);
        }

        $ext    = $permitidos[$mime];
        $dirRel = 'uploads' . DS . 'compras';
        $dirAbs = ROOT_PATH . $dirRel;
        if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);

        $nombre  = 'compra_' . $compraId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destino = $dirAbs . DS . $nombre;
        $relPath = str_replace(DS, '/', $dirRel . DS . $nombre);

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo guardar el archivo.']);
        }

        $compra = $this->compra->obtenerPorId($compraId);
        if (!empty($compra['archivo_path'])) {
            $viejo = ROOT_PATH . str_replace('/', DS, $compra['archivo_path']);
            if (is_file($viejo) && strpos($viejo, $dirAbs) === 0) @unlink($viejo);
        }

        $nombreOriginal = $_POST['nombre_original'] ?? $file['name'];
        $this->compra->actualizarAdjunto($compraId, $relPath, $nombreOriginal);

        jsonResponse(['ok' => true, 'msg' => 'Archivo adjuntado.', 'path' => BASE_URL . $relPath]);
    }

    public function eliminarArchivo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $compraId = (int)($_POST['compra_id'] ?? 0);
        $compra = $this->compra->obtenerPorId($compraId);
        if (!$compra) {
            jsonResponse(['ok' => false, 'msg' => 'Compra no encontrada.']);
        }

        if (!empty($compra['archivo_path'])) {
            $full = ROOT_PATH . str_replace('/', DS, $compra['archivo_path']);
            if (is_file($full)) @unlink($full);
        }

        $this->compra->actualizarAdjunto($compraId, null, null);
        jsonResponse(['ok' => true, 'msg' => 'Archivo eliminado.']);
    }

    /* ==========================================================
       EXPORTAR / IMPRIMIR / PDF
       ========================================================== */
    public function exportar(): void
    {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $proveedorId = (int)($_GET['proveedor_id'] ?? 0);
        $busqueda = trim($_GET['busqueda'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        $compras = $this->compra->listar($desde, $hasta, $proveedorId, $busqueda, $estado);

        $filename = 'comprobantes_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['ID', 'Fecha', 'Proveedor', 'CUIT',
                       'Comprobante', 'Punto venta', 'Número', 'CAE',
                       'Subtotal', 'IVA', 'Imp. Internos', 'Total',
                       'Cuenta contable', 'Estado'], ';');

        foreach ($compras as $c) {
            fputcsv($out, [
                $c['id'],
                date('d/m/Y', strtotime($c['fecha_comprobante'])),
                $c['proveedor_razon_social'],
                $c['proveedor_cuit'] ?? '',
                $c['tipo_comprobante_desc'] ?? '',
                $c['punto_venta'] ?? '',
                $c['numero'] ?? '',
                $c['cae'] ?? '',
                number_format((float)$c['subtotal'], 2, ',', '.'),
                number_format((float)$c['iva'], 2, ',', '.'),
                number_format((float)$c['impuestos_internos'], 2, ',', '.'),
                number_format((float)$c['total'], 2, ',', '.'),
                $c['cuenta_contable_nombre'] ?? '',
                $c['estado'] === 'ACTIVO' ? 'Registrada' : 'Anulada',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function imprimir(): void
    {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $compras = $this->compra->listar($desde, $hasta, (int)($_GET['proveedor_id'] ?? 0), trim($_GET['busqueda'] ?? ''), trim($_GET['estado'] ?? ''));

        $filtros = [
            'Desde: ' . date('d/m/Y', strtotime($desde)),
            'Hasta: ' . date('d/m/Y', strtotime($hasta)),
        ];
        require_once ROOT_PATH . 'views' . DS . 'compra_print.php';
    }

    public function pdf(): void
    {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $compras = $this->compra->listar($desde, $hasta, (int)($_GET['proveedor_id'] ?? 0), trim($_GET['busqueda'] ?? ''), trim($_GET['estado'] ?? ''));

        $filtros = [
            'Desde: ' . date('d/m/Y', strtotime($desde)),
            'Hasta: ' . date('d/m/Y', strtotime($hasta)),
        ];

        ob_start();
        require ROOT_PATH . 'views' . DS . 'compra_pdf.php';
        $html = ob_get_clean();

        generarPdf($html, 'comprobantes', 'landscape');
    }

    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'compra.php';
    }
}