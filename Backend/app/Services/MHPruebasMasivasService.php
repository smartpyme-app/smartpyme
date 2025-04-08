<?php

namespace App\Services;

use App\Constants\FacturacionElectronica\FEConstants;
use App\Models\Admin\Documento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\MH\MHFactura;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFacturaExportacion;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHNotaDebito;
use App\Models\TrabajosPendientes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MHPruebasMasivasService
{
    protected $baseVenta;
    protected $empresa;
    protected $sucursal;
    protected $ambiente;
    protected $simulationMode = true; // No afecta realmente los correlativos
    protected $userId; // Para ejecución en segundo plano
    protected $empresaId; // Para ejecución en segundo plano
    protected $batchSize = 5;

    public function __construct() {}

    /**
     * Inicializar datos del usuario y empresa
     * Puede recibir un ID de usuario específico para trabajos en segundo plano
     */
    protected function inicializarDatosUsuario($userId = null)
    {
        // Comprobar si ya tenemos la empresa
        if (!$this->empresa) {
            // Si nos proporcionan un usuario específico, usarlo
            if ($userId) {
                $usuario = \App\Models\User::find($userId);
                $this->userId = $userId;
            } else {
                $usuario = Auth::user();
            }

            if (!$usuario) {
                throw new \Exception('No se pudo obtener el usuario');
            }

            $this->empresa = $usuario->empresa;
            $this->empresaId = $this->empresa->id;

            if (!$this->empresa) {
                throw new \Exception('No se pudo obtener la empresa del usuario');
            }

            $this->ambiente = $this->empresa->fe_ambiente;
        }
    }

    /**
     * Obtener estadísticas de las pruebas realizadas
     */
    public function obtenerEstadisticas($userId = null)
    {
        $this->inicializarDatosUsuario($userId);

        // Obtener conteos de la base de datos para DTEs marcados como pruebas masivas
        $stats = [
            'facturas' => [
                'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURA_CONSUMIDOR_FINAL)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 90
            ],
            'creditosFiscales' => [
                'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 75
            ],
            // 'notasCredito' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_NOTA_DE_CREDITO)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 10
            // ],
            // 'notasDebito' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_NOTA_DE_DEBITO)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 10
            // ],
            // 'facturasExportacion' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURAS_DE_EXPORTACION)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 5
            // ],
            // 'sujetoExcluido' => [
            //     'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURA_DE_SUJETO_EXCLUIDO)
            //         // ->where('prueba_masiva', true)
            //         ->where('sello_mh', '!=', null)
            //         ->where('dte', '!=', null)
            //         ->where('id_empresa', $this->empresa->id)
            //         ->count(),
            //     'requeridas' => 5
            // ],
        ];

        return $stats;
    }

    public function ejecutarPruebasMasivas($tipo, $cantidad, $idDocumentoBase = null, $userId = null)
    {
        try {
            // Establecer límites para prevenir bloqueos
            set_time_limit(30); // Reducido porque solo vamos a verificar requisitos
            ini_set('memory_limit', '128M');

            // Inicializar con el usuario correcto
            $this->inicializarDatosUsuario($userId);

            // Validar que estamos en ambiente de pruebas
            if ($this->ambiente !== '00') {
                return [
                    'success' => false,
                    'message' => 'Las pruebas masivas solo pueden ejecutarse en ambiente de pruebas'
                ];
            }

            // Validación básica del documento base (si existe)
            if ($idDocumentoBase) {
                $baseVentaExiste = Venta::where('id', $idDocumentoBase)
                    ->where('id_empresa', $this->empresa->id)
                    ->exists();

                if (!$baseVentaExiste) {
                    return [
                        'success' => false,
                        'message' => 'No se encontró el documento base especificado'
                    ];
                }
            } else {
                // Verificar si existe al menos un documento del tipo seleccionado
                $baseVentaExiste = Venta::where('tipo_dte', $tipo)
                    ->where('sello_mh', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->exists();

                if (!$baseVentaExiste) {
                    return [
                        'success' => false,
                        'message' => 'No se encontraron documentos base del tipo seleccionado para generar las pruebas'
                    ];
                }
            }

            // Determinar el tipo de documento para el mensaje
            $tiposDescriptivos = [
                '01' => 'Facturas Consumidor Final',
                '03' => 'Comprobantes de Crédito Fiscal',
                '05' => 'Notas de Crédito',
                '06' => 'Notas de Débito',
                '11' => 'Facturas de Exportación',
                '14' => 'Facturas de Sujeto Excluido'
            ];

            $descripcionTipo = $tiposDescriptivos[$tipo] ?? "documentos tipo $tipo";

            // Solo devolver información de verificación
            return [
                'success' => true,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'message' => "Se verificaron los requisitos para generar {$cantidad} {$descripcionTipo}."
            ];
        } catch (\Exception $e) {
            Log::error('Error al verificar requisitos para pruebas masivas: ' . $e->getMessage());

            // Si tenemos un ID de usuario, enviar notificación de error por correo
            if ($userId) {
                $this->enviarNotificacionError($e->getMessage(), $tipo, $cantidad);
            }

            return [
                'success' => false,
                'message' => 'Error al verificar requisitos: ' . $e->getMessage()
            ];
        }
    }

    public function procesarPruebasMasivas($tipo, $cantidad, $idDocumentoBase = null, $userId = null,$correlativoInicial = null)
    {
        try {
            // Establecer límites para prevenir bloqueos
            set_time_limit(90); // 1.5 minutos
            ini_set('memory_limit', '256M');

            // Inicializar con el usuario correcto
            $this->inicializarDatosUsuario($userId);

            // Validar que estamos en ambiente de pruebas
            if ($this->ambiente !== '00') {
                return [
                    'success' => false,
                    'message' => 'Las pruebas masivas solo pueden ejecutarse en ambiente de pruebas'
                ];
            }

            // Obtener documento base
            if ($idDocumentoBase) {
                $this->baseVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal', 'documento'])
                    ->findOrFail($idDocumentoBase);
            } else {
                // Buscar el último documento del tipo seleccionado que fue emitido exitosamente
                $this->baseVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal', 'documento'])
                    ->where('tipo_dte', $tipo)
                    ->where('sello_mh', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->latest()
                    ->first();
            }

            if (!$this->baseVenta) {
                return [
                    'success' => false,
                    'message' => 'No se encontró un documento base para generar las pruebas'
                ];
            }

            $this->empresa = $this->baseVenta->empresa;
            $this->sucursal = $this->baseVenta->sucursal;

            // Resultados del proceso
            $resultados = [
                'exitosos' => 0,
                'fallidos' => 0,
                'detalles' => []
            ];

            // Guardar el correlativo original para restaurarlo después
            $documento = $this->baseVenta->documento;
            $correlativoOriginal = $documento->correlativo;

            // Buscar el correlativo más alto existente para evitar duplicados
            $ultimoCorrelativo = Venta::where('tipo_dte', $tipo)
                ->where('id_empresa', $this->empresa->id)
                ->where('id_documento', $this->baseVenta->id_documento)
                ->max('correlativo');

            
                // Si se proporciona un correlativo inicial, usarlo si es mayor que el último
            if ($correlativoInicial !== null) {
                $startCorrelativo = max($correlativoInicial, $ultimoCorrelativo + 1);
            } else {
                $startCorrelativo = max($correlativoOriginal, $ultimoCorrelativo + 1);
            }
            
            // Procesar en lotes pequeños
            $totalLotes = ceil($cantidad / $this->batchSize);

            DB::beginTransaction();

            try {
                $ventasGeneradas = collect();
                $token = null; // Para reutilizar el token de autenticación

                // Emitir documentos en lotes
                for ($lote = 0; $lote < $totalLotes; $lote++) {
                    $tamanoLote = min($this->batchSize, $cantidad - ($lote * $this->batchSize));

                    if ($tamanoLote <= 0) break;

                    // Log::info("Procesando lote " . ($lote + 1) . " de " . $totalLotes . ", tamaño: " . $tamanoLote);

                    // Procesar cada documento del lote
                    for ($i = 0; $i < $tamanoLote; $i++) {
                        $newCorrelativo = $startCorrelativo + ($lote * $this->batchSize) + $i;

                        try {
                            // Crear nueva venta
                            $nuevaVenta = new Venta();
                            $nuevaVenta->fill([
                                'tipo_dte' => $tipo,
                                'correlativo' => $newCorrelativo,
                                'numero_control' => 'DTE-' . $tipo . '-' . $this->sucursal->cod_estable_mh . '0001-' . str_pad($newCorrelativo, 15, '0', STR_PAD_LEFT),
                                'codigo_generacion' => strtoupper(Uuid::uuid4()->toString()),
                                'fecha' => Carbon::now()->format('Y-m-d'),
                                'fecha_pago' => Carbon::now()->format('Y-m-d'),
                                'prueba_masiva' => true,
                                'estado' => 'Pendiente',
                                'forma_pago' => $this->baseVenta->forma_pago,
                                'observaciones' => 'Documento de prueba generado automáticamente',
                                'sub_total' => $this->baseVenta->sub_total,
                                'descuento' => $this->baseVenta->descuento,
                                'iva' => $this->baseVenta->iva,
                                'iva_retenido' => $this->baseVenta->iva_retenido,
                                'iva_percibido' => $this->baseVenta->iva_percibido,
                                'gravada' => $this->baseVenta->gravada,
                                'exenta' => $this->baseVenta->exenta,
                                'no_sujeta' => $this->baseVenta->no_sujeta,
                                'cuenta_a_terceros' => $this->baseVenta->cuenta_a_terceros,
                                'total' => $this->baseVenta->total,
                                'id_cliente' => $this->baseVenta->id_cliente,
                                'id_usuario' => $this->baseVenta->id_usuario,
                                'id_vendedor' => $this->baseVenta->id_vendedor,
                                'id_empresa' => $this->empresa->id,
                                'id_sucursal' => $this->sucursal->id,
                                'id_documento' => $this->baseVenta->id_documento,
                                'id_bodega' => $this->baseVenta->id_bodega,
                                'id_canal' => $this->baseVenta->id_canal
                            ]);

                            $nuevaVenta->save();
                            $ventasGeneradas->push($nuevaVenta);

                            // Duplicar los detalles
                            foreach ($this->baseVenta->detalles as $detalleOriginal) {
                                $nuevaVenta->detalles()->create([
                                    'id_producto' => $detalleOriginal->id_producto,
                                    'cantidad' => $detalleOriginal->cantidad,
                                    'precio' => $detalleOriginal->precio,
                                    'costo' => $detalleOriginal->costo,
                                    'descuento' => $detalleOriginal->descuento,
                                    'total' => $detalleOriginal->total,
                                    'total_costo' => $detalleOriginal->total_costo,
                                    'descripcion' => $detalleOriginal->descripcion ?? 'Producto'
                                ]);
                            }

                            // Volver a cargar la venta con sus detalles para el DTE
                            $nuevaVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal'])->find($nuevaVenta->id);

                            // Generar el DTE con el objeto
                            $dte = $this->generarDTE($nuevaVenta);

                            // Firmar y enviar DTE, pasando el token para reutilización
                            $resultado = $this->firmarYEmitirDTE($nuevaVenta, $dte, $token);

                            // Si recibimos un nuevo token, lo guardamos para reutilizarlo
                            if (isset($resultado['token'])) {
                                $token = $resultado['token'];
                            }

                            if ($resultado['success']) {
                                $resultados['exitosos']++;
                                $resultados['detalles'][] = [
                                    'correlativo' => $nuevaVenta->correlativo,
                                    'status' => 'Éxito',
                                    'message' => 'DTE emitido correctamente'
                                ];

                                // Actualizar la venta con los datos del DTE
                                Venta::where('id', $nuevaVenta->id)
                                    ->update([
                                        'dte' => $dte,
                                        'sello_mh' => $resultado['selloRecibido'] ?? null,
                                        'estado' => 'Prueba'
                                    ]);
                            } else {
                                $resultados['fallidos']++;
                                $resultados['detalles'][] = [
                                    'correlativo' => $nuevaVenta->correlativo,
                                    'status' => 'Error',
                                    'message' => $resultado['message']
                                ];

                                // Marcar la venta para eliminación
                                $ventasGeneradas->push($nuevaVenta);
                            }
                        } catch (\Exception $e) {
                            Log::error('Error en prueba masiva: ' . $e->getMessage());
                            $resultados['fallidos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => isset($nuevaVenta) ? $nuevaVenta->correlativo : 'N/A',
                                'status' => 'Error',
                                'message' => 'Excepción: ' . $e->getMessage()
                            ];

                            // Marcar la venta para eliminación si hubo excepción
                            if (isset($nuevaVenta) && $nuevaVenta->id) {
                                $ventasGeneradas->push($nuevaVenta);
                            }
                        }
                    }

                    // Realizar una pequeña pausa para evitar sobrecargar el servidor
                    if ($lote < $totalLotes - 1) {
                        sleep(1);
                    }

                    // Liberar memoria
                    gc_collect_cycles();
                }

                // Restaurar el correlativo original
                $this->restaurarCorrelativo($documento, $correlativoOriginal);

                DB::commit();

                // Obtener estadísticas actualizadas
                $estadisticas = $this->obtenerEstadisticas($userId);

                // Si tenemos un ID de usuario, enviar notificación por correo
                if ($userId) {
                    $this->enviarNotificacionPorCorreo($resultados, $tipo, $cantidad, $estadisticas);
                }

                // Eliminar las ventas generadas como parte de las pruebas masivas
                $this->eliminarPruebasMasivas($this->empresa->id);

                return [
                    'success' => true,
                    'message' => "Proceso completado: {$resultados['exitosos']} documentos emitidos, {$resultados['fallidos']} fallidos",
                    'resultados' => $resultados,
                    'stats' => $estadisticas
                ];
            } catch (\Exception $e) {
                DB::rollback();
                Log::error('Error general en pruebas masivas: ' . $e->getMessage());

                // Si tenemos un ID de usuario, enviar notificación de error por correo
                if ($userId) {
                    $this->enviarNotificacionError($e->getMessage(), $tipo, $cantidad);
                }

                return [
                    'success' => false,
                    'message' => 'Error en el proceso: ' . $e->getMessage()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error en preparación de pruebas masivas: ' . $e->getMessage());

            // Si tenemos un ID de usuario, enviar notificación de error por correo
            if ($userId) {
                $this->enviarNotificacionError($e->getMessage(), $tipo, $cantidad);
            }

            return [
                'success' => false,
                'message' => 'Error al preparar pruebas: ' . $e->getMessage()
            ];
        }
    }

    protected function obtenerCargaSistema()
    {
        // En Linux
        if (function_exists('sys_getloadavg') && stristr(PHP_OS, 'linux')) {
            $load = sys_getloadavg();
            $cores = $this->getCPUCores();
            // Normalizar la carga por número de núcleos y convertir a porcentaje
            return min(100, round(($load[0] / $cores) * 100));
        }

        // Método alternativo para otros sistemas
        if (function_exists('shell_exec')) {
            // Para Windows
            if (stristr(PHP_OS, 'win')) {
                $cmd = "wmic cpu get loadpercentage /all";
                @exec($cmd, $output);

                if (isset($output[1])) {
                    return (int)$output[1];
                }
            }
        }

        // Si no se puede determinar la carga, asumimos un valor moderado
        return 50;
    }

    /**
     * Obtiene el número de núcleos de CPU
     * @return int Número de núcleos
     */
    protected function getCPUCores()
    {
        $cores = 1; // Valor por defecto

        if (function_exists('shell_exec')) {
            // Para Linux
            if (stristr(PHP_OS, 'linux')) {
                $cmd = "cat /proc/cpuinfo | grep processor | wc -l";
                $cores = (int)shell_exec($cmd);
            }
            // Para Windows
            elseif (stristr(PHP_OS, 'win')) {
                $cmd = "echo %NUMBER_OF_PROCESSORS%";
                $cores = (int)shell_exec($cmd);
            }
            // Para Mac
            elseif (stristr(PHP_OS, 'darwin')) {
                $cmd = "sysctl -n hw.ncpu";
                $cores = (int)shell_exec($cmd);
            }
        }

        return $cores > 0 ? $cores : 1;
    }

    protected function enviarNotificacionPorCorreo($resultados, $tipo, $cantidad, $estadisticas)
    {
        try {
            $usuario = \App\Models\User::find($this->userId);

            if ($usuario && $usuario->email) {
                $tipoTexto = $this->getTipoTexto($tipo);

                Mail::send('mails.pruebas-masivas-completadas', [
                    'resultado' => $resultados,
                    'tipo' => $tipo,         // Añadir el tipo como número
                    'tipoDTE' => $tipo,      // Añadir el tipoDTE para compatibilidad
                    'tipoTexto' => $tipoTexto,
                    'cantidad' => $cantidad,
                    'estadisticas' => $estadisticas
                ], function ($mensaje) use ($usuario, $tipoTexto) {
                    // $mensaje->to($usuario->email, $usuario->name)
                    $mensaje->to("joseespana94@gmail.com", $usuario->name)
                        ->subject('Pruebas Masivas MH Completadas: ' . $tipoTexto);
                });
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de notificación: ' . $e->getMessage());
        }
    }

    /**
     * Enviar notificación de error por correo
     */

    protected function enviarNotificacionError($mensaje, $tipo, $cantidad)
    {
        try {
            $usuario = \App\Models\User::find($this->userId);

            if ($usuario && $usuario->email) {
                $tipoTexto = $this->getTipoTexto($tipo);

                Mail::send('mails.pruebas-masivas-error', [
                    'error' => $mensaje,
                    'tipo' => $tipo,        // Añadir el tipo como número
                    'tipoDTE' => $tipo,     // Añadir el tipoDTE para compatibilidad
                    'tipoTexto' => $tipoTexto,
                    'cantidad' => $cantidad
                ], function ($mensaje) use ($usuario, $tipoTexto) {
                    // $mensaje->to($usuario->email, $usuario->name)
                    $mensaje->to("joseespana94@gmail.com", $usuario->name)
                        ->subject('Error en Pruebas Masivas MH: ' . $tipoTexto);
                });
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de notificación de error: ' . $e->getMessage());
        }
    }


    /**
     * Obtener descripción textual del tipo de documento
     */
    protected function getTipoTexto($tipo)
    {
        $tipos = [
            '01' => 'Facturas Consumidor Final',
            '03' => 'Comprobantes de Crédito Fiscal',
            '05' => 'Notas de Crédito',
            '06' => 'Notas de Débito',
            '11' => 'Facturas de Exportación',
            '14' => 'Facturas Sujeto Excluido'
        ];

        return $tipos[$tipo] ?? 'Documento Tipo ' . $tipo;
    }

    protected function duplicarDocumento($ventaBase, $nuevoCorrelativo)
    {
        // Crear una nueva instancia persistiendo solo en memoria
        $nuevaVenta = new Venta();

        // Copiar propiedades básicas
        $nuevaVenta->id_empresa = $ventaBase->id_empresa;
        $nuevaVenta->id_sucursal = $ventaBase->id_sucursal;
        $nuevaVenta->id_documento = $ventaBase->id_documento;
        $nuevaVenta->id_cliente = $ventaBase->id_cliente;
        $nuevaVenta->id_bodega = $ventaBase->id_bodega;
        $nuevaVenta->id_usuario = $ventaBase->id_usuario;
        $nuevaVenta->id_vendedor = $ventaBase->id_vendedor;
        $nuevaVenta->id_canal = $ventaBase->id_canal;
        $nuevaVenta->fecha = Carbon::now()->format('Y-m-d');
        $nuevaVenta->fecha_pago = Carbon::now()->format('Y-m-d');
        $nuevaVenta->estado = $ventaBase->estado;
        $nuevaVenta->observaciones = 'Documento de prueba generado automáticamente';
        $nuevaVenta->forma_pago = $ventaBase->forma_pago;
        $nuevaVenta->sub_total = $ventaBase->sub_total;
        $nuevaVenta->iva = $ventaBase->iva;
        $nuevaVenta->iva_retenido = $ventaBase->iva_retenido;
        $nuevaVenta->iva_percibido = $ventaBase->iva_percibido;
        $nuevaVenta->descuento = $ventaBase->descuento;
        $nuevaVenta->total = $ventaBase->total;
        $nuevaVenta->exenta = $ventaBase->exenta ?? 0;
        $nuevaVenta->gravada = $ventaBase->gravada ?? 0;
        $nuevaVenta->no_sujeta = $ventaBase->no_sujeta ?? 0;
        $nuevaVenta->cuenta_a_terceros = $ventaBase->cuenta_a_terceros ?? 0;

        // Campos específicos para facturas de exportación
        if ($ventaBase->tipo_dte == '11') {
            $nuevaVenta->tipo_item_export = $ventaBase->tipo_item_export;
            $nuevaVenta->cod_incoterm = $ventaBase->cod_incoterm;
            $nuevaVenta->incoterm = $ventaBase->incoterm;
            $nuevaVenta->recinto_fiscal = $ventaBase->recinto_fiscal;
            $nuevaVenta->regimen = $ventaBase->regimen;
            $nuevaVenta->seguro = $ventaBase->seguro ?? 0;
            $nuevaVenta->flete = $ventaBase->flete ?? 0;
        }

        // Asignar datos de FE
        $nuevaVenta->tipo_dte = $ventaBase->tipo_dte;
        $nuevaVenta->correlativo = $nuevoCorrelativo;
        $nuevaVenta->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
        $nuevaVenta->numero_control = 'DTE-' . $ventaBase->tipo_dte . '-' .
            $this->sucursal->cod_estable_mh . '0001-' .
            str_pad($nuevoCorrelativo, 15, '0', STR_PAD_LEFT);

        // Marcar como prueba masiva
        $nuevaVenta->prueba_masiva = true;

        // Guardar la referencia a los detalles originales para usarlos en la generación del DTE
        // Esta propiedad no se guardará en la base de datos pero estará disponible en memoria
        $this->detallesOriginales = $ventaBase->detalles;

        // Si es en modo simulación, no guardamos en la BD
        if (!$this->simulationMode) {
            $nuevaVenta->save();

            // Duplicar los detalles
            if ($ventaBase->detalles && count($ventaBase->detalles) > 0) {
                foreach ($ventaBase->detalles as $detalle) {
                    $nuevoDetalle = new Detalle();
                    $nuevoDetalle->id_venta = $nuevaVenta->id;
                    $nuevoDetalle->id_producto = $detalle->id_producto;
                    $nuevoDetalle->codigo = $detalle->codigo;
                    $nuevoDetalle->nombre_producto = $detalle->nombre_producto;
                    $nuevoDetalle->descripcion = $detalle->descripcion;
                    $nuevoDetalle->unidad = $detalle->unidad;
                    $nuevoDetalle->cantidad = $detalle->cantidad;
                    $nuevoDetalle->precio = $detalle->precio;
                    $nuevoDetalle->costo = $detalle->costo;
                    $nuevoDetalle->descuento = $detalle->descuento;
                    $nuevoDetalle->total = $detalle->total;
                    $nuevoDetalle->total_costo = $detalle->total_costo;
                    $nuevoDetalle->exenta = $detalle->exenta ?? 0;
                    $nuevoDetalle->gravada = $detalle->gravada ?? 0;
                    $nuevoDetalle->no_sujeta = $detalle->no_sujeta ?? 0;
                    $nuevoDetalle->cuenta_a_terceros = $detalle->cuenta_a_terceros ?? 0;
                    $nuevoDetalle->save();
                }
            }
        }

        // Actualizar el correlativo en la tabla de documentos
        $documento = Documento::find($ventaBase->id_documento);
        if ($documento) {
            $documento->correlativo = $nuevoCorrelativo;
            $documento->save();
        }

        return $nuevaVenta;
    }

    protected function generarDTE($venta)
    {
        // Establecer límite de tiempo para esta operación
        set_time_limit(30);

        // Si estamos en modo simulación, necesitamos crear una venta temporal
        // con los detalles ya guardados en la relación
        if ($this->simulationMode && isset($this->detallesOriginales)) {
            // Creamos una colección temporal para los detalles
            $detallesTmp = collect();

            // Copiamos cada detalle como un objeto que simulará ser un modelo Eloquent
            foreach ($this->detallesOriginales as $detalle) {
                $nuevoDetalle = new \stdClass();

                // Copiamos todas las propiedades
                foreach ((array)$detalle as $key => $value) {
                    $nuevoDetalle->$key = $value;
                }

                // Añadimos métodos necesarios usando closures
                $nuevoDetalle->producto = function () use ($detalle) {
                    $producto = new \stdClass();
                    $producto->tipo = $detalle->tipo_item == 2 ? 'Servicio' : 'Producto';

                    // Simulamos el comportamiento de pluck
                    $producto->pluck = function ($field) use ($producto) {
                        if ($field == 'tipo') {
                            return collect([$producto->tipo]);
                        }
                        return collect([]);
                    };

                    // Simulamos first() para devolver el objeto
                    $producto->first = function () use ($producto) {
                        return $producto;
                    };

                    return $producto;
                };

                // Añadimos el detalle a la colección
                $detallesTmp->push($nuevoDetalle);
            }

            // Reemplazamos temporalmente los detalles de la venta
            // Guardamos los detalles originales para restaurarlos después
            $detallesOriginales = $venta->detalles;
            $venta->detalles = $detallesTmp;

            // Aseguramos que la venta no se guarde
            $saveMethod = $venta->save;
            $venta->save = function () {
                return true;
            };
        }

        try {
            // Generamos el DTE con la clase correspondiente
            $resultado = null;
            switch ($venta->tipo_dte) {
                case '01':
                    $mh = new MHFactura();
                    $resultado = $mh->generarDTE($venta);
                    break;
                case '03':
                    $mh = new MHCCF();
                    $resultado = $mh->generarDTE($venta);
                    break;
                case '11':
                    $mh = new MHFacturaExportacion();
                    $resultado = $mh->generarDTE($venta);
                    break;
                case '05':
                    $mh = new MHNotaCredito();
                    $resultado = $mh->generarDTE($venta);
                    break;
                case '06':
                    $mh = new MHNotaDebito();
                    $resultado = $mh->generarDTE($venta);
                    break;
                default:
                    throw new \Exception("Tipo de documento no soportado para pruebas masivas: {$venta->tipo_dte}");
            }

            return $resultado;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            // Si estábamos en modo simulación, restauramos los detalles originales
            if ($this->simulationMode && isset($detallesOriginales)) {
                $venta->detalles = $detallesOriginales;
                $venta->save = $saveMethod;
            }

            // Liberar memoria
            gc_collect_cycles();
        }
    }

    protected function firmarYEmitirDTE($venta, $dte, &$token = null)
    {
        // Establecer un límite de tiempo específico para esta operación
        set_time_limit(60); // 60 segundos solo para este método

        // Preparar datos para firma
        $datosParaFirma = [
            'nit' => str_replace('-', '', $this->empresa->nit),
            'activo' => true,
            'passwordPri' => $this->empresa->mh_pwd_certificado,
            'dteJson' => $dte
        ];

        try {
            // Crear cliente HTTP una sola vez y reutilizarlo
            $client = new Client([
                'timeout' => 30, // Timeout razonable
                'connect_timeout' => 10,
                'verify' => false, // Para entornos de desarrollo
                'http_errors' => false // No lanzar excepciones por respuestas de error HTTP
            ]);

            // Firmar DTE usando el servicio externo
            $responseFirma = $client->post(config('app.mh_url_firmado', 'https://facturadtesv.com:8443/firmardocumento/'), [
                'json' => $datosParaFirma,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $bodyFirma = $responseFirma->getBody()->getContents();
            $dteFirmado = json_decode($bodyFirma, true);

            // Liberar recursos
            $responseFirma = null;
            $bodyFirma = null;

            if (isset($dteFirmado['status']) && $dteFirmado['status'] === 'ERROR') {
                return [
                    'success' => false,
                    'message' => 'Error al firmar DTE: ' . ($dteFirmado['body']['mensaje'] ?? 'Error desconocido')
                ];
            }

            // Solo autenticamos si no tenemos un token válido
            if ($token === null) {
                $responseAuth = $client->post(config('app.mh_url_auth', 'https://apitest.dtes.mh.gob.sv/seguridad/auth'), [
                    'form_params' => [
                        'user' => str_replace('-', '', $this->empresa->mh_usuario),
                        'pwd' => $this->empresa->mh_contrasena
                    ]
                ]);

                $bodyAuth = $responseAuth->getBody()->getContents();
                $authData = json_decode($bodyAuth, true);

                // Liberar recursos
                $responseAuth = null;
                $bodyAuth = null;

                if (!isset($authData['body']['token'])) {
                    return [
                        'success' => false,
                        'message' => 'Error de autenticación con MH: ' . json_encode($authData)
                    ];
                }

                $token = $authData['body']['token'];
            }

            // Preparar datos para envío al MH
            $datosMH = [
                'ambiente' => $this->empresa->fe_ambiente,
                'idEnvio' => $venta->id ?? uniqid(),
                'version' => $dte['identificacion']['version'],
                'tipoDte' => $venta->tipo_dte,
                'documento' => isset($dteFirmado['body']) ? $dteFirmado['body'] : $dteFirmado,
                'codigoGeneracion' => $venta->codigo_generacion
            ];

            // Enviar DTE firmado
            $responseEnvio = $client->post(config('app.mh_url_recepcion', 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte'), [
                'json' => $datosMH,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $token
                ]
            ]);

            $bodyEnvio = $responseEnvio->getBody()->getContents();
            $respuestaMH = json_decode($bodyEnvio, true);

            // Liberar recursos
            $responseEnvio = null;
            $bodyEnvio = null;
            $datosMH = null;

            if (isset($respuestaMH['estado']) && $respuestaMH['estado'] === 'PROCESADO') {
                // Registrar el documento como exitoso si no estamos en modo simulación
                if (!$this->simulationMode) {
                    $this->registrarDocumentoExitoso($venta, $dte, $dteFirmado, $respuestaMH);
                }

                return [
                    'success' => true,
                    'message' => 'DTE emitido correctamente',
                    'selloRecibido' => $respuestaMH['selloRecibido'] ?? null,
                    'token' => $token // Devolver el token para reutilizarlo
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al procesar DTE: ' . json_encode($respuestaMH),
                    'token' => $token // Devolver el token para reutilizarlo
                ];
            }
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $errorMessage
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Excepción al procesar DTE: ' . $e->getMessage()
            ];
        } finally {
            // Liberar memoria explícitamente al final del proceso
            gc_collect_cycles();
        }
    }

    protected function registrarDocumentoExitoso($venta, $dte, $dteFirmado, $respuestaMH)
    {
        try {
            // Guardar solo los campos necesarios para evitar problemas
            $venta->dte = $dte;
            $venta->sello_mh = $respuestaMH['selloRecibido'];
            $venta->estado = 'Prueba';

            // Guardar solo estos campos específicos
            Venta::where('id', $venta->id)->update([
                'dte' => $dte,
                'sello_mh' => $respuestaMH['selloRecibido'],
                'estado' => 'Prueba'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al registrar documento exitoso: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Restaurar el correlativo original del documento
     */
    protected function restaurarCorrelativo($documento, $correlativoOriginal)
    {
        // Restaurar correlativo
        if ($documento) {
            $documento->correlativo = $correlativoOriginal;
            $documento->save();
            return true;
        }

        return false;
    }

    public function eliminarPruebasMasivas($empresaId = null)
    {
        try {
            // Usar el ID de empresa proporcionado o el del objeto actual
            $idEmpresa = $empresaId ?? $this->empresaId;

            if (!$idEmpresa) {
                throw new \Exception("No se ha especificado un ID de empresa válido");
            }

            // Eliminar ventas marcadas como pruebas masivas
            $ventasToDelete = Venta::where('prueba_masiva', true)
                ->where('id_empresa', $idEmpresa)
                ->get();

            $count = 0;
            foreach ($ventasToDelete as $venta) {
                // Eliminar los detalles de la venta
                $venta->detalles()->delete();

                // Eliminar la venta
                $venta->delete();
                $count++;
            }

            Log::info("Se eliminaron {$count} ventas de pruebas masivas para la empresa {$idEmpresa}");

            return true;
        } catch (\Exception $e) {
            Log::error("Error al eliminar pruebas masivas: " . $e->getMessage());
            return false;
        }
    }

    protected function eliminarCorretivo()
    {
        // Solo si estamos en procesamiento en segundo plano
        // if (app()->runningInConsole()) {
        $ventasToDelete = Venta::where('prueba_masiva', true)
            ->where('id_empresa', $this->empresaId)->get();

        foreach ($ventasToDelete as $venta) {
            $venta->delete();
        }
        // }

        return true;
    }
}
