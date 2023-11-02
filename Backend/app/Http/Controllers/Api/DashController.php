<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use stdClass;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Admin\Corte;

use App\Models\Indicador;
use App\Models\User;

use JWTAuth;

class DashController extends Controller
{

    public function index(Request $request) {

        $usuario = JWTAuth::parseToken()->authenticate();

        $indicadores = new Indicador(['inicio' => $request->inicio, 'fin' => $request->fin, 'id_empresa' => $usuario->id_empresa, 'id_sucursal' => $request->id_sucursal]);
        // return $indicadores;
        // Salidas

            $indicadores->total_salidas = $indicadores->getTotalComprasPagadas() 
                                + $indicadores->getTotalComprasPendientes()
                                + $indicadores->getTotalGastosPagados()
                                + $indicadores->getTotalGastosPendientes()
                                - $indicadores->getTotalDevolucionesCompra();
        
            $indicadores->total_compras = $indicadores->getTotalComprasPagadas();
            $indicadores->total_gastos = $indicadores->getTotalGastosPagados();

            $indicadores->total_salidas_semana = $indicadores->getTotalSalidasSemana();

        // Ingresos
            $indicadores->total_ventas = $indicadores->getTotalVentasPagadas()
                                        + $indicadores->getTotalVentasPendientes()
                                        - $indicadores->getTotalDevolucionesVenta();
            
            $indicadores->total_ventas_semana = $indicadores->getTotalVentasSemana();
            
            $indicadores->total_ventas_canal = $indicadores->getVentasByCanal();
            $indicadores->total_ventas_forma_pago = $indicadores->getVentasByFormaPago();


            // $ultima = $datos->total_ventas_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
            // if ($ultima)
            //     $datos->total_ventas_percent = round((($datos->total_ventas / $ultima) - 1) * 100, 2);
            // else
            //     $datos->total_ventas_percent = 0;

            // $datos->total_cxc = $indicadores->getTotalVentasPendientes();

        // Transacciones

            $indicadores->total_transacciones = $indicadores->getCantidadVentasPagadas() + $indicadores->getCantidadVentasPendientes() - $indicadores->getCantidadDevolucionesVenta();
            $indicadores->total_transacciones_semana = $indicadores->getTotalTransaccionesSemana();

            // $ultima = $datos->total_transacciones_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
            // if ($ultima)
            //     $datos->total_transacciones_percent = round((($datos->total_transacciones / $ultima) - 1) * 100, 2);
            // else
            //     $datos->total_transacciones_percent = 0;

        // Balance

            $indicadores->total_balance = $indicadores->total_ventas - $indicadores->total_salidas;
            
            $indicadores->total_balance_semana   = $indicadores->getTotalBalancesSemana();
            // $ultima = $datos->total_balance_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
            // if ($ultima)
            //     $datos->total_balance_percent = round((($datos->total_balance / $ultima) - 1) * 100, 2);
            // else
            //     $datos->total_balance_percent = 0;

        return Response()->json($indicadores, 200);
    }


    public function barcode($codigo) {
        
        return view('reportes.barcode', compact('codigo'));
        
        $reportes = \PDF::loadView('reportes.barcode', compact('codigo'))->setPaper('letter');
        return $reportes->stream();

    }



}
