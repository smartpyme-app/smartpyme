<?php

namespace App\Services\Bancos;

use App\Models\Admin\FormaDePago;
use App\Models\Bancos\Cuenta;
use App\Models\Bancos\Transaccion;
use Illuminate\Support\Facades\DB;
use Exception;
use Auth;

class TransaccionesService
{
    /**
     * Cuenta bancaria asociada al registro (detalle_banco o forma de pago).
     */
    private function resolverCuentaBancaria($registro): ?Cuenta
    {
        $cuenta_bancaria = null;

        if ($registro->detalle_banco) {
            $cuenta_bancaria = Cuenta::where('nombre_banco', $registro->detalle_banco)->first();
        }

        if (!$cuenta_bancaria) {
            $forma_pago = FormaDePago::with('banco')->where('nombre', $registro->forma_pago)->first();
            if ($forma_pago && $forma_pago->banco) {
                $cuenta_bancaria = $forma_pago->banco;
            }
        }

        return $cuenta_bancaria;
    }

    /**
     * Al editar un gasto: actualiza la transacción bancaria vinculada solo si está Pendiente;
     * si ya no aplica pago bancario, elimina la transacción pendiente;
     * si pasa a pago bancario y no existía movimiento, la crea.
     */
    public function sincronizarConGasto($gasto): void
    {
        $transaccion = Transaccion::where('referencia', 'Gasto')
            ->where('id_referencia', $gasto->id)
            ->first();

        $esPagoBancario = $gasto->forma_pago != 'Efectivo' && $gasto->forma_pago != 'Cheque';

        if (!$esPagoBancario) {
            if ($transaccion && $transaccion->estado === 'Pendiente') {
                $transaccion->delete();
            }
            return;
        }

        $cuenta_bancaria = $this->resolverCuentaBancaria($gasto);
        $concepto = 'Gasto: ' . $gasto->tipo_documento . ' #' . ($gasto->referencia ? $gasto->referencia : '');

        if (!$cuenta_bancaria) {
            if ($transaccion && $transaccion->estado === 'Pendiente') {
                $transaccion->delete();
            }
            return;
        }

        if (!$transaccion) {
            $this->crear($gasto, 'Cargo', $concepto, 'Gasto');
            return;
        }

        if ($transaccion->estado !== 'Pendiente') {
            return;
        }

        $transaccion->total = $gasto->total;
        $transaccion->concepto = $concepto;
        $transaccion->fecha = $gasto->fecha;
        $transaccion->id_cuenta = $cuenta_bancaria->id;
        $transaccion->save();
    }

    public function crear($registro, $tipo, $concepto, $referencia)
    {

        DB::beginTransaction();

        try {
            
            if($registro->forma_pago != 'Efectivo' && $registro->forma_pago != 'Cheque'){
                $cuenta_bancaria = $this->resolverCuentaBancaria($registro);
                
                if($cuenta_bancaria){
                    $transaccion = new Transaccion;
                    $transaccion->estado = 'Pendiente';
                    $transaccion->tipo = $tipo;
                    $transaccion->tipo_operacion = 'Transferencia';
                    $transaccion->concepto = $concepto; //'Venta: ' + $registro->nombre_documento + ' #' + $registro->correlativo;
                    $transaccion->id_cuenta = $cuenta_bancaria->id;
                    $transaccion->referencia = $referencia;
                    $transaccion->id_referencia = $registro->id;
                    $transaccion->total = $registro->total;
                    $transaccion->fecha = isset($registro->fecha) && $registro->fecha
                        ? $registro->fecha
                        : date('Y-m-d');
                    $transaccion->id_empresa = Auth::user()->id_empresa;
                    $transaccion->id_usuario = Auth::user()->id;
                    $transaccion->save();
                }
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

}
