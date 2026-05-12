<?php

namespace App\Services\FidelizacionCliente;

use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Services\FidelizacionCliente\NotificacionPuntosService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ConsumoPuntosService
{
    /**
     * Tipo de fidelización a aplicar al cliente: el asignado en `id_tipo_cliente` o el default
     * de la empresa efectiva (empresa padre si el cliente es de una sucursal con licencia).
     * Debe coincidir con `Cliente::getTipoClienteEfectivo()` para que mínimo/máximo de canje
     * y reglas de puntos no diverjan del CRM ni de la facturación.
     */
    private function resolveTipoClienteParaCliente(\App\Models\Ventas\Clientes\Cliente $cliente): ?TipoClienteEmpresa
    {
        $tipo = $cliente->getTipoClienteEfectivo();
        if ($tipo && !$tipo->relationLoaded('tipoBase')) {
            $tipo->load('tipoBase');
        }

        return $tipo;
    }

    /**
     * Expuesto para otros servicios (p. ej. devoluciones) que deben usar la misma regla que las ventas.
     */
    public function obtenerTipoClienteEfectivo(\App\Models\Ventas\Clientes\Cliente $cliente): ?TipoClienteEmpresa
    {
        return $this->resolveTipoClienteParaCliente($cliente);
    }

    /**
     * Procesar la acumulación de puntos para una venta
     *
     * @param Venta $venta
     * @return bool
     */
    public function procesarAcumulacionPuntos(Venta $venta): bool
    {
        // Evitar procesar en contextos de testing o comandos específicos
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $command = request()->server('argv.1') ?? '';
            if (in_array($command, ['migrate', 'db:seed', 'queue:work', 'schedule:run'])) {
                return false;
            }
        }

        // Procesar solo si la venta está confirmada/completada
        if (in_array($venta->estado, ['Borrador', 'Cancelada', 'Anulada'])) {
            return false;
        }

        // Procesar la acumulación de puntos
        return $this->procesarAcumulacionPuntosSync($venta);
    }

    /**
     * Procesar la acumulación de puntos de forma síncrona
     *
     * @param Venta $venta
     * @return bool
     */
    private function procesarAcumulacionPuntosSync(Venta $venta): bool
    {
        // 1. Verificaciones básicas rápidas primero
        if (!$venta->id_cliente) {
            Log::debug('Venta sin cliente asignado, no se generan puntos', ['venta_id' => $venta->id]);
            return false;
        }

        // 2. Verificar que no se hayan generado puntos previamente (optimizado)
        if ($venta->puntos_ganados > 0) {
            Log::debug('Venta ya tiene puntos generados', ['venta_id' => $venta->id]);
            return false;
        }

        // 2b. Con canje de puntos en la misma venta no se acumulan puntos nuevos
        if ($venta->tieneCanjeDePuntosEnVenta()) {
            Log::debug('Venta con canje de puntos: no se acumulan puntos', ['venta_id' => $venta->id]);
            return false;
        }

        // 3. Verificar si ya existe una transacción de puntos para esta venta (más eficiente)
        $existeTransaccion = TransaccionPuntos::where('id_venta', $venta->id)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->exists();
            
        if ($existeTransaccion) {
            Log::debug('Ya existe transacción de puntos para esta venta', ['venta_id' => $venta->id]);
            return false;
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
            return false;
        }

        // 5. Obtener el cliente (empresa: necesaria para default vía empresa padre en licencias)
        $cliente = $venta->cliente()->with(['tipoCliente.tipoBase', 'empresa'])->first();
        if (!$cliente) {
            Log::warning('Cliente no encontrado para venta', ['venta_id' => $venta->id, 'cliente_id' => $venta->id_cliente]);
            return false;
        }

        // 6. Tipo efectivo = asignado o default de empresa padre (no `id_empresa` solo de la venta)
        $tipoCliente = $this->resolveTipoClienteParaCliente($cliente);

        if (!$tipoCliente) {
            Log::warning('No se pudo determinar tipo de cliente efectivo', [
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresaId
            ]);
            return false;
        }

        // 7. Calcular puntos basado en el monto total de la venta
        $puntosCalculados = $tipoCliente->calcularPuntos($venta->total);
        
        if ($puntosCalculados <= 0) {
            Log::debug('No se generan puntos para esta venta (puntos calculados: 0)', [
                'venta_id' => $venta->id,
                'monto' => $venta->total,
                'puntos_por_dolar' => $tipoCliente->puntos_por_dolar
            ]);
            return false;
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

            // Enviar notificación por email al cliente
            $this->enviarNotificacionPuntos($venta, $puntosCalculados);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al crear transacción de puntos', [
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
        $puntosCliente = PuntosCliente::where('id_cliente', $cliente->id)
            ->where('id_empresa', $venta->id_empresa)
            ->first();

        if (!$puntosCliente) {
            $puntosCliente = PuntosCliente::create([
                'id_cliente' => $cliente->id,
                'id_empresa' => $venta->id_empresa,
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
     * Verificar si la empresa tiene fidelización habilitada
     *
     * @param int $empresaId
     * @return bool
     */
    private function verificarFidelizacionHabilitada(int $empresaId): bool
    {
        return cache()->remember(
            "empresa_fidelizacion_{$empresaId}",
            now()->addMinutes(30),
            function () use ($empresaId) {
                $empresa = \App\Models\Admin\Empresa::find($empresaId);
                return $empresa && $empresa->tieneFidelizacionHabilitada();
            }
        );
    }

    /**
     * Procesar puntos de forma asíncrona
     *
     * @param Venta $venta
     * @return void
     */
    private function procesarPuntosAsync(Venta $venta)
    {
        // Aquí se podría implementar un job para procesar en cola
        // Por ahora, procesamos de forma síncrona pero con logging
        Log::info('Procesando puntos de forma asíncrona para venta', ['venta_id' => $venta->id]);
        $this->procesarAcumulacionPuntosSync($venta);
    }

    /**
     * Canjear puntos por descuento siguiendo lógica FIFO
     *
     * @param int $clienteId
     * @param int $empresaId
     * @param int $puntosACanjear
     * @param string $descripcion
     * @param string $idempotencyToken Token de idempotencia opcional desde frontend
     * @return array
     */
    public function canjearPuntos(int $clienteId, int $empresaId, int $puntosACanjear, string $descripcion = null, string $idempotencyToken = null): array
    {
        try {
            // 1. Verificar que la empresa tiene fidelización habilitada
            $empresa = \App\Models\Admin\Empresa::find($empresaId);
            if (!$empresa || !$empresa->tieneFidelizacionHabilitada()) {
                return ['success' => false, 'error' => 'La empresa no tiene habilitado el módulo de fidelización'];
            }

            // 2. Cliente + empresa (misma resolución de tipo que CRM / getTipoClienteEfectivo)
            $cliente = \App\Models\Ventas\Clientes\Cliente::with(['tipoCliente.tipoBase', 'empresa'])
                ->find($clienteId);
            if (!$cliente) {
                return ['success' => false, 'error' => 'Cliente no encontrado'];
            }

            // 3. Configuración de canje según tipo efectivo (no el default de id_empresa de la sucursal)
            $tipoCliente = $this->resolveTipoClienteParaCliente($cliente);

            if (!$tipoCliente) {
                return ['success' => false, 'error' => 'No se pudo determinar la configuración del cliente'];
            }

            // 4. Validaciones básicas con configuración del tipo de cliente
            if ($puntosACanjear <= 0) {
                return ['success' => false, 'error' => 'La cantidad de puntos debe ser mayor a 0'];
            }

            if ($puntosACanjear < $tipoCliente->minimo_canje) {
                return [
                    'success' => false, 
                    'error' => "El mínimo de canje para este tipo de cliente es {$tipoCliente->minimo_canje} puntos",
                    'minimo_canje' => $tipoCliente->minimo_canje
                ];
            }

            if ($puntosACanjear > $tipoCliente->maximo_canje) {
                return [
                    'success' => false,
                    'error' => "El máximo de canje para este tipo de cliente es {$tipoCliente->maximo_canje} puntos",
                    'maximo_canje' => $tipoCliente->maximo_canje
                ];
            }

            // 5. Procesar el canje en una transacción (verificación de saldo dentro con lock)
            return DB::transaction(function () use ($clienteId, $empresaId, $puntosACanjear, $descripcion, $tipoCliente, $idempotencyToken) {
                // Obtener el cliente con bloqueo exclusivo para evitar race conditions
                $puntosCliente = PuntosCliente::where('id_cliente', $clienteId)
                    ->where('id_empresa', $empresaId)
                    ->lockForUpdate()
                    ->first();

                // Verificar saldo disponible DENTRO de la transacción con lock
                if (!$puntosCliente || $puntosCliente->puntos_disponibles < $puntosACanjear) {
                    return [
                        'success' => false,
                        'error' => 'Puntos insuficientes',
                        'puntos_disponibles' => $puntosCliente ? $puntosCliente->puntos_disponibles : 0,
                        'puntos_solicitados' => $puntosACanjear
                    ];
                }

                return $this->procesarCanjeConFifo($clienteId, $empresaId, $puntosACanjear, $descripcion, $puntosCliente, $tipoCliente, $idempotencyToken);
            });

        } catch (\Exception $e) {
            Log::error('Error al canjear puntos', [
                'cliente_id' => $clienteId,
                'empresa_id' => $empresaId,
                'puntos_solicitados' => $puntosACanjear,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['success' => false, 'error' => 'Error interno al procesar el canje'];
        }
    }

    /**
     * Procesar el canje aplicando lógica FIFO
     *
     * @param int $clienteId
     * @param int $empresaId
     * @param int $puntosACanjear
     * @param string $descripcion
     * @param PuntosCliente $puntosCliente
     * @param TipoClienteEmpresa $tipoCliente
     * @param string $idempotencyToken
     * @return array
     */
    private function procesarCanjeConFifo(int $clienteId, int $empresaId, int $puntosACanjear, ?string $descripcion, PuntosCliente $puntosCliente, TipoClienteEmpresa $tipoCliente, ?string $idempotencyToken): array
    {
        // 1. Obtener ganancias disponibles ordenadas por FIFO con bloqueo exclusivo
        $gananciasDisponibles = TransaccionPuntos::where('id_cliente', $clienteId)
            ->where('id_empresa', $empresaId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->where('fecha_expiracion', '>=', now()->toDateString()) // Solo no expiradas
            ->whereRaw('puntos - puntos_consumidos > 0') // Solo con puntos disponibles
            ->orderBy('fecha_expiracion', 'asc') // Primero las que expiran pronto
            ->orderBy('created_at', 'asc') // Luego por orden de creación (FIFO)
            ->orderBy('id', 'asc') // Orden determinístico final para evitar deadlocks
            ->lockForUpdate() // Bloqueo exclusivo para evitar race conditions
            ->get();

        if ($gananciasDisponibles->isEmpty()) {
            return ['success' => false, 'error' => 'No hay puntos disponibles para canjear'];
        }

        // 2. Crear la transacción de canje
        $puntosAntes = $puntosCliente->puntos_disponibles;
        $puntosDespues = $puntosAntes - $puntosACanjear;

        // Generar clave de idempotencia
        // Si el frontend envía un token, usarlo directamente para idempotencia real
        // Si no, generar UUID único (sin protección de duplicados - responsabilidad del frontend)
        if ($idempotencyToken) {
            $idempotencyKey = "canje_{$clienteId}_{$empresaId}_{$idempotencyToken}";

            // Verificar si ya existe un canje con este token (protección anti-duplicados)
            $canjeExistente = TransaccionPuntos::where('idempotency_key', $idempotencyKey)->first();

            if ($canjeExistente) {
                Log::info('Canje duplicado detectado por idempotency_token', [
                    'cliente_id' => $clienteId,
                    'empresa_id' => $empresaId,
                    'idempotency_token' => $idempotencyToken,
                    'canje_existente_id' => $canjeExistente->id
                ]);

                // Devolver el canje existente como exitoso (idempotencia correcta)
                return [
                    'success' => true,
                    'transaccion_id' => $canjeExistente->id,
                    'puntos_canjeados' => abs($canjeExistente->puntos),
                    'valor_descuento' => $canjeExistente->monto_asociado,
                    'puntos_despues' => $canjeExistente->puntos_despues,
                    'mensaje' => 'Canje procesado previamente (idempotente)',
                    'duplicado' => true
                ];
            }
        } else {
            // Sin token: generar UUID único (sin protección, cada request es considerado único)
            $uuid = \Illuminate\Support\Str::uuid();
            $idempotencyKey = "canje_{$clienteId}_{$empresaId}_{$uuid}";
        }

        // Calcular el valor del descuento usando la configuración del tipo de cliente
        $valorDescuento = $tipoCliente->calcularDescuento($puntosACanjear);

        $transaccionCanje = TransaccionPuntos::create([
            'id_cliente' => $clienteId,
            'id_empresa' => $empresaId,
            'id_venta' => null, // No está asociado a una venta específica
            'tipo' => TransaccionPuntos::TIPO_CANJE,
            'puntos' => -$puntosACanjear, // Negativo para indicar que se restan
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'monto_asociado' => $valorDescuento, // Valor del descuento calculado
            'puntos_consumidos' => 0, // No aplica para canjes
            'descripcion' => $descripcion ?: "Canje de {$puntosACanjear} puntos por \${$valorDescuento}",
            'fecha_expiracion' => null, // No aplica para canjes
            'idempotency_key' => $idempotencyKey
        ]);

        // 3. Aplicar FIFO: consumir puntos de las ganancias más antiguas
        $puntosRestantes = $puntosACanjear;
        $detallesConsumo = [];

        foreach ($gananciasDisponibles as $ganancia) {
            if ($puntosRestantes <= 0) break;

            $puntosDisponiblesEnGanancia = $ganancia->getPuntosDisponibles();
            $puntosAConsumir = min($puntosRestantes, $puntosDisponiblesEnGanancia);

            if ($puntosAConsumir > 0) {
                // Actualizar la ganancia
                $ganancia->increment('puntos_consumidos', $puntosAConsumir);

                // Crear registro en consumo_puntos para trazabilidad
                $consumo = \App\Models\FidelizacionClientes\ConsumoPuntos::create([
                    'id_empresa' => $empresaId,
                    'id_cliente' => $clienteId,
                    'id_canje_tx' => $transaccionCanje->id,
                    'id_ganancia_tx' => $ganancia->id,
                    'puntos_consumidos' => $puntosAConsumir,
                    'descripcion' => "Consumo FIFO - Ganancia del " . $ganancia->created_at->format('Y-m-d')
                ]);

                $detallesConsumo[] = [
                    'ganancia_id' => $ganancia->id,
                    'fecha_ganancia' => $ganancia->created_at,
                    'fecha_expiracion' => $ganancia->fecha_expiracion,
                    'puntos_consumidos' => $puntosAConsumir,
                    'puntos_restantes_en_ganancia' => $ganancia->getPuntosDisponibles()
                ];

                $puntosRestantes -= $puntosAConsumir;
            }
        }

        // 4. Actualizar el saldo consolidado del cliente
        $puntosCliente->decrement('puntos_disponibles', $puntosACanjear);
        $puntosCliente->increment('puntos_totales_canjeados', $puntosACanjear);
        $puntosCliente->update(['fecha_ultima_actividad' => now()]);

        Log::info('Canje de puntos procesado exitosamente', [
            'cliente_id' => $clienteId,
            'empresa_id' => $empresaId,
            'transaccion_canje_id' => $transaccionCanje->id,
            'puntos_canjeados' => $puntosACanjear,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'ganancias_afectadas' => count($detallesConsumo)
        ]);

        return [
            'success' => true,
            'transaccion_id' => $transaccionCanje->id,
            'puntos_canjeados' => $puntosACanjear,
            'valor_descuento' => $valorDescuento,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'detalles_consumo' => $detallesConsumo,
            'tipo_cliente' => $tipoCliente->nombre_efectivo,
            'configuracion' => [
                'valor_punto' => $tipoCliente->valor_punto,
                'minimo_canje' => $tipoCliente->minimo_canje,
                'maximo_canje' => $tipoCliente->maximo_canje
            ],
            'mensaje' => "Se canjearon {$puntosACanjear} puntos por \${$valorDescuento} de descuento"
        ];
    }

    /**
     * Obtener información de puntos disponibles para canje
     *
     * @param int $clienteId
     * @param int $empresaId
     * @return array
     */
    public function obtenerInformacionPuntosDisponibles(int $clienteId, int $empresaId): array
    {
        // Verificar que la empresa tiene fidelización habilitada
        $empresa = \App\Models\Admin\Empresa::find($empresaId);
        if (!$empresa || !$empresa->tieneFidelizacionHabilitada()) {
            return [
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'ganancias_detalle' => [],
                'configuracion' => null,
                'error' => 'Empresa no tiene fidelización habilitada'
            ];
        }

        $cliente = \App\Models\Ventas\Clientes\Cliente::with(['tipoCliente.tipoBase', 'empresa'])
            ->find($clienteId);
        $tipoCliente = $cliente ? $this->resolveTipoClienteParaCliente($cliente) : null;

        $puntosCliente = PuntosCliente::where('id_cliente', $clienteId)
            ->where('id_empresa', $empresaId)
            ->first();

        if (!$puntosCliente) {
            return [
                'puntos_disponibles' => 0,
                'puntos_totales_ganados' => 0,
                'puntos_totales_canjeados' => 0,
                'ganancias_detalle' => [],
                'configuracion' => $tipoCliente ? [
                    'valor_punto' => $tipoCliente->valor_punto,
                    'minimo_canje' => $tipoCliente->minimo_canje,
                    'maximo_canje' => $tipoCliente->maximo_canje,
                    'tipo_cliente' => $tipoCliente->nombre_efectivo,
                    'nivel' => $tipoCliente->nivel
                ] : null
            ];
        }

        // Obtener detalle de ganancias disponibles
        $gananciasDisponibles = TransaccionPuntos::where('id_cliente', $clienteId)
            ->where('id_empresa', $empresaId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->where('fecha_expiracion', '>=', now()->toDateString())
            ->whereRaw('puntos - puntos_consumidos > 0')
            ->orderBy('fecha_expiracion', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($ganancia) {
                return [
                    'id' => $ganancia->id,
                    'puntos_originales' => $ganancia->puntos,
                    'puntos_disponibles' => $ganancia->getPuntosDisponibles(),
                    'fecha_ganancia' => $ganancia->created_at,
                    'fecha_expiracion' => $ganancia->fecha_expiracion,
                    'dias_para_expirar' => now()->diffInDays($ganancia->fecha_expiracion, false),
                    'venta_id' => $ganancia->id_venta
                ];
            });

        return [
            'puntos_disponibles' => $puntosCliente->puntos_disponibles,
            'puntos_totales_ganados' => $puntosCliente->puntos_totales_ganados,
            'puntos_totales_canjeados' => $puntosCliente->puntos_totales_canjeados,
            'ganancias_detalle' => $gananciasDisponibles,
            'configuracion' => $tipoCliente ? [
                'valor_punto' => $tipoCliente->valor_punto,
                'minimo_canje' => $tipoCliente->minimo_canje,
                'maximo_canje' => $tipoCliente->maximo_canje,
                'tipo_cliente' => $tipoCliente->nombre_efectivo,
                'nivel' => $tipoCliente->nivel,
                'puntos_por_dolar' => $tipoCliente->puntos_por_dolar
            ] : null
        ];
    }

    /**
     * Enviar notificación de puntos ganados por email
     *
     * @param Venta $venta
     * @param int $puntosGanados
     * @return void
     */
    private function enviarNotificacionPuntos(Venta $venta, int $puntosGanados): void
    {
        try {
            $notificacionService = new NotificacionPuntosService();
            
            // Verificar si se debe enviar notificación
            if ($notificacionService->debeEnviarNotificacion($venta->id_empresa)) {
                $notificacionService->enviarNotificacionPuntosGanados($venta, $puntosGanados);
            }
        } catch (\Exception $e) {
            // Log del error pero no interrumpir el proceso de puntos
            Log::error('Error al enviar notificación de puntos', [
                'venta_id' => $venta->id,
                'puntos_ganados' => $puntosGanados,
                'error' => $e->getMessage()
            ]);
        }
    }
}
