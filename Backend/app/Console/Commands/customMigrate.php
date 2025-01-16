<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Helper\ProgressBar;

class CustomMigrate extends Command
{
    protected $signature = 'migrate:custom';
    protected $description = 'Ejecuta migraciones específicas con barra de progreso';

    // Lista predefinida de migraciones a ejecutar
    protected $migrationsToExecute = [
        // 'create_users_table', //ejemplo
        // 'create_plans_table',
        'create_suscripciones_table',
    ];

    public function handle()
    {
        // Filtrar y validar las migraciones solicitadas
        $migrationsPath = database_path('migrations');
        $availableMigrations = File::glob($migrationsPath . '/*.php');
        $migrationsToRun = [];

        foreach ($this->migrationsToExecute as $requestedFile) {
            $found = false;
            foreach ($availableMigrations as $migration) {
                if (str_contains(basename($migration), $requestedFile)) {
                    $migrationsToRun[] = $migration;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->error("Migración no encontrada: $requestedFile");
                return;
            }
        }

        if (empty($migrationsToRun)) {
            $this->error('No se encontraron migraciones para ejecutar');
            return;
        }

        // Mostrar las migraciones que se van a ejecutar
        $this->info('Se ejecutarán las siguientes migraciones:');
        foreach ($migrationsToRun as $migration) {
            $this->line('- ' . basename($migration));
        }

        if (!$this->confirm('¿Deseas continuar?')) {
            $this->info('Operación cancelada');
            return;
        }

        // Configurar la barra de progreso
        $progressBar = $this->output->createProgressBar(count($migrationsToRun));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');

        // Ejecutar las migraciones
        foreach ($migrationsToRun as $migration) {
            $fileName = basename($migration);
            $progressBar->setMessage("Migrando: $fileName");
            
            try {
                $this->call('migrate', [
                    '--path' => 'database/migrations/' . $fileName,
                    '--force' => true,
                    '--quiet' => true
                ]);
                
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->error("\nError al ejecutar $fileName: " . $e->getMessage());
                return;
            }
        }

        $progressBar->finish();
        $this->info("\n¡Migraciones completadas exitosamente!");
    }
}