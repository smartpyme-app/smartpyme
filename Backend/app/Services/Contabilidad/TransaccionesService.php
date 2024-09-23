<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Configuracion;
use App\Models\Bancos\Cuenta as CuentaBanco;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Exception;

class TransaccionesService
{
    public function crearPartida($transaccion)
    {
        $configuracion = Configuracion::firstOrFail();

        $cuenta = CuentaBanco::where('id', $transaccion->id_cuenta)->firstOrFail();

        if ($transaccion->tipo == 'Cargo') {
            $cuenta_haber = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();
            $cuenta_debe = Cuenta::where('id', $cuenta->id_cuenta_contable)->firstOrFail();
        }
        if ($transaccion->tipo == 'Abono') {
            $cuenta_haber = Cuenta::where('id', $cuenta->id_cuenta_contable)->firstOrFail();
            $cuenta_debe = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();
        }

        $tipo = 'Diario';
        if ($transaccion->referencia == 'Venta' || $transaccion->referencia == 'Abono de Venta') {
            $tipo = 'CxC';
        }
        if ($transaccion->referencia == 'Abono de Compra' || $transaccion->referencia == 'Compra') {
            $tipo = 'CxP';
        }

        $partida = Partida::create([
            'fecha'         => $transaccion->fecha,
            'tipo'          => $tipo,
            'concepto'      => 'Transacción bancaria: ' . $transaccion->concepto,
            'estado'        => 'Pendiente',
            'referencia'    => 'Transacción',
            'id_referencia' => $transaccion->id,
            'id_usuario'    => $transaccion->id_usuario,
            'id_empresa'    => $transaccion->id_empresa,
        ]);

        //Debe
            Detalle::create([
                'id_cuenta'         => $cuenta_debe->id,
                'codigo'            => $cuenta_debe->codigo,
                'nombre_cuenta'     => $cuenta_debe->nombre,
                'concepto'          => 'Transacción bancaria',
                'debe'              => $transaccion->total,
                'haber'             => NULL,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

        // Haber
            Detalle::create([
                'id_cuenta'         => $cuenta_haber->id,
                'codigo'            => $cuenta_haber->codigo,
                'nombre_cuenta'     => $cuenta_haber->nombre,
                'concepto'          => 'Transacción bancaria',
                'debe'              => NULL,
                'haber'             => $transaccion->total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);


    }

}
