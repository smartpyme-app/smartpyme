<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KardexMasivoQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\KardexMasivoMail;
use App\Mail\KardexMasivoErrorMail;

class ProcesarColaKardexMasivo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kardex:procesar-cola {--limit=0 : Número máximo de elementos a procesar (0 = todos)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa la cola de kardex masivo en segundo plano';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = $this->option('limit');
        
        // Obtener elementos pendientes de la cola
        $query = KardexMasivoQueue::pending()
            ->orderBy('created_at', 'asc');
            
        // Si limit es 0, procesar todos. Si no, aplicar el límite
        if ($limit > 0) {
            $query->limit($limit);
        }
        
        $queueItems = $query->get();
            
        if ($queueItems->isEmpty()) {
            $this->info('No hay elementos pendientes en la cola.');
            return Command::SUCCESS;
        }
        
        $totalItems = $queueItems->count();
        $limitText = $limit > 0 ? " (límite: {$limit})" : " (todos los elementos)";
        $this->info("Procesando {$totalItems} elemento(s) de la cola{$limitText}...");
        
        foreach ($queueItems as $item) {
            $this->info("Procesando kardex masivo para empresa: {$item->id_empresa}, email: {$item->email}");
            
            // Marcar como procesando
            $item->update([
                'status' => 'processing',
                'started_at' => now()
            ]);
            
            try {
                // Ejecutar comando de generación
                $exitCode = Artisan::call('kardex:generar-masivo', [
                    'email' => $item->email,
                    'id_empresa' => $item->id_empresa
                ]);
                
                if ($exitCode !== 0) {
                    throw new \Exception("Error al ejecutar comando de generación de kardex");
                }
                
                // Marcar como completado
                $item->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                
                $this->info("Kardex masivo completado para empresa: {$item->id_empresa}");
                
            } catch (\Exception $e) {
                // Marcar como fallido
                $item->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now()
                ]);
                
                // Enviar correo de error
                try {
                    Mail::to($item->email)->send(new KardexMasivoErrorMail($e->getMessage()));
                } catch (\Exception $mailError) {
                    Log::error("Error al enviar correo de error: " . $mailError->getMessage());
                }
                
                $this->error("Error al procesar kardex masivo para empresa: {$item->id_empresa} - " . $e->getMessage());
            }
        }
        
        return Command::SUCCESS;
    }
}
