<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;

class VentasService
{
    public function crearPartida($venta)
    {
        $configuracion = Configuracion::firstOrFail();

        $cuenta_debe = Cuenta::where('id', $configuracion->id_cuenta_ingresos)->firstOrFail();
        $cuenta_haber = Cuenta::where('id', 5)->firstOrFail();
        $cuenta_inpuesto = Cuenta::where('id', 320)->firstOrFail();

        $partida = Partida::create([
            'fecha' => $venta->fecha,
            'tipo' => 'Ingreso',
            'concepto' => 'Ingresos por ventas',
            'estado' => 'Pendiente',
            'id_usuario' => $venta->id_usuario,
            'id_empresa' => $venta->id_empresa,
        ]);

        Detalle::create([
            'id_cuenta' => $cuenta_debe->id,
            'codigo' => $cuenta_debe->codigo,
            'nombre_cuenta' => $cuenta_debe->nombre,
            'concepto' => 'Ingresos por ventas',
            'debe' => $venta->total,
            'haber' => NULL,
            'saldo' => 0,
            'id_partida' => $partida->id
        ]);

        Detalle::create([
            'id_cuenta' => $cuenta_haber->id,
            'codigo' => $cuenta_haber->codigo,
            'nombre_cuenta' => $cuenta_haber->nombre,
            'concepto' => 'Ingresos por ventas',
            'debe' => NULL,
            'haber' => $venta->sub_total,
            'saldo' => 0,
            'id_partida' => $partida->id
        ]);

        if (isset($venta['iva']) && $venta['iva'] > 0) {
            Detalle::create([
                'id_cuenta' => $cuenta_inpuesto->id,
                'codigo' => $cuenta_inpuesto->codigo,
                'nombre_cuenta' => $cuenta_inpuesto->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => NULL,
                'haber' => $venta->iva,
                'saldo' => 0,
                'id_partida' => $partida->id
            ]);
        }

    }

}
