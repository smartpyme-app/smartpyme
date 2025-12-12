<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Mail\VentasDetallesExportMail;

class GenerarVentasDetallesExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $filtros;
    protected $userId;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;
    
    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 7200; // 2 horas

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email, $filtros, $userId = null)
    {
        $this->email = $email;
        $this->filtros = $filtros;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info("Iniciando generación de export de detalles de ventas", [
                'email' => $this->email,
                'filtros' => $this->filtros
            ]);
            
            // Aumentar límite de memoria y tiempo de ejecución
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 0);
            set_time_limit(0);
            
            // Contar registros sin cargarlos en memoria
            $totalRegistros = $this->contarRegistros();
            
            if ($totalRegistros == 0) {
                Log::warning("No se encontraron registros para exportar");
                Mail::to($this->email)->send(new \App\Mail\VentasDetallesExportErrorMail('No se encontraron registros para el período seleccionado.'));
                return;
            }

            Log::info("Se encontraron {$totalRegistros} registros para procesar");

            // Generar archivo CSV directamente (más eficiente que Excel para grandes volúmenes)
            $fileName = 'ventas-detalles-' . date('Ymd_His') . '.csv';
            $filePath = storage_path('app/temp/' . $fileName);
            
            // Crear directorio si no existe
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            $file = fopen($filePath, 'w');
            
            // Escribir BOM para Excel (UTF-8)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Escribir encabezados
            $headers = [
                'Fecha',
                'Cliente',
                'Telefono',
                'DUI',
                'NIT',
                'Producto',
                'Codigo',
                'Marca',
                'Categoria',
                'Documento',
                'Proyecto',
                'Num Identificacion',
                'Correlativo',
                'Forma de pago',
                'Banco',
                'Estado',
                'Canal',
                'Cantidad',
                'Costo',
                'Precio',
                'Descuento',
                'IVA',
                'Utilidad',
                'Total',
                'Empresa',
                'Observaciones', 
                'Usuario',
                'Vendedor',
                'Sucursal'
            ];
            fputcsv($file, $headers, ';');
            
            // Procesar en chunks pequeños para evitar problemas de memoria
            $chunkSize = 500;
            $offset = 0;
            $totalProcessed = 0;
            
            do {
                if ($totalProcessed % 5000 == 0) {
                    Log::info("Procesando registros: {$totalProcessed} de {$totalRegistros}");
                }
                
                // Obtener chunk de detalles con relaciones cargadas
                $detalles = $this->obtenerChunk($offset, $chunkSize);
                
                if ($detalles->isEmpty()) {
                    break;
                }
                
                // Escribir datos al archivo
                foreach ($detalles as $row) {
                    $venta = $row->venta;
                    $producto = $row->producto;
                    $vendedor = $row->vendedor;
                    
                    $documentoNombre = $venta && $venta->documento ? $venta->documento->nombre : null;
                    $esFacturaExportacion = $documentoNombre && strtolower($documentoNombre) === 'factura de exportación';
                    
                    // Calcular IVA
                    $iva = 0;
                    if ($venta && $venta->iva > 0 && !$esFacturaExportacion) {
                        $ivaDetalle = $row->iva ?? 0;
                        if ($ivaDetalle > 0) {
                            $iva = $ivaDetalle;
                        }
                    }
                    
                    $totalConIva = $row->total + $iva;
                    
                    // Obtener datos de relaciones ya cargadas
                    $cliente = $venta && $venta->cliente ? $venta->cliente : null;
                    $categoriaNombre = $producto && $producto->categoria ? $producto->categoria->nombre : '';
                    $canalNombre = $venta && $venta->canal ? $venta->canal->nombre : null;
                    $sucursal = $venta && $venta->sucursal ? $venta->sucursal : null;
                    $empresaNombre = $sucursal && $sucursal->empresa ? $sucursal->empresa->nombre : null;
                    $sucursalNombre = $sucursal ? $sucursal->nombre : null;
                    $usuarioNombre = $venta && $venta->usuario ? $venta->usuario->name : null;
                    
                    $fields = [
                        $venta ? $venta->fecha : null,
                        $venta ? $venta->nombre_cliente : 'Consumidor Final',
                        $cliente ? $cliente->telefono : null,
                        $cliente ? $cliente->dui : null,
                        $cliente ? $cliente->nit : null,
                        $producto ? $producto->nombre : null,
                        $producto ? $producto->codigo : null,
                        $producto ? $producto->marca : null,
                        $categoriaNombre,
                        $documentoNombre,
                        $row->nombre_proyecto,
                        $venta ? $venta->num_identificacion : null,
                        $venta ? $venta->correlativo : null,
                        $venta ? $venta->forma_pago : null,
                        $venta ? $venta->detalle_banco : null,
                        $venta ? $venta->estado : null,
                        $canalNombre,
                        $row->cantidad,
                        number_format($row->costo, 2, '.', ''),
                        number_format($row->precio, 2, '.', ''),
                        number_format($row->descuento, 2, '.', ''),
                        number_format($iva, 2, '.', ''),
                        number_format($row->total - ($row->costo * $row->cantidad), 2, '.', ''),
                        number_format($totalConIva, 2, '.', ''),
                        $empresaNombre,
                        $venta ? $venta->observaciones : null,
                        $usuarioNombre,
                        $vendedor ? $vendedor->name : null,
                        $sucursalNombre
                    ];
                    fputcsv($file, $fields, ';');
                    $totalProcessed++;
                }
                
                $offset += $chunkSize;
                
                // Limpiar memoria
                unset($detalles);
                gc_collect_cycles();
                
            } while (true);
            
            fclose($file);
            
            Log::info("Archivo generado: {$filePath}");
            Log::info("Total de registros procesados: {$totalProcessed}");
            
            // Verificar que el archivo existe y tiene contenido
            if (!file_exists($filePath)) {
                throw new \Exception("El archivo no se generó correctamente: {$filePath}");
            }
            
            $fileSize = filesize($filePath);
            if ($fileSize === 0) {
                throw new \Exception("El archivo generado está vacío");
            }
            
            Log::info("Tamaño del archivo: {$fileSize} bytes");
            
            // Enviar por correo
            Log::info("Enviando correo a: {$this->email}");
            Mail::to($this->email)->send(new VentasDetallesExportMail($filePath, $fileName));
            
            Log::info("Correo enviado exitosamente a: {$this->email}");
            
            // Limpiar archivo temporal después de enviar (opcional, se puede mantener por un tiempo)
            // unlink($filePath);
            
        } catch (\Exception $e) {
            Log::error("Error al generar export de ventas detalles: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            Log::error("Email destino: {$this->email}");
            
            // Enviar correo de error si es posible
            try {
                if ($this->email) {
                    Mail::to($this->email)->send(new \App\Mail\VentasDetallesExportErrorMail($e->getMessage()));
                    Log::info("Correo de error enviado a: {$this->email}");
                } else {
                    Log::error("No se puede enviar correo de error: email no está definido");
                }
            } catch (\Exception $mailError) {
                Log::error("Error al enviar correo de error: " . $mailError->getMessage());
                Log::error("Stack trace del error de correo: " . $mailError->getTraceAsString());
            }
            
            throw $e;
        }
    }

    /**
     * Cuenta los registros que cumplen con los filtros
     */
    private function contarRegistros()
    {
        $request = (object) $this->filtros;
        
        return Detalle::whereHas('venta', function($query) use ($request) {
            $this->aplicarFiltrosVenta($query, $request);
        })->count();
    }

    /**
     * Obtiene un chunk de registros con relaciones cargadas
     */
    private function obtenerChunk($offset, $limit)
    {
        $request = (object) $this->filtros;
        
        // Obtener id_empresa del usuario si está disponible
        $idEmpresa = null;
        if ($this->userId) {
            $user = \App\Models\User::find($this->userId);
            if ($user) {
                $idEmpresa = $user->id_empresa;
            }
        }
        
        return Detalle::with([
            'venta' => function($q) {
                $q->with([
                    'cliente:id,nombre,telefono,dui,nit',
                    'documento:id,nombre',
                    'canal:id,nombre',
                    'usuario:id,name',
                    'sucursal' => function($sq) {
                        $sq->with('empresa:id,nombre');
                    }
                ]);
            },
            'producto' => function($q) {
                $q->with('categoria:id,nombre');
            },
            'vendedor:id,name'
        ])
        ->whereHas('venta', function($query) use ($request, $idEmpresa) {
            // Aplicar filtro de empresa si está disponible
            if ($idEmpresa) {
                $query->where('id_empresa', $idEmpresa);
            }
            $this->aplicarFiltrosVenta($query, $request);
        })
        ->orderBy('id', 'desc')
        ->offset($offset)
        ->limit($limit)
        ->get();
    }

    /**
     * Aplica los filtros a la query de ventas
     */
    private function aplicarFiltrosVenta($query, $request)
    {
        $query->when($request->inicio ?? null, function ($q) use ($request) {
            return $q->where('fecha', '>=', $request->inicio);
        })
        ->when($request->fin ?? null, function ($q) use ($request) {
            return $q->where('fecha', '<=', $request->fin);
        })
        ->when(isset($request->recurrente) && $request->recurrente !== null, function ($q) use ($request) {
            $q->where('recurrente', !!$request->recurrente);
        })
        ->when($request->num_identificacion ?? null, function ($q) use ($request) {
            $q->where('num_identificacion', $request->num_identificacion);
        })
        ->when($request->id_sucursal ?? null, function ($q) use ($request) {
            return $q->where('id_sucursal', $request->id_sucursal);
        })
        ->when($request->id_bodega ?? null, function ($q) use ($request) {
            return $q->where('id_bodega', $request->id_bodega);
        })
        ->when($request->id_cliente ?? null, function ($q) use ($request) {
            return $q->where('id_cliente', $request->id_cliente);
        })
        ->when($request->id_usuario ?? null, function ($q) use ($request) {
            return $q->where('id_usuario', $request->id_usuario);
        })
        ->when($request->forma_pago ?? null, function ($q) use ($request) {
            return $q->where('forma_pago', $request->forma_pago)
                ->orwhereHas('metodos_de_pago', function ($q2) use ($request) {
                    $q2->where('nombre', $request->forma_pago);
                });
        })
        ->when($request->id_vendedor ?? null, function ($q) use ($request) {
            return $q->where('id_vendedor', $request->id_vendedor)
                ->orwhereHas('detalles', function ($q2) use ($request) {
                    $q2->where('id_vendedor', $request->id_vendedor);
                });
        })
        ->when($request->id_canal ?? null, function ($q) use ($request) {
            return $q->where('id_canal', $request->id_canal);
        })
        ->when($request->id_proyecto ?? null, function ($q) use ($request) {
            return $q->where('id_proyecto', $request->id_proyecto);
        })
        ->when($request->id_documento ?? null, function ($q) use ($request) {
            $documento = \App\Models\Admin\Documento::find($request->id_documento);
            if ($documento) {
                return $q->whereHas('documento', function ($q2) use ($documento) {
                    $q2->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                });
            } else {
                return $q->where('id_documento', $request->id_documento);
            }
        })
        ->when($request->estado ?? null, function ($q) use ($request) {
            return $q->where('estado', $request->estado);
        })
        ->when($request->metodo_pago ?? null, function ($q) use ($request) {
            return $q->where('metodo_pago', $request->metodo_pago);
        })
        ->when($request->tipo_documento ?? null, function ($q) use ($request) {
            return $q->whereHas('documento', function ($q2) use ($request) {
                $q2->where('nombre', $request->tipo_documento);
            });
        })
        ->when(isset($request->dte) && $request->dte == 1, function ($q) {
            return $q->whereNull('sello_mh');
        })
        ->when(isset($request->dte) && $request->dte == 2, function ($q) {
            return $q->whereNotNull('sello_mh');
        })
        ->where('cotizacion', 0)
        ->when($request->buscador ?? null, function ($q) use ($request) {
            $buscador = '%' . $request->buscador . '%';
            return $q->where(function ($q2) use ($buscador) {
                $q2->whereHas('cliente', function ($qCliente) use ($buscador) {
                    $qCliente->where('nombre', 'like', $buscador)
                        ->orWhere('nombre_empresa', 'like', $buscador)
                        ->orWhere('ncr', 'like', $buscador)
                        ->orWhere('nit', 'like', $buscador);
                })
                    ->orWhere('correlativo', 'like', $buscador)
                    ->orWhere('estado', 'like', $buscador)
                    ->orWhere('observaciones', 'like', $buscador)
                    ->orWhere('forma_pago', 'like', $buscador);
            });
        });
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Job GenerarVentasDetallesExport falló: " . $exception->getMessage());
        
        // Intentar enviar correo de error
        try {
            Mail::to($this->email)->send(new \App\Mail\VentasDetallesExportErrorMail($exception->getMessage()));
        } catch (\Exception $mailError) {
            Log::error("Error al enviar correo de error: " . $mailError->getMessage());
        }
    }
}

