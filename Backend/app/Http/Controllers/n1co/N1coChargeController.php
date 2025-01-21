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

    public function createPaymentMethod(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer.name' => 'required|string',
                'customer.email' => 'required|email',
                'customer.phoneNumber' => 'required|string',
                'card.number' => 'required|string',
                'card.expirationMonth' => 'required|string',
                'card.expirationYear' => 'required|string',
                'card.cvv' => 'required|string',
                'card.cardHolder' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $paymentMethodData = [
                'customer' => $request->input('customer'),
                'card' => $request->input('card')
            ];

            $result = $this->n1coGateway->createPaymentMethod($paymentMethodData);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error creating payment method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error creating payment method',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function processCharge(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer_name' => 'required|string',
                'customer_email' => 'required|email',
                'customer_phone' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'card_id' => 'required|string',
                'authentication_id' => 'nullable|string',
                'order_name' => 'nullable|string',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $result = $this->n1coGateway->processCharge(
                $request->all(),
                $request->input('card_id'),
                $request->input('authentication_id')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error processing charge', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error processing charge',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
