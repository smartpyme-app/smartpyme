<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;

class ProductosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */

    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    private function incluirComponenteQuimico(): bool
    {
        $user = Auth::user();
        if (!$user || !$user->id_empresa) {
            return false;
        }
        $empresa = Empresa::find($user->id_empresa);
        return $empresa && $empresa->isComponenteQuimicoHabilitado();
    }

    public function headings(): array
    {
        $headings = [
            'Nombre',
            'Categoria',
            'Codigo',
            'Codigo_de_barra',
            'Marca',
        ];
        if ($this->incluirComponenteQuimico()) {
            $headings[] = 'Componente químico';
        }
        $headings = array_merge($headings, [
            'Costo',
            'Precio sin IVA',
            'Ganancia',
            'Precio con IVA',
            'Stock',
            'Proveedor',
            'Estado',
            // 'Etiquetas',
            'Descripcion',
        ]);
        return $headings;
    }

    public function map($row): array
    {
        $etiquetas = $row->etiquetas;
        $stockSum = $row->inventarios->sum('stock');

        // Obtener la empresa y verificar si tiene shopify_store_url configurado
        $nombreProducto = $row->nombre;

        // Si la empresa tiene shopify_store_url y el producto tiene nombre_variante, concatenar
        if ($row->empresa && $row->empresa->shopify_store_url && $row->nombre_variante) {
            $nombreProducto = $row->nombre . ' ' . $row->nombre_variante;
        }

        $fields = [
            $nombreProducto,
            $row->nombre_categoria,
            $row->codigo,
            $row->barcode,
            $row->marca,
        ];
        if ($this->incluirComponenteQuimico()) {
            $fields[] = $row->componente_quimico ?? '';
        }
        $fields = array_merge($fields, [
            $row->empresa()->pluck('valor_inventario')->first() == 'promedio' ? number_format($row->costo_promedio, 2) : number_format($row->costo, 2),
            number_format($row->precio, 2),
            number_format($row->precio - $row->costo, 2),
            number_format($row->precio + ($row->precio * ($row->empresa()->pluck('iva')->first() ? $row->empresa()->pluck('iva')->first() / 100 : 0)), 2),
            $stockSum ? $stockSum : '0',
            $row->proveedores()->count() ? $row->proveedores()->first()->nombre_proveedor : '',
            $row->enable ? 'Activo' : 'Inactivo',
            // $etiquetas,
            $row->descripcion,
        ]);
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Producto::with(['inventarios' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        }, 'precios', 'empresa'])
            ->when($request->id_categoria, function ($query) use ($request) {
                return $query->where('id_categoria', $request->id_categoria);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orwhere('codigo', 'like', "%" . $request->buscador . "%")
                    ->orwhere('barcode', 'like', "%" . $request->buscador . "%")
                    ->orwhere('etiquetas', 'like', "%" . $request->buscador . "%")
                    ->orwhere('marca', 'like', "%" . $request->buscador . "%")
                    ->orwhere('descripcion', 'like', "%" . $request->buscador . "%");
            })
            ->when($request->id_proveedor, function ($q) use ($request) {
                $q->whereHas('proveedores', function ($q) use ($request) {
                    return $q->where("id_proveedor", $request->id_proveedor);
                });
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->marca, function ($query) use ($request) {
                return $query->where('marca', 'like', '%' . $request->marca . '%');
            })
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            // ->whereNotIn('id_categoria', [1,2])
            ->orderBy('enable', 'desc')
            ->orderBy($request->orden, $request->direccion)
            ->get();
    }
}
