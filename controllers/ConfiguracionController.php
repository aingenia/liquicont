<?php
/**
 * controllers/ConfiguracionController.php
 * Solo Admin/Dev.
 */

class ConfiguracionController
{
    private ConfiguracionApp $config;
    private Emisor $emisor;

    /**
     * Estructura declarativa de los parámetros del sistema.
     * La vista la recorre para generar los inputs.
     */
    private array $categorias = [
        'empresa' => [
            'titulo'      => 'Datos de la empresa',
            'descripcion' => 'Información fiscal del emisor principal. Se imprime en remitos, listas de precios y emails.',
            'campos' => [
                'empresa_razon_social'             => ['label' => 'Razón social',              'tipo' => 'text',  'obligatorio' => true],
                'empresa_nombre_fantasia'          => ['label' => 'Nombre de fantasía',        'tipo' => 'text'],
                'empresa_cuit'                     => ['label' => 'CUIT',                      'tipo' => 'text',  'obligatorio' => true, 'help' => 'Con guiones, ej: 30-71816263-3'],
                'empresa_condicion_iva'            => ['label' => 'Condición frente al IVA',   'tipo' => 'select', 'opciones' => ['Responsable Inscripto','Monotributista','Exento','No alcanzado']],
                'empresa_ingresos_brutos'          => ['label' => 'Ingresos Brutos',           'tipo' => 'text'],
                'empresa_fecha_inicio_actividades' => ['label' => 'Fecha de inicio actividades','tipo' => 'text', 'help' => 'Formato dd/mm/aaaa'],
                'empresa_domicilio'                => ['label' => 'Domicilio completo',        'tipo' => 'text',  'obligatorio' => true],
                'empresa_telefono'                 => ['label' => 'Teléfono',                  'tipo' => 'text'],
                'empresa_email'                    => ['label' => 'Email',                     'tipo' => 'email'],
                'empresa_sitio_web'                => ['label' => 'Sitio web',                 'tipo' => 'text'],
            ],
        ],
        'lista_precios' => [
            'titulo'      => 'Lista de precios',
            'descripcion' => 'Datos de la lista de precios vigente que se imprime y se envía por email.',
            'campos' => [
                'lista_precios_numero'         => ['label' => 'Número de lista',      'tipo' => 'text', 'help' => 'Se actualiza manualmente cada vez que cambian los precios'],
                'lista_precios_vigente_desde'  => ['label' => 'Vigente desde',        'tipo' => 'date'],
            ],
        ],
        'impuestos' => [
            'titulo'      => 'Impuestos y alícuotas',
            'descripcion' => 'Porcentajes de impuestos no recuperables que se suman al costo de los insumos.',
            'campos' => [
                'impuesto_bebidas_alcoholicas' => ['label' => 'Impuesto a bebidas alcohólicas (%)', 'tipo' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100, 'help' => 'Se usa como default en nuevos insumos alcohólicos'],
            ],
        ],
        'fason' => [
            'titulo'      => 'Fasón',
            'descripcion' => 'Parámetros por defecto del servicio de fraccionamiento.',
            'campos' => [
                'fason_franquicia_litros' => ['label' => 'Franquicia de litros', 'tipo' => 'number', 'step' => '0.01', 'min' => 0, 'help' => 'Litros que se descuentan del total antes de facturar'],
            ],
        ],
        'arca' => [
            'titulo'      => 'Parámetros ARCA (globales)',
            'descripcion' => 'Valores por defecto de facturación electrónica. Cada emisor puede sobrescribirlos desde su propia configuración.',
            'campos' => [
                'arca_concepto'               => ['label' => 'Concepto', 'tipo' => 'select', 'opciones' => [
                    '1' => '1 = Productos',
                    '2' => '2 = Servicios',
                    '3' => '3 = Productos y Servicios',
                ], 'help' => 'Default para nuevos emisores'],
                'arca_iva_id'                 => ['label' => 'Código de alícuota IVA', 'tipo' => 'select', 'opciones' => [
                    '3' => '3 = 0%',
                    '4' => '4 = 10.5%',
                    '5' => '5 = 21%',
                    '6' => '6 = 27%',
                ]],
            ],
        ],
    ];

    public function __construct()
    {
        if (!esAdmin()) {
            if (esPeticionAjax()) {
                jsonResponse(['ok' => false, 'msg' => 'No tiene permisos.'], 403);
            }
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        $this->config = new ConfiguracionApp();
        $this->emisor = new Emisor();
    }

    /* ==========================================================
       INDEX
       ========================================================== */
    public function index(): void
    {
        // Cargar todos los valores actuales de la configuración
        $valores = [];
        foreach ($this->categorias as $cat) {
            foreach ($cat['campos'] as $clave => $def) {
                $valores[$clave] = $this->config->obtener($clave, '') ?? '';
            }
        }

        $emisores = $this->emisor->listar(false); // incluye inactivos

        $categorias     = $this->categorias;
        $page_title     = 'Configuración';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript     = 'configuracion.js';
        $pageStyle      = 'configuracion.css';

        require_once ROOT_PATH . 'views' . DS . 'configuracion.php';
    }

    /* ==========================================================
       GUARDAR PARÁMETROS
       ========================================================== */
    public function guardarParametros(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $enviados = $_POST['param'] ?? [];
        if (!is_array($enviados)) {
            jsonResponse(['ok' => false, 'msg' => 'Datos inválidos.']);
        }

        $guardados = 0;
        $errores   = [];

        foreach ($this->categorias as $cat) {
            foreach ($cat['campos'] as $clave => $def) {
                if (!array_key_exists($clave, $enviados)) continue;

                $valor = trim((string)$enviados[$clave]);

                // Validar obligatorios
                if (!empty($def['obligatorio']) && $valor === '') {
                    $errores[] = 'El campo "' . $def['label'] . '" es obligatorio.';
                    continue;
                }

                // Validar tipo
                if ($valor !== '' && ($def['tipo'] ?? '') === 'number' && !is_numeric($valor)) {
                    $errores[] = 'El campo "' . $def['label'] . '" debe ser numérico.';
                    continue;
                }

                if ($this->config->guardar($clave, $valor)) {
                    $guardados++;
                } else {
                    $errores[] = 'No se pudo guardar "' . $def['label'] . '".';
                }
            }
        }

        if (!empty($errores)) {
            jsonResponse(['ok' => false, 'msg' => implode(' ', $errores)]);
        }

        jsonResponse([
            'ok'  => true,
            'msg' => 'Configuración guardada (' . $guardados . ' parámetro' . ($guardados === 1 ? '' : 's') . ' actualizado' . ($guardados === 1 ? '' : 's') . ').',
        ]);
    }

    /* ==========================================================
       EMISORES — listado
       ========================================================== */
    public function emisoresListar(): void
    {
        $emisores = $this->emisor->listar(false);

        // Agregar flag de "tiene facturas" para saber si se puede eliminar
        foreach ($emisores as &$e) {
            $e['tiene_facturas'] = $this->emisor->tieneFacturas((int)$e['id']);
        }
        unset($e);

        jsonResponse(['ok' => true, 'emisores' => $emisores]);
    }

    /* ==========================================================
       EMISOR — CREAR
       ========================================================== */
    public function emisorCrear(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $d = $this->armarDatosEmisor();
            if ($d === null) {
                return; // ya respondió con error
            }

            $id = $this->emisor->crear($d);
            if ($id === false) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo crear el emisor.']);
            }

            jsonResponse([
                'ok'       => true,
                'msg'      => 'Emisor creado con éxito.',
                'redirect' => BASE_URL . 'configuracion?tab=emisores',
            ]);
        }

        $emisor     = null;
        $modo       = 'crear';
        $page_title = 'Nuevo emisor';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript = 'configuracion.js';
        $pageStyle  = 'configuracion.css';

        require_once ROOT_PATH . 'views' . DS . 'configuracion_emisor_form.php';
    }

    /* ==========================================================
       EMISOR — EDITAR
       ========================================================== */
    public function emisorEditar(): void
    {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . 'configuracion?tab=emisores');
            exit;
        }

        $emisor = $this->emisor->obtenerPorId($id);
        if (!$emisor) {
            header('Location: ' . BASE_URL . 'configuracion?tab=emisores');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $d = $this->armarDatosEmisor();
            if ($d === null) return;

            if (!$this->emisor->actualizar($id, $d)) {
                jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar el emisor.']);
            }

            jsonResponse([
                'ok'       => true,
                'msg'      => 'Emisor actualizado con éxito.',
                'redirect' => BASE_URL . 'configuracion?tab=emisores',
            ]);
        }

        $modo       = 'editar';
        $page_title = 'Editar emisor';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');
        $pageScript = 'configuracion.js';
        $pageStyle  = 'configuracion.css';

        require_once ROOT_PATH . 'views' . DS . 'configuracion_emisor_form.php';
    }

    /* ==========================================================
       EMISOR — ACTIVAR / DESACTIVAR
       ========================================================== */
    public function emisorCambiarEstado(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'msg' => 'Método no permitido.'], 405);
        }

        $id     = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);

        if ($id <= 0) {
            jsonResponse(['ok' => false, 'msg' => 'ID inválido.']);
        }

        // No dejar el sistema sin emisores activos
        if ($activo === 0) {
            $activos = array_filter($this->emisor->listar(), fn($e) => (int)$e['id'] !== $id);
            if (empty($activos)) {
                jsonResponse(['ok' => false, 'msg' => 'No podés desactivar el último emisor activo.']);
            }
        }

        if (!$this->emisor->cambiarActivo($id, $activo === 1)) {
            jsonResponse(['ok' => false, 'msg' => 'No se pudo actualizar el estado.']);
        }

        jsonResponse([
            'ok'  => true,
            'msg' => $activo === 1 ? 'Emisor activado.' : 'Emisor desactivado.',
        ]);
    }

    /* ==========================================================
       HELPER — armar datos del emisor desde $_POST
       ========================================================== */
    private function armarDatosEmisor(): ?array
    {
        $d = [
            'nombre'                    => trim($_POST['nombre'] ?? ''),
            'razon_social'              => trim($_POST['razon_social'] ?? ''),
            'nombre_fantasia'           => trim($_POST['nombre_fantasia'] ?? ''),
            'cuit'                      => trim($_POST['cuit'] ?? ''),
            'condicion_iva'             => trim($_POST['condicion_iva'] ?? ''),
            'ingresos_brutos'           => trim($_POST['ingresos_brutos'] ?? ''),
            'fecha_inicio_actividades'  => trim($_POST['fecha_inicio_actividades'] ?? ''),
            'domicilio'                 => trim($_POST['domicilio'] ?? ''),
            'telefono'                  => trim($_POST['telefono'] ?? ''),
            'email'                     => trim($_POST['email'] ?? ''),
            'sitio_web'                 => trim($_POST['sitio_web'] ?? ''),
            'logo_path'                 => trim($_POST['logo_path'] ?? ''),
            'arca_entorno'              => $_POST['arca_entorno'] ?? 'homologacion',
            'arca_punto_venta'          => (int)($_POST['arca_punto_venta'] ?? 0),
            'arca_tipo_comprobante_letra' => strtoupper(trim($_POST['arca_tipo_comprobante_letra'] ?? '')) ?: null,
            'arca_concepto'             => (int)($_POST['arca_concepto'] ?? 3),
            'arca_iva_id'               => (int)($_POST['arca_iva_id'] ?? 5),
            'cert_path'                 => trim($_POST['cert_path'] ?? ''),
            'key_path'                  => trim($_POST['key_path'] ?? ''),
            'key_passphrase'            => trim($_POST['key_passphrase'] ?? ''),
            'ta_cache_dir'              => trim($_POST['ta_cache_dir'] ?? ''),
            'remito_prefijo'            => trim($_POST['remito_prefijo'] ?? ''),
            'remito_punto_venta'        => (int)($_POST['remito_punto_venta'] ?? 1),
            'activo'                    => isset($_POST['activo']) ? 1 : 0,
        ];

        // Validaciones
        if ($d['nombre'] === '' || $d['razon_social'] === '' || $d['cuit'] === '' || $d['condicion_iva'] === '' || $d['domicilio'] === '') {
            jsonResponse(['ok' => false, 'msg' => 'Nombre, razón social, CUIT, condición IVA y domicilio son obligatorios.']);
        }
        if (strlen(preg_replace('/\D/', '', $d['cuit'])) !== 11) {
            jsonResponse(['ok' => false, 'msg' => 'El CUIT debe tener 11 dígitos.']);
        }
        if (!in_array($d['arca_entorno'], ['homologacion', 'produccion'], true)) {
            $d['arca_entorno'] = 'homologacion';
        }
        if ($d['arca_tipo_comprobante_letra'] !== null && !in_array($d['arca_tipo_comprobante_letra'], ['A', 'B', 'C'], true)) {
            jsonResponse(['ok' => false, 'msg' => 'La letra de comprobante debe ser A, B o C.']);
        }

        // Rutas de certificados: si vienen vacías, autogenerar según convención
        if ($d['cert_path'] === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $d['nombre']));
            $d['cert_path'] = 'config/arca/' . $slug . '/cert.crt';
        }
        if ($d['key_path'] === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $d['nombre']));
            $d['key_path'] = 'config/arca/' . $slug . '/private.key';
        }
        if ($d['ta_cache_dir'] === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $d['nombre']));
            $d['ta_cache_dir'] = 'config/arca/' . $slug . '/ta_cache';
        }

        return $d;
    }

    /* ==========================================================
       AYUDA
       ========================================================== */
    public function ayuda(): void
    {
        require ROOT_PATH . 'views' . DS . 'ayuda' . DS . 'configuracion.php';
    }
}