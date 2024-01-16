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
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Inventario\Producto;

use JWTAuth;

class DashController extends Controller
{

    public function index(Request $request) {

        $usuario = JWTAuth::parseToken()->authenticate();
        $tiempo = 'DAY';
        $fecha = Carbon::now()->subDays(8);

        if (strtoupper($request->time) == 'DAY') {
            $tiempo = 'HOUR';
            $fecha = Carbon::now()->subHours(12);
        }
        if (strtoupper($request->time) == 'WEEK') {
            $tiempo = 'DAY';
            $fecha = Carbon::now()->subDays(8);
        }
        if (strtoupper($request->time) == 'MONTH') {
            $fecha = Carbon::now()->subWeeks(4);
            $tiempo = 'WEEK';
        }
        if (strtoupper($request->time) == 'YEAR') {
            $fecha = Carbon::now()->subMonths(12);
            $tiempo = 'MONTH';
        }

        $indicadores = new Indicador(['inicio' => $request->inicio, 'fin' => $request->fin, 'id_empresa' => $usuario->id_empresa, 'id_sucursal' => $request->id_sucursal]);
        // return $indicadores;
        // Salidas

            $indicadores->total_salidas = $indicadores->getTotalGastos();
        
            $indicadores->total_compras = $indicadores->getTotalComprasPagadas() + $indicadores->getTotalComprasPendientes();
            $indicadores->total_gastos = $indicadores->getTotalGastosPagados() + $indicadores->getTotalGastosPendientes();

            $indicadores->total_cxp = $indicadores->getTotalComprasPendientes() + $indicadores->getTotalGastosPendientes();

            $indicadores->totales_salidas = $indicadores->getTotalesSalidas($tiempo, $fecha);

        // Ingresos

            $indicadores->total_ventas = $indicadores->getTotalVentas();
            
            $indicadores->totales_ventas = $indicadores->getTotalesVentas($tiempo, $fecha);
            
            $indicadores->total_ventas_canal = $indicadores->getVentasByCanal();
            $indicadores->total_ventas_forma_pago = $indicadores->getVentasByFormaPago();

            $indicadores->total_cxc = $indicadores->getTotalVentasPendientes();

        // Transacciones

            $indicadores->total_transacciones = $indicadores->getCantidadTransacciones();
            $indicadores->totales_transacciones = $indicadores->getTotalesTransacciones($tiempo, $fecha);

            $indicadores->cantidad_cxc = $indicadores->getCantidadVentasPendientes();

        // Balance

            // ($indicadores->getTotalVentasPagadas() - $indicadores->getTotalDevolucionesVenta()) - 
            // ($indicadores->getTotalComprasPagadas() + $indicadores->getTotalGastosPagados() - $indicadores->getTotalDevolucionesCompra())

            $indicadores->total_balance = $indicadores->getTotalResultados();
            
            $indicadores->totales_balance   = $indicadores->getTotalesBalances($tiempo, $fecha);

        return Response()->json($indicadores, 200);
    }

    public function corte(Request $request){
        $usuario = JWTAuth::parseToken()->authenticate();

        $indicadores = new Indicador(['inicio' => $request->fecha, 'fin' => $request->fecha, 'id_empresa' => $usuario->id_empresa, 'id_sucursal' => $request->id_sucursal]);
        
        $indicadores->totalVentasPagadas = $indicadores->getTotalVentasPagadas();
        $indicadores->totalRecibos = $indicadores->getTotalRecibos();
        $indicadores->totalVentasPendientes = $indicadores->getTotalVentasPendientes();
        $indicadores->totalDevolucionesVenta = $indicadores->getTotalDevolucionesVenta();
        $indicadores->totalGastosPagados = $indicadores->getTotalGastosPagados();

        $indicadores->total_ventas_forma_pago = $indicadores->getVentasByFormaPago();
        $indicadores->total_ventas_canal = $indicadores->getVentasByCanal();
        $indicadores->total_ventas_banco = $indicadores->getVentasByBanco();
        $indicadores->total_documentos_emitidos = $indicadores->getDocumentoEmitidos();
        $indicadores->total_documentos_con_devoluciones = $indicadores->getDocumentoConDevolucion();
        $indicadores->total_documentos_anulados = $indicadores->getDocumentosAnulados();

        $indicadores->cantidadRecibos = $indicadores->getCantidadRecibos();
        $indicadores->totalRecibos = $indicadores->getTotalRecibos();
        $indicadores->cantidadDevolucionesVenta = $indicadores->getCantidadDevolucionesVenta();
        $indicadores->totalDevolucionesVenta = $indicadores->getTotalDevolucionesVenta();
        $indicadores->cantidadGastosPagados = $indicadores->getCantidadGastosPagados();
        $indicadores->totalGastosPagados = $indicadores->getTotalGastosPagados();
        $indicadores->cantidadVentasPendientes = $indicadores->getCantidadVentasPendientes();
        $indicadores->totalVentasPendientes = $indicadores->getTotalVentasPendientes();


        return Response()->json($indicadores, 200);
    }

    public function cortePdf($id_sucursal = null, $fechaDe = null)
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        if (!$fechaDe)
            $fechaDe = date("Y-m-d");

        $indicadores = new Indicador(['inicio' => $fechaDe, 'fin' => $fechaDe, 'id_empresa' => $usuario->id_empresa, 'id_sucursal' => $id_sucursal]);

        $pdf = \PDF::loadView('reportes.corte', compact('indicadores'));
        return $pdf->stream();
    }


    public function barcode($codigo) {
        
        return view('reportes.barcode', compact('codigo'));
        
        $reportes = \PDF::loadView('reportes.barcode', compact('codigo'))->setPaper('letter');
        return $reportes->stream();

    }


    public function buscador($txt) {
        
        $data = collect();

        $productos = Producto::where('nombre', 'like', '%' . $txt . '%')->get();
        $clientes = Cliente::where('nombre', 'like', '%' . $txt . '%')
                            ->orwhere('nombre_empresa', 'like',  '%'. $txt .'%')
                            ->get();
        $proveedores = Proveedor::where('nombre', 'like', '%' . $txt . '%')
                            ->orwhere('nombre_empresa', 'like',  '%'. $txt .'%')
                            ->get();

        foreach ($productos as $producto) {
            $data->push([
                'nombre' => $producto->nombre,
                'tipo' => 'Producto',
                'url' => '/producto/editar/' . $producto->id,
            ]);
        }

        foreach ($clientes as $cliente) {
            $data->push([
                'nombre' =>  $cliente->tipo == 'Persona' ? $cliente->nombre_completo : $cliente->nombre_empresa,
                'tipo' => 'Cliente',
                'url' => '/cliente/editar/' . $cliente->id,
            ]);
        }

        foreach ($proveedores as $proveedor) {
            $data->push([
                'nombre' => $proveedor->tipo == 'Persona' ? $proveedor->nombre_completo : $proveedor->nombre_empresa,
                'tipo' => 'Proveedor',
                'url' => '/proveedor/editar/' . $proveedor->id,
            ]);
        }

        return Response()->json($data, 200);

    }



}
