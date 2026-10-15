<?php
/**
 * includes/ContabilidadService.php
 *
 * Servicio central de contabilidad automática.
 * Todos los módulos (factura, compra, cobro, pago) llaman acá.
 *
 * Regla: si algo falta (configuración, ejercicio), NO lanza excepción.
 * Devuelve ['ok' => false, 'msg' => ...] y loguea.
 * La operación principal (factura, compra, etc.) sigue funcionando.
 */

require_once ROOT_PATH . 'models' . DS . 'EjercicioContable.php';
require_once ROOT_PATH . 'models' . DS . 'Asiento.php';
require_once ROOT_PATH . 'models' . DS . 'ConfiguracionContable.php';

class ContabilidadService
{
    private PDO $db;
    private EjercicioContable      $ejercicios;
    private Asiento                $asientos;
    private ConfiguracionContable  $config;

    public function __construct()
    {
        $this->db         = Conexion::getConnection();
        $this->ejercicios = new EjercicioContable();
        $this->asientos   = new Asiento();
        $this->config     = new ConfiguracionContable();
    }

    /* ==========================================================
       ASENTAR — Factura de venta
       ========================================================== */
    public function asentarFactura(int $facturaId): array
    {
        require_once ROOT_PATH . 'models' . DS . 'Factura.php';
        $facturaModel = new Factura();
        $f = $facturaModel->obtenerPorId($facturaId);
        if (!$f) return ['ok' => false, 'msg' => 'Factura no encontrada.'];

        // Si ya tiene asiento confirmado, no duplicar
        if ($this->existeAsiento('factura', $facturaId)) {
            return ['ok' => true, 'msg' => 'Ya tenía asiento.'];
        }

        // Resolver cuentas
        $ctaDeudores = $this->config->get('cta_deudores_ventas');
        $ctaVentas   = $this->config->get('cta_ventas');
        $ctaIvaDeb   = $this->config->get('cta_iva_debito');
        $ctaImpInt   = $this->config->get('cta_impuestos_internos');

        if (!$ctaDeudores || !$ctaVentas) {
            return $this->faltanCuentas(['Deudores por Ventas', 'Ventas']);
        }

        $total       = (float)$f['total'];
        $subtotal    = (float)$f['subtotal'];
        $iva         = (float)$f['iva'];
        $impInt      = (float)$f['impuestos_internos'];

        // Ajuste por descuento general: las líneas deben balancear con el total
        // Simplificación: neto de ventas = total - iva - imp - (descuentos no aplicados como línea)
        // En este esquema, el subtotal ya es el neto gravado. Si hay descuento general, lo aplicamos:
        $descGral = (float)($f['descuento_general_porcentaje'] ?? 0);
        if ($descGral > 0) {
            $subtotal = round($subtotal * (1 - $descGral / 100), 2);
        }

        $lineas = [
            $this->linea($ctaDeudores, $total, 0, null, 'CLIENTE', (int)$f['cliente_id']),
            $this->linea($ctaVentas,   0, $subtotal),
        ];
        if ($iva > 0 && $ctaIvaDeb) {
            $lineas[] = $this->linea($ctaIvaDeb, 0, $iva);
        }
        if ($impInt > 0 && $ctaImpInt) {
            $lineas[] = $this->linea($ctaImpInt, 0, $impInt);
        }

        $cabecera = [
            'ejercicio_id'    => $this->ejercicioParaFecha($f['fecha'])['id'] ?? null,
            'emisor_id'       => (int)$f['emisor_id'],
            'fecha'           => $f['fecha'],
            'tipo'            => 'VENTA',
            'descripcion'     => $this->descFactura($f),
            'referencia_tipo' => 'factura',
            'referencia_id'   => $facturaId,
        ];

        return $this->crearYConfirmar($cabecera, $lineas);
    }

    /* ==========================================================
       ASENTAR — Compra
       ========================================================== */
    public function asentarCompra(int $compraId): array
    {
        require_once ROOT_PATH . 'models' . DS . 'Compra.php';
        $compraModel = new Compra();
        $c = $compraModel->obtenerPorId($compraId);
        if (!$c) return ['ok' => false, 'msg' => 'Compra no encontrada.'];

        if ($this->existeAsiento('compra', $compraId)) {
            return ['ok' => true, 'msg' => 'Ya tenía asiento.'];
        }

        // Cuenta de gasto: primero plan_cuenta_id de la categoría, si no fallback
        $ctaGasto = null;
        if (!empty($c['cuenta_contable_id'])) {
            $stmt = $this->db->prepare(
                "SELECT plan_cuenta_id FROM cuenta_contable WHERE id = :id"
            );
            $stmt->execute([':id' => (int)$c['cuenta_contable_id']]);
            $ctaGasto = $stmt->fetchColumn() ?: null;
        }
        if (!$ctaGasto) {
            $ctaGasto = $this->config->get('cta_compras_insumos');
        }

        $ctaProveed = $this->config->get('cta_proveedores');
        $ctaIvaCred = $this->config->get('cta_iva_credito');
        $ctaImpInt  = $this->config->get('cta_impuestos_internos');

        if (!$ctaGasto || !$ctaProveed) {
            return $this->faltanCuentas(['Compras (o categoría de la compra)', 'Proveedores']);
        }

        $subtotal = (float)$c['subtotal'];
        $iva      = (float)$c['iva'];
        $impInt   = (float)$c['impuestos_internos'];
        $total    = (float)$c['total'];

        $lineas = [
            $this->linea($ctaGasto, $subtotal, 0),
        ];
        if ($iva > 0 && $ctaIvaCred) {
            $lineas[] = $this->linea($ctaIvaCred, $iva, 0);
        }
        if ($impInt > 0 && $ctaImpInt) {
            $lineas[] = $this->linea($ctaImpInt, $impInt, 0);
        }
        $lineas[] = $this->linea($ctaProveed, 0, $total, null, 'PROVEEDOR', (int)$c['proveedor_id']);

        $cabecera = [
            'ejercicio_id'    => $this->ejercicioParaFecha($c['fecha_comprobante'])['id'] ?? null,
            'emisor_id'       => (int)($c['emisor_id'] ?? $this->emisorPorDefecto()),
            'fecha'           => $c['fecha_comprobante'],
            'tipo'            => 'COMPRA',
            'descripcion'     => $this->descCompra($c),
            'referencia_tipo' => 'compra',
            'referencia_id'   => $compraId,
        ];

        return $this->crearYConfirmar($cabecera, $lineas);
    }

    /* ==========================================================
       ASENTAR — Cobro
       ========================================================== */
    public function asentarCobro(int $cobroId): array
    {
        require_once ROOT_PATH . 'models' . DS . 'Cobro.php';
        $cobroModel = new Cobro();
        $c = $cobroModel->obtenerPorId($cobroId);
        if (!$c) return ['ok' => false, 'msg' => 'Cobro no encontrado.'];

        if ($this->existeAsiento('cobro', $cobroId)) {
            return ['ok' => true, 'msg' => 'Ya tenía asiento.'];
        }

        $medios = $cobroModel->obtenerMedios($cobroId);
        if (empty($medios)) return ['ok' => false, 'msg' => 'Cobro sin medios.'];

        $ctaDeudores = $this->config->get('cta_deudores_ventas');
        if (!$ctaDeudores) return $this->faltanCuentas(['Deudores por Ventas']);

        $total = 0.0;
        $lineas = [];

        foreach ($medios as $m) {
            $importe = (float)$m['importe'];
            if ($importe <= 0) continue;
            $total += $importe;

            $cta = $this->ctaParaMedioCobro($m);
            if (!$cta) {
                error_log('[Contabilidad] Cobro #' . $cobroId . ' — sin cuenta para medio ' . $m['medio']);
                continue;
            }
            $lineas[] = $this->linea($cta, $importe, 0, $this->detalleMedio($m));
        }

        $lineas[] = $this->linea($ctaDeudores, 0, $total, null, 'CLIENTE', (int)$c['cliente_id']);

        $cabecera = [
            'ejercicio_id'    => $this->ejercicioParaFecha($c['fecha'])['id'] ?? null,
            'emisor_id'       => (int)($c['emisor_id'] ?? $this->emisorPorDefecto()),
            'fecha'           => $c['fecha'],
            'tipo'            => 'COBRO',
            'descripcion'     => 'Cobro #' . $cobroId . ' — ' . ($c['cliente_razon_social'] ?? ''),
            'referencia_tipo' => 'cobro',
            'referencia_id'   => $cobroId,
        ];

        return $this->crearYConfirmar($cabecera, $lineas);
    }

    /* ==========================================================
       ASENTAR — Pago
       ========================================================== */
    public function asentarPago(int $pagoId): array
    {
        require_once ROOT_PATH . 'models' . DS . 'Pago.php';
        $pagoModel = new Pago();
        $p = $pagoModel->obtenerPorId($pagoId);
        if (!$p) return ['ok' => false, 'msg' => 'Pago no encontrado.'];

        if ($this->existeAsiento('pago', $pagoId)) {
            return ['ok' => true, 'msg' => 'Ya tenía asiento.'];
        }

        $medios = $pagoModel->obtenerMedios($pagoId);
        if (empty($medios)) return ['ok' => false, 'msg' => 'Pago sin medios.'];

        $ctaProveed = $this->config->get('cta_proveedores');
        if (!$ctaProveed) return $this->faltanCuentas(['Proveedores']);

        $total = 0.0;
        $lineas = [];

        // DEBE Proveedores por el total
        $lineas[] = $this->linea($ctaProveed, (float)$p['total'], 0, null, 'PROVEEDOR', (int)$p['proveedor_id']);

        // HABER por cada medio
        foreach ($medios as $m) {
            $importe = (float)$m['importe'];
            if ($importe <= 0) continue;
            $total += $importe;

            $cta = $this->ctaParaMedioPago($m);
            if (!$cta) {
                error_log('[Contabilidad] Pago #' . $pagoId . ' — sin cuenta para medio ' . $m['medio']);
                continue;
            }
            $lineas[] = $this->linea($cta, 0, $importe, $this->detalleMedio($m));
        }

        $cabecera = [
            'ejercicio_id'    => $this->ejercicioParaFecha($p['fecha'])['id'] ?? null,
            'emisor_id'       => (int)($p['emisor_id'] ?? $this->emisorPorDefecto()),
            'fecha'           => $p['fecha'],
            'tipo'            => 'PAGO',
            'descripcion'     => 'Pago #' . $pagoId . ' — ' . ($p['proveedor_razon_social'] ?? ''),
            'referencia_tipo' => 'pago',
            'referencia_id'   => $pagoId,
        ];

        return $this->crearYConfirmar($cabecera, $lineas);
    }

    /* ==========================================================
       ANULAR ASIENTO POR REFERENCIA
       ========================================================== */
    public function anularAsientoPorReferencia(string $tipo, int $refId, string $motivo = ''): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM asiento
             WHERE referencia_tipo = :t
               AND referencia_id   = :r
               AND estado = 'CONFIRMADO'
             LIMIT 1"
        );
        $stmt->execute([':t' => $tipo, ':r' => $refId]);
        $asId = $stmt->fetchColumn();

        if (!$asId) {
            return ['ok' => true, 'msg' => 'No había asiento que anular.'];
        }

        return $this->asientos->anular(
            (int)$asId,
            (int)($_SESSION['user_id'] ?? 0),
            $motivo ?: ('Anulado por reversión de ' . $tipo . ' #' . $refId)
        );
    }

    /* ==========================================================
       HELPERS PRIVADOS
       ========================================================== */
    private function linea(
        int $cuentaId,
        float $debe,
        float $haber,
        ?string $detalle = null,
        string $auxTipo = 'NINGUNO',
        ?int $auxId = null
    ): array {
        return [
            'cuenta_id'     => $cuentaId,
            'debe'          => $debe,
            'haber'         => $haber,
            'detalle'       => $detalle,
            'auxiliar_tipo' => $auxTipo,
            'auxiliar_id'   => $auxId,
        ];
    }

    private function crearYConfirmar(array $cabecera, array $lineas): array
    {
        // Validar que haya ejercicio
        if (empty($cabecera['ejercicio_id'])) {
            $msg = 'No hay ejercicio contable abierto para la fecha ' . $cabecera['fecha'];
            error_log('[Contabilidad] ' . $msg);
            return ['ok' => false, 'msg' => $msg, 'sin_ejercicio' => true];
        }
        if (empty($cabecera['emisor_id'])) {
            $msg = 'No se pudo determinar el emisor.';
            error_log('[Contabilidad] ' . $msg);
            return ['ok' => false, 'msg' => $msg];
        }

        $r = $this->asientos->crear(
            $cabecera,
            $lineas,
            (int)($_SESSION['user_id'] ?? 0),
            'CONFIRMADO'
        );

        if (!$r['ok']) {
            error_log('[Contabilidad] Error al crear asiento: ' . ($r['msg'] ?? ''));
        }
        return $r;
    }

    private function ejercicioParaFecha(string $fecha): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return null;
        $stmt = $this->db->prepare(
            "SELECT * FROM ejercicio_contable
             WHERE estado = 'ABIERTO'
               AND :f BETWEEN fecha_desde AND fecha_hasta
             ORDER BY fecha_desde DESC LIMIT 1"
        );
        $stmt->execute([':f' => $fecha]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    private function existeAsiento(string $tipo, int $refId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM asiento
             WHERE referencia_tipo = :t
               AND referencia_id   = :r
               AND estado IN ('BORRADOR','CONFIRMADO')"
        );
        $stmt->execute([':t' => $tipo, ':r' => $refId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function emisorPorDefecto(): ?int
    {
        $id = $this->db->query(
            "SELECT id FROM emisor WHERE activo = 1 ORDER BY id LIMIT 1"
        )->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function ctaParaMedioCobro(array $m): ?int
    {
        switch ($m['medio']) {
            case 'EFECTIVO':      return $this->config->get('cta_caja');
            case 'TRANSFERENCIA': return $this->config->get('cta_banco');
            case 'CHEQUE':
                // Si tiene fecha de cobro futura → cartera; si es al día → banco
                if (!empty($m['cheque_fecha_cobro']) && $m['cheque_fecha_cobro'] > date('Y-m-d')) {
                    return $this->config->get('cta_cheques_cartera')
                        ?: $this->config->get('cta_banco');
                }
                return $this->config->get('cta_banco');
            case 'RETENCION':
                $t = strtoupper((string)($m['retencion_tipo'] ?? ''));
                if (str_contains($t, 'IVA'))       return $this->config->get('cta_ret_iva');
                if (str_contains($t, 'IIBB'))      return $this->config->get('cta_ret_iibb');
                if (str_contains($t, 'GANANCIA'))  return $this->config->get('cta_ret_ganancias');
                return null;
            default:
                return null; // TARJETA, OTRO: por ahora no se asientan
        }
    }

    private function ctaParaMedioPago(array $m): ?int
    {
        switch ($m['medio']) {
            case 'EFECTIVO':      return $this->config->get('cta_caja');
            case 'TRANSFERENCIA': return $this->config->get('cta_banco');
            case 'CHEQUE':
                if (!empty($m['cheque_fecha_cobro']) && $m['cheque_fecha_cobro'] > date('Y-m-d')) {
                    return $this->config->get('cta_cheques_diferidos')
                        ?: $this->config->get('cta_banco');
                }
                return $this->config->get('cta_banco');
            case 'RETENCION':
                return $this->config->get('cta_ret_a_depositar');
            default:
                return null;
        }
    }

    private function detalleMedio(array $m): string
    {
        if ($m['medio'] === 'CHEQUE') {
            return 'Cheque ' . ($m['cheque_banco'] ?? '') . ' N° ' . ($m['cheque_numero'] ?? '');
        }
        if ($m['medio'] === 'RETENCION') {
            return 'Retención ' . ($m['retencion_tipo'] ?? '') . ' N° ' . ($m['retencion_nro'] ?? '');
        }
        return $m['medio'];
    }

    private function descFactura(array $f): string
    {
        $letra = $f['arca_tipo_comprobante_letra'] ?? '';
        $pv    = !empty($f['arca_punto_venta']) ? str_pad((string)$f['arca_punto_venta'], 4, '0', STR_PAD_LEFT) : '';
        $nro   = !empty($f['arca_numero_comprobante']) ? str_pad((string)$f['arca_numero_comprobante'], 8, '0', STR_PAD_LEFT) : '';
        $cliente = $f['cliente_razon_social'] ?? ('#' . $f['cliente_id']);

        if ($pv && $nro) {
            return "Factura $letra $pv-$nro — $cliente";
        }
        return "Factura #{$f['id']} — $cliente";
    }

    private function descCompra(array $c): string
    {
        $prov = $c['proveedor_razon_social'] ?? ('#' . $c['proveedor_id']);
        $pv = $c['punto_venta'] ?? '';
        $nro = $c['numero'] ?? '';
        if ($pv && $nro) {
            return "Compra $pv-$nro — $prov";
        }
        return "Compra #{$c['id']} — $prov";
    }

    private function faltanCuentas(array $nombres): array
    {
        $msg = 'Faltan cuentas contables en la configuración: ' . implode(', ', $nombres)
             . '. Configuralas en Contabilidad → Configuración.';
        error_log('[Contabilidad] ' . $msg);
        return ['ok' => false, 'msg' => $msg, 'config_faltante' => true];
    }
}