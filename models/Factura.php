<?php
// models/Factura.php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/Emisor.php';
require_once __DIR__ . '/Cliente.php';
require_once __DIR__ . '/Pedido.php';
require_once __DIR__ . '/CuentaCorriente.php';

class Factura
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LECTURA
       ========================================================== */
    public function listar(
        string $desde = '',
        string $hasta = '',
        int    $emisorId = 0,
        int    $clienteId = 0,
        string $busqueda = '',
        string $estado = '',
        string $arcaEstado = ''
    ): array {
        $sql = "SELECT f.*,
                       e.nombre AS emisor_nombre,
                       e.razon_social AS emisor_razon_social,
                       c.razon_social AS cliente_razon_social,
                       c.cuit AS cliente_cuit,
                       u.nombre AS creado_por_nombre,
                       p.id AS pedido_numero
                FROM factura f
                INNER JOIN emisor e ON e.id = f.emisor_id
                INNER JOIN cliente c ON c.id = f.cliente_id
                LEFT JOIN usuario u ON u.id = f.creado_por_id
                LEFT JOIN pedido p ON p.id = f.pedido_id
                WHERE f.tenant_id = :tenant";
        $params = [':tenant' => tenantId()];

        if ($desde !== '') {
            $sql .= " AND f.fecha >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND f.fecha <= :hasta";
            $params[':hasta'] = $hasta;
        }
        if ($emisorId > 0) {
            $sql .= " AND f.emisor_id = :eid";
            $params[':eid'] = $emisorId;
        }
        if ($clienteId > 0) {
            $sql .= " AND f.cliente_id = :cid";
            $params[':cid'] = $clienteId;
        }
        if ($busqueda !== '') {
            $sql .= " AND (c.razon_social LIKE :q1
                        OR c.cuit LIKE :q2
                        OR f.remito_numero LIKE :q3
                        OR f.arca_numero_comprobante LIKE :q4)";
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }
        if ($estado !== '' && in_array($estado, ['ACTIVA', 'ANULADA'], true)) {
            $sql .= " AND f.estado = :est";
            $params[':est'] = $estado;
        }
        if ($arcaEstado !== '' && in_array($arcaEstado, ['PENDIENTE', 'APROBADO', 'ERROR'], true)) {
            $sql .= " AND f.arca_estado = :aest";
            $params[':aest'] = $arcaEstado;
        }

        $sql .= " ORDER BY f.fecha DESC, f.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT f.*,
                       e.nombre AS emisor_nombre,
                       e.razon_social AS emisor_razon_social,
                       e.nombre_fantasia AS emisor_nombre_fantasia,
                       e.cuit AS emisor_cuit,
                       e.cuit_sin_guiones AS emisor_cuit_sin_guiones,
                       e.condicion_iva AS emisor_condicion_iva,
                       e.ingresos_brutos AS emisor_ingresos_brutos,
                       e.fecha_inicio_actividades AS emisor_fecha_inicio,
                       e.domicilio AS emisor_domicilio,
                       e.telefono AS emisor_telefono,
                       e.email AS emisor_email,
                       e.logo_path AS emisor_logo,
                       c.razon_social AS cliente_razon_social,
                       c.nombre_fantasia AS cliente_nombre_fantasia,
                       c.cuit AS cliente_cuit,
                       c.domicilio_fiscal AS cliente_domicilio,
                       c.telefono AS cliente_telefono,
                       c.id_condicion_iva AS cliente_condicion_iva_id,
                       ci.descripcion AS cliente_condicion_iva,
                       u.nombre AS creado_por_nombre
                FROM factura f
                INNER JOIN emisor e ON e.id = f.emisor_id
                INNER JOIN cliente c ON c.id = f.cliente_id
                LEFT JOIN condiciones_iva ci ON ci.id = c.id_condicion_iva
                LEFT JOIN usuario u ON u.id = f.creado_por_id
                WHERE f.id = :id
                  AND f.tenant_id = :tenant
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant' => tenantId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerDetalle(int $facturaId): array
    {
        $sql = "SELECT fd.*,
                       a.nombre AS articulo_nombre,
                       a.codigo AS articulo_codigo,
                       pk.descripcion AS pakaging_desc
                FROM factura_detalle fd
                INNER JOIN articulo a ON a.id = fd.articulo_id
                LEFT JOIN pakaging pk ON pk.id = fd.pakaging_id
                WHERE fd.factura_id = :id
                  AND fd.tenant_id = :tenant
                ORDER BY fd.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $facturaId, ':tenant' => tenantId()]);
        return $stmt->fetchAll();
    }

    public function obtenerMovimientosStock(int $facturaId): array
    {
        $sql = "SELECT * FROM factura_movimiento_stock
                WHERE factura_id = :id AND tenant_id = :tenant
                ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $facturaId, ':tenant' => tenantId()]);
        return $stmt->fetchAll();
    }

    public function obtenerActivaPorPedido(int $pedidoId): ?array
    {
        $sql = "SELECT * FROM factura
                WHERE pedido_id = :pid
                  AND estado = 'ACTIVA'
                  AND tenant_id = :tenant
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':pid' => $pedidoId, ':tenant' => tenantId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ==========================================================
       CREAR DESDE PEDIDO
       ========================================================== */
    public function crearDesdePedido(int $pedidoId, int $emisorId, int $usuarioId): array
    {
        $pedidoModel = new Pedido();
        $pedido = $pedidoModel->obtenerPorId($pedidoId);

        if (!$pedido) {
            return ['ok' => false, 'msg' => 'El pedido no existe.'];
        }
        if ($pedido['estado'] !== 'PREPARADO') {
            return ['ok' => false, 'msg' => 'Solo se pueden facturar pedidos en estado PREPARADO.'];
        }
        if ($this->obtenerActivaPorPedido($pedidoId)) {
            return ['ok' => false, 'msg' => 'Ese pedido ya tiene una factura activa.'];
        }
        if ((int)$pedido['stock_descontado'] === 1) {
            return ['ok' => false, 'msg' => 'El stock de ese pedido ya fue descontado.'];
        }

        $detallePedido = $pedidoModel->obtenerDetalle($pedidoId);
        if (empty($detallePedido)) {
            return ['ok' => false, 'msg' => 'El pedido no tiene artículos.'];
        }

        $detalle = [];
        foreach ($detallePedido as $d) {
            $detalle[] = [
                'articulo_id'         => (int)$d['articulo_id'],
                'pakaging_id'         => !empty($d['pakaging_id']) ? (int)$d['pakaging_id'] : null,
                'cantidad_pakaging'   => (float)$d['cantidad_pakaging'],
                'cantidad_unidades'   => (float)$d['cantidad_unidades'],
                'precio_unitario'     => (float)$d['precio_unitario'],
                'descuento_porcentaje'=> (float)$d['descuento_porcentaje'],
                'sin_precio'          => (int)($d['sin_precio'] ?? 0),
                'condicion_iva'       => 'GRAVADO',
                'alicuota_iva'        => 21.00,
            ];
        }

        $clienteModel = new Cliente();
        $cliente = $clienteModel->obtenerPorId((int)$pedido['cliente_id']);
        if (!$cliente) {
            return ['ok' => false, 'msg' => 'El cliente del pedido no existe.'];
        }

        $cabecera = [
            'emisor_id'                    => $emisorId,
            'pedido_id'                    => $pedidoId,
            'cliente_id'                   => (int)$pedido['cliente_id'],
            'categoria_override'           => null,
            'fecha'                        => date('Y-m-d'),
            'descuento_general_porcentaje' => (float)$pedido['descuento_general_porcentaje'],
            'perc_iva'                     => 0,
            'perc_iibb'                    => 0,
            'notas'                        => $pedido['notas'] ?? null,
            'creado_por_id'                => $usuarioId,
        ];

        $resultado = $this->crear($cabecera, $detalle);
        if (!$resultado['ok']) {
            return $resultado;
        }

        $this->db->prepare(
            "UPDATE pedido
             SET estado = 'FACTURADO', stock_descontado = 1, fecha_facturacion = CURDATE()
             WHERE id = :id AND tenant_id = :tenant"
        )->execute([':id' => $pedidoId, ':tenant' => tenantId()]);

        return $resultado;
    }

    /* ==========================================================
       CREAR VENTA INMEDIATA
       ========================================================== */
    public function crearVentaInmediata(
        array $cabecera,
        array $detalle,
        int $emisorId,
        int $usuarioId
    ): array {
        if (empty($detalle)) {
            return ['ok' => false, 'msg' => 'Cargá al menos un artículo.'];
        }

        $cabecera['emisor_id']       = $emisorId;
        $cabecera['pedido_id']       = null;
        $cabecera['creado_por_id']   = $usuarioId;

        return $this->crear($cabecera, $detalle);
    }

    /* ==========================================================
       CREAR
       ========================================================== */
    private function crear(array $cabecera, array $detalle): array
    {
        try {
            $this->db->beginTransaction();

            $emisorModel = new Emisor();
            $emisor = $emisorModel->obtenerPorId((int)$cabecera['emisor_id']);
            if (!$emisor) {
                $this->db->rollBack();
                return ['ok' => false, 'msg' => 'El emisor no existe.'];
            }

            $nroRemito = $emisorModel->siguienteRemito((int)$emisor['id']);
            if ($nroRemito <= 0) {
                $this->db->rollBack();
                return ['ok' => false, 'msg' => 'No se pudo asignar el nº de remito.'];
            }

            $percIva  = (float)($cabecera['perc_iva']  ?? 0);
            $percIibb = (float)($cabecera['perc_iibb'] ?? 0);

            $totales = $this->calcularTotales(
                $detalle,
                (float)($cabecera['descuento_general_porcentaje'] ?? 0),
                $percIva,
                $percIibb
            );

            $sql = "INSERT INTO factura
                    (tenant_id, emisor_id, pedido_id, cliente_id, categoria_override, fecha,
                     remito_prefijo, remito_punto_venta, remito_numero,
                     arca_tipo_comprobante_letra, arca_punto_venta,
                     subtotal, descuento_general_porcentaje,
                     neto_gravado, neto_no_gravado, neto_exento,
                     iva, perc_iva, perc_iibb, impuestos_internos, total,
                     notas, creado_por_id)
                    VALUES
                    (:tenant, :emisor_id, :pedido_id, :cliente_id, :categoria_override, :fecha,
                     :remito_prefijo, :remito_pv, :remito_nro,
                     :arca_letra, :arca_pv,
                     :subtotal, :desc_gral,
                     :neto_gravado, :neto_no_gravado, :neto_exento,
                     :iva, :perc_iva, :perc_iibb, :imp_int, :total,
                     :notas, :creado_por)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant'             => tenantId(),
                ':emisor_id'          => (int)$cabecera['emisor_id'],
                ':pedido_id'          => $cabecera['pedido_id'] ?? null,
                ':cliente_id'         => (int)$cabecera['cliente_id'],
                ':categoria_override' => $cabecera['categoria_override'] ?? null,
                ':fecha'              => $cabecera['fecha'] ?? date('Y-m-d'),
                ':remito_prefijo'     => $emisor['remito_prefijo'] ?? null,
                ':remito_pv'          => (int)$emisor['remito_punto_venta'],
                ':remito_nro'         => $nroRemito,
                ':arca_letra'         => $emisor['arca_tipo_comprobante_letra'] ?? null,
                ':arca_pv'            => (int)$emisor['arca_punto_venta'] ?: null,
                ':subtotal'           => $totales['subtotal'],
                ':desc_gral'          => $totales['descuento_general_porcentaje'],
                ':neto_gravado'       => $totales['neto_gravado'],
                ':neto_no_gravado'    => $totales['neto_no_gravado'],
                ':neto_exento'        => $totales['neto_exento'],
                ':iva'                => $totales['iva'],
                ':perc_iva'           => $totales['perc_iva'],
                ':perc_iibb'          => $totales['perc_iibb'],
                ':imp_int'            => $totales['impuestos_internos'],
                ':total'              => $totales['total'],
                ':notas'              => $cabecera['notas'] ?? null,
                ':creado_por'         => (int)$cabecera['creado_por_id'],
            ]);
            $facturaId = (int)$this->db->lastInsertId();

            $cc = new CuentaCorriente();
            $cc->registrar(
                (int)$cabecera['cliente_id'],
                $cabecera['fecha'] ?? date('Y-m-d'),
                'FACTURA',
                $facturaId,
                (float)$totales['total'],
                0.0,
                'Factura #' . $facturaId,
                (int)$cabecera['creado_por_id']
            );

            $this->guardarDetalle($facturaId, $detalle);
            $this->descontarStock($facturaId, $detalle);

            $this->db->commit();

            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $rCont = $svc->asentarFactura($facturaId);
                if (empty($rCont['ok'])) {
                    error_log('[Factura::crear] Asiento no generado para factura #' . $facturaId
                            . ': ' . ($rCont['msg'] ?? 'desconocido'));
                }
            } catch (\Throwable $e) {
                error_log('[Factura::crear] Excepción en ContabilidadService: ' . $e->getMessage());
            }

            return [
                'ok'         => true,
                'msg'        => 'Factura guardada. Recordá enviarla a ARCA cuando corresponda.',
                'factura_id' => $facturaId,
                'remito_nro' => $nroRemito,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Factura::crear] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al guardar la factura: ' . $e->getMessage()];
        }
    }

    private function guardarDetalle(int $facturaId, array $detalle): void
    {
        $sql = "INSERT INTO factura_detalle
                (tenant_id, factura_id, articulo_id, pakaging_id,
                 cantidad_pakaging, cantidad_unidades,
                 precio_unitario, descuento_porcentaje, subtotal_linea, sin_precio,
                 condicion_iva, alicuota_iva, importe_iva)
                VALUES
                (:tenant, :factura_id, :articulo_id, :pakaging_id,
                 :cant_pk, :cant_un,
                 :precio, :desc, :subtotal, :sin_precio,
                 :cond, :al, :imp)";
        $stmt = $this->db->prepare($sql);

        $tenant = tenantId();
        foreach ($detalle as $d) {
            $cantidadUnidades = (float)$d['cantidad_unidades'];
            $precio           = (float)$d['precio_unitario'];
            $descPct          = (float)($d['descuento_porcentaje'] ?? 0);
            $sinPrecio        = (int)($d['sin_precio'] ?? 0);

            if ($sinPrecio === 1) {
                $precio  = 0;
                $descPct = 0;
            }

            $subtotalLinea = round($cantidadUnidades * $precio * (1 - $descPct / 100), 4);

            $cond = strtoupper((string)($d['condicion_iva'] ?? 'GRAVADO'));
            if (!in_array($cond, ['GRAVADO','NO_GRAVADO','EXENTO'], true)) {
                $cond = 'GRAVADO';
            }
            $al = (float)($d['alicuota_iva'] ?? 21.00);
            if ($sinPrecio === 1 || $cond !== 'GRAVADO') {
                $al = 0.0;
                $impIva = 0.0;
            } else {
                $impIva = round($subtotalLinea * $al / 100, 4);
            }

            $stmt->execute([
                ':tenant'       => $tenant,
                ':factura_id'   => $facturaId,
                ':articulo_id'  => (int)$d['articulo_id'],
                ':pakaging_id'  => !empty($d['pakaging_id']) ? (int)$d['pakaging_id'] : null,
                ':cant_pk'      => (float)$d['cantidad_pakaging'],
                ':cant_un'      => $cantidadUnidades,
                ':precio'       => $precio,
                ':desc'         => $descPct,
                ':subtotal'     => $subtotalLinea,
                ':sin_precio'   => $sinPrecio,
                ':cond'         => $cond,
                ':al'           => $al,
                ':imp'          => $impIva,
            ]);
        }
    }

    /* ==========================================================
       DESCUENTO DE STOCK
       ========================================================== */
    private function descontarStock(int $facturaId, array $detalle): void
    {
        $tenant = tenantId();
        $acumulado = ['ARTICULO' => [], 'INSUMO' => []];

        foreach ($detalle as $d) {
            $articuloId       = (int)$d['articulo_id'];
            $cantidadUnidades = (float)$d['cantidad_unidades'];

            if ($cantidadUnidades <= 0) continue;

            if (!isset($acumulado['ARTICULO'][$articuloId])) {
                $acumulado['ARTICULO'][$articuloId] = 0.0;
            }
            $acumulado['ARTICULO'][$articuloId] += $cantidadUnidades;

            $stmt = $this->db->prepare(
                "SELECT idinsumo, cantidad FROM formula
                 WHERE idarticulo = :aid AND tenant_id = :tenant"
            );
            $stmt->execute([':aid' => $articuloId, ':tenant' => $tenant]);
            $formula = $stmt->fetchAll();

            foreach ($formula as $f) {
                $insumoId    = (int)$f['idinsumo'];
                $cantFormula = (float)$f['cantidad'];

                if (!isset($acumulado['INSUMO'][$insumoId])) {
                    $acumulado['INSUMO'][$insumoId] = 0.0;
                }
                $acumulado['INSUMO'][$insumoId] += $cantidadUnidades * $cantFormula;
            }
        }

        $stmtArt = $this->db->prepare(
            "UPDATE articulo SET stock = stock - :c WHERE id = :id AND tenant_id = :tenant"
        );
        $stmtMov = $this->db->prepare(
            "INSERT INTO factura_movimiento_stock (tenant_id, factura_id, tipo, referencia_id, cantidad)
             VALUES (:tenant, :fid, :tipo, :ref, :cant)"
        );

        foreach ($acumulado['ARTICULO'] as $artId => $cant) {
            $cantRedondeada = round($cant, 3);
            if ($cantRedondeada <= 0) continue;

            $stmtArt->execute([
                ':c'      => $cantRedondeada,
                ':id'     => $artId,
                ':tenant' => $tenant,
            ]);
            $stmtMov->execute([
                ':tenant' => $tenant,
                ':fid'    => $facturaId,
                ':tipo'   => 'ARTICULO',
                ':ref'    => $artId,
                ':cant'   => $cantRedondeada,
            ]);
        }

        $stmtIns = $this->db->prepare(
            "UPDATE insumo SET stock = stock - :c WHERE id = :id AND tenant_id = :tenant"
        );

        foreach ($acumulado['INSUMO'] as $insId => $cant) {
            $cantRedondeada = round($cant, 3);
            if ($cantRedondeada <= 0) continue;

            $stmtIns->execute([
                ':c'      => $cantRedondeada,
                ':id'     => $insId,
                ':tenant' => $tenant,
            ]);
            $stmtMov->execute([
                ':tenant' => $tenant,
                ':fid'    => $facturaId,
                ':tipo'   => 'INSUMO',
                ':ref'    => $insId,
                ':cant'   => $cantRedondeada,
            ]);
        }
    }

    /* ==========================================================
       CÁLCULO DE TOTALES (sin cambios — solo calcula)
       ========================================================== */
    private function calcularTotales(
        array $detalle,
        float $descGral,
        float $percIva,
        float $percIibb
    ): array {
        $subtotal     = 0.0;
        $netoGravado  = 0.0;
        $netoNoGrav   = 0.0;
        $netoExento   = 0.0;
        $ivaTotal     = 0.0;

        foreach ($detalle as $d) {
            if ((int)($d['sin_precio'] ?? 0) === 1) continue;

            $unidades = (float)$d['cantidad_unidades'];
            $precio   = (float)$d['precio_unitario'];
            $desc     = (float)($d['descuento_porcentaje'] ?? 0);

            $linea = $unidades * $precio * (1 - $desc / 100);
            $subtotal += $linea;

            $cond = strtoupper((string)($d['condicion_iva'] ?? 'GRAVADO'));
            if (!in_array($cond, ['GRAVADO','NO_GRAVADO','EXENTO'], true)) {
                $cond = 'GRAVADO';
            }
            $al = (float)($d['alicuota_iva'] ?? 21.00);

            if ($cond === 'GRAVADO') {
                $netoGravado += $linea;
                $ivaTotal    += $linea * $al / 100;
            } elseif ($cond === 'NO_GRAVADO') {
                $netoNoGrav += $linea;
            } else {
                $netoExento += $linea;
            }
        }

        $factor = 1 - $descGral / 100;

        $subtotal    = round($subtotal    * $factor, 2);
        $netoGravado = round($netoGravado * $factor, 2);
        $netoNoGrav  = round($netoNoGrav  * $factor, 2);
        $netoExento  = round($netoExento  * $factor, 2);
        $ivaTotal    = round($ivaTotal    * $factor, 2);

        $total = round($subtotal + $ivaTotal + $percIva + $percIibb, 2);

        return [
            'subtotal'                     => $subtotal,
            'descuento_general_porcentaje' => $descGral,
            'neto_gravado'                 => $netoGravado,
            'neto_no_gravado'              => $netoNoGrav,
            'neto_exento'                  => $netoExento,
            'iva'                          => $ivaTotal,
            'perc_iva'                     => round($percIva, 2),
            'perc_iibb'                    => round($percIibb, 2),
            'impuestos_internos'           => 0.0,
            'total'                        => $total,
        ];
    }

    /* ==========================================================
       ANULAR
       ========================================================== */
    public function anular(int $facturaId, string $motivo, int $usuarioId): array
    {
        $factura = $this->obtenerPorId($facturaId);
        if (!$factura) {
            return ['ok' => false, 'msg' => 'La factura no existe.'];
        }
        if ($factura['estado'] === 'ANULADA') {
            return ['ok' => false, 'msg' => 'La factura ya estaba anulada.'];
        }

        try {
            $this->db->beginTransaction();

            $this->revertirStock($facturaId);

            $stmt = $this->db->prepare(
                "UPDATE factura
                 SET estado = 'ANULADA',
                     anulada_el = NOW(),
                     anulada_por_id = :u,
                     anulada_motivo = :m
                 WHERE id = :id AND tenant_id = :tenant"
            );
            $stmt->execute([
                ':u'      => $usuarioId,
                ':m'      => $motivo ?: null,
                ':id'     => $facturaId,
                ':tenant' => tenantId(),
            ]);

            if (!empty($factura['pedido_id'])) {
                $this->db->prepare(
                    "UPDATE pedido
                     SET estado = 'PREPARADO', stock_descontado = 0, fecha_facturacion = NULL
                     WHERE id = :pid AND tenant_id = :tenant"
                )->execute([
                    ':pid'    => (int)$factura['pedido_id'],
                    ':tenant' => tenantId(),
                ]);
            }

            $cc = new CuentaCorriente();
            $cc->anularPorReferencia($facturaId, 'FACTURA', $usuarioId);

            $this->db->commit();

            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $svc->anularAsientoPorReferencia('factura', $facturaId,
                    'Anulación de factura: ' . ($motivo ?: 'sin motivo'));
            } catch (\Throwable $e) {
                error_log('[Factura::anular] Error al anular asiento: ' . $e->getMessage());
            }

            return ['ok' => true, 'msg' => 'Factura anulada. Stock revertido.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Factura::anular] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al anular: ' . $e->getMessage()];
        }
    }

    private function revertirStock(int $facturaId): void
    {
        $movs = $this->obtenerMovimientosStock($facturaId);
        $tenant = tenantId();

        $stmtArt = $this->db->prepare(
            "UPDATE articulo SET stock = stock + :c WHERE id = :id AND tenant_id = :tenant"
        );
        $stmtIns = $this->db->prepare(
            "UPDATE insumo SET stock = stock + :c WHERE id = :id AND tenant_id = :tenant"
        );

        foreach ($movs as $m) {
            $cant = (float)$m['cantidad'];
            if ($m['tipo'] === 'ARTICULO') {
                $stmtArt->execute([
                    ':c'      => $cant,
                    ':id'     => (int)$m['referencia_id'],
                    ':tenant' => $tenant,
                ]);
            } else {
                $stmtIns->execute([
                    ':c'      => $cant,
                    ':id'     => (int)$m['referencia_id'],
                    ':tenant' => $tenant,
                ]);
            }
        }
    }

    /* ==========================================================
       HELPERS FISCALES
       ========================================================== */
    public function tieneAlicuotasMixtas(int $facturaId): array
    {
        $sql = "SELECT DISTINCT alicuota_iva
                FROM factura_detalle
                WHERE factura_id = :id
                  AND condicion_iva = 'GRAVADO'
                  AND sin_precio = 0
                  AND tenant_id = :tenant
                ORDER BY alicuota_iva";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $facturaId, ':tenant' => tenantId()]);
        $alicuotas = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return [
            'mixtas'    => count($alicuotas) > 1,
            'alicuotas' => $alicuotas,
        ];
    }

    public function getAlicuotaUnica(int $facturaId): ?float
    {
        $sql = "SELECT DISTINCT alicuota_iva
                FROM factura_detalle
                WHERE factura_id = :id
                  AND condicion_iva = 'GRAVADO'
                  AND sin_precio = 0
                  AND tenant_id = :tenant
                LIMIT 2";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $facturaId, ':tenant' => tenantId()]);
        $alicuotas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($alicuotas) !== 1) return null;
        return (float)$alicuotas[0];
    }

    public static function alicuotaToArcaId(?float $alicuota): int
    {
        if ($alicuota === null) return 5;
        if (abs($alicuota - 0)   < 0.01) return 3;
        if (abs($alicuota - 10.5) < 0.01) return 4;
        if (abs($alicuota - 21)  < 0.01) return 5;
        if (abs($alicuota - 27)  < 0.01) return 6;
        return 5;
    }

    /* ==========================================================
       ENVIAR A ARCA
       ========================================================== */
    public function enviarAArca(int $facturaId): array
    {
        require_once __DIR__ . '/../includes/ArcaClient.php';

        $factura = $this->obtenerPorId($facturaId);
        if (!$factura) {
            return ['ok' => false, 'msg' => 'La factura no existe.'];
        }
        if ($factura['estado'] !== 'ACTIVA') {
            return ['ok' => false, 'msg' => 'No se puede enviar a ARCA una factura anulada.'];
        }
        if ($factura['arca_estado'] === 'APROBADO') {
            return ['ok' => false, 'msg' => 'Esta factura ya fue aprobada por ARCA (CAE ' . $factura['cae'] . ').'];
        }
        if (empty($factura['arca_tipo_comprobante_letra'])) {
            return ['ok' => false, 'msg' => 'El emisor no tiene configurada la letra de comprobante.'];
        }
        if (empty($factura['arca_punto_venta'])) {
            return ['ok' => false, 'msg' => 'El emisor no tiene configurado el punto de venta de ARCA.'];
        }

        $chk = $this->tieneAlicuotasMixtas($facturaId);
        if ($chk['mixtas']) {
            $lista = implode(', ', array_map(function ($a) {
                return rtrim(rtrim(number_format($a, 2, '.', ''), '0'), '.');
            }, $chk['alicuotas']));
            return [
                'ok'  => false,
                'msg' => 'ARCA no permite alícuotas mixtas en este flujo (' . $lista
                       . '%). Separá la factura en dos: una con cada alícuota.'
            ];
        }

        $alicuotaUnica = $this->getAlicuotaUnica($facturaId);
        $arcaIdDinamico = self::alicuotaToArcaId($alicuotaUnica);

        $arca = new ArcaClient((int)$factura['emisor_id']);

        $detalle = $this->obtenerDetalle($facturaId);
        $netoGravado = 0.0;
        foreach ($detalle as $d) {
            if ((int)$d['sin_precio'] === 1) continue;
            if (($d['condicion_iva'] ?? 'GRAVADO') === 'GRAVADO') {
                $netoGravado += (float)$d['subtotal_linea'];
            }
        }
        $factor = 1 - (float)$factura['descuento_general_porcentaje'] / 100;
        $netoGravado = round($netoGravado * $factor, 2);

        $totalParaArca = (float)$factura['subtotal']
                       + (float)$factura['iva']
                       + (float)$factura['perc_iva']
                       + (float)$factura['perc_iibb']
                       + (float)$factura['impuestos_internos'];

        if ($totalParaArca <= 0) {
            return ['ok' => false, 'msg' => 'El total a facturar es 0 (todas las líneas son sin cargo).'];
        }

        $emisorModel = new Emisor();
        $emisor = $emisorModel->obtenerPorId((int)$factura['emisor_id']);

        $res = $arca->emitirFactura([
            'total'                    => $totalParaArca,
            'neto_gravado'             => $netoGravado,
            'cliente_cuit'             => $factura['cliente_cuit'],
            'cliente_condicion_iva_id' => (int)($factura['cliente_condicion_iva_id'] ?? 5),
            'punto_venta'              => (int)$factura['arca_punto_venta'],
            'letra'                    => $factura['arca_tipo_comprobante_letra'],
            'concepto'                 => (int)($emisor['arca_concepto'] ?? 3),
            'iva_id'                   => $arcaIdDinamico,
            'impuestos_internos'       => (float)$factura['impuestos_internos'],
        ]);

        if (!$res['ok']) {
            $this->db->prepare(
                "UPDATE factura
                 SET arca_estado = 'ERROR',
                     arca_observaciones = :obs,
                     arca_enviado_el = NOW()
                 WHERE id = :id AND tenant_id = :tenant"
            )->execute([
                ':obs'    => substr($res['msg'], 0, 500),
                ':id'     => $facturaId,
                ':tenant' => tenantId(),
            ]);
            return ['ok' => false, 'msg' => $res['msg']];
        }

        $d = $res['datos'];
        $this->db->prepare(
            "UPDATE factura
             SET arca_estado = 'APROBADO',
                 arca_numero_comprobante = :nro,
                 cae = :cae,
                 cae_vencimiento = :vto,
                 arca_observaciones = NULL,
                 arca_enviado_el = NOW()
             WHERE id = :id AND tenant_id = :tenant"
        )->execute([
            ':nro'    => (int)$d['numero_comprobante'],
            ':cae'    => $d['cae'],
            ':vto'    => $d['cae_vencimiento'],
            ':id'     => $facturaId,
            ':tenant' => tenantId(),
        ]);

        return [
            'ok'  => true,
            'msg' => 'Factura aprobada por ARCA. CAE ' . $d['cae'] . '.',
        ];
    }
}