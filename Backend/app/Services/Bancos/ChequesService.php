<?php

namespace App\Services\Bancos;

use App\Models\Bancos\Cheque;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class ChequesService
{
    public function crear($registro, $anombrede, $concepto, $referencia)
    {
        if ($registro->forma_pago !== 'Cheque') {
            return;
        }

        if (!CuentaBancariaResolver::debeRegistrarMovimiento()) {
            return;
        }

        DB::beginTransaction();

        try {
            $cuenta_bancaria = CuentaBancariaResolver::resolve($registro);

            $cheque = new Cheque;
            $cheque->estado = 'Pendiente';
            $cheque->concepto = $concepto;
            $cheque->id_cuenta = $cuenta_bancaria->id;
            [$cheque->correlativo, $incrementarSecuenciaCuenta] = self::resolverCorrelativoCheque(
                $registro,
                $cuenta_bancaria
            );
            $cheque->referencia = $referencia;
            $cheque->id_referencia = $registro->id;
            $cheque->anombrede = $anombrede;
            $cheque->total = $registro->total;
            $cheque->fecha = date('Y-m-d');
            $cheque->id_empresa = Auth::user()->id_empresa;
            $cheque->id_usuario = Auth::user()->id;
            $cheque->save();

            if ($incrementarSecuenciaCuenta) {
                $cheque->cuenta->increment('correlativo_cheques');
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        }
    }

    /**
     * Número de cheque: referencia del pago (ej. abono) o secuencia de la cuenta bancaria.
     *
     * @return array{0: int, 1: bool} correlativo y si debe incrementar correlativo_cheques
     */
    private static function resolverCorrelativoCheque(object $registro, $cuenta_bancaria): array
    {
        $referenciaPago = trim((string) ($registro->referencia ?? ''));
        if ($referenciaPago !== '' && ctype_digit($referenciaPago)) {
            return [(int) $referenciaPago, false];
        }

        $secuencia = $cuenta_bancaria->correlativo_cheques;
        if ($secuencia === null || $secuencia === '') {
            $secuencia = 1;
        }

        return [(int) $secuencia, true];
    }
}
