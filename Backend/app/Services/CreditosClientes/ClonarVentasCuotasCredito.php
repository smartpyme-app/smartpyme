<?php

namespace App\Services\CreditosClientes;

use App\Models\CreditosClientes\CreditoContrato;
use App\Models\CreditosClientes\CreditoCuota;
use App\Models\Ventas\Venta;

class ClonarVentasCuotasCredito
{
    private const VENTA_MONTO_FIELDS = [
        'iva',
        'total_costo',
        'descuento',
        'sub_total',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total',
        'propina',
        'iva_percibido',
        'iva_retenido',
        'renta_retenida',
        'equivalent_total',
        'equivalent_iva',
    ];

    private const DETALLE_MONTO_FIELDS = [
        'precio',
        'precio_sin_iva',
        'precio_con_iva',
        'descuento',
        'sub_total',
        'no_sujeta',
        'exenta',
        'gravada',
        'cuenta_a_terceros',
        'total',
        'iva',
    ];

    public function clonarRestantes(Venta $origen, CreditoContrato $contrato): void
    {
        $origen->loadMissing(['detalles.composiciones', 'impuestos']);
        $contrato->loadMissing('cuotas');

        foreach ($contrato->cuotas as $cuota) {
            if ((int) $cuota->numero <= 1 || $cuota->id_venta) {
                continue;
            }
            $clone = $this->clonar($origen, $cuota);
            $cuota->id_venta = $clone->id;
            $cuota->save();
        }
    }

    private function clonar(Venta $origen, CreditoCuota $cuota): Venta
    {
        $fecha = $cuota->fecha_vencimiento
            ? $cuota->fecha_vencimiento->toDateString()
            : null;

        $clone = $origen->replicate([
            'correlativo',
            'numero_control',
            'codigo_generacion',
            'sello_mh',
            'dte',
            'dte_invalidacion',
            'tipo_dte',
            'codigo_generacion_remplazo',
            'dte_s3_key',
            'dte_invalidacion_s3_key',
            'dte_migrated_at',
            'dte_invalidacion_migrated_at',
            'id_caja',
            'id_corte',
        ]);
        $clone->fecha = $fecha;
        $clone->fecha_pago = $fecha;
        $clone->estado = 'Pendiente';
        $clone->monto_pago = 0;
        $clone->cambio = 0;
        $clone->puntos_ganados = 0;
        $clone->puntos_canjeados = 0;
        $clone->descuento_puntos = 0;

        $this->escalarMontos($clone, (float) $origen->total, (float) $cuota->monto);
        $clone->save();

        foreach ($origen->detalles as $detalle) {
            $copia = $detalle->replicate();
            $copia->id_venta = $clone->id;
            $this->escalarCampos($copia, self::DETALLE_MONTO_FIELDS, (float) $origen->total, (float) $cuota->monto);
            $copia->save();

            foreach ($detalle->composiciones as $compuesto) {
                $copiaCompuesto = $compuesto->replicate();
                $copiaCompuesto->id_detalle = $copia->id;
                $copiaCompuesto->save();
            }
        }

        foreach ($origen->impuestos as $impuesto) {
            $copia = $impuesto->replicate();
            $copia->id_venta = $clone->id;
            $copia->monto = $this->escalarValor($impuesto->monto, (float) $origen->total, (float) $cuota->monto);
            $copia->save();
        }

        return $clone;
    }

    private function escalarMontos(Venta $venta, float $origenTotal, float $montoCuota): void
    {
        $this->escalarCampos($venta, self::VENTA_MONTO_FIELDS, $origenTotal, $montoCuota);
        $venta->total = round($montoCuota, 2);
    }

    private function escalarCampos(object $modelo, array $campos, float $origenTotal, float $montoCuota): void
    {
        foreach ($campos as $campo) {
            $modelo->{$campo} = $this->escalarValor($modelo->{$campo} ?? 0, $origenTotal, $montoCuota);
        }
    }

    private function escalarValor($valor, float $origenTotal, float $montoCuota): float
    {
        if ($origenTotal <= 0 || abs($origenTotal - $montoCuota) < 0.001) {
            return round((float) $valor, 2);
        }

        return round(((float) $valor) * ($montoCuota / $origenTotal), 2);
    }
}
