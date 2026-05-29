<?php

namespace App\Jobs;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPaqueteFacturadoWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $paqueteId
    ) {
    }

    public function handle(): void
    {
        $paquete = Paquete::withoutGlobalScopes()->find($this->paqueteId);
        if (!$paquete || $paquete->estado !== 'Facturado' || !$paquete->id_venta) {
            return;
        }

        $empresa = Empresa::query()->find($paquete->id_empresa);
        if (!$empresa || !$empresa->webhook_paquete_venta_enabled) {
            return;
        }

        $url = trim((string) ($empresa->webhook_paquete_venta_url ?? ''));
        if ($url === '') {
            return;
        }

        $venta = Venta::withoutGlobalScopes()
            ->where('id', $paquete->id_venta)
            ->where('id_empresa', $paquete->id_empresa)
            ->first();

        $payload = [
            'event' => 'paquete.facturado',
            'id_empresa' => (int) $paquete->id_empresa,
            'paquete' => [
                'id' => (int) $paquete->id,
                'wr' => $paquete->wr,
                'estado' => $paquete->estado,
                'num_guia' => $paquete->num_guia,
                'num_seguimiento' => $paquete->num_seguimiento,
                'id_venta' => (int) $paquete->id_venta,
                'id_venta_detalle' => $paquete->id_venta_detalle ? (int) $paquete->id_venta_detalle : null,
            ],
            'venta' => $venta ? [
                'id' => (int) $venta->id,
                'correlativo' => $venta->correlativo,
                'estado' => $venta->estado,
                'fecha' => $venta->fecha,
                'total' => $venta->total !== null ? (float) $venta->total : null,
            ] : null,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SmartPYME-Webhook/1.0',
        ];

        $secret = $empresa->webhook_paquete_venta_secret;
        if ($secret !== null && trim((string) $secret) !== '') {
            $headers['X-SmartPyme-Signature'] = 'sha256='.hash_hmac('sha256', $body, (string) $secret);
        }

        $bearer = $empresa->webhook_paquete_venta_bearer_token;
        if ($bearer !== null && trim((string) $bearer) !== '') {
            $headers['Authorization'] = 'Bearer '.trim((string) $bearer);
        }

        $timeout = (int) config('webhooks.paquete_venta_timeout', 15);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);

            if (!$response->successful()) {
                Log::warning('Webhook paquete facturado: respuesta no exitosa', [
                    'paquete_id' => $this->paqueteId,
                    'empresa_id' => $empresa->id,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                $response->throw();
            }

        } catch (\Throwable $e) {
            Log::error('Webhook paquete facturado falló', [
                'paquete_id' => $this->paqueteId,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
