<?php
// models/Compra.php

require_once __DIR__ . '/../config/conexion.php';

class Compra
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function listar(
        string $desde = '',
        string $hasta = '',
        int    $proveedorId = 0,
        string $busqueda = '',
        string $estado = ''
    ): array {
        $sql = "SELECT c.*,
                       p.razon_social AS proveedor_razon_social,
                       p.nombre_fantasia AS proveedor_fantasia,
                       p.cuit AS proveedor_cuit,
                       t.descripcion AS tipo_comprobante_desc,
                       t.letra AS tipo_comprobante_letra,
                       cc.nombre AS cuenta_contable_nombre
                FROM compra c
                INNER JOIN proveedor p ON p.id = c.proveedor_id
                LEFT JOIN tipos_comprobante_compra t ON t.id = c.tipo_comprobante_id
                LEFT JOIN cuenta_contable cc ON cc.id = c.cuenta_contable_id
                WHERE 1=1";
        $params = [];

        if ($desde !== '') {
            $sql .= " AND c.fecha_comprobante >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND c.fecha_comprobante <= :hasta";
            $params[':hasta'] = $hasta;
        }
        if ($proveedorId > 0) {
            $sql .= " AND c.proveedor_id = :pid";
            $params[':pid'] = $proveedorId;
        }
        if ($busqueda !== '') {
            $sql .= " AND (c.numero LIKE :q1
                        OR c.cae LIKE :q2
                        OR c.punto_venta LIKE :q3)";
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }
        if ($estado !== '' && in_array($estado, ['REGISTRADA', 'ANULADA'], true)) {
            $sql .= " AND c.estado = :est";
            $params[':est'] = $estado;
        }

        $sql .= " ORDER BY c.fecha_comprobante DESC, c.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT c.*,
                       p.razon_social AS proveedor_razon_social,
                       p.nombre_fantasia AS proveedor_fantasia,
                       p.cuit AS proveedor_cuit,
                       p.domicilio_fiscal AS proveedor_domicilio,
                       p.id_condicion_iva AS proveedor_condicion_iva_id,
                       ci.descripcion AS proveedor_condicion_iva,
                       t.descripcion AS tipo_comprobante_desc,
                       t.letra AS tipo_comprobante_letra,
                       cc.nombre AS cuenta_contable_nombre,
                       cc.codigo AS cuenta_contable_codigo
                FROM compra c
                INNER JOIN proveedor p ON p.id = c.proveedor_id
                LEFT JOIN condiciones_iva ci ON ci.id = p.id_condicion_iva
                LEFT JOIN tipos_comprobante_compra t ON t.id = c.tipo_comprobante_id
                LEFT JOIN cuenta_contable cc ON cc.id = c.cuenta_contable_id
                WHERE c.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerDetalle(int $compraId): array
    {
        $sql = "SELECT cd.*,
                       i.nombre AS insumo_nombre,
                       i.codigo AS insumo_codigo,
                       um.abreviatura AS unidad_abrev
                FROM compra_detalle cd
                LEFT JOIN insumo i ON i.id = cd.insumo_id
                LEFT JOIN unidades_medida um ON um.id = i.unidad_medida_id
                WHERE cd.compra_id = :id
                ORDER BY cd.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $compraId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CREAR (transacción)
       ========================================================== */
    public function crear(array $cabecera, array $detalle): int|false
    {
        if (empty($detalle)) return false;

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO compra
                    (proveedor_id, tipo_comprobante_id, punto_venta, numero,
                     fecha_comprobante, cae, cae_vencimiento, cuenta_contable_id,
                     primer_vencimiento, segundo_vencimiento,
                     archivo_path, archivo_nombre_original,
                     subtotal, neto_gravado, neto_no_gravado, neto_exento,
                     iva, perc_iva, perc_iibb, impuestos_internos, total,
                     observaciones, estado)
                    VALUES
                    (:proveedor_id, :tipo_comprobante_id, :punto_venta, :numero,
                     :fecha_comprobante, :cae, :cae_vencimiento, :cuenta_contable_id,
                     :primer_vencimiento, :segundo_vencimiento,
                     :archivo_path, :archivo_nombre_original,
                     :subtotal, :neto_gravado, :neto_no_gravado, :neto_exento,
                     :iva, :perc_iva, :perc_iibb, :impuestos_internos, :total,
                     :observaciones, 'REGISTRADA')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':proveedor_id'           => $cabecera['proveedor_id'],
                ':tipo_comprobante_id'    => $cabecera['tipo_comprobante_id'],
                ':punto_venta'            => $cabecera['punto_venta'],
                ':numero'                 => $cabecera['numero'],
                ':fecha_comprobante'      => $cabecera['fecha_comprobante'],
                ':cae'                    => $cabecera['cae'],
                ':cae_vencimiento'        => $cabecera['cae_vencimiento'],
                ':cuenta_contable_id'     => $cabecera['cuenta_contable_id'],
                ':primer_vencimiento'     => $cabecera['primer_vencimiento'],
                ':segundo_vencimiento'    => $cabecera['segundo_vencimiento'],
                ':archivo_path'           => $cabecera['archivo_path'],
                ':archivo_nombre_original'=> $cabecera['archivo_nombre_original'],
                ':subtotal'               => $cabecera['subtotal'],
                ':neto_gravado'           => $cabecera['neto_gravado']     ?? 0,
                ':neto_no_gravado'        => $cabecera['neto_no_gravado']  ?? 0,
                ':neto_exento'            => $cabecera['neto_exento']      ?? 0,
                ':iva'                    => $cabecera['iva'],
                ':perc_iva'               => $cabecera['perc_iva']         ?? 0,
                ':perc_iibb'              => $cabecera['perc_iibb']        ?? 0,
                ':impuestos_internos'     => $cabecera['impuestos_internos'],
                ':total'                  => $cabecera['total'],
                ':observaciones'          => $cabecera['observaciones'],
            ]);
            $compraId = (int)$this->db->lastInsertId();

            require_once __DIR__ . '/CuentaCorrienteProveedor.php';
            $ccProv = new CuentaCorrienteProveedor();
            $ccProv->registrar(
                (int)$cabecera['proveedor_id'],
                $cabecera['fecha_comprobante'],
                'COMPRA',
                $compraId,
                (float)$cabecera['total'],
                0.0,
                'Compra #' . $compraId,
                $_SESSION['user_id'] ?? null
            );

            $this->aplicarDetalle(
                $compraId,
                $detalle,
                (int)$cabecera['proveedor_id'],
                (int)$cabecera['tipo_comprobante_id'],
                false
            );

            $this->db->commit();

            // Fase 2: asiento contable automático
            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $rCont = $svc->asentarCompra($compraId);
                if (empty($rCont['ok'])) {
                    error_log('[Compra::crear] Asiento no generado para compra #' . $compraId
                            . ': ' . ($rCont['msg'] ?? 'desconocido'));
                }
            } catch (\Throwable $e) {
                error_log('[Compra::crear] Excepción en ContabilidadService: ' . $e->getMessage());
            }
            return $compraId;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Compra::crear] ' . $e->getMessage());
            return false;
        }
    }

    /* ==========================================================
       ACTUALIZAR (edición) — reversión + reaplicación
       ========================================================== */
    public function actualizar(int $compraId, array $cabecera, array $nuevoDetalle): array
    {
        try {
            $this->db->beginTransaction();

            $compra = $this->obtenerPorId($compraId);
            if (!$compra) {
                throw new Exception('Compra no encontrada.');
            }
            if ($compra['estado'] === 'ANULADA') {
                throw new Exception('No se puede editar una compra anulada.');
            }

            $detalleOriginal = $this->obtenerDetalle($compraId);

            $this->db->prepare(
                "UPDATE historial_valores
                    SET anulado = 1, anulado_el = NOW(), anulado_por = :u
                  WHERE referencia_compra_id = :cid
                    AND tipo = 'INSUMO_COSTO'
                    AND anulado = 0"
            )->execute([
                ':u'   => $_SESSION['user_id'] ?? null,
                ':cid' => $compraId,
            ]);

            $lineasParaRevertir = [];
            foreach ($detalleOriginal as $d) {
                if (!empty($d['insumo_id'])) {
                    $lineasParaRevertir[] = [
                        'insumo_id'      => (int)$d['insumo_id'],
                        'cantidad'       => (float)$d['cantidad'],
                        'costo_unitario' => (float)$d['costo_unitario'],
                    ];
                }
            }
            if (!empty($lineasParaRevertir)) {
                $this->aplicarDetalle(
                    $compraId,
                    $lineasParaRevertir,
                    (int)$compra['proveedor_id'],
                    (int)$compra['tipo_comprobante_id'],
                    true
                );
            }

            $this->db->prepare("DELETE FROM compra_detalle WHERE compra_id = :id")
                     ->execute([':id' => $compraId]);

            $sql = "UPDATE compra SET
                        proveedor_id          = :proveedor_id,
                        tipo_comprobante_id   = :tipo_comprobante_id,
                        punto_venta           = :punto_venta,
                        numero                = :numero,
                        fecha_comprobante     = :fecha_comprobante,
                        cae                   = :cae,
                        cae_vencimiento       = :cae_vencimiento,
                        cuenta_contable_id    = :cuenta_contable_id,
                        primer_vencimiento    = :primer_vencimiento,
                        segundo_vencimiento   = :segundo_vencimiento,
                        subtotal              = :subtotal,
                        neto_gravado          = :neto_gravado,
                        neto_no_gravado       = :neto_no_gravado,
                        neto_exento           = :neto_exento,
                        iva                   = :iva,
                        perc_iva              = :perc_iva,
                        perc_iibb             = :perc_iibb,
                        impuestos_internos    = :impuestos_internos,
                        total                 = :total,
                        observaciones         = :observaciones,
                        editado_el            = NOW(),
                        editado_por_id        = :user_id,
                        cantidad_ediciones    = cantidad_ediciones + 1
                    WHERE id = :id";
            $this->db->prepare($sql)->execute([
                ':proveedor_id'         => $cabecera['proveedor_id'],
                ':tipo_comprobante_id'  => $cabecera['tipo_comprobante_id'],
                ':punto_venta'          => $cabecera['punto_venta'],
                ':numero'               => $cabecera['numero'],
                ':fecha_comprobante'    => $cabecera['fecha_comprobante'],
                ':cae'                  => $cabecera['cae'],
                ':cae_vencimiento'      => $cabecera['cae_vencimiento'],
                ':cuenta_contable_id'   => $cabecera['cuenta_contable_id'],
                ':primer_vencimiento'   => $cabecera['primer_vencimiento'],
                ':segundo_vencimiento'  => $cabecera['segundo_vencimiento'],
                ':subtotal'             => $cabecera['subtotal'],
                ':neto_gravado'         => $cabecera['neto_gravado']     ?? 0,
                ':neto_no_gravado'      => $cabecera['neto_no_gravado']  ?? 0,
                ':neto_exento'          => $cabecera['neto_exento']      ?? 0,
                ':iva'                  => $cabecera['iva'],
                ':perc_iva'             => $cabecera['perc_iva']         ?? 0,
                ':perc_iibb'            => $cabecera['perc_iibb']        ?? 0,
                ':impuestos_internos'   => $cabecera['impuestos_internos'],
                ':total'                => $cabecera['total'],
                ':observaciones'        => $cabecera['observaciones'],
                ':user_id'              => $_SESSION['user_id'] ?? null,
                ':id'                   => $compraId,
            ]);

            $this->aplicarDetalle(
                $compraId,
                $nuevoDetalle,
                (int)$cabecera['proveedor_id'],
                (int)$cabecera['tipo_comprobante_id'],
                false
            );

            $this->ajustarCCProveedor($compraId, $cabecera);

            $this->db->commit();

            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $svc->anularAsientoPorReferencia('compra', $compraId, 'Edición de compra');
                $rCont = $svc->asentarCompra($compraId);
                if (empty($rCont['ok'])) {
                    error_log('[Compra::actualizar] Asiento no regenerado para compra #' . $compraId
                            . ': ' . ($rCont['msg'] ?? 'desconocido'));
                }
            } catch (\Throwable $e) {
                error_log('[Compra::actualizar] Excepción en ContabilidadService: ' . $e->getMessage());
            }
            return ['ok' => true];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Compra::actualizar] ' . $e->getMessage());
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    private function ajustarCCProveedor(int $compraId, array $cabecera): void
    {
        require_once __DIR__ . '/CuentaCorrienteProveedor.php';
        $cc = new CuentaCorrienteProveedor();

        $cc->anularPorReferencia($compraId, 'COMPRA', (int)($_SESSION['user_id'] ?? 0));
        $cc->registrar(
            (int)$cabecera['proveedor_id'],
            $cabecera['fecha_comprobante'],
            'COMPRA',
            $compraId,
            (float)$cabecera['total'],
            0.0,
            'Compra #' . $compraId . ' (editada)',
            $_SESSION['user_id'] ?? null
        );
    }

    /* ==========================================================
       APLICAR / REVERTIR DETALLE
       ========================================================== */
    private function aplicarDetalle(
        int $compraId,
        array $detalle,
        int $proveedorId,
        int $tipoComprobanteId,
        bool $revertir
    ): void {
        $esNotaCredito = $this->esNotaCredito($tipoComprobanteId);
        $sumaStock = $revertir ? $esNotaCredito : !$esNotaCredito;

        $stmtDet = $this->db->prepare(
            "INSERT INTO compra_detalle
                (compra_id, insumo_id, concepto, cantidad, costo_unitario, subtotal_linea,
                 condicion_iva, alicuota_iva, importe_iva)
             VALUES (:c, :i, :cn, :q, :cu, :st, :cond, :al, :imp)"
        );

        foreach ($detalle as $linea) {
            $insumoId = !empty($linea['insumo_id']) ? (int)$linea['insumo_id'] : null;
            $concepto = trim((string)($linea['concepto'] ?? '')) ?: null;
            $cantidad = (float)$linea['cantidad'];
            $costo    = (float)$linea['costo_unitario'];
            $subtotal = round($cantidad * $costo, 4);

            // Fiscal
            $condicionIva = strtoupper((string)($linea['condicion_iva'] ?? 'GRAVADO'));
            if (!in_array($condicionIva, ['GRAVADO','NO_GRAVADO','EXENTO'], true)) {
                $condicionIva = 'GRAVADO';
            }
            $alicuota = (float)($linea['alicuota_iva'] ?? 21.00);
            if ($condicionIva !== 'GRAVADO') {
                $alicuota = 0.0;
                $importeIva = 0.0;
            } else {
                $importeIva = round($subtotal * $alicuota / 100, 4);
            }

            if (!$revertir) {
                $stmtDet->execute([
                    ':c'    => $compraId,
                    ':i'    => $insumoId,
                    ':cn'   => $concepto,
                    ':q'    => $cantidad,
                    ':cu'   => $costo,
                    ':st'   => $subtotal,
                    ':cond' => $condicionIva,
                    ':al'   => $alicuota,
                    ':imp'  => $importeIva,
                ]);
            }

            if ($insumoId === null) {
                continue;
            }

            if ($sumaStock) {
                $this->db->prepare("UPDATE insumo SET stock = stock + :q WHERE id = :id")
                         ->execute([':q' => $cantidad, ':id' => $insumoId]);
            } else {
                $this->db->prepare("UPDATE insumo SET stock = stock - :q WHERE id = :id")
                         ->execute([':q' => $cantidad, ':id' => $insumoId]);
            }

            if (!$revertir) {
                $this->upsertCostoProveedor($insumoId, $proveedorId, $costo);
            }

            $this->recalcularInsumo($insumoId, $compraId);
        }
    }

    private function upsertCostoProveedor(int $insumoId, int $proveedorId, float $costo): void
    {
        $sql = "INSERT INTO insumo_proveedor (insumo_id, proveedor_id, costo, actualizado_el)
                VALUES (:i, :p, :c, NOW())
                ON DUPLICATE KEY UPDATE
                    costo = VALUES(costo),
                    actualizado_el = NOW()";
        $this->db->prepare($sql)->execute([':i' => $insumoId, ':p' => $proveedorId, ':c' => $costo]);
    }

    private function recalcularInsumo(int $insumoId, ?int $compraId = null): void
    {
        if (!class_exists('Insumo')) {
            $p = __DIR__ . '/Insumo.php';
            if (file_exists($p)) require_once $p;
        }
        if (!class_exists('Insumo')) return;

        (new Insumo())->recalcularCosto($insumoId);

        if ($compraId) {
            $this->db->prepare(
                "UPDATE historial_valores
                    SET referencia_compra_id = :cid
                  WHERE tipo = 'INSUMO_COSTO'
                    AND referencia_id = :iid
                    AND referencia_compra_id IS NULL
                    AND anulado = 0"
            )->execute([':cid' => $compraId, ':iid' => $insumoId]);
        }
    }

    private function esNotaCredito(int $tipoComprobanteId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT codigo_afip FROM tipos_comprobante_compra WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $tipoComprobanteId]);
        $codigo = (string)$stmt->fetchColumn();
        return in_array($codigo, ['003', '008', '013'], true);
    }

    /* ==========================================================
       ANULAR
       ========================================================== */
    public function anular(int $compraId): array
    {
        try {
            $this->db->beginTransaction();

            $compra = $this->obtenerPorId($compraId);
            if (!$compra) {
                $this->db->rollBack();
                return ['ok' => false, 'msg' => 'La compra no existe.'];
            }
            if ($compra['estado'] === 'ANULADA') {
                $this->db->rollBack();
                return ['ok' => false, 'msg' => 'La compra ya estaba anulada.'];
            }

            $detalle = $this->obtenerDetalle($compraId);

            $lineas = [];
            foreach ($detalle as $d) {
                if (!empty($d['insumo_id'])) {
                    $lineas[] = [
                        'insumo_id'      => (int)$d['insumo_id'],
                        'cantidad'       => (float)$d['cantidad'],
                        'costo_unitario' => (float)$d['costo_unitario'],
                    ];
                }
            }
            if (!empty($lineas)) {
                $this->aplicarDetalle(
                    $compraId,
                    $lineas,
                    (int)$compra['proveedor_id'],
                    (int)$compra['tipo_comprobante_id'],
                    true
                );
            }

            $this->db->prepare(
                "UPDATE historial_valores
                    SET anulado = 1, anulado_el = NOW(), anulado_por = :u
                  WHERE referencia_compra_id = :cid
                    AND tipo = 'INSUMO_COSTO'
                    AND anulado = 0"
            )->execute([
                ':u'   => $_SESSION['user_id'] ?? null,
                ':cid' => $compraId,
            ]);

            $this->db->prepare("UPDATE compra SET estado = 'ANULADA' WHERE id = :id")
                     ->execute([':id' => $compraId]);

            require_once __DIR__ . '/CuentaCorrienteProveedor.php';
            $ccProv = new CuentaCorrienteProveedor();
            $ccProv->anularPorReferencia($compraId, 'COMPRA', (int)($_SESSION['user_id'] ?? 0));

            $this->db->commit();

            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $svc->anularAsientoPorReferencia('compra', $compraId, 'Anulación de compra');
            } catch (\Throwable $e) {
                error_log('[Compra::anular] Error al anular asiento: ' . $e->getMessage());
            }

            return ['ok' => true, 'msg' => 'Compra anulada. Stock revertido.'];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Compra::anular] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'No se pudo anular la compra.'];
        }
    }

    /* ==========================================================
       ADJUNTO
       ========================================================== */
    public function actualizarAdjunto(int $id, ?string $path, ?string $nombreOriginal): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE compra SET archivo_path = :p, archivo_nombre_original = :n WHERE id = :id"
        );
        return $stmt->execute([':p' => $path, ':n' => $nombreOriginal, ':id' => $id]);
    }

    /* ==========================================================
       COMBOS
       ========================================================== */
    public function combos(): array
    {
        return [
            'proveedores' => $this->db->query(
                "SELECT id, razon_social, nombre_fantasia, cuit
                 FROM proveedor WHERE estado = 'ACTIVO' ORDER BY razon_social"
            )->fetchAll(),
            'tipos_comprobante' => $this->db->query(
                "SELECT id, codigo_afip, descripcion, letra
                 FROM tipos_comprobante_compra
                 WHERE id <= 11
                 ORDER BY id"
            )->fetchAll(),
            'cuentas_contables' => $this->db->query(
                "SELECT id, codigo, nombre, plan_cuenta_id
                 FROM cuenta_contable
                 WHERE estado = 'ACTIVA' ORDER BY codigo"
            )->fetchAll(),
            'insumos' => $this->db->query(
                "SELECT i.id, i.nombre, i.codigo, i.costo,
                        um.abreviatura AS unidad_abrev
                 FROM insumo i
                 INNER JOIN unidades_medida um ON um.id = i.unidad_medida_id
                 WHERE i.estado = 'ACTIVO'
                 ORDER BY i.nombre"
            )->fetchAll(),
        ];
    }
}