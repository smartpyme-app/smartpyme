<?php

namespace App\Services;

use App\Constants\FacturacionElectronica\FEConstants;
use App\Models\Admin\Documento;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\MH\MHFactura;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFacturaExportacion;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHNotaDebito;
use App\Models\MH\MHSujetoExcluidoGasto;
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
    protected $baseGasto;
    protected $empresa;
    protected $sucursal;
    protected $ambiente;
    protected $simulationMode = true; // No afecta realmente los correlativos
    protected $userId; // Para ejecución en segundo plano
    protected $empresaId; // Para ejecución en segundo plano
    protected $batchSize = 5;

    protected $ventasCCFGeneradas = [];
    protected $notasCreditoRequeridas = 50;
    protected $notasDebitoRequeridas = 50;
    protected $detallesOriginales;

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
            'notasCredito' => [
                'emitidas' => \App\Models\Ventas\Devoluciones\Devolucion::where('tipo_dte', FEConstants::TIPO_DTE_NOTA_DE_CREDITO)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 50
            ],
            'notasDebito' => [
                'emitidas' => \App\Models\Ventas\Devoluciones\Devolucion::where('tipo_dte', FEConstants::TIPO_DTE_NOTA_DE_DEBITO)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 50
            ],
            'facturasExportacion' => [
                'emitidas' => Venta::where('tipo_dte', FEConstants::TIPO_DTE_FACTURAS_DE_EXPORTACION)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 90
            ],
            'sujetoExcluido' => [
                'emitidas' => \App\Models\Compras\Gastos\Gasto::where('tipo_dte', FEConstants::TIPO_DTE_FACTURA_DE_SUJETO_EXCLUIDO)
                    // ->where('prueba_masiva', true)
                    ->where('sello_mh', '!=', null)
                    ->where('dte', '!=', null)
                    ->where('id_empresa', $this->empresa->id)
                    ->count(),
                'requeridas' => 25
            ],
        ];

        return $stats;
    }

    public function ejecutarPruebasMasivas($tipo, $cantidad, $idDocumentoBase = null, $userId = null)
    {
        try {
            set_time_limit(30);
            ini_set('memory_limit', '128M');

            $this->inicializarDatosUsuario($userId);

            if ($this->ambiente !== '00') {
                return [
                    'success' => false,
                    'message' => 'Las pruebas masivas solo pueden ejecutarse en ambiente de pruebas'
                ];
            }

            if ($idDocumentoBase) {
                if ($tipo === '14') {
                    // Para sujeto excluido, validar en gastos
                    $baseGastoExiste = \App\Models\Compras\Gastos\Gasto::where('id', $idDocumentoBase)
                        ->where('id_empresa', $this->empresa->id)
                        ->where('tipo_dte', '14')
                        ->where('sello_mh', '!=', null)
                        ->exists();

                    if (!$baseGastoExiste) {
                        return [
                            'success' => false,
                            'message' => 'No se encontró el gasto de sujeto excluido especificado'
                        ];
                    }
                } else {
                    // Para otros tipos, validar en ventas
                    $baseVentaExiste = Venta::where('id', $idDocumentoBase)
                        ->where('id_empresa', $this->empresa->id)
                        ->exists();

                    if (!$baseVentaExiste) {
                        return [
                            'success' => false,
                            'message' => 'No se encontró el documento base especificado'
                        ];
                    }
                }
            } else {
                if ($tipo === '14') {
                    // Para sujeto excluido, buscar gastos
                    $baseGastoExiste = \App\Models\Compras\Gastos\Gasto::where('tipo_dte', '14')
                        ->where('sello_mh', '!=', null)
                        ->where('id_empresa', $this->empresa->id)
                        ->exists();

                    if (!$baseGastoExiste) {
                        return [
                            'success' => false,
                            'message' => 'No se encontraron gastos de sujeto excluido para generar las pruebas'
                        ];
                    }
                } else {
                    $tipoBase = ($tipo === '05' || $tipo === '06') ? '03' : $tipo;

                    $baseVentaExiste = Venta::where('tipo_dte', $tipoBase)
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
            }

            $tiposDescriptivos = [
                '01' => 'Facturas Consumidor Final',
                '03' => 'Comprobantes de Crédito Fiscal',
                '05' => 'Notas de Crédito',
                '06' => 'Notas de Débito',
                '11' => 'Facturas de Exportación',
                '14' => 'Facturas de Sujeto Excluido'
            ];

            $descripcionTipo = $tiposDescriptivos[$tipo] ?? "documentos tipo $tipo";

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

    /**
     * Procesar pruebas masivas
     */
    public function procesarPruebasMasivas($tipo, $cantidad, $idDocumentoBase = null, $userId = null, $correlativoInicial = null)
    {
        try {
            set_time_limit(90);
            ini_set('memory_limit', '256M');

            $this->inicializarDatosUsuario($userId);

            if ($this->ambiente !== '00') {
                return [
                    'success' => false,
                    'message' => 'Las pruebas masivas solo pueden ejecutarse en ambiente de pruebas'
                ];
            }

            // Obtener documento base
            if ($idDocumentoBase) {
                if ($tipo === '14') {
                    // Para sujeto excluido, buscar en gastos
                    $this->baseGasto = \App\Models\Compras\Gastos\Gasto::with(['proveedor', 'empresa', 'sucursal'])
                        ->findOrFail($idDocumentoBase);
                } else {
                    $this->baseVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal', 'documento'])
                        ->findOrFail($idDocumentoBase);
                }
            } else {
                if ($tipo === '14') {
                    // Para sujeto excluido, buscar gasto base
                    $this->baseGasto = \App\Models\Compras\Gastos\Gasto::with(['proveedor', 'empresa', 'sucursal'])
                        ->where('tipo_dte', '14')
                        ->where('sello_mh', '!=', null)
                        ->where('id_empresa', $this->empresa->id)
                        ->latest()
                        ->first();
                } else {
                    $tipoBase = ($tipo === '05' || $tipo === '06') ? '03' : $tipo;

                    $this->baseVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal', 'documento'])
                        ->where('tipo_dte', $tipoBase)
                        ->where('sello_mh', '!=', null)
                        ->where('id_empresa', $this->empresa->id)
                        ->latest()
                        ->first();
                }
            }

            if ($tipo === '14') {
                if (!$this->baseGasto) {
                    return [
                        'success' => false,
                        'message' => 'No se encontró un gasto base para generar las pruebas de sujeto excluido'
                    ];
                }
                $this->empresa = $this->baseGasto->empresa;
                $this->sucursal = $this->baseGasto->sucursal;
            } else {
                if (!$this->baseVenta) {
                    return [
                        'success' => false,
                        'message' => 'No se encontró un documento base para generar las pruebas'
                    ];
                }
                $this->empresa = $this->baseVenta->empresa;
                $this->sucursal = $this->baseVenta->sucursal;
            }

            $resultados = [
                'exitosos' => 0,
                'fallidos' => 0,
                'detalles' => []
            ];

            // LÓGICA PRINCIPAL: Si es CCF, también generar notas automáticamente
            if ($tipo === '03') {
                // CCF: genera CCF + NC + ND automáticamente
                $resultados = $this->procesarCCFConNotas($cantidad, $correlativoInicial, $resultados);
            } elseif ($tipo === '05') {
                // Solo NC: genera CCF + NC únicamente  
                $resultados = $this->procesarNotasCredito($cantidad, $correlativoInicial, $resultados);
            } elseif ($tipo === '06') {
                // Solo ND: genera CCF + ND únicamente
                $resultados = $this->procesarNotasDebito($cantidad, $correlativoInicial, $resultados);
            } elseif ($tipo === '14') {
                // Sujeto Excluido: genera gastos de sujeto excluido
                $resultados = $this->procesarSujetoExcluido($cantidad, $correlativoInicial, $resultados);
            } else {
                // Otros documentos (facturas, exportación)
                $resultados = $this->procesarDocumentosNormales($tipo, $cantidad, $correlativoInicial, $resultados);
            }


            // if ($tipo === '03') { // CCF
            //     $resultados = $this->procesarCCFConNotas($cantidad, $correlativoInicial, $resultados);
            // } else {
            //     $resultados = $this->procesarDocumentosNormales($tipo, $cantidad, $correlativoInicial, $resultados);
            // }

            // Restaurar correlativo y obtener estadísticas
            if ($tipo === '14') {
                // Para gastos no hay correlativo que restaurar
                // Los gastos usan referencia en lugar de correlativo
            } else {
                $documento = $this->baseVenta->documento;
                $correlativoOriginal = $documento->correlativo;
                $this->restaurarCorrelativo($documento, $correlativoOriginal);
            }

            DB::commit();

            $estadisticas = $this->obtenerEstadisticas($userId);

            if ($userId) {
                $this->enviarNotificacionPorCorreo($resultados, $tipo, $cantidad, $estadisticas);
            }

            $this->eliminarPruebasMasivas($this->empresa->id);

            return [
                'success' => true,
                'message' => "Proceso completado: {$resultados['exitosos']} documentos emitidos, {$resultados['fallidos']} fallidos",
                'resultados' => $resultados,
                'stats' => $estadisticas
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en pruebas masivas: ' . $e->getMessage());

            if ($userId) {
                $this->enviarNotificacionError($e->getMessage(), $tipo, $cantidad);
            }

            return [
                'success' => false,
                'message' => 'Error en el proceso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Procesar Sujeto Excluido
     */
    protected function procesarSujetoExcluido($cantidad, $correlativoInicial, $resultados)
    {
        DB::beginTransaction();

        try {
            // Obtener último número de referencia de gastos
            $ultimaReferencia = \App\Models\Compras\Gastos\Gasto::where('tipo_dte', '14')
                ->where('id_empresa', $this->empresa->id)
                ->max('referencia');

            $startReferencia = $correlativoInicial !== null ?
                max($correlativoInicial, $ultimaReferencia + 1) :
                max($this->baseGasto->referencia, $ultimaReferencia + 1);

            $totalLotes = ceil($cantidad / $this->batchSize);
            $token = null;

            // Procesar gastos en lotes
            for ($lote = 0; $lote < $totalLotes; $lote++) {
                $tamanoLote = min($this->batchSize, $cantidad - ($lote * $this->batchSize));

                if ($tamanoLote <= 0) break;

                for ($i = 0; $i < $tamanoLote; $i++) {
                    $newReferencia = $startReferencia + ($lote * $this->batchSize) + $i;

                    try {
                        // Generar gasto de sujeto excluido
                        $gastoResult = $this->generarYEmitirGasto($newReferencia, $token);

                        if ($gastoResult['success']) {
                            $resultados['exitosos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newReferencia,
                                'tipo' => 'Sujeto Excluido',
                                'status' => 'Éxito',
                                'message' => 'Gasto de sujeto excluido emitido correctamente'
                            ];

                            if (isset($gastoResult['token'])) {
                                $token = $gastoResult['token'];
                            }
                        } else {
                            $resultados['fallidos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newReferencia,
                                'tipo' => 'Sujeto Excluido',
                                'status' => 'Error',
                                'message' => $gastoResult['message']
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error en Sujeto Excluido: ' . $e->getMessage());
                        $resultados['fallidos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $newReferencia,
                            'tipo' => 'Sujeto Excluido',
                            'status' => 'Error',
                            'message' => 'Excepción: ' . $e->getMessage()
                        ];
                    }
                }

                if ($lote < $totalLotes - 1) {
                    sleep(1);
                }
                gc_collect_cycles();
            }

            return $resultados;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * NUEVA FUNCIÓN: Procesar CCF con notas automáticas
     */
    protected function procesarCCFConNotas($cantidad, $correlativoInicial, $resultados)
    {
        DB::beginTransaction();

        try {
            $documento = $this->baseVenta->documento;
            $correlativoOriginal = $documento->correlativo;

            $ultimoCorrelativo = Venta::where('tipo_dte', '03')
                ->where('id_empresa', $this->empresa->id)
                ->where('id_documento', $this->baseVenta->id_documento)
                ->max('correlativo');

            $startCorrelativo = $correlativoInicial !== null ?
                max($correlativoInicial, $ultimoCorrelativo + 1) :
                max($correlativoOriginal, $ultimoCorrelativo + 1);

            $totalLotes = ceil($cantidad / $this->batchSize);
            $token = null;

            // Procesar CCF en lotes
            for ($lote = 0; $lote < $totalLotes; $lote++) {
                $tamanoLote = min($this->batchSize, $cantidad - ($lote * $this->batchSize));

                if ($tamanoLote <= 0) break;

                for ($i = 0; $i < $tamanoLote; $i++) {
                    $newCorrelativo = $startCorrelativo + ($lote * $this->batchSize) + $i;

                    try {
                        // Generar CCF
                        $ccfResult = $this->generarYEmitirDocumento('03', $newCorrelativo, $token);

                        if ($ccfResult['success']) {
                            $resultados['exitosos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativo,
                                'tipo' => 'CCF',
                                'status' => 'Éxito',
                                'message' => 'CCF emitido correctamente'
                            ];

                            // Guardar referencia del CCF para generar notas
                            $this->ventasCCFGeneradas[] = $ccfResult['venta'];

                            if (isset($ccfResult['token'])) {
                                $token = $ccfResult['token'];
                            }
                        } else {
                            $resultados['fallidos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativo,
                                'tipo' => 'CCF',
                                'status' => 'Error',
                                'message' => $ccfResult['message']
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error en CCF: ' . $e->getMessage());
                        $resultados['fallidos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $newCorrelativo,
                            'tipo' => 'CCF',
                            'status' => 'Error',
                            'message' => 'Excepción: ' . $e->getMessage()
                        ];
                    }
                }

                if ($lote < $totalLotes - 1) {
                    sleep(1);
                }
                gc_collect_cycles();
            }

            // GENERAR NOTAS DE CRÉDITO Y DÉBITO AUTOMÁTICAMENTE
            $this->generarNotasAutomaticas($resultados, $token);

            return $resultados;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function procesarNotasCredito($cantidad, $correlativoInicial, $resultados)
    {
        DB::beginTransaction();

        try {
            $documento = $this->baseVenta->documento;
            $correlativoOriginal = $documento->correlativo;

            // Obtener último correlativo de CCF para generar nuevos CCF
            $ultimoCorrelativoCCF = Venta::where('tipo_dte', '03')
                ->where('id_empresa', $this->empresa->id)
                ->where('id_documento', $this->baseVenta->id_documento)
                ->max('correlativo');

            // Si hay correlativo inicial, usarlo para CCF, sino continuar después del último
            $startCorrelativoCCF = $correlativoInicial !== null ?
                $correlativoInicial :
                max($correlativoOriginal, $ultimoCorrelativoCCF + 1);

            // Obtener último correlativo de Notas de Crédito
            $ultimoCorrelativoNC = \App\Models\Ventas\Devoluciones\Devolucion::where('tipo_dte', '05')
                ->where('id_empresa', $this->empresa->id)
                ->max('correlativo');

            // Si hay correlativo inicial, las NC empiezan después del último CCF que se va a generar
            // Si no hay correlativo inicial, las NC continúan después del último correlativo NC existente
            if ($correlativoInicial !== null) {
                $ultimoCCFGenerado = $startCorrelativoCCF + $cantidad - 1;
                $startCorrelativoNC = $ultimoCCFGenerado + 1;
            } else {
                $startCorrelativoNC = ($ultimoCorrelativoNC ?? 0) + 1;
            }

            $totalLotes = ceil($cantidad / $this->batchSize);
            $token = null;

            // Generar CCF y NC en lotes
            for ($lote = 0; $lote < $totalLotes; $lote++) {
                $tamanoLote = min($this->batchSize, $cantidad - ($lote * $this->batchSize));
                if ($tamanoLote <= 0) break;

                for ($i = 0; $i < $tamanoLote; $i++) {
                    $indiceProceso = ($lote * $this->batchSize) + $i;
                    $newCorrelativoCCF = $startCorrelativoCCF + $indiceProceso;
                    $newCorrelativoNC = $startCorrelativoNC + $indiceProceso;

                    try {
                        // Generar CCF
                        $ccfResult = $this->generarYEmitirDocumento('03', $newCorrelativoCCF, $token);

                        if ($ccfResult['success']) {
                            $resultados['exitosos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativoCCF,
                                'tipo' => 'CCF',
                                'status' => 'Éxito',
                                'message' => 'CCF emitido correctamente'
                            ];

                            // Generar Nota de Crédito con correlativo específico y secuencial
                            $notaCreditoResult = $this->generarNotaCredito($ccfResult['venta'], $token, $newCorrelativoNC);
                            if ($notaCreditoResult['success']) {
                                $resultados['exitosos']++;
                                $resultados['detalles'][] = [
                                    'correlativo' => 'NC-' . $newCorrelativoNC,
                                    'tipo' => 'Nota Crédito',
                                    'status' => 'Éxito',
                                    'message' => 'Nota de crédito generada automáticamente'
                                ];
                            } else {
                                $resultados['fallidos']++;
                                $resultados['detalles'][] = [
                                    'correlativo' => 'NC-' . $newCorrelativoNC,
                                    'tipo' => 'Nota Crédito',
                                    'status' => 'Error',
                                    'message' => $notaCreditoResult['message']
                                ];
                            }

                            if (isset($ccfResult['token'])) {
                                $token = $ccfResult['token'];
                            }
                        } else {
                            $resultados['fallidos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativoCCF,
                                'tipo' => 'CCF',
                                'status' => 'Error',
                                'message' => $ccfResult['message']
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error en NC: ' . $e->getMessage());
                        $resultados['fallidos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $newCorrelativoCCF,
                            'tipo' => 'CCF+NC',
                            'status' => 'Error',
                            'message' => 'Excepción: ' . $e->getMessage()
                        ];
                    }
                }

                if ($lote < $totalLotes - 1) {
                    sleep(1);
                }
                gc_collect_cycles();
            }

            return $resultados;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function procesarNotasDebito($cantidad, $correlativoInicial, $resultados)
    {
        DB::beginTransaction();

        try {
            $documento = $this->baseVenta->documento;
            $correlativoOriginal = $documento->correlativo;

            // Obtener último correlativo de CCF para generar nuevos CCF
            $ultimoCorrelativoCCF = Venta::where('tipo_dte', '03')
                ->where('id_empresa', $this->empresa->id)
                ->where('id_documento', $this->baseVenta->id_documento)
                ->max('correlativo');

            // Si hay correlativo inicial, usarlo para CCF, sino continuar después del último
            $startCorrelativoCCF = $correlativoInicial !== null ?
                $correlativoInicial :
                max($correlativoOriginal, $ultimoCorrelativoCCF + 1);

            // Obtener último correlativo de Notas de Débito
            $ultimoCorrelativoND = \App\Models\Ventas\Devoluciones\Devolucion::where('tipo_dte', '06')
                ->where('id_empresa', $this->empresa->id)
                ->max('correlativo');

            // Si hay correlativo inicial, las ND empiezan después del último CCF que se va a generar
            // Si no hay correlativo inicial, las ND continúan después del último correlativo ND existente
            if ($correlativoInicial !== null) {
                $ultimoCCFGenerado = $startCorrelativoCCF + $cantidad - 1;
                $startCorrelativoND = $ultimoCCFGenerado + 1;
            } else {
                $startCorrelativoND = ($ultimoCorrelativoND ?? 0) + 1;
            }

            $totalLotes = ceil($cantidad / $this->batchSize);
            $token = null;

            // Generar CCF y ND en lotes
            for ($lote = 0; $lote < $totalLotes; $lote++) {
                $tamanoLote = min($this->batchSize, $cantidad - ($lote * $this->batchSize));
                if ($tamanoLote <= 0) break;

                for ($i = 0; $i < $tamanoLote; $i++) {
                    $indiceProceso = ($lote * $this->batchSize) + $i;
                    $newCorrelativoCCF = $startCorrelativoCCF + $indiceProceso;
                    $newCorrelativoND = $startCorrelativoND + $indiceProceso;

                    try {
                        // Generar CCF
                        $ccfResult = $this->generarYEmitirDocumento('03', $newCorrelativoCCF, $token);

                        if ($ccfResult['success']) {
                            $resultados['exitosos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativoCCF,
                                'tipo' => 'CCF',
                                'status' => 'Éxito',
                                'message' => 'CCF emitido correctamente'
                            ];

                            // Generar Nota de Débito con correlativo específico y secuencial
                            $notaDebitoResult = $this->generarNotaDebito($ccfResult['venta'], $token, $newCorrelativoND);
                            if ($notaDebitoResult['success']) {
                                $resultados['exitosos']++;
                                $resultados['detalles'][] = [
                                    'correlativo' => 'ND-' . $newCorrelativoND,
                                    'tipo' => 'Nota Débito',
                                    'status' => 'Éxito',
                                    'message' => 'Nota de débito generada automáticamente'
                                ];
                            } else {
                                $resultados['fallidos']++;
                                $resultados['detalles'][] = [
                                    'correlativo' => 'ND-' . $newCorrelativoND,
                                    'tipo' => 'Nota Débito',
                                    'status' => 'Error',
                                    'message' => $notaDebitoResult['message']
                                ];
                            }

                            if (isset($ccfResult['token'])) {
                                $token = $ccfResult['token'];
                            }
                        } else {
                            $resultados['fallidos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativoCCF,
                                'tipo' => 'CCF',
                                'status' => 'Error',
                                'message' => $ccfResult['message']
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error en ND: ' . $e->getMessage());
                        $resultados['fallidos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $newCorrelativoCCF,
                            'tipo' => 'CCF+ND',
                            'status' => 'Error',
                            'message' => 'Excepción: ' . $e->getMessage()
                        ];
                    }
                }

                if ($lote < $totalLotes - 1) {
                    sleep(1);
                }
                gc_collect_cycles();
            }

            return $resultados;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * NUEVA FUNCIÓN: Generar notas automáticamente a partir de CCF
     */
    protected function generarNotasAutomaticas(&$resultados, &$token)
    {
        // Calcular correlativos iniciales
        $ultimoCorrelativoCCF = Venta::where('tipo_dte', '03')
            ->where('id_empresa', $this->empresa->id)
            ->max('correlativo');

        $inicioNC = $ultimoCorrelativoCCF + 1;
        $inicioND = $inicioNC + count($this->ventasCCFGeneradas);

        $contadorNC = 0;
        $contadorND = 0;

        foreach ($this->ventasCCFGeneradas as $ccfVenta) {
            try {
                // Generar Nota de Crédito con correlativo específico
                $correlativoNC = $inicioNC + $contadorNC;
                $notaCreditoResult = $this->generarNotaCredito($ccfVenta, $token, $correlativoNC);
                if ($notaCreditoResult['success']) {
                    $resultados['exitosos']++;
                    $resultados['detalles'][] = [
                        'correlativo' => 'NC-' . $correlativoNC,
                        'tipo' => 'Nota Crédito',
                        'status' => 'Éxito',
                        'message' => 'Nota de crédito generada automáticamente'
                    ];
                    if (isset($notaCreditoResult['token'])) {
                        $token = $notaCreditoResult['token'];
                    }
                } else {
                    $resultados['fallidos']++;
                    $resultados['detalles'][] = [
                        'correlativo' => 'NC-' . $correlativoNC,
                        'tipo' => 'Nota Crédito',
                        'status' => 'Error',
                        'message' => $notaCreditoResult['message']
                    ];
                }
                $contadorNC++;

                // Generar Nota de Débito con correlativo específico
                $correlativoND = $inicioND + $contadorND;
                $notaDebitoResult = $this->generarNotaDebito($ccfVenta, $token, $correlativoND);
                if ($notaDebitoResult['success']) {
                    $resultados['exitosos']++;
                    $resultados['detalles'][] = [
                        'correlativo' => 'ND-' . $correlativoND,
                        'tipo' => 'Nota Débito',
                        'status' => 'Éxito',
                        'message' => 'Nota de débito generada automáticamente'
                    ];
                    if (isset($notaDebitoResult['token'])) {
                        $token = $notaDebitoResult['token'];
                    }
                } else {
                    $resultados['fallidos']++;
                    $resultados['detalles'][] = [
                        'correlativo' => 'ND-' . $correlativoND,
                        'tipo' => 'Nota Débito',
                        'status' => 'Error',
                        'message' => $notaDebitoResult['message']
                    ];
                }
                $contadorND++;
            } catch (\Exception $e) {
                Log::error('Error generando notas automáticas: ' . $e->getMessage());
                $resultados['fallidos'] += 2;
            }
        }
    }

    /**
     * NUEVA FUNCIÓN: Generar nota de crédito
     */
    protected function generarNotaCredito($ventaBase, &$token, $correlativoEspecifico = null)

    {
        try {
            // Crear una nueva devolución (nota de crédito)
            $devolucion = new \App\Models\Ventas\Devoluciones\Devolucion();

            // Obtener el último correlativo de notas de crédito
            $ultimoCorrelativoNC = \App\Models\Ventas\Devoluciones\Devolucion::where('tipo_dte', '05')
                ->where('id_empresa', $this->empresa->id)
                ->max('correlativo');

            $nuevoCorrelativoNC = $correlativoEspecifico ?? (($ultimoCorrelativoNC ?? 0) + 1);

            $devolucion->fill([
                'tipo_dte' => '05',
                'correlativo' => $nuevoCorrelativoNC,
                'numero_control' => 'DTE-05-' . $this->sucursal->cod_estable_mh . '0001-' . str_pad($nuevoCorrelativoNC, 15, '0', STR_PAD_LEFT),
                'codigo_generacion' => strtoupper(Uuid::uuid4()->toString()),
                'fecha' => Carbon::now()->format('Y-m-d'),
                'fecha_pago' => Carbon::now()->format('Y-m-d'),
                'prueba_masiva' => true,
                'estado' => 'Pendiente',
                'observaciones' => 'Nota de crédito de prueba generada automáticamente',
                'sub_total' => $ventaBase->sub_total,
                'descuento' => $ventaBase->descuento,
                'iva' => $ventaBase->iva,
                'total' => $ventaBase->total,
                'id_venta' => $ventaBase->id, // Relacionar con la venta original
                'id_cliente' => $ventaBase->id_cliente,
                'id_usuario' => $ventaBase->id_usuario,
                'id_empresa' => $this->empresa->id,
                'nombre_cliente' => $ventaBase->nombre_cliente,
                'forma_pago' => $ventaBase->forma_pago
            ]);

            $devolucion->save();

            // Duplicar los detalles
            foreach ($ventaBase->detalles as $detalleOriginal) {
                $devolucion->detalles()->create([
                    'id_producto' => $detalleOriginal->id_producto,
                    'cantidad' => $detalleOriginal->cantidad,
                    'precio' => $detalleOriginal->precio,
                    'costo' => $detalleOriginal->costo,
                    'descuento' => $detalleOriginal->descuento,
                    'total' => $detalleOriginal->total,
                    'descripcion' => $detalleOriginal->descripcion ?? 'Producto'
                ]);
            }

            // Recargar con relaciones
            $devolucion = \App\Models\Ventas\Devoluciones\Devolucion::with(['detalles', 'cliente', 'empresa', 'venta'])
                ->find($devolucion->id);

            // Generar el DTE
            $dte = $this->generarDTENotaCredito($devolucion);

            // Firmar y emitir
            $resultado = $this->firmarYEmitirDTE($devolucion, $dte, $token);

            if ($resultado['success']) {
                \App\Models\Ventas\Devoluciones\Devolucion::where('id', $devolucion->id)
                    ->update([
                        'dte' => $dte,
                        'sello_mh' => $resultado['selloRecibido'] ?? null,
                        'prueba_masiva' => true
                    ]);
            }

            return $resultado;
        } catch (\Exception $e) {
            Log::error('Error generando nota de crédito: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error generando nota de crédito: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Generar nota de débito
     */
    protected function generarNotaDebito($ventaBase, &$token, $correlativoEspecifico = null)
    {
        try {

            Log::info("Iniciando creación de Nota de Débito", [
                'venta_base_id' => $ventaBase->id,
                'empresa_id' => $this->empresa->id
            ]);


            // Crear una nueva devolución (nota de débito)
            $devolucion = new \App\Models\Ventas\Devoluciones\Devolucion();

            // Obtener el último correlativo de notas de débito
            $ultimoCorrelativoND = \App\Models\Ventas\Devoluciones\Devolucion::where('tipo_dte', '06')
                ->where('id_empresa', $this->empresa->id)
                ->max('correlativo');

            $nuevoCorrelativoND = $correlativoEspecifico ?? (($ultimoCorrelativoND ?? 0) + 1);

            $devolucion->fill([
                'tipo_dte' => '06',
                'correlativo' => $nuevoCorrelativoND,
                'numero_control' => 'DTE-06-' . $this->sucursal->cod_estable_mh . '0001-' . str_pad($nuevoCorrelativoND, 15, '0', STR_PAD_LEFT),
                'codigo_generacion' => strtoupper(Uuid::uuid4()->toString()),
                'fecha' => Carbon::now()->format('Y-m-d'),
                'fecha_pago' => Carbon::now()->format('Y-m-d'),
                'prueba_masiva' => true,
                'estado' => 'Pendiente',
                'observaciones' => 'Nota de débito de prueba generada automáticamente',
                'sub_total' => $ventaBase->sub_total,
                'descuento' => $ventaBase->descuento,
                'iva' => $ventaBase->iva,
                'total' => $ventaBase->total,
                'id_venta' => $ventaBase->id, // Relacionar con la venta original
                'id_cliente' => $ventaBase->id_cliente,
                'id_usuario' => $ventaBase->id_usuario,
                'id_empresa' => $this->empresa->id,
                'nombre_cliente' => $ventaBase->nombre_cliente,
                'forma_pago' => $ventaBase->forma_pago
            ]);

            $devolucion->save();

            // Duplicar los detalles
            foreach ($ventaBase->detalles as $detalleOriginal) {
                $devolucion->detalles()->create([
                    'id_producto' => $detalleOriginal->id_producto,
                    'cantidad' => $detalleOriginal->cantidad,
                    'precio' => $detalleOriginal->precio,
                    'costo' => $detalleOriginal->costo,
                    'descuento' => $detalleOriginal->descuento,
                    'total' => $detalleOriginal->total,
                    'descripcion' => $detalleOriginal->descripcion ?? 'Producto'
                ]);
            }

            // Recargar con relaciones
            $devolucion = \App\Models\Ventas\Devoluciones\Devolucion::with(['detalles', 'cliente', 'empresa', 'venta'])
                ->find($devolucion->id);

            // Generar el DTE
            $dte = $this->generarDTENotaDebito($devolucion);

            // Firmar y emitir
            $resultado = $this->firmarYEmitirDTE($devolucion, $dte, $token);

            if ($resultado['success']) {
                \App\Models\Ventas\Devoluciones\Devolucion::where('id', $devolucion->id)
                    ->update([
                        'dte' => $dte,
                        'sello_mh' => $resultado['selloRecibido'] ?? null,
                        'prueba_masiva' => true
                    ]);
            }

            return $resultado;
        } catch (\Exception $e) {
            Log::error('Error generando nota de débito: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error generando nota de débito: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NUEVA FUNCIÓN: Generar DTE para nota de crédito
     */
    protected function generarDTENotaCredito($devolucion)
    {
        $mh = new MHNotaCredito();
        return $mh->generarDTE($devolucion);
    }

    /**
     * NUEVA FUNCIÓN: Generar DTE para nota de débito
     */
    protected function generarDTENotaDebito($devolucion)
    {
        $mh = new MHNotaDebito();
        return $mh->generarDTE($devolucion);
    }

    /**
     * NUEVA FUNCIÓN: Generar DTE para gasto de sujeto excluido
     */
    protected function generarDTEGasto($gasto)
    {
        $mh = new MHSujetoExcluidoGasto();
        return $mh->generarDTE($gasto);
    }

    /**
     * Procesar documentos normales (facturas, etc.) - SIN CAMBIOS
     */
    protected function procesarDocumentosNormales($tipo, $cantidad, $correlativoInicial, $resultados)
    {
        // La lógica original para facturas y otros documentos permanece igual
        DB::beginTransaction();

        try {
            $documento = $this->baseVenta->documento;
            $correlativoOriginal = $documento->correlativo;

            $ultimoCorrelativo = Venta::where('tipo_dte', $tipo)
                ->where('id_empresa', $this->empresa->id)
                ->where('id_documento', $this->baseVenta->id_documento)
                ->max('correlativo');

            $startCorrelativo = $correlativoInicial !== null ?
                max($correlativoInicial, $ultimoCorrelativo + 1) :
                max($correlativoOriginal, $ultimoCorrelativo + 1);

            $totalLotes = ceil($cantidad / $this->batchSize);
            $token = null;

            for ($lote = 0; $lote < $totalLotes; $lote++) {
                $tamanoLote = min($this->batchSize, $cantidad - ($lote * $this->batchSize));

                if ($tamanoLote <= 0) break;

                for ($i = 0; $i < $tamanoLote; $i++) {
                    $newCorrelativo = $startCorrelativo + ($lote * $this->batchSize) + $i;

                    try {
                        $resultado = $this->generarYEmitirDocumento($tipo, $newCorrelativo, $token);

                        if ($resultado['success']) {
                            $resultados['exitosos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativo,
                                'status' => 'Éxito',
                                'message' => 'DTE emitido correctamente'
                            ];

                            if (isset($resultado['token'])) {
                                $token = $resultado['token'];
                            }
                        } else {
                            $resultados['fallidos']++;
                            $resultados['detalles'][] = [
                                'correlativo' => $newCorrelativo,
                                'status' => 'Error',
                                'message' => $resultado['message']
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Error en documento normal: ' . $e->getMessage());
                        $resultados['fallidos']++;
                        $resultados['detalles'][] = [
                            'correlativo' => $newCorrelativo,
                            'status' => 'Error',
                            'message' => 'Excepción: ' . $e->getMessage()
                        ];
                    }
                }

                if ($lote < $totalLotes - 1) {
                    sleep(1);
                }
                gc_collect_cycles();
            }

            return $resultados;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * NUEVA FUNCIÓN: Generar y emitir gasto de sujeto excluido
     */
    protected function generarYEmitirGasto($referencia, &$token)
    {
        // Crear nuevo gasto
        $nuevoGasto = new \App\Models\Compras\Gastos\Gasto();
        $nuevoGasto->fill([
            'tipo_dte' => '14',
            'referencia' => $referencia,
            'numero_control' => 'DTE-14-' . $this->sucursal->cod_estable_mh . 'P001-' . str_pad($referencia, 15, '0', STR_PAD_LEFT),
            'codigo_generacion' => strtoupper(Uuid::uuid4()->toString()),
            'fecha' => Carbon::now()->format('Y-m-d'),
            'fecha_pago' => Carbon::now()->format('Y-m-d'),
            'prueba_masiva' => true,
            'estado' => 'Pendiente',
            'concepto' => 'Gasto de prueba generado automáticamente',
            'sub_total' => $this->baseGasto->sub_total,
            'descuento' => $this->baseGasto->descuento,
            'iva' => $this->baseGasto->iva,
            'iva_retenido' => $this->baseGasto->iva_retenido,
            'renta_retenida' => $this->baseGasto->renta_retenida,
            'total' => $this->baseGasto->total,
            'forma_pago' => $this->baseGasto->forma_pago,
            'id_proveedor' => $this->baseGasto->id_proveedor,
            'id_usuario' => $this->baseGasto->id_usuario,
            'id_empresa' => $this->empresa->id,
            'id_sucursal' => $this->sucursal->id,
            'codigo' => $this->baseGasto->codigo,
            'nombre_proveedor' => $this->baseGasto->nombre_proveedor,
            'tipo_documento' => $this->baseGasto->tipo_documento,
            'num_identificacion' => $this->baseGasto->num_identificacion
        ]);

        $nuevoGasto->save();

        // Recargar con relaciones
        $nuevoGasto = \App\Models\Compras\Gastos\Gasto::with(['proveedor', 'empresa', 'sucursal'])
            ->find($nuevoGasto->id);

        // Generar el DTE
        $dte = $this->generarDTEGasto($nuevoGasto);

        // Firmar y emitir DTE
        $resultado = $this->firmarYEmitirDTE($nuevoGasto, $dte, $token);

        if ($resultado['success']) {
            // Actualizar el gasto con los datos del DTE
            \App\Models\Compras\Gastos\Gasto::where('id', $nuevoGasto->id)
                ->update([
                    'dte' => $dte,
                    'sello_mh' => $resultado['selloRecibido'] ?? null,
                    'estado' => 'Prueba'
                ]);
        }

        return $resultado;
    }

    /**
     * NUEVA FUNCIÓN: Generar y emitir documento genérico
     */
    protected function generarYEmitirDocumento($tipo, $correlativo, &$token)
    {
        // Crear nueva venta
        $nuevaVenta = new Venta();
        $nuevaVenta->fill([
            'tipo_dte' => $tipo,
            'correlativo' => $correlativo,
            'numero_control' => 'DTE-' . $tipo . '-' . $this->sucursal->cod_estable_mh . '0001-' . str_pad($correlativo, 15, '0', STR_PAD_LEFT),
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

        // Volver a cargar la venta con sus detalles
        $nuevaVenta = Venta::with(['detalles', 'cliente', 'empresa', 'sucursal'])->find($nuevaVenta->id);

        // Generar el DTE
        $dte = $this->generarDTE($nuevaVenta);

        // Firmar y enviar DTE
        $resultado = $this->firmarYEmitirDTE($nuevaVenta, $dte, $token);

        if ($resultado['success']) {
            // Actualizar la venta con los datos del DTE
            Venta::where('id', $nuevaVenta->id)
                ->update([
                    'dte' => $dte,
                    'sello_mh' => $resultado['selloRecibido'] ?? null,
                    'estado' => 'Prueba'
                ]);

            $resultado['venta'] = $nuevaVenta; // Devolver la venta para posibles notas
        }

        return $resultado;
    }

    protected function enviarNotificacionPorCorreo($resultados, $tipo, $cantidad, $estadisticas)
    {
        try {
            $usuario = \App\Models\User::find($this->userId);
            $tipoTexto = $this->getTipoTexto($tipo);

            // Log detallado de los resultados
            Log::info('=== PRUEBAS MASIVAS COMPLETADAS ===', [
                'usuario_id' => $this->userId,
                'usuario_email' => $usuario ? $usuario->email : null,
                'empresa_id' => $this->empresaId,
                'tipo' => $tipo,
                'tipo_texto' => $tipoTexto,
                'cantidad_solicitada' => $cantidad,
                'resultados' => [
                    'exitosos' => $resultados['exitosos'] ?? 0,
                    'fallidos' => $resultados['fallidos'] ?? 0,
                    'total_procesados' => ($resultados['exitosos'] ?? 0) + ($resultados['fallidos'] ?? 0)
                ],
                'detalles' => $resultados['detalles'] ?? [],
                'estadisticas' => $estadisticas
            ]);

            if ($usuario && $usuario->email) {
                Mail::send('mails.pruebas-masivas-completadas', [
                    'resultado' => $resultados,
                    'tipo' => $tipo,
                    'tipoDTE' => $tipo,
                    'tipoTexto' => $tipoTexto,
                    'cantidad' => $cantidad,
                    'estadisticas' => $estadisticas
                ], function ($mensaje) use ($usuario, $tipoTexto) {
                    // $mensaje->to("jose.e@smartpyme.sv", $usuario->name)
                    $mensaje->to($usuario->email, $usuario->name)
                        ->subject('Pruebas Masivas MH Completadas: ' . $tipoTexto);
                });

                Log::info('Correo de notificación enviado exitosamente', [
                    'usuario_email' => $usuario->email,
                    'tipo' => $tipoTexto
                ]);
            } else {
                Log::warning('No se pudo enviar correo: usuario o email no encontrado', [
                    'usuario_id' => $this->userId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de notificación: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function enviarNotificacionError($mensaje, $tipo, $cantidad)
    {
        try {
            $usuario = \App\Models\User::find($this->userId);
            $tipoTexto = $this->getTipoTexto($tipo);

            // Log detallado del error
            Log::error('=== ERROR EN PRUEBAS MASIVAS ===', [
                'usuario_id' => $this->userId,
                'usuario_email' => $usuario ? $usuario->email : null,
                'empresa_id' => $this->empresaId,
                'tipo' => $tipo,
                'tipo_texto' => $tipoTexto,
                'cantidad_solicitada' => $cantidad,
                'error' => $mensaje,
                'fecha' => now()->toDateTimeString()
            ]);

            if ($usuario && $usuario->email) {
                Mail::send('mails.pruebas-masivas-error', [
                    'error' => $mensaje,
                    'tipo' => $tipo,
                    'tipoDTE' => $tipo,
                    'tipoTexto' => $tipoTexto,
                    'cantidad' => $cantidad
                ], function ($mensaje) use ($usuario, $tipoTexto) {
                    // $mensaje->to("jose.e@smartpyme.sv", $usuario->name)
                    $mensaje->to($usuario->email, $usuario->name)
                        ->subject('Error en Pruebas Masivas MH: ' . $tipoTexto);
                });

                Log::info('Correo de error enviado exitosamente', [
                    'usuario_email' => $usuario->email,
                    'tipo' => $tipoTexto
                ]);
            } else {
                Log::warning('No se pudo enviar correo de error: usuario o email no encontrado', [
                    'usuario_id' => $this->userId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al enviar correo de notificación de error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'error_original' => $mensaje
            ]);
        }
    }

    protected function getTipoTexto($tipo)
    {
        $tipos = [
            '01' => 'Facturas Consumidor Final',
            '03' => 'Comprobantes de Crédito Fiscal',
            '05' => 'Notas de Crédito',
            '06' => 'Notas de Débito',
            '11' => 'Facturas de Exportación',
            '14' => 'Facturas de Sujeto Excluido'
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
            $nuevaVenta->tipo_item_export = $ventaBase->tipo_item_export ?? 1;
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
        set_time_limit(30);

        if ($this->simulationMode && isset($this->detallesOriginales)) {
            $detallesTmp = collect();

            foreach ($this->detallesOriginales as $detalle) {
                $nuevoDetalle = new \stdClass();

                foreach ((array)$detalle as $key => $value) {
                    $nuevoDetalle->$key = $value;
                }

                $nuevoDetalle->producto = function () use ($detalle) {
                    $producto = new \stdClass();
                    $producto->tipo = $detalle->tipo_item == 2 ? 'Servicio' : 'Producto';

                    $producto->pluck = function ($field) use ($producto) {
                        if ($field == 'tipo') {
                            return collect([$producto->tipo]);
                        }
                        return collect([]);
                    };

                    $producto->first = function () use ($producto) {
                        return $producto;
                    };

                    return $producto;
                };

                $detallesTmp->push($nuevoDetalle);
            }

            $detallesOriginales = $venta->detalles;
            $venta->detalles = $detallesTmp;

            $saveMethod = $venta->save;
            $venta->save = function () {
                return true;
            };
        }

        try {
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

                    Log::info('DTE Exportación generado:', [
                        'tipoItemExpor' => $resultado['emisor']['tipoItemExpor'] ?? 'NO_DEFINIDO',
                        'correlativo' => $venta->correlativo,
                        'dte_completo' => $resultado
                    ]);

                    break;

                case '14':
                    $mh = new MHSujetoExcluidoGasto();
                    $resultado = $mh->generarDTE($venta);

                    Log::info('DTE Sujeto Excluido generado:', [
                        'correlativo' => $venta->correlativo,
                        'dte_completo' => $resultado
                    ]);

                    break;
                case '05':
                    $mh = new MHNotaCredito();
                    $resultado = $mh->generarDTE($venta);

                    Log::info('DTE Nota Crédito generado:', [
                        'correlativo' => $venta->correlativo,
                        'dte_completo' => $resultado
                    ]);

                    break;
                case '06':
                    $mh = new MHNotaDebito();
                    $resultado = $mh->generarDTE($venta);

                    Log::info('DTE Nota Débito generado:', [
                        'correlativo' => $venta->correlativo,
                        'dte_completo' => $resultado
                    ]);

                    break;
                default:
                    throw new \Exception("Tipo de documento no soportado para pruebas masivas: {$venta->tipo_dte}");
            }

            return $resultado;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            if ($this->simulationMode && isset($detallesOriginales)) {
                $venta->detalles = $detallesOriginales;
                $venta->save = $saveMethod;
            }

            gc_collect_cycles();
        }
    }

    protected function firmarYEmitirDTE($venta, $dte, &$token = null)
    {
        set_time_limit(60);

        $datosParaFirma = [
            'nit' => str_replace('-', '', $this->empresa->nit),
            'activo' => true,
            'passwordPri' => $this->empresa->mh_pwd_certificado,
            'dteJson' => $dte
        ];

        try {
            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 10,
                'verify' => false,
                'http_errors' => false
            ]);

            $responseFirma = $client->post(config('app.mh_url_firmado', 'https://facturadtesv.com:8443/firmardocumento/'), [
                'json' => $datosParaFirma,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);

            $bodyFirma = $responseFirma->getBody()->getContents();
            $dteFirmado = json_decode($bodyFirma, true);

            $responseFirma = null;
            $bodyFirma = null;

            if (isset($dteFirmado['status']) && $dteFirmado['status'] === 'ERROR') {
                return [
                    'success' => false,
                    'message' => 'Error al firmar DTE: ' . ($dteFirmado['body']['mensaje'] ?? 'Error desconocido')
                ];
            }

            if ($token === null) {
                $responseAuth = $client->post(config('app.mh_url_auth', 'https://apitest.dtes.mh.gob.sv/seguridad/auth'), [
                    'form_params' => [
                        'user' => str_replace('-', '', $this->empresa->mh_usuario),
                        'pwd' => $this->empresa->mh_contrasena
                    ]
                ]);

                $bodyAuth = $responseAuth->getBody()->getContents();
                $authData = json_decode($bodyAuth, true);

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

            $datosMH = [
                'ambiente' => $this->empresa->fe_ambiente,
                'idEnvio' => $venta->id ?? uniqid(),
                'version' => $dte['identificacion']['version'],
                'tipoDte' => $venta->tipo_dte,
                'documento' => isset($dteFirmado['body']) ? $dteFirmado['body'] : $dteFirmado,
                'codigoGeneracion' => $venta->codigo_generacion
            ];

            $responseEnvio = $client->post(config('app.mh_url_recepcion', 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte'), [
                'json' => $datosMH,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $token
                ]
            ]);

            $bodyEnvio = $responseEnvio->getBody()->getContents();
            $respuestaMH = json_decode($bodyEnvio, true);

            $responseEnvio = null;
            $bodyEnvio = null;
            $datosMH = null;

            if (isset($respuestaMH['estado']) && $respuestaMH['estado'] === 'PROCESADO') {
                if (!$this->simulationMode) {
                    $this->registrarDocumentoExitoso($venta, $dte, $dteFirmado, $respuestaMH);
                }

                return [
                    'success' => true,
                    'message' => 'DTE emitido correctamente',
                    'selloRecibido' => $respuestaMH['selloRecibido'] ?? null,
                    'token' => $token
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al procesar DTE: ' . json_encode($respuestaMH),
                    'token' => $token
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
                $venta->detalles()->delete();
                $venta->delete();
                $count++;
            }

            // NUEVO: Eliminar devoluciones (notas) marcadas como pruebas masivas
            $devolucionesToDelete = \App\Models\Ventas\Devoluciones\Devolucion::where('prueba_masiva', true)
                ->where('id_empresa', $idEmpresa)
                ->get();

            foreach ($devolucionesToDelete as $devolucion) {
                $devolucion->detalles()->delete();
                $devolucion->delete();
                $count++;
            }

            // NUEVO: Eliminar gastos marcados como pruebas masivas
            $gastosToDelete = Gasto::where('prueba_masiva', true)
                ->where('id_empresa', $idEmpresa)
                ->get();

            foreach ($gastosToDelete as $gasto) {
                $gasto->delete();
                $count++;
            }

            Log::info("Se eliminaron {$count} documentos de pruebas masivas para la empresa {$idEmpresa}");

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
