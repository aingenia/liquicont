<?php
// models/LibroIva.php

require_once __DIR__ . '/../config/conexion.php';

class LibroIva
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LIBRO IVA COMPRAS
       ========================================================== */
    public function compras(string $desde, string $hasta, ?int $emisorId = null): array
    {
        $where  = [
            "c.estado = 'REGISTRADA'",
            "c.fecha_comprobante BETWEEN :desde AND :hasta",
        ];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($emisorId) {
            $where[] = 'c.emisor_id = :em';
            $params[':em'] = $emisorId;
        }

        // Fallback: si neto_gravado = 0 y subtotal > 0, usar subtotal
        $sql = "SELECT
                    c.id,
                    c.fecha_comprobante AS fecha,
                    t.descripcion AS tipo_comprobante,
                    t.letra AS tipo_letra,
                    CONCAT(IFNULL(c.punto_venta,''), '-', IFNULL(c.numero,'')) AS numero,
                    p.razon_social, p.cuit,
                    ci.descripcion AS condicion_iva,
                    pr.descripcion AS provincia,
                    c.cae,
                    CASE WHEN c.neto_gravado = 0 AND c.subtotal > 0
                         THEN c.subtotal ELSE c.neto_gravado END AS neto_gravado,
                    c.neto_no_gravado,
                    c.neto_exento,
                    c.iva,
                    c.perc_iva,
                    c.perc_iibb,
                    c.impuestos_internos,
                    c.total,
                    c.observaciones
                FROM compra c
                INNER JOIN proveedor p ON p.id = c.proveedor_id
                LEFT JOIN condiciones_iva ci ON ci.id = p.id_condicion_iva
                LEFT JOIN provincias pr ON pr.codigo = p.provincia_id
                LEFT JOIN tipos_comprobante_compra t ON t.id = c.tipo_comprobante_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.fecha_comprobante ASC, c.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       LIBRO IVA VENTAS
       ========================================================== */
    public function ventas(string $desde, string $hasta, ?int $emisorId = null): array
    {
        $where  = [
            "f.estado = 'ACTIVA'",
            "f.fecha BETWEEN :desde AND :hasta",
        ];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($emisorId) {
            $where[] = 'f.emisor_id = :em';
            $params[':em'] = $emisorId;
        }

        $sql = "SELECT
                    f.id,
                    f.fecha,
                    CONCAT('Factura ', IFNULL(f.arca_tipo_comprobante_letra,'')) AS tipo_comprobante,
                    f.arca_tipo_comprobante_letra AS tipo_letra,
                    CONCAT(
                        LPAD(IFNULL(f.arca_punto_venta,0), 4, '0'), '-',
                        LPAD(IFNULL(f.arca_numero_comprobante,0), 8, '0')
                    ) AS numero,
                    c.razon_social, c.cuit,
                    ci.descripcion AS condicion_iva,
                    pr.descripcion AS provincia,
                    f.cae,
                    CASE WHEN f.neto_gravado = 0 AND f.subtotal > 0
                         THEN f.subtotal ELSE f.neto_gravado END AS neto_gravado,
                    f.neto_no_gravado,
                    f.neto_exento,
                    f.iva,
                    f.perc_iva,
                    f.perc_iibb,
                    f.impuestos_internos,
                    f.total,
                    f.notas AS observaciones
                FROM factura f
                INNER JOIN cliente c ON c.id = f.cliente_id
                LEFT JOIN condiciones_iva ci ON ci.id = c.id_condicion_iva
                LEFT JOIN provincias pr ON pr.codigo = c.provincia_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY f.fecha ASC, f.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       TOTALES
       ========================================================== */
    public function totalizar(array $filas): array
    {
        $t = [
            'neto_gravado'      => 0,
            'neto_no_gravado'   => 0,
            'neto_exento'       => 0,
            'iva'               => 0,
            'perc_iva'          => 0,
            'perc_iibb'         => 0,
            'impuestos_internos'=> 0,
            'total'             => 0,
            'cantidad'          => count($filas),
        ];

        foreach ($filas as $f) {
            $t['neto_gravado']       += (float)$f['neto_gravado'];
            $t['neto_no_gravado']    += (float)$f['neto_no_gravado'];
            $t['neto_exento']        += (float)$f['neto_exento'];
            $t['iva']                += (float)$f['iva'];
            $t['perc_iva']           += (float)$f['perc_iva'];
            $t['perc_iibb']          += (float)$f['perc_iibb'];
            $t['impuestos_internos'] += (float)$f['impuestos_internos'];
            $t['total']              += (float)$f['total'];
        }

        // Redondear
        foreach ($t as $k => $v) {
            if ($k !== 'cantidad') $t[$k] = round($v, 2);
        }

        return $t;
    }

        /* ==========================================================
       COMPARATIVA VENTAS vs COMPRAS (por mes)
       ========================================================== */
    public function comparativaVentasCompras(
        string $desde,
        string $hasta,
        ?int $emisorId = null
    ): array {
        // Normalizar el rango a meses completos (1ro del mes desde / último del mes hasta)
        $desdeMes = date('Y-m-01', strtotime($desde));
        $hastaMes = date('Y-m-t', strtotime($hasta));

        // Traer los dos conjuntos de datos
        $ventas  = $this->ventas($desdeMes, $hastaMes, $emisorId);
        $compras = $this->compras($desdeMes, $hastaMes, $emisorId);

        // Armar un índice de meses entre desdeMes y hastaMes
        $meses = $this->mesesEnRango($desdeMes, $hastaMes);

        // Estructura de salida: [ 'YYYY-MM' => [
        //     'anio' => 2026, 'mes' => 1, 'mes_label' => 'Enero 2026',
        //     'ventas'  => [neto, iva, perc_iibb, imp_int, total],
        //     'compras' => [neto, iva, perc_iibb, imp_int, total],
        //     'posicion'=> [neto, iva, perc_iibb, imp_int, total],
        // ]]
        $out = [];
        foreach ($meses as $ym => $info) {
            $out[$ym] = [
                'anio'       => $info['anio'],
                'mes'        => $info['mes'],
                'mes_label'  => $info['label'],
                'ventas'     => $this->totalesVacios(),
                'compras'    => $this->totalesVacios(),
                'posicion'   => $this->totalesVacios(),
            ];
        }

        // Volcar ventas
        foreach ($ventas as $v) {
            $ym = date('Y-m', strtotime($v['fecha']));
            if (!isset($out[$ym])) continue;

            $neto = (float)$v['neto_gravado']
                  + (float)$v['neto_no_gravado']
                  + (float)$v['neto_exento'];

            $out[$ym]['ventas']['neto']       += $neto;
            $out[$ym]['ventas']['iva']        += (float)$v['iva'];
            $out[$ym]['ventas']['perc_iibb']  += (float)$v['perc_iibb'];
            $out[$ym]['ventas']['imp_int']    += (float)$v['impuestos_internos'];
            $out[$ym]['ventas']['total']      += (float)$v['total'];
        }

        // Volcar compras
        foreach ($compras as $c) {
            $ym = date('Y-m', strtotime($c['fecha']));
            if (!isset($out[$ym])) continue;

            $neto = (float)$c['neto_gravado']
                  + (float)$c['neto_no_gravado']
                  + (float)$c['neto_exento'];

            $out[$ym]['compras']['neto']      += $neto;
            $out[$ym]['compras']['iva']       += (float)$c['iva'];
            $out[$ym]['compras']['perc_iibb'] += (float)$c['perc_iibb'];
            $out[$ym]['compras']['imp_int']   += (float)$c['impuestos_internos'];
            $out[$ym]['compras']['total']     += (float)$c['total'];
        }

        // Calcular posición = ventas - compras
        foreach ($out as $ym => &$r) {
            $r['posicion']['neto']      = round($r['ventas']['neto']      - $r['compras']['neto'], 2);
            $r['posicion']['iva']       = round($r['ventas']['iva']       - $r['compras']['iva'], 2);
            $r['posicion']['perc_iibb'] = round($r['ventas']['perc_iibb'] - $r['compras']['perc_iibb'], 2);
            $r['posicion']['imp_int']   = round($r['ventas']['imp_int']   - $r['compras']['imp_int'], 2);
            $r['posicion']['total']     = round($r['ventas']['total']     - $r['compras']['total'], 2);

            // Redondear los acumulados
            foreach (['ventas','compras'] as $tipo) {
                foreach ($r[$tipo] as $k => $v) {
                    $r[$tipo][$k] = round($v, 2);
                }
            }
        }
        unset($r);

        // Totales generales
        $totales = [
            'ventas'   => $this->totalesVacios(),
            'compras'  => $this->totalesVacios(),
            'posicion' => $this->totalesVacios(),
        ];
        foreach ($out as $r) {
            foreach (['ventas','compras','posicion'] as $tipo) {
                foreach ($r[$tipo] as $k => $v) {
                    $totales[$tipo][$k] += $v;
                }
            }
        }
        foreach ($totales as $tipo => &$t) {
            foreach ($t as $k => $v) $t[$k] = round($v, 2);
        }
        unset($t);

        return [
            'filas'   => array_values($out),
            'totales' => $totales,
            'desde'   => $desdeMes,
            'hasta'   => $hastaMes,
        ];
    }

    /* ==========================================================
       HELPERS PRIVADOS
       ========================================================== */
    private function totalesVacios(): array
    {
        return [
            'neto'      => 0.0,
            'iva'       => 0.0,
            'perc_iibb' => 0.0,
            'imp_int'   => 0.0,
            'total'     => 0.0,
        ];
    }

    private function mesesEnRango(string $desde, string $hasta): array
    {
        $meses = [];
        $cursor = strtotime($desde);
        $fin    = strtotime($hasta);

        $nombres = [
            1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril',
            5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto',
            9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre',
        ];

        while ($cursor <= $fin) {
            $anio = (int)date('Y', $cursor);
            $mes  = (int)date('n', $cursor);
            $ym   = date('Y-m', $cursor);

            $meses[$ym] = [
                'anio'  => $anio,
                'mes'   => $mes,
                'label' => $nombres[$mes] . ' ' . $anio,
            ];

            $cursor = strtotime('+1 month', $cursor);
        }
        return $meses;
    }
}