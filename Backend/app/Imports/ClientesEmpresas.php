<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Empresa;
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
            $empresa = Empresa::find(FacadesAuth::user()->id_empresa);
            if ($empresa) {
                $codPais = $empresa->cod_pais;
                $pais = trim($empresa->pais ?? '');
                
                // Si tiene código de país 'SV', es El Salvador
                if ($codPais === 'SV') {
                    $this->esElSalvador = true;
                }
                // Si tiene código de país diferente a 'SV' y no es NULL, no es El Salvador
                elseif ($codPais !== null && $codPais !== 'SV') {
                    $this->esElSalvador = false;
                }
                // Si cod_pais es NULL, verificar campo pais
                else {
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
            Log::error("Error al detectar país de empresa en ClientesEmpresas: " . $e->getMessage());
            // Por defecto, asumir que es El Salvador para mantener compatibilidad
            $this->esElSalvador = true;
        }
    }

    public function model(array $row)
    {
        // Validar campos requeridos según el país
        // Para otros países, aceptar diferentes nombres de columnas
        $campoIdentificacion = null;
        if ($this->esElSalvador) {
            $campoIdentificacion = $row['ncr'] ?? null;
        } else {
            // Para otros países, aceptar ncr, numero_registro, identificacion_fiscal, n. de registro
            $campoIdentificacion = $row['ncr'] ?? $row['numero_registro'] ?? $row['identificacion_fiscal'] ?? 
                                   $row['n. de registro'] ?? $row['n_de_registro'] ?? null;
        }
        
        if (empty($row['nombre_empresa']) || empty($campoIdentificacion)) {
            return null;
        } 

        ++$this->numRows;

        // Para El Salvador: validar y normalizar NCR
        // Para otros países: usar número de registro como texto libre
        $numeroRegistro = null;
        if ($this->esElSalvador) {
            $ncrNormalizado = $this->normalizarNcr($row['ncr']);
        
            if (!empty($ncrNormalizado)) {
                $existeNcr = Cliente::where('id_empresa', FacadesAuth::user()->id_empresa)
                    ->where(function($query) use ($row, $ncrNormalizado) {
                        $query->where('ncr', $row['ncr']) // NCR original
                              ->orWhere('ncr', $ncrNormalizado); // NCR normalizado
                    })
                    ->exists();
                
                // if ($existeNcr) {
                //     throw new \Exception("Ya existe una empresa con el NCR: {$row['ncr']}");
                // }
            }
            $numeroRegistro = $ncrNormalizado ?: $row['ncr'];
        } else {
            // Para otros países, usar el campo como número de registro (texto libre)
            // Aceptar diferentes nombres de columnas
            $numeroRegistro = $row['ncr'] ?? $row['numero_registro'] ?? $row['identificacion_fiscal'] ?? 
                             $row['n. de registro'] ?? $row['n_de_registro'] ?? null;
        }

        // Buscar códigos solo si es El Salvador
        $codigos = $this->esElSalvador ? $this->buscarCodigos($row) : null;

        $cliente = new Cliente();
        $cliente->nombre_empresa = $row['nombre_empresa'];
        $cliente->ncr = $numeroRegistro;
        
        // Para El Salvador: buscar código de actividad económica
        // Para otros países: guardar giro como texto libre
        if ($this->esElSalvador && $codigos) {
            $cliente->giro = $row['giro'] ?? null;
            $cliente->cod_giro = $codigos['actividad_economica'] ? $codigos['actividad_economica']->cod : null;
        } else {
            // Para otros países, aceptar "Giro o Rubro" o "Giro" o "Rubro"
            $cliente->giro = $row['giro'] ?? $row['giro o rubro'] ?? $row['rubro'] ?? 
                            $row['actividad_economica'] ?? null;
            $cliente->cod_giro = null;
        }
        
        $cliente->tipo = 'Empresa';
        $cliente->tipo_contribuyente = $row['tipo_contribuyente'] ?? null;
        $cliente->dui = $row['dui'] ?? null;
        $cliente->nit = $row['nit'] ?? null;
        $cliente->empresa_direccion = $row['direccion'] ?? null;
        
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
        
        $cliente->empresa_telefono = $row['telefono'] ?? null;
        $cliente->telefono = $row['telefono'] ?? null;
        $cliente->correo = $row['correo'] ?? null;

        $cliente->id_usuario = FacadesAuth::user()->id;
        $cliente->id_empresa = FacadesAuth::user()->id_empresa;
        
        try {
            $cliente->save();
            return $cliente;
        } catch (\Exception $e) {
            Log::error("Error al guardar cliente empresa: " . $e->getMessage(), $row);
            return null;
        }
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
        // Para El Salvador: validaciones estrictas con códigos MH
        if ($this->esElSalvador) {
            return [
                // Campos obligatorios básicos
                'nombre_empresa' => 'required|string|max:255',
                'ncr' => 'required|max:50',
                
                // Validar giro/actividad económica
                'giro' => 'required|string',
                
                // Validar departamento
                'departamento' => 'required|string',
                
                // Validar municipio
                'municipio' => 'required|string', 
                
                // Validar distrito
                'distrito' => 'required|string',
                
                // Campos opcionales
                'tipo_contribuyente' => 'nullable|string|max:100',
                'dui' => 'nullable|string|max:20',
                'nit' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:500',
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email|max:255',
            ];
        }
        
        // Para otros países: validaciones mínimas (solo campos básicos)
        return [
            // Campos obligatorios básicos
            'nombre_empresa' => 'required|string|max:255',
            'numero_registro' => 'required|max:50', // Puede ser ncr, numero_registro o identificacion_fiscal
            
            // Giro/Rubro es opcional para otros países
            'giro' => 'nullable|string|max:255',
            
            // Campos opcionales
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|max:20',
            'direccion' => 'nullable|string|max:500',
        ];
    }

    public function customValidationMessages()
    {
        return [
            // Actividad económica
            'giro.required' => 'Debe seleccionar un giro de la lista.',
            
            // Departamento
            'departamento.required' => 'Debe seleccionar un departamento.',
            
            // Municipio
            'municipio.required' => 'Debe seleccionar un municipio.',
            
            // Distrito
            'distrito.required' => 'Debe seleccionar un distrito.',
            
            // Campos básicos
            'nombre_empresa.required' => 'El nombre de la empresa es obligatorio.',
            'ncr.required' => 'El NCR es obligatorio.',
            'correo.email' => 'El correo debe tener un formato válido.',
        ];
    }

    private function buscarCodigos(array $row)
    {
        // Solo buscar códigos si es El Salvador
        if (!$this->esElSalvador) {
            return [
                'actividad_economica' => null,
                'departamento' => null,
                'municipio' => null,
                'distrito' => null,
            ];
        }
        
        return [
            'actividad_economica' => isset($row['giro']) ? ActividadEconomica::where('nombre', $row['giro'])->first() : null,
            'departamento' => isset($row['departamento']) ? Departamento::where('nombre', $row['departamento'])->first() : null,
            'municipio' => isset($row['municipio']) ? Municipio::where('nombre', $row['municipio'])->first() : null,
            'distrito' => isset($row['distrito']) ? Distrito::where('nombre', $row['distrito'])->first() : null,
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
