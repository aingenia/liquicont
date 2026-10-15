<?php
/**
 * controllers/ContabilidadController.php
 * Solo Admin / Desarrollador.
 */

require_once ROOT_PATH . 'models' . DS . 'EjercicioContable.php';
require_once ROOT_PATH . 'models' . DS . 'PlanCuentas.php';
require_once ROOT_PATH . 'models' . DS . 'Asiento.php';
require_once ROOT_PATH . 'models' . DS . 'Emisor.php';

class ContabilidadController
{
    private EjercicioContable $ejercicios;
    private PlanCuentas       $cuentas;
    private Asiento           $asientos;
    private Emisor            $emisores;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->ejercicios = new EjercicioContable();
        $this->cuentas    = new PlanCuentas();
        $this->asientos   = new Asiento();
        $this->emisores   = new Emisor();
    }

    /* ==========================================================
       DASHBOARD
       ========================================================== */
    public function index(): void
    {
        $ejercicioActual = $this->ejercicios->actual();
        $ultimos = $this->asientos->listar([
            'ejercicio_id' => $ejercicioActual['id'] ?? null,
        ]);

        $page_title     = 'Contabilidad';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad.php';
    }

    /* ==========================================================
       EJERCICIOS
       ========================================================== */
    public function ejercicios(): void
    {
        $lista = $this->ejercicios->all();

        $page_title     = 'Ejercicios contables';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_ejercicios.php';
    }

    public function ejercicioForm(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $ej = $id ? $this->ejercicios->find($id) : null;

        $page_title     = $ej ? 'Editar ejercicio' : 'Nuevo ejercicio';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_ejercicio_form.php';
    }

    public function ejercicioGuardar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'nombre'      => $_POST['nombre']      ?? '',
            'fecha_desde' => $_POST['fecha_desde'] ?? '',
            'fecha_hasta' => $_POST['fecha_hasta'] ?? '',
        ];

        if ($id > 0) {
            $r = $this->ejercicios->actualizar($id, $data);
        } else {
            $r = $this->ejercicios->crear($data, (int)$_SESSION['user_id']);
        }
        jsonResponse($r);
    }

    public function ejercicioCerrar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $r  = $this->ejercicios->cerrar($id, (int)$_SESSION['user_id']);
        jsonResponse($r);
    }

    public function ejercicioReabrir(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $r  = $this->ejercicios->reabrir($id);
        jsonResponse($r);
    }

    public function ejercicioEliminar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $r  = $this->ejercicios->eliminar($id);
        jsonResponse($r);
    }

    /* ==========================================================
       PLAN DE CUENTAS
       ========================================================== */
    public function planCuentas(): void
    {
        $arbol = $this->cuentas->arbol();

        $page_title     = 'Plan de cuentas';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_plan_cuentas.php';
    }

    public function cuentaForm(): void
    {
        $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $cta = $id ? $this->cuentas->find($id) : null;

        // Cuentas candidatas a padre (todas menos la propia y sus descendientes)
        $padres = $this->cuentas->all();

        $page_title     = $cta ? 'Editar cuenta' : 'Nueva cuenta';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_cuenta_form.php';
    }

    public function cuentaGuardar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'codigo'     => $_POST['codigo']     ?? '',
            'nombre'     => $_POST['nombre']     ?? '',
            'tipo'       => $_POST['tipo']       ?? '',
            'naturaleza' => $_POST['naturaleza'] ?? '',
            'padre_id'   => $_POST['padre_id']   ?? null,
            'imputable'  => isset($_POST['imputable']) ? 1 : 0,
            'orden'      => $_POST['orden']      ?? 0,
        ];

        if ($id > 0) {
            $r = $this->cuentas->actualizar($id, $data);
        } else {
            $r = $this->cuentas->crear($data);
        }
        jsonResponse($r);
    }

    public function cuentaEstado(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? 'ACTIVA';
        $r      = $this->cuentas->cambiarEstado($id, $estado);
        jsonResponse($r);
    }

    /* ==========================================================
       ASIENTOS
       ========================================================== */
    public function asientos(): void
    {
        $filtros = [
            'ejercicio_id' => $_GET['ejercicio_id'] ?? null,
            'emisor_id'    => $_GET['emisor_id']    ?? null,
            'estado'       => $_GET['estado']       ?? null,
            'tipo'         => $_GET['tipo']         ?? null,
            'desde'        => $_GET['desde']        ?? null,
            'hasta'        => $_GET['hasta']        ?? null,
            'texto'        => $_GET['q']            ?? null,
        ];
        $lista = $this->asientos->listar($filtros);

        $ejercicios = $this->ejercicios->all();
        $emisores   = $this->emisores->all();

        $page_title     = 'Asientos contables';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_asientos.php';
    }

    public function asientoForm(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $as = $id ? $this->asientos->find($id) : null;

        if ($as && $as['estado'] !== 'BORRADOR') {
            header('Location: ' . BASE_URL . 'contabilidad/asiento-ver?id=' . $id);
            exit;
        }

        $ejercicioActual = $this->ejercicios->actual();
        if (!$ejercicioActual && !$as) {
            $_SESSION['flash_error'] = 'Primero creá un ejercicio contable abierto.';
            header('Location: ' . BASE_URL . 'contabilidad/ejercicios');
            exit;
        }

        $cuentas   = $this->cuentas->imputables();
        $emisores  = $this->emisores->all();
        $ejercicios = $this->ejercicios->abiertos();

        $page_title     = $as ? 'Editar asiento' : 'Nuevo asiento';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_asiento_form.php';
    }

    public function asientoVer(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $as = $this->asientos->find($id);

        if (!$as) {
            header('Location: ' . BASE_URL . 'contabilidad/asientos');
            exit;
        }

        $page_title     = 'Asiento N° ' . $as['numero'];
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_asiento_ver.php';
    }

    public function asientoGuardar(): void
    {
        $id = (int)($_POST['id'] ?? 0);

        // Recolectar líneas del POST (arrays paralelos)
        $cuentaIds   = $_POST['linea_cuenta_id']   ?? [];
        $debes       = $_POST['linea_debe']        ?? [];
        $haberes     = $_POST['linea_haber']       ?? [];
        $detalles    = $_POST['linea_detalle']     ?? [];
        $auxTipos    = $_POST['linea_aux_tipo']    ?? [];
        $auxIds      = $_POST['linea_aux_id']      ?? [];

        $lineas = [];
        foreach ($cuentaIds as $i => $cuentaId) {
            $lineas[] = [
                'cuenta_id'     => (int)$cuentaId,
                'debe'          => (float)($debes[$i]   ?? 0),
                'haber'         => (float)($haberes[$i] ?? 0),
                'detalle'       => $detalles[$i]  ?? null,
                'auxiliar_tipo' => $auxTipos[$i]  ?? 'NINGUNO',
                'auxiliar_id'   => !empty($auxIds[$i]) ? (int)$auxIds[$i] : null,
            ];
        }

        $cab = [
            'ejercicio_id'    => (int)($_POST['ejercicio_id'] ?? 0),
            'emisor_id'       => (int)($_POST['emisor_id']    ?? 0),
            'fecha'           => $_POST['fecha']              ?? '',
            'tipo'            => $_POST['tipo']               ?? 'DIARIO',
            'descripcion'     => $_POST['descripcion']        ?? '',
            'referencia_tipo' => null,
            'referencia_id'   => null,
        ];

        if ($id > 0) {
            // El ejercicio/emisor no se pueden cambiar al editar
            $actual = $this->asientos->find($id);
            if (!$actual) jsonResponse(['ok' => false, 'msg' => 'Asiento inexistente.']);
            $cab['ejercicio_id'] = (int)$actual['ejercicio_id'];
            $cab['emisor_id']    = (int)$actual['emisor_id'];

            $r = $this->asientos->actualizar($id, $cab, $lineas);
            if (!empty($r['ok'])) $r['id'] = $id;
        } else {
            $r = $this->asientos->crear($cab, $lineas, (int)$_SESSION['user_id']);
        }
        jsonResponse($r);
    }

    public function asientoConfirmar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $r  = $this->asientos->confirmar($id, (int)$_SESSION['user_id']);
        jsonResponse($r);
    }

    public function asientoAnular(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') jsonResponse(['ok' => false, 'msg' => 'Indicá el motivo.']);
        $r = $this->asientos->anular($id, (int)$_SESSION['user_id'], $motivo);
        jsonResponse($r);
    }

    public function asientoEliminar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $r  = $this->asientos->eliminar($id);
        jsonResponse($r);
    }

    /* ==========================================================
       REPORTES
       ========================================================== */
    public function libroDiario(): void
    {
        $desde      = $_GET['desde'] ?? date('Y-m-01');
        $hasta      = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId   = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;
        $filas      = $this->asientos->libroDiario($desde, $hasta, $emisorId);
        $emisores   = $this->emisores->all();

        $page_title     = 'Libro Diario';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_libro_diario.php';
    }

    public function libroMayor(): void
    {
        $desde     = $_GET['desde']     ?? date('Y-m-01');
        $hasta     = $_GET['hasta']     ?? date('Y-m-d');
        $cuentaId  = (int)($_GET['cuenta_id'] ?? 0);
        $emisorId  = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $cuenta    = $cuentaId ? $this->cuentas->find($cuentaId) : null;
        $data      = $cuentaId ? $this->asientos->libroMayor($cuentaId, $desde, $hasta, $emisorId) : null;

        $cuentas   = $this->cuentas->imputables();
        $emisores  = $this->emisores->all();

        $page_title     = 'Libro Mayor';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_libro_mayor.php';
    }

    public function balance(): void
    {
        $desde     = $_GET['desde'] ?? date('Y-01-01');
        $hasta     = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId  = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $data      = $this->asientos->balanceSumasYSaldos($desde, $hasta, $emisorId);
        $emisores  = $this->emisores->all();

        $page_title     = 'Balance de Sumas y Saldos';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_balance.php';
    }

    /* ==========================================================
   CONFIGURACIÓN CONTABLE
   ========================================================== */
    public function configuracion(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'ConfiguracionContable.php';
        $configModel = new ConfiguracionContable();

        $config = $configModel->all();
        $faltantes = $configModel->faltantes();
        $cuentasImputables = $this->cuentas->imputables();

        $page_title     = 'Configuración contable';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_configuracion.php';
    }

    public function guardarConfiguracion(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'ConfiguracionContable.php';
        $configModel = new ConfiguracionContable();

        $claves = $_POST['cuentas'] ?? [];
        if (!is_array($claves)) {
            jsonResponse(['ok' => false, 'msg' => 'Datos inválidos.']);
        }

        // Sanitizar: valores vacíos → null
        $valores = [];
        foreach ($claves as $clave => $cuentaId) {
            $valores[$clave] = ($cuentaId !== '' && $cuentaId !== null)
                ? (int)$cuentaId
                : null;
        }

        $r = $configModel->guardar($valores);
        if (!empty($r['ok'])) {
            $r['msg'] = 'Configuración guardada.';
        }
        jsonResponse($r);
    }

/* ==========================================================
   LIBRO IVA
   ========================================================== */
    public function libroIvaCompras(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'LibroIva.php';
        $libro = new LibroIva();

        $desde    = $_GET['desde'] ?? date('Y-m-01');
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $filas    = $libro->compras($desde, $hasta, $emisorId);
        $totales  = $libro->totalizar($filas);
        $emisores = $this->emisores->all();

        $page_title     = 'Libro IVA Compras';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_libro_iva_compras.php';
    }

    public function libroIvaVentas(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'LibroIva.php';
        $libro = new LibroIva();

        $desde    = $_GET['desde'] ?? date('Y-m-01');
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $filas    = $libro->ventas($desde, $hasta, $emisorId);
        $totales  = $libro->totalizar($filas);
        $emisores = $this->emisores->all();

        $page_title     = 'Libro IVA Ventas';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_libro_iva_ventas.php';
    }

    public function libroIvaComprasCsv(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'LibroIva.php';
        $libro = new LibroIva();

        $desde    = $_GET['desde'] ?? date('Y-m-01');
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $filas = $libro->compras($desde, $hasta, $emisorId);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="libro_iva_compras_' . date('Ymd') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Fecha', 'Tipo', 'Número', 'Razón Social', 'CUIT', 'Cond. IVA', 'Provincia',
            'Neto Gravado', 'No Gravado', 'Exento', 'IVA', 'Perc. IVA', 'Perc. IIBB',
            'Imp. Internos', 'Total',
        ], ';');

        foreach ($filas as $f) {
            fputcsv($out, [
                date('d/m/Y', strtotime($f['fecha'])),
                $f['tipo_comprobante'] ?? '',
                $f['numero'] ?? '',
                $f['razon_social'],
                $f['cuit'] ?? '',
                $f['condicion_iva'] ?? '',
                $f['provincia'] ?? '',
                number_format((float)$f['neto_gravado'], 2, ',', '.'),
                number_format((float)$f['neto_no_gravado'], 2, ',', '.'),
                number_format((float)$f['neto_exento'], 2, ',', '.'),
                number_format((float)$f['iva'], 2, ',', '.'),
                number_format((float)$f['perc_iva'], 2, ',', '.'),
                number_format((float)$f['perc_iibb'], 2, ',', '.'),
                number_format((float)$f['impuestos_internos'], 2, ',', '.'),
                number_format((float)$f['total'], 2, ',', '.'),
            ], ';');
        }

        fclose($out);
        exit;
    }

    public function libroIvaVentasCsv(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'LibroIva.php';
        $libro = new LibroIva();

        $desde    = $_GET['desde'] ?? date('Y-m-01');
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $filas = $libro->ventas($desde, $hasta, $emisorId);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="libro_iva_ventas_' . date('Ymd') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Fecha', 'Tipo', 'Número', 'Razón Social', 'CUIT', 'Cond. IVA', 'Provincia',
            'Neto Gravado', 'No Gravado', 'Exento', 'IVA', 'Perc. IVA', 'Perc. IIBB',
            'Imp. Internos', 'Total',
        ], ';');

        foreach ($filas as $f) {
            fputcsv($out, [
                date('d/m/Y', strtotime($f['fecha'])),
                $f['tipo_comprobante'] ?? '',
                $f['numero'] ?? '',
                $f['razon_social'],
                $f['cuit'] ?? '',
                $f['condicion_iva'] ?? '',
                $f['provincia'] ?? '',
                number_format((float)$f['neto_gravado'], 2, ',', '.'),
                number_format((float)$f['neto_no_gravado'], 2, ',', '.'),
                number_format((float)$f['neto_exento'], 2, ',', '.'),
                number_format((float)$f['iva'], 2, ',', '.'),
                number_format((float)$f['perc_iva'], 2, ',', '.'),
                number_format((float)$f['perc_iibb'], 2, ',', '.'),
                number_format((float)$f['impuestos_internos'], 2, ',', '.'),
                number_format((float)$f['total'], 2, ',', '.'),
            ], ';');
        }

        fclose($out);
        exit;
    }

    /* ==========================================================
   COMPARATIVA VENTAS vs COMPRAS
   ========================================================== */
    public function comparativa(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'LibroIva.php';
        $libro = new LibroIva();

        $desde    = $_GET['desde'] ?? date('Y-01-01');
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $data     = $libro->comparativaVentasCompras($desde, $hasta, $emisorId);
        $emisores = $this->emisores->all();

        $page_title     = 'Comparativa Ventas vs Compras';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_comparativa.php';
    }

    public function comparativaCsv(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'LibroIva.php';
        $libro = new LibroIva();

        $desde    = $_GET['desde'] ?? date('Y-01-01');
        $hasta    = $_GET['hasta'] ?? date('Y-m-d');
        $emisorId = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $data = $libro->comparativaVentasCompras($desde, $hasta, $emisorId);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="comparativa_' . date('Ymd') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Mes', 'Concepto',
                    'Neto', 'IVA', 'Perc. IIBB', 'Imp. Internos', 'Total'], ';');

        foreach ($data['filas'] as $f) {
            foreach (['ventas','compras','posicion'] as $tipo) {
                $label = $tipo === 'ventas' ? 'VENTAS'
                    : ($tipo === 'compras' ? 'COMPRAS' : 'POSICIÓN');
                fputcsv($out, [
                    $f['mes_label'],
                    $label,
                    number_format($f[$tipo]['neto'], 2, ',', '.'),
                    number_format($f[$tipo]['iva'], 2, ',', '.'),
                    number_format($f[$tipo]['perc_iibb'], 2, ',', '.'),
                    number_format($f[$tipo]['imp_int'], 2, ',', '.'),
                    number_format($f[$tipo]['total'], 2, ',', '.'),
                ], ';');
            }
        }

        // Totales generales
        foreach (['ventas','compras','posicion'] as $tipo) {
            $label = $tipo === 'ventas' ? 'TOTAL VENTAS'
                : ($tipo === 'compras' ? 'TOTAL COMPRAS' : 'POSICIÓN TOTAL');
            fputcsv($out, [
                '—', $label,
                number_format($data['totales'][$tipo]['neto'], 2, ',', '.'),
                number_format($data['totales'][$tipo]['iva'], 2, ',', '.'),
                number_format($data['totales'][$tipo]['perc_iibb'], 2, ',', '.'),
                number_format($data['totales'][$tipo]['imp_int'], 2, ',', '.'),
                number_format($data['totales'][$tipo]['total'], 2, ',', '.'),
            ], ';');
        }

        fclose($out);
        exit;
    }
    
    /* ==========================================================
   ESTADOS CONTABLES
   ========================================================== */
    public function estadoResultados(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'EstadosContables.php';
        $model = new EstadosContables();

        $ejercicioId    = (int)($_GET['ejercicio_id'] ?? 0);
        $compararId     = (int)($_GET['comparar_id'] ?? 0) ?: null;
        $emisorId       = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        // Si no hay ejercicio elegido, usar el abierto actual
        if ($ejercicioId <= 0) {
            $actual = $this->ejercicios->actual();
            $ejercicioId = $actual['id'] ?? 0;
        }

        $data = $ejercicioId > 0
            ? $model->estadoResultados($ejercicioId, $emisorId, $compararId)
            : ['ok' => false, 'msg' => 'No hay ejercicio seleccionado.'];

        $ejercicios = $this->ejercicios->all();
        $emisores   = $this->emisores->all();

        $page_title     = 'Estado de Resultados';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_estado_resultados.php';
    }

    public function balanceGeneral(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'EstadosContables.php';
        $model = new EstadosContables();

        $ejercicioId    = (int)($_GET['ejercicio_id'] ?? 0);
        $compararId     = (int)($_GET['comparar_id'] ?? 0) ?: null;
        $emisorId       = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        if ($ejercicioId <= 0) {
            $actual = $this->ejercicios->actual();
            $ejercicioId = $actual['id'] ?? 0;
        }

        $data = $ejercicioId > 0
            ? $model->balanceGeneral($ejercicioId, $emisorId, $compararId)
            : ['ok' => false, 'msg' => 'No hay ejercicio seleccionado.'];

        $ejercicios = $this->ejercicios->all();
        $emisores   = $this->emisores->all();

        $page_title     = 'Balance General';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'contabilidad.js';
        $pageStyle      = 'contabilidad.css';

        require_once ROOT_PATH . 'views' . DS . 'contabilidad_balance_general.php';
    }

    public function estadoResultadosCsv(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'EstadosContables.php';
        $model = new EstadosContables();

        $ejercicioId = (int)($_GET['ejercicio_id'] ?? 0);
        $compararId  = (int)($_GET['comparar_id'] ?? 0) ?: null;
        $emisorId    = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $data = $model->estadoResultados($ejercicioId, $emisorId, $compararId);
        if (empty($data['ok'])) {
            http_response_code(400);
            die($data['msg'] ?? 'Error.');
        }

        $this->streamCsv('estado_resultados_' . date('Ymd') . '.csv', function ($out) use ($data) {
            $tieneComp = !empty($data['grupos_ant']);

            fputcsv($out, ['ESTADO DE RESULTADOS', '', '', '']);
            fputcsv($out, ['Ejercicio:', $data['ejercicio']['nombre'], '', '']);
            fputcsv($out, ['Desde:', $data['ejercicio']['fecha_desde'], 'Hasta:', $data['ejercicio']['fecha_hasta']]);
            fputcsv($out, ['', '', '', '']);
            fputcsv($out, array_merge(
                ['Código', 'Cuenta', 'Saldo actual'],
                $tieneComp ? ['Saldo comparativo', 'Variación %'] : []
            ), ';');

            $emitir = function (array $grupos, string $titulo) use ($out, $tieneComp) {
                foreach ($grupos as $g) {
                    fputcsv($out, [$g['codigo'], $titulo . ': ' . $g['nombre'], '', ''], ';');
                    foreach ($g['cuentas'] as $c) {
                        $row = [$c['codigo'], '  ' . $c['nombre'], number_format($c['saldo'], 2, ',', '.')];
                        if ($tieneComp) {
                            $row[] = number_format($c['saldo_anterior'] ?? 0, 2, ',', '.');
                            $row[] = $c['variacion_pct'] !== null
                                ? number_format($c['variacion_pct'], 2, ',', '.') . '%'
                                : '—';
                        }
                        fputcsv($out, $row, ';');
                    }
                    fputcsv($out, ['', 'Subtotal ' . $g['nombre'], number_format($g['total'], 2, ',', '.')], ';');
                }
            };

            $emitir($data['grupos']['ingresos'], 'INGRESOS');
            fputcsv($out, [], ';');
            $emitir($data['grupos']['egresos'], 'EGRESOS');
            fputcsv($out, [], ';');
            fputcsv($out, ['', 'RESULTADO DEL EJERCICIO', number_format($data['total'], 2, ',', '.')], ';');
        });
    }

    public function balanceGeneralCsv(): void
    {
        require_once ROOT_PATH . 'models' . DS . 'EstadosContables.php';
        $model = new EstadosContables();

        $ejercicioId = (int)($_GET['ejercicio_id'] ?? 0);
        $compararId  = (int)($_GET['comparar_id'] ?? 0) ?: null;
        $emisorId    = !empty($_GET['emisor_id']) ? (int)$_GET['emisor_id'] : null;

        $data = $model->balanceGeneral($ejercicioId, $emisorId, $compararId);
        if (empty($data['ok'])) {
            http_response_code(400);
            die($data['msg'] ?? 'Error.');
        }

        $this->streamCsv('balance_general_' . date('Ymd') . '.csv', function ($out) use ($data) {
            $tieneComp = !empty($data['grupos_ant']);

            fputcsv($out, ['BALANCE GENERAL', '', '', '']);
            fputcsv($out, ['Ejercicio:', $data['ejercicio']['nombre'], '', '']);
            fputcsv($out, ['Al', $data['ejercicio']['fecha_hasta'], '', '']);
            fputcsv($out, ['', '', '', '']);
            fputcsv($out, array_merge(
                ['Código', 'Cuenta', 'Saldo actual'],
                $tieneComp ? ['Saldo comparativo', 'Variación %'] : []
            ), ';');

            $emitir = function (array $grupos, string $titulo) use ($out, $tieneComp) {
                foreach ($grupos as $g) {
                    fputcsv($out, [$g['codigo'], $titulo . ': ' . $g['nombre'], '', ''], ';');
                    foreach ($g['cuentas'] as $c) {
                        $row = [$c['codigo'], '  ' . $c['nombre'], number_format($c['saldo'], 2, ',', '.')];
                        if ($tieneComp) {
                            $row[] = number_format($c['saldo_anterior'] ?? 0, 2, ',', '.');
                            $row[] = $c['variacion_pct'] !== null
                                ? number_format($c['variacion_pct'], 2, ',', '.') . '%'
                                : '—';
                        }
                        fputcsv($out, $row, ';');
                    }
                    fputcsv($out, ['', 'Subtotal ' . $g['nombre'], number_format($g['total'], 2, ',', '.')], ';');
                }
            };

            $emitir($data['grupos']['activo'], 'ACTIVO');
            fputcsv($out, ['', 'TOTAL ACTIVO', number_format($data['totales']['activo'], 2, ',', '.')], ';');
            fputcsv($out, [], ';');

            $emitir($data['grupos']['pasivo'], 'PASIVO');
            fputcsv($out, ['', 'TOTAL PASIVO', number_format($data['totales']['pasivo'], 2, ',', '.')], ';');
            fputcsv($out, [], ';');

            $emitir($data['grupos']['patrimonio'], 'PATRIMONIO');
            fputcsv($out, ['', 'TOTAL PATRIMONIO', number_format($data['totales']['patrimonio'], 2, ',', '.')], ';');
            fputcsv($out, [], ';');

            fputcsv($out, ['', 'TOTAL PASIVO + PATRIMONIO',
                number_format($data['totales']['pasivo'] + $data['totales']['patrimonio'], 2, ',', '.')], ';');

            fputcsv($out, ['', 'CUADRA', $data['cuadra'] ? 'SÍ' : 'NO'], ';');
        });
    }

    /**
     * Helper: emite CSV con headers y llama al callback con el handler abierto.
     */
    private function streamCsv(string $filename, callable $writer): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM UTF-8
        $writer($out);
        fclose($out);
        exit;
    }

    /* ==========================================================
       AYUDA
       ========================================================== */
    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'contabilidad.php';
    }
}