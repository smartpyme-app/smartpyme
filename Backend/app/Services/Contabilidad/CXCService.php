<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Ventas\Venta;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Illuminate\Support\Facades\DB;
use Exception;

class CXCService
{
    public function crearPartida($cxp)
    {
        $configuracion = Configuracion::firstOrFail();
        $venta = Venta::where('id', $cxp->id_venta)->firstOrFail();

        DB::beginTransaction();

        try {

            $partida = Partida::create([
                'fecha'         => $cxp->fecha,
                'tipo'          => 'Egreso',
                'concepto'      => 'Abono a cuenta por cobrar. ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                'estado'        => 'Pendiente',
                'referencia'    => 'Abono de Venta',
                'id_referencia' => $cxp->id,
                'id_usuario'    => $cxp->id_usuario,
                'id_empresa'    => $cxp->id_empresa,
            ]);

            // Debe
                $formapago = FormaDePago::with('banco')->where('nombre', $cxp->forma_pago)->firstOrFail();
                $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->firstOrFail();
                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Abono a cuenta por cobrar',
                    'debe'              => $cxp->total,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

            //Haber
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxc)->firstOrFail();
                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Abono a cuenta por cobrar',
                    'debe'              => NULL,
                    'haber'             => $cxp->total,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
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
