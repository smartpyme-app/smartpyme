<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AnularUltimoPagoService
{
    public function __construct(private PrestamoPartidaService $partidas)
    {
    }

    public function anular(PrestamoEmpresa $prestamo): PrestamoEmpresa
    {
        $ultimo = $prestamo->pagos()->orderByDesc('id')->first();
        if (!$ultimo) {
            throw new InvalidArgumentException('No hay pagos para anular.');
        }

        PeriodoContableCerrado::assertAbierto((int) $prestamo->id_empresa, $ultimo->fecha->toDateString());

        return DB::transaction(function () use ($prestamo, $ultimo) {
            $this->partidas->revertirPago((int) $ultimo->id);

            $prestamo->cuotas()->where('id_pago', $ultimo->id)->update([
                'estado' => 'pendiente',
                'id_pago' => null,
            ]);

            $prestamo->saldo = round((float) $prestamo->saldo + (float) $ultimo->capital, 2);
            $prestamo->estado = 'activo';
            $prestamo->save();
            $ultimo->delete();

            return $prestamo->fresh(['cuotas', 'pagos']);
        });
    }
}
