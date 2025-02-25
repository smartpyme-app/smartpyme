<?php

namespace App\Console\Commands;

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
                            {--user= : ID de usuario específico (opcional)}
                            {--force : Sobrescribir claves existentes}
                            {--active-only : Solo generar para usuarios activos}';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Genera claves API de WooCommerce para usuarios';

    /**
     * Ejecutar el comando de consola.
     *
     * @return int
     */
    public function handle()
    {
        // Determinar qué usuarios actualizar
        if ($this->option('user')) {
            $userId = $this->option('user');
            $users = User::where('id', $userId)->get();
            
            if ($users->isEmpty()) {
                $this->error("No se encontró el usuario con ID: {$userId}");
                return 1;
            }
        } else {
            $query = User::query();
            
            if ($this->option('active-only')) {
                $query->where('enable', 1);
            }
            
            $users = $query->get();
            
            if ($users->isEmpty()) {
                $this->error("No se encontraron usuarios");
                return 1;
            }
            
            if (!$this->option('force') && !$this->confirm('¿Estás seguro de generar claves API para ' . $users->count() . ' usuarios?')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }
        
        $bar = $this->output->createProgressBar(count($users));
        $bar->start();
        
        $generatedCount = 0;
        $skippedCount = 0;
        
        foreach ($users as $user) {
            // Verificar si ya tiene una clave y si debemos sobrescribirla
            if ($user->woocommerce_api_key && !$this->option('force')) {
                $this->line("\nUsuario {$user->name} ya tiene clave API. Usa --force para sobrescribir.");
                $skippedCount++;
            } else {
                // Generar clave única
                $apiKey = $this->generateUniqueApiKey();
                $user->woocommerce_api_key = $apiKey;
                $user->save();
                
                $generatedCount++;
                $this->line("\nGenerada clave para {$user->name}: {$apiKey}");
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
            $exists = User::where('woocommerce_api_key', $key)->exists();
        } while ($exists);
        
        return $key;
    }
}