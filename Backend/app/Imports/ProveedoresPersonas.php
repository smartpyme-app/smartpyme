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
 * Sin WithMultipleSheets, Maatwebsite valida todas las hojas con las mismas reglas y los errores
 * en «fila 2» suelen venir de catálogos u otras pestañas.
 */
class ProveedoresPersonas implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, WithCalculatedFormulas, WithMultipleSheets
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
            'nombre', 'apellido', 'dui', 'nit', 'rtn', 'n_de_identificacion',
            'direccion', 'municipio', 'departamento', 'telefono', 'correo',
            'banco', 'tipo_cuenta', 'numero_cuenta', 'titular_cuenta', 'forma_pago',
        ];

        $row = $this->applyExcelRowNormalization($row, $stringKeys, false);

        // Honduras: "N. de Identificación" → slug n_de_identificacion
        if ((!isset($row['dui']) || trim((string) ($row['dui'] ?? '')) === '')
            && !empty($row['n_de_identificacion'])) {
            $row['dui'] = $row['n_de_identificacion'];
        }

        return $row;
    }

    public function isEmptyRow(array $row): bool
    {
        $nombre = isset($row['nombre']) ? trim((string) $row['nombre']) : '';
        $apellido = isset($row['apellido']) ? trim((string) $row['apellido']) : '';

        return $nombre === '' && $apellido === '';
    }

    public function model(array $row)
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $proveedor = new Proveedor();
        $proveedor->nombre = $row['nombre'];
        $proveedor->apellido = $row['apellido'];
        $proveedor->tipo = 'Persona';
        $proveedor->tipo_contribuyente = 'Pequeño';
        $proveedor->dui = $row['dui'] ?? $row['n_de_identificacion'] ?? null;
        $proveedor->nit = $row['nit'] ?? $row['rtn'] ?? null;
        $proveedor->direccion = $row['direccion'] ?? null;
        $proveedor->municipio = $row['municipio'] ?? null;
        $proveedor->departamento = $row['departamento'] ?? null;
        $proveedor->telefono = $row['telefono'] ?? null;
        $proveedor->correo = $row['correo'] ?? null;
        $proveedor->banco = $row['banco'] ?? null;
        $proveedor->tipo_cuenta = $row['tipo_cuenta'] ?? null;
        $proveedor->numero_cuenta = $row['numero_cuenta'] ?? null;
        $proveedor->titular_cuenta = $row['titular_cuenta'] ?? null;
        $proveedor->forma_pago = $row['forma_pago'] ?? null;

        $proveedor->id_usuario = $user->id;
        $proveedor->id_empresa = $user->id_empresa;
        $proveedor->save();

        ++$this->numRows;

        return $proveedor;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string',
            'apellido' => 'required|string',
            'dui' => 'nullable|string',
            'n_de_identificacion' => 'nullable|string',
            'nit' => 'nullable|string',
            'rtn' => 'nullable|string',
            'direccion' => 'nullable|string',
            'municipio' => 'nullable|string',
            'departamento' => 'nullable|string',
            'telefono' => 'nullable|string',
            'correo' => 'nullable|string',
            ...\App\Http\Requests\Compras\Proveedores\StoreProveedorRequest::reglasDatosBancarios(),
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
