<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMassTestsJob;
use App\Models\TrabajosPendientes;
use Illuminate\Http\Request;
use App\Services\MHPruebasMasivasService;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MHPruebasMasivasController extends Controller
{
    protected $pruebasMasivasService;

    /**
     * Constructor del controlador
     */
    public function __construct(MHPruebasMasivasService $pruebasMasivasService)
    {
        $this->pruebasMasivasService = $pruebasMasivasService;
    }

    /**
     * Obtener estadísticas de las pruebas realizadas
     */
    public function estadisticas()
    {
        try {
            // Comprobar autenticación
            if (!Auth::user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            $estadisticas = $this->pruebasMasivasService->obtenerEstadisticas();

            // Obtener el estado general
            $user = Auth::user();
            $estadoGeneral = [
                'completado' => $user->empresa->fe_pruebas_estadisticas['completado'] ?? false,
                'fecha_completado' => $user->empresa->fe_pruebas_estadisticas['fecha_completado'] ?? null
            ];

            return response()->json([
                'tipos' => $estadisticas,
                'estado' => $estadoGeneral
            ]);
        } catch (\Exception $e) {
            Log::error('Error en estadísticas de pruebas masivas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Error al obtener estadísticas: ' . $e->getMessage()], 500);
        }
    }

    public function ejecutar(Request $request)
    {
        try {
            $request->validate([
                'tipo' => 'required|string|in:facturas,creditosFiscales,notasCredito,notasDebito,facturasExportacion',
                'cantidad' => 'required|integer|min:1|max:100',
                'id_documento_base' => 'nullable|integer|exists:ventas,id',
                'correlativo_inicial' => 'nullable|integer|min:1'
            ]);
    
            // Comprobar autenticación
            if (!Auth::user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }
    
            // Verificar que el ambiente sea de pruebas
            $user = Auth::user();
            if ($user->empresa->fe_ambiente !== '00') {
                return response()->json([
                    'success' => false,
                    'message' => 'Las pruebas masivas solo pueden ejecutarse en ambiente de pruebas'
                ], 400);
            }
    
            // Mapear el tipo a código DTE
            $tiposDTE = [
                'facturas' => '01',
                'creditosFiscales' => '03',
                'notasCredito' => '05',
                'notasDebito' => '06',
                'facturasExportacion' => '11',
                // 'sujetoExcluido' => '14'
            ];
    
            $tiposDescriptivos = [
                '01' => 'Facturas Consumidor Final',
                '03' => 'Comprobantes de Crédito Fiscal',
                '05' => 'Notas de Crédito',
                '06' => 'Notas de Débito',
                '11' => 'Facturas de Exportación',
                // '14' => 'Facturas de Sujeto Excluido'
            ];
    
            $tipoDTE = $tiposDTE[$request->tipo];
            
            if (in_array($tipoDTE, ['05', '06'])) {
                // Verificar que existan CCF emitidos para poder generar notas
                $ccfEmitidos = Venta::where('tipo_dte', '03')
                    ->where('sello_mh', '!=', null)
                    ->where('id_empresa', $user->id_empresa)
                    ->count();

                if ($ccfEmitidos == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pueden generar notas sin tener al menos un Comprobante de Crédito Fiscal emitido'
                    ], 400);
                }

                // Ajustar la cantidad si es mayor a los CCF disponibles
                if ($request->cantidad > $ccfEmitidos) {
                    return response()->json([
                        'success' => false,
                        'message' => "Solo se pueden generar máximo {$ccfEmitidos} notas (número de CCF emitidos)"
                    ], 400);
                }
            }

            Log::info('Tipo DTE:', [
                'tipoDTE' => $tipoDTE,
                'cantidad' => $request->cantidad,
                'idDocumentoBase' => $request->id_documento_base,
                'idUsuario' => $user->id,
            ]);
            
            $verificacion = $this->pruebasMasivasService->ejecutarPruebasMasivas(
                $tipoDTE,
                $request->cantidad,
                $request->id_documento_base,
                $user->id
            );
            
            // Si la verificación falló, retornar el error
            if (!$verificacion['success']) {
                return response()->json($verificacion, 400);
            }
            
            // Guardar el trabajo pendiente en la base de datos
            $trabajo = TrabajosPendientes::create([
                'tipo' => 'pruebas_masivas',
                'parametros' => json_encode([
                    'tipo_dte' => $tipoDTE,
                    'cantidad' => $request->cantidad,
                    'id_documento_base' => $request->id_documento_base,
                    'correlativo_inicial' => $request->correlativo_inicial,
                    'id_usuario' => $user->id,
                    'id_empresa' => $user->id_empresa
                ]),
                'estado' => 'pendiente',
                'fecha_creacion' => now(),
                'id_usuario' => $user->id,
                'id_empresa' => $user->id_empresa
            ]);
    
            // Ejecutar el comando artisan en segundo plano
            $command = "php " . base_path('artisan') . " trabajos:procesar --limite=1 --id=" . $trabajo->id . " > /dev/null 2>&1 &";
    
            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen("start /B " . $command, "r"));
            } else {
                exec($command);
            }


            // MENSAJE PERSONALIZADO PARA CCF CON NOTAS AUTOMÁTICAS
            $mensaje = "Se ha programado la generación de {$request->cantidad} {$tiposDescriptivos[$tipoDTE]}.";
            
            if ($tipoDTE === '03' && $request->cantidad >= 1) {
                $mensaje .= " Además, se generarán automáticamente {$request->cantidad} Notas de Crédito y {$request->cantidad} Notas de Débito relacionadas.";
            }
            
            $mensaje .= " El proceso se ejecutará en segundo plano y recibirá una notificación por correo cuando finalice.";

            return response()->json([
                'success' => true,
                'queued' => true,
                'trabajo_id' => $trabajo->id,
                'message' => "Se ha programado la generación de {$request->cantidad} {$tiposDescriptivos[$tipoDTE]}. " .
                           "El proceso se ejecutará en segundo plano y recibirá una notificación por correo cuando finalice."
            ]);

        } catch (\Exception $e) {

            Log::error('Error al iniciar pruebas masivas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar el proceso: ' . $e->getMessage()
            ], 500);
        }
    }

    private function procesarEnSegundoPlano($trabajo)
    {
        // Enviar respuesta al navegador antes de continuar
        if (function_exists('fastcgi_finish_request')) {
            // Cerrar la conexión pero mantener el script ejecutándose
            fastcgi_finish_request();
        } else {
            // Alternativa si fastcgi no está disponible
            ob_end_clean();
            header("Connection: close");
            ignore_user_abort(true);
            ob_start();
            echo json_encode(['status' => 'processing']);
            $size = ob_get_length();
            header("Content-Length: $size");
            ob_end_flush();
            flush();
        }
        
        try {
            // Marcar como en proceso
            $trabajo->estado = 'procesando';
            $trabajo->fecha_inicio = now();
            $trabajo->save();
            
            // Obtener parámetros
            $params = json_decode($trabajo->parametros, true);
            
            // Ejecutar el proceso
            $resultado = $this->pruebasMasivasService->ejecutarPruebasMasivas(
                $params['tipo_dte'],
                $params['cantidad'],
                $params['id_documento_base'],
                $params['id_usuario']
            );
            
            // Actualizar el trabajo
            $trabajo->estado = 'completado';
            $trabajo->resultado = json_encode($resultado);
            $trabajo->fecha_fin = now();
            $trabajo->save();
        } catch (\Exception $e) {
            Log::error('Error en procesamiento en segundo plano: ' . $e->getMessage());
            
            // Marcar como fallido
            $trabajo->estado = 'fallido';
            $trabajo->resultado = json_encode(['error' => $e->getMessage()]);
            $trabajo->fecha_fin = now();
            $trabajo->save();
        }
    }

    public function documentosBase()
    {
        try {
            // Comprobar autenticación
            if (!Auth::user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            $user = Auth::user();
            $idEmpresa = $user->id_empresa;

            // Obtener los últimos documentos emitidos exitosamente por tipo
            $documentos = Venta::where('id_empresa', $idEmpresa)
                ->whereNotNull('sello_mh')
                ->whereIn('tipo_dte', ['01', '03', '05', '06', '11', '14'])
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get(); // Sin seleccionar campos específicos

            return response()->json($documentos);
        } catch (\Exception $e) {
            Log::error('Error en documentos base de pruebas masivas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Error al obtener documentos base: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Limpiar documentos de prueba (solo para administradores)
     */
    public function limpiarDocumentosPrueba()
    {
        try {
            // Comprobar autenticación
            if (!Auth::user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            $user = Auth::user();

            // Verificar que sea administrador
            if ($user->tipo != 'Administrador') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para realizar esta acción'
                ], 403);
            }

             // ACTUALIZADO: Eliminar tanto ventas como devoluciones de prueba
            $eliminadosVentas = Venta::where('prueba_masiva', true)
                ->where('id_empresa', $user->id_empresa)
                ->count();

            $eliminadosDevoluciones = \App\Models\Ventas\Devoluciones\Devolucion::where('prueba_masiva', true)
                ->where('id_empresa', $user->id_empresa)
                ->count();

            // Ejecutar la eliminación usando el servicio
            $this->pruebasMasivasService->eliminarPruebasMasivas($user->id_empresa);

            $totalEliminados = $eliminadosVentas + $eliminadosDevoluciones;

            return response()->json([
                'success' => true,
                'message' => "Se han eliminado {$totalEliminados} documentos de prueba ({$eliminadosVentas} ventas y {$eliminadosDevoluciones} notas)"
            ]);
        } catch (\Exception $e) {
            Log::error('Error al limpiar documentos de prueba: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar documentos de prueba: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reiniciarEstadisticas()
    {
        try {
            // Comprobar autenticación
            if (!Auth::user()) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            $user = Auth::user();

            // Verificar que sea administrador
            if ($user->tipo != 'Administrador') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para realizar esta acción'
                ], 403);
            }

            // Reiniciar estadísticas
            $user->empresa->inicializarEstadoPruebasMasivas();

            return response()->json([
                'success' => true,
                'message' => "Se han reiniciado las estadísticas de pruebas masivas"
            ]);
        } catch (\Exception $e) {
            Log::error('Error al reiniciar estadísticas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al reiniciar estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}
