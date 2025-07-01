<?php

namespace App\Http\Controllers\Api\Authorization;

use App\Http\Controllers\Controller;
use App\Services\Authorization\AuthorizationService;
use App\Models\Authorization\Authorization;
use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

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

       try {

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

            // Si es compras_altas, SIEMPRE crear compra pendiente
            if ($request->type === 'compras_altas') {
                Log::info('Creating pending purchase for compras_altas...');
                return $this->createCompraPendiente($request);
            }

            // Si es orden de compra, crear orden pendiente
            if (str_starts_with($request->type, 'orden_compra_nivel_')) {
                Log::info('Creating pending orden compra for ' . $request->type);
                return $this->createOrdenCompraPendiente($request, $authorization);
            }

            if (str_starts_with($request->type, 'editar_usuario_')) {
                return $this->handleUserPendingChanges($request, $authorization);
            }
        
    
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
            $compra->update(['id_authorization' => $authorization->id]);

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
     * Crear orden de compra pendiente 
     */
    private function createOrdenCompraPendiente($request, $authorization = null)
    {
       $ordenData = $request->data;
    
       if (!$ordenData || !isset($ordenData['detalles'])) {
           throw new \Exception('Datos de orden de compra requeridos');
       }
    
       DB::beginTransaction();
       
       try {
           $ordenData['estado'] = 'Pendiente Autorización';
           $ordenData['id_sucursal'] = auth()->user()->id_sucursal;
           $ordenData['id_empresa'] = auth()->user()->id_empresa;
           
           Log::info("Creando orden de compra pendiente con estado: Pendiente Autorización");
           
           // Crear la orden
           $orden = \App\Models\OrdenCompra::create($ordenData);
           
           Log::info("Orden de compra creada con ID: " . $orden->id);
           
           // Crear detalles
           foreach ($ordenData['detalles'] as $det) {
               $detalle = new \App\Models\OrdenCompraDetalle;
               $det['id_orden_compra'] = $orden->id;
               $detalle->fill($det);
               $detalle->save();
           }
    
           // Si ya existe autorización, solo vincular
           if ($authorization) {
               $orden->update(['id_authorization' => $authorization->id]);
               $authorization->update([
                   'authorizeable_type' => 'App\Models\OrdenCompra',
                   'authorizeable_id' => $orden->id
               ]);
               $authorizationToReturn = $authorization;
           } 
           // Si no existe, crear nueva (caso legacy)
           else {
               $authTypeModel = AuthorizationType::where('name', $request->type)->first();
               
               if (!$authTypeModel) {
                   throw new \Exception("Tipo de autorización '{$request->type}' no encontrado");
               }
    
               $authorizationToReturn = Authorization::create([
                   'authorization_type_id' => $authTypeModel->id,
                   'authorizeable_type' => 'App\Models\OrdenCompra',
                   'authorizeable_id' => $orden->id,
                   'requested_by' => auth()->id(),
                   'description' => $request->description,
                   'data' => json_encode($request->data),
                   'operation_type' => 'creacion',
                   'operation_data' => json_encode($this->extractRelevantData($ordenData, 'orden_compra')),
                   'operation_hash' => $this->generateOperationHash('creacion', $ordenData, 'orden_compra'),
                   'expires_at' => now()->addHours($authTypeModel->expiration_hours ?? 24),
               ]);
    
               $orden->update(['id_authorization' => $authorizationToReturn->id]);
           }
    
           DB::commit();
    
           return response()->json([
               'ok' => true,
               'message' => 'Orden de compra creada pendiente de autorización',
               'data' => $authorizationToReturn,
               'orden' => $orden,
               'estado' => 'Pendiente Autorización'
           ]);
           
       } catch (\Exception $e) {
           DB::rollback();
           Log::error("Error creando orden pendiente: " . $e->getMessage());
           throw $e;
       }
    }

    private function handleUserPendingChanges($request, $authorization)
    {
        $userId = $request->data['id_usuario'];
        $user = \App\Models\User::findOrFail($userId);
        
        $dataToStore = $request->data;
        
        Log::info('=== HANDLE USER PENDING CHANGES (FIXED) ===');
        Log::info('User ID:', ['id' => $userId]);
        Log::info('Request type:', ['type' => $request->type]);
        Log::info('Request data received:', $request->data);
        
        // CORRECCIÓN: Limpiar y procesar los datos correctamente
        $cleanedData = [];
        
        foreach ($dataToStore as $key => $value) {
            // Evitar campos problemáticos
            if (in_array($key, ['roles', 'pending_changes', 'created_at', 'updated_at'])) {
                Log::info("Saltando campo problemático: {$key}");
                continue;
            }
            
            // Si el valor es "[object Object]", saltarlo
            if ($value === '[object Object]') {
                Log::warning("Saltando valor [object Object] para campo: {$key}");
                continue;
            }
            
            // Si es un string que parece JSON, intentar decodificarlo
            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cleanedData[$key] = $decoded;
                    Log::info("JSON decodificado para {$key}:", $decoded);
                } else {
                    $cleanedData[$key] = $value;
                }
            } else {
                $cleanedData[$key] = $value;
            }
        }
        
        Log::info('Datos limpiados:', $cleanedData);
        
        // Procesar según el tipo de cambio
        switch ($request->type) {
            case 'editar_usuario_password':
                if (isset($cleanedData['password'])) {
                    $cleanedData['password'] = Hash::make($cleanedData['password']);
                    Log::info('Password hasheado para usuario:', ['user_id' => $userId]);
                }
                break;
                
            case 'editar_usuario_rol':
                if (!isset($cleanedData['rol_id'])) {
                    Log::error('rol_id no encontrado en datos:', $cleanedData);
                    throw new \Exception('rol_id es requerido para cambiar el rol del usuario');
                }
                
                $rol = \Spatie\Permission\Models\Role::find($cleanedData['rol_id']);
                if (!$rol) {
                    throw new \Exception("Rol con ID {$cleanedData['rol_id']} no encontrado");
                }
                
                $cleanedData['rol_name'] = $rol->name;
                
                Log::info('Preparando cambio de rol:', [
                    'user_id' => $userId,
                    'new_rol_id' => $cleanedData['rol_id'],
                    'rol_name' => $rol->name
                ]);
                break;
                
            case 'editar_usuario_codigo':
                if (!isset($cleanedData['codigo_autorizacion'])) {
                    throw new \Exception('codigo_autorizacion es requerido');
                }
                break;
        }

        $pendingChanges = [
            'type' => $request->type,
            'data' => $cleanedData  // Usar datos limpiados
        ];
        
        Log::info('Guardando pending_changes limpios:', $pendingChanges);
        
        $user->pending_changes = $pendingChanges;
        $user->id_authorization = $authorization->id;
        $user->save();

        $authorization->update([
            'authorizeable_type' => 'App\Models\User',
            'authorizeable_id' => $user->id
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Cambio pendiente de autorización',
            'data' => $authorization,
            'pending_changes' => $pendingChanges
        ]);
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