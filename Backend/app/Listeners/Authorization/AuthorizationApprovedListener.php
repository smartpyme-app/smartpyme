<?php

namespace App\Listeners\Authorization;

use App\Events\AuthorizationApproved;
use App\Http\Controllers\Api\Compras\ComprasController;
use Illuminate\Support\Facades\Log;

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
    }
}