<?php

namespace App\Observers;

use App\Helpers\ShopifyHelper;
use App\Jobs\SincronizarPagoVentaAShopifyJob;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Venta;

class ShopifyVentaObserver
{
    /**
     * Se dispara cuando una venta es actualizada en SmartPyme.
     * Si la venta cambió su estado a 'Pagada' y tiene una orden enlazada en Shopify,
     * sincroniza la transacción de pago a Shopify.
     */
    public function updated(Venta $venta): void
    {
        // Solo actuar si el estado cambió a 'Pagada'
        if (!$venta->wasChanged('estado') || $venta->estado !== 'Pagada') {
            return;
        }

        // Solo si la venta está enlazada con una orden en Shopify
        if (empty($venta->referencia_shopify)) {
            return;
        }

        // Evitar bucle si el cambio de estado proviene de un webhook entrante de Shopify
        if (request()->is('*webhook/shopify*')) {
            return;
        }

        // Evitar procesar cotizaciones
        if ((int) ($venta->getAttribute('cotizacion') ?? 0) === 1) {
            return;
        }

        $empresa = Empresa::find($venta->id_empresa);
        if (!$empresa || !$empresa->shopify_sync_ventas) {
            return;
        }

        if ($empresa->shopify_status !== 'connected' || !$empresa->tieneCredencialesShopify()) {
            return;
        }

        ShopifyHelper::log("ShopifyVentaObserver::updated: Venta #{$venta->id} pasó a Pagada. Encolando sincronización de pago hacia Shopify.");

        SincronizarPagoVentaAShopifyJob::dispatch($venta->id);
    }
}
