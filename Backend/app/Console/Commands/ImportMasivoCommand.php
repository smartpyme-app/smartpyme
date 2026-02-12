<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ImportMasivoController;
use Illuminate\Console\Command;

class ImportMasivoCommand extends Command
{
    protected $signature = 'import-masivo';
    protected $description = 'Importa clientes, ventas y detalles desde Backend/datos/ (evita timeout del servidor)';

    public function handle(): int
    {
        $this->info('Iniciando importación masiva...');

        $controller = app(ImportMasivoController::class);
        $response = $controller();

        $data = $response->getData(true);
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response->getStatusCode() >= 400 ? 1 : 0;
    }
}
