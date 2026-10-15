<?php
// models/ListaPrecios.php

require_once __DIR__ . '/../config/conexion.php';

class ListaPrecios
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       AGRUPACIÓN DE ARTÍCULOS PARA LA LISTA
       ========================================================== */

    /**
     * Devuelve los artículos de la lista agrupados por volumen,
     * filtrados por la categoría indicada (solo los que tienen
     * `visible = 1` para esa categoría).
     *
     * Estructura de retorno:
     *   [
     *     200 => [
     *       'volumen_cc'        => 200,
     *       'nombre'            => 'Petacas',
     *       'unidades_por_caja' => 24,
     *       'items' => [
     *         [
     *           'articulo_id'   => 5,
     *           'codigo'        => '200CC',
     *           'codigo_corto'  => 'CC',
     *           'nombre'        => 'Café al Cognac x 200cc',
     *           'precio_unidad' => 895.00,
     *           'precio_caja'   => 21480.00,
     *         ],
     *         ...
     *       ]
     *     ],
     *     750 => [...],
     *   ]
     */
    public function articulosAgrupados(int $categoriaId): array
    {
        // 1) Traer todos los artículos con precio visible en esa categoría
        $sql = "SELECT a.id, a.codigo, a.nombre,
                       apc.precio AS precio_unidad
                FROM articulo a
                INNER JOIN articulo_precio_categoria apc
                        ON apc.articulo_id = a.id
                       AND apc.categoria_id = :cat
                       AND apc.visible = 1
                WHERE a.estado = 'ACTIVO'
                  AND a.codigo IS NOT NULL
                  AND a.codigo <> ''
                ORDER BY a.codigo ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cat' => $categoriaId]);
        $articulos = $stmt->fetchAll();

        // 2) Empaques por volumen (con nombre)
        $empaques = [];
        foreach ($this->volumenesConNombre() as $e) {
            $empaques[(int)$e['volumen_cc']] = [
                'unidades_por_caja' => (int)$e['unidades_por_caja'],
                'nombre'            => $e['nombre'] ?: ('Artículos ' . (int)$e['volumen_cc'] . ' cc'),
            ];
        }

        // 3) Agrupar por volumen extraído del código
        $grupos = [];

        foreach ($articulos as $a) {
            // Extraer volumen y código corto con regex
            if (preg_match('/^(\d+)(.*)$/', (string)$a['codigo'], $m)) {
                $volumen     = (int)$m[1];
                $codigoCorto = trim($m[2]);
            } else {
                // Código sin volumen al inicio → grupo "Otros"
                $volumen     = 0;
                $codigoCorto = (string)$a['codigo'];
            }

            if (!isset($grupos[$volumen])) {
                $emp = $empaques[$volumen] ?? [
                    'unidades_por_caja' => 0,
                    'nombre'            => $volumen > 0 ? ('Artículos ' . $volumen . ' cc') : 'Otros',
                ];

                $grupos[$volumen] = [
                    'volumen_cc'        => $volumen,
                    'nombre'            => $emp['nombre'],
                    'unidades_por_caja' => $emp['unidades_por_caja'],
                    'items'             => [],
                ];
            }

            $precioUnidad = (float)$a['precio_unidad'];
            $precioCaja = null;
            if ($grupos[$volumen]['unidades_por_caja'] > 0) {
                $precioCaja = round($precioUnidad * $grupos[$volumen]['unidades_por_caja'], 2);
            }

            $grupos[$volumen]['items'][] = [
                'articulo_id'   => (int)$a['id'],
                'codigo'        => (string)$a['codigo'],
                'codigo_corto'  => $codigoCorto,
                'nombre'        => (string)$a['nombre'],
                'precio_unidad' => $precioUnidad,
                'precio_caja'   => $precioCaja,
            ];
        }

        // 4) Ordenar los items de cada grupo alfabéticamente por nombre
        foreach ($grupos as &$g) {
            usort($g['items'], function ($a, $b) {
                return strcasecmp($a['nombre'], $b['nombre']);
            });
        }
        unset($g);

        // 5) Ordenar los grupos por volumen ascendente, dejando "Otros" (0) al final
        uksort($grupos, function ($a, $b) {
            if ($a === 0) return 1;
            if ($b === 0) return -1;
            return $a - $b;
        });

        return $grupos;
    }

    /* ==========================================================
       VOLÚMENES CON NOMBRE (para Configuración y para la lista)
       ========================================================== */
    public function volumenesConNombre(): array
    {
        $sql = "SELECT volumen_cc, unidades_por_caja, nombre
                FROM lista_precios_empaque
                ORDER BY volumen_cc ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /* ==========================================================
       ACTUALIZAR EL NOMBRE DE UN VOLUMEN
       ========================================================== */
    public function actualizarNombreVolumen(int $volumenCc, string $nombre): bool
    {
        $sql = "UPDATE lista_precios_empaque SET nombre = :n WHERE volumen_cc = :v";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':n' => $nombre, ':v' => $volumenCc]);
    }

    /* ==========================================================
       CLIENTES POR CATEGORÍA (con email) — para el modal de envío
       ========================================================== */
    /**
     * Devuelve los clientes con al menos un email cargado, de la
     * categoría indicada. Se usan el email general y el de facturación
     * (si están ambos, se prefieren ambos en el mismo envío).
     */
    public function clientesConEmailPorCategoria(int $categoriaId): array
    {
        $sql = "SELECT id, razon_social, nombre_fantasia, cuit,
                       email, email_facturacion
                FROM cliente
                WHERE categoria = :cat
                  AND estado = 'ACTIVO'
                  AND (
                       (email IS NOT NULL AND email <> '')
                    OR (email_facturacion IS NOT NULL AND email_facturacion <> '')
                  )
                ORDER BY razon_social ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cat' => $categoriaId]);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CATEGORÍAS (para el select de la vista)
       ========================================================== */
    public function categorias(): array
    {
        return $this->db->query(
            "SELECT id, descripcion FROM cliente_categoria ORDER BY id"
        )->fetchAll();
    }
}