<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ClientesPersonas implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private $numRows = 0;

    public function model(array $row)
    {
        // Solo procesar si hay datos
        if (empty($row['nombre']) || empty($row['apellido'])) {
            return null;
        }

        $duiNormalizado = $this->normalizarDui($row['dui']);
    
        // Verificar DUI único ANTES de crear el cliente
        if (!empty($duiNormalizado)) {
            $duiSinGuion = str_replace('-', '', $duiNormalizado);
            $existeDui = Cliente::where('id_empresa', Auth::user()->id_empresa)
                ->where(function($query) use ($duiNormalizado, $duiSinGuion) {
                    $query->where('dui', $duiNormalizado)
                        ->orWhere('dui', $duiSinGuion);
                })->exists();
            
            if ($existeDui) {
                throw new \Exception("Ya existe un cliente con el DUI: {$duiNormalizado}");
            }
        }

        ++$this->numRows;

        Log::info("Datos recibidos:", $row);

        $codigos = $this->buscarCodigos($row);

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->apellido = $row['apellido'];
        $cliente->tipo = 'Persona';
        $cliente->tipo_contribuyente = 'Pequeño';
        $cliente->dui = $duiNormalizado;
        $cliente->nit = $row['nit'];
        $cliente->direccion = $row['direccion'];
        $cliente->departamento = $row['departamento'];
        $cliente->cod_departamento = $codigos['departamento'] ? $codigos['departamento']->cod : null;
        $cliente->municipio = $row['municipio'];
        $cliente->cod_municipio = $codigos['municipio'] ? $codigos['municipio']->cod : null;
        $cliente->distrito = $row['distrito'];
        $cliente->cod_distrito = $codigos['distrito'] ? $codigos['distrito']->cod : null;
        $cliente->telefono = $row['telefono'];
        $cliente->correo = $row['correo'];

        $cliente->id_usuario = Auth::user()->id;
        $cliente->id_empresa = Auth::user()->id_empresa;
        $cliente->save();

        return $cliente;
    }

    private function buscarCodigos(array $row)
    {
        return [
            'departamento' => Departamento::where('nombre', $row['departamento'])->first(),
            'municipio' => Municipio::where('nombre', $row['municipio'])->first(),
            'distrito' => Distrito::where('nombre', $row['distrito'])->first(),
        ];
    }

    public function rules(): array
    {
        return [];
    }
    
    public function withValidator($validator)
    {
       $validator->after(function ($validator) {
           $data = $validator->getData();
           
           // Solo validar si hay datos
           if (empty($data['nombre']) || empty($data['apellido'])) {
               return;
           }
           
           // Validaciones geográficas
           if (empty($data['departamento']) || !Departamento::where('nombre', $data['departamento'])->exists()) {
               $validator->errors()->add('departamento', 'El departamento es requerido y debe existir.');
           }
           
           if (empty($data['municipio']) || !Municipio::where('nombre', $data['municipio'])->exists()) {
               $validator->errors()->add('municipio', 'El municipio es requerido y debe existir.');
           }
           
           if (empty($data['distrito']) || !Distrito::where('nombre', $data['distrito'])->exists()) {
               $validator->errors()->add('distrito', 'El distrito es requerido y debe existir.');
           }
       });
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }

    private function normalizarDui($dui)
    {
        // Quitar espacios y guiones
        $dui = str_replace([' ', '-'], '', $dui);
        
        // Agregar guión si tiene 9 dígitos
        if (strlen($dui) == 9 && is_numeric($dui)) {
            return substr($dui, 0, 8) . '-' . substr($dui, 8, 1);
        }
        
        return $dui;
    }
}