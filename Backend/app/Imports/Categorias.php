<?php

namespace App\Imports;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Categorias\SubCategoria;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class Categorias implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;


        $categoria = new Categoria();
        $categoria->nombre = $row['nombre'];
        $categoria->descripcion = $row['descripcion'];
        $categoria->empresa_id = 1;
        $categoria->save();

    }

    public function rules(): array
    {
        return [
            'nombre'        => 'required|string',
            'descripcion'        => 'required|numeric'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
