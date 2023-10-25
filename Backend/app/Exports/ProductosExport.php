<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Producto;

class ProductosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $texto;
    private $id_categoria;
    private $id_proveedor;
    private $id_sucursal;

    public function filter(Request $request)
    {
        $this->texto = $request->texto;
        $this->id_categoria = $request->id_categoria;
        $this->id_proveedor = $request->id_proveedor;
        $this->id_sucursal = $request->id_sucursal;
    }

    public function headings():array{
       return[
            'Nombre',
            'Precio',
            'Costo',
            'Stock',
            'Categoria',
            'Marca',
            'Proveedor',
            'Codigo',
            'Codigo_de_barra',
        ];
    }

    public function map($row): array{
           $fields = [
              $row->nombre,
              $row->precio,
              $row->costo,
              $this->id_sucursal ? $row->inventarios()->where('id_sucursal', $this->id_sucursal)->pluck('stock')->first() : $row->inventarios()->sum('stock'),
              $row->categoria,
              $row->marca,
              $row->proveedores()->count() ? $row->proveedores()->first()->nombre_proveedor : '',
              $row->codigo,
              $row->barcode,
         ];
        return $fields;
    }

    public function collection()
    {
        $id_proveedor = $this->id_proveedor;
        $id_categoria = $this->id_categoria;
        $texto = $this->texto;
        $id_sucursal = $this->id_sucursal;

        return Producto::when($id_categoria, function($q) use ($id_categoria){
                          return $q->where('id_categoria', $id_categoria);
                        })
                        ->when($texto, function($q) use ($texto){
                          return $q->where('nombre', 'like', '%'. $texto . '%');
                        })
                        ->when($id_sucursal, function($q) use ($id_sucursal){
                            $q->whereHas('inventarios', function($q) use ($id_sucursal){
                                return $q->where("id_sucursal", $id_sucursal);
                            });
                        })
                        ->when($id_proveedor, function($q) use ($id_proveedor){
                            $q->whereHas('proveedores', function($q) use ($id_proveedor){
                                return $q->where("id_proveedor", $id_proveedor);
                            });
                        })->orderBy('nombre', 'asc')->get();
    }
}
