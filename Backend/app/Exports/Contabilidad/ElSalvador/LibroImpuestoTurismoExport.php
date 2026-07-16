<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;

class LibroImpuestoTurismoExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public $request;
    private float $total = 0.0;
    private int $filas = 0;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    private function tieneFacturacionElectronica(): bool
    {
        $empresa = Auth::user()->empresa()->first();

        return $empresa && $empresa->facturacion_electronica === true;
    }

    private function filtrarVentasPorFacturacionElectronica($ventas)
    {
        if (!$this->tieneFacturacionElectronica()) {
            return $ventas;
        }

        $ventasSinSello = $ventas->filter(function ($venta) {
            return empty($venta->sello_mh);
        });

        if ($ventasSinSello->isNotEmpty()) {
            Log::warning('Se excluyeron ventas sin sello al exportar impuesto de turismo', [
                'ventas' => $ventasSinSello->pluck('id'),
            ]);
        }

        return $ventas->reject(function ($venta) {
            return empty($venta->sello_mh);
        });
    }

    public function collection()
    {
        $request = $this->request;

        $ventas = Venta::query()
            ->with(['cliente', 'documento', 'impuestos.impuesto'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($query) => $query->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->whereHas('impuestos', function ($query) use ($request) {
                $query->where('monto', '>', 0)
                    ->whereHas('impuesto', function ($impuestoQuery) use ($request) {
                        $impuestoQuery->where('porcentaje', 5)
                            ->where(function ($codigoQuery) {
                                $codigoQuery->whereNull('codigo_mh')
                                    ->orWhere('codigo_mh', '!=', '20');
                            });

                        if ($request->filled('id_impuesto')) {
                            $impuestoQuery->where('impuestos.id', $request->id_impuesto);
                        }
                    });
            })
            ->orderBy('fecha')
            ->orderBy('correlativo')
            ->get();

        return $this->filtrarVentasPorFacturacionElectronica($ventas);
    }

    public function headings(): array
    {
        return ['FECHA', 'DOCUMENTO', 'CLIENTE', 'BASE', 'MONTO 5%'];
    }

    public function map($venta): array
    {
        $idImpuesto = $this->request && $this->request->filled('id_impuesto')
            ? (string) $this->request->id_impuesto
            : null;

        $montoTurismo = (float) $venta->impuestos
            ->filter(function ($ventaImpuesto) use ($idImpuesto) {
                $impuesto = $ventaImpuesto->impuesto;

                return (float) $ventaImpuesto->monto > 0
                    && (float) optional($impuesto)->porcentaje === 5.0
                    && (string) optional($impuesto)->codigo_mh !== '20'
                    && ($idImpuesto === null || (string) $ventaImpuesto->id_impuesto === $idImpuesto);
            })
            ->sum('monto');

        $this->total += $montoTurismo;
        $this->filas++;

        return [
            $venta->fecha,
            $venta->numero_control ?: trim((string) $venta->correlativo),
            $venta->nombre_cliente,
            (float) $venta->sub_total,
            $montoTurismo,
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);
                $event->sheet->setCellValue('A1', 'IMPUESTO DE TURISMO 5%');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A3', 'NRC: ' . Auth::user()->empresa()->pluck('ncr')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')));
                $event->sheet->setCellValue('E4', 'Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
            AfterSheet::class => function (AfterSheet $event) {
                $filaTotal = $this->filas + 6;
                $event->sheet->setCellValue('D' . $filaTotal, 'TOTAL PERÍODO');
                $event->sheet->setCellValue('E' . $filaTotal, $this->total);
            },
        ];
    }
}
