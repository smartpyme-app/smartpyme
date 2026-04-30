<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Empresa;
use App\Imports\Concerns\NormalizesClienteExcelRow;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Solo se importa la primera hoja; el libro puede incluir catálogos para fórmulas (cod_*).
 */
class ClientesPersonas implements ToModel, WithHeadingRow, WithValidation, WithCalculatedFormulas, SkipsEmptyRows, WithMultipleSheets
{
    use NormalizesClienteExcelRow;

    private $numRows = 0;
    private $errores = [];
    private $clientesProcesados = 0;
    private $esElSalvador = false;

    public function __construct()
    {
        try {
            $empresa = Empresa::find(Auth::user()->id_empresa);
            if ($empresa) {
                $codPais = $empresa->cod_pais;
                $pais = trim($empresa->pais ?? '');

                if ($codPais === 'SV') {
                    $this->esElSalvador = true;
                } elseif ($codPais !== null && $codPais !== 'SV') {
                    $this->esElSalvador = false;
                } else {
                    if (strtolower($pais) === 'el salvador') {
                        $this->esElSalvador = true;
                    } elseif (empty($pais)) {
                        $this->esElSalvador = true;
                    } else {
                        $this->esElSalvador = false;
                    }
                }
            } else {
                $this->esElSalvador = true;
            }
        } catch (\Exception $e) {
            Log::error('Error al detectar país de empresa en ClientesPersonas: ' . $e->getMessage());
            $this->esElSalvador = true;
        }
    }

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function prepareForValidation(array $row, $index): array
    {
        $stringKeys = [
            'nombre', 'apellido', 'codigo_de_cliente', 'codigo_cliente', 'dui', 'nit', 'direccion',
            'departamento', 'cod_departamento', 'distrito', 'cod_distrito', 'municipio', 'cod_municipio',
            'telefono', 'tipo_contribuyente', 'pais', 'documento_identidad',
            'provincia', 'estado', 'ciudad', 'n_de_documento',
        ];

        return $this->applyExcelRowNormalization($row, $stringKeys, $this->esElSalvador);
    }

    public function model(array $row)
    {
        if (empty($row['nombre']) || empty($row['apellido'])) {
            return null;
        }

        $documentoIdentidad = null;
        if ($this->esElSalvador) {
            $duiNormalizado = $this->normalizarDui($row['dui'] ?? '');

            if (!empty($duiNormalizado) && !$this->esDuiValido($duiNormalizado)) {
                $this->errores[] = "DUI con formato inválido: '{$row['dui']}' (Fila: " . ($this->numRows + 1) . ") - Formato esperado: 12345678-9";
                Log::warning("DUI con formato inválido: {$row['dui']}");

                return null;
            }

            if (!empty($duiNormalizado)) {
                $duiSinGuion = str_replace('-', '', $duiNormalizado);
                $existeDui = Cliente::where('id_empresa', Auth::user()->id_empresa)
                    ->where(function ($query) use ($duiNormalizado, $duiSinGuion) {
                        $query->where('dui', $duiNormalizado)
                            ->orWhere('dui', $duiSinGuion);
                    })->exists();

                if ($existeDui) {
                    $this->errores[] = "Ya existe un cliente con el DUI: {$duiNormalizado} (Fila: " . ($this->numRows + 1) . ")";
                    Log::warning("DUI duplicado encontrado: {$duiNormalizado}");

                    return null;
                }
            }
            $documentoIdentidad = $duiNormalizado;
        } else {
            $documentoIdentidad = $row['dui'] ?? $row['documento_identidad'] ?? $row['n. de documento'] ?? $row['n_de_documento'] ?? null;
        }

        ++$this->numRows;

        Log::info('Datos recibidos:', $row);

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->apellido = $row['apellido'];
        $codigoCliente = $row['codigo_de_cliente'] ?? $row['codigo_cliente'] ?? null;
        $cliente->codigo_cliente = ($codigoCliente !== null && $codigoCliente !== '') ? (string) $codigoCliente : null;

        $cliente->tipo = 'Persona';
        $cliente->tipo_contribuyente = $row['tipo_contribuyente'] ?? 'Pequeño';
        $cliente->dui = $documentoIdentidad;
        $cliente->nit = $row['nit'] ?? null;
        $cliente->direccion = $row['direccion'] ?? null;

        if ($this->esElSalvador) {
            $cliente->departamento = $row['departamento'] ?? null;
            $cliente->cod_departamento = $row['cod_departamento'] ?? null;
            $cliente->municipio = $row['municipio'] ?? null;
            $cliente->cod_municipio = $row['cod_municipio'] ?? null;
            $cliente->distrito = $row['distrito'] ?? null;
            $cliente->cod_distrito = $row['cod_distrito'] ?? null;
        } else {
            $cliente->departamento = $row['departamento'] ?? $row['provincia'] ?? $row['estado'] ?? null;
            $cliente->cod_departamento = null;
            $cliente->municipio = $row['municipio'] ?? $row['ciudad'] ?? null;
            $cliente->cod_municipio = null;
            $cliente->distrito = $row['distrito'] ?? null;
            $cliente->cod_distrito = null;
        }

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
            Log::error('Error al guardar cliente: ' . $e->getMessage(), $row);

            return null;
        }
    }

    public function rules(): array
    {
        if ($this->esElSalvador) {
            return [
                'nombre' => 'required|string|max:255',
                'apellido' => 'required|string|max:255',
                'dui' => 'required|string|max:20',
                'nit' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:500',
                'departamento' => 'required|string',
                'cod_departamento' => ['required', Rule::exists('departamentos', 'cod')],
                'municipio' => 'required|string',
                'cod_municipio' => ['required', Rule::exists('municipios', 'cod')],
                'distrito' => 'required|string',
                'cod_distrito' => ['required', Rule::exists('distritos', 'cod')],
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email:filter|max:255',
                'tipo_contribuyente' => 'nullable|string|max:100',
                'codigo_de_cliente' => 'nullable|string|max:60',
            ];
        }

        return [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'dui' => 'nullable|string|max:50',
            'documento_identidad' => 'nullable|string|max:50',
            'n. de documento' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:500',
            'correo' => 'nullable|email:filter|max:255',
            'telefono' => 'nullable|max:20',
            'codigo_de_cliente' => 'nullable|string|max:60',
            'codigo_cliente' => 'nullable|string|max:60',
            'n_de_documento' => 'nullable|string|max:50',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'cod_departamento.exists' => 'El departamento seleccionado no es válido.',
            'cod_municipio.exists' => 'El municipio seleccionado no es válido.',
            'cod_distrito.exists' => 'El distrito seleccionado no es válido.',
            'departamento.required' => 'Debe indicar el departamento.',
            'municipio.required' => 'Debe indicar el municipio.',
            'distrito.required' => 'Debe indicar el distrito.',
            'dui.required' => 'El DUI es obligatorio.',
            'correo.email' => 'El correo debe tener un formato válido.',
        ];
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
        if (empty($dui) || $dui === null) {
            return '';
        }

        $dui = str_replace([' ', '-'], '', $dui);

        if (!is_numeric($dui)) {
            return $dui;
        }

        if (strlen($dui) == 9 && is_numeric($dui)) {
            return substr($dui, 0, 8) . '-' . substr($dui, 8, 1);
        }

        return $dui;
    }

    private function esDuiValido($dui)
    {
        return preg_match('/^\d{8}-\d{1}$/', $dui);
    }
}
