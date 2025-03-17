<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Models\Compras\Gastos\Gasto;
use App\Exports\Contabilidad\LibroContribuyentesExport;
use App\Exports\Contabilidad\AnexoContribuyentesExport;
use App\Exports\Contabilidad\LibroConsumidoresExport;
use App\Exports\Contabilidad\AnexoConsumidoresExport;
use App\Exports\Contabilidad\LibroComprasExport;
use App\Exports\Contabilidad\AnexoComprasExport;
use App\Exports\Contabilidad\GlobalDttesExport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class LibrosIVAController extends Controller
{

    public function consumidores(Request $request)
    {

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', function ($q) {
                $q->where('nombre', 'Factura')
                    ->orWhere('nombre', 'Factura de exportación');
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
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
                'ventas_gravadas'       => $venta->documento->nombre === 'Factura de exportación' ? '0' : $venta->sub_total,
                'exportaciones'         => $venta->documento->nombre === 'Factura de exportación' ? $venta->sub_total : '0',
                'total'                 => $venta->sub_total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros,
                'no_sujeta'            => $venta->no_sujeta,
                'id_venta'              => $venta->id,
            ];
        });
        //


        // Ordenamos por 'correlativo' de forma descendente y reindexamos
        $ivas = $ivas->sortByDesc('num_documento')->values()->all();
        // Log::info($ivas);

        return response()->json($ivas, 200);
    }

    public function consumidoresLibroExport(Request $request)
    {
        $consumidores = new LibroConsumidoresExport();
        $consumidores->filter($request);

        return Excel::download($consumidores, 'LibroConsumidoresExport.xlsx');
    }

    public function consumidoresAnexoExport(Request $request)
    {
        $consumidores = new AnexoConsumidoresExport();
        $consumidores->filter($request);

        return Excel::download($consumidores, 'AnexoConsumidoresExport.xlsx');
    }

    public function contribuyentes(Request $request)
    {

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', function ($q) {
                $q->where('nombre', 'Crédito fiscal');
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderByDesc('fecha')
            ->get();

        $ventasData = $ventas->map(function ($venta) {
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
                'ventas_exentas_cuenta_a_terceros' => 0,
                'ventas_gravadas_cuenta_a_terceros' => 0,
                'debito_fiscal_cuenta_a_terceros' => 0,
                'debito_fiscal_cuenta_a_terceros' => 0,
                'iva_retenido'         => $venta->iva_retenido,
                'iva_percibido'         => $venta->iva_percibido,
                'total'                 => $venta->total,
                'no_sujeta'            => $venta->no_sujeta,
                'id_venta'              => $venta->id,
            ];
        });

        $devoluciones = DevolucionVenta::with(['cliente'])
            ->where('enable', true)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();


        // Transformar devoluciones
        $devolucionesData = $devoluciones->map(function ($venta) {
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            return [
                'fecha'                 => $venta->fecha,
                'correlativo'         => $venta->correlativo,
                'num_documento'         => $venta->correlativo,
                'nombre_cliente'        => $venta->nombre_cliente,
                'nit_nrc'               => $cliente->nit ?? $cliente->ncr,
                'ventas_exentas'        => $venta->exenta > 0 ? $venta->exenta * -1 : $venta->exenta,
                'ventas_no_sujetas'     => $venta->no_sujeta > 0 ? $venta->no_sujeta * -1 : $venta->no_sujeta,
                'ventas_gravadas'       => $venta->sub_total > 0 ? $venta->sub_total * -1 : $venta->sub_total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros > 0 ? $venta->cuenta_a_terceros * -1 : $venta->cuenta_a_terceros,
                'debito_fiscal'         => $venta->iva > 0 ? $venta->iva * -1 : $venta->iva,
                'ventas_exentas_cuenta_a_terceros' => 0,
                'ventas_gravadas_cuenta_a_terceros' => 0,
                'debito_fiscal_cuenta_a_terceros' => 0,
                'debito_fiscal_cuenta_a_terceros' => 0,
                'iva_retenido'         => $venta->iva_retenido > 0 ? $venta->iva_retenido * -1 : $venta->iva_retenido,
                'iva_percibido'         => $venta->iva_percibido > 0 ? $venta->iva_percibido * -1 : $venta->iva_percibido,
                'total'                 => $venta->total > 0 ? $venta->total * -1 : $venta->total,
            ];
        });

        // Unir y ordenar ambas colecciones por fecha
        $libroventas = collect($ventasData)
            ->merge(collect($devolucionesData))
            ->sortByDesc(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            })
            ->values()
            ->all();

        return response()->json($libroventas, 200);
    }

    public function contribuyentesLibroExport(Request $request)
    {
        $contribuyentes = new LibroContribuyentesExport();
        $contribuyentes->filter($request);

        return Excel::download($contribuyentes, 'LibroContribuyentesExport.xlsx');
    }

    public function contribuyentesAnexoExport(Request $request)
    {
        $contribuyentes = new AnexoContribuyentesExport();
        $contribuyentes->filter($request);

        return Excel::download($contribuyentes, 'AnexoContribuyentesExport.xlsx');
    }

    public function compras(Request $request)
    {

        // Obtener las compras
        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();


        // Transformar compras
        // $comprasData = $compras->map(function ($compra) {
        //     $proveedor = optional($compra->proveedor);

        //     return [
        //         'fecha'                 => $compra->fecha,
        //         'clase_documento'       => 1,
        //         'tipo_documento'        => $compra->tipo_documento,
        //         'num_documento'         => $compra->referencia,
        //         'nit_nrc'               => $proveedor->nit ?? $proveedor->ncr,
        //         'nombre_proveedor'      => $compra->nombre_proveedor,
        //         'compras_exentas'       => $compra->exenta,
        //         'importaciones_exentas' => 0,
        //         'compras_gravadas'      => $compra->sub_total,
        //         'importaciones_gravadas' => 0,
        //         'credito_fiscal'        => $compra->iva,
        //         'anticipo_iva_percibido' => $compra->percepcion,
        //         'compras_cuenta_terceros' => 0,
        //         'credito_cuenta_terceros' => 0,
        //         'total'                 => $compra->tipo_documento != 'Sujeto excluido' ? $compra->total : 0,
        //         'sujeto_excluido'       => $compra->tipo_documento == 'Sujeto excluido' ? $compra->total : 0,
        //         'no_sujeta'            => $compra->no_sujeta,
        //         'id_compra'             => $compra->id,
        //     ];
        // });

        $comprasData = $compras->map(function ($compra) {
            $proveedor = optional($compra->proveedor);

            $data = [
                'fecha' => $compra->fecha,
                'clase_documento' => 1,
                'tipo_documento' => $compra->tipo_documento,
                'num_documento' => $compra->referencia,
                'nit_nrc' => $proveedor->nit ?? $proveedor->ncr,
                'nombre_proveedor' => $compra->nombre_proveedor,
                'compras_exentas' => 0,
                'importaciones_exentas' => 0,
                'compras_gravadas' => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal' => 0,
                'anticipo_iva_percibido' => 0,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total' => 0,
                'sujeto_excluido' => 0,
                'no_sujeta' => 0,
                'id_compra' => $compra->id,
            ];


            switch ($compra->tipo_documento) {
                case 'Sujeto excluido':
                    $data['sujeto_excluido'] = $compra->total;
                    break;
                default:
                    $data['compras_gravadas'] = $compra->sub_total;
                    $data['credito_fiscal'] = $compra->iva;
                    $data['total'] = $compra->total;
                    break;
            }

            return $data;
        });

        // Obtener los gastos
        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        // Transformar gastos
        $gastosData = $gastos->map(function ($gasto) {
            $proveedor = optional($gasto->proveedor);

            $data = [
                'fecha'                 => $gasto->fecha,
                'clase_documento'       => 1, // Por ejemplo, otro tipo de documento para gastos
                'tipo_documento'        => $gasto->tipo_documento,
                'num_documento'         => $gasto->referencia,
                'nit_nrc'               => $proveedor->nit ?? $proveedor->ncr,
                'nombre_proveedor'      => $gasto->nombre_proveedor,
                'compras_exentas'       => 0,
                'importaciones_exentas' => 0,
                'compras_gravadas'      => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal'        => 0,
                'anticipo_iva_percibido' => $gasto->percepcion,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total'                 => 0,
                'sujeto_excluido'       => 0,
            ];

            switch ($gasto->tipo_documento) {
                case 'Sujeto excluido':
                    $data['sujeto_excluido'] = $gasto->total;
                    break;
                default:
                    $data['compras_gravadas'] = $gasto->sub_total;
                    $data['credito_fiscal'] = $gasto->iva;
                    $data['total'] = $gasto->total;
                    break;
            }

            return $data;
        });

        $devoluciones = DevolucionCompra::with(['proveedor'])
            ->where('enable', true)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();


        // Transformar gastos
        $devolucionesData = $devoluciones->map(function ($devolucion) {
            $proveedor = optional($devolucion->proveedor);


            $data = [
                'fecha'                 => $devolucion->fecha,
                'clase_documento'       => 1,
                'tipo_documento'        => $devolucion->tipo_documento,
                'num_documento'         => $devolucion->referencia,
                'nit_nrc'               => $proveedor->nit ?? $proveedor->ncr,
                'nombre_proveedor'      => $devolucion->nombre_proveedor,
                'compras_exentas'       => 0,
                'importaciones_exentas' => 0,
                'compras_gravadas'      => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal'        => 0,
                'anticipo_iva_percibido' => $devolucion->percepcion * -1,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total'                 => 0,
                'sujeto_excluido'       => 0,
            ];

            switch ($devolucion->tipo_documento) {
                case 'Sujeto excluido':
                    $data['sujeto_excluido'] = $devolucion->total * -1;
                    break;
                default:
                    $data['compras_gravadas'] = $devolucion->sub_total * -1;
                    $data['credito_fiscal'] = $devolucion->iva * -1;
                    $data['total'] = $devolucion->total * -1;
                    break;
            }

            return $data;
        });

        // Unir y ordenar ambas colecciones por fecha
        $libroCompras = collect($comprasData)
            ->merge(collect($gastosData))
            ->merge(collect($devolucionesData))
            ->sortBy('fecha')
            ->values()
            ->all();



        return response()->json($libroCompras, 200);
    }


    public function comprasLibroExport(Request $request)
    {
        $compras = new LibroComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'LibroComprasExport.xlsx');
    }

    public function comprasAnexoExport(Request $request)
    {
        $compras = new AnexoComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'AnexoComprasExport.xlsx');
    }

    public function GlobalDttesExport(Request $request)
    {
        try {
            $dttes = new GlobalDttesExport();
            $dttes->filter($request);
            
            $result = $dttes->generateZip();
            
            if (!$result['success']) {
                Log::error('Error al generar ZIP: ' . $result['message']);
                
                // Devolver texto plano en lugar de JSON
                return response($result['message'], 400)
                    ->header('Content-Type', 'text/plain');
            }
            
            return response()->download(
                storage_path('app/' . $result['path']),
                $result['filename'],
                [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename=' . $result['filename']
                ]
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Excepción al exportar DTEs: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            // Devolver texto plano en lugar de JSON
            return response('Error al procesar la solicitud: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
}
