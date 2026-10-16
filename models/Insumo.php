<?php
// models/Insumo.php

require_once __DIR__ . '/../config/conexion.php';

class Insumo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTADO Y OBTENCIÓN
       ========================================================== */
    public function listar(string $search = '', string $status = ''): array
    {
        $sql = "SELECT i.*,
                       um.nombre AS unidad_nombre,
                       um.abreviatura AS unidad_abrev
                FROM insumo i
                INNER JOIN unidades_medida um ON um.id = i.unidad_medida_id
                WHERE i.tenant_id = :tenant";
        $params = [':tenant' => tenantId()];

        if ($search !== '') {
            $sql .= " AND (i.nombre LIKE :s1
                        OR i.codigo LIKE :s2
                        OR i.descripcion LIKE :s3)";
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
        }

        if ($status === 'ACTIVO') {
            $sql .= " AND i.estado = 'ACTIVO'";
        } elseif ($status === 'INACTIVO') {
            $sql .= " AND i.estado = 'INACTIVO'";
        }

        $sql .= " ORDER BY i.estado DESC, i.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT i.*,
                       um.nombre AS unidad_nombre,
                       um.abreviatura AS unidad_abrev
                FROM insumo i
                INNER JOIN unidades_medida um ON um.id = i.unidad_medida_id
                WHERE i.id = :id
                  AND i.tenant_id = :tenant
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant' => tenantId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function codigoExiste(string $codigo, ?int $exceptId = null): bool
    {
        if ($codigo === '') return false;

        $sql = "SELECT id FROM insumo
                WHERE codigo = :c
                  AND tenant_id = :tenant";
        $params = [
            ':c'      => $codigo,
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
        $stmtProv = $this->db->prepare(
            "SELECT id, razon_social, nombre_fantasia FROM proveedor
             WHERE estado = 'ACTIVO'
               AND tenant_id = :tenant
             ORDER BY razon_social"
        );
        $stmtProv->execute([':tenant' => tenantId()]);

        return [
            'unidades_medida' => $this->db->query(
                "SELECT id, nombre, abreviatura FROM unidades_medida ORDER BY nombre"
            )->fetchAll(),
            'proveedores' => $stmtProv->fetchAll(),
        ];
    }

    /* ==========================================================
       CRUD
       ========================================================== */
    public function crear(array $d): int|false
    {
        if (!empty($d['codigo']) && $this->codigoExiste($d['codigo'])) {
            return false;
        }

        $sql = "INSERT INTO insumo
                (tenant_id, codigo, nombre, descripcion, unidad_medida_id, medida, stock,
                 saldo_fraccionario, ppedido, metodo_costo, costo,
                 es_alcoholico, aplica_impuestos_internos, alicuota_impuestos_internos, estado)
                VALUES
                (:tenant, :codigo, :nombre, :descripcion, :unidad_medida_id, :medida, :stock,
                 0, :ppedido, :metodo_costo, 0,
                 :es_alcoholico, :aplica_impuestos_internos, :alicuota, :estado)";

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            ':tenant'                    => tenantId(),
            ':codigo'                    => $d['codigo'],
            ':nombre'                    => $d['nombre'],
            ':descripcion'               => $d['descripcion'],
            ':unidad_medida_id'          => $d['unidad_medida_id'],
            ':medida'                    => $d['medida'],
            ':stock'                     => $d['stock'] ?? 0,
            ':ppedido'                   => $d['ppedido'],
            ':metodo_costo'              => $d['metodo_costo'],
            ':es_alcoholico'             => $d['es_alcoholico'],
            ':aplica_impuestos_internos' => $d['aplica_impuestos_internos'],
            ':alicuota'                  => $d['alicuota_impuestos_internos'],
            ':estado'                    => $d['estado'],
        ]);

        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    public function actualizar(int $id, array $d): bool
    {
        if (!empty($d['codigo']) && $this->codigoExiste($d['codigo'], $id)) {
            return false;
        }

        $sql = "UPDATE insumo SET
                    codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    unidad_medida_id = :unidad_medida_id,
                    medida = :medida,
                    ppedido = :ppedido,
                    metodo_costo = :metodo_costo,
                    es_alcoholico = :es_alcoholico,
                    aplica_impuestos_internos = :aplica_impuestos_internos,
                    alicuota_impuestos_internos = :alicuota,
                    estado = :estado,
                    actualizado_el = NOW()
                WHERE id = :id
                  AND tenant_id = :tenant";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':codigo'                    => $d['codigo'],
            ':nombre'                    => $d['nombre'],
            ':descripcion'               => $d['descripcion'],
            ':unidad_medida_id'          => $d['unidad_medida_id'],
            ':medida'                    => $d['medida'],
            ':ppedido'                   => $d['ppedido'],
            ':metodo_costo'              => $d['metodo_costo'],
            ':es_alcoholico'             => $d['es_alcoholico'],
            ':aplica_impuestos_internos' => $d['aplica_impuestos_internos'],
            ':alicuota'                  => $d['alicuota_impuestos_internos'],
            ':estado'                    => $d['estado'],
            ':id'                        => $id,
            ':tenant'                    => tenantId(),
        ]);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE insumo
             SET estado = :e, actualizado_el = NOW()
             WHERE id = :id
               AND tenant_id = :tenant"
        );
        return $stmt->execute([
            ':e'      => $estado,
            ':id'     => $id,
            ':tenant' => tenantId(),
        ]);
    }

    /* ==========================================================
       PROVEEDORES DEL INSUMO
       ========================================================== */
    public function listarProveedores(int $insumoId): array
    {
        $sql = "SELECT ip.*, p.razon_social, p.nombre_fantasia, p.cuit
                FROM insumo_proveedor ip
                INNER JOIN proveedor p ON p.id = ip.proveedor_id
                WHERE ip.insumo_id = :id
                  AND ip.tenant_id = :tenant
                ORDER BY ip.costo ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $insumoId, ':tenant' => tenantId()]);
        return $stmt->fetchAll();
    }

    public function guardarProveedor(int $insumoId, int $proveedorId, float $costo, ?string $codigoProv): int|false
    {
        try {
            $sql = "INSERT INTO insumo_proveedor (tenant_id, insumo_id, proveedor_id, costo, codigo_proveedor)
                    VALUES (:tenant, :i, :p, :c, :cp)
                    ON DUPLICATE KEY UPDATE
                        costo = VALUES(costo),
                        codigo_proveedor = VALUES(codigo_proveedor),
                        actualizado_el = NOW()";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant' => tenantId(),
                ':i'      => $insumoId,
                ':p'      => $proveedorId,
                ':c'      => $costo,
                ':cp'     => $codigoProv,
            ]);

            $last = (int)$this->db->lastInsertId();
            if ($last > 0) return $last;

            $stmt = $this->db->prepare(
                "SELECT id FROM insumo_proveedor
                 WHERE insumo_id = :i AND proveedor_id = :p AND tenant_id = :tenant
                 LIMIT 1"
            );
            $stmt->execute([
                ':i'      => $insumoId,
                ':p'      => $proveedorId,
                ':tenant' => tenantId(),
            ]);
            return (int)$stmt->fetchColumn() ?: false;
        } catch (PDOException $e) {
            error_log('[Insumo::guardarProveedor] ' . $e->getMessage());
            return false;
        }
    }

    public function eliminarProveedor(int $id, int $insumoId): bool
    {
        $sql = "DELETE FROM insumo_proveedor
                WHERE id = :id
                  AND insumo_id = :i
                  AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'     => $id,
            ':i'      => $insumoId,
            ':tenant' => tenantId(),
        ]);
    }

    /* ==========================================================
       CÁLCULO DE COSTO
       ========================================================== */

    public function recalcularCosto(int $insumoId): float
    {
        $insumo = $this->obtenerPorId($insumoId);
        if (!$insumo) return 0.0;

        $provs = $this->listarProveedores($insumoId);
        $costos = array_map(fn($p) => (float)$p['costo'], $provs);

        if (empty($costos)) {
            $base = 0.0;
        } elseif ($insumo['metodo_costo'] === 'MAYOR') {
            $base = max($costos);
        } else {
            $base = array_sum($costos) / count($costos);
        }

        $alicuota = (float)($insumo['alicuota_impuestos_internos'] ?? 0);
        $final = ((int)$insumo['aplica_impuestos_internos'] === 1)
            ? round($base * (1 + $alicuota / 100), 4)
            : round($base, 4);

        $baseAnterior = $this->obtenerUltimoCostoBase($insumoId);

        $stmt = $this->db->prepare(
            "UPDATE insumo SET costo = :c WHERE id = :id AND tenant_id = :tenant"
        );
        $stmt->execute([
            ':c'      => $final,
            ':id'     => $insumoId,
            ':tenant' => tenantId(),
        ]);

        if ($baseAnterior === null || abs($base - $baseAnterior) > 0.0001) {
            $this->registrarCambioCosto($insumoId, $baseAnterior ?? 0.0, $base);
        }

        $this->recalcularArticulosQueUsan($insumoId);

        return $final;
    }

    private function recalcularArticulosQueUsan(int $insumoId): void
    {
        $sql = "SELECT DISTINCT idarticulo FROM formula
                WHERE idinsumo = :id
                  AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $insumoId, ':tenant' => tenantId()]);
        $articulos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($articulos)) return;

        if (!class_exists('Articulo')) {
            $path = __DIR__ . '/Articulo.php';
            if (file_exists($path)) require_once $path;
        }
        if (!class_exists('Articulo')) return;

        $modelo = new Articulo();
        foreach ($articulos as $artId) {
            $modelo->recalcularCosto((int)$artId);
        }
    }

    private function obtenerUltimoCostoBase(int $insumoId): ?float
    {
        $sql = "SELECT valor_nuevo
                  FROM historial_valores
                 WHERE tipo = 'INSUMO_COSTO'
                   AND referencia_id = :ref
                   AND anulado = 0
                   AND tenant_id = :tenant
                 ORDER BY fecha DESC, id DESC
                 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':ref' => $insumoId, ':tenant' => tenantId()]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (float)$v : null;
    }

    public function actualizarProveedor(int $rowId, int $insumoId, float $costo, ?string $codigoProv): bool
    {
        $sql = "UPDATE insumo_proveedor
                   SET costo = :c,
                       codigo_proveedor = :cp,
                       actualizado_el = NOW()
                 WHERE id = :id
                   AND insumo_id = :i
                   AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':c'      => $costo,
            ':cp'     => $codigoProv,
            ':id'     => $rowId,
            ':i'      => $insumoId,
            ':tenant' => tenantId(),
        ]);
    }

    public function registrarCambioCosto(int $insumoId, float $anterior, float $nuevo): void
    {
        $variacion = $anterior != 0
            ? round((($nuevo - $anterior) / $anterior) * 100, 2)
            : null;

        $usuarioId = $_SESSION['user_id'] ?? null;

        $sql = "INSERT INTO historial_valores
                (tenant_id, tipo, referencia_id, valor_anterior, valor_nuevo, variacion_pct, usuario_id)
                VALUES (:tenant, 'INSUMO_COSTO', :ref, :va, :vn, :vp, :u)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant' => tenantId(),
            ':ref'    => $insumoId,
            ':va'     => $anterior,
            ':vn'     => $nuevo,
            ':vp'     => $variacion,
            ':u'      => $usuarioId,
        ]);
    }

    public function historialCostos(int $insumoId, int $limite = 15): array
    {
        $sql = "SELECT hv.*, u.nombre AS usuario_nombre
                FROM historial_valores hv
                LEFT JOIN usuario u ON u.id = hv.usuario_id
                WHERE hv.tipo = 'INSUMO_COSTO'
                  AND hv.referencia_id = :ref
                  AND hv.anulado = 0
                  AND hv.tenant_id = :tenant
                ORDER BY hv.fecha DESC
                LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ref', $insumoId, PDO::PARAM_INT);
        $stmt->bindValue(':tenant', tenantId(), PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}