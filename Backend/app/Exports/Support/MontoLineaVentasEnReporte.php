<?php

namespace App\Exports\Support;

use App\Models\Admin\Empresa;

class MontoLineaVentasEnReporte
{
    public const CONFIG_KEY = 'reporte_ventas_categoria_vendedor_incluir_iva';

    public static function empresaIncluyeIva(?int $idEmpresa): bool
    {
        if (!$idEmpresa) {
            return false;
        }

        $empresa = Empresa::query()->find($idEmpresa);
        if (!$empresa) {
            return false;
        }

        return (bool) $empresa->getCustomConfigValue('configuraciones', self::CONFIG_KEY, false);
    }

    public static function sqlSumDetalleVenta(string $alias = 'dv', bool $incluirIva = false): string
    {
        if ($incluirIva) {
            return "SUM({$alias}.total + COALESCE({$alias}.iva, 0))";
        }

        return "SUM({$alias}.total)";
    }

    /**
     * ponytail: detalles_devolucion_venta no tiene columna iva; el total de línea es lo disponible.
     */
    public static function sqlSumDetalleDevolucionVenta(string $alias = 'ddv', bool $incluirIva = false): string
    {
        return "SUM(-ABS({$alias}.total))";
    }
}
