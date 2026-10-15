<?php
/**
 * models/EjercicioContable.php
 * Ejercicios contables: apertura, cierre y numeración.
 */

require_once __DIR__ . '/../config/conexion.php';

class EjercicioContable
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTADOS
       ========================================================== */
    public function all(): array
    {
        $sql = "SELECT e.*, u.nombre AS creado_por_nombre,
                       (SELECT COUNT(*) FROM asiento a WHERE a.ejercicio_id = e.id) AS cantidad_asientos
                FROM ejercicio_contable e
                LEFT JOIN usuario u ON u.id = e.creado_por_id
                ORDER BY e.fecha_desde DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function abiertos(): array
    {
        $sql = "SELECT * FROM ejercicio_contable
                WHERE estado = 'ABIERTO'
                ORDER BY fecha_desde DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /** Devuelve el ejercicio abierto más reciente, o null. */
    public function actual(): ?array
    {
        $sql = "SELECT * FROM ejercicio_contable
                WHERE estado = 'ABIERTO'
                ORDER BY fecha_desde DESC LIMIT 1";
        $row = $this->db->query($sql)->fetch();
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ejercicio_contable WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /* ==========================================================
       ALTA / EDICIÓN
       ========================================================== */
    public function crear(array $d, int $userId): array
    {
        $nombre      = trim($d['nombre'] ?? '');
        $fechaDesde  = $d['fecha_desde'] ?? '';
        $fechaHasta  = $d['fecha_hasta'] ?? '';

        $err = $this->validar($nombre, $fechaDesde, $fechaHasta);
        if ($err) return ['ok' => false, 'msg' => $err];

        // Chequeo de solapamiento con otros ejercicios
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM ejercicio_contable
             WHERE (fecha_desde <= :h AND fecha_hasta >= :d)"
        );
        $stmt->execute([':h' => $fechaHasta, ':d' => $fechaDesde]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'El rango se superpone con un ejercicio existente.'];
        }

        $sql = "INSERT INTO ejercicio_contable
                    (nombre, fecha_desde, fecha_hasta, estado, proximo_numero_asiento, creado_por_id)
                VALUES (:n, :d, :h, 'ABIERTO', 1, :u)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':n' => $nombre, ':d' => $fechaDesde, ':h' => $fechaHasta, ':u' => $userId,
        ]);
        return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    public function actualizar(int $id, array $d): array
    {
        $ej = $this->find($id);
        if (!$ej) return ['ok' => false, 'msg' => 'Ejercicio inexistente.'];

        // Solo se puede editar si está abierto y sin asientos confirmados
        if ($ej['estado'] === 'CERRADO') {
            return ['ok' => false, 'msg' => 'No se puede editar un ejercicio cerrado.'];
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM asiento WHERE ejercicio_id = :id AND estado = 'CONFIRMADO'"
        );
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'No se puede editar: ya tiene asientos confirmados.'];
        }

        $nombre     = trim($d['nombre'] ?? '');
        $fechaDesde = $d['fecha_desde'] ?? '';
        $fechaHasta = $d['fecha_hasta'] ?? '';

        $err = $this->validar($nombre, $fechaDesde, $fechaHasta);
        if ($err) return ['ok' => false, 'msg' => $err];

        // Solapamiento excluyendo este mismo
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM ejercicio_contable
             WHERE id <> :id AND (fecha_desde <= :h AND fecha_hasta >= :d)"
        );
        $stmt->execute([':id' => $id, ':h' => $fechaHasta, ':d' => $fechaDesde]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'El rango se superpone con otro ejercicio.'];
        }

        $sql = "UPDATE ejercicio_contable
                SET nombre = :n, fecha_desde = :d, fecha_hasta = :h
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':n' => $nombre, ':d' => $fechaDesde, ':h' => $fechaHasta, ':id' => $id,
        ]);
        return ['ok' => true];
    }

    public function cerrar(int $id, int $userId): array
    {
        $ej = $this->find($id);
        if (!$ej)                        return ['ok' => false, 'msg' => 'Ejercicio inexistente.'];
        if ($ej['estado'] === 'CERRADO') return ['ok' => false, 'msg' => 'Ya estaba cerrado.'];

        // Validar que no queden borradores
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM asiento WHERE ejercicio_id = :id AND estado = 'BORRADOR'"
        );
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'Hay asientos en BORRADOR. Confirmalos o anulalos antes de cerrar.'];
        }

        $sql = "UPDATE ejercicio_contable
                SET estado = 'CERRADO', cerrado_el = NOW(), cerrado_por_id = :u
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':u' => $userId, ':id' => $id]);
        return ['ok' => true];
    }

    public function reabrir(int $id): array
    {
        $ej = $this->find($id);
        if (!$ej) return ['ok' => false, 'msg' => 'Ejercicio inexistente.'];
        if ($ej['estado'] === 'ABIERTO') return ['ok' => false, 'msg' => 'Ya estaba abierto.'];

        $sql = "UPDATE ejercicio_contable
                SET estado = 'ABIERTO', cerrado_el = NULL, cerrado_por_id = NULL
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return ['ok' => true];
    }

    public function eliminar(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM asiento WHERE ejercicio_id = :id"
        );
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'No se puede eliminar: tiene asientos asociados.'];
        }
        $stmt = $this->db->prepare("DELETE FROM ejercicio_contable WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['ok' => true];
    }

    /* ==========================================================
       HELPERS
       ========================================================== */
    public function proximoNumeroAsiento(int $ejercicioId, int $emisorId): int
    {
        $sql = "SELECT COALESCE(MAX(numero), 0) + 1
                FROM asiento
                WHERE ejercicio_id = :e AND emisor_id = :m";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $ejercicioId, ':m' => $emisorId]);
        return (int)$stmt->fetchColumn();
    }

    private function validar(string $nombre, string $desde, string $hasta): ?string
    {
        if ($nombre === '')                return 'El nombre es obligatorio.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) return 'Fecha desde inválida.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) return 'Fecha hasta inválida.';
        if (strtotime($desde) > strtotime($hasta)) return 'La fecha desde no puede ser posterior a la hasta.';
        return null;
    }
}