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
                $forma_pago = FormaDePago::with('banco')->where('nombre', $registro->forma_pago)->first();
                if($forma_pago && $forma_pago->banco){
                    $cheque = new Cheque;
                    $cheque->estado = 'Pendiente';
                    $cheque->concepto = $concepto;
                    $cheque->id_cuenta = $forma_pago->banco->id;
                    $cheque->correlativo = $forma_pago->banco->correlativo_cheques;
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
