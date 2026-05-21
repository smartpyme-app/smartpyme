<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use Carbon\Carbon;
use Exception;

class CierreResultadosService
{
    public const REFERENCIA_PARTIDA = 'CierreEjercicioFiscal';

    public function __construct(
        protected CierreMesService $cierreMesService
    ) {
    }

    /**
     * Determina si la cuenta es de resultados (ingresos, costos, gastos) por rubro.
     */
    public function esCuentaResultadoTemporal(Cuenta $cuenta): bool
    {
        $r = mb_strtolower(trim((string) $cuenta->rubro), 'UTF-8');
        if ($r === '') {
            return false;
        }

        return str_contains($r, 'ingreso')
            || str_contains($r, 'costo')
            || str_contains($r, 'gasto');
    }

    /**
     * Crea la partida de cierre de resultados del último mes del ejercicio (debe estar sin partida de cierre previa).
     *
     * @return Partida
     *
     * @throws Exception
     */
    public function crearPartidaCierreResultados(
        int $empresaId,
        int $usuarioId,
        int $mesInicioEjercicio,
        int $anioReferencia,
        int $idCuentaPatrimonio
    ) {
        $ultimo = FiscalYearCalendar::ultimoMesEjercicio($mesInicioEjercicio, $anioReferencia);
        $y = $ultimo['year'];
        $m = $ultimo['month'];

        $existente = Partida::where('id_empresa', $empresaId)
            ->where('referencia', self::REFERENCIA_PARTIDA)
            ->where('id_referencia', $anioReferencia)
            ->where('estado', '!=', 'Anulada')
            ->first();

        if ($existente) {
            throw new Exception('Ya existe una partida de cierre para este ejercicio fiscal.');
        }

        $patrimonio = Cuenta::where('id_empresa', $empresaId)->where('id', $idCuentaPatrimonio)->firstOrFail();

        $saldos = $this->cierreMesService->calcularSaldosPeriodo($y, $m, $empresaId);

        $lineasResultado = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($saldos as $row) {
            if ((int) $row['id_cuenta'] === $idCuentaPatrimonio) {
                continue;
            }

            $cuenta = Cuenta::where('id_empresa', $empresaId)->where('id', $row['id_cuenta'])->first();
            if (!$cuenta || !$this->esCuentaResultadoTemporal($cuenta)) {
                continue;
            }

            $saldoFinal = round((float) $row['saldo_final'], 2);
            if (abs($saldoFinal) < 0.01) {
                continue;
            }

            $naturaleza = $row['naturaleza'];
            $debeLine = 0.0;
            $haberLine = 0.0;

            if ($naturaleza === 'Deudor') {
                if ($saldoFinal > 0) {
                    $haberLine = $saldoFinal;
                } else {
                    $debeLine = abs($saldoFinal);
                }
            } else {
                if ($saldoFinal > 0) {
                    $debeLine = $saldoFinal;
                } else {
                    $haberLine = abs($saldoFinal);
                }
            }

            $lineasResultado[] = [
                'id_cuenta' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'concepto' => 'Cierre ejercicio '.$anioReferencia,
                'debe' => $debeLine,
                'haber' => $haberLine,
            ];
            $totalDebe += $debeLine;
            $totalHaber += $haberLine;
        }

        $diff = round($totalDebe - $totalHaber, 2);
        if (abs($diff) >= 0.01) {
            if ($diff > 0) {
                $lineasResultado[] = [
                    'id_cuenta' => $patrimonio->id,
                    'codigo' => $patrimonio->codigo,
                    'nombre_cuenta' => $patrimonio->nombre,
                    'concepto' => 'Utilidad (pérdida) ejercicio '.$anioReferencia,
                    'debe' => 0,
                    'haber' => $diff,
                ];
                $totalHaber += $diff;
            } else {
                $lineasResultado[] = [
                    'id_cuenta' => $patrimonio->id,
                    'codigo' => $patrimonio->codigo,
                    'nombre_cuenta' => $patrimonio->nombre,
                    'concepto' => 'Utilidad (pérdida) ejercicio '.$anioReferencia,
                    'debe' => abs($diff),
                    'haber' => 0,
                ];
                $totalDebe += abs($diff);
            }
        }

        if (empty($lineasResultado)) {
            throw new Exception('No hay movimientos de resultados para cerrar en el último mes del ejercicio.');
        }

        if (abs(round($totalDebe - $totalHaber, 2)) > 0.01) {
            throw new Exception('El asiento de cierre de resultados no cuadra (debe/haber).');
        }

        $fechaCierre = Carbon::create($y, $m, 1)->endOfMonth()->format('Y-m-d');

        $partida = new Partida;
        $partida->fecha = $fechaCierre;
        $partida->tipo = 'Cierre';
        $partida->concepto = 'Cierre de resultados — ejercicio fiscal '.$anioReferencia;
        $partida->estado = 'Aplicada';
        $partida->referencia = self::REFERENCIA_PARTIDA;
        $partida->id_referencia = $anioReferencia;
        $partida->id_usuario = $usuarioId;
        $partida->id_empresa = $empresaId;
        $partida->save();

        foreach ($lineasResultado as $ln) {
            $d = new Detalle;
            $d->id_partida = $partida->id;
            $d->id_cuenta = $ln['id_cuenta'];
            $d->codigo = $ln['codigo'];
            $d->nombre_cuenta = $ln['nombre_cuenta'];
            $d->concepto = $ln['concepto'];
            $d->debe = $ln['debe'];
            $d->haber = $ln['haber'];
            $d->saldo = 0;
            $d->save();
        }

        return $partida->fresh(['detalles']);
    }
}
