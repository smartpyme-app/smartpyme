<?php

namespace App\Imports;

use App\Models\Compras\Proveedores\Proveedor;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Auth;

class ProveedoresPersonas implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        $cliente = new Proveedor();
        $cliente->nombre = $row['nombre'];
        $cliente->apellido = $row['apellido'];
        $cliente->tipo   = 'Persona';
        $cliente->tipo_contribuyente   = 'Pequeño';
        $cliente->dui   = $row['dui'];
        $cliente->nit   = $row['nit'];
        $cliente->direccion = $row['direccion'];
        $cliente->municipio = $row['municipio'];
        $cliente->departamento  = $row['departamento'];
        $cliente->telefono  = $row['telefono'];
        $cliente->correo    = $row['correo'];

        $cliente->id_usuario = Auth::user()->id;
        $cliente->id_empresa = Auth::user()->id_empresa;
        $cliente->save();

    }

    public function rules(): array
    {
        return [
            'nombre'        => 'required|string',
            'apellido'        => 'required|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
