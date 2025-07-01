<?php

namespace App\Listeners\Authorization;

use App\Events\AuthorizationApproved;
use App\Http\Controllers\Api\Compras\ComprasController;
use App\Http\Controllers\Api\Compras\Cotizaciones\CotizacionesController;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class AuthorizationApprovedListener
{
    public function handle(AuthorizationApproved $event)
    {
        $authorization = $event->authorization;
        
        Log::info("Procesando autorización aprobada: " . $authorization->id);
        
        // Si es una autorización de compras, procesar la compra
        if ($authorization->authorizationType->name === 'compras_altas' || 
            $authorization->authorizationType->name === 'compras_facturacion') {
            
            if ($authorization->authorizeable_type === 'App\Models\Compras\Compra' && 
                $authorization->authorizeable_id > 0) {
                
                try {
                    $compraController = app(ComprasController::class);
                    $compraController->procesarCompraAutorizada($authorization->authorizeable_id);
                    
                    Log::info("Compra procesada exitosamente tras autorización");
                    
                } catch (\Exception $e) {
                    Log::error("Error procesando compra tras autorización: " . $e->getMessage());
                }
            }
        }

        // Si es una autorización de orden de compra, procesar la orden
        if (str_starts_with($authorization->authorizationType->name, 'orden_compra_nivel_')) {
            
            if ($authorization->authorizeable_type === 'App\Models\OrdenCompra' && 
                $authorization->authorizeable_id > 0) {
                
                try {
                    $ordenController = app(CotizacionesController::class);
                    $ordenController->procesarOrdenAutorizada($authorization->authorizeable_id);
                    
                    Log::info("Orden de compra procesada exitosamente tras autorización");
                    
                } catch (\Exception $e) {
                    Log::error("Error procesando orden de compra tras autorización: " . $e->getMessage());
                }
            }
        }

        if (str_starts_with($authorization->authorizationType->name, 'editar_usuario_')) {
            $this->applyUserChanges($authorization);
        }
    }

    private function applyUserChanges($authorization)
    {
        try {
            $user = \App\Models\User::find($authorization->authorizeable_id);
            if (!$user) {
                Log::warning("Usuario no encontrado: " . $authorization->authorizeable_id);
                return;
            }

            if (!$user->pending_changes) {
                Log::warning("Sin cambios pendientes para usuario: " . $authorization->authorizeable_id);
                return;
            }

            $changes = $user->pending_changes;
            
            Log::info("=== APLICANDO CAMBIOS DE USUARIO ===");
            Log::info("User ID: " . $user->id);
            Log::info("Changes type: " . $changes['type']);
            Log::info("Changes data: ", $changes['data']);
            
            switch ($changes['type']) {
                case 'editar_usuario_password':
                    if (isset($changes['data']['password'])) {
                        $user->password = $changes['data']['password'];
                        Log::info("✅ Contraseña actualizada para usuario: " . $user->id);
                    } else {
                        Log::error("❌ No se encontró password en los datos de cambio");
                        return;
                    }
                    break;
                    
                    case 'editar_usuario_rol':
                        if (isset($changes['data']['rol_id'])) {
                            $newRolId = $changes['data']['rol_id'];
                            
                            $rol = Role::find($newRolId);
                            if (!$rol) {
                                Log::error("❌ Rol con ID {$newRolId} no encontrado");
                                return;
                            }
                            
                            // Obtener roles actuales usando Spatie
                            $currentRoles = $user->getRoleNames()->toArray();
                            
                            // CAMBIO: Usar métodos de Spatie en lugar de sync()
                            $user->syncRoles([$rol->name]); // ← ESTA es la línea que cambias
                            
                            Log::info("✅ Rol actualizado para usuario: " . $user->id);
                            Log::info("   Roles anteriores: " . implode(', ', $currentRoles));
                            Log::info("   Nuevo rol: " . $rol->name . " (ID: {$newRolId})");
                        } else {
                            Log::error("❌ No se encontró rol_id en los datos de cambio");
                            Log::info("Datos disponibles: " . implode(', ', array_keys($changes['data'])));
                            return;
                        }
                        break;
                    
                case 'editar_usuario_codigo':
                    if (isset($changes['data']['codigo_autorizacion'])) {
                        $user->codigo_autorizacion = $changes['data']['codigo_autorizacion'];
                        Log::info("✅ Código de autorización actualizado para usuario: " . $user->id);
                    } else {
                        Log::error("❌ No se encontró codigo_autorizacion en los datos de cambio");
                        return;
                    }
                    break;

                default:
                    Log::warning("⚠️ Tipo de cambio no reconocido: " . $changes['type']);
                    return;
            }
            
            $user->pending_changes = null;
            $user->id_authorization = null;
            $user->save();
            
            Log::info("✅ Cambios de usuario aplicados exitosamente: " . $user->id);
            
        } catch (\Exception $e) {
            Log::error("❌ Error aplicando cambios de usuario: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
        }
    }
}