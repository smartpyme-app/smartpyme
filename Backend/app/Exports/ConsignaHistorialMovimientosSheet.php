<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConsignaHistorialMovimientosSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private array $payload
    ) {}

    public function title(): string
    {
        return 'Historial movimientos';
    }

    public function headings(): array
    {
        $cols = ['Fecha', 'Tipo', 'Código', 'Producto', 'Cantidad'];
        if (! empty($this->payload['incluir_lote'])) {
            $cols[] = 'Lote';
            $cols[] = 'Vence';
        }
        $cols[] = 'Documento';
        $cols[] = 'Notas';

        return $cols;
    }

    public function map($row): array
    {
        $out = [
            $row['fecha'] ?? '',
            $row['tipo'] ?? '',
            $row['codigo'] ?? '',
            $row['producto'] ?? '',
            $row['cantidad'] ?? 0,
        ];
        if (! empty($this->payload['incluir_lote'])) {
            $out[] = $row['lote'] ?? '';
            $out[] = $row['vence'] ?? '';
        }
        $out[] = $row['documento'] ?? '';
        $out[] = $row['notas'] ?? '';

        return $out;
    }

    public function collection(): Collection
    {
        return collect($this->payload['movimientos'] ?? []);
    }
}
