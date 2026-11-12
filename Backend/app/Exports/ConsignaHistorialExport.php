<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ConsignaHistorialExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private array $payload,
        private string $alcance
    ) {}

    public function sheets(): array
    {
        return [
            new ConsignaHistorialResumenSheet($this->payload, $this->alcance),
            new ConsignaHistorialMovimientosSheet($this->payload),
        ];
    }
}
