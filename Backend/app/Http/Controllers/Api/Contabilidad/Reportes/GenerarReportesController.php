<?php

namespace App\Http\Controllers\Api\Contabilidad\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Contabilidad\Catalogo\CuentaMayorizada;
use App\Models\Contabilidad\Catalogo\CuentaReporte;
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
        $cuenta= Cuenta::where('codigo', $codigo_c)->where('id_empresa', auth()->user()->id_empresa)->first();

        //debe
        $debe= $detalles->sum('abono');

        //haber
        $haber=$detalles->sum('cargo');

        //establecer la naturaleza para realizar los calculos de la cuenta segun su saldo
        if($cuenta->naturaleza == 'Deudor'){
            $saldo_calc = $debe - $haber;
        }else{
            $saldo_calc = $haber - $debe ;
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

    public function generarRepLibroDiarioAux($desde,$hasta){

        // Falta revisar porque lo enviua desordenado

        $cuentas = [];


        $empresa_id= auth()->user()->id_empresa;
        $empresa = Empresa::findOrfail($empresa_id);

        $det_agrup= Detalle::whereHas('partida', function($query) use ($empresa_id) {
            $query->where('id_empresa', $empresa_id);})->whereBetween('created_at', [$desde, $hasta])->orderBy('codigo', 'asc')
            ->get()->groupBy('codigo')->all();

        foreach ($det_agrup as $index => $collection){

            $cuent= Cuenta::where('codigo', $index)->where('id_empresa', auth()->user()->id_empresa)->first();
            $nombre_cuent= $cuent->nombre;
            $nat_cuenta= $cuent->naturaleza;

            $sum_deb=0;
            $sum_hab=0;
            foreach ($collection as $det_part){
//                las cuentas de ACTIVO, COSTO Y GASTOS (son de saldo deudor), aumentan con un cargo (debe) y disminuyen con un abono(haber) y las cuentas de PASIVO,
//                PATRIMONIO E INGRESOS(son de saldo acreedor) aumentan con un abono (haber) y disminuyen con un cargo(debe)

                $sum_deb+=$det_part->debe;
                $sum_hab+=$det_part->haber;
            }

            $cuenta_reporte = new CuentaReporte();
            $cuenta_reporte->cuenta= $index;
            $cuenta_reporte->nombre= $nombre_cuent;
            $cuenta_reporte->detalles= $collection;
            $cuenta_reporte->naturaleza= $nat_cuenta;
            $cuenta_reporte->cargo= $sum_deb;
            $cuenta_reporte->abono= $sum_hab;
            $cuenta_reporte->saldo_actual= 100;  //este dato llega a la blade actualizado con el dato de salgo anterior para que se haga el calculo en la blade
            $cuenta_reporte->saldo_anterior= 100;  //este saldo debe venir de la base de datos con respecto al mes anterior al que se esta haciendo la prueba
            array_push($cuentas,$cuenta_reporte);
        }

//        dd($cuentas);
// Fecha en formato dd/mm/yyyy
        $fecha = date('d/m/Y');

// Hora en formato 12 horas con a.m./p.m.
        $hora = date('h:i:s a');

        $pdf = PDF::loadView('reportes.contabilidad.libro_diario_auxiliar', compact('cuentas', 'empresa','desde', 'hasta', 'hora', 'fecha'));
        $pdf->setPaper('US Letter', 'landscape');

        return  $pdf->stream();
    }

    public function generarRepLibroDiarioMayor($startDate, $endDate, $concepto = null){

        $cuentas = [];

        //nivel de cuenta padre, las siguientes van a aceptar datos pero esta no
        $nivel_datos=2;

        $empresa_id= auth()->user()->id_empresa;

        //cuentas que no aceptan datos segun nivel
        $cuentas_padre= Cuenta::where('nivel', $nivel_datos)->where('id_empresa', auth()->user()->id_empresa)->get();

        $partidas= Detalle::whereHas('partida', function($query) use ($empresa_id) {
            $query->where('id_empresa', $empresa_id);})->whereBetween('created_at', [$startDate, $endDate])->get();

        //elegir entre los detalles de las partidas cuales tienen cuentas eque empiezan con los cuatros digitos de las partidas padre
        foreach ($cuentas_padre->pluck('codigo') as $cod_padre)
        {
            $partidasFiltradas = $partidas->filter(function ($detalle) use ($cod_padre){
                return strpos($detalle->codigo, (string)$cod_padre) === 0;
            });

            // Convertir el resultado a una colección nuevamente (opcional)
            $partidasFiltradas = $partidasFiltradas->values();

//            LLENADO DE LOS DEBE Y HABER DE CADA CUENTA

            $sum_deb=0;
            $sum_hab=0;
            foreach ($partidasFiltradas as $det_part){

//                las cuentas de ACTIVO, COSTO Y GASTOS (son de saldo deudor), aumentan con un cargo (debe) y disminuyen con un abono(haber) y las cuentas de PASIVO,
//                PATRIMONIO E INGRESOS(son de saldo acreedor) aumentan con un abono (haber) y disminuyen con un cargo(debe)

                $sum_deb+=$det_part->debe;
                $sum_hab+=$det_part->haber;

            }

            if (count($partidasFiltradas)!=0){

                $cnt = $cuentas_padre->firstWhere('codigo', $cod_padre);


                $cuenta_reporte= new CuentaReporte();
                $cuenta_reporte->cuenta= $cod_padre;
                $cuenta_reporte->nombre= $cnt->nombre;
                $cuenta_reporte->detalles = $partidasFiltradas;
                $cuenta_reporte->naturaleza = $cnt->naturaleza;
                $cuenta_reporte->cargo = $sum_deb;
                $cuenta_reporte->abono = $sum_hab;
                $cuenta_reporte->saldo_actual = 0;
                $cuenta_reporte->saldo_anterior = 0;


                array_push($cuentas,$cuenta_reporte);

            }


        }

//        dd($cuentas);

        $empresa = Empresa::findOrfail($empresa_id);

        $desde= $startDate;
        $hasta= $endDate;

        if ($concepto!=null){
            $pdf= PDF::loadView('reportes.contabilidad.libro_mayor', compact('cuentas', 'empresa', 'desde', 'hasta', 'concepto'));
        }else{
            $pdf= PDF::loadView('reportes.contabilidad.libro_diario_mayor', compact('cuentas', 'empresa', 'desde', 'hasta'));

        }

        $pdf->setPaper('US Letter', 'portrait' );

        return $pdf->stream();

    }

    public function generarRepMovCuenta($startDate, $endDate, $cuenta_cod){

        $empresa_id= auth()->user()->id_empresa;

        $cuenta= Cuenta::where('codigo', $cuenta_cod)->where('id_empresa', auth()->user()->id_empresa)->first();

//        dd($cuenta_cod);

        $det_agrup= Detalle::whereHas('partida', function($query) use ($empresa_id) {
            $query->where('id_empresa', $empresa_id);})
            ->whereBetween('created_at', [$startDate, $endDate])->where('codigo', $cuenta_cod)
            ->get();

        $empresa = Empresa::findOrfail($empresa_id);

        $desde= $startDate;
        $hasta= $endDate;

        // Fecha en formato dd/mm/yyyy
        $fecha = date('d/m/Y');

// Hora en formato 12 horas con a.m./p.m.
        $hora = date('h:i:s a');

        $sum_deb=0;
        $sum_hab=0;
        foreach ($det_agrup as $detalle){
//                las cuentas de ACTIVO, COSTO Y GASTOS (son de saldo deudor), aumentan con un cargo (debe) y disminuyen con un abono(haber) y las cuentas de PASIVO,
//                PATRIMONIO E INGRESOS(son de saldo acreedor) aumentan con un abono (haber) y disminuyen con un cargo(debe)

            $sum_deb+=$detalle->debe;
            $sum_hab+=$detalle->haber;
        }

//        dd($cuenta);

        $cuenta_reporte = new CuentaReporte();
        $cuenta_reporte->cuenta= $cuenta_cod;
        $cuenta_reporte->nombre= $cuenta->nombre;
        $cuenta_reporte->detalles= $det_agrup;
        $cuenta_reporte->naturaleza= $cuenta->naturaleza;
        $cuenta_reporte->cargo= $sum_deb;
        $cuenta_reporte->abono= $sum_hab;
        $cuenta_reporte->saldo_actual= 0;  //este dato llega a la blade actualizado con el dato de salgo anterior para que se haga el calculo en la blade
        $cuenta_reporte->saldo_anterior= 0;

        $pdf= PDF::loadView('reportes.contabilidad.movimiento_cuenta', compact('cuenta_reporte',  'desde', 'hasta', 'empresa', 'fecha', 'hora'));

        $pdf->setPaper('US Letter', 'landscape' );

        return $pdf->stream();


    }
    public function generarBalanceComprobacion(){

        //dd($cuentas);
        $startDate = Carbon::createFromFormat('Y-m-d', '2024-06-18')->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', '2024-06-19')->endOfDay();

        $detalles = Detalle::whereBetween('created_at', [$startDate, $endDate])->get();

        //separacion de activos y gastos

        $cuentas_deudoras = Cuenta::where('naturaleza','Deudor')->get();
        $codigos_deudores = $cuentas_deudoras->pluck('codigo');

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

    public function generarBalanceGeneral(){

        $pdf= PDF::loadView('reportes.contabilidad.balance_general');
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream();

    }
}
