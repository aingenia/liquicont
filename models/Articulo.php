<?php
// models/Articulo.php

require_once __DIR__ . '/../config/conexion.php';

class Articulo
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
        $sql = "SELECT a.*,
               pk.descripcion AS pakaging_desc,
               pk.cantidad AS pakaging_factor,
               (SELECT COUNT(*) FROM formula f
                WHERE f.idarticulo = a.id
                  AND f.tenant_id = a.tenant_id) AS insumos_count
                FROM articulo a
                LEFT JOIN pakaging pk ON pk.id = a.pakaging_id
                WHERE a.tenant_id = :tenant";
        $params = [':tenant' => tenantId()];

        if ($search !== '') {
            $sql .= " AND (a.nombre LIKE :s1
                        OR a.codigo LIKE :s2
                        OR a.descripcion LIKE :s3)";
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
        }

        if ($status === 'ACTIVO') {
            $sql .= " AND a.estado = 'ACTIVO'";
        } elseif ($status === 'INACTIVO') {
            $sql .= " AND a.estado = 'INACTIVO'";
        }

        $sql .= " ORDER BY a.estado DESC, a.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM articulo WHERE id = :id AND tenant_id = :tenant LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tenant' => tenantId()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function codigoExiste(string $codigo, ?int $exceptId = null): bool
    {
        if ($codigo === '') return false;
        $sql = "SELECT id FROM articulo
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

    /* ==========================================================
       CRUD
       ========================================================== */
    public function crear(array $d): int|false
    {
        if (!empty($d['codigo']) && $this->codigoExiste($d['codigo'])) {
            return false;
        }

        $sql = "INSERT INTO articulo
                (tenant_id, codigo, nombre, descripcion, formato, sabor, costo, costo_automatico, precio,
                 stock, ppedido, pakaging_id, estado)
                VALUES
                (:tenant, :codigo, :nombre, :descripcion, :formato, :sabor, 0, :costo_automatico, 0,
                 0, :ppedido, :pakaging_id, :estado)";

        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            ':tenant'           => tenantId(),
            ':codigo'           => $d['codigo'],
            ':nombre'           => $d['nombre'],
            ':descripcion'      => $d['descripcion'],
            ':formato'          => $d['formato'] ?? null,
            ':sabor'            => $d['sabor'] ?? null,
            ':costo_automatico' => $d['costo_automatico'],
            ':ppedido'          => $d['ppedido'],
            ':pakaging_id'      => $d['pakaging_id'],
            ':estado'           => $d['estado'],
        ]);

        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    public function actualizar(int $id, array $d): bool
    {
        if (!empty($d['codigo']) && $this->codigoExiste($d['codigo'], $id)) {
            return false;
        }

        $sql = "UPDATE articulo SET
            codigo = :codigo,
            nombre = :nombre,
            descripcion = :descripcion,
            formato = :formato,
            sabor = :sabor,
            costo_automatico = :costo_automatico,
            ppedido = :ppedido,
            pakaging_id = :pakaging_id,
            estado = :estado,
            actualizado_el = NOW()
            WHERE id = :id
              AND tenant_id = :tenant";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':codigo'           => $d['codigo'],
            ':nombre'           => $d['nombre'],
            ':descripcion'      => $d['descripcion'],
            ':formato'          => $d['formato'] ?? null,
            ':sabor'            => $d['sabor'] ?? null,
            ':costo_automatico' => $d['costo_automatico'],
            ':ppedido'          => $d['ppedido'],
            ':pakaging_id'      => $d['pakaging_id'],
            ':estado'           => $d['estado'],
            ':id'               => $id,
            ':tenant'           => tenantId(),
        ]);
    }

    public function actualizarCostoManual(int $id, float $costo): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE articulo SET costo = :c, actualizado_el = NOW()
             WHERE id = :id AND tenant_id = :tenant"
        );
        return $stmt->execute([
            ':c'      => $costo,
            ':id'     => $id,
            ':tenant' => tenantId(),
        ]);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE articulo SET estado = :e, actualizado_el = NOW()
             WHERE id = :id AND tenant_id = :tenant"
        );
        return $stmt->execute([
            ':e'      => $estado,
            ':id'     => $id,
            ':tenant' => tenantId(),
        ]);
    }

    public function actualizarImagen(int $id, ?string $path, ?string $nombreOriginal): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE articulo SET imagen_path = :p, imagen_nombre_original = :n
             WHERE id = :id AND tenant_id = :tenant"
        );
        return $stmt->execute([
            ':p'      => $path,
            ':n'      => $nombreOriginal,
            ':id'     => $id,
            ':tenant' => tenantId(),
        ]);
    }

    /* ==========================================================
       FÓRMULA
       ========================================================== */
    public function listarFormula(int $articuloId): array
    {
        $sql = "SELECT f.*,
                       i.nombre AS insumo_nombre,
                       i.codigo AS insumo_codigo,
                       i.costo AS insumo_costo,
                       i.stock AS insumo_stock,
                       um.abreviatura AS unidad_abrev
                FROM formula f
                INNER JOIN insumo i ON i.id = f.idinsumo
                INNER JOIN unidades_medida um ON um.id = i.unidad_medida_id
                WHERE f.idarticulo = :id
                  AND f.tenant_id = :tenant
                ORDER BY i.nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $articuloId, ':tenant' => tenantId()]);
        return $stmt->fetchAll();
    }

    public function agregarInsumo(int $articuloId, int $insumoId, float $cantidad): int|false
    {
        try {
            // Si ya existe, actualizamos la cantidad
            $stmt = $this->db->prepare(
                "SELECT id FROM formula
                 WHERE idarticulo = :a AND idinsumo = :i AND tenant_id = :tenant
                 LIMIT 1"
            );
            $stmt->execute([
                ':a'      => $articuloId,
                ':i'      => $insumoId,
                ':tenant' => tenantId(),
            ]);
            $existente = $stmt->fetchColumn();

            if ($existente) {
                $stmt = $this->db->prepare(
                    "UPDATE formula SET cantidad = :c
                     WHERE id = :id AND tenant_id = :tenant"
                );
                $stmt->execute([
                    ':c'      => $cantidad,
                    ':id'     => $existente,
                    ':tenant' => tenantId(),
                ]);
                return (int)$existente;
            }

            $sql = "INSERT INTO formula (tenant_id, idarticulo, idinsumo, unidad, medida, cantidad)
                    VALUES (:tenant, :a, :i, '', 1, :c)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tenant' => tenantId(),
                ':a'      => $articuloId,
                ':i'      => $insumoId,
                ':c'      => $cantidad,
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[Articulo::agregarInsumo] ' . $e->getMessage());
            return false;
        }
    }

    public function actualizarCantidadInsumo(int $formulaId, int $articuloId, float $cantidad): bool
    {
        $sql = "UPDATE formula SET cantidad = :c
                WHERE id = :id AND idarticulo = :a AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':c'      => $cantidad,
            ':id'     => $formulaId,
            ':a'      => $articuloId,
            ':tenant' => tenantId(),
        ]);
    }

    public function eliminarInsumo(int $formulaId, int $articuloId): bool
    {
        $sql = "DELETE FROM formula
                WHERE id = :id AND idarticulo = :a AND tenant_id = :tenant";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'     => $formulaId,
            ':a'      => $articuloId,
            ':tenant' => tenantId(),
        ]);
    }

    /* ==========================================================
       PRECIOS POR CATEGORÍA
       ========================================================== */
    public function listarPrecios(int $articuloId): array
    {
        $sql = "SELECT apc.*, cc.descripcion AS categoria_nombre
                FROM articulo_precio_categoria apc
                INNER JOIN cliente_categoria cc ON cc.id = apc.categoria_id
                WHERE apc.articulo_id = :id
                  AND apc.tenant_id = :tenant
                ORDER BY cc.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $articuloId, ':tenant' => tenantId()]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['categoria_id']] = $r;
        }
        return $out;
    }

    public function guardarPrecio(int $articuloId, int $categoriaId, float $precio, int $visible): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id, precio FROM articulo_precio_categoria
             WHERE articulo_id = :a AND categoria_id = :c AND tenant_id = :tenant
             LIMIT 1"
        );
        $stmt->execute([
            ':a'      => $articuloId,
            ':c'      => $categoriaId,
            ':tenant' => tenantId(),
        ]);
        $previo = $stmt->fetch();

        $sql = "INSERT INTO articulo_precio_categoria (tenant_id, articulo_id, categoria_id, precio, visible)
                VALUES (:tenant, :a, :c, :p, :v)
                ON DUPLICATE KEY UPDATE
                    precio = VALUES(precio),
                    visible = VALUES(visible),
                    actualizado_el = NOW()";
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            ':tenant' => tenantId(),
            ':a'      => $articuloId,
            ':c'      => $categoriaId,
            ':p'      => $precio,
            ':v'      => $visible,
        ]);

        if ($ok && $previo && abs((float)$previo['precio'] - $precio) > 0.0001) {
            $this->registrarCambioPrecioCategoria(
                $articuloId, $categoriaId,
                (float)$previo['precio'], $precio
            );
        }

        return $ok;
    }

    /* ==========================================================
       CÁLCULO DE COSTO
       ========================================================== */
    public function recalcularCosto(int $articuloId): float
    {
        $art = $this->obtenerPorId($articuloId);
        if (!$art) return 0.0;

        if ((int)$art['costo_automatico'] !== 1) {
            return (float)$art['costo'];
        }

        $formula = $this->listarFormula($articuloId);
        $total = 0.0;
        foreach ($formula as $f) {
            $total += (float)$f['cantidad'] * (float)$f['insumo_costo'];
        }
        $nuevo = round($total, 4);
        $anterior = (float)$art['costo'];

        if (abs($nuevo - $anterior) > 0.0001) {
            $stmt = $this->db->prepare(
                "UPDATE articulo SET costo = :c, actualizado_el = NOW()
                 WHERE id = :id AND tenant_id = :tenant"
            );
            $stmt->execute([
                ':c'      => $nuevo,
                ':id'     => $articuloId,
                ':tenant' => tenantId(),
            ]);
            $this->registrarCambioCosto($articuloId, $anterior, $nuevo);
        }

        return $nuevo;
    }

    /* ==========================================================
       HISTORIAL
       ========================================================== */
    public function historialCostos(int $articuloId, int $limite = 15): array
    {
        $sql = "SELECT hv.*, u.nombre AS usuario_nombre
                FROM historial_valores hv
                LEFT JOIN usuario u ON u.id = hv.usuario_id
                WHERE hv.tipo = 'ARTICULO_COSTO'
                  AND hv.referencia_id = :ref
                  AND hv.anulado = 0
                  AND hv.tenant_id = :tenant
                ORDER BY hv.fecha DESC
                LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ref', $articuloId, PDO::PARAM_INT);
        $stmt->bindValue(':tenant', tenantId(), PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function registrarCambioCosto(int $articuloId, float $anterior, float $nuevo): void
    {
        $variacion = $anterior != 0
            ? round((($nuevo - $anterior) / $anterior) * 100, 2)
            : null;

        $stmt = $this->db->prepare(
            "INSERT INTO historial_valores
             (tenant_id, tipo, referencia_id, valor_anterior, valor_nuevo, variacion_pct, usuario_id)
             VALUES (:tenant, 'ARTICULO_COSTO', :ref, :va, :vn, :vp, :u)"
        );
        $stmt->execute([
            ':tenant' => tenantId(),
            ':ref'    => $articuloId,
            ':va'     => $anterior,
            ':vn'     => $nuevo,
            ':vp'     => $variacion,
            ':u'      => $_SESSION['user_id'] ?? null,
        ]);
    }

    private function registrarCambioPrecioCategoria(
        int $articuloId, int $categoriaId, float $anterior, float $nuevo
    ): void {
        $variacion = $anterior != 0
            ? round((($nuevo - $anterior) / $anterior) * 100, 2)
            : null;

        $stmt = $this->db->prepare(
            "INSERT INTO historial_precios_categoria
             (tenant_id, articulo_id, categoria_id, precio_anterior, precio_nuevo, variacion_pct, usuario_id)
             VALUES (:tenant, :a, :c, :pa, :pn, :vp, :u)"
        );
        $stmt->execute([
            ':tenant' => tenantId(),
            ':a'      => $articuloId,
            ':c'      => $categoriaId,
            ':pa'     => $anterior,
            ':pn'     => $nuevo,
            ':vp'     => $variacion,
            ':u'      => $_SESSION['user_id'] ?? null,
        ]);
    }

    /* ==========================================================
       COMBOS
       ========================================================== */
    public function combos(): array
    {
        $stmtIns = $this->db->prepare(
            "SELECT i.id, i.nombre, i.codigo, i.costo,
                    um.abreviatura AS unidad_abrev
            FROM insumo i
            INNER JOIN unidades_medida um ON um.id = i.unidad_medida_id
            WHERE i.estado = 'ACTIVO'
              AND i.tenant_id = :tenant
            ORDER BY i.nombre"
        );
        $stmtIns->execute([':tenant' => tenantId()]);

        $stmtCat = $this->db->prepare(
            "SELECT id, descripcion FROM cliente_categoria
             WHERE tenant_id = :tenant ORDER BY id"
        );
        $stmtCat->execute([':tenant' => tenantId()]);

        $stmtPk = $this->db->prepare(
            "SELECT id, descripcion, cantidad FROM pakaging
             WHERE tenant_id = :tenant ORDER BY cantidad"
        );
        $stmtPk->execute([':tenant' => tenantId()]);

        return [
            'insumos'     => $stmtIns->fetchAll(),
            'categorias'  => $stmtCat->fetchAll(),
            'packagings'  => $stmtPk->fetchAll(),
        ];
    }
}