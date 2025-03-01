<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateWooCommerceApiKeys extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'woocommerce:generate-keys 
                            {--empresa= : ID de empresa específica (opcional)}
                            {--force : Sobrescribir claves existentes}
                            {--active-only : Solo generar para empresas activas}';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Genera claves API de WooCommerce para empresas';

    /**
     * Ejecutar el comando de consola.
     *
     * @return int
     */
    public function handle()
    {
        // Determinar qué empresas actualizar
        if ($this->option('empresa')) {
            $empresaId = $this->option('empresa');
            $empresas = Empresa::where('id', $empresaId)->get();
            
            if ($empresas->isEmpty()) {
                $this->error("No se encontró la empresa con ID: {$empresaId}");
                return 1;
            }
        } else {
            $query = Empresa::query();
            
            if ($this->option('active-only')) {
                $query->where('activo', 1);
            }
            
            $empresas = $query->get();
            
            if ($empresas->isEmpty()) {
                $this->error("No se encontraron empresas");
                return 1;
            }
            
            if (!$this->option('force') && !$this->confirm('¿Estás seguro de generar claves API para ' . $empresas->count() . ' empresas?')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }
        
        $bar = $this->output->createProgressBar(count($empresas));
        $bar->start();
        
        $generatedCount = 0;
        $skippedCount = 0;
        
        foreach ($empresas as $empresa) {
            // Verificar si ya tiene una clave y si debemos sobrescribirla
            if ($empresa->woocommerce_api_key && !$this->option('force')) {
                $this->line("\nEmpresa {$empresa->name} ya tiene clave API. Usa --force para sobrescribir.");
                $skippedCount++;
            } else {
                // Generar clave única
                $apiKey = $this->generateUniqueApiKey();
                $empresa->woocommerce_api_key = $apiKey;
                $empresa->save();
                
                $generatedCount++;
                $this->line("\nGenerada clave para {$empresa->name}: {$apiKey}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        
        $this->newLine(2);
        $this->info("Proceso completado: {$generatedCount} claves generadas, {$skippedCount} omitidas.");
        
        return 0;
    }
    
    /**
     * Genera una clave API única verificando que no exista en la base de datos
     *
     * @return string
     */
    protected function generateUniqueApiKey()
    {
        do {
            $key = Str::random(64);
            $exists = Empresa::where('woocommerce_api_key', $key)->exists();
        } while ($exists);
        
        return $key;
    }
}