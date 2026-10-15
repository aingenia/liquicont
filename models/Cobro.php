<?php
// models/Cobro.php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/CuentaCorriente.php';

class Cobro
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
        int    $clienteId = 0,
        string $busqueda = '',
        string $estado = ''
    ): array {
        $sql = "SELECT c.*,
                       cl.razon_social AS cliente_razon_social,
                       cl.nombre_fantasia AS cliente_fantasia,
                       cl.cuit AS cliente_cuit,
                       u.nombre AS creado_por_nombre,
                       (SELECT COUNT(*) FROM cobro_factura WHERE cobro_id = c.id) AS facturas_count
                FROM cobro c
                INNER JOIN cliente cl ON cl.id = c.cliente_id
                LEFT JOIN usuario u ON u.id = c.creado_por_id
                WHERE 1=1";
        $params = [];

        if ($desde !== '') {
            $sql .= " AND c.fecha >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND c.fecha <= :hasta";
            $params[':hasta'] = $hasta;
        }
        if ($clienteId > 0) {
            $sql .= " AND c.cliente_id = :cid";
            $params[':cid'] = $clienteId;
        }
        if ($busqueda !== '') {
            $sql .= " AND (cl.razon_social LIKE :q1
                        OR cl.nombre_fantasia LIKE :q2
                        OR cl.cuit LIKE :q3
                        OR c.id = :q4)";
            $like = '%' . $busqueda . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = (int)$busqueda;
        }
        if ($estado !== '' && in_array($estado, ['ACTIVO', 'ANULADO'], true)) {
            $sql .= " AND c.estado = :est";
            $params[':est'] = $estado;
        }

        $sql .= " ORDER BY c.fecha DESC, c.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       OBTENER
       ========================================================== */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT c.*,
                       cl.razon_social AS cliente_razon_social,
                       cl.nombre_fantasia AS cliente_fantasia,
                       cl.cuit AS cliente_cuit,
                       cl.domicilio_fiscal AS cliente_domicilio,
                       u.nombre AS creado_por_nombre,
                       ua.nombre AS anulado_por_nombre
                FROM cobro c
                INNER JOIN cliente cl ON cl.id = c.cliente_id
                LEFT JOIN usuario u ON u.id = c.creado_por_id
                LEFT JOIN usuario ua ON ua.id = c.anulado_por_id
                WHERE c.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerMedios(int $cobroId): array
    {
        $sql = "SELECT cm.*, cc.numero_cheque_calendario
                FROM cobro_medio cm
                LEFT JOIN (
                    SELECT id, id AS numero_cheque_calendario FROM calendario_cheques
                ) cc ON cc.id = cm.calendario_cheque_id
                WHERE cm.cobro_id = :id
                ORDER BY cm.id";
        // Nota: simplificamos el SELECT porque el JOIN de arriba era complejo
        // Vamos con lo básico:
        $sql = "SELECT * FROM cobro_medio WHERE cobro_id = :id ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $cobroId]);
        return $stmt->fetchAll();
    }

    public function obtenerFacturasAplicadas(int $cobroId): array
    {
        $sql = "SELECT cf.*,
                       f.fecha AS factura_fecha,
                       f.total AS factura_total,
                       f.remito_prefijo, f.remito_punto_venta, f.remito_numero,
                       f.arca_tipo_comprobante_letra, f.arca_punto_venta, f.arca_numero_comprobante
                FROM cobro_factura cf
                INNER JOIN factura f ON f.id = cf.factura_id
                WHERE cf.cobro_id = :id
                ORDER BY cf.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $cobroId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       FACTURAS PENDIENTES DE UN CLIENTE
       ========================================================== */
    /**
     * Lista facturas ACTIVAS con saldo pendiente del cliente.
     */
        public function facturasPendientes(int $clienteId): array
    {
        $sql = "SELECT * FROM (
                    SELECT f.id, f.fecha, f.total,
                           f.remito_prefijo, f.remito_punto_venta, f.remito_numero,
                           f.arca_tipo_comprobante_letra, f.arca_punto_venta,
                           f.arca_numero_comprobante, f.cae,
                           COALESCE(
                               (SELECT SUM(importe_aplicado) FROM cobro_factura
                                WHERE factura_id = f.id
                                  AND cobro_id IN (SELECT id FROM cobro WHERE estado = 'ACTIVO')),
                               0
                           ) AS cobrado,
                           f.total - COALESCE(
                               (SELECT SUM(importe_aplicado) FROM cobro_factura
                                WHERE factura_id = f.id
                                  AND cobro_id IN (SELECT id FROM cobro WHERE estado = 'ACTIVO')),
                               0
                           ) AS saldo_pendiente
                    FROM factura f
                    WHERE f.cliente_id = :cid
                      AND f.estado = 'ACTIVA'
                ) AS sub
                WHERE saldo_pendiente > 0.004
                ORDER BY fecha ASC, id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cid' => $clienteId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CREAR COBRO (transacción)
       ========================================================== */
    /**
     * Crea un cobro completo:
     *   - cabecera
     *   - medios de pago (uno o varios)
     *   - imputación a facturas
     *   - movimientos de cuenta corriente
     *   - (opcional) altas en calendario de cheques
     *
     * @param array $cabecera   ['cliente_id', 'fecha', 'notas']
     * @param array $medios     [['medio','importe', ...campos opcionales], ...]
     * @param array $imputacion [['factura_id','importe_aplicado'], ...]
     */
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
            return ['ok' => false, 'msg' => 'Imputá el cobro a al menos una factura.'];
        }

        // Validar que el total de medios coincida con el total de imputación
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

            // 1) Cabecera del cobro
            $sql = "INSERT INTO cobro (cliente_id, fecha, total, notas, creado_por_id)
                    VALUES (:cid, :fecha, :total, :notas, :uid)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':cid'   => (int)$cabecera['cliente_id'],
                ':fecha' => $cabecera['fecha'],
                ':total' => $totalMedios,
                ':notas' => $cabecera['notas'] ?? null,
                ':uid'   => $usuarioId,
            ]);
            $cobroId = (int)$this->db->lastInsertId();

            // 2) Medios de pago + cheques en calendario
            $stmtMedio = $this->db->prepare(
                "INSERT INTO cobro_medio
                 (cobro_id, medio, importe,
                  cheque_banco, cheque_numero, cheque_fecha_cobro, cheque_titular,
                  retencion_tipo, retencion_nro,
                  calendario_cheque_id, observaciones)
                 VALUES
                 (:cid, :medio, :importe,
                  :cb, :cn, :cfc, :ct,
                  :rt, :rn,
                  :cal, :obs)"
            );

            foreach ($medios as $m) {
                $calendarioChequeId = null;

                // Si es cheque con fecha de cobro, crear el registro en el calendario
                if ($m['medio'] === 'CHEQUE' && !empty($m['cheque_fecha_cobro'])) {
                    $stmtCal = $this->db->prepare(
                        "INSERT INTO calendario_cheques
                         (fecha, descripcion, importe, banco, nro_cheque, tipo, creado_por_id)
                         VALUES (:fecha, :desc, :imp, :banco, :nro, :tipo, :uid)"
                    );
                    $descripcion = 'Cobro ' . ($m['cheque_titular'] ?? 'cliente')
                                 . ' - Cheque ' . ($m['cheque_numero'] ?? '');
                    $stmtCal->execute([
                        ':fecha' => $m['cheque_fecha_cobro'],
                        ':desc'  => $descripcion,
                        ':imp'   => (float)$m['importe'],
                        ':banco' => $m['cheque_banco']   ?? null,
                        ':nro'   => $m['cheque_numero']  ?? null,
                        ':tipo'  => 'TERCERO', // asumimos tercero por defecto
                        ':uid'   => $usuarioId,
                    ]);
                    $calendarioChequeId = (int)$this->db->lastInsertId();
                }

                $stmtMedio->execute([
                    ':cid'     => $cobroId,
                    ':medio'   => $m['medio'],
                    ':importe' => (float)$m['importe'],
                    ':cb'      => $m['cheque_banco']        ?? null,
                    ':cn'      => $m['cheque_numero']       ?? null,
                    ':cfc'     => $m['cheque_fecha_cobro']  ?? null,
                    ':ct'      => $m['cheque_titular']      ?? null,
                    ':rt'      => $m['retencion_tipo']      ?? null,
                    ':rn'      => $m['retencion_nro']       ?? null,
                    ':cal'     => $calendarioChequeId,
                    ':obs'     => $m['observaciones']       ?? null,
                ]);
            }

            // 3) Imputación a facturas
            $stmtImp = $this->db->prepare(
                "INSERT INTO cobro_factura (cobro_id, factura_id, importe_aplicado)
                 VALUES (:cid, :fid, :imp)"
            );
            foreach ($imputacion as $i) {
                $stmtImp->execute([
                    ':cid' => $cobroId,
                    ':fid' => (int)$i['factura_id'],
                    ':imp' => (float)$i['importe_aplicado'],
                ]);
            }

            // 4) Movimiento en cuenta corriente (HABER = reduce deuda del cliente)
            $cc = new CuentaCorriente();
            $cc->registrar(
                (int)$cabecera['cliente_id'],
                $cabecera['fecha'],
                'COBRO',
                $cobroId,
                0.0,
                $totalMedios,
                'Cobro #' . $cobroId,
                $usuarioId
            );

            $this->db->commit();

            // Fase 2: asiento contable automático
            require_once ROOT_PATH . 'includes' . DS . 'ContabilidadService.php';
            try {
                $svc = new ContabilidadService();
                $rCont = $svc->asentarCobro($cobroId);
                if (empty($rCont['ok'])) {
                    error_log('[Cobro::crear] Asiento no generado para cobro #' . $cobroId
                            . ': ' . ($rCont['msg'] ?? 'desconocido'));
                }
            } catch (\Throwable $e) {
                error_log('[Cobro::crear] Excepción en ContabilidadService: ' . $e->getMessage());
            }

            return [
                'ok'       => true,
                'msg'      => 'Cobro registrado con éxito.',
                'cobro_id' => $cobroId,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Cobro::crear] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al guardar el cobro: ' . $e->getMessage()];
        }
    }

    /* ==========================================================
       ANULAR COBRO
       ========================================================== */
    public function anular(int $cobroId, string $motivo, int $usuarioId): array
    {
        $cobro = $this->obtenerPorId($cobroId);
        if (!$cobro) {
            return ['ok' => false, 'msg' => 'El cobro no existe.'];
        }
        if ($cobro['estado'] === 'ANULADO') {
            return ['ok' => false, 'msg' => 'El cobro ya estaba anulado.'];
        }

        try {
            $this->db->beginTransaction();

            // 1) Marcar cobro como anulado
            $this->db->prepare(
                "UPDATE cobro
                 SET estado = 'ANULADO', anulado_el = NOW(),
                     anulado_por_id = :uid, anulado_motivo = :mot
                 WHERE id = :id"
            )->execute([
                ':uid' => $usuarioId,
                ':mot' => $motivo ?: null,
                ':id'  => $cobroId,
            ]);

            // 2) Anular movimientos de cuenta corriente asociados
            $cc = new CuentaCorriente();
            $cc->anularPorReferencia($cobroId, 'COBRO', $usuarioId);

            // 3) Eliminar los cheques del calendario que se crearon
            $stmt = $this->db->prepare(
                "SELECT calendario_cheque_id FROM cobro_medio
                 WHERE cobro_id = :id AND calendario_cheque_id IS NOT NULL"
            );
            $stmt->execute([':id' => $cobroId]);
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
                $svc->anularAsientoPorReferencia('cobro', $cobroId,
                    'Anulación de cobro: ' . ($motivo ?: 'sin motivo'));
            } catch (\Throwable $e) {
                error_log('[Cobro::anular] Error al anular asiento: ' . $e->getMessage());
            }
            return ['ok' => true, 'msg' => 'Cobro anulado. Se revirtió la cuenta corriente y se eliminaron los cheques asociados.'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Cobro::anular] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al anular el cobro.'];
        }
    }

    /* ==========================================================
       COMBOS
       ========================================================== */
    public function combos(?int $vendedorId = null): array
    {
        $sqlCli = "SELECT c.id, c.razon_social, c.nombre_fantasia, c.cuit,
                          c.condicion_venta, c.limite_credito
                   FROM cliente c
                   WHERE c.estado = 'ACTIVO'";
        $params = [];
        if ($vendedorId !== null) {
            $sqlCli .= " AND c.vendedor_id = :vid";
            $params[':vid'] = $vendedorId;
        }
        $sqlCli .= " ORDER BY c.razon_social";

        $stmt = $this->db->prepare($sqlCli);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();

        return ['clientes' => $clientes];
    }
}