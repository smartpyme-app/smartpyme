<?php

namespace App\Imports;

use App\Models\Compras\Proveedores\Proveedor;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Auth;

class Proveedores implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        $proveedor = new Proveedor();
        $proveedor->nombre = $row['nombre'];
        $proveedor->apellido = $row['apellido'];
        $proveedor->ncr  = $row['ncr'];
        $proveedor->giro  = $row['giro'];
        $proveedor->tipo   = $row['tipo'] ? $row['tipo'] : 'Persona';
        $proveedor->tipo_contribuyente   = $row['tipo_contribuyente'];
        $proveedor->dui   = $row['dui'];
        $proveedor->nit   = $row['nit'];
        $proveedor->nombre_empresa   = $row['nombre_empresa'];
        $proveedor->direccion = $row['direccion'];
        $proveedor->municipio = $row['municipio'];
        $proveedor->departamento  = $row['departamento'];
        $proveedor->telefono  = $row['telefono'];
        $proveedor->correo    = $row['correo'];

        $proveedor->id_usuario = Auth::user()->id;
        $proveedor->id_empresa = Auth::user()->id_empresa;
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
