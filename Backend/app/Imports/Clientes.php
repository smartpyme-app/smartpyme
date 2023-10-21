<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Clientes implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->registro  = $row['registro'];
        $cliente->giro  = $row['giro'];
        // $cliente->dui   = $row['dui'];
        $cliente->nit   = $row['nit'];
        // $cliente->fecha_nacimiento  = $row['fecha_nacimiento'];
        $cliente->direccion = $row['direccion'];
        $cliente->municipio = $row['municipio'];
        $cliente->departamento  = $row['departamento'];
        $cliente->telefono  = $row['telefono'];
        $cliente->correo    = $row['correo'];
        // $cliente->sexo  = $row['sexo'];
        // $cliente->profesion = $row['profesion'];
        // $cliente->estado_civil  = $row['estado_civil'];

        $cliente->usuario_id = 1;
        $cliente->empresa_id = 1;
        $cliente->save();

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
