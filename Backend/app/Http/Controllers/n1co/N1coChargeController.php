<?php

namespace App\Http\Controllers\n1co;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaymentGateways\N1coGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

    public function createPaymentMethod(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer.id' => 'required|integer',
                'customer.name' => 'required|string',
                'customer.email' => 'required|email',
                'customer.phoneNumber' => 'required|string',
                'card.number' => 'required|string|min:13|max:16',
                'card.expirationMonth' => 'required|string|size:2|in:01,02,03,04,05,06,07,08,09,10,11,12',
                'card.expirationYear' => 'required|string|size:2',
                'card.cvv' => 'required|string|min:3|max:4',
                'card.cardHolder' => 'required|string|min:3'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $customerId = $request->input('customer.id');
            $metodoPago = MetodoPago::where('id_usuario', $customerId)->where('esta_activo', true)->where('es_predeterminado', true)->first();
            if ($metodoPago) {

                Log::info('Método de pago encontrado', [
                    'metodo_pago' => $metodoPago
                ]);

                $ordenPago = OrdenPago::where('id_usuario', $customerId)->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_FALLIDA'))->first();

                if ($ordenPago) {
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
                                    // 'sku' => $request->input('order.lineItems.0.sku'),
                                    'product' => [
                                        'name' => $ordenPago->plan,
                                        'price' => $ordenPago->monto
                                    ],
                                    'quantity' => 1
                                ]
                            ],
                            'description' => $ordenPago->plan,
                            'name' => $ordenPago->plan
                        ],
                        'billingInfo' => [
                            'countryCode' =>  $metodoPago->codigo_pais,
                            'stateCode' => $request->input('billingInfo.stateCode'),
                            'zipCode' => $request->input('billingInfo.zipCode')
                        ]
                    ];

                    Log::info('Datos de la orden de pago', [
                        'charge_data' => $chargeData
                    ]);

                    $chargeResult = $this->n1coGateway->createCharge($chargeData);

                    Log::info('Resultado de la creación del cargo', [
                        'charge_result' => $chargeResult
                    ]);


                    if ($chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
                        $authenticationId = $chargeResult['data']['authentication']['id'];
                        $authenticationUrl = $chargeResult['data']['authentication']['url'];

                        $ordenPago->updateStatusAuthentication3DS($authenticationId, $authenticationUrl, config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE'));


                        Log::info('ID de autenticación 3DS', [
                            'authentication_id' => $authenticationId
                        ]);

                        return response()->json([
                            'success' => true,
                            'requires_3ds' => true,
                            'authentication_url' => $authenticationUrl,
                            'authentication_id' => $authenticationId,
                            'order_id' => $ordenPago->id_orden
                        ]);
                    }
                }
            } else {
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al crear método de pago',
                        'error' => $result['error']
                    ], 500);
                }

                $paymentMethod = MetodoPago::create([
                    'id_usuario' => $request->input('customer.id'),
                    'id_tarjeta' => $result['data']['id'],
                    'marca_tarjeta' => $result['data']['bin']['brand'],
                    'ultimos_cuatro' => substr($request->input('card.number'), -4),
                    'titular_tarjeta' => $request->input('card.cardHolder'),
                    'nombre_emisor' => $result['data']['bin']['issuerName'],
                    'codigo_pais' => $result['data']['bin']['countryCode'],
                    'es_predeterminado' => true,
                    'esta_activo' => true
                ]);
            }

            $plan = Plan::find($request->input('plan.id_plan'));

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
                'monto' => $plan->precio,
                'estado' => 'pendiente',
            ]);

            $chargeData = [
                'customer' => [
                    'name' => $request->input('customer.name'),
                    'email' => $request->input('customer.email'),
                    'phoneNumber' => $request->input('customer.phoneNumber')
                ],
                'cardId' => $result['data']['id'],
                'order' => [
                    'id' => $order->id_orden,
                    'lineItems' => [
                        [
                            // 'sku' => $request->input('order.lineItems.0.sku'),
                            'product' => [
                                'name' => $plan->nombre,
                                'price' => $plan->precio
                            ],
                            'quantity' => 1
                        ]
                    ],
                    'description' => $plan->descripcion,
                    'name' => $plan->nombre
                ],
                'billingInfo' => [
                    'countryCode' =>  $result['data']['bin']['countryCode'],
                    'stateCode' => $request->input('billingInfo.stateCode'),
                    'zipCode' => $request->input('billingInfo.zipCode')
                ]
            ];

            $chargeResult = $this->n1coGateway->createCharge($chargeData);

            Log::info('Resultado de la creación del cargo', [
                'charge_result' => $chargeResult
            ]);

            if (!$chargeResult['success']) {
                Log::error('Error al crear cargo', [
                    'message' => $chargeResult['error']
                ]);
                $paymentMethod->update(['is_active' => false]);
                return response()->json($chargeResult, 500);
            }

            if ($chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
                $authenticationId = $chargeResult['data']['authentication']['id'];
                $authenticationUrl = $chargeResult['data']['authentication']['url'];

                $order->updateStatusAuthentication3DS($authenticationId, $authenticationUrl, config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE'));


                Log::info('ID de autenticación 3DS', [
                    'authentication_id' => $authenticationId
                ]);

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
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Error en createPaymentMethod:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMethodPayment(Request $request)
    {
        try {
            // Validación de los datos de entrada
            $validator = Validator::make($request->all(), [
                'customer.id' => 'required|integer',
                'customer.name' => 'required|string',
                'customer.email' => 'required|email',
                'customer.phoneNumber' => 'required|string',
                'card.number' => 'required|string|min:13|max:16',
                'card.expirationMonth' => 'required|string|size:2|in:01,02,03,04,05,06,07,08,09,10,11,12',
                'card.expirationYear' => 'required|string|size:2',
                'card.cvv' => 'required|string|min:3|max:4',
                'card.cardHolder' => 'required|string|min:3',
                'billingInfo.countryCode' => 'required|string',
                'billingInfo.stateCode' => 'required|string',
                'billingInfo.zipCode' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

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
            Log::error('Error en updateMethodPayment:', [
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

    public function processCharge(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'customer_name' => 'required|string',
                'customer_email' => 'required|email',
                'customer_phone' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'card_id' => 'required|string',
                'authentication_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->n1coGateway->processCharge(
                $request->all(),
                $request->input('card_id'),
                $request->input('token'),
                $request->input('authentication_id')
            );

            if (!$result['success']) {
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
            Log::error('Error processing charge', [
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

            Log::info('Resultado de la creación del cargo', [
                'charge_result' => $result
            ]);

            if (!$result['success']) {
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
            Log::error('Error processing charge 3DS', [
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

        Log::info('Resultado de la verificación de autenticación', [
            'result' => $result
        ]);

        $ordenPago = $result['data'];
        return response()->json([
            'success' => true,
            'estado' => $ordenPago->estado
        ]);
    }
}
