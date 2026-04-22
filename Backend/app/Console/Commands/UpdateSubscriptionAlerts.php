<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Suscripcion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateSubscriptionAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:update-alerts {--dry-run : Ejecutar sin hacer cambios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza alerta_suscripcion (banners: admin días 3 y 1; todos 0..-N; bloqueo -N-1)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando actualización de alertas de suscripción...');
        
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: No se realizarán cambios en la base de datos');
        }

        try {
            DB::beginTransaction();

            // Obtener todas las empresas que podrían tener suscripciones activas
            // Obtener todas las empresas que tienen suscripciones (sin importar el estado)
            $empresasConSuscripcion = Empresa::whereHas('suscripciones')->get();

            $this->info("📊 Total empresas encontradas con suscripciones: " . $empresasConSuscripcion->count());

            $empresasConAlerta = 0;
            $empresasSinAlerta = 0;
            $empresasSinSuscripcion = 0;

            foreach ($empresasConSuscripcion as $empresa) {
                $this->line("🏢 Procesando empresa: {$empresa->nombre} (ID: {$empresa->id})");
                
                $suscripcionActiva = $empresa->suscripcionActivaCommand();
                
                if (!$suscripcionActiva) {
                    $this->line("❌ No tiene suscripción activa");
                    $empresasSinSuscripcion++;
                    continue;
                }

                $this->line("✅ Suscripción encontrada - Estado: {$suscripcionActiva->estado}");
                $this->line("📅 Fecha próximo pago: {$suscripcionActiva->fecha_proximo_pago}");

                // Siempre usar fecha_proximo_pago sin importar el estado
                $diasFaltantes = $suscripcionActiva->diasFaltantes();
                
                $this->line("⏰ Días faltantes calculados: " . ($diasFaltantes ?? 'NULL'));
                
                // Si no podemos calcular días faltantes, skip
                if ($diasFaltantes === null) {
                    $this->line("⚠️ No se pudo calcular días faltantes");
                    continue;
                }

                $debeActivarAlerta = false;

                // Tabla de banners: admin en dias_faltantes 3 y 1; cualquier día de vencimiento o mora (<=0); no banner en 2,4,5…
                $debeActivarAlerta = \in_array($diasFaltantes, [3, 1], true)
                    || $diasFaltantes <= 0;

                // Debug: Mostrar información aunque no cambie el estado
                $estadoActual = $empresa->alerta_suscripcion ? 'ACTIVADA' : 'DESACTIVADA';
                $this->line("🔍 Debug - Empresa: {$empresa->nombre} - Días: {$diasFaltantes} - Alerta actual: {$estadoActual} - Debe activar: " . ($debeActivarAlerta ? 'SÍ' : 'NO'));

                // Solo actualizar si el estado cambió
                if ($empresa->alerta_suscripcion != $debeActivarAlerta) {
                    if (!$dryRun) {
                        $empresa->update(['alerta_suscripcion' => $debeActivarAlerta ? 1 : 0]);
                    }

                    if ($debeActivarAlerta) {
                        $empresasConAlerta++;
                        $estado = $diasFaltantes < 0 ? 'VENCIDA' : "PRÓXIMA A VENCER";
                        $this->line("✅ Empresa: {$empresa->nombre} - Días faltantes: {$diasFaltantes} - Estado: {$estado}");
                    } else {
                        $empresasSinAlerta++;
                        $this->line("ℹ️  Empresa: {$empresa->nombre} - Alerta desactivada - Días faltantes: {$diasFaltantes}");
                    }
                } else {
                    $this->line("➖ Sin cambios para {$empresa->nombre}");
                }
            }

            // También revisar empresas que actualmente tienen alerta activada pero no tienen suscripciones
            $empresasSinSuscripcion = Empresa::where('alerta_suscripcion', 1)
                ->whereDoesntHave('suscripciones')
                ->get();

            foreach ($empresasSinSuscripcion as $empresa) {
                if (!$dryRun) {
                    $empresa->update(['alerta_suscripcion' => 0]);
                }
                $empresasSinAlerta++;
                $this->line("⚠️  Empresa: {$empresa->nombre} - Sin suscripciones - Alerta desactivada");
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            // Resumen
            $this->newLine();
            $this->info('=== RESUMEN ===');
            $this->info("Empresas con alerta activada: {$empresasConAlerta}");
            $this->info("Empresas con alerta desactivada: {$empresasSinAlerta}");
            $this->info("Empresas sin suscripción activa: {$empresasSinSuscripcion}");
            
            if ($dryRun) {
                $this->warn('Ningún cambio fue aplicado (modo dry-run)');
            } else {
                $this->info('✅ Actualización completada exitosamente');
            }

            // Log para auditoría
            Log::info('Comando subscription:update-alerts ejecutado', [
                'empresas_con_alerta' => $empresasConAlerta,
                'empresas_sin_alerta' => $empresasSinAlerta,
                'empresas_sin_suscripcion' => $empresasSinSuscripcion,
                'dry_run' => $dryRun
            ]);

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error al actualizar alertas de suscripción: ' . $e->getMessage());
            Log::error('Error en comando subscription:update-alerts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}