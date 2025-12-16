<?php

namespace App\Http\Controllers;

use App\Models\Admin\Empresa;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use App\Notifications\SuscripcionExitosa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\WebhookN1coRequest;

class WebhookN1coController extends Controller
{
    public function handle(WebhookN1coRequest $request)
    {
        try {
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

            // Buscar la orden usando el orderReference
            $ordenPago = OrdenPago::where('id_orden', $payload['orderReference'])
                ->first();

            if (!$ordenPago) {
                Log::channel('payments_error')->warning('Orden de pago no encontrada', [
                    'payload' => $payload
                ]);
                return response()->json(['status' => 'success']);
            }

            // Verificar si ya se procesó este pago (evita procesamiento duplicado)
            if (
                $ordenPago->estado === config('constants.ESTADO_ORDEN_PAGO_COMPLETADO') &&
                $ordenPago->payment_id === $metadata['PaymentId'] &&
                $ordenPago->charge_id === $metadata['ChargeId']
            ) {
                Log::channel('payments_success')->info('Pago ya procesado anteriormente, ignorando evento duplicado', [
                    'orden_id' => $ordenPago->id_orden,
                    'payment_id' => $metadata['PaymentId']
                ]);
                return response()->json(['status' => 'success']);
            }

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

            if ($user) {
                // Buscar si ya existe una suscripción
                $empresa = Empresa::find($user->id_empresa);
                $suscripcionExistente = Suscripcion::where('empresa_id', $empresa->id)->first();
                $esNuevaSuscripcion = !$suscripcionExistente ||
                    !in_array($suscripcionExistente->estado, [
                        config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                        config('constants.ESTADO_SUSCRIPCION_PENDIENTE'),
                        config('constants.ESTADO_SUSCRIPCION_VENCIDO')
                    ]);

                // Obtener el tipo_plan de la empresa o suscripción existente
                // Prioridad: empresa->tipo_plan > empresa->frecuencia_pago > suscripción->tipo_plan > plan->tipo_plan
                $tipoPlan = $empresa->tipo_plan 
                    ?? $empresa->frecuencia_pago 
                    ?? ($suscripcionExistente ? $suscripcionExistente->tipo_plan : null)
                    ?? $this->determinarTipoPlanPorDuracion($plan->duracion_dias);

                Log::channel('payments_success')->info('Tipo de plan determinado', [
                    'tipo_plan' => $tipoPlan,
                    'empresa_tipo_plan' => $empresa->tipo_plan,
                    'empresa_frecuencia_pago' => $empresa->frecuencia_pago,
                    'suscripcion_tipo_plan' => $suscripcionExistente ? $suscripcionExistente->tipo_plan : null,
                    'plan_duracion_dias' => $plan->duracion_dias
                            ]);

                // Calcular la nueva fecha de próximo pago según el tipo_plan
                $fechaProximoPago = $this->calcularFechaProximoPagoWebhook($tipoPlan, $suscripcionExistente);


                // Actualizar o crear suscripción
                $suscripcion = Suscripcion::updateOrCreate(
                    ['empresa_id' => $user->id_empresa],
                    [
                        'plan_id' => $plan->id,
                        'tipo_plan' => $tipoPlan,
                        'empresa_id' => $user->id_empresa,
                        'usuario_id' => $user->id,
                        'metodo_pago' => config('constants.METODO_PAGO_N1CO'),
                        'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                        'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_COMPLETADO'),
                        'fecha_ultimo_pago' => now(),
                        'fecha_proximo_pago' => $fechaProximoPago,
                        'id_pago' => $metadata['PaymentId'],
                        'id_orden' => $payload['orderId'],
                        'monto' => $ordenPago->monto,
                        'created_at' => now(),
                        // 'updated_at' => now()
                    ]
                );

                $empresa = Empresa::find($user->id_empresa);
                Log::channel('payments_success')->info('Empresa encontrada', [
                    'empresa' => $empresa
                ]);

                $empresa->update([
                    'metodo_pago' => config('constants.METODO_PAGO_N1CO')
                ]);

                if (method_exists($this, 'enviarCorreoSuscripcion')) {
                    $this->enviarCorreoSuscripcion($user, $suscripcion, $empresa);
                }

                if (method_exists($this, 'enviarNotificacionPagoAdmin')) {
                    $this->enviarNotificacionPagoAdmin($user, $suscripcion, $empresa, $ordenPago, $esNuevaSuscripcion);
                }

                // Si este método existe, lo llamamos
                if (method_exists($ordenPago, 'generarVenta')) {
                    try {
                        $ordenPago->generarVenta();
                    } catch (\Exception $e) {
                        Log::channel('payments_error')->error('Error al generar venta', [
                            'error' => $e->getMessage(),
                            'ordenPago' => $ordenPago->id_orden
                        ]);
                        // Continuamos con el proceso a pesar del error
                    }
                }
            } else {
                Log::channel('payments_error')->error('Usuario no encontrado', [
                    'user_id' => $ordenPago->id_usuario,
                ]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error procesando pago exitoso', [
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
        Log::channel('payments_success')->info('N1co Webhook: Order finalized', ['orderId' => $payload['orderId']]);
        // Implementar lógica para orden finalizada
        return response()->json(['status' => 'success']);
    }

    private function handleSuccessfulReverse($payload)
    {
        Log::channel('payments_success')->info('N1co Webhook: Payment reversed successfully', ['orderId' => $payload['orderId']]);
        // Implementar lógica para reversión exitosa
        return response()->json(['status' => 'success']);
    }

    private function handleRequires3ds($payload)
    {
        Log::channel('payments_success')->info('N1co Webhook: Payment requires 3DS', ['orderId' => $payload['orderId']]);

        return response()->json(['status' => 'success']);
    }

    private function handle3DSAuthSuccess($payload)
    {
        try {
            Log::channel('payments_success')->info('N1co Webhook: 3DS Authentication successful', [
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
                Log::channel('payments_error')->warning('N1co Webhook: Orden de pago no encontrada o no está en estado pendiente de autenticación', [
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
                'id_autorizacion_3ds' => $payload['metadata']['authenticationId'],
            ]);

            Log::channel('payments_success')->info('N1co Webhook: Estado de autenticación 3DS actualizado exitosamente', [
                'orderReference' => $payload['orderReference'],
                'newStatus' => config('constants.ESTADO_ORDEN_AUTENTICACION_EXITOSA')
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de autenticación actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('N1co Webhook: Error procesando autenticación 3DS exitosa', [
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
            Log::channel('payments_error')->info('N1co Webhook: 3DS Authentication failed', [
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
                Log::channel('payments_error')->warning('N1co Webhook: Orden de pago no encontrada o no está en estado pendiente de autenticación', [
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
                'id_autorizacion_3ds' => $payload['metadata']['authenticationId'],
            ]);

            $empresa = Empresa::find($usuario->id_empresa);
            $suscripcion = Suscripcion::where('empresa_id', $empresa->id)->first();

            $suscripcion->update([
                'estado_ultimo_pago' => config('constants.ESTADO_ORDEN_PAGO_FALLIDO'),
                'fecha_ultimo_pago' => now(),
                'estado' => config('constants.ESTADO_SUSCRIPCION_PENDIENTE')
            ]);

            Log::channel('payments_error')->info('N1co Webhook: Suscripcion actualizada fail', [
                'suscripcion' => $suscripcion
            ]);

            Log::channel('payments_error')->info('N1co Webhook: Estado de autenticación 3DS actualizado a fallido', [
                'orderReference' => $payload['orderReference'],
                'newStatus' => config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA')
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Estado de autenticación actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('N1co Webhook: Error procesando autenticación 3DS fallida', [
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
            Log::channel('payments_error')->info('N1co Webhook: 3DS Authentication error', [
                'orderId' => $payload['orderId'],
                'orderReference' => $payload['orderReference'],
                'reason' => $payload['metadata']['reason'] ?? 'No reason provided',
                'description' => $payload['metadata']['description'] ?? 'No description provided'
            ]);

            $ordenPago = OrdenPago::where('id_orden', $payload['orderReference'])
                ->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE'))
                ->first();

            if (!$ordenPago) {
                Log::channel('payments_error')->warning('N1co Webhook: Orden de pago no encontrada para error 3DS', [
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

            Log::channel('payments_error')->info('N1co Webhook: Estado de autenticación 3DS actualizado a fallido', [
                'orderReference' => $payload['orderReference'],
                'newStatus' => config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA')
            ]);


            $suscripcion = Suscripcion::where('id_orden', $payload['orderReference'])->first();

            Log::channel('payments_error')->info('N1co Webhook: Suscripcion encontrada', [
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
            Log::channel('payments_error')->error('N1co Webhook: Error procesando fallo de autenticación 3DS', [
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
            Log::channel('payments_error')->error('N1co Webhook: Empty signature provided');
            return false;
        }

        $secret = config('services.nico.webhook_secret');

        if (empty($secret)) {
            Log::channel('payments_error')->error('N1co Webhook: Webhook secret not configured');
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
        Log::channel('payments_error')->debug('N1co Webhook: Signature details', [
            'received_signature' => $signature,
            'calculated_signature' => $calculatedSignature,
            'payload_sample' => substr($payload, 0, 100) . '...' // solo para debug
        ]);

        return $signature === $calculatedSignature;
    }

    public function createPaymentLink($plan, $cliente)
    {
        try {
            Log::channel('payments_success')->info('Creando enlace de pago N1co', [
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

                Log::channel('payments_success')->info('Enlace de pago creado exitosamente', [
                    'orderId' => $result['orderId'],
                    'url' => $result['paymentLinkUrl']
                ]);

                return [
                    'success' => true,
                    'paymentUrl' => $result['paymentLinkUrl'],
                    'orderId' => $result['orderId']
                ];
            }

            Log::channel('payments_error')->error('Error al crear enlace de pago', [
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear enlace de pago',
                'details' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error al crear enlace de pago', [
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

    private function enviarCorreoSuscripcion($user, $suscripcion, $empresa)
    {
        // $emailDestino = app()->environment('production') ? $user->email : 'jose.e@smartpyme.sv';
        $emailDestino = $user->email;

        try {
            Mail::send('mails.suscripcion-exitosa', [
                'suscripcion' => $suscripcion,
                'empresa' => $empresa,
                'usuario' => $user
            ], function ($m) use ($emailDestino, $empresa) {
                // $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                $m->from('noreply@smartpyme.sv', 'SmartPyme')
                    ->to($emailDestino)
                    ->subject('Confirmación de Suscripción - ' . $empresa->nombre);
            });

            Log::channel('payments_success')->info('Correo de confirmación de suscripción enviado', [
                'email' => $emailDestino
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error al enviar correo de confirmación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    private function enviarNotificacionPagoAdmin($user, $suscripcion, $empresa, $ordenPago, $esNuevaSuscripcion)
    {
        $adminEmails = [
            'jose.e@smartpyme.sv',
            'jennifer.d@smartpyme.sv',
            'alejandro.a@smartpyme.sv',
        ];

        // $fromAddress = env('MAIL_FROM_ADDRESS');
        $fromAddress = 'noreply@smartpyme.sv';

        try {
            foreach ($adminEmails as $adminEmail) {
                Mail::send('mails.admin-pago-notificacion', [
                    'usuario' => $user,
                    'suscripcion' => $suscripcion,
                    'empresa' => $empresa,
                    'ordenPago' => $ordenPago,
                    'esNuevaSuscripcion' => $esNuevaSuscripcion
                ], function ($m) use ($adminEmail, $empresa, $fromAddress, $ordenPago, $esNuevaSuscripcion) {
                    $tipo = $esNuevaSuscripcion ? 'Nueva Suscripción' : 'Renovación';
                    $m->from($fromAddress, 'SmartPyme Sistema')
                        ->to($adminEmail)
                        ->subject("[$tipo] Pago de {$empresa->nombre} - \${$ordenPago->monto}");
                });
            }

            Log::channel('payments_success')->info('Notificación de pago enviada a administradores', [
                'empresa' => $empresa->nombre,
                'monto' => $ordenPago->monto,
                'es_nueva' => $esNuevaSuscripcion
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error al enviar notificación de pago a administradores', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Determina el tipo de plan basándose en la duración en días
     * 
     * @param int $duracionDias Duración del plan en días
     * @return string Tipo de plan (Mensual, Trimestral, Anual)
     */
    private function determinarTipoPlanPorDuracion($duracionDias)
    {
        if ($duracionDias == 30 || $duracionDias == 31) {
            return 'Mensual';
        } elseif ($duracionDias == 90) {
            return 'Trimestral';
        } elseif ($duracionDias == 365 || $duracionDias == 366) {
            return 'Anual';
        }
        
        // Por defecto, mensual
        return 'Mensual';
    }

    /**
     * Calcula la fecha del próximo pago según el tipo de plan y la suscripción existente
     * 
     * @param string $tipoPlan Tipo de plan (Mensual, Trimestral, Anual)
     * @param Suscripcion|null $suscripcionExistente Suscripción existente si hay
     * @return Carbon Fecha del próximo pago
     */
    private function calcularFechaProximoPagoWebhook($tipoPlan, $suscripcionExistente = null)
    {
        // Si hay suscripción existente y aún no ha vencido, extender desde la fecha actual
        if ($suscripcionExistente && $suscripcionExistente->fecha_proximo_pago && $suscripcionExistente->fecha_proximo_pago->isFuture()) {
            $fechaBase = $suscripcionExistente->fecha_proximo_pago;
            
            Log::channel('payments_success')->info('Renovación anticipada, extendiendo desde la fecha de vencimiento actual', [
                'tipo_plan' => $tipoPlan,
                'fecha_vencimiento_actual' => $fechaBase->format('Y-m-d'),
            ]);
        } else {
            // Si no hay suscripción o ya venció, calcular desde hoy
            $fechaBase = now();
            
            Log::channel('payments_success')->info('Nueva suscripción o renovación desde hoy', [
                'tipo_plan' => $tipoPlan,
                'fecha_actual' => $fechaBase->format('Y-m-d'),
            ]);
        }

        // Calcular según el tipo de plan
        switch ($tipoPlan) {
            case 'Trimestral':
                $fechaProximoPago = $fechaBase->copy()->addMonths(3);
                break;
            case 'Anual':
                $fechaProximoPago = $fechaBase->copy()->addMonths(12);
                break;
            case 'Mensual':
            default:
                $fechaProximoPago = $fechaBase->copy()->addMonth();
                break;
        }

        Log::channel('payments_success')->info('Fecha de próximo pago calculada', [
            'tipo_plan' => $tipoPlan,
            'fecha_proximo_pago' => $fechaProximoPago->format('Y-m-d')
        ]);

        return $fechaProximoPago;
    }
}
