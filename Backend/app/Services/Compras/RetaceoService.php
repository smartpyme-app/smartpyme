<?php

namespace App\Services\Compras;

use Illuminate\Support\Facades\Log;
use Exception;

class RetaceoService
{
    /**
     * Calcula la distribución de gastos de retaceo entre los productos
     * 
     * Realiza los siguientes cálculos:
     * - Calcula el total de gastos (transporte, seguro, DAI, otros)
     * - Calcula el valor FOB total
     * - Calcula el porcentaje de distribución por producto
     * - Distribuye proporcionalmente los gastos
     * - Calcula el costo landed
     * - Calcula el costo retaceado
     *
     * @param array $gastos Array con los gastos: transporte, seguro, dai, otros
     * @param array $detalles Array con los detalles de productos a retacear
     * @return array Array con la distribución calculada y totales
     * @throws Exception Si el valor FOB total es menor o igual a cero
     */
    public function calcularDistribucion(array $gastos, array $detalles): array
    {
        // Obtener los gastos
        $gastoTransporte = $gastos['transporte'] ?? 0;
        $gastoSeguro = $gastos['seguro'] ?? 0;
        $gastoDAI = $gastos['dai'] ?? 0;
        $gastoOtros = $gastos['otros'] ?? 0;

        $totalGastos = $gastoTransporte + $gastoSeguro + $gastoDAI + $gastoOtros;

        // Calcular el valor FOB total
        $valorFobTotal = 0;
        foreach ($detalles as $detalle) {
            $valorFobTotal += ($detalle['costo_original'] * $detalle['cantidad']);
        }

        if ($valorFobTotal <= 0) {
            throw new Exception('El valor FOB total debe ser mayor que cero');
        }

        // Calcular la distribución
        $distribucion = [];
        foreach ($detalles as $detalle) {
            $valorFob = $detalle['costo_original'] * $detalle['cantidad'];
            $porcentajeDistribucion = ($valorFob / $valorFobTotal) * 100;

            $montoTransporte = ($porcentajeDistribucion / 100) * $gastoTransporte;
            $montoSeguro = ($porcentajeDistribucion / 100) * $gastoSeguro;
            $montoDAI = ($porcentajeDistribucion / 100) * $gastoDAI;
            $montoOtros = ($porcentajeDistribucion / 100) * $gastoOtros;

            $costoLanded = $valorFob + $montoTransporte + $montoSeguro + $montoDAI + $montoOtros;
            $costoRetaceado = $detalle['cantidad'] > 0 ? $costoLanded / $detalle['cantidad'] : 0;

            $distribucion[] = [
                'id_producto' => $detalle['id_producto'],
                'id_detalle_compra' => $detalle['id'] ?? null,
                'cantidad' => $detalle['cantidad'],
                'costo_original' => $detalle['costo_original'],
                'valor_fob' => $valorFob,
                'porcentaje_distribucion' => $porcentajeDistribucion,
                'monto_transporte' => $montoTransporte,
                'monto_seguro' => $montoSeguro,
                'monto_dai' => $montoDAI,
                'monto_otros' => $montoOtros,
                'costo_landed' => $costoLanded,
                'costo_retaceado' => $costoRetaceado,
            ];
        }

        return [
            'distribucion' => $distribucion,
            'total_gastos' => $totalGastos,
            'total_retaceado' => array_sum(array_column($distribucion, 'costo_landed'))
        ];
    }
}

