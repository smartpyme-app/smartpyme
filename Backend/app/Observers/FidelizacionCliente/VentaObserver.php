<?php

namespace App\Observers\FidelizacionCliente;

use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VentaObserver
{
    /**
     * Handle the Venta "created" event.
     *
     * @param  \App\Models\Venta  $venta
     * @return void
     */
    public function created(Venta $venta)
    {
        try {
            $this->procesarAcumulacionPuntos($venta);
        } catch (\Exception $e) {
            // Log del error pero no interrumpir la venta
            Log::error('Error al procesar acumulación de puntos para venta ID: ' . $venta->id, [
                'error' => $e->getMessage(),
                'venta_id' => $venta->id,
                'cliente_id' => $venta->id_cliente,
                'empresa_id' => $venta->id_empresa,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Procesar la acumulación de puntos para una venta
     *
     * @param Venta $venta
     * @return void
     */
    private function procesarAcumulacionPuntos(Venta $venta)
    {
        // 1. Verificar si la empresa tiene fidelización habilitada
        if (!$venta->empresa->tieneFidelizacionHabilitada()) {
            Log::info('Empresa no tiene fidelización habilitada', ['empresa_id' => $venta->id_empresa]);
            return;
        }

        // 2. Verificar que la venta tenga cliente asignado
        if (!$venta->id_cliente) {
            Log::info('Venta sin cliente asignado, no se generan puntos', ['venta_id' => $venta->id]);
            return;
        }

        // 3. Verificar que no se hayan generado puntos previamente
        if ($venta->tienePuntosGenerados()) {
            Log::info('Venta ya tiene puntos generados', ['venta_id' => $venta->id]);
            return;
        }

        // 4. Obtener el cliente y su tipo efectivo
        $cliente = $venta->cliente;
        if (!$cliente) {
            Log::warning('Cliente no encontrado para venta', ['venta_id' => $venta->id, 'cliente_id' => $venta->id_cliente]);
            return;
        }

        $tipoCliente = $cliente->getTipoClienteEfectivo();
        if (!$tipoCliente) {
            Log::warning('No se pudo determinar tipo de cliente efectivo', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'empresa_id' => $venta->id_empresa
            ]);
            return;
        }

        // 5. Calcular puntos basado en el monto total de la venta
        $puntosCalculados = $tipoCliente->calcularPuntos($venta->total);
        
        if ($puntosCalculados <= 0) {
            Log::info('No se generan puntos para esta venta (puntos calculados: 0)', [
                'venta_id' => $venta->id,
                'monto' => $venta->total,
                'puntos_por_dolar' => $tipoCliente->puntos_por_dolar
            ]);
            return;
        }

        // 6. Procesar la acumulación en una transacción de base de datos
        DB::transaction(function () use ($venta, $cliente, $tipoCliente, $puntosCalculados) {
            $this->crearTransaccionPuntos($venta, $cliente, $tipoCliente, $puntosCalculados);
        });

        Log::info('Puntos acumulados exitosamente', [
            'venta_id' => $venta->id,
            'cliente_id' => $cliente->id,
            'puntos_generados' => $puntosCalculados,
            'tipo_cliente' => $tipoCliente->nombre_efectivo
        ]);
    }

    /**
     * Crear la transacción de puntos y actualizar saldos
     *
     * @param Venta $venta
     * @param \App\Models\Ventas\Clientes\Cliente $cliente
     * @param TipoClienteEmpresa $tipoCliente
     * @param int $puntosCalculados
     * @return void
     */
    private function crearTransaccionPuntos($venta, $cliente, $tipoCliente, $puntosCalculados)
    {
        // Obtener o crear registro de puntos del cliente
        $puntosCliente = PuntosCliente::firstOrCreate(
            [
                'id_cliente' => $cliente->id,
                'id_empresa' => $venta->id_empresa
            ],
            [
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'fecha_ultima_actividad' => now()
            ]
        );

        $puntosAntes = $puntosCliente->puntos_disponibles;
        $puntosDespues = $puntosAntes + $puntosCalculados;

        // Generar clave de idempotencia
        $idempotencyKey = TransaccionPuntos::generarIdempotencyKey(
            $cliente->id,
            TransaccionPuntos::TIPO_GANANCIA,
            $venta->id
        );

        // Crear transacción de puntos
        $transaccion = TransaccionPuntos::create([
            'id_cliente' => $cliente->id,
            'id_empresa' => $venta->id_empresa,
            'id_venta' => $venta->id,
            'tipo' => TransaccionPuntos::TIPO_GANANCIA,
            'puntos' => $puntosCalculados,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'monto_asociado' => $venta->total,
            'puntos_consumidos' => 0,
            'descripcion' => "Puntos ganados por venta #{$venta->id}",
            'fecha_expiracion' => $tipoCliente->getFechaExpiracion(),
            'idempotency_key' => $idempotencyKey
        ]);

        // Actualizar saldo consolidado en puntos_cliente
        $puntosCliente->agregarPuntos($puntosCalculados);

        // Actualizar campo puntos_ganados en la venta
        $venta->update(['puntos_ganados' => $puntosCalculados]);

        Log::info('Transacción de puntos creada', [
            'transaccion_id' => $transaccion->id,
            'venta_id' => $venta->id,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'fecha_expiracion' => $transaccion->fecha_expiracion
        ]);
    }

    /**
     * Handle the Venta "updated" event.
     *
     * @param  \App\Models\Venta  $venta
     * @return void
     */
    public function updated(Venta $venta)
    {
        // Por ahora no manejamos actualizaciones de ventas
        // En el futuro se podría implementar lógica para ajustar puntos
        // si cambia el monto de la venta
    }

    /**
     * Handle the Venta "deleted" event.
     *
     * @param  \App\Models\Venta  $venta
     * @return void
     */
    public function deleted(Venta $venta)
    {
        // Por ahora no manejamos eliminación de ventas
        // En el futuro se podría implementar lógica para revertir puntos
    }

    /**
     * Handle the Venta "restored" event.
     *
     * @param  \App\Models\Venta  $venta
     * @return void
     */
    public function restored(Venta $venta)
    {
        // Por ahora no manejamos restauración de ventas
    }

    /**
     * Handle the Venta "force deleted" event.
     *
     * @param  \App\Models\Venta  $venta
     * @return void
     */
    public function forceDeleted(Venta $venta)
    {
        // Por ahora no manejamos eliminación forzada de ventas
    }
}
