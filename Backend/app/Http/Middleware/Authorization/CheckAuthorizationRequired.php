<?php

namespace App\Http\Middleware\Authorization;

use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\Request;

class CheckAuthorizationRequired
{
    protected $authorizationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    public function handle(Request $request, Closure $next, $authorizationType)
    {
        // Obtener datos del request para evaluar condiciones
        $data = $this->extractDataFromRequest($request, $authorizationType);
        
        // Verificar si requiere autorización
        $requiresAuth = $this->authorizationService->requiresAuthorization(
            $authorizationType, 
            $data
        );

        if ($requiresAuth) {
            return response()->json([
                'ok' => false,
                'requires_authorization' => true,
                'authorization_type' => $authorizationType,
                'message' => 'Esta acción requiere autorización'
            ], 403);
        }

        return $next($request);
    }

    private function extractDataFromRequest(Request $request, $authorizationType)
    {
        $data = [];

        switch ($authorizationType) {
            case 'purchase_orders_high_amount':
                $data['amount'] = $request->input('total', 0);
                break;
            case 'sales_high_discount':
                $data['discount'] = $request->input('discount_percentage', 0);
                break;
            case 'inventory_adjustments_high':
                $data['quantity'] = abs($request->input('quantity_adjustment', 0));
                break;
        }

        return $data;
    }
}