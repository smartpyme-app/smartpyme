<?php

namespace App\Http\Controllers\Api\FidelizacionCliente;

use App\Http\Controllers\Controller;
use App\Services\FidelizacionCliente\ConsumoPuntosService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CanjePuntosController extends Controller
{
    protected $consumoPuntosService;

    public function __construct(ConsumoPuntosService $consumoPuntosService)
    {
        $this->consumoPuntosService = $consumoPuntosService;
    }

    /**
     * Obtener información de puntos disponibles para canje
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function obtenerPuntosDisponibles(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente_id' => 'required|integer|exists:clientes,id',
                'empresa_id' => 'required|integer|exists:empresas,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $clienteId = $request->input('cliente_id');
            $empresaId = $request->input('empresa_id');

            $informacionPuntos = $this->consumoPuntosService->obtenerInformacionPuntosDisponibles($clienteId, $empresaId);

            return response()->json([
                'success' => true,
                'data' => $informacionPuntos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de puntos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar canje de puntos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function canjearPuntos(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente_id' => 'required|integer|exists:clientes,id',
                'empresa_id' => 'required|integer|exists:empresas,id',
                'puntos_a_canjear' => 'required|integer|min:1',
                'descripcion' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $clienteId = $request->input('cliente_id');
            $empresaId = $request->input('empresa_id');
            $puntosACanjear = $request->input('puntos_a_canjear');
            $descripcion = $request->input('descripcion');

            $resultado = $this->consumoPuntosService->canjearPuntos($clienteId, $empresaId, $puntosACanjear, $descripcion);

            if ($resultado['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['mensaje'],
                    'data' => $resultado
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $resultado['error'],
                    'data' => $resultado
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar el canje',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de canjes de un cliente
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function obtenerHistorialCanjes(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente_id' => 'required|integer|exists:clientes,id',
                'empresa_id' => 'required|integer|exists:empresas,id',
                'limite' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $clienteId = $request->input('cliente_id');
            $empresaId = $request->input('empresa_id');
            $limite = $request->input('limite', 20);

            $canjes = \App\Models\FidelizacionClientes\TransaccionPuntos::where('id_cliente', $clienteId)
                ->where('id_empresa', $empresaId)
                ->where('tipo', \App\Models\FidelizacionClientes\TransaccionPuntos::TIPO_CANJE)
                ->with(['consumosComoCanje.transaccionGanancia'])
                ->orderBy('created_at', 'desc')
                ->limit($limite)
                ->get()
                ->map(function ($canje) {
                    return [
                        'id' => $canje->id,
                        'puntos_canjeados' => abs($canje->puntos),
                        'descripcion' => $canje->descripcion,
                        'fecha_canje' => $canje->created_at,
                        'puntos_antes' => $canje->puntos_antes,
                        'puntos_despues' => $canje->puntos_despues,
                        'detalles_consumo' => $canje->consumosComoCanje->map(function ($consumo) {
                            return [
                                'ganancia_id' => $consumo->id_ganancia_tx,
                                'puntos_consumidos' => $consumo->puntos_consumidos,
                                'fecha_ganancia_original' => $consumo->transaccionGanancia->created_at,
                                'fecha_expiracion_original' => $consumo->transaccionGanancia->fecha_expiracion
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $canjes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de canjes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
