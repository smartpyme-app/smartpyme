<?php

namespace App\Imports;

use App\Models\Contabilidad\Catalogo\Cuenta;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Auth;

class CatalogoImport implements ToModel, WithHeadingRow, WithValidation
{

    private $numRows = 0;
    public function model(array $row)
    {
        ++$this->numRows;
        $cuenta = new Cuenta();
        $cuenta->codigo = $row['codigo']; // error en $row['codigo'] -- undefined index codigo
        $cuenta->nombre = $row['nombre'];
        $cuenta->naturaleza = $row['naturaleza'];
        $cuenta->id_cuenta_padre = $row['id_cuenta_padre'];
        $cuenta->rubro = $row['rubro'];
        $cuenta->nivel = $row['nivel'];
        $cuenta->id_empresa = Auth::user()->id ;
        $cuenta ->save();

    }

    public function rules(): array
    {
        return [
            'codigo'        => 'required|int',
            'nombre'        => 'required|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
