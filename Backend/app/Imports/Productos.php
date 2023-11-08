<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Inventario;
use App\Models\Compras\Proveedores\Proveedor;
use App\ModelsInventario\ProductoProveedor;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Productos implements ToModel, WithHeadingRow, WithValidation
{
    // use Importable;

    private $numRows = 0;
    
    public function model(array $row)
    {
        ++$this->numRows;

        $id_categoria = Categoria::where('nombre', $row['categoria'])
                                ->where('id_empresa', Auth::user()->id_empresa)
                                ->pluck('id')->first();
        

        if(!$id_categoria){
            $categoria = new Categoria();
            $categoria->nombre = $row['categoria'];
            $categoria->descripcion = $row['categoria'];
            $categoria->enable = true;
            $categoria->id_empresa = Auth::user()->id_empresa;
            $categoria->save();
            $id_categoria = $categoria->id;
        }

        if ($row['proveedor']) {
            $id_proveedor = Proveedor::where('nombre', $row['proveedor'])
                                    ->where('id_empresa', Auth::user()->id_empresa)
                                    ->pluck('id')->first();
            if(!$id_proveedor){
                $proveedor = new Proveedor();
                $proveedor->nombre = $row['proveedor'];
                $proveedor->enable = true;
                $proveedor->id_empresa = Auth::user()->id_empresa;
                $proveedor->save();
                $id_proveedor = $proveedor->id;
            }
        }

        $producto = new Producto();
        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        $producto->costo = $row['costo'];
        $producto->stock = $row['stock'];
        $producto->id_categoria = $id_categoria;
        $producto->codigo = $row['codigo'];
        $producto->descripcion = $row['descripcion'];
        $producto->marca = $row['marca'];
        $producto->barcode = $row['codigo_de_barra'];
        $producto->enable  = true;
        $producto->id_empresa =  Auth::user()->id_empresa;
        $producto->save();

        if (isset($id_proveedor)) {
            ProductoProveedor::create([
                'id_proveedor' => $id_proveedor,
                'id_producto' => $producto->id,
            ]);
        }

        $sucursales = Sucursal::all();

        for($i = 0; $i < $sucursales->count(); $i++){
            if (isset($row['stock']) && $i == 0) {
                $inventario = new Inventario();
                $inventario->id_producto = $producto->id;
                $inventario->id_sucursal = $sucursales[$i]->id;
                $inventario->stock = isset($row['stock']) ? $row['stock'] : 0;
                $inventario->save(); 
            }
            else{
                $inventario = new Inventario();
                $inventario->id_producto = $producto->id;
                $inventario->id_sucursal = $sucursales[$i]->id;
                $inventario->stock = 0;
                $inventario->save(); 
            }
        }


        return $producto;

    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string',
            'precio' => 'required|numeric',
            'costo' => 'required|numeric',
            'stock' => 'required|numeric',
            'categoria' => 'required|string',
            // 'codigo' => 'sometimes|string',
            // 'codigo_de_barra' => 'sometimes|string',
        ];
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
