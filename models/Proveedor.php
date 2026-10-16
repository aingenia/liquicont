<?php
// models/Proveedor.php

require_once __DIR__ . '/../config/conexion.php';

class Proveedor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    public function listar(string $search = '', string $status = ''): array
    {
        $sql = "SELECT p.*,
                       td.descripcion AS tipo_doc_desc,
                       ci.descripcion AS condicion_iva_desc,
                       cb.descripcion AS condicion_iibb_desc,
                       pr.descripcion AS provincia_desc
                FROM proveedor p
                LEFT JOIN tipos_documento  td ON td.id = p.tipo_documento_id
                LEFT JOIN condiciones_iva  ci ON ci.id = p.id_condicion_iva
                LEFT JOIN condiciones_iibb cb ON cb.id = p.id_condicion_iibb
                LEFT JOIN provincias       pr ON pr.codigo = p.provincia_id
                WHERE p.tenant_id = :tenant";
        $params = [':tenant' => tenantId()];

        if ($search !== '') {
            $sql .= " AND (p.razon_social LIKE :s1
                        OR p.nombre_fantasia LIKE :s2
                        OR p.codigo LIKE :s3
                        OR p.cuit LIKE :s4)";
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
            $params[':s4'] = $like;
        }

        if ($status === 'ACTIVO') {
            $sql .= " AND p.estado = 'ACTIVO'";
        } elseif ($status === 'INACTIVO') {
            $sql .= " AND p.estado = 'INACTIVO'";
        }

        $sql .= " ORDER BY p.estado DESC, p.razon_social ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT p.*,
                       td.descripcion AS tipo_doc_desc,
                       ci.descripcion AS condicion_iva_desc,
                       cb.descripcion AS condicion_iibb_desc,
                       pr.descripcion AS provincia_desc
                FROM proveedor p
                LEFT JOIN tipos_documento  td ON td.id = p.tipo_documento_id
                LEFT JOIN condiciones_iva  ci ON ci.id = p.id_condicion_iva
                LEFT JOIN condiciones_iibb cb ON cb.id = p.id_condicion_iibb
                LEFT JOIN provincias       pr ON pr.codigo = p.provincia_id
                WHERE p.id = :id
                  AND p.tenant_id = :tenant
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant' => tenantId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function cuitExiste(string $cuit, ?int $exceptId = null): bool
    {
        if ($cuit === '') return false;

        $sql = "SELECT id FROM proveedor
                WHERE cuit = :cuit
                  AND tenant_id = :tenant";
        $params = [
            ':cuit'   => $cuit,
            ':tenant' => tenantId(),
        ];
        if ($exceptId !== null) {
            $sql .= " AND id <> :id";
            $params[':id'] = $exceptId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    public function combos(): array
    {
        // Catálogos globales: NO se filtran por tenant
        return [
            'tipos_documento'  => $this->db->query("SELECT id, descripcion FROM tipos_documento ORDER BY id")->fetchAll(),
            'condiciones_iva'  => $this->db->query("SELECT id, descripcion FROM condiciones_iva ORDER BY id")->fetchAll(),
            'condiciones_iibb' => $this->db->query("SELECT id, descripcion FROM condiciones_iibb ORDER BY id")->fetchAll(),
            'provincias'       => $this->db->query("SELECT codigo AS id, descripcion FROM provincias ORDER BY descripcion")->fetchAll(),
        ];
    }

    public function crear(array $d): int|false
    {
        if (!empty($d['cuit']) && $this->cuitExiste($d['cuit'])) {
            return false;
        }

        $sql = "INSERT INTO proveedor
                (tenant_id, tipo_documento_id, cuit, razon_social, nombre_fantasia, codigo,
                 personeria, id_condicion_iva, id_condicion_iibb, nro_inscripcion_iibb,
                 email, email_facturacion, telefono,
                 domicilio_fiscal, localidad, provincia_id, estado)
                VALUES
                (:tenant, :tipo_documento_id, :cuit, :razon_social, :nombre_fantasia, :codigo,
                 :personeria, :id_condicion_iva, :id_condicion_iibb, :nro_inscripcion_iibb,
                 :email, :email_facturacion, :telefono,
                 :domicilio_fiscal, :localidad, :provincia_id, :estado)";

        $stmt = $this->db->prepare($sql);
        $params = $this->mapearParams($d);
        $params[':tenant'] = tenantId();
        $ok = $stmt->execute($params);
        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    public function actualizar(int $id, array $d): bool
    {
        if (!empty($d['cuit']) && $this->cuitExiste($d['cuit'], $id)) {
            return false;
        }

        $sql = "UPDATE proveedor SET
                    tipo_documento_id = :tipo_documento_id,
                    cuit = :cuit,
                    razon_social = :razon_social,
                    nombre_fantasia = :nombre_fantasia,
                    codigo = :codigo,
                    personeria = :personeria,
                    id_condicion_iva = :id_condicion_iva,
                    id_condicion_iibb = :id_condicion_iibb,
                    nro_inscripcion_iibb = :nro_inscripcion_iibb,
                    email = :email,
                    email_facturacion = :email_facturacion,
                    telefono = :telefono,
                    domicilio_fiscal = :domicilio_fiscal,
                    localidad = :localidad,
                    provincia_id = :provincia_id,
                    estado = :estado,
                    actualizado_el = NOW()
                WHERE id = :id
                  AND tenant_id = :tenant";

        $params = $this->mapearParams($d);
        $params[':id'] = $id;
        $params[':tenant'] = tenantId();

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $sql = "UPDATE proveedor
                SET estado = :estado, actualizado_el = NOW()
                WHERE id = :id
                  AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':id'     => $id,
            ':tenant' => tenantId(),
        ]);
    }

    private function mapearParams(array $d): array
    {
        return [
            ':tipo_documento_id'    => $d['tipo_documento_id'],
            ':cuit'                 => $d['cuit'],
            ':razon_social'         => $d['razon_social'],
            ':nombre_fantasia'      => $d['nombre_fantasia'],
            ':codigo'               => $d['codigo'],
            ':personeria'           => $d['personeria'],
            ':id_condicion_iva'     => $d['id_condicion_iva'],
            ':id_condicion_iibb'    => $d['id_condicion_iibb'],
            ':nro_inscripcion_iibb' => $d['nro_inscripcion_iibb'],
            ':email'                => $d['email'],
            ':email_facturacion'    => $d['email_facturacion'],
            ':telefono'             => $d['telefono'],
            ':domicilio_fiscal'     => $d['domicilio_fiscal'],
            ':localidad'            => $d['localidad'],
            ':provincia_id'         => $d['provincia_id'],
            ':estado'               => $d['estado'],
        ];
    }
}