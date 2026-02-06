<?php

namespace App\Services\Bancos;

use App\Models\Admin\FormaDePago;
use App\Models\Bancos\Transaccion;
use Illuminate\Support\Facades\DB;
use Exception;
use Auth;

class TransaccionesService
{
    public function crear($registro, $tipo, $concepto, $referencia)
    {

        DB::beginTransaction();

        try {
            
            if($registro->forma_pago != 'Efectivo' && $registro->forma_pago != 'Cheque'){
                $cuenta_bancaria = null;
                
                // Si hay un banco seleccionado específicamente (detalle_banco), usarlo
                if($registro->detalle_banco){
                    $cuenta_bancaria = \App\Models\Bancos\Cuenta::where('nombre_banco', $registro->detalle_banco)->first();
                }
                
                // Si no se encontró cuenta por detalle_banco, usar el banco por defecto del método de pago
                if(!$cuenta_bancaria){
                    $forma_pago = FormaDePago::with('banco')->where('nombre', $registro->forma_pago)->first();
                    if($forma_pago && $forma_pago->banco){
                        $cuenta_bancaria = $forma_pago->banco;
                    }
                }
                
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
                    $transaccion->fecha = date('Y-m-d');
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
