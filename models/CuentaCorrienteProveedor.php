<?php
// models/CuentaCorrienteProveedor.php

require_once __DIR__ . '/../config/conexion.php';

class CuentaCorrienteProveedor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       SALDO ACTUAL
       ========================================================== */
    /**
     * Saldo actual del proveedor.
     * Positivo = le debemos. Negativo = tenemos saldo a favor.
     */
    public function obtenerSaldo(int $proveedorId): float
    {
        $sql = "SELECT COALESCE(SUM(debe) - SUM(haber), 0)
                FROM cuenta_corriente_proveedor_movimiento
                WHERE proveedor_id = :pid AND anulado = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':pid' => $proveedorId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    /* ==========================================================
       LISTAR MOVIMIENTOS
       ========================================================== */
    public function listarMovimientos(
        int $proveedorId,
        string $desde = '',
        string $hasta = '',
        bool $incluirAnulados = false
    ): array {
        $sql = "SELECT ccm.*, u.nombre AS usuario_nombre
                FROM cuenta_corriente_proveedor_movimiento ccm
                LEFT JOIN usuario u ON u.id = ccm.creado_por_id
                WHERE ccm.proveedor_id = :pid";
        $params = [':pid' => $proveedorId];

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
        int $proveedorId,
        string $fecha,
        string $tipo,
        ?int $referenciaId,
        float $debe,
        float $haber,
        ?string $observaciones = null,
        ?int $usuarioId = null
    ): int|false {
        try {
            $sql = "INSERT INTO cuenta_corriente_proveedor_movimiento
                    (proveedor_id, fecha, tipo, referencia_id, debe, haber,
                     saldo_acumulado, observaciones, creado_por_id)
                    VALUES
                    (:pid, :fecha, :tipo, :ref, :debe, :haber,
                     0, :obs, :uid)";
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([
                ':pid'   => $proveedorId,
                ':fecha' => $fecha,
                ':tipo'  => $tipo,
                ':ref'   => $referenciaId,
                ':debe'  => $debe,
                ':haber' => $haber,
                ':obs'   => $observaciones,
                ':uid'   => $usuarioId,
            ]);
            if (!$ok) return false;

            $id = (int)$this->db->lastInsertId();
            $this->recalcularSaldos($proveedorId);
            return $id;
        } catch (PDOException $e) {
            error_log('[CCProveedor::registrar] ' . $e->getMessage());
            return false;
        }
    }

    private function recalcularSaldos(int $proveedorId): void
    {
        $sql = "SELECT id, debe, haber
                FROM cuenta_corriente_proveedor_movimiento
                WHERE proveedor_id = :pid AND anulado = 0
                ORDER BY fecha ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':pid' => $proveedorId]);
        $movs = $stmt->fetchAll();

        $stmtUpd = $this->db->prepare(
            "UPDATE cuenta_corriente_proveedor_movimiento SET saldo_acumulado = :s WHERE id = :id"
        );

        $saldo = 0.0;
        foreach ($movs as $m) {
            $saldo += (float)$m['debe'] - (float)$m['haber'];
            $stmtUpd->execute([':s' => round($saldo, 2), ':id' => (int)$m['id']]);
        }
    }

    public function anularPorReferencia(int $referenciaId, string $tipo, int $usuarioId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE cuenta_corriente_proveedor_movimiento
                 SET anulado = 1, anulado_el = NOW(), anulado_por_id = :uid
                 WHERE referencia_id = :ref AND tipo = :tipo AND anulado = 0"
            );
            $ok = $stmt->execute([
                ':uid'  => $usuarioId,
                ':ref'  => $referenciaId,
                ':tipo' => $tipo,
            ]);
            if (!$ok) return false;

            $stmt = $this->db->prepare(
                "SELECT DISTINCT proveedor_id FROM cuenta_corriente_proveedor_movimiento
                 WHERE referencia_id = :ref AND tipo = :tipo LIMIT 1"
            );
            $stmt->execute([':ref' => $referenciaId, ':tipo' => $tipo]);
            $proveedorId = (int)$stmt->fetchColumn();
            if ($proveedorId > 0) {
                $this->recalcularSaldos($proveedorId);
            }
            return true;
        } catch (PDOException $e) {
            error_log('[CCProveedor::anularPorReferencia] ' . $e->getMessage());
            return false;
        }
    }

    public function recalcularCliente(int $proveedorId): void
    {
        $this->recalcularSaldos($proveedorId);
    }

    /* ==========================================================
       RESUMEN DE TODOS LOS PROVEEDORES
       ========================================================== */
    public function resumenPorProveedor(string $soloConSaldo = ''): array
    {
        $sql = "SELECT p.id AS proveedor_id, p.razon_social, p.nombre_fantasia, p.cuit,
                       COALESCE(SUM(ccm.debe) - SUM(ccm.haber), 0) AS saldo
                FROM proveedor p
                LEFT JOIN cuenta_corriente_proveedor_movimiento ccm
                       ON ccm.proveedor_id = p.id AND ccm.anulado = 0
                WHERE p.estado = 'ACTIVO'";

        if ($soloConSaldo === 'ACREEDORES') {
            $sql .= " GROUP BY p.id HAVING saldo > 0";
        } elseif ($soloConSaldo === 'DEUDORES') {
            $sql .= " GROUP BY p.id HAVING saldo < 0";
        } else {
            $sql .= " GROUP BY p.id";
        }

        $sql .= " ORDER BY saldo DESC, p.razon_social ASC";
        return $this->db->query($sql)->fetchAll();
    }
}