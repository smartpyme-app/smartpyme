<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use App\Models\PrestamosEmpresa\PrestamoPago;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarPagoService
{
    public function __construct(private PrestamoPartidaService $partidas)
    {
    }

    public function registrar(PrestamoEmpresa $prestamo, array $data, int $usuarioId): PrestamoPago
    {
        if ($prestamo->estado === 'pagado') {
            throw new InvalidArgumentException('El préstamo ya está pagado.');
        }

        PeriodoContableCerrado::assertAbierto((int) $prestamo->id_empresa, (string) $data['fecha']);

        $n = max(1, (int) ($data['n_cuotas'] ?? 1));
        $pendientes = $prestamo->cuotas()->where('estado', '!=', 'pagada')->orderBy('numero')->get();
        if ($pendientes->isEmpty()) {
            throw new InvalidArgumentException('No hay cuotas pendientes.');
        }
        $elegidas = $pendientes->take($n);
        $monto = PlanAmortizacion::montoACobrar(
            $elegidas->map(fn ($c) => ['total' => (float) $c->total])->all(),
            (float) $prestamo->saldo
        );
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a pagar debe ser mayor a 0.');
        }

        $pago = DB::transaction(function () use ($prestamo, $data, $usuarioId, $elegidas, $monto) {
            $restante = $monto;
            $capitalPago = 0.0;
            $interesPago = 0.0;
            $aplicadas = [];

            foreach ($elegidas as $cuota) {
                if ($restante <= 0) {
                    break;
                }
                $tomar = min((float) $cuota->total, $restante);
                $cap = min((float) $cuota->capital, $tomar);
                $int = round($tomar - $cap, 2);
                $capitalPago = round($capitalPago + $cap, 2);
                $interesPago = round($interesPago + $int, 2);
                $aplicadas[] = $cuota;
                $restante = round($restante - $tomar, 2);
            }

            $pago = PrestamoPago::create([
                'id_prestamo' => $prestamo->id,
                'fecha' => $data['fecha'],
                'monto' => $monto,
                'capital' => $capitalPago,
                'interes' => $interesPago,
                'metodo' => $data['metodo'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'detalle_banco' => $data['detalle_banco'] ?? null,
                'id_usuario' => $usuarioId,
            ]);

            foreach ($aplicadas as $cuota) {
                $cuota->estado = 'pagada';
                $cuota->id_pago = $pago->id;
                $cuota->save();
            }

            $prestamo->saldo = round((float) $prestamo->saldo - $capitalPago, 2);
            if ($prestamo->saldo <= 0) {
                $prestamo->saldo = 0;
                $prestamo->estado = 'pagado';
            }
            $prestamo->save();
            $this->partidas->pago($prestamo->fresh(), $pago);

            return $pago;
        });

        return $pago->fresh('cuotas');
    }
}
