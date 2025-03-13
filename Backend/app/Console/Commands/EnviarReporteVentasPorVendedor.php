<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Ventas\VentasController;
use Illuminate\Console\Command;

class EnviarReporteVentasPorVendedor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reporte:ventas-por-vendedor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía el reporte diario de ventas por vendedor por correo.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $controller = new VentasController();
      

        try {
            $controller->enviarReporteDiario();
            $this->info('Reporte enviado correctamente.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error al enviar el reporte: ' . $e->getMessage());
            return 1;
        }
    }
}
