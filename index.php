<?php
/* ============================================================
   KROWN SPIRITS — Front Controller / Enrutador principal
   ============================================================ */

session_start();
ob_start();

define('DS', DIRECTORY_SEPARATOR);
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . DS);
}

$configFile = ROOT_PATH . 'config' . DS . 'conexion.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('Error crítico: no se encontró config/conexion.php');
}
require_once $configFile;

spl_autoload_register(function (string $class): void {
    $candidates = [
        ROOT_PATH . 'models'      . DS . $class . '.php',
        ROOT_PATH . 'includes'    . DS . $class . '.php',
        ROOT_PATH . 'controllers' . DS . $class . '.php',
    ];
    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/* ------------------------------------------------------------
   Parseo de la URL
   ------------------------------------------------------------ */
$url = isset($_GET['url']) ? trim($_GET['url'], '/') : '';
$url = filter_var($url, FILTER_SANITIZE_URL);
$routeKey = strtolower($url);

/* ------------------------------------------------------------
   Mapa de rutas "amigables" → [Controlador, Acción]
   ------------------------------------------------------------ */
$routes = [
    ''                  => ['AuthController', 'login'],
    'login'             => ['AuthController', 'login'],
    'logout'            => ['AuthController', 'logout'],
    'forgot'            => ['AuthController', 'forgot'],
    'reset'             => ['AuthController', 'reset'],

    'dashboard'         => ['DashboardController', 'index'],
    // Usuarios (solo admin/developer — verificado en el controller)
    'usuarios'           => ['UsuariosController', 'index'],
    'usuarios/buscar'    => ['UsuariosController', 'buscar'],
    'usuarios/exportar'  => ['UsuariosController', 'exportar'],  
    'usuarios/imprimir'  => ['UsuariosController', 'imprimir'],
    'usuarios/pdf'       => ['UsuariosController', 'pdf'], 
    'usuarios/crear'     => ['UsuariosController', 'crear'],
    'usuarios/editar'    => ['UsuariosController', 'editar'],
    'usuarios/eliminar'  => ['UsuariosController', 'eliminar'],

    // Perfil (todos los roles)
    'perfil'             => ['PerfilController', 'index'],

    // Clientes (Admin/Dev y Vendedor — controlado en el controller)
    'clientes'           => ['ClientesController', 'index'],
    'clientes/buscar'    => ['ClientesController', 'buscar'],
    'clientes/crear'     => ['ClientesController', 'crear'],
    'clientes/editar'    => ['ClientesController', 'editar'],
    'clientes/eliminar'  => ['ClientesController', 'eliminar'],
    'clientes/exportar'  => ['ClientesController', 'exportar'],   
    'clientes/imprimir'  => ['ClientesController', 'imprimir'],  
    'clientes/pdf'       => ['ClientesController', 'pdf'],   
    'clientes/arca'      => ['ClientesController', 'arca'],

    // Proveedores (solo Admin/Dev)
    'proveedores'            => ['ProveedoresController', 'index'],
    'proveedores/buscar'     => ['ProveedoresController', 'buscar'],
    'proveedores/crear'      => ['ProveedoresController', 'crear'],
    'proveedores/editar'     => ['ProveedoresController', 'editar'],
    'proveedores/eliminar'   => ['ProveedoresController', 'eliminar'],
    'proveedores/arca'       => ['ProveedoresController', 'arca'],
    'proveedores/exportar'   => ['ProveedoresController', 'exportar'],
    'proveedores/imprimir'   => ['ProveedoresController', 'imprimir'],
    'proveedores/pdf'        => ['ProveedoresController', 'pdf'],

    // Insumos (Admin/Dev: total · Productor: solo lectura · Vendedor: sin acceso)
    'insumos'                    => ['InsumosController', 'index'],
    'insumos/buscar'             => ['InsumosController', 'buscar'],
    'insumos/crear'              => ['InsumosController', 'crear'],
    'insumos/editar'             => ['InsumosController', 'editar'],
    'insumos/eliminar'           => ['InsumosController', 'eliminar'],
    'insumos/proveedor/agregar'  => ['InsumosController', 'agregarProveedor'],
    'insumos/proveedor/actualizar' => ['InsumosController', 'actualizarProveedor'],
    'insumos/proveedor/eliminar' => ['InsumosController', 'eliminarProveedor'],
    'insumos/exportar'           => ['InsumosController', 'exportar'],
    'insumos/imprimir'           => ['InsumosController', 'imprimir'],
    'insumos/pdf'                => ['InsumosController', 'pdf'],
    'insumos/historial'          => ['InsumosController', 'historial'],
    'insumos/historial/anular'   => ['InsumosController', 'anularHistorial'],
    'insumos/ayuda'              => ['InsumosController', 'ayuda'],

    // Artículos (solo Admin/Dev)
    'articulos'                       => ['ArticulosController', 'index'],
    'articulos/buscar'                => ['ArticulosController', 'buscar'],
    'articulos/crear'                 => ['ArticulosController', 'crear'],
    'articulos/editar'                => ['ArticulosController', 'editar'],
    'articulos/eliminar'              => ['ArticulosController', 'eliminar'],
    'articulos/formula/agregar'       => ['ArticulosController', 'agregarInsumo'],
    'articulos/formula/actualizar'    => ['ArticulosController', 'actualizarInsumo'],
    'articulos/formula/eliminar'      => ['ArticulosController', 'eliminarInsumo'],
    'articulos/precio/guardar'        => ['ArticulosController', 'guardarPrecio'],
    'articulos/imagen/subir'          => ['ArticulosController', 'subirImagen'],
    'articulos/imagen/eliminar'       => ['ArticulosController', 'eliminarImagen'],
    'articulos/historial'             => ['ArticulosController', 'historial'],
    'articulos/historial/anular'      => ['ArticulosController', 'anularHistorial'],
    'articulos/exportar'              => ['ArticulosController', 'exportar'],
    'articulos/imprimir'              => ['ArticulosController', 'imprimir'],
    'articulos/pdf'                   => ['ArticulosController', 'pdf'],
    'articulos/ayuda'                 => ['ArticulosController', 'ayuda'],

    // Comprobantes de compra (solo Admin/Dev)
    'comprobantes'                     => ['ComprasController', 'index'],
    'comprobantes/buscar'              => ['ComprasController', 'buscar'],
    'comprobantes/crear'               => ['ComprasController', 'crear'],
    'comprobantes/ver'                 => ['ComprasController', 'ver'],
    'comprobantes/anular'              => ['ComprasController', 'anular'],
    'comprobantes/arca/constatar'      => ['ComprasController', 'arcaConstatar'],
    'comprobantes/subir-archivo'       => ['ComprasController', 'subirArchivo'],
    'comprobantes/eliminar-archivo'    => ['ComprasController', 'eliminarArchivo'],
    'comprobantes/exportar'            => ['ComprasController', 'exportar'],
    'comprobantes/imprimir'            => ['ComprasController', 'imprimir'],
    'comprobantes/pdf'                 => ['ComprasController', 'pdf'],
    'comprobantes/ayuda'               => ['ComprasController', 'ayuda'],
    'comprobantes/editar'              => ['ComprasController', 'editar'],

        // Pedidos (Admin/Dev · Vendedor: sus clientes · Productor: sin precios)
    'pedidos'                    => ['PedidosController', 'index'],
    'pedidos/buscar'             => ['PedidosController', 'buscar'],
    'pedidos/crear'              => ['PedidosController', 'crear'],
    'pedidos/editar'             => ['PedidosController', 'editar'],
    'pedidos/ver'                => ['PedidosController', 'ver'],
    'pedidos/anular'             => ['PedidosController', 'anular'],
    'pedidos/cambiar-estado'     => ['PedidosController', 'cambiarEstado'],
    'pedidos/precio-articulo'    => ['PedidosController', 'precioArticulo'],
    'pedidos/exportar'           => ['PedidosController', 'exportar'],
    'pedidos/imprimir'           => ['PedidosController', 'imprimir'],
    'pedidos/ayuda'              => ['PedidosController', 'ayuda'],

        // Facturación (solo Admin/Dev)
    'facturacion'                     => ['FacturasController', 'index'],
    'facturas'                        => ['FacturasController', 'index'],
    'facturas/buscar-pedidos'         => ['FacturasController', 'buscarPedidos'],
    'facturas/buscar'                 => ['FacturasController', 'buscarFacturas'],
    'facturas/crear'                  => ['FacturasController', 'crear'],
    'facturas/ver'                    => ['FacturasController', 'ver'],
    'facturas/enviar-arca'            => ['FacturasController', 'enviarArca'],
    'facturas/anular'                 => ['FacturasController', 'anular'],
    'facturas/imprimir'               => ['FacturasController', 'imprimirFactura'],
    'facturas/remito-con-precio'      => ['FacturasController', 'remitoConPrecio'],
    'facturas/remito-sin-precio'      => ['FacturasController', 'remitoSinPrecio'],
    'facturas/exportar'               => ['FacturasController', 'exportar'],
    'facturas/ayuda'                  => ['FacturasController', 'ayuda'],

        // Configuración (solo Admin/Dev)
    'configuracion'                     => ['ConfiguracionController', 'index'],
    'configuracion/guardar-parametros'  => ['ConfiguracionController', 'guardarParametros'],
    'configuracion/emisores'            => ['ConfiguracionController', 'emisoresListar'],
    'configuracion/emisor-crear'        => ['ConfiguracionController', 'emisorCrear'],
    'configuracion/emisor-editar'       => ['ConfiguracionController', 'emisorEditar'],
    'configuracion/emisor-estado'       => ['ConfiguracionController', 'emisorCambiarEstado'],
    'configuracion/ayuda'               => ['ConfiguracionController', 'ayuda'],

        // Calendario de cheques
    'calendario'            => ['CalendarioChequesController', 'index'],
    'calendario/listar'     => ['CalendarioChequesController', 'listar'],
    'calendario/listar-fecha' => ['CalendarioChequesController', 'listarPorFecha'],
    'calendario/crear'      => ['CalendarioChequesController', 'crear'],
    'calendario/actualizar' => ['CalendarioChequesController', 'actualizar'],
    'calendario/eliminar'   => ['CalendarioChequesController', 'eliminar'],
    'calendario/ayuda'      => ['CalendarioChequesController', 'ayuda'],

        // Cobros
    'cobros'                       => ['CobrosController', 'index'],
    'cobros/buscar'                => ['CobrosController', 'buscar'],
    'cobros/ver'                   => ['CobrosController', 'ver'],
    'cobros/crear'                 => ['CobrosController', 'crear'],
    'cobros/anular'                => ['CobrosController', 'anular'],
    'cobros/facturas-pendientes'   => ['CobrosController', 'facturasPendientes'],
    'cobros/exportar'              => ['CobrosController', 'exportar'],
    'cobros/ayuda'                 => ['CobrosController', 'ayuda'],

        // Lista de Precios
    'lista-precios'                       => ['ListaPreciosController', 'index'],
    'lista-precios/guardar'               => ['ListaPreciosController', 'guardar'],
    'lista-precios/pdf'                   => ['ListaPreciosController', 'generarPdf'],
    'lista-precios/clientes-por-categoria'=> ['ListaPreciosController', 'clientesPorCategoria'],
    'lista-precios/enviar'                => ['ListaPreciosController', 'enviar'],
    'lista-precios/ayuda'                 => ['ListaPreciosController', 'ayuda'],

        // Pagos a proveedores (solo Admin/Dev)
    'pagos'                      => ['PagosController', 'index'],
    'pagos/buscar'               => ['PagosController', 'buscar'],
    'pagos/ver'                  => ['PagosController', 'ver'],
    'pagos/crear'                => ['PagosController', 'crear'],
    'pagos/anular'               => ['PagosController', 'anular'],
    'pagos/compras-pendientes'   => ['PagosController', 'comprasPendientes'],
    'pagos/exportar'             => ['PagosController', 'exportar'],
    'pagos/ayuda'                => ['PagosController', 'ayuda'],
    'pagos/buscar-compras'       => ['PagosController', 'buscarCompras'],

        // ============================================================
    // CONTABILIDAD (solo Admin/Dev)
    // ============================================================
    'contabilidad'                     => ['ContabilidadController', 'index'],
    'contabilidad/ejercicios'          => ['ContabilidadController', 'ejercicios'],
    'contabilidad/ejercicio-form'      => ['ContabilidadController', 'ejercicioForm'],
    'contabilidad/ejercicio-guardar'   => ['ContabilidadController', 'ejercicioGuardar'],
    'contabilidad/ejercicio-cerrar'    => ['ContabilidadController', 'ejercicioCerrar'],
    'contabilidad/ejercicio-reabrir'   => ['ContabilidadController', 'ejercicioReabrir'],
    'contabilidad/ejercicio-eliminar'  => ['ContabilidadController', 'ejercicioEliminar'],
    'contabilidad/plan-cuentas'        => ['ContabilidadController', 'planCuentas'],
    'contabilidad/cuenta-form'         => ['ContabilidadController', 'cuentaForm'],
    'contabilidad/cuenta-guardar'      => ['ContabilidadController', 'cuentaGuardar'],
    'contabilidad/cuenta-estado'       => ['ContabilidadController', 'cuentaEstado'],
    'contabilidad/asientos'            => ['ContabilidadController', 'asientos'],
    'contabilidad/asiento-form'        => ['ContabilidadController', 'asientoForm'],
    'contabilidad/asiento-ver'         => ['ContabilidadController', 'asientoVer'],
    'contabilidad/asiento-guardar'     => ['ContabilidadController', 'asientoGuardar'],
    'contabilidad/asiento-confirmar'   => ['ContabilidadController', 'asientoConfirmar'],
    'contabilidad/asiento-anular'      => ['ContabilidadController', 'asientoAnular'],
    'contabilidad/asiento-eliminar'    => ['ContabilidadController', 'asientoEliminar'],
    'contabilidad/libro-diario'        => ['ContabilidadController', 'libroDiario'],
    'contabilidad/libro-mayor'         => ['ContabilidadController', 'libroMayor'],
    'contabilidad/balance'             => ['ContabilidadController', 'balance'],
    'contabilidad/ayuda'               => ['ContabilidadController', 'ayuda'],
    'contabilidad/configuracion'        => ['ContabilidadController', 'configuracion'],
    'contabilidad/configuracion-guardar'=> ['ContabilidadController', 'guardarConfiguracion'],
    'contabilidad/libro-iva-compras'      => ['ContabilidadController', 'libroIvaCompras'],
    'contabilidad/libro-iva-ventas'       => ['ContabilidadController', 'libroIvaVentas'],
    'contabilidad/libro-iva-compras-csv'  => ['ContabilidadController', 'libroIvaComprasCsv'],
    'contabilidad/libro-iva-ventas-csv'   => ['ContabilidadController', 'libroIvaVentasCsv'],
    'contabilidad/estado-resultados'      => ['ContabilidadController', 'estadoResultados'],
    'contabilidad/balance-general'        => ['ContabilidadController', 'balanceGeneral'],
    'contabilidad/estado-resultados-csv'  => ['ContabilidadController', 'estadoResultadosCsv'],
    'contabilidad/balance-general-csv'    => ['ContabilidadController', 'balanceGeneralCsv'],
];


/* ------------------------------------------------------------
   Middleware de autenticación
   ------------------------------------------------------------ */
$publicRoutes = ['', 'login', 'forgot', 'reset'];

if (!in_array($routeKey, $publicRoutes, true) && empty($_SESSION['user_id'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Recargue la página.']);
    } else {
        header('Location: ' . BASE_URL . 'login');
    }
    exit;
}

/* ------------------------------------------------------------
   Resolución de controlador / acción
   ------------------------------------------------------------ */
$controllerName = 'AuthController';
$actionName     = 'login';
$params         = [];

if (isset($routes[$routeKey])) {
    [$controllerName, $actionName] = $routes[$routeKey];
} elseif ($url !== '') {
    $parts          = explode('/', $url);
    $controllerName = ucfirst($parts[0]) . 'Controller';
    $actionName     = !empty($parts[1]) ? $parts[1] : 'index';
    $params         = array_slice($parts, 2);

    if (!file_exists(ROOT_PATH . 'controllers' . DS . $controllerName . '.php')) {
        $base = str_replace('Controller', '', $controllerName);
        foreach ([$base . '.php', strtolower($base) . '.php'] as $try) {
            if (file_exists(ROOT_PATH . 'controllers' . DS . $try)) {
                $controllerName = str_replace('.php', '', $try);
                break;
            }
        }
    }
}

/* ------------------------------------------------------------
   Ejecución
   ------------------------------------------------------------ */
$controllerFile = ROOT_PATH . 'controllers' . DS . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    http_response_code(404);
    echo "Error 404: No se encontró el controlador '{$controllerName}'.";
    ob_end_flush();
    exit;
}

require_once $controllerFile;

$className = $controllerName;
if (!class_exists($className)) {
    $alt = str_replace('Controller', '', $controllerName);
    if (class_exists($alt)) {
        $className = $alt;
    }
}

if (!class_exists($className)) {
    http_response_code(500);
    echo "Error 500: La clase '{$controllerName}' no está definida.";
    ob_end_flush();
    exit;
}

$controller = new $className();

if (!method_exists($controller, $actionName)) {
    http_response_code(404);
    echo "Error 404: El método '{$actionName}()' no existe en '{$className}'.";
    ob_end_flush();
    exit;
}

call_user_func_array([$controller, $actionName], $params);

ob_end_flush();