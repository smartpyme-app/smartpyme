<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\DB;

class FacturacionConsignaService
{
    /**
     * Procesar facturación consigna
     *
     * @param array $data
     * @return Venta
     */
    public function procesarConsigna(array $data): Venta
    {
        $venta = Venta::where('id', $data['id'])
            ->with('detalles')
            ->firstOrFail();

        if (round($venta->total, 2) > round($data['total'], 2)) {
            $this->crearConsigna($venta, $data);
            $this->actualizarDetallesVenta($venta, $data);
        }

        $venta->fecha = $data['fecha'];
        $venta->estado = 'Pagada';
        $venta->save();

        return $venta;
    }

    /**
     * Crear consigna cuando el total es menor
     *
     * @param Venta $venta
     * @param array $data
     * @return void
     */
    protected function crearConsigna(Venta $venta, array $data): void
    {
        $consigna = new Venta();
        $consigna->fill($data);
        $consigna->estado = 'Consigna';
        $consigna->sub_total = $venta->sub_total - $data['sub_total'];
        $consigna->total_costo = $venta->total_costo - $data['total_costo'];
        $consigna->total = $venta->total - $data['total'];
        $consigna->iva = $venta->iva - $data['iva'];
        $consigna->save();

        foreach ($data['detalles'] as $detalle) {
            $detalle_venta = $venta->detalles()->where('id', $detalle['id'])->first();
            if ($detalle_venta && $detalle_venta->cantidad > $detalle['cantidad']) {
                $detalle_consigna = new Detalle();
                $detalle_consigna->id_producto = $detalle['id_producto'];
                $detalle_consigna->precio = $detalle['precio'];
                $detalle_consigna->cantidad = $detalle_venta->cantidad - $detalle['cantidad'];
                $detalle_consigna->total = $detalle_consigna->precio * $detalle_consigna->cantidad;
                $detalle_consigna->id_venta = $consigna->id;
                $detalle_consigna->save();
            }
        }
    }

    /**
     * Actualizar detalles de la venta
     *
     * @param Venta $venta
     * @param array $data
     * @return void
     */
    protected function actualizarDetallesVenta(Venta $venta, array $data): void
    {
        $venta->detalles()->delete();

        foreach ($data['detalles'] as $detalle) {
            if ($detalle['cantidad'] > 0) {
                $det = new Detalle();
                $det->id_producto = $detalle['id_producto'];
                $det->cantidad = $detalle['cantidad'];
                $det->precio = $detalle['precio'];
                $det->total = $detalle['cantidad'] * $detalle['precio'];
                $det->descuento = 0;
                $det->id_venta = $venta->id;
                $det->save();
            }
        }

        $venta->total = $data['total'];
        $venta->iva = $data['iva'];
        $venta->sub_total = $data['sub_total'];
    }
}


