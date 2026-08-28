<?php

namespace App\Services\Bancos;

use App\Models\Bancos\Transaccion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class TransaccionesService
{
    public function crear($registro, $tipo, $concepto, $referencia)
    {
        if ($registro->forma_pago === 'Efectivo' || $registro->forma_pago === 'Cheque') {
            return;
        }

        DB::beginTransaction();

        try {
            $cuenta_bancaria = CuentaBancariaResolver::resolve($registro);

            $transaccion = new Transaccion;
            $transaccion->estado = 'Pendiente';
            $transaccion->tipo = $tipo;
            $transaccion->tipo_operacion = 'Transferencia';
            $transaccion->concepto = $concepto;
            $transaccion->id_cuenta = $cuenta_bancaria->id;
            $transaccion->referencia = $referencia;
            $transaccion->id_referencia = $registro->id;
            $transaccion->total = $registro->total;
            $transaccion->fecha = date('Y-m-d');
            $transaccion->id_empresa = Auth::user()->id_empresa;
            $transaccion->id_usuario = Auth::user()->id;
            $transaccion->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        }
    }
}
