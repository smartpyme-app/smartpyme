<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Admin\Sucursal;
use Carbon\Carbon;

class InventarioAFechaExport implements FromCollection, WithHeadings, WithMapping
{
    private $sucursales;
    private $fecha;
    private $id_empresa;

    public function __construct()
    {
        $this->fecha = '2024-12-31';
        $this->id_empresa = 324;

        // Preload all required sucursales for optimization
        $this->sucursales = Sucursal::where('id_empresa', $this->id_empresa)->get();
    }

    public function headings(): array
    {
        $headings = ['Nombre', 'Categoría'];

        foreach ($this->sucursales as $sucursal) {
            $headings[] = $sucursal->nombre;
        }

        return $headings;
    }

    public function map($producto): array
    {
        $fields = [
            $producto->nombre,
            $producto->nombre_categoria,
        ];

        // Fetch inventory data for all sucursales in one loop
        foreach ($this->sucursales as $sucursal) {
            $inventario = $producto->inventarios->firstWhere('id_sucursal', $sucursal->id);

            if ($inventario) {
                $ultimoKardex = $inventario->kardexs()
                    ->whereMonth('fecha', Carbon::parse($this->fecha)->month)
                    ->whereYear('fecha', Carbon::parse($this->fecha)->year)
                    ->orderBy('fecha', 'desc')
                    ->first();

                $fields[] = $ultimoKardex ? $ultimoKardex->total_cantidad : $inventario->stock;
            } else {
                $fields[] = 0;
            }
        }

        return $fields;
    }

    public function collection()
    {
        $fechaInicio = Carbon::create($this->fecha);

        return Producto::with(['inventarios.kardexs'])
            ->where('id_empresa', $this->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            ->get();
    }
}
