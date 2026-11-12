<?php

namespace App\Exports;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaProductosVentaListadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignasExport implements FromCollection, WithHeadings, WithMapping
{
    private ?Request $request = null;

    private ?Empresa $empresa = null;

    private bool $incluirLote = false;

    public function filter(Request $request): void
    {
        $this->request = $request;
        $this->empresa = Empresa::query()->find(Auth::user()->id_empresa);
        $this->incluirLote = app(ConsignaProductosVentaListadoService::class)
            ->agruparPorProductoYLote($this->empresa);
    }

    public function headings(): array
    {
        $headings = [
            'Producto',
            'Categoria',
            'Precio',
            'Codigo',
            'Cliente',
        ];
        if ($this->incluirLote) {
            $headings[] = 'Lote';
            $headings[] = 'Vencimiento lote';
        }
        $headings[] = 'Stock';

        return $headings;
    }

    public function map($row): array
    {
        $fields = [
            $row['nombre'],
            $row['nombre_categoria'],
            $row['precio'],
            $row['codigo'],
            $row['clientes_texto'] ?? '',
        ];
        if ($this->incluirLote) {
            $fields[] = $row['lote_numero'] ?? '';
            $fields[] = $row['lote_vencimiento'] ?? '';
        }
        $fields[] = $row['stock'];

        return $fields;
    }

    public function collection()
    {
        if (! $this->request) {
            return collect();
        }

        return app(ConsignaProductosVentaListadoService::class)->coleccion($this->request, $this->empresa);
    }
}
