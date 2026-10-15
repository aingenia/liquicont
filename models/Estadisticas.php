<?php
// models/Estadisticas.php

require_once __DIR__ . '/../config/conexion.php';

class Estadisticas
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       1) FACTURACIÓN POR EMISOR
       ========================================================== */
    public function facturacionPorEmisor(string $desde, string $hasta): array
    {
        $sql = "SELECT e.id, e.nombre, e.razon_social,
                       COUNT(f.id) AS cantidad_facturas,
                       COALESCE(SUM(f.total), 0) AS total_facturado
                FROM emisor e
                LEFT JOIN factura f
                       ON f.emisor_id = e.id
                      AND f.estado = 'ACTIVA'
                      AND f.fecha BETWEEN :desde AND :hasta
                GROUP BY e.id, e.nombre, e.razon_social
                ORDER BY total_facturado DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       2) VENTAS MES vs MES (año a año) — en CAJAS equivalentes
       ========================================================== */
    /**
     * Devuelve un array indexado por año, con 12 posiciones (1-12)
     * con las cajas equivalentes vendidas cada mes.
     *
     * Ejemplo:
     *   [
     *     2024 => [1=>676, 2=>385, ..., 12=>139],
     *     2025 => [1=>1005, 2=>1099, ...],
     *   ]
     */
    public function ventasMesesCajas(array $anios): array
    {
        if (empty($anios)) return [];

        // Por seguridad, solo años numéricos
        $anios = array_map('intval', $anios);
        $in = implode(',', array_fill(0, count($anios), '?'));

        $sql = "SELECT YEAR(f.fecha) AS anio,
                       MONTH(f.fecha) AS mes,
                       a.formato,
                       lpe.unidades_por_caja,
                       SUM(fd.cantidad_unidades) AS unidades
                FROM factura f
                INNER JOIN factura_detalle fd ON fd.factura_id = f.id
                INNER JOIN articulo a ON a.id = fd.articulo_id
                LEFT JOIN lista_precios_empaque lpe
                       ON lpe.volumen_cc = CAST(a.formato AS UNSIGNED)
                WHERE f.estado = 'ACTIVA'
                  AND fd.sin_precio = 0
                  AND YEAR(f.fecha) IN ($in)
                GROUP BY anio, mes, a.formato, lpe.unidades_por_caja
                ORDER BY anio ASC, mes ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($anios);
        $rows = $stmt->fetchAll();

        // Armar matriz [anio][mes] = cajas
        $resultado = [];
        foreach ($anios as $a) {
            $resultado[$a] = array_fill(1, 12, 0.0);
        }

        foreach ($rows as $r) {
            $anio = (int)$r['anio'];
            $mes  = (int)$r['mes'];
            $unidades = (float)$r['unidades'];
            $factor = (float)($r['unidades_por_caja'] ?? 0);
            $cajas = $factor > 0 ? $unidades / $factor : $unidades;

            $resultado[$anio][$mes] = round($resultado[$anio][$mes] + $cajas, 2);
        }

        return $resultado;
    }

    /* ==========================================================
       3) FORMATOS — cajas equivalentes por formato
       ========================================================== */
    public function formatosCajas(string $desde, string $hasta): array
    {
        $sql = "SELECT a.formato,
                       lpe.unidades_por_caja,
                       SUM(fd.cantidad_unidades) AS unidades
                FROM factura f
                INNER JOIN factura_detalle fd ON fd.factura_id = f.id
                INNER JOIN articulo a ON a.id = fd.articulo_id
                LEFT JOIN lista_precios_empaque lpe
                       ON lpe.volumen_cc = CAST(a.formato AS UNSIGNED)
                WHERE f.estado = 'ACTIVA'
                  AND fd.sin_precio = 0
                  AND f.fecha BETWEEN :desde AND :hasta
                  AND a.formato IS NOT NULL
                  AND a.formato <> ''
                GROUP BY a.formato, lpe.unidades_por_caja
                ORDER BY CAST(a.formato AS UNSIGNED) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $factor = (float)($r['unidades_por_caja'] ?? 0);
            $unidades = (float)$r['unidades'];
            $cajas = $factor > 0 ? $unidades / $factor : $unidades;

            $out[] = [
                'formato'           => (string)$r['formato'],
                'unidades'          => $unidades,
                'unidades_por_caja' => $factor,
                'cajas'             => round($cajas, 2),
            ];
        }

        return $out;
    }

    /* ==========================================================
       4) CAJAS POR CLIENTE (top N)
       ========================================================== */
    public function cajasPorCliente(string $desde, string $hasta, int $limite = 20): array
    {
        $sql = "SELECT c.id, c.razon_social, c.nombre_fantasia,
                       SUM(fd.cantidad_unidades) AS unidades,
                       SUM(fd.subtotal_linea) AS total_facturado
                FROM factura f
                INNER JOIN factura_detalle fd ON fd.factura_id = f.id
                INNER JOIN cliente c ON c.id = f.cliente_id
                INNER JOIN articulo a ON a.id = fd.articulo_id
                LEFT JOIN lista_precios_empaque lpe
                       ON lpe.volumen_cc = CAST(a.formato AS UNSIGNED)
                WHERE f.estado = 'ACTIVA'
                  AND fd.sin_precio = 0
                  AND f.fecha BETWEEN :desde AND :hasta
                GROUP BY c.id, c.razon_social, c.nombre_fantasia";

        // La división por caja la hacemos en SQL para poder ordenar bien
        // (usamos COALESCE para artículos sin formato configurado)
        // Ojo: expresiones matemáticas con CASE
        $sql = "SELECT c.id, c.razon_social, c.nombre_fantasia,
                       SUM(fd.cantidad_unidades) AS unidades,
                       SUM(
                           fd.cantidad_unidades / 
                           CASE 
                               WHEN COALESCE(lpe.unidades_por_caja, 0) > 0 
                               THEN lpe.unidades_por_caja 
                               ELSE 1 
                           END
                       ) AS cajas,
                       SUM(fd.subtotal_linea) AS total_facturado
                FROM factura f
                INNER JOIN factura_detalle fd ON fd.factura_id = f.id
                INNER JOIN cliente c ON c.id = f.cliente_id
                INNER JOIN articulo a ON a.id = fd.articulo_id
                LEFT JOIN lista_precios_empaque lpe
                       ON lpe.volumen_cc = CAST(a.formato AS UNSIGNED)
                WHERE f.estado = 'ACTIVA'
                  AND fd.sin_precio = 0
                  AND f.fecha BETWEEN :desde AND :hasta
                GROUP BY c.id, c.razon_social, c.nombre_fantasia
                ORDER BY cajas DESC
                LIMIT $limite";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['cajas'] = round((float)$r['cajas'], 2);
            $r['total_facturado'] = round((float)$r['total_facturado'], 2);
        }
        unset($r);

        return $rows;
    }

    /* ==========================================================
       5) SHARE SABORES dentro de un formato
       ========================================================== */
    public function shareSabores(string $desde, string $hasta, ?string $formato = null): array
    {
        $sql = "SELECT a.sabor,
                       SUM(fd.cantidad_unidades) AS unidades
                FROM factura f
                INNER JOIN factura_detalle fd ON fd.factura_id = f.id
                INNER JOIN articulo a ON a.id = fd.articulo_id
                WHERE f.estado = 'ACTIVA'
                  AND fd.sin_precio = 0
                  AND f.fecha BETWEEN :desde AND :hasta
                  AND a.sabor IS NOT NULL
                  AND a.sabor <> ''";

        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($formato !== null && $formato !== '') {
            $sql .= " AND a.formato = :formato";
            $params[':formato'] = $formato;
        }

        $sql .= " GROUP BY a.sabor
                  ORDER BY unidades DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Calcular porcentajes
        $total = array_sum(array_column($rows, 'unidades'));
        foreach ($rows as &$r) {
            $r['unidades'] = (float)$r['unidades'];
            $r['pct'] = $total > 0 ? round(($r['unidades'] / $total) * 100, 2) : 0;
        }
        unset($r);

        return ['items' => $rows, 'total' => $total];
    }

    /* ==========================================================
       6) FORMATOS POR SABOR (stacked bar)
       ========================================================== */
    public function formatosPorSabor(string $desde, string $hasta): array
    {
        $sql = "SELECT a.sabor, a.formato,
                       SUM(fd.cantidad_unidades) AS unidades
                FROM factura f
                INNER JOIN factura_detalle fd ON fd.factura_id = f.id
                INNER JOIN articulo a ON a.id = fd.articulo_id
                WHERE f.estado = 'ACTIVA'
                  AND fd.sin_precio = 0
                  AND f.fecha BETWEEN :desde AND :hasta
                  AND a.sabor IS NOT NULL
                  AND a.sabor <> ''
                  AND a.formato IS NOT NULL
                  AND a.formato <> ''
                GROUP BY a.sabor, a.formato
                ORDER BY a.sabor ASC, a.formato ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       HELPERS
       ========================================================== */
    public function anosDisponibles(): array
    {
        $sql = "SELECT DISTINCT YEAR(fecha) AS anio
                FROM factura
                WHERE estado = 'ACTIVA'
                ORDER BY anio DESC";
        $anios = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        // Si no hay facturas, devolvemos al menos el año actual
        if (empty($anios)) {
            return [(int)date('Y')];
        }
        return array_map('intval', $anios);
    }
}