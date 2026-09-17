<?php

namespace App\Exports\Contabilidad\Activos;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ActivosReporteExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private array $data) {}

    public function collection(): Collection
    {
        return collect($this->data['lineas'] ?? []);
    }

    public function headings(): array
    {
        return $this->data['columnas'] ?? [];
    }

    public function title(): string
    {
        return substr($this->data['titulo'] ?? 'Activos', 0, 31);
    }
}
