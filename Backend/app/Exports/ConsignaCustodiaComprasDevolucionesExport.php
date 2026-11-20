<?php

namespace App\Exports;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaCustodiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignaCustodiaComprasDevolucionesExport implements FromCollection, WithHeadings, WithMapping
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
            'Id proveedor',
            'Fecha devolución',
            'Proveedor',
            'Tipo documento',
            'Referencia',
            'Compra consigna origen',
            'Producto',
            'Código',
        ];
        if ($this->incluirLote) {
            $headings[] = 'Lote';
            $headings[] = 'Vencimiento lote';
        }
        $headings[] = 'Cantidad';
        $headings[] = 'Costo unitario';
        $headings[] = 'Total línea';

        return $headings;
    }

    public function map($row): array
    {
        $out = [
            $row['id_proveedor'],
            $row['fecha'],
            $row['proveedor'],
            $row['tipo_documento'],
            $row['referencia'],
            $row['compra_origen'],
            $row['producto'],
            $row['codigo'],
        ];
        if ($this->incluirLote) {
            $out[] = $row['lote_numero'] ?? '';
            $out[] = $row['lote_vencimiento'] ?? '';
        }
        $out[] = $row['cantidad'];
        $out[] = $row['costo'];
        $out[] = $row['total_linea'];

        return $out;
    }

    public function collection()
    {
        if (! $this->request) {
            return collect();
        }

        return app(ConsignaCustodiaService::class)->coleccionExportDevolucionesCompraConsigna(
            $this->request,
            (int) Auth::user()->id_empresa,
            $this->empresa
        );
    }
}
