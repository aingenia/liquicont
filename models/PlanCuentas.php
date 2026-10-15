<?php
/**
 * models/PlanCuentas.php
 * Plan de cuentas jerárquico. Compartido entre emisores.
 */

require_once __DIR__ . '/../config/conexion.php';

class PlanCuentas
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       CONSULTAS
       ========================================================== */
    public function all(): array
    {
        $sql = "SELECT * FROM plan_cuentas ORDER BY codigo ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /** Solo cuentas imputables (las que reciben asientos). */
    public function imputables(): array
    {
        $sql = "SELECT * FROM plan_cuentas
                WHERE imputable = 1 AND estado = 'ACTIVA'
                ORDER BY codigo ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM plan_cuentas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function porCodigo(string $codigo): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM plan_cuentas WHERE codigo = :c");
        $stmt->execute([':c' => $codigo]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hijos(int $padreId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM plan_cuentas WHERE padre_id = :p ORDER BY codigo ASC"
        );
        $stmt->execute([':p' => $padreId]);
        return $stmt->fetchAll();
    }

    /** Devuelve el árbol completo anidado. */
    public function arbol(): array
    {
        $todas = $this->all();
        $byId  = [];
        foreach ($todas as $c) {
            $c['hijos'] = [];
            $byId[$c['id']] = $c;
        }
        $raices = [];
        foreach ($byId as $id => &$c) {
            if ($c['padre_id'] && isset($byId[$c['padre_id']])) {
                $byId[$c['padre_id']]['hijos'][] = &$c;
            } else {
                $raices[] = &$c;
            }
        }
        unset($c);
        return $raices;
    }

    /* ==========================================================
       ALTA / EDICIÓN
       ========================================================== */
    public function crear(array $d): array
    {
        $codigo     = trim($d['codigo'] ?? '');
        $nombre     = trim($d['nombre'] ?? '');
        $tipo       = $d['tipo'] ?? '';
        $naturaleza = $d['naturaleza'] ?? '';
        $padreId    = !empty($d['padre_id']) ? (int)$d['padre_id'] : null;
        $imputable  = !empty($d['imputable']) ? 1 : 0;
        $orden      = (int)($d['orden'] ?? 0);

        $err = $this->validar($codigo, $nombre, $tipo, $naturaleza);
        if ($err) return ['ok' => false, 'msg' => $err];

        if ($this->porCodigo($codigo)) {
            return ['ok' => false, 'msg' => 'Ya existe una cuenta con ese código.'];
        }

        $sql = "INSERT INTO plan_cuentas
                    (codigo, nombre, tipo, naturaleza, padre_id, imputable, orden)
                VALUES (:c, :n, :t, :nat, :p, :i, :o)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':c' => $codigo, ':n' => $nombre, ':t' => $tipo, ':nat' => $naturaleza,
            ':p' => $padreId, ':i' => $imputable, ':o' => $orden,
        ]);
        return ['ok' => true, 'id' => (int)$this->db->lastInsertId()];
    }

    public function actualizar(int $id, array $d): array
    {
        $c = $this->find($id);
        if (!$c) return ['ok' => false, 'msg' => 'Cuenta inexistente.'];

        $codigo     = trim($d['codigo'] ?? '');
        $nombre     = trim($d['nombre'] ?? '');
        $tipo       = $d['tipo'] ?? '';
        $naturaleza = $d['naturaleza'] ?? '';
        $padreId    = !empty($d['padre_id']) ? (int)$d['padre_id'] : null;
        $imputable  = !empty($d['imputable']) ? 1 : 0;
        $orden      = (int)($d['orden'] ?? 0);

        $err = $this->validar($codigo, $nombre, $tipo, $naturaleza);
        if ($err) return ['ok' => false, 'msg' => $err];

        // Código duplicado (excluyendo esta cuenta)
        $stmt = $this->db->prepare("SELECT id FROM plan_cuentas WHERE codigo = :c AND id <> :id");
        $stmt->execute([':c' => $codigo, ':id' => $id]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'msg' => 'Ya existe otra cuenta con ese código.'];
        }

        // No puede ser su propio padre
        if ($padreId === $id) {
            return ['ok' => false, 'msg' => 'Una cuenta no puede ser padre de sí misma.'];
        }

        // Si tiene movimientos, no cambiar tipo ni naturaleza
        if ($this->tieneMovimientos($id) && ($c['tipo'] !== $tipo || $c['naturaleza'] !== $naturaleza)) {
            return ['ok' => false, 'msg' => 'No se puede cambiar tipo/naturaleza: la cuenta tiene movimientos.'];
        }

        $sql = "UPDATE plan_cuentas
                SET codigo = :c, nombre = :n, tipo = :t, naturaleza = :nat,
                    padre_id = :p, imputable = :i, orden = :o
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':c' => $codigo, ':n' => $nombre, ':t' => $tipo, ':nat' => $naturaleza,
            ':p' => $padreId, ':i' => $imputable, ':o' => $orden, ':id' => $id,
        ]);
        return ['ok' => true];
    }

    public function cambiarEstado(int $id, string $estado): array
    {
        if (!in_array($estado, ['ACTIVA','INACTIVA'], true)) {
            return ['ok' => false, 'msg' => 'Estado inválido.'];
        }
        $stmt = $this->db->prepare("UPDATE plan_cuentas SET estado = :e WHERE id = :id");
        $stmt->execute([':e' => $estado, ':id' => $id]);
        return ['ok' => true];
    }

    public function tieneMovimientos(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM asiento_linea WHERE cuenta_id = :id");
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function estaEnUso(int $id): bool
    {
        // En uso = tiene movimientos o está referenciada en config contable
        if ($this->tieneMovimientos($id)) return true;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM configuracion_contable WHERE cuenta_id = :id");
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function validar(string $codigo, string $nombre, string $tipo, string $naturaleza): ?string
    {
        if ($codigo === '') return 'El código es obligatorio.';
        if ($nombre === '') return 'El nombre es obligatorio.';
        $tiposOk = ['ACTIVO','PASIVO','PATRIMONIO','RESULTADO_POSITIVO','RESULTADO_NEGATIVO'];
        if (!in_array($tipo, $tiposOk, true)) return 'Tipo inválido.';
        if (!in_array($naturaleza, ['DEUDORA','ACREEDORA'], true)) return 'Naturaleza inválida.';
        return null;
    }
}