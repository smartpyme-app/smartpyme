<?php

namespace App\Services\FidelizacionCliente;

use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TransaccionPuntos;

class PuntosService
{
    /**
     * Calcula los puntos vencidos (no consumidos) para un cliente específico
     */
    public function calcularPuntosVencidos(int $clienteId): int
    {
        // Los puntos vencidos son aquellos de ganancias expiradas que NO fueron consumidos
        $gananciasExpiradas = TransaccionPuntos::where('id_cliente', $clienteId)
            ->where('tipo', TransaccionPuntos::TIPO_GANANCIA)
            ->whereNotNull('fecha_expiracion')
            ->where('fecha_expiracion', '<', now())
            ->get();

        $puntosVencidos = 0;
        foreach ($gananciasExpiradas as $ganancia) {
            // Calcular puntos que no fueron consumidos antes de expirar
            $puntosNoConsumidos = max(0, $ganancia->puntos - $ganancia->puntos_consumidos);
            $puntosVencidos += $puntosNoConsumidos;
        }

        return $puntosVencidos;
    }
}