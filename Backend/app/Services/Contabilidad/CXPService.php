<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Compras\Compra;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Illuminate\Support\Facades\DB;
use Exception;

class CXPService
{
    public function crearPartida($cxp)
    {
        // Validar que el abono CXP existe
        if (!$cxp || !isset($cxp->id)) {
            throw new Exception('El abono a cuenta por pagar proporcionado no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Validar que el CXP tiene los datos necesarios
        if (!$cxp->id_compra) {
            throw new Exception('El abono no tiene compra asociada', 400);
        }

        if (!$cxp->fecha) {
            throw new Exception('El abono no tiene fecha asignada', 400);
        }

        if (!$cxp->total || $cxp->total <= 0) {
            throw new Exception('El abono no tiene un monto válido', 400);
        }

        if (!$cxp->forma_pago) {
            throw new Exception('El abono no tiene forma de pago asignada', 400);
        }

        // Cargar la compra con validación
        $compra = Compra::find($cxp->id_compra);
        if (!$compra) {
            throw new Exception('No se encontró la compra asociada al abono', 400);
        }

        Partida::assertNoExisteParaOrigen('Abono de Compra', $cxp->id, 'Ya existen partidas contables generadas para este abono a compra.');

        DB::beginTransaction();

        try {
            $partida = Partida::create([
                'fecha'         => $cxp->fecha,
                'tipo'          => 'Egreso',
                'concepto'      => 'Abono a cuenta por pagar. ' . ($compra->tipo_documento ?? 'Documento') . ' #' . ($compra->referencia ?? 'Sin referencia'),
                'estado'        => 'Pendiente',
                'referencia'    => 'Abono de Compra',
                'id_referencia' => $cxp->id,
                'id_usuario'    => $cxp->id_usuario,
                'id_empresa'    => $cxp->id_empresa,
            ]);

            // Debe - Cuenta por pagar
            if (!$configuracion->id_cuenta_cxp) {
                throw new Exception('No se ha configurado la cuenta de cuentas por pagar en la configuración contable', 400);
            }

            $cuenta_cxp = Cuenta::find($configuracion->id_cuenta_cxp);
            if (!$cuenta_cxp) {
                throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
            }

            Detalle::create([
                'id_cuenta'         => $cuenta_cxp->id,
                'codigo'            => $cuenta_cxp->codigo,
                'nombre_cuenta'     => $cuenta_cxp->nombre,
                'concepto'          => 'Abono a cuenta por pagar',
                'debe'              => $cxp->total,
                'haber'             => NULL,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            // Haber - Forma de pago
            $formapago = FormaDePago::with('banco')->where('nombre', $cxp->forma_pago)->first();
            if (!$formapago) {
                throw new Exception('No se encontró la forma de pago: ' . $cxp->forma_pago, 400);
            }

            if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
                throw new Exception('La forma de pago no tiene un banco o cuenta contable configurada', 400);
            }

            $cuenta_forma_pago = Cuenta::find($formapago->banco->id_cuenta_contable);
            if (!$cuenta_forma_pago) {
                throw new Exception('No se encontró la cuenta contable del banco asociado a la forma de pago', 400);
            }

            Detalle::create([
                'id_cuenta'         => $cuenta_forma_pago->id,
                'codigo'            => $cuenta_forma_pago->codigo,
                'nombre_cuenta'     => $cuenta_forma_pago->nombre,
                'concepto'          => 'Abono a cuenta por pagar',
                'debe'              => NULL,
                'haber'             => $cxp->total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de abono CXP creada exitosamente',
                'partida_id' => $partida->id
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de abono CXP: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de abono CXP: ' . $e->getMessage(), 500);
        }
    }
}
