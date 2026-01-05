<?php

namespace App\Services\Authorization;

use App\Models\Authorization\Authorization;
use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PendingRecordService
{
    /**
     * Crear compra pendiente de autorización
     *
     * @param array $compraData
     * @param string $authType
     * @param string $description
     * @param array $requestData
     * @return array
     */
    public function createCompraPendiente(array $compraData, string $authType, string $description, array $requestData = []): array
    {
        if (!isset($compraData['detalles'])) {
            throw new \Exception('Datos de compra requeridos para crear compra pendiente');
        }

        $authTypeModel = AuthorizationType::where('name', $authType)->first();
        
        if (!$authTypeModel) {
            throw new \Exception("Tipo de autorización '{$authType}' no encontrado");
        }

        DB::beginTransaction();
        
        try {
            // Preparar datos para crear la compra
            $compraData['estado'] = 'Pendiente Autorización';
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('Usuario no autenticado');
            }
            $compraData['id_sucursal'] = $user->id_sucursal;
            
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
                'requested_by' => $user->id,
                'description' => $description,
                'data' => json_encode($requestData),
                'operation_type' => 'facturacion',
                'operation_data' => json_encode($this->extractRelevantData($compraData, 'compras')),
                'operation_hash' => $this->generateOperationHash('facturacion', $compraData, 'compras'),
                'expires_at' => now()->addHours($authTypeModel->expiration_hours ?? 24),
            ]);

            // Vincular autorización con compra
            $compra->update(['id_authorization' => $authorization->id]);

            DB::commit();

            Log::info("Compra pendiente creada exitosamente - ID: {$compra->id}, Estado: {$compra->estado}");

            return [
                'authorization' => $authorization,
                'compra' => $compra,
                'estado' => 'Pendiente Autorización'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando compra pendiente: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Crear orden de compra pendiente de autorización
     *
     * @param array $ordenData
     * @param string $authType
     * @param string $description
     * @param array $requestData
     * @param Authorization|null $authorization
     * @return array
     */
    public function createOrdenCompraPendiente(array $ordenData, string $authType, string $description, array $requestData = [], ?Authorization $authorization = null): array
    {
        if (!isset($ordenData['detalles'])) {
            throw new \Exception('Datos de orden de compra requeridos');
        }

        DB::beginTransaction();
        
        try {
            $ordenData['estado'] = 'Pendiente Autorización';
            /** @var User|null $user */
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('Usuario no autenticado');
            }
            $ordenData['id_sucursal'] = $user->id_sucursal;
            $ordenData['id_empresa'] = $user->id_empresa;
            
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
                $authTypeModel = AuthorizationType::where('name', $authType)->first();
                
                if (!$authTypeModel) {
                    throw new \Exception("Tipo de autorización '{$authType}' no encontrado");
                }

                $authorizationToReturn = Authorization::create([
                    'authorization_type_id' => $authTypeModel->id,
                    'authorizeable_type' => 'App\Models\OrdenCompra',
                    'authorizeable_id' => $orden->id,
                    'requested_by' => $user->id,
                    'description' => $description,
                    'data' => json_encode($requestData),
                    'operation_type' => 'creacion',
                    'operation_data' => json_encode($this->extractRelevantData($ordenData, 'orden_compra')),
                    'operation_hash' => $this->generateOperationHash('creacion', $ordenData, 'orden_compra'),
                    'expires_at' => now()->addHours($authTypeModel->expiration_hours ?? 24),
                ]);

                $orden->update(['id_authorization' => $authorizationToReturn->id]);
            }

            DB::commit();

            return [
                'authorization' => $authorizationToReturn,
                'orden' => $orden,
                'estado' => 'Pendiente Autorización'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando orden pendiente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Manejar cambios pendientes de usuario
     *
     * @param int $userId
     * @param string $authType
     * @param array $requestData
     * @param Authorization $authorization
     * @return array
     */
    public function handleUserPendingChanges(int $userId, string $authType, array $requestData, Authorization $authorization): array
    {
        $user = User::findOrFail($userId);
        
        Log::info('=== HANDLE USER PENDING CHANGES ===');
        Log::info('User ID:', ['id' => $userId]);
        Log::info('Request type:', ['type' => $authType]);
        Log::info('Request data received:', $requestData);
        
        // Limpiar y procesar los datos correctamente
        $cleanedData = $this->cleanUserData($requestData, $authType);
        
        Log::info('Datos limpiados:', $cleanedData);
        
        // Procesar según el tipo de cambio
        $cleanedData = $this->processUserChangeByType($authType, $cleanedData, $userId);

        $pendingChanges = [
            'type' => $authType,
            'data' => $cleanedData
        ];
        
        Log::info('Guardando pending_changes limpios:', $pendingChanges);
        
        $user->pending_changes = $pendingChanges;
        $user->id_authorization = $authorization->id;
        $user->save();

        $authorization->update([
            'authorizeable_type' => 'App\Models\User',
            'authorizeable_id' => $user->id
        ]);

        return [
            'authorization' => $authorization,
            'pending_changes' => $pendingChanges
        ];
    }

    /**
     * Limpiar datos de usuario
     *
     * @param array $data
     * @param string $authType
     * @return array
     */
    private function cleanUserData(array $data, string $authType): array
    {
        $cleanedData = [];
        
        foreach ($data as $key => $value) {
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
        
        return $cleanedData;
    }

    /**
     * Procesar cambio de usuario según tipo
     *
     * @param string $authType
     * @param array $cleanedData
     * @param int $userId
     * @return array
     */
    private function processUserChangeByType(string $authType, array $cleanedData, int $userId): array
    {
        switch ($authType) {
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

        return $cleanedData;
    }

    /**
     * Extraer datos relevantes según el módulo
     *
     * @param array $data
     * @param string $module
     * @return array
     */
    public function extractRelevantData(array $data, string $module): array
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
            
            case 'orden_compra':
                return [
                    'total' => $data['total'] ?? 0,
                    'id_proveedor' => $data['id_proveedor'] ?? null,
                    'detalles_count' => count($data['detalles'] ?? []),
                    'fecha' => $data['fecha'] ?? null,
                ];
            
            default:
                return array_intersect_key($data, array_flip(['id', 'total', 'fecha']));
        }
    }

    /**
     * Generar hash único de operación
     *
     * @param string $action
     * @param array $data
     * @param string $module
     * @return string
     */
    public function generateOperationHash(string $action, array $data, string $module): string
    {
        $relevantData = $this->extractRelevantData($data, $module);
        /** @var User|null $user */
        $user = Auth::user();
        $userId = $user ? $user->id : 0;
        return hash('sha256', 
            $module . 
            $action . 
            serialize($relevantData) . 
            $userId . 
            date('Y-m-d')
        );
    }

    /**
     * Determinar si el tipo de autorización necesita crear un registro pendiente
     *
     * @param string $authType
     * @param array $data
     * @return bool
     */
    public function needsPendingRecord(string $authType, array $data): bool
    {
        Log::info("Checking needsPendingRecord - authType: $authType");
        Log::info("Data keys: " . json_encode(array_keys($data ?? [])));
        
        // Lista de tipos que requieren registro pendiente
        $typesWithPendingRecords = [
            'compras_altas',
            'ventas_monto_alto',
        ];

        $hasCorrectType = in_array($authType, $typesWithPendingRecords);
        
        // Verificar si tiene datos de compra de cualquier forma
        $hasCompraData = isset($data['compra_data']) || // Datos del interceptor
                        isset($data['detalles']) ||    // Datos directos
                        isset($data['total']);         // Al menos tiene total
        
        Log::info("hasCorrectType: " . ($hasCorrectType ? 'YES' : 'NO'));
        Log::info("hasCompraData: " . ($hasCompraData ? 'YES' : 'NO'));

        $needsPending = $hasCorrectType && $hasCompraData;
        Log::info("Needs pending record: " . ($needsPending ? 'YES' : 'NO'));

        return $needsPending;
    }
}
