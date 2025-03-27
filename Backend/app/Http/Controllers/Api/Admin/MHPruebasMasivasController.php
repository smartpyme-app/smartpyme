<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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

    /**
     * Obtener los documentos que pueden usarse como base para las pruebas
     */
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
     * Ejecutar pruebas masivas para un tipo de documento
     */
    public function ejecutar(Request $request)
    {
        try {
            $request->validate([
                'tipo' => 'required|string|in:facturas,creditosFiscales,notasCredito,notasDebito,facturasExportacion,sujetoExcluido',
                'cantidad' => 'required|integer|min:1|max:100',
                'id_documento_base' => 'nullable|integer|exists:ventas,id'
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
                'sujetoExcluido' => '14'
            ];

            $tipoDTE = $tiposDTE[$request->tipo];

            // Ejecutar pruebas masivas
            $resultado = $this->pruebasMasivasService->ejecutarPruebasMasivas(
                $tipoDTE,
                $request->cantidad,
                $request->id_documento_base
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Error en ejecución de pruebas masivas: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al ejecutar pruebas masivas: ' . $e->getMessage()
            ], 500);
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

            // Eliminar documentos marcados como prueba_masiva
            $eliminados = Venta::where('prueba_masiva', true)
                ->where('id_empresa', $user->id_empresa)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Se han eliminado {$eliminados} documentos de prueba"
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
