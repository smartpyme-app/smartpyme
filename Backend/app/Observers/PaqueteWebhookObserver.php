<?php

namespace App\Observers;

use App\Models\Inventario\Paquete;
use App\Services\Webhooks\WebhookPaqueteVentaDispatcher;

class PaqueteWebhookObserver
{
    public function updated(Paquete $paquete): void
    {
        if (!$paquete->wasChanged('estado')) {
            return;
        }
        if ($paquete->estado !== 'Facturado') {
            return;
        }
        if (!$paquete->id_venta) {
            return;
        }

        WebhookPaqueteVentaDispatcher::dispatch((int) $paquete->id);
    }
}
