<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientesExtranjeros implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;
        // $formato_documento = [
        //     'DUI' => '13',
        //     'NIT' => '36',
        //     'Pasaporte' => '03',
        //     'Carnet de residente' => '02',
        //     'Otro' => '37',
        // ];

        // Log::info($formato_documento);
        //imprimir row
        Log::info($row);

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'] ?? '';
        $cliente->apellido = $row['apellido'] ?? '';
        $cliente->tipo   = 'Extranjero';
        $cliente->tipo_contribuyente   = 'Pequeño';
        $cliente->dui   = $row['numero_identificacion'] ?? '';
         $cliente->tipo_documento = $row['tipo_documento'] ?? '';
      //  $cliente->tipo_documento = $formato_documento[$row['tipo_documento']];
        $cliente->direccion = $row['direccion'] ?? '';
        $cliente->telefono  = $row['telefono'] ?? '';
        $cliente->correo    = $row['correo'] ?? '';
        $cliente->pais      = $row['pais'] ?? '';
        $cliente->giro      = $row['giro'] ?? '';
        //tipo_persona
        $cliente->tipo_persona = $row['tipo_persona'] ?? '';
        //nombre_empresa
        $cliente->nombre_empresa = $row['nombre_empresa'] ?? '';

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
