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
use App\Exports\Contabilidad\LibroAnuladosExport;
use App\Exports\Contabilidad\AnexoAnuladosExport;
use App\Exports\Contabilidad\LibroSujetosExcluidosExport;
use App\Exports\Contabilidad\AnexoSujetosExcluidosExport;
use App\Exports\Contabilidad\LibroComprasExport;
use App\Exports\Contabilidad\AnexoComprasExport;
use App\Exports\Contabilidad\GlobalDttesExport;
use App\Exports\Contabilidad\LibroRetencion1Export;
use App\Exports\Contabilidad\AnexoRetencion1Export;
use App\Exports\Contabilidad\LibroPercepcion1Export;
use App\Exports\Contabilidad\AnexoPercepcion1Export;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade as PDF;

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
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
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
                'correlativo'           => $venta->sello_mh ? $venta->dte['identificacion']['codigoGeneracion'] : trim($venta->correlativo),
                'num_control_interno'   => $venta->correlativo,
                'ventas_exentas'        => $venta->iva == 0 ? $venta->total : 0,
                'ventas_gravadas'       => $venta->iva > 0 ? ($venta->documento->nombre === 'Factura de exportación' ? '0.00' : $venta->total) : 0,
                'exportaciones'         => $venta->documento->nombre === 'Factura de exportación' ? $venta->total : '0.00',
                'total'                 => $venta->total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros,
                'no_sujeta'            => $venta->no_sujeta,
                'id_venta'              => $venta->id,
            ];
        });
        //


        // Ordenamos por 'correlativo' de forma descendente y reindexamos
        $libroconsumidores = $ivas->sortByDesc(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            })->values()->all();
        

        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = PDF::loadView('reportes.contabilidad.libro-consumidores', compact('libroconsumidores', 'request'));
            $pdf->setPaper('US Letter', 'landscape');

            return $pdf->stream('libro-consumidores.pdf');
        }

        return response()->json($libroconsumidores, 200);
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

        return Excel::download($consumidores, 'AnexoConsumidoresExport.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function contribuyentes(Request $request)
    {

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', function ($q) {
                $q->where('nombre', 'Crédito fiscal');
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
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
                'correlativo'           => $venta->correlativo,
                'num_documento'         => $venta->sello_mh ? $venta->dte['identificacion']['codigoGeneracion'] : trim($venta->correlativo),
                'nombre_cliente'        => $venta->nombre_cliente,
                'nit_nrc'               => $cliente->ncr ?? $cliente->nit,
                'ventas_exentas'        => $venta->iva == 0 ? $venta->sub_total : 0,
                'ventas_gravadas'       => $venta->iva > 0 ? $venta->sub_total : 0,
                'debito_fiscal'         => $venta->iva,
                'ventas_exentas_cuenta_a_terceros' => 0.00,
                'ventas_gravadas_cuenta_a_terceros' => $venta->cuenta_a_terceros,
                'debito_fiscal_cuenta_a_terceros' => 0.00,
                'iva_percibido'         => $venta->iva_percibido,
                'total'                 => $venta->total,
                'id_venta'              => $venta->id,
            ];
        });

        $devoluciones = DevolucionVenta::with(['cliente', 'venta'])
            ->where('enable', true)
            ->whereHas('venta', function ($query) {
                $query->where('estado', '!=', 'Anulada');
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
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
                'iva_retenido'         => $venta->iva_retenido > 0 ? $venta->iva_retenido * -1 : $venta->iva_retenido,
                'iva_percibido'         => $venta->iva_percibido > 0 ? $venta->iva_percibido * -1 : $venta->iva_percibido,
                'total'                 => $venta->total > 0 ? $venta->total * -1 : $venta->total,
            ];
        });

        // Unir y ordenar ambas colecciones por fecha
        $librocontribuyentes = collect($ventasData)
            ->merge(collect($devolucionesData))
            ->sortByDesc(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            })
            ->values()
            ->all();

        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = PDF::loadView('reportes.contabilidad.libro-contribuyentes', compact('librocontribuyentes', 'request'));
            $pdf->setPaper('US Letter', 'landscape');

            return $pdf->stream('libro-contribuyentes.pdf');
        }

        return response()->json($librocontribuyentes, 200);
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

        return Excel::download($contribuyentes, 'AnexoContribuyentesExport.csv', \Maatwebsite\Excel\Excel::CSV);

    }

    public function anulados(Request $request)
    {

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', 'Anulada')
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderByDesc('fecha')
            ->get();

        $ivas = $ventas->map(function ($venta) {
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            return [
                'resolucion'            => $venta->sello_mh ? $venta->dte['identificacion']['numeroControl'] : '',
                'clase'                 => $venta->sello_mh ? 4 : 1, // DTE o impreso
                'desde_pre'             => $venta->sello_mh ? 0 : trim($venta->correlativo),
                'hasta_pre'             => $venta->sello_mh ? 0 : trim($venta->correlativo),
                'tipo_documento'        => $venta->nombre_documento,
                'tipo_detalle'          => 'Documento Anulado',
                'serie'                 => $venta->sello_mh ? $venta->dte['sello'] : '',
                'desde'                 => $venta->sello_mh ? 0 : trim($venta->correlativo),
                'hasta'                 => $venta->sello_mh ? 0 : trim($venta->correlativo),
                'codigo_generacion'     => $venta->sello_mh ? $venta->dte['identificacion']['codigoGeneracion'] : '',
            ];
        });
        //


        // Ordenamos por 'correlativo' de forma descendente y reindexamos
        $ivas = $ivas->sortByDesc(function ($item) {
                return [$item['desde']];
            })->values()->all();
        // Log::info($ivas);

        return response()->json($ivas, 200);
    }

    public function anuladosLibroExport(Request $request)
    {
        $anulados = new LibroAnuladosExport();
        $anulados->filter($request);

        return Excel::download($anulados, 'LibroAnuladosExport.xlsx');
    }

    public function anuladosAnexoExport(Request $request)
    {
        $anulados = new AnexoAnuladosExport();
        $anulados->filter($request);

        return Excel::download($anulados, 'AnexoAnuladosExport.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function compras(Request $request)
    {

        // Obtener las compras
        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get()
            ->map(function ($compra) {
                $compra->origen = 'compra';
                return $compra;
            });

        $comprasData = $compras->map(function ($compra) {
            $proveedor = optional($compra->proveedor()->first());

            $data = [
                'fecha' => $compra->fecha,
                'clase_documento' => 1,
                'tipo_documento' => $compra->tipo_documento,
                'num_documento' => $compra->referencia,
                'nit_nrc' => $proveedor->ncr ?? $proveedor->nit,
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
                'registro' => $compra,
                'origen' => $compra->origen,
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
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($gasto) {
                $gasto->origen = 'gasto';
                return $gasto;
            });

        // Transformar gastos
        $gastosData = $gastos->map(function ($gasto) {
            $proveedor = optional($gasto->proveedor()->first());

            $data = [
                'fecha'                 => $gasto->fecha,
                'clase_documento'       => 1, // Por ejemplo, otro tipo de documento para gastos
                'tipo_documento'        => $gasto->tipo_documento,
                'num_documento'         => $gasto->referencia,
                'nit_nrc'               => $proveedor->ncr ?? $proveedor->nit,
                'nombre_proveedor'      => $gasto->nombre_proveedor,
                'compras_exentas'       => $gasto->total_otros_impuestos,
                'importaciones_exentas' => 0,
                'compras_gravadas'      => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal'        => 0,
                'anticipo_iva_percibido' => $gasto->percepcion,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total'                 => 0,
                'sujeto_excluido'       => 0,
                'registro' => $gasto,
                'origen' => $gasto->origen,
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
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($devolucion) {
                $devolucion->origen = 'devolucion';
                return $devolucion;
            });


        // Transformar gastos
        $devolucionesData = $devoluciones->map(function ($devolucion) {
            $proveedor = optional($devolucion->proveedor()->first());


            $data = [
                'fecha'                 => $devolucion->fecha,
                'clase_documento'       => 1,
                'tipo_documento'        => $devolucion->tipo_documento,
                'num_documento'         => $devolucion->referencia,
                'nit_nrc'               => $proveedor->ncr ?? $proveedor->nit,
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
                'registro' => $devolucion,
                'origen' => $devolucion->origen,
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
        $librocompras = collect($comprasData)
            ->merge(collect($gastosData))
            ->merge(collect($devolucionesData))
            ->sortBy('fecha')
            ->values()
            ->all();

        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = PDF::loadView('reportes.contabilidad.libro-compras', compact('librocompras', 'request'));
            $pdf->setPaper('US Letter', 'landscape');

            return $pdf->stream('libro-compras.pdf');
        }


        return response()->json($librocompras, 200);
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

        return Excel::download($compras, 'AnexoComprasExport.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function comprasSujetosExcluidos(Request $request)
    {

        // Obtener las compras
        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            // ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Sujeto excluido')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get()
            ->map(function ($compra) {
                $compra->origen = 'compra';
                return $compra;
            });

        $comprasData = $compras->map(function ($compra) {
            $proveedor = optional($compra->proveedor()->first());

            $data = [
                'tipo_documento' => $proveedor->nit ? 'NIT' : 'DUI',  // A - TIPO DE DOCUMENTO
                'num_documento' => $proveedor->nit ? $proveedor->nit : $proveedor->dui,  // B - NUMERO DE NIT, DI-II, IJ OTRO DOCUMENTO
                'proveedor' => $compra->nombre_proveedor,  // C - NOMBRE, RAZ N SOCIAL O DENOMINACI N
                'fecha' => $compra->fecha,  // D - FECHA DE EMISI N DEL DOCUMENTO
                'serie' => $compra->num_serie,  // E - NUMERO DE SERIE DEL DOCUMENTO
                'referencia' => $compra->referencia,  // F - NUMERO DE DOCUMENTO
                'total' => $compra->total,  // G - MONTO DE LA OPERACIÖN
                'iva' => $compra->iva,  // H - MONTO DE LA RETENCIÖN IVA 13%
                'tipo_operacion' => $compra->exenta > 0 ? 'Exenta' : 'Gravada',  // I - TIPO DE OPERACIÖN
                'clasificacion' =>  'Costo' ,  // J - CLASIFICACI Costo gasto
                'sector' => $compra->sector,  // K - SECTOR
                'tipo' =>   $compra->tipo,  // L - TIPO DE COSTO / GASTO
                'num_anexo' => 5,  // M - NUMERO DE ANEXO
            ];
            return $data;
        });

        // Obtener los gastos
        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            // ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Sujeto excluido')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($gasto) {
                $gasto->origen = 'gasto';
                return $gasto;
            });

        // Transformar gastos
        $gastosData = $gastos->map(function ($gasto) {
            $proveedor = optional($gasto->proveedor()->first());

            $data = [
                'tipo_documento' => $proveedor->nit ? 'NIT' : 'DUI',
                'num_documento' => $proveedor->nit ? $proveedor->nit : $proveedor->dui,
                'proveedor' => $gasto->nombre_proveedor,
                'fecha' => $gasto->fecha,
                'serie' => '',
                'referencia' => $gasto->referencia,
                'total' => $gasto->total,
                'iva' => $gasto->iva,
                'tipo_operacion' => $gasto->exenta > 0 ? 'Exenta' : 'Gravada',  // I - TIPO DE OPERACIÖN
                'clasificacion' => 'Gasto' ,  // J - CLASIFICACI Costo gasto
                'sector' => $gasto->sector,  // K - SECTOR
                'tipo' =>   $gasto->tipo,  // L - TIPO DE COSTO / GASTO
                'num_anexo' => 5,
            ];

            return $data;
        });

        // Unir y ordenar ambas colecciones por fecha
        $libroSujetoExcluido = collect($comprasData)
            ->merge(collect($gastosData))
            ->sortBy('fecha')
            ->values()
            ->all();

        return response()->json($libroSujetoExcluido, 200);
    }


    public function comprasSujetosExcluidosLibroExport(Request $request)
    {
        $compras = new LibroSujetosExcluidosExport();
        $compras->filter($request);

        return Excel::download($compras, 'LibroSujetosExcluidos.xlsx');
    }

    public function comprasSujetosExcluidosAnexoExport(Request $request)
    {
        $compras = new AnexoSujetosExcluidosExport();
        $compras->filter($request);

        return Excel::download($compras, 'AnexoSujetosExcluidos.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function GlobalDttesExport(Request $request)
    {
        try {
            
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            $dttes = new GlobalDttesExport();
            $dttes->filter($request);
            
            $result = $dttes->generateZip();
            
            if (!$result['success']) {
                Log::error('Error al generar ZIP: ' . $result['message']);
                return response($result['message'], 400)
                    ->header('Content-Type', 'text/plain');
            }
            
            $filePath = storage_path('app/' . $result['path']);
            
            if (!file_exists($filePath)) {
                Log::error('Archivo ZIP no encontrado: ' . $filePath);
                return response('Archivo no encontrado', 404)
                    ->header('Content-Type', 'text/plain');
            }
            
            $fileSize = filesize($filePath);
            
            // Leer contenido
            $fileContent = file_get_contents($filePath);
            
            // Eliminar archivo
            @unlink($filePath);
            
            
            // Retornar respuesta con headers claros
            return response($fileContent, 200)
                ->header('Content-Type', 'application/zip')
                ->header('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
                ->header('Content-Length', strlen($fileContent))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
            
        } catch (\Exception $e) {
            Log::error('Excepción al exportar DTEs: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response('Error al procesar la solicitud: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
    
    public function libroRetencion1Export(Request $request)
    {
        $retencion = new LibroRetencion1Export();
        $retencion->filter($request);

        return Excel::download($retencion, 'LibroRetencion1.xlsx');
    }


    public function libroPercepcion1Export(Request $request)
    {
        $percepcion = new LibroPercepcion1Export();
        $percepcion->filter($request);

        return Excel::download($percepcion, 'LibroPercepcion1.xlsx');
    }

    public function anexoRetencion1Export(Request $request)
    {
        $retencion = new AnexoRetencion1Export();
        $retencion->filter($request);

        return Excel::download($retencion, 'AnexoRetencion1.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function anexoPercepcion1Export(Request $request)
    {
        $percepcion = new AnexoPercepcion1Export();
        $percepcion->filter($request);

        return Excel::download($percepcion, 'AnexoPercepcion1.csv', \Maatwebsite\Excel\Excel::CSV);
    }

}
