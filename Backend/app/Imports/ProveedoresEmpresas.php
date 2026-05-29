<?php

namespace App\Imports;

use App\Imports\Concerns\NormalizesClienteExcelRow;
use App\Models\Compras\Proveedores\Proveedor;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Solo la primera pestaña del libro (índice 0).
 * Sin WithMultipleSheets, Maatwebsite valida todas las hojas (p. ej. catálogos) con las mismas reglas
 * y los errores aparecen como «fila 2» aunque la hoja de datos esté correcta.
 */
class ProveedoresEmpresas implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, WithCalculatedFormulas, WithMultipleSheets
{
    use NormalizesClienteExcelRow;

    private $numRows = 0;

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function prepareForValidation(array $row, $index): array
    {
        $stringKeys = [
            'nombre_empresa', 'ncr', 'giro', 'tipo_contribuyente', 'dui', 'nit',
            'direccion', 'municipio', 'departamento', 'telefono', 'correo',
        ];

        return $this->applyExcelRowNormalization($row, $stringKeys, false);
    }

    public function isEmptyRow(array $row): bool
    {
        $nombre = isset($row['nombre_empresa']) ? trim((string) $row['nombre_empresa']) : '';

        return $nombre === '';
    }

    public function model(array $row)
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $proveedor = new Proveedor();
        $proveedor->nombre_empresa = $row['nombre_empresa'];
        $proveedor->ncr = $row['ncr'];
        $proveedor->giro = $row['giro'];
        $proveedor->tipo = 'Empresa';
        $proveedor->tipo_contribuyente = $row['tipo_contribuyente'] ?? null;
        $proveedor->dui = $row['dui'] ?? null;
        $proveedor->nit = $row['nit'] ?? null;
        $proveedor->direccion = $row['direccion'] ?? null;
        $proveedor->municipio = $row['municipio'] ?? null;
        $proveedor->departamento = $row['departamento'] ?? null;
        $proveedor->telefono = $row['telefono'] ?? null;
        $proveedor->correo = $row['correo'] ?? null;

        $proveedor->id_usuario = $user->id;
        $proveedor->id_empresa = $user->id_empresa;
        $proveedor->save();

        ++$this->numRows;

        return $proveedor;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa' => 'required|string',
            'ncr' => 'required|string',
            'giro' => 'required|string',
            'tipo_contribuyente' => 'nullable|string',
            'dui' => 'nullable|string',
            'nit' => 'nullable|string',
            'direccion' => 'nullable|string',
            'municipio' => 'nullable|string',
            'departamento' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correo' => 'nullable|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
