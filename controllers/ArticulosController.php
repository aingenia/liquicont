<?php
/**
 * controllers/ArticulosController.php
 * Solo Admin/Dev.
 */

class ArticulosController
{
    private Articulo $articulo;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos para esta acción.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->articulo = new Articulo();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $articulos = $this->articulo->listar($search, $status);

        $page_title     = 'Artículos';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'articulos.js';
        $pageStyle      = 'articulo.css';

        require_once ROOT_PATH . 'views' . DS . 'articulo.php';
    }

    public function buscar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['status' => 'error', 'msg' => 'Método no permitido.'], 405);
        }
        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $articulos = $this->articulo->listar($search, $status);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'articulos_row.php';
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

        $modo          = 'crear';
        $articulo      = null;
        $formula       = [];
        $precios       = [];
        $combos        = $this->articulo->combos();
        $page_title    = 'Nuevo Artículo';
        $headerGreeting= 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript    = 'articulos.js';
        $pageStyle     = 'articulo.css';

        require_once ROOT_PATH . 'views' . DS . 'articulo_form.php';
    }

    public function editar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'articulos');
            exit;
        }

        $articulo = $this->articulo->obtenerPorId($id);
        if (!$articulo) {
            header('Location: ' . BASE_URL . 'articulos');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar($id);
            return;
        }

        $modo          = 'editar';
        $formula       = $this->articulo->listarFormula($id);
        $precios       = $this->articulo->listarPrecios($id);
        $combos        = $this->articulo->combos();
        $page_title    = 'Editar Artículo';
        $headerGreeting= 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript    = 'articulos.js';
        $pageStyle     = 'articulo.css';

        require_once ROOT_PATH . 'views' . DS . 'articulo_form.php';
    }

    private function guardar(?int $id = null): void
    {
        $d = [
            'codigo'           => trim($_POST['codigo'] ?? ''),
            'nombre'           => trim($_POST['nombre'] ?? ''),
            'descripcion'      => trim($_POST['descripcion'] ?? ''),
            'formato'          => trim($_POST['formato'] ?? '') ?: null,
            'sabor'            => trim($_POST['sabor']   ?? '') ?: null,
            'ppedido'          => (int)($_POST['ppedido'] ?? 0),
            'costo_automatico' => isset($_POST['costo_automatico']) ? 1 : 0,
            'pakaging_id'      => !empty($_POST['pakaging_id']) ? (int)$_POST['pakaging_id'] : null,
            'estado'           => $_POST['estado'] ?? 'ACTIVO',
        ];

        if ($d['nombre'] === '') {
            jsonResponse(['ok' => false, 'msg' => 'El nombre es obligatorio.']);
        }
        if (!in_array($d['estado'], ['ACTIVO', 'INACTIVO'], true)) {
            $d['estado'] = 'ACTIVO';
        }

        if ($id === null) {
            $nuevoId = $this->articulo->crear($d);
            if ($nuevoId === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear (el código ya existe).']);
            }
            jsonResponse([
                'ok'       => true,
                'msg'      => 'Artículo creado con éxito.',
                'redirect' => BASE_URL . 'articulos/editar?id=' . $nuevoId,
            ]);
        }

        if (!$this->articulo->actualizar($id, $d)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar (el código ya está en uso).']);
        }

        // Si NO es automático y vino un costo manual, lo guardamos
        if ((int)$d['costo_automatico'] === 0 && isset($_POST['costo_manual'])) {
            $costoManual = (float)$_POST['costo_manual'];
            $anterior = (float)$this->articulo->obtenerPorId($id)['costo'];
            if (abs($costoManual - $anterior) > 0.0001) {
                $this->articulo->actualizarCostoManual($id, $costoManual);
                // registrar en historial vía articulo (reusamos recalcular pero forzamos)
            }
        } else {
            // Recalcular si es automático
            $this->articulo->recalcularCosto($id);
        }

        $art = $this->articulo->obtenerPorId($id);

        jsonResponse([
            'ok'    => true,
            'msg'   => 'Artículo actualizado con éxito.',
            'costo' => (float)$art['costo'],
        ]);
    }

    public function eliminar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->articulo->obtenerPorId($id)) {
            jsonResponse(['ok' => false, 'msg' => 'El artículo no existe.']);
        }
        if (!$this->articulo->cambiarEstado($id, 'INACTIVO')) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo desactivar.']);
        }
        jsonResponse(['ok' => true, 'msg' => 'Artículo desactivado.']);
    }

    /* ==========================================================
       FÓRMULA — insumos del artículo
       ========================================================== */
    public function agregarInsumo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $articuloId = (int)($_POST['articulo_id'] ?? 0);
        $insumoId   = (int)($_POST['insumo_id'] ?? 0);
        $cantidad   = (float)($_POST['cantidad'] ?? 0);

        if ($articuloId <= 0 || $insumoId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }
        if ($cantidad <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'La cantidad debe ser mayor a 0.']);
        }

        $rowId = $this->articulo->agregarInsumo($articuloId, $insumoId, $cantidad);
        if ($rowId === false) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo agregar el insumo.']);
        }

        $nuevoCosto = $this->articulo->recalcularCosto($articuloId);

        // Traer la fila recién creada/actualizada para devolver su HTML
        $formula = $this->articulo->listarFormula($articuloId);
        $fila = null;
        foreach ($formula as $f) {
            if ((int)$f['id'] === $rowId) { $fila = $f; break; }
        }

        $html = '';
        if ($fila) {
            ob_start();
            $row = $fila;
            require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'articulo_formula_row.php';
            $html = ob_get_clean();
        }

        jsonResponse([
            'ok'    => true,
            'msg'   => 'Insumo agregado.',
            'html'  => $html,
            'rowId' => $rowId,
            'costo' => $nuevoCosto,
        ]);
    }

    public function actualizarInsumo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $rowId      = (int)($_POST['id'] ?? 0);
        $articuloId = (int)($_POST['articulo_id'] ?? 0);
        $cantidad   = (float)($_POST['cantidad'] ?? 0);

        if ($rowId <= 0 || $articuloId <= 0 || $cantidad <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos inválidos.']);
        }

        if (!$this->articulo->actualizarCantidadInsumo($rowId, $articuloId, $cantidad)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar.']);
        }

        $nuevoCosto = $this->articulo->recalcularCosto($articuloId);

        $formula = $this->articulo->listarFormula($articuloId);
        $fila = null;
        foreach ($formula as $f) {
            if ((int)$f['id'] === $rowId) { $fila = $f; break; }
        }

        $html = '';
        if ($fila) {
            ob_start();
            $row = $fila;
            require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'articulo_formula_row.php';
            $html = ob_get_clean();
        }

        jsonResponse([
            'ok'    => true,
            'msg'   => 'Cantidad actualizada.',
            'html'  => $html,
            'costo' => $nuevoCosto,
        ]);
    }

    public function eliminarInsumo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $rowId      = (int)($_POST['id'] ?? 0);
        $articuloId = (int)($_POST['articulo_id'] ?? 0);

        if ($rowId <= 0 || $articuloId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos inválidos.']);
        }

        if (!$this->articulo->eliminarInsumo($rowId, $articuloId)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo eliminar.']);
        }

        $nuevoCosto = $this->articulo->recalcularCosto($articuloId);

        jsonResponse([
            'ok'    => true,
            'msg'   => 'Insumo eliminado de la fórmula.',
            'costo' => $nuevoCosto,
        ]);
    }

    /* ==========================================================
       PRECIOS POR CATEGORÍA
       ========================================================== */
    public function guardarPrecio(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $articuloId  = (int)($_POST['articulo_id'] ?? 0);
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $precio      = (float)($_POST['precio'] ?? 0);
        $visible     = (int)($_POST['visible'] ?? 0);

        if ($articuloId <= 0 || $categoriaId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'Datos incompletos.']);
        }
        if ($precio < 0) {
            jsonResponse(['ok' => false, 'msg' => 'El precio no puede ser negativo.']);
        }

        if (!$this->articulo->guardarPrecio($articuloId, $categoriaId, $precio, $visible)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo guardar el precio.']);
        }

        jsonResponse(['ok' => true, 'msg' => 'Precio guardado.']);
    }

    /* ==========================================================
       IMAGEN
       ========================================================== */
    public function subirImagen(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $articuloId = (int)($_POST['articulo_id'] ?? 0);
        if ($articuloId <= 0 || !$this->articulo->obtenerPorId($articuloId)) {
            jsonResponse(['ok' => false, 'msg' => 'Artículo inválido.']);
        }

        if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['ok' => false, 'msg' => 'No se recibió la imagen.']);
        }

        $file = $_FILES['imagen'];
        if ($file['size'] > 2 * 1024 * 1024) {
            jsonResponse(['ok' => false, 'msg' => 'La imagen no puede superar los 2 MB.']);
        }

        $info = @getimagesize($file['tmp_name']);
        if (!$info) {
            jsonResponse(['ok' => false, 'msg' => 'El archivo no es una imagen válida.']);
        }

        $permitidos = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];
        if (!isset($permitidos[$info[2]])) {
            jsonResponse(['ok' => false, 'msg' => 'Formato no permitido (solo JPG, PNG o WebP).']);
        }

        $ext       = $permitidos[$info[2]];
        $dirRel    = 'uploads' . DS . 'articulos';
        $dirAbs    = ROOT_PATH . $dirRel;
        if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);

        $nombre    = 'articulo_' . $articuloId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destino   = $dirAbs . DS . $nombre;
        $relPath   = str_replace(DS, '/', $dirRel . DS . $nombre);

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo guardar el archivo.']);
        }

        // Borrar la imagen anterior
        $art = $this->articulo->obtenerPorId($articuloId);
        if (!empty($art['imagen_path'])) {
            $viejo = ROOT_PATH . str_replace('/', DS, $art['imagen_path']);
            if (is_file($viejo) && strpos($viejo, $dirAbs) === 0) {
                @unlink($viejo);
            }
        }

        $nombreOriginal = $_POST['nombre_original'] ?? $file['name'];
        $this->articulo->actualizarImagen($articuloId, $relPath, $nombreOriginal);

        jsonResponse([
            'ok'   => true,
            'msg'  => 'Imagen subida.',
            'path' => BASE_URL . $relPath,
        ]);
    }

    public function eliminarImagen(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $articuloId = (int)($_POST['articulo_id'] ?? 0);
        $art = $this->articulo->obtenerPorId($articuloId);
        if (!$art) {
            jsonResponse(['ok' => false, 'msg' => 'Artículo no encontrado.']);
        }

        if (!empty($art['imagen_path'])) {
            $full = ROOT_PATH . str_replace('/', DS, $art['imagen_path']);
            if (is_file($full)) @unlink($full);
        }

        $this->articulo->actualizarImagen($articuloId, null, null);
        jsonResponse(['ok' => true, 'msg' => 'Imagen eliminada.']);
    }

    /* ==========================================================
       HISTORIAL DE COSTO
       ========================================================== */
    public function historial(): void
    {
        $articuloId = (int)($_GET['articulo_id'] ?? 0);
        if ($articuloId <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $historial = $this->articulo->historialCostos($articuloId);

        ob_start();
        require ROOT_PATH . 'views' . DS . 'layouts' . DS . 'articulo_historial_row.php';
        $html = ob_get_clean();

        jsonResponse(['ok' => true, 'html' => $html]);
    }

    public function anularHistorial(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        $hist = new HistorialValores();
        jsonResponse($hist->anular($id, (int)$_SESSION['user_id']));
    }

    /* ==========================================================
       EXPORTAR / IMPRIMIR / PDF
       ========================================================== */
    public function exportar(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $articulos = $this->articulo->listar($search, $status);

        $filename = 'articulos_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['ID', 'Código', 'Nombre', 'Insumos', 'Costo', 'Punto pedido', 'Estado'], ';');

        foreach ($articulos as $a) {
            fputcsv($out, [
                $a['id'],
                $a['codigo'] ?? '',
                $a['nombre'],
                (int)$a['insumos_count'],
                number_format((float)$a['costo'], 2, ',', '.'),
                (int)$a['ppedido'],
                $a['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo',
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function imprimir(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $articulos = $this->articulo->listar($search, $status);

        $filtros = [];
        if ($search !== '') $filtros[] = 'Búsqueda: "' . $search . '"';
        if ($status !== '') $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');

        require_once ROOT_PATH . 'views' . DS . 'articulo_print.php';
    }

    public function pdf(): void
    {
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $articulos = $this->articulo->listar($search, $status);

        $filtros = [];
        if ($search !== '') $filtros[] = 'Búsqueda: "' . $search . '"';
        if ($status !== '') $filtros[] = 'Estado: ' . ($status === 'ACTIVO' ? 'Activos' : 'Inactivos');

        ob_start();
        require ROOT_PATH . 'views' . DS . 'articulo_pdf.php';
        $html = ob_get_clean();

        generarPdf($html, 'articulos', 'portrait');
    }

    /* ==========================================================
       AYUDA
       ========================================================== */
    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'articulo.php';
    }
}