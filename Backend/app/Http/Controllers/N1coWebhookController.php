<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class N1coWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Verificar la firma del webhook
            $signature = $request->header('X-H4B-Hmac-Sha256');
            if (!$this->verifySignature($request->getContent(), $signature)) {
                Log::warning('N1co Webhook: Invalid signature received');
                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $payload = $request->all();

            // Validar estructura del payload
            $requiredFields = ['orderId', 'description', 'level', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($payload[$field])) {
                    Log::error('N1co Webhook: Missing required field', ['field' => $field]);
                    return response()->json(['error' => "Missing required field: {$field}"], 400);
                }
            }

            // Registrar la recepción del webhook
            Log::info('N1co Webhook received', [
                'type' => $payload['type'],
                'orderId' => $payload['orderId'],
                'level' => $payload['level']
            ]);

            // Manejar los diferentes tipos de eventos
            switch ($payload['type']) {
                case 'Created':
                    return $this->handleOrderCreated($payload);

                case 'SuccessPayment':
                    return $this->handleSuccessfulPayment($payload);

                case 'PaymentError':
                    return $this->handleFailedPayment($payload);

                case 'Cancelled':
                    return $this->handleCancelledPayment($payload);

                case 'Finalized':
                    return $this->handleOrderFinalized($payload);

                case 'SuccessReverse':
                    return $this->handleSuccessfulReverse($payload);

                case 'ReverseError':
                    return $this->handleReverseError($payload);

                case 'ThreeDSecureAuthSucceeded':
                    return $this->handle3DSAuthSuccess($payload);

                case 'ThreeDSecureAuthError':
                    return $this->handle3DSAuthError($payload);

                default:
                    Log::info('N1co Webhook: Unhandled event type', ['type' => $payload['type']]);
                    return response()->json(['status' => 'success', 'message' => 'Event type not handled']);
            }
        } catch (\Exception $e) {
            Log::error('N1co Webhook: Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function handleSuccessfulPayment($payload)
    {
        // Extraer información del metadata
        $metadata = $payload['metadata'];
        $userEmail = $metadata['BuyerEmail'];

        // Buscar o crear al usuario
        $user = User::where('email', $userEmail)->first();

        if ($user) {
            // Actualizar estado de suscripción
            Suscripcion::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'active',
                    'fecha_ultimo_pago' => now(),
                    'fecha_proximo_pago' => now()->addMonth(),
                    'id_pago' => $metadata['PaymentId']
                ]
            );

            // Activar funcionalidades premium
            $user->update(['is_premium' => true]);

            Log::info("Pago exitoso para usuario: {$userEmail}");
        }

        return response()->json(['status' => 'success']);
    }

    private function handleFailedPayment($payload)
    {
        $metadata = $payload['metadata'];
        $userEmail = $metadata['BuyerEmail'];

        $user = User::where('email', $userEmail)->first();

        if ($user) {
            $subscription = $user->subscription;
            if ($subscription) {
                $subscription->update([
                    'status' => 'payment_failed',
                    'failed_payment_date' => now()
                ]);
            }

            // Enviar notificación al usuario
            // $user->notify(new PaymentFailedNotification());
        }

        return response()->json(['status' => 'success']);
    }

    private function handleOrderCreated($payload)
    {
        Log::info('N1co Webhook: Order created', ['orderId' => $payload['orderId']]);
        // Implementar lógica para orden creada
        return response()->json(['status' => 'success']);
    }

    private function handleOrderFinalized($payload)
    {
        Log::info('N1co Webhook: Order finalized', ['orderId' => $payload['orderId']]);
        // Implementar lógica para orden finalizada
        return response()->json(['status' => 'success']);
    }

    private function handleSuccessfulReverse($payload)
    {
        Log::info('N1co Webhook: Payment reversed successfully', ['orderId' => $payload['orderId']]);
        // Implementar lógica para reversión exitosa
        return response()->json(['status' => 'success']);
    }

    private function handle3DSAuthSuccess($payload)
    {
        Log::info('N1co Webhook: 3DS Authentication successful', [
            'orderId' => $payload['orderId'],
            'authenticationId' => $payload['metadata']['authenticationId'] ?? null
        ]);
        // Implementar lógica para autenticación 3DS exitosa
        return response()->json(['status' => 'success']);
    }

    private function verifySignature($payload, $signature)
    {
        $calculatedSignature = hash_hmac(
            'sha256',
            $payload,
            config('services.nico.webhook_secret')
        );

        return hash_equals($calculatedSignature, $signature);
    }
}
