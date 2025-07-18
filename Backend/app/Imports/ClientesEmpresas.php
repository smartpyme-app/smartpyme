<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\MH\ActividadEconomica;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ClientesEmpresas implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private $numRows = 0;

    public function model(array $row)
    {

        if (empty($row['nombre_empresa']) || empty($row['ncr'])) {
            return null;
        } 

        ++$this->numRows;

        $ncrNormalizado = $this->normalizarNcr($row['ncr']);
    
        if (!empty($ncrNormalizado)) {
            $existeNcr = Cliente::where('id_empresa', FacadesAuth::user()->id_empresa)
                ->where(function($query) use ($row, $ncrNormalizado) {
                    $query->where('ncr', $row['ncr']) // NCR original
                          ->orWhere('ncr', $ncrNormalizado); // NCR normalizado
                })
                ->exists();
            
            if ($existeNcr) {
                throw new \Exception("Ya existe una empresa con el NCR: {$row['ncr']}");
            }
        }

        $codigos = $this->buscarCodigos($row);

        $cliente = new Cliente();
        $cliente->nombre_empresa   = $row['nombre_empresa'];
        $cliente->ncr  = $row['ncr'];
        $cliente->giro  = $row['giro'];
        $cliente->cod_giro = $codigos['actividad_economica'] ? $codigos['actividad_economica']->cod : null;
        $cliente->tipo   = 'Empresa';
        $cliente->tipo_contribuyente   = $row['tipo_contribuyente'];
        $cliente->dui   = $row['dui'];
        $cliente->nit   = $row['nit'];
        $cliente->empresa_direccion = $row['direccion'];
        $cliente->departamento  = $row['departamento'];
        $cliente->cod_departamento = $codigos['departamento'] ? $codigos['departamento']->cod : null;
        $cliente->municipio = $row['municipio'];
        $cliente->cod_municipio = $codigos['municipio'] ? $codigos['municipio']->cod : null;
        $cliente->distrito = $row['distrito'];
        $cliente->cod_distrito = $codigos['distrito'] ? $codigos['distrito']->cod : null;
        $cliente->empresa_telefono  = $row['telefono'];
        $cliente->telefono  = $row['telefono'];
        $cliente->correo    = $row['correo'];

        $cliente->id_usuario = FacadesAuth::user()->id;
        $cliente->id_empresa = FacadesAuth::user()->id_empresa;
        // $cliente->save();

        return $cliente;
    }

    private function normalizarNcr($ncr)
    {
        $ncr = preg_replace('/[^0-9]/', '', $ncr);
        
        if (strlen($ncr) == 14 && is_numeric($ncr)) {
            return $ncr;
        }
        
        return $ncr;
    }

    public function rules(): array
    {

        return [];
        return [
            // Campos obligatorios básicos
            'nombre_empresa' => 'required|string|max:255',
            'ncr' => 'required|max:50',
            
            // Validar giro/actividad económica
            'giro' => 'required|string',
            'cod_giro' => [
                'required',
                Rule::exists('actividades_economicas', 'cod')
            ],
            
            // Validar departamento
            'departamento' => 'required|string',
            'cod_departamento' => [
                'required',
                Rule::exists('departamentos', 'cod')
            ],
            
            // Validar municipio
            'municipio' => 'required|string', 
            'cod_municipio' => [
                'required',
                Rule::exists('municipios', 'cod')
            ],
            
            // Validar distrito
            'distrito' => 'required|string',
            'cod_distrito' => [
                'required',
                Rule::exists('distritos', 'cod')
            ],
            
            // Campos opcionales
            'tipo_contribuyente' => 'nullable|string|max:100',
            'dui' => 'nullable|string|max:20',
            'nit' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:255',
        ];
    }

    public function customValidationMessages()
    {
        return [
            // Actividad económica
            'cod_giro.exists' => 'El giro seleccionado no es válido.',
            'giro.required' => 'Debe seleccionar un giro de la lista.',
            
            // Departamento
            'cod_departamento.exists' => 'El departamento seleccionado no es válido.',
            'departamento.required' => 'Debe seleccionar un departamento.',
            
            // Municipio
            'cod_municipio.exists' => 'El municipio seleccionado no es válido.',
            'municipio.required' => 'Debe seleccionar un municipio.',
            
            // Distrito
            'cod_distrito.exists' => 'El distrito seleccionado no es válido.',
            'distrito.required' => 'Debe seleccionar un distrito.',
            
            // Campos básicos
            'nombre_empresa.required' => 'El nombre de la empresa es obligatorio.',
            'ncr.required' => 'El NCR es obligatorio.',
            'correo.email' => 'El correo debe tener un formato válido.',
        ];
    }

    private function buscarCodigos(array $row)
    {
    return [
        'actividad_economica' => ActividadEconomica::where('nombre', $row['giro'])->first(),
        'departamento' => Departamento::where('nombre', $row['departamento'])->first(),
        'municipio' => Municipio::where('nombre', $row['municipio'])->first(),
        'distrito' => Distrito::where('nombre', $row['distrito'])->first(),
    ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}