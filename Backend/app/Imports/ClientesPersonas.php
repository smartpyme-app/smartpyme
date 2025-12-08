<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Empresa;
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
    private $errores = [];
    private $clientesProcesados = 0;
    private $esElSalvador = false;

    /**
     * Constructor: Detecta si la empresa es de El Salvador
     * Lógica de retrocompatibilidad:
     * - Si cod_pais === 'SV' → es El Salvador
     * - Si cod_pais es NULL y pais === 'El Salvador' → es El Salvador
     * - Si cod_pais es NULL y pais es NULL/vacío → es El Salvador (por defecto, retrocompatibilidad)
     * - En cualquier otro caso → no es El Salvador
     */
    public function __construct()
    {
        try {
            $empresa = Empresa::find(Auth::user()->id_empresa);
            if ($empresa) {
                // Si tiene código de país 'SV', es El Salvador
                if ($empresa->cod_pais === 'SV') {
                    $this->esElSalvador = true;
                }
                // Si tiene código de país diferente a 'SV' y no es NULL, no es El Salvador
                elseif ($empresa->cod_pais !== null && $empresa->cod_pais !== 'SV') {
                    $this->esElSalvador = false;
                }
                // Si cod_pais es NULL, verificar campo pais
                else {
                    $pais = trim($empresa->pais ?? '');
                    // Si pais es 'El Salvador', es El Salvador
                    if (strtolower($pais) === 'el salvador') {
                        $this->esElSalvador = true;
                    }
                    // Si pais está vacío o es NULL, asumir El Salvador (retrocompatibilidad)
                    elseif (empty($pais)) {
                        $this->esElSalvador = true;
                    }
                    // Si pais tiene otro valor, no es El Salvador
                    else {
                        $this->esElSalvador = false;
                    }
                }
            } else {
                // Si no se encuentra la empresa, asumir El Salvador para mantener compatibilidad
                $this->esElSalvador = true;
            }
        } catch (\Exception $e) {
            Log::warning("Error al detectar país de empresa en ClientesPersonas: " . $e->getMessage());
            // Por defecto, asumir que es El Salvador para mantener compatibilidad
            $this->esElSalvador = true;
        }
    }

    public function model(array $row)
    {
        // Solo procesar si hay datos
        if (empty($row['nombre']) || empty($row['apellido'])) {
            return null;
        }

        // Para El Salvador: validar y normalizar DUI
        // Para otros países: usar documento de identidad como texto libre
        $documentoIdentidad = null;
        if ($this->esElSalvador) {
            $duiNormalizado = $this->normalizarDui($row['dui'] ?? '');
            
            // Validar formato de DUI solo para El Salvador
            if (!empty($duiNormalizado) && !$this->esDuiValido($duiNormalizado)) {
                $this->errores[] = "DUI con formato inválido: '{$row['dui']}' (Fila: " . ($this->numRows + 1) . ") - Formato esperado: 12345678-9";
                Log::warning("DUI con formato inválido: {$row['dui']}");
                return null; // Saltar este registro
            }
        
            // Verificar DUI único solo para El Salvador
            if (!empty($duiNormalizado)) {
                $duiSinGuion = str_replace('-', '', $duiNormalizado);
                $existeDui = Cliente::where('id_empresa', Auth::user()->id_empresa)
                    ->where(function($query) use ($duiNormalizado, $duiSinGuion) {
                        $query->where('dui', $duiNormalizado)
                            ->orWhere('dui', $duiSinGuion);
                    })->exists();
                
                if ($existeDui) {
                    $this->errores[] = "Ya existe un cliente con el DUI: {$duiNormalizado} (Fila: " . ($this->numRows + 1) . ")";
                    Log::warning("DUI duplicado encontrado: {$duiNormalizado}");
                    return null; // Saltar este registro
                }
            }
            $documentoIdentidad = $duiNormalizado;
        } else {
            // Para otros países, usar el campo como documento de identidad (texto libre)
            $documentoIdentidad = $row['dui'] ?? $row['documento_identidad'] ?? null;
        }

        ++$this->numRows;

        Log::info("Datos recibidos:", $row);

        // Buscar códigos solo si es El Salvador
        $codigos = $this->esElSalvador ? $this->buscarCodigos($row) : null;

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->apellido = $row['apellido'];
        $cliente->tipo = 'Persona';
        $cliente->tipo_contribuyente = $row['tipo_contribuyente'] ?? 'Pequeño';
        $cliente->dui = $documentoIdentidad;
        $cliente->nit = $row['nit'] ?? null;
        $cliente->direccion = $row['direccion'] ?? null;
        
        // Para El Salvador: usar códigos MH si están disponibles
        // Para otros países: guardar como texto libre
        if ($this->esElSalvador && $codigos) {
            $cliente->departamento = $row['departamento'] ?? null;
            $cliente->cod_departamento = $codigos['departamento'] ? $codigos['departamento']->cod : null;
            $cliente->municipio = $row['municipio'] ?? null;
            $cliente->cod_municipio = $codigos['municipio'] ? $codigos['municipio']->cod : null;
            $cliente->distrito = $row['distrito'] ?? null;
            $cliente->cod_distrito = $codigos['distrito'] ? $codigos['distrito']->cod : null;
        } else {
            // Para otros países: guardar ubicación como texto libre
            $cliente->departamento = $row['departamento'] ?? $row['provincia'] ?? $row['estado'] ?? null;
            $cliente->cod_departamento = null;
            $cliente->municipio = $row['municipio'] ?? $row['ciudad'] ?? null;
            $cliente->cod_municipio = null;
            $cliente->distrito = $row['distrito'] ?? null;
            $cliente->cod_distrito = null;
        }
        
        // Campo país para empresas no salvadoreñas
        if (!$this->esElSalvador && isset($row['pais'])) {
            $cliente->pais = $row['pais'];
        }
        
        $cliente->telefono = $row['telefono'] ?? null;
        $cliente->correo = $row['correo'] ?? null;

        $cliente->id_usuario = Auth::user()->id;
        $cliente->id_empresa = Auth::user()->id_empresa;
        
        try {
            $cliente->save();
            $this->clientesProcesados++;
            return $cliente;
        } catch (\Exception $e) {
            $this->errores[] = "Error al guardar cliente {$row['nombre']} {$row['apellido']}: " . $e->getMessage();
            Log::error("Error al guardar cliente: " . $e->getMessage(), $row);
            return null;
        }
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
           
           // Validaciones geográficas solo para El Salvador
           if ($this->esElSalvador) {
               if (empty($data['departamento']) || !Departamento::where('nombre', $data['departamento'])->exists()) {
                   $validator->errors()->add('departamento', 'El departamento es requerido y debe existir.');
               }
               
               if (empty($data['municipio']) || !Municipio::where('nombre', $data['municipio'])->exists()) {
                   $validator->errors()->add('municipio', 'El municipio es requerido y debe existir.');
               }
               
               if (empty($data['distrito']) || !Distrito::where('nombre', $data['distrito'])->exists()) {
                   $validator->errors()->add('distrito', 'El distrito es requerido y debe existir.');
               }
           }
           // Para otros países, las validaciones geográficas son opcionales
       });
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }

    public function getErrores(): array
    {
        return $this->errores;
    }

    public function getClientesProcesados(): int
    {
        return $this->clientesProcesados;
    }

    private function normalizarDui($dui)
    {
        // Si está vacío o es nulo, retornar vacío
        if (empty($dui) || $dui === null) {
            return '';
        }
        
        // Quitar espacios y guiones
        $dui = str_replace([' ', '-'], '', $dui);
        
        // Validar que solo contenga dígitos
        if (!is_numeric($dui)) {
            return $dui; // Retornar tal como está para que se detecte como inválido
        }
        
        // Agregar guión si tiene 9 dígitos
        if (strlen($dui) == 9 && is_numeric($dui)) {
            return substr($dui, 0, 8) . '-' . substr($dui, 8, 1);
        }
        
        return $dui;
    }

    private function esDuiValido($dui)
    {
        // Verificar que tenga el formato correcto: 8 dígitos + guión + 1 dígito
        return preg_match('/^\d{8}-\d{1}$/', $dui);
    }
}