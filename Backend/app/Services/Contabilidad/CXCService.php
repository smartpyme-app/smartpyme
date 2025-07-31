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
    public function crearPartida($cxc)
    {
        // Validar que el abono CXC existe
        if (!$cxc || !isset($cxc->id)) {
            throw new Exception('El abono a cuenta por cobrar proporcionado no es válido', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Validar que el CXC tiene los datos necesarios
        if (!$cxc->id_venta) {
            throw new Exception('El abono no tiene venta asociada', 400);
        }

        if (!$cxc->fecha) {
            throw new Exception('El abono no tiene fecha asignada', 400);
        }

        if (!$cxc->total || $cxc->total <= 0) {
            throw new Exception('El abono no tiene un monto válido', 400);
        }

        if (!$cxc->forma_pago) {
            throw new Exception('El abono no tiene forma de pago asignada', 400);
        }

        // Cargar la venta con validación
        $venta = Venta::find($cxc->id_venta);
        if (!$venta) {
            throw new Exception('No se encontró la venta asociada al abono', 400);
        }

        DB::beginTransaction();

        try {
            $partida = Partida::create([
                'fecha'         => $cxc->fecha,
                'tipo'          => 'Egreso',
                'concepto'      => 'Abono a cuenta por cobrar. ' . ($venta->nombre_documento ?? 'Documento') . ' #' . ($venta->correlativo ?? 'Sin correlativo'),
                'estado'        => 'Pendiente',
                'referencia'    => 'Abono de Venta',
                'id_referencia' => $cxc->id,
                'id_usuario'    => $cxc->id_usuario,
                'id_empresa'    => $cxc->id_empresa,
            ]);

            // Debe - Forma de pago
            $formapago = FormaDePago::with('banco')->where('nombre', $cxc->forma_pago)->first();
            if (!$formapago) {
                throw new Exception('No se encontró la forma de pago: ' . $cxc->forma_pago, 400);
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
                'concepto'          => 'Abono a cuenta por cobrar',
                'debe'              => $cxc->total,
                'haber'             => NULL,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            // Haber - Cuenta por cobrar
            if (!$configuracion->id_cuenta_cxc) {
                throw new Exception('No se ha configurado la cuenta de cuentas por cobrar en la configuración contable', 400);
            }

            $cuenta_cxc = Cuenta::find($configuracion->id_cuenta_cxc);
            if (!$cuenta_cxc) {
                throw new Exception('No se encontró la cuenta contable de cuentas por cobrar', 400);
            }

            Detalle::create([
                'id_cuenta'         => $cuenta_cxc->id,
                'codigo'            => $cuenta_cxc->codigo,
                'nombre_cuenta'     => $cuenta_cxc->nombre,
                'concepto'          => 'Abono a cuenta por cobrar',
                'debe'              => NULL,
                'haber'             => $cxc->total,
                'saldo'             => 0,
                'id_partida'        => $partida->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de abono CXC creada exitosamente',
                'partida_id' => $partida->id,
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de abono CXC: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de abono CXC: ' . $e->getMessage(), 500);
        }
    }
}
