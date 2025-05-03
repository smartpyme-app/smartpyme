<?php

namespace App\Services\Contabilidad;

use App\Models\Compras\Retaceo\Retaceo;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use Illuminate\Support\Facades\DB;
use Exception;

class RetaceoService
{
    public function crearPartida($id_retaceo)
    {
        $configuracion = Configuracion::firstOrFail();
        $retaceo = Retaceo::with('distribucion', 'gastos')->findOrFail($id_retaceo);


        if ($retaceo->estado !== 'Aplicado') {
            throw new Exception('El retaceo debe estar en estado Aplicado para generar la partida contable.', 400);
        }

 
        $partidaExistente = Partida::where('referencia', 'Retaceo')
                                  ->where('id_referencia', $retaceo->id)
                                  ->first();
        if ($partidaExistente) {
            throw new Exception('Ya existe una partida contable para este retaceo.', 400);
        }

        DB::beginTransaction();

        try {
        

            
            DB::commit();
            return ['success' => true, 'mensaje' => 'Partidas contables generadas correctamente'];
        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        }
    }

}