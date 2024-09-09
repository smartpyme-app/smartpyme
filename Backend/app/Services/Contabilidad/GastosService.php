<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Compras\Gastos\Gasto;
use Illuminate\Support\Facades\DB;

class GastosService
{
    public function crearPartida($gasto)
    {
        $configuracion = Configuracion::firstOrFail();
        $gasto->categoria = Gasto::with('categoria')->where('id', $gasto['id'])->firstOrFail()->categoria;

        //Debe
            $cuenta_debe = Cuenta::where('id', $gasto->categoria->id_cuenta_contable)->firstOrFail();

        // Haber
            if ($gasto->estado == 'Pendiente') {
                $cuenta_haber = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();
            }else{
                $cuenta_haber = Cuenta::where('id', $gasto->id_cuenta_contable)->firstOrFail();
            }

        DB::beginTransaction();

        try {

            $partida = Partida::create([
                'fecha'         => $gasto->fecha,
                'tipo'          => $gasto->estado == 'Pendiente' ? 'CxP' : 'Egreso',
                'concepto'      => 'Gasto de ' . $gasto->categoria->nombre . '. ' . $gasto->tipo_documento . ' #' . $gasto->referencia,
                'estado'        => 'Pendiente',
                'referencia'    => 'Gasto',
                'id_referencia' => $gasto->id,
                'id_usuario'    => $gasto->id_usuario,
                'id_empresa'    => $gasto->id_empresa,
            ]);

            //Debe
                Detalle::create([
                    'id_cuenta'         => $cuenta_debe->id,
                    'codigo'            => $cuenta_debe->codigo,
                    'nombre_cuenta'     => $cuenta_debe->nombre,
                    'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                    'debe'              => $gasto->total,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);
            // Haber
                Detalle::create([
                    'id_cuenta'         => $cuenta_haber->id,
                    'codigo'            => $cuenta_haber->codigo,
                    'nombre_cuenta'     => $cuenta_haber->nombre,
                    'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                    'debe'              => NULL,
                    'haber'             => $gasto->total - $gasto->iva - $gasto->iva_percibido - $gasto->renta_retenida,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                if ($gasto->iva > 0) {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                        'debe'              => NULL,
                        'haber'             => $gasto->iva,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                if ($gasto->iva_percibido > 0) {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                        'debe'              => NULL,
                        'haber'             => $gasto->iva_percibido,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                if ($gasto->renta_retenida > 0) {
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                        'debe'              => NULL,
                        'haber'             => $gasto->renta_retenida,
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
