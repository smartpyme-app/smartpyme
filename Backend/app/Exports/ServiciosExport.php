<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Producto as Servicio;

class ServiciosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{
       return[
            'Nombre',
            'Categoria',
            'Costo',
            'Precio sin IVA',
            'Ganancia',
            'Precio con IVA',
            'Estado',
            // 'Etiquetas',
            'Descripcion',
        ];
    }

    public function map($row): array{
           $fields = [
              $row->nombre,
              $row->nombre_categoria,
              number_format($row->costo, 2),
              number_format($row->precio, 2),
              number_format($row->precio - $row->costo, 2),
              number_format($row->precio + ($row->precio * ($row->empresa()->pluck('iva')->first() ? $row->empresa()->pluck('iva')->first() / 100 : 0)), 2),
              $row->enable ? 'Activo' : 'Inactivo',
              // $etiquetas,
              $row->descripcion,
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Servicio::with('inventarios')
                                ->when($request->id_categoria, function($query) use ($request){
                                    return $query->where('id_categoria', $request->id_categoria);
                                })
                                ->when($request->id_sucursal, function($q) use ($request){
                                    $q->whereHas('inventarios', function($q) use ($request){
                                        return $q->where('id_sucursal', $request->id_sucursal);
                                    });
                                })
                                ->when($request->id_proveedor, function($q) use ($request){
                                    $q->whereHas('proveedores', function($q) use ($request){
                                        return $q->where('id_proveedor', $request->id_proveedor);
                                    });
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('codigo', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('barcode', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('etiquetas', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('marca', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('descripcion', 'like' ,"%" . $request->buscador . "%");
                                })
                                ->where('tipo', 'Servicio')
                                ->whereNotIn('id_categoria', [1,2])
                                ->orderBy('enable', 'desc')
                                ->orderBy($request->orden, $request->direccion)
                    ->get();
    }
}
