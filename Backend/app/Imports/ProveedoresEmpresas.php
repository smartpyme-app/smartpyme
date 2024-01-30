<?php

namespace App\Imports;

use App\Models\Compras\Proveedores\Proveedor;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Auth;

class ProveedoresEmpresas implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        $cliente = new Proveedor();
        $cliente->nombre_empresa   = $row['nombre_empresa'];
        $cliente->ncr  = $row['ncr'];
        $cliente->giro  = $row['giro'];
        $cliente->tipo   = 'Empresa';
        $cliente->tipo_contribuyente   = $row['tipo_contribuyente'];
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
            'nombre_empresa' => 'required',
            'ncr'        => 'required',
            'giro'        => 'required'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
