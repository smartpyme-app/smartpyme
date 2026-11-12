<?php

namespace App\Exports;

use App\Models\Ventas\Detalle as DetalleVenta;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignaCustodiaVentasExport implements FromCollection, WithHeadings, WithMapping
{
    private ?Request $request = null;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Fecha venta',
            'Cliente',
            'Documento',
            'Nº factura',
            'Fecha pago',
            'Producto',
            'Código',
            'Cantidad en consigna',
            'Precio unitario',
            'Total línea',
        ];
    }

    public function map($row): array
    {
        return [
            $row['fecha'],
            $row['cliente'],
            $row['documento'],
            $row['correlativo'],
            $row['fecha_pago'],
            $row['producto'],
            $row['codigo'],
            $row['cantidad'],
            $row['precio'],
            $row['total_linea'],
        ];
    }

    public function collection()
    {
        $request = $this->request ?? new Request();

        $query = DetalleVenta::query()
            ->whereHas('venta', fn ($q) => $q->where('estado', 'Consigna')->where('cotizacion', 0))
            ->when($request->filled('buscador'), function ($q) use ($request) {
                $t = trim((string) $request->buscador);
                $q->where(function ($w) use ($t) {
                    $w->whereHas('venta', function ($v) use ($t) {
                        $v->where(function ($vq) use ($t) {
                            $vq->where('correlativo', 'like', '%' . $t . '%');
                            if (ctype_digit($t)) {
                                $vq->orWhere('correlativo', (int) $t);
                            }
                            $vq->orWhere('nombre_cliente', 'like', '%' . $t . '%')
                                ->orWhereHas('documento', fn ($d) => $d->where('nombre', 'like', '%' . $t . '%'));
                        });
                    })->orWhereHas('producto', function ($p) use ($t) {
                        $p->where('nombre', 'like', '%' . $t . '%')
                            ->orWhere('codigo', 'like', '%' . $t . '%');
                    });
                });
            })
            ->with(['producto:id,nombre,codigo', 'venta.documento:id,nombre']);

        return $query
            ->orderByDesc('id_venta')
            ->get()
            ->map(function (DetalleVenta $detalle) {
                $venta = $detalle->venta;
                $doc = $venta->documento?->nombre ?? $venta->nombre_documento ?? 'Documento';

                return [
                    'fecha' => $venta->fecha,
                    'cliente' => $venta->nombre_cliente,
                    'documento' => $doc,
                    'correlativo' => $venta->correlativo,
                    'fecha_pago' => $venta->fecha_pago,
                    'producto' => $detalle->producto?->nombre,
                    'codigo' => $detalle->producto?->codigo,
                    'cantidad' => $detalle->cantidad,
                    'precio' => $detalle->precio,
                    'total_linea' => $detalle->total,
                ];
            });
    }
}
