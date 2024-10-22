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

       if($row['acepta_datos'] == 'Si'){
           $row['acepta_datos'] = 1;
       }
       elseif ($row['rubro'] == 'No'){
           $row['acepta_datos'] = 0;
       }

        if($row['rubro'] == 1){
            $row['rubro'] = 'Activos';
        }
        if($row['rubro'] == 2){
            $row['rubro'] = 'Pasivos';
        }
        if($row['rubro'] == 3){
            $row['rubro'] = 'Capital';
        }
        if($row['rubro'] == 4){
            $row['rubro'] = 'Costos y gastos';
        }
        if($row['rubro'] == 5){
            $row['rubro'] = 'Ingresos';
        }
        if($row['rubro'] == 6){
            $row['rubro'] = 'Resultados';
        }
        if($row['rubro'] == 7){
            $row['rubro'] = 'Contingencia';
        }
        if($row['rubro'] == 8){
            $row['rubro'] = 'Presupuestos';
        }
        if($row['rubro'] == 9){
            $row['rubro'] = 'Otros';
        }


        $cuenta->codigo = $row['codigo'];
        $cuenta->nombre = $row['nombre'];
        $cuenta->naturaleza = $row['naturaleza'];
        $cuenta->id_cuenta_padre = $row['id_cuenta_padre'];
        $cuenta->rubro = $row['rubro'];
        $cuenta->nivel = $row['nivel'];
        $cuenta->id_empresa = Auth::user()->id_empresa;
        $cuenta->acepta_datos= $row['acepta_datos'];
        $cuenta->abono= $row['abono'];
        $cuenta->cargo= $row['cargo'];
        $cuenta->saldo= $row['saldo'];
        $cuenta ->save();


    }

    public function rules(): array
    {
        return [
            'codigo'       => 'required|int',
            'nombre'       => 'required|string',
            'naturaleza'   => 'required|string',
            'rubro'        => 'required|int',
            'nivel'        => 'required|int',
            'saldo'        => 'required|numeric'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
