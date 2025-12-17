<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Kardex;
use App\Exports\Inventario\KardexMasivoExport;
use App\Mail\KardexMasivoMail;
use Maatwebsite\Excel\Facades\Excel;

class GenerarKardexMasivo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    protected $email;
    protected $idEmpresa;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;
    
    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hora

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email, $idEmpresa)
    {
        $this->email = $email;
        $this->idEmpresa = $idEmpresa;
        $this->onQueue('smartpyme-daily-reports');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info("Iniciando generación de kardex masivo para empresa: {$this->idEmpresa}, email: {$this->email}");
            
            // Aumentar límite de memoria y tiempo de ejecución
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 0);
            
            // Contar productos sin cargarlos en memoria
            $productosCount = Producto::where('id_empresa', $this->idEmpresa)
                ->where('tipo', 'Producto')
                ->count();

            if ($productosCount == 0) {
                Log::warning("No se encontraron productos para la empresa: {$this->idEmpresa}");
                return;
            }

            Log::info("Se encontraron {$productosCount} productos para procesar");

            // Generar archivo CSV directamente
            $fileName = 'kardex_completo_' . $this->idEmpresa . '_' . date('Ymd_His') . '.csv';
            $filePath = storage_path('app/temp/' . $fileName);
            
            // Crear directorio si no existe
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            $file = fopen($filePath, 'w');
            
            // Escribir encabezados
            $headers = [
                'Fecha',
                'Producto',
                'Código',
                'Bodega',
                'Sucursal',
                'Tipo de Movimiento',
                'Detalle',
                'Entrada',
                'Salida',
                'Stock',
                'Costo U',
                'Costo Total',
                'Usuario',
                'Referencia'
            ];
            fputcsv($file, $headers);
            
            // Procesar en chunks para evitar problemas de memoria
            $chunkSize = 500;
            $offset = 0;
            $totalProcessed = 0;
            
            do {
                Log::info("Procesando chunk desde {$offset}...");
                
                // Obtener IDs de productos
                $productoIds = Producto::where('id_empresa', $this->idEmpresa)
                    ->where('tipo', 'Producto')
                    ->offset($offset)
                    ->limit($chunkSize)
                    ->pluck('id');
                
                if ($productoIds->isEmpty()) {
                    break;
                }
                
                // Obtener movimientos de kardex para estos productos
                $kardexMovements = Kardex::whereIn('id_producto', $productoIds)
                    ->with(['producto.categoria', 'inventario.sucursal', 'usuario'])
                    ->orderBy('fecha', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
                
                // Escribir datos al archivo
                foreach ($kardexMovements as $kardex) {
                    // Obtener la sucursal directamente desde la relación inventario (que apunta a Bodega)
                    $sucursalNombre = '';
                    try {
                        if(isset($kardex->inventario) && isset($kardex->inventario->sucursal)) {
                            $sucursalNombre = $kardex->inventario->sucursal->nombre;
                        } else {
                            $sucursalNombre = 'SIN SUCURSAL';
                        }
                    } catch (\Exception $e) {
                        $sucursalNombre = 'ERROR: ' . $e->getMessage();
                    }
                    
                    $row = [
                        $kardex->fecha ? \Carbon\Carbon::parse($kardex->fecha)->format('d/m/Y H:i:s') : '',
                        $kardex->producto->nombre ?? '',
                        $kardex->producto->codigo ?? '',
                        $kardex->inventario->nombre ?? '',
                        $sucursalNombre,
                        $this->getTipoMovimiento($kardex->detalle),
                        $kardex->detalle ?? '',
                        number_format($kardex->entrada_cantidad ?? 0, 2),
                        number_format($kardex->salida_cantidad ?? 0, 2),
                        number_format($kardex->total_cantidad ?? 0, 2),
                        number_format($kardex->costo_unitario ?? 0, 2),
                        number_format($kardex->total_valor ?? 0, 2),
                        $kardex->usuario->name ?? '',
                        $kardex->referencia ?? ''
                    ];
                    fputcsv($file, $row);
                    $totalProcessed++;
                }
                
                $offset += $chunkSize;
                
                // Limpiar memoria
                unset($kardexMovements);
                gc_collect_cycles();
                
            } while (true);
            
            fclose($file);
            
            Log::info("Archivo generado: {$filePath}");
            Log::info("Total de registros procesados: {$totalProcessed}");
            
            // Enviar por correo usando cola
            \App\Jobs\SendKardexMasivoEmail::dispatch($this->email, $filePath, $fileName);
            
            Log::info("Correo encolado exitosamente para: {$this->email}");
            
        } catch (\Exception $e) {
            Log::error("Error al generar kardex masivo: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            // Enviar correo de error usando cola
            try {
                \App\Jobs\SendKardexMasivoErrorEmail::dispatch($this->email, $e->getMessage());
            } catch (\Exception $mailError) {
                Log::error("Error al encolar correo de error: " . $mailError->getMessage());
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Job GenerarKardexMasivo falló: " . $exception->getMessage());
        
        // Intentar enviar correo de error usando cola
        try {
            \App\Jobs\SendKardexMasivoErrorEmail::dispatch($this->email, $exception->getMessage());
        } catch (\Exception $mailError) {
            Log::error("Error al encolar correo de error: " . $mailError->getMessage());
        }
    }
    
    private function getTipoMovimiento($detalle)
    {
        // Mapear los detalles del kardex a tipos más legibles
        if (strpos($detalle, 'Venta') !== false) {
            return 'Venta';
        }
        if (strpos($detalle, 'Compra') !== false) {
            return 'Compra';
        }
        if (strpos($detalle, 'Traslado') !== false) {
            return 'Traslado';
        }
        if (strpos($detalle, 'Ajuste') !== false) {
            return 'Ajuste';
        }
        if (strpos($detalle, 'Devolución') !== false) {
            return 'Devolución';
        }
        if (strpos($detalle, 'Entrada') !== false) {
            return 'Entrada';
        }
        if (strpos($detalle, 'Salida') !== false) {
            return 'Salida';
        }
        
        return $detalle ?? 'Otro';
    }
}

