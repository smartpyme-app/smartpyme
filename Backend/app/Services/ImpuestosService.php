<?php

namespace App\Services;

use App\Models\Admin\Impuesto;
use App\Models\Ventas\Impuesto as VentaImpuesto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ImpuestosService
{
    /**
     * Obtiene el impuesto principal (IVA) configurado para la empresa
     *
     * @param int $empresaId
     * @return Impuesto|null
     */
    public function obtenerImpuestoPrincipal($empresaId)
    {
        // Cachear el impuesto por 1 hora para mejorar performance
        $cacheKey = "empresa_{$empresaId}_impuesto_principal";

        return Cache::remember($cacheKey, 3600, function () use ($empresaId) {
            // Buscar el impuesto principal (IVA, IVU, IGV, etc.)
            // Primero intentar por nombre común
            $impuesto = Impuesto::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresaId)
                ->whereIn('nombre', ['IVA', 'IGV', 'IVU', 'Impuesto'])
                ->orderBy('porcentaje', 'desc') // El mayor porcentaje primero
                ->first();

            // Si no se encuentra, obtener el primer impuesto configurado
            if (!$impuesto) {
                $impuesto = Impuesto::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresaId)
                    ->orderBy('porcentaje', 'desc')
                    ->first();
            }

            if ($impuesto) {
                Log::info('Impuesto principal obtenido', [
                    'empresa_id' => $empresaId,
                    'impuesto_id' => $impuesto->id,
                    'nombre' => $impuesto->nombre,
                    'porcentaje' => $impuesto->porcentaje
                ]);
            } else {
                Log::warning('No se encontró impuesto configurado para empresa', [
                    'empresa_id' => $empresaId
                ]);
            }

            return $impuesto;
        });
    }

    /**
     * Calcula el precio sin impuesto desde un precio con impuesto
     *
     * @param float $precioConImpuesto
     * @param int $empresaId
     * @return float
     */
    public function calcularPrecioSinImpuesto($precioConImpuesto, $empresaId)
    {
        $precioConImpuesto = floatval($precioConImpuesto);

        if ($precioConImpuesto <= 0) {
            return 0.0;
        }

        $impuesto = $this->obtenerImpuestoPrincipal($empresaId);

        // Si no hay impuesto configurado, devolver el precio original
        if (!$impuesto || $impuesto->porcentaje <= 0) {
            Log::warning('No hay impuesto configurado, usando precio original', [
                'empresa_id' => $empresaId,
                'precio' => $precioConImpuesto
            ]);
            return $precioConImpuesto;
        }

        // Calcular factor de división
        // Si el impuesto es 13%, el factor es: 1 / (1 + 0.13) = 1 / 1.13
        $factorSinImpuesto = 1 / (1 + ($impuesto->porcentaje / 100));
        $precioSinImpuesto = $precioConImpuesto * $factorSinImpuesto;

        // Redondear a 2 decimales
        $precioSinImpuesto = round($precioSinImpuesto, 2);

        Log::debug('Precio calculado sin impuesto', [
            'precio_con_impuesto' => $precioConImpuesto,
            'precio_sin_impuesto' => $precioSinImpuesto,
            'impuesto_porcentaje' => $impuesto->porcentaje,
            'impuesto_nombre' => $impuesto->nombre,
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

        $impuesto = $this->obtenerImpuestoPrincipal($empresaId);

        // Si no hay impuesto configurado, devolver el precio original
        if (!$impuesto || $impuesto->porcentaje <= 0) {
            return $precioSinImpuesto;
        }

        // Calcular precio con impuesto
        // Si el impuesto es 13%: precio_con_impuesto = precio_sin_impuesto * 1.13
        $precioConImpuesto = $precioSinImpuesto * (1 + ($impuesto->porcentaje / 100));

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
     * @return array ['monto' => float, 'impuesto_id' => int, 'porcentaje' => float]
     */
    public function calcularMontoImpuesto($precioConImpuesto, $empresaId, $cantidad = 1)
    {
        $precioConImpuesto = floatval($precioConImpuesto);
        $cantidad = floatval($cantidad);

        if ($precioConImpuesto <= 0 || $cantidad <= 0) {
            return [
                'monto' => 0.0,
                'impuesto_id' => null,
                'porcentaje' => 0.0
            ];
        }

        $impuesto = $this->obtenerImpuestoPrincipal($empresaId);

        // Si no hay impuesto configurado
        if (!$impuesto || $impuesto->porcentaje <= 0) {
            return [
                'monto' => 0.0,
                'impuesto_id' => null,
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
            'impuesto_id' => $impuesto->id,
            'porcentaje' => $impuesto->porcentaje,
            'nombre' => $impuesto->nombre
        ];
    }

    /**
     * Guarda el impuesto de una venta en la tabla venta_impuestos
     *
     * @param int $ventaId
     * @param float $montoImpuesto
     * @param int $empresaId
     * @return VentaImpuesto|null
     */
    public function guardarImpuestoVenta($ventaId, $montoImpuesto, $empresaId)
    {
        $impuesto = $this->obtenerImpuestoPrincipal($empresaId);

        if (!$impuesto) {
            Log::warning('No se pudo guardar impuesto de venta - impuesto no configurado', [
                'venta_id' => $ventaId,
                'empresa_id' => $empresaId
            ]);
            return null;
        }

        // Verificar si ya existe un registro para esta venta
        $ventaImpuesto = VentaImpuesto::where('id_venta', $ventaId)
            ->where('id_impuesto', $impuesto->id)
            ->first();

        if ($ventaImpuesto) {
            // Actualizar el monto existente
            $ventaImpuesto->update(['monto' => $montoImpuesto]);

            Log::info('Impuesto de venta actualizado', [
                'venta_id' => $ventaId,
                'impuesto_id' => $impuesto->id,
                'monto' => $montoImpuesto
            ]);
        } else {
            // Crear nuevo registro
            $ventaImpuesto = VentaImpuesto::create([
                'id_venta' => $ventaId,
                'id_impuesto' => $impuesto->id,
                'monto' => $montoImpuesto
            ]);

            Log::info('Impuesto de venta creado', [
                'venta_id' => $ventaId,
                'impuesto_id' => $impuesto->id,
                'monto' => $montoImpuesto
            ]);
        }

        return $ventaImpuesto;
    }

    /**
     * Obtiene el porcentaje de impuesto configurado para una empresa
     *
     * @param int $empresaId
     * @return float
     */
    public function obtenerPorcentajeImpuesto($empresaId)
    {
        $impuesto = $this->obtenerImpuestoPrincipal($empresaId);

        return $impuesto ? floatval($impuesto->porcentaje) : 0.0;
    }

    /**
     * Limpia el cache de impuestos para una empresa
     *
     * @param int $empresaId
     * @return void
     */
    public function limpiarCacheImpuesto($empresaId)
    {
        $cacheKey = "empresa_{$empresaId}_impuesto_principal";
        Cache::forget($cacheKey);

        Log::info('Cache de impuesto limpiado', [
            'empresa_id' => $empresaId
        ]);
    }
}
