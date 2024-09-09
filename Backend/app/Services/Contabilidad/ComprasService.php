<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Illuminate\Support\Facades\DB;
use Exception;

class ComprasService
{
    public function crearPartida($compra)
    {
        $configuracion = Configuracion::firstOrFail();

        DB::beginTransaction();

        try {

            $partida = Partida::create([
                'fecha'         => $compra->fecha,
                'tipo'          => $compra->estado == 'Pendiente' ? 'CxP' : 'Egreso',
                'concepto'      => 'Compra de mercancía. ' . $compra->tipo_documento . ' #' . $compra->referencia,
                'estado'        => 'Pendiente',
                'referencia'    => 'Compra',
                'id_referencia' => $compra->id,
                'id_usuario'    => $compra->id_usuario,
                'id_empresa'    => $compra->id_empresa,
            ]);

            // Haber
                if ($compra->estado == 'Pendiente') {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();
                }else{
                    $formapago = FormaDePago::with('banco')->where('nombre', $compra->forma_pago)->firstOrFail();
                    $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->firstOrFail();
                }
                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Compra de mercancía',
                    'debe'              => NULL,
                    'haber'             => $compra->total,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

            //Debe
                $cuenta = Cuenta::where('id', $configuracion->id_cuenta_inventario)->firstOrFail();
                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Compra de mercancía',
                    'debe'              => $compra->sub_total,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);


                if ($compra->iva > 0) {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Compra de mercancía',
                        'debe'              => $compra->iva,
                        'haber'             => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                if ($compra->percepcion > 0) {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Compra de mercancía',
                        'debe'             => $compra->percepcion,
                        'haber'              => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                if ($compra->renta_retenida > 0) {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Compra de mercancía',
                        'debe'              => $compra->renta_retenida,
                        'haber'             => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

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
