<?php

namespace App\Services\FidelizacionCliente;

use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TransaccionPuntos;

class PuntosService
{
    /**
     * Calcula los puntos vencidos para un cliente específico
     */
    public function calcularPuntosVencidos(int $clienteId): int
    {
        // Los puntos vencidos se calculan desde las transacciones de ganancia que ya expiraron
        return TransaccionPuntos::where('id_cliente', $clienteId)
            ->where('tipo', 'ganancia')
            ->where('fecha_expiracion', '<', now())
            ->where('fecha_expiracion', '!=', null)
            ->sum('puntos') ?? 0;
    }
}