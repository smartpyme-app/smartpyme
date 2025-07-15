<?php

namespace App\Traits\Authorization;

use App\Services\Authorization\AutoAuthorizationService;
use App\Models\Authorization\Authorization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait HasAutoAuthorization
{
    // protected $authModule = null; // Definir en cada controlador

    /**
     * Verificar autorización automática
     */
    /**
     * Verificar autorización automática - MÉTODO ACTUALIZADO
     */
    protected function checkAuth($action, $data = [])
    {
        Log::info("=== CHECK AUTH ===");
        Log::info("Module: {$this->authModule}, Action: {$action}");

        if (!$this->authModule) {
            throw new \Exception('Debe definir $authModule en el controlador');
        }

        // NUEVO: Usar la lógica del RequiresAuthorizationCheck
        $authCheck = $this->requiresAuth($action, $data);

        // Si no requiere autorización, continuar normalmente
        if (!$authCheck) {
            Log::info("No requiere autorización");
            return null;
        }

        Log::info("Requiere autorización:", $authCheck);

        // Generar hash único de la operación
        $operationHash = $this->generateOperationHash($action, $data);
        $authType = $authCheck['type']; // Usar el tipo del nuevo método

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

        // Retornar que requiere autorización con el mensaje mejorado
        return response()->json([
            'ok' => false,
            'requires_authorization' => true,
            'authorization_type' => $authType,
            'message' => $authCheck['message'] // Usar mensaje del nuevo método
        ], 403);
    }

    /**
     * MÉTODO AGREGADO: Del RequiresAuthorizationCheck
     */
    protected function requiresAuth($action, $data = [])
    {
        if (!$this->authModule) {
            throw new \Exception('Debe definir $authModule en el controlador');
        }

        // Verificar condiciones específicas usando la lógica detallada
        if (!$this->shouldRequireAuthorizationDetailed($action, $data)) {
            return false; // No requiere autorización
        }

        // Devolver información de la autorización requerida
        return [
            'required' => true,
            'type' => $this->getAuthorizationTypeDetailed($action, $data),
            'message' => $this->getAuthorizationMessageDetailed($action, $data),
            'data' => $this->getRelevantOperationDataDetailed($data)
        ];
    }

    /**
     * MÉTODOS AGREGADOS: Versiones detalladas del RequiresAuthorizationCheck
     */
    private function shouldRequireAuthorizationDetailed($action, $data)
    {

        if ($this->isUserExcludedFromAuthorization($action, $data)) {
            Log::info("Usuario excluido de autorización por rol");
            return false; // Usuario excluido, NO necesita autorización
        }
        
        switch ($this->authModule) {
            case 'compras':
                return $this->checkComprasConditionsDetailed($action, $data);
            case 'orden_compra':
            case 'ordenes_compra':
                return $this->checkOrdenCompraConditionsDetailed($action, $data);
            case 'ventas':
                return $this->checkVentasConditionsDetailed($action, $data);
            case 'usuarios':
                return $this->checkUsuariosConditionsDetailed($action, $data);
            case 'inventario':
                return $this->checkInventarioConditionsDetailed($action, $data);
            case 'caja':
                return $this->checkCajaConditionsDetailed($action, $data);
            default:
                return false;
        }
    }

    /**
     * MÉTODO NUEVO: Verificar si el usuario está excluido por rol
     */
    private function isUserExcludedFromAuthorization($action, $data)
    {
        $authType = $this->getAuthorizationTypeDetailed($action, $data);
        $authTypeModel = \App\Models\Authorization\AuthorizationType::where('name', $authType)->first();
        
        if (!$authTypeModel || !$authTypeModel->conditions) {
            return false;
        }
        
        // Usar el método evaluateConditions del modelo
        $needsAuth = $authTypeModel->evaluateConditions($data);
        
        // Si evaluateConditions devuelve false, significa que NO necesita autorización
        return !$needsAuth;
    }

    private function checkComprasConditionsDetailed($action, $data)
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

    private function checkOrdenCompraConditionsDetailed($action, $data)
    {
        if (in_array($action, ['store'])) {
            $total = $data['total'] ?? $data['sub_total'] ?? 0;

            if ($total == 0 && isset($data['detalles'])) {
                $total = collect($data['detalles'])->sum('total');
            }

            return $total > 0; // Cualquier monto requiere autorización por niveles
        }
        return false;
    }

    private function checkVentasConditionsDetailed($action, $data)
    {
        switch ($action) {
            case 'apply_discount':
                return ($data['discount_percentage'] ?? 0) > 15;
            case 'facturacion':
                $total = $data['total'] ?? 0;
                return $total > 5000;
            default:
                return false;
        }
    }

    private function checkUsuariosConditionsDetailed($action, $data)
    {
        return in_array($action, ['change_password', 'change_role', 'update_critical_data']);
    }

    private function checkInventarioConditionsDetailed($action, $data)
    {
        switch ($action) {
            case 'ajuste':
                $cantidad = abs($data['cantidad_ajuste'] ?? 0);
                return $cantidad > 100;
            case 'transferencia':
                return ($data['valor_total'] ?? 0) > 2000;
            default:
                return false;
        }
    }

    private function checkCajaConditionsDetailed($action, $data)
    {
        switch ($action) {
            case 'retiro':
                return ($data['monto'] ?? 0) > 1000;
            case 'arqueo_diferencia':
                $diferencia = abs($data['diferencia'] ?? 0);
                return $diferencia > 50;
            default:
                return false;
        }
    }

    private function getAuthorizationTypeDetailed($action, $data = [])
    {
        $mappings = [
            'compras' => [
                'store' => 'compras_altas',
                'facturacion' => 'compras_altas'
            ],
            'orden_compra' => [
                'store' => $this->determinarTipoAutorizacionOrdenDetailed($data)
            ],
            'ventas' => [
                'apply_discount' => 'ventas_descuento_alto',
                'facturacion' => 'ventas_monto_alto'
            ],
            'usuarios' => [
                'change_password' => 'editar_usuario_password',
                'change_role' => 'editar_usuario_rol',
                'update_critical_data' => 'editar_usuario_critico'
            ],
            'inventario' => [
                'ajuste' => 'inventario_ajuste_alto',
                'transferencia' => 'inventario_transferencia_alta'
            ],
            'caja' => [
                'retiro' => 'caja_retiro_alto',
                'arqueo_diferencia' => 'caja_diferencia_alta'
            ]
        ];

        return $mappings[$this->authModule][$action] ?? $this->authModule . '_' . $action;
    }

    private function determinarTipoAutorizacionOrdenDetailed($data)
    {
        $total = $data['total'] ?? $data['sub_total'] ?? 0;
        if ($total == 0 && isset($data['detalles'])) {
            $total = collect($data['detalles'])->sum('total');
        }

        if ($total >= 5000) return 'orden_compra_nivel_3';
        if ($total >= 300) return 'orden_compra_nivel_2';
        return 'orden_compra_nivel_1';
    }

    private function getAuthorizationMessageDetailed($action, $data)
    {
        switch ($this->authModule) {
            case 'compras':
                $total = $data['total'] ?? $data['sub_total'] ?? 0;
                if ($total == 0 && isset($data['detalles'])) {
                    $total = collect($data['detalles'])->sum('total');
                }
                return "Esta compra de $" . number_format($total, 2) . " requiere autorización (supera los $3,000)";

            case 'orden_compra':
            case 'ordenes_compra':
                $total = $data['total'] ?? $data['sub_total'] ?? 0;
                if ($total == 0 && isset($data['detalles'])) {
                    $total = collect($data['detalles'])->sum('total');
                }
                return "Esta orden de compra de $" . number_format($total, 2) . " requiere autorización";

            case 'ventas':
                if ($action === 'apply_discount') {
                    $descuento = $data['discount_percentage'] ?? 0;
                    return "Descuento del {$descuento}% requiere autorización (supera el 15%)";
                }
                $total = $data['total'] ?? 0;
                return "Venta de $" . number_format($total, 2) . " requiere autorización (supera los $5,000)";

            case 'usuarios':
                return "La modificación de datos de usuario requiere autorización";

            case 'inventario':
                if ($action === 'ajuste') {
                    $cantidad = abs($data['cantidad_ajuste'] ?? 0);
                    return "Ajuste de {$cantidad} unidades requiere autorización (supera las 100)";
                }
                $valor = $data['valor_total'] ?? 0;
                return "Transferencia de $" . number_format($valor, 2) . " requiere autorización";

            case 'caja':
                if ($action === 'retiro') {
                    $monto = $data['monto'] ?? 0;
                    return "Retiro de $" . number_format($monto, 2) . " requiere autorización (supera los $1,000)";
                }
                $diferencia = abs($data['diferencia'] ?? 0);
                return "Diferencia de $" . number_format($diferencia, 2) . " en arqueo requiere autorización";

            default:
                return "Acción '{$action}' en módulo '{$this->authModule}' requiere autorización";
        }
    }

    private function getRelevantOperationDataDetailed($data)
    {
        switch ($this->authModule) {
            case 'compras':
                return [
                    'total' => $data['total'] ?? 0,
                    'id_proveedor' => $data['id_proveedor'] ?? null,
                    'detalles_count' => count($data['detalles'] ?? []),
                    'fecha' => $data['fecha'] ?? null,
                ];

            case 'orden_compra':
            case 'ordenes_compra':
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

            case 'inventario':
                return [
                    'id_producto' => $data['id_producto'] ?? null,
                    'cantidad' => $data['cantidad_ajuste'] ?? $data['cantidad'] ?? 0,
                    'tipo_ajuste' => $data['tipo_ajuste'] ?? 'manual',
                ];

            case 'caja':
                return [
                    'monto' => $data['monto'] ?? $data['diferencia'] ?? 0,
                    'id_caja' => $data['id_caja'] ?? null,
                    'tipo_operacion' => $data['tipo_operacion'] ?? 'retiro',
                ];

            default:
                return array_intersect_key($data, array_flip(['id', 'total', 'fecha']));
        }
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
        return hash(
            'sha256',
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

            case 'orden_compra':
                $total = $data['total'] ?? $data['sub_total'] ?? 0;
                if ($total == 0 && isset($data['detalles'])) {
                    $total = collect($data['detalles'])->sum('total');
                }
                return "Orden de compra por $" . number_format($total, 2) . " que supera el límite autorizado";

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
            case 'orden_compra':
                return $this->checkOrdenCompraConditions($action, $data);
            case 'ventas':
                return $this->checkVentasConditions($action, $data);
            case 'usuarios':
                return $this->checkUsuariosConditions($action, $data);
            case 'ordenes_compra':
                return $this->checkOrdenCompraConditions($action, $data);
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
     * Verificar condiciones de orden de compra
     */
    private function checkOrdenCompraConditions($action, $data)
    {
        if (in_array($action, ['store'])) {
            $total = $data['total'] ?? $data['sub_total'] ?? 0;

            if ($total == 0 && isset($data['detalles'])) {
                $total = collect($data['detalles'])->sum('total');
            }

            // Cualquier orden de compra requiere autorización según el nivel
            return $total > 0;
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
        return in_array($action, ['change_password', 'change_role', 'change_auth_code']);
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
