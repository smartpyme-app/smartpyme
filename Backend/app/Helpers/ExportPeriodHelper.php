<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class ExportPeriodHelper
{
    public const MAX_DIAS_DETALLES = 31;

    public const MAX_DIAS_VENTAS_TOTALES = 90;

    public const MAX_DIAS_GENERAL = 90;

    /**
     * Valida inicio/fin para descargas manuales (ventas, compras, gastos, inventario, etc.).
     * Lanza abort(422) si el período es inválido o supera el máximo.
     */
    public static function assertValidPeriod(Request $request, int $maxDias): void
    {
        $inicio = trim((string) ($request->input('inicio') ?? ''));
        $fin = trim((string) ($request->input('fin') ?? ''));

        if ($inicio === '' || $fin === '') {
            abort(422, self::mensajeFechasRequeridas($maxDias));
        }

        if (!self::isValidDate($inicio) || !self::isValidDate($fin)) {
            abort(422, 'Las fechas de inicio y fin deben tener formato válido (YYYY-MM-DD).');
        }

        if ($inicio > $fin) {
            abort(422, 'La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        $dias = self::diasEntre($inicio, $fin);
        if ($dias > $maxDias) {
            abort(422, self::mensajeRangoExcedido($maxDias));
        }
    }

    public static function diasEntre(string $inicio, string $fin): int
    {
        $s = \DateTime::createFromFormat('Y-m-d', $inicio);
        $e = \DateTime::createFromFormat('Y-m-d', $fin);

        if (!$s || !$e || $s > $e) {
            return -1;
        }

        return (int) $s->diff($e)->days;
    }

    public static function mensajeRangoExcedido(int $maxDias): string
    {
        return "El rango no puede superar {$maxDias} días. Para históricos más amplios, configure un reporte en Reportes automáticos.";
    }

    public static function mensajeFechasRequeridas(int $maxDias): string
    {
        return "Debe indicar fecha de inicio y fin (máximo {$maxDias} días entre ambas). Para históricos, use Reportes automáticos.";
    }

    private static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }
}
