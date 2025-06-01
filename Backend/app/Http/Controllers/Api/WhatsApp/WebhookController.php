<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Verificación del webhook (GET)
     */
    public function verify(Request $request)
    {
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        // Token de verificación configurado en .env
        $verifyToken = config('services.whatsapp.verify_token', 'smartpyme_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verificado correctamente');
            return response($challenge, 200);
        }

        Log::warning('Falló la verificación del webhook WhatsApp', [
            'mode' => $mode,
            'token' => $token
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Procesamiento de mensajes (POST)
     */
    public function handle(Request $request)
    {
        try {
            if (config('app.env') === 'production') {
                Log::info('WhatsApp webhook recibido', ['payload' => $request->all()]);
            } else {
                Log::info('📱 WhatsApp webhook recibido [DEV]', [
                    'from' => $request->input('entry.0.changes.0.value.messages.0.from'),
                    'message' => $request->input('entry.0.changes.0.value.messages.0.text.body'),
                    'timestamp' => $request->input('entry.0.changes.0.value.messages.0.timestamp')
                ]);
            }

            if (!$this->isValidWebhook($request)) {
                Log::warning('Webhook inválido recibido');
                return response()->json(['status' => 'invalid_webhook'], 400);
            }
            $result = $this->whatsAppService->processIncomingMessage($request->all());

            if ($result['success']) {
                Log::info('✅ Mensaje procesado exitosamente', [
                    'success' => $result['success'],
                    'has_response' => isset($result['response'])
                ]);
            } else {
                Log::error('❌ Error procesando mensaje', [
                    'error' => $result['error'] ?? 'Error desconocido'
                ]);
            }

            return response()->json(['status' => 'received'], 200);

        } catch (Exception $e) {
            Log::error('Error en webhook WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function isValidWebhook(Request $request): bool
    {
        $data = $request->all();

        return isset($data['entry']) 
            && is_array($data['entry']) 
            && count($data['entry']) > 0
            && isset($data['entry'][0]['changes'])
            && is_array($data['entry'][0]['changes'])
            && count($data['entry'][0]['changes']) > 0;
    }
}