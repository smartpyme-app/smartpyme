<?php

namespace App\Http\Controllers\n1co;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateways\N1coGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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

            $token = $request->input('token');
            $paymentData = [
                'customer' => [
                    'name' => $request->input('customer.name'),
                    'email' => $request->input('customer.email'),
                    'phoneNumber' => $request->input('customer.phoneNumber')
                ],
                'card' => [
                    'number' => preg_replace('/\s+/', '', $request->input('card.number')),
                    'expirationMonth' => $request->input('card.expirationMonth'),
                    'expirationYear' => $request->input('card.expirationYear'),
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
