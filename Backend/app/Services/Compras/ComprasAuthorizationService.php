<?php

namespace App\Services\Compras;

use App\Services\Compras\CompraService;
use App\Models\Authorization\AuthorizationType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ComprasAuthorizationService
{
    protected CompraService $compraService;
    protected float $montoLimiteAutorizacion = 3000.00;

    public function __construct(CompraService $compraService)
    {
        $this->compraService = $compraService;
    }

    /**
     * Valida si una compra requiere autorización según el monto y roles del usuario
     *
     * @param array|\Illuminate\Http\Request $data Datos de la compra
     * @param int|null $idCompra ID de la compra si es una actualización
     * @param int|null $idAuthorization ID de autorización si ya existe
     * @return array Resultado de la validación con 'requires_authorization' y datos adicionales
     */
    public function validarAutorizacionRequerida($data, ?int $idCompra = null, ?int $idAuthorization = null): array
    {
        // No requiere autorización si:
        // 1. Es una compra existente (tiene id)
        // 2. Ya tiene una autorización asociada (tiene id_authorization)
        if ($idCompra || $idAuthorization) {
            return [
                'requires_authorization' => false,
                'ok' => true
            ];
        }

        // Calcular el total de la compra
        $total = $this->compraService->calcularTotal($data);

        Log::info("Validación de autorización - Total: $" . $total);

        // Si el total supera el límite, verificar si requiere autorización
        if ($total > $this->montoLimiteAutorizacion) {
            // Verificar si el usuario tiene roles que lo excluyen de autorización
            if ($this->usuarioExcluidoDeAutorizacion($total)) {
                Log::info("Usuario excluido de autorización por rol - Total: $" . $total);
                return [
                    'requires_authorization' => false,
                    'ok' => true,
                    'total' => $total
                ];
            }

            Log::info("Compra requiere autorización - Total: $" . $total);

            return [
                'requires_authorization' => true,
                'ok' => false,
                'authorization_type' => 'compras_altas',
                'message' => "Esta compra de $" . number_format($total, 2) . " requiere autorización (supera los $" . number_format($this->montoLimiteAutorizacion, 2) . ")",
                'total' => $total
            ];
        }

        // No requiere autorización
        return [
            'requires_authorization' => false,
            'ok' => true,
            'total' => $total
        ];
    }

    /**
     * Verifica si el usuario actual tiene roles que lo excluyen de la autorización
     *
     * @param float $total Total de la compra
     * @return bool True si el usuario está excluido, false si necesita autorización
     */
    protected function usuarioExcluidoDeAutorizacion(float $total): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // Obtener el tipo de autorización
        $authTypeModel = AuthorizationType::where('name', 'compras_altas')->first();
        
        if (!$authTypeModel || !$authTypeModel->conditions) {
            return false;
        }

        // Verificar si hay roles excluidos en las condiciones
        $excludeRoles = $authTypeModel->conditions['exclude_roles'] ?? [];
        
        if (empty($excludeRoles)) {
            return false;
        }

        // Cargar roles del usuario si no están cargados
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // Verificar si el usuario tiene algún rol excluido
        $userRoles = $user->roles->pluck('name')->toArray();
        $isExcluded = !empty(array_intersect($userRoles, $excludeRoles));

        if ($isExcluded) {
            Log::info("Usuario excluido de autorización por rol - Usuario: " . $user->id . " - Roles: " . implode(', ', $userRoles));
        }

        return $isExcluded;
    }

    /**
     * Obtiene el monto límite para autorización
     *
     * @return float
     */
    public function getMontoLimiteAutorizacion(): float
    {
        return $this->montoLimiteAutorizacion;
    }
}

