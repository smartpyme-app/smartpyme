<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActualizarEstadoPaquetes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paquetes:actualizar-estado 
                            {--ruta=datos/wr.txt : Ruta al archivo wr.txt con los WR a excluir}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza paquetes de estado En Bodega a Facturado, excluyendo los WR listados en wr.txt';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $basePath = base_path();
        $ruta = $basePath . '/' . ltrim($this->option('ruta'), '/');

        if (!file_exists($ruta)) {
            $this->error("Archivo no encontrado: {$ruta}");
            return 1;
        }

        $contenido = file_get_contents($ruta);
        $wrExcluir = array_filter(
            array_map('trim', explode(',', $contenido)),
            fn($w) => $w !== ''
        );

        $wrExcluir = array_values(array_unique($wrExcluir));

        $this->info('WR a excluir (no actualizar): ' . count($wrExcluir));
        $this->info('Actualizando paquetes En Bodega → Facturado para empresas 187 y 290, donde wr NO está en la lista...');

        try {
            $empresas = [187, 290];

            $query = DB::table('paquetes')
                ->where('estado', 'En bodega')
                ->whereIn('id_empresa', $empresas);

            if (!empty($wrExcluir)) {
                $query->whereNotIn('wr', $wrExcluir);
            }

            $actualizados = $query->update(['estado' => 'Facturado']);

            $this->info("Paquetes actualizados: {$actualizados}");
            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
