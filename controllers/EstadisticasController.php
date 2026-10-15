<?php
/**
 * controllers/EstadisticasController.php
 * Solo Admin/Dev.
 */

class EstadisticasController
{
    private Estadisticas $stats;

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->stats = new Estadisticas();
    }

    /* ==========================================================
       INDEX
       ========================================================== */
    public function index(): void
    {
        $anios = $this->stats->anosDisponibles();
        $anioActual = (int)date('Y');

        // Si el año actual no está en la lista (no facturó aún este año),
        // lo agregamos al frente para el selector
        if (!in_array($anioActual, $anios, true)) {
            array_unshift($anios, $anioActual);
        }

        $page_title     = 'Estadísticas';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'estadisticas.js';
        $pageStyle      = 'estadisticas.css';

        require_once ROOT_PATH . 'views' . DS . 'estadisticas.php';
    }

    /* ==========================================================
       AJAX: FACTURACIÓN POR EMISOR
       ========================================================== */
    public function facturacionEmisor(): void
    {
        $this->jsonHeader();
        [$desde, $hasta] = $this->parsearRango();

        $data = $this->stats->facturacionPorEmisor($desde, $hasta);

        $labels = [];
        $series = [];
        $facturas = [];
        $total = 0;

        foreach ($data as $d) {
            $labels[]   = $d['nombre'];
            $monto = (float)$d['total_facturado'];
            $series[]   = $monto;
            $facturas[] = (int)$d['cantidad_facturas'];
            $total += $monto;
        }

        jsonResponse([
            'ok'       => true,
            'labels'   => $labels,
            'series'   => $series,
            'facturas' => $facturas,
            'total'    => $total,
        ]);
    }

    /* ==========================================================
       AJAX: VENTAS MES vs MES
       ========================================================== */
    public function ventasMes(): void
    {
        $this->jsonHeader();

        $aniosRaw = $_POST['anios'] ?? $_GET['anios'] ?? '';
        $anios = array_filter(array_map('intval', explode(',', (string)$aniosRaw)));

        // Si no pasaron años, usar los últimos 3
        if (empty($anios)) {
            $disponibles = $this->stats->anosDisponibles();
            $anios = array_slice($disponibles, 0, 3);
            if (empty($anios)) $anios = [(int)date('Y')];
        }

        $data = $this->stats->ventasMesesCajas($anios);

        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
        $series = [];
        foreach ($data as $anio => $valores) {
            $fila = [];
            for ($m = 1; $m <= 12; $m++) {
                $fila[] = $valores[$m] ?? 0;
            }
            $series[] = ['name' => (string)$anio, 'data' => $fila];
        }

        jsonResponse([
            'ok'     => true,
            'labels' => $meses,
            'series' => $series,
        ]);
    }

    /* ==========================================================
       AJAX: FORMATOS
       ========================================================== */
    public function formatos(): void
    {
        $this->jsonHeader();
        [$desde, $hasta] = $this->parsearRango();

        $data = $this->stats->formatosCajas($desde, $hasta);

        $labels = [];
        $cajas  = [];
        $unidades = [];
        $totalCajas = 0;
        $totalUnidades = 0;

        foreach ($data as $d) {
            $labels[]   = $d['formato'] . ' cc';
            $cajas[]    = $d['cajas'];
            $unidades[] = $d['unidades'];
            $totalCajas += $d['cajas'];
            $totalUnidades += $d['unidades'];
        }

        // Calcular % de cada formato
        $pct = [];
        foreach ($cajas as $c) {
            $pct[] = $totalCajas > 0 ? round(($c / $totalCajas) * 100, 1) : 0;
        }

        jsonResponse([
            'ok'             => true,
            'labels'         => $labels,
            'cajas'          => $cajas,
            'unidades'       => $unidades,
            'pct'            => $pct,
            'total_cajas'    => $totalCajas,
            'total_unidades' => $totalUnidades,
        ]);
    }

    /* ==========================================================
       AJAX: CAJAS POR CLIENTE
       ========================================================== */
    public function clientes(): void
    {
        $this->jsonHeader();
        [$desde, $hasta] = $this->parsearRango();

        $limite = (int)($_POST['limite'] ?? $_GET['limite'] ?? 20);
        if ($limite < 5)  $limite = 5;
        if ($limite > 50) $limite = 50;

        $data = $this->stats->cajasPorCliente($desde, $hasta, $limite);

        $labels = [];
        $cajas  = [];
        $monto  = [];

        foreach ($data as $d) {
            $nombre = $d['nombre_fantasia'] ?: $d['razon_social'];
            $labels[] = $nombre;
            $cajas[]  = $d['cajas'];
            $monto[]  = $d['total_facturado'];
        }

        jsonResponse([
            'ok'     => true,
            'labels' => $labels,
            'cajas'  => $cajas,
            'monto'  => $monto,
        ]);
    }

    /* ==========================================================
       AJAX: SHARE SABORES
       ========================================================== */
    public function sabores(): void
    {
        $this->jsonHeader();
        [$desde, $hasta] = $this->parsearRango();

        $formato = trim($_POST['formato'] ?? $_GET['formato'] ?? '');

        $res = $this->stats->shareSabores($desde, $hasta, $formato ?: null);

        $labels = [];
        $series = [];
        foreach ($res['items'] as $i) {
            $labels[] = $i['sabor'];
            $series[] = $i['unidades'];
        }

        jsonResponse([
            'ok'     => true,
            'labels' => $labels,
            'series' => $series,
            'total'  => $res['total'],
            'items'  => $res['items'],
        ]);
    }

    /* ==========================================================
       AJAX: FORMATOS POR SABOR (stacked)
       ========================================================== */
    public function formatosPorSabor(): void
    {
        $this->jsonHeader();
        [$desde, $hasta] = $this->parsearRango();

        $rows = $this->stats->formatosPorSabor($desde, $hasta);

        // Pivotear: { sabor => { formato => unidades } }
        $sabores = [];
        $formatosSet = [];
        foreach ($rows as $r) {
            $sabor = $r['sabor'];
            $formato = $r['formato'];
            $unidades = (float)$r['unidades'];

            if (!isset($sabores[$sabor])) {
                $sabores[$sabor] = [];
            }
            $sabores[$sabor][$formato] = $unidades;
            $formatosSet[$formato] = true;
        }

        // Ordenar formatos asc
        $formatos = array_keys($formatosSet);
        sort($formatos, SORT_NUMERIC);

        // Armar series de ApexCharts (una serie por formato)
        $labels = array_keys($sabores);
        sort($labels);

        $series = [];
        foreach ($formatos as $fmt) {
            $data = [];
            foreach ($labels as $sabor) {
                $data[] = $sabores[$sabor][$fmt] ?? 0;
            }
            $series[] = ['name' => $fmt . ' cc', 'data' => $data];
        }

        jsonResponse([
            'ok'       => true,
            'labels'   => $labels,
            'series'   => $series,
            'formatos' => $formatos,
        ]);
    }

    /* ==========================================================
       AYUDA
       ========================================================== */
    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'estadisticas.php';
    }

    /* ==========================================================
       HELPERS PRIVADOS
       ========================================================== */
    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Lee desde/hasta del POST o GET.
     * Default: desde 1 enero del año actual, hasta hoy.
     */
    private function parsearRango(): array
    {
        $desde = trim($_POST['desde'] ?? $_GET['desde'] ?? '');
        $hasta = trim($_POST['hasta'] ?? $_GET['hasta'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = date('Y-01-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = date('Y-m-d');
        }

        return [$desde, $hasta];
    }
}