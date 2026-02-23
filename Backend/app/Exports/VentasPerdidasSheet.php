<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasPerdidasSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $ventasPorCliente;

    public function __construct(array $ventasPorCliente)
    {
        $this->ventasPorCliente = $ventasPorCliente;
    }

    public function headings(): array
    {
        return [
            'ID Empresa',
            'Empresa',
            'ID Sucursal',
            'Sucursal',
            'ID Cliente',
            'Cliente',
            'ID Venta',
            'Fecha',
            'Correlativo',
            'Total',
            'Estado',
            'Forma Pago',
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->ventasPorCliente as $grupo) {
            foreach ($grupo['ventas'] as $item) {
                $v = $item['venta'];
                $rows[] = [
                    'id_empresa' => $v->id_empresa,
                    'nombre_empresa' => $v->nombre_empresa ?? '',
                    'id_sucursal' => $v->id_sucursal,
                    'nombre_sucursal' => $v->nombre_sucursal ?? '',
                    'id_cliente' => $grupo['id_cliente'],
                    'nombre_cliente' => $grupo['nombre_cliente'],
                    'id_venta' => $v->id,
                    'fecha' => $v->fecha,
                    'correlativo' => $v->correlativo,
                    'total' => round($v->total, 2),
                    'estado' => $v->estado ?? '',
                    'forma_pago' => $v->forma_pago ?? '',
                ];
            }
        }

        usort($rows, function ($a, $b) {
            if ($a['id_empresa'] !== $b['id_empresa']) {
                return $a['id_empresa'] - $b['id_empresa'];
            }
            if ($a['id_sucursal'] !== $b['id_sucursal']) {
                return $a['id_sucursal'] - $b['id_sucursal'];
            }
            return ($a['id_cliente'] ?? 0) - ($b['id_cliente'] ?? 0);
        });

        return array_map(function ($r) {
            return [
                $r['id_empresa'],
                $r['nombre_empresa'],
                $r['id_sucursal'],
                $r['nombre_sucursal'],
                $r['id_cliente'],
                $r['nombre_cliente'],
                $r['id_venta'],
                $r['fecha'],
                $r['correlativo'],
                $r['total'],
                $r['estado'],
                $r['forma_pago'],
            ];
        }, $rows);
    }

    public function title(): string
    {
        return 'Ventas perdidas por cliente';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
