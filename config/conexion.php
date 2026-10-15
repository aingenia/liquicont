<?php
/* ============================================================
   KROWN SPIRITS — Sistema de gestión
    ============================================================ */

// Previene acceso directo a la configuracion
if (!defined('ROOT_PATH')) {
    define('DS', DIRECTORY_SEPARATOR);
    define('ROOT_PATH', dirname(__DIR__) . DS);
}

// ----------------------------------------------------------------------
// 0. CONFIGURACIÓN LOCAL (no versionada)
// ----------------------------------------------------------------------
// Si existe config/local.php, se carga primero.
// Ahí van credenciales de DB, mail, y valores específicos del entorno.
$localFile = __DIR__ . DS . 'local.php';
if (file_exists($localFile)) {
    require_once $localFile;
}


// ----------------------------------------------------------------------
// 1. ENTORNOS Y CONFIGURACIÓN REGIONAL
// ----------------------------------------------------------------------
define('ENVIRONMENT', 'development'); // 'development' o 'production'

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Zona horaria oficial para Argentina (Requerido para el ciclo de vida de tokens de ARCA)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// ----------------------------------------------------------
// 2. CONSTANTES DE BASE DE DATOS
// ----------------------------------------------------------------------


// Valores por defecto (fallback para desarrollo)
if (!defined('DB_HOST'))    define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))    define('DB_NAME', 'liquicont_dev');
if (!defined('DB_USER'))    define('DB_USER', 'root');
if (!defined('DB_PASS'))    define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ----------------------------------------------------------------------
// 3. RUTAS Y DOMINIO DE LA APLICACIÓN
// ----------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Obtener la carpeta raíz del script de ejecución
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $baseUrl   = rtrim($protocol . $host . $scriptDir, '/') . '/';
    
    define('BASE_URL', $baseUrl);
}

define('APP_NAME', 'LIQUICONT');

// --- Configuración de conexión con ARCA (ex-AFIP) ---
// Se usa desde includes/arca.php para autocompletar datos de proveedores
// por CUIT (WSAA + Consulta a Padrón Alcance 13).
//
// Para que funcione necesitás:
//  1) Un certificado digital X.509 asociado al WS "ws_sr_padron_a13"
//     (se gestiona con WSASS para homologación, o "Administrador de
//     Certificados Digitales" para producción, con tu Clave Fiscal).
//  2) Cargar las rutas al .crt y .key abajo.
// Mientras ARCA_CERT_PATH/ARCA_KEY_PATH no existan, el botón "ARCA" del
// formulario de proveedores va a mostrar un mensaje claro en vez de fallar.
define('ARCA_ENTORNO', 'homologacion'); // 'homologacion' o 'produccion'
define('ARCA_CUIT_REPRESENTADA', '');   // CUIT (sin guiones) de la empresa que consulta
define('ARCA_CERT_PATH', ROOT_PATH . 'config/arca/cert.crt');
define('ARCA_KEY_PATH', ROOT_PATH . 'config/arca/private.key');
define('ARCA_KEY_PASSPHRASE', '');      // si el .key tiene contraseña
define('ARCA_TA_CACHE_PATH', ROOT_PATH . 'config/arca/ta_padron_cache.json');

// ... definiciones de MAIL_HOST, MAIL_USER, etc. ...
// Configuración de PHPMailer (SMTP)
define('MAIL_HOST', '');
define('MAIL_USER', '');
define('MAIL_PASS', '');
define('MAIL_PORT', 465);

// Nombre y remitente visible del email
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_FROM_ADDRESS', MAIL_USER);   // debe coincidir con MAIL_USER en Ferozo
define('MAIL_REPLY_TO', MAIL_USER);

// Ruta a la librería PHPMailer
define('MAIL_LIB_PATH', ROOT_PATH . 'PHPMailer' . DS );

// Modo debug: 0 = silencioso, 2 = muestra todo el diálogo SMTP (solo en dev)
define('MAIL_DEBUG', ENVIRONMENT === 'development' ? 2 : 0);
// ----------------------------------------------------------------------
// 4. HELPERS GLOBALES DE LA APP
// ----------------------------------------------------------------------
if (!function_exists('esDeveloper')) {
    function esDeveloper(): bool {
        return (int)($_SESSION['user_rol'] ?? 0) === 1;
    }
}

if (!function_exists('esAdmin')) {
    function esAdmin(): bool {
        return in_array((int)($_SESSION['user_rol'] ?? 0), [1, 2], true);
    }
}

if (!function_exists('puedeVerInsumos')) {
    function puedeVerInsumos(): bool {
        return esAdmin() || esProductor();
    }
}

if (!function_exists('puedeEditarInsumos')) {
    function puedeEditarInsumos(): bool {
        return esAdmin();
    }
}

if (!function_exists('puedeGestionarUsuarios')) {
    function puedeGestionarUsuarios(): bool {
        return esAdmin();
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $code = 200): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('esPeticionAjax')) {
    function esPeticionAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('esVendedor')) {
    function esVendedor(): bool {
        return (int)($_SESSION['user_rol'] ?? 0) === 3;
    }
}

if (!function_exists('esProductor')) {
    function esProductor(): bool {
        return (int)($_SESSION['user_rol'] ?? 0) === 4;
    }
}

if (!function_exists('puedeGestionarClientes')) {
    function puedeGestionarClientes(): bool {
        return esAdmin() || esVendedor();
    }
}

if (!function_exists('puedeVerTodosLosClientes')) {
    function puedeVerTodosLosClientes(): bool {
        return esAdmin();
    }
}

if (!function_exists('puedeVerCalendario')) {
    function puedeVerCalendario(): bool {
        return esAdmin() || esVendedor() || esProductor();
    }
}

if (!function_exists('puedeEditarCalendario')) {
    function puedeEditarCalendario(): bool {
        return esAdmin() || esVendedor();
    }
}

if (!function_exists('puedeVerCobros')) {
    function puedeVerCobros(): bool {
        return esAdmin() || esVendedor();
    }
}

if (!function_exists('puedeCobrar')) {
    function puedeCobrar(): bool {
        return esAdmin() || esVendedor();
    }
}

if (!function_exists('puedeVerListaPrecios')) {
    function puedeVerListaPrecios(): bool {
        return esAdmin() || esVendedor();
    }
}

if (!function_exists('puedeEditarListaPrecios')) {
    function puedeEditarListaPrecios(): bool {
        return esAdmin();
    }
}

if (!function_exists('puedeVerEstadisticas')) {
    function puedeVerEstadisticas(): bool {
        return esAdmin();
    }
}

if (!function_exists('urlConFiltros')) {
    /**
     * Arma una URL limpia con BASE_URL + ruta + filtros específicos.
     * Ignora el parámetro interno `url` del router.
     *
     * Uso:
     *   urlConFiltros('contabilidad/balance-general-csv', [
     *       'ejercicio_id' => 1,
     *       'comparar_id'  => 2,
     *       'emisor_id'    => 0,   // se ignora si es null/''/0
     *   ])
     */
    function urlConFiltros(string $ruta, array $params = []): string
    {
        // Filtrar vacíos, nulls y ceros
        $params = array_filter($params, function ($v) {
            return $v !== null && $v !== '' && $v !== 0 && $v !== '0';
        });

        $query = http_build_query($params);
        return BASE_URL . $ruta . ($query ? '?' . $query : '');
    }
}

/* ============================================================
   FLASH MESSAGES (una sola lectura)
   ============================================================ */

if (!function_exists('flashSet')) {
    /**
     * Encola un mensaje para mostrarlo en el próximo render.
     * @param string $tipo 'ok' | 'error' | 'info'
     */
    function flashSet(string $tipo, string $mensaje): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }
}

if (!function_exists('flashGet')) {
    /** Devuelve y limpia la cola de mensajes. */
    function flashGet(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return [];
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}

if (!function_exists('flashRender')) {
    /** Renderiza el HTML de los mensajes pendientes. */
    function flashRender(): string
    {
        $f = flashGet();
        if (!$f) return '';
        $html = '';
        foreach ($f as $m) {
            $cls = match ($m['tipo'] ?? 'info') {
                'ok'    => 'msg-ok',
                'error' => 'msg-error',
                default => 'msg-info',
            };
            $html .= '<div class="msg-box ' . $cls . '">'
                   . htmlspecialchars($m['mensaje'])
                   . '</div>';
        }
        return '<div class="flash-stack">' . $html . '</div>';
    }
}

if (!function_exists('generarPdf')) {
    /**
     * Convierte un HTML completo en un PDF descargable.
     *
     * @param string $html       HTML completo (con <html>, <head>, etc.)
     * @param string $filename   Nombre del archivo descargado, sin .pdf
     * @param string $orientation 'portrait' | 'landscape'
     */
    function generarPdf(string $html, string $filename, string $orientation = 'portrait'): void
    {
        $autoload = ROOT_PATH . 'includes' . DS . 'dompdf' . DS . 'autoload.inc.php';
        if (!file_exists($autoload)) {
            http_response_code(500);
            die('Dompdf no está instalado. Ver config/conexion.php.');
        }
        require_once $autoload;

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);   // permite cargar imágenes con URL
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // soporta acentos y ñ
        $options->set('chroot', ROOT_PATH);       // raíz para rutas relativas

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        // Nombre con fecha
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
        $safeName .= '_' . date('Y-m-d_His') . '.pdf';

        $dompdf->stream($safeName, ['Attachment' => true]); // true = descarga
        exit; // ← crítico: evita que index.php agregue nada al output binario
    }

    if (!function_exists('renderDescripcion')) {
    /**
     * Convierte saltos de línea en <br> y escapa el resto.
     * Uso seguro para mostrar descripciones en HTML.
     */
        function renderDescripcion(?string $texto): string
        {
            if ($texto === null || $texto === '') return '';
            return nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));
        }
    }

    if (!function_exists('puedeVerPedidos')) {
        function puedeVerPedidos(): bool {
            return esAdmin() || esVendedor() || esProductor();
        }
    }

    if (!function_exists('estadoPedidoLabel')) {
        function estadoPedidoLabel(string $estado): string {
            return [
                'RECIBIDO'   => 'Recibido',
                'PROCESANDO' => 'Procesando',
                'PREPARADO'  => 'Preparado',
                'FACTURADO'  => 'Facturado',
                'ENTREGADO'  => 'Entregado',
                'ANULADO'    => 'Anulado',
            ][$estado] ?? $estado;
        }
    }

    if (!function_exists('estadoPedidoClase')) {
        function estadoPedidoClase(string $estado): string {
            return [
                'RECIBIDO'   => 'estado-recibido',
                'PROCESANDO' => 'estado-procesando',
                'PREPARADO'  => 'estado-preparado',
                'FACTURADO'  => 'estado-facturado',
                'ENTREGADO'  => 'estado-entregado',
                'ANULADO'    => 'estado-anulado',
            ][$estado] ?? '';
        }
    }
}


// ----------------------------------------------------------------------
// 5. CLASE CONEXIÓN A LA BASE DE DATOS (Patrón Singleton con PDO)
// ----------------------------------------------------------------------
class Conexion
{
    private static ?PDO $instance = null;

    /**
     * Constructor privado para prevenir instanciación directa
     */
    private function __construct() {}

    /**
     * Obtener la instancia única de conexión PDO
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Registrar el error en el log interno del servidor
                error_log("Error de Conexión BD: " . $e->getMessage());

                if (ENVIRONMENT === 'development') {
                    die("Error de conexión a la base de datos: " . $e->getMessage());
                } else {
                    die("Ocurrió un problema de conexión con la base de datos. Por favor, intente más tarde.");
                }
            }
        }

        return self::$instance;
    }

//Previene la clonación del objeto Conexion
    // Al declarar __clone() con visibilidad private, 
    // PHP arrojará un error fatal si alguien intenta duplicar el objeto, 
    // asegurando que solo exista una única conexión activa a MySQL durante todo el ciclo de ejecución de la solicitud.
    private function __clone() {}

    // Evita la deserialización del objeto Conexion.  
    // La función nativa unserialize() de PHP intenta reconstruir un objeto previamente serializado (serialize()),
    //  lo cual crearía una nueva instancia por una vía trasera. 
    // Al lanzar explícitamente una excepción dentro de __wakeup(), se aborta cualquier intento de recrear la clase 
    // fuera del método estático getConnection(). No debo hacer nada, funciona al definirse aquí.

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}