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

       if(strtoupper($row['acepta_datos']) == 'SI'){
           $row['acepta_datos'] = 1;
       }
       else{
           $row['acepta_datos'] = 0;
       }

//        if($row['rubro'] == 1){
//            $row['rubro'] = 'Activos';
//        }
//        if($row['rubro'] == 2){
//            $row['rubro'] = 'Pasivos';
//        }
//        if($row['rubro'] == 3){
//            $row['rubro'] = 'Capital';
//        }
//        if($row['rubro'] == 4){
//            $row['rubro'] = 'Costos y gastos';
//        }
//        if($row['rubro'] == 5){
//            $row['rubro'] = 'Ingresos';
//        }
//        if($row['rubro'] == 6){
//            $row['rubro'] = 'Resultados';
//        }
//        if($row['rubro'] == 7){
//            $row['rubro'] = 'Contingencia';
//        }
//        if($row['rubro'] == 8){
//            $row['rubro'] = 'Presupuestos';
//        }
//        if($row['rubro'] == 9){
//            $row['rubro'] = 'Otros';
//        }


        $cuenta->codigo = $row['codigo'];
        $cuenta->nombre = $row['nombre'];
        $cuenta->naturaleza = $row['naturaleza'];
        if (!empty($row['id_cuenta_padre'])) {
            $cuentaPadre = Cuenta::where('codigo', $row['id_cuenta_padre'])
                ->where('id_empresa', Auth::user()->id_empresa)
                ->first();
            $cuenta->id_cuenta_padre = $cuentaPadre ? $cuentaPadre->id : null;
        } else {
            $cuenta->id_cuenta_padre = null;
        }
        $cuenta->rubro = ucfirst(strtolower($row['rubro']));
        $cuenta->nivel = $row['nivel'];
        $cuenta->id_empresa = Auth::user()->id_empresa;
        $cuenta->acepta_datos= $row['acepta_datos'];
        $cuenta->abono= isset($row['abono']) ? $row['abono'] : 0;
        $cuenta->cargo= isset($row['cargo']) ? $row['cargo'] : 0;
        $cuenta->saldo= isset($row['saldo']) ? $row['saldo'] : 0;
        $cuenta->saldo_inicial= isset($row['saldo']) ? $row['saldo'] : 0;
        $cuenta ->save();


    }

    public function rules(): array
    {
        return [
            'codigo'       => 'required|int',
            'nombre'       => 'required|string',
            'naturaleza'   => 'required|string',
            'rubro'        => 'required|string',
            'nivel'        => 'required|int',
            'saldo'        => 'required|numeric'
        ];
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
