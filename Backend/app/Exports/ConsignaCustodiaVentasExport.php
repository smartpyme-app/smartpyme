<?php

namespace App\Exports;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaCustodiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignaCustodiaVentasExport implements FromCollection, WithHeadings, WithMapping
{
    private ?Request $request = null;

    private ?Empresa $empresa = null;

    private bool $incluirLote = false;

    public function filter(Request $request): void
    {
        $this->request = $request;
        $this->empresa = Empresa::query()->find(Auth::user()->id_empresa);
        $this->incluirLote = app(ConsignaCustodiaService::class)->incluirLoteEnExport($this->empresa);
    }

    public function headings(): array
    {
        $headings = [
            'Id cliente',
            'Fecha venta',
            'Cliente',
            'Documento',
            'Nº factura',
            'Fecha pago',
            'Producto',
            'Código',
        ];
        if ($this->incluirLote) {
            $headings[] = 'Lote';
            $headings[] = 'Vencimiento lote';
        }
        $headings[] = 'Cantidad en consigna';
        $headings[] = 'Precio unitario';
        $headings[] = 'Total línea';

        return $headings;
    }

    public function map($row): array
    {
        $fields = [
            $row['id_cliente'],
            $row['fecha'],
            $row['cliente'],
            $row['documento'],
            $row['correlativo'],
            $row['fecha_pago'],
            $row['producto'],
            $row['codigo'],
        ];
        if ($this->incluirLote) {
            $fields[] = $row['lote_numero'] ?? '';
            $fields[] = $row['lote_vencimiento'] ?? '';
        }
        $fields[] = $row['cantidad'];
        $fields[] = $row['precio'];
        $fields[] = $row['total_linea'];

        return $fields;
    }

    public function collection()
    {
        $user = Auth::user();
        if (! $this->request || ! $user?->id_empresa) {
            return collect();
        }

        return app(ConsignaCustodiaService::class)->coleccionExportVentasConsigna(
            $this->request,
            (int) $user->id_empresa,
            $this->empresa
        );
    }
}
