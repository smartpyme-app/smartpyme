<?php

namespace App\Exports;

use App\Models\Compras\Compra;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignasComprasExport implements FromCollection, WithHeadings, WithMapping
{
    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Proveedor',
            'Documento',
            'Referencia',
            'Fecha pago',
            'Bodega',
            'Sucursal',
            'Producto',
            'Código',
            'Cantidad',
            'Costo',
            'Total línea',
            'Total compra',
        ];
    }

    public function map($row): array
    {
        return [
            $row['fecha'],
            $row['proveedor'],
            $row['tipo_documento'],
            $row['referencia'],
            $row['fecha_pago'],
            $row['bodega'],
            $row['sucursal'],
            $row['producto'],
            $row['codigo'],
            $row['cantidad'],
            $row['costo'],
            $row['total_linea'],
            $row['total_compra'],
        ];
    }

    public function collection()
    {
        $compras = Compra::query()
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->with(['detalles.producto', 'bodega', 'sucursal'])
            ->orderByDesc('fecha')
            ->get();

        $rows = collect();
        foreach ($compras as $compra) {
            foreach ($compra->detalles as $detalle) {
                $rows->push([
                    'fecha' => $compra->fecha,
                    'proveedor' => $compra->nombre_proveedor,
                    'tipo_documento' => $compra->tipo_documento,
                    'referencia' => $compra->referencia,
                    'fecha_pago' => $compra->fecha_pago,
                    'bodega' => $compra->bodega?->nombre ?? '',
                    'sucursal' => $compra->sucursal?->nombre ?? '',
                    'producto' => $detalle->producto?->nombre,
                    'codigo' => $detalle->producto?->codigo,
                    'cantidad' => $detalle->cantidad,
                    'costo' => $detalle->costo,
                    'total_linea' => $detalle->total,
                    'total_compra' => $compra->total,
                ]);
            }
        }

        return $rows;
    }
}
