<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Inventario;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Inventario\Proveedor as ProductoProveedor;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use JWTAuth;

class Productos implements ToModel, WithHeadingRow, WithValidation
{
    // use Importable;

    private $numRows = 0;
    
    public function model(array $row)
    {
        ++$this->numRows;
        $usuario = JWTAuth::parseToken()->authenticate();

        $id_categoria = Categoria::where('nombre', $row['categoria'])
                                ->where('id_empresa', $usuario->id_empresa)
                                ->pluck('id')->first();
        

        if(!$id_categoria){
            $categoria = new Categoria();
            $categoria->nombre = $row['categoria'];
            $categoria->descripcion = $row['categoria'];
            $categoria->enable = true;
            $categoria->id_empresa = $usuario->id_empresa;
            $categoria->save();
            $id_categoria = $categoria->id;
        }

        if ($row['proveedor_nombre'] && $row['proveedor_apellido']) {
            $id_proveedor = Proveedor::where('nombre', $row['proveedor_nombre'])
                                    ->where('apellido', $row['proveedor_apellido'])
                                    ->where('id_empresa', $usuario->id_empresa)
                                    ->pluck('id')->first();
            if(!$id_proveedor){
                $proveedor = new Proveedor();
                $proveedor->nombre = $row['proveedor_nombre'];
                $proveedor->apellido = $row['proveedor_apellido'];
                $proveedor->enable = true;
                $proveedor->id_empresa = $usuario->id_empresa;
                $proveedor->id_usuario = $usuario->id;
                $proveedor->save();
                $id_proveedor = $proveedor->id;
            }
        }

        $producto = new Producto();
        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        $producto->costo = $row['costo'];
        $producto->stock = $row['sucursal_1_stock'];
        $producto->id_categoria = $id_categoria;
        $producto->codigo = $row['codigo'];
        $producto->descripcion = $row['descripcion'];
        $producto->marca = $row['marca'];
        $producto->barcode = $row['codigo_de_barra'];
        $producto->enable  = true;
        $producto->id_empresa =  $usuario->id_empresa;
        $producto->save();

        if (isset($id_proveedor)) {
            ProductoProveedor::create([
                'id_proveedor' => $id_proveedor,
                'id_producto' => $producto->id,
            ]);
        }

        $sucursales = Sucursal::all();

        if (isset($sucursales[0]) && isset($row['sucursal_1_stock'])) {
            $inventario = new Inventario();
            $inventario->id_producto = $producto->id;
            $inventario->id_sucursal = $sucursales[0]->id;
            $inventario->stock = isset($row['sucursal_1_stock']) ? $row['sucursal_1_stock'] : 0;
            $inventario->save(); 
        }

        if (isset($sucursales[1]) && isset($row['sucursal_2_stock'])) {
            $inventario = new Inventario();
            $inventario->id_producto = $producto->id;
            $inventario->id_sucursal = $sucursales[1]->id;
            $inventario->stock = isset($row['sucursal_2_stock']) ? $row['sucursal_2_stock'] : 0;
            $inventario->save(); 
        }

        if ($sucursales->count() > 2) {
           for ($i=2; $i < $sucursales->count(); $i++) { 
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
            'sucursal_1_stock' => 'required|numeric',
            'categoria' => 'required|string',
            'proveedor_apellido' => 'required_with:proveedor_nombre',
            // 'codigo_de_barra' => 'sometimes|string',
        ];
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
