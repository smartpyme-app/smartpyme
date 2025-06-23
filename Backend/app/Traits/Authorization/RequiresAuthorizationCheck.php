<?php

namespace App\Traits\Authorization;

trait RequiresAuthorizationCheck
{
    // protected $authModule = null; // Definir en cada controlador
    
    /**
     * SOLO verificar si requiere autorización (no crear nada)
     */
    protected function requiresAuth($action, $data = [])
    {
        if (!$this->authModule) {
            throw new \Exception('Debe definir $authModule en el controlador');
        }

        // Verificar condiciones específicas
        if (!$this->shouldRequireAuthorization($action, $data)) {
            return false; // No requiere autorización
        }

        // Devolver información de la autorización requerida
        return [
            'required' => true,
            'type' => $this->getAuthorizationType($action),
            'message' => $this->getAuthorizationMessage($action, $data),
            'data' => $this->getRelevantOperationData($data)
        ];
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
            case 'inventario':
                return $this->checkInventarioConditions($action, $data);
            case 'caja':
                return $this->checkCajaConditions($action, $data);
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

    /**
     * Verificar condiciones de usuarios
     */
    private function checkUsuariosConditions($action, $data)
    {
        return in_array($action, ['change_password', 'change_role', 'update_critical_data']);
    }

    /**
     * Verificar condiciones de inventario
     */
    private function checkInventarioConditions($action, $data)
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

    /**
     * Verificar condiciones de caja
     */
    private function checkCajaConditions($action, $data)
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
                if ($action === 'apply_discount') {
                    $descuento = $data['discount_percentage'] ?? 0;
                    return "Descuento del {$descuento}% requiere autorización (supera el 15%)";
                }
                $total = $data['total'] ?? 0;
                return "Venta de $" . number_format($total, 2) . " requiere autorización (supera los $5,000)";
            
            case 'usuarios':
                return "La modificación de datos críticos de usuario requiere autorización";
            
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
}