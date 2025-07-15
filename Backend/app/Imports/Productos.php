<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
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

        if ($row['proveedor_nombre']) {
            $id_proveedor = Proveedor::where(function ($query) use ($row) {
                    $query->where('nombre', $row['proveedor_nombre'])
                          ->where('apellido', $row['proveedor_apellido']);
                })
                ->orWhere('nombre_empresa', $row['proveedor_nombre'])
                ->where('id_empresa', $usuario->id_empresa)
                ->pluck('id')
                ->first();

            if(!$id_proveedor){
                $proveedor = new Proveedor();
                $proveedor->nombre = $row['proveedor_apellido'] ? $row['proveedor_nombre'] : null;
                $proveedor->apellido = $row['proveedor_apellido'];
                $proveedor->tipo = $row['proveedor_apellido'] ? 'Persona' : 'Empresa';
                $proveedor->nombre_empresa = $proveedor->tipo == 'Empresa' ? $row['proveedor_nombre'] : NULL;
                $proveedor->enable = true;
                $proveedor->id_empresa = $usuario->id_empresa;
                $proveedor->id_usuario = $usuario->id;
                $proveedor->save();
                $id_proveedor = $proveedor->id;
            }
        }

        $producto = Producto::where('nombre', $row['nombre'])
                                ->when(isset($row['codigo']) && !empty($row['codigo']), function ($query) use ($row) {
                                    return $query->where('codigo', $row['codigo']);
                                })
                                ->where('id_empresa', $usuario->id_empresa)
                                ->first();

        if(!$producto){
            $producto = new Producto();
            ++$this->numRows;
        }

        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        $producto->costo = $row['costo'];
        $producto->costo_promedio = $row['costo'];
        $producto->stock = $row['sucursal_1_stock'];
        $producto->id_categoria = $id_categoria;
        $producto->codigo = $row['codigo'];
        $producto->descripcion = $row['descripcion'];
        $producto->marca = $row['marca'];
        $producto->medida = $row['unidad_medida'];
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


        $bodegas = Bodega::all();

        if (isset($bodegas[0]) && isset($row['sucursal_1_stock'])) {
            
            $inventario = Inventario::where('id_producto', $producto->id)->where('id_bodega', $bodegas[0]->id)->first();

            if (!$inventario) {
                $inventario = new Inventario();
            }

            $inventario->id_producto = $producto->id;
            $inventario->id_bodega = $bodegas[0]->id;
            $inventario->stock = isset($row['sucursal_1_stock']) ? $row['sucursal_1_stock'] : 0;
            $inventario->save(); 


            $ajuste = Ajuste::create([
                'concepto' => 'Ajuste inicial',
                'id_producto' => $producto->id,
                'id_bodega' => $bodegas[0]->id,
                'stock_actual' => 0,
                'stock_real' => $inventario->stock,
                'ajuste' => $inventario->stock,
                'estado' => 'Confirmado',
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);
            
            if (!$inventario) {
                $inventario->kardex($ajuste, $ajuste->ajuste);
            }

        }

        if (isset($bodegas[1]) && isset($row['sucursal_2_stock'])) {
            
            $inventario = Inventario::where('id_producto', $producto->id)->where('id_bodega', $bodegas[1]->id)->first();

            if (!$inventario) {
                $inventario = new Inventario();
            }

            $inventario->id_producto = $producto->id;
            $inventario->id_bodega = $bodegas[1]->id;
            $inventario->stock = isset($row['sucursal_2_stock']) ? $row['sucursal_2_stock'] : 0;
            $inventario->save();


            $ajuste = Ajuste::create([
                'concepto' => 'Ajuste inicial',
                'id_producto' => $producto->id,
                'id_bodega' => $bodegas[1]->id,
                'stock_actual' => 0,
                'stock_real' => $inventario->stock,
                'ajuste' => $inventario->stock,
                'estado' => 'Confirmado',
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            if ($inventario) {
                $inventario->kardex($ajuste, $ajuste->ajuste);
            }
        }

        if (isset($bodegas[2]) && isset($row['sucursal_3_stock'])) {
            
            $inventario = Inventario::where('id_producto', $producto->id)->where('id_bodega', $bodegas[2]->id)->first();

            if (!$inventario) {
                $inventario = new Inventario();
            }

            $inventario->id_producto = $producto->id;
            $inventario->id_bodega = $bodegas[2]->id;
            $inventario->stock = isset($row['sucursal_3_stock']) ? $row['sucursal_3_stock'] : 0;
            $inventario->save();


            $ajuste = Ajuste::create([
                'concepto' => 'Ajuste inicial',
                'id_producto' => $producto->id,
                'id_bodega' => $bodegas[2]->id,
                'stock_actual' => 0,
                'stock_real' => $inventario->stock,
                'ajuste' => $inventario->stock,
                'estado' => 'Confirmado',
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            if ($inventario) {
                $inventario->kardex($ajuste, $ajuste->ajuste);
            }
        }

        if ($bodegas->count() > 3) {
           for ($i=2; $i < $bodegas->count(); $i++) { 
               
               $inventario = Inventario::where('id_producto', $producto->id)->where('id_bodega', $bodegas[$i]->id)->first();

               if (!$inventario) {
                    $inventario = new Inventario();
               }

               $inventario->id_producto = $producto->id;
               $inventario->id_bodega = $bodegas[$i]->id;
               $inventario->stock = 0;
               $inventario->save();


               $ajuste = Ajuste::create([
                   'concepto' => 'Ajuste inicial',
                   'id_producto' => $producto->id,
                   'id_bodega' => $bodegas[$i]->id,
                   'stock_actual' => 0,
                   'stock_real' => $inventario->stock,
                   'ajuste' => $inventario->stock,
                   'estado' => 'Confirmado',
                   'id_empresa' => $usuario->id_empresa,
                   'id_usuario' => $usuario->id,
               ]);

               if ($inventario) {
                   $inventario->kardex($ajuste, $ajuste->ajuste);
               }
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
