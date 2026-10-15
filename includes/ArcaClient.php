<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/Emisor.php';

/**
 * ArcaClient
 * Integración con los webservices SOAP de ARCA (ex-AFIP):
 *   1) WSAA (Web Service de Autenticación y Autorización)
 *   2) ws_sr_padron_a13 (Consulta a Padrón Alcance 13)
 *   3) wscdc (Constatación de Comprobantes)
 *   4) wsfev1 (Facturación electrónica)
 *
 * MULTI-EMISOR: el constructor puede recibir un $emisorId opcional.
 *   - Con emisorId: carga la config de ESE emisor desde la tabla `emisor`.
 *   - Sin emisorId: usa el primer emisor activo de la tabla (por defecto).
 *   - Si la tabla está vacía o falla: cae a las constantes de conexion.php
 *     (retrocompatibilidad total con el código existente).
 */
class ArcaClient
{
    private string $wsdlWsaa;
    private string $wsdlPadron;
    private string $wsdlCdc;
    private string $wsdlWsfe;

    // Config resuelta del emisor (viene de la BD o de las constantes)
    private int    $emisorId;
    private string $cuitRepresentada;
    private string $entorno;
    private string $certPath;
    private string $keyPath;
    private string $keyPassphrase;
    private string $taCacheDir;

    public function __construct(?int $emisorId = null)
    {
        $emisor = null;

        // 1) Intentar cargar el emisor desde la BD
        try {
            $modelo = new Emisor();
            $emisor = ($emisorId !== null)
                ? $modelo->obtenerPorId($emisorId)
                : $modelo->obtenerPorDefecto();
        } catch (\Throwable $e) {
            error_log('[ArcaClient] No se pudo cargar emisor de BD: ' . $e->getMessage());
        }

        if ($emisor) {
            // 2) Usar la config del emisor de la BD
            $this->emisorId         = (int)$emisor['id'];
            $this->cuitRepresentada = (string)$emisor['cuit_sin_guiones'];
            $this->entorno          = (string)$emisor['arca_entorno'];
            $this->certPath         = ROOT_PATH . str_replace('/', DS, $emisor['cert_path']);
            $this->keyPath          = ROOT_PATH . str_replace('/', DS, $emisor['key_path']);
            $this->keyPassphrase    = (string)($emisor['key_passphrase'] ?? '');
            $this->taCacheDir       = ROOT_PATH . str_replace('/', DS, $emisor['ta_cache_dir']);
        } else {
            // 3) Fallback a constantes globales (retrocompatibilidad)
            $this->emisorId         = 0;
            $this->cuitRepresentada = ARCA_CUIT_REPRESENTADA;
            $this->entorno          = ARCA_ENTORNO;
            $this->certPath         = ARCA_CERT_PATH;
            $this->keyPath          = ARCA_KEY_PATH;
            $this->keyPassphrase    = ARCA_KEY_PASSPHRASE;
            $this->taCacheDir       = dirname(ARCA_TA_CACHE_PATH);
        }

        // WSDLs según entorno
        if ($this->entorno === 'produccion') {
            $this->wsdlWsaa   = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl';
            $this->wsdlPadron = 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13?wsdl';
            $this->wsdlCdc    = 'https://servicios1.arca.gob.ar/WSCDC/service.asmx?WSDL';
            $this->wsdlWsfe   = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';
        } else {
            $this->wsdlWsaa   = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';
            $this->wsdlPadron = 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13?wsdl';
            $this->wsdlCdc    = 'https://wswhomo.afip.gob.ar/WSCDC/service.asmx?WSDL';
            $this->wsdlWsfe   = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
        }
    }

    /** Permite al controller saber con qué emisor se está operando. */
    public function getEmisorId(): int
    {
        return $this->emisorId;
    }

    /** Expone el CUIT usado para representar (útil para logs y debug). */
    public function getCuitRepresentada(): string
    {
        return $this->cuitRepresentada;
    }

    /**
     * Consulta un CUIT en el Padrón y devuelve los campos ya mapeados.
     */
    public function consultarPersona(string $cuit): array
    {
        $cuit = preg_replace('/\D/', '', $cuit);

        $chequeo = $this->chequearRequisitos();
        if (!$chequeo['ok']) {
            return $chequeo;
        }

        if (strlen($cuit) !== 11) {
            return ['ok' => false, 'msg' => 'El CUIT debe tener 11 dígitos para consultar en ARCA.'];
        }

        try {
            $ta = $this->obtenerTicketAcceso('ws_sr_padron_a13');
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'No se pudo autenticar contra ARCA (WSAA): ' . $e->getMessage()];
        }

        try {
            $cliente = new SoapClient($this->wsdlPadron, ['exceptions' => true, 'connection_timeout' => 15]);

            $respuesta = $cliente->getPersona([
                'token'            => $ta['token'],
                'sign'             => $ta['sign'],
                'cuitRepresentada' => $this->cuitRepresentada,   // ← antes ARCA_CUIT_REPRESENTADA
                'idPersona'        => $cuit,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'No se pudo consultar el Padrón de ARCA: ' . $e->getMessage()];
        }

        $persona = $respuesta->personaReturn->datosGenerales ?? null;

        if (!$persona) {
            return ['ok' => false, 'msg' => 'ARCA no devolvió datos para ese CUIT.'];
        }

        $domicilio = $persona->domicilioFiscal ?? null;

        $datos = [
            'razon_social'     => $persona->razonSocial
                                   ?? trim(($persona->nombre ?? '') . ' ' . ($persona->apellido ?? '')),
            'personeria'       => (isset($persona->tipoPersona) && stripos((string) $persona->tipoPersona, 'FISICA') !== false)
                                   ? 'FISICA' : 'JURIDICA',
            'domicilio_fiscal' => trim(($domicilio->direccion ?? '')),
            'localidad'        => $domicilio->localidad ?? ($domicilio->descripcionProvincia ?? ''),
            'provincia_nombre' => $domicilio->descripcionProvincia ?? '',
            'estado'           => (isset($persona->estadoClave) && strtoupper((string) $persona->estadoClave) === 'ACTIVO')
                                   ? 'ACTIVO' : 'ACTIVO',
        ];

        return ['ok' => true, 'msg' => 'Datos obtenidos de ARCA.', 'datos' => $datos];
    }

    /**
     * Constata (valida) un comprobante de compra contra ARCA (WSCDC).
     */
    public function constatarComprobante(array $datos): array
    {
        $chequeo = $this->chequearRequisitos();
        if (!$chequeo['ok']) {
            return $chequeo;
        }

        $cuitEmisor = preg_replace('/\D/', '', $datos['cuit_emisor'] ?? '');
        $cae        = preg_replace('/\D/', '', $datos['cae'] ?? '');

        if (strlen($cuitEmisor) !== 11) {
            return ['ok' => false, 'msg' => 'El CUIT del proveedor debe tener 11 dígitos.'];
        }
        if ($cae === '') {
            return ['ok' => false, 'msg' => 'Cargá el CAE del comprobante antes de constatarlo con ARCA.'];
        }

        try {
            $ta = $this->obtenerTicketAcceso('wscdc');
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'No se pudo autenticar contra ARCA (WSAA): ' . $e->getMessage()];
        }

        try {
            $cliente = new SoapClient($this->wsdlCdc, ['exceptions' => true, 'connection_timeout' => 15]);

            $respuesta = $cliente->ComprobanteConstatar([
                'Auth' => [
                    'Token' => $ta['token'],
                    'Sign'  => $ta['sign'],
                    'Cuit'  => $this->cuitRepresentada,   // ← antes ARCA_CUIT_REPRESENTADA
                ],
                'CmpReq' => [
                    'CbteModo'         => 'CAE',
                    'CuitEmisor'       => $cuitEmisor,
                    'PtoVta'           => (int) ($datos['punto_venta'] ?? 0),
                    'CbteTipo'         => (int) ($datos['tipo_comprobante_codigo'] ?? 0),
                    'CbteNro'          => (int) ($datos['numero'] ?? 0),
                    'CbteFch'          => str_replace('-', '', (string) ($datos['fecha'] ?? '')),
                    'ImpTotal'         => (float) ($datos['importe_total'] ?? 0),
                    'CodAutorizacion'  => $cae,
                    'DocTipoReceptor'  => 80,
                    'DocNroReceptor'   => $this->cuitRepresentada,   // ← antes ARCA_CUIT_REPRESENTADA
                ],
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'No se pudo constatar el comprobante en ARCA: ' . $e->getMessage()];
        }

        $resultado = $respuesta->ComprobanteConstatarResult ?? null;
        if (!$resultado) {
            return ['ok' => false, 'msg' => 'ARCA no devolvió una respuesta reconocible.'];
        }

        $observaciones = [];
        if (!empty($resultado->Observaciones->Obs)) {
            $obs = is_array($resultado->Observaciones->Obs) ? $resultado->Observaciones->Obs : [$resultado->Observaciones->Obs];
            foreach ($obs as $o) {
                $observaciones[] = trim(($o->Codigo ?? '') . ': ' . ($o->Mensaje ?? ''));
            }
        }

        $aprobado = isset($resultado->Resultado) && strtoupper((string) $resultado->Resultado) === 'A';

        return [
            'ok'            => $aprobado,
            'msg'           => $aprobado
                                ? 'ARCA confirmó que el comprobante es válido.'
                                : 'ARCA no pudo validar el comprobante.',
            'observaciones' => $observaciones,
        ];
    }

    /**
     * Solicita a ARCA el CAE (autorización) de una factura, por WSFEv1.
     *
     * MODIFICADO para multi-emisor: los datos de punto de venta, letra,
     * concepto e IVA se leen del emisor (parámetros), no de ConfiguracionApp.
     *
     * @param array $datos ['total','cliente_cuit','cliente_condicion_iva_id',
     *                      'punto_venta','letra','concepto','iva_id',
     *                      'impuestos_internos' (opcional)]
     */
    public function emitirFactura(array $datos): array
    {
        $chequeo = $this->chequearRequisitos();
        if (!$chequeo['ok']) {
            return $chequeo;
        }

        // Parámetros obligatorios del emisor (vienen del controller)
        $puntoVenta = (int)($datos['punto_venta'] ?? 0);
        $letra      = strtoupper(trim((string)($datos['letra'] ?? '')));
        $concepto   = (int)($datos['concepto'] ?? 3);
        $ivaId      = (int)($datos['iva_id'] ?? 5);
        $impInt     = (float)($datos['impuestos_internos'] ?? 0);

        if ($puntoVenta <= 0 || !in_array($letra, ['A', 'B', 'C'], true)) {
            return [
                'ok'  => false,
                'msg' => 'Falta configurar el Punto de Venta y/o la Letra del comprobante para este emisor.',
            ];
        }

        $cbteTipo = $this->letraATipoComprobante($letra);
        $cuitReceptor = preg_replace('/\D/', '', $datos['cliente_cuit'] ?? '');
        if (strlen($cuitReceptor) !== 11) {
            return ['ok' => false, 'msg' => 'El CUIT del cliente debe tener 11 dígitos para facturar por ARCA.'];
        }

        $total = round((float) ($datos['total'] ?? 0), 2);

        if ($letra === 'C') {
            $impNeto = $total;
            $impIva  = 0.0;
            $alicuotas = [];
        } else {
            $porcentajeIva = $this->porcentajeDeIvaId($ivaId);
            $impNeto = round($total / (1 + $porcentajeIva / 100), 2);
            $impIva  = round($total - $impNeto, 2);
            $alicuotas = [[
                'Id'      => $ivaId,
                'BaseImp' => $impNeto,
                'Importe' => $impIva,
            ]];
        }

        try {
            $ta = $this->obtenerTicketAcceso('wsfe');
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'No se pudo autenticar contra ARCA (WSAA): ' . $e->getMessage()];
        }

        try {
            $cliente = new SoapClient($this->wsdlWsfe, ['exceptions' => true, 'connection_timeout' => 15]);

            $auth = [
                'Token' => $ta['token'],
                'Sign'  => $ta['sign'],
                'Cuit'  => $this->cuitRepresentada,   // ← antes ARCA_CUIT_REPRESENTADA
            ];

            // Último número autorizado para ese PV + tipo
            $ultimo = $cliente->FECompUltimoAutorizado([
                'Auth'     => $auth,
                'PtoVta'   => $puntoVenta,
                'CbteTipo' => $cbteTipo,
            ]);
            $numeroSiguiente = ((int) ($ultimo->FECompUltimoAutorizadoResult->CbteNro ?? 0)) + 1;

            $detalle = [
                'Concepto'       => $concepto,
                'DocTipo'        => 80,
                'DocNro'         => $cuitReceptor,
                'CbteDesde'      => $numeroSiguiente,
                'CbteHasta'      => $numeroSiguiente,
                'CbteFch'        => date('Ymd'),
                'ImpTotal'       => $total,
                'ImpTotConc'     => 0,
                'ImpNeto'        => $impNeto,
                'ImpOpEx'        => 0,
                'ImpIVA'         => $impIva,
                'ImpTrib'        => $impInt,
                'MonId'          => 'PES',
                'MonCotiz'       => 1,
                'CondicionIVAReceptorId' => (int) ($datos['cliente_condicion_iva_id'] ?? 5),
            ];
            if ($alicuotas) {
                $detalle['Iva'] = ['AlicIva' => $alicuotas];
            }
            if ($concepto !== 1) {
                $detalle['FchServDesde'] = date('Ymd');
                $detalle['FchServHasta'] = date('Ymd');
                $detalle['FchVtoPago']   = date('Ymd');
            }

            $respuesta = $cliente->FECAESolicitar([
                'Auth'    => $auth,
                'FeCAEReq' => [
                    'FeCabReq' => [
                        'CantReg'  => 1,
                        'PtoVta'   => $puntoVenta,
                        'CbteTipo' => $cbteTipo,
                    ],
                    'FeDetReq' => ['FECAEDetRequest' => $detalle],
                ],
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'No se pudo facturar en ARCA: ' . $e->getMessage()];
        }

        $det = $respuesta->FECAESolicitarResult->FeDetResp->FECAEDetResponse ?? null;
        if (!$det) {
            return ['ok' => false, 'msg' => 'ARCA no devolvió una respuesta reconocible.'];
        }

        $observaciones = [];
        if (!empty($det->Observaciones->Obs)) {
            $obs = is_array($det->Observaciones->Obs) ? $det->Observaciones->Obs : [$det->Observaciones->Obs];
            foreach ($obs as $o) {
                $observaciones[] = trim(($o->Code ?? '') . ': ' . ($o->Msg ?? ''));
            }
        }

        $aprobado = isset($det->Resultado) && strtoupper((string) $det->Resultado) === 'A';

        if (!$aprobado) {
            return [
                'ok'  => false,
                'msg' => 'ARCA rechazó el comprobante' . ($observaciones ? ': ' . implode(' | ', $observaciones) : '.'),
            ];
        }

        return [
            'ok'  => true,
            'msg' => 'ARCA aprobó el comprobante. CAE: ' . $det->CAE,
            'datos' => [
                'letra'               => $letra,
                'punto_venta'         => $puntoVenta,
                'numero_comprobante'  => $numeroSiguiente,
                'cae'                 => (string) $det->CAE,
                'cae_vencimiento'     => $this->fechaAfipAFecha((string) $det->CAEFchVto),
            ],
        ];
    }

    private function letraATipoComprobante(string $letra): int
    {
        return ['A' => 1, 'B' => 6, 'C' => 11][$letra] ?? 11;
    }

    private function porcentajeDeIvaId(int $ivaId): float
    {
        return [3 => 0.0, 4 => 10.5, 5 => 21.0, 6 => 27.0][$ivaId] ?? 21.0;
    }

    private function fechaAfipAFecha(string $fechaAfip): ?string
    {
        if (strlen($fechaAfip) !== 8) {
            return null;
        }
        return substr($fechaAfip, 0, 4) . '-' . substr($fechaAfip, 4, 2) . '-' . substr($fechaAfip, 6, 2);
    }

    /**
     * Verifica los requisitos del emisor actual.
     */
    private function chequearRequisitos(): array
    {
        if (!extension_loaded('soap')) {
            return ['ok' => false, 'msg' => 'El servidor no tiene habilitada la extensión PHP "soap".'];
        }
        if (!extension_loaded('openssl')) {
            return ['ok' => false, 'msg' => 'El servidor no tiene habilitada la extensión PHP "openssl".'];
        }
        if ($this->cuitRepresentada === '') {
            return ['ok' => false, 'msg' => 'Falta configurar el CUIT del emisor.'];
        }
        if (!file_exists($this->certPath) || !file_exists($this->keyPath)) {
            return [
                'ok'  => false,
                'msg' => 'El certificado del emisor no está cargado en disco. '
                       . 'Verificá las rutas del emisor en la base de datos.',
            ];
        }
        return ['ok' => true, 'msg' => ''];
    }

    /**
     * Devuelve un Ticket de Acceso (token + sign) válido para el
     * servicio pedido, con caché en el directorio del emisor.
     */
    private function obtenerTicketAcceso(string $servicio): array
    {
        $cache = $this->leerCacheTicket($servicio);
        if ($cache) {
            return $cache;
        }

        $tra = $this->generarTra($servicio);
        $cms = $this->firmarTra($tra);

        $cliente = new SoapClient($this->wsdlWsaa, ['exceptions' => true, 'connection_timeout' => 15]);
        $respuesta = $cliente->loginCms(['in0' => $cms]);

        $xml = simplexml_load_string($respuesta->loginCmsReturn);
        $ta = [
            'token'      => (string) $xml->credentials->token,
            'sign'       => (string) $xml->credentials->sign,
            'expiracion' => (string) $xml->header->expirationTime,
        ];

        $this->guardarCacheTicket($servicio, $ta);

        return $ta;
    }

    private function generarTra(string $servicio): string
    {
        $uniqueId = time();
        $generationTime = date('c', $uniqueId - 60);
        $expirationTime  = date('c', $uniqueId + 60 * 10);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0">'
            . '<header>'
            . "<uniqueId>{$uniqueId}</uniqueId>"
            . "<generationTime>{$generationTime}</generationTime>"
            . "<expirationTime>{$expirationTime}</expirationTime>"
            . '</header>'
            . "<service>{$servicio}</service>"
            . '</loginTicketRequest>';
    }

    /**
     * Firma el TRA con el certificado del emisor.
     */
    private function firmarTra(string $tra): string
    {
        $traFile = tempnam(sys_get_temp_dir(), 'arca_tra_');
        $cmsFile = tempnam(sys_get_temp_dir(), 'arca_cms_');
        file_put_contents($traFile, $tra);

        // ← antes ARCA_KEY_PASSPHRASE / ARCA_CERT_PATH / ARCA_KEY_PATH
        $keyParam = $this->keyPassphrase !== ''
            ? ['file://' . $this->keyPath, $this->keyPassphrase]
            : 'file://' . $this->keyPath;

        $firmado = openssl_pkcs7_sign(
            $traFile,
            $cmsFile,
            'file://' . $this->certPath,
            $keyParam,
            [],
            !PKCS7_DETACHED
        );

        if (!$firmado) {
            @unlink($traFile);
            @unlink($cmsFile);
            throw new RuntimeException('No se pudo firmar el TRA con el certificado configurado (revisá cert/key del emisor).');
        }

        $contenido = file_get_contents($cmsFile);
        @unlink($traFile);
        @unlink($cmsFile);

        $partes = explode("\n\n", $contenido, 2);
        $base64 = trim($partes[1] ?? $contenido);

        return $base64;
    }

    private function leerCacheTicket(string $servicio): ?array
    {
        $ruta = $this->rutaCache($servicio);
        if (!file_exists($ruta)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($ruta), true);
        if (!$data || empty($data['expiracion'])) {
            return null;
        }

        if (strtotime($data['expiracion']) - 120 < time()) {
            return null;
        }

        return $data;
    }

    private function guardarCacheTicket(string $servicio, array $ta): void
    {
        $ruta = $this->rutaCache($servicio);
        $dir = dirname($ruta);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($ruta, json_encode($ta));
    }

    /**
     * Arma la ruta del archivo de caché del TA para el emisor actual.
     * Cada emisor tiene su propio directorio, así que no hay colisión.
     */
    private function rutaCache(string $servicio): string
    {
        return rtrim($this->taCacheDir, DS)
             . DS . 'ta_' . preg_replace('/[^a-z0-9_]/i', '', $servicio) . '.json';
    }
}