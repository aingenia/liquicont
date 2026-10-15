<?php
// models/Cliente.php

require_once __DIR__ . '/../config/conexion.php';

class Cliente
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTADO CON FILTROS
       ========================================================== */

    /**
     * @param string   $search     Búsqueda libre (razón social, código, CUIT, nombre fantasía)
     * @param string   $status     'ACTIVO' | 'INACTIVO' | '' (todos)
     * @param int|null $vendedorId Si no es null, restringe a ese vendedor
     */
    public function listar(string $search = '', string $status = '', ?int $vendedorId = null): array
    {
        $sql = "SELECT c.*,
                       td.descripcion AS tipo_doc_desc,
                       ci.descripcion AS condicion_iva_desc,
                       cb.descripcion AS condicion_iibb_desc,
                       cat.descripcion AS categoria_desc,
                       p.descripcion AS provincia_desc,
                       u.nombre AS vendedor_nombre
                FROM cliente c
                LEFT JOIN tipos_documento  td  ON td.id  = c.tipo_documento_id
                LEFT JOIN condiciones_iva  ci  ON ci.id  = c.id_condicion_iva
                LEFT JOIN condiciones_iibb cb  ON cb.id  = c.id_condicion_iibb
                LEFT JOIN cliente_categoria cat ON cat.id = c.categoria
                LEFT JOIN provincias       p   ON p.codigo = c.provincia_id
                LEFT JOIN usuario          u   ON u.id   = c.vendedor_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (c.razon_social LIKE :s1
                        OR c.nombre_fantasia LIKE :s2
                        OR c.codigo LIKE :s3
                        OR c.cuit LIKE :s4)";
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
            $params[':s4'] = $like;
        }

        if ($status === 'ACTIVO') {
            $sql .= " AND c.estado = 'ACTIVO'";
        } elseif ($status === 'INACTIVO') {
            $sql .= " AND c.estado = 'INACTIVO'";
        }

        if ($vendedorId !== null) {
            $sql .= " AND c.vendedor_id = :vid";
            $params[':vid'] = $vendedorId;
        }

        $sql .= " ORDER BY c.estado DESC, c.razon_social ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT c.*,
                       td.descripcion AS tipo_doc_desc,
                       ci.descripcion AS condicion_iva_desc,
                       cb.descripcion AS condicion_iibb_desc,
                       cat.descripcion AS categoria_desc,
                       p.descripcion AS provincia_desc,
                       u.nombre AS vendedor_nombre
                FROM cliente c
                LEFT JOIN tipos_documento  td  ON td.id  = c.tipo_documento_id
                LEFT JOIN condiciones_iva  ci  ON ci.id  = c.id_condicion_iva
                LEFT JOIN condiciones_iibb cb  ON cb.id  = c.id_condicion_iibb
                LEFT JOIN cliente_categoria cat ON cat.id = c.categoria
                LEFT JOIN provincias       p   ON p.codigo = c.provincia_id
                LEFT JOIN usuario          u   ON u.id   = c.vendedor_id
                WHERE c.id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Verifica si un CUIT ya existe. Excluye opcionalmente un ID (al editar).
     */
    public function cuitExiste(string $cuit, ?int $exceptId = null): bool
    {
        if ($cuit === '') return false;

        $sql = "SELECT id FROM cliente WHERE cuit = :cuit";
        $params = [':cuit' => $cuit];
        if ($exceptId !== null) {
            $sql .= " AND id <> :id";
            $params[':id'] = $exceptId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    /* ==========================================================
       COMBOS PARA EL FORMULARIO
       ========================================================== */
    public function combos(): array
    {
        return [
            'tipos_documento'   => $this->db->query("SELECT id, descripcion FROM tipos_documento ORDER BY id")->fetchAll(),
            'categorias'        => $this->db->query("SELECT id, descripcion FROM cliente_categoria ORDER BY id")->fetchAll(),
            'condiciones_iva'   => $this->db->query("SELECT id, descripcion FROM condiciones_iva ORDER BY id")->fetchAll(),
            'condiciones_iibb'  => $this->db->query("SELECT id, descripcion FROM condiciones_iibb ORDER BY id")->fetchAll(),
            'provincias'        => $this->db->query("SELECT codigo AS id, descripcion FROM provincias ORDER BY descripcion")->fetchAll(),
            'vendedores'        => $this->db->query("SELECT id, nombre FROM usuario WHERE rol = 3 AND estado = 1 ORDER BY nombre")->fetchAll(),
        ];
    }

    /* ==========================================================
       CREAR
       ========================================================== */
    public function crear(array $d): int|false
    {
        if (!empty($d['cuit']) && $this->cuitExiste($d['cuit'])) {
            return false;
        }

        $sql = "INSERT INTO cliente
                (tipo_documento_id, cuit, razon_social, nombre_fantasia, codigo,
                 personeria, categoria, id_condicion_iva, id_condicion_iibb, nro_inscripcion_iibb,
                 email, email_facturacion, telefono,
                 domicilio_fiscal, domicilio_entrega, localidad, provincia_id,
                 condicion_venta, vendedor_id, limite_credito, estado)
                VALUES
                (:tipo_documento_id, :cuit, :razon_social, :nombre_fantasia, :codigo,
                 :personeria, :categoria, :id_condicion_iva, :id_condicion_iibb, :nro_inscripcion_iibb,
                 :email, :email_facturacion, :telefono,
                 :domicilio_fiscal, :domicilio_entrega, :localidad, :provincia_id,
                 :condicion_venta, :vendedor_id, :limite_credito, :estado)";

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute($this->mapearParams($d));

        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    /* ==========================================================
       ACTUALIZAR
       ========================================================== */
    public function actualizar(int $id, array $d): bool
    {
        if (!empty($d['cuit']) && $this->cuitExiste($d['cuit'], $id)) {
            return false;
        }

        $sql = "UPDATE cliente SET
                    tipo_documento_id = :tipo_documento_id,
                    cuit = :cuit,
                    razon_social = :razon_social,
                    nombre_fantasia = :nombre_fantasia,
                    codigo = :codigo,
                    personeria = :personeria,
                    categoria = :categoria,
                    id_condicion_iva = :id_condicion_iva,
                    id_condicion_iibb = :id_condicion_iibb,
                    nro_inscripcion_iibb = :nro_inscripcion_iibb,
                    email = :email,
                    email_facturacion = :email_facturacion,
                    telefono = :telefono,
                    domicilio_fiscal = :domicilio_fiscal,
                    domicilio_entrega = :domicilio_entrega,
                    localidad = :localidad,
                    provincia_id = :provincia_id,
                    condicion_venta = :condicion_venta,
                    vendedor_id = :vendedor_id,
                    limite_credito = :limite_credito,
                    estado = :estado,
                    actualizado_el = NOW()
                WHERE id = :id";

        $params = $this->mapearParams($d);
        $params[':id'] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Cuando el vendedor edita, NO puede tocar estado/categoría/vendedor.
     * Este método arma el UPDATE solo con los campos permitidos.
     */
    public function actualizarComoVendedor(int $id, array $d): bool
    {
        if (!empty($d['cuit']) && $this->cuitExiste($d['cuit'], $id)) {
            return false;
        }

        $sql = "UPDATE cliente SET
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
                    domicilio_entrega = :domicilio_entrega,
                    localidad = :localidad,
                    provincia_id = :provincia_id,
                    condicion_venta = :condicion_venta,
                    limite_credito = :limite_credito,
                    actualizado_el = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
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
            ':domicilio_entrega'    => $d['domicilio_entrega'],
            ':localidad'            => $d['localidad'],
            ':provincia_id'         => $d['provincia_id'],
            ':condicion_venta'      => $d['condicion_venta'],
            ':limite_credito'       => $d['limite_credito'],
            ':id'                   => $id,
        ]);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $sql = "UPDATE cliente SET estado = :estado, actualizado_el = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    /* ==========================================================
       HELPERS
       ========================================================== */
    private function mapearParams(array $d): array
    {
        return [
            ':tipo_documento_id'    => $d['tipo_documento_id'],
            ':cuit'                 => $d['cuit'],
            ':razon_social'         => $d['razon_social'],
            ':nombre_fantasia'      => $d['nombre_fantasia'],
            ':codigo'               => $d['codigo'],
            ':personeria'           => $d['personeria'],
            ':categoria'            => $d['categoria'],
            ':id_condicion_iva'     => $d['id_condicion_iva'],
            ':id_condicion_iibb'    => $d['id_condicion_iibb'],
            ':nro_inscripcion_iibb' => $d['nro_inscripcion_iibb'],
            ':email'                => $d['email'],
            ':email_facturacion'    => $d['email_facturacion'],
            ':telefono'             => $d['telefono'],
            ':domicilio_fiscal'     => $d['domicilio_fiscal'],
            ':domicilio_entrega'    => $d['domicilio_entrega'],
            ':localidad'            => $d['localidad'],
            ':provincia_id'         => $d['provincia_id'],
            ':condicion_venta'      => $d['condicion_venta'],
            ':vendedor_id'          => $d['vendedor_id'],
            ':limite_credito'       => $d['limite_credito'],
            ':estado'               => $d['estado'],
        ];
    }
}