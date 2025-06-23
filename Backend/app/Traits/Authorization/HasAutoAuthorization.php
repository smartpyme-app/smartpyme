<?php

namespace App\Traits\Authorization;

use App\Services\Authorization\AutoAuthorizationService;
use App\Models\Authorization\Authorization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HasAutoAuthorization
{
    // protected $authModule = null; // Definir en cada controlador
    
    /**
     * Verificar autorización automática
     */
    protected function checkAuth($action, $data = [])
    {
        if (!$this->authModule) {
            throw new \Exception('Debe definir $authModule en el controlador');
        }

        // Verificar condiciones específicas primero
        if (!$this->shouldRequireAuthorization($action, $data)) {
            return null; // No requiere autorización
        }

        // Generar hash único de la operación
        $operationHash = $this->generateOperationHash($action, $data);
        $authType = $this->getAuthorizationType($action);

        // Buscar si ya existe una autorización pendiente para esta operación
        $existingAuth = Authorization::whereHas('authorizationType', function ($query) use ($authType) {
                $query->where('name', $authType);
            })
            ->where('operation_hash', $operationHash)
            ->where('status', 'pending')
            ->first();

        if ($existingAuth) {
            return $this->createPendingRecord($data, $existingAuth);
        }

        // CAMBIO PRINCIPAL: Solo retornar que requiere autorización, NO crear nada automáticamente
        return response()->json([
            'ok' => false,
            'requires_authorization' => true,
            'authorization_type' => $authType,
            'message' => $this->getAuthorizationMessage($action, $data)
        ], 403);
    }

    /**
     * MÉTODO NUEVO: Solo verificar si requiere autorización (sin crear nada)
     */
    protected function checkAuthRequired($action, $data = [])
    {
        if (!$this->authModule) {
            throw new \Exception('Debe definir $authModule en el controlador');
        }

        if (!$this->shouldRequireAuthorization($action, $data)) {
            return false;
        }

        return [
            'required' => true,
            'type' => $this->getAuthorizationType($action),
            'message' => $this->getAuthorizationMessage($action, $data),
            'data' => $this->getRelevantOperationData($data)
        ];
    }

    /**
     * MÉTODO NUEVO: Crear autorización + compra pendiente (solo cuando se solicita manualmente)
     */
    protected function createAuthorizationWithPendingRecord($action, $data, $authType)
    {
        $authTypeModel = \App\Models\Authorization\AuthorizationType::where('name', $authType)->first();
        
        if (!$authTypeModel) {
            throw new \Exception("Tipo de autorización '{$authType}' no encontrado");
        }

        DB::beginTransaction();
        
        try {
            // Crear la compra en estado pendiente
            $compraData = $data;
            $compraData['estado'] = 'Pendiente Autorización';
            $compraData['id_sucursal'] = Auth::user()->id_sucursal;
            
            $modelClass = $this->getModelClass();
            $compraPendiente = $modelClass::create($compraData);
            
            // Crear detalles si existen
            if (isset($data['detalles'])) {
                foreach ($data['detalles'] as $det) {
                    $detalleClass = $this->getDetalleClass();
                    $detalle = new $detalleClass;
                    $det['id_compra'] = $compraPendiente->id;
                    $detalle->fill($det);
                    $detalle->save();
                }
            }

            // Crear la autorización vinculada
            $authorization = Authorization::create([
                'authorization_type_id' => $authTypeModel->id,
                'authorizeable_type' => $modelClass,
                'authorizeable_id' => $compraPendiente->id,
                'requested_by' => Auth::id(),
                'description' => $this->getAuthorizationDescription($action, $data),
                'data' => json_encode($data),
                'operation_type' => $action,
                'operation_data' => json_encode($this->getRelevantOperationData($data)),
                'operation_hash' => $this->generateOperationHash($action, $data),
                'expires_at' => now()->addHours($authTypeModel->expiration_hours ?? 24),
            ]);

            // Actualizar compra con el ID de autorización
            $compraPendiente->update(['authorization_id' => $authorization->id]);

            DB::commit();

            return [
                'authorization' => $authorization,
                'compra' => $compraPendiente
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Crear registro pendiente de autorización
     */
    private function createPendingRecord($data, $authorization)
    {
        // Este método llama al método implementado en el controlador
        return $this->handlePendingAuthorization($data, $authorization);
    }

    /**
     * Generar hash único de la operación
     */
    private function generateOperationHash($action, $data)
    {
        $relevantData = $this->getRelevantOperationData($data);
        return hash('sha256', 
            $this->authModule . 
            $action . 
            serialize($relevantData) . 
            Auth::id() . 
            date('Y-m-d')
        );
    }

    /**
     * Obtener datos relevantes para identificar la operación
     */
    private function getRelevantOperationData($data)
    {
        switch ($this->authModule) {
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
            
            case 'usuarios':
                return [
                    'id_usuario' => $data['id'] ?? $data['id_usuario'] ?? null,
                    'campo_modificado' => $data['campo_modificado'] ?? 'general',
                ];
            
            default:
                return array_intersect_key($data, array_flip(['id', 'total', 'fecha']));
        }
    }

    /**
     * Obtener clase del modelo
     */
    private function getModelClass()
    {
        $modelMappings = [
            'compras' => 'App\\Models\\Compras\\Compra',
            'ventas' => 'App\\Models\\Ventas\\Venta',
            'usuarios' => 'App\\Models\\User',
        ];

        return $modelMappings[$this->authModule] ?? 'App\\Models\\Unknown';
    }

    /**
     * Obtener clase del detalle
     */
    private function getDetalleClass()
    {
        $detalleMappings = [
            'compras' => 'App\\Models\\Compras\\Detalle',
            'ventas' => 'App\\Models\\Ventas\\DetalleVenta',
        ];

        return $detalleMappings[$this->authModule] ?? null;
    }

    /**
     * Generar descripción de autorización
     */
    private function getAuthorizationDescription($action, $data)
    {
        switch ($this->authModule) {
            case 'compras':
                $total = $data['total'] ?? $data['sub_total'] ?? 0;
                if ($total == 0 && isset($data['detalles'])) {
                    $total = collect($data['detalles'])->sum('total');
                }
                return "Compra por $" . number_format($total, 2) . " que supera el límite autorizado";
            
            case 'ventas':
                return "Venta con condiciones especiales que requiere autorización";
            
            case 'usuarios':
                return "Modificación de datos críticos de usuario";
            
            default:
                return "Operación {$action} en {$this->authModule}";
        }
    }

    /**
     * Determinar si requiere autorización basado en condiciones específicas
     */
    private function shouldRequireAuthorization($action, $data)
    {
        switch ($this->authModule) {
            case 'compras':
                return $this->checkComprasConditions($action, $data);
            case 'ventas':
                return $this->checkVentasConditions($action, $data);
            case 'usuarios':
                return $this->checkUsuariosConditions($action, $data);
            default:
                return false;
        }
    }

    /**
     * Verificar condiciones de compras
     */
    private function checkComprasConditions($action, $data)
    {
        if (in_array($action, ['store', 'facturacion'])) {
            $total = $data['total'] ?? $data['sub_total'] ?? 0;
            
            if ($total == 0 && isset($data['detalles'])) {
                $total = collect($data['detalles'])->sum('total');
            }
            
            return $total > 3000;
        }
        return false;
    }

    /**
     * Verificar condiciones de ventas
     */
    private function checkVentasConditions($action, $data)
    {
        if ($action === 'apply_discount') {
            return $data['below_minimum_price'] ?? false;
        }
        return false;
    }

    /**
     * Verificar condiciones de usuarios
     */
    private function checkUsuariosConditions($action, $data)
    {
        return in_array($action, ['change_password', 'change_role', 'update_critical_data']);
    }

    /**
     * Obtener tipo de autorización específico
     */
    private function getAuthorizationType($action)
    {
        $mappings = [
            'compras' => [
                'store' => 'compras_altas',
                'facturacion' => 'compras_altas'
            ],
            'ventas' => [
                'apply_discount' => 'ventas_precio_minimo'
            ],
            'usuarios' => [
                'change_password' => 'editar_usuario_password',
                'change_role' => 'editar_usuario_rol',
                'update_critical_data' => 'editar_usuario_critico'
            ]
        ];

        return $mappings[$this->authModule][$action] ?? $this->authModule . '_' . $action;
    }

    /**
     * Generar mensaje de autorización personalizado
     */
    private function getAuthorizationMessage($action, $data)
    {
        switch ($this->authModule) {
            case 'compras':
                $total = $data['total'] ?? $data['sub_total'] ?? 0;
                if ($total == 0 && isset($data['detalles'])) {
                    $total = collect($data['detalles'])->sum('total');
                }
                return "Esta compra de $" . number_format($total, 2) . " requiere autorización (supera los $3,000)";
            
            case 'ventas':
                return "Esta venta con precio bajo el mínimo requiere autorización";
            
            case 'usuarios':
                return "La modificación de datos de usuario requiere autorización";
            
            default:
                return "Acción '{$action}' en módulo '{$this->authModule}' requiere autorización";
        }
    }

    /**
     * Solicitar autorización automática
     */
    protected function requestAuth($action, $model, $description, $data = [])
    {
        $authService = app(AutoAuthorizationService::class);
        return $authService->requestAuthorizationAuto(
            $this->authModule, 
            $action, 
            $model, 
            $description, 
            $data
        );
    }

    /**
     * Método abstracto que debe ser implementado en cada controlador
     * para manejar la creación de registros pendientes de autorización
     */
    abstract protected function handlePendingAuthorization($data, $authorization);
}