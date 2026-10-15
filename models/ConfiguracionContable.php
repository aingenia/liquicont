<?php
// models/ConfiguracionContable.php

require_once __DIR__ . '/../config/conexion.php';

class ConfiguracionContable
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /** Devuelve todas las claves con su cuenta asignada (o null). */
    public function all(): array
    {
        $sql = "SELECT cc.clave, cc.cuenta_id, cc.descripcion,
                       pc.codigo AS cuenta_codigo,
                       pc.nombre AS cuenta_nombre
                FROM configuracion_contable cc
                LEFT JOIN plan_cuentas pc ON pc.id = cc.cuenta_id
                ORDER BY cc.clave";
        return $this->db->query($sql)->fetchAll();
    }

    /** Devuelve el ID de cuenta asignada a una clave, o null. */
    public function get(string $clave): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT cuenta_id FROM configuracion_contable WHERE clave = :c"
        );
        $stmt->execute([':c' => $clave]);
        $r = $stmt->fetchColumn();
        return $r !== false && $r !== null ? (int)$r : null;
    }

    /** Devuelve la fila completa de la cuenta asignada a una clave, o null. */
    public function getCuenta(string $clave): ?array
    {
        $sql = "SELECT pc.* FROM configuracion_contable cc
                INNER JOIN plan_cuentas pc ON pc.id = cc.cuenta_id
                WHERE cc.clave = :c";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':c' => $clave]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Guarda múltiples claves de una sola vez. */
    public function guardar(array $valores): array
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "UPDATE configuracion_contable SET cuenta_id = :c WHERE clave = :k"
            );
            foreach ($valores as $clave => $cuentaId) {
                $stmt->execute([
                    ':c' => ($cuentaId !== '' && $cuentaId !== null) ? (int)$cuentaId : null,
                    ':k' => $clave,
                ]);
            }
            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /** Devuelve las claves que aún no tienen cuenta asignada. */
    public function faltantes(): array
    {
        $sql = "SELECT clave, descripcion FROM configuracion_contable
                WHERE cuenta_id IS NULL
                ORDER BY clave";
        return $this->db->query($sql)->fetchAll();
    }

    /** ¿Está lista la configuración para operar? */
    public function estaCompleta(): bool
    {
        return count($this->faltantes()) === 0;
    }
}