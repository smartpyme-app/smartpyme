<?php

namespace App\Imports;

use Illuminate\Support\Facades\Auth;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\User;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use JWTAuth;

class Paquetes implements ToModel, WithHeadingRow, WithValidation
{
    // use Importable;

    private $numRows = 0;
    
    public function model(array $row)
    {
        // throw new \Exception($mensaje);
        
        $usuario = JWTAuth::parseToken()->authenticate();

        $id_cliente = Cliente::where('nombre', $row['cliente'])
                                ->where('id_empresa', $usuario->id_empresa)
                                ->pluck('id')->first();
        

        if(!$id_cliente){
            $cliente = new Cliente();
            $cliente->nombre = $row['cliente'];
            $cliente->enable = true;
            $cliente->id_usuario = $usuario->id;
            $cliente->id_empresa = $usuario->id_empresa;
            $cliente->save();
            $id_cliente = $cliente->id;
        }

        $paquete = Paquete::where('wr', $row['wr'])
                                ->where('id_empresa', $usuario->id_empresa)
                                ->first();

        if (!$paquete) {

            $paquete = new Paquete();
            $paquete->fecha     = date('Y-m-d');
            $paquete->wr        = $row['wr'];
            $paquete->transportista = isset($row['transportista']) ? $row['transportista'] : '';
            $paquete->consignatario = isset($row['consignatario']) ? $row['consignatario'] : '';
            $paquete->transportador = isset($row['transportador']) ? $row['transportador'] : '';
            $paquete->estado    = 'En bodega';
            $paquete->num_seguimiento   = isset($row['seguimiento']) ? $row['seguimiento'] : '';
            $paquete->num_guia  = isset($row['guia']) ? $row['guia'] : '';
            $paquete->piezas    = $row['piezas'];
            $paquete->embalaje    = $row['embalaje'];
            $paquete->peso      = $row['peso'];
            $paquete->precio    = $row['precio'];
            $paquete->volumen   = isset($row['volumen']) ? $row['volumen'] : null;
            $paquete->nota      = isset($row['nota']) ? $row['nota'] : '';
            $paquete->cuenta_a_terceros    = $row['cuenta_a_terceros'];
            $paquete->otros    = $row['otros'];
            $paquete->total    = $row['total'];
            $paquete->id_cliente    = $id_cliente;
            $paquete->id_asesor     = isset($row['codigo_asesor']) ? User::where('id_empresa', $usuario->id_empresa)->where('codigo', $row['codigo_asesor'])->pluck('id')->first() : null;
            $paquete->id_usuario    = $usuario->id;
            $paquete->id_sucursal   = $usuario->id_sucursal;
            $paquete->id_empresa    = $usuario->id_empresa;
            $paquete->save();

            ++$this->numRows;
            
            return $paquete;

        }


    }

    public function rules(): array
    {
        return [
            'cliente'   => 'required|string',
            'codigo_asesor'    => 'required',
            // 'wr'    => 'required|unique:paquetes,wr',
            'wr'    => 'required',
            // 'seguimiento'    => 'required',
            'guia'    => 'required',
            'piezas'    => 'required|numeric',
            'precio'    => 'required|numeric',
            'peso'      => 'required|numeric',
            'otros'      => 'required|numeric',
            'cuenta_a_terceros' => 'required|numeric',
            'embalaje' => 'required',
            'total'      => 'required|numeric',
        ];
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
