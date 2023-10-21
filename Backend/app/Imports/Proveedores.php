<?php

namespace App\Imports;

use App\Models\Compras\Proveedores\Proveedor;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Proveedores implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        $proveedor = new Proveedor();
        $proveedor->nombre = $row['nombre'];
        $proveedor->registro  = $row['registro'];
        $proveedor->giro  = $row['giro'];
        // $proveedor->dui   = $row['dui'];
        $proveedor->nit   = $row['nit'];
        // $proveedor->fecha_nacimiento  = $row['fecha_nacimiento'];
        $proveedor->direccion = $row['direccion'];
        $proveedor->municipio = $row['municipio'];
        $proveedor->departamento  = $row['departamento'];
        $proveedor->telefono  = $row['telefono'];
        $proveedor->correo    = $row['correo'];
        // $proveedor->sexo  = $row['sexo'];
        // $proveedor->profesion = $row['profesion'];
        // $proveedor->estado_civil  = $row['estado_civil'];

        $proveedor->usuario_id = 1;
        $proveedor->empresa_id = 1;
        $proveedor->save();

    }

    public function rules(): array
    {
        return [
            'nombre'        => 'required|string'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
