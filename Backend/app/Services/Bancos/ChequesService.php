<?php

namespace App\Services\Bancos;

use App\Models\Admin\FormaDePago;
use App\Models\Bancos\Cheque;
use Illuminate\Support\Facades\DB;
use Exception;
use Auth;

class ChequesService
{
    public function crear($registro, $anombrede, $concepto, $referencia)
    {

        DB::beginTransaction();

        try {
            
            if($registro->forma_pago == 'Cheque'){
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
                    $cheque = new Cheque;
                    $cheque->estado = 'Pendiente';
                    $cheque->concepto = $concepto;
                    $cheque->id_cuenta = $cuenta_bancaria->id;
                    $cheque->correlativo = $cuenta_bancaria->correlativo_cheques;
                    $cheque->referencia = $referencia;
                    $cheque->id_referencia = $registro->id;
                    $cheque->anombrede = $anombrede;
                    $cheque->total = $registro->total;
                    $cheque->fecha = date('Y-m-d');
                    $cheque->id_empresa = Auth::user()->id_empresa;
                    $cheque->id_usuario = Auth::user()->id;
                    $cheque->save();

                    // Incrementar correlativo
                    $cheque->cuenta->increment('correlativo_cheques');

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
