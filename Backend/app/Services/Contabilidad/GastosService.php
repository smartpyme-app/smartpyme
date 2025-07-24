<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Compras\Gastos\Gasto;
use Illuminate\Support\Facades\DB;
use Exception;

class GastosService
{
    public function crearPartida($gasto)
    {
        $configuracion = Configuracion::firstOrFail();
        $gasto->categoria = Gasto::with('categoria')->where('id', $gasto['id'])->firstOrFail()->categoria;

        // Validar que la categoría del gasto exista y tenga cuenta contable configurada
        if (!$gasto->categoria || !$gasto->categoria->id_cuenta_contable) {
            throw new Exception('La categoría del gasto no tiene una cuenta contable configurada', 400);
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

            // Haber
                // Haber
                    if ($gasto->estado == 'Pendiente') {
                        if (!$configuracion->id_cuenta_cxp) {
                            throw new Exception('No se ha configurado la cuenta de cuentas por pagar', 400);
                        }
                        $cuenta = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();
                    } else {
                        $formapago = FormaDePago::with('banco')->where('nombre', $gasto->forma_pago)->first();

                        if (!$formapago) {
                            throw new Exception('No se encontró la forma de pago: ' . $gasto->forma_pago, 400);
                        }

                        if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
                            throw new Exception('La forma de pago no tiene un banco o cuenta contable configurada', 400);
                        }

                        $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->firstOrFail();
                    }

                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                    'debe'              => NULL,
                    'haber'             => $gasto->total,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

            //Debe
                $cuenta = Cuenta::where('id', $gasto->categoria->id_cuenta_contable)->firstOrFail();
                Detalle::create([
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                    'debe'              => $gasto->sub_total,
                    'haber'             => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                if ($gasto->iva > 0) {
                    if (!$configuracion->id_cuenta_iva_compras) {
                        throw new Exception('No se ha configurado la cuenta de IVA compras', 400);
                    }
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                        'debe'             => $gasto->iva,
                        'haber'              => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                if ($gasto->iva_percibido > 0) {
                    if (!$configuracion->id_cuenta_iva_retenido_compras) {
                        throw new Exception('No se ha configurado la cuenta de IVA retenido compras', 400);
                    }
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                        'debe'             => $gasto->iva_percibido,
                        'haber'              => NULL,
                        'saldo'             => 0,
                        'id_partida'        => $partida->id
                    ]);
                }

                if ($gasto->renta_retenida > 0) {
                    if (!$configuracion->id_cuenta_renta_retenida_compras) {
                        throw new Exception('No se ha configurado la cuenta de renta retenida compras', 400);
                    }
                    $cuenta = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();
                    Detalle::create([
                        'id_cuenta'         => $cuenta->id,
                        'codigo'            => $cuenta->codigo,
                        'nombre_cuenta'     => $cuenta->nombre,
                        'concepto'          => 'Gasto de ' . $gasto->categoria->nombre,
                        'debe'             => NULL,
                        'haber'              => $gasto->renta_retenida,
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
