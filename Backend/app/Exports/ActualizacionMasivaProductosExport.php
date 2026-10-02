<?php

namespace App\Exports;

use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use App\Support\Inventario\ActualizacionMasivaProductos;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActualizacionMasivaProductosExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /** @var array<int, string> */
    private array $headingsRow;

    /** @var array<int, array<int, mixed>> */
    private array $rows;

    public function __construct()
    {
        $idEmpresa = Auth::user()?->id_empresa;
        $bodegas = $idEmpresa
            ? Bodega::where('id_empresa', $idEmpresa)
                ->where('activo', true)
                ->with('sucursal')
                ->orderBy('id_sucursal')
                ->orderBy('id')
                ->get()
            : collect();

        $columnasBodega = [];
        foreach ($bodegas as $bodega) {
            $sucursal = $bodega->sucursal ? (string) $bodega->sucursal->nombre : 'Sin sucursal';
            $columnasBodega[] = [
                'id' => (int) $bodega->id,
                'minimo' => ActualizacionMasivaProductos::encabezadoStock('stock_minimo', (int) $bodega->id, (string) $bodega->nombre, $sucursal),
                'maximo' => ActualizacionMasivaProductos::encabezadoStock('stock_maximo', (int) $bodega->id, (string) $bodega->nombre, $sucursal),
            ];
        }

        $this->headingsRow = array_merge(
            ['id_creacion', 'codigo', 'costo', 'precio_sin_iva', 'entra_a_menu', 'genera_comanda'],
            array_merge(...(array_map(fn ($c) => [$c['minimo'], $c['maximo']], $columnasBodega) ?: [[]]))
        );

        $productos = $idEmpresa
            ? Producto::where('id_empresa', $idEmpresa)
                ->where('tipo', '!=', 'Servicio')
                ->with('inventarios')
                ->orderBy('id')
                ->get()
            : collect();

        $this->rows = [];
        foreach ($productos as $producto) {
            $porBodega = $producto->inventarios->keyBy('id_bodega');
            $fila = [
                $producto->id,
                $producto->codigo,
                $producto->costo,
                $producto->precio,
                $producto->mostrar_en_restaurante ? 'Si' : 'No',
                $producto->genera_comanda ? 'Si' : 'No',
            ];
            foreach ($columnasBodega as $columna) {
                $inventario = $porBodega->get($columna['id']);
                $fila[] = $inventario ? $inventario->stock_minimo : '';
                $fila[] = $inventario ? $inventario->stock_maximo : '';
            }
            $this->rows[] = $fila;
        }

        if ($this->rows === []) {
            $this->rows[] = array_fill(0, count($this->headingsRow), '');
        }
    }

    public function headings(): array
    {
        return $this->headingsRow;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Actualizar productos';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA'],
                ],
            ],
        ];
    }
}
