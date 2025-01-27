<?php

namespace App\Http\Controllers\n1co;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\OrdenPago;
use App\Models\Plan;
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

            $plan = Plan::find($request->input('plan.id_plan'));

            $order = OrdenPago::create([
                'id_usuario' => $request->input('customer.id'),
                'id_orden' => 'ORD-' . time() . '-' . Str::random(8),
                'id_orden_n1co' => null,
                'id_plan' => $plan->id,
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

            if (!$chargeResult['success']) {
                Log::error('Error al crear cargo', [
                    'message' => $chargeResult['error']
                ]);
                $paymentMethod->update(['is_active' => false]);
                return response()->json($chargeResult, 500);
            }
    
            if ($chargeResult['data']['status'] === 'AUTHENTICATION_REQUIRED') {
                Log::info('Autenticación requerida', [
                    'authentication_id' => $chargeResult['data']['authentication']['id'],
                    'authentication_url' => $chargeResult['data']['authentication']['url'],
                    'charge_data' => $chargeResult['data']
                ]);
                $order->update([
                    'estado' => 'autenticacion_pendiente',
                    'id_autenticacion_3ds' => $chargeResult['data']['authentication']['id']
                ]);
    
                return response()->json([
                    'success' => true,
                    'requires_3ds' => true,
                    'authentication_url' => $chargeResult['data']['authentication']['url'],
                    'authentication_id' => $chargeResult['data']['authentication']['id'],
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
                    'message' => 'Error al procesar el cargo',
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
                'message' => 'Error al procesar el cargo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
