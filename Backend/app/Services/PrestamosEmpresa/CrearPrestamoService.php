<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\PrestamosEmpresa\PrestamoCuota;
use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CrearPrestamoService
{
    public function __construct(private PrestamoPartidaService $partidas)
    {
    }

    public function crear(array $data, int $empresaId, int $usuarioId): PrestamoEmpresa
    {
        $fechaDesembolso = (string) $data['fecha_desembolso'];
        PeriodoContableCerrado::assertAbierto($empresaId, $fechaDesembolso);

        $historico = (bool) ($data['historico'] ?? false);
        $monto = round((float) $data['monto'], 2);
        $nCuotas = (int) $data['n_cuotas'];
        $frecuencia = (string) $data['frecuencia'];
        $generaInteres = (bool) ($data['genera_interes'] ?? false);
        $tasa = (float) ($data['tasa_interes'] ?? 0);
        $fechaPrimera = (string) $data['fecha_primera_cuota'];

        $plan = isset($data['cuotas']) && is_array($data['cuotas']) && count($data['cuotas']) === $nCuotas
            ? $this->normalizarCuotas($data['cuotas'])
            : PlanAmortizacion::generar($generaInteres, $monto, $tasa, $nCuotas, $fechaPrimera, $frecuencia);

        $generarAsiento = array_key_exists('generar_asiento_desembolso', $data)
            ? (bool) $data['generar_asiento_desembolso']
            : !$historico;

        $prestamo = DB::transaction(function () use ($data, $empresaId, $usuarioId, $historico, $monto, $nCuotas, $frecuencia, $generaInteres, $tasa, $fechaDesembolso, $fechaPrimera, $plan, $generarAsiento) {
            $prestamo = PrestamoEmpresa::create([
                'id_empresa' => $empresaId,
                'id_usuario' => $usuarioId,
                'tipo_acreedor' => $data['tipo_acreedor'],
                'acreedor' => $data['acreedor'],
                'concepto' => $data['concepto'] ?? null,
                'historico' => $historico,
                'monto_original' => isset($data['monto_original']) ? round((float) $data['monto_original'], 2) : null,
                'monto' => $monto,
                'saldo' => $monto,
                'genera_interes' => $generaInteres,
                'tasa_interes' => $tasa,
                'n_cuotas' => $nCuotas,
                'frecuencia' => $frecuencia,
                'fecha_desembolso' => $fechaDesembolso,
                'fecha_primera_cuota' => $fechaPrimera,
                'id_cuenta_banco' => $data['id_cuenta_banco'] ?? null,
                'generar_asiento_desembolso' => $generarAsiento,
                'clasificacion' => PlanAmortizacion::clasificacion($nCuotas, $frecuencia),
                'estado' => 'activo',
            ]);

            foreach ($plan as $fila) {
                PrestamoCuota::create([
                    'id_prestamo' => $prestamo->id,
                    'numero' => $fila['numero'],
                    'fecha_vencimiento' => $fila['fecha_vencimiento'],
                    'capital' => $fila['capital'],
                    'interes' => $fila['interes'],
                    'total' => $fila['total'],
                    'estado' => 'pendiente',
                ]);
            }

            $this->partidas->desembolso($prestamo->fresh());

            return $prestamo;
        });

        return $prestamo->fresh(['cuotas', 'pagos']);
    }

    public function actualizarCuotasPendientes(PrestamoEmpresa $prestamo, array $cuotas): PrestamoEmpresa
    {
        $porNumero = [];
        foreach ($cuotas as $fila) {
            $porNumero[(int) ($fila['numero'] ?? 0)] = $fila;
        }

        foreach ($prestamo->cuotas as $cuota) {
            if ($cuota->estado === 'pagada') {
                continue;
            }
            $fila = $porNumero[$cuota->numero] ?? null;
            if (!$fila) {
                continue;
            }
            $cuota->fecha_vencimiento = $fila['fecha_vencimiento'] ?? $cuota->fecha_vencimiento;
            $cuota->capital = round((float) ($fila['capital'] ?? $cuota->capital), 2);
            $cuota->interes = round((float) ($fila['interes'] ?? $cuota->interes), 2);
            $cuota->total = isset($fila['total'])
                ? round((float) $fila['total'], 2)
                : round($cuota->capital + $cuota->interes, 2);
            $cuota->save();
        }

        return $prestamo->fresh(['cuotas', 'pagos']);
    }

    /** @param  list<array<string, mixed>>  $cuotas */
    private function normalizarCuotas(array $cuotas): array
    {
        $out = [];
        foreach ($cuotas as $i => $fila) {
            $capital = round((float) ($fila['capital'] ?? 0), 2);
            $interes = round((float) ($fila['interes'] ?? 0), 2);
            $total = isset($fila['total']) ? round((float) $fila['total'], 2) : round($capital + $interes, 2);
            if ($total <= 0) {
                throw new InvalidArgumentException('Cada cuota debe ser mayor a 0.');
            }
            $out[] = [
                'numero' => (int) ($fila['numero'] ?? $i + 1),
                'fecha_vencimiento' => (string) $fila['fecha_vencimiento'],
                'capital' => $capital,
                'interes' => $interes,
                'total' => $total,
            ];
        }

        return $out;
    }
}
