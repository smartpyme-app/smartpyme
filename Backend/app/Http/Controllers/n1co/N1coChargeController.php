<?php

namespace App\Http\Controllers\n1co;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\MetodoPago;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\PaymentGateways\N1coGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\N1co\CreatePaymentMethodRequest;
use App\Http\Requests\N1co\UpdateMethodPaymentRequest;
use App\Http\Requests\N1co\ProcessChargeReadyRequest;
use App\Http\Requests\N1co\ProcessChargeRequest;

class N1coChargeController extends Controller
{
    protected $n1coGateway;

    public function __construct(N1coGateway $n1coGateway)
    {
        $this->n1coGateway = $n1coGateway;
    }

    public function getToken()
    {
        $result = $this->n1coGateway->getToken();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener token',
                'error' => $result['error']
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ]);
    }

    public function createPaymentMethod(CreatePaymentMethodRequest $request)
    {
        try {

            $customerId = $request->input('customer.id');

            // Verificar si se fuerza un nuevo método de pago (desde paywall)
            $forceNewPaymentMethod = $request->input('forceNewPaymentMethod', false);

            // Solo buscar método existente si NO se fuerza nuevo método
            $metodoPago = null;
            if (!$forceNewPaymentMethod) {
                $metodoPago = MetodoPago::where('id_usuario', $customerId)
                    ->where('esta_activo', true)
                    ->where('es_predeterminado', true)
                    ->first();
            }

            // Usar método existente solo en casos específicos (pago inicial, reintentos)
            $useExistingMethod = !$forceNewPaymentMethod &&
                $metodoPago &&
                $request->input('updatePaymentMethod') == false &&
                $request->input('showPaymentForm') == false;

            if ($useExistingMethod) {
                Log::channel('payments_success')->info('Usando método de pago existente', [
                    'metodo_pago_id' => $metodoPago->id,
                    'customer_id' => $customerId
                ]);

                // Verificar si hay orden fallida para reintento
                $ordenPago = OrdenPago::where('id_usuario', $customerId)
                    ->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA'))
                    ->first();

                if ($ordenPago) {
                    return $this->handleFailedOrderRetry($ordenPago, $metodoPago, $request);
                } else {
                    return $this->createNewOrderWithExistingMethod($metodoPago, $request);
                }
            } else {
                // CREAR NUEVO MÉTODO DE PAGO
                Log::channel('payments_success')->info('Creando nuevo método de pago', [
                    'force_new' => $forceNewPaymentMethod,
                    'customer_id' => $customerId
                ]);

                return $this->createNewPaymentMethodAndCharge($request);
            }
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error en createPaymentMethod controller:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function createNewPaymentMethodAndCharge(Request $request)
    {
        $user = User::find($request->input('customer.id'));
        $empresa = Empresa::find($user->id_empresa);
        $telefono = $request->input('customer.phoneNumber') ? $request->input('customer.phoneNumber') : $empresa->telefono;
        $paymentData = [
            'customer' => [
                'id' => $request->input('customer.id'),
                'name' => $request->input('customer.name'),
                'email' => $request->input('customer.email'),
                'phoneNumber' => $telefono
            ],
            'card' => [
                'number' => preg_replace('/\s+/', '', $request->input('card.number')),
                'expirationMonth' => $request->input('card.expirationMonth'),
                'expirationYear' => "20" . $request->input('card.expirationYear'),
                'cvv' => $request->input('card.cvv'),
                'cardHolder' => $request->input('card.cardHolder')
            ]
        ];

        $result = $this->n1coGateway->createPaymentMethod($paymentData);

        if (!$result['success']) {
            Log::channel('payments_error')->error('Error al crear método de pago', [
                'error' => $result['error']
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear método de pago',
                'error' => $result['error']
            ], 500);
        }

        // Guardar o actualizar método de pago local
        $paymentMethodExist = MetodoPago::where('id_usuario', $request->input('customer.id'))
            ->where('ultimos_cuatro', substr($request->input('card.number'), -4))
            ->where('id_tarjeta', $result['data']['id'])
            ->first();

        if ($paymentMethodExist) {
            $paymentMethodExist->update([
                'es_predeterminado' => true,
                'esta_activo' => true
            ]);
        } else {
            MetodoPago::create([
                'id_usuario' => $request->input('customer.id'),
                'id_tarjeta' => $result['data']['id'],
                'marca_tarjeta' => $result['data']['bin']['brand'],
                'ultimos_cuatro' => substr($request->input('card.number'), -4),
                'titular_tarjeta' => $request->input('card.cardHolder'),
                'nombre_emisor' => $result['data']['bin']['issuerName'],
                'codigo_pais' => $request->input('billingInfo.countryCode'),
                'codigo_estado' => $request->input('billingInfo.stateCode'),
                'codigo_postal' => $request->input('billingInfo.zipCode'),
                'es_predeterminado' => true,
                'esta_activo' => true
            ]);
        }

        // Desactivar métodos anteriores si se está actualizando
        if ($request->input('updatePaymentMethod') == true || $request->input('forceNewPaymentMethod') == true) {
            MetodoPago::where('id_usuario', $request->input('customer.id'))
                ->where('esta_activo', true)
                ->where('id_tarjeta', '!=', $result['data']['id'])
                ->update([
                    'es_predeterminado' => false,
                    'esta_activo' => false
                ]);
        }

        // Crear orden y procesar cargo
        return $this->createOrderAndCharge($request, $result['data']['id']);
    }

    private function createOrderAndCharge(Request $request, string $cardId)
    {
        return DB::transaction(function () use ($request, $cardId) {
            $plan = Plan::find($request->input('plan.id_plan'));
            $user = User::find($request->input('customer.id'));
            $empresa = Empresa::where('id', $user->id_empresa)->first();
            $suscripcion = Suscripcion::where('empresa_id', $empresa->id)
                ->where('plan_id', $plan->id)
                ->first();

            $monto = $suscripcion ? $suscripcion->monto : $plan->precio;

            $order = OrdenPago::create([
                'id_usuario' => $request->input('customer.id'),
                'id_orden' => 'ORD-' . time() . '-' . Str::random(8),
                'id_orden_n1co' => null,
                'id_autorizacion_3ds' => null,
                'autorizacion_url' => null,
                'id_plan' => $plan->id,
                'nombre_cliente' => $request->input('customer.name'),
                'email_cliente' => $request->input('customer.email'),
                'telefono_cliente' => $request->input('customer.phoneNumber'),
                'plan' => $plan->nombre,
                'monto' => $monto,
                'estado' => 'pendiente',
            ]);

            $chargeData = [
                'customer' => [
                    'name' => $request->input('customer.name'),
                    'email' => $request->input('customer.email'),
                    'phoneNumber' => $request->input('customer.phoneNumber')
                ],
                'cardId' => $cardId,
                'order' => [
                    'id' => $order->id_orden,
                    'lineItems' => [
                        [
                            'product' => [
                                'name' => $plan->nombre,
                                'price' => $suscripcion->monto
                            ],
                            'quantity' => 1
                        ]
                    ],
                    'description' => $plan->descripcion,
                    'name' => $plan->nombre
                ],
                'billingInfo' => [
                    'countryCode' => $request->input('billingInfo.countryCode'),
                    'stateCode' => $request->input('billingInfo.stateCode'),
                    'zipCode' => $request->input('billingInfo.zipCode')
                ]
            ];

            $chargeResult = $this->n1coGateway->createCharge($chargeData);

            Log::channel('payments_success')->info('Resultado de la creación del cargo', [
                'charge_result' => $chargeResult
            ]);

            if (!$chargeResult['success']) {
                Log::channel('payments_error')->error('Error al crear cargo', [
                    'message' => $chargeResult['error']
                ]);
                // Si falla el cargo, se hace rollback automáticamente
                throw new \Exception('Error al crear cargo: ' . $chargeResult['error']);
            }

            if ($chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
                $authenticationId = $chargeResult['data']['authentication']['id'];
                $authenticationUrl = $chargeResult['data']['authentication']['url'];

            $order->updateStatusAuthentication3DS(
                $authenticationId,
                $authenticationUrl,
                config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE')
            );

                return response()->json([
                    'success' => true,
                    'requires_3ds' => true,
                    'authentication_url' => $authenticationUrl,
                    'authentication_id' => $authenticationId,
                    'order_id' => $order->id_orden
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Método de pago creado exitosamente',
                'data' => $chargeResult['data']
            ]);
        });
    }

    private function handleFailedOrderRetry($ordenPago, $metodoPago, $request)
    {
        // Lógica para reintento de orden fallida (ya existe en tu código)
        $chargeData = [
            'customer' => [
                'name' => $ordenPago->nombre_cliente,
                'email' => $ordenPago->email_cliente,
                'phoneNumber' => $ordenPago->telefono_cliente
            ],
            'cardId' => $metodoPago->id_tarjeta,
            'order' => [
                'id' => $ordenPago->id_orden,
                'lineItems' => [
                    [
                        'product' => [
                            'name' => $ordenPago->plan,
                            'price' => floatval($ordenPago->monto)
                        ],
                        'quantity' => 1
                    ]
                ],
                'description' => 'Reintento de pago - ' . $ordenPago->plan,
                'name' => $ordenPago->plan
            ],
            'billingInfo' => [
                'countryCode' => $metodoPago->codigo_pais,
                'stateCode' => $request->input('billingInfo.stateCode'),
                'zipCode' => $request->input('billingInfo.zipCode')
            ]
        ];

        $chargeResult = $this->n1coGateway->createCharge($chargeData);

        if ($chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
            $authenticationId = $chargeResult['data']['authentication']['id'];
            $authenticationUrl = $chargeResult['data']['authentication']['url'];

            $ordenPago->updateStatusAuthentication3DS(
                $authenticationId,
                $authenticationUrl,
                config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE')
            );

            return response()->json([
                'success' => true,
                'requires_3ds' => true,
                'authentication_url' => $authenticationUrl,
                'authentication_id' => $authenticationId,
                'order_id' => $ordenPago->id_orden
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pago procesado exitosamente',
            'data' => $chargeResult['data']
        ]);
    }

    private function createNewOrderWithExistingMethod($metodoPago, $request)
    {
        return DB::transaction(function () use ($metodoPago, $request) {
            // Crear nueva orden con método existente (para pago inicial)
            $plan = Plan::find($request->input('plan.id_plan'));
            $user = User::find($request->input('customer.id'));
            $empresa = Empresa::where('id', $user->id_empresa)->first();
            $suscripcion = Suscripcion::where('empresa_id', $empresa->id)->first();
            $monto = $suscripcion ? $suscripcion->monto : $plan->precio;

            $ordenPago = OrdenPago::create([
                'id_usuario' => $request->input('customer.id'),
                'id_orden' => 'ORD-' . time() . '-' . Str::random(8),
                'id_orden_n1co' => null,
                'id_autorizacion_3ds' => null,
                'autorizacion_url' => null,
                'id_plan' => $plan->id,
                'nombre_cliente' => $request->input('customer.name'),
                'email_cliente' => $request->input('customer.email'),
                'telefono_cliente' => $request->input('customer.phoneNumber'),
                'plan' => $plan->nombre,
                'monto' => $monto,
                'estado' => 'pendiente',
            ]);

            $chargeData = [
                'customer' => [
                    'name' => $request->input('customer.name'),
                    'email' => $request->input('customer.email'),
                    'phoneNumber' => $request->input('customer.phoneNumber')
                ],
                'cardId' => $metodoPago->id_tarjeta,
                'order' => [
                    'id' => $ordenPago->id_orden,
                    'lineItems' => [
                        [
                            'product' => [
                                'name' => $plan->nombre,
                                'price' => $suscripcion->monto
                            ],
                            'quantity' => 1
                        ]
                    ],
                    'description' => $plan->descripcion,
                    'name' => $plan->nombre
                ],
                'billingInfo' => [
                    'countryCode' => $metodoPago->codigo_pais,
                    'stateCode' => $request->input('billingInfo.stateCode'),
                    'zipCode' => $request->input('billingInfo.zipCode')
                ]
            ];

            $chargeResult = $this->n1coGateway->createCharge($chargeData);

            if (!$chargeResult['success']) {
                Log::channel('payments_error')->error('Error al crear cargo con método existente', [
                    'error' => $chargeResult['error']
                ]);
                // Si falla el cargo, se hace rollback automáticamente
                throw new \Exception('Error al crear cargo: ' . $chargeResult['error']);
            }

            if ($chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
                $authenticationId = $chargeResult['data']['authentication']['id'];
                $authenticationUrl = $chargeResult['data']['authentication']['url'];

                $ordenPago->updateStatusAuthentication3DS(
                    $authenticationId,
                    $authenticationUrl,
                    config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE')
                );

                return response()->json([
                    'success' => true,
                    'requires_3ds' => true,
                    'authentication_url' => $authenticationUrl,
                    'authentication_id' => $authenticationId,
                    'order_id' => $ordenPago->id_orden
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cargo creado exitosamente',
                'data' => $chargeResult['data']
            ]);
        });
    }

    public function updateMethodPayment(UpdateMethodPaymentRequest $request)
    {
        try {

            // Primero crear el nuevo método de pago
            $paymentData = [
                'customer' => [
                    'id' => $request->input('customer.id'),
                    'name' => $request->input('customer.name'),
                    'email' => $request->input('customer.email'),
                    'phoneNumber' => $request->input('customer.phoneNumber')
                ],
                'card' => [
                    'number' => preg_replace('/\s+/', '', $request->input('card.number')),
                    'expirationMonth' => $request->input('card.expirationMonth'),
                    'expirationYear' => "20" . $request->input('card.expirationYear'),
                    'cvv' => $request->input('card.cvv'),
                    'cardHolder' => $request->input('card.cardHolder')
                ]
            ];

            $result = $this->n1coGateway->createPaymentMethod($paymentData);

            if (!$result['success']) {
                Log::channel('payments_error')->error('Error al crear nuevo método de pago', [
                    'error' => $result['error']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear nuevo método de pago',
                    'error' => $result['error']
                ], 500);
            }

            // Buscar y actualizar el método de pago existente
            $customerId = $request->input('customer.id');
            $metodoPagoAnterior = MetodoPago::where('id_usuario', $customerId)
                ->where('esta_activo', true)
                ->where('es_predeterminado', true)
                ->first();

            // Desactivar método de pago anterior si existe
            if ($metodoPagoAnterior) {
                $metodoPagoAnterior->update([
                    'esta_activo' => false,
                    'es_predeterminado' => false
                ]);
            }

            // Crear nuevo registro de método de pago
            $metodoPago = MetodoPago::create([
                'id_usuario' => $customerId,
                'id_tarjeta' => $result['data']['id'],
                'marca_tarjeta' => $result['data']['bin']['brand'],
                'ultimos_cuatro' => substr($request->input('card.number'), -4),
                'titular_tarjeta' => $request->input('card.cardHolder'),
                'nombre_emisor' => $result['data']['bin']['issuerName'],
                'codigo_pais' => $result['data']['bin']['countryCode'],
                'codigo_estado' => $request->input('billingInfo.stateCode'),
                'codigo_postal' => $request->input('billingInfo.zipCode'),
                'es_predeterminado' => true,
                'esta_activo' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Método de pago actualizado exitosamente',
                'data' => [
                    'cardId' => $result['data']['id'],
                    'brand' => $result['data']['bin']['brand'],
                    'lastDigits' => substr($request->input('card.number'), -4)
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error en updateMethodPayment:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function processChargeReady(ProcessChargeReadyRequest $request)
    {
        try {

            return DB::transaction(function () use ($request) {
                // Obtener el método de pago
                $metodoPago = MetodoPago::where('id', $request->metodo_pago_id)
                    ->where('id_usuario', $request->id_usuario)
                    ->where('esta_activo', true)
                    ->first();

                if (!$metodoPago) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Método de pago no encontrado o no está activo'
                    ], 404);
                }

                // Obtener el plan
                $plan = Plan::find($request->plan_id);

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan no encontrado'
                ], 404);
            }

            // Obtener la suscripción
            $usuario = User::find($request->id_usuario);
            $suscripcion = Suscripcion::where('empresa_id', $usuario->id_empresa)
                ->where('plan_id', $request->plan_id)
                ->first();

            // Determinar el monto: usar el de la suscripción si existe, sino el del plan, o el del request
            $monto = $request->input('amount');
            if (!$monto && $suscripcion) {
                $monto = $suscripcion->monto;
            }
            if (!$monto) {
                $monto = $plan->precio;
            }

            // Crear orden de pago
            $ordenPago = OrdenPago::create([
                'id_usuario' => $request->id_usuario,
                'id_orden' => 'ORD-' . time() . '-' . Str::random(8),
                'id_orden_n1co' => null,
                'id_autorizacion_3ds' => null,
                'autorizacion_url' => null,
                'id_plan' => $plan->id,
                'nombre_cliente' => $request->customer_name,
                'email_cliente' => $request->customer_email,
                'telefono_cliente' => $request->customer_phone,
                'plan' => $plan->nombre,
                'monto' => $monto,
                'estado' => 'pendiente',
                'divisa' => 'USD'
            ]);

                // Preparar datos para el cargo
                $chargeData = [
                    'customer' => [
                        'name' => $request->customer_name,
                        'email' => $request->customer_email,
                        'phoneNumber' => $request->customer_phone
                    ],
                    'cardId' => $metodoPago->id_tarjeta,
                    'order' => [
                        'id' => $ordenPago->id_orden,
                        'lineItems' => [
                            [
                                'product' => [
                                    'name' => $plan->nombre,
                                    'price' => $ordenPago->monto
                                ],
                                'quantity' => 1
                            ]
                        ],
                        'description' => $plan->descripcion ?? "Pago de suscripción del plan {$plan->nombre}",
                        'name' => $plan->nombre
                    ],
                    'billingInfo' => [
                        'countryCode' => $metodoPago->codigo_pais,
                        'stateCode' => $metodoPago->codigo_estado,
                        'zipCode' => $metodoPago->codigo_postal
                    ]
                ];

                // Realizar el cargo
                $chargeResult = $this->n1coGateway->createCharge($chargeData);

                Log::channel('payments_success')->info('Resultado de la creación del cargo', [
                    'charge_result' => $chargeResult
                ]);

                // Si requiere autenticación 3DS
                if (isset($chargeResult['data']['status']) && $chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
                    $authenticationId = $chargeResult['data']['authentication']['id'];
                    $authenticationUrl = $chargeResult['data']['authentication']['url'];

                    $ordenPago->updateStatusAuthentication3DS(
                        $authenticationId,
                        $authenticationUrl,
                        config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE')
                    );

                    return response()->json([
                        'success' => true,
                        'requires_3ds' => true,
                        'authentication_url' => $authenticationUrl,
                        'authentication_id' => $authenticationId,
                        'order_id' => $ordenPago->id_orden
                    ]);
                }

                // Si el cargo fue exitoso
                if ($chargeResult['success']) {
                    // Actualizar la orden de pago
                    $ordenPago->update([
                        'id_orden_n1co' => $chargeResult['data']['id'],
                        'estado' => 'completado',
                        'fecha_transaccion' => now()
                    ]);

                    // Crear o actualizar suscripción
                    $empresa = Empresa::find($request->empresa_id);
                    if ($empresa) {
                        $suscripcion = Suscripcion::updateOrCreate(
                            ['empresa_id' => $empresa->id, 'estado' => 'activo'],
                            [
                                'plan_id' => $plan->id,
                                'usuario_id' => $request->id_usuario,
                                'tipo_plan' => 'mensual', // O el valor que corresponda
                                'monto' => $ordenPago->monto,
                                'fecha_inicio' => now(),
                                'fecha_ultimo_pago' => now(),
                                'fecha_proximo_pago' => now()->addMonth(),
                                'estado' => 'activo'
                            ]
                        );
                    }

                return response()->json([
                    'success' => true,
                    'message' => 'Pago procesado exitosamente',
                    'data' => $chargeResult['data']
                ]);
            } else {
                // Si hubo un error en el cargo
                $ordenPago->update([
                    'estado' => 'fallido'
                ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Error al procesar el pago',
                        'error' => $chargeResult['error'] ?? 'Error desconocido'
                    ], 500);
                }
            });

        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error en processChargeReady:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function processCharge(ProcessChargeRequest $request)
    {
        try {

            $result = $this->n1coGateway->processCharge(
                $request->all(),
                $request->input('card_id'),
                $request->input('token'),
                $request->input('authentication_id')
            );

            if (!$result['success']) {
                Log::channel('payments_error')->error('Error al procesar el cargo processCharge', [
                    'error' => $result['error']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el cargo processCharge',
                    'error' => $result['error']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error processing charge', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el cargo processCharge2',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function processCharge3DS(Request $request)
    {
        try {
            $result = $this->n1coGateway->processCharge3DS($request->all());

            Log::channel('payments_success')->info('Resultado de la creación del cargo', [
                'charge_result' => $result
            ]);

            if (!$result['success']) {
                Log::channel('payments_error')->error('Error al procesar el cargo processCharge3DS', [
                    'error' => $result['error']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el cargo processCharge3DS',
                    'error' => $result['error']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'amount' => $result['amount'],
                'currency' => $result['currency']
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error processing charge 3DS', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el cargo processCharge3DS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkAuthenticationStatus(Request $request)
    {
        $data = $request->all();
        $result = $this->n1coGateway->checkAuthenticationStatus($data);

        Log::channel('payments_success')->info('Resultado de la verificación de autenticación', [
            'result' => $result
        ]);

        $ordenPago = $result['data'];
        return response()->json([
            'success' => true,
            'estado' => $ordenPago->estado
        ]);
    }

    public function getExistingPaymentMethod($userId)
    {
        try {
            $metodoPago = MetodoPago::where('id_usuario', $userId)
                ->where('esta_activo', true)
                ->where('es_predeterminado', true)
                ->first();

            if (!$metodoPago) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró método de pago activo'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $metodoPago->id,
                    'id_tarjeta' => $metodoPago->id_tarjeta,
                    'marca_tarjeta' => $metodoPago->marca_tarjeta,
                    'ultimos_cuatro' => $metodoPago->ultimos_cuatro,
                    'titular_tarjeta' => $metodoPago->titular_tarjeta,
                    'nombre_emisor' => $metodoPago->nombre_emisor,
                    'codigo_pais' => $metodoPago->codigo_pais,
                    'codigo_estado' => $metodoPago->codigo_estado,
                    'codigo_postal' => $metodoPago->codigo_postal,
                    'es_predeterminado' => $metodoPago->es_predeterminado,
                    'esta_activo' => $metodoPago->esta_activo
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error al obtener método de pago:', [
                'message' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changeStatusAuthentication3DS(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'authentication_id' => 'required|string',
                'order_id' => 'required|string',
                'status' => 'nullable|string|in:success,failed' // Estado opcional: success o failed
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buscar la orden primero por authentication_id
            $ordenPago = OrdenPago::where('id_autorizacion_3ds', $request->authentication_id)
                ->first();

            // Si no se encuentra por authentication_id, intentar buscar por order_id
            if (!$ordenPago) {
                $orderIdToSearch = $request->order_id;
                $ordPosition = strpos($orderIdToSearch, 'ORD-');

                if ($ordPosition !== false) {
                    $orderIdToSearch = substr($orderIdToSearch, $ordPosition);
                }

                $ordenPago = OrdenPago::where('id_orden', $orderIdToSearch)
                    ->first();
            }

            if (!$ordenPago) {
                Log::channel('payments_error')->error('Orden no encontrada para actualizar estado', [
                    'authentication_id' => $request->authentication_id,
                    'order_id' => $request->order_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada'
                ], 404);
            }

            // Determinar el estado según el parámetro recibido
            $status = $request->input('status', 'success'); // Por defecto success
            $estado = $status === 'failed'
                ? config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA')
                : config('constants.ESTADO_ORDEN_AUTENTICACION_EXITOSA');

            // Actualizar el estado de la orden
            $ordenPago->update([
                'estado' => $estado
            ]);

            $logChannel = $status === 'failed' ? 'payments_error' : 'payments_success';
            Log::channel($logChannel)->info('Estado de orden actualizado', [
                'orden_id' => $ordenPago->id,
                'id_orden' => $ordenPago->id_orden,
                'authentication_id' => $request->authentication_id,
                'estado' => $estado
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado de orden actualizado exitosamente',
                'data' => [
                    'id_orden' => $ordenPago->id_orden,
                    'estado' => $ordenPago->estado
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('payments_error')->error('Error al actualizar estado de autenticación:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado de la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
