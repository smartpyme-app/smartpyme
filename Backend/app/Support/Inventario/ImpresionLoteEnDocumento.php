<?php

namespace App\Support\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;

final class ImpresionLoteEnDocumento
{
    public static function mostrarLotes(?Empresa $empresa): bool
    {
        return $empresa?->isLotesActivo() ?? false;
    }

    public static function prepararVenta(Venta $venta, ?Empresa $empresa): bool
    {
        if (! self::mostrarLotes($empresa)) {
            return false;
        }
        $venta->loadMissing(['detalles.lote']);

        return true;
    }

    public static function prepararDevolucion(Devolucion $devolucion, ?Empresa $empresa): bool
    {
        if (! self::mostrarLotes($empresa)) {
            return false;
        }
        $devolucion->loadMissing(['detalles.lote']);

        return true;
    }

    public static function prepararCompra(Compra $compra, ?Empresa $empresa): bool
    {
        if (! self::mostrarLotes($empresa)) {
            return false;
        }
        $compra->loadMissing(['detalles.lote']);

        return true;
    }
}
