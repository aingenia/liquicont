<?php
// models/CuentaCorriente.php

require_once __DIR__ . '/../config/conexion.php';

class CuentaCorriente
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       SALDO ACTUAL
       ========================================================== */
    public function obtenerSaldo(int $clienteId): float
    {
        $sql = "SELECT COALESCE(SUM(debe) - SUM(haber), 0)
                FROM cuenta_corriente_movimiento
                WHERE cliente_id = :cid
                  AND anulado = 0
                  AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cid'    => $clienteId,
            ':tenant' => tenantId(),
        ]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    /* ==========================================================
       LISTAR MOVIMIENTOS
       ========================================================== */
    public function listarMovimientos(
        int $clienteId,
        string $desde = '',
        string $hasta = '',
        bool $incluirAnulados = false
    ): array {
        $sql = "SELECT ccm.*, u.nombre AS usuario_nombre
                FROM cuenta_corriente_movimiento ccm
                LEFT JOIN usuario u ON u.id = ccm.creado_por_id
                WHERE ccm.cliente_id = :cid
                  AND ccm.tenant_id = :tenant";
        $params = [
            ':cid'    => $clienteId,
            ':tenant' => tenantId(),
        ];

        if (!$incluirAnulados) {
            $sql .= " AND ccm.anulado = 0";
        }
        if ($desde !== '') {
            $sql .= " AND ccm.fecha >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND ccm.fecha <= :hasta";
            $params[':hasta'] = $hasta;
        }
        $sql .= " ORDER BY ccm.fecha ASC, ccm.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       REGISTRAR MOVIMIENTO
       ========================================================== */
    public function registrar(
        int $clienteId,
        string $fecha,
        string $tipo,
        ?int $referenciaId,
        float $debe,
        float $haber,
        ?string $observaciones = null,
        ?int $usuarioId = null,
        bool $recalcular = false
    ): int|false {
        try {
            $tenant = tenantId();

            $sql = "INSERT INTO cuenta_corriente_movimiento
                    (tenant_id, cliente_id, fecha, tipo, referencia_id, debe, haber,
                     saldo_acumulado, observaciones, creado_por_id)
                    VALUES
                    (:tenant, :cid, :fecha, :tipo, :ref, :debe, :haber,
                     0, :obs, :uid)";
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([
                ':tenant' => $tenant,
                ':cid'    => $clienteId,
                ':fecha'  => $fecha,
                ':tipo'   => $tipo,
                ':ref'    => $referenciaId,
                ':debe'   => $debe,
                ':haber'  => $haber,
                ':obs'    => $observaciones,
                ':uid'    => $usuarioId,
            ]);
            if (!$ok) return false;

            $id = (int)$this->db->lastInsertId();
            $this->recalcularSaldos($clienteId, $id);
            return $id;
        } catch (PDOException $e) {
            error_log('[CuentaCorriente::registrar] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalcula el saldo acumulado desde un movimiento en adelante.
     * Solo sobre movimientos del tenant actual.
     */
    private function recalcularSaldos(int $clienteId, int $desdeMovimientoId = 0): void
    {
        $tenant = tenantId();

        $sql = "SELECT id, debe, haber
                FROM cuenta_corriente_movimiento
                WHERE cliente_id = :cid
                  AND anulado = 0
                  AND tenant_id = :tenant
                ORDER BY fecha ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cid'    => $clienteId,
            ':tenant' => $tenant,
        ]);
        $movs = $stmt->fetchAll();

        $stmtUpd = $this->db->prepare(
            "UPDATE cuenta_corriente_movimiento
             SET saldo_acumulado = :s
             WHERE id = :id AND tenant_id = :tenant"
        );

        $saldo = 0.0;
        foreach ($movs as $m) {
            $saldo += (float)$m['debe'] - (float)$m['haber'];
            $stmtUpd->execute([
                ':s'      => round($saldo, 2),
                ':id'     => (int)$m['id'],
                ':tenant' => $tenant,
            ]);
        }
    }

    /* ==========================================================
       ANULAR MOVIMIENTO
       ========================================================== */
    public function anularPorReferencia(int $referenciaId, string $tipo, int $usuarioId): bool
    {
        try {
            $tenant = tenantId();

            $stmt = $this->db->prepare(
                "UPDATE cuenta_corriente_movimiento
                 SET anulado = 1, anulado_el = NOW(), anulado_por_id = :uid
                 WHERE referencia_id = :ref
                   AND tipo = :tipo
                   AND anulado = 0
                   AND tenant_id = :tenant"
            );
            $ok = $stmt->execute([
                ':uid'    => $usuarioId,
                ':ref'    => $referenciaId,
                ':tipo'   => $tipo,
                ':tenant' => $tenant,
            ]);
            if (!$ok) return false;

            // Obtener cliente para recalcular
            $stmt = $this->db->prepare(
                "SELECT DISTINCT cliente_id FROM cuenta_corriente_movimiento
                 WHERE referencia_id = :ref
                   AND tipo = :tipo
                   AND tenant_id = :tenant
                 LIMIT 1"
            );
            $stmt->execute([
                ':ref'    => $referenciaId,
                ':tipo'   => $tipo,
                ':tenant' => $tenant,
            ]);
            $clienteId = (int)$stmt->fetchColumn();
            if ($clienteId > 0) {
                $this->recalcularSaldos($clienteId);
            }
            return true;
        } catch (PDOException $e) {
            error_log('[CuentaCorriente::anularPorReferencia] ' . $e->getMessage());
            return false;
        }
    }

    /* ==========================================================
       LISTAR CLIENTES CON SALDO
       ========================================================== */
    public function resumenPorCliente(string $soloConSaldo = ''): array
    {
        // Nota: el JOIN filtra con ccm.tenant_id = c.tenant_id (referencia
        // a columna, no placeholder) para evitar HY093 y garantizar que
        // solo se suman movimientos del mismo tenant que el cliente.
        $sql = "SELECT c.id AS cliente_id, c.razon_social, c.nombre_fantasia, c.cuit,
                       c.condicion_venta, c.limite_credito,
                       COALESCE(SUM(ccm.debe) - SUM(ccm.haber), 0) AS saldo
                FROM cliente c
                LEFT JOIN cuenta_corriente_movimiento ccm
                       ON ccm.cliente_id = c.id
                      AND ccm.anulado = 0
                      AND ccm.tenant_id = c.tenant_id
                WHERE c.estado = 'ACTIVO'
                  AND c.tenant_id = :tenant";

        if ($soloConSaldo === 'DEUDORES') {
            $sql .= " GROUP BY c.id
                      HAVING saldo > 0";
        } elseif ($soloConSaldo === 'ACREEDORES') {
            $sql .= " GROUP BY c.id
                      HAVING saldo < 0";
        } else {
            $sql .= " GROUP BY c.id";
        }

        $sql .= " ORDER BY saldo DESC, c.razon_social ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant' => tenantId()]);
        return $stmt->fetchAll();
    }
}