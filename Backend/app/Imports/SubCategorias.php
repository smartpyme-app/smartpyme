<?php

namespace App\Imports;

use App\Models\Inventario\Categorias\SubCategoria;
use App\Models\Inventario\Categorias\Categoria;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SubCategorias implements ToModel, WithHeadingRow, WithValidation
{
    private $numRows = 0;

    public function model(array $row)
    {
        ++$this->numRows;


        $subcategoria = new SubCategoria();
        $subcategoria->nombre = $row['nombre'];
        $subcategoria->descripcion = $row['descripcion'];
        
        $categoria = Categoria::where('descripcion', $row['categoria_id'])->firstOrFail();
        
        $subcategoria->categoria_id = $categoria->id;
        $subcategoria->save();

    }

    public function rules(): array
    {
        return [
            'nombre'        => 'required|string',
            'descripcion'   => 'required|string',
            'categoria_id'  => 'required|numeric'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
