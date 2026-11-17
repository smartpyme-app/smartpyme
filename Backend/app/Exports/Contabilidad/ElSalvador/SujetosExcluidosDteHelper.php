<?php

namespace App\Exports\Contabilidad\ElSalvador;

/**
 * Extrae sello y código de generación del DTE (tipo 14) almacenado en compra/gasto.
 * El código de generación se entrega con el formato original (incluye guiones).
 */
final class SujetosExcluidosDteHelper
{
    public static function dteArray($registro): ?array
    {
        $dte = $registro->dte ?? null;
        if ($dte === null) {
            return null;
        }
        if (is_string($dte)) {
            $decoded = json_decode($dte, true);
            return is_array($decoded) ? $decoded : null;
        }

        return is_array($dte) ? $dte : null;
    }

    public static function codigoGeneracion($registro): string
    {
        $dte = self::dteArray($registro);
        $raw = data_get($dte, 'identificacion.codigoGeneracion', '');
        if ($raw === null || $raw === '') {
            return '';
        }

        return strtoupper((string) $raw);
    }

    public static function selloRecepcion($registro): string
    {
        if (!empty($registro->sello_mh)) {
            return (string) $registro->sello_mh;
        }

        return '';
    }

    /**
     * Eventos de retorno (18) sellados sobre compras de sujeto excluido: filas negativas para libro/anexo.
     * Toman sello/código del evento y la clasificación (operación, sector, costo) de la compra original.
     */
    public static function retornosCompra(object $request): \Illuminate\Support\Collection
    {
        return \App\Models\Compras\Devoluciones\Devolucion::with(['proveedor', 'compra'])
            ->where('enable', '1')
            ->where('tipo_dte', '18')
            ->whereNotNull('sello_mh')
            ->whereHas('compra', fn ($q) => $q->where('estado', '!=', 'Anulada')->where('tipo_documento', 'Sujeto excluido'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($retorno) {
                $resumen = $retorno->dte['resumen'] ?? [];
                $retorno->origen = 'retorno';
                $retorno->sub_total = 0;
                $retorno->total = -1 * (float) ($resumen['totalPagar'] ?? $retorno->total);
                $retorno->renta_retenida = -1 * (float) ($resumen['reteRenta'] ?? 0);
                $retorno->iva = 0;
                foreach (['tipo_operacion', 'tipo_clasificacion', 'tipo_sector', 'tipo_costo_gasto'] as $campo) {
                    $retorno->{$campo} = optional($retorno->compra)->{$campo};
                }

                return $retorno;
            });
    }

    /** Monto bruto de la operación (anexo F07), antes de retención de renta. */
    public static function montoOperacionBruto($registro): float
    {
        $subTotal = (float) ($registro->sub_total ?? 0);
        if ($subTotal > 0) {
            return round($subTotal, 2);
        }

        return round(
            (float) ($registro->total ?? 0) + (float) ($registro->renta_retenida ?? 0),
            2
        );
    }
}
