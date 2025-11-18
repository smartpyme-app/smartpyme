<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
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

    class LibroConsumidoresExport implements FromCollection, WithMapping, WithHeadings, WithEvents
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
                Log::warning('Se excluyeron ventas sin sello al exportar libro consumidores', [
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

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);

                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS A CONSUMIDORES ');
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
            'Fecha',
            'Correlativo Inicial',
            'Correlativo Final',
            'VENTAS EXENTAS',
            'VENTAS INTERNAS GRAVADAS',
            'EXPORTACIONES',
            'TOTAL DE VENTAS DIARIAS PROPIAS',
            'VENTAS A CUENTA DE TERCEROS',
        ];
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)

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

        $filas = $this->generarFilas($ventasFiltradas);

        return $filas;

    }

    public function map($fila): array{
        $fila = is_array($fila) ? $fila : (array) $fila;

        if (!empty($fila['es_total'])) {
            return [
                '',
                'Totales',
                '',
                '',
                $fila['ventas_exentas'],
                $fila['ventas_internas_gravadas'],
                $fila['exportaciones'],
                $fila['total_ventas_diarias_propias'],
                $fila['ventas_a_cuenta_de_terceros'],
            ];
        }

        return [
            $this->index++,
            Carbon::parse($fila['fecha'])->format('d/m/Y'),
            $fila['correlativo_inicial'],
            $fila['correlativo_final'],
            $fila['ventas_exentas'],
            $fila['ventas_internas_gravadas'],
            $fila['exportaciones'],
            $fila['total_ventas_diarias_propias'],
            $fila['ventas_a_cuenta_de_terceros'],
        ];
    }

    protected function generarFilas($ventas)
    {
        $filas = $ventas
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
            ->values();

        $totales = [
            'es_total' => true,
            'ventas_exentas' => $filas->sum('ventas_exentas'),
            'ventas_internas_gravadas' => $filas->sum('ventas_internas_gravadas'),
            'exportaciones' => $filas->sum('exportaciones'),
            'total_ventas_diarias_propias' => $filas->sum('total_ventas_diarias_propias'),
            'ventas_a_cuenta_de_terceros' => $filas->sum('ventas_a_cuenta_de_terceros'),
        ];

        return $filas->push($totales);
    }
}
