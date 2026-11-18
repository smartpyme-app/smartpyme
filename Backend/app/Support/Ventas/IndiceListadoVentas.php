<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Evita que el listado de ventas/cotizaciones con buscador recorra la tabla `ventas` de todas las empresas.
 *
 * Con buscador (OR entre id_cliente IN, correlativo LIKE, MATCH y etiquetas) + ORDER BY fecha DESC LIMIT n,
 * MariaDB elige `idx_ventas_fecha_estado` (fecha, estado: sin id_empresa) para no ordenar, y recorre las
 * ventas de todas las empresas por fecha hasta juntar n filas. Si el término coincide con pocas ventas
 * lee ~1.85M filas (4-6 s). Ignorando ese índice usa los de id_empresa: 0.05-0.3 s con el mismo
 * resultado y el mismo total del paginador (medido en producción el 2026-11-18, empresas 24, 187, 569 y 726).
 *
 * `idx_ventas_fecha_estado` no está en las migraciones (se creó a mano en producción). Un IGNORE INDEX
 * sobre un índice inexistente da ERROR 1176, por eso se verifica que exista antes de usarlo.
 */
final class IndiceListadoVentas
{
    private const INDICE = 'idx_ventas_fecha_estado';

    public static function evitarIndiceGlobalDeFecha(Builder $query): Builder
    {
        if (self::indiceExiste()) {
            $query->ignoreIndex(self::INDICE);
        }

        return $query;
    }

    private static function indiceExiste(): bool
    {
        try {
            return Cache::remember(
                'ventas:existe_indice:' . self::INDICE,
                now()->addHour(),
                fn () => Schema::hasIndex('ventas', self::INDICE)
            );
        } catch (\Throwable $e) {
            Log::warning('IndiceListadoVentas: no se pudo verificar el índice; se omite el hint', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
