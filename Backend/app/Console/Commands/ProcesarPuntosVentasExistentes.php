<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TipoClienteEmpresa;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesarPuntosVentasExistentes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fidelizacion:procesar-ventas-existentes {--empresa=} {--limite=100} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa las ventas existentes que no tienen puntos generados para generar puntos de fidelización';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $empresaId = $this->option('empresa');
        $limite = (int) $this->option('limite');
        $dryRun = $this->option('dry-run');

        $this->info('🚀 Procesando ventas existentes para generar puntos de fidelización...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios en la base de datos');
            $this->newLine();
        }

        // Construir query para ventas sin puntos generados
        $query = Venta::whereHas('cliente')
            ->whereHas('empresa', function($q) {
                $q->where('fidelizacion_habilitada', true);
            })
            ->where(function($q) {
                $q->whereNull('puntos_ganados')
                  ->orWhere('puntos_ganados', 0);
            })
            ->where('estado', 'Pagada')
            ->whereNotNull('id_cliente');

        if ($empresaId) {
            $query->where('id_empresa', $empresaId);
        }

        $ventas = $query->with(['cliente', 'empresa', 'cliente.tipoCliente.tipoBase'])
            ->orderBy('created_at', 'asc')
            ->limit($limite)
            ->get();

        if ($ventas->isEmpty()) {
            $this->info('✅ No se encontraron ventas pendientes de procesar.');
            return Command::SUCCESS;
        }

        $this->info("📊 Ventas a procesar: {$ventas->count()}");
        $this->newLine();

        $procesadas = 0;
        $errores = 0;
        $sinPuntos = 0;

        $bar = $this->output->createProgressBar($ventas->count());
        $bar->start();

        foreach ($ventas as $venta) {
            try {
                $resultado = $this->procesarVenta($venta, $dryRun);
                
                if ($resultado['success']) {
                    $procesadas++;
                    if ($resultado['puntos'] > 0) {
                        $this->line("✓ Venta {$venta->id}: {$resultado['puntos']} puntos generados");
                    } else {
                        $sinPuntos++;
                    }
                } else {
                    $errores++;
                    $this->error("✗ Venta {$venta->id}: {$resultado['error']}");
                }
            } catch (\Exception $e) {
                $errores++;
                $this->error("✗ Venta {$venta->id}: Error inesperado - {$e->getMessage()}");
                Log::error("Error procesando venta {$venta->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Resumen
        $this->info('📈 RESUMEN:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Ventas procesadas', $procesadas],
                ['Ventas sin puntos', $sinPuntos],
                ['Errores', $errores],
                ['Total procesadas', $ventas->count()]
            ]
        );

        if ($dryRun) {
            $this->warn('⚠️  Este fue un DRY-RUN. Ejecuta sin --dry-run para aplicar los cambios.');
        }

        return Command::SUCCESS;
    }

    /**
     * Procesar una venta individual
     */
    private function procesarVenta(Venta $venta, bool $dryRun = false): array
    {
        // 1. Verificar si la empresa tiene fidelización habilitada
        if (!$venta->empresa->tieneFidelizacionHabilitada()) {
            return ['success' => false, 'error' => 'Empresa sin fidelización habilitada'];
        }

        // 2. Verificar que la venta tenga cliente asignado
        if (!$venta->id_cliente) {
            return ['success' => false, 'error' => 'Venta sin cliente asignado'];
        }

        // 3. Verificar que no se hayan generado puntos previamente
        if ($venta->tienePuntosGenerados()) {
            return ['success' => false, 'error' => 'Venta ya tiene puntos generados'];
        }

        // 4. Obtener el cliente y su tipo efectivo
        $cliente = $venta->cliente;
        if (!$cliente) {
            return ['success' => false, 'error' => 'Cliente no encontrado'];
        }

        $tipoCliente = $cliente->getTipoClienteEfectivo();
        if (!$tipoCliente) {
            return ['success' => false, 'error' => 'No se pudo determinar tipo de cliente efectivo'];
        }

        // 5. Calcular puntos basado en el monto total de la venta
        $puntosCalculados = $tipoCliente->calcularPuntos($venta->total);
        
        if ($puntosCalculados <= 0) {
            return ['success' => true, 'puntos' => 0, 'message' => 'No se generan puntos para esta venta'];
        }

        if ($dryRun) {
            return ['success' => true, 'puntos' => $puntosCalculados, 'message' => 'DRY-RUN: Puntos calculados'];
        }

        // 6. Procesar la acumulación en una transacción de base de datos
        try {
            DB::transaction(function () use ($venta, $cliente, $tipoCliente, $puntosCalculados) {
                $this->crearTransaccionPuntos($venta, $cliente, $tipoCliente, $puntosCalculados);
            });

            return ['success' => true, 'puntos' => $puntosCalculados, 'message' => 'Puntos generados exitosamente'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error en transacción: ' . $e->getMessage()];
        }
    }

    /**
     * Crear la transacción de puntos y actualizar saldos
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

        Log::info('Transacción de puntos creada para venta existente', [
            'venta_id' => $venta->id,
            'cliente_id' => $cliente->id,
            'puntos_generados' => $puntosCalculados,
            'puntos_antes' => $puntosAntes,
            'puntos_despues' => $puntosDespues,
            'fecha_expiracion' => $transaccion->fecha_expiracion
        ]);
    }
}
