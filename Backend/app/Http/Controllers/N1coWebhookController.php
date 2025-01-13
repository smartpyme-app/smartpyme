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
        // Verificar la firma del webhook
        $signature = $request->header('X-H4B-Hmac-Sha256');
        if (!$this->verifySignature($request->getContent(), $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = $request->all();

        switch ($payload['type']) {
            case 'SuccessPayment':
                return $this->handleSuccessfulPayment($payload);
            
            case 'PaymentError':
                return $this->handleFailedPayment($payload);
                
            case 'Cancelled':
                return $this->handleCancelledPayment($payload);
        }

        return response()->json(['status' => 'success']);
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

    private function verifySignature($payload, $signature)
    {
        $calculatedSignature = hash_hmac(
            'sha256', 
            $payload, 
            config('services.nico.webhook_secret'), 
            true
        );
        
        return base64_encode($calculatedSignature) === $signature;
    }
}
