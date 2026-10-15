<?php
// models/Pedido.php

require_once __DIR__ . '/../config/conexion.php';

class Pedido
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
        string $search = '',
        string $estado = '',
        ?int   $vendedorId = null,
        string $desde = '',
        string $hasta = ''
    ): array {
        $sql = "SELECT p.*,
                       c.razon_social AS cliente_razon_social,
                       c.nombre_fantasia AS cliente_fantasia,
                       c.categoria AS cliente_categoria_id,
                       cc.descripcion AS cliente_categoria,
                       u.nombre AS creado_por_nombre,
                       (SELECT COUNT(*) FROM pedido_detalle WHERE pedido_id = p.id) AS items_count
                FROM pedido p
                INNER JOIN cliente c ON c.id = p.cliente_id
                LEFT JOIN cliente_categoria cc ON cc.id = c.categoria
                LEFT JOIN usuario u ON u.id = p.creado_por_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (c.razon_social LIKE :s1
                        OR c.nombre_fantasia LIKE :s2
                        OR p.id = :s3)";
            $like = '%' . $search . '%';
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = (int)$search;
        }

        if ($estado !== '' && in_array($estado, ['RECIBIDO','PROCESANDO','PREPARADO','FACTURADO','ENTREGADO','ANULADO'], true)) {
            $sql .= " AND p.estado = :est";
            $params[':est'] = $estado;
        }

        if ($vendedorId !== null) {
            $sql .= " AND c.vendedor_id = :vid";
            $params[':vid'] = $vendedorId;
        }

        if ($desde !== '') {
            $sql .= " AND p.fecha_pedido >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= " AND p.fecha_pedido <= :hasta";
            $params[':hasta'] = $hasta;
        }

        // Estados activos primero, luego por fecha descendente
        $sql .= " ORDER BY 
                    FIELD(p.estado, 'RECIBIDO','PROCESANDO','PREPARADO','FACTURADO','ENTREGADO','ANULADO'),
                    p.fecha_pedido DESC, p.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT p.*,
                       c.razon_social AS cliente_razon_social,
                       c.nombre_fantasia AS cliente_fantasia,
                       c.cuit AS cliente_cuit,
                       c.domicilio_entrega AS cliente_domicilio_entrega,
                       c.domicilio_fiscal AS cliente_domicilio_fiscal,
                       c.localidad AS cliente_localidad,
                       c.categoria AS cliente_categoria_id,
                       c.id_condicion_iva AS cliente_condicion_iva_id,
                       cc.descripcion AS cliente_categoria,
                       ci.descripcion AS cliente_condicion_iva,
                       u.nombre AS creado_por_nombre,
                       pv.descripcion AS provincia_desc
                FROM pedido p
                INNER JOIN cliente c ON c.id = p.cliente_id
                LEFT JOIN cliente_categoria cc ON cc.id = c.categoria
                LEFT JOIN condiciones_iva ci ON ci.id = c.id_condicion_iva
                LEFT JOIN provincias pv ON pv.codigo = c.provincia_id
                LEFT JOIN usuario u ON u.id = p.creado_por_id
                WHERE p.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function obtenerDetalle(int $pedidoId): array
    {
        $sql = "SELECT pd.*,
                       a.nombre AS articulo_nombre,
                       a.codigo AS articulo_codigo,
                       a.imagen_path AS articulo_imagen,
                       pk.descripcion AS pakaging_desc,
                       pk.cantidad AS pakaging_factor
                FROM pedido_detalle pd
                INNER JOIN articulo a ON a.id = pd.articulo_id
                LEFT JOIN pakaging pk ON pk.id = pd.pakaging_id
                WHERE pd.pedido_id = :id
                ORDER BY pd.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CREAR (transacción)
       ========================================================== */
    public function crear(array $cabecera, array $detalle, int $usuarioId): int|false
    {
        if (empty($detalle)) return false;

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO pedido
                    (cliente_id, creado_por_id, fecha_pedido, estado,
                     subtotal, descuento_general_porcentaje, total,
                     notas, notas_internas)
                    VALUES
                    (:cliente_id, :creado_por_id, :fecha_pedido, 'RECIBIDO',
                     :subtotal, :desc_gral, :total,
                     :notas, :notas_internas)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':cliente_id'      => $cabecera['cliente_id'],
                ':creado_por_id'   => $usuarioId,
                ':fecha_pedido'    => $cabecera['fecha_pedido'],
                ':subtotal'        => $cabecera['subtotal'],
                ':desc_gral'       => $cabecera['descuento_general_porcentaje'],
                ':total'           => $cabecera['total'],
                ':notas'           => $cabecera['notas'],
                ':notas_internas'  => $cabecera['notas_internas'],
            ]);
            $pedidoId = (int)$this->db->lastInsertId();

            $this->guardarDetalle($pedidoId, $detalle);

            $this->db->commit();
            return $pedidoId;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Pedido::crear] ' . $e->getMessage());
            return false;
        }
    }

    /* ==========================================================
       ACTUALIZAR (reemplaza el detalle completo)
       ========================================================== */
    public function actualizar(int $id, array $cabecera, array $detalle, int $usuarioId, bool $esVendedor): bool
    {
        if (empty($detalle)) return false;

        try {
            $this->db->beginTransaction();

            // Cabecera
            $sql = "UPDATE pedido SET
                        cliente_id = :cliente_id,
                        fecha_pedido = :fecha_pedido,
                        subtotal = :subtotal,
                        descuento_general_porcentaje = :desc_gral,
                        total = :total,
                        notas = :notas,
                        notas_internas = :notas_internas,
                        actualizado_el = NOW()";

            $params = [
                ':cliente_id'      => $cabecera['cliente_id'],
                ':fecha_pedido'    => $cabecera['fecha_pedido'],
                ':subtotal'        => $cabecera['subtotal'],
                ':desc_gral'       => $cabecera['descuento_general_porcentaje'],
                ':total'           => $cabecera['total'],
                ':notas'           => $cabecera['notas'],
                ':notas_internas'  => $cabecera['notas_internas'],
                ':id'              => $id,
            ];

            // Marcar como modificado por vendedor
            if ($esVendedor) {
                $sql .= ", modificado_por_vendedor_el = NOW(),
                          modificado_por_vendedor_visto = 0";
            }

            $sql .= " WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Reemplazar detalle
            $this->db->prepare("DELETE FROM pedido_detalle WHERE pedido_id = :id")
                     ->execute([':id' => $id]);
            $this->guardarDetalle($id, $detalle);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[Pedido::actualizar] ' . $e->getMessage());
            return false;
        }
    }

    private function guardarDetalle(int $pedidoId, array $detalle): void
    {
        $sql = "INSERT INTO pedido_detalle
                (pedido_id, articulo_id, pakaging_id, cantidad,
                 cantidad_pakaging, cantidad_unidades,
                 precio_unitario, descuento_porcentaje, subtotal_linea)
                VALUES
                (:pedido_id, :articulo_id, :pakaging_id, :cantidad,
                 :cantidad_pakaging, :cantidad_unidades,
                 :precio_unitario, :descuento_porcentaje, :subtotal_linea)";
        $stmt = $this->db->prepare($sql);

        foreach ($detalle as $linea) {
            $cantidadUnidades = (float)$linea['cantidad_unidades'];
            $precio           = (float)$linea['precio_unitario'];
            $descPct          = (float)($linea['descuento_porcentaje'] ?? 0);
            $subtotalLinea    = round($cantidadUnidades * $precio * (1 - $descPct / 100), 4);

            $stmt->execute([
                ':pedido_id'           => $pedidoId,
                ':articulo_id'         => (int)$linea['articulo_id'],
                ':pakaging_id'         => !empty($linea['pakaging_id']) ? (int)$linea['pakaging_id'] : null,
                ':cantidad'            => $cantidadUnidades, // columna legacy
                ':cantidad_pakaging'   => (float)$linea['cantidad_pakaging'],
                ':cantidad_unidades'   => $cantidadUnidades,
                ':precio_unitario'     => $precio,
                ':descuento_porcentaje'=> $descPct,
                ':subtotal_linea'      => $subtotalLinea,
            ]);
        }
    }

    /* ==========================================================
       CAMBIAR ESTADO
       ========================================================== */
    public function cambiarEstado(int $id, string $nuevoEstado): array
    {
        $estadosValidos = ['RECIBIDO','PROCESANDO','PREPARADO','FACTURADO','ENTREGADO','ANULADO'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            return ['ok' => false, 'msg' => 'Estado inválido.'];
        }

        $pedido = $this->obtenerPorId($id);
        if (!$pedido) {
            return ['ok' => false, 'msg' => 'El pedido no existe.'];
        }

        // Fechas automáticas
        $extra = '';
        if ($nuevoEstado === 'PREPARADO' && empty($pedido['fecha_preparacion'])) {
            $extra = ", fecha_preparacion = CURDATE()";
        } elseif ($nuevoEstado === 'FACTURADO' && empty($pedido['fecha_facturacion'])) {
            $extra = ", fecha_facturacion = CURDATE()";
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE pedido SET estado = :est $extra, actualizado_el = NOW() WHERE id = :id"
            );
            $stmt->execute([':est' => $nuevoEstado, ':id' => $id]);
            return ['ok' => true, 'msg' => 'Estado actualizado a ' . $nuevoEstado . '.'];
        } catch (PDOException $e) {
            error_log('[Pedido::cambiarEstado] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'No se pudo actualizar el estado.'];
        }
    }

    /* ==========================================================
       ANULAR (soft delete)
       ========================================================== */
    public function anular(int $id): array
    {
        $pedido = $this->obtenerPorId($id);
        if (!$pedido) {
            return ['ok' => false, 'msg' => 'El pedido no existe.'];
        }
        if ($pedido['estado'] === 'ANULADO') {
            return ['ok' => false, 'msg' => 'El pedido ya estaba anulado.'];
        }
        if (in_array($pedido['estado'], ['FACTURADO','ENTREGADO'], true)) {
            return ['ok' => false, 'msg' => 'No se puede anular un pedido facturado o entregado.'];
        }

        $stmt = $this->db->prepare(
            "UPDATE pedido SET estado = 'ANULADO', actualizado_el = NOW() WHERE id = :id"
        );
        if (!$stmt->execute([':id' => $id])) {
            return ['ok' => false, 'msg' => 'No se pudo anular.'];
        }
        return ['ok' => true, 'msg' => 'Pedido anulado.'];
    }

    /* ==========================================================
       MARCAS DE MODIFICACIÓN
       ========================================================== */
    public function marcarModificadoVisto(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE pedido SET modificado_por_vendedor_visto = 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    /* ==========================================================
       PRECIO DEL ARTÍCULO SEGÚN CATEGORÍA DEL CLIENTE
       ========================================================== */
    public function precioArticulo(int $articuloId, int $clienteId, ?int $categoriaOverride = null): array
    {
        // Traer categoría del cliente y datos del artículo
        $sql = "SELECT a.id, a.nombre, a.codigo, a.pakaging_id,
                    c.categoria AS categoria_cliente_id,
                    cc.descripcion AS categoria_desc,
                    pk.descripcion AS pakaging_desc, pk.cantidad AS pakaging_factor,
                    apc.precio AS precio_categoria
                FROM articulo a
                INNER JOIN cliente c ON c.id = :cliente_id
                LEFT JOIN cliente_categoria cc ON cc.id = c.categoria
                LEFT JOIN pakaging pk ON pk.id = a.pakaging_id
                LEFT JOIN articulo_precio_categoria apc
                    ON apc.articulo_id = a.id
                    AND apc.categoria_id = :categoria_id
                WHERE a.id = :articulo_id LIMIT 1";

        // Si viene override, usamos esa categoría; sino, la del cliente
        if ($categoriaOverride === null) {
            // Primero necesitamos saber la categoría del cliente
            $stmt = $this->db->prepare("SELECT categoria FROM cliente WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $clienteId]);
            $categoriaOverride = (int)$stmt->fetchColumn();
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cliente_id'     => $clienteId,
            ':articulo_id'    => $articuloId,
            ':categoria_id'   => $categoriaOverride
        ]);
        $row = $stmt->fetch();

        // Si no encontró precio para la categoría pedida, igual devolvemos el artículo
        if ($row) {
            $row['categoria_efectiva_id'] = $categoriaOverride;
            // Buscar la descripción de la categoría efectiva
            $stmt2 = $this->db->prepare("SELECT descripcion FROM cliente_categoria WHERE id = :id");
            $stmt2->execute([':id' => $categoriaOverride]);
            $row['categoria_desc'] = (string)$stmt2->fetchColumn();
        }

        return $row ?: [];
    }

    /* ==========================================================
       COMBOS
       ========================================================== */
    public function combos(?int $vendedorId = null): array
    {
        // Clientes (filtrados por vendedor si aplica)
        $sqlCli = "SELECT c.id, c.razon_social, c.nombre_fantasia, c.cuit,
                        c.categoria, cc.descripcion AS categoria_desc
                FROM cliente c
                LEFT JOIN cliente_categoria cc ON cc.id = c.categoria
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

        // Artículos activos (con packaging)
        $articulos = $this->db->query(
            "SELECT a.id, a.nombre, a.codigo, a.precio, a.pakaging_id,
                    pk.descripcion AS pakaging_desc, pk.cantidad AS pakaging_factor
            FROM articulo a
            LEFT JOIN pakaging pk ON pk.id = a.pakaging_id
            WHERE a.estado = 'ACTIVO'
            ORDER BY a.nombre"
        )->fetchAll();

        // Packagings
        $packagings = $this->db->query(
            "SELECT id, descripcion, cantidad FROM pakaging ORDER BY cantidad"
        )->fetchAll();

        // ← NUEVO: Categorías de cliente
        $categorias = $this->db->query(
            "SELECT id, descripcion FROM cliente_categoria ORDER BY id"
        )->fetchAll();

        return [
            'clientes'   => $clientes,
            'articulos'  => $articulos,
            'packagings' => $packagings,
            'categorias' => $categorias,   // ← NUEVO
        ];
    }
}