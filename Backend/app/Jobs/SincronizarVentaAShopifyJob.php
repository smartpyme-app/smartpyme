<?php

namespace App\Jobs;

use App\Models\Ventas\Venta;
use App\Services\ShopifyOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SincronizarVentaAShopifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $ventaId;

    public function __construct(int $ventaId)
    {
        $this->ventaId = $ventaId;
        $this->afterCommit = true;
    }

    public function handle(ShopifyOrderService $orderService): void
    {
        $venta = Venta::withoutGlobalScopes()->find($this->ventaId);

        if (!$venta) {
            Log::warning("SincronizarVentaAShopifyJob omitido: Venta #{$this->ventaId} no encontrada.");
            return;
        }

        $orderService->crearOrdenDesdeVenta($venta);
    }
}
