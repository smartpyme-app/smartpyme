<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientesPerdidosSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $clientesPerdidos;

    public function __construct(array $clientesPerdidos)
    {
        $this->clientesPerdidos = $clientesPerdidos;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Apellido',
            'Nombre Empresa',
            'NIT',
            'DUI',
            'Teléfono',
            'Correo',
            'Dirección',
            'ID Empresa',
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->clientesPerdidos as $c) {
            $rows[] = [
                $c->id,
                $c->nombre ?? '',
                $c->apellido ?? '',
                $c->nombre_empresa ?? '',
                $c->nit ?? '',
                $c->dui ?? '',
                $c->telefono ?? '',
                $c->correo ?? '',
                $c->direccion ?? '',
                $c->id_empresa ?? '',
            ];
        }
        return $rows;
    }

    public function title(): string
    {
        return 'Clientes perdidos';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
