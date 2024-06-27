<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Contabilidad\Catalogo\CuentaMayorizada;
use Monolog\Handler\ZendMonitorHandler;

class GenerarReportesController extends Controller
{

    public function mayorizacion($codigo_c){
        // la idea es que pueda recibir un codigo de cuenta y buscar durante el mes el general de la cuenta con saldos

        //$codigo_c = 110101;

        //dd($cuentas);
        $startDate = Carbon::createFromFormat('Y-m-d', '2024-06-18')->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', '2024-06-19')->endOfDay();

        $detalles= Detalle::where('id_cuenta', $codigo_c)->whereBetween('created_at', [$startDate, $endDate])->get(); //colocar la fecha para el balance respectivo del mes

        //naturaleza de la cuenta
        $cuenta= Cuenta::where('codigo', $codigo_c)->first();

        //debe
        $debe= $detalles->sum('abono');

        //haber
        $haber=$detalles->sum('cargo');

        //establecer la naturaleza para realizar los calculos de la cuenta segun su saldo
        if($cuenta->naturaleza == 'Deudor'){
            $saldo_calc = $debe - $haber;
        }else{
            $saldo_calc =$haber - $debe ;
        }

        //si la cuenta de es de una naturaleza se suma el debe y se resta el haber
        // si una cuenta es de una naturaleza se suba el haber y se resta el debe

        $mayorizada= new CuentaMayorizada();
        $mayorizada->codigo= $codigo_c;
        $mayorizada->nombre = $cuenta->nombre;
        $mayorizada->saldo= $saldo_calc;
        $mayorizada->cargo= $haber;
        $mayorizada->abono= $debe;
        $mayorizada->naturaleza_saldo= $cuenta->naturaleza;

        return collect($mayorizada);

    }

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
        $codigos_deudores= $cuentas_deudoras->pluck('codigo');

        //separacion de pasivos y productos
        $cuentas_acreedoras = Cuenta::where('naturaleza','Acreedor')->get();
        $codigos_acreedoras = $cuentas_acreedoras->pluck('codigo');

        //variable para las cuentas mayorizadas deudoras
        $mayorizadas_deudoras= [];
        foreach ($codigos_deudores as $c_deudo){

            //dd($this->mayorizacion($c_deudo));

            array_push($mayorizadas_deudoras, $this->mayorizacion($c_deudo));

        }

         //dd($mayorizadas_deudoras);

        //variable para las cuentas mayorizadas acreedoras
        $mayorizadas_acreedoras= [];
        foreach ($codigos_acreedoras as $c_acree){

            //dd($this->mayorizacion($c_deudo));

            array_push($mayorizadas_acreedoras, $this->mayorizacion($c_acree));

        }

        //creacion de reporte con las cuentas

        $empresa = Empresa::findOrfail(13);

        $pdf = PDF::loadView('reportes.contabilidad.balance_comprobacion', compact('mayorizadas_deudoras', 'mayorizadas_acreedoras', 'empresa'));
        $pdf->setPaper('US Letter', 'portrait' );

        return $pdf->stream();

    }
}
