<?php

namespace App\Services\Contabilidad\Partidas;

use Exception;
use Illuminate\Support\Collection;

final class ReglaLineasDebeIngresoVenta
{
    /**
     * @return list<array{nombre: string, monto: float}>
     *
     * @throws Exception
     */
    public static function lineasDebe(object $venta): array
    {
        $forma = trim((string) ($venta->forma_pago ?? ''));
        $total = round((float) ($venta->total ?? 0), 2);

        if ($forma !== 'Multiple') {
            if ($forma === '') {
                throw new Exception('La venta no tiene forma de pago asignada', 400);
            }

            return [['nombre' => $forma, 'monto' => $total]];
        }

        $metodos = collect($venta->metodos_de_pago ?? []);
        $lineas = [];
        foreach ($metodos as $metodo) {
            $monto = round((float) ($metodo->total ?? 0), 2);
            if ($monto <= 0) {
                continue;
            }
            $nombre = trim((string) ($metodo->nombre ?? ''));
            if ($nombre === '') {
                throw new Exception('La venta con pago múltiple tiene un método sin nombre', 400);
            }
            $lineas[] = ['nombre' => $nombre, 'monto' => $monto];
        }

        if ($lineas === []) {
            throw new Exception('La venta con pago múltiple no tiene métodos de pago registrados', 400);
        }

        $suma = round(array_sum(array_column($lineas, 'monto')), 2);
        // ponytail: tolerancia 2 céntimos por redondeo en UI; si falla, revisar propina/vuelto en venta_metodos_pago
        if (abs($suma - $total) > 0.02) {
            throw new Exception(
                'La suma de métodos de pago (' . $suma . ') no coincide con el total de la venta (' . $total . ')',
                400
            );
        }

        return $lineas;
    }

    /**
     * Nombres de formas de pago a precargar en catálogo (venta simple o desglose múltiple).
     *
     * @return list<string>
     */
    public static function nombresFormasPago(object $venta): array
    {
        $forma = trim((string) ($venta->forma_pago ?? ''));
        if ($forma !== 'Multiple') {
            return $forma !== '' ? [$forma] : [];
        }

        return collect($venta->metodos_de_pago ?? [])
            ->map(fn ($m) => trim((string) ($m->nombre ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $ingresos
     * @return list<string>
     */
    public static function nombresFormasPagoDesdeIngresos(iterable $ingresos): array
    {
        $nombres = [];
        foreach ($ingresos as $ingreso) {
            if (($ingreso->tipo ?? '') === 'abono') {
                $fp = trim((string) ($ingreso->forma_pago ?? ''));
                if ($fp !== '') {
                    $nombres[] = $fp;
                }
                continue;
            }
            foreach (self::nombresFormasPago($ingreso) as $nombre) {
                $nombres[] = $nombre;
            }
        }

        return array_values(array_unique($nombres));
    }
}
