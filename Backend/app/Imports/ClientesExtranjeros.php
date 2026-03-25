<?php

namespace App\Imports;

use App\Models\MH\ActividadEconomica;
use App\Models\MH\Pais;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ClientesExtranjeros implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private $numRows = 0;
    private $filasVaciasConsecutivas = 0;
    private $formatosDocumento = [
        'DUI' => '13',
        'NIT' => '36',
        'Pasaporte' => '03',
        'Carnet de residente' => '02',
        'Otro' => '37',
    ];


    public function model(array $row)
    {
        // Solo procesar si hay datos principales
        if (empty($row['nombre']) || empty($row['apellido']) || empty($row['numero_identificacion'])) {
            return null;
        }
    
        // Validar documento único
        $this->validarDocumentoUnico($row);
    
        // Buscar actividad económica
        $actividadEconomica = $this->buscarActividadEconomica($row['giro']);
    
        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->apellido = $row['apellido'];
        $cliente->tipo = 'Extranjero';
        $cliente->tipo_contribuyente = 'Pequeño';
        $cliente->tipo_persona = $row['tipo_persona'];
        $cliente->tipo_documento = $this->formatosDocumento[$row['tipo_documento']] ?? '37';
        $cliente->dui = $row['numero_identificacion'];
        $cliente->giro = $row['giro'];
        $cliente->cod_giro = $actividadEconomica->cod ?? null;
        $cliente->nombre_empresa = $row['nombre_empresa'] ?? null;
        $cliente->pais = $row['pais'];
        $cliente->cod_pais = $this->getCodPais($row['pais']);
        $cliente->direccion = $row['direccion'];
        $cliente->telefono = $row['telefono'];
        $cliente->correo = $row['correo'];
    
        $cliente->id_usuario = Auth::user()->id;
        $cliente->id_empresa = Auth::user()->id_empresa;
        $cliente->save();
    
        ++$this->numRows; // Solo cuenta registros guardados exitosamente
    
        return $cliente;
    }

    private function validarDocumentoUnico(array $row)
    {
        $numeroIdentificacion = $row['numero_identificacion'];
        $tipoDocumento = $this->formatosDocumento[$row['tipo_documento']] ?? '37';
        
        if (!empty($numeroIdentificacion)) {
            $existeDocumento = Cliente::where('id_empresa', Auth::user()->id_empresa)
                ->where('dui', $numeroIdentificacion)
                ->where('tipo_documento', $tipoDocumento)
                ->exists();
            
            if ($existeDocumento) {
                throw new \Exception("Ya existe un cliente con {$row['tipo_documento']}: {$numeroIdentificacion}");
            }
        }
    }

    private function buscarActividadEconomica($giro)
    {
        $actividadEconomica = ActividadEconomica::where('nombre', $giro)->first();
        return $actividadEconomica->cod ?? null;
    }

    private function getCodPais($pais)
    {
        $pais = Pais::where('nombre', $pais)->first();
        return $pais->cod;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            
            if (empty($data['nombre']) || empty($data['apellido'])) {
                return;
            }
            
            if (empty($data['tipo_documento']) || !array_key_exists($data['tipo_documento'], $this->formatosDocumento)) {
                $validator->errors()->add('tipo_documento', 'Tipo de documento inválido. Debe ser: DUI, NIT, Pasaporte, Carnet de residente u Otro.');
            }
            
            if (empty($data['tipo_persona']) || !in_array($data['tipo_persona'], ['Persona Natural', 'Persona Jurídica'])) {
                $validator->errors()->add('tipo_persona', 'Tipo de persona debe ser: Persona Natural o Persona Jurídica.');
            }
            
            if (empty($data['giro']) || !ActividadEconomica::where('nombre', $data['giro'])->exists()) {
                $validator->errors()->add('giro', 'El giro es requerido y debe existir.');
            }
            
            if (empty($data['numero_identificacion'])) {
                $validator->errors()->add('numero_identificacion', 'El número de identificación es requerido.');
            }
        });
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
