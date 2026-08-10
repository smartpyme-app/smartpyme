<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Deprecated: ya no se auto-siembra planilla.
 * Usar `php artisan empresa-config:migrar` y/o Importar Base por empresa.
 */
class ConfiguracionPlanillaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(
            'ConfiguracionPlanillaSeeder es no-op. Migra con empresa-config:migrar o Importar Base.'
        );
    }
}
