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
        // Validar que el gasto existe
        if (!$gasto || !isset($gasto->id)) {
            throw new Exception('El gasto proporcionado no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Cargar la categoría del gasto con validación
        $gastoCompleto = Gasto::with('categoria')->find($gasto->id);
        if (!$gastoCompleto) {
            throw new Exception('No se encontró el gasto con ID: ' . $gasto->id, 400);
        }

        // Validar que el gasto tiene los datos necesarios
        if (!$gastoCompleto->fecha) {
            throw new Exception('El gasto no tiene fecha asignada', 400);
        }

        if (!$gastoCompleto->total || $gastoCompleto->total <= 0) {
            throw new Exception('El gasto no tiene un monto válido', 400);
        }

        // Validar que la categoría del gasto exista y tenga cuenta contable configurada
        if (!$gastoCompleto->categoria || !$gastoCompleto->categoria->id_cuenta_contable) {
            throw new Exception('La categoría del gasto no tiene una cuenta contable configurada', 400);
        }

        Partida::assertNoExisteParaOrigen('Gasto', $gastoCompleto->id, 'Ya existen partidas contables generadas para este gasto.');

        DB::beginTransaction();

        try {
            $partida = Partida::create([
                'fecha'         => $gastoCompleto->fecha,
                'tipo'          => $gastoCompleto->estado == 'Pendiente' ? 'CxP' : 'Egreso',
                'concepto'      => 'Gasto de ' . $gastoCompleto->categoria->nombre . '. ' . ($gastoCompleto->tipo_documento ?? 'Documento') . ' #' . ($gastoCompleto->referencia ?? 'Sin referencia'),
                'estado'        => 'Pendiente',
                'referencia'    => 'Gasto',
                'id_referencia' => $gastoCompleto->id,
                'id_usuario'    => $gastoCompleto->id_usuario,
                'id_empresa'    => $gastoCompleto->id_empresa,
            ]);

            // Haber - Determinar cuenta según estado
            if ($gastoCompleto->estado == 'Pendiente') {
                if (!$configuracion->id_cuenta_cxp) {
                    throw new Exception('No se ha configurado la cuenta de cuentas por pagar', 400);
                }
                $cuenta_haber = Cuenta::find($configuracion->id_cuenta_cxp);
                if (!$cuenta_haber) {
                    throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
                }
            } else {
                if (!$gastoCompleto->forma_pago) {
                    throw new Exception('El gasto no tiene forma de pago asignada', 400);
                }

                $formapago = FormaDePago::with('banco')->where('nombre', $gastoCompleto->forma_pago)->first();

                if (!$formapago) {
                    throw new Exception('No se encontró la forma de pago: ' . $gastoCompleto->forma_pago, 400);
                }

                if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
                    throw new Exception('La forma de pago no tiene un banco o cuenta contable configurada, para configurarla puede ir al menú de la aplicación, en Finanzas > Métodos de pago', 400);
                }

                $cuenta_haber = Cuenta::find($formapago->banco->id_cuenta_contable);
                if (!$cuenta_haber) {
                    throw new Exception('No se encontró la cuenta contable del banco asociado a la forma de pago, para configurarla puede ir al menú de la aplicación, en Finanzas > Bancos, seleccionar el tab de Cuentas y agregar la cuenta contable al banco.', 400);
                }
            }

            Detalle::create([
                'id_cuenta'         => $cuenta_haber->id,
                'codigo'            => $cuenta_haber->codigo,
                'nombre_cuenta'     => $cuenta_haber->nombre,
                'concepto'          => 'Gasto de ' . $gastoCompleto->categoria->nombre,
                'debe'              => NULL,
                'haber'             => $gastoCompleto->total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            // Debe - Cuenta de la categoría del gasto
            $cuenta_categoria = Cuenta::find($gastoCompleto->categoria->id_cuenta_contable);
            if (!$cuenta_categoria) {
                throw new Exception('No se encontró la cuenta contable de la categoría del gasto', 400);
            }

            Detalle::create([
                'id_cuenta'         => $cuenta_categoria->id,
                'codigo'            => $cuenta_categoria->codigo,
                'nombre_cuenta'     => $cuenta_categoria->nombre,
                'concepto'          => 'Gasto de ' . $gastoCompleto->categoria->nombre,
                'debe'              => $gastoCompleto->sub_total,
                'haber'             => NULL,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            $impuestos_aplicados = [];

            // IVA
            if ($gastoCompleto->iva > 0) {
                if (!$configuracion->id_cuenta_iva_compras) {
                    throw new Exception('No se ha configurado la cuenta de IVA compras', 400);
                }
                $cuenta_iva = Cuenta::find($configuracion->id_cuenta_iva_compras);
                if (!$cuenta_iva) {
                    throw new Exception('No se encontró la cuenta contable de IVA compras', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta_iva->id,
                    'codigo'            => $cuenta_iva->codigo,
                    'nombre_cuenta'     => $cuenta_iva->nombre,
                    'concepto'          => 'Gasto de ' . $gastoCompleto->categoria->nombre,
                    'debe'             => $gastoCompleto->iva,
                    'haber'              => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                $impuestos_aplicados['iva'] = $gastoCompleto->iva;
            }

            // IVA Percibido
            if ($gastoCompleto->iva_percibido > 0) {
                if (!$configuracion->id_cuenta_iva_retenido_compras) {
                    throw new Exception('No se ha configurado la cuenta de IVA retenido compras', 400);
                }
                $cuenta_iva_percibido = Cuenta::find($configuracion->id_cuenta_iva_retenido_compras);
                if (!$cuenta_iva_percibido) {
                    throw new Exception('No se encontró la cuenta contable de IVA retenido compras', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta_iva_percibido->id,
                    'codigo'            => $cuenta_iva_percibido->codigo,
                    'nombre_cuenta'     => $cuenta_iva_percibido->nombre,
                    'concepto'          => 'Gasto de ' . $gastoCompleto->categoria->nombre,
                    'debe'             => $gastoCompleto->iva_percibido,
                    'haber'              => NULL,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                $impuestos_aplicados['iva_percibido'] = $gastoCompleto->iva_percibido;
            }

            // Renta Retenida
            if ($gastoCompleto->renta_retenida > 0) {
                if (!$configuracion->id_cuenta_renta_retenida_compras) {
                    throw new Exception('No se ha configurado la cuenta de renta retenida compras', 400);
                }
                $cuenta_renta = Cuenta::find($configuracion->id_cuenta_renta_retenida_compras);
                if (!$cuenta_renta) {
                    throw new Exception('No se encontró la cuenta contable de renta retenida compras', 400);
                }

                Detalle::create([
                    'id_cuenta'         => $cuenta_renta->id,
                    'codigo'            => $cuenta_renta->codigo,
                    'nombre_cuenta'     => $cuenta_renta->nombre,
                    'concepto'          => 'Gasto de ' . $gastoCompleto->categoria->nombre,
                    'debe'             => NULL,
                    'haber'              => $gastoCompleto->renta_retenida,
                    'saldo'             => 0,
                    'id_partida'        => $partida->id
                ]);

                $impuestos_aplicados['renta_retenida'] = $gastoCompleto->renta_retenida;
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de gasto creada exitosamente',
                'partida_id' => $partida->id,
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de gasto: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de gasto: ' . $e->getMessage(), 500);
        }
    }
}
