<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GenerarReportesController extends Controller
{

    public function generarRepLibroDiarioAux(){

        // falta agregar totales y nombre y numero de cuenta arriba de cada table

        $detalles = Detalle::get();
        $duplica =$detalles->groupBy('id_cuenta');
        $det_agrup= $duplica->all();
        //dd($det_agrup);

//        foreach ($det_agrup as $part_detalle){
//            dd(key($det_agrup));
//        }

        $empresa = Empresa::findOrfail(13);
//        dd($empresa->logo);
//        return Response()->json($empresa, 200);

        $desde= '28/03/2024';
        $hasta= '28/03/2024';

        $pdf = PDF::loadView('reportes.contabilidad.libro_diario_auxiliar', compact('det_agrup', 'empresa','desde', 'hasta'));
        $pdf->setPaper('US Letter', 'landscape');

        return  $pdf->stream();
    }

    public function generarRepLibroDiarioMayor(){

        $detalles = Detalle::get();
        $duplica =$detalles->groupBy('id_cuenta');
        $det_agrup= $duplica->all();

        $empresa = Empresa::findOrfail(13);

        $desde= '28/03/2024';
        $hasta= '28/03/2024';

        $pdf= PDF::loadView('reportes.contabilidad.libro_diario_mayor', compact('det_agrup', 'empresa', 'desde', 'hasta'));
        $pdf->setPaper('US Letter', 'portrait' );

        return $pdf->stream();

    }

    public function generarBalanceComprobacion(){


        //dd($cuentas);
        $startDate = Carbon::createFromFormat('Y-m-d', '2024-06-18')->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', '2024-06-19')->endOfDay();

        $detalles = Detalle::whereBetween('created_at', [$startDate, $endDate])->get();

        //separacion de activos y gastos
        $cuentas_deudoras = Cuenta::where('naturaleza','Deudor')->get();

        //separacion de pasivos y productos
        $cuentas_acreedoras = Cuenta::where('naturaleza','Acreedor')->get();

        //obtención de datos por detalle de partida segun cuenta

        $detalles = Detalle::get();
        $detalles= $detalles->groupBy('id_cuenta');
        foreach ($detalles as $det){
                $saldo_cuenta = 0 ;
                foreach ($det as $part_det){
                    // Process posts
                    $saldo_cuenta+=$part_det->saldo;
                }

            $det->put('saldo_cuenta', $saldo_cuenta);
                //dd($det->last());
        }

        dd($detalles);

        //creacion de reporte con las cuentas

        $empresa = Empresa::findOrfail(13);

        $pdf = PDF::loadView('reportes.contabilidad.balance_comprobacion', compact('cuentas_deudoras', 'cuentas_acreedoras', 'empresa'));
        $pdf->setPaper('US Letter', 'portrait' );

        return $pdf->stream();



    }
}
