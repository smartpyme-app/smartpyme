<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Admin\FormaDePago;
use Illuminate\Support\Facades\DB;
use Exception;

class VentasService
{
    public function crearPartida($venta)
    {
        $configuracion = Configuracion::firstOrFail();

        DB::beginTransaction();

        try {

            $partida = Partida::create([
                'fecha' => $venta->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas. ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                'estado' => 'Pendiente',
                'referencia'    => 'Venta',
                'id_referencia' => $venta->id,
                'id_usuario' => $venta->id_usuario,
                'id_empresa' => $venta->id_empresa,
            ]);

        // Debe
            // Haber
                if ($venta->estado == 'Pendiente') {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxc)->firstOrFail();
                }else{
                    $formapago = FormaDePago::with('banco')->where('nombre', $venta->forma_pago)->firstOrFail();
                    $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->firstOrFail();
                }
            Detalle::create([
                'id_cuenta' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => $venta->total,
                'haber' => NULL,
                'saldo' => 0,
                'id_partida' => $partida->id
            ]);

        // Haber
            $cuenta = Cuenta::where('id', $configuracion->id_cuenta_ventas)->firstOrFail();
            Detalle::create([
                'id_cuenta' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => NULL,
                'haber' => $venta->sub_total,
                'saldo' => 0,
                'id_partida' => $partida->id
            ]);

            if ($venta->iva > 0) {
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_ventas)->firstOrFail();
                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => NULL,
                    'haber' => $venta->iva,
                    'saldo' => 0,
                    'id_partida' => $partida->id
                ]);
            }

            if ($venta->iva_retenido > 0) {
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_ventas)->firstOrFail();
                Detalle::create([
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ventas',
                    'debe' => $venta->iva_retenido,
                    'haber' => NULL,
                    'saldo' => 0,
                    'id_partida' => $partida->id
                ]);
            }


            // Costo de venta

            $partida = Partida::create([
                'fecha' => $venta->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por costo de ventas. ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                'estado' => 'Pendiente',
                'referencia'    => 'Venta',
                'id_referencia' => $venta->id,
                'id_usuario' => $venta->id_usuario,
                'id_empresa' => $venta->id_empresa,
            ]);

            $cuenta = Cuenta::where('id', $configuracion->id_cuenta_costo_venta)->firstOrFail();
            Detalle::create([
                'id_cuenta' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => $venta->total_costo,
                'haber' => NULL,
                'saldo' => 0,
                'id_partida' => $partida->id
            ]);

            // Inventario
            $cuenta = Cuenta::where('id', $configuracion->id_cuenta_inventario)->firstOrFail();
            Detalle::create([
                'id_cuenta' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'concepto' => 'Ingresos por ventas',
                'debe' => NULL,
                'haber' => $venta->total_costo,
                'saldo' => 0,
                'id_partida' => $partida->id
            ]);

        DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), 400);
        }

    }

}
