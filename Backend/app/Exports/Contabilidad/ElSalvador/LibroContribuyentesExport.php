<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Empresa;

class LibroContribuyentesExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{

    public $request;
    private $index = 1;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Verifica si la empresa tiene facturación electrónica habilitada
     */
    private function tieneFacturacionElectronica(): bool
    {
        $empresa = Auth::user()->empresa()->first();
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
                Log::warning('Se excluyeron ventas sin sello al exportar libro contribuyentes', [
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

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);

                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS A CONTRIBUYENTES ');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A3', 'NRC: ' . Auth::user()->empresa()->pluck('ncr')->first());
                $event->sheet->setCellValue('E3', 'Folio N°:');
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')));
                $event->sheet->setCellValue('E4', 'Año: ' . Carbon::parse($this->request->inicio)->format('Y'));

            },
        ];
    }

    public function headings():array{
        return[
            'N°',
            'FECHA',
            'CÓDIGO DE GENERACIÓN',
            'NÚMERO DE CONTROL',
            'SELLO',
            'NOMBRE DEL CLIENTE MANDANTE O MANDATARIO',
            'NRC DEL CLIENTE',
            'VENTAS EXENTAS',
            'VENTAS INTERNAS GRAVADAS',
            'DÉBITO FISCAL',
            'VENTAS EXENTAS A CUENTA DE TERCEROS',
            'VENTAS INTERNAS GRAVADAS A CUENTA DE TERCEROS',
            'DEBITO FISCAL POR CUENTA DE TERCEROS',
            'IVA RETENIDO',
            'IVA PERCIBIDO',
            'TOTAL',
        ];
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)

        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Crédito fiscal');
                            });
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

        $libroVentas = $ventasData
            ->merge($devolucionesData)
            ->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            })
            ->values();

        $totales = [
            'es_total' => true,
            'nombre_cliente' => 'Totales',
            'ventas_exentas' => $libroVentas->sum('ventas_exentas'),
            'ventas_internas_gravadas' => $libroVentas->sum('ventas_internas_gravadas'),
            'debito_fiscal' => $libroVentas->sum('debito_fiscal'),
            'ventas_exentas_a_cuenta_de_terceros' => $libroVentas->sum('ventas_exentas_a_cuenta_de_terceros'),
            'ventas_internas_gravadas_a_cuenta_de_terceros' => $libroVentas->sum('ventas_internas_gravadas_a_cuenta_de_terceros'),
            'debito_fiscal_por_cuenta_de_terceros' => $libroVentas->sum('debito_fiscal_por_cuenta_de_terceros'),
            'iva_retenido' => $libroVentas->sum('iva_retenido'),
            'iva_percibido' => $libroVentas->sum('iva_percibido'),
            'total' => $libroVentas->sum('total'),
        ];

        return $libroVentas->push($totales);

    }

    public function map($fila): array{
            $fila = is_array($fila) ? $fila : (array) $fila;

            if (!empty($fila['es_total'])) {
                return [
                    '',
                    'Totales',
                    '',
                    '',
                    '',
                    $fila['nombre_cliente'],
                    '',
                    $fila['ventas_exentas'],
                    $fila['ventas_internas_gravadas'],
                    $fila['debito_fiscal'],
                    $fila['ventas_exentas_a_cuenta_de_terceros'],
                    $fila['ventas_internas_gravadas_a_cuenta_de_terceros'],
                    $fila['debito_fiscal_por_cuenta_de_terceros'],
                    $fila['iva_retenido'],
                    $fila['iva_percibido'],
                    $fila['total'],
                ];
            }

            return [
                $this->index++,
                Carbon::parse($fila['fecha'])->format('d/m/Y'),
                $fila['codigo_generacion'],
                $fila['numero_control'],
                $fila['sello'],
                $fila['nombre_cliente'],
                $fila['nrc_cliente'],
                $fila['ventas_exentas'],
                $fila['ventas_internas_gravadas'],
                $fila['debito_fiscal'],
                $fila['ventas_exentas_a_cuenta_de_terceros'],
                $fila['ventas_internas_gravadas_a_cuenta_de_terceros'],
                $fila['debito_fiscal_por_cuenta_de_terceros'],
                $fila['iva_retenido'],
                $fila['iva_percibido'],
                $fila['total'],
            ];

    }
}
