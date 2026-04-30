<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use JWTAuth;

class Servicios implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, WithCalculatedFormulas, WithMultipleSheets
{
    private $numRows = 0;

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function prepareForValidation(array $row, $index): array
    {
        foreach (['nombre', 'categoria', 'codigo', 'descripcion'] as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                $row[$key] = trim($row[$key]);
            }
        }

        if (empty($row['precio'] ?? null) && isset($row['precio_sin_iva']) && $row['precio_sin_iva'] !== '') {
            $row['precio'] = $row['precio_sin_iva'];
        }

        foreach (['precio', 'costo'] as $key) {
            if (!isset($row[$key]) || $row[$key] === '') {
                continue;
            }
            $normalized = $this->normalizeDecimalForValidation($row[$key]);
            if ($normalized !== null) {
                $row[$key] = $normalized;
            }
        }

        if (($row['codigo'] ?? '') === '') {
            $row['codigo'] = null;
        }

        return $row;
    }

    private function normalizeDecimalForValidation($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        $s = str_replace(' ', '', trim((string) $value));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',') && !str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, '.') && str_contains($s, ',')) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        }

        return is_numeric($s) ? $s : null;
    }

    public function isEmptyRow(array $row): bool
    {
        $nombre = isset($row['nombre']) ? trim((string) $row['nombre']) : '';

        return $nombre === '';
    }

    public function model(array $row)
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        $nombreCategoria = $row['categoria'] ?? null;
        if ($nombreCategoria === null || $nombreCategoria === '') {
            return null;
        }

        $id_categoria = Categoria::where('nombre', $nombreCategoria)
            ->where('id_empresa', $usuario->id_empresa)
            ->value('id');

        if (!$id_categoria) {
            $categoria = new Categoria();
            $categoria->nombre = $nombreCategoria;
            $categoria->descripcion = $nombreCategoria;
            $categoria->enable = true;
            $categoria->id_empresa = $usuario->id_empresa;
            $categoria->save();
            $id_categoria = $categoria->id;
        }

        $codigo = $row['codigo'] ?? null;
        if ($codigo === '') {
            $codigo = null;
        }

        $producto = Producto::where('nombre', $row['nombre'])
            ->where('tipo', 'Servicio')
            ->when($codigo !== null, function ($query) use ($codigo) {
                return $query->where('codigo', $codigo);
            })
            ->where('id_empresa', $usuario->id_empresa)
            ->first();

        if (!$producto) {
            $producto = new Producto();
        }

        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        $producto->costo = $row['costo'];
        $producto->id_categoria = $id_categoria;
        $producto->codigo = $codigo;
        $producto->descripcion = $row['descripcion'] ?? null;
        $producto->tipo = 'Servicio';
        $producto->enable = true;
        $producto->id_empresa = $usuario->id_empresa;
        $producto->save();

        ++$this->numRows;

        return $producto;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string',
            'precio' => 'required|numeric',
            'costo' => 'required|numeric',
            'categoria' => 'required|string',
            'codigo' => 'nullable|string',
            'descripcion' => 'nullable|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
