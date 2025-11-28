<?php

namespace App\Services\Compras;

use App\Services\Compras\CompraService;
use Illuminate\Support\Facades\Log;

class ComprasAuthorizationService
{
    protected CompraService $compraService;
    protected float $montoLimiteAutorizacion = 3000.00;

    public function __construct(CompraService $compraService)
    {
        $this->compraService = $compraService;
    }

    /**
     * Valida si una compra requiere autorización según el monto
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

        // Si el total supera el límite, requiere autorización
        if ($total > $this->montoLimiteAutorizacion) {
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
     * Obtiene el monto límite para autorización
     *
     * @return float
     */
    public function getMontoLimiteAutorizacion(): float
    {
        return $this->montoLimiteAutorizacion;
    }
}

