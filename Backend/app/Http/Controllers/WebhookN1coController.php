<?php

namespace App\Http\Controllers;

use App\Models\Admin\Empresa;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookN1coController extends Controller
{
    public function handle(Request $request)
    {
        try {

            Log::debug('N1co Webhook: Configuration', [
                'secret_configured' => !empty(config('services.nico.webhook_secret')),
                'secret_length' => strlen(config('services.nico.webhook_secret'))
            ]);
            // Log inicial de la solicitud
            Log::info('N1co Webhook: Request received', [
                'headers' => $request->headers->all(),
                'content' => $request->getContent(),
                'raw_payload' => $request->all()
            ]);

            // Verificar la firma del webhook
            $signature = $request->header('X-H4B-Hmac-Sha256');
            if (!$signature) {
                Log::error('N1co Webhook: No signature provided in headers');
                return response()->json(['error' => 'No signature provided'], 400);
            }

            if (!$this->verifySignature($request->getContent(), $signature)) {
                Log::warning('N1co Webhook: Invalid signature', [
                    'received_signature' => $signature,
                    'content_length' => strlen($request->getContent())
                ]);
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
            Log::info('N1co Webhook: Processing event', [
                'type' => $payload['type'],
                'orderId' => $payload['orderId'],
                'level' => $payload['level'],
                'description' => $payload['description']
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

                case 'Requires3ds':
                    return $this->handleRequires3ds($payload);
    

                case 'ThreeDSecureAuthSucceeded':
                    return $this->handle3DSAuthSuccess($payload);

                case 'ThreeDSecureAuthFailed':
                    return $this->handle3DSAuthFailed($payload);

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
        try {
            $metadata = $payload['metadata'];

            Log::info('Procesando pago exitoso', [
                'payload' => $payload
            ]);

            // Buscar la orden usando el checkoutNote
            $ordenPago = OrdenPago::where('id_orden', $payload['orderReference'])
                // ->where('id_orden_n1co', $payload['orderId'])
                ->first();

            if ($ordenPago) {
                Log::info('Orden de pago encontrada en handleSuccessfulPayment', [
                    'ordenPago' => $ordenPago
                ]);

                $ordenPago->update([
                    'estado' => config('constants.ESTADO_ORDEN_PAGO_COMPLETADO'),
                    'item_id' => $metadata['orderDetail'][0]['itemId'] ?? null,
                    'fecha_transaccion' => Carbon::parse($metadata['TransactionDate'])->format('Y-m-d H:i:s'),
                    'payment_id' => $metadata['PaymentId'],
                    'charge_id' => $metadata['ChargeId']
                ]);

                // Buscar el usuario
                $user = User::find($ordenPago->id_usuario);
                $plan = Plan::find($ordenPago->id_plan);
                $tipoPlan = $plan->duracion_dias == 30 ? 'Mensual' : $plan->tipo_plan;

                if ($user) {
                    // Actualizar o crear suscripción
                    Suscripcion::updateOrCreate(
                        ['usuario_id' => $user->id],
                        [
                            'plan_id' => $plan->id,
                            'tipo_plan' => $tipoPlan,
                            'empresa_id' => $user->id_empresa,
                            'metodo_pago' => config('constants.METODO_PAGO_N1CO'),
                            'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                            'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_COMPLETADO'),
                            'fecha_ultimo_pago' => now(),
                            'fecha_proximo_pago' => now()->addMonth(),
                            'id_pago' => $metadata['PaymentId'],
                            'id_orden' => $payload['orderId'],
                            'monto' => $ordenPago->monto
                        ]
                    );

                    $empresa = Empresa::find($user->id_empresa);
                    Log::info('Empresa encontrada', [
                        'empresa' => $empresa
                    ]);

                    $empresa->update([
                        'metodo_pago' => config('constants.METODO_PAGO_N1CO')
                    ]);

                    Log::info('Empresa actualizada', [
                        'empresa' => $empresa->metodo_pago
                    ]);

                    Log::info('Suscripción actualizada', [
                        'user_id' => $user->id,
                        'order_id' => $payload['orderId']
                    ]);
                } else {
                    Log::error('Usuario no encontrado', [
                        'user_id' => $ordenPago->id_usuario,
                    ]);
                }
            } else {
                Log::warning('Orden de pago no encontrada', [
                    'payload' => $payload
                ]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error procesando pago exitoso', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);

            return response()->json(['error' => 'Error procesando pago'], 500);
        }
    }

    private function handleFailedPayment($payload)
    {
        $metadata = $payload['metadata'];
        $userEmail = $metadata['BuyerEmail'];

        $user = User::where('email', $userEmail)->first();

        if ($user) {
            $subscription = $user->suscripcion;
            if ($subscription) {
                $subscription->update([
                    'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO'),
                    'fecha_ultimo_pago' => now()
                ]);
            }

            $empresa = Empresa::find($user->id_empresa);
            $empresa->update([
                'metodo_pago' => config('constants.METODO_PAGO_N1CO')
            ]);

            // Enviar notificación al usuario
            // $user->notify(new PaymentFailedNotification());
        }

        return response()->json(['status' => 'success']);
    }

    private function handleOrderCreated($payload)
    {
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

    private function handleRequires3ds($payload)
    {
        Log::info('N1co Webhook: Payment requires 3DS', ['orderId' => $payload['orderId']]);
     
        return response()->json(['status' => 'success']);
    }

    private function handle3DSAuthSuccess($payload)
    {
        try {
            Log::info('N1co Webhook: 3DS Authentication successful', [
                'orderId' => $payload['orderId'],
                'orderReference' => $payload['orderReference'],
                'authenticationId' => $payload['metadata']['authenticationId'] ?? null
            ]);

            // Buscar la orden de pago por el orderReference
            $ordenPago = OrdenPago::where('id_orden', $payload['orderReference'])
                ->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE'))
                ->first();

            $ordenPago->update([
                'id_orden_n1co' => $payload['orderId'],
                'updated_at' => now()
            ]);

            if (!$ordenPago) {
                Log::warning('N1co Webhook: Orden de pago no encontrada o no está en estado pendiente de autenticación', [
                    'orderReference' => $payload['orderReference']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Orden no encontrada o no está en estado pendiente de autenticación'
                ], 404);
            }

            // Actualizar el estado de autenticación
            $ordenPago->update([
                'estado' => config('constants.ESTADO_ORDEN_AUTENTICACION_EXITOSA'),
                'authentication_id' => $payload['metadata']['authenticationId'],
            ]);

            Log::info('N1co Webhook: Estado de autenticación 3DS actualizado exitosamente', [
                'orderReference' => $payload['orderReference'],
                'newStatus' => config('constants.ESTADO_ORDEN_AUTENTICACION_EXITOSA')
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de autenticación actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('N1co Webhook: Error procesando autenticación 3DS exitosa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error procesando autenticación 3DS'
            ], 500);
        }
    }

    private function handle3DSAuthFailed($payload)
    {
        try {
            Log::info('N1co Webhook: 3DS Authentication failed', [
                'orderId' => $payload['orderId'],
                'orderReference' => $payload['orderReference'],
                'authenticationId' => $payload['metadata']['authenticationId'] ?? null
            ]);

            // Buscar la orden de pago por el orderReference
            $ordenPago = OrdenPago::where('id_orden', $payload['orderReference'])
                ->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE'))
                ->first();

            $ordenPago->update([
                'id_orden_n1co' => $payload['orderId'],
                'estado' => config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA'),
                'updated_at' => now()
            ]);

            $usuario = User::find($ordenPago->id_usuario);
            $empresa = Empresa::find($usuario->id_empresa);
            $empresa->update([
                'metodo_pago' => config('constants.METODO_PAGO_N1CO')
            ]);

            if (!$ordenPago) {
                Log::warning('N1co Webhook: Orden de pago no encontrada o no está en estado pendiente de autenticación', [
                    'orderReference' => $payload['orderReference']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Orden no encontrada o no está en estado pendiente de autenticación'
                ], 404);
            }

            // Actualizar el estado de autenticación
            $ordenPago->update([
                'estado' => config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA'),
                'authentication_id' => $payload['metadata']['authenticationId'],
            ]);


            $suscripcion = Suscripcion::where('usuario_id', $usuario->id)->first();

            $suscripcion->update([
                'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO'),
                'fecha_ultimo_pago' => now(),
                'estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE')
            ]);

            Log::info('N1co Webhook: Suscripcion actualizada fail', [
                'suscripcion' => $suscripcion
            ]);

            Log::info('N1co Webhook: Estado de autenticación 3DS actualizado a fallido', [
                'orderReference' => $payload['orderReference'],
                'newStatus' => config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA')
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de autenticación actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('N1co Webhook: Error procesando autenticación 3DS fallida', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error procesando autenticación 3DS'
            ], 500);
        }
    }

    private function handle3DSAuthError($payload)
    {
        try {
            Log::info('N1co Webhook: 3DS Authentication error', [
                'orderId' => $payload['orderId'],
                'orderReference' => $payload['orderReference'],
                'reason' => $payload['metadata']['reason'] ?? 'No reason provided',
                'description' => $payload['metadata']['description'] ?? 'No description provided'
            ]);

            $ordenPago = OrdenPago::where('id_orden', $payload['orderReference'])
                ->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE'))
                ->first();

            if (!$ordenPago) {
                Log::warning('N1co Webhook: Orden de pago no encontrada para error 3DS', [
                    'orderReference' => $payload['orderReference']
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Orden no encontrada'
                ], 404);
            }

            // Actualizar el estado a fallido
            $ordenPago->updateStatusAuthentication3DS(
                $payload['metadata']['authenticationId'],
                null,
                config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA')
            );

            Log::info('N1co Webhook: Estado de autenticación 3DS actualizado a fallido', [
                'orderReference' => $payload['orderReference'],
                'newStatus' => config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA')
            ]);

            
            $suscripcion = Suscripcion::where('id_orden', $payload['orderReference'])->first();

            Log::info('N1co Webhook: Suscripcion encontrada', [
                'suscripcion' => $suscripcion
            ]);

            $suscripcion->update([
                'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO'),
                'fecha_ultimo_pago' => now()
            ]);


            return response()->json([
                'status' => 'success',
                'message' => 'Estado de autenticación actualizado a fallido'
            ]);
        } catch (\Exception $e) {
            Log::error('N1co Webhook: Error procesando fallo de autenticación 3DS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error procesando fallo de autenticación 3DS'
            ], 500);
        }
    }


    private function verifySignature($payload, $signature)
    {
        if (empty($signature)) {
            Log::error('N1co Webhook: Empty signature provided');
            return false;
        }

        $secret = config('services.nico.webhook_secret');

        if (empty($secret)) {
            Log::error('N1co Webhook: Webhook secret not configured');
            return false;
        }

        // Usar el contenido exacto sin ninguna transformación
        $calculatedSignature = base64_encode(
            hash_hmac(
                'sha256',
                $payload,  // payload sin transformar
                $secret,   // secret sin transformar
                true      // obtener salida binaria
            )
        );

        // Log para debugging
        Log::debug('N1co Webhook: Signature details', [
            'received_signature' => $signature,
            'calculated_signature' => $calculatedSignature,
            'payload_sample' => substr($payload, 0, 100) . '...' // solo para debug
        ]);

        return $signature === $calculatedSignature;
    }

    public function createPaymentLink($plan, $cliente)
    {
        try {
            Log::info('Creando enlace de pago N1co', [
                'plan' => $plan->nombre,
                'cliente' => $cliente->email
            ]);

            $data = [
                "orderName" => "SmartPyme " . $plan->nombre,
                "orderDescription" => "Suscripción al plan " . strtolower($plan->nombre),
                "amount" => $plan->precio,
                "successUrl" => config('app.url') . "/payment/success",
                "cancelUrl" => config('app.url') . "/payment/cancel",
                "metadata" => [
                    [
                        "name" => "clientId",
                        "value" => $cliente->id
                    ],
                    [
                        "name" => "planId",
                        "value" => $plan->id
                    ],
                    [
                        "name" => "empresaId",
                        "value" => $cliente->empresa_id
                    ]
                ],
                "expirationMinutes" => 60 // 1 hora de expiración
            ];

            // Hacer la petición a N1co
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.nico.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.nico.base_url') . '/paymentlink/checkout', $data);

            if ($response->successful()) {
                $result = $response->json();

                // Actualizar el plan con la información del enlace
                $plan->update([
                    'enlace_n1co' => $result['paymentLinkUrl'],
                    'id_enlace_pago_n1co' => $result['orderId'],
                    'n1co_metadata' => [
                        'orderCode' => $result['orderCode'],
                        'createdAt' => now()
                    ]
                ]);

                Log::info('Enlace de pago creado exitosamente', [
                    'orderId' => $result['orderId'],
                    'url' => $result['paymentLinkUrl']
                ]);

                return [
                    'success' => true,
                    'paymentUrl' => $result['paymentLinkUrl'],
                    'orderId' => $result['orderId']
                ];
            }

            Log::error('Error al crear enlace de pago', [
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear enlace de pago',
                'details' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Error al crear enlace de pago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear enlace de pago',
                'message' => $e->getMessage()
            ];
        }
    }
}
