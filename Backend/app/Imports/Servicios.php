<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use JWTAuth;

class Servicios implements ToModel, WithHeadingRow, WithValidation
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
        $producto->id_categoria = $id_categoria;
        $producto->codigo = $row['codigo'];
        $producto->descripcion = $row['descripcion'];
        $producto->tipo = 'Servicio';
        $producto->enable  = true;
        $producto->id_empresa =  $usuario->id_empresa;
        $producto->save();

        return $producto;

    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string',
            'precio' => 'required|numeric',
            'costo' => 'required|numeric',
            'categoria' => 'required|string',
        ];
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
