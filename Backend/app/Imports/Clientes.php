<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Auth;

class Clientes implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->apellido = $row['apellido'];
        $cliente->ncr  = $row['ncr'];
        $cliente->giro  = $row['giro'];
        $cliente->tipo   = $row['tipo'] ? $row['tipo'] : 'Persona';
        $cliente->tipo_contribuyente   = $row['tipo_contribuyente'];
        $cliente->dui   = $row['dui'];
        $cliente->nit   = $row['nit'];
        $cliente->nombre_empresa   = $row['nombre_empresa'];
        $cliente->empresa_telefono   = $row['telefono_empresa'];
        $cliente->empresa_direccion   = $row['direccion_empresa'];
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
            'nombre'        => 'required|string'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
