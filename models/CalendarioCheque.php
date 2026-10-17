<?php
// models/CalendarioCheque.php

require_once __DIR__ . '/../config/conexion.php';

class CalendarioCheque
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTAR por rango de fechas (para el calendario)
       ========================================================== */
    public function listarRango(string $desde, string $hasta): array
    {
        $sql = "SELECT cc.*, u.nombre AS usuario_nombre
                FROM calendario_cheques cc
                LEFT JOIN usuario u ON u.id = cc.creado_por_id
                WHERE cc.fecha BETWEEN :desde AND :hasta
                  AND cc.tenant_id = :tenant
                ORDER BY cc.fecha ASC, cc.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':desde'  => $desde,
            ':hasta'  => $hasta,
            ':tenant' => tenantId(),
        ]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       LISTAR por fecha específica
       ========================================================== */
    public function listarPorFecha(string $fecha): array
    {
        $sql = "SELECT cc.*, u.nombre AS usuario_nombre
                FROM calendario_cheques cc
                LEFT JOIN usuario u ON u.id = cc.creado_por_id
                WHERE cc.fecha = :fecha
                  AND cc.tenant_id = :tenant
                ORDER BY cc.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':fecha'  => $fecha,
            ':tenant' => tenantId(),
        ]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CHEQUES QUE VENCEN MAÑANA (para el dashboard)
       ========================================================== */
    public function listarManana(): array
    {
        $manana = date('Y-m-d', strtotime('+1 day'));
        return $this->listarPorFecha($manana);
    }

    /* ==========================================================
       OBTENER por ID
       ========================================================== */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM calendario_cheques
             WHERE id = :id AND tenant_id = :tenant
             LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tenant' => tenantId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ==========================================================
       CREAR
       ========================================================== */
    public function crear(array $d, int $usuarioId): int|false
    {
        try {
            $sql = "INSERT INTO calendario_cheques
                    (tenant_id, fecha, descripcion, importe, banco, nro_cheque, tipo, creado_por_id)
                    VALUES
                    (:tenant, :fecha, :descripcion, :importe, :banco, :nro_cheque, :tipo, :usuario_id)";
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([
                ':tenant'      => tenantId(),
                ':fecha'       => $d['fecha'],
                ':descripcion' => $d['descripcion'],
                ':importe'     => (float)($d['importe'] ?? 0),
                ':banco'       => $d['banco'] ?? null,
                ':nro_cheque'  => $d['nro_cheque'] ?? null,
                ':tipo'        => $d['tipo'] ?? 'TERCERO',
                ':usuario_id'  => $usuarioId,
            ]);
            return $ok ? (int)$this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('[CalendarioCheque::crear] ' . $e->getMessage());
            return false;
        }
    }

    /* ==========================================================
       ACTUALIZAR
       ========================================================== */
    public function actualizar(int $id, array $d): bool
    {
        $sql = "UPDATE calendario_cheques SET
                    fecha = :fecha,
                    descripcion = :descripcion,
                    importe = :importe,
                    banco = :banco,
                    nro_cheque = :nro_cheque,
                    tipo = :tipo,
                    actualizado_el = NOW()
                WHERE id = :id
                  AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':fecha'       => $d['fecha'],
            ':descripcion' => $d['descripcion'],
            ':importe'     => (float)($d['importe'] ?? 0),
            ':banco'       => $d['banco'] ?? null,
            ':nro_cheque'  => $d['nro_cheque'] ?? null,
            ':tipo'        => $d['tipo'] ?? 'TERCERO',
            ':id'          => $id,
            ':tenant'      => tenantId(),
        ]);
    }

    /* ==========================================================
       ELIMINAR
       ========================================================== */
    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM calendario_cheques
             WHERE id = :id AND tenant_id = :tenant"
        );
        return $stmt->execute([':id' => $id, ':tenant' => tenantId()]);
    }
}