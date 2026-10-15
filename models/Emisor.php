<?php
// models/Emisor.php

require_once __DIR__ . '/../config/conexion.php';

class Emisor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function listar(bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM emisor";
        if ($soloActivos) $sql .= " WHERE activo = 1";
        $sql .= " ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM emisor WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Devuelve el emisor por defecto (el primero activo). Útil para
     * retrocompatibilidad con código que no especifica emisor.
     */
    public function obtenerPorDefecto(): ?array
    {
        $stmt = $this->db->query("SELECT * FROM emisor WHERE activo = 1 ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ==========================================================
       CONTADOR DE REMITOS
       ========================================================== */
    /**
     * Reserva el próximo número de remito del emisor y lo incrementa.
     * Devuelve el número entero (sin prefijo ni punto de venta).
     */
        /**
     * Reserva el próximo número de remito del emisor y lo incrementa.
     * Devuelve el número entero (sin prefijo ni punto de venta).
     *
     * Detecta si ya hay una transacción activa (ej: al crear una factura)
     * y en ese caso se suma a ella sin abrir una nueva. El FOR UPDATE
     * sigue funcionando igual.
     */
    public function siguienteRemito(int $emisorId): int
    {
        $transaccionPropia = false;

        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $transaccionPropia = true;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT remito_ultimo_nro FROM emisor WHERE id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $emisorId]);
            $ultimo = (int)$stmt->fetchColumn();

            $siguiente = $ultimo + 1;

            $stmt = $this->db->prepare(
                "UPDATE emisor SET remito_ultimo_nro = :nro WHERE id = :id"
            );
            $stmt->execute([':nro' => $siguiente, ':id' => $emisorId]);

            if ($transaccionPropia) {
                $this->db->commit();
            }

            return $siguiente;
        } catch (PDOException $e) {
            if ($transaccionPropia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Emisor::siguienteRemito] ' . $e->getMessage());
            return 0;
        }
    }

        /* ==========================================================
       CREAR
       ========================================================== */
    public function crear(array $d): int|false
    {
        try {
            $sql = "INSERT INTO emisor
                    (nombre, razon_social, nombre_fantasia, cuit, cuit_sin_guiones,
                     condicion_iva, ingresos_brutos, fecha_inicio_actividades,
                     domicilio, telefono, email, sitio_web, logo_path,
                     arca_entorno, arca_punto_venta, arca_tipo_comprobante_letra,
                     arca_concepto, arca_iva_id,
                     cert_path, key_path, key_passphrase, ta_cache_dir,
                     remito_prefijo, remito_punto_venta, remito_ultimo_nro, activo)
                    VALUES
                    (:nombre, :razon_social, :nombre_fantasia, :cuit, :cuit_sin_guiones,
                     :condicion_iva, :ingresos_brutos, :fecha_inicio,
                     :domicilio, :telefono, :email, :sitio_web, :logo_path,
                     :entorno, :pv, :letra,
                     :concepto, :iva_id,
                     :cert, :key, :pass, :ta_dir,
                     :rem_prefijo, :rem_pv, :rem_ultimo, :activo)";

            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([
                ':nombre'           => $d['nombre'],
                ':razon_social'     => $d['razon_social'],
                ':nombre_fantasia'  => $d['nombre_fantasia'] ?: null,
                ':cuit'             => $d['cuit'],
                ':cuit_sin_guiones' => preg_replace('/\D/', '', $d['cuit']),
                ':condicion_iva'    => $d['condicion_iva'],
                ':ingresos_brutos'  => $d['ingresos_brutos'] ?: null,
                ':fecha_inicio'     => $d['fecha_inicio_actividades'] ?: null,
                ':domicilio'        => $d['domicilio'],
                ':telefono'         => $d['telefono'] ?: null,
                ':email'            => $d['email'] ?: null,
                ':sitio_web'        => $d['sitio_web'] ?: null,
                ':logo_path'        => $d['logo_path'] ?: null,
                ':entorno'          => $d['arca_entorno'],
                ':pv'               => $d['arca_punto_venta'] ?: null,
                ':letra'            => $d['arca_tipo_comprobante_letra'] ?: null,
                ':concepto'         => $d['arca_concepto'],
                ':iva_id'           => $d['arca_iva_id'],
                ':cert'             => $d['cert_path'],
                ':key'              => $d['key_path'],
                ':pass'             => $d['key_passphrase'] ?: null,
                ':ta_dir'           => $d['ta_cache_dir'],
                ':rem_prefijo'      => $d['remito_prefijo'] ?: null,
                ':rem_pv'           => $d['remito_punto_venta'],
                ':rem_ultimo'       => (int)($d['remito_ultimo_nro'] ?? 0),
                ':activo'           => $d['activo'] ? 1 : 0,
            ]);

            return $ok ? (int)$this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('[Emisor::crear] ' . $e->getMessage());
            return false;
        }
    }

    /* ==========================================================
       ACTUALIZAR
       ========================================================== */
    public function actualizar(int $id, array $d): bool
    {
        try {
            $sql = "UPDATE emisor SET
                        nombre = :nombre,
                        razon_social = :razon_social,
                        nombre_fantasia = :nombre_fantasia,
                        cuit = :cuit,
                        cuit_sin_guiones = :cuit_sin_guiones,
                        condicion_iva = :condicion_iva,
                        ingresos_brutos = :ingresos_brutos,
                        fecha_inicio_actividades = :fecha_inicio,
                        domicilio = :domicilio,
                        telefono = :telefono,
                        email = :email,
                        sitio_web = :sitio_web,
                        logo_path = :logo_path,
                        arca_entorno = :entorno,
                        arca_punto_venta = :pv,
                        arca_tipo_comprobante_letra = :letra,
                        arca_concepto = :concepto,
                        arca_iva_id = :iva_id,
                        cert_path = :cert,
                        key_path = :key,
                        key_passphrase = :pass,
                        ta_cache_dir = :ta_dir,
                        remito_prefijo = :rem_prefijo,
                        remito_punto_venta = :rem_pv,
                        activo = :activo
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':nombre'           => $d['nombre'],
                ':razon_social'     => $d['razon_social'],
                ':nombre_fantasia'  => $d['nombre_fantasia'] ?: null,
                ':cuit'             => $d['cuit'],
                ':cuit_sin_guiones' => preg_replace('/\D/', '', $d['cuit']),
                ':condicion_iva'    => $d['condicion_iva'],
                ':ingresos_brutos'  => $d['ingresos_brutos'] ?: null,
                ':fecha_inicio'     => $d['fecha_inicio_actividades'] ?: null,
                ':domicilio'        => $d['domicilio'],
                ':telefono'         => $d['telefono'] ?: null,
                ':email'            => $d['email'] ?: null,
                ':sitio_web'        => $d['sitio_web'] ?: null,
                ':logo_path'        => $d['logo_path'] ?: null,
                ':entorno'          => $d['arca_entorno'],
                ':pv'               => $d['arca_punto_venta'] ?: null,
                ':letra'            => $d['arca_tipo_comprobante_letra'] ?: null,
                ':concepto'         => $d['arca_concepto'],
                ':iva_id'           => $d['arca_iva_id'],
                ':cert'             => $d['cert_path'],
                ':key'              => $d['key_path'],
                ':pass'             => $d['key_passphrase'] ?: null,
                ':ta_dir'           => $d['ta_cache_dir'],
                ':rem_prefijo'      => $d['remito_prefijo'] ?: null,
                ':rem_pv'           => $d['remito_punto_venta'],
                ':activo'           => $d['activo'] ? 1 : 0,
                ':id'               => $id,
            ]);
        } catch (PDOException $e) {
            error_log('[Emisor::actualizar] ' . $e->getMessage());
            return false;
        }
    }

    /* ==========================================================
       ACTIVAR / DESACTIVAR
       ========================================================== */
    public function cambiarActivo(int $id, bool $activo): bool
    {
        $stmt = $this->db->prepare("UPDATE emisor SET activo = :a WHERE id = :id");
        return $stmt->execute([':a' => $activo ? 1 : 0, ':id' => $id]);
    }

    /**
     * ¿Tiene facturas emitidas? Si sí, no se puede desactivar sin
     * dejar facturas huérfanas conceptualmente.
     */
    public function tieneFacturas(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM factura WHERE emisor_id = :id");
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

        /* ==========================================================
       LISTADO LIGERO PARA SELECTS
       ========================================================== */
    /**
     * Devuelve id, nombre y razón social de emisores activos.
     * Pensado para poblar <select> sin traer todo el registro.
     */
    public function all(): array
    {
        $sql = "SELECT id, nombre, razon_social, nombre_fantasia
                FROM emisor
                WHERE activo = 1
                ORDER BY nombre ASC";
        return $this->db->query($sql)->fetchAll();
    }
}