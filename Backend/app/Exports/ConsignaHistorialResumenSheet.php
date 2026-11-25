<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConsignaHistorialResumenSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private array $payload,
        private string $alcance,
        private ?string $tituloHoja = null,
    ) {}

    public function title(): string
    {
        return $this->tituloHoja ?? 'Productos en consigna';
    }

    public function headings(): array
    {
        $cols = ['Código', 'Producto'];
        if (! empty($this->payload['incluir_lote'])) {
            $cols[] = 'Lote';
            $cols[] = 'Vence';
        }
        $cols[] = 'Bodega';
        $cols[] = $this->alcance === 'compras' ? 'Recibidas' : 'Enviadas';
        $cols[] = $this->alcance === 'compras' ? 'Vendidas' : 'Facturadas';
        $cols[] = 'Devueltas';
        $cols[] = 'Disponible';
        $cols[] = 'Precio unitario';

        return $cols;
    }

    public function map($row): array
    {
        $out = [
            $row['codigo'] ?? '',
            $row['producto'] ?? '',
        ];
        if (! empty($this->payload['incluir_lote'])) {
            $out[] = $row['lote'] ?? '';
            $out[] = $row['vence'] ?? '';
        }
        $out[] = $row['bodega'] ?? '';
        $out[] = $row['enviadas'] ?? 0;
        $out[] = $row['facturadas'] ?? 0;
        $out[] = $row['devueltas'] ?? 0;
        $out[] = $row['disponible'] ?? 0;
        $out[] = $row['precio_unitario'] ?? 0;

        return $out;
    }

    public function collection(): Collection
    {
        return collect($this->payload['resumen'] ?? []);
    }
}
