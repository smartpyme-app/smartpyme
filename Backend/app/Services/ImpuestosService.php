<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Log;

class ImpuestosService
{
    /**
     * Obtiene el porcentaje de IVA configurado para la empresa
     * El campo empresa.iva contiene el porcentaje directamente (13 = 13%, 16 = 16%, etc.)
     *
     * @param int $empresaId
     * @return float
     */
    public function obtenerPorcentajeImpuesto($empresaId)
    {
        $empresa = Empresa::withoutGlobalScope('empresa')->find($empresaId);

        if (!$empresa) {
            Log::warning('Empresa no encontrada', [
                'empresa_id' => $empresaId
            ]);
            return 0.0;
        }

        return floatval($empresa->iva ?? 0);
    }

    /**
     * Calcula el precio sin impuesto desde un precio con impuesto
     *
     * @param float $precioConImpuesto
     * @param int $empresaId
     * @param bool $redondear Si true (default), redondea a 2 decimales. Si false, retorna el float con todos sus decimales.
     * @return float
     */
    public function calcularPrecioSinImpuesto($precioConImpuesto, $empresaId, $redondear = true)
    {
        $precioConImpuesto = floatval($precioConImpuesto);

        if ($precioConImpuesto <= 0) {
            return 0.0;
        }

        $porcentajeIva = $this->obtenerPorcentajeImpuesto($empresaId);

        // Si no hay IVA configurado, devolver el precio original
        if ($porcentajeIva <= 0) {
            Log::warning('No hay IVA configurado, usando precio original', [
                'empresa_id' => $empresaId,
                'precio' => $precioConImpuesto
            ]);
            return $precioConImpuesto;
        }

        // Calcular factor de división
        // Si el IVA es 13%, el factor es: 1 / (1 + 0.13) = 1 / 1.13
        $factorSinImpuesto = 1 / (1 + ($porcentajeIva / 100));
        $precioSinImpuesto = $precioConImpuesto * $factorSinImpuesto;

        if ($redondear) {
            $precioSinImpuesto = round($precioSinImpuesto, 2);
        }

        Log::debug('Precio calculado sin impuesto', [
            'precio_con_impuesto' => $precioConImpuesto,
            'precio_sin_impuesto' => $precioSinImpuesto,
            'iva_porcentaje' => $porcentajeIva,
            'factor_usado' => $factorSinImpuesto
        ]);

        return $precioSinImpuesto;
    }

    /**
     * Calcula el precio con impuesto desde un precio sin impuesto
     *
     * @param float $precioSinImpuesto
     * @param int $empresaId
     * @return float
     */
    public function calcularPrecioConImpuesto($precioSinImpuesto, $empresaId)
    {
        $precioSinImpuesto = floatval($precioSinImpuesto);

        if ($precioSinImpuesto <= 0) {
            return 0.0;
        }

        $porcentajeIva = $this->obtenerPorcentajeImpuesto($empresaId);

        // Si no hay IVA configurado, devolver el precio original
        if ($porcentajeIva <= 0) {
            return $precioSinImpuesto;
        }

        // Calcular precio con impuesto
        // Si el IVA es 13%: precio_con_impuesto = precio_sin_impuesto * 1.13
        $precioConImpuesto = $precioSinImpuesto * (1 + ($porcentajeIva / 100));

        // Redondear a 2 decimales
        $precioConImpuesto = round($precioConImpuesto, 2);

        return $precioConImpuesto;
    }

    /**
     * Calcula el monto de impuesto desde un precio con impuesto
     *
     * @param float $precioConImpuesto
     * @param int $empresaId
     * @param int $cantidad
     * @return array ['monto' => float, 'porcentaje' => float]
     */
    public function calcularMontoImpuesto($precioConImpuesto, $empresaId, $cantidad = 1)
    {
        $precioConImpuesto = floatval($precioConImpuesto);
        $cantidad = floatval($cantidad);

        if ($precioConImpuesto <= 0 || $cantidad <= 0) {
            return [
                'monto' => 0.0,
                'porcentaje' => 0.0
            ];
        }

        $porcentajeIva = $this->obtenerPorcentajeImpuesto($empresaId);

        // Si no hay IVA configurado
        if ($porcentajeIva <= 0) {
            return [
                'monto' => 0.0,
                'porcentaje' => 0.0
            ];
        }

        // Calcular precio sin impuesto
        $precioSinImpuesto = $this->calcularPrecioSinImpuesto($precioConImpuesto, $empresaId);

        // Calcular monto de impuesto por unidad y total
        $impuestoPorUnidad = $precioConImpuesto - $precioSinImpuesto;
        $montoTotal = round($impuestoPorUnidad * $cantidad, 2);

        return [
            'monto' => $montoTotal,
            'porcentaje' => $porcentajeIva
        ];
    }

}
