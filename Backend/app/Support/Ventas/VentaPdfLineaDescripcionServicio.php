<?php

namespace App\Support\Ventas;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Support\Collection;

/**
 * Texto de catálogo (producto.descripcion) para líneas de servicio en PDF de venta.
 */
final class VentaPdfLineaDescripcionServicio
{
    public static function textoParaPdf(?Venta $venta, int $lineaIdx1Based, ?Empresa $empresa): string
    {
        if ($venta === null || $lineaIdx1Based < 1) {
            return '';
        }

        if ($empresa && ! (bool) $empresa->getCustomConfigValue('configuraciones', 'dte_mostrar_descripcion_producto', true)) {
            return '';
        }

        $detalle = self::detalleEnPosicion($venta, $lineaIdx1Based);
        if ($detalle === null) {
            return '';
        }

        $producto = $detalle->producto;
        if ($producto === null || strcasecmp((string) ($producto->tipo ?? ''), 'Servicio') !== 0) {
            return '';
        }

        $texto = trim((string) ($producto->descripcion ?? ''));
        if ($texto === '') {
            return '';
        }

        $nombreLinea = trim((string) ($detalle->descripcion ?? $detalle->nombre_producto ?? ''));
        if ($nombreLinea !== '' && strcasecmp($texto, $nombreLinea) === 0) {
            return '';
        }

        return $texto;
    }

    private static function detalleEnPosicion(Venta $venta, int $lineaIdx1Based): ?Detalle
    {
        $detalles = self::detallesOrdenados($venta);

        return $detalles->get($lineaIdx1Based - 1);
    }

    /** @return Collection<int, Detalle> */
    private static function detallesOrdenados(Venta $venta): Collection
    {
        if ($venta->relationLoaded('detalles')) {
            return $venta->detalles->sortBy('id')->values();
        }

        return $venta->detalles()->orderBy('id')->with('producto')->get();
    }
}