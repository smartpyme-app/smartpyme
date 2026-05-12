<?php

namespace App\Services\Webhooks;

use App\Jobs\SendPaqueteFacturadoWebhookJob;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Paquete;

class WebhookPaqueteVentaDispatcher
{
    public static function dispatch(int $paqueteId): void
    {
        $paquete = Paquete::withoutGlobalScopes()->find($paqueteId);
        if (!$paquete || $paquete->estado !== 'Facturado' || !$paquete->id_venta) {
            return;
        }

        $empresa = Empresa::query()->find($paquete->id_empresa);
        if (!$empresa || !$empresa->webhook_paquete_venta_enabled) {
            return;
        }
        if (trim((string) ($empresa->webhook_paquete_venta_url ?? '')) === '') {
            return;
        }

        SendPaqueteFacturadoWebhookJob::dispatch($paqueteId);
    }
}
