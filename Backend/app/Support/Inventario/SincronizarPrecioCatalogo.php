<?php

namespace App\Support\Inventario;

/** Alinea precio_sin_iva / precio_con_iva con precio base (catálogo). */
final class SincronizarPrecioCatalogo
{
    public static function porcentajeIvaEfectivo(?float $porcentajeProducto, ?float $ivaEmpresa): float
    {
        $pct = (float) ($porcentajeProducto ?? 0);

        return $pct > 0 ? $pct : (float) ($ivaEmpresa ?? 0);
    }

  /**
   * @param  object{precio?: mixed, precio_sin_iva?: mixed, precio_con_iva?: mixed}  $producto
   */
    public static function aplicar(object $producto, float $porcentajeIva): void
    {
        $sin = round((float) ($producto->precio ?? 0), 6);
        $producto->precio = $sin;
        $producto->precio_sin_iva = $sin;
        $producto->precio_con_iva = $porcentajeIva > 0
            ? round($sin * (1 + $porcentajeIva / 100), 2)
            : $sin;
    }
}
