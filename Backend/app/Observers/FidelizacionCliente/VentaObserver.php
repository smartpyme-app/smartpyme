<?php

namespace App\Observers\FidelizacionCliente;

use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class VentaObserver
{
    /**
     * Handle the Venta "created" event.
     *
     * @param  \App\Models\Ventas\Venta  $venta
     * @return void
     */
    public function created(Venta $venta)
    {
        // Evitar procesar en contextos de testing o comandos específicos
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $command = request()->server('argv.1') ?? '';
            if (in_array($command, ['migrate', 'db:seed', 'queue:work', 'schedule:run'])) {
                return;
            }
        }

        // Procesar solo si la venta está confirmada/completada
        if (in_array($venta->estado, ['Borrador', 'Cancelada', 'Anulada'])) {
            return;
        }

        try {
            // Verificar si el sistema está bajo alta carga
            if ($this->shouldProcessAsync()) {
                $this->procesarPuntosAsync($venta);
            } else {
                $this->procesarAcumulacionPuntos($venta);
            }
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
        // 1. Verificaciones básicas rápidas primero
        if (!$venta->id_cliente) {
            Log::debug('Venta sin cliente asignado, no se generan puntos', ['venta_id' => $venta->id]);
            return;
        }

        // 2. Verificar que no se hayan generado puntos previamente (optimizado)
        if ($venta->puntos_ganados > 0) {
            Log::debug('Venta ya tiene puntos generados', ['venta_id' => $venta->id]);
            return;
        }

        // 2b. Con canje de puntos en la misma venta no se acumulan puntos nuevos
        if ($venta->tieneCanjeDePuntosEnVenta()) {
            Log::debug('Venta con canje de puntos: no se acumulan puntos', ['venta_id' => $venta->id]);
            return;
        }

        // 3. Verificar si ya existe una transacción de puntos para esta venta (más eficiente)
        $existeTransaccion = TransaccionPuntos::where('id_venta', $venta->id)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->exists();
            
        if ($existeTransaccion) {
            Log::debug('Ya existe transacción de puntos para esta venta', ['venta_id' => $venta->id]);
            return;
        }

        // 4. Verificar si la empresa tiene fidelización habilitada (con cache)
        $empresaId = $venta->id_empresa;
        Log::info('Empresa ID: ' . $empresaId);
        Log::info("Iniciando verificación de fidelización para empresa", ['empresa_id' => $empresaId]);

        $tieneFidelizacion = $this->verificarFidelizacionHabilitada($empresaId);

        Log::info("Resultado final de tieneFidelizacion", [
            'empresa_id' => $empresaId,
            'tieneFidelizacion' => $tieneFidelizacion
        ]);

        if (!$tieneFidelizacion) {
            Log::debug('Empresa no tiene fidelización habilitada', ['empresa_id' => $empresaId]);
            return;
        }

        // 5. Obtener el cliente con su tipo de cliente (eager loading)
        $cliente = $venta->cliente()->with('tipoCliente.tipoBase')->first();
        if (!$cliente) {
            Log::warning('Cliente no encontrado para venta', ['venta_id' => $venta->id, 'cliente_id' => $venta->id_cliente]);
            return;
        }

        // 6. Obtener tipo de cliente efectivo (optimizado)
        $tipoCliente = $cliente->tipoCliente;
        if (!$tipoCliente) {
            // Obtener tipo por defecto de la empresa (con cache)
            // Si la empresa tiene licencia, usar la configuración de la empresa padre
            $empresa = \App\Models\Admin\Empresa::find($empresaId);
            $empresaEfectivaId = $empresaId;
            
            if ($empresa && $empresa->esEmpresaHija()) {
                $empresaEfectivaId = $empresa->getEmpresaPadre()->id;
            }
            
            $tipoCliente = cache()->remember(
                "empresa_tipo_default_{$empresaEfectivaId}",
                now()->addMinutes(60),
                function () use ($empresaEfectivaId) {
                    return TipoClienteEmpresa::where('id_empresa', $empresaEfectivaId)
                        ->where('is_default', true)
                        ->with('tipoBase')
                        ->first();
                }
            );
        }

        if (!$tipoCliente) {
            Log::warning('No se pudo determinar tipo de cliente efectivo', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresaId
            ]);
            return;
        }

        // 7. Calcular puntos basado en el monto total de la venta
        $puntosCalculados = $tipoCliente->calcularPuntos($venta->total);
        
        if ($puntosCalculados <= 0) {
            Log::debug('No se generan puntos para esta venta (puntos calculados: 0)', [
                'venta_id' => $venta->id,
                'monto' => $venta->total,
                'puntos_por_dolar' => $tipoCliente->puntos_por_dolar
            ]);
            return;
        }

        // 8. Procesar la acumulación en una transacción de base de datos
        try {
            DB::transaction(function () use ($venta, $cliente, $tipoCliente, $puntosCalculados) {
                $this->crearTransaccionPuntos($venta, $cliente, $tipoCliente, $puntosCalculados);
            });

            Log::info('Puntos acumulados exitosamente', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'puntos_generados' => $puntosCalculados,
                'tipo_cliente' => $tipoCliente->nombre_efectivo
            ]);
        } catch (\Exception $e) {
            Log::error('Error en transacción de puntos', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
        // Obtener o crear registro de puntos del cliente (optimizado)
        // Si la empresa tiene licencia, los puntos se acumulan en la empresa padre
        $empresa = \App\Models\Admin\Empresa::find($venta->id_empresa);
        $empresaPuntosId = $venta->id_empresa;
        
        if ($empresa && $empresa->esEmpresaHija()) {
            $empresaPuntosId = $empresa->getEmpresaPadre()->id;
        }
        
        $puntosCliente = PuntosCliente::where('id_cliente', $cliente->id)
            ->where('id_empresa', $empresaPuntosId)
            ->first();

        if (!$puntosCliente) {
            $puntosCliente = PuntosCliente::create([
                'id_cliente' => $cliente->id,
                'id_empresa' => $empresaPuntosId,
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'fecha_ultima_actividad' => now()
            ]);
        }

        $puntosAntes = $puntosCliente->puntos_disponibles;
        $puntosDespues = $puntosAntes + $puntosCalculados;

        // Generar clave de idempotencia
        $idempotencyKey = TransaccionPuntos::generarIdempotencyKey(
            $cliente->id,
            TransaccionPuntos::TIPO_GANANCIA,
            $venta->id
        );

        // Verificar duplicados por idempotencia antes de crear
        $transaccionExistente = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->first();
        if ($transaccionExistente) {
            Log::warning('Transacción duplicada detectada por idempotencia', [
                'venta_id' => $venta->id,
                'idempotency_key' => $idempotencyKey
            ]);
            return;
        }

        // Crear transacción de puntos
        $transaccion = TransaccionPuntos::create([
            'id_cliente' => $cliente->id,
            'id_empresa' => $empresaPuntosId, // Usar la empresa padre si hay licencia
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

        // Actualizar saldo consolidado en puntos_cliente (usando update directo para mejor rendimiento)
        $puntosCliente->increment('puntos_disponibles', $puntosCalculados);
        $puntosCliente->increment('puntos_totales_ganados', $puntosCalculados);
        $puntosCliente->update(['fecha_ultima_actividad' => now()]);

        // Actualizar campo puntos_ganados en la venta (usando update directo)
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
     * @param  \App\Models\Ventas\Venta  $venta
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
     * @param  \App\Models\Ventas\Venta  $venta
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
     * @param  \App\Models\Ventas\Venta  $venta
     * @return void
     */
    public function restored(Venta $venta)
    {
        // Por ahora no manejamos restauración de ventas
    }

    /**
     * Handle the Venta "force deleted" event.
     *
     * @param  \App\Models\Ventas\Venta  $venta
     * @return void
     */
    public function forceDeleted(Venta $venta)
    {
        // Por ahora no manejamos eliminación forzada de ventas
    }

    /**
     * Determinar si se debe procesar de forma asíncrona
     *
     * @return bool
     */
    private function shouldProcessAsync()
    {
        // Verificar si hay muchas conexiones activas a la base de datos
        $activeConnections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
        
        // Si hay más de 50 conexiones activas, procesar async
        if ($activeConnections > 50) {
            return true;
        }

        // Verificar si el tiempo de respuesta promedio es alto
        $avgResponseTime = cache()->get('avg_response_time', 0);
        if ($avgResponseTime > 2000) { // 2 segundos
            return true;
        }

        return false;
    }

    /**
     * Procesar puntos de forma asíncrona
     *
     * @param Venta $venta
     * @return void
     */
    private function procesarPuntosAsync(Venta $venta)
    {
        // Crear un job para procesar los puntos más tarde
        Log::info('Procesando puntos de forma asíncrona', ['venta_id' => $venta->id]);
        
        // Por ahora, simplemente marcamos que se procesará después
        // En una implementación completa, aquí se crearía un Job
        cache()->put("puntos_pendientes_{$venta->id}", [
            'venta_id' => $venta->id,
            'created_at' => now()
        ], now()->addHours(1));
    }

    /**
     * Verificar si la empresa tiene fidelización habilitada con cache
     *
     * @param int $empresaId
     * @return bool
     */
    private function verificarFidelizacionHabilitada($empresaId)
    {
        $cacheKey = "empresa_fidelizacion_{$empresaId}";
        
        // Primero intentar obtener del cache
        $cachedValue = cache()->get($cacheKey);
        
        // Si no hay cache o es false, verificar directamente en la base de datos
        if ($cachedValue === null || $cachedValue === false) {
            Log::info("Consultando EmpresaFuncionalidad para empresa (sin cache)", ['empresa_id' => $empresaId]);
            
            $query = \App\Models\Admin\EmpresaFuncionalidad::where('id_empresa', $empresaId)
                ->whereHas('funcionalidad', function($query) {
                    Log::info("Filtrando funcionalidad por slug 'fidelizacion-clientes'");
                    $query->where('slug', 'fidelizacion-clientes');
                })
                ->where('activo', true);

            $existe = $query->exists();
            
            Log::info("Resultado de existencia de funcionalidad de fidelización", [
                'empresa_id' => $empresaId,
                'existe' => $existe
            ]);
            
            // Solo cachear si el resultado es true, para evitar cachear falsos negativos
            if ($existe) {
                cache()->put($cacheKey, true, now()->addMinutes(30));
            }
            
            return $existe;
        }
        
        // Si hay cache y es true, usar el valor cacheado
        Log::info("Usando valor del cache para empresa", [
            'empresa_id' => $empresaId,
            'cached_value' => $cachedValue
        ]);
        
        return $cachedValue;
    }

    /**
     * Método para procesar puntos pendientes (puede ser llamado por un comando)
     *
     * @return void
     */
    public static function procesarPuntosPendientes()
    {
        $keys = cache()->get('puntos_pendientes_*');
        foreach ($keys as $key) {
            $data = cache()->get($key);
            if ($data) {
                $venta = Venta::find($data['venta_id']);
                if ($venta) {
                    $observer = new self();
                    $observer->procesarAcumulacionPuntos($venta);
                    cache()->forget($key);
                }
            }
        }
    }
}
