<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Configuracion;
use App\Models\Bancos\Cuenta as CuentaBanco;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Illuminate\Support\Facades\DB;
use Exception;

class TransaccionesService
{
    public function crearPartida($transaccion)
    {
        // Validar que la transacción existe
        if (!$transaccion || !isset($transaccion->id)) {
            throw new Exception('La transacción proporcionada no es válida', 400);
        }

        $configuracion = Configuracion::first();
        if (!$configuracion) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        // Validar que la transacción tiene los datos necesarios
        if (!$transaccion->fecha) {
            throw new Exception('La transacción no tiene fecha asignada', 400);
        }

        if (!$transaccion->total || $transaccion->total <= 0) {
            throw new Exception('La transacción no tiene un monto válido', 400);
        }

        if (!$transaccion->tipo || !in_array($transaccion->tipo, ['Cargo', 'Abono'])) {
            throw new Exception('La transacción no tiene un tipo válido (debe ser Cargo o Abono)', 400);
        }

        if (!$transaccion->id_cuenta) {
            throw new Exception('La transacción no tiene cuenta bancaria asociada', 400);
        }

        // Cargar cuenta bancaria con validación
        $cuenta_banco = CuentaBanco::find($transaccion->id_cuenta);
        if (!$cuenta_banco) {
            throw new Exception('No se encontró la cuenta bancaria asociada a la transacción', 400);
        }

        if (!$cuenta_banco->id_cuenta_contable) {
            throw new Exception('La cuenta bancaria no tiene cuenta contable asociada', 400);
        }

        DB::beginTransaction();

        try {
            // Definir cuentas según el tipo de transacción
            if ($transaccion->tipo == 'Cargo') {
                // Cargo: Debe = Cuenta bancaria, Haber = CxP
                if (!$configuracion->id_cuenta_cxp) {
                    throw new Exception('No se ha configurado la cuenta de cuentas por pagar', 400);
                }

                $cuenta_haber = Cuenta::find($configuracion->id_cuenta_cxp);
                if (!$cuenta_haber) {
                    throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
                }

                $cuenta_debe = Cuenta::find($cuenta_banco->id_cuenta_contable);
                if (!$cuenta_debe) {
                    throw new Exception('No se encontró la cuenta contable del banco', 400);
                }
            } else {
                // Abono: Debe = CxP, Haber = Cuenta bancaria
                if (!$configuracion->id_cuenta_cxp) {
                    throw new Exception('No se ha configurado la cuenta de cuentas por pagar', 400);
                }

                $cuenta_haber = Cuenta::find($cuenta_banco->id_cuenta_contable);
                if (!$cuenta_haber) {
                    throw new Exception('No se encontró la cuenta contable del banco', 400);
                }

                $cuenta_debe = Cuenta::find($configuracion->id_cuenta_cxp);
                if (!$cuenta_debe) {
                    throw new Exception('No se encontró la cuenta contable de cuentas por pagar', 400);
                }
            }

            // Determinar tipo de partida según referencia
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
                'concepto'      => 'Transacción bancaria: ' . ($transaccion->concepto ?? 'Sin concepto'),
                'estado'        => 'Pendiente',
                'referencia'    => 'Transacción',
                'id_referencia' => $transaccion->id,
                'id_usuario'    => $transaccion->id_usuario,
                'id_empresa'    => $transaccion->id_empresa,
            ]);

            // Crear detalle del Debe
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

            // Crear detalle del Haber
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

            DB::commit();

            return [
                'success' => true,
                'message' => 'Partida contable de transacción creada exitosamente',
                'partida_id' => $partida->id
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw new Exception('Error al crear la partida de transacción: ' . $e->getMessage(), 400);
        } catch (\Throwable $e) {
            DB::rollback();
            throw new Exception('Error inesperado al crear la partida de transacción: ' . $e->getMessage(), 500);
        }
    }
}
