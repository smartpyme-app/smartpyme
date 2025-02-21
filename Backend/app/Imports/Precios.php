<?php

namespace App\Imports;

use App\Models\Inventario\Precios\Precio;
use App\Models\Inventario\Precios\Usuario;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use JWTAuth;

class Precios implements ToModel, WithHeadingRow, WithValidation
{
    // use Importable;

    private $numRows = 0;
    
    public function model(array $row)
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        $producto = Producto::where('codigo', $row['codigo'])
                                ->where('id_empresa', $usuario->id_empresa)
                                ->first();

        if(!$producto){
            return null;
        }

        $precio = Precio::where('precio', $row['precio'])
                                ->where('id_producto', $producto->id)
                                ->first();
        if($precio){
            return null;
        }


        $precio = new Precio();
        $precio->precio = $row['precio'];
        $precio->id_producto =  $producto->id;
        $precio->save();

        foreach ($usuario->empresa()->first()->usuarios()->get() as $user) {
            $usuario = new Usuario;
            $usuario->id_precio =  $precio->id;
            $usuario->id_usuario =  $user->id;
            $usuario->save();
        }
        
        ++$this->numRows;

        return $producto;

    }

    public function rules(): array
    {
        return [
            'codigo' => 'required',
            'precio' => 'required|numeric',
        ];
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
