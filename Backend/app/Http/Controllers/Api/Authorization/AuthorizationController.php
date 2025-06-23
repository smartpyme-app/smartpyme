<?php

namespace App\Http\Controllers\Api\Authorization;

use App\Http\Controllers\Controller;
use App\Services\Authorization\AuthorizationService;
use App\Models\Authorization\Authorization;
use App\Models\Authorization\AuthorizationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthorizationController extends Controller
{
    protected $authorizationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    public function index(Request $request)
    {
        $query = Authorization::with(['authorizationType', 'requester', 'authorizer'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($request->paginate ?? 15));
    }

    public function pending()
    {
        $authorizations = $this->authorizationService
            ->getPendingAuthorizationsForUser(auth()->id());

        return response()->json([
            'ok' => true,
            'data' => $authorizations
        ]);
    }

    public function approve(Request $request, $code)
    {
        $request->validate([
            'authorization_code' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        try {
            $authorization = $this->authorizationService->approveAuthorization(
                $code,
                $request->authorization_code,
                $request->notes
            );

            return response()->json([
                'ok' => true,
                'message' => 'Autorización aprobada exitosamente',
                'data' => $authorization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function reject(Request $request, $code)
    {
        $request->validate([
            'authorization_code' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        try {
            $authorization = $this->authorizationService->rejectAuthorization(
                $code,
                $request->authorization_code,
                $request->notes
            );

            return response()->json([
                'ok' => true,
                'message' => 'Autorización rechazada',
                'data' => $authorization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function show($code)
    {
        $authorization = Authorization::where('code', $code)
            ->with(['authorizationType', 'requester', 'authorizer', 'authorizeable'])
            ->first();

        if (!$authorization) {
            return response()->json([
                'ok' => false,
                'message' => 'Autorización no encontrada'
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $authorization
        ]);
    }

    public function request(Request $request)
    {
       $request->validate([
           'type' => 'required|string',
           'model_type' => 'required|string',
           'model_id' => 'nullable|integer',
           'description' => 'required|string',
           'data' => 'nullable|array'
       ]);
    
       // DEBUG: Agregar logs para ver qué datos llegan
       Log::info('=== AUTHORIZATION REQUEST DEBUG ===');
       Log::info('Type: ' . $request->type);
       Log::info('Data: ' . json_encode($request->data));
    
       try {
           // CAMBIO: Si es compras_altas, SIEMPRE crear compra pendiente
           if ($request->type === 'compras_altas') {
               Log::info('Creating pending purchase for compras_altas...');
               return $this->createCompraPendiente($request);
           }

           // Para otros tipos de autorización, usar el flujo normal
           if ($request->model_id) {
               $modelClass = $request->model_type;
               $model = $modelClass::findOrFail($request->model_id);
           } else {
               $model = new \stdClass();
               $model->id = 0;
           }
    
           $authorization = $this->authorizationService->requestAuthorization(
               $request->type,
               $model,
               $request->description,
               $request->data ?? []
           );
    
           return response()->json([
               'ok' => true,
               'message' => 'Autorización solicitada exitosamente',
               'data' => $authorization
           ]);
       } catch (\Exception $e) {
           Log::error('Authorization request error: ' . $e->getMessage());
           return response()->json([
               'ok' => false,
               'message' => $e->getMessage()
           ], 400);
       }
    }

    /**
     * Determinar si el tipo de autorización necesita crear un registro pendiente
     */
    private function needsPendingRecord($authType, $data)
    {
        Log::info("Checking needsPendingRecord - authType: $authType");
        Log::info("Data keys: " . json_encode(array_keys($data ?? [])));
        
        // Lista de tipos que requieren registro pendiente
        $typesWithPendingRecords = [
            'compras_altas',
            'ventas_monto_alto',
        ];

        $hasCorrectType = in_array($authType, $typesWithPendingRecords);
        
        // CAMBIO: Verificar si tiene datos de compra de cualquier forma
        $hasCompraData = isset($data['compra_data']) || // Datos del interceptor
                        isset($data['detalles']) ||    // Datos directos
                        isset($data['total']);         // Al menos tiene total
        
        Log::info("hasCorrectType: " . ($hasCorrectType ? 'YES' : 'NO'));
        Log::info("hasCompraData: " . ($hasCompraData ? 'YES' : 'NO'));

        $needsPending = $hasCorrectType && $hasCompraData;
        Log::info("Needs pending record: " . ($needsPending ? 'YES' : 'NO'));

        return $needsPending;
    }

    /**
     * Crear registro pendiente basado en el tipo de autorización
     */
    private function createPendingRecord($request)
    {
        switch ($request->type) {
            case 'compras_altas':
                return $this->createCompraPendiente($request);
            
            case 'ventas_monto_alto':
                return $this->createVentaPendiente($request);
            
            // Agregar más casos según necesites
            default:
                throw new \Exception("Tipo de registro pendiente no soportado: {$request->type}");
        }
    }

    /**
     * Crear compra pendiente (SIEMPRE para compras_altas)
     */
    private function createCompraPendiente($request)
    {
        // Los datos vienen directamente en $request->data
        $compraData = $request->data;

        if (!$compraData || !isset($compraData['detalles'])) {
            throw new \Exception('Datos de compra requeridos para crear compra pendiente');
        }

        $authTypeModel = AuthorizationType::where('name', $request->type)->first();
        
        if (!$authTypeModel) {
            throw new \Exception("Tipo de autorización '{$request->type}' no encontrado");
        }

        DB::beginTransaction();
        
        try {
            // Preparar datos para crear la compra
            $compraData['estado'] = 'Pendiente Autorización';
            $compraData['id_sucursal'] = auth()->user()->id_sucursal;
            
            Log::info("Creando compra pendiente con estado: Pendiente Autorización");
            
            // Crear la compra
            $compra = \App\Models\Compras\Compra::create($compraData);
            
            Log::info("Compra creada con ID: " . $compra->id);
            
            // Crear detalles de la compra (sin actualizar inventario)
            foreach ($compraData['detalles'] as $det) {
                $detalle = new \App\Models\Compras\Detalle;
                $det['id_compra'] = $compra->id;
                $detalle->fill($det);
                $detalle->save();
            }

            // Crear la autorización vinculada a la compra
            $authorization = Authorization::create([
                'authorization_type_id' => $authTypeModel->id,
                'authorizeable_type' => 'App\Models\Compras\Compra',
                'authorizeable_id' => $compra->id,
                'requested_by' => auth()->id(),
                'description' => $request->description,
                'data' => json_encode($request->data),
                'operation_type' => 'facturacion',
                'operation_data' => json_encode($this->extractRelevantData($compraData, 'compras')),
                'operation_hash' => $this->generateOperationHash('facturacion', $compraData, 'compras'),
                'expires_at' => now()->addHours($authTypeModel->expiration_hours ?? 24),
            ]);

            // Vincular autorización con compra
            $compra->update(['authorization_id' => $authorization->id]);

            DB::commit();

            Log::info("Compra pendiente creada exitosamente - ID: {$compra->id}, Estado: {$compra->estado}");

            return response()->json([
                'ok' => true,
                'message' => 'Compra creada pendiente de autorización',
                'data' => $authorization,
                'compra' => $compra,
                'estado' => 'Pendiente Autorización'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando compra pendiente: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Crear venta pendiente (ejemplo para futuro)
     */
    private function createVentaPendiente($request)
    {
        // Implementar lógica similar para ventas
        throw new \Exception('Creación de ventas pendientes no implementada aún');
    }

    /**
     * Extraer datos relevantes según el módulo
     */
    private function extractRelevantData($data, $module)
    {
        switch ($module) {
            case 'compras':
                return [
                    'total' => $data['total'] ?? 0,
                    'id_proveedor' => $data['id_proveedor'] ?? null,
                    'detalles_count' => count($data['detalles'] ?? []),
                    'fecha' => $data['fecha'] ?? null,
                ];
            
            case 'ventas':
                return [
                    'total' => $data['total'] ?? 0,
                    'id_cliente' => $data['id_cliente'] ?? null,
                    'descuento' => $data['descuento'] ?? 0,
                ];
            
            default:
                return array_intersect_key($data, array_flip(['id', 'total', 'fecha']));
        }
    }

    /**
     * Generar hash único de operación
     */
    private function generateOperationHash($action, $data, $module)
    {
        $relevantData = $this->extractRelevantData($data, $module);
        return hash('sha256', 
            $module . 
            $action . 
            serialize($relevantData) . 
            auth()->id() . 
            date('Y-m-d')
        );
    }

    public function checkRequirement(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'data' => 'nullable|array'
        ]);

        $required = $this->authorizationService->requiresAuthorization(
            $request->type,
            $request->data ?? []
        );

        return response()->json([
            'ok' => true,
            'requires_authorization' => $required
        ]);
    }

    public function history($modelType, $modelId)
    {
        $modelClass = urldecode($modelType);
        $model = $modelClass::findOrFail($modelId);

        $history = $this->authorizationService->getAuthorizationHistory($model);

        return response()->json([
            'ok' => true,
            'data' => $history
        ]);
    }
}