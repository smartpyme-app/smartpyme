<?php

namespace App\Http\Controllers\Cliente360;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Clientes\Cliente;
use App\Services\Cliente360\Cliente360Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Cliente360Controller extends Controller
{
    protected $cliente360Service;

    public function __construct(Cliente360Service $cliente360Service)
    {
        $this->cliente360Service = $cliente360Service;
    }

    /**
     * Listar clientes con paginación y búsqueda opcional
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = Cliente::query();

            // Agregar búsqueda si se proporciona
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhere('nit', 'like', "%{$search}%");
                });
            }

            $clientes = $query->orderBy('nombre')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $clientes
            ]);
        } catch (\Exception $e) {
            Log::error('Error en Cliente360Controller@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la lista de clientes'
            ], 500);
        }
    }

    /**
     * Obtener datos completos del cliente
     */
    public function show($id)
    {
        try {
            // Validar que el ID sea numérico
            $validator = Validator::make(['id' => $id], [
                'id' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de cliente inválido',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Verificar que el cliente exista antes de procesar
            $cliente = Cliente::find($id);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], 404);
            }

            // Obtener datos del servicio
            $clienteData = $this->cliente360Service->getClienteData($id);

            if (!$clienteData) {
                Log::warning("Cliente360Service devolvió null para cliente ID: {$id}");
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron obtener los datos del cliente'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $clienteData
            ]);
        } catch (\Exception $e) {
            Log::error('Error en Cliente360Controller@show: ' . $e->getMessage(), [
                'cliente_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del cliente'
            ], 500);
        }
    }

    /**
     * Obtener métricas resumidas de un cliente (endpoint ligero)
     */
    public function metrics($id)
    {
        try {
            // Validar ID
            $validator = Validator::make(['id' => $id], [
                'id' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de cliente inválido',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Obtener solo métricas básicas (más rápido)
            $metricas = DB::table('cliente_metricas_rfm')
                ->where('id_cliente', $id)
                ->select([
                    'health_score',
                    'total_gastado',
                    'total_compras',
                    'ticket_promedio',
                    'segmento_rfm',
                    'dias_ultima_compra',
                    'fecha_calculo'
                ])
                ->first();

            if (!$metricas) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron métricas para este cliente'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $metricas
            ]);
        } catch (\Exception $e) {
            Log::error('Error en Cliente360Controller@metrics: ' . $e->getMessage(), [
                'cliente_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener métricas del cliente'
            ], 500);
        }
    }
}
