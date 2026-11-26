<?php

namespace App\Imports;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Empresa;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
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
    /** @var array<string, true> */
    private $duiEnImportacion = [];
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
            'nombre', 'apellido', 'nombre_comercial', 'codigo_de_cliente', 'codigo_cliente', 'dui', 'nit', 'rtn', 'direccion',
            'departamento', 'cod_departamento', 'distrito', 'cod_distrito', 'municipio', 'cod_municipio',
            'telefono', 'tipo_contribuyente', 'pais', 'documento_identidad',
            'provincia', 'estado', 'ciudad', 'n_de_documento',
        ];

        $row = $this->applyExcelRowNormalization($row, $stringKeys, $this->esElSalvador);

        if ($this->esElSalvador) {
            $row = $this->aplicarCodigosDesdeNombresUbicacion($row);

            $duiNorm = $this->normalizarDui($row['dui'] ?? '');
            if ($duiNorm === '') {
                $row['dui'] = null;
            } elseif ($this->esDuiValido($duiNorm)) {
                $row['dui'] = $this->canonicalizarDui($duiNorm);
            } else {
                $row['dui'] = null;
            }
        }

        return $row;
    }

    public function model(array $row)
    {
        if (empty($row['nombre'])) {
            return null;
        }

        ++$this->numRows;
        $fila = $this->numRows;

        $documentoIdentidad = null;
        if ($this->esElSalvador) {
            $duiValor = $row['dui'] ?? null;
            if ($duiValor !== null && $duiValor !== '') {
                $duiNormalizado = (string) $duiValor;
                $duiClave = str_replace('-', '', $duiNormalizado);

                if (isset($this->duiEnImportacion[$duiClave])) {
                    $this->errores[] = "DUI duplicado en el archivo: {$duiNormalizado} (Fila: {$fila})";
                    Log::warning("DUI duplicado en importación: {$duiNormalizado}");

                    return null;
                }

                $duiSinGuion = $duiClave;
                $existeDui = Cliente::where('id_empresa', Auth::user()->id_empresa)
                    ->where(function ($query) use ($duiNormalizado, $duiSinGuion) {
                        $query->where('dui', $duiNormalizado)
                            ->orWhere('dui', $duiSinGuion);
                    })->exists();

                if ($existeDui) {
                    $this->errores[] = "Ya existe un cliente con el DUI: {$duiNormalizado} (Fila: {$fila})";
                    Log::warning("DUI duplicado encontrado: {$duiNormalizado}");

                    return null;
                }
            }
            $documentoIdentidad = ($duiValor !== null && $duiValor !== '') ? (string) $duiValor : null;
        } else {
            $documentoIdentidad = $row['dui'] ?? $row['rtn'] ?? $row['documento_identidad'] ?? $row['n. de documento'] ?? $row['n_de_documento'] ?? null;
        }

        Log::info('Datos recibidos:', $row);

        $cliente = new Cliente();
        $cliente->nombre = $row['nombre'];
        $cliente->nombre_comercial = $row['nombre_comercial'] ?? null;
        $apellidoRaw = $row['apellido'] ?? '';
        $cliente->apellido = is_string($apellidoRaw) ? trim($apellidoRaw) : '';
        $codigoCliente = $row['codigo_de_cliente'] ?? $row['codigo_cliente'] ?? null;
        $cliente->codigo_cliente = ($codigoCliente !== null && $codigoCliente !== '') ? (string) $codigoCliente : null;

        $cliente->tipo = 'Persona';
        $cliente->tipo_contribuyente = $row['tipo_contribuyente'] ?? 'Pequeño';
        $cliente->dui = $documentoIdentidad;
        $cliente->nit = $row['rtn'] ?? $row['nit'] ?? null;
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
            if ($this->esElSalvador && $documentoIdentidad !== null && $documentoIdentidad !== '') {
                $this->duiEnImportacion[str_replace('-', '', (string) $documentoIdentidad)] = true;
            }
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
                'nombre_comercial' => 'nullable|string|max:255',
                'apellido' => 'nullable|string|max:255',
                'dui' => 'nullable|string|max:20',
                'nit' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:500',
                'departamento' => 'nullable|string|max:255',
                'cod_departamento' => ['nullable', 'required_with:departamento', 'string', 'max:50', Rule::exists('departamentos', 'cod')],
                'municipio' => 'nullable|string|max:255',
                'cod_municipio' => ['nullable', 'required_with:municipio', 'string', 'max:50', Rule::exists('municipios', 'cod')],
                'distrito' => 'nullable|string|max:255',
                'cod_distrito' => ['nullable', 'required_with:distrito', 'string', 'max:50', Rule::exists('distritos', 'cod')],
                'telefono' => 'nullable|string|max:20',
                'correo' => 'nullable|email:filter|max:255',
                'tipo_contribuyente' => 'nullable|string|max:100',
                'codigo_de_cliente' => 'nullable|string|max:60',
            ];
        }

        return [
            'nombre' => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
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
            'cod_departamento.required_with' => 'El departamento seleccionado no es válido.',
            'cod_municipio.exists' => 'El municipio seleccionado no es válido.',
            'cod_municipio.required_with' => 'El municipio seleccionado no es válido.',
            'cod_distrito.exists' => 'El distrito seleccionado no es válido.',
            'cod_distrito.required_with' => 'El distrito seleccionado no es válido.',
            'departamento.required' => 'Debe indicar el departamento.',
            'municipio.required' => 'Debe indicar el municipio.',
            'distrito.required' => 'Debe indicar el distrito.',
            'correo.email' => 'El correo debe tener un formato válido.',
        ];
    }

    /**
     * Nombre visible del Excel manda sobre cod_* (fórmulas/columnas ocultas suelen desincronizarse).
     * Si hay nombre y no hay match en catálogo MH, anula el cod_* para fallar required_with/exists.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function aplicarCodigosDesdeNombresUbicacion(array $row): array
    {
        foreach (['departamento', 'municipio', 'distrito'] as $campo) {
            if (!array_key_exists($campo, $row)) {
                continue;
            }
            if ($row[$campo] === null || (is_string($row[$campo]) && trim($row[$campo]) === '')) {
                $row[$campo] = null;
            }
        }

        $depNombre = $row['departamento'] ?? null;
        if ($depNombre !== null && $depNombre !== '') {
            $dep = $this->buscarDepartamentoPorNombre($depNombre);
            $row['cod_departamento'] = $dep ? (string) $dep->cod : null;
        }

        $codDep = $row['cod_departamento'] ?? null;
        $munNombre = $row['municipio'] ?? null;
        if ($munNombre !== null && $munNombre !== '') {
            $mun = $this->buscarMunicipioPorNombre($munNombre, $codDep);
            $row['cod_municipio'] = $mun ? (string) $mun->cod : null;
        }

        $codMun = $row['cod_municipio'] ?? null;
        $disNombre = $row['distrito'] ?? null;
        if ($disNombre !== null && $disNombre !== '') {
            $dis = $this->buscarDistritoPorNombre($disNombre, $codDep, $codMun);
            $row['cod_distrito'] = $dis ? (string) $dis->cod : null;
        }

        return $row;
    }

    private function buscarDepartamentoPorNombre($nombre): ?Departamento
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            return null;
        }
        $nombre = is_string($nombre) ? trim($nombre) : (string) $nombre;

        return Departamento::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)])->first();
    }

    private function buscarMunicipioPorNombre($nombre, $cod_departamento = null): ?Municipio
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            return null;
        }
        $nombre = is_string($nombre) ? trim($nombre) : (string) $nombre;
        $query = Municipio::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)]);
        if ($cod_departamento !== null && $cod_departamento !== '') {
            $query->where('cod_departamento', $cod_departamento);
        }

        return $query->first();
    }

    private function buscarDistritoPorNombre($nombre, $cod_departamento = null, $cod_municipio = null): ?Distrito
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            return null;
        }
        $nombre = is_string($nombre) ? trim($nombre) : (string) $nombre;
        $query = Distrito::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($nombre)]);
        if ($cod_departamento !== null && $cod_departamento !== '') {
            $query->where('cod_departamento', $cod_departamento);
        }
        if ($cod_municipio !== null && $cod_municipio !== '') {
            $query->where('cod_municipio', $cod_municipio);
        }

        return $query->first();
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }

    public function getErrores(): array
    {
        return $this->compactarErroresDui($this->errores);
    }

    public function getFallidosCount(): int
    {
        return count($this->errores);
    }

    /**
     * Agrupa DUI repetidos para que el UI muestre una lista corta.
     *
     * @param  array<int, string>  $errores
     * @return array<int, string>
     */
    private function compactarErroresDui(array $errores): array
    {
        $porDuiExiste = [];
        $porDuiArchivo = [];
        $otros = [];

        foreach ($errores as $error) {
            if (!is_string($error)) {
                continue;
            }
            if (preg_match('/^Ya existe un cliente con el DUI:\s+(\S+)\s+\(Fila:\s*(\d+)\)$/u', $error, $m)) {
                $porDuiExiste[$m[1]][] = (int) $m[2];
                continue;
            }
            if (preg_match('/^DUI duplicado en el archivo:\s+(\S+)\s+\(Fila:\s*(\d+)\)$/u', $error, $m)) {
                $porDuiArchivo[$m[1]][] = (int) $m[2];
                continue;
            }
            $otros[] = $error;
        }

        $compactos = [];
        foreach ($porDuiExiste as $dui => $filas) {
            $compactos[] = $this->formatoErrorDuiAgrupado('Ya existe un cliente con el DUI', $dui, $filas);
        }
        foreach ($porDuiArchivo as $dui => $filas) {
            $compactos[] = $this->formatoErrorDuiAgrupado('DUI duplicado en el archivo', $dui, $filas);
        }

        return array_merge($compactos, $otros);
    }

    /**
     * @param  array<int, int>  $filas
     */
    private function formatoErrorDuiAgrupado(string $prefijo, string $dui, array $filas): string
    {
        $filas = array_values(array_unique($filas));
        sort($filas);
        $n = count($filas);
        if ($n === 1) {
            return "{$prefijo}: {$dui} (Fila: {$filas[0]})";
        }
        $preview = implode(', ', array_slice($filas, 0, 5));
        if ($n > 5) {
            $preview .= ', …';
        }

        return "{$prefijo}: {$dui} ({$n} filas: {$preview})";
    }

    public function getClientesProcesados(): int
    {
        return $this->clientesProcesados;
    }

    private function normalizarDui($dui)
    {
        if ($dui === null || $dui === '') {
            return '';
        }

        $s = trim((string) $dui);
        if ($s === '') {
            return '';
        }

        // Quitar espacios, NBSP, guión normal y tipográficos (— –), guión bajo
        $s = str_replace("\xc2\xa0", '', $s);
        $s = preg_replace('/[\s\-\x{2013}\x{2014}_]+/u', '', $s);

        if ($s === '') {
            return '';
        }

        // Solo dígitos (celdas numéricas de Excel ya vienen sin guión)
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) === 9 && ctype_digit($digits)) {
            return substr($digits, 0, 8) . '-' . substr($digits, 8, 1);
        }

        return $s;
    }

    /**
     * Acepta 12345678-9 o 123456789 (9 dígitos). Devuelve siempre XXXXXXXX-Y si es válido.
     */
    private function canonicalizarDui(string $dui): string
    {
        if (preg_match('/^(\d{8})-(\d{1})$/', $dui, $m)) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/^(\d{9})$/', $dui, $m)) {
            return substr($m[1], 0, 8) . '-' . substr($m[1], 8, 1);
        }

        return $dui;
    }

    private function esDuiValido($dui)
    {
        if ($dui === '' || $dui === null) {
            return false;
        }

        if (preg_match('/^\d{8}-\d{1}$/', $dui)) {
            return true;
        }

        return preg_match('/^\d{9}$/', $dui) && ctype_digit($dui);
    }
}
