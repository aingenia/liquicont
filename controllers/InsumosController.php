<?php
/**
 * controllers/InsumosController.php
 * Admin/Dev: CRUD completo.
 * Productor: solo lectura (listado + form en modo lectura + exportaciones).
 */

class InsumosController
{
    private Insumo $insumo;
    private ConfiguracionApp $config;

    public function __construct()
    {
        if (!puedeVerInsumos()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para esta acción.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->insumo = new Insumo();
        $this->config = new ConfiguracionApp();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $insumos = $this->insumo->listar($search, $status);

        $page_title     = 'Insumos';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'insumos.js';
        $pageStyle      = 'insumo.css'; 

        require_once ROOT_PATH . 'views' . DS . 'insumo.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }
        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $insumos = $this->insumo->listar($search, $status);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumos_row.php';
        $html = ob_get_clean();

        jsonResponse(['status' => 'success', 'html' => $html]);
    }

    /* ==========================================================
       CREAR / EDITAR
       ========================================================== */
    public function crear(): void
    {
        if (!puedeEditarInsumos()) {
            header('Location: ' . BASE_URL . 'insumos');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
            return;
        }

        $modo                     = 'crear';
        $insumo                   = null;
        $proveedoresDelInsumo     = [];
        $combos                   = $this->insumo->combos();
        $alicuotaDefault          = $this->config->obtenerFloat('impuesto_bebidas_alcoholicas', 26);
        $page_title               = 'Nuevo Insumo';
        $headerGreeting           = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript               = 'insumos.js';
        $pageStyle                = 'insumo.css'; 

        require_once ROOT_PATH . 'views' . DS . 'insumo_form.php';
    }

    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'insumos');
            exit;
        }

        $insumo = $this->insumo->obtenerPorId($id);
        if (!$insumo) {
            header('Location: ' . BASE_URL . 'insumos');
            exit;
        }

        // Si es POST y no puede editar → bloquear
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!puedeEditarInsumos()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para editar.'], 403);
            }
            $this->guardar($id);
            return;
        }

        $modo                 = 'editar';
        $proveedoresDelInsumo = $this->insumo->listarProveedores($id);
        $combos               = $this->insumo->combos();
        $alicuotaDefault      = $this->config->obtenerFloat('impuesto_bebidas_alcoholicas', 26);
        $page_title           = 'Editar Insumo';
        $headerGreeting       = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript           = 'insumos.js';
        $pageStyle            = 'insumo.css'; 

        require_once ROOT_PATH . 'views' . DS . 'insumo_form.php';
    }

    private function guardar(?int $id = null): void
    {
        $d = [
            'codigo'                     => trim($_POST['codigo'] ?? ''),
            'nombre'                     => trim($_POST['nombre'] ?? ''),
            'descripcion'                => trim($_POST['descripcion'] ?? ''),
            'unidad_medida_id'           => (int)($_POST['unidad_medida_id'] ?? 0),
            'medida'                     => (float)($_POST['medida'] ?? 1),
            'ppedido'                    => (float)($_POST['ppedido'] ?? 0),
            'metodo_costo'               => $_POST['metodo_costo'] ?? 'PROMEDIO',
            'es_alcoholico'              => isset($_POST['es_alcoholico']) ? 1 : 0,
            'aplica_impuestos_internos'  => isset($_POST['aplica_impuestos_internos']) ? 1 : 0,
            'alicuota_impuestos_internos'=> $_POST['alicuota_impuestos_internos'] !== ''
                                              ? (float)$_POST['alicuota_impuestos_internos']
                                              : null,
            'estado'                     => $_POST['estado'] ?? 'ACTIVO',
        ];

        if ($d['nombre'] === '') {
            jsonResponse(['ok' => false, 'msg' => 'El nombre es obligatorio.']);
        }
        if ($d['unidad_medida_id'] <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'La unidad de medida es obligatoria.']);
        }
        if (!in_array($d['metodo_costo'], ['PROMEDIO', 'MAYOR'], true)) {
            $d['metodo_costo'] = 'PROMEDIO';
        }
        if (!in_array($d['estado'], ['ACTIVO', 'INACTIVO'], true)) {
            $d['estado'] = 'ACTIVO';
        }
        // Si no es alcohólico, limpiamos los campos relacionados
        if (!$d['es_alcoholico']) {
            $d['aplica_impuestos_internos'] = 0;
            $d['alicuota_impuestos_internos'] = null;
        }

        if ($id === null) {
            $nuevoId = $this->insumo->crear($d);
            if ($nuevoId === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear (el código ya existe).']);
            }
            jsonResponse(['ok' => true, 'msg' => 'Insumo creado con éxito.', 'redirect' => BASE_URL . 'insumos/editar?id=' . $nuevoId]);
        }

        if (!$this->insumo->actualizar($id, $d)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar (el código ya está en uso).']);
        }

        // Recalcular costo (por si cambió el método o la alícuota)
        $nuevoCosto = $this->insumo->recalcularCosto($id);

        jsonResponse([
            'ok'    => true,
            'msg'   => 'Insumo actualizado con éxito.',
            'costo' => $nuevoCosto,
        ]);
    }

    public function eliminar(): void
    {
        if (!puedeEditarInsumos()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para esta acción.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        if (!$this->insumo->obtenerPorId($id)) {
            jsonResponse(['ok' => false, 'msg' => 'El insumo no existe.']);
        }

        if (!$this->insumo->cambiarEstado($id, 'INACTIVO')) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo desactivar el insumo.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Insumo desactivado.']);
    }

    /* ==========================================================
       PROVEEDORES DEL INSUMO
       ========================================================== */
    public function agregarProveedor(): void
    {
        if (!puedeEditarInsumos()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $insumoId     = (int)($_POST['insumo_id'] ?? 0);
        $proveedorId  = (int)($_POST['proveedor_id'] ?? 0);
        $costo        = (float)($_POST['costo'] ?? 0);
        $codigoProv   = trim($_POST['codigo_proveedor'] ?? '') ?: null;

        if ($insumoId <= 0 || $proveedorId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }
        if ($costo <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'El costo debe ser mayor a 0.']);
        }

        if (!$this->insumo->obtenerPorId($insumoId)) {
            jsonResponse(['ok' => false, 'msg' => 'El insumo no existe.']);
        }

        $rowId = $this->insumo->guardarProveedor($insumoId, $proveedorId, $costo, $codigoProv);
        if ($rowId === false) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo guardar el proveedor.']);
        }

        $nuevoCosto = $this->insumo->recalcularCosto($insumoId);

        // Devolver el HTML de la fila nueva para que el JS la inserte
        $proveedores = $this->insumo->listarProveedores($insumoId);
        $filaNueva = null;
        foreach ($proveedores as $p) {
            if ((int)$p['id'] === $rowId) {
                $filaNueva = $p;
                break;
            }
        }

        $html = '';
        if ($filaNueva) {
            ob_start();
            $row = $filaNueva; // variable consumida por el template
            require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_proveedor_row.php';
            $html = ob_get_clean();
        }

                // Generar HTML del historial actualizado (para refrescar sin recargar)
        $historial = $this->insumo->historialCostos($insumoId);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_historial_row.php';
        $historialHtml = ob_get_clean();

        jsonResponse([
            'ok'             => true,
            'msg'            => 'Proveedor agregado.',
            'html'           => $html,
            'rowId'          => $rowId,
            'costo'          => $nuevoCosto,
            'historial_html' => $historialHtml,  
             ]);
    }

    public function eliminarProveedor(): void
    {
        if (!puedeEditarInsumos()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id       = (int)($_POST['id'] ?? 0);
        $insumoId = (int)($_POST['insumo_id'] ?? 0);

        if ($id <= 0 || $insumoId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }

        if (!$this->insumo->eliminarProveedor($id, $insumoId)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo eliminar el proveedor.']);
        }

        $nuevoCosto = $this->insumo->recalcularCosto($insumoId);

                // Generar HTML del historial actualizado (para refrescar sin recargar)
        $historial = $this->insumo->historialCostos($insumoId);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_historial_row.php';
        $historialHtml = ob_get_clean();

        jsonResponse(['ok' => true, 'msg' => 'Proveedor eliminado.', 'costo' => $nuevoCosto]);
    }

        /* ==========================================================
       ACTUALIZAR PROVEEDOR DEL INSUMO (edición inline)
       ========================================================== */
    public function actualizarProveedor(): void
    {
        if (!puedeEditarInsumos()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $rowId      = (int)($_POST['id'] ?? 0);
        $insumoId   = (int)($_POST['insumo_id'] ?? 0);
        $costo      = (float)($_POST['costo'] ?? 0);
        $codigoProv = trim($_POST['codigo_proveedor'] ?? '') ?: null;

        if ($rowId <= 0 || $insumoId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }
        if ($costo <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'El costo debe ser mayor a 0.']);
        }

        if (!$this->insumo->actualizarProveedor($rowId, $insumoId, $costo, $codigoProv)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar.']);
        }

        $nuevoCosto = $this->insumo->recalcularCosto($insumoId);

        // Devolver HTML de la fila actualizada + costo del insumo
        $proveedores = $this->insumo->listarProveedores($insumoId);
        $fila = null;
        foreach ($proveedores as $p) {
            if ((int)$p['id'] === $rowId) {
                $fila = $p;
                break;
            }
        }

        $html = '';
        if ($fila) {
            ob_start();
            $row = $fila;
            require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_proveedor_row.php';
            $html = ob_get_clean();
        }

                // Generar HTML del historial actualizado (para refrescar sin recargar)
        $historial = $this->insumo->historialCostos($insumoId);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_historial_row.php';
        $historialHtml = ob_get_clean();

        jsonResponse([
            'ok'    => true,
            'msg'   => 'Proveedor actualizado.',
            'html'  => $html,
            'costo' => $nuevoCosto,
            'historial_html' => $historialHtml,
        ]);
    }

    /* ==========================================================
       HISTORIAL DE COSTOS
    ========================================================== */
    public function historial(): void
    {
        if (!puedeEditarInsumos()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }

        $insumoId = (int)($_GET['insumo_id'] ?? 0);
        if ($insumoId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $historial = $this->insumo->historialCostos($insumoId);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'insumo_historial_row.php';
        $html = ob_get_clean();

        jsonResponse(['ok' => true, 'html' => $html, 'count' => count($historial)]);
    }

    public function anularHistorial(): void
    {
        if (!puedeEditarInsumos()) {
            jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $historial = new HistorialValores();
        $res = $historial->anular($id, (int)$_SESSION['user_id']);

        jsonResponse($res);
    }

    /* ==========================================================
       EXPORTAR CSV / IMPRIMIR / PDF
       ========================================================== */
    public function exportar(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $insumos = $this->insumo->listar($search, $status);

        $filename = 'insumos_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['ID', 'Código', 'Nombre', 'Unidad', 'Medida', 'Stock',
                       'Punto pedido', 'Costo', 'Método', 'Alcohólico',
                       'Aplica II', 'Alícuota II', 'Estado'], ';');

        foreach ($insumos as $i) {
            fputcsv($out, [
                $i['id'],
                $i['codigo'] ?? '',
                $i['nombre'],
                $i['unidad_abrev'] ?? '',
                number_format((float)$i['medida'], 3, ',', '.'),
                number_format((float)$i['stock'], 3, ',', '.'),
                number_format((float)$i['ppedido'], 3, ',', '.'),
                number_format((float)$i['costo'], 2, ',', '.'),
                $i['metodo_costo'] === 'MAYOR' ? 'Mayor' : 'Promedio',
                (int)$i['es_alcoholico'] === 1 ? 'Sí' : 'No',
                (int)$i['aplica_impuestos_internos'] === 1 ? 'Sí' : 'No',
                $i['alicuota_impuestos_internos'] !== null
                    ? number_format((float)$i['alicuota_impuestos_internos'], 2, ',', '.') . '%'
                    : '—',
                $i['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function imprimir(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $insumos = $this->insumo->listar($search, $status);

        $filtros = [];
        if ($search !== '') $filtros[] = 'Búsqueda: "' . $search . '"';
        if ($status !== '') $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');

        require_once ROOT_PATH . 'views' . DS . 'insumo_print.php';
    }

    public function pdf(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $insumos = $this->insumo->listar($search, $status);

        $filtros = [];
        if ($search !== '') $filtros[] = 'Búsqueda: "' . $search . '"';
        if ($status !== '') $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');

        ob_start();
        require ROOT_PATH . 'views' . DS . 'insumo_pdf.php';
        $html = ob_get_clean();

        generarPdf($html, 'insumos', 'portrait');
    }

    /* ==========================================================
       AYUDA DEL MÓDULO
    ========================================================== */
    public function ayuda(): void
    {
        // Sin permisos especiales: si puede ver el módulo, puede ver la ayuda.
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'insumo.php';
    }
}