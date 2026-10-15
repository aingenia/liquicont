<?php
// models/Pago.php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/CuentaCorrienteProveedor.php';

class Pago
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
        $sql = "SELECT p.*,
                       pr.razon_social AS proveedor_razon_social,
                       pr.nombre_fantasia AS proveedor_fantasia,
                       pr.cuit AS proveedor_cuit,
                       u.nombre AS creado_por_nombre,
                       (SELECT COUNT(*) FROM pago_compra WHERE pago_id = p.id) AS compras_count
                FROM pago p
                INNER JOIN proveedor pr ON pr.id = p.proveedor_id
                LEFT JOIN usuario u ON u.id = p.creado_por_id
                WHERE 1=1";
        $params = [];

        if ($desde !== '') {
            $sql .= " AND p.fecha >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND p.fecha <= :hasta";
            $params[':hasta'] = $hasta;
        }
        if ($proveedorId > 0) {
            $sql .= " AND p.proveedor_id = :pid";
            $params[':pid'] = $proveedorId;
        }
        if ($busqueda !== '') {
            $sql .= " AND (pr.razon_social LIKE :q1
                        OR pr.nombre_fantasia LIKE :q2
                        OR pr.cuit LIKE :q3
                        OR p.id = :q4)";
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = (int)$busqueda;
        }
        if ($estado !== '' && in_array($estado, ['ACTIVO', 'ANULADO'], true)) {
            $sql .= " AND p.estado = :est";
            $params[':est'] = $estado;
        }

        $sql .= " ORDER BY p.fecha DESC, p.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       OBTENER
       ========================================================== */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT p.*,
                       pr.razon_social AS proveedor_razon_social,
                       pr.nombre_fantasia AS proveedor_fantasia,
                       pr.cuit AS proveedor_cuit,
                       pr.domicilio_fiscal AS proveedor_domicilio,
                       u.nombre AS creado_por_nombre,
                       ua.nombre AS anulado_por_nombre
                FROM pago p
                INNER JOIN proveedor pr ON pr.id = p.proveedor_id
                LEFT JOIN usuario u ON u.id = p.creado_por_id
                LEFT JOIN usuario ua ON ua.id = p.anulado_por_id
                WHERE p.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerMedios(int $pagoId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM pago_medio WHERE pago_id = :id ORDER BY id");
        $stmt->execute([':id' => $pagoId]);
        return $stmt->fetchAll();
    }

    public function obtenerComprasAplicadas(int $pagoId): array
    {
        $sql = "SELECT pc.*,
                       c.fecha_comprobante, c.total AS compra_total,
                       c.punto_venta, c.numero, c.cae,
                       t.descripcion AS tipo_comprobante_desc
                FROM pago_compra pc
                INNER JOIN compra c ON c.id = pc.compra_id
                LEFT JOIN tipos_comprobante_compra t ON t.id = c.tipo_comprobante_id
                WHERE pc.pago_id = :id
                ORDER BY pc.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $pagoId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       COMPRAS PENDIENTES DE PAGO
       ========================================================== */
        public function comprasPendientes(int $proveedorId): array
    {
        $sql = "SELECT * FROM (
                    SELECT c.id, c.fecha_comprobante, c.total,
                           c.punto_venta, c.numero, c.cae,
                           c.primer_vencimiento, c.segundo_vencimiento,
                           t.descripcion AS tipo_comprobante_desc, t.letra,
                           COALESCE(
                               (SELECT SUM(importe_aplicado) FROM pago_compra
                                WHERE compra_id = c.id
                                  AND pago_id IN (SELECT id FROM pago WHERE estado = 'ACTIVO')),
                               0
                           ) AS pagado,
                           c.total - COALESCE(
                               (SELECT SUM(importe_aplicado) FROM pago_compra
                                WHERE compra_id = c.id
                                  AND pago_id IN (SELECT id FROM pago WHERE estado = 'ACTIVO')),
                               0
                           ) AS saldo_pendiente
                    FROM compra c
                    LEFT JOIN tipos_comprobante_compra t ON t.id = c.tipo_comprobante_id
                    WHERE c.proveedor_id = :pid
                      AND c.estado = 'REGISTRADA'
                ) AS sub
                WHERE saldo_pendiente > 0.004
                ORDER BY fecha_comprobante ASC, id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':pid' => $proveedorId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CREAR PAGO (transacción)
       ========================================================== */
    public function crear(
        array $cabecera,
        array $medios,
        array $imputacion,
        int $usuarioId
    ): array {
        if (empty($medios)) {
            return ['ok' => false, 'msg' => 'Cargá al menos un medio de pago.'];
        }
        if (empty($imputacion)) {
            return ['ok' => false, 'msg' => 'Imputá el pago a al menos una compra.'];
        }

        // Validar totales
        $totalMedios = 0;
        foreach ($medios as $m) {
            $totalMedios += (float)($m['importe'] ?? 0);
        }
        $totalImputado = 0;
        foreach ($imputacion as $i) {
            $totalImputado += (float)($i['importe_aplicado'] ?? 0);
        }
        $totalMedios   = round($totalMedios, 2);
        $totalImputado = round($totalImputado, 2);

        if (abs($totalMedios - $totalImputado) > 0.01) {
            return [
                'ok'  => false,
                'msg' => 'El total de medios ($' . number_format($totalMedios, 2, ',', '.')
                       . ') no coincide con el total imputado ($' . number_format($totalImputado, 2, ',', '.') . ').',
            ];
        }

        try {
            $this->db->beginTransaction();

            // 1) Cabecera
            $sql = "INSERT INTO pago (proveedor_id, fecha, total, notas, creado_por_id)
                    VALUES (:pid, :fecha, :total, :notas, :uid)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':pid'   => (int)$cabecera['proveedor_id'],
                ':fecha' => $cabecera['fecha'],
                ':total' => $totalMedios,
                ':notas' => $cabecera['notas'] ?? null,
                ':uid'   => $usuarioId,
            ]);
            $pagoId = (int)$this->db->lastInsertId();

            // 2) Medios + cheques propios en calendario
            $stmtMedio = $this->db->prepare(
                "INSERT INTO pago_medio
                 (pago_id, medio, importe,
                  cheque_banco, cheque_numero, cheque_fecha_cobro, cheque_titular,
                  retencion_tipo, retencion_nro,
                  calendario_cheque_id, observaciones)
                 VALUES
                 (:pid, :medio, :importe,
                  :cb, :cn, :cfc, :ct,
                  :rt, :rn,
                  :cal, :obs)"
            );

            foreach ($medios as $m) {
                $calendarioChequeId = null;

                if ($m['medio'] === 'CHEQUE' && !empty($m['cheque_fecha_cobro'])) {
                    $stmtCal = $this->db->prepare(
                        "INSERT INTO calendario_cheques
                         (fecha, descripcion, importe, banco, nro_cheque, tipo, creado_por_id)
                         VALUES (:fecha, :desc, :imp, :banco, :nro, 'PROPIO', :uid)"
                    );
                    $descripcion = 'Cheque propio a ' . ($m['cheque_titular'] ?? 'proveedor')
                                 . ' - N° ' . ($m['cheque_numero'] ?? '');
                    $stmtCal->execute([
                        ':fecha' => $m['cheque_fecha_cobro'],
                        ':desc'  => $descripcion,
                        ':imp'   => (float)$m['importe'],
                        ':banco' => $m['cheque_banco']  ?? null,
                        ':nro'   => $m['cheque_numero'] ?? null,
                        ':uid'   => $usuarioId,
                    ]);
                    $calendarioChequeId = (int)$this->db->lastInsertId();
                }

                $stmtMedio->execute([
                    ':pid'     => $pagoId,
                    ':medio'   => $m['medio'],
                    ':importe' => (float)$m['importe'],
                    ':cb'      => $m['cheque_banco']       ?? null,
                    ':cn'      => $m['cheque_numero']      ?? null,
                    ':cfc'     => $m['cheque_fecha_cobro'] ?? null,
                    ':ct'      => $m['cheque_titular']     ?? null,
                    ':rt'      => $m['retencion_tipo']     ?? null,
                    ':rn'      => $m['retencion_nro']      ?? null,
                    ':cal'     => $calendarioChequeId,
                    ':obs'     => $m['observaciones']      ?? null,
                ]);
            }

            // 3) Imputación a compras
            $stmtImp = $this->db->prepare(
                "INSERT INTO pago_compra (pago_id, compra_id, importe_aplicado)
                 VALUES (:pid, :cid, :imp)"
            );
            foreach ($imputacion as $i) {
                $stmtImp->execute([
                    ':pid' => $pagoId,
                    ':cid' => (int)$i['compra_id'],
                    ':imp' => (float)$i['importe_aplicado'],
                ]);
            }

            // 4) Movimiento en cuenta corriente proveedor
            //    (HABER = reduce lo que le debemos)
            $cc = new CuentaCorrienteProveedor();
            $cc->registrar(
                (int)$cabecera['proveedor_id'],
                $cabecera['fecha'],
                'PAGO',
                $pagoId,
                0.0,
                $totalMedios,
                'Pago #' . $pagoId,
                $usuarioId
            );

            $this->db->commit();

            // Fase 2: asiento contable automático
            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $rCont = $svc->asentarPago($pagoId);
                if (empty($rCont['ok'])) {
                    error_log('[Pago::crear] Asiento no generado para pago #' . $pagoId
                            . ': ' . ($rCont['msg'] ?? 'desconocido'));
                }
            } catch (\Throwable $e) {
                error_log('[Pago::crear] Excepción en ContabilidadService: ' . $e->getMessage());
            }

            return [
                'ok'      => true,
                'msg'     => 'Pago registrado con éxito.',
                'pago_id' => $pagoId,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Pago::crear] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al guardar el pago: ' . $e->getMessage()];
        }
    }

    /* ==========================================================
       ANULAR PAGO
       ========================================================== */
    public function anular(int $pagoId, string $motivo, int $usuarioId): array
    {
        $pago = $this->obtenerPorId($pagoId);
        if (!$pago) {
            return ['ok' => false, 'msg' => 'El pago no existe.'];
        }
        if ($pago['estado'] === 'ANULADO') {
            return ['ok' => false, 'msg' => 'El pago ya estaba anulado.'];
        }

        try {
            $this->db->beginTransaction();

            $this->db->prepare(
                "UPDATE pago
                 SET estado = 'ANULADO', anulado_el = NOW(),
                     anulado_por_id = :uid, anulado_motivo = :mot
                 WHERE id = :id"
            )->execute([
                ':uid' => $usuarioId,
                ':mot' => $motivo ?: null,
                ':id'  => $pagoId,
            ]);

            // Anular movimiento CC
            $cc = new CuentaCorrienteProveedor();
            $cc->anularPorReferencia($pagoId, 'PAGO', $usuarioId);

            // Eliminar cheques propios del calendario
            $stmt = $this->db->prepare(
                "SELECT calendario_cheque_id FROM pago_medio
                 WHERE pago_id = :id AND calendario_cheque_id IS NOT NULL"
            );
            $stmt->execute([':id' => $pagoId]);
            $idsCheques = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($idsCheques)) {
                $placeholders = implode(',', array_fill(0, count($idsCheques), '?'));
                $this->db->prepare("DELETE FROM calendario_cheques WHERE id IN ($placeholders)")
                         ->execute($idsCheques);
            }

            $this->db->commit();

            // Fase 2: anular el asiento contable asociado
            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $svc->anularAsientoPorReferencia('pago', $pagoId,
                    'Anulación de pago: ' . ($motivo ?: 'sin motivo'));
            } catch (\Throwable $e) {
                error_log('[Pago::anular] Error al anular asiento: ' . $e->getMessage());
            }

            return ['ok' => true, 'msg' => 'Pago anulado. Se revirtió la cuenta corriente y se eliminaron los cheques propios.'];

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Pago::anular] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al anular el pago.'];
        }
    }

    /* ==========================================================
       COMBOS
       ========================================================== */
    public function combos(): array
    {
        $proveedores = $this->db->query(
            "SELECT id, razon_social, nombre_fantasia, cuit
             FROM proveedor WHERE estado = 'ACTIVO' ORDER BY razon_social"
        )->fetchAll();

        return ['proveedores' => $proveedores];
    }

        /* ==========================================================
       LISTAR COMPRAS CON ESTADO DE PAGO
       ========================================================== */
    /**
     * Devuelve compras con su estado de pago calculado.
     *
     * @param string $estadoPago  '' | 'PENDIENTE' | 'PARCIAL' | 'PAGADA' | 'ANULADA'
     */
    public function listarComprasConEstado(
        string $desde = '',
        string $hasta = '',
        int $proveedorId = 0,
        string $estadoPago = '',
        string $busqueda = ''
    ): array {
        $sql = "SELECT * FROM (
                    SELECT c.id, c.fecha_comprobante, c.total, c.estado,
                           c.punto_venta, c.numero, c.cae,
                           c.primer_vencimiento, c.segundo_vencimiento,
                           p.id AS proveedor_id,
                           p.razon_social AS proveedor_razon_social,
                           p.nombre_fantasia AS proveedor_fantasia,
                           p.cuit AS proveedor_cuit,
                           t.descripcion AS tipo_comprobante_desc, t.letra,
                           COALESCE(
                               (SELECT SUM(importe_aplicado) FROM pago_compra
                                WHERE compra_id = c.id
                                  AND pago_id IN (SELECT id FROM pago WHERE estado = 'ACTIVO')),
                               0
                           ) AS pagado,
                           c.total - COALESCE(
                               (SELECT SUM(importe_aplicado) FROM pago_compra
                                WHERE compra_id = c.id
                                  AND pago_id IN (SELECT id FROM pago WHERE estado = 'ACTIVO')),
                               0
                           ) AS saldo_pendiente
                    FROM compra c
                    INNER JOIN proveedor p ON p.id = c.proveedor_id
                    LEFT JOIN tipos_comprobante_compra t ON t.id = c.tipo_comprobante_id
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
            $sql .= " AND (p.razon_social LIKE :q1
                        OR p.nombre_fantasia LIKE :q2
                        OR c.numero LIKE :q3
                        OR c.cae LIKE :q4
                        OR c.id = :q5)";
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = (int)$busqueda;
        }

        $sql .= ") AS sub WHERE 1=1";

        // Filtro de estado de pago
        if ($estadoPago === 'POR_PAGAR') {
            // Pendientes + parciales (excluye pagadas y anuladas)
            $sql .= " AND sub.saldo_pendiente > 0.004 AND sub.estado = 'REGISTRADA'";
        } elseif ($estadoPago === 'PENDIENTE') {
            $sql .= " AND sub.pagado = 0 AND sub.estado = 'REGISTRADA'";
        } elseif ($estadoPago === 'PARCIAL') {
            $sql .= " AND sub.pagado > 0 AND sub.saldo_pendiente > 0.004 AND sub.estado = 'REGISTRADA'";
        } elseif ($estadoPago === 'PAGADA') {
            $sql .= " AND sub.saldo_pendiente <= 0.004 AND sub.estado = 'REGISTRADA'";
        } elseif ($estadoPago === 'ANULADA') {
            $sql .= " AND sub.estado = 'ANULADA'";
        }

        $sql .= " ORDER BY sub.fecha_comprobante DESC, sub.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}