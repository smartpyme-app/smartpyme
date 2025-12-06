<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;

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
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Empresa;

class LibrosIVAController extends Controller
{
    /**
     * Obtiene la empresa del usuario autenticado
     */
    private function obtenerEmpresa(): ?Empresa
    {
        return Auth::user()->empresa()->first();
    }

    /**
     * Verifica si la empresa tiene facturación electrónica habilitada
     */
    private function tieneFacturacionElectronica(): bool
    {
        $empresa = $this->obtenerEmpresa();
        return $empresa && $empresa->facturacion_electronica === true;
    }

    /**
     * Filtra ventas según si tienen facturación electrónica o no
     */
    private function filtrarVentasPorFacturacionElectronica($ventas)
    {
        if ($this->tieneFacturacionElectronica()) {
            // Con facturación electrónica: solo ventas con sello_mh
            $ventasSinSello = $ventas->filter(function ($venta) {
                return empty($venta->sello_mh);
            });

            if ($ventasSinSello->isNotEmpty()) {
                Log::warning('Se excluyeron ventas sin sello', [
                    'ventas' => $ventasSinSello->pluck('id'),
                ]);
            }

            return $ventas->reject(function ($venta) {
                return empty($venta->sello_mh);
            });
        } else {
            // Sin facturación electrónica: todas las ventas
            return $ventas;
        }
    }

    /**
     * Obtiene el código de generación o correlativo según facturación electrónica
     */
    private function obtenerCodigoGeneracion($venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh && isset($venta->dte['identificacion']['codigoGeneracion'])) {
            return $venta->dte['identificacion']['codigoGeneracion'];
        }
        return trim((string) $venta->correlativo);
    }

    /**
     * Obtiene el número de control según facturación electrónica
     */
    private function obtenerNumeroControl($venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh && isset($venta->dte['identificacion']['numeroControl'])) {
            return $venta->dte['identificacion']['numeroControl'];
        }
        return $venta->numero_control ?? trim((string) $venta->correlativo);
    }

    /**
     * Obtiene el sello según facturación electrónica
     */
    private function obtenerSello($venta): string
    {
        if ($this->tieneFacturacionElectronica() && isset($venta->dte['sello'])) {
            return $venta->dte['sello'];
        }
        return $venta->sello_mh ?? '';
    }

    /**
     * Obtiene la clase de documento (DTE o Impreso)
     */
    private function obtenerClaseDocumento($venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh) {
            return '4'; // DTE
        }
        return '1'; // Impreso
    }

    /**
     * Obtiene el código de generación para devoluciones
     */
    private function obtenerCodigoGeneracionDevolucion($devolucion): string
    {
        if ($this->tieneFacturacionElectronica()) {
            $dte = $devolucion->dte ?? [];
            if ($devolucion->codigo_generacion) {
                return $devolucion->codigo_generacion;
            }
            if (isset($dte['identificacion']['codigoGeneracion'])) {
                return $dte['identificacion']['codigoGeneracion'];
            }
        }
        return trim((string) $devolucion->correlativo);
    }

    /**
     * Obtiene el número de control para devoluciones
     */
    private function obtenerNumeroControlDevolucion($devolucion): string
    {
        if ($this->tieneFacturacionElectronica()) {
            $dte = $devolucion->dte ?? [];
            if ($devolucion->numero_control) {
                return $devolucion->numero_control;
            }
            if (isset($dte['identificacion']['numeroControl'])) {
                return $dte['identificacion']['numeroControl'];
            }
        }
        return trim((string) $devolucion->correlativo);
    }

    /**
     * Obtiene el sello para devoluciones
     */
    private function obtenerSelloDevolucion($devolucion): string
    {
        if ($this->tieneFacturacionElectronica()) {
            $dte = $devolucion->dte ?? [];
            if (isset($dte['sello'])) {
                return $dte['sello'];
            }
        }
        return $devolucion->sello_mh ?? '';
    }

    private function validarVentasPendientes(Request $request, ?array $documentos = null): ?JsonResponse
    {
        if (!$request->filled('inicio') || !$request->filled('fin')) {
            return null;
        }

        // Solo validar ventas pendientes si tiene facturación electrónica
        if (!$this->tieneFacturacionElectronica()) {
            return null;
        }

        $ventasPendientes = Venta::query()
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when(!empty($documentos), function ($query) use ($documentos) {
                $query->whereHas('documento', function ($subQuery) use ($documentos) {
                    $subQuery->whereIn('nombre', $documentos);
                });
            })
            ->where(function ($query) {
                $query->whereNull('sello_mh')
                    ->orWhere('sello_mh', '');
            })
            ->exists();

        if ($ventasPendientes) {
            return response()->json([
                'message' => 'Existen ventas pendientes de emitirse para el período seleccionado, por favor complete las ventas pendientes emitiendo los documentos.',
            ], 500);
        }

        return null;
    }

    public function consumidores(BaseLibroIVARequest $request)
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
            ->get();

        // Filtrar ventas según facturación electrónica
        $ventasFiltradas = $this->filtrarVentasPorFacturacionElectronica($ventas);

        $libroconsumidores = $ventasFiltradas
            ->groupBy(function ($venta) {
                return Carbon::parse($venta->fecha)->format('Y-m-d');
            })
            ->map(function ($ventasDia, $fecha) {
                $ventasOrdenadasPorCorrelativo = $ventasDia->sortBy(function ($venta) {
                    return trim((string) $venta->correlativo);
                });

                $ventasOrdenadasPorCodigo = $ventasDia->sortBy(function ($venta) {
                    return $this->obtenerCodigoGeneracion($venta);
                });

                // Primero identificar exportaciones (sin importar el IVA)
                $exportaciones = $ventasDia->sum(function ($venta) {
                    $documentoNombre = optional($venta->documento)->nombre;
                    return $documentoNombre === 'Factura de exportación'
                        ? (float) $venta->total
                        : 0;
                });

                // Luego clasificar las ventas restantes (excluyendo exportaciones)
                $ventasExentas = $ventasDia->sum(function ($venta) {
                    $documentoNombre = optional($venta->documento)->nombre;
                    // Excluir exportaciones
                    if ($documentoNombre === 'Factura de exportación') {
                        return 0;
                    }
                    return $venta->iva == 0 ? (float) $venta->total : 0;
                });

                $ventasGravadas = $ventasDia->sum(function ($venta) {
                    $documentoNombre = optional($venta->documento)->nombre;
                    // Excluir exportaciones
                    if ($documentoNombre === 'Factura de exportación') {
                        return 0;
                    }
                    return $venta->iva > 0 ? (float) $venta->total : 0;
                });

                $ventasTerceros = $ventasDia->sum(function ($venta) {
                    return (float) $venta->cuenta_a_terceros;
                });

                $totalDiario = $ventasDia->sum(function ($venta) {
                    return (float) $venta->total;
                });

                $primeraVenta = $ventasOrdenadasPorCodigo->first();
                $ultimaVenta = $ventasOrdenadasPorCodigo->last();
                $correlativoInicial = optional($ventasOrdenadasPorCorrelativo->first())->correlativo;

                return [
                    'fecha' => $fecha,
                    'correlativo_inicial' => $primeraVenta ? $this->obtenerCodigoGeneracion($primeraVenta) : null,
                    'correlativo_final' => $ultimaVenta ? $this->obtenerCodigoGeneracion($ultimaVenta) : null,
                    'ventas_exentas' => round($ventasExentas, 2),
                    'ventas_internas_gravadas' => round($ventasGravadas, 2),
                    'exportaciones' => round($exportaciones, 2),
                    'total_ventas_diarias_propias' => round($totalDiario, 2),
                    'ventas_a_cuenta_de_terceros' => round($ventasTerceros, 2),
                    'correlativo_orden' => trim((string) $correlativoInicial),
                ];
            })
            ->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo_orden'] ?? ''];
            })
            ->values()
            ->all();

        $totalesConsumidores = collect($libroconsumidores)->reduce(function ($carry, $item) {
            $carry['ventas_exentas'] += $item['ventas_exentas'];
            $carry['ventas_internas_gravadas'] += $item['ventas_internas_gravadas'];
            $carry['exportaciones'] += $item['exportaciones'];
            $carry['total_ventas_diarias_propias'] += $item['total_ventas_diarias_propias'];
            $carry['ventas_a_cuenta_de_terceros'] += $item['ventas_a_cuenta_de_terceros'];
            return $carry;
        }, [
            'ventas_exentas' => 0,
            'ventas_internas_gravadas' => 0,
            'exportaciones' => 0,
            'total_ventas_diarias_propias' => 0,
            'ventas_a_cuenta_de_terceros' => 0,
        ]);


        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = PDF::loadView(
                'reportes.contabilidad.libro-consumidores',
                [
                    'libroconsumidores' => $libroconsumidores,
                    'request' => $request,
                    'totalesConsumidores' => $totalesConsumidores,
                ]
            );
            $pdf->setPaper('Legal', 'landscape');

            return $pdf->stream('libro-consumidores.pdf');
        }

        return response()->json($libroconsumidores, 200);
    }

    public function consumidoresLibroExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request, ['Factura', 'Factura de exportación'])) {
            return $alerta;
        }

        $consumidores = new LibroConsumidoresExport();
        $consumidores->filter($request);

        return Excel::download($consumidores, 'LibroConsumidoresExport.xlsx');
    }

    public function consumidoresAnexoExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request, ['Factura', 'Factura de exportación'])) {
            return $alerta;
        }

        $consumidores = new AnexoConsumidoresExport();
        $consumidores->filter($request);

        return Excel::download($consumidores, 'AnexoConsumidoresExport.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function contribuyentes(BaseLibroIVARequest $request)
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
            ->get();

        // Filtrar ventas según facturación electrónica
        $ventasFiltradas = $this->filtrarVentasPorFacturacionElectronica($ventas);

        $ventasData = $ventasFiltradas->map(function ($venta) {
            $cliente = optional($venta->cliente);

            $codigoGeneracion = $this->obtenerCodigoGeneracion($venta);
            $numeroControl = $this->obtenerNumeroControl($venta);
            $sello = $this->obtenerSello($venta);

            return [
                'fecha' => $venta->fecha,
                'codigo_generacion' => $codigoGeneracion,
                'numero_control' => $numeroControl,
                'sello' => $sello,
                'correlativo' => trim((string) $venta->correlativo),
                'nombre_cliente' => $venta->nombre_cliente,
                'nrc_cliente' => $cliente->ncr ?? $cliente->nit,
                'ventas_exentas' => $venta->iva == 0 ? (float) $venta->sub_total : 0,
                'ventas_internas_gravadas' => $venta->iva > 0 ? (float) $venta->sub_total : 0,
                'debito_fiscal' => (float) $venta->iva,
                'ventas_exentas_a_cuenta_de_terceros' => 0.0,
                'ventas_internas_gravadas_a_cuenta_de_terceros' => (float) $venta->cuenta_a_terceros,
                'debito_fiscal_por_cuenta_de_terceros' => 0.0,
                'iva_retenido' => (float) $venta->iva_retenido,
                'iva_percibido' => (float) $venta->iva_percibido,
                'total' => (float) $venta->total,
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
        $devolucionesData = $devoluciones->map(function ($devolucion) {
            $cliente = optional($devolucion->cliente);

            $codigoGeneracion = $this->obtenerCodigoGeneracionDevolucion($devolucion);
            $numeroControl = $this->obtenerNumeroControlDevolucion($devolucion);
            $sello = $this->obtenerSelloDevolucion($devolucion);

            return [
                'fecha' => $devolucion->fecha,
                'codigo_generacion' => $codigoGeneracion,
                'numero_control' => $numeroControl,
                'sello' => $sello,
                'correlativo' => trim((string) $devolucion->correlativo),
                'nombre_cliente' => $devolucion->nombre_cliente,
                'nrc_cliente' => $cliente->ncr ?? $cliente->nit,
                'ventas_exentas' => $devolucion->exenta > 0 ? $devolucion->exenta * -1 : $devolucion->exenta,
                'ventas_internas_gravadas' => $devolucion->sub_total > 0 ? $devolucion->sub_total * -1 : $devolucion->sub_total,
                'debito_fiscal' => $devolucion->iva > 0 ? $devolucion->iva * -1 : $devolucion->iva,
                'ventas_exentas_a_cuenta_de_terceros' => 0.0,
                'ventas_internas_gravadas_a_cuenta_de_terceros' => $devolucion->cuenta_a_terceros > 0 ? $devolucion->cuenta_a_terceros * -1 : $devolucion->cuenta_a_terceros,
                'debito_fiscal_por_cuenta_de_terceros' => 0.0,
                'iva_retenido' => $devolucion->iva_retenido > 0 ? $devolucion->iva_retenido * -1 : $devolucion->iva_retenido,
                'iva_percibido' => $devolucion->iva_percibido > 0 ? $devolucion->iva_percibido * -1 : $devolucion->iva_percibido,
                'total' => $devolucion->total > 0 ? $devolucion->total * -1 : $devolucion->total,
            ];
        });

        // Unir y ordenar ambas colecciones por fecha
        $librocontribuyentes = collect($ventasData)
            ->merge($devolucionesData)
            ->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            })
            ->values()
            ->all();

        $totalesContribuyentes = collect($librocontribuyentes)->reduce(function ($carry, $item) {
            $carry['ventas_exentas'] += $item['ventas_exentas'];
            $carry['ventas_internas_gravadas'] += $item['ventas_internas_gravadas'];
            $carry['debito_fiscal'] += $item['debito_fiscal'];
            $carry['ventas_exentas_a_cuenta_de_terceros'] += $item['ventas_exentas_a_cuenta_de_terceros'];
            $carry['ventas_internas_gravadas_a_cuenta_de_terceros'] += $item['ventas_internas_gravadas_a_cuenta_de_terceros'];
            $carry['debito_fiscal_por_cuenta_de_terceros'] += $item['debito_fiscal_por_cuenta_de_terceros'];
            $carry['iva_retenido'] += $item['iva_retenido'];
            $carry['iva_percibido'] += $item['iva_percibido'];
            $carry['total'] += $item['total'];
            return $carry;
        }, [
            'ventas_exentas' => 0,
            'ventas_internas_gravadas' => 0,
            'debito_fiscal' => 0,
            'ventas_exentas_a_cuenta_de_terceros' => 0,
            'ventas_internas_gravadas_a_cuenta_de_terceros' => 0,
            'debito_fiscal_por_cuenta_de_terceros' => 0,
            'iva_retenido' => 0,
            'iva_percibido' => 0,
            'total' => 0,
        ]);

        $formato = $request->query('formato') ?? 'json';

        if ($formato === 'pdf') {
            $pdf = PDF::loadView(
                'reportes.contabilidad.libro-contribuyentes',
                [
                    'librocontribuyentes' => $librocontribuyentes,
                    'request' => $request,
                    'totalesContribuyentes' => $totalesContribuyentes,
                ]
            );
            $pdf->setPaper('Legal', 'landscape');

            return $pdf->stream('libro-contribuyentes.pdf');
        }

        return response()->json($librocontribuyentes, 200);
    }

    public function contribuyentesLibroExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request, ['Crédito fiscal'])) {
            return $alerta;
        }

        $contribuyentes = new LibroContribuyentesExport();
        $contribuyentes->filter($request);

        return Excel::download($contribuyentes, 'LibroContribuyentesExport.xlsx');
    }

    public function contribuyentesAnexoExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request, ['Crédito fiscal'])) {
            return $alerta;
        }

        $contribuyentes = new AnexoContribuyentesExport();
        $contribuyentes->filter($request);

        return Excel::download($contribuyentes, 'AnexoContribuyentesExport.csv', \Maatwebsite\Excel\Excel::CSV);

    }

    public function anulados(BaseLibroIVARequest $request)
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

    public function anuladosLibroExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $anulados = new LibroAnuladosExport();
        $anulados->filter($request);

        return Excel::download($anulados, 'LibroAnuladosExport.xlsx');
    }

    public function anuladosAnexoExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $anulados = new AnexoAnuladosExport();
        $anulados->filter($request);

        return Excel::download($anulados, 'AnexoAnuladosExport.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function compras(BaseLibroIVARequest $request)
    {

        // Obtener las compras
        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('iva' , '>', 0)
            ->whereIn('tipo_documento', ['Crédito fiscal', 'Factura', 'Factura de exportación', 'Importación', 'Nota de crédito', 'Nota de débito'])
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
            ->whereIn('tipo_documento', ['Crédito fiscal', 'Factura', 'Factura de exportación', 'Importación', 'Nota de crédito', 'Nota de débito'])
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
            ->whereIn('tipo_documento', ['Crédito fiscal', 'Factura', 'Factura de exportación', 'Importación', 'Nota de crédito', 'Nota de débito'])
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


    public function comprasLibroExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $compras = new LibroComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'LibroComprasExport.xlsx');
    }

    public function comprasAnexoExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $compras = new AnexoComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'AnexoComprasExport.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function comprasSujetosExcluidos(BaseLibroIVARequest $request)
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


    public function comprasSujetosExcluidosLibroExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $compras = new LibroSujetosExcluidosExport();
        $compras->filter($request);

        return Excel::download($compras, 'LibroSujetosExcluidos.xlsx');
    }

    public function comprasSujetosExcluidosAnexoExport(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $compras = new AnexoSujetosExcluidosExport();
        $compras->filter($request);

        return Excel::download($compras, 'AnexoSujetosExcluidos.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function GlobalDttesExport(BaseLibroIVARequest $request)
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

    public function libroRetencion1Export(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $retencion = new LibroRetencion1Export();
        $retencion->filter($request);

        return Excel::download($retencion, 'LibroRetencion1.xlsx');
    }


    public function libroPercepcion1Export(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $percepcion = new LibroPercepcion1Export();
        $percepcion->filter($request);

        return Excel::download($percepcion, 'LibroPercepcion1.xlsx');
    }

    public function anexoRetencion1Export(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $retencion = new AnexoRetencion1Export();
        $retencion->filter($request);

        return Excel::download($retencion, 'AnexoRetencion1.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function anexoPercepcion1Export(BaseLibroIVARequest $request)
    {
        if ($alerta = $this->validarVentasPendientes($request)) {
            return $alerta;
        }

        $percepcion = new AnexoPercepcion1Export();
        $percepcion->filter($request);

        return Excel::download($percepcion, 'AnexoPercepcion1.csv', \Maatwebsite\Excel\Excel::CSV);
    }

}
