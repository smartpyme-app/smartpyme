<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Partidas\Detalle;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;

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
}
