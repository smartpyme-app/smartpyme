<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\Impuesto;
use App\Models\Admin\Banco;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;

class ComprasService
{
    public function crearPartida($compra)
    {
        $configuracion = Configuracion::firstOrFail();

        //Debe
            $cuenta_debe = Cuenta::where('id', $configuracion->id_cuenta_inventario)->firstOrFail();
            $cuenta_inpuesto = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->firstOrFail();

        // Haber
            if ($compra->estado == 'Pendiente') {
                $cuenta_haber = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();
            }else{
                $banco = Banco::where('nombre', $compra->detalle_banco)->first();
                $cuenta_haber = Cuenta::where('id', $compra->id_cuenta_contable)->firstOrFail();
            }

        $partida = Partida::create([
            'fecha'         => $compra->fecha,
            'tipo'          => $compra->estado == 'Pendiente' ? 'CxP' : 'Egreso',
            'concepto'      => 'Compra de mercancía',
            'estado'        => 'Pendiente',
            'referencia'    => 'Compra',
            'id_referencia' => $compra->id, 'id_usuario' => $compra->id_usuario,
            'id_usuario'    => $compra->id_usuario,
            'id_empresa'    => $compra->id_empresa,
        ]);

        //Debe
            Detalle::create([
                'id_cuenta'         => $cuenta_debe->id,
                'codigo'            => $cuenta_debe->codigo,
                'nombre_cuenta'     => $cuenta_debe->nombre,
                'concepto'          => 'Compra de mercancía',
                'debe'              => $compra->sub_total,
                'haber'             => NULL,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);
            Detalle::create([
                'id_cuenta'         => $cuenta_inpuesto->id,
                'codigo'            => $cuenta_inpuesto->codigo,
                'nombre_cuenta'     => $cuenta_inpuesto->nombre,
                'concepto'          => 'Compra de mercancía',
                'debe'              => $compra->iva,
                'haber'             => NULL,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

        // Haber
            Detalle::create([
                'id_cuenta'         => $cuenta_haber->id,
                'codigo'            => $cuenta_haber->codigo,
                'nombre_cuenta'     => $cuenta_haber->nombre,
                'concepto'          => 'Compra de mercancía',
                'debe'              => NULL,
                'haber'             => $compra->total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);


    }

}
