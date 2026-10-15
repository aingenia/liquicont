<?php
/**
 * models/Asiento.php
 * Asientos contables (partida doble) + reportes.
 */

require_once __DIR__ . '/../config/conexion.php';

class Asiento
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       LISTADO
       ========================================================== */
    public function listar(array $f = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($f['ejercicio_id'])) {
            $where[] = 'a.ejercicio_id = :ej';
            $params[':ej'] = (int)$f['ejercicio_id'];
        }
        if (!empty($f['emisor_id'])) {
            $where[] = 'a.emisor_id = :em';
            $params[':em'] = (int)$f['emisor_id'];
        }
        if (!empty($f['estado'])) {
            $where[] = 'a.estado = :es';
            $params[':es'] = $f['estado'];
        }
        if (!empty($f['tipo'])) {
            $where[] = 'a.tipo = :ti';
            $params[':ti'] = $f['tipo'];
        }
        if (!empty($f['desde'])) {
            $where[] = 'a.fecha >= :d';
            $params[':d'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where[] = 'a.fecha <= :h';
            $params[':h'] = $f['hasta'];
        }
        if (!empty($f['texto'])) {
            $where[] = '(a.descripcion LIKE :tx OR a.numero = :txn)';
            $params[':tx']  = '%' . $f['texto'] . '%';
            $params[':txn'] = (int)$f['texto'];
        }

        $sql = "SELECT a.*, e.nombre AS emisor_nombre, u.nombre AS creado_por_nombre
                FROM asiento a
                INNER JOIN emisor  e ON e.id = a.emisor_id
                INNER JOIN usuario u ON u.id = a.creado_por_id";

        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY a.fecha DESC, a.numero DESC LIMIT 500';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /* ==========================================================
       CONSULTAS POR ID
       ========================================================== */
    public function find(int $id): ?array
    {
        $sql = "SELECT a.*, e.nombre AS emisor_nombre, u.nombre AS creado_por_nombre,
                       ej.nombre AS ejercicio_nombre
                FROM asiento a
                INNER JOIN emisor              e  ON e.id  = a.emisor_id
                INNER JOIN usuario             u  ON u.id  = a.creado_por_id
                INNER JOIN ejercicio_contable  ej ON ej.id = a.ejercicio_id
                WHERE a.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $cab = $stmt->fetch();
        if (!$cab) return null;

        $stmt = $this->db->prepare(
            "SELECT al.*, pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
             FROM asiento_linea al
             INNER JOIN plan_cuentas pc ON pc.id = al.cuenta_id
             WHERE al.asiento_id = :id
             ORDER BY al.orden ASC, al.id ASC"
        );
        $stmt->execute([':id' => $id]);
        $cab['lineas'] = $stmt->fetchAll();
        return $cab;
    }

    /* ==========================================================
       ALTA
       ==========================================================
       $cab: ejercicio_id, emisor_id, fecha, tipo, descripcion,
             referencia_tipo, referencia_id
       $lineas: array de { cuenta_id, debe, haber, detalle,
                           auxiliar_tipo, auxiliar_id }
       $estadoInicial: 'BORRADOR' (default, manual) | 'CONFIRMADO' (automático)
       ========================================================== */
    public function crear(
        array $cab,
        array $lineas,
        int $userId,
        string $estadoInicial = 'BORRADOR'
    ): array {
        $err = $this->validarCabecera($cab);
        if ($err) return ['ok' => false, 'msg' => $err];

        $err = $this->validarLineas($lineas);
        if ($err) return ['ok' => false, 'msg' => $err];

        if (!in_array($estadoInicial, ['BORRADOR', 'CONFIRMADO'], true)) {
            return ['ok' => false, 'msg' => 'Estado inicial inválido.'];
        }

        try {
            $this->db->beginTransaction();

            // Bloquear ejercicio para numerar
            $stmt = $this->db->prepare(
                "SELECT * FROM ejercicio_contable WHERE id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => (int)$cab['ejercicio_id']]);
            $ej = $stmt->fetch();
            if (!$ej)                        throw new Exception('Ejercicio inexistente.');
            if ($ej['estado'] === 'CERRADO') throw new Exception('El ejercicio está cerrado.');
            if ($cab['fecha'] < $ej['fecha_desde'] || $cab['fecha'] > $ej['fecha_hasta']) {
                throw new Exception('La fecha está fuera del rango del ejercicio.');
            }

            // Próximo número por (ejercicio, emisor)
            $stmt = $this->db->prepare(
                "SELECT COALESCE(MAX(numero), 0) + 1
                 FROM asiento WHERE ejercicio_id = :e AND emisor_id = :m"
            );
            $stmt->execute([':e' => (int)$cab['ejercicio_id'], ':m' => (int)$cab['emisor_id']]);
            $numero = (int)$stmt->fetchColumn();

            $totDebe  = array_sum(array_map(fn($l) => (float)$l['debe'],  $lineas));
            $totHaber = array_sum(array_map(fn($l) => (float)$l['haber'], $lineas));

            // Confirmación inline: si ya nace CONFIRMADO, guardamos el timestamp
            $sql = "INSERT INTO asiento
                        (ejercicio_id, emisor_id, numero, fecha, tipo, descripcion,
                         referencia_tipo, referencia_id, total_debe, total_haber,
                         estado, creado_por_id, confirmado_el, confirmado_por_id)
                    VALUES
                        (:ej, :em, :nu, :fe, :ti, :de,
                         :rt, :ri, :td, :th, :est, :us, " .
                         ($estadoInicial === 'CONFIRMADO' ? 'NOW()' : 'NULL') . ", " .
                         ($estadoInicial === 'CONFIRMADO' ? ':us2' : 'NULL') . ")";

            $stmt = $this->db->prepare($sql);
            $params = [
                ':ej'  => (int)$cab['ejercicio_id'],
                ':em'  => (int)$cab['emisor_id'],
                ':nu'  => $numero,
                ':fe'  => $cab['fecha'],
                ':ti'  => $cab['tipo'] ?? 'DIARIO',
                ':de'  => trim($cab['descripcion']),
                ':rt'  => $cab['referencia_tipo'] ?? null,
                ':ri'  => $cab['referencia_id']   ?? null,
                ':td'  => $totDebe,
                ':th'  => $totHaber,
                ':est' => $estadoInicial,
                ':us'  => $userId,
            ];
            if ($estadoInicial === 'CONFIRMADO') {
                $params[':us2'] = $userId;
            }
            $stmt->execute($params);

            $asientoId = (int)$this->db->lastInsertId();

            $this->insertarLineas($asientoId, $lineas);

            $this->db->commit();
            return ['ok' => true, 'id' => $asientoId, 'numero' => $numero];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /* ==========================================================
       EDICIÓN (solo BORRADOR)
       ========================================================== */
    public function actualizar(int $id, array $cab, array $lineas): array
    {
        $a = $this->find($id);
        if (!$a) return ['ok' => false, 'msg' => 'Asiento inexistente.'];
        if ($a['estado'] !== 'BORRADOR') {
            return ['ok' => false, 'msg' => 'Solo se pueden editar asientos en BORRADOR.'];
        }

        $err = $this->validarCabecera($cab);
        if ($err) return ['ok' => false, 'msg' => $err];

        $err = $this->validarLineas($lineas);
        if ($err) return ['ok' => false, 'msg' => $err];

        try {
            $this->db->beginTransaction();

            $totDebe  = array_sum(array_map(fn($l) => (float)$l['debe'],  $lineas));
            $totHaber = array_sum(array_map(fn($l) => (float)$l['haber'], $lineas));

            $sql = "UPDATE asiento
                    SET fecha = :fe, tipo = :ti, descripcion = :de,
                        total_debe = :td, total_haber = :th
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':fe' => $cab['fecha'],
                ':ti' => $cab['tipo'] ?? 'DIARIO',
                ':de' => trim($cab['descripcion']),
                ':td' => $totDebe,
                ':th' => $totHaber,
                ':id' => $id,
            ]);

            $this->db->prepare("DELETE FROM asiento_linea WHERE asiento_id = :id")
                     ->execute([':id' => $id]);

            $this->insertarLineas($id, $lineas);

            $this->db->commit();
            return ['ok' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /* ==========================================================
       CONFIRMAR / ANULAR / ELIMINAR
       ========================================================== */
    public function confirmar(int $id, int $userId): array
    {
        $a = $this->find($id);
        if (!$a)                           return ['ok' => false, 'msg' => 'Asiento inexistente.'];
        if ($a['estado'] === 'CONFIRMADO') return ['ok' => false, 'msg' => 'Ya está confirmado.'];
        if ($a['estado'] === 'ANULADO')    return ['ok' => false, 'msg' => 'Está anulado.'];

        if (abs((float)$a['total_debe'] - (float)$a['total_haber']) > 0.01) {
            return ['ok' => false, 'msg' => 'El asiento no balancea.'];
        }

        $sql = "UPDATE asiento
                SET estado = 'CONFIRMADO', confirmado_el = NOW(), confirmado_por_id = :u
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':u' => $userId, ':id' => $id]);
        return ['ok' => true];
    }

    public function anular(int $id, int $userId, string $motivo): array
    {
        $a = $this->find($id);
        if (!$a) return ['ok' => false, 'msg' => 'Asiento inexistente.'];
        if ($a['estado'] === 'ANULADO') return ['ok' => false, 'msg' => 'Ya está anulado.'];

        $sql = "UPDATE asiento
                SET estado = 'ANULADO', anulado_el = NOW(),
                    anulado_por_id = :u, anulado_motivo = :m
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':u' => $userId, ':m' => $motivo, ':id' => $id]);
        return ['ok' => true];
    }

    public function eliminar(int $id): array
    {
        $a = $this->find($id);
        if (!$a) return ['ok' => false, 'msg' => 'Asiento inexistente.'];
        if ($a['estado'] !== 'BORRADOR') {
            return ['ok' => false, 'msg' => 'Solo se pueden eliminar asientos en BORRADOR.'];
        }
        $this->db->prepare("DELETE FROM asiento WHERE id = :id")->execute([':id' => $id]);
        return ['ok' => true];
    }

    /* ==========================================================
       REPORTES
       ========================================================== */
    public function libroDiario(string $desde, string $hasta, ?int $emisorId, ?int $ejercicioId = null): array
    {
        $where = ["a.fecha BETWEEN :d AND :h", "a.estado = 'CONFIRMADO'"];
        $params = [':d' => $desde, ':h' => $hasta];

        if ($emisorId) {
            $where[] = 'a.emisor_id = :em';
            $params[':em'] = $emisorId;
        }
        if ($ejercicioId) {
            $where[] = 'a.ejercicio_id = :ej';
            $params[':ej'] = $ejercicioId;
        }

        $sql = "SELECT a.id AS asiento_id, a.numero, a.fecha, a.tipo, a.descripcion AS asiento_desc,
                       e.nombre AS emisor_nombre,
                       al.debe, al.haber, al.detalle AS linea_detalle,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre
                FROM asiento a
                INNER JOIN asiento_linea al ON al.asiento_id = a.id
                INNER JOIN plan_cuentas  pc ON pc.id = al.cuenta_id
                INNER JOIN emisor        e  ON e.id  = a.emisor_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.fecha ASC, a.numero ASC, al.orden ASC, al.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function libroMayor(int $cuentaId, string $desde, string $hasta, ?int $emisorId = null): array
    {
        $sql = "SELECT COALESCE(SUM(al.debe),0) AS d, COALESCE(SUM(al.haber),0) AS h
                FROM asiento_linea al
                INNER JOIN asiento a ON a.id = al.asiento_id
                WHERE al.cuenta_id = :cta
                  AND a.estado = 'CONFIRMADO'
                  AND a.fecha < :d";
        $params = [':cta' => $cuentaId, ':d' => $desde];
        if ($emisorId) { $sql .= ' AND a.emisor_id = :em'; $params[':em'] = $emisorId; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $ini = $stmt->fetch();
        $saldoInicial = (float)$ini['d'] - (float)$ini['h'];

        $where  = ["al.cuenta_id = :cta", "a.estado = 'CONFIRMADO'", "a.fecha BETWEEN :d AND :h"];
        $params = [':cta' => $cuentaId, ':d' => $desde, ':h' => $hasta];
        if ($emisorId) { $where[] = 'a.emisor_id = :em'; $params[':em'] = $emisorId; }

        $sql = "SELECT a.numero, a.fecha, a.tipo, a.descripcion AS asiento_desc,
                       al.debe, al.haber, al.detalle AS linea_detalle,
                       e.nombre AS emisor_nombre
                FROM asiento_linea al
                INNER JOIN asiento a ON a.id = al.asiento_id
                INNER JOIN emisor   e ON e.id = a.emisor_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.fecha ASC, a.numero ASC, al.orden ASC, al.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $movs = $stmt->fetchAll();

        $saldo = $saldoInicial;
        foreach ($movs as &$m) {
            $saldo += (float)$m['debe'] - (float)$m['haber'];
            $m['saldo'] = round($saldo, 2);
        }
        unset($m);

        return [
            'saldo_inicial' => round($saldoInicial, 2),
            'movimientos'   => $movs,
            'saldo_final'   => round($saldo, 2),
        ];
    }

    public function balanceSumasYSaldos(string $desde, string $hasta, ?int $emisorId = null): array
    {
        $where  = ["a.estado = 'CONFIRMADO'", "a.fecha BETWEEN :d AND :h"];
        $params = [':d' => $desde, ':h' => $hasta];
        if ($emisorId) { $where[] = 'a.emisor_id = :em'; $params[':em'] = $emisorId; }

        $sql = "SELECT pc.id, pc.codigo, pc.nombre, pc.tipo, pc.naturaleza,
                       COALESCE(SUM(al.debe), 0)  AS sumas_debe,
                       COALESCE(SUM(al.haber), 0) AS sumas_haber
                FROM asiento_linea al
                INNER JOIN asiento a     ON a.id  = al.asiento_id
                INNER JOIN plan_cuentas pc ON pc.id = al.cuenta_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY pc.id, pc.codigo, pc.nombre, pc.tipo, pc.naturaleza
                HAVING sumas_debe <> 0 OR sumas_haber <> 0
                ORDER BY pc.codigo ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totD = $totH = $totSD = $totSA = 0.0;
        foreach ($rows as &$r) {
            $r['sumas_debe']  = round((float)$r['sumas_debe'], 2);
            $r['sumas_haber'] = round((float)$r['sumas_haber'], 2);
            $saldo = $r['sumas_debe'] - $r['sumas_haber'];
            $r['saldo_deudor']  = $saldo > 0 ? round($saldo, 2) : 0;
            $r['saldo_acreedor'] = $saldo < 0 ? round(-$saldo, 2) : 0;

            $totD  += $r['sumas_debe'];
            $totH  += $r['sumas_haber'];
            $totSD += $r['saldo_deudor'];
            $totSA += $r['saldo_acreedor'];
        }
        unset($r);

        return [
            'filas'   => $rows,
            'totales' => [
                'sumas_debe'    => round($totD, 2),
                'sumas_haber'   => round($totH, 2),
                'saldo_deudor'  => round($totSD, 2),
                'saldo_acreedor'=> round($totSA, 2),
            ],
        ];
    }

    /* ==========================================================
    SALDOS A UNA FECHA (para Estados Contables)
    ==========================================================
    Devuelve un array [cuenta_id => ['debe'=>X, 'haber'=>Y]]
    con las sumas acumuladas entre $desde y $hasta.
    Si $desde es null, toma desde el inicio de los tiempos.
    Solo cuenta asientos CONFIRMADOS.
    ========================================================== */
    public function saldosPorCuenta(
        ?string $desde,
        string  $hasta,
        ?int    $emisorId = null
    ): array {
        $where  = ["a.estado = 'CONFIRMADO'", "a.fecha <= :h"];
        $params = [':h' => $hasta];

        if ($desde) {
            $where[] = 'a.fecha >= :d';
            $params[':d'] = $desde;
        }
        if ($emisorId) {
            $where[] = 'a.emisor_id = :em';
            $params[':em'] = $emisorId;
        }

        $sql = "SELECT al.cuenta_id,
                       COALESCE(SUM(al.debe), 0)  AS debe,
                       COALESCE(SUM(al.haber), 0) AS haber
                FROM asiento_linea al
                INNER JOIN asiento a ON a.id = al.asiento_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY al.cuenta_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['cuenta_id']] = [
                'debe'  => (float)$r['debe'],
                'haber' => (float)$r['haber'],
            ];
        }
        return $out;
    }

    /* ==========================================================
       SALDOS POR GRUPO (agrupado por cuenta padre / rubro)
       ==========================================================
       Recibe el resultado de saldosPorCuenta() y devuelve un
       árbol de rubros con totales. Se usa desde EstadosContables.
       ========================================================== */
    public function saldosPorGrupo(
        ?string $desde,
        string  $hasta,
        array   $tipos,
        ?int    $emisorId = null
    ): array {
        require_once __DIR__ . '/PlanCuentas.php';
        $pcModel = new PlanCuentas();

        $saldos = $this->saldosPorCuenta($desde, $hasta, $emisorId);

        $cuentas = $pcModel->all();
        $byId    = [];
        foreach ($cuentas as $c) $byId[(int)$c['id']] = $c;

        // Agrupar por padre inmediato
        $grupos = [];
        foreach ($saldos as $cuentaId => $s) {
            if (!isset($byId[$cuentaId])) continue;
            $c = $byId[$cuentaId];

            // Filtrar por tipos pedidos (ACTIVO, PASIVO, RESULTADO_*, etc.)
            if (!in_array($c['tipo'], $tipos, true)) continue;

            $padre = null;
            if (!empty($c['padre_id']) && isset($byId[(int)$c['padre_id']])) {
                $padre = $byId[(int)$c['padre_id']];
            }

            // Calcular saldo según naturaleza
            $saldo = ($c['naturaleza'] === 'DEUDORA')
                ? ($s['debe'] - $s['haber'])
                : ($s['haber'] - $s['debe']);

            if (abs($saldo) < 0.005) continue;  // omitir cuentas sin movimiento

            $grupoKey = $padre
                ? $padre['codigo']
                : $c['codigo']; // si no tiene padre, se agrupa solo

            if (!isset($grupos[$grupoKey])) {
                $grupos[$grupoKey] = [
                    'codigo'  => $grupoKey,
                    'nombre'  => $padre ? $padre['nombre'] : $c['nombre'],
                    'tipo'    => $c['tipo'],
                    'cuentas' => [],
                    'total'   => 0.0,
                ];
            }

            $grupos[$grupoKey]['cuentas'][] = [
                'cuenta_id' => $cuentaId,
                'codigo'    => $c['codigo'],
                'nombre'    => $c['nombre'],
                'naturaleza'=> $c['naturaleza'],
                'tipo'      => $c['tipo'],
                'saldo'     => round($saldo, 2),
            ];
            $grupos[$grupoKey]['total'] += $saldo;
        }

        // Redondear y ordenar
        foreach ($grupos as &$g) {
            $g['total'] = round($g['total'], 2);
            usort($g['cuentas'], fn($a, $b) => strcmp($a['codigo'], $b['codigo']));
        }
        unset($g);

        ksort($grupos);
        return array_values($grupos);
    }

    /* ==========================================================
       HELPERS PRIVADOS
       ========================================================== */
    private function insertarLineas(int $asientoId, array $lineas): void
    {
        $sql = "INSERT INTO asiento_linea
                    (asiento_id, cuenta_id, debe, haber, detalle,
                     auxiliar_tipo, auxiliar_id, orden)
                VALUES
                    (:a, :c, :d, :h, :de, :at, :ai, :o)";
        $stmt = $this->db->prepare($sql);
        $orden = 0;
        foreach ($lineas as $l) {
            $stmt->execute([
                ':a'  => $asientoId,
                ':c'  => (int)$l['cuenta_id'],
                ':d'  => (float)$l['debe'],
                ':h'  => (float)$l['haber'],
                ':de' => $l['detalle'] ?? null,
                ':at' => $l['auxiliar_tipo'] ?? 'NINGUNO',
                ':ai' => $l['auxiliar_id']   ?? null,
                ':o'  => $orden++,
            ]);
        }
    }

    private function validarCabecera(array $cab): ?string
    {
        if (empty($cab['ejercicio_id'])) return 'Falta el ejercicio.';
        if (empty($cab['emisor_id']))    return 'Falta el emisor.';
        if (empty($cab['fecha']))        return 'Falta la fecha.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cab['fecha'])) return 'Fecha inválida.';
        if (empty(trim($cab['descripcion'] ?? ''))) return 'La descripción es obligatoria.';
        return null;
    }

    private function validarLineas(array $lineas): ?string
    {
        $lineas = array_values(array_filter($lineas, function ($l) {
            return !empty($l['cuenta_id']) &&
                   ((float)($l['debe'] ?? 0) != 0 || (float)($l['haber'] ?? 0) != 0);
        }));

        if (count($lineas) < 2) return 'El asiento necesita al menos 2 líneas con importe.';

        $td = 0.0; $th = 0.0;
        foreach ($lineas as $l) {
            $d = (float)($l['debe']  ?? 0);
            $h = (float)($l['haber'] ?? 0);
            if ($d < 0 || $h < 0) return 'No se permiten importes negativos.';
            if ($d > 0 && $h > 0) return 'Una línea no puede tener debe y haber a la vez.';
            $td += $d; $th += $h;
        }

        if (abs($td - $th) > 0.01) {
            return 'El asiento no balancea. Debe: ' . number_format($td, 2, ',', '.') .
                   ' — Haber: ' . number_format($th, 2, ',', '.');
        }
        if ($td == 0) return 'El asiento no puede tener importe cero.';
        return null;
    }
}