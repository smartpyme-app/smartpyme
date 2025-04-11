<?php

namespace App\Services\PaymentGateways;

use App\Models\MetodoPago;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N1coGateway extends BasePaymentGateway
{
    protected function initialize(): void
    {
        $this->baseUrl = $this->isSandbox
            ? config('services.nico.sandbox_url')
            : config('services.nico.base_url');
    }

    public function getToken(): array
    {
        try {
            Log::info('Obteniendo token N1co', [
                'client_id' => config('services.nico.client_id'),
                'client_secret' => config('services.nico.client_secret'),
                'base_url' => $this->baseUrl
            ]);
            $response = Http::post($this->baseUrl . '/Token', [
                'clientId' => config('services.nico.client_id'),
                'clientSecret' => config('services.nico.client_secret')
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            Log::error('Error obteniendo token N1co', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => 'Error al obtener token'
            ];
        } catch (\Exception $e) {
            Log::error('Exception obteniendo token N1co', [
                'message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function getHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    public function createPaymentMethod(array $data): array
    {
        Log::info('Creando método de pago', [
            'customer_email' => $data['customer']['email'] ?? null
        ]);

        try {

            $token = $this->getToken()['data']['accessToken'];
            Log::info('Token obtenido', [
                'token' => $token
            ]);
            $response = Http::withHeaders($this->getHeaders($token))
                ->post($this->baseUrl . '/PaymentMethods', [
                    'customer' => [
                        'id' => $data['customer']['id'],
                        'name' => $data['customer']['name'],
                        'email' => $data['customer']['email'],
                        'phoneNumber' => $data['customer']['phoneNumber']
                    ],
                    'card' => [
                        'number' => $data['card']['number'],
                        'expirationMonth' => $data['card']['expirationMonth'],
                        'expirationYear' => $data['card']['expirationYear'],
                        'cvv' => $data['card']['cvv'],
                        'cardHolder' => $data['card']['cardHolder']
                    ]
                ]);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Método de pago creado exitosamente', [
                    'card_id' => $result['id'] ?? null
                ]);
                return [
                    'success' => true,
                    'data' => $result
                ];
            }

            Log::error('Error creando método de pago', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Error creando método de pago'
            ];
        } catch (\Exception $e) {
            Log::error('Error en createPaymentMethod GATEWAY:', [
                'message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createCharge(array $chargeData): array
    {
        try {

            Log::info('Iniciando cargo', [
                'amount' => $chargeData['order']['amount'] ?? null,
                'customer_email' => $chargeData['customer']['email'] ?? null,
                'card_id' => $chargeData['cardId'] ?? null
            ]);

            $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
                ->post($this->baseUrl . '/Charges', $chargeData);

            Log::info('Respuesta de la creación del cargo', [
                'response' => $response->json()
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'data' => $result
                ];
            }

            Log::error('Error creando cargo', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Error al crear el cargo'
            ];
        } catch (\Exception $e) {
            Log::error('Error en createCharge:', [
                'message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function createCharge3DS(array $chargeData): array
    {
        try {

            Log::info('Iniciando cargo', [
                'amount' => $chargeData['order']['amount'] ?? null,
                'customer_email' => $chargeData['customer']['email'] ?? null,
                'card_id' => $chargeData['cardId'] ?? null
            ]);

            $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
                ->post($this->baseUrl . '/Charges', $chargeData);

            Log::info('Respuesta de la creación del cargo', [
                'response' => $response->json()
            ]);

            if ($response->successful()) {

                Log::info('Resultado del cargo', [
                    'result' => $response->json()
                ]);

                $result = $response->json();
                if ($result['status'] === 'SUCCEEDED') {
                    $ordenPago = OrdenPago::where('id_orden', $result['order']['reference'])->first();
                    if ($ordenPago) {
                        $ordenPago->update([
                            'id_orden_n1co' => $result['order']['id'],
                            'estado' => config('constants.ESTADO_ORDEN_PAGO_COMPLETADO'),
                            'codigo_autorizacion' => $result['order']['authorizationCode']
                        ]);
                    }
                } else if ($result['status'] === 'AUTHENTICATION_REQUIRED') {
                    $ordenPago = OrdenPago::where('id_orden', $result['order']['reference'])->first();
                    if ($ordenPago) {
                        $ordenPago->update([
                            'id_autorizacion_3ds' => $result['authentication']['id'],
                            'autorizacion_url' => $result['authentication']['url'],
                            'estado' => config('constants.ESTADO_ORDEN_AUTENTICACION_PENDIENTE')
                        ]);
                    }
                }

                Log::info('Resultado del cargo despues de los ifs', [
                    'result' => $result
                ]);

                return [
                    'success' => true,
                    'data' => $result
                ];

            }

            Log::error('Error creando cargo', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Error al crear el cargo'
            ];
        } catch (\Exception $e) {
            Log::error('Error en createCharge:', [
                'message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function processCharge3DS(array $data): array
    {
        try {
            if (!isset($data['order_id']) || !isset($data['authentication_id'])) {
                return [
                    'success' => false,
                    'error' => 'Datos de orden incompletos'
                ];
            }
            

            // Buscar la orden y sus datos relacionados
            $ordenPago = OrdenPago::where('id_orden', $data['order_id'])
                // ->where('estado', config('constants.ESTADO_ORDEN_AUTENTICACION_EXITOSA'))
                ->first();

            if (!$ordenPago) {
                Log::error('Orden no encontrada o no está autenticada', [
                    'order_id' => $data['order_id']
                ]);
                return [
                    'success' => false,
                    'error' => 'Orden no encontrada o no está autenticada'
                ];
            }

            // Obtener el método de pago asociado
            $methodPayment = MetodoPago::where('id_usuario', $ordenPago->id_usuario)
                ->where('esta_activo', true)
                ->first();

            if (!$methodPayment) {
                return [
                    'success' => false,
                    'error' => 'Método de pago no encontrado'
                ];
            }

            $plan = Plan::find($ordenPago->id_plan);
            // $suscripcion = Suscripcion::where('id_usuario', $ordenPago->id_usuario)->where('id_plan', $ordenPago->id_plan)->first();

            $chargeData = [
                'customer' => [
                    'name' => $ordenPago->nombre_cliente,
                    'email' => $ordenPago->email_cliente,
                    'phoneNumber' => $ordenPago->telefono_cliente
                ],
                'cardId' => $methodPayment->id_tarjeta,
                'authenticationId' => $data['authentication_id'],
                'order' => [
                    'id' => $ordenPago->id_orden,
                    'lineItems' => [
                        [
                            'sku' => $plan->sku,
                            'product' => [
                                'name' => $ordenPago->plan,
                                'price' => floatval($ordenPago->monto)
                            ],
                            'quantity' => 1
                        ]
                    ],
                    'description' => 'Pago de suscripción ' . $ordenPago->plan,
                    'name' => 'Orden #' . $ordenPago->id_orden
                ],
                'billingInfo' => [
                    'countryCode' => $methodPayment->codigo_pais,
                    'stateCode' => $methodPayment->codigo_estado ?? 'SS',
                    'zipCode' => $methodPayment->codigo_postal ?? '1101'
                ]
            ];

            Log::info('Iniciando cargo con autenticación 3DS', [
                'order_id' => $ordenPago->id_orden,
                'authentication_id' => $data['authentication_id'],
                'charge_data' => $chargeData
            ]);

            $charge = $this->createCharge3DS($chargeData);

            if ($charge['success']) {
                return [
                    'success' => true,
                    'message' => $charge['data']['message'],
                    'amount' => $charge['data']['order']['amount'],
                    'currency' => $charge['data']['order']['currency'],
                    // 'authorization_code' => $charge['data']['authorizationCode']
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error en processCharge3DS:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function processCharge(array $orderData, string $cardId, string $token, ?string $authenticationId = null): array
    {
        $chargeData = [
            'customer' => [
                'name' => $orderData['customer_name'],
                'email' => $orderData['customer_email'],
                'phoneNumber' => $orderData['customer_phone']
            ],
            'order' => [
                'amount' => $orderData['amount'],
                'description' => $orderData['description'] ?? 'Cargo por compra',
                'name' => $orderData['order_name'] ?? 'Orden de compra'
            ],
            'cardId' => $cardId
        ];

        if ($authenticationId) {
            $chargeData['authenticationId'] = $authenticationId;
        }

        if (!empty($orderData['metadata'])) {
            $chargeData['metadata'] = $orderData['metadata'];
        }

        return $this->createCharge($chargeData, $token);
    }

    public function checkAuthenticationStatus(array $data): array
    {
        Log::info('Verificando estado de autenticación', [
            'data' => $data
        ]);

       $authenticationId = $data['authentication_id'];

       Log::info('Authentication ID', [
        'authentication_id' => $authenticationId
       ]);

       $ordenPago = OrdenPago::where('id_autorizacion_3ds', $authenticationId)->first();

       Log::info('Orden de pago encontrada', [
        'orden_pago' => $ordenPago
       ]);

       if ($ordenPago) {
        return ['success' => true, 'data' => $ordenPago];
       }
       return ['success' => false, 'error' => 'Orden no encontrada'];
    }

    //aqui voy

    public function createCustomer(array $customerData): array
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->post($this->baseUrl . '/customers', $customerData);

        return $this->handleResponse($response, 'customer creation');
    }

    public function processPayment(array $paymentData): array
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->post($this->baseUrl . '/payments', $paymentData);

        return $this->handleResponse($response, 'payment processing');
    }

    public function createSubscription(array $subscriptionData): array
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->post($this->baseUrl . '/subscriptions', $subscriptionData);

        return $this->handleResponse($response, 'subscription creation');
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->delete($this->baseUrl . "/subscriptions/{$subscriptionId}");

        return $response->successful();
    }

    public function getCustomer(string $customerId): array
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->get($this->baseUrl . "/customers/{$customerId}");

        return $this->handleResponse($response, 'get customer');
    }

    public function createRefund(array $refundData): array
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->post($this->baseUrl . '/Refunds', $refundData);

        return $this->handleResponse($response, 'refund creation');
    }

    public function updatePaymentMethod(array $updateData): array
    {
        $response = Http::withHeaders($this->getHeaders($this->getToken()['data']['accessToken']))
            ->put($this->baseUrl . '/PaymentMethods', $updateData);

        return $this->handleResponse($response, 'payment method update');
    }

    private function handleResponse(Response $response, string $action): array
    {
        if ($response->successful()) {
            return ['success' => true, 'data' => $response->json()];
        }
        return ['success' => false, 'error' => $response->json()];
    }
}
