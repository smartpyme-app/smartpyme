<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use stdClass;

use App\Models\Admin\Empresa;
use App\Models\Admin\Caja;
use App\Models\Admin\Corte;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as VentaDetalle;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Clientes\Cliente;

use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Models\Compras\Gastos\Gasto;

use App\Models\Inventario\Producto;

use App\Models\Transporte\Fletes\Flete;
use App\Models\Transporte\Mantenimientos\Mantenimiento;

use App\Models\User;

class DashController extends Controller
{

    public function index(Request $request) {

        $datos = new stdClass();

        $datos->total_salidas = Compra::whereBetween('fecha', [$request->inicio, $request->fin])
                                        ->when($request->id_sucursal, function($q) use($request){
                                            $q->where('id_sucursal', $request->id_sucursal);
                                        })
                                        ->sum('total_compra');
        $datos->total_salidas_semana   = Compra::
                                    selectRaw('DAY(fecha) as dia')
                                    ->selectRaw('sum(total_compra) as total')
                                    ->groupBy('dia')
                                    ->where('fecha', '>=', Carbon::now()->subDays(8))
                                    ->orderBy('dia')
                                    ->get();
        $ultima = $datos->total_salidas_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
        if ($ultima)
            $datos->total_salidas_percent = round((($datos->total_salidas / $ultima) - 1) * 100, 2);
        else
            $datos->total_ventas_percent = 0;

        // Ingresos
            $datos->total_ventas = Venta::whereBetween('fecha', [$request->inicio, $request->fin])
                                            ->when($request->id_sucursal, function($q) use($request){
                                                $q->where('id_sucursal', $request->id_sucursal);
                                            })
                                            ->sum('total_venta');
            $datos->total_ventas_semana   = Venta::
                                        selectRaw('DAY(fecha) as dia')
                                        ->selectRaw('sum(total_venta) as total')
                                        ->groupBy('dia')
                                        ->where('fecha', '>=', Carbon::now()->subDays(8))
                                        ->orderBy('dia')
                                        ->get();
            $ultima = $datos->total_ventas_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
            if ($ultima)
                $datos->total_ventas_percent = round((($datos->total_ventas / $ultima) - 1) * 100, 2);
            else
                $datos->total_ventas_percent = 0;

        // Transacciones

            $datos->total_transacciones = Venta::whereBetween('fecha', [$request->inicio, $request->fin])
                                            ->when($request->id_sucursal, function($q) use($request){
                                                $q->where('id_sucursal', $request->id_sucursal);
                                            })
                                            ->count();
            $datos->total_transacciones_semana   = Venta::
                                        selectRaw('DAY(fecha) as dia')
                                        ->selectRaw('count(*) as total')
                                        ->groupBy('dia')
                                        ->where('fecha', '>=', Carbon::now()->subDays(8))
                                        ->orderBy('dia')
                                        ->get();
            $ultima = $datos->total_transacciones_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
            if ($ultima)
                $datos->total_transacciones_percent = round((($datos->total_transacciones / $ultima) - 1) * 100, 2);
            else
                $datos->total_transacciones_percent = 0;

        // Balance

            $datos->total_balance = Venta::whereBetween('fecha', [$request->inicio, $request->fin])
                                            ->when($request->id_sucursal, function($q) use($request){
                                                $q->where('id_sucursal', $request->id_sucursal);
                                            })
                                            ->sum('total_venta');
            $datos->total_balance_semana   = Venta::
                                        selectRaw('DAY(fecha) as dia')
                                        ->selectRaw('sum(total_venta) as total')
                                        ->groupBy('dia')
                                        ->where('fecha', '>=', Carbon::now()->subDays(8))
                                        ->orderBy('dia')
                                        ->get();
            $ultima = $datos->total_balance_semana->sortByDesc('dia')->skip(1)->take(1)->pluck('total')->first();
            if ($ultima)
                $datos->total_balance_percent = round((($datos->total_balance / $ultima) - 1) * 100, 2);
            else
                $datos->total_balance_percent = 0;

        return Response()->json($datos, 200);
    }

    public function vendedor() {

        $datos = new stdClass();

        $usuario_id = \JWTAuth::parseToken()->authenticate()->id;

        $ordenes                = Venta::where('usuario_id', $usuario_id)
                                        ->whereMonth('fecha', date('m'))
                                        ->whereYear('fecha', date('Y'))
                                        ->where('estado', '!=', 'Cancelada')->get();
                         
        $datos->cantidad_ordenes    = $ordenes->count();
        $datos->suma_ordenes        = $ordenes->sum('total');
                            
        $datos->tclientes        = Cliente::whereHas('ordenes', function($q) use($usuario_id){
                                            $q->whereMonth('fecha', date('m'))->whereYear('fecha', date('Y'))
                                            ->where('estado', '!=', 'Cancelada')
                                            ->where('usuario_id', $usuario_id);
                                        })->count();
                            
        $datos->tproductos        = VentaDetalle::whereHas('venta', function($q) use($usuario_id){
                                            $q->whereMonth('fecha', date('m'))->whereYear('fecha', date('Y'))
                                            ->where('estado', '!=', 'Cancelada')
                                            ->where('usuario_id', $usuario_id);
                                        })->count();


        return Response()->json($datos, 200);
    }

    public function cajero($id){

        // $datos = new stdClass();
        $usuario = User::findOrFail($id);
        $caja    = Caja::where('id', $usuario->caja_id)->with('corte')->firstOrFail();
        
        // Sino hay corte se muestran los datos del dia.
        if ($caja->corte) {
            $ordenes     = Venta::where('estado', 'En Proceso')
                                    // ->where('fecha', '>=', Carbon::parse($caja->corte->apertura)->format('Y-m-d'))
                                    ->paginate(10000);
        } else {
            $ordenes     = Venta::where('estado', 'En Proceso')
                                    ->where('fecha', '>=', Carbon::today())
                                    ->paginate(10000);
        }
        
        // Se saca el total de venta
        // $datos->ventas              = $ventas->sum('total');
        // $datos->ventas_efectivo     = $ventas->where('forma_de_pago','Efectivo')->sum('total');
        // $datos->ventas_tarjeta      = $ventas->where('forma_de_pago','Tarjeta')->sum('total');
        // $datos->ventas_vale         = $ventas->where('forma_de_pago','Vale')->sum('total');
        // $datos->ventas_cheque       = $ventas->where('forma_de_pago','Cheque')->sum('total');
        // $datos->ventas_versatec     = $ventas->where('forma_de_pago','Versatec')->sum('total');

        return Response()->json($ordenes, 200);
    }

    public function barcode($codigo) {
        
        return view('reportes.barcode', compact('codigo'));

        
        $reportes = \PDF::loadView('reportes.barcode', compact('codigo'))->setPaper('letter');
        return $reportes->stream();

    }



}
