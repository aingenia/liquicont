<?php
// models/HistorialValores.php

require_once __DIR__ . '/../config/conexion.php';

class HistorialValores
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /**
     * Lista las entradas activas (no anuladas) de un tipo + referencia.
     * Ordena de la más reciente a la más antigua.
     */
    public function listar(string $tipo, int $referenciaId, int $limite = 20): array
    {
        $sql = "SELECT hv.*, u.nombre AS usuario_nombre
                FROM historial_valores hv
                LEFT JOIN usuario u ON u.id = hv.usuario_id
                WHERE hv.tipo = :tipo
                  AND hv.referencia_id = :ref
                  AND hv.anulado = 0
                ORDER BY hv.fecha DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':ref',  $referenciaId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Anula una entrada del historial y reengancha la cadena.
     *
     * Estrategia:
     *   1) Marca la entrada como anulada (soft-delete).
     *   2) Busca la siguiente entrada activa (mismo tipo + referencia, fecha >).
     *   3) Si existe, actualiza su valor_anterior al valor_anterior de la anulada
     *      y recalcula su variación_pct.
     *
     * @return array ['ok' => bool, 'msg' => string]
     */
    public function anular(int $id, int $usuarioId): array
    {
        try {
            $this->db->beginTransaction();

            // 1) Traer la entrada a anular
            $stmt = $this->db->prepare(
                "SELECT * FROM historial_valores WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $entrada = $stmt->fetch();

            if (!$entrada) {
                $this->db->rollBack();
                return ['ok' => false, 'msg' => 'La entrada no existe.'];
            }
            if ((int)$entrada['anulado'] === 1) {
                $this->db->rollBack();
                return ['ok' => false, 'msg' => 'La entrada ya estaba anulada.'];
            }

            // 2) Marcar como anulada
            $stmt = $this->db->prepare(
                "UPDATE historial_valores
                    SET anulado = 1, anulado_por = :u, anulado_el = NOW()
                  WHERE id = :id"
            );
            $stmt->execute([':u' => $usuarioId, ':id' => $id]);

            // 3) Buscar la siguiente activa
            $stmt = $this->db->prepare(
                "SELECT id, valor_nuevo
                   FROM historial_valores
                  WHERE tipo = :tipo
                    AND referencia_id = :ref
                    AND anulado = 0
                    AND fecha > :fecha
                  ORDER BY fecha ASC, id ASC
                  LIMIT 1"
            );
            $stmt->execute([
                ':tipo'  => $entrada['tipo'],
                ':ref'   => $entrada['referencia_id'],
                ':fecha' => $entrada['fecha'],
            ]);
            $siguiente = $stmt->fetch();

            if ($siguiente) {
                $nuevoAnterior = (float)$entrada['valor_anterior'];
                $nuevoValor    = (float)$siguiente['valor_nuevo'];
                $nuevaVariacion = $nuevoAnterior != 0
                    ? round((($nuevoValor - $nuevoAnterior) / $nuevoAnterior) * 100, 2)
                    : null;

                $stmt = $this->db->prepare(
                    "UPDATE historial_valores
                        SET valor_anterior = :va, variacion_pct = :vp
                      WHERE id = :id"
                );
                $stmt->execute([
                    ':va' => $nuevoAnterior,
                    ':vp' => $nuevaVariacion,
                    ':id' => $siguiente['id'],
                ]);
            }

            $this->db->commit();
            return ['ok' => true, 'msg' => 'Entrada anulada y cadena recalculada.'];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[HistorialValores::anular] ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Error al anular la entrada.'];
        }
    }
}