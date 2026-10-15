<?php
/**
 * models/EstadosContables.php
 * Genera Estado de Resultados y Balance General.
 */

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/Asiento.php';
require_once __DIR__ . '/EjercicioContable.php';

class EstadosContables
{
    private PDO $db;
    private Asiento $asientos;
    private EjercicioContable $ejercicios;

    public function __construct()
    {
        $this->db         = Conexion::getConnection();
        $this->asientos   = new Asiento();
        $this->ejercicios = new EjercicioContable();
    }

    /* ==========================================================
       ESTADO DE RESULTADOS
       ========================================================== */
    public function estadoResultados(
        int $ejercicioId,
        ?int $emisorId = null,
        ?int $ejercicioCompararId = null
    ): array {
        $ej = $this->ejercicios->find($ejercicioId);
        if (!$ej) return ['ok' => false, 'msg' => 'Ejercicio inexistente.'];

        // Actual
        $grupos = $this->asientos->saldosPorGrupo(
            $ej['fecha_desde'],
            $ej['fecha_hasta'],
            ['RESULTADO_POSITIVO', 'RESULTADO_NEGATIVO'],
            $emisorId
        );

        $actual = $this->clasificarGrupos($grupos, 'resultado');
        $totalActual = $this->totalDeGrupos($grupos, 'resultado');

        // Comparativo
        $anterior = null;
        $totalAnterior = null;
        if ($ejercicioCompararId) {
            $ejAnt = $this->ejercicios->find($ejercicioCompararId);
            if ($ejAnt) {
                $gruposAnt = $this->asientos->saldosPorGrupo(
                    $ejAnt['fecha_desde'],
                    $ejAnt['fecha_hasta'],
                    ['RESULTADO_POSITIVO', 'RESULTADO_NEGATIVO'],
                    $emisorId
                );
                $anterior = $this->clasificarGrupos($gruposAnt, 'resultado');
                $totalAnterior = $this->totalDeGrupos($gruposAnt, 'resultado');

                // Cruzar cuentas para variaciones
                $this->cruzarVariaciones($actual, $anterior);
            }
        }

        return [
            'ok'              => true,
            'ejercicio'       => $ej,
            'ejercicio_ant'   => $ejercicioCompararId ? $this->ejercicios->find($ejercicioCompararId) : null,
            'grupos'          => $actual,
            'grupos_ant'      => $anterior,
            'total'           => $totalActual,
            'total_ant'       => $totalAnterior,
            'variacion_pct'   => $totalAnterior !== null && $totalAnterior != 0
                ? round((($totalActual - $totalAnterior) / abs($totalAnterior)) * 100, 2)
                : null,
        ];
    }

    /* ==========================================================
       BALANCE GENERAL
       ==========================================================
       El resultado del período se calcula al vuelo y se inyecta
       en la cuenta 3.4 Resultado del Ejercicio si esta no tiene
       saldo propio. Así el Balance siempre cuadra.
       ========================================================== */
    public function balanceGeneral(
        int $ejercicioId,
        ?int $emisorId = null,
        ?int $ejercicioCompararId = null
    ): array {
        $ej = $this->ejercicios->find($ejercicioId);
        if (!$ej) return ['ok' => false, 'msg' => 'Ejercicio inexistente.'];

        // A) Saldos de Activo / Pasivo / PN al cierre
        $gruposPyG = $this->asientos->saldosPorGrupo(
            $ej['fecha_desde'],
            $ej['fecha_hasta'],
            ['ACTIVO', 'PASIVO', 'PATRIMONIO'],
            $emisorId
        );

        // B) Resultado del período (para inyectar en 3.4)
        $gruposResultado = $this->asientos->saldosPorGrupo(
            $ej['fecha_desde'],
            $ej['fecha_hasta'],
            ['RESULTADO_POSITIVO', 'RESULTADO_NEGATIVO'],
            $emisorId
        );
        $resultadoPeriodo = $this->totalDeGrupos($gruposResultado, 'resultado');

        $actual = $this->clasificarGrupos($gruposPyG, 'balance');

        // Inyectar resultado del período si 3.4 no tiene saldo propio
        $this->inyectarResultado($actual, $resultadoPeriodo);

        // Totales por tipo
        $totales = $this->totalizarBalance($actual);

        // Comparativo
        $anterior = null;
        $totalesAnt = null;
        $variaciones = null;
        if ($ejercicioCompararId) {
            $ejAnt = $this->ejercicios->find($ejercicioCompararId);
            if ($ejAnt) {
                $gruposPyGAnt = $this->asientos->saldosPorGrupo(
                    $ejAnt['fecha_desde'],
                    $ejAnt['fecha_hasta'],
                    ['ACTIVO', 'PASIVO', 'PATRIMONIO'],
                    $emisorId
                );
                $gruposResAnt = $this->asientos->saldosPorGrupo(
                    $ejAnt['fecha_desde'],
                    $ejAnt['fecha_hasta'],
                    ['RESULTADO_POSITIVO', 'RESULTADO_NEGATIVO'],
                    $emisorId
                );
                $resAnt = $this->totalDeGrupos($gruposResAnt, 'resultado');

                $anterior = $this->clasificarGrupos($gruposPyGAnt, 'balance');
                $this->inyectarResultado($anterior, $resAnt);
                $totalesAnt = $this->totalizarBalance($anterior);

                $this->cruzarVariaciones($actual, $anterior);
                $variaciones = $this->calcularVariacionesTotales($totales, $totalesAnt);
            }
        }

        // Validación: Activo = Pasivo + Patrimonio
        $cuadra = abs($totales['activo'] - ($totales['pasivo'] + $totales['patrimonio'])) < 0.02;

        return [
            'ok'              => true,
            'ejercicio'       => $ej,
            'ejercicio_ant'   => $ejercicioCompararId ? $this->ejercicios->find($ejercicioCompararId) : null,
            'grupos'          => $actual,
            'grupos_ant'      => $anterior,
            'totales'         => $totales,
            'totales_ant'     => $totalesAnt,
            'variaciones'     => $variaciones,
            'resultado_periodo' => $resultadoPeriodo,
            'cuadra'          => $cuadra,
        ];
    }

    /* ==========================================================
       HELPERS
       ========================================================== */
    private function clasificarGrupos(array $grupos, string $tipo): array
    {
        $activo        = [];
        $pasivo        = [];
        $patrimonio    = [];
        $ingresos      = [];
        $egresos       = [];

        foreach ($grupos as $g) {
            // Determinar categoría según el tipo de las cuentas internas
            $tipoCuenta = $g['tipo'] ?? null;

            if ($tipoCuenta === 'ACTIVO')          $activo[]     = $g;
            elseif ($tipoCuenta === 'PASIVO')      $pasivo[]     = $g;
            elseif ($tipoCuenta === 'PATRIMONIO')  $patrimonio[] = $g;
            elseif ($tipoCuenta === 'RESULTADO_POSITIVO') $ingresos[] = $g;
            elseif ($tipoCuenta === 'RESULTADO_NEGATIVO') $egresos[]  = $g;
        }

        return [
            'activo'     => $activo,
            'pasivo'     => $pasivo,
            'patrimonio' => $patrimonio,
            'ingresos'   => $ingresos,
            'egresos'    => $egresos,
        ];
    }

    private function totalDeGrupos(array $grupos, string $tipo): float
    {
        $total = 0.0;
        foreach ($grupos as $g) {
            // En resultado: ingresos suman, egresos restan
            if ($tipo === 'resultado') {
                if ($g['tipo'] === 'RESULTADO_POSITIVO') $total += $g['total'];
                else                                     $total -= $g['total'];
            } else {
                $total += $g['total'];
            }
        }
        return round($total, 2);
    }

    /**
     * Inyecta el resultado del período en la cuenta 3.4 si esta
     * no tiene saldo propio. Así el Balance cuadra sin cierre.
     */
    private function inyectarResultado(array &$clasificados, float $resultado): void
    {
        if (abs($resultado) < 0.005) return;

        // Caso 1: ya existe una cuenta 3.4 con saldo propio → no inyectar
        foreach ($clasificados['patrimonio'] as $g) {
            foreach ($g['cuentas'] as $c) {
                if ($c['codigo'] === '3.4' && abs($c['saldo']) > 0.005) {
                    return;
                }
            }
        }

        // Caso 2: buscar el grupo que contiene 3.4 para inyectarlo ahí
        $encontrado = false;
        foreach ($clasificados['patrimonio'] as &$g) {
            foreach ($g['cuentas'] as &$c) {
                if ($c['codigo'] === '3.4') {
                    $c['saldo'] = round($resultado, 2);
                    $g['total'] = round($g['total'] + $resultado, 2);
                    $encontrado = true;
                    break 2;
                }
            }
            unset($c);
        }
        unset($g);

        // Caso 3: la cuenta 3.4 no está en el reporte (sin movimientos)
        // → crear un grupo virtual "Resultado del Ejercicio"
        if (!$encontrado) {
            $clasificados['patrimonio'][] = [
                'codigo'  => '3.4',
                'nombre'  => 'Resultado del Ejercicio',
                'tipo'    => 'PATRIMONIO',
                'cuentas' => [[
                    'cuenta_id'      => 0,
                    'codigo'         => '3.4',
                    'nombre'         => 'Resultado del Ejercicio',
                    'naturaleza'     => 'ACREEDORA',
                    'tipo'           => 'PATRIMONIO',
                    'saldo'          => round($resultado, 2),
                    'saldo_anterior' => 0,
                    'variacion_pct'  => null,
                ]],
                'total'   => round($resultado, 2),
            ];
        }
    }

    private function totalizarBalance(array $clasificados): array
    {
        $sumar = function (array $grupos): float {
            $t = 0.0;
            foreach ($grupos as $g) $t += $g['total'];
            return round($t, 2);
        };

        return [
            'activo'     => $sumar($clasificados['activo']),
            'pasivo'     => $sumar($clasificados['pasivo']),
            'patrimonio' => $sumar($clasificados['patrimonio']),
        ];
    }

    /**
     * Cruza las cuentas de actual vs anterior para calcular variaciones.
     */
    private function cruzarVariaciones(array &$actual, array $anterior): void
    {
        $indexAnt = [];
        foreach ($anterior as $cat => $grupos) {
            foreach ($grupos as $g) {
                foreach ($g['cuentas'] as $c) {
                    $indexAnt[$c['codigo']] = $c['saldo'];
                }
            }
        }

        foreach ($actual as &$grupos) {
            foreach ($grupos as &$g) {
                foreach ($g['cuentas'] as &$c) {
                    $saldoAnt = $indexAnt[$c['codigo']] ?? 0;
                    $c['saldo_anterior'] = round($saldoAnt, 2);
                    $c['variacion_pct'] = ($saldoAnt != 0)
                        ? round((($c['saldo'] - $saldoAnt) / abs($saldoAnt)) * 100, 2)
                        : null;
                }
                unset($c);
            }
            unset($g);
        }
        unset($grupos);
    }

    private function calcularVariacionesTotales(array $tot, array $ant): array
    {
        $v = [];
        foreach ($tot as $k => $val) {
            $v[$k] = ($ant[$k] ?? 0) != 0
                ? round((($val - $ant[$k]) / abs($ant[$k])) * 100, 2)
                : null;
        }
        return $v;
    }
}