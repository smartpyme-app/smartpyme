<?php

namespace App\Exports\Suscripciones;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FlujoCajaMensualBloqueSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    /** @var array<int, array<int, string|int|float>> */
    private $filas;

    /** @var string */
    private $tituloHoja;

    public function __construct(array $filas, string $tituloHoja)
    {
        $this->filas = $filas;
        $this->tituloHoja = $tituloHoja;
    }

    public function array(): array
    {
        return $this->filas;
    }

    public function title(): string
    {
        return $this->tituloHoja;
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [];
        $maxRow = count($this->filas);
        for ($r = 1; $r <= $maxRow; $r++) {
            $primera = $this->filas[$r - 1][0] ?? '';
            if (is_string($primera) && (
                str_contains($primera, 'BLOQUE') ||
                str_contains($primera, 'Desglose') ||
                str_contains($primera, 'Método') ||
                str_contains($primera, 'MONTO TOTAL')
            )) {
                $styles[$r] = [
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE8E8E8'],
                    ],
                ];
            }
        }

        return $styles;
    }
}
