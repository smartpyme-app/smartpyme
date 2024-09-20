<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Venta;
use App\Models\Compras\Compra;
use App\Exports\Contabilidad\LibroContribuyentesExport;
use App\Exports\Contabilidad\AnexoContribuyentesExport;
use App\Exports\Contabilidad\LibroConsumidorFinalExport;
use App\Exports\Contabilidad\AnexoConsumidorFinalExport;
use Maatwebsite\Excel\Facades\Excel;

class LibrosIVAController extends Controller
{

    public function consumidores(Request $request) 
    {
        $star = $request->inicio;
        $end = $request->fin;

        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Pendiente')
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Factura');
                            });
                        })
                        ->whereBetween('fecha', [$star, $end])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();

        $ivas = $ventas->map(function ($venta) {
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            return [
                'fecha'                 => $venta->fecha,
                'correlativo'           => $venta->correlativo,
                'num_control_interno'   => $venta->correlativo,
                'ventas_exentas'        => $venta->exenta,
                'ventas_gravadas'       => $venta->sub_total,
                'exportaciones'         => 0,
                'total'                 => $venta->total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros,
            ];
        });

        // Ordenamos por 'correlativo' de forma descendente y reindexamos
        $ivas = $ivas->sortByDesc('num_documento')->values()->all();

        return response()->json($ivas, 200);
    }

    public function contribuyentes(Request $request) 
    {
        $star = $request->inicio;
        $end = $request->fin;

        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Pendiente')
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Factura');
                            });
                        })
                        ->whereBetween('fecha', [$star, $end])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();

        $ivas = $ventas->map(function ($venta) {
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            return [
                'fecha'                 => $venta->fecha,
                'correlativo'         => $venta->correlativo,
                'num_documento'         => $venta->correlativo,
                'nombre_cliente'        => $venta->nombre_cliente,
                'nit_nrc'               => $cliente->nit ?? $cliente->ncr,
                'ventas_exentas'        => $venta->exenta,
                'ventas_no_sujetas'     => $venta->no_sujeta,
                'ventas_gravadas'       => $venta->sub_total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros,
                'debito_fiscal'         => $venta->iva,
                'ventas_exentas_cuenta_a_terceros'=> 0,
                'ventas_gravadas_cuenta_a_terceros'=> 0,
                'debito_fiscal_cuenta_a_terceros'=> 0,
                'debito_fiscal_cuenta_a_terceros'=> 0,
                'iva_percibido'         => $venta->iva_percibido,
                'total'                 => $venta->total,
            ];

        });

        $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return response()->json($ivas, 200);
    }

    public function compras(Request $request) {
        $star = $request->inicio;
        $end = $request->fin;

        $compras = Compra::with(['proveedor'])
                            ->where('estado', '!=', 'Anulada')
                            ->when($request->id_sucursal, function($q) use ($request){
                                $q->where('id_sucursal', $request->id_sucursal);
                            })
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->where('cotizacion', 0)
                            ->orderBy('id', 'desc')->get();

        $ivas = $compras->map(function ($compra) {
            $proveedor = $compra->proveedor;

            return [
                'fecha'                 => $compra->fecha,
                'clase_documento'       => 1,
                'tipo_documento'        => $compra->tipo_documento,
                'num_documento'         => $compra->referencia,
                'nit_nrc'               => $proveedor->nit ?? $proveedor->ncr,
                'nombre_proveedor'      => $compra->nombre_proveedor,
                'compras_exentas' => $compra->exenta,
                'importaciones_exentas' => 0,
                'compras_gravadas'      => $compra->sub_total,
                'importaciones_gravadas' => 0,
                'credito_fiscal'         => $compra->iva,
                'anticipo_iva_percibido'=> $compra->iva_percibido,
                'compras_cuenta_terceros'=> 0,
                'credito_cuenta_terceros'=> 0,
                'total'                 => $compra->total,
                'sujeto_excluido'       => 0,
            ];
        });


        $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return Response()->json($ivas, 200);

    }

    public function comprasLibroExport(Request $request){
        $compras = new LibroComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'LibroComprasExport.xlsx');
    }

    public function comprasAnexoExport(Request $request){
        $compras = new LibroComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'LibroComprasExport.xlsx');
    }

}
