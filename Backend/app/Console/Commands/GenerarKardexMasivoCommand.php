<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Kardex;
use App\Mail\KardexMasivoMail;
use App\Mail\KardexMasivoErrorMail;

class GenerarKardexMasivoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kardex:generar-masivo {email} {id_empresa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera kardex masivo para una empresa y lo envía por correo';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');
        $idEmpresa = $this->argument('id_empresa');
        
        try {
            $this->info("Iniciando generación de kardex masivo para empresa: {$idEmpresa}");
            
            // Configuración optimizada para Hostinger
            $config = config('hostinger.kardex_masivo', []);
            ini_set('memory_limit', $config['memory_limit'] ?? '256M');
            ini_set('max_execution_time', $config['timeout'] ?? 120);
            
            // Verificar que la empresa tenga productos
            $productosCount = Producto::where('id_empresa', $idEmpresa)
                ->where('tipo', 'Producto')
                ->count();
                
            if ($productosCount == 0) {
                $this->error("No se encontraron productos para la empresa: {$idEmpresa}");
                return 1;
            }
            
            $this->info("Se encontraron {$productosCount} productos para procesar");
            
            // Generar archivo CSV en lugar de Excel para mejor rendimiento
            $fileName = 'kardex_completo_' . $idEmpresa . '_' . date('Ymd_His') . '.csv';
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
                'Cantidad',
                'Stock Anterior',
                'Stock Nuevo',
                'Usuario',
                'Referencia'
            ];
            fputcsv($file, $headers);
            
            // Procesar en chunks para evitar problemas de memoria
            // Chunks muy pequeños para Hostinger
            $chunkSize = $config['chunk_size'] ?? 50;
            $offset = 0;
            $totalProcessed = 0;
            
            do {
                $this->info("Procesando chunk desde {$offset}...");
                
                // Obtener IDs de productos
                $productoIds = Producto::where('id_empresa', $idEmpresa)
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
                
                // Pequeña pausa para Hostinger
                $sleepTime = ($config['sleep_between_chunks'] ?? 0.1) * 1000000;
                usleep($sleepTime);
                
            } while (true);
            
            fclose($file);
            
            $this->info("Archivo generado: {$filePath}");
            $this->info("Total de registros procesados: {$totalProcessed}");
            
            // Enviar por correo
            Mail::to($email)->send(new KardexMasivoMail($filePath, $fileName));
            
            $this->info("Correo enviado exitosamente a: {$email}");
            
            // Limpiar archivo temporal
            unlink($filePath);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error al generar kardex masivo: " . $e->getMessage());
            
            // Enviar correo de error
            try {
                Mail::to($email)->send(new KardexMasivoErrorMail($e->getMessage()));
            } catch (\Exception $mailError) {
                $this->error("Error al enviar correo de error: " . $mailError->getMessage());
            }
            
            return 1;
        }
    }
    
    private function getTipoMovimiento($tipo)
    {
        $tipos = [
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'ajuste' => 'Ajuste',
            'traslado_entrada' => 'Traslado Entrada',
            'traslado_salida' => 'Traslado Salida',
            'compra' => 'Compra',
            'venta' => 'Venta',
            'devolucion_compra' => 'Devolución Compra',
            'devolucion_venta' => 'Devolución Venta',
            'consigna_entrada' => 'Consigna Entrada',
            'consigna_salida' => 'Consigna Salida'
        ];

        return $tipos[$tipo] ?? ucfirst($tipo);
    }
}
