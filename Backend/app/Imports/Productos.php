<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Precio;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Categorias\SubCategoria;


use App\Models\Inventario\Sucursal as ProductoSucursal;
use App\Models\Admin\Sucursal as AdminSucursal;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Productos implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        
        $producto = new Producto;
        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        
        $producto->medida = 'Unidad';
        $producto->costo = isset($row['costo']) ? $row['costo'] : 0;

        $categoria = Categoria::where('descripcion', $row['id_categoria'])->first();
        $subcategoria = SubCategoria::where('descripcion', $row['id_subcategoria'])->first();

        $producto->categoria_id = isset($categoria->id) ? $categoria->id : null;
        $producto->subcategoria_id = isset($subcategoria->id) ? $subcategoria->id : null;
        $producto->codigo = $row['codigo'];
        $producto->tipo_impuesto = 'Gravada';
        $producto->impuesto = 0.13;
        $producto->tipo = 'Producto';
        $producto->empresa_id = 1;
        $producto->save();

        if ($row['precio2']) {
            Precio::create(['precio' => $row['precio2'], 'producto_id' => $producto->id]);
        }

        if ($row['precio3']) {
            Precio::create(['precio' => $row['precio3'], 'producto_id' => $producto->id]);
        }

        if ($row['precio4']) {
            Precio::create(['precio' => $row['precio4'], 'producto_id' => $producto->id]);
        }

        // Sucursal 1
            $producto_sucursal = new ProductoSucursal();
            $producto_sucursal->producto_id = $producto->id;
            $producto_sucursal->activo = true;
            $producto_sucursal->sucursal_id = 1;
            $producto_sucursal->save();

                $inventario = new Inventario;
                $inventario->producto_id = $producto->id;
                $inventario->stock = isset($row['stock1']) ? $row['stock1'] : 0;
                $inventario->stock_min = 10;
                $inventario->stock_max = 100;
                $inventario->nota = '';
                $inventario->bodega_id = 1;
                $inventario->save();

                $ajuste = new Ajuste;
                $ajuste->producto_id  = $producto->id;
                $ajuste->bodega_id    = 1;
                $ajuste->stock_inicial= 0;
                $ajuste->stock_final  = isset($row['stock1']) ? $row['stock1'] : 0;;
                $ajuste->usuario_id   = 1;
                $ajuste->nota         = 'Inventario Inicial';
                $ajuste->save();
                $valorAjuste = $ajuste->stock_final - $ajuste->stock_inicial;
                $inventario->kardex($ajuste, $valorAjuste);

        // Sucursal 2
            $producto_sucursal = new ProductoSucursal();
            $producto_sucursal->producto_id = $producto->id;
            $producto_sucursal->activo = true;
            $producto_sucursal->sucursal_id = 2;
            $producto_sucursal->save();

                $inventario = new Inventario;
                $inventario->producto_id = $producto->id;
                $inventario->stock = isset($row['stock2']) ? $row['stock2'] : 0;
                $inventario->stock_min = 10;
                $inventario->stock_max = 100;
                $inventario->nota = '';
                $inventario->bodega_id = 2;
                $inventario->save();

                $ajuste = new Ajuste;
                $ajuste->producto_id  = $producto->id;
                $ajuste->bodega_id    = 2;
                $ajuste->stock_inicial= 0;
                $ajuste->stock_final  = isset($row['stock2']) ? $row['stock2'] : 0;;
                $ajuste->usuario_id   = 1;
                $ajuste->nota         = 'Inventario Inicial';
                $ajuste->save();
                $valorAjuste = $ajuste->stock_final - $ajuste->stock_inicial;
                $inventario->kardex($ajuste, $valorAjuste);

        
        return $producto;


    }

    public function rules(): array
    {
        return [
            'nombre'        => 'required|string',
            'precio'        => 'required|numeric',
            'costo'         => 'sometimes|numeric|nullable',
            'id_categoria'     => 'sometimes|string|nullable',
            'id_subcategoria'     => 'sometimes|string|nullable',
            'stock1'        => 'sometimes|numeric|nullable',
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
